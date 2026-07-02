<?php
/**
 * Shared helpers used across the archive modules: the canonical post-type sets
 * and the relationship-value utilities (formerly duplicated between the CPT and
 * display plugins).
 */

defined( 'ABSPATH' ) || exit;

/** The entity "authority record" post types (entities only, not materials). */
function nqa_entity_post_types() {
	return array( 'nqa_person', 'nqa_org', 'nqa_event', 'nqa_place' );
}

/** All archive content: core `post` (materials) plus the four entity CPTs. */
function nqa_content_types() {
	return array_merge( array( 'post' ), nqa_entity_post_types() );
}

/** Normalize an ACF relationship value (ids or post objects) to an int array. */
function nqa_normalize_ids( $val ) {
	$val = is_array( $val ) ? $val : ( $val ? array( $val ) : array() );
	return array_map(
		function ( $r ) {
			return is_object( $r ) ? (int) $r->ID : (int) $r;
		},
		$val
	);
}

/** Render a list of post IDs as permalink links joined by $sep (skips missing). */
function nqa_render_post_links( $ids, $sep = ', ' ) {
	$links = array();
	foreach ( $ids as $rid ) {
		if ( $rid && get_post_status( $rid ) ) {
			$links[] = '<a href="' . esc_url( get_permalink( $rid ) ) . '">' . esc_html( get_the_title( $rid ) ) . '</a>';
		}
	}
	return $links ? implode( $sep, $links ) : '';
}
