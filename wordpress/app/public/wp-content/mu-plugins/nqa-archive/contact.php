<?php
/**
 * Contact page shortcode.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'nqa_contact_page', 'nqa_contact_page_shortcode' );

function nqa_contact_page_shortcode() {
	$tell_url = esc_url( home_url( '/tell/' ) );

	$h  = '<section class="contact-hero">';
	$h .= '<div class="contact-hero__inner">';

	$h .= '<div>';
	$h .= '<div class="eyebrow">Contact</div>';
	$h .= '<h1>We&rsquo;d love to hear from you.</h1>';
	$h .= '</div>';

	$h .= '<div class="contact-hero__body">';
	$h .= '<p class="contact-hero__intro">Whether you have a question, want to get involved, are looking to contribute materials, or just want to learn more about what we&rsquo;re building &mdash; reach out. We&rsquo;ll get back to you as soon as we can.</p>';
	$h .= '<div class="contact-direct">';
	$h .= '<div class="contact-direct__label">Email us directly</div>';
	$h .= '<div class="contact-direct__addr"><a href="mailto:thevariables@gmail.com">thevariables@gmail.com</a></div>';
	$h .= '</div>';
	$h .= '<div class="contact-note">';
	$h .= '<strong>A note on our values</strong>';
	$h .= 'The Niagara Queer Archive is a space built on respect. Hate, bigotry, and bad-faith contact are not welcome. We&rsquo;re here for community &mdash; not for debate.';
	$h .= '</div>';
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	$h .= '<section class="contact-form-section">';
	$h .= '<div class="contact-form-section__inner">';

	$h .= '<div class="contact-form-section__info">';
	$h .= '<h2>What can we help with?</h2>';
	$h .= '<p>Reach out for any of the following:</p>';
	$h .= '<ul>';
	$h .= '<li>Questions about the archive or a specific record</li>';
	$h .= '<li>Wanting to volunteer or get involved</li>';
	$h .= '<li>Institutional partnerships or research inquiries</li>';
	$h .= '<li>Media and press requests</li>';
	$h .= '<li>Accessibility or consent requests</li>';
	$h .= '<li>Anything else &mdash; we read every message</li>';
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
