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
			'desc'    => 'The first marches, the early organizers, the fights for space and visibility. Documents how a Pride movement took root in small cities and towns across Niagara — outside the major urban centres that usually define the Canadian Pride story.',
			'accent'  => $pal['violet'],
		),
		'progress-protest'      => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'progress-protest',
			'title'   => 'Progress & Protest',
			'desc'    => 'Records of advocacy and public action: campaigns, controversies, and the moments Niagara\'s queer community refused to stay quiet. Rainbow crosswalks, flag-raising ceremonies, school board battles — this collection maps the political landscape.',
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
			'desc'    => 'Books, drag performers, writers, markets, and the arts spaces that gave Niagara\'s queer community somewhere to be seen. Includes the performers, publishers, and venues that made cultural life possible.',
			'accent'  => $pal['pink'],
		),
		'trans-niagara'         => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'trans-niagara',
			'title'   => 'Trans Niagara',
			'desc'    => 'The trans-specific thread through Niagara\'s queer history: advocacy organizations, visibility events, and the people who built support structures that didn\'t yet exist.',
			'accent'  => $pal['yellow'],
		),
		'two-spirit-indigenous' => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'two-spirit-indigenous',
			'title'   => 'Two-Spirit & Indigenous Queer Niagara',
			'desc'    => 'Centres Indigenous queer and Two-Spirit histories in a region whose territory has been home to Haudenosaunee and Anishinaabe peoples since long before colonial settlement. These records are often thinly sourced; this collection holds what has been documented and marks what still needs to be gathered.',
			'accent'  => $pal['violet'],
		),
		'faith-inclusion'       => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'faith-inclusion',
			'title'   => 'Faith & Inclusion',
			'desc'    => 'Documents the relationship between queer people and faith communities in Niagara: the congregations that made space, the denominations that pushed back, and the individuals who found or lost a spiritual home because of who they are.',
			'accent'  => $pal['pink'],
		),
		'drag-performer'        => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'drag-performer',
			'title'   => 'Drag Performers',
			'desc'    => 'Kings, queens, and performers who built audiences and community from Niagara stages. The art form as documentation: drag as history, as survival, as joy.',
			'accent'  => $pal['yellow'],
		),
		'love-support'          => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'love-support',
			'title'   => 'Love & Support',
			'desc'    => 'Ally organizations, PFLAG chapters, support networks, and the institutions that stood beside Niagara\'s queer community — documented with clear framing as partners and supporters.',
			'accent'  => $pal['violet'],
		),
		'in-memorium'           => array(
			'section' => 'theme',
			'kind'    => 'collection',
			'term'    => 'in-memorium',
			'title'   => 'In Memoriam',
			'desc'    => 'Records of loss: people who have died, places that have closed, organizations that no longer exist. A space to honour what has ended while keeping it part of the permanent record.',
			'accent'  => $pal['pink'],
		),
	);

	return array_merge( $by_place, $by_theme );
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

function nqa_collections_render() {
	$registry = nqa_collections_registry();
	$featured = nqa_collections_featured( $registry );
	$recent   = nqa_collections_recent( 6 );

	$places = array_filter( $registry, function ( $c ) { return 'place' === $c['section']; } );
	$themes = array_filter( $registry, function ( $c ) { return 'theme' === $c['section']; } );

	ob_start();
	?>
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
 * 6) Shortcode + auto-injection on the Collections page.
 * ---------------------------------------------------------------------- */
add_shortcode( 'nqa_collections', 'nqa_collections_render' );

/**
 * Robust, idempotent auto-render: if the Collections page content does not
 * already contain the shortcode, append the rendered wayfinding grid to the
 * page content. Runs at priority 9 (before core do_shortcode at 11) so the raw
 * [nqa_collections] shortcode is still present to detect.
 */
add_filter(
	'the_content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_page( 'collections' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$done = true;
		if ( has_shortcode( $content, 'nqa_collections' ) ) {
			return $content; // Shortcode present — let it render in place, no dupe.
		}
		return $content . nqa_collections_render();
	},
	9
);
