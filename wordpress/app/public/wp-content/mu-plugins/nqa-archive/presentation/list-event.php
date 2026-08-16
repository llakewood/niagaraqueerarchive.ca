<?php
/**
 * "List an Event" page shortcode — the front end of the event intake stream
 * captured in functions/events.php.
 *
 * Deliberately reuses the Tell Your Story page's layout classes (tell-hero,
 * tell-body, tell-sidebar, tell-form-col, tell-faq, tell-cta): the two intake
 * pages are siblings and should read as a matched pair, so this file adds
 * markup only, never new CSS.
 *
 * Prose is editable via the "List an Event content" ACF group in
 * functions/page-fields.php (attached to the `page-list-an-event` template);
 * every field carries the default text below, so the page renders correctly
 * before an editor has touched it.
 *
 * The CF7 form ID is read from the `nqa_event_form_id` option so the form can be
 * rebuilt on production without a code change; it falls back to a lookup by
 * title. If no form is found the page still renders, with an editor-facing
 * notice in place of the form rather than a bare `[contact-form-7]` string.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the event intake CF7 form ID: the `nqa_event_form_id` option first,
 * then a by-title lookup, so a rebuilt form is picked up automatically.
 */
function nqa_event_form_id() : int {
	$id = (int) get_option( 'nqa_event_form_id', 0 );
	if ( $id && 'wpcf7_contact_form' === get_post_type( $id ) ) {
		return $id;
	}
	$found = get_posts(
		array(
			'post_type'      => 'wpcf7_contact_form',
			'title'          => 'Event listing submission',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		)
	);
	return $found ? (int) $found[0] : 0;
}

add_shortcode( 'nqa_list_event_page', 'nqa_list_event_page_shortcode' );

