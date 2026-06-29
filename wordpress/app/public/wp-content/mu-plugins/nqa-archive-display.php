<?php
/**
 * Plugin Name: NQA – Archive Item Details Display
 * Description: Renders the ACF "Item Details" fields (source, citation, publisher,
 *              date, link, location, contact) as a styled panel below single-post
 *              content. Dynamic — no per-post block markup (no DB bloat), no theme
 *              change. Tracked in git; contains no secrets.
 * Version:     1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the "Archive details" panel for a post from its ACF Item Details fields.
 * Returns '' when there is nothing to show.
 */
function nqa_render_item_details( $post_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$rows = array();
	$add  = function ( $label, $html ) use ( &$rows ) {
		if ( $html !== '' && $html !== null ) {
			$rows[] = array( $label, $html );
		}
	};

	$date = get_field( 'date', $post_id );
	if ( is_string( $date ) && trim( $date ) !== '' ) {
		$add( 'Date', esc_html( $date ) );
	}

	// `location` is an ACF Google Map field (array) — show its address.
	$loc = get_field( 'location', $post_id );
	if ( is_array( $loc ) && ! empty( $loc['address'] ) ) {
		$add( 'Location', esc_html( $loc['address'] ) );
	} elseif ( is_string( $loc ) && trim( $loc ) !== '' ) {
		$add( 'Location', esc_html( $loc ) );
	}

	// Render a URL value as a tidy external link (host + path, no scheme).
	$as_link = function ( $url ) {
		$display = preg_replace( '#^https?://#', '', untrailingslashit( $url ) );
		return '<a href="' . esc_url( $url ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $display ) . '</a>';
	};

	$source = get_field( 'source', $post_id );
	if ( is_string( $source ) && trim( $source ) !== '' ) {
		$add( 'Source', esc_html( $source ) );
	}

	// `publisher` is stored as a URL — link it (fall back to plain text).
	$pub = get_field( 'publisher', $post_id );
	if ( is_string( $pub ) && trim( $pub ) !== '' ) {
		$add( 'Publisher', preg_match( '#^https?://#', $pub ) ? $as_link( $pub ) : esc_html( $pub ) );
	}

	$citation = get_field( 'citation', $post_id );
	if ( is_string( $citation ) && trim( $citation ) !== '' ) {
		$add( 'Citation', esc_html( $citation ) );
	}

	$link = get_field( 'link', $post_id );
	if ( is_string( $link ) && trim( $link ) !== '' ) {
		$add( 'Link', $as_link( $link ) );
	}

	$cp = get_field( 'contact_person', $post_id );
	if ( is_string( $cp ) && trim( $cp ) !== '' ) {
		$add( 'Contact', esc_html( $cp ) );
	}
	$em = get_field( 'email', $post_id );
	if ( is_string( $em ) && trim( $em ) !== '' ) {
		$add( 'Email', '<a href="mailto:' . esc_attr( $em ) . '">' . esc_html( $em ) . '</a>' );
	}
	$ph = get_field( 'phone_number', $post_id );
	if ( is_string( $ph ) && trim( $ph ) !== '' ) {
		$add( 'Phone', esc_html( $ph ) );
	}

	if ( ! $rows ) {
		return '';
	}

	$out  = '<aside class="nqa-item-details"><h2 class="nqa-item-details__title">Archive details</h2><dl class="nqa-item-details__list">';
	foreach ( $rows as $r ) {
		$out .= '<div class="nqa-item-details__row"><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
	}
	$out .= '</dl></aside>';

	return $out;
}

add_filter( 'the_content', function ( $content ) {
	if ( is_singular( 'post' ) && in_the_loop() && is_main_query() ) {
		$content .= nqa_render_item_details( get_the_ID() );
	}
	return $content;
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'nqa-archive', false );
	wp_enqueue_style( 'nqa-archive' );
	$css = '.nqa-item-details{margin-block-start:2.5rem;padding:1.25rem 1.5rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;font-size:.95em}'
		. '.nqa-item-details__title{font-size:1rem;margin:0 0 .75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.75}'
		. '.nqa-item-details__list{margin:0}'
		. '.nqa-item-details__row{display:grid;grid-template-columns:8rem 1fr;gap:.75rem;padding:.4rem 0;border-block-start:1px solid rgba(128,128,128,.2)}'
		. '.nqa-item-details__row:first-of-type{border-block-start:0}'
		. '.nqa-item-details dt{font-weight:600;margin:0}'
		. '.nqa-item-details dd{margin:0;word-break:break-word}'
		. '@media(max-width:480px){.nqa-item-details__row{grid-template-columns:1fr;gap:.1rem}}';
	wp_add_inline_style( 'nqa-archive', $css );
} );
