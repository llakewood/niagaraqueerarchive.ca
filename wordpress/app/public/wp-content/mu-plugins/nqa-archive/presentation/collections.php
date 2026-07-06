<?php
/**
 * Collections — an editorial, query-driven wayfinding grid of "collection cards"
 * (doorways into the archive) grouped By Place (the 12 Niagara municipalities)
 * and By Theme. Themed collections are backed by the `nqa_collection` taxonomy
 * (registered in content-model.php) so they grow as content is tagged. Counts
 * are live; a Featured collection rotates weekly (deterministic by ISO week) and
 * a "Recently added" strip surfaces the newest records. Rendered via the
 * [nqa_collections] shortcode, auto-injected on the Collections page.
 */

defined( 'ABSPATH' ) || exit;

/**
 * A doorway's intro text. For taxonomy-backed doorways (thematic collections
 * and the municipalities) this is the term's Description field — edited in the
 * CMS, not hard-coded — so curators own the copy. Returns '' when unset.
 * Stripped to plain text for the card; the term-archive page renders the
 * description block itself.
 */
function nqa_term_intro( string $taxonomy, string $slug ) : string {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}
	return trim( wp_strip_all_tags( (string) $term->description ) );
}

/* -------------------------------------------------------------------------
 * 1) The collection registry.
 *    Each entry is a curated doorway. `kind` decides how its link + count
 *    resolve so everything stays query-driven (grows as content is added):
 *      - 'municipality' : native municipality term archive
 *      - 'cpt'          : native custom-post-type archive
 *      - 'category'     : native category term archive
 *      - 'collection'   : nqa_collection term archive (thematic, curated tag)
 * ---------------------------------------------------------------------- */
function nqa_collections_registry() {
	$pal = nqa_palette();

	$by_place = array();
	$munis    = array(
		'st-catharines'       => 'St. Catharines',
		'niagara-falls'       => 'Niagara Falls',
		'welland'             => 'Welland',
		'fort-erie'           => 'Fort Erie',
		'thorold'             => 'Thorold',
		'port-colborne'       => 'Port Colborne',
		'niagara-on-the-lake' => 'Niagara-on-the-Lake',
		'pelham'              => 'Pelham',
		'fonthill'            => 'Fonthill',
		'lincoln'             => 'Lincoln',
		'grimsby'             => 'Grimsby',
		'smithville'          => 'Smithville',
	);
	// Rotating accent per town, from the pinned palette.
	$accents = array( $pal['violet'], $pal['pink'], $pal['yellow'] );
	$i       = 0;
	foreach ( $munis as $slug => $name ) {
		$by_place[ 'muni-' . $slug ] = array(
			'section' => 'place',
			'kind'    => 'municipality',
			'term'    => $slug,
			'title'   => $name,
			'desc'    => '',
			'accent'  => $accents[ $i % 3 ],
		);
		$i++;
	}

	$by_theme = array(
		'pride-roots'           => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'pride-roots',
			'title'   => 'Pride Roots',
			'accent'  => $pal['violet'],
		),
		'progress-protest'      => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'progress-protest',
			'title'   => 'Progress & Protest',
			'accent'  => $pal['yellow'],
		),
		'community-organizing'  => array(
			'section' => 'theme',
			'kind'    => 'cpt',
			'term'    => 'nqa_org',
			'title'   => 'Community Organizing',
			'desc'    => 'The groups, non-profits and networks that built queer community here.',
			'accent'  => $pal['pink'],
		),
		'gathering-places'      => array(
			'section' => 'theme',
			'kind'    => 'cpt',
			'term'    => 'nqa_place',
			'title'   => 'Gathering Places',
			'desc'    => 'Venues, parks, cafés and landmarks where the community has gathered.',
			'accent'  => $pal['violet'],
		),
		'queer-arts-letters'    => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'queer-arts-letters',
			'title'   => 'Queer Arts & Letters',
			'accent'  => $pal['pink'],
		),
		'trans-niagara'         => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'trans-niagara',
			'title'   => 'Trans Niagara',
			'accent'  => $pal['yellow'],
		),
		'two-spirit-indigenous' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'two-spirit-indigenous',
			'title'   => 'Two-Spirit & Indigenous Queer Niagara',
			'accent'  => $pal['violet'],
		),
		'faith-inclusion'       => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'faith-inclusion',
			'title'   => 'Faith & Inclusion',
			'accent'  => $pal['pink'],
		),
		'drag-performer'        => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'drag-performer',
			'title'   => 'Drag Performers',
			'accent'  => $pal['yellow'],
		),
		'love-support'          => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'love-support',
			'title'   => 'Love & Support',
			'accent'  => $pal['violet'],
		),
		'in-memorium'           => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'in-memorium',
			'title'   => 'In Memoriam',
			'accent'  => $pal['pink'],
		),
	);

	// Intros are now CMS-owned: thematic collections and municipalities take
	// their card text from the term's Description field, resolved here so the
	// grid stays query-driven. CPT-backed doorways have no term, so they keep
	// the inline `desc` fallback set above.
	$registry = array_merge( $by_place, $by_theme );
	foreach ( $registry as &$c ) {
		if ( 'collection' === $c['kind'] ) {
			$c['desc'] = nqa_term_intro( 'nqa_collection', $c['term'] );
		} elseif ( 'municipality' === $c['kind'] ) {
			$c['desc'] = nqa_term_intro( 'municipality', $c['term'] );
		}
	}
	unset( $c );

	return $registry;
}

