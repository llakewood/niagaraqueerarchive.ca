<?php
/**
 * Plugin Name: NQA – Archive Platform
 * Description: The Niagara Queer Archive's custom code, consolidated into one mu-plugin:
 *              the entity content model (Person / Organization / Event / Place + the
 *              Collection taxonomy), ACF field groups, the single-record "Archive details"
 *              panel, staff-only archival notes, source preservation (Wayback/link-rot),
 *              the Collections wayfinding page, and the archive listing styling + controls.
 *              Modules live in nqa-archive/ and are loaded by this file only (WordPress
 *              auto-loads top-level mu-plugin files, not subdirectories).
 * Version:     3.0.0
 * Author:      Niagara Queer Archive
 *
 * Tracked in git; contains no secrets. Runtime secrets live in the separate,
 * gitignored 0-nqa-runtime-config.php (loaded first via its "0-" prefix).
 */

defined( 'ABSPATH' ) || exit;

define( 'NQA_VERSION', '3.0.0' );
define( 'NQA_DIR', __DIR__ . '/nqa-archive' );

/**
 * Modules are organised into three folders:
 *   support/       — shared foundations (palette, helpers, base assets)
 *   functions/     — logic: content model, ACF fields, admin, intake, CLI tools
 *   presentation/  — front-end views: single-record panels, listings, pages
 * (static assets live in assets/). Load order: support first, then functions
 * (the data layer + tools other code builds on), then presentation. Every
 * module self-registers its own hooks.
 */
$nqa_modules = array(
	// Shared foundations.
	'support/palette.php',
	'support/helpers.php',
	'support/assets.php',
	// Functions — data model + fields, then admin / intake / CLI.
	'functions/content-model.php',
	'functions/fields.php',
	'functions/page-fields.php',
	'functions/stewardship.php',
	'functions/access.php',
	'functions/archival-note.php',
	'functions/preservation.php',
	'functions/geocode.php',
	'functions/leads.php',
	'functions/importers.php',
	'functions/submissions.php',
	'functions/newsletter.php',
	'functions/forms.php',
	'functions/shortcodes.php',
	// Presentation — front-end views, pages, panels.
	'presentation/item-details.php',
	'presentation/collections.php',
	'presentation/resources.php',
	'presentation/listing.php',
	'presentation/listing-controls.php',
	'presentation/view-toggle.php',
	'presentation/map.php',
	'presentation/search.php',
	'presentation/tell.php',
	'presentation/contact.php',
	'presentation/privacy.php',
);

foreach ( $nqa_modules as $nqa_module ) {
	$nqa_path = NQA_DIR . '/' . $nqa_module;
	if ( is_readable( $nqa_path ) ) {
		require_once $nqa_path;
	}
}
unset( $nqa_modules, $nqa_module, $nqa_path );
