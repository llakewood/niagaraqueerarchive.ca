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
	// Options page copy — falls back to defaults if fields are not yet saved.
	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	// Dynamic stat counts.
	$total = 0;
	foreach ( nqa_content_types() as $type ) {
		$c      = wp_count_posts( $type );
		$total += isset( $c->publish ) ? (int) $c->publish : 0;
	}
	$article_count    = (int) ( wp_count_posts( 'post' )->publish ?? 0 );
	$collection_count = (int) wp_count_terms( array( 'taxonomy' => 'nqa_collection', 'hide_empty' => false ) );
	$muni_count       = (int) wp_count_terms( array( 'taxonomy' => 'municipality',   'hide_empty' => false ) );

	$collections_url = esc_url( $opt( 'home_cta_1_url' ) ?: home_url( '/collections/' ) );
	$cta2_url        = esc_url( $opt( 'home_cta_2_url' ) ?: home_url( '/collections/' ) );
	$tell_url        = esc_url( $opt( 'home_cta_3_url' ) ?: home_url( '/tell/' ) );

	$h  = '<section class="home-hero">';
	$h .= '<div class="home-hero__inner">';

	// Left column.
	$h .= '<div>';
	$h .= '<div class="home-hero__tag">' . esc_html( $opt( 'home_hero_tag', "Niagara, Ontario \xe2\x80\x94 Est. 2025" ) ) . '</div>';
	$h .= '<h1>' . esc_html( $opt( 'home_hero_heading', "Preserving Niagara\xe2\x80\x99s Queer past \xe2\x80\x94 celebrating our living history." ) ) . '</h1>';
	$h .= '<p class="home-hero__lede">' . esc_html( $opt( 'home_hero_lede', "A community project dedicated to cataloguing, curating, and preserving LGBTQ2S+ stories across the Niagara region \xe2\x80\x94 from St.\xc2\xa0Catharines to Fort Erie, Welland to Niagara-on-the-Lake." ) ) . '</p>';
	$h .= '<div class="home-hero__ctas">';
	$h .= '<a href="' . $collections_url . '" class="btn btn--primary">' . esc_html( $opt( 'home_cta_1_label', 'Browse the Archive' ) ) . '</a>';
	$h .= '<a href="' . $cta2_url . '" class="btn btn--ghost">' . esc_html( $opt( 'home_cta_2_label', 'Explore Collections' ) ) . '</a>';
	$h .= '<a href="' . $tell_url . '" class="btn btn--ghost">' . esc_html( $opt( 'home_cta_3_label', 'Submit Your Story' ) ) . '</a>';
	$h .= '</div>';
	$h .= '</div>';

	// Right column: live stats.
	$h .= '<div class="home-hero__stats">';
	$h .= '<div class="home-hero__stats-title">' . esc_html( $opt( 'home_stats_title', 'Archive at a Glance' ) ) . '</div>';
	$h .= '<div class="home-hero__stat-grid">';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $total . '<em>+</em></div><div class="home-hero__stat-label">Records</div><div class="home-hero__stat-sub">' . esc_html( $opt( 'home_stat_records_sub', 'People, orgs, events, places' ) ) . '</div></div>';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $article_count . '</div><div class="home-hero__stat-label">Archival Articles</div><div class="home-hero__stat-sub">' . esc_html( $opt( 'home_stat_articles_sub', 'Niagara press, sourced &amp; preserved' ) ) . '</div></div>';
	$h .= '<hr class="home-hero__stat-divider">';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $collection_count . '</div><div class="home-hero__stat-label">Collections</div><div class="home-hero__stat-sub">' . esc_html( $opt( 'home_stat_collections_sub', 'Themed, curated sets' ) ) . '</div></div>';
	$h .= '<div class="home-hero__stat"><div class="home-hero__stat-n">' . $muni_count . '</div><div class="home-hero__stat-label">Municipalities</div><div class="home-hero__stat-sub">' . esc_html( $opt( 'home_stat_muni_sub', 'Across the Niagara region' ) ) . '</div></div>';
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

	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	$h  = '<section class="home-featured">';
	$h .= '<div class="home-featured__inner">';
	$h .= '<div class="section-head"><h2>' . esc_html( $opt( 'global_featured_label', 'Featured Collection' ) ) . '</h2>';
	$h .= '<a href="' . $collections_url . '" class="eyebrow" style="text-decoration:none">' . esc_html( $opt( 'global_view_all_collections', "View all collections \xe2\x86\x92" ) ) . '</a></div>';
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

	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	$collections_url = esc_url( home_url( '/collections/' ) );
	return '<section class="home-recent"><div class="home-recent__inner">'
		. '<div class="section-head"><h2>' . esc_html( $opt( 'home_recent_heading', 'Recently Added' ) ) . '</h2>'
		. '<a href="' . $collections_url . '" class="eyebrow" style="text-decoration:none">' . esc_html( $opt( 'home_recent_link', 'View all records →' ) ) . '</a></div>'
		. '<div class="home-recent__grid">' . $cards . '</div>'
		. '</div></section>';
}

// ── Principles ────────────────────────────────────────────────────────────────

add_shortcode( 'nqa_principles', 'nqa_principles_shortcode' );

