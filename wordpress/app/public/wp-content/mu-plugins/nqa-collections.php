<?php
/**
 * Plugin Name: NQA – Collections (editorial wayfinding)
 * Description: Turns the "Collections" page into a curated, query-driven wayfinding grid of
 *              "collection cards" — doorways into the archive grouped By Place (the 12 Niagara
 *              municipalities) and By Theme. Themed collections are backed by a lightweight
 *              `nqa_collection` taxonomy so they grow automatically as content is tagged.
 *              Counts are live; a Featured collection rotates weekly (deterministic by ISO week)
 *              and a "Recently added" strip surfaces the newest records. Rendered via the
 *              [nqa_collections] shortcode; the shortcode is auto-injected on the Collections
 *              page so no manual block editing is required.
 * Version:     1.0.0
 *
 * Tracked in git; contains no secrets. All customization via mu-plugin (no child theme).
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Pinned palette (archive visual language). Do NOT swap for theme presets —
 * contrast must hold across style variations (WCAG 2.1 AA).
 * ---------------------------------------------------------------------- */
const NQA_COL_VIOLET = '#503AA8';
const NQA_COL_YELLOW = '#FFEE58';
const NQA_COL_PINK   = '#F6CFF4';
const NQA_COL_CREAM  = '#FBFAF3';
const NQA_COL_INK    = '#111';
const NQA_COL_BASE   = '#fff';

/* -------------------------------------------------------------------------
 * 1) Register the `nqa_collection` taxonomy (post + the four entity CPTs).
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_taxonomy(
		'nqa_collection',
		array( 'post', 'nqa_person', 'nqa_org', 'nqa_event', 'nqa_place' ),
		array(
			'labels'            => array(
				'name'          => 'Collections',
				'singular_name' => 'Collection',
				'menu_name'     => 'Collections',
				'all_items'     => 'All Collections',
				'edit_item'     => 'Edit Collection',
				'add_new_item'  => 'Add New Collection',
				'search_items'  => 'Search Collections',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'collection', 'with_front' => false ),
		)
	);
}, 9 );

/* -------------------------------------------------------------------------
 * 2) The collection registry.
 *    Each entry is a curated doorway. `kind` decides how its link + count
 *    resolve so everything stays query-driven (grows as content is added):
 *      - 'municipality' : native municipality term archive
 *      - 'cpt'          : native custom-post-type archive
 *      - 'category'     : native category term archive
 *      - 'collection'   : nqa_collection term archive (thematic, curated tag)
 * ---------------------------------------------------------------------- */
function nqa_collections_registry() {
	$by_place = array();
	$munis = array(
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
	$accents = array( NQA_COL_VIOLET, NQA_COL_PINK, NQA_COL_YELLOW );
	$i = 0;
	foreach ( $munis as $slug => $name ) {
		$by_place[ 'muni-' . $slug ] = array(
			'section' => 'place',
			'kind'    => 'municipality',
			'term'    => $slug,
			'title'   => $name,
			'desc'    => 'Stories, people, places and events rooted in ' . $name . '.',
			'accent'  => $accents[ $i % 3 ],
		);
		$i++;
	}

	$by_theme = array(
		'pride-roots' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'pride-roots',
			'title'   => 'Pride Roots',
			'desc'    => 'Origin stories and the longest-running Pride organizations and festivals in Niagara.',
			'accent'  => NQA_COL_VIOLET,
		),
		'progress-protest' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'progress-protest',
			'title'   => 'Progress & Protest',
			'desc'    => 'Crosswalks, flag-raisings, contested votes and the backlash met along the way.',
			'accent'  => NQA_COL_YELLOW,
		),
		'community-organizing' => array(
			'section' => 'theme',
			'kind'    => 'cpt',
			'term'    => 'nqa_org',
			'title'   => 'Community Organizing',
			'desc'    => 'The groups, non-profits and networks that built queer community here.',
			'accent'  => NQA_COL_PINK,
		),
		'gathering-places' => array(
			'section' => 'theme',
			'kind'    => 'cpt',
			'term'    => 'nqa_place',
			'title'   => 'Gathering Places',
			'desc'    => 'Venues, parks, cafés and landmarks where the community has gathered.',
			'accent'  => NQA_COL_VIOLET,
		),
		'queer-arts-letters' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'queer-arts-letters',
			'title'   => 'Queer Arts & Letters',
			'desc'    => 'Books, writers, drag, markets and the arts spaces of Niagara.',
			'accent'  => NQA_COL_PINK,
		),
		'trans-niagara' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'trans-niagara',
			'title'   => 'Trans Niagara',
			'desc'    => 'The trans-specific thread: advocacy, visibility and Trans Day of Visibility.',
			'accent'  => NQA_COL_YELLOW,
		),
		'two-spirit-indigenous' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'two-spirit-indigenous',
			'title'   => 'Two-Spirit & Indigenous Queer Niagara',
			'desc'    => 'Two-Spirit and Indigenous queer life in Niagara — flag-raisings, gatherings, and the Fort Erie Native Friendship Centre.',
			'accent'  => NQA_COL_VIOLET,
		),
	);

	return array_merge( $by_place, $by_theme );
}

