<?php
/**
 * Plugin Name: NQA – Source Preservation & Link-Rot Backstop
 * Description: Captures an Internet Archive (Wayback) snapshot of each article's source
 *              URL, stores a PRIVATE full-text copy (never shown publicly unless a per-
 *              article rights flag is set), and tracks source liveness so the display
 *              layer can fall back to the archived snapshot when a link rots. WP-CLI:
 *              `wp nqa capture-sources`, `wp nqa check-sources`. No secrets; git-tracked.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Protected (underscore-prefixed) meta keys used by this feature.
 *   _nqa_wayback_url     Absolute URL of the closest Wayback snapshot, if any.
 *   _nqa_wayback_ts      Wayback snapshot timestamp (YYYYMMDDhhmmss).
 *   _nqa_archive_text    PRIVATE plain-text copy of the source page. Never public.
 *   _nqa_source_ok       '1' if the live source responded 200 at last check, else '0'.
 *   _nqa_source_checked  Datetime (mysql) of the last liveness check.
 *   _nqa_text_public     Per-article rights flag; truthy = full text may be shown.
 */
const NQA_META_WAYBACK_URL   = '_nqa_wayback_url';
const NQA_META_WAYBACK_TS    = '_nqa_wayback_ts';
const NQA_META_ARCHIVE_TEXT  = '_nqa_archive_text';
const NQA_META_SOURCE_OK     = '_nqa_source_ok';
const NQA_META_SOURCE_CHECK  = '_nqa_source_checked';
const NQA_META_TEXT_PUBLIC   = '_nqa_text_public';

/** A normal-looking browser User-Agent so publishers don't 403 a bare bot. */
function nqa_preservation_user_agent() {
	return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
		. '(KHTML, like Gecko) Chrome/124.0 Safari/537.36';
}

/**
 * Resolve an article's source URL: ACF `link` first, then raw post meta `link`.
 * Returns '' when absent/blank.
 */
function nqa_source_url( $post_id ) {
	$url = function_exists( 'get_field' ) ? get_field( 'link', $post_id ) : '';
	if ( ! is_string( $url ) || trim( $url ) === '' ) {
		$url = get_post_meta( $post_id, 'link', true );
	}
	$url = is_string( $url ) ? trim( $url ) : '';
	return preg_match( '#^https?://#i', $url ) ? $url : '';
}

/**
 * Query the Wayback availability API for the closest snapshot of $url.
 * Returns [ 'url' => string, 'timestamp' => string ] or null.
 */
function nqa_wayback_lookup( $url ) {
	$api = 'https://archive.org/wayback/available?url=' . rawurlencode( $url );
	$res = wp_remote_get( $api, array(
		'timeout'    => 15,
		'user-agent' => nqa_preservation_user_agent(),
	) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$snap = isset( $data['archived_snapshots']['closest'] )
		? $data['archived_snapshots']['closest']
		: null;
	if ( ! is_array( $snap ) || empty( $snap['url'] ) ) {
		return null;
	}
	return array(
		'url'       => (string) $snap['url'],
		'timestamp' => isset( $snap['timestamp'] ) ? (string) $snap['timestamp'] : '',
	);
}

/**
 * Ask the Wayback Machine to save $url now. Best-effort: timeouts/errors are
 * expected and tolerated (the save can succeed even when the request times out).
 */
function nqa_wayback_save( $url ) {
	wp_remote_get( 'https://web.archive.org/save/' . $url, array(
		'timeout'     => 30,
		'redirection' => 3,
		'user-agent'  => nqa_preservation_user_agent(),
	) );
}

/**
 * Full capture pass for one post: Wayback snapshot + private full-text copy +
 * source-liveness. Safe to re-run; $force also triggers a fresh Wayback save.
 * Returns a small status array for CLI reporting.
 */
function nqa_capture_article( $post_id, $force = false ) {
	$post_id = (int) $post_id;
	$url     = nqa_source_url( $post_id );
	$result  = array(
		'post_id'  => $post_id,
		'url'      => $url,
		'skipped'  => false,
		'wayback'  => false,
		'text'     => false,
		'live_ok'  => false,
	);
	if ( '' === $url ) {
		$result['skipped'] = true;
		return $result;
	}

	// 1) Wayback: look up an existing snapshot; if none (or forced), request a
	//    save and re-query. Store whatever we end up finding.
	$snap = nqa_wayback_lookup( $url );
	if ( ( null === $snap || $force ) ) {
		nqa_wayback_save( $url );
		usleep( 500000 ); // 0.5s – be polite before re-querying.
		$fresh = nqa_wayback_lookup( $url );
		if ( null !== $fresh ) {
			$snap = $fresh;
		}
	}
	if ( null !== $snap ) {
		update_post_meta( $post_id, NQA_META_WAYBACK_URL, $snap['url'] );
		update_post_meta( $post_id, NQA_META_WAYBACK_TS, $snap['timestamp'] );
		$result['wayback'] = true;
	}

	usleep( 300000 ); // 0.3s between network calls.

	// 2) Live fetch: store a PRIVATE plain-text copy and record liveness.
	$res = wp_remote_get( $url, array(
		'timeout'     => 15,
		'redirection' => 3,
		'user-agent'  => nqa_preservation_user_agent(),
	) );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$body = (string) wp_remote_retrieve_body( $res );
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $body ) ) );
		update_post_meta( $post_id, NQA_META_ARCHIVE_TEXT, $text );
		update_post_meta( $post_id, NQA_META_SOURCE_OK, '1' );
		$result['text']    = ( '' !== $text );
		$result['live_ok'] = true;
	} else {
		update_post_meta( $post_id, NQA_META_SOURCE_OK, '0' );
	}
	update_post_meta( $post_id, NQA_META_SOURCE_CHECK, current_time( 'mysql' ) );

	return $result;
}

