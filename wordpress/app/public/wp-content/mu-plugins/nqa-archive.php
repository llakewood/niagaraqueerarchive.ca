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
 * Load order matters: shared support (palette, helpers, base assets) first, then
 * the content model + fields (data layer), then the front-end and admin features
 * that build on them. Every module self-registers its own hooks.
 */
$nqa_modules = array(
	// Shared foundations.
	'support/palette.php',
	'support/helpers.php',
	'support/assets.php',
	// Data layer.
	'content-model.php',
	'fields.php',
	'page-fields.php',
	// Admin.
	'access.php',
	'archival-note.php',
	'preservation.php',
	// Front end.
	'item-details.php',
	'collections.php',
	'listing.php',
	'listing-controls.php',
	'view-toggle.php',
	'map.php',
	'submissions.php',
	'shortcodes.php',
	'search.php',
	'tell.php',
	'contact.php',
	'privacy.php',
	'forms.php',
);

foreach ( $nqa_modules as $nqa_module ) {
	$nqa_path = NQA_DIR . '/' . $nqa_module;
	if ( is_readable( $nqa_path ) ) {
		require_once $nqa_path;
	}
}
unset( $nqa_modules, $nqa_module, $nqa_path );
