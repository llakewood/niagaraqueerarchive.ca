<?php
/**
 * Geocoding — bulk-fill EMPTY Google Map `location` fields from the `address`
 * text field (or title + municipality) via the Google Geocoding API. WP-CLI:
 * `wp nqa geocode`.
 *
 * This is the one sanctioned exception to "set `location` via the admin map
 * picker only": it only ever fills records that have NO coordinates yet, so a
 * hand-placed pin is never touched. Results are biased to Ontario, Canada, and
 * every pin should still be reviewed in the admin before publishing (closed
 * venues and region-wide orgs are easily mislocated). Use `--overwrite` only if
 * you deliberately want to re-geocode existing pins.
 *
 * Requires NQA_GOOGLE_MAPS_KEY (0-nqa-runtime-config.php). On production run it
 * with `./scripts/wp-prod nqa geocode`.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Geocode one free-text query, biased to Ontario, Canada.
 *
 * @return array|null  ACF google_map value array, or null on failure.
 */
function nqa_geocode_query_string( string $query ) {
	$api_key = defined( 'NQA_GOOGLE_MAPS_KEY' ) ? NQA_GOOGLE_MAPS_KEY : '';
	if ( ! $api_key ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'address'    => $query,
			'components' => 'country:CA|administrative_area:Ontario',
			'key'        => $api_key,
		),
		'https://maps.googleapis.com/maps/api/geocode/json'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 'OK' !== ( $data['status'] ?? '' ) || empty( $data['results'] ) ) {
		return null;
	}

	$r = $data['results'][0];
	return array(
		'address'  => $r['formatted_address'],
		'lat'      => $r['geometry']['location']['lat'],
		'lng'      => $r['geometry']['location']['lng'],
		'zoom'     => 15,
		'place_id' => $r['place_id'] ?? '',
	);
}

/**
 * Build the geocode query for a record: prefer the `address` field (places),
 * fall back to title + municipality.
 *
 * @return array{query:string,source:string}
 */
function nqa_geocode_build_query( int $id ) : array {
	$title      = get_the_title( $id );
	$muni_terms = wp_get_object_terms( $id, 'municipality', array( 'fields' => 'names' ) );
	$muni       = ( ! empty( $muni_terms ) && ! is_wp_error( $muni_terms ) )
		? $muni_terms[0] . ', Ontario, Canada'
		: 'Niagara Region, Ontario, Canada';

	if ( 'nqa_place' === get_post_type( $id ) ) {
		$address = (string) get_field( 'address', $id );
		if ( trim( $address ) !== '' ) {
			$q = trim( $address );
			if ( stripos( $q, 'ontario' ) === false && stripos( $q, $muni_terms[0] ?? '' ) === false ) {
				$q .= ', ' . $muni;
			}
			return array( 'query' => $q, 'source' => 'address field' );
		}
	}

	return array( 'query' => $title . ', ' . $muni, 'source' => 'title + municipality' );
}

// ── WP-CLI ───────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class NQA_Geocode_CLI {

		/**
		 * Fill empty Google Map `location` fields by geocoding the address /
		 * title of place, org, and event records.
		 *
		 * Records that already have coordinates are skipped (never overwritten)
		 * unless --overwrite is given. Results are biased to Ontario, Canada.
		 * Review every new pin in the admin before publishing.
		 *
		 * ## OPTIONS
		 *
		 * [--type=<type>]
		 * : Limit to one type (nqa_place|nqa_org|nqa_event). Default: all three.
		 *
		 * [--overwrite]
		 * : Re-geocode records that already have coordinates. Off by default so
		 *   hand-placed pins are never disturbed.
		 *
		 * [--dry-run]
		 * : Print the query that would be sent for each record; write nothing.
		 *
		 * ## EXAMPLES
		 *
		 *     wp nqa geocode
		 *     wp nqa geocode --type=nqa_place --dry-run
		 *
		 * @when after_wp_load
		 */
		public function geocode( $args, $assoc ) {
			if ( ! function_exists( 'update_field' ) ) {
				WP_CLI::error( 'ACF is not available.' );
			}

			$allowed   = array( 'nqa_place', 'nqa_org', 'nqa_event' );
			$type      = $assoc['type'] ?? '';
			$overwrite = ! empty( $assoc['overwrite'] );
			$dry       = ! empty( $assoc['dry-run'] );

			// The API key is only needed for a real run; --dry-run works offline.
			if ( ! $dry && ( ! defined( 'NQA_GOOGLE_MAPS_KEY' ) || ! NQA_GOOGLE_MAPS_KEY ) ) {
				WP_CLI::error( 'NQA_GOOGLE_MAPS_KEY is not defined — check 0-nqa-runtime-config.php.' );
			}

			if ( $type && ! in_array( $type, $allowed, true ) ) {
				WP_CLI::error( "Unknown --type: $type (allowed: " . implode( ', ', $allowed ) . ')' );
			}
			$types = $type ? array( $type ) : $allowed;

			$ids = get_posts(
				array(
					'post_type'      => $types,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'post_type',
					'order'          => 'ASC',
				)
			);

			WP_CLI::log( sprintf( 'Scanning %d records (types: %s)%s…', count( $ids ), implode( ', ', $types ), $dry ? ' [dry run]' : '' ) );

			$n = array( 'set' => 0, 'skip' => 0, 'fail' => 0 );

			foreach ( $ids as $id ) {
				$title = nqa_preservation_trim_title( $id );
				$ptype = get_post_type( $id );
				$loc   = get_field( 'location', $id );

				if ( ! $overwrite && ! empty( $loc['lat'] ) && ! empty( $loc['lng'] ) ) {
					WP_CLI::log( sprintf( '  #%d  %-40s  [%s] — has pin, skipped', $id, $title, $ptype ) );
					$n['skip']++;
					continue;
				}

				$q = nqa_geocode_build_query( $id );

				if ( $dry ) {
					WP_CLI::log( sprintf( '  #%d  %-40s  [%s] via %s → "%s"', $id, $title, $ptype, $q['source'], $q['query'] ) );
					$n['set']++;
					continue;
				}

				$result = nqa_geocode_query_string( $q['query'] );
				if ( ! $result ) {
					WP_CLI::warning( sprintf( '#%d %s — no geocode result for "%s"', $id, $title, $q['query'] ) );
					$n['fail']++;
					sleep( 1 );
					continue;
				}

				update_field( 'location', $result, $id );
				WP_CLI::log( sprintf( '  #%d  %-40s  SET %.5f, %.5f ← %s', $id, $title, $result['lat'], $result['lng'], $result['address'] ) );
				$n['set']++;
				sleep( 1 ); // respect the Geocoding API rate limit
			}

			WP_CLI::success(
				$dry
					? sprintf( 'Dry run: %d would be geocoded, %d skipped (already pinned).', $n['set'], $n['skip'] )
					: sprintf( 'Done. %d set, %d skipped (already pinned), %d failed. Review every new pin in the admin before publishing.', $n['set'], $n['skip'], $n['fail'] )
			);
		}
	}

	WP_CLI::add_command( 'nqa geocode', array( new NQA_Geocode_CLI(), 'geocode' ) );
}
