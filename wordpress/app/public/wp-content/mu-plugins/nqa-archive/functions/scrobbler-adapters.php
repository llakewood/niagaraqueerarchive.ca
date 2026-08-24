<?php
/**
 * Scrobbler adapters — the per-source-type fetchers that turn a watched source
 * into a list of normalized event candidates. The orchestration (scoring,
 * dedupe, queueing, CLI, cron) lives in `scrobbler.php`; this file only knows
 * how to read the outside world.
 *
 * Every adapter returns an array of candidate rows, or a WP_Error:
 *
 *   uid         string  Stable identifier within this source (dedupe anchor).
 *   title       string  The event's own name, verbatim.
 *   start,end   string  'Ymd' (ACF date_picker storage form), or '' if unknown.
 *   venue       string  Location as published (address/venue text, not a pin).
 *   organizer   string  Organizer as published.
 *   url         string  Canonical link to the event.
 *   description string  The publisher's own words, VERBATIM (rule #1).
 *   image       string  Image URL — recorded only, never sideloaded (rights).
 *   recurrence  string  'Recurring' when the source says so, else ''.
 *   note        string  Anything a human reviewer must check (e.g. guessed year).
 *   raw         string  Trimmed raw payload, kept for preservation.
 *
 * Nothing here writes to the database, and nothing here rewrites a publisher's
 * text: an adapter that cannot determine a date returns '' and says so in
 * `note` rather than guessing silently.
 *
 * Source kinds:
 *   ics        A calendar feed (.ics / webcal) — Google Calendar, Apple, most
 *              WordPress event plugins. The best-quality source by a distance.
 *   page       Any web page carrying schema.org Event JSON-LD. Covers Eventbrite
 *              event pages, Squarespace, municipal sites, most modern CMSes.
 *   rss        A news/blog feed. Items are articles, not events: dates are
 *              publish dates, so each item's link is followed for JSON-LD and
 *              flagged for human dating when none is found.
 *   tribe      The Events Calendar's REST API (very common on community sites).
 *   bluesky    A hashtag (#…) or an account handle. Account feeds are public;
 *              hashtag search needs an app password (see nqa_scrobble_bsky_token).
 *   instagram  Instagram Graph API hashtag search / business discovery.
 *              Requires Meta app credentials; see nqa_scrobble_ig_config().
 */

defined( 'ABSPATH' ) || exit;

/**
 * The source kinds this scrobbler understands, as slug => human label.
 * Used by the admin picker, the CLI validator, and the dispatcher below.
 */
function nqa_scrobble_kinds() : array {
	return array(
		'ics'       => 'Calendar feed (.ics / webcal)',
		'page'      => 'Web page with schema.org Event data',
		'rss'       => 'RSS / Atom feed (news or blog)',
		'tribe'     => 'The Events Calendar REST API',
		'bluesky'   => 'Bluesky hashtag or account',
		'instagram' => 'Instagram hashtag or account (Graph API)',
	);
}

/** An honest, identifiable User-Agent. Community orgs should be able to see us. */
const NQA_SCROBBLE_UA = 'NiagaraQueerArchiveBot/1.0 (+https://niagaraqueerarchive.ca/about/)';

/** Shared wp_remote_get arguments for every outbound scrobbler request. */
function nqa_scrobble_http_args( int $timeout = 20 ) : array {
	return array(
		'timeout'     => $timeout,
		'redirection' => 3,
		'user-agent'  => NQA_SCROBBLE_UA,
		'headers'     => array( 'Accept' => '*/*' ),
	);
}

/**
 * Fetch a URL politely. Returns the body string or a WP_Error.
 * `$permission` = true means the organization has given us explicit permission,
 * which is the only thing that overrides their robots.txt.
 */
function nqa_scrobble_fetch( string $url, bool $permission = false ) {
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return new WP_Error( 'nqa_bad_url', 'Not an http(s) URL: ' . $url );
	}
	if ( ! $permission && ! nqa_scrobble_robots_ok( $url ) ) {
		return new WP_Error( 'nqa_robots', 'robots.txt disallows this path for our bot.' );
	}

	$res  = wp_remote_get( $url, nqa_scrobble_http_args() );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( 200 !== $code ) {
		return new WP_Error( 'nqa_http', 'HTTP ' . $code . ' from ' . $url );
	}
	return (string) wp_remote_retrieve_body( $res );
}

/* -------------------------------------------------------------------------
 * robots.txt
 *
 * A community archive that watches community organizations should behave like a
 * good neighbour, so every HTML/feed fetch checks the host's robots.txt first.
 * Rules are cached for 12h per host. A watched source can be marked as having
 * explicit permission from the organization, which bypasses this check — that is
 * a relationship fact recorded by a human, not a switch the code flips itself.
 * ---------------------------------------------------------------------- */