/* -------------------------------------------------------------------------
 * 2) Resolve each collection's live link + count from its `kind`.
 * ---------------------------------------------------------------------- */
function nqa_collection_link( array $c ) {
	switch ( $c['kind'] ) {
		case 'municipality':
			$l = get_term_link( $c['term'], 'municipality' );
			break;
		case 'category':
			$l = get_term_link( $c['term'], 'category' );
			break;
		case 'collection':
			$l = get_term_link( $c['term'], 'nqa_collection' );
			break;
		case 'cpt':
			$l = get_post_type_archive_link( $c['term'] );
			break;
		default:
			$l = '';
	}
	return is_wp_error( $l ) || ! $l ? '' : $l;
}

function nqa_collection_count( array $c ) {
	switch ( $c['kind'] ) {
		case 'municipality':
			$t = get_term_by( 'slug', $c['term'], 'municipality' );
			return $t ? (int) $t->count : 0;
		case 'category':
			$t = get_term_by( 'slug', $c['term'], 'category' );
			return $t ? (int) $t->count : 0;
		case 'collection':
			$t = get_term_by( 'slug', $c['term'], 'nqa_collection' );
			return $t ? (int) $t->count : 0;
		case 'cpt':
			$n = wp_count_posts( $c['term'] );
			return $n ? (int) $n->publish : 0;
	}
	return 0;
}

/* -------------------------------------------------------------------------
 * 3) Recently added — newest published records across the archive.
 * ---------------------------------------------------------------------- */
