<?php
/**
 * Event intake stream — "List an Event".
 *
 * A second community-contribution channel, parallel to Tell Your Story
 * (`submissions.php`). It captures upcoming events so each gets a linkable,
 * preservable page (useful precisely when the event has no other home online),
 * and that same page lives on as the archival record once the date passes —
 * promo now, primary source later. "Upcoming"/"past" is a display label computed
 * from the dates (see `nqa_event_is_upcoming()`), never a post type or DB status.
 *
 *   1. Capture: a dedicated CF7 form (marked with a hidden `nqa-intake=event`
 *      field) is caught here — independently of the story form's hook — and
 *      stored as a private `nqa_submission` tagged `_nqa_sub_kind = event`.
 *   2. Auto-publish (RBAC): if the submitter is an authenticated user holding the
 *      `nqa_publish_events` capability AND supplied an address, the submission is
 *      converted straight to a PUBLISHED `nqa_event` (consent self-granted by the
 *      organizer). Everyone else queues as a draft for archivist review — the
 *      same path as the story stream.
 *   3. Converter: `nqa_create_event_from_submission()` maps the submitted fields
 *      to the event record, preserving the contributor's description verbatim and
 *      never setting the map pin (rule #8 — an archivist pins/links a Venue on
 *      review; the required address is stored so `wp nqa geocode` can seed it).
 *
 * The CF7 form and the `/list-an-event/` page are prod content (see
 * docs/FOR-DEVS.md); this file is the code that reads them. Expected form field
 * `name` attributes: nqa-intake (hidden = "event"), event-name, start-date,
 * end-date, venue, organizer, event-municipality, existing-link, your-message,
 * your-name, your-email, your-telephone, file-upload, credit_preference.
 */

defined( 'ABSPATH' ) || exit;

// ── 1. Trusted-publisher capability (RBAC) ──────────────────────────────────

/**
 * Ensure the `nqa_publish_events` capability exists on administrators. Onboard
 * individual organizers with `wp nqa trust-publisher <user>` (below), which grants
 * the same capability to their account regardless of role.
 */
add_action(
	'init',
	function () {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( 'nqa_publish_events' ) ) {
			$role->add_cap( 'nqa_publish_events' );
		}
	}
);

/** Whether a user may auto-publish event submissions. Defaults to current user. */
function nqa_user_can_publish_events( int $user_id = 0 ) : bool {
	$user_id = $user_id ?: get_current_user_id();
	return $user_id > 0 && user_can( $user_id, 'nqa_publish_events' );
}

// ── 2. CF7 capture (event form only) ────────────────────────────────────────

