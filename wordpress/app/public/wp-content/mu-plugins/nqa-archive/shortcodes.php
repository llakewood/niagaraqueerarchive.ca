<?php
/**
 * Reusable archive shortcodes.
 *
 * [nqa_hero]                — hero section with live stat counts (homepage only)
 * [nqa_featured_collection] — pinned or random collection feature card
 * [nqa_recent_records]      — N most-recently-modified published records
 *
 * Used in front-page.html and the Collections page via <!-- wp:shortcode -->.
 * All use <span> (not <div>) for children inside <a> so wpautop's
 * block-before-inline paragraph injection doesn't corrupt the markup.
 */

defined( 'ABSPATH' ) || exit;

// ── Hero ──────────────────────────────────────────────────────────────────────

add_shortcode( 'nqa_hero', 'nqa_hero_shortcode' );

function nqa_hero_shortcode() {
	// Dynamic stat counts.
	$total = 0;
	foreach ( nqa_content_types() as $type ) {
		$c      = wp_count_posts( $type );
		$total += isset( $c->publish ) ? (int) $c->publish : 0;
	}
	$article_count    = (int) ( wp_count_posts( 'post' )->publish ?? 0 );
	$collection_count = (int) wp_count_terms( array( 'taxonomy' => 'nqa_collection', 'hide_empty' => false ) );
	$muni_count       = (int) wp_count_terms( array( 'taxonomy' => 'municipality',   'hide_empty' => false ) );

	$collections_url = esc_url( home_url( '/collections/' ) );
	$tell_url        = esc_url( home_url( '/tell/' ) );

	// Compact string — no newlines between elements — minimises wpautop interference.
	$h  = '<section class="home-hero">';
	$h .= '<div class="home-hero__inner">';

	// Left column: static copy.
	$h .= '<div>';
	$h .= '<div class="home-hero__tag">Niagara, Ontario &mdash; Est. 2025</div>';
	$h .= '<h1>Preserving Niagara&rsquo;s <em>Queer</em> past &mdash; celebrating our living history.</h1>';
	$h .= '<p class="home-hero__lede">A community project dedicated to cataloguing, curating, and preserving LGBTQ2S+ stories across the Niagara region &mdash; from St.&nbsp;Catharines to Fort Erie, Welland to Niagara-on-the-Lake.</p>';
	$h .= '<div class="home-hero__ctas">';
	$h .= '<a href="' . $collections_url . '" class="btn btn--primary">Browse the Archive</a>';
	$h .= '<a href="' . $collections_url . '" class="btn btn--ghost">Explore Collections</a>';
	$h .= '<a href="' . $tell_url . '" class="btn btn--ghost">Submit Your Story</a>';
	$h .= '</div>';
	$h .= '</div>';

	// Right column: live stats.
	$h .= '<div class="home-hero__stats">';
	$h .= '<div class="home-hero__stats-title">Archive at a Glance</div>';
	$h .= '<div class="home-hero__stat-grid">';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $total . '<em>+</em></div><div class="home-hero__stat-label">Records</div><div class="home-hero__stat-sub">People, orgs, events, places</div></div>';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $article_count . '</div><div class="home-hero__stat-label">Archival Articles</div><div class="home-hero__stat-sub">Niagara press, sourced &amp; preserved</div></div>';
	$h .= '<hr class="home-hero__stat-divider">';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $collection_count . '</div><div class="home-hero__stat-label">Collections</div><div class="home-hero__stat-sub">Themed, curated sets</div></div>';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $muni_count . '</div><div class="home-hero__stat-label">Municipalities</div><div class="home-hero__stat-sub">Across the Niagara region</div></div>';
	$h .= '</div>';
	$h .= '</div>';

	$h .= '</div>'; // /home-hero__inner
	$h .= '</section>';

	return $h;
}

// ── Featured Collection ───────────────────────────────────────────────────────

add_shortcode( 'nqa_featured_collection', 'nqa_featured_collection_shortcode' );