/** True when robots.txt permits our bot to fetch $url (fails open on error). */
function nqa_scrobble_robots_ok( string $url ) : bool {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return false;
	}
	$host   = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$path   = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	$cache  = 'nqa_robots_' . md5( $host );
	$rules  = get_transient( $cache );

	if ( false === $rules ) {
		$res  = wp_remote_get( $host . '/robots.txt', nqa_scrobble_http_args( 10 ) );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		// No robots.txt (or unreachable) means no restriction — fail open.
		$rules = ( 200 === $code ) ? nqa_scrobble_parse_robots( (string) wp_remote_retrieve_body( $res ) ) : array();
		set_transient( $cache, $rules, 12 * HOUR_IN_SECONDS );
	}
	if ( ! $rules ) {
		return true;
	}

	// Longest matching rule wins; Allow beats Disallow at equal length (RFC 9309).
	$best = null;
	foreach ( $rules as $rule ) {
		list( $type, $pattern ) = $rule;
		if ( '' === $pattern ) {
			continue;
		}
		if ( 0 === strpos( $path, $pattern ) ) {
			$len = strlen( $pattern );
			if ( null === $best || $len > $best[1] || ( $len === $best[1] && 'allow' === $type ) ) {
				$best = array( $type, $len );
			}
		}
	}
	return ( null === $best ) || 'allow' === $best[0];
}

/**
 * Extract the Allow/Disallow rules that apply to us from a robots.txt body.
 * Rules named for our bot win outright over the `*` group.
 */
