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

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( nqa_content_types() ) ) {
			return;
		}
		// Colour-blocked "catalogue card". Palette comes from the shared :root
		// --nqa-* variables (audited WCAG 2.1 AA); the card reads as a light inset
		// on dark theme variations because the hexes are pinned, not theme presets.
		$css = '.nqa-item-details{margin-block-start:3rem;background:var(--nqa-cream);color:var(--nqa-ink);'
				. 'border:2px solid var(--nqa-ink);border-radius:0;overflow:hidden;'
				. 'box-shadow:7px 7px 0 var(--nqa-violet);font-size:.95em}'
			// Header bar: solid violet block + tri-colour motif.
			. '.nqa-item-details__title{display:flex;align-items:center;gap:.7rem;margin:0;'
				. 'padding:.85rem 1.25rem;background:var(--nqa-violet);color:var(--nqa-base);'
				. 'font-family:var(--nqa-mono);font-size:.78rem;font-weight:600;'
				. 'letter-spacing:.2em;text-transform:uppercase}'
			. '.nqa-item-details__title::before{content:"";flex:0 0 auto;width:2.4rem;height:.7rem;'
				. 'background:linear-gradient(90deg,var(--nqa-yellow) 0 33.34%,var(--nqa-pink) 0 66.67%,var(--nqa-base) 0)}'
			. '.nqa-item-details__list{margin:0;padding:.35rem 1.25rem .55rem}'
			. '.nqa-item-details__row{display:grid;grid-template-columns:9.5rem 1fr;gap:1rem;'
				. 'align-items:baseline;padding:.7rem 0;'
				. 'border-block-start:1px solid color-mix(in srgb,var(--nqa-ink) 14%,transparent)}'
			. '.nqa-item-details__row:first-of-type{border-block-start:0}'
			// Monospaced catalogue-style term labels.
			. '.nqa-item-details dt{margin:0;padding-block-start:.15rem;color:var(--nqa-violet);'
				. 'font-family:var(--nqa-mono);font-size:.7rem;font-weight:600;'
				. 'letter-spacing:.12em;text-transform:uppercase}'
			. '.nqa-item-details dd{margin:0;line-height:1.55;word-break:break-word}'
			. '.nqa-item-details dd a{color:var(--nqa-violet);text-underline-offset:2px;'
				. 'text-decoration-thickness:1px;transition:background .12s ease}'
			. '.nqa-item-details dd a:hover{background:var(--nqa-yellow);text-decoration:none}'
			// Visible keyboard focus (WCAG 2.1 SC 2.4.7).
			. '.nqa-item-details a:focus-visible{outline:2px solid var(--nqa-ink);outline-offset:2px;border-radius:2px}'
			// Related entries -> tactile chips (the bi-directional cross-references).
			. '.nqa-item-details__row--related dd{display:flex;flex-wrap:wrap;gap:.45rem}'
			. '.nqa-item-details__row--related dd a{display:inline-block;padding:.32rem .7rem;'
				. 'background:var(--nqa-pink);color:var(--nqa-violet);border:1px solid var(--nqa-violet);'
				. 'border-radius:999px;font-size:.85em;line-height:1.25;text-decoration:none;'
				. 'transition:transform .12s ease,background .12s ease,color .12s ease}'
			. '.nqa-item-details__row--related dd a:hover{background:var(--nqa-violet);'
				. 'color:var(--nqa-base);transform:translateY(-1px)}'
			// Historical era badge (cream strip below the violet header)
			. '.nqa-item-details__era{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .85rem;'
				. 'padding:.55rem 1.25rem;background:var(--nqa-yellow);border-bottom:2px solid var(--nqa-ink)}'
			. '.nqa-item-details__era-label{font-family:var(--nqa-mono);font-size:.65rem;font-weight:700;'
				. 'letter-spacing:.15em;text-transform:uppercase;color:var(--nqa-ink)}'
			. '.nqa-item-details__era-period{font-size:.8rem;color:var(--nqa-ink);opacity:.8}'
			// Subscriber-gated field prompt
			. '.nqa-gated{font-size:.85rem;color:#555;font-style:italic}'
			. '.nqa-gated__cta{color:var(--nqa-violet);font-style:normal;font-weight:600;'
				. 'text-underline-offset:2px;text-decoration-thickness:1px}'
			. '.nqa-gated__cta:hover{text-decoration:none;background:var(--nqa-yellow)}'
			. '.nqa-gated--pending{font-style:italic;color:#555}'
			. '@media(max-width:480px){.nqa-item-details{box-shadow:4px 4px 0 var(--nqa-violet)}'
				. '.nqa-item-details__row{grid-template-columns:1fr;gap:.25rem}'
				. '.nqa-item-details dt{padding-block-start:0}}';
		nqa_add_style( $css );
	}
);
