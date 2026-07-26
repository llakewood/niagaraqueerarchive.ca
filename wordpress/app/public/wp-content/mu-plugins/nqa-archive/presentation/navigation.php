<?php
/**
 * Navigation — register editable menu locations and render them into the block
 * theme's template parts.
 *
 * The header and footer nav used to be hardcoded <a> lists in parts/header.html
 * and parts/footer.html, which meant broken links could only be fixed in code.
 * Instead we register menu locations (which also re-enables Appearance → Menus,
 * hidden by default in a block theme) and render them with wp_nav_menu() via the
 * [nqa_menu] shortcode — the same pattern the theme uses for [nqa_hero] etc.
 *
 * Menu *content* is managed in the admin (or seeded); no links are hardcoded here.
 * If a location has no menu assigned, the shortcode renders nothing rather than a
 * misleading fallback.
 *
 * Locations:
 *   - nqa-header          → the primary site nav in the header
 *   - nqa-footer-archive  → footer "Archive" column
 *   - nqa-footer-about    → footer "About" column
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		register_nav_menus(
			array(
				'nqa-header'         => 'Header navigation',
				'nqa-footer-archive' => 'Footer — Archive column',
				'nqa-footer-about'   => 'Footer — About column',
			)
		);
	},
	11
);

/**
 * Render a registered menu location.
 *
 *   [nqa_menu location="nqa-header" nav_class="site-nav" aria="Site navigation" list_class="site-nav__list"]
 *   [nqa_menu location="nqa-footer-archive" list_class="site-footer__links"]
 *
 * `nav_class` present → wraps the list in a <nav> with that class (and optional
 * `aria` label). Absent → no container, just the <ul>. Returns '' when the
 * location has no menu assigned.
 */
add_shortcode(
	'nqa_menu',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'location'   => '',
				'nav_class'  => '',
				'aria'       => '',
				'list_class' => '',
			),
			$atts,
			'nqa_menu'
		);

		if ( '' === $a['location'] || ! has_nav_menu( $a['location'] ) ) {
			return '';
		}

		$args = array(
			'theme_location' => $a['location'],
			'depth'          => 1,
			'echo'           => false,
			'fallback_cb'    => false,
			'items_wrap'     => '<ul class="' . esc_attr( $a['list_class'] ) . '">%3$s</ul>',
		);

		if ( '' !== $a['nav_class'] ) {
			$args['container']       = 'nav';
			$args['container_class'] = $a['nav_class'];
			if ( '' !== $a['aria'] ) {
				$args['container_aria_label'] = $a['aria'];
			}
		} else {
			$args['container'] = false;
		}

		$html = wp_nav_menu( $args );
		return is_string( $html ) ? $html : '';
	}
);
