<?php
/**
 * Tell Your Story page.
 */

defined( 'ABSPATH' ) || exit;

// ── Tell Your Story page ──────────────────────────────────────────────────────

add_shortcode( 'nqa_tell_page', 'nqa_tell_page_shortcode' );

function nqa_tell_page_shortcode() {
	$contact_url = esc_url( home_url( '/contact/' ) );

	$h  = '<section class="tell-hero">';
	$h .= '<div class="tell-hero__inner">';
	$h .= '<div class="eyebrow eyebrow--light">Tell Your Story</div>';
	$h .= '<h1>Tell your story.</h1>';
	$h .= '<p>We welcome contributions from anyone connected to Niagara&rsquo;s LGBTQ2S+ communities. Every story, photograph, document, or artifact helps us build a fuller picture of our history in this region.</p>';
	$h .= '</div>';
	$h .= '</section>';

	$h .= '<section class="tell-body">';
	$h .= '<div class="tell-body__inner">';

	// Sidebar.
	$h .= '<div class="tell-sidebar">';
	$h .= '<h2>Before you submit</h2>';
	$h .= '<p>The Niagara Queer Archive is built on trust, care, and community. Your story or materials will help ensure that Queer lives in our region are remembered, respected, and accessible for years to come.</p>';
	$h .= '<ul>';
	$h .= '<li><strong>Consent matters</strong>Only submit materials you have the right to share. If others are included, please note whether you have their permission.</li>';
	$h .= '<li><strong>You are in control</strong>You choose how your name appears &mdash; full name, initials, or anonymous. You can request changes or removal at any time.</li>';
	$h .= '<li><strong>How submissions are used</strong>With your permission, your contribution may appear in the online archive, physical archive, curated collections, or future exhibitions.</li>';
	$h .= '<li><strong>Need help?</strong>If you need assistance digitizing materials or aren&rsquo;t sure what to submit, <a href="' . $contact_url . '">reach out first</a>.</li>';
	$h .= '</ul>';
	$h .= '</div>';

	// Form column.
	$h .= '<div class="tell-form-col">';
	$h .= '<h2>Submit an entry</h2>';
	$h .= do_shortcode( '[contact-form-7 id="61"]' );
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	// FAQ.
	$h .= '<section class="tell-faq">';
	$h .= '<div class="tell-faq__inner">';
	$h .= '<div class="section-head"><h2>Frequently Asked Questions</h2></div>';
	$h .= '<div class="tell-faq__grid">';
	$h .= '<div class="tell-faq__item"><h3>What can I submit?</h3><ul>';
	$h .= '<li>Personal stories, memories, or reflections (written, audio, or video)</li>';
	$h .= '<li>Photographs (digital or scanned)</li>';
	$h .= '<li>Event flyers, posters, or programs</li>';
	$h .= '<li>Newspaper clippings or documents</li>';
	$h .= '<li>Letters, journals, or other materials</li>';
	$h .= '<li>Objects or artifacts (please describe &mdash; we&rsquo;ll contact you)</li>';
	$h .= '</ul></div>';
	$h .= '<div class="tell-faq__item"><h3>How will submissions be used?</h3><p>Your contribution may appear in our online archive, physical archive, or both. Entries may be included in curated collections, exhibitions, or educational projects. We will always credit contributors unless you request anonymity.</p></div>';
	$h .= '<div class="tell-faq__item"><h3>What control do I have?</h3><p>You choose how your name appears. You can request removal or changes at any time by contacting us. You set the terms of your contribution during submission.</p></div>';
	$h .= '<div class="tell-faq__item"><h3>What permissions do I need for other people&rsquo;s names?</h3><p>By submitting, you confirm you have the right to share this material. If your submission includes other people, please note their names and whether you have their permission to share.</p></div>';
	$h .= '</div>';
	$h .= '</div>';
	$h .= '</section>';

	// CTA.
	$h .= '<section class="tell-cta">';
	$h .= '<h2>Have questions first?</h2>';
	$h .= '<p>We&rsquo;re happy to help you figure out what to submit and how.</p>';
	$h .= '<a href="' . $contact_url . '" class="btn btn--outline">Contact us</a>';
	$h .= '</section>';

	return $h;
}