add_action(
	'wpcf7_before_send_mail',
	function ( $contact_form, &$abort, $submission ) {
		if ( ! ( $contact_form instanceof WPCF7_ContactForm ) ) {
			return;
		}
		$data = $submission->get_posted_data();

		// Only handle the event form; the story form (#61) is handled elsewhere.
		if ( ( $data['nqa-intake'] ?? '' ) !== 'event' ) {
			return;
		}

		$flat = function ( $v ) : string {
			return sanitize_text_field( is_array( $v ) ? implode( ', ', $v ) : (string) $v );
		};

		$event_name = $flat( $data['event-name'] ?? '' );
		$name       = $flat( $data['your-name'] ?? '' );
		$venue      = $flat( $data['venue'] ?? '' );

		$title = $event_name !== ''
			? $event_name
			: ( $name !== '' ? $name . ' — event ' . current_time( 'Y-m-d' ) : 'Event — ' . current_time( 'Y-m-d H:i' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'nqa_submission',
				'post_title'  => $title,
				'post_content'=> '', // archivist notes (editable in admin)
				'post_status' => 'private',
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return;
		}

		$meta = array(
			'_nqa_sub_kind'      => 'event',
			'_nqa_sub_title'     => $event_name,
			'_nqa_sub_name'      => $name ?: '(anonymous)',
			'_nqa_sub_email'     => sanitize_email( $data['your-email'] ?? '' ),
			'_nqa_sub_phone'     => $flat( $data['your-telephone'] ?? '' ),
			'_nqa_sub_type'      => 'Event',
			'_nqa_sub_story'     => sanitize_textarea_field( $data['your-message'] ?? '' ),
			'_nqa_sub_loc'       => $flat( $data['event-municipality'] ?? '' ),
			'_nqa_sub_credit'    => $flat( $data['credit_preference'] ?? '' ),
			'_nqa_sub_evt_start' => nqa_normalize_date( $data['start-date'] ?? '' ),
			'_nqa_sub_evt_end'   => nqa_normalize_date( $data['end-date'] ?? '' ),
			'_nqa_sub_evt_venue' => $venue,
			'_nqa_sub_evt_org'   => $flat( $data['organizer'] ?? '' ),
			'_nqa_sub_evt_link'  => esc_url_raw( $data['existing-link'] ?? '' ),
			'_nqa_sub_user'      => get_current_user_id(),
			'_nqa_sub_status'    => 'pending',
		);
		foreach ( $meta as $key => $val ) {
			update_post_meta( $post_id, $key, $val );
		}

		// Move any uploaded file into the media library (reuses the story sideload).
		if ( function_exists( 'nqa_submission_sideload' ) ) {
			$uploaded  = $submission->uploaded_files();
			$file_raw  = $uploaded['file-upload'] ?? null;
			$file_path = is_array( $file_raw ) ? ( $file_raw[0] ?? null ) : $file_raw;
			if ( $file_path && file_exists( $file_path ) ) {
				$attachment_id = nqa_submission_sideload( $file_path, $post_id );
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					update_post_meta( $post_id, '_nqa_sub_attachment', $attachment_id );
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}

		// Auto-publish for a trusted, authenticated organizer who supplied an
		// address; otherwise the submission waits in the review queue as a draft.
		if ( nqa_user_can_publish_events() && $venue !== '' ) {
			$record = nqa_create_event_from_submission( $post_id, true );
			if ( ! is_wp_error( $record ) ) {
				update_post_meta( $post_id, '_nqa_sub_status', 'published' );
			}
		}
	},
	10,
	3
);

/** Normalize a submitted date to ACF date_picker storage form (Ymd), or '' . */
function nqa_normalize_date( $raw ) : string {
	$raw = trim( (string) ( is_array( $raw ) ? reset( $raw ) : $raw ) );
	if ( $raw === '' ) {
		return '';
	}
	$ts = strtotime( $raw );
	return $ts ? gmdate( 'Ymd', $ts ) : '';
}

// ── 3. Converter: submission → nqa_event ────────────────────────────────────

/**
 * Create an `nqa_event` from a stored event submission. When $publish is true and
 * an address is present, the record is published with consent self-granted by the
 * organizer; otherwise it is a draft held behind the consent gate (pending).
 * Idempotent: returns the existing record if already converted.
 *
 * @return int|WP_Error  Record ID, or WP_Error on failure.
 */
function nqa_create_event_from_submission( int $submission_id, bool $publish ) {
	if ( 'nqa_submission' !== get_post_type( $submission_id ) ) {
		return new WP_Error( 'nqa_not_submission', 'Not a submission.' );
	}

	$existing = (int) get_post_meta( $submission_id, '_nqa_created_record', true );
	if ( $existing && get_post_status( $existing ) ) {
		return $existing;
	}

	$m       = fn( $k ) => get_post_meta( $submission_id, $k, true );
	$name    = $m( '_nqa_sub_name' );
	$story   = (string) $m( '_nqa_sub_story' );
	$email   = $m( '_nqa_sub_email' );
	$credit  = $m( '_nqa_sub_credit' );
	$loc     = $m( '_nqa_sub_loc' );
	$att     = (int) $m( '_nqa_sub_attachment' );
	$start   = $m( '_nqa_sub_evt_start' );
	$end     = $m( '_nqa_sub_evt_end' );
	$venue   = trim( (string) $m( '_nqa_sub_evt_venue' ) );
	$org     = trim( (string) $m( '_nqa_sub_evt_org' ) );
	$link    = $m( '_nqa_sub_evt_link' );

	// Address is required to auto-publish — never let a trusted submission go live
	// without one, even if the caller asked to publish.
	if ( $publish && $venue === '' ) {
		$publish = false;
	}

	$sub_title = $m( '_nqa_sub_title' );
	$title     = $sub_title ?: ( 'Event submission ' . $submission_id . ' — needs a title' );

	// Insert as a draft first; publish (if warranted) only after all fields —
	// including consent — are set, so the stewardship gate never sees a gap.
	$record_id = wp_insert_post(
		array(
			'post_type'    => 'nqa_event',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => $story, // VERBATIM: the contributor's description.
		),
		true
	);
	if ( is_wp_error( $record_id ) ) {
		return $record_id;
	}

	// Event fields.
	if ( $start ) {
		update_field( 'field_nqa_evt_start', $start, $record_id );
	}
	if ( $end ) {
		update_field( 'field_nqa_evt_end', $end, $record_id );
	}
	if ( $venue !== '' ) {
		// Raw submitted location — visible on the page and geocodable; an archivist
		// later pins the map / links a Venue place record (rule #8).
		update_field( 'field_nqa_evt_submitted_address', $venue, $record_id );
	}
	if ( $link ) {
		update_field( 'link', $link, $record_id );
		update_field( 'source', $link, $record_id );
	}

	// Best-effort organizer match to an existing Org; note it otherwise.
	$org_note = '';
	if ( $org !== '' ) {
		$org_post = get_posts(
			array(
				'post_type'      => 'nqa_org',
				'title'          => $org,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'draft' ),
			)
		);
		if ( $org_post ) {
			update_field( 'field_nqa_evt_org', array( (int) $org_post[0] ), $record_id );
		} else {
			$org_note = sprintf( ' Organizer as submitted: "%s" — link to an Org record.', $org );
		}
	}

	// Stewardship: mark origin and set consent.
	update_field( 'field_nqa_provenance', 'community-submission', $record_id );
	update_field( 'field_nqa_provenance_submitter', $name ?: '(anonymous)', $record_id );
	update_field( 'field_nqa_provenance_date', get_the_date( 'Y-m-d', $submission_id ), $record_id );
	update_field( 'field_nqa_consent_status', $publish ? 'granted' : 'pending', $record_id );
	update_field(
		'field_nqa_consent_notes',
		$publish
			? sprintf( 'Auto-published from event submission #%d by trusted organizer (user #%d), who authorized public listing. Contact: %s.', $submission_id, (int) $m( '_nqa_sub_user' ), $email ?: 'none given' )
			: sprintf( 'From event submission #%d. Credit preference: %s. Contact: %s. Confirm consent before publishing.', $submission_id, $credit ?: 'not specified', $email ?: 'none given' ),
		$record_id
	);

	// Municipality from the free-text location field.
	if ( $loc ) {
		$term = get_term_by( 'name', $loc, 'municipality' ) ?: get_term_by( 'slug', sanitize_title( $loc ), 'municipality' );
		if ( $term ) {
			wp_set_object_terms( $record_id, array( (int) $term->term_id ), 'municipality', false );
		}
	}

	// Carry any uploaded asset over as the featured image.
	if ( $att && get_post( $att ) ) {
		set_post_thumbnail( $record_id, $att );
	}

	// Two-way link between submission and record.
	update_post_meta( $record_id, '_nqa_from_submission', $submission_id );
	update_post_meta( $submission_id, '_nqa_created_record', $record_id );
	update_post_meta(
		$record_id,
		'_nqa_archival_note',
		sprintf(
			'Created from event submission #%d. The body is the contributor\'s own words — do not rewrite them. Set the map pin (or link a Venue) and verify details.%s%s',
			$submission_id,
			$org_note,
			$publish ? ' Auto-published (trusted organizer).' : ' Held as draft with consent Pending.'
		)
	);

	// Publish last, once consent is Granted, so the stewardship gate passes cleanly.
	if ( $publish ) {
		wp_update_post( array( 'ID' => $record_id, 'post_status' => 'publish' ) );
	}

	return (int) $record_id;
}

// ── 4. Admin: event details on the submission review screen ──────────────────

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'nqa_submission_event',
			'Event details',
			'nqa_submission_event_box',
			'nqa_submission',
			'normal',
			'high'
		);
	}
);