/**
 * Lightweight liveness re-check: updates _nqa_source_ok + _nqa_source_checked
 * only. Tries a HEAD first, falling back to GET (some hosts reject HEAD).
 * Returns bool source-ok, or null when the post has no source URL.
 */
function nqa_check_source( $post_id ) {
	$post_id = (int) $post_id;
	$url     = nqa_source_url( $post_id );
	if ( '' === $url ) {
		return null;
	}
	$args = array(
		'timeout'     => 15,
		'redirection' => 3,
		'user-agent'  => nqa_preservation_user_agent(),
	);
	$res  = wp_remote_head( $url, $args );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		// Some servers 405/403 HEAD but serve GET fine – confirm with a GET.
		$res  = wp_remote_get( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}
	$ok = ( 200 === $code );
	update_post_meta( $post_id, NQA_META_SOURCE_OK, $ok ? '1' : '0' );
	update_post_meta( $post_id, NQA_META_SOURCE_CHECK, current_time( 'mysql' ) );
	return $ok;
}

/**
 * True only when the per-article rights flag permits showing the full text.
 * The private _nqa_archive_text must never be surfaced unless this is true.
 */
function nqa_archive_text_is_public( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, NQA_META_TEXT_PUBLIC, true );
}

/**
 * Display helper for the source link with a link-rot fallback.
 * Returns:
 *   [ 'url' => string, 'archived' => bool, 'wayback' => string|null ]
 * When the live source is known-dead (_nqa_source_ok === '0') and a Wayback
 * snapshot exists, the primary url IS the snapshot (archived=true). Otherwise
 * the primary is the live link and 'wayback' carries the snapshot (if any) so
 * the caller can offer a secondary "Archived copy" link. Returns null when the
 * post has no source URL at all.
 */
function nqa_source_fallback( $post_id ) {
	$post_id  = (int) $post_id;
	$live     = nqa_source_url( $post_id );
	$wayback  = get_post_meta( $post_id, NQA_META_WAYBACK_URL, true );
	$wayback  = is_string( $wayback ) && '' !== $wayback ? $wayback : null;
	$ok       = get_post_meta( $post_id, NQA_META_SOURCE_OK, true );
	$dead     = ( '0' === (string) $ok );

	if ( '' === $live && null === $wayback ) {
		return null;
	}

	if ( $dead && null !== $wayback ) {
		return array( 'url' => $wayback, 'archived' => true, 'wayback' => null );
	}

	return array(
		'url'      => '' !== $live ? $live : $wayback,
		'archived' => ( '' === $live ),
		'wayback'  => ( '' !== $live ) ? $wayback : null,
	);
}

