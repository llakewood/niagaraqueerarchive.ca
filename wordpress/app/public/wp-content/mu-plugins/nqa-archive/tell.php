<?php
/**
 * Tell Your Story page shortcode.
 * Prose content is editable via ACF fields on the Tell page.
 * The "Need help?" sidebar item always renders with a dynamic contact link.
 * FAQ answers auto-render as a bullet list when they contain line breaks,
 * or as a paragraph when they are a single block of text.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'nqa_tell_page', 'nqa_tell_page_shortcode' );

function nqa_tell_page_shortcode() {
	$contact_url = esc_url( home_url( '/contact/' ) );
	$pid         = get_queried_object_id();

	$f = function ( string $key, string $fallback = '' ) use ( $pid ) : string {
		$val = get_field( $key, $pid );
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
	$h .= '<div class="eyebrow eyebrow--light">Tell Your Story</div>';
	$h .= '<h1>' . esc_html( $f( 'tell_hero_heading', 'Tell your story.' ) ) . '</h1>';
	$h .= '<p>' . esc_html( $f( 'tell_hero_lede', "We welcome contributions from anyone connected to Niagara\xe2\x80\x99s LGBTQ2S+ communities. Every story, photograph, document, or artifact helps us build a fuller picture of our history in this region." ) ) . '</p>';
	$h .= '</div>';
	$h .= '</section>';

	// ── Body ──────────────────────────────────────────────────────────────────

	$h .= '<section class="tell-body">';
	$h .= '<div class="tell-body__inner">';

	// Sidebar.
	$h .= '<div class="tell-sidebar">';
	$h .= '<h2>' . esc_html( $f( 'tell_sidebar_heading', 'Before you submit' ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'tell_sidebar_body', "The Niagara Queer Archive is built on trust, care, and community. Your story or materials will help ensure that Queer lives in our region are remembered, respected, and accessible for years to come." ) ) . '</p>';

	$raw_items = $f( 'tell_sidebar_items', "<strong>Consent matters</strong> Only submit materials you have the right to share. If others are included, please note whether you have their permission.\n<strong>You are in control</strong> You choose how your name appears \xe2\x80\x94 full name, initials, or anonymous. You can request changes or removal at any time.\n<strong>How submissions are used</strong> With your permission, your contribution may appear in the online archive, physical archive, curated collections, or future exhibitions." );
	$items     = array_filter( array_map( 'trim', explode( "\n", $raw_items ) ) );
	$h .= '<ul>';
	foreach ( $items as $item ) {
		$h .= '<li>' . wp_kses_post( $item ) . '</li>';
	}
	// "Need help?" always renders with a live contact link.
	$h .= '<li><strong>Need help?</strong> If you need assistance digitizing materials or aren&rsquo;t sure what to submit, <a href="' . $contact_url . '">reach out first</a>.</li>';
	$h .= '</ul>';
	$h .= '</div>';

	// Form column.
	$h .= '<div class="tell-form-col">';
	$h .= '<h2>' . esc_html( $f( 'tell_form_heading', 'Submit an entry' ) ) . '</h2>';
	$h .= do_shortcode( '[contact-form-7 id="61"]' );
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	// ── FAQ ───────────────────────────────────────────────────────────────────

	$faq_items = array(
		array(
			'q' => $f( 'tell_faq_1_question', 'What can I submit?' ),
			'a' => $f( 'tell_faq_1_answer', "Personal stories, memories, or reflections (written, audio, or video)\nPhotographs (digital or scanned)\nEvent flyers, posters, or programs\nNewspaper clippings or documents\nLetters, journals, or other materials\nObjects or artifacts (please describe \xe2\x80\x94 we\xe2\x80\x99ll contact you)" ),
		),
		array(
			'q' => $f( 'tell_faq_2_question', 'How will submissions be used?' ),
			'a' => $f( 'tell_faq_2_answer', 'Your contribution may appear in our online archive, physical archive, or both. Entries may be included in curated collections, exhibitions, or educational projects. We will always credit contributors unless you request anonymity.' ),
		),
		array(
			'q' => $f( 'tell_faq_3_question', 'What control do I have?' ),
			'a' => $f( 'tell_faq_3_answer', 'You choose how your name appears. You can request removal or changes at any time by contacting us. You set the terms of your contribution during submission.' ),
		),
		array(
			'q' => $f( 'tell_faq_4_question', "What permissions do I need for other people\xe2\x80\x99s names?" ),
			'a' => $f( 'tell_faq_4_answer', 'By submitting, you confirm you have the right to share this material. If your submission includes other people, please note their names and whether you have their permission to share.' ),
		),
	);

	$h .= '<section class="tell-faq">';
	$h .= '<div class="tell-faq__inner">';
	$h .= '<div class="section-head"><h2>' . esc_html( $f( 'tell_faq_heading', 'Frequently Asked Questions' ) ) . '</h2></div>';
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
	$h .= '<h2>' . esc_html( $f( 'tell_cta_heading', 'Have questions first?' ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'tell_cta_body', "We\xe2\x80\x99re happy to help you figure out what to submit and how." ) ) . '</p>';
	$h .= '<a href="' . $contact_url . '" class="btn btn--outline">' . esc_html( $f( 'tell_cta_label', 'Contact us' ) ) . '</a>';
	$h .= '</section>';

	return $h;
}
