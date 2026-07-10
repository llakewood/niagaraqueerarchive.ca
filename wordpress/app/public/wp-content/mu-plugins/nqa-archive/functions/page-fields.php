<?php
/**
 * ACF field groups for page-level editable content regions.
 *
 * Two mechanisms, applied where they fit:
 *   Front-page group ("Site Copy") — homepage hero and global UI labels. ACF
 *     options pages are Pro-only, so this group is attached to the static front
 *     page (page_type == front_page) and read via get_field('key',
 *     nqa_home_page_id()) — see shortcodes.php.
 *   Page-specific groups — attached by page_template location rule; one group
 *     per shortcode-driven page. Accessed with get_field('key', $page_id).
 *
 * Editor conventions (noted in field instructions where relevant):
 *   - Blank line between paragraphs in multi-paragraph textarea fields.
 *   - One list item per line in list textarea fields (HTML allowed).
 *   - Use {email} where the site admin email link should appear.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// NOTE: ACF options pages are Pro-only. These homepage fields are
		// attached to the static front page instead (location rule below),
		// and read by the homepage shortcodes via nqa_home_page_id().

		acf_add_local_field_group( array(
			'key'      => 'group_nqa_options',
			'title'    => 'Site Copy',
			'location' => array( array( array(
				'param'    => 'page_type',
				'operator' => '==',
				'value'    => 'front_page',
			) ) ),
			'fields'   => array(

				// ─ Homepage hero ─────────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_hero', 'label' => 'Homepage — Hero', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_hero_tag',     'label' => 'Location tag', 'name' => 'home_hero_tag',     'type' => 'text',     'default_value' => 'Niagara, Ontario — Est. 2025', 'instructions' => 'Small label shown above the headline.' ),
				array( 'key' => 'field_nqa_opt_hero_heading', 'label' => 'Headline',     'name' => 'home_hero_heading', 'type' => 'text',     'default_value' => 'Preserving Niagara\'s Queer past — celebrating our living history.' ),
				array( 'key' => 'field_nqa_opt_hero_lede',    'label' => 'Lede',         'name' => 'home_hero_lede',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'A community project dedicated to cataloguing, curating, and preserving LGBTQ2S+ stories across the Niagara region — from St. Catharines to Fort Erie, Welland to Niagara-on-the-Lake.' ),

				// ─ Homepage CTAs ──────────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_ctas', 'label' => 'Homepage — CTAs', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_cta1_label', 'label' => 'CTA 1 — label', 'name' => 'home_cta_1_label', 'type' => 'text', 'default_value' => 'Browse the Archive' ),
				array( 'key' => 'field_nqa_opt_cta1_url',   'label' => 'CTA 1 — URL',   'name' => 'home_cta_1_url',   'type' => 'url',  'instructions' => 'Leave blank to use the Collections page.' ),
				array( 'key' => 'field_nqa_opt_cta2_label', 'label' => 'CTA 2 — label', 'name' => 'home_cta_2_label', 'type' => 'text', 'default_value' => 'Explore Collections' ),
				array( 'key' => 'field_nqa_opt_cta2_url',   'label' => 'CTA 2 — URL',   'name' => 'home_cta_2_url',   'type' => 'url' ),
				array( 'key' => 'field_nqa_opt_cta3_label', 'label' => 'CTA 3 — label', 'name' => 'home_cta_3_label', 'type' => 'text', 'default_value' => 'Submit Your Story' ),
				array( 'key' => 'field_nqa_opt_cta3_url',   'label' => 'CTA 3 — URL',   'name' => 'home_cta_3_url',   'type' => 'url',  'instructions' => 'Leave blank to use the Tell Your Story page.' ),

				// ─ Stats panel ────────────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_stats', 'label' => 'Homepage — Stats panel', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_stats_title',  'label' => 'Panel heading',            'name' => 'home_stats_title',         'type' => 'text', 'default_value' => 'Archive at a Glance' ),
				array( 'key' => 'field_nqa_opt_stat1_sub',    'label' => 'Records — subtitle',        'name' => 'home_stat_records_sub',    'type' => 'text', 'default_value' => 'People, orgs, events, places' ),
				array( 'key' => 'field_nqa_opt_stat2_sub',    'label' => 'Articles — subtitle',       'name' => 'home_stat_articles_sub',   'type' => 'text', 'default_value' => 'Niagara press, sourced & preserved' ),
				array( 'key' => 'field_nqa_opt_stat3_sub',    'label' => 'Collections — subtitle',    'name' => 'home_stat_collections_sub','type' => 'text', 'default_value' => 'Themed, curated sets' ),
				array( 'key' => 'field_nqa_opt_stat4_sub',    'label' => 'Municipalities — subtitle', 'name' => 'home_stat_muni_sub',       'type' => 'text', 'default_value' => 'Across the Niagara region' ),

				// ─ Principles section ─────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_principles', 'label' => 'Homepage — Principles', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_p1_n', 'label' => 'Principle 1 — number',  'name' => 'home_principle_1_num',  'type' => 'text',     'default_value' => '01' ),
				array( 'key' => 'field_nqa_opt_p1_h', 'label' => 'Principle 1 — heading', 'name' => 'home_principle_1_head', 'type' => 'text',     'default_value' => 'Catalogue' ),
				array( 'key' => 'field_nqa_opt_p1_b', 'label' => 'Principle 1 — body',    'name' => 'home_principle_1_body', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Gather and record the documents, stories, and materials that make up our shared history — newspaper articles, photographs, personal accounts, event records.' ),
				array( 'key' => 'field_nqa_opt_p2_n', 'label' => 'Principle 2 — number',  'name' => 'home_principle_2_num',  'type' => 'text',     'default_value' => '02' ),
				array( 'key' => 'field_nqa_opt_p2_h', 'label' => 'Principle 2 — heading', 'name' => 'home_principle_2_head', 'type' => 'text',     'default_value' => 'Curate' ),
				array( 'key' => 'field_nqa_opt_p2_b', 'label' => 'Principle 2 — body',    'name' => 'home_principle_2_body', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Organize entries into meaningful collections that reflect our diverse identities and experiences — by theme, era, municipality, and community.' ),
				array( 'key' => 'field_nqa_opt_p3_n', 'label' => 'Principle 3 — number',  'name' => 'home_principle_3_num',  'type' => 'text',     'default_value' => '03' ),
				array( 'key' => 'field_nqa_opt_p3_h', 'label' => 'Principle 3 — heading', 'name' => 'home_principle_3_head', 'type' => 'text',     'default_value' => 'Preserve' ),
				array( 'key' => 'field_nqa_opt_p3_b', 'label' => 'Principle 3 — body',    'name' => 'home_principle_3_body', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Ensure these histories are cared for and carried forward — with source liveness checks, Wayback Machine captures, and careful consent management.' ),

				// ─ Submit CTA block ───────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_cta_block', 'label' => 'Homepage — Submit CTA', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_cta_bh',  'label' => 'Heading',         'name' => 'home_cta_block_heading', 'type' => 'text',     'default_value' => 'Your story belongs here.' ),
				array( 'key' => 'field_nqa_opt_cta_bb',  'label' => 'Body text',        'name' => 'home_cta_block_body',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'The Niagara Queer Archive is only as complete as the stories shared with it. Whether you have a memory, a photograph, a document, or an artifact — we want to hear from you.' ),
				array( 'key' => 'field_nqa_opt_cta_bl1', 'label' => 'Button 1 — label', 'name' => 'home_cta_block_l1',      'type' => 'text',     'default_value' => 'Submit your story' ),
				array( 'key' => 'field_nqa_opt_cta_bu1', 'label' => 'Button 1 — URL',   'name' => 'home_cta_block_u1',      'type' => 'url',      'instructions' => 'Leave blank to use /tell/' ),
				array( 'key' => 'field_nqa_opt_cta_bl2', 'label' => 'Button 2 — label', 'name' => 'home_cta_block_l2',      'type' => 'text',     'default_value' => 'Learn about the archive' ),
				array( 'key' => 'field_nqa_opt_cta_bu2', 'label' => 'Button 2 — URL',   'name' => 'home_cta_block_u2',      'type' => 'url',      'instructions' => 'Leave blank to use /about/' ),

				// ─ Newsletter section ─────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_nl', 'label' => 'Homepage — Newsletter', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_nl_h', 'label' => 'Heading',   'name' => 'home_newsletter_heading', 'type' => 'text',     'default_value' => 'Get archive updates in your inbox' ),
				array( 'key' => 'field_nqa_opt_nl_b', 'label' => 'Body text', 'name' => 'home_newsletter_body',    'type' => 'textarea', 'rows' => 2, 'default_value' => 'New records, collection launches, and storytelling events — delivered when there\'s something worth telling.' ),

				// ─ Global labels ──────────────────────────────────────────────
				array( 'key' => 'field_nqa_opt_tab_labels', 'label' => 'Global labels', 'name' => '', 'type' => 'tab', 'placement' => 'top' ),

				array( 'key' => 'field_nqa_opt_feat_label',  'label' => '"Featured Collection" heading',   'name' => 'global_featured_label',      'type' => 'text', 'default_value' => 'Featured Collection', 'instructions' => 'Appears on the homepage featured collection block.' ),
				array( 'key' => 'field_nqa_opt_view_all',    'label' => '"View all collections" link text', 'name' => 'global_view_all_collections', 'type' => 'text', 'default_value' => 'View all collections →' ),
				array( 'key' => 'field_nqa_opt_recent_head', 'label' => '"Recently Added" heading',         'name' => 'home_recent_heading',         'type' => 'text', 'default_value' => 'Recently Added' ),
				array( 'key' => 'field_nqa_opt_recent_link', 'label' => '"View all records" link text',     'name' => 'home_recent_link',            'type' => 'text', 'default_value' => 'View all records →' ),
			),
		) );

		// ── Privacy Policy ────────────────────────────────────────────────────

		acf_add_local_field_group( array(
			'key'      => 'group_nqa_privacy',
			'title'    => 'Privacy Policy content',
			'location' => array( array( array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => 'page-privacy-policy',
			) ) ),
			'fields'   => array(

				array( 'key' => 'field_nqa_prv_lede', 'label' => 'Page lede', 'name' => 'privacy_hero_lede', 'type' => 'text', 'default_value' => 'Last updated: July 2025 — Written for the NQA community, not a legal department.', 'instructions' => 'Shown beneath the page title.' ),

				// §1 — The short version
				array( 'key' => 'field_nqa_prv_s1_h',  'label' => '§1 — Heading',       'name' => 'privacy_s1_heading',   'type' => 'text',     'default_value' => 'The short version' ),
				array( 'key' => 'field_nqa_prv_s1_hl', 'label' => '§1 — Highlight box', 'name' => 'privacy_s1_highlight', 'type' => 'textarea', 'rows' => 3, 'default_value' => 'We collect only what we need to run the archive. We don\'t sell your data. We don\'t track you beyond basic server logs. Submissions are handled confidentially. You can ask us to remove your data at any time.' ),
				array( 'key' => 'field_nqa_prv_s1_b',  'label' => '§1 — Body',          'name' => 'privacy_s1_body',      'type' => 'textarea', 'rows' => 3, 'default_value' => 'The Niagara Queer Archive is a community project. We handle your information with the same care and discretion we ask of our community. This policy explains what we collect, why, and what you can do about it.' ),

				// §2 — What we collect
				array( 'key' => 'field_nqa_prv_s2_h', 'label' => '§2 — Heading', 'name' => 'privacy_s2_heading', 'type' => 'text',     'default_value' => 'What we collect and why' ),
				array( 'key' => 'field_nqa_prv_s2_b', 'label' => '§2 — Body',    'name' => 'privacy_s2_body',    'type' => 'textarea', 'rows' => 10, 'default_value' => "<strong>Contact form submissions.</strong> When you contact us or submit a story, we receive your name, email address, and message. We use this only to respond to you and to process your submission. We do not add you to any mailing list without your explicit consent.\n\n<strong>Archive submissions.</strong> When you submit a story, photograph, or other material, we store the content of your submission, your contact details, and your credit and consent preferences. These are used solely to manage your submission within the archive.\n\n<strong>Newsletter sign-ups.</strong> If you subscribe to our email list, we store your email address. You can unsubscribe at any time via the link in any email we send.\n\n<strong>Server logs.</strong> Our web host automatically logs basic access data (IP addresses, browser type, pages visited, timestamps). These logs are used for security and performance monitoring and are not shared.\n\n<strong>Cookies.</strong> This site uses only essential cookies — for login sessions (staff only) and security. No third-party tracking cookies are used.", 'instructions' => 'Separate paragraphs with a blank line. HTML allowed (e.g. <strong>Section name.</strong> Description).' ),

				// §3 — Archive submissions
				array( 'key' => 'field_nqa_prv_s3_h',  'label' => '§3 — Heading',       'name' => 'privacy_s3_heading',   'type' => 'text',     'default_value' => 'Archive submissions and consent' ),
				array( 'key' => 'field_nqa_prv_s3_b',  'label' => '§3 — Body',          'name' => 'privacy_s3_body',      'type' => 'textarea', 'rows' => 6, 'default_value' => "Submissions to the archive are held in confidence during our review process. Before any submission appears on the public site, a human reviews it and confirms that it meets our curation standards and the contributor's stated consent preferences.\n\nYou may submit under your full name, initials, or anonymously. Your credit preference is honoured in all published material.\n\nIf your submission includes information about other people, we may reach out to confirm consent before publishing. We will not publish personally identifying information about private individuals without their knowledge." ),
				array( 'key' => 'field_nqa_prv_s3_hl', 'label' => '§3 — Highlight box', 'name' => 'privacy_s3_highlight', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Use {email} where the site admin email link should appear.', 'default_value' => 'You can request removal or amendment of any submission at any time. We will action removal requests within 14 days. Email us at {email}.' ),

				// §4 — Your rights
				array( 'key' => 'field_nqa_prv_s4_h',    'label' => '§4 — Heading',    'name' => 'privacy_s4_heading', 'type' => 'text',     'default_value' => 'Your rights' ),
				array( 'key' => 'field_nqa_prv_s4_intro', 'label' => '§4 — Intro',      'name' => 'privacy_s4_intro',   'type' => 'text',     'default_value' => 'You have the right to:' ),
				array( 'key' => 'field_nqa_prv_s4_items', 'label' => '§4 — List items', 'name' => 'privacy_s4_items',   'type' => 'textarea', 'rows' => 6, 'instructions' => 'One item per line.', 'default_value' => "Access a copy of the personal data we hold about you\nRequest correction of inaccurate data\nRequest deletion of your data\nWithdraw consent for your submission to be published\nObject to how we use your information" ),
				array( 'key' => 'field_nqa_prv_s4_ft',    'label' => '§4 — Footer',     'name' => 'privacy_s4_footer',  'type' => 'textarea', 'rows' => 2, 'instructions' => 'Use {email} where the site admin email link should appear.', 'default_value' => 'To exercise any of these rights, contact us at {email}. We will respond within 30 days.' ),

				// §5 — Third-party services
				array( 'key' => 'field_nqa_prv_s5_h',    'label' => '§5 — Heading',    'name' => 'privacy_s5_heading', 'type' => 'text',     'default_value' => 'Third-party services' ),
				array( 'key' => 'field_nqa_prv_s5_intro', 'label' => '§5 — Intro',      'name' => 'privacy_s5_intro',   'type' => 'text',     'default_value' => 'This site uses the following third-party services:' ),
				array( 'key' => 'field_nqa_prv_s5_items', 'label' => '§5 — List items', 'name' => 'privacy_s5_items',   'type' => 'textarea', 'rows' => 6, 'instructions' => 'One item per line. HTML allowed (e.g. <strong>Name</strong> — description).', 'default_value' => "<strong>Contact Form 7</strong> — processes contact and submission forms. Form data is transmitted to our server and stored in our database. No data is sent to third parties by CF7.\n<strong>Dreamhost</strong> — our web host. Server logs are maintained under Dreamhost's privacy policy.\n<strong>Wayback Machine (Internet Archive)</strong> — we submit external source URLs for archival capture. No personal data is shared in this process.\n<strong>Google Maps</strong> — used on some record pages to display a location. Google's privacy policy applies when the map is loaded." ),
				array( 'key' => 'field_nqa_prv_s5_ft',    'label' => '§5 — Footer',     'name' => 'privacy_s5_footer',  'type' => 'text',     'default_value' => 'We do not use Google Analytics, Meta Pixel, or any advertising technology.' ),

				// §6 — Sensitive information
				array( 'key' => 'field_nqa_prv_s6_h', 'label' => '§6 — Heading', 'name' => 'privacy_s6_heading', 'type' => 'text',     'default_value' => 'Sensitive information' ),
				array( 'key' => 'field_nqa_prv_s6_b', 'label' => '§6 — Body',    'name' => 'privacy_s6_body',    'type' => 'textarea', 'rows' => 5, 'default_value' => "The nature of an LGBTQ2S+ archive means we may hold sensitive information — including details about people's identities, relationships, health history, and family situations. We treat all such information with extreme care.\n\nWe do not publish private personal details (home addresses, personal phone numbers, private email addresses) about any individual without explicit consent.\n\nWe maintain separate, staff-only notes for each record where sensitivity concerns are flagged. Public-facing records contain only information that has been sourced, reviewed, and consented." ),

				// §7 — Changes
				array( 'key' => 'field_nqa_prv_s7_h', 'label' => '§7 — Heading', 'name' => 'privacy_s7_heading', 'type' => 'text',     'default_value' => 'Changes to this policy' ),
				array( 'key' => 'field_nqa_prv_s7_b', 'label' => '§7 — Body',    'name' => 'privacy_s7_body',    'type' => 'textarea', 'rows' => 4, 'instructions' => 'Use {email} where the site admin email link should appear.', 'default_value' => "If we make material changes to this policy, we'll update the date at the top of this page and note the change. We don't anticipate frequent changes — this is a simple site with a clear purpose.\n\nQuestions? Email us: {email}" ),
			),
		) );

		// ── Contact page ──────────────────────────────────────────────────────

		acf_add_local_field_group( array(
			'key'      => 'group_nqa_contact',
			'title'    => 'Contact page content',
			'location' => array( array( array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => 'page-contact-us',
			) ) ),
			'fields'   => array(
				array( 'key' => 'field_nqa_ctp_heading',  'label' => 'Hero heading',           'name' => 'contact_hero_heading',  'type' => 'text',     'default_value' => 'We\'d love to hear from you.' ),
				array( 'key' => 'field_nqa_ctp_intro',    'label' => 'Hero intro',             'name' => 'contact_hero_intro',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'Whether you have a question, want to get involved, are looking to contribute materials, or just want to learn more about what we\'re building — reach out. We\'ll get back to you as soon as we can.' ),
				array( 'key' => 'field_nqa_ctp_note_h',   'label' => 'Values note — heading', 'name' => 'contact_note_heading',  'type' => 'text',     'default_value' => 'A note on our values' ),
				array( 'key' => 'field_nqa_ctp_note_b',   'label' => 'Values note — body',    'name' => 'contact_note_body',     'type' => 'textarea', 'rows' => 2, 'default_value' => 'The Niagara Queer Archive is a space built on respect. Hate, bigotry, and bad-faith contact are not welcome. We\'re here for community — not for debate.' ),
				array( 'key' => 'field_nqa_ctp_sb_h',     'label' => 'Sidebar heading',        'name' => 'contact_sidebar_heading','type' => 'text',     'default_value' => 'What can we help with?' ),
				array( 'key' => 'field_nqa_ctp_sb_items', 'label' => 'Sidebar list items',    'name' => 'contact_sidebar_items', 'type' => 'textarea', 'rows' => 7, 'instructions' => 'One item per line.', 'default_value' => "Questions about the archive or a specific record\nWanting to volunteer or get involved\nInstitutional partnerships or research inquiries\nMedia and press requests\nAccessibility or consent requests\nAnything else — we read every message" ),
			),
		) );

		// ── Tell Your Story page ──────────────────────────────────────────────

		acf_add_local_field_group( array(
			'key'      => 'group_nqa_tell',
			'title'    => 'Tell Your Story content',
			'location' => array( array( array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => 'page-tell',
			) ) ),
			'fields'   => array(
				// Hero
				array( 'key' => 'field_nqa_tlp_h1',   'label' => 'Hero heading', 'name' => 'tell_hero_heading', 'type' => 'text',     'default_value' => 'Tell your story.' ),
				array( 'key' => 'field_nqa_tlp_lede',  'label' => 'Hero lede',   'name' => 'tell_hero_lede',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'We welcome contributions from anyone connected to Niagara\'s LGBTQ2S+ communities. Every story, photograph, document, or artifact helps us build a fuller picture of our history in this region.' ),
				// Sidebar
				array( 'key' => 'field_nqa_tlp_sb_h',    'label' => 'Sidebar heading',    'name' => 'tell_sidebar_heading', 'type' => 'text',     'default_value' => 'Before you submit' ),
				array( 'key' => 'field_nqa_tlp_sb_body',  'label' => 'Sidebar intro',      'name' => 'tell_sidebar_body',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'The Niagara Queer Archive is built on trust, care, and community. Your story or materials will help ensure that Queer lives in our region are remembered, respected, and accessible for years to come.' ),
				array( 'key' => 'field_nqa_tlp_sb_items', 'label' => 'Sidebar list items', 'name' => 'tell_sidebar_items',   'type' => 'textarea', 'rows' => 6, 'instructions' => 'One item per line. HTML allowed (e.g. <strong>Label</strong> Body text).', 'default_value' => "<strong>Consent matters</strong> Only submit materials you have the right to share. If others are included, please note whether you have their permission.\n<strong>You are in control</strong> You choose how your name appears — full name, initials, or anonymous. You can request changes or removal at any time.\n<strong>How submissions are used</strong> With your permission, your contribution may appear in the online archive, physical archive, curated collections, or future exhibitions." ),
				// Form column
				array( 'key' => 'field_nqa_tlp_form_h', 'label' => 'Form heading', 'name' => 'tell_form_heading', 'type' => 'text', 'default_value' => 'Submit an entry' ),
				// FAQ
				array( 'key' => 'field_nqa_tlp_faq_h',  'label' => 'FAQ heading',    'name' => 'tell_faq_heading',    'type' => 'text', 'default_value' => 'Frequently Asked Questions' ),
				array( 'key' => 'field_nqa_tlp_faq_1q', 'label' => 'FAQ 1 — question', 'name' => 'tell_faq_1_question', 'type' => 'text', 'default_value' => 'What can I submit?' ),
				array( 'key' => 'field_nqa_tlp_faq_1a', 'label' => 'FAQ 1 — answer',   'name' => 'tell_faq_1_answer',   'type' => 'textarea', 'rows' => 5, 'instructions' => 'One item per line renders as a bullet list. A single block of text renders as a paragraph.', 'default_value' => "Personal stories, memories, or reflections (written, audio, or video)\nPhotographs (digital or scanned)\nEvent flyers, posters, or programs\nNewspaper clippings or documents\nLetters, journals, or other materials\nObjects or artifacts (please describe — we'll contact you)" ),
				array( 'key' => 'field_nqa_tlp_faq_2q', 'label' => 'FAQ 2 — question', 'name' => 'tell_faq_2_question', 'type' => 'text', 'default_value' => 'How will submissions be used?' ),
				array( 'key' => 'field_nqa_tlp_faq_2a', 'label' => 'FAQ 2 — answer',   'name' => 'tell_faq_2_answer',   'type' => 'textarea', 'rows' => 3, 'default_value' => 'Your contribution may appear in our online archive, physical archive, or both. Entries may be included in curated collections, exhibitions, or educational projects. We will always credit contributors unless you request anonymity.' ),
				array( 'key' => 'field_nqa_tlp_faq_3q', 'label' => 'FAQ 3 — question', 'name' => 'tell_faq_3_question', 'type' => 'text', 'default_value' => 'What control do I have?' ),
				array( 'key' => 'field_nqa_tlp_faq_3a', 'label' => 'FAQ 3 — answer',   'name' => 'tell_faq_3_answer',   'type' => 'textarea', 'rows' => 3, 'default_value' => 'You choose how your name appears. You can request removal or changes at any time by contacting us. You set the terms of your contribution during submission.' ),
				array( 'key' => 'field_nqa_tlp_faq_4q', 'label' => 'FAQ 4 — question', 'name' => 'tell_faq_4_question', 'type' => 'text', 'default_value' => 'What permissions do I need for other people\'s names?' ),
				array( 'key' => 'field_nqa_tlp_faq_4a', 'label' => 'FAQ 4 — answer',   'name' => 'tell_faq_4_answer',   'type' => 'textarea', 'rows' => 3, 'default_value' => 'By submitting, you confirm you have the right to share this material. If your submission includes other people, please note their names and whether you have their permission to share.' ),
				// CTA
				array( 'key' => 'field_nqa_tlp_cta_h',     'label' => 'CTA heading',      'name' => 'tell_cta_heading', 'type' => 'text',     'default_value' => 'Have questions first?' ),
				array( 'key' => 'field_nqa_tlp_cta_b',     'label' => 'CTA body',         'name' => 'tell_cta_body',    'type' => 'textarea', 'rows' => 2, 'default_value' => 'We\'re happy to help you figure out what to submit and how.' ),
				array( 'key' => 'field_nqa_tlp_cta_label', 'label' => 'CTA button label', 'name' => 'tell_cta_label',   'type' => 'text',     'default_value' => 'Contact us' ),
			),
		) );

		// ── Collections page ──────────────────────────────────────────────────

		acf_add_local_field_group( array(
			'key'      => 'group_nqa_collections_page',
			'title'    => 'Collections page content',
			'location' => array( array( array(
				'param'    => 'page_template',
				'operator' => '==',
				'value'    => 'page-collections',
			) ) ),
			'fields'   => array(
				array( 'key' => 'field_nqa_cpg_heading', 'label' => 'Hero heading', 'name' => 'col_page_heading', 'type' => 'text',     'default_value' => 'Curated windows into Niagara\'s queer history.' ),
				array( 'key' => 'field_nqa_cpg_lede',    'label' => 'Hero lede',    'name' => 'col_page_lede',    'type' => 'textarea', 'rows' => 3, 'default_value' => 'Collections bring individual records together into a narrative. Each collection is a thematic lens — a way of seeing connections across people, places, eras, and communities that might not be visible record by record.' ),
			),
		) );
	}
);