/* -------------------------------------------------------------------------
 * WP-CLI
 * ---------------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/** Post IDs that carry a source `link`, for a given set of post types. */
	function nqa_preservation_target_ids( $post_types ) {
		return get_posts( array(
			'post_type'        => $post_types,
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		) );
	}

	class NQA_Preservation_CLI {

		/**
		 * Capture Wayback snapshots + private text for article sources.
		 *
		 * ## OPTIONS
		 *
		 * [--all]
		 * : Also include the entity post types (person/org/event/place) that
		 *   carry a source `link`, not just core `post` articles.
		 *
		 * [--force]
		 * : Force a fresh Wayback save even when a snapshot already exists.
		 *
		 * ## EXAMPLES
		 *
		 *     wp nqa capture-sources
		 *     wp nqa capture-sources --all --force
		 *
		 * @when after_wp_load
		 */
		public function capture_sources( $args, $assoc ) {
			$force = isset( $assoc['force'] );
			$types = array( 'post' );
			if ( isset( $assoc['all'] ) && function_exists( 'nqa_entity_post_types' ) ) {
				$types = array_merge( $types, nqa_entity_post_types() );
			}

			$ids = nqa_preservation_target_ids( $types );
			WP_CLI::log( sprintf( 'Scanning %d posts (types: %s)…', count( $ids ), implode( ', ', $types ) ) );

			$n = array( 'total' => 0, 'skipped' => 0, 'wayback' => 0, 'text' => 0, 'live_ok' => 0 );
			foreach ( $ids as $id ) {
				$r = nqa_capture_article( $id, $force );
				$n['total']++;
				if ( $r['skipped'] ) {
					$n['skipped']++;
					WP_CLI::log( sprintf( '  #%d  %-40s  [no source link — skipped]', $id, nqa_preservation_trim_title( $id ) ) );
					continue;
				}
				$n['wayback'] += $r['wayback'] ? 1 : 0;
				$n['text']    += $r['text'] ? 1 : 0;
				$n['live_ok'] += $r['live_ok'] ? 1 : 0;
				WP_CLI::log( sprintf(
					'  #%d  %-40s  wayback:%s  live:%s  text:%s',
					$id,
					nqa_preservation_trim_title( $id ),
					$r['wayback'] ? 'yes' : 'no ',
					$r['live_ok'] ? '200' : 'dead',
					$r['text'] ? 'yes' : 'no '
				) );
			}

			WP_CLI::success( sprintf(
				'Done. %d scanned, %d skipped (no link), %d with Wayback snapshot, %d reachable (200), %d with stored text.',
				$n['total'], $n['skipped'], $n['wayback'], $n['live_ok'], $n['text']
			) );
		}

		/**
		 * Re-check source liveness only (no re-capture).
		 *
		 * ## OPTIONS
		 *
		 * [--all]
		 * : Include entity post types too.
		 *
		 * @when after_wp_load
		 */
		public function check_sources( $args, $assoc ) {
			$types = array( 'post' );
			if ( isset( $assoc['all'] ) && function_exists( 'nqa_entity_post_types' ) ) {
				$types = array_merge( $types, nqa_entity_post_types() );
			}

			$ids = nqa_preservation_target_ids( $types );
			WP_CLI::log( sprintf( 'Checking %d posts…', count( $ids ) ) );

			$n = array( 'checked' => 0, 'ok' => 0, 'dead' => 0, 'skipped' => 0 );
			foreach ( $ids as $id ) {
				$ok = nqa_check_source( $id );
				if ( null === $ok ) {
					$n['skipped']++;
					continue;
				}
				$n['checked']++;
				$ok ? $n['ok']++ : $n['dead']++;
				WP_CLI::log( sprintf(
					'  #%d  %-40s  %s',
					$id, nqa_preservation_trim_title( $id ), $ok ? 'ok' : 'DEAD'
				) );
			}

			WP_CLI::success( sprintf(
				'%d checked, %d ok, %d dead, %d skipped (no link).',
				$n['checked'], $n['ok'], $n['dead'], $n['skipped']
			) );
		}
	}

	/** Short, padded post title for tidy CLI columns. */
	function nqa_preservation_trim_title( $id ) {
		$t = html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ) );
		return mb_strlen( $t ) > 40 ? mb_substr( $t, 0, 37 ) . '…' : $t;
	}

	$nqa_cli = new NQA_Preservation_CLI();
	WP_CLI::add_command( 'nqa capture-sources', array( $nqa_cli, 'capture_sources' ) );
	WP_CLI::add_command( 'nqa check-sources', array( $nqa_cli, 'check_sources' ) );
}
