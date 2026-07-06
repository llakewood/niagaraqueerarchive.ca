<?php
/**
 * Contact page shortcode.
 * Prose content is editable via ACF fields on the Contact Us page.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'nqa_contact_page', 'nqa_contact_page_shortcode' );

function nqa_contact_page_shortcode() {
	$tell_url  = esc_url( home_url( '/tell/' ) );
	$email     = get_option( 'admin_email' );
	$email_url = esc_url( 'mailto:' . $email );
	$email     = esc_html( $email );
	$pid       = get_queried_object_id();

	$f = function ( string $key, string $fallback = '' ) use ( $pid ) : string {
		$val = get_field( $key, $pid );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	// ── Hero ──────────────────────────────────────────────────────────────────

	$h  = '<section class="contact-hero">';
	$h .= '<div class="contact-hero__inner">';
	$h .= '<div>';
	$h .= '<div class="eyebrow">Contact</div>';
	$h .= '<h1>' . esc_html( $f( 'contact_hero_heading', "We\xe2\x80\x99d love to hear from you." ) ) . '</h1>';
	$h .= '</div>';
	$h .= '<div class="contact-hero__body">';
	$h .= '<p class="contact-hero__intro">' . esc_html( $f( 'contact_hero_intro', "Whether you have a question, want to get involved, are looking to contribute materials, or just want to learn more about what we\xe2\x80\x99re building \xe2\x80\x94 reach out. We\xe2\x80\x99ll get back to you as soon as we can." ) ) . '</p>';
	$h .= '<div class="contact-direct">';
	$h .= '<div class="contact-direct__label">Email us directly</div>';
	$h .= '<div class="contact-direct__addr"><a href="' . $email_url . '">' . $email . '</a></div>';
	$h .= '</div>';
	$h .= '<div class="contact-note">';
	$h .= '<strong>' . esc_html( $f( 'contact_note_heading', 'A note on our values' ) ) . '</strong>';
	$h .= esc_html( $f( 'contact_note_body', "The Niagara Queer Archive is a space built on respect. Hate, bigotry, and bad-faith contact are not welcome. We\xe2\x80\x99re here for community \xe2\x80\x94 not for debate." ) );
	$h .= '</div>';
	$h .= '</div>';
	$h .= '</div>';
	$h .= '</section>';

	// ── Form section ──────────────────────────────────────────────────────────

	$h .= '<section class="contact-form-section">';
	$h .= '<div class="contact-form-section__inner">';

	$h .= '<div class="contact-form-section__info">';
	$h .= '<h2>' . esc_html( $f( 'contact_sidebar_heading', 'What can we help with?' ) ) . '</h2>';

	$raw_items = $f( 'contact_sidebar_items', "Questions about the archive or a specific record\nWanting to volunteer or get involved\nInstitutional partnerships or research inquiries\nMedia and press requests\nAccessibility or consent requests\nAnything else \xe2\x80\x94 we read every message" );
	$items     = array_filter( array_map( 'trim', explode( "\n", $raw_items ) ) );
	$h .= '<ul>';
	foreach ( $items as $item ) {
		$h .= '<li>' . esc_html( $item ) . '</li>';
	}
	$h .= '</ul>';

	$h .= '<p>If you&rsquo;re ready to submit a story or materials, use our <a href="' . $tell_url . '">submission form</a> instead.</p>';
	$h .= '</div>';

	$h .= '<div class="contact-form-col">';
	$h .= do_shortcode( '[contact-form-7 id="60"]' );
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	return $h;
}
