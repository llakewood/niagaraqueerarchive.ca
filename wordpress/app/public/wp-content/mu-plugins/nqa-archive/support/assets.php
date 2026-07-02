<?php
/**
 * The single front-end stylesheet handle every NQA component attaches to. We
 * register one empty handle ('nqa'), bind the palette to :root once, and expose
 * nqa_add_style() so feature modules append their own (conditionally-loaded)
 * CSS to the same handle instead of each registering a handle of their own.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register + enqueue the base handle and print the palette variables. Priority 5
 * so feature modules (default priority 10) can safely call nqa_add_style().
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'nqa', false, array(), NQA_VERSION );
		wp_enqueue_style( 'nqa' );
		wp_add_inline_style( 'nqa', nqa_css_vars( ':root' ) );
	},
	5
);

/** Append a CSS slice to the shared NQA stylesheet. */
function nqa_add_style( $css ) {
	wp_add_inline_style( 'nqa', $css );
}