function nqa_submission_event_box( WP_Post $post ) : void {
	if ( 'event' !== get_post_meta( $post->ID, '_nqa_sub_kind', true ) ) {
		echo '<p class="description">Not an event submission.</p>';
		return;
	}
	$fields = array(
		'Event name' => get_post_meta( $post->ID, '_nqa_sub_title', true ),
		'Start'      => get_post_meta( $post->ID, '_nqa_sub_evt_start', true ),
		'End'        => get_post_meta( $post->ID, '_nqa_sub_evt_end', true ),
		'Venue'      => get_post_meta( $post->ID, '_nqa_sub_evt_venue', true ),
		'Organizer'  => get_post_meta( $post->ID, '_nqa_sub_evt_org', true ),
		'Existing link' => get_post_meta( $post->ID, '_nqa_sub_evt_link', true ),
		'Municipality'  => get_post_meta( $post->ID, '_nqa_sub_loc', true ),
	);
	echo '<table class="nqa-sub-table"><tbody>';
	foreach ( $fields as $label => $val ) {
		if ( $val === '' || $val === null ) {
			continue;
		}
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">Convert to an <strong>Event</strong> record with the '
		. '"Create archive record" box. Event submissions map their dates, address, and '
		. 'organizer automatically; the description is preserved verbatim.</p>';
}

// ── 5. WP-CLI: onboard / offboard a trusted event publisher ──────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Grant (or revoke) the `nqa_publish_events` capability on a user, so their
	 * event submissions auto-publish.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * [--revoke]
	 * : Remove the capability instead of granting it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nqa trust-publisher pride@example.org
	 *     wp nqa trust-publisher 42 --revoke
	 *
	 * @when after_wp_load
	 */
	WP_CLI::add_command(
		'nqa trust-publisher',
		function ( $args, $assoc ) {
			$user = get_user_by( 'id', $args[0] )
				?: get_user_by( 'login', $args[0] )
				?: get_user_by( 'email', $args[0] );
			if ( ! $user ) {
				WP_CLI::error( "No user found: {$args[0]}" );
			}
			if ( ! empty( $assoc['revoke'] ) ) {
				$user->remove_cap( 'nqa_publish_events' );
				WP_CLI::success( "Revoked event auto-publish from {$user->user_login} (#{$user->ID})." );
			} else {
				$user->add_cap( 'nqa_publish_events' );
				WP_CLI::success( "{$user->user_login} (#{$user->ID}) can now auto-publish event submissions." );
			}
		}
	);
}
