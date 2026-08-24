<?php
/**
 * Event scrobbler — a discovery layer that watches community calendars, event
 * pages, news feeds, and social accounts, and drops what it finds into the SAME
 * review queue a person uses when they fill in "List an Event".
 *
 * The archive's problem is not that Niagara has no queer events; it is that they
 * are announced once, on a platform that forgets them. The scrobbler catches
 * those announcements while they are still live, so each becomes a linkable page
 * now and a primary source later — the thesis already stated in `events.php`.
 *
 *   Watched Source (nqa_watch)  →  adapter (scrobbler-adapters.php)
 *     →  relevance filter  →  dedupe  →  private nqa_submission (kind=event)
 *     →  archivist review  →  nqa_create_event_from_submission()  →  draft
 *
 * Three rules shape every design decision here:
 *
 *   · It never publishes. Scrobbled candidates always land as pending
 *     submissions with consent Pending (rule #2). The trusted-organizer
 *     auto-publish path in `events.php` stays exclusive to authenticated people
 *     submitting their OWN event — a robot reading someone's Instagram is not
 *     that person consenting.
 *   · It never writes prose. Titles, descriptions, and dates are carried over
 *     verbatim from the publisher; anything the code could not read (a caption
 *     with no year, an article with no event data) is left EMPTY and flagged for
 *     a human, never guessed into place (rule #1).
 *   · It behaves like a neighbour. Identifiable User-Agent, robots.txt honoured
 *     unless the organization has explicitly given permission, no image files
 *     copied off other people's servers.
 *
 * Sources are content, not code: they live in the CMS as `nqa_watch` posts so an
 * editor can add a new calendar without a deploy (and so prod stays the source of
 * truth for them, like all other content).
 *
 * WP-CLI:
 *   wp nqa scrobble [--source=<id>] [--dry-run] [--no-filter] [--limit=<n>]
 *   wp nqa watch list | add | test
 */

defined( 'ABSPATH' ) || exit;

const NQA_SCROBBLE_CRON = 'nqa_scrobble_daily';

/* -------------------------------------------------------------------------
 * 1. Watched Source post type
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		register_post_type(
			'nqa_watch',
			array(
				'labels'              => array(
					'name'               => 'Watched Sources',
					'singular_name'      => 'Watched Source',
					'menu_name'          => 'Watched Sources',
					'all_items'          => 'Watched Sources',
					'add_new_item'       => 'Add Watched Source',
					'edit_item'          => 'Edit Watched Source',
					'search_items'       => 'Search Watched Sources',
					'not_found'          => 'No watched sources yet.',
					'not_found_in_trash' => 'No watched sources in trash.',
				),
				'public'              => false,
				'show_ui'             => true,
				// Sits under Submissions: watching and reviewing are one workflow.
				'show_in_menu'        => 'edit.php?post_type=nqa_submission',
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
			)
		);
	}
);

/** Read one watched source's configuration into a plain array. */
function nqa_scrobble_watch( int $watch_id ) : array {
	$m = fn( $k ) => get_post_meta( $watch_id, $k, true );
	return array(
		'id'         => $watch_id,
		'label'      => get_the_title( $watch_id ),
		'kind'       => (string) $m( '_nqa_watch_kind' ),
		'target'     => (string) $m( '_nqa_watch_target' ),
		'mode'       => $m( '_nqa_watch_mode' ) === 'all' ? 'all' : 'strict',
		'muni'       => (string) $m( '_nqa_watch_muni' ),
		'org'        => (int) $m( '_nqa_watch_org' ),
		'permission' => (bool) $m( '_nqa_watch_permission' ),
		'active'     => '0' !== (string) $m( '_nqa_watch_active' ),
	);
}

/** All watched sources, optionally only the active ones. */
function nqa_scrobble_watches( bool $active_only = true ) : array {
	$ids = get_posts(
		array(
			'post_type'      => 'nqa_watch',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		$w = nqa_scrobble_watch( (int) $id );
		if ( ! $active_only || $w['active'] ) {
			$out[] = $w;
		}
	}
	return $out;
}

// ── Editor for a watched source ────────────────────────────────────────────

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'nqa_watch_config', 'Source', 'nqa_watch_config_box', 'nqa_watch', 'normal', 'high' );
		add_meta_box( 'nqa_watch_status', 'Last run', 'nqa_watch_status_box', 'nqa_watch', 'side', 'default' );
	}
);