function nqa_principles_shortcode() {
	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	$principles = array(
		array(
			'num'  => $opt( 'home_principle_1_num',  '01' ),
			'head' => $opt( 'home_principle_1_head', 'Catalogue' ),
			'body' => $opt( 'home_principle_1_body', 'Gather and record the documents, stories, and materials that make up our shared history — newspaper articles, photographs, personal accounts, event records.' ),
		),
		array(
			'num'  => $opt( 'home_principle_2_num',  '02' ),
			'head' => $opt( 'home_principle_2_head', 'Curate' ),
			'body' => $opt( 'home_principle_2_body', 'Organize entries into meaningful collections that reflect our diverse identities and experiences — by theme, era, municipality, and community.' ),
		),
		array(
			'num'  => $opt( 'home_principle_3_num',  '03' ),
			'head' => $opt( 'home_principle_3_head', 'Preserve' ),
			'body' => $opt( 'home_principle_3_body', 'Ensure these histories are cared for and carried forward — with source liveness checks, Wayback Machine captures, and careful consent management.' ),
		),
	);

	$h  = '<div class="home-principles">';
	$h .= '<div class="home-principles__inner">';
	foreach ( $principles as $p ) {
		$h .= '<div class="home-principle">';
		$h .= '<div class="home-principle__n">' . esc_html( $p['num'] ) . '</div>';
		$h .= '<h3>' . esc_html( $p['head'] ) . '</h3>';
		$h .= '<p>' . esc_html( $p['body'] ) . '</p>';
		$h .= '</div>';
	}
	$h .= '</div>';
	$h .= '</div>';

	return $h;
}

// ── Submit CTA ────────────────────────────────────────────────────────────────

add_shortcode( 'nqa_submit_cta', 'nqa_submit_cta_shortcode' );

function nqa_submit_cta_shortcode() {
	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	$heading  = $opt( 'home_cta_block_heading', 'Your story belongs here.' );
	$body     = $opt( 'home_cta_block_body', 'The Niagara Queer Archive is only as complete as the stories shared with it. Whether you have a memory, a photograph, a document, or an artifact — we want to hear from you.' );
	$l1       = $opt( 'home_cta_block_l1', 'Submit your story' );
	$u1       = esc_url( $opt( 'home_cta_block_u1' ) ?: home_url( '/tell/' ) );
	$l2       = $opt( 'home_cta_block_l2', 'Learn about the archive' );
	$u2       = esc_url( $opt( 'home_cta_block_u2' ) ?: home_url( '/about/' ) );

	$h  = '<section class="home-submit-cta">';
	$h .= '<h2>' . esc_html( $heading ) . '</h2>';
	$h .= '<p>' . esc_html( $body ) . '</p>';
	$h .= '<div class="ctas">';
	$h .= '<a href="' . $u1 . '" class="btn btn--primary">' . esc_html( $l1 ) . '</a>';
	$h .= '<a href="' . $u2 . '" class="btn btn--outline">' . esc_html( $l2 ) . '</a>';
	$h .= '</div>';
	$h .= '</section>';

	return $h;
}

// ── Newsletter ────────────────────────────────────────────────────────────────

add_shortcode( 'nqa_newsletter', 'nqa_newsletter_shortcode' );

function nqa_newsletter_shortcode() {
	$opt = function ( string $key, string $fallback = '' ) : string {
		$val = get_field( $key, 'option' );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	$heading = $opt( 'home_newsletter_heading', 'Get archive updates in your inbox' );
	$body    = $opt( 'home_newsletter_body', "New records, collection launches, and storytelling events \xe2\x80\x94 delivered when there\xe2\x80\x99s something worth telling." );

	// Confirmation / error message set by the admin-post handler (newsletter.php).
	$status  = isset( $_GET['nqa_newsletter'] ) ? sanitize_key( wp_unslash( $_GET['nqa_newsletter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$notices = array(
		'ok'      => array( 'ok',  "You\xe2\x80\x99re on the list \xe2\x80\x94 we\xe2\x80\x99ll be in touch when there\xe2\x80\x99s something worth telling." ),
		'dupe'    => array( 'ok',  "You\xe2\x80\x99re already subscribed \xe2\x80\x94 thank you for staying connected." ),
		'invalid' => array( 'err', 'That didn\'t work. Please check your email address and try again.' ),
	);
	$msg     = '';
	if ( isset( $notices[ $status ] ) ) {
		[ $kind, $text ] = $notices[ $status ];
		$msg = '<p class="home-newsletter__msg home-newsletter__msg--' . $kind . '" role="status">'
			. esc_html( $text ) . '</p>';
	}

	$h  = '<section class="home-newsletter" id="newsletter">';
	$h .= '<div class="eyebrow" style="justify-content:center;margin-bottom:.85rem">Stay connected</div>';
	$h .= '<h2>' . esc_html( $heading ) . '</h2>';
	$h .= '<p>' . esc_html( $body ) . '</p>';
	$h .= $msg;
	$h .= '<form class="home-newsletter__form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" aria-label="Newsletter sign-up">';
	$h .= '<input type="hidden" name="action" value="nqa_newsletter_signup">';
	$h .= wp_nonce_field( 'nqa_newsletter_signup', 'nqa_newsletter_nonce', true, false );
	// Honeypot — hidden from humans, tempting to bots.
	$h .= '<div aria-hidden="true" style="position:absolute;left:-9999px" tabindex="-1">';
	$h .= '<label>Website<input type="text" name="nqa_website" tabindex="-1" autocomplete="off"></label>';
	$h .= '</div>';
	$h .= '<label for="home-email" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Email address</label>';
	$h .= '<input class="home-newsletter__input" id="home-email" type="email" name="email" placeholder="your@email.com" autocomplete="email" required>';
	$h .= '<button type="submit" class="home-newsletter__btn">Subscribe</button>';
	$h .= '</form>';
	$h .= '</section>';

	return $h;
}