/* -------------------------------------------------------------------------
 * 3) Resolve each collection's live link + count from its `kind`.
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
 * 4) Recently added — newest published records across the archive.
 * ---------------------------------------------------------------------- */
function nqa_collections_recent( $limit = 6 ) {
	$q = new WP_Query( array(
		'post_type'           => array( 'post', 'nqa_person', 'nqa_org', 'nqa_event', 'nqa_place' ),
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	) );
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
 * 5) Featured collection — rotates weekly, deterministic by ISO week so it
 *    does not change on every page load (no per-request randomness).
 *    Only rotates among themed collections that currently have content.
 * ---------------------------------------------------------------------- */
function nqa_collections_featured( array $registry ) {
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
	$c    = $candidates[ $keys[ $week % count( $keys ) ] ];
	return $c;
}

/* -------------------------------------------------------------------------
 * 6) Rendering helpers.
 * ---------------------------------------------------------------------- */
function nqa_collections_card_html( array $c, $featured = false ) {
	$count = nqa_collection_count( $c );
	$url   = nqa_collection_link( $c );
	$accent = $c['accent'];
	// Ink text on yellow/pink/cream; white text on violet — both AA.
	$on_violet = ( strtoupper( $accent ) === strtoupper( NQA_COL_VIOLET ) );
	$title_color = $on_violet ? NQA_COL_BASE : NQA_COL_INK;
	$noun = ( 1 === $count ) ? 'item' : 'items';

	$classes = 'nqa-col-card' . ( $featured ? ' nqa-col-card--featured' : '' );
	$tag = $url ? 'a' : 'div';
	$href = $url ? ' href="' . esc_url( $url ) . '"' : '';

	ob_start();
	?>
	<<?php echo $tag; ?> class="<?php echo esc_attr( $classes ); ?>"<?php echo $href; ?> style="--nqa-accent:<?php echo esc_attr( $accent ); ?>;--nqa-title:<?php echo esc_attr( $title_color ); ?>;">
		<span class="nqa-col-card__block" aria-hidden="true"></span>
		<span class="nqa-col-card__body">
			<?php if ( $featured ) : ?><span class="nqa-col-card__kicker">Featured this week</span><?php endif; ?>
			<span class="nqa-col-card__title"><?php echo esc_html( $c['title'] ); ?></span>
			<span class="nqa-col-card__desc"><?php echo esc_html( $c['desc'] ); ?></span>
			<span class="nqa-col-card__count"><?php echo esc_html( $count . ' ' . $noun ); ?></span>
		</span>
	</<?php echo $tag; ?>>
	<?php
	return ob_get_clean();
}