function nqa_watch_config_box( WP_Post $post ) : void {
	wp_nonce_field( 'nqa_watch_save', 'nqa_watch_nonce' );
	$w = nqa_scrobble_watch( $post->ID );

	echo '<style>.nqa-watch th{text-align:left;padding:.6rem .9rem .6rem 0;vertical-align:top;width:11rem}'
		. '.nqa-watch td{padding:.6rem 0}.nqa-watch input[type=text]{width:100%}'
		. '.nqa-watch .description{margin:.3rem 0 0}</style>';
	echo '<table class="nqa-watch"><tbody>';

	echo '<tr><th><label for="nqa_watch_kind">Kind</label></th><td><select name="nqa_watch_kind" id="nqa_watch_kind">';
	foreach ( nqa_scrobble_kinds() as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $w['kind'], $slug, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">A calendar feed (.ics) is by far the most reliable. Social posts are the least — they carry no date field, so a person has to read them.</p></td></tr>';

	printf(
		'<tr><th><label for="nqa_watch_target">URL or handle</label></th><td>'
		. '<input type="text" name="nqa_watch_target" id="nqa_watch_target" value="%s" placeholder="https://example.org/events/ &nbsp;·&nbsp; #niagarapride &nbsp;·&nbsp; @prideniagara">'
		. '<p class="description">A feed/page URL, or — for Bluesky and Instagram — a <code>#hashtag</code> or <code>@account</code>.</p></td></tr>',
		esc_attr( $w['target'] )
	);

	echo '<tr><th>Filter</th><td>';
	printf(
		'<label><input type="radio" name="nqa_watch_mode" value="strict"%s> <strong>Only queer-relevant items</strong> — keyword filter (use for municipal calendars, news feeds, hashtags)</label><br>',
		checked( $w['mode'], 'strict', false )
	);
	printf(
		'<label><input type="radio" name="nqa_watch_mode" value="all"%s> <strong>Everything from this source</strong> — the whole calendar belongs in the archive (use for Pride Niagara, OUTniagara, etc.)</label>',
		checked( $w['mode'], 'all', false )
	);
	echo '</td></tr>';

	echo '<tr><th><label for="nqa_watch_muni">Default municipality</label></th><td><select name="nqa_watch_muni" id="nqa_watch_muni"><option value="">— none —</option>';
	foreach ( get_terms( array( 'taxonomy' => 'municipality', 'hide_empty' => false ) ) as $term ) {
		if ( is_wp_error( $term ) || ! isset( $term->slug ) ) {
			continue;
		}
		printf( '<option value="%s"%s>%s</option>', esc_attr( $term->slug ), selected( $w['muni'], $term->slug, false ), esc_html( $term->name ) );
	}
	echo '</select><p class="description">Applied when an item names no place of its own. Setting this also satisfies the geographic half of the filter, so a Welland-only calendar need not say "Welland" in every listing.</p></td></tr>';

	echo '<tr><th><label for="nqa_watch_org">Organizer</label></th><td><select name="nqa_watch_org" id="nqa_watch_org"><option value="">— none —</option>';
	foreach ( get_posts( array( 'post_type' => 'nqa_org', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $org ) {
		printf( '<option value="%d"%s>%s</option>', (int) $org->ID, selected( $w['org'], (int) $org->ID, false ), esc_html( nqa_decode_entities( $org->post_title ) ) );
	}
	echo '</select><p class="description">The Organization record this source belongs to, if any — suggested on every item it produces.</p></td></tr>';

	echo '<tr><th>Options</th><td>';
	printf( '<label><input type="checkbox" name="nqa_watch_active" value="1"%s> Active — include in scheduled runs</label><br>', checked( $w['active'], true, false ) );
	printf( '<label><input type="checkbox" name="nqa_watch_permission" value="1"%s> This organization has given us permission to read their site</label>', checked( $w['permission'], true, false ) );
	echo '<p class="description">Without permission we honour the site\'s robots.txt and skip anything it disallows. Only tick this when a person has actually said yes — record who and when in the outreach log.</p>';
	echo '</td></tr>';

	echo '</tbody></table>';
}

function nqa_watch_status_box( WP_Post $post ) : void {
	$when   = get_post_meta( $post->ID, '_nqa_watch_last_run', true );
	$status = get_post_meta( $post->ID, '_nqa_watch_last_status', true );
	if ( ! $when ) {
		echo '<p class="description">Never run. Save this source, then run <code>wp nqa watch test ' . (int) $post->ID . '</code> to try it without writing anything.</p>';
		return;
	}
	echo '<p><strong>' . esc_html( $when ) . '</strong><br>' . esc_html( $status ?: '—' ) . '</p>';
	printf(
		'<p class="description">Found %d · queued %d · filtered out %d · already known %d</p>',
		(int) get_post_meta( $post->ID, '_nqa_watch_found', true ),
		(int) get_post_meta( $post->ID, '_nqa_watch_queued', true ),
		(int) get_post_meta( $post->ID, '_nqa_watch_filtered', true ),
		(int) get_post_meta( $post->ID, '_nqa_watch_dupes', true )
	);
}

add_action(
	'save_post_nqa_watch',
	function ( $post_id ) {
		if ( ! isset( $_POST['nqa_watch_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nqa_watch_nonce'] ) ), 'nqa_watch_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$kind = sanitize_text_field( wp_unslash( $_POST['nqa_watch_kind'] ?? '' ) );
		update_post_meta( $post_id, '_nqa_watch_kind', isset( nqa_scrobble_kinds()[ $kind ] ) ? $kind : 'ics' );

		$target = trim( wp_unslash( $_POST['nqa_watch_target'] ?? '' ) );
		// Handles (#tag / @account) are not URLs — only run esc_url_raw on URLs.
		update_post_meta(
			$post_id,
			'_nqa_watch_target',
			preg_match( '#^https?://|^webcal://#i', $target ) ? esc_url_raw( $target ) : sanitize_text_field( $target )
		);

		update_post_meta( $post_id, '_nqa_watch_mode', ( ( $_POST['nqa_watch_mode'] ?? '' ) === 'all' ) ? 'all' : 'strict' );
		update_post_meta( $post_id, '_nqa_watch_muni', sanitize_title( wp_unslash( $_POST['nqa_watch_muni'] ?? '' ) ) );
		update_post_meta( $post_id, '_nqa_watch_org', (int) ( $_POST['nqa_watch_org'] ?? 0 ) );
		update_post_meta( $post_id, '_nqa_watch_active', empty( $_POST['nqa_watch_active'] ) ? '0' : '1' );
		update_post_meta( $post_id, '_nqa_watch_permission', empty( $_POST['nqa_watch_permission'] ) ? '0' : '1' );
	}
);

// ── Watched-source list columns ────────────────────────────────────────────

add_filter(
	'manage_nqa_watch_posts_columns',
	function ( array $cols ) : array {
		unset( $cols['date'] );
		return array_merge(
			array( 'cb' => $cols['cb'] ?? '<input type="checkbox">', 'title' => 'Source' ),
			array(
				'nqa_watch_kind'   => 'Kind',
				'nqa_watch_target' => 'Watching',
				'nqa_watch_mode'   => 'Filter',
				'nqa_watch_run'    => 'Last run',
			)
		);
	}
);

add_action(
	'manage_nqa_watch_posts_custom_column',
	function ( string $col, int $post_id ) : void {
		$w = nqa_scrobble_watch( $post_id );
		switch ( $col ) {
			case 'nqa_watch_kind':
				echo esc_html( nqa_scrobble_kinds()[ $w['kind'] ] ?? $w['kind'] ?: '—' );
				if ( ! $w['active'] ) {
					echo ' <em style="color:#b71c1c">(paused)</em>';
				}
				break;
			case 'nqa_watch_target':
				echo '<code style="font-size:.8em">' . esc_html( $w['target'] ?: '—' ) . '</code>';
				break;
			case 'nqa_watch_mode':
				echo esc_html( 'all' === $w['mode'] ? 'Everything' : 'Queer-relevant only' );
				break;
			case 'nqa_watch_run':
				$when = get_post_meta( $post_id, '_nqa_watch_last_run', true );
				echo $when
					? esc_html( $when ) . '<br><span style="color:#666">' . esc_html( (string) get_post_meta( $post_id, '_nqa_watch_last_status', true ) ) . '</span>'
					: '<span style="color:#666">never</span>';
				break;
		}
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * 2. Relevance filter
 *
 * Municipal calendars, news feeds, and hashtags are firehoses. A candidate in
 * `strict` mode has to look queer AND look like Niagara before a human is asked
 * to spend attention on it. The filter only ever decides what to SHOW someone —
 * it never edits a record — so a false positive costs one glance and a false
 * negative costs an event, which is why the vocabulary below is generous.
 * ---------------------------------------------------------------------- */

/** Terms that mark an item as queer-relevant. Filterable. */
function nqa_scrobble_keywords() : array {
	return (array) apply_filters(
		'nqa_scrobble_keywords',
		array(
			'lgbt', 'lgbtq', '2slgbtq', 'lgbtq2s', 'queer', 'pride', 'rainbow',
			'gay', 'lesbian', 'bisexual', 'sapphic', 'dyke',
			'trans', 'transgender', 'trans+', 'nonbinary', 'non-binary', 'genderqueer',
			'gender-diverse', 'two spirit', 'two-spirit', '2-spirit', '2spirit',
			'drag', 'drag queen', 'drag king', 'ballroom', 'voguing',
			'coming out', 'chosen family', 'gsa', 'pflag', 'outniagara',
			'hiv', 'aids', 'sexual health', 'safe space', 'affirming',
			'intersex', 'asexual', 'aromantic', 'polyamor', 'kink',
			'transgender day', 'idahobit', 'day of silence', 'pronoun',
		)
	);
}

/** Terms that mark an item as being in Niagara. Derived from the taxonomy. */
function nqa_scrobble_geo_terms() : array {
	$terms = get_terms( array( 'taxonomy' => 'municipality', 'hide_empty' => false, 'fields' => 'names' ) );
	$names = is_wp_error( $terms ) ? array() : array_map( 'strtolower', (array) $terms );
	return (array) apply_filters(
		'nqa_scrobble_geo_terms',
		array_values( array_unique( array_merge( $names, array( 'niagara', 'crystal beach', 'ridgeway', 'fonthill', 'jordan', 'beamsville', 'virgil', 'queenston', 'chippawa', 'brock university', 'niagara college' ) ) ) )
	);
}

/**
 * Score a candidate against a watched source's filter mode.
 * Returns [ 'pass' => bool, 'score' => int, 'queer' => string[], 'geo' => string[], 'why' => string ].
 */
function nqa_scrobble_relevance( array $cand, array $watch ) : array {
	$haystack = strtolower(
		trim( ( $cand['title'] ?? '' ) . ' ' . ( $cand['description'] ?? '' ) . ' ' . ( $cand['venue'] ?? '' ) . ' ' . ( $cand['organizer'] ?? '' ) )
	);

	$queer = array();
	foreach ( nqa_scrobble_keywords() as $kw ) {
		// Word-ish boundary so "drag" doesn't fire on "dragon" or "gay" on "gayle".
		if ( preg_match( '/(?<![a-z])' . preg_quote( strtolower( $kw ), '/' ) . '(?![a-z])/u', $haystack ) ) {
			$queer[] = $kw;
		}
	}
	$geo = array();
	foreach ( nqa_scrobble_geo_terms() as $place ) {
		if ( '' !== $place && false !== strpos( $haystack, $place ) ) {
			$geo[] = $place;
		}
	}

	// A source with a default municipality is geographically vouched for already.
	$geo_ok = ( ! empty( $geo ) || '' !== ( $watch['muni'] ?? '' ) );
	$score  = count( $queer ) * 2 + count( $geo );

	if ( 'all' === ( $watch['mode'] ?? 'strict' ) ) {
		return array( 'pass' => true, 'score' => $score, 'queer' => $queer, 'geo' => $geo, 'why' => 'Source is set to capture everything.' );
	}
	if ( ! $queer ) {
		return array( 'pass' => false, 'score' => $score, 'queer' => $queer, 'geo' => $geo, 'why' => 'No queer-relevant term found.' );
	}
	if ( ! $geo_ok ) {
		return array( 'pass' => false, 'score' => $score, 'queer' => $queer, 'geo' => $geo, 'why' => 'Nothing places this in Niagara (set a default municipality on the source if it is region-specific).' );
	}
	return array(
		'pass'  => true,
		'score' => $score,
		'queer' => $queer,
		'geo'   => $geo,
		'why'   => 'Matched: ' . implode( ', ', array_slice( $queer, 0, 4 ) ),
	);
}

/* -------------------------------------------------------------------------
 * 3. Dedupe
 * ---------------------------------------------------------------------- */

/** Normalize a title for comparison: lowercase, alphanumerics and spaces only. */
function nqa_scrobble_normalize_title( string $title ) : string {
	$t = strtolower( nqa_decode_entities( $title ) );
	$t = preg_replace( '/[^a-z0-9 ]+/u', ' ', $t );
	return trim( preg_replace( '/\s+/', ' ', (string) $t ) );
}

/** Content-identity key for a candidate: normalized title + start date. */
function nqa_scrobble_key( array $cand ) : string {
	return sha1( nqa_scrobble_normalize_title( (string) $cand['title'] ) . '|' . (string) $cand['start'] );
}

/**
 * Map of "normalized title|Ymd" => event ID for every event already in the
 * archive, at any status. Built once per run: the event set is small, and one
 * query beats one lookup per candidate.
 */
function nqa_scrobble_existing_events() : array {
	$map = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'nqa_event',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	) as $p ) {
		$start = (string) get_field( 'start_date', $p->ID );
		$ymd   = $start ? gmdate( 'Ymd', strtotime( $start ) ) : '';
		$norm  = nqa_scrobble_normalize_title( $p->post_title );
		$map[ $norm . '|' . $ymd ] = (int) $p->ID;
		// Also index title-only, so a re-run that reads a different date for the
		// same event does not create a second copy of it.
		$map[ $norm . '|' ] = (int) $p->ID;
	}
	return $map;
}

/** True when this source has already produced a submission for this item. */
function nqa_scrobble_seen( string $meta_key, string $value ) : bool {
	$hit = get_posts(
		array(
			'post_type'      => 'nqa_submission',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => $meta_key,
			'meta_value'     => $value,
		)
	);
	return ! empty( $hit );
}

/* -------------------------------------------------------------------------
 * 4. Queue a candidate as a submission
 * ---------------------------------------------------------------------- */

/**
 * Store one candidate as a private `nqa_submission` of kind `event` — the exact
 * shape `events.php` already reviews and converts. Returns the submission ID.
 */
function nqa_scrobble_queue( array $cand, array $watch, array $rel ) : int {
	$title = trim( (string) $cand['title'] ) ?: ( 'Scrobbled item — ' . current_time( 'Y-m-d H:i' ) );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'nqa_submission',
			'post_title'   => $title,
			'post_content' => '', // archivist notes
			'post_status'  => 'private',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return 0;
	}

	// Everything a reviewer needs to check the machine's work, in one place.
	$notes = array_filter(
		array(
			sprintf( 'Found automatically on %s (%s).', $watch['label'], nqa_scrobble_kinds()[ $watch['kind'] ] ?? $watch['kind'] ),
			$cand['note'] ?? '',
			'' === (string) $cand['start'] ? 'NO DATE — read the source and set the dates by hand.' : '',
			'' !== (string) ( $cand['image'] ?? '' ) ? 'Poster image at ' . $cand['image'] . ' — not copied; obtain permission before using it.' : '',
			'Nothing here has been verified. Confirm against the source before publishing.',
		)
	);

	$meta = array(
		// The submission shape events.php reads.
		'_nqa_sub_kind'         => 'event',
		'_nqa_sub_title'        => $title,
		'_nqa_sub_name'         => $cand['organizer'] ?: $watch['label'],
		'_nqa_sub_email'        => '',
		'_nqa_sub_phone'        => '',
		'_nqa_sub_type'         => 'Event',
		'_nqa_sub_story'        => (string) $cand['description'], // VERBATIM.
		'_nqa_sub_loc'          => $watch['muni'],
		'_nqa_sub_credit'       => 'source credited',
		'_nqa_sub_evt_start'    => (string) $cand['start'],
		'_nqa_sub_evt_end'      => (string) $cand['end'],
		'_nqa_sub_evt_venue'    => (string) $cand['venue'],
		'_nqa_sub_evt_org'      => (string) $cand['organizer'],
		'_nqa_sub_evt_link'     => (string) $cand['url'],
		'_nqa_sub_user'         => 0,
		'_nqa_sub_status'       => 'pending',
		// Scrobbler provenance. `staff-research` is the honest vocabulary term:
		// no one submitted this, the archive went and found it.
		'_nqa_sub_origin'       => 'scrobble',
		'_nqa_sub_provenance'   => 'staff-research',
		'_nqa_scrobble_source'  => $watch['id'],
		'_nqa_scrobble_uid'     => $watch['id'] . '|' . $cand['uid'],
		'_nqa_scrobble_key'     => nqa_scrobble_key( $cand ),
		'_nqa_scrobble_score'   => (int) $rel['score'],
		'_nqa_scrobble_why'     => (string) $rel['why'],
		'_nqa_scrobble_image'   => (string) ( $cand['image'] ?? '' ),
		'_nqa_scrobble_notes'   => implode( ' ', $notes ),
		'_nqa_scrobble_org_ref' => (int) $watch['org'],
		// Raw payload, capped — the source page will be gone long before we need it.
		'_nqa_scrobble_raw'     => mb_substr( (string) ( $cand['raw'] ?? '' ), 0, 20000 ),
		'_nqa_scrobble_at'      => current_time( 'mysql' ),
	);
	foreach ( $meta as $key => $val ) {
		update_post_meta( $post_id, $key, $val );
	}

	// A recurring source item keeps its recurrence text for the converted record.
	if ( ! empty( $cand['recurrence'] ) ) {
		update_post_meta( $post_id, '_nqa_sub_evt_recurrence', (string) $cand['recurrence'] );
	}

	return (int) $post_id;
}

/* -------------------------------------------------------------------------
 * 5. The run
 * ---------------------------------------------------------------------- */

/**
 * Read every (or one) watched source and queue what is new.
 *
 * @param array $args  sources: int[] watch IDs (default: all active)
 *                     dry_run: bool  — report only, write nothing
 *                     no_filter: bool — report items the filter would drop
 *                     limit: int     — max queued per source (0 = no cap)
 *                     log: callable  — line logger, for CLI output
 * @return array  Per-source report rows.
 */
function nqa_scrobble_run( array $args = array() ) : array {
	$dry       = ! empty( $args['dry_run'] );
	$no_filter = ! empty( $args['no_filter'] );
	$limit     = (int) ( $args['limit'] ?? 0 );
	$log       = $args['log'] ?? function () {};

	$watches = array();
	if ( ! empty( $args['sources'] ) ) {
		foreach ( (array) $args['sources'] as $id ) {
			$watches[] = nqa_scrobble_watch( (int) $id );
		}
	} else {
		$watches = nqa_scrobble_watches( true );
	}

	$existing = nqa_scrobble_existing_events();
	$today    = current_time( 'Ymd' );
	$report   = array();

	foreach ( $watches as $w ) {
		$row = array(
			'watch'    => $w,
			'error'    => '',
			'found'    => 0,
			'queued'   => 0,
			'filtered' => 0,
			'dupes'    => 0,
			'past'     => 0,
			'items'    => array(),
		);

		$log( sprintf( '→ %s [%s] %s', $w['label'], $w['kind'], $w['target'] ) );

		$cands = nqa_scrobble_read_source( $w );
		if ( is_wp_error( $cands ) ) {
			$row['error'] = $cands->get_error_message();
			$log( '   ! ' . $row['error'] );
			if ( ! $dry ) {
				nqa_scrobble_record_run( $w['id'], 'Error: ' . $row['error'], $row );
			}
			$report[] = $row;
			continue;
		}

		$row['found'] = count( $cands );

		foreach ( $cands as $cand ) {
			// Past events: the archive wants them eventually, but not discovered
			// blind by a robot — an event we never saw announced needs a source
			// and a human, not an automatic listing.
			$when = $cand['end'] ?: $cand['start'];
			if ( '' !== $when && $when < $today && empty( $cand['recurrence'] ) ) {
				$row['past']++;
				continue;
			}

			$rel = nqa_scrobble_relevance( $cand, $w );
			if ( ! $rel['pass'] && ! $no_filter ) {
				$row['filtered']++;
				continue;
			}

			$key = nqa_scrobble_key( $cand );
			$uid = $w['id'] . '|' . $cand['uid'];
			if ( nqa_scrobble_seen( '_nqa_scrobble_uid', $uid ) || nqa_scrobble_seen( '_nqa_scrobble_key', $key ) ) {
				$row['dupes']++;
				continue;
			}
			$norm = nqa_scrobble_normalize_title( (string) $cand['title'] );
			if ( isset( $existing[ $norm . '|' . $cand['start'] ] ) || isset( $existing[ $norm . '|' ] ) ) {
				$row['dupes']++;
				$log( sprintf( '   = %s — already an event record', $cand['title'] ) );
				continue;
			}

			if ( $limit > 0 && $row['queued'] >= $limit ) {
				break;
			}

			$row['items'][] = array(
				'title' => $cand['title'],
				'start' => $cand['start'],
				'pass'  => $rel['pass'],
				'why'   => $rel['why'],
				'url'   => $cand['url'],
			);

			if ( $dry ) {
				$log( sprintf( '   ~ %-52s %s  [%s]', mb_substr( $cand['title'], 0, 52 ), $cand['start'] ?: '(no date)', $rel['why'] ) );
				$row['queued']++;
				continue;
			}

			$sub_id = nqa_scrobble_queue( $cand, $w, $rel );
			if ( $sub_id ) {
				$row['queued']++;
				// Keep the in-memory index current so two sources announcing the
				// same event in one run only queue it once.
				$existing[ $norm . '|' . $cand['start'] ] = $sub_id;
				$log( sprintf( '   + #%d %-48s %s', $sub_id, mb_substr( $cand['title'], 0, 48 ), $cand['start'] ?: '(no date)' ) );
			}
		}

		$status = sprintf( '%d found · %d queued · %d filtered · %d known · %d past', $row['found'], $row['queued'], $row['filtered'], $row['dupes'], $row['past'] );
		$log( '   ' . $status );
		if ( ! $dry ) {
			nqa_scrobble_record_run( $w['id'], $status, $row );
		}
		$report[] = $row;
	}

	return $report;
}

/** Write the outcome of a run back onto the watched source. */
function nqa_scrobble_record_run( int $watch_id, string $status, array $row ) : void {
	update_post_meta( $watch_id, '_nqa_watch_last_run', current_time( 'mysql' ) );
	update_post_meta( $watch_id, '_nqa_watch_last_status', $status );
	update_post_meta( $watch_id, '_nqa_watch_found', (int) $row['found'] );
	update_post_meta( $watch_id, '_nqa_watch_queued', (int) $row['queued'] );
	update_post_meta( $watch_id, '_nqa_watch_filtered', (int) $row['filtered'] );
	update_post_meta( $watch_id, '_nqa_watch_dupes', (int) $row['dupes'] );
}

/* -------------------------------------------------------------------------
 * 6. Schedule
 *
 * Daily is right: event announcements are not breaking news, and a slow cadence
 * keeps us light on other people's servers. Define NQA_SCROBBLE_DISABLE to stop
 * scheduled runs (the CLI still works).
 *
 * WP-Cron only fires when someone visits the site, which an archive cannot rely
 * on. On production, prefer a real cron entry calling `wp nqa scrobble` — see
 * docs/FOR-DEVS.md. This schedule is the backstop for when that is not set up.
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		$disabled = defined( 'NQA_SCROBBLE_DISABLE' ) && NQA_SCROBBLE_DISABLE;
		if ( $disabled ) {
			$next = wp_next_scheduled( NQA_SCROBBLE_CRON );
			if ( $next ) {
				wp_unschedule_event( $next, NQA_SCROBBLE_CRON );
			}
			return;
		}
		if ( ! wp_next_scheduled( NQA_SCROBBLE_CRON ) ) {
			// 04:00 site time — after overnight posting, before anyone reviews.
			// wp_schedule_event() wants UTC, so convert back off the site offset.
			$local = strtotime( 'tomorrow 04:00', current_time( 'timestamp' ) );
			$utc   = $local - (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			wp_schedule_event( $utc, 'daily', NQA_SCROBBLE_CRON );
		}
	}
);

add_action( NQA_SCROBBLE_CRON, function () { nqa_scrobble_run(); } );

/* -------------------------------------------------------------------------
 * 7. Review surface
 *
 * Scrobbled items sit in the same Submissions queue as community submissions, so
 * there is one place to look. What they need on top is an audit trail: where this
 * came from, why the filter kept it, and a blunt reminder that a machine wrote
 * none of it down from a person.
 * ---------------------------------------------------------------------- */

// Only on scrobbled submissions — a form submission's review screen is already
// busy enough without a box explaining that a robot was not involved.
add_action(
	'add_meta_boxes',
	function ( $post_type, $post ) {
		if ( 'nqa_submission' !== $post_type || ! ( $post instanceof WP_Post ) ) {
			return;
		}
		if ( 'scrobble' !== get_post_meta( $post->ID, '_nqa_sub_origin', true ) ) {
			return;
		}
		add_meta_box( 'nqa_scrobble_origin', 'Where this came from', 'nqa_scrobble_origin_box', 'nqa_submission', 'normal', 'high' );
	},
	10,
	2
);

function nqa_scrobble_origin_box( WP_Post $post ) : void {
	$m       = fn( $k ) => get_post_meta( $post->ID, $k, true );
	$watch   = (int) $m( '_nqa_scrobble_source' );
	$url     = (string) $m( '_nqa_sub_evt_link' );
	$image   = (string) $m( '_nqa_scrobble_image' );

	echo '<div style="border-left:4px solid #503AA8;padding:.25rem 0 .25rem 1rem">';
	echo '<p style="margin-top:0"><strong>Found automatically.</strong> Nobody submitted this and nobody has checked it. '
		. 'Verify the details against the source, then convert it with the “Create archive record” box — it will be a draft with consent Pending.</p>';

	echo '<table class="nqa-sub-table"><tbody>';
	printf(
		'<tr><th>Source</th><td>%s</td></tr>',
		$watch && get_post( $watch )
			? '<a href="' . esc_url( get_edit_post_link( $watch ) ) . '">' . esc_html( get_the_title( $watch ) ) . '</a>'
			: '<em>source deleted</em>'
	);
	if ( $url ) {
		printf( '<tr><th>Original</th><td><a href="%1$s" target="_blank" rel="noopener nofollow">%1$s</a></td></tr>', esc_url( $url ) );
	}
	printf( '<tr><th>Why it was kept</th><td>%s <span style="color:#666">(score %d)</span></td></tr>', esc_html( (string) $m( '_nqa_scrobble_why' ) ), (int) $m( '_nqa_scrobble_score' ) );
	printf( '<tr><th>Captured</th><td>%s</td></tr>', esc_html( (string) $m( '_nqa_scrobble_at' ) ) );
	if ( $image ) {
		printf(
			'<tr><th>Poster image</th><td><a href="%1$s" target="_blank" rel="noopener nofollow">%1$s</a><br><span style="color:#666">Recorded only — not copied. Get permission before using it.</span></td></tr>',
			esc_url( $image )
		);
	}
	echo '</tbody></table>';

	printf( '<p><strong>Checks needed:</strong> %s</p>', esc_html( (string) $m( '_nqa_scrobble_notes' ) ) );

	$raw = (string) $m( '_nqa_scrobble_raw' );
	if ( '' !== $raw ) {
		echo '<details><summary style="cursor:pointer">Raw captured payload</summary>'
			. '<pre style="max-height:16rem;overflow:auto;background:#f6f7f7;padding:.75rem;font-size:11px;white-space:pre-wrap">'
			. esc_html( $raw ) . '</pre></details>';
	}
	echo '</div>';
}

// Mark scrobbled rows in the Submissions list, so a reviewer can see at a glance
// which items came from a person and which from a machine.
add_filter(
	'manage_nqa_submission_posts_columns',
	function ( array $cols ) : array {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'nqa_sub_who' === $key ) {
				$out['nqa_sub_origin'] = 'Origin';
			}
		}
		return isset( $out['nqa_sub_origin'] ) ? $out : array_merge( $out, array( 'nqa_sub_origin' => 'Origin' ) );
	},
	20
);