function nqa_scrobble_parse_robots( string $body ) : array {
	$groups  = array();  // agent => [ [type, path], … ]
	$agents  = array();
	$in_rule = false;

	foreach ( preg_split( '/\R/', $body ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' === $line || false === strpos( $line, ':' ) ) {
			continue;
		}
		list( $field, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
		$field = strtolower( $field );

		if ( 'user-agent' === $field ) {
			if ( $in_rule ) {          // a new agent block starts here
				$agents  = array();
				$in_rule = false;
			}
			$agents[] = strtolower( $value );
			continue;
		}
		if ( ( 'allow' === $field || 'disallow' === $field ) && $agents ) {
			$in_rule = true;
			foreach ( $agents as $agent ) {
				$groups[ $agent ][] = array( $field === 'allow' ? 'allow' : 'disallow', $value );
			}
		}
	}

	foreach ( array( 'niagaraqueerarchivebot', '*' ) as $key ) {
		if ( isset( $groups[ $key ] ) ) {
			return $groups[ $key ];
		}
	}
	return array();
}

/* -------------------------------------------------------------------------
 * Dispatcher
 * ---------------------------------------------------------------------- */

/**
 * Run the adapter for one watched source.
 *
 * @param array $watch  Source config: kind, target, permission, label.
 * @return array|WP_Error  Candidate rows, or WP_Error.
 */
function nqa_scrobble_read_source( array $watch ) {
	$kind   = $watch['kind'] ?? '';
	$target = trim( (string) ( $watch['target'] ?? '' ) );
	$perm   = ! empty( $watch['permission'] );

	if ( '' === $target ) {
		return new WP_Error( 'nqa_no_target', 'This source has no URL or handle set.' );
	}

	switch ( $kind ) {
		case 'ics':
			return nqa_scrobble_read_ics( $target, $perm );
		case 'page':
			return nqa_scrobble_read_page( $target, $perm );
		case 'rss':
			return nqa_scrobble_read_rss( $target, $perm );
		case 'tribe':
			return nqa_scrobble_read_tribe( $target, $perm );
		case 'bluesky':
			return nqa_scrobble_read_bluesky( $target );
		case 'instagram':
			return nqa_scrobble_read_instagram( $target );
	}
	return new WP_Error( 'nqa_bad_kind', 'Unknown source kind: ' . $kind );
}

/* -------------------------------------------------------------------------
 * 1. ICS calendar feeds
 * ---------------------------------------------------------------------- */

/** Read an .ics/webcal feed into candidates. */
function nqa_scrobble_read_ics( string $url, bool $permission = false ) {
	$url  = preg_replace( '#^webcal://#i', 'https://', $url );
	$body = nqa_scrobble_fetch( $url, $permission );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	if ( false === stripos( $body, 'BEGIN:VEVENT' ) ) {
		return new WP_Error( 'nqa_not_ics', 'No VEVENT blocks found — is this really a calendar feed?' );
	}

	// Unfold RFC 5545 continuation lines (CRLF followed by a space or tab).
	$body   = preg_replace( "/\r?\n[ \t]/", '', $body );
	$out    = array();
	$in     = false;
	$props  = array();

	foreach ( preg_split( '/\R/', $body ) as $line ) {
		$line = rtrim( $line );
		if ( 'BEGIN:VEVENT' === strtoupper( $line ) ) {
			$in    = true;
			$props = array();
			continue;
		}
		if ( 'END:VEVENT' === strtoupper( $line ) ) {
			$in = false;
			$c  = nqa_scrobble_ics_event( $props );
			if ( $c ) {
				$out[] = $c;
			}
			continue;
		}
		if ( ! $in || false === strpos( $line, ':' ) ) {
			continue;
		}

		list( $name_part, $value ) = explode( ':', $line, 2 );
		$bits   = explode( ';', $name_part );
		$name   = strtoupper( array_shift( $bits ) );
		$params = array();
		foreach ( $bits as $bit ) {
			if ( false !== strpos( $bit, '=' ) ) {
				list( $k, $v ) = explode( '=', $bit, 2 );
				$params[ strtoupper( $k ) ] = trim( $v, '"' );
			}
		}
		// Keep the first occurrence of each property (ignores per-locale repeats).
		if ( ! isset( $props[ $name ] ) ) {
			$props[ $name ] = array( 'value' => $value, 'params' => $params );
		}
	}

	return $out;
}

/** Turn one parsed VEVENT property bag into a candidate row. */
function nqa_scrobble_ics_event( array $props ) : ?array {
	$get = function ( $key ) use ( $props ) {
		return isset( $props[ $key ] ) ? nqa_scrobble_ics_unescape( $props[ $key ]['value'] ) : '';
	};

	$title = trim( $get( 'SUMMARY' ) );
	if ( '' === $title ) {
		return null;
	}

	$start_raw = $props['DTSTART']['value'] ?? '';
	$end_raw   = $props['DTEND']['value'] ?? '';
	$all_day   = ( ( $props['DTSTART']['params']['VALUE'] ?? '' ) === 'DATE' );

	$start = nqa_scrobble_ics_date( $start_raw );
	$end   = nqa_scrobble_ics_date( $end_raw );

	// RFC 5545: an all-day DTEND is EXCLUSIVE — a one-day event ends "tomorrow".
	// Step it back a day so the stored end date is the last day people can attend.
	if ( $all_day && '' !== $end ) {
		$ts  = strtotime( $end );
		$end = $ts ? gmdate( 'Ymd', $ts - DAY_IN_SECONDS ) : $end;
		if ( $end < $start ) {
			$end = $start;
		}
	}

	// ORGANIZER is a mailto: URI; the human-readable name rides in the CN param.
	$organizer = $props['ORGANIZER']['params']['CN'] ?? '';

	return array(
		'uid'         => (string) ( $props['UID']['value'] ?? md5( $title . $start ) ),
		'title'       => $title,
		'start'       => $start,
		'end'         => $end,
		'venue'       => trim( $get( 'LOCATION' ) ),
		'organizer'   => trim( (string) $organizer ),
		'url'         => trim( $get( 'URL' ) ),
		'description' => trim( $get( 'DESCRIPTION' ) ),
		'image'       => '',
		'recurrence'  => isset( $props['RRULE'] ) ? 'Recurring' : '',
		'note'        => isset( $props['RRULE'] ) ? 'Calendar marks this as a repeating event (RRULE) — only the first occurrence is captured.' : '',
		'raw'         => wp_json_encode( array_map( fn( $p ) => $p['value'], $props ) ),
	);
}

/** Decode RFC 5545 TEXT escaping (\n \, \; \\). */
function nqa_scrobble_ics_unescape( string $v ) : string {
	return str_replace(
		array( '\\n', '\\N', '\\,', '\\;', '\\\\' ),
		array( "\n", "\n", ',', ';', '\\' ),
		$v
	);
}

/** ICS date/date-time (20260823 / 20260823T190000Z) → 'Ymd', or ''. */
function nqa_scrobble_ics_date( string $raw ) : string {
	$raw = trim( $raw );
	if ( ! preg_match( '/^(\d{4})(\d{2})(\d{2})/', $raw, $m ) ) {
		return '';
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $m[1] . $m[2] . $m[3] : '';
}

/* -------------------------------------------------------------------------
 * 2. Web pages carrying schema.org Event JSON-LD
 * ---------------------------------------------------------------------- */

/** Read every schema.org Event embedded in a web page. */
function nqa_scrobble_read_page( string $url, bool $permission = false ) {
	$html = nqa_scrobble_fetch( $url, $permission );
	if ( is_wp_error( $html ) ) {
		return $html;
	}
	$events = nqa_scrobble_jsonld_events( $html );
	if ( ! $events ) {
		return new WP_Error(
			'nqa_no_jsonld',
			'No schema.org Event data found on that page. Try the org\'s calendar feed (.ics) instead, or an individual event page.'
		);
	}
	$out = array();
	foreach ( $events as $e ) {
		$c = nqa_scrobble_jsonld_event( $e, $url );
		if ( $c ) {
			$out[] = $c;
		}
	}
	return $out;
}

/** Pull every JSON-LD node of an Event-ish @type out of an HTML document. */
function nqa_scrobble_jsonld_events( string $html ) : array {
	if ( ! preg_match_all(
		'#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
		$html,
		$blocks
	) ) {
		return array();
	}

	$found = array();
	foreach ( $blocks[1] as $json ) {
		$data = json_decode( trim( html_entity_decode( $json, ENT_QUOTES, 'UTF-8' ) ), true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		nqa_scrobble_jsonld_walk( $data, $found );
	}
	return $found;
}

/** Recursively collect Event nodes (walks @graph, itemListElement, plain arrays). */
function nqa_scrobble_jsonld_walk( array $node, array &$found ) : void {
	// A list of nodes rather than a node itself.
	if ( isset( $node[0] ) ) {
		foreach ( $node as $child ) {
			if ( is_array( $child ) ) {
				nqa_scrobble_jsonld_walk( $child, $found );
			}
		}
		return;
	}

	$types = (array) ( $node['@type'] ?? array() );
	foreach ( $types as $type ) {
		// Event, SocialEvent, Festival, TheaterEvent, MusicEvent… all end in "Event".
		if ( is_string( $type ) && preg_match( '/Event$/i', $type ) && ! empty( $node['name'] ) ) {
			$found[] = $node;
			break;
		}
	}

	foreach ( array( '@graph', 'itemListElement', 'item', 'subEvent', 'events' ) as $key ) {
		if ( isset( $node[ $key ] ) && is_array( $node[ $key ] ) ) {
			nqa_scrobble_jsonld_walk( $node[ $key ], $found );
		}
	}
}

/** Map one schema.org Event node onto a candidate row. */
function nqa_scrobble_jsonld_event( array $e, string $source_url ) : ?array {
	$name = is_string( $e['name'] ?? null ) ? trim( $e['name'] ) : '';
	if ( '' === $name ) {
		return null;
	}

	// location: Place { name, address { streetAddress, addressLocality … } } | string.
	$venue = '';
	$loc   = $e['location'] ?? null;
	if ( is_string( $loc ) ) {
		$venue = $loc;
	} elseif ( is_array( $loc ) ) {
		$loc   = isset( $loc[0] ) && is_array( $loc[0] ) ? $loc[0] : $loc;
		$parts = array();
		if ( ! empty( $loc['name'] ) && is_string( $loc['name'] ) ) {
			$parts[] = $loc['name'];
		}
		$addr = $loc['address'] ?? null;
		if ( is_string( $addr ) ) {
			$parts[] = $addr;
		} elseif ( is_array( $addr ) ) {
			foreach ( array( 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode' ) as $k ) {
				if ( ! empty( $addr[ $k ] ) && is_string( $addr[ $k ] ) ) {
					$parts[] = $addr[ $k ];
				}
			}
		}
		$venue = implode( ', ', array_unique( array_filter( $parts ) ) );
	}

	$org = $e['organizer'] ?? ( $e['performer'] ?? null );
	if ( is_array( $org ) ) {
		$org = isset( $org[0] ) && is_array( $org[0] ) ? $org[0] : $org;
		$org = is_array( $org ) ? ( $org['name'] ?? '' ) : $org;
	}

	$image = $e['image'] ?? '';
	if ( is_array( $image ) ) {
		$image = $image['url'] ?? ( is_string( $image[0] ?? null ) ? $image[0] : '' );
	}

	$url = is_string( $e['url'] ?? null ) && $e['url'] !== '' ? $e['url'] : $source_url;

	return array(
		'uid'         => (string) ( $e['@id'] ?? $url . '#' . $name ),
		'title'       => $name,
		'start'       => nqa_scrobble_iso_date( $e['startDate'] ?? '' ),
		'end'         => nqa_scrobble_iso_date( $e['endDate'] ?? '' ),
		'venue'       => $venue,
		'organizer'   => is_string( $org ) ? trim( $org ) : '',
		'url'         => $url,
		'description' => is_string( $e['description'] ?? null ) ? trim( wp_strip_all_tags( $e['description'] ) ) : '',
		'image'       => is_string( $image ) ? $image : '',
		'recurrence'  => ! empty( $e['eventSchedule'] ) ? 'Recurring' : '',
		'note'        => '',
		'raw'         => wp_json_encode( $e ),
	);
}

/** ISO 8601 date/datetime → 'Ymd', or ''. */
function nqa_scrobble_iso_date( $raw ) : string {
	$raw = is_array( $raw ) ? (string) reset( $raw ) : (string) $raw;
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}
	$ts = strtotime( $raw );
	return $ts ? gmdate( 'Ymd', $ts ) : '';
}

/* -------------------------------------------------------------------------
 * 3. RSS / Atom feeds
 *
 * Feed items are ARTICLES, not events — their dates are publication dates. Each
 * item's link is therefore followed once and checked for JSON-LD Event data; if
 * that finds nothing, the candidate is queued with NO date and a note asking the
 * reviewer to read the article. We never treat a publish date as an event date.
 * ---------------------------------------------------------------------- */

function nqa_scrobble_read_rss( string $url, bool $permission = false ) {
	if ( ! $permission && ! nqa_scrobble_robots_ok( $url ) ) {
		return new WP_Error( 'nqa_robots', 'robots.txt disallows this path for our bot.' );
	}

	include_once ABSPATH . WPINC . '/feed.php';
	add_filter( 'wp_feed_cache_transient_lifetime', 'nqa_scrobble_feed_ttl' );
	$feed = fetch_feed( $url );
	remove_filter( 'wp_feed_cache_transient_lifetime', 'nqa_scrobble_feed_ttl' );

	if ( is_wp_error( $feed ) ) {
		return $feed;
	}

	$out   = array();
	$items = $feed->get_items( 0, (int) apply_filters( 'nqa_scrobble_rss_limit', 25 ) );

	foreach ( $items as $item ) {
		$link  = (string) $item->get_permalink();
		$title = trim( html_entity_decode( (string) $item->get_title(), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $title ) {
			continue;
		}

		$cand = array(
			'uid'         => (string) ( $item->get_id() ?: $link ),
			'title'       => $title,
			'start'       => '',
			'end'         => '',
			'venue'       => '',
			'organizer'   => '',
			'url'         => $link,
			'description' => trim( wp_strip_all_tags( (string) $item->get_description() ) ),
			'image'       => '',
			'recurrence'  => '',
			'note'        => '',
			'raw'         => '',
		);

		// Follow the article once for real event data.
		$enriched = $link ? nqa_scrobble_read_page( $link, $permission ) : new WP_Error( 'nqa_no_link', 'no link' );
		if ( ! is_wp_error( $enriched ) && $enriched ) {
			$cand = array_merge( $cand, $enriched[0] );
			$cand['uid'] = (string) ( $item->get_id() ?: $link );
		} else {
			$cand['note'] = sprintf(
				'From a news/blog feed with no structured event data — published %s. Read the article and set the real event dates before converting.',
				(string) $item->get_date( 'Y-m-d' )
			);
		}
		$out[] = $cand;
	}
	return $out;
}

/** Keep SimplePie's cache short so a scheduled run sees fresh items. */
function nqa_scrobble_feed_ttl() : int {
	return 30 * MINUTE_IN_SECONDS;
}

/* -------------------------------------------------------------------------
 * 4. The Events Calendar REST API
 *
 * A large share of Ontario community-org WordPress sites run The Events Calendar,
 * which exposes /wp-json/tribe/events/v1/events publicly. Give the adapter the
 * site root (or the full endpoint) and it does the rest.
 * ---------------------------------------------------------------------- */

function nqa_scrobble_read_tribe( string $url, bool $permission = false ) {
	$endpoint = ( false !== strpos( $url, '/wp-json/' ) )
		? $url
		: rtrim( $url, '/' ) . '/wp-json/tribe/events/v1/events';
	$endpoint = add_query_arg(
		array( 'per_page' => 50, 'start_date' => current_time( 'Y-m-d' ) ),
		$endpoint
	);

	$body = nqa_scrobble_fetch( $endpoint, $permission );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || ! isset( $data['events'] ) || ! is_array( $data['events'] ) ) {
		return new WP_Error( 'nqa_not_tribe', 'That endpoint did not return a Tribe events payload.' );
	}

	$out = array();
	foreach ( $data['events'] as $e ) {
		if ( empty( $e['title'] ) ) {
			continue;
		}
		$venue = '';
		if ( ! empty( $e['venue'] ) && is_array( $e['venue'] ) ) {
			$venue = implode(
				', ',
				array_filter(
					array(
						$e['venue']['venue']   ?? '',
						$e['venue']['address'] ?? '',
						$e['venue']['city']    ?? '',
					)
				)
			);
		}
		$org = '';
		if ( ! empty( $e['organizer'] ) && is_array( $e['organizer'] ) ) {
			$first = isset( $e['organizer'][0] ) ? $e['organizer'][0] : $e['organizer'];
			$org   = is_array( $first ) ? ( $first['organizer'] ?? '' ) : (string) $first;
		}

		$out[] = array(
			'uid'         => (string) ( $e['id'] ?? ( $e['url'] ?? '' ) ),
			'title'       => trim( html_entity_decode( wp_strip_all_tags( (string) $e['title'] ), ENT_QUOTES, 'UTF-8' ) ),
			'start'       => nqa_scrobble_iso_date( $e['start_date'] ?? '' ),
			'end'         => nqa_scrobble_iso_date( $e['end_date'] ?? '' ),
			'venue'       => $venue,
			'organizer'   => $org,
			'url'         => (string) ( $e['url'] ?? '' ),
			'description' => trim( wp_strip_all_tags( (string) ( $e['description'] ?? '' ) ) ),
			'image'       => is_array( $e['image'] ?? null ) ? (string) ( $e['image']['url'] ?? '' ) : '',
			'recurrence'  => ! empty( $e['recurring'] ) ? 'Recurring' : '',
			'note'        => '',
			'raw'         => wp_json_encode( $e ),
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 5. Social posts (Bluesky, Instagram)
 *
 * A social post is a poster, not a calendar entry: there is no date field, only
 * prose. `nqa_scrobble_dates_from_text()` reads a date out of the caption when
 * the caption plainly contains one, records the exact phrase it matched, and
 * leaves the date EMPTY otherwise. Every social candidate carries a note telling
 * the reviewer to confirm against the original post.
 * ---------------------------------------------------------------------- */

/**
 * Best-effort date extraction from prose. Returns
 *   [ 'start' => 'Ymd'|'', 'matched' => string, 'guessed_year' => bool ]
 *
 * Deliberately conservative: only ISO dates and written month/day forms are
 * recognised. Numeric forms like 08/09 are ignored entirely — the ambiguity
 * between Aug 9 and Sep 8 is exactly the kind of quiet error an archive must not
 * make. When the caption gives no year, the next occurrence at or after the post
 * date is used and `guessed_year` is set so the reviewer is told.
 */
function nqa_scrobble_dates_from_text( string $text, int $posted_ts = 0 ) : array {
	$none     = array( 'start' => '', 'matched' => '', 'guessed_year' => false );
	$posted_ts = $posted_ts ?: current_time( 'timestamp' );

	// ISO first — unambiguous.
	if ( preg_match( '/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m ) ) {
		if ( checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return array( 'start' => $m[1] . $m[2] . $m[3], 'matched' => $m[0], 'guessed_year' => false );
		}
	}

	$months = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

	// "August 23", "Aug 23rd, 2026", "23 August 2026". The (?!\d) guards stop the
	// day group from swallowing the first digits of a year: without them
	// "23 August 2027" reads as "August 20".
	$patterns = array(
		'/\b(' . $months . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?(?!\d)(?:,?\s*(\d{4})(?!\d))?/i',
		'/\b(\d{1,2})(?:st|nd|rd|th)?(?!\d)\s+(' . $months . ')\.?(?:,?\s*(\d{4})(?!\d))?/i',
	);
	foreach ( $patterns as $i => $pattern ) {
		if ( ! preg_match( $pattern, $text, $m ) ) {
			continue;
		}
		$month_word = $i === 0 ? $m[1] : $m[2];
		$day        = (int) ( $i === 0 ? $m[2] : $m[1] );
		$year       = isset( $m[3] ) && $m[3] !== '' ? (int) $m[3] : 0;
		$month      = (int) gmdate( 'n', strtotime( $month_word . ' 1 2000' ) );

		$guessed = false;
		if ( ! $year ) {
			// No year given: the next occurrence at or after the post date.
			$guessed = true;
			$year    = (int) gmdate( 'Y', $posted_ts );
			if ( mktime( 0, 0, 0, $month, $day, $year ) < strtotime( gmdate( 'Y-m-d', $posted_ts ) ) ) {
				$year++;
			}
		}
		if ( checkdate( $month, $day, $year ) ) {
			return array(
				'start'        => sprintf( '%04d%02d%02d', $year, $month, $day ),
				'matched'      => trim( $m[0] ),
				'guessed_year' => $guessed,
			);
		}
	}
	return $none;
}

/** Shape a social post into a candidate row, with honest dating caveats. */
function nqa_scrobble_social_candidate( array $args ) : array {
	$text  = trim( (string) $args['text'] );
	$dates = nqa_scrobble_dates_from_text( $text, (int) ( $args['posted_ts'] ?? 0 ) );

	$notes = array( sprintf( 'Scrobbled from a %s post by %s — confirm every detail against the original post before converting.', $args['network'], $args['author'] ) );
	if ( '' === $dates['start'] ) {
		$notes[] = 'No date could be read from the caption — set the event dates by hand.';
	} else {
		$notes[] = sprintf( 'Date read from the caption phrase "%s".', $dates['matched'] );
		if ( $dates['guessed_year'] ) {
			$notes[] = 'The caption gave no year; the next occurrence after the post date was assumed. VERIFY.';
		}
	}

	// The first line of a caption is nearly always the event name; fall back to a
	// trimmed opening. Never invent a title the poster did not write.
	$first = trim( (string) strtok( $text, "\n" ) );
	$title = $first !== '' ? mb_substr( $first, 0, 120 ) : 'Untitled post — needs a title';

	return array(
		'uid'         => (string) $args['uid'],
		'title'       => $title,
		'start'       => $dates['start'],
		'end'         => '',
		'venue'       => '',
		'organizer'   => (string) $args['author'],
		'url'         => (string) $args['url'],
		'description' => $text,   // VERBATIM caption.
		'image'       => (string) ( $args['image'] ?? '' ),
		'recurrence'  => '',
		'note'        => implode( ' ', $notes ),
		'raw'         => wp_json_encode( $args['raw'] ?? array() ),
	);
}

/**
 * A Bluesky access token, or null when no credentials are configured.
 *
 * Account feeds (`getAuthorFeed`) are readable without any credentials, but
 * Bluesky closed unauthenticated *search* — `searchPosts` now returns 403 — so
 * hashtag watching needs a login. This is a far lower bar than Meta's: create a
 * free app password at Settings → App Passwords on any Bluesky account (use one
 * belonging to the archive, not a personal account), then set in the gitignored
 * 0-nqa-runtime-config.php (production: GitHub Secrets):
 *
 *   NQA_BSKY_HANDLE        e.g. niagaraqueerarchive.bsky.social
 *   NQA_BSKY_APP_PASSWORD  the app password, NOT the account password
 *
 * Sessions are cached for 90 minutes (tokens last ~2h).
 */
function nqa_scrobble_bsky_token() : ?string {
	if ( ! defined( 'NQA_BSKY_HANDLE' ) || ! defined( 'NQA_BSKY_APP_PASSWORD' ) || ! NQA_BSKY_HANDLE || ! NQA_BSKY_APP_PASSWORD ) {
		return null;
	}
	$cached = get_transient( 'nqa_bsky_token' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$res = wp_remote_post(
		'https://bsky.social/xrpc/com.atproto.server.createSession',
		array_merge(
			nqa_scrobble_http_args(),
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array( 'identifier' => NQA_BSKY_HANDLE, 'password' => NQA_BSKY_APP_PASSWORD )
				),
			)
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$token = is_array( $data ) ? (string) ( $data['accessJwt'] ?? '' ) : '';
	if ( '' === $token ) {
		return null;
	}
	set_transient( 'nqa_bsky_token', $token, 90 * MINUTE_IN_SECONDS );
	return $token;
}

/**
 * Bluesky. Target is either a hashtag ("#niagarapride") or an account handle
 * ("prideniagara.bsky.social"). Account feeds need no credentials; hashtag
 * search needs an app password — see nqa_scrobble_bsky_token().
 */
function nqa_scrobble_read_bluesky( string $target ) {
	$target = trim( $target );
	$is_tag = ( 0 === strpos( $target, '#' ) );
	$token  = nqa_scrobble_bsky_token();

	if ( $is_tag && null === $token ) {
		return new WP_Error(
			'nqa_bsky_auth',
			'Bluesky hashtag search needs credentials (they closed anonymous search). Set NQA_BSKY_HANDLE + NQA_BSKY_APP_PASSWORD, or watch an account handle instead — those still work without a login.'
		);
	}

	// Authenticated calls must go to bsky.social; the public mirror is read-only
	// and does not accept bearer tokens.
	$host = $token ? 'https://bsky.social' : 'https://public.api.bsky.app';

	$endpoint = $is_tag
		? add_query_arg(
			array( 'q' => $target, 'limit' => 25, 'sort' => 'latest' ),
			$host . '/xrpc/app.bsky.feed.searchPosts'
		)
		: add_query_arg(
			array( 'actor' => ltrim( $target, '@' ), 'limit' => 25 ),
			$host . '/xrpc/app.bsky.feed.getAuthorFeed'
		);

	$args = nqa_scrobble_http_args();
	if ( $token ) {
		$args['headers']['Authorization'] = 'Bearer ' . $token;
	}

	$res  = wp_remote_get( $endpoint, $args );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( 401 === $code && $token ) {
		// Token expired early — drop it so the next run logs in again.
		delete_transient( 'nqa_bsky_token' );
		return new WP_Error( 'nqa_bsky', 'Bluesky session expired. Re-run to log in again.' );
	}
	if ( 200 !== $code ) {
		return new WP_Error( 'nqa_bsky', 'Bluesky API returned HTTP ' . $code . '.' );
	}

	$data  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$posts = $is_tag ? ( $data['posts'] ?? array() ) : wp_list_pluck( $data['feed'] ?? array(), 'post' );
	if ( ! is_array( $posts ) ) {
		return array();
	}

	$out = array();
	foreach ( $posts as $p ) {
		if ( ! is_array( $p ) || empty( $p['record']['text'] ) ) {
			continue;
		}
		$handle = (string) ( $p['author']['handle'] ?? 'unknown' );
		$rkey   = (string) substr( strrchr( (string) ( $p['uri'] ?? '' ), '/' ), 1 );

		$out[] = nqa_scrobble_social_candidate(
			array(
				'network'   => 'Bluesky',
				'uid'       => (string) ( $p['uri'] ?? $rkey ),
				'text'      => (string) $p['record']['text'],
				'author'    => '@' . $handle,
				'url'       => $rkey ? 'https://bsky.app/profile/' . $handle . '/post/' . $rkey : '',
				'posted_ts' => strtotime( (string) ( $p['record']['createdAt'] ?? '' ) ) ?: 0,
				'image'     => (string) ( $p['embed']['images'][0]['thumb'] ?? '' ),
				'raw'       => $p,
			)
		);
	}
	return $out;
}

/**
 * Instagram credentials, or null when unconfigured.
 *
 * Instagram has no open hashtag API. Reading hashtags or other accounts requires
 * the Instagram Graph API: an Instagram *Business/Creator* account (NQA already
 * runs @NiagaraQueerArchive), a linked Facebook Page, a Meta app, and App Review
 * for `instagram_basic` + `instagram_manage_insights`. Once approved, set these
 * in the gitignored 0-nqa-runtime-config.php (production: GitHub Secrets):
 *
 *   NQA_IG_TOKEN     Long-lived page access token
 *   NQA_IG_USER_ID   The archive's Instagram Business account ID
 *
 * Scraping instagram.com instead is against Meta's terms and is not implemented
 * here. Note the platform's own limits: hashtag search returns only the last ~24h
 * of top/recent media and permits 30 unique hashtags per rolling 7 days.
 */
function nqa_scrobble_ig_config() : ?array {
	if ( ! defined( 'NQA_IG_TOKEN' ) || ! defined( 'NQA_IG_USER_ID' ) || ! NQA_IG_TOKEN || ! NQA_IG_USER_ID ) {
		return null;
	}
	return array( 'token' => NQA_IG_TOKEN, 'user_id' => NQA_IG_USER_ID );
}

/** Instagram hashtag ("#pridniagara") or business account ("@prideniagara"). */
function nqa_scrobble_read_instagram( string $target ) {
	$cfg = nqa_scrobble_ig_config();
	if ( null === $cfg ) {
		return new WP_Error(
			'nqa_ig_unconfigured',
			'Instagram is not configured. Set NQA_IG_TOKEN and NQA_IG_USER_ID (see nqa_scrobble_ig_config() for what Meta requires).'
		);
	}

	$api    = 'https://graph.facebook.com/v21.0/';
	$target = trim( $target );
	$get    = function ( $url ) {
		$res  = wp_remote_get( $url, nqa_scrobble_http_args() );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( isset( $data['error']['message'] ) ) {
			return new WP_Error( 'nqa_ig_api', 'Instagram API: ' . $data['error']['message'] );
		}
		return is_array( $data ) ? $data : new WP_Error( 'nqa_ig_api', 'Unreadable Instagram response.' );
	};

	if ( 0 === strpos( $target, '#' ) ) {
		// Hashtag → resolve to an ID, then read recent media.
		$hash = $get(
			add_query_arg(
				array( 'user_id' => $cfg['user_id'], 'q' => ltrim( $target, '#' ), 'access_token' => $cfg['token'] ),
				$api . 'ig_hashtag_search'
			)
		);
		if ( is_wp_error( $hash ) ) {
			return $hash;
		}
		$hash_id = $hash['data'][0]['id'] ?? '';
		if ( ! $hash_id ) {
			return new WP_Error( 'nqa_ig_tag', 'Instagram does not know the hashtag ' . $target . '.' );
		}
		$media = $get(
			add_query_arg(
				array(
					'user_id'      => $cfg['user_id'],
					'fields'       => 'id,caption,permalink,timestamp,media_url',
					'limit'        => 25,
					'access_token' => $cfg['token'],
				),
				$api . $hash_id . '/recent_media'
			)
		);
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$items  = $media['data'] ?? array();
		$author = $target;
	} else {
		// Account → business_discovery (the account must be Business/Creator).
		$handle = ltrim( $target, '@' );
		$data   = $get(
			add_query_arg(
				array(
					'fields'       => 'business_discovery.username(' . rawurlencode( $handle ) . '){media.limit(25){id,caption,permalink,timestamp,media_url}}',
					'access_token' => $cfg['token'],
				),
				$api . $cfg['user_id']
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$items  = $data['business_discovery']['media']['data'] ?? array();
		$author = '@' . $handle;
	}

	$out = array();
	foreach ( (array) $items as $m ) {
		if ( empty( $m['caption'] ) ) {
			continue;
		}
		$out[] = nqa_scrobble_social_candidate(
			array(
				'network'   => 'Instagram',
				'uid'       => (string) ( $m['id'] ?? '' ),
				'text'      => (string) $m['caption'],
				'author'    => $author,
				'url'       => (string) ( $m['permalink'] ?? '' ),
				'posted_ts' => strtotime( (string) ( $m['timestamp'] ?? '' ) ) ?: 0,
				'image'     => (string) ( $m['media_url'] ?? '' ),
				'raw'       => $m,
			)
		);
	}
	return $out;
}