function nqa_collections_render() {
	$registry = nqa_collections_registry();
	$featured = nqa_collections_featured( $registry );
	$recent   = nqa_collections_recent( 6 );

	$places = array_filter( $registry, function ( $c ) { return 'place' === $c['section']; } );
	$themes = array_filter( $registry, function ( $c ) { return 'theme' === $c['section']; } );

	ob_start();
	?>
	<style>
	.nqa-collections{--v:<?php echo NQA_COL_VIOLET; ?>;--y:<?php echo NQA_COL_YELLOW; ?>;--p:<?php echo NQA_COL_PINK; ?>;--cr:<?php echo NQA_COL_CREAM; ?>;--ink:<?php echo NQA_COL_INK; ?>;
		font-family:var(--wp--preset--font-family--fira-code, ui-monospace, monospace);color:var(--ink);max-width:1200px;margin:0 auto;}
	.nqa-collections *{box-sizing:border-box;}
	.nqa-col-lede{background:var(--cr);border:2px solid var(--ink);box-shadow:6px 6px 0 var(--ink);padding:1.75rem 2rem;margin:0 0 2.5rem;}
	.nqa-col-lede p{margin:.4rem 0 0;font-size:1.05rem;line-height:1.6;font-family:inherit;}
	.nqa-col-eyebrow{font-family:var(--wp--preset--font-family--fira-code, ui-monospace, monospace);text-transform:uppercase;letter-spacing:.14em;font-size:.72rem;font-weight:700;margin:0;}
	.nqa-col-section-head{display:flex;align-items:baseline;gap:.9rem;margin:2.75rem 0 1.25rem;border-bottom:2px solid var(--ink);padding-bottom:.5rem;}
	.nqa-col-section-head h2{font-family:inherit;text-transform:uppercase;letter-spacing:.06em;font-size:1.5rem;margin:0;line-height:1;}
	.nqa-col-section-head .nqa-col-eyebrow{color:var(--v);}
	.nqa-col-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.1rem;}
	.nqa-col-grid--place{grid-template-columns:repeat(auto-fill,minmax(190px,1fr));}
	.nqa-col-card{position:relative;display:flex;flex-direction:column;background:var(--wp--preset--color--base,#fff);border:2px solid var(--ink);box-shadow:5px 5px 0 var(--ink);text-decoration:none;color:var(--ink);overflow:hidden;transition:transform .12s ease,box-shadow .12s ease;}
	a.nqa-col-card:hover,a.nqa-col-card:focus-visible{transform:translate(-2px,-2px);box-shadow:8px 8px 0 var(--ink);}
	a.nqa-col-card:focus-visible{outline:3px solid var(--v);outline-offset:3px;}
	.nqa-col-card__block{display:block;height:14px;background:var(--nqa-accent);border-bottom:2px solid var(--ink);}
	.nqa-col-card__body{display:flex;flex-direction:column;gap:.5rem;padding:1rem 1.05rem 1.1rem;flex:1;}
	.nqa-col-card__kicker{font-size:.66rem;text-transform:uppercase;letter-spacing:.14em;font-weight:700;color:var(--v);}
	.nqa-col-card__title{font-size:1.15rem;font-weight:700;line-height:1.15;letter-spacing:.01em;}
	.nqa-col-card__desc{font-size:.82rem;line-height:1.5;color:#333;flex:1;}
	.nqa-col-card__count{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;font-weight:700;color:var(--ink);border:2px solid var(--ink);align-self:flex-start;padding:.2rem .5rem;background:var(--nqa-accent);color:var(--nqa-title);}
	.nqa-col-card--featured{grid-column:1/-1;flex-direction:row;background:var(--v);color:#fff;box-shadow:8px 8px 0 var(--ink);}
	.nqa-col-card--featured .nqa-col-card__block{width:16px;height:auto;border-bottom:0;border-right:2px solid var(--ink);}
	.nqa-col-card--featured .nqa-col-card__title{font-size:1.8rem;color:#fff;}
	.nqa-col-card--featured .nqa-col-card__desc{color:var(--p);font-size:.95rem;}
	.nqa-col-card--featured .nqa-col-card__kicker{color:var(--y);}
	.nqa-col-card--featured .nqa-col-card__count{background:var(--y);color:var(--ink);border-color:var(--ink);}
	a.nqa-col-card--featured:hover,a.nqa-col-card--featured:focus-visible{transform:translate(-3px,-3px);box-shadow:12px 12px 0 var(--ink);}
	.nqa-col-recent{margin:2.75rem 0 0;background:var(--cr);border:2px solid var(--ink);box-shadow:6px 6px 0 var(--ink);padding:1.4rem 1.6rem;}
	.nqa-col-recent h2{font-family:inherit;text-transform:uppercase;letter-spacing:.06em;font-size:1.1rem;margin:0 0 .9rem;}
	.nqa-col-recent ul{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.6rem;}
	.nqa-col-recent li{margin:0;}
	.nqa-col-recent a{display:inline-block;text-decoration:none;color:var(--ink);border:2px solid var(--ink);padding:.35rem .6rem;background:#fff;font-size:.8rem;line-height:1.3;transition:background .12s ease;}
	.nqa-col-recent a:hover{background:var(--p);}
	.nqa-col-recent a:focus-visible{outline:3px solid var(--v);outline-offset:2px;}
	.nqa-col-recent .nqa-col-recent__date{display:block;font-size:.66rem;text-transform:uppercase;letter-spacing:.1em;color:#555;}
	@media (max-width:640px){.nqa-col-card--featured{flex-direction:column;}.nqa-col-card--featured .nqa-col-card__block{width:auto;height:14px;border-right:0;border-bottom:2px solid var(--ink);}}
	</style>

	<div class="nqa-collections">

		<?php if ( $featured ) : ?>
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
 * 7) Shortcode + auto-injection on the Collections page.
 * ---------------------------------------------------------------------- */
add_shortcode( 'nqa_collections', 'nqa_collections_render' );

/**
 * Robust, idempotent auto-render: if the Collections page content does not
 * already contain the shortcode, append the rendered wayfinding grid to the
 * page content. This means the page works whether or not the block content
 * has been replaced with [nqa_collections].
 */
add_filter( 'the_content', function ( $content ) {
	static $done = false;
	if ( $done || ! is_page( 'collections' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	// Priority 9 — runs BEFORE core do_shortcode (priority 11) — so the raw
	// [nqa_collections] shortcode is still present to detect. If it is, let the
	// shortcode expand in place (single render); otherwise append once. The
	// static guard also prevents a duplicate if the_content filters more than once.
	$done = true;
	if ( has_shortcode( $content, 'nqa_collections' ) ) {
		return $content; // Shortcode present — let it render in place, no dupe.
	}
	return $content . nqa_collections_render();
}, 9 );
