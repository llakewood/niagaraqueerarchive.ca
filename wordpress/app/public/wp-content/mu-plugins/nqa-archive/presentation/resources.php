<?php
/**
 * Resources — a front-facing directory of CURRENT community support services
 * (health, crisis, trans & Two-Spirit, youth & family, faith, arts, advocacy).
 * Distinct from the archive: these point people to help that exists today, so
 * they are the standalone `nqa_resource` CPT (see content-model.php), grouped by
 * the `nqa_resource_cat` taxonomy and rendered here via [nqa_resources], placed
 * on the Resources page.
 *
 * Category order is deliberate (crisis & safety first, so it is never buried);
 * any category not in the list falls to the end, alphabetically. Within a
 * category, resources sort by menu_order then title (Page Attributes → Order
 * lets an editor pin an item to the top). Category intro copy is CMS-owned (the
 * term Description), mirroring nqa_term_intro() on the Collections page.
 */

defined( 'ABSPATH' ) || exit;

/** Deliberate front-end ordering of resource categories by slug. */
function nqa_resource_cat_order() : array {
	return array(
		'crisis',      // Crisis & Safety — always first.
		'health',      // Health & Sexual Health
		'trans-2s',    // Trans & Two-Spirit
		'youth',       // Youth & Family
		'community',   // Community & Social
		'advocacy',    // Advocacy & Activism
		'faith',       // Affirming Faith
		'arts',        // Arts & Culture
	);
}

/**
 * Ordered list of nqa_resource_cat terms that actually have published
 * resources: known slugs first (in nqa_resource_cat_order), then any others
 * alphabetically. Empty categories are dropped.
 */
function nqa_resources_ordered_terms() : array {
	$terms = get_terms( array( 'taxonomy' => 'nqa_resource_cat', 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$by_slug = array();
	foreach ( $terms as $t ) {
		$by_slug[ $t->slug ] = $t;
	}

	$ordered = array();
	foreach ( nqa_resource_cat_order() as $slug ) {
		if ( isset( $by_slug[ $slug ] ) ) {
			$ordered[] = $by_slug[ $slug ];
			unset( $by_slug[ $slug ] );
		}
	}
	// Remaining (unranked) categories, alphabetically by name.
	$rest = array_values( $by_slug );
	usort( $rest, function ( $a, $b ) { return strcasecmp( $a->name, $b->name ); } );

	return array_merge( $ordered, $rest );
}

/** One resource card. Title links out to the website if set, else the record. */
function nqa_resource_card_html( int $post_id ) : string {
	$title   = nqa_decode_entities( get_the_title( $post_id ) );
	$website = (string) get_field( 'website', $post_id );
	$phone   = (string) get_field( 'phone_number', $post_id );
	$email   = (string) get_field( 'email', $post_id );
	$area    = (string) get_field( 'service_area', $post_id );

	// Description: the excerpt, else the trimmed body.
	$desc = has_excerpt( $post_id )
		? get_the_excerpt( $post_id )
		: wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 40, '&hellip;' );
	$desc = nqa_decode_entities( $desc );

	$href      = $website ?: get_permalink( $post_id );
	$is_extern = (bool) $website;

	$h  = '<div class="nqa-res-card">';
	$h .= '<h3 class="nqa-res-card__title">';
	$h .= '<a href="' . esc_url( $href ) . '"' . ( $is_extern ? ' rel="noopener"' : '' ) . '>' . esc_html( $title ) . '</a>';
	$h .= '</h3>';

	if ( $area ) {
		$h .= '<div class="nqa-res-card__area">' . esc_html( $area ) . '</div>';
	}
	if ( $desc ) {
		$h .= '<p class="nqa-res-card__desc">' . esc_html( $desc ) . '</p>';
	}

	// Contact row — only the channels that are set.
	$links = array();
	if ( $website ) {
		$label  = preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $website ) );
		$links[] = '<a class="nqa-res-card__link" href="' . esc_url( $website ) . '" rel="noopener">' . esc_html( $label ) . '</a>';
	}
	if ( $phone ) {
		$tel     = preg_replace( '/[^0-9+]/', '', $phone );
		$links[] = '<a class="nqa-res-card__link" href="tel:' . esc_attr( $tel ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( $email && is_email( $email ) ) {
		$links[] = '<a class="nqa-res-card__link" href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}
	if ( $links ) {
		$h .= '<div class="nqa-res-card__contact">' . implode( '', $links ) . '</div>';
	}

	$h .= '</div>';
	return $h;
}

/**
 * [nqa_resources] — the full directory, grouped by category. Renders nothing but
 * a gentle empty state when no resources are published yet.
 */
add_shortcode( 'nqa_resources', 'nqa_resources_shortcode' );

function nqa_resources_shortcode() : string {
	$terms = nqa_resources_ordered_terms();
	if ( ! $terms ) {
		return '<p class="nqa-res-empty">The resource directory is being compiled. Check back soon.</p>';
	}

	$h = '<div class="nqa-resources">';

	foreach ( $terms as $term ) {
		$q = new WP_Query( array(
			'post_type'      => 'nqa_resource',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array( array(
				'taxonomy' => 'nqa_resource_cat',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			) ),
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'no_found_rows'  => true,
		) );

		if ( ! $q->have_posts() ) {
			continue;
		}

		$intro = nqa_term_intro( 'nqa_resource_cat', $term->slug );

		$h .= '<section class="nqa-res-group">';
		$h .= '<div class="nqa-res-group__head">';
		$h .= '<h2 class="nqa-res-group__title">' . esc_html( $term->name ) . '</h2>';
		if ( $intro ) {
			$h .= '<p class="nqa-res-group__intro">' . esc_html( $intro ) . '</p>';
		}
		$h .= '</div>';

		$h .= '<div class="nqa-res-grid">';
		foreach ( $q->posts as $p ) {
			$h .= nqa_resource_card_html( (int) $p->ID );
		}
		$h .= '</div>';
		$h .= '</section>';
	}
	wp_reset_postdata();

	$h .= '</div>';
	return $h;
}

/**
 * Single resource page: append the public contact channels beneath the body so
 * a standalone /resource/… URL is useful even though the directory is the main
 * entry point. Only runs on singular nqa_resource, in the main query.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'nqa_resource' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$id      = get_the_ID();
	$website = (string) get_field( 'website', $id );
	$phone   = (string) get_field( 'phone_number', $id );
	$email   = (string) get_field( 'email', $id );
	$area    = (string) get_field( 'service_area', $id );

	if ( ! $website && ! $phone && ! $email && ! $area ) {
		return $content;
	}

	$rows = '';
	if ( $area ) {
		$rows .= '<div class="nqa-res-single__row"><span class="nqa-res-single__k">Serves</span><span>' . esc_html( $area ) . '</span></div>';
	}
	if ( $website ) {
		$label = preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $website ) );
		$rows .= '<div class="nqa-res-single__row"><span class="nqa-res-single__k">Website</span><a href="' . esc_url( $website ) . '" rel="noopener">' . esc_html( $label ) . '</a></div>';
	}
	if ( $phone ) {
		$tel   = preg_replace( '/[^0-9+]/', '', $phone );
		$rows .= '<div class="nqa-res-single__row"><span class="nqa-res-single__k">Phone</span><a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $phone ) . '</a></div>';
	}
	if ( $email && is_email( $email ) ) {
		$rows .= '<div class="nqa-res-single__row"><span class="nqa-res-single__k">Email</span><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></div>';
	}

	return $content . '<aside class="nqa-res-single" aria-label="Contact">' . $rows . '</aside>';
} );