function nqa_featured_collection_shortcode() {
	$term = null;

	// Prefer a collection pinned via the ACF "Featured collection" checkbox.
	$pinned = get_terms( array(
		'taxonomy'   => 'nqa_collection',
		'hide_empty' => false,
		'meta_query' => array(
			array( 'key' => 'nqa_collection_featured', 'value' => '1', 'compare' => '=' ),
		),
		'number' => 1,
	) );
	if ( ! is_wp_error( $pinned ) && ! empty( $pinned ) ) {
		$term = $pinned[0];
	}

	// Fall back to a random published collection.
	if ( ! $term ) {
		$terms = get_terms( array( 'taxonomy' => 'nqa_collection', 'hide_empty' => true ) );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[ array_rand( $terms ) ];
		}
	}

	if ( ! $term ) {
		return '';
	}

	// Cycle accent colour through NQA palette by term_id.
	$palette = array( 'var(--nqa-violet)', 'var(--nqa-pink)', 'var(--nqa-yellow)' );
	$colour  = $palette[ $term->term_id % 3 ];
	$url     = esc_url( get_term_link( $term ) );
	$name    = esc_html( $term->name );
	$desc    = $term->description
	           ? esc_html( wp_trim_words( $term->description, 55, '&hellip;' ) )
	           : '';
	$count   = (int) $term->count;
	$collections_url = esc_url( home_url( '/collections/' ) );

	// Use <span> (not <div>) inside <a> so wpautop doesn't inject </p> before block children.
	$h  = '<section class="home-featured">';
	$h .= '<div class="home-featured__inner">';
	$h .= '<div class="section-head"><h2>Featured Collection</h2>';
	$h .= '<a href="' . $collections_url . '" class="eyebrow" style="text-decoration:none">View all collections &rarr;</a></div>';
	$h .= '<a href="' . $url . '" class="col-card col-card--featured">';
	$h .= '<span class="col-card__block" style="background:' . $colour . '"></span>';
	$h .= '<span class="col-card__body">';
	$h .= '<span class="col-card__kicker">Featured &middot; ' . $name . '</span>';
	$h .= '<span class="col-card__title">' . $name . '</span>';
	if ( $desc ) {
		$h .= '<span class="col-card__desc">' . $desc . '</span>';
	}
	$h .= '<span class="col-card__count">View collection (' . $count . ' records) &rarr;</span>';
	$h .= '</span>'; // /col-card__body
	$h .= '</a>';
	$h .= '</div>';
	$h .= '</section>';

	return $h;
}

// ── Recently Added ────────────────────────────────────────────────────────────

add_shortcode( 'nqa_recent_records', 'nqa_recent_records_shortcode' );

function nqa_recent_records_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'count' => 3,
		),
		$atts,
		'nqa_recent_records'
	);

	$type_labels = array(
		'post'       => 'Article',
		'nqa_person' => 'Person',
		'nqa_org'    => 'Organization',
		'nqa_event'  => 'Event',
		'nqa_place'  => 'Place',
	);

	$query = new WP_Query( array(
		'post_type'           => nqa_content_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $atts['count'],
		'orderby'             => 'modified',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	) );

	if ( ! $query->have_posts() ) {
		return '';
	}

	$cards = '';
	while ( $query->have_posts() ) {
		$query->the_post();

		$post_type  = get_post_type();
		$type_label = $type_labels[ $post_type ] ?? ucfirst( $post_type );
		$permalink  = get_permalink();
		$title      = get_the_title();
		$excerpt    = wp_trim_words( get_the_excerpt(), 20, '&hellip;' );

		$muni_terms = get_the_terms( get_the_ID(), 'municipality' );
		$muni       = ( $muni_terms && ! is_wp_error( $muni_terms ) )
		              ? esc_html( $muni_terms[0]->name )
		              : '';

		$col_terms = get_the_terms( get_the_ID(), 'nqa_collection' );
		$col       = ( $col_terms && ! is_wp_error( $col_terms ) )
		             ? esc_html( $col_terms[0]->name )
		             : '';

		$meta = '';
		if ( $muni || $col ) {
			$meta .= '<span class="cat-card__meta">';
			if ( $muni ) { $meta .= '<span>' . $muni . '</span>'; }
			if ( $col )  { $meta .= '<span>' . $col  . '</span>'; }
			$meta .= '</span>';
		}

		$cards .= sprintf(
			'<a href="%s" class="cat-card"><span class="cat-card__type">%s</span><span class="cat-card__title">%s</span>%s%s</a>',
			esc_url( $permalink ),
			esc_html( $type_label ),
			esc_html( $title ),
			$excerpt ? '<span class="cat-card__excerpt">' . esc_html( $excerpt ) . '</span>' : '',
			$meta
		);
	}

	wp_reset_postdata();

	$collections_url = esc_url( home_url( '/collections/' ) );
	return '<section class="home-recent"><div class="home-recent__inner">'
		. '<div class="section-head"><h2>Recently Added</h2>'
		. '<a href="' . $collections_url . '" class="eyebrow" style="text-decoration:none">View all records &rarr;</a></div>'
		. '<div class="home-recent__grid">' . $cards . '</div>'
		. '</div></section>';
}
