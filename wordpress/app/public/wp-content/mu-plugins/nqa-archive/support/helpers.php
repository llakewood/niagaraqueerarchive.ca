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

/**
 * True when a record is safely historical (deceased person, defunct org,
 * closed place, or any past event). Historical records are fully public.
 * Active/living records gate sensitive fields behind subscriber login.
 */
function nqa_is_historical( int $post_id ) : bool {
	switch ( get_post_type( $post_id ) ) {
		case 'nqa_person':
			return (bool) get_field( 'died', $post_id );

		case 'nqa_org':
			$status = get_field( 'status', $post_id );
			return $status === 'defunct' || (bool) get_field( 'dissolved', $post_id );

		case 'nqa_place':
			// Only treat as historical if explicitly marked closed (meta key present
			// and set to 0). Unset = unknown = treat as active (safer default).
			$meta = get_post_meta( $post_id, 'still_exists', true );
			return $meta !== '' && ! (bool) $meta;

		case 'nqa_event':
		case 'post':
			return true; // archival by nature

		default:
			return false;
	}
}

/**
 * Human-readable active period derived from existing date fields.
 * Returns '' when no date data is available.
 */
function nqa_active_period( int $post_id ) : string {
	switch ( get_post_type( $post_id ) ) {
		case 'nqa_person':
			$born = get_field( 'born', $post_id );
			$died = get_field( 'died', $post_id );
			$parts = array();
			if ( $born ) { $parts[] = 'b. ' . $born; }
			if ( $died ) { $parts[] = 'd. ' . $died; }
			return implode( ' — ', $parts );

		case 'nqa_org':
			$founded   = get_field( 'founded', $post_id );
			$dissolved = get_field( 'dissolved', $post_id );
			if ( $founded && $dissolved ) { return $founded . ' – ' . $dissolved; }
			if ( $founded )              { return 'Est. ' . $founded; }
			if ( $dissolved )            { return 'Dissolved ' . $dissolved; }
			return '';

		case 'nqa_place':
			return (string) ( get_field( 'years_active', $post_id ) ?? '' );

		case 'nqa_event':
			$start = get_field( 'start_date', $post_id );
			$end   = get_field( 'end_date', $post_id );
			if ( $start && $end && $start !== $end ) { return $start . ' – ' . $end; }
			return $start ?: ( $end ?: '' );

		default:
			return '';
	}
}

/**
 * HTML shown in place of a gated field value when the visitor lacks subscriber
 * access. Distinguishes pending members (already registered, awaiting approval)
 * from visitors who haven't registered yet.
 */
function nqa_gated_field_html() : string {
	if ( is_user_logged_in() ) {
		return '<span class="nqa-gated nqa-gated--pending">'
			. 'Your account is pending approval. You\'ll receive an email once approved.'
			. '</span>';
	}
	return '<span class="nqa-gated">'
		. 'Visible to registered members. '
		. '<a class="nqa-gated__cta" href="' . esc_url( wp_registration_url() ) . '">Register free</a>'
		. ' or <a class="nqa-gated__cta" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a>'
		. '</span>';
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