add_action(
	'manage_nqa_submission_posts_custom_column',
	function ( string $col, int $post_id ) : void {
		if ( 'nqa_sub_origin' !== $col ) {
			return;
		}
		if ( 'scrobble' !== get_post_meta( $post_id, '_nqa_sub_origin', true ) ) {
			echo '<span style="color:#666">Submitted</span>';
			return;
		}
		$watch = (int) get_post_meta( $post_id, '_nqa_scrobble_source', true );
		echo '<span style="display:inline-block;padding:.15rem .55rem;background:#111;color:#FFEE58;'
			. 'font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Found</span><br>'
			. '<span style="color:#666;font-size:.85em">' . esc_html( $watch ? get_the_title( $watch ) : '—' ) . '</span>';
	},
	20,
	2
);

/* -------------------------------------------------------------------------
 * 8. WP-CLI
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Read the watched sources and queue newly-found events for review.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<id>]
	 * : Run one watched source (by post ID), including paused ones.
	 *
	 * [--dry-run]
	 * : Report what would be queued. Writes nothing.
	 *
	 * [--no-filter]
	 * : Include items the relevance filter would drop — use with --dry-run to
	 *   see what a source holds and tune the filter.
	 *
	 * [--limit=<n>]
	 * : Cap how many items each source may queue in this run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nqa scrobble --dry-run
	 *     wp nqa scrobble --source=812 --no-filter --dry-run
	 *     wp nqa scrobble --limit=10
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command(
		'nqa scrobble',
		function ( $args, $assoc ) {
			$report = nqa_scrobble_run(
				array(
					'sources'   => empty( $assoc['source'] ) ? array() : array( (int) $assoc['source'] ),
					'dry_run'   => isset( $assoc['dry-run'] ),
					'no_filter' => isset( $assoc['no-filter'] ),
					'limit'     => (int) ( $assoc['limit'] ?? 0 ),
					'log'       => function ( $line ) {
						WP_CLI::log( $line );
					},
				)
			);

			if ( ! $report ) {
				WP_CLI::warning( 'No active watched sources. Add one in Submissions → Watched Sources, or with `wp nqa watch add`.' );
				return;
			}

			$totals = array( 'found' => 0, 'queued' => 0, 'filtered' => 0, 'dupes' => 0, 'past' => 0, 'errors' => 0 );
			foreach ( $report as $row ) {
				foreach ( array( 'found', 'queued', 'filtered', 'dupes', 'past' ) as $k ) {
					$totals[ $k ] += (int) $row[ $k ];
				}
				$totals['errors'] += $row['error'] ? 1 : 0;
			}

			WP_CLI::success(
				sprintf(
					'%d sources · %d items seen · %d %s · %d filtered out · %d already known · %d past · %d sources errored.',
					count( $report ),
					$totals['found'],
					$totals['queued'],
					isset( $assoc['dry-run'] ) ? 'would be queued' : 'queued for review',
					$totals['filtered'],
					$totals['dupes'],
					$totals['past'],
					$totals['errors']
				)
			);
			if ( $totals['queued'] && ! isset( $assoc['dry-run'] ) ) {
				WP_CLI::log( 'Review them under Submissions → Pending. Nothing is published until a person converts it.' );
			}
		}
	);

	/**
	 * Manage watched sources.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | add | test
	 *
	 * [<id>]
	 * : Watched source ID (for `test`).
	 *
	 * [--label=<label>]
	 * : Name of the source (for `add`).
	 *
	 * [--kind=<kind>]
	 * : ics | page | rss | tribe | bluesky | instagram (for `add`).
	 *
	 * [--target=<target>]
	 * : URL, #hashtag, or @account (for `add`).
	 *
	 * [--mode=<mode>]
	 * : strict (default — queer-relevant items only) | all (whole source).
	 *
	 * [--municipality=<slug>]
	 * : Default municipality slug, e.g. st-catharines.
	 *
	 * [--org=<id>]
	 * : Organization record this source belongs to.
	 *
	 * [--permission]
	 * : The organization has given us permission to read their site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nqa watch list
	 *     wp nqa watch add --label="Pride Niagara calendar" --kind=ics \
	 *       --target="https://example.org/events.ics" --mode=all --org=270
	 *     wp nqa watch test 812
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command(
		'nqa watch',
		function ( $args, $assoc ) {
			$action = $args[0] ?? 'list';

			if ( 'list' === $action ) {
				$rows = array();
				foreach ( nqa_scrobble_watches( false ) as $w ) {
					$rows[] = array(
						'ID'      => $w['id'],
						'source'  => $w['label'],
						'kind'    => $w['kind'],
						'target'  => mb_strimwidth( $w['target'], 0, 52, '…' ),
						'filter'  => 'all' === $w['mode'] ? 'everything' : 'queer-only',
						'active'  => $w['active'] ? 'yes' : 'paused',
						'last'    => (string) get_post_meta( $w['id'], '_nqa_watch_last_status', true ),
					);
				}
				if ( ! $rows ) {
					WP_CLI::warning( 'No watched sources yet.' );
					return;
				}
				WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'source', 'kind', 'target', 'filter', 'active', 'last' ) );
				return;
			}

			if ( 'add' === $action ) {
				$kind   = (string) ( $assoc['kind'] ?? '' );
				$target = (string) ( $assoc['target'] ?? '' );
				if ( ! isset( nqa_scrobble_kinds()[ $kind ] ) ) {
					WP_CLI::error( 'Unknown --kind. One of: ' . implode( ', ', array_keys( nqa_scrobble_kinds() ) ) );
				}
				if ( '' === $target ) {
					WP_CLI::error( '--target is required (a URL, #hashtag, or @account).' );
				}

				$id = wp_insert_post(
					array(
						'post_type'   => 'nqa_watch',
						'post_status' => 'publish',
						'post_title'  => (string) ( $assoc['label'] ?? $target ),
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					WP_CLI::error( $id->get_error_message() );
				}
				update_post_meta( $id, '_nqa_watch_kind', $kind );
				update_post_meta( $id, '_nqa_watch_target', $target );
				update_post_meta( $id, '_nqa_watch_mode', ( ( $assoc['mode'] ?? 'strict' ) === 'all' ) ? 'all' : 'strict' );
				update_post_meta( $id, '_nqa_watch_muni', sanitize_title( (string) ( $assoc['municipality'] ?? '' ) ) );
				update_post_meta( $id, '_nqa_watch_org', (int) ( $assoc['org'] ?? 0 ) );
				update_post_meta( $id, '_nqa_watch_active', '1' );
				update_post_meta( $id, '_nqa_watch_permission', isset( $assoc['permission'] ) ? '1' : '0' );

				WP_CLI::success( sprintf( 'Watching #%d — %s. Try it with: wp nqa watch test %d', $id, get_the_title( $id ), $id ) );
				return;
			}

			if ( 'test' === $action ) {
				$id = (int) ( $args[1] ?? 0 );
				if ( ! $id || 'nqa_watch' !== get_post_type( $id ) ) {
					WP_CLI::error( 'Pass a watched source ID: wp nqa watch test <id>' );
				}
				$w     = nqa_scrobble_watch( $id );
				$cands = nqa_scrobble_read_source( $w );
				if ( is_wp_error( $cands ) ) {
					WP_CLI::error( $cands->get_error_message() );
				}
				WP_CLI::log( sprintf( '%s — %d items read.', $w['label'], count( $cands ) ) );

				$rows = array();
				foreach ( $cands as $c ) {
					$rel    = nqa_scrobble_relevance( $c, $w );
					$rows[] = array(
						'keep'  => $rel['pass'] ? 'yes' : 'no',
						'start' => $c['start'] ?: '(none)',
						'title' => mb_strimwidth( $c['title'], 0, 46, '…' ),
						'venue' => mb_strimwidth( $c['venue'], 0, 28, '…' ),
						'why'   => mb_strimwidth( $rel['why'], 0, 44, '…' ),
					);
				}
				if ( $rows ) {
					WP_CLI\Utils\format_items( 'table', $rows, array( 'keep', 'start', 'title', 'venue', 'why' ) );
				}
				WP_CLI::success( 'Read-only test — nothing was written.' );
				return;
			}

			WP_CLI::error( 'Unknown action. Use: list | add | test' );
		}
	);
}
