<?php
/**
 * The single-record "Archive details" panel: renders an entity's fields as a
 * styled catalogue card below the content, for archival materials (core `post`)
 * and the entity post types. Dynamic — no per-post block markup, no theme change.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ordered [ name, label, kind, gated? ] field specs per post type.
 * kind:  text | url | email | date | select | map | bool | rel | related
 * gated: true = subscriber-only when record is active/living; omit or false = always public
 */
function nqa_field_specs( $post_type ) {
	switch ( $post_type ) {
		case 'nqa_person':
			$specs = array(
				array( 'pronouns', 'Pronouns', 'text' ),
				array( 'born', 'Born', 'text' ),
				array( 'died', 'Died', 'text' ),
				array( 'aliases', 'Also known as', 'text' ),
				array( 'roles', 'Roles', 'text' ),
				array( 'source', 'Source', 'text' ),
				array( 'citation', 'Citation', 'text' ),
				array( 'link', 'Link', 'url' ),
			);
			break;
		case 'nqa_org':
			$specs = array(
				array( 'org_type', 'Type', 'select' ),
				array( 'status', 'Status', 'select' ),
				array( 'founded', 'Founded', 'text' ),
				array( 'dissolved', 'Dissolved', 'text' ),
				array( 'website', 'Website', 'url' ),
				array( 'location', 'Location', 'map', true ),
				array( 'contact_person', 'Contact', 'text', true ),
				array( 'email', 'Email', 'email', true ),
				array( 'phone_number', 'Phone', 'text', true ),
				array( 'source', 'Source', 'text' ),
				array( 'citation', 'Citation', 'text' ),
				array( 'link', 'Link', 'url' ),
			);
			break;
		case 'nqa_event':
			$specs = array(
				array( 'start_date', 'Start', 'date' ),
				array( 'end_date', 'End', 'date' ),
				array( 'recurrence', 'Recurrence', 'text' ),
				array( 'organizer', 'Organizer', 'rel' ),
				array( 'venue', 'Venue', 'rel' ),
				array( 'location', 'Location', 'map' ), // events are always historical
				array( 'source', 'Source', 'text' ),
				array( 'citation', 'Citation', 'text' ),
				array( 'link', 'Link', 'url' ),
			);
			break;
		case 'nqa_place':
			$specs = array(
				array( 'place_type', 'Type', 'select' ),
				array( 'address', 'Address', 'text', true ),
				array( 'location', 'Location', 'map', true ),
				array( 'still_exists', 'Still exists', 'bool' ),
				array( 'years_active', 'Years active', 'text' ),
				array( 'source', 'Source', 'text' ),
				array( 'citation', 'Citation', 'text' ),
				array( 'link', 'Link', 'url' ),
			);
			break;
		default: // archival materials (core post) — always historical
			$specs = array(
				array( 'date', 'Date', 'text' ),
				array( 'location', 'Location', 'map' ),
				array( 'source', 'Source', 'text' ),
				array( 'publisher', 'Publisher', 'url' ),
				array( 'citation', 'Citation', 'text' ),
				array( 'link', 'Link', 'url' ),
				array( 'contact_person', 'Contact', 'text' ),
				array( 'email', 'Email', 'email' ),
				array( 'phone_number', 'Phone', 'text' ),
			);
	}

	// Cross-references: every type shares the "Related entries" relationship
	// field, shown last as the "see also" links.
	$specs[] = array( 'relationship', 'Related entries', 'related' );

	return $specs;
}

