<?php
/**
 * Niagara Queer Archive — theme bootstrap.
 *
 * All archive features live in the nqa-archive mu-plugin.
 * This file only handles what the theme layer must own:
 * loading the Google Fonts that power the NQA design system.
 *
 * @package NQA
 */

defined( 'ABSPATH' ) || exit;

function nqa_theme_fonts(): void {
	wp_enqueue_style(
		'nqa-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,700;1,9..144,400&family=Space+Mono:wght@400;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap',
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'nqa_theme_fonts' );
add_action( 'admin_enqueue_scripts', 'nqa_theme_fonts' ); // editor preview
