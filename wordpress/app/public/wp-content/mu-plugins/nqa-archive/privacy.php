<?php
/**
 * Privacy Policy page shortcode.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'nqa_privacy_page', 'nqa_privacy_page_shortcode' );

function nqa_privacy_page_shortcode() {
	$email     = get_option( 'admin_email' );
	$email_url = 'mailto:' . $email;

	$h  = '<section class="privacy-hero">';
	$h .= '<div class="privacy-hero__inner">';
	$h .= '<div class="eyebrow">Legal</div>';
	$h .= '<h1>Privacy Policy</h1>';
	$h .= '<p>Last updated: July 2025 &mdash; Written for the NQA community, not a legal department.</p>';
	$h .= '</div>';
	$h .= '</section>';

	$h .= '<section class="privacy-body">';
	$h .= '<div class="privacy-body__inner">';

	// § The short version.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>The short version</h2>';
	$h .= '<div class="privacy-highlight">We collect only what we need to run the archive. We don&rsquo;t sell your data. We don&rsquo;t track you beyond basic server logs. Submissions are handled confidentially. You can ask us to remove your data at any time.</div>';
	$h .= '<p>The Niagara Queer Archive is a community project. We handle your information with the same care and discretion we ask of our community. This policy explains what we collect, why, and what you can do about it.</p>';
	$h .= '</div>';

	// § What we collect.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>What we collect and why</h2>';
	$h .= '<p><strong>Contact form submissions.</strong> When you contact us or submit a story, we receive your name, email address, and message. We use this only to respond to you and to process your submission. We do not add you to any mailing list without your explicit consent.</p>';
	$h .= '<p><strong>Archive submissions.</strong> When you submit a story, photograph, or other material, we store the content of your submission, your contact details, and your credit and consent preferences. These are used solely to manage your submission within the archive.</p>';
	$h .= '<p><strong>Newsletter sign-ups.</strong> If you subscribe to our email list, we store your email address. You can unsubscribe at any time via the link in any email we send.</p>';
	$h .= '<p><strong>Server logs.</strong> Our web host automatically logs basic access data (IP addresses, browser type, pages visited, timestamps). These logs are used for security and performance monitoring and are not shared.</p>';
	$h .= '<p><strong>Cookies.</strong> This site uses only essential cookies &mdash; for login sessions (staff only) and security. No third-party tracking cookies are used.</p>';
	$h .= '</div>';

	// § Archive submissions.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>Archive submissions and consent</h2>';
	$h .= '<p>Submissions to the archive are held in confidence during our review process. Before any submission appears on the public site, a human reviews it and confirms that it meets our curation standards and the contributor&rsquo;s stated consent preferences.</p>';
	$h .= '<p>You may submit under your full name, initials, or anonymously. Your credit preference is honoured in all published material.</p>';
	$h .= '<p>If your submission includes information about other people, we may reach out to confirm consent before publishing. We will not publish personally identifying information about private individuals without their knowledge.</p>';
	$h .= '<div class="privacy-highlight">You can request removal or amendment of any submission at any time. We will action removal requests within 14 days. Email us at <a href="' . esc_url( $email_url ) . '">' . esc_html( $email ) . '</a>.</div>';
	$h .= '</div>';

	// § Your rights.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>Your rights</h2>';
	$h .= '<p>You have the right to:</p>';
	$h .= '<ul>';
	$h .= '<li>Access a copy of the personal data we hold about you</li>';
	$h .= '<li>Request correction of inaccurate data</li>';
	$h .= '<li>Request deletion of your data</li>';
	$h .= '<li>Withdraw consent for your submission to be published</li>';
	$h .= '<li>Object to how we use your information</li>';
	$h .= '</ul>';
	$h .= '<p>To exercise any of these rights, contact us at <a href="' . esc_url( $email_url ) . '">' . esc_html( $email ) . '</a>. We will respond within 30 days.</p>';
	$h .= '</div>';

	// § Third-party services.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>Third-party services</h2>';
	$h .= '<p>This site uses the following third-party services:</p>';
	$h .= '<ul>';
	$h .= '<li><strong>Contact Form 7</strong> &mdash; processes contact and submission forms. Form data is transmitted to our server and stored in our database. No data is sent to third parties by CF7.</li>';
	$h .= '<li><strong>Dreamhost</strong> &mdash; our web host. Server logs are maintained under Dreamhost&rsquo;s privacy policy.</li>';
	$h .= '<li><strong>Wayback Machine (Internet Archive)</strong> &mdash; we submit external source URLs for archival capture. No personal data is shared in this process.</li>';
	$h .= '<li><strong>Google Maps</strong> &mdash; used on some record pages to display a location. Google&rsquo;s privacy policy applies when the map is loaded.</li>';
	$h .= '</ul>';
	$h .= '<p>We do not use Google Analytics, Meta Pixel, or any advertising technology.</p>';
	$h .= '</div>';

	// § Sensitive information.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>Sensitive information</h2>';
	$h .= '<p>The nature of an LGBTQ2S+ archive means we may hold sensitive information &mdash; including details about people&rsquo;s identities, relationships, health history, and family situations. We treat all such information with extreme care.</p>';
	$h .= '<p>We do not publish private personal details (home addresses, personal phone numbers, private email addresses) about any individual without explicit consent.</p>';
	$h .= '<p>We maintain separate, staff-only notes for each record where sensitivity concerns are flagged. Public-facing records contain only information that has been sourced, reviewed, and consented.</p>';
	$h .= '</div>';

	// § Changes.
	$h .= '<div class="privacy-section">';
	$h .= '<h2>Changes to this policy</h2>';
	$h .= '<p>If we make material changes to this policy, we&rsquo;ll update the date at the top of this page and note the change. We don&rsquo;t anticipate frequent changes &mdash; this is a simple site with a clear purpose.</p>';
	$h .= '<p>Questions? Email us: <a href="' . esc_url( $email_url ) . '">' . esc_html( $email ) . '</a></p>';
	$h .= '</div>';

	$h .= '</div>';
	$h .= '</section>';

	return $h;
}
