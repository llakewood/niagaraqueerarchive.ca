<?php
/**
 * Registers and enqueues the NQA stylesheet. The palette CSS custom properties
 * are generated from nqa_palette() and prepended as an inline style on the
 * same handle so modules only need var(--nqa-*) — no hard-coded hexes.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'nqa',
			WPMU_PLUGIN_URL . '/nqa-archive/assets/nqa.css',
			array(),
			NQA_VERSION
		);
		wp_add_inline_style( 'nqa', nqa_css_vars( ':root' ) );
	},
	5
);
