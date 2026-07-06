<?php
/**
 * Privacy Policy page shortcode.
 * Prose content is editable via ACF fields on the Privacy Policy page.
 * Fallbacks are the original hardcoded text so the page renders correctly
 * before any editor has touched the fields.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'nqa_privacy_page', 'nqa_privacy_page_shortcode' );

function nqa_privacy_page_shortcode() {
	$email     = get_option( 'admin_email' );
	$email_url = 'mailto:' . $email;
	$pid       = get_queried_object_id();

	// Replace {email} with a mailto link.
	$inject = function ( string $text ) use ( $email, $email_url ) : string {
		$link = '<a href="' . esc_url( $email_url ) . '">' . esc_html( $email ) . '</a>';
		return str_replace( '{email}', $link, wp_kses_post( $text ) );
	};

	// Textarea → HTML paragraphs; strip empty <p> tags wpautop sometimes emits.
	$paras = function ( string $raw ) : string {
		$out = wpautop( wp_kses_post( $raw ) );
		return preg_replace( '/<p[^>]*>(\s|&nbsp;)*<\/p>/', '', $out );
	};

	// Textarea → <ul><li> list (one item per line, HTML allowed per line).
	$list = function ( string $raw ) : string {
		$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		$out   = '<ul>';
		foreach ( $lines as $line ) {
			$out .= '<li>' . wp_kses_post( $line ) . '</li>';
		}
		return $out . '</ul>';
	};

	// Read ACF field; return $fallback when the field is empty/unsaved.
	$f = function ( string $key, string $fallback = '' ) use ( $pid ) : string {
		$val = get_field( $key, $pid );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	// ── Hero ──────────────────────────────────────────────────────────────────

	$h  = '<section class="privacy-hero">';
	$h .= '<div class="privacy-hero__inner">';
	$h .= '<div class="eyebrow">Legal</div>';
	$h .= '<h1>Privacy Policy</h1>';
	$h .= '<p>' . esc_html( $f( 'privacy_hero_lede', 'Last updated: July 2025 — Written for the NQA community, not a legal department.' ) ) . '</p>';
	$h .= '</div>';
	$h .= '</section>';

	$h .= '<section class="privacy-body">';
	$h .= '<div class="privacy-body__inner">';

	// ── §1 The short version ──────────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s1_heading', 'The short version' ) ) . '</h2>';
	$h .= '<div class="privacy-highlight">' . wp_kses_post( $f( 'privacy_s1_highlight', "We collect only what we need to run the archive. We don\xe2\x80\x99t sell your data. We don\xe2\x80\x99t track you beyond basic server logs. Submissions are handled confidentially. You can ask us to remove your data at any time." ) ) . '</div>';
	$s1_body = $f( 'privacy_s1_body', "The Niagara Queer Archive is a community project. We handle your information with the same care and discretion we ask of our community. This policy explains what we collect, why, and what you can do about it." );
	$h .= $paras( $s1_body );
	$h .= '</div>';

	// ── §2 What we collect ────────────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s2_heading', 'What we collect and why' ) ) . '</h2>';
	$s2_val = $f( 'privacy_s2_body' );
	if ( $s2_val ) {
		$h .= $paras( $s2_val );
	} else {
		$h .= '<p><strong>Contact form submissions.</strong> When you contact us or submit a story, we receive your name, email address, and message. We use this only to respond to you and to process your submission. We do not add you to any mailing list without your explicit consent.</p>';
		$h .= '<p><strong>Archive submissions.</strong> When you submit a story, photograph, or other material, we store the content of your submission, your contact details, and your credit and consent preferences. These are used solely to manage your submission within the archive.</p>';
		$h .= '<p><strong>Newsletter sign-ups.</strong> If you subscribe to our email list, we store your email address. You can unsubscribe at any time via the link in any email we send.</p>';
		$h .= '<p><strong>Server logs.</strong> Our web host automatically logs basic access data (IP addresses, browser type, pages visited, timestamps). These logs are used for security and performance monitoring and are not shared.</p>';
		$h .= '<p><strong>Cookies.</strong> This site uses only essential cookies &mdash; for login sessions (staff only) and security. No third-party tracking cookies are used.</p>';
	}
	$h .= '</div>';

	// ── §3 Archive submissions and consent ────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s3_heading', 'Archive submissions and consent' ) ) . '</h2>';
	$s3_body = $f( 'privacy_s3_body' );
	if ( $s3_body ) {
		$h .= $paras( $s3_body );
	} else {
		$h .= '<p>Submissions to the archive are held in confidence during our review process. Before any submission appears on the public site, a human reviews it and confirms that it meets our curation standards and the contributor&rsquo;s stated consent preferences.</p>';
		$h .= '<p>You may submit under your full name, initials, or anonymously. Your credit preference is honoured in all published material.</p>';
		$h .= '<p>If your submission includes information about other people, we may reach out to confirm consent before publishing. We will not publish personally identifying information about private individuals without their knowledge.</p>';
	}
	$h .= '<div class="privacy-highlight">' . $inject( $f( 'privacy_s3_highlight', "You can request removal or amendment of any submission at any time. We will action removal requests within 14 days. Email us at {email}." ) ) . '</div>';
	$h .= '</div>';

	// ── §4 Your rights ────────────────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s4_heading', 'Your rights' ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'privacy_s4_intro', 'You have the right to:' ) ) . '</p>';
	$s4_items = $f( 'privacy_s4_items', "Access a copy of the personal data we hold about you\nRequest correction of inaccurate data\nRequest deletion of your data\nWithdraw consent for your submission to be published\nObject to how we use your information" );
	$h .= $list( $s4_items );
	$h .= '<p>' . $inject( $f( 'privacy_s4_footer', 'To exercise any of these rights, contact us at {email}. We will respond within 30 days.' ) ) . '</p>';
	$h .= '</div>';

	// ── §5 Third-party services ───────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s5_heading', 'Third-party services' ) ) . '</h2>';
	$h .= '<p>' . esc_html( $f( 'privacy_s5_intro', 'This site uses the following third-party services:' ) ) . '</p>';
	$s5_items = $f( 'privacy_s5_items' );
	if ( $s5_items ) {
		$h .= $list( $s5_items );
	} else {
		$h .= '<ul>';
		$h .= '<li><strong>Contact Form 7</strong> &mdash; processes contact and submission forms. Form data is transmitted to our server and stored in our database. No data is sent to third parties by CF7.</li>';
		$h .= '<li><strong>Dreamhost</strong> &mdash; our web host. Server logs are maintained under Dreamhost&rsquo;s privacy policy.</li>';
		$h .= '<li><strong>Wayback Machine (Internet Archive)</strong> &mdash; we submit external source URLs for archival capture. No personal data is shared in this process.</li>';
		$h .= '<li><strong>Google Maps</strong> &mdash; used on some record pages to display a location. Google&rsquo;s privacy policy applies when the map is loaded.</li>';
		$h .= '</ul>';
	}
	$h .= '<p>' . esc_html( $f( 'privacy_s5_footer', 'We do not use Google Analytics, Meta Pixel, or any advertising technology.' ) ) . '</p>';
	$h .= '</div>';

	// ── §6 Sensitive information ──────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s6_heading', 'Sensitive information' ) ) . '</h2>';
	$s6_val = $f( 'privacy_s6_body' );
	if ( $s6_val ) {
		$h .= $paras( $s6_val );
	} else {
		$h .= '<p>The nature of an LGBTQ2S+ archive means we may hold sensitive information &mdash; including details about people&rsquo;s identities, relationships, health history, and family situations. We treat all such information with extreme care.</p>';
		$h .= '<p>We do not publish private personal details (home addresses, personal phone numbers, private email addresses) about any individual without explicit consent.</p>';
		$h .= '<p>We maintain separate, staff-only notes for each record where sensitivity concerns are flagged. Public-facing records contain only information that has been sourced, reviewed, and consented.</p>';
	}
	$h .= '</div>';

	// ── §7 Changes ────────────────────────────────────────────────────────────

	$h .= '<div class="privacy-section">';
	$h .= '<h2>' . esc_html( $f( 'privacy_s7_heading', 'Changes to this policy' ) ) . '</h2>';
	$s7_val = $f( 'privacy_s7_body' );
	if ( $s7_val ) {
		$h .= $paras( $inject( $s7_val ) );
	} else {
		$h .= '<p>If we make material changes to this policy, we&rsquo;ll update the date at the top of this page and note the change. We don&rsquo;t anticipate frequent changes &mdash; this is a simple site with a clear purpose.</p>';
		$h .= '<p>Questions? Email us: <a href="' . esc_url( $email_url ) . '">' . esc_html( $email ) . '</a></p>';
	}
	$h .= '</div>';

	$h .= '</div>'; // /privacy-body__inner
	$h .= '</section>';

	return $h;
}
