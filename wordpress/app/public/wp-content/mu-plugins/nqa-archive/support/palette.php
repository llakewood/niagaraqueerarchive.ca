<?php
/**
 * Shared palette — the single source of truth for the archive's colour-blocked
 * visual language. Every front-end component references the --nqa-* custom
 * properties emitted here rather than hard-coding hexes, so the palette changes
 * in exactly one place.
 *
 * The hexes are PINNED (never swapped for theme preset var()s): those retrack
 * across Twenty Twenty-Five style variations and break text contrast. Every
 * pairing below is audited WCAG 2.1 AA against the cream/white grounds.
 */

defined( 'ABSPATH' ) || exit;

/** The pinned archive palette, keyed by role. */
function nqa_palette() {
	return array(
		'violet' => '#503AA8',
		'yellow' => '#FFEE58',
		'pink'   => '#F6CFF4',
		'cream'  => '#FBFAF3',
		'ink'    => '#111',
		'base'   => '#fff',
	);
}

/**
 * The monospace font stack (theme's Fira Code if present). Contrast-neutral, so
 * it is the one token allowed to retrack to the active theme.
 */
function nqa_mono_stack() {
	return 'var(--wp--preset--font-family--fira-code,ui-monospace,SFMono-Regular,Menlo,monospace)';
}

/**
 * A CSS custom-property block binding the palette (+ mono font) as --nqa-*
 * variables on $selector. Emitted once on :root by the base asset loader; every
 * component then uses var(--nqa-violet) etc.
 */
function nqa_css_vars( $selector = ':root' ) {
	$css = $selector . '{';
	foreach ( nqa_palette() as $role => $hex ) {
		$css .= '--nqa-' . $role . ':' . $hex . ';';
	}
	$css .= '--nqa-mono:' . nqa_mono_stack() . '}';
	return $css;
}