/** Render a single field value to HTML (or '' to omit). */
function nqa_format_field( $kind, $name, $post_id, $gated = false ) {
	// Subscriber gate: show prompt instead of value when field is sensitive,
	// record is active/living, and visitor lacks subscriber read access.
	if ( $gated && ! nqa_is_historical( $post_id ) && ! current_user_can( 'read' ) ) {
		return nqa_gated_field_html();
	}
	$as_link = function ( $url ) {
		$display = preg_replace( '#^https?://#', '', untrailingslashit( $url ) );
		return '<a href="' . esc_url( $url ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $display ) . '</a>';
	};
	$val = get_field( $name, $post_id );

	switch ( $kind ) {
		case 'url':
			// The article source `link` uses the preservation fallback so a
			// rotted link degrades to its Wayback snapshot; a live link with a
			// snapshot also gets a secondary "Archived copy" pointer.
			if ( 'link' === $name && function_exists( 'nqa_source_fallback' ) ) {
				$fb = nqa_source_fallback( $post_id );
				if ( ! is_array( $fb ) || empty( $fb['url'] ) ) {
					return '';
				}
				$out = $as_link( $fb['url'] );
				if ( ! empty( $fb['archived'] ) ) {
					$out .= ' <span class="nqa-src-archived">(archived)</span>';
				}
				if ( ! empty( $fb['wayback'] ) ) {
					$out .= '<br><a class="nqa-src-wayback" href="' . esc_url( $fb['wayback'] )
						. '" rel="noopener nofollow" target="_blank">Archived copy (Internet Archive)</a>';
				}
				return $out;
			}
			return ( is_string( $val ) && trim( $val ) !== '' && preg_match( '#^https?://#', $val ) ) ? $as_link( $val ) : '';
		case 'email':
			return ( is_string( $val ) && trim( $val ) !== '' ) ? '<a href="mailto:' . esc_attr( $val ) . '">' . esc_html( $val ) . '</a>' : '';
		case 'date':
		case 'text':
			return ( is_string( $val ) && trim( $val ) !== '' ) ? esc_html( $val ) : '';
		case 'select':
			if ( ! $val ) {
				return '';
			}
			$f = get_field_object( $name, $post_id );
			return esc_html( ( $f && isset( $f['choices'][ $val ] ) ) ? $f['choices'][ $val ] : $val );
		case 'map':
			return ( is_array( $val ) && ! empty( $val['address'] ) ) ? esc_html( $val['address'] ) : '';
		case 'bool':
			$raw = get_post_meta( $post_id, $name, true );
			if ( $raw === '' ) {
				return '';
			}
			return $val ? 'Yes' : 'No';
		case 'rel':
			return nqa_render_post_links( nqa_normalize_ids( $val ) );
		case 'related':
			// Bi-directional: forward links this post sets, plus the reverse
			// links (other entries that name this one in their `relationship`).
			$forward = nqa_normalize_ids( $val );
			$reverse = nqa_reverse_related_ids( $post_id );
			$ids     = array_values( array_diff( array_unique( array_merge( $forward, $reverse ) ), array( (int) $post_id ) ) );
			return nqa_render_post_links( $ids, '' ); // chips; CSS gap spaces them
	}
	return '';
}

/** Published entries that reference $post_id in their `relationship` field. */
function nqa_reverse_related_ids( $post_id ) {
	$ids = get_posts(
		array(
			'post_type'        => nqa_content_types(),
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			// ACF stores relationship ids serialized as e.g. s:3:"270"; the quoted
			// id makes the LIKE exact (won't match 1270 or 2700).
			'meta_query'       => array(
				array(
					'key'     => 'relationship',
					'value'   => '"' . (int) $post_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);
	return array_map( 'intval', $ids );
}

/** Build the "Archive details" panel for a post from its type's fields. */
function nqa_render_item_details( $post_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}
	$rows = array();
	foreach ( nqa_field_specs( get_post_type( $post_id ) ) as $spec ) {
		list( $name, $label, $kind ) = $spec;
		$gated                       = ! empty( $spec[3] );
		$html                        = nqa_format_field( $kind, $name, $post_id, $gated );
		if ( $html !== '' && $html !== null ) {
			$rows[] = array( $label, $html, $kind );
		}
	}
	if ( ! $rows ) {
		return '';
	}

	// Historical badge: shown when the record is categorically past.
	$era = '';
	if ( nqa_is_historical( $post_id ) ) {
		$period = nqa_active_period( $post_id );
		$era    = '<div class="nqa-item-details__era">'
			. '<span class="nqa-item-details__era-label">Historical record</span>'
			. ( $period ? '<span class="nqa-item-details__era-period">' . esc_html( $period ) . '</span>' : '' )
			. '</div>';
	}

	$out = '<aside class="nqa-item-details"><h2 class="nqa-item-details__title">Archive details</h2>' . $era . '<dl class="nqa-item-details__list">';
	foreach ( $rows as $r ) {
		$mod  = ( 'related' === $r[2] ) ? ' nqa-item-details__row--related' : '';
		$out .= '<div class="nqa-item-details__row' . $mod . '"><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
	}
	$out .= '</dl></aside>';
	return $out;
}

add_filter(
	'the_content',
	function ( $content ) {
		if ( is_singular( nqa_content_types() ) && in_the_loop() && is_main_query() ) {
			$content .= nqa_render_item_details( get_the_ID() );
		}
		return $content;
	},
	20
);