function nqa_collections_recent( $limit = 6 ) {
	$q   = new WP_Query(
		array(
			'post_type'           => nqa_content_types(),
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	$out = array();
	foreach ( $q->posts as $p ) {
		$out[] = array(
			'title' => get_the_title( $p ),
			'url'   => get_permalink( $p ),
			'date'  => get_the_date( 'M Y', $p ),
		);
	}
	wp_reset_postdata();
	return $out;
}

/* -------------------------------------------------------------------------
 * 4) Featured collection — rotates weekly, deterministic by ISO week so it
 *    does not change on every page load. Only rotates among themed collections
 *    that currently have content.
 * ---------------------------------------------------------------------- */
function nqa_collections_featured( array $registry ) {
	// Prefer an explicitly pinned term (set via the admin checkbox).
	$pinned = get_terms( array(
		'taxonomy'   => 'nqa_collection',
		'hide_empty' => false,
		'meta_query' => array(
			array( 'key' => 'nqa_collection_featured', 'value' => '1', 'compare' => '=' ),
		),
		'number'     => 1,
	) );
	if ( ! is_wp_error( $pinned ) && ! empty( $pinned ) ) {
		$slug = $pinned[0]->slug;
		if ( isset( $registry[ $slug ] ) ) {
			return $registry[ $slug ];
		}
	}

	// Fall back to weekly rotation among themed collections with content.
	$candidates = array();
	foreach ( $registry as $key => $c ) {
		if ( 'theme' === $c['section'] && nqa_collection_count( $c ) > 0 ) {
			$candidates[ $key ] = $c;
		}
	}
	if ( empty( $candidates ) ) {
		return null;
	}
	$keys = array_keys( $candidates );
	$week = (int) gmdate( 'oW' ); // ISO year+week, e.g. 202627.
	return $candidates[ $keys[ $week % count( $keys ) ] ];
}

/* -------------------------------------------------------------------------
 * 5) Rendering helpers.
 * ---------------------------------------------------------------------- */
function nqa_collections_card_html( array $c, $featured = false ) {
	$pal    = nqa_palette();
	$count  = nqa_collection_count( $c );
	$url    = nqa_collection_link( $c );
	$accent = $c['accent'];
	// Ink text on yellow/pink/cream; white text on violet — both AA.
	$on_violet   = ( strtoupper( $accent ) === strtoupper( $pal['violet'] ) );
	$title_color = $on_violet ? $pal['base'] : $pal['ink'];
	$noun        = ( 1 === $count ) ? 'item' : 'items';

	$classes = 'nqa-col-card' . ( $featured ? ' nqa-col-card--featured' : '' );
	$tag     = $url ? 'a' : 'div';
	$href    = $url ? ' href="' . esc_url( $url ) . '"' : '';

	ob_start();
	?>
	<<?php echo $tag; ?> class="<?php echo esc_attr( $classes ); ?>"<?php echo $href; ?> style="--nqa-accent:<?php echo esc_attr( $accent ); ?>;--nqa-title:<?php echo esc_attr( $title_color ); ?>;">
		<span class="nqa-col-card__block" aria-hidden="true"></span>
		<span class="nqa-col-card__body"><?php if ( $featured ) : ?><span class="nqa-col-card__kicker">Featured this week</span><?php endif; ?><span class="nqa-col-card__title"><?php echo esc_html( $c['title'] ); ?></span><?php if ( '' !== trim( (string) $c['desc'] ) ) : ?><span class="nqa-col-card__desc"><?php echo esc_html( $c['desc'] ); ?></span><?php endif; ?><span class="nqa-col-card__count"><?php echo esc_html( $count . ' ' . $noun ); ?></span></span>
	</<?php echo $tag; ?>><?php
	return ob_get_clean();
}

function nqa_collections_render( $skip_featured = false ) {
	$registry = nqa_collections_registry();
	$featured = nqa_collections_featured( $registry );
	$recent   = nqa_collections_recent( 6 );

	$places = array_filter( $registry, function ( $c ) { return 'place' === $c['section']; } );
	$themes = array_filter( $registry, function ( $c ) { return 'theme' === $c['section']; } );

	ob_start();
	?>
	<div class="nqa-collections">

		<?php if ( ! $skip_featured && $featured ) : ?>
			<div class="nqa-col-grid" style="margin-bottom:2rem;">
				<?php echo nqa_collections_card_html( $featured, true ); ?>
			</div>
		<?php endif; ?>

		<div class="nqa-col-section-head">
			<h2>By Place</h2>
			<span class="nqa-col-eyebrow">12 Niagara municipalities</span>
		</div>
		<div class="nqa-col-grid nqa-col-grid--place">
			<?php foreach ( $places as $c ) { echo nqa_collections_card_html( $c ); } ?>
		</div>

		<div class="nqa-col-section-head">
			<h2>By Theme</h2>
			<span class="nqa-col-eyebrow">Curated threads</span>
		</div>
		<div class="nqa-col-grid">
			<?php foreach ( $themes as $c ) { echo nqa_collections_card_html( $c ); } ?>
		</div>

		<?php if ( ! empty( $recent ) ) : ?>
			<div class="nqa-col-recent">
				<h2>Recently added</h2>
				<ul>
					<?php foreach ( $recent as $r ) : ?>
						<li><a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['title'] ); ?><span class="nqa-col-recent__date"><?php echo esc_html( $r['date'] ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 6) Shortcodes.
 * ---------------------------------------------------------------------- */
add_shortcode( 'nqa_collections', 'nqa_collections_render' );

add_shortcode( 'nqa_collections_page', 'nqa_collections_page_shortcode' );

function nqa_collections_page_shortcode() {
	$pid = get_queried_object_id();

	$f = function ( string $key, string $fallback = '' ) use ( $pid ) : string {
		$val = get_field( $key, $pid );
		return ( $val !== null && $val !== '' && $val !== false ) ? (string) $val : $fallback;
	};

	// Live stats.
	$total = 0;
	foreach ( nqa_content_types() as $type ) {
		$c      = wp_count_posts( $type );
		$total += isset( $c->publish ) ? (int) $c->publish : 0;
	}
	$collection_count = (int) wp_count_terms( array( 'taxonomy' => 'nqa_collection', 'hide_empty' => false ) );
	$muni_count       = (int) wp_count_terms( array( 'taxonomy' => 'municipality',   'hide_empty' => false ) );

	$h  = '<section class="col-hero">';
	$h .= '<div class="col-hero__inner">';
	$h .= '<div class="eyebrow eyebrow--light">Collections</div>';
	$h .= '<h1>' . esc_html( $f( 'col_page_heading', "Curated windows into Niagara\xe2\x80\x99s queer history." ) ) . '</h1>';
	$h .= '<p class="col-hero__lede">' . esc_html( $f( 'col_page_lede', "Collections bring individual records together into a narrative. Each collection is a thematic lens \xe2\x80\x94 a way of seeing connections across people, places, eras, and communities that might not be visible record by record." ) ) . '</p>';
	$h .= '<div class="col-hero__stat-row">';
	$h .= '<div class="col-hero__stat"><div class="col-hero__stat-n">' . $collection_count . '</div><div class="col-hero__stat-l">Collections</div></div>';
	$h .= '<div class="col-hero__stat"><div class="col-hero__stat-n">' . $total . '<span class="col-hero__stat-plus">+</span></div><div class="col-hero__stat-l">Records</div></div>';
	$h .= '<div class="col-hero__stat"><div class="col-hero__stat-n">' . $muni_count . '</div><div class="col-hero__stat-l">Municipalities</div></div>';
	$h .= '</div>';
	$h .= '</div>'; // /col-hero__inner
	$h .= '</section>';

	$h .= '<div class="col-content">';
	$h .= '<div class="col-content__inner">';
	$h .= nqa_collections_render();
	// "By Location" map as the final block. The map module renders '' when there
	// are no plottable records, so this is a no-op on an empty archive.
	$h .= do_shortcode( '[nqa_map title="By Location" height="520"]' );
	$h .= '</div>';
	$h .= '</div>';

	return $h;
}

/* -------------------------------------------------------------------------
 * 7) Featured-collection admin: enforcement + confirmation prompt.
 * ---------------------------------------------------------------------- */

// After ACF saves nqa_collection term meta, clear the featured flag from every
// other collection so only one can be featured at a time.
add_action( 'acf/save_post', function ( $post_id ) {
	if ( 0 !== strpos( (string) $post_id, 'term_' ) ) {
		return;
	}
	$term_id = (int) substr( (string) $post_id, 5 );

	$term    = get_term( $term_id );
	if ( ! $term || is_wp_error( $term ) || 'nqa_collection' !== $term->taxonomy ) {
		return;
	}
	if ( ! get_term_meta( $term_id, 'nqa_collection_featured', true ) ) {
		return;
	}
	$others = get_terms( array( 'taxonomy' => 'nqa_collection', 'hide_empty' => false, 'exclude' => array( $term_id ) ) );
	foreach ( (array) $others as $other ) {
		if ( $other instanceof WP_Term ) {
			update_term_meta( $other->term_id, 'nqa_collection_featured', '' );
		}
	}
}, 20 );

// Enqueue the JS confirmation prompt on nqa_collection edit screens.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'nqa_collection' !== $screen->taxonomy ) {
		return;
	}
	// Which term is currently being edited (if any)?
	$editing_id = isset( $_GET['tag_ID'] ) ? (int) $_GET['tag_ID'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

	// Is a different collection already featured?
	$pinned = get_terms( array(
		'taxonomy'   => 'nqa_collection',
		'hide_empty' => false,
		'exclude'    => $editing_id ? array( $editing_id ) : array(),
		'meta_query' => array(
			array( 'key' => 'nqa_collection_featured', 'value' => '1', 'compare' => '=' ),
		),
		'number'     => 1,
	) );
	$featured_name = ( ! is_wp_error( $pinned ) && ! empty( $pinned ) ) ? $pinned[0]->name : '';

	wp_enqueue_script(
		'nqa-collection-featured',
		WPMU_PLUGIN_URL . '/nqa-archive/assets/nqa-collection-featured.js',
		array(),
		null,
		true
	);
	wp_localize_script( 'nqa-collection-featured', 'nqaFeatured', array(
		'currentFeatured' => $featured_name,
	) );
} );