function nqa_list_event_page_shortcode() : string {
	$contact_url  = esc_url( home_url( '/contact-us/' ) );
	$calendar_url = esc_url( home_url( '/calendar/' ) );
	$pid          = get_queried_object_id();

	$f = function ( string $key, string $fallback = '' ) use ( $pid ) : string {
		$val = function_exists( 'get_field' ) ? get_field( $key, $pid ) : null;
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	// Render a FAQ answer: list if it contains newlines, paragraph otherwise.
	$faq_answer = function ( string $raw ) : string {
		$raw = trim( $raw );
		if ( strpos( $raw, "\n" ) !== false ) {
			$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
			$out   = '<ul>';
			foreach ( $lines as $line ) {
				$out .= '<li>' . wp_kses_post( $line ) . '</li>';
			}
			return $out . '</ul>';
		}
		return '<p>' . wp_kses_post( $raw ) . '</p>';
	};

	// ── Hero ──────────────────────────────────────────────────────────────────

	$h  = '<section class="tell-hero">';
	$h .= '<div class="tell-hero__inner">';
	$h .= '<div class="eyebrow eyebrow--light">List an Event</div>';
	$h .= '<h1>' . esc_html( $f( 'evt_hero_heading', 'List an event.' ) ) . '</h1>';
	$h .= '<p>' . esc_html( $f( 'evt_hero_lede', "Running something for Niagara\xe2\x80\x99s LGBTQ2S+ communities \xe2\x80\x94 a Pride event, a fundraiser, a meet-up, a performance? Tell us, and it gets a page here. The listing promotes the event now, and stays on as part of the record once the date passes." ) ) . '</p>';
	$h .= '</div>';
	$h .= '</section>';

	// ── Body ──────────────────────────────────────────────────────────────────

	$h .= '<section class="tell-body">';
	$h .= '<div class="tell-body__inner">';

	// Sidebar.
	$h .= '<div class="tell-sidebar">';
	$h .= '<h2>' . esc_html( $f( 'evt_sidebar_heading', 'Before you submit' ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'evt_sidebar_body', "Every listing becomes a permanent, linkable page \xe2\x80\x94 which matters most when an event has no other home online. Share it, print the link, and it keeps working after the event is over." ) ) . '</p>';

	$raw_items = $f(
		'evt_sidebar_items',
		"<strong>Upcoming events only</strong> This form is for events that haven\xe2\x80\x99t happened yet. For a past event you remember, use <a href=\"/tell/\">Tell Your Story</a> instead.\n<strong>A location is required</strong> We need a venue or address to place the event on the map and the calendar.\n<strong>An archivist reviews first</strong> Listings are read before they go live, usually within a few days. Regular organizers can be set up to publish directly \xe2\x80\x94 just ask.\n<strong>Only list what\xe2\x80\x99s yours to list</strong> Submit events you organize, or have permission to publicize."
	);
	$items     = array_filter( array_map( 'trim', explode( "\n", $raw_items ) ) );
	$h .= '<ul>';
	foreach ( $items as $item ) {
		$h .= '<li>' . wp_kses_post( $item ) . '</li>';
	}
	$h .= '<li><strong>Already listed?</strong> Check <a href="' . $calendar_url . '">the calendar</a> first, and <a href="' . $contact_url . '">contact us</a> to update an existing listing.</li>';
	$h .= '</ul>';
	$h .= '</div>';

	// Form column.
	$h .= '<div class="tell-form-col">';
	$h .= '<h2>' . esc_html( $f( 'evt_form_heading', 'Event details' ) ) . '</h2>';

	$form_id = nqa_event_form_id();
	if ( $form_id ) {
		$h .= do_shortcode( '[contact-form-7 id="' . $form_id . '"]' );
	} elseif ( current_user_can( 'edit_pages' ) ) {
		// Editor-only: never show a broken form (or a raw shortcode) to the public.
		$h .= '<p class="nqa-form__notice">No event intake form found. Create a Contact Form 7 form '
			. 'titled <strong>Event listing submission</strong>, or set the '
			. '<code>nqa_event_form_id</code> option to its ID.</p>';
	} else {
		$h .= '<p>The event submission form is temporarily unavailable. '
			. '<a href="' . $contact_url . '">Contact us</a> and we\'ll list your event.</p>';
	}
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	// ── FAQ ───────────────────────────────────────────────────────────────────

	$faq_items = array(
		array(
			'q' => $f( 'evt_faq_1_question', 'What kinds of events can I list?' ),
			'a' => $f( 'evt_faq_1_answer', "Pride events, marches, and flag raisings\nDrag and other performances\nFundraisers and community benefits\nSupport groups, peer meet-ups, and social nights\nWorkshops, talks, and film screenings\nExhibitions, launches, and readings" ),
		),
		array(
			'q' => $f( 'evt_faq_2_question', 'What happens after I submit?' ),
			'a' => $f( 'evt_faq_2_answer', "Your submission goes to an archivist, who reviews the details and creates the event page. We may email you with questions. Once published, the event appears on the calendar and on the events listing, and stays online afterward as a record of what happened." ),
		),
		array(
			'q' => $f( 'evt_faq_3_question', 'Can I publish my own events directly?' ),
			'a' => $f( 'evt_faq_3_answer', "Yes \xe2\x80\x94 if you organize events regularly. Register for an account and contact us, and we can enable direct publishing for you. Your listings then go live as soon as you submit them, as long as you\xe2\x80\x99ve included a location." ),
		),
		array(
			'q' => $f( 'evt_faq_4_question', 'Why does the archive list upcoming events?' ),
			'a' => $f( 'evt_faq_4_answer', "Because today\xe2\x80\x99s event is tomorrow\xe2\x80\x99s record. Most of what we know about queer life in Niagara before the internet survives only where someone kept a flyer. Listing an event now means it can\xe2\x80\x99t go undocumented later." ),
		),
	);

	$h .= '<section class="tell-faq">';
	$h .= '<div class="tell-faq__inner">';
	$h .= '<div class="section-head"><h2>' . esc_html( $f( 'evt_faq_heading', 'Frequently Asked Questions' ) ) . '</h2></div>';
	$h .= '<div class="tell-faq__grid">';
	foreach ( $faq_items as $item ) {
		$h .= '<div class="tell-faq__item">';
		$h .= '<h3>' . esc_html( $item['q'] ) . '</h3>';
		$h .= $faq_answer( $item['a'] );
		$h .= '</div>';
	}
	$h .= '</div>';
	$h .= '</div>';
	$h .= '</section>';

	// ── CTA ───────────────────────────────────────────────────────────────────

	$h .= '<section class="tell-cta">';
	$h .= '<h2>' . esc_html( $f( 'evt_cta_heading', "See what\xe2\x80\x99s coming up" ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'evt_cta_body', 'Browse events already listed across the Niagara region.' ) ) . '</p>';
	$h .= '<a href="' . $calendar_url . '" class="btn btn--outline">' . esc_html( $f( 'evt_cta_label', 'View the calendar' ) ) . '</a>';
	$h .= '</section>';

	return $h;
}
