<?php
/**
 * Archive search — REST endpoint + script enqueue.
 *
 * GET /wp-json/nqa/v1/search
 *   ?q        — search term (optional; omit for "show all, newest first")
 *   ?type     — post type slug, or empty for all content types
 *   ?muni     — municipality term slug
 *   ?coll     — nqa_collection term slug
 *   ?decade   — decade post_tag slug (e.g. "1990s")
 *   ?sort     — relevance | newest | oldest | az
 *   ?per_page — max results (default 24, max 100)
 *
 * The nqa-search.js module is enqueued only on the Search page (slug: search).
 */

defined( 'ABSPATH' ) || exit;

// ── REST endpoint ─────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
	register_rest_route( 'nqa/v1', '/search', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'nqa_search_endpoint',
		'permission_callback' => '__return_true',
		'args'                => array(
			'q'        => array( 'type' => 'string',  'default' => '',           'sanitize_callback' => 'sanitize_text_field' ),
			'type'     => array( 'type' => 'string',  'default' => '',           'sanitize_callback' => 'sanitize_key' ),
			'muni'     => array( 'type' => 'string',  'default' => '',           'sanitize_callback' => 'sanitize_key' ),
			'coll'     => array( 'type' => 'string',  'default' => '',           'sanitize_callback' => 'sanitize_key' ),
			'decade'   => array( 'type' => 'string',  'default' => '',           'sanitize_callback' => 'sanitize_key' ),
			'sort'     => array( 'type' => 'string',  'default' => 'relevance',  'sanitize_callback' => 'sanitize_key' ),
			'per_page' => array( 'type' => 'integer', 'default' => 24 ),
		),
	) );
} );

function nqa_search_endpoint( WP_REST_Request $req ) {
	$q        = $req->get_param( 'q' );
	$type     = $req->get_param( 'type' );
	$muni     = $req->get_param( 'muni' );
	$coll     = $req->get_param( 'coll' );
	$decade   = $req->get_param( 'decade' );
	$sort     = $req->get_param( 'sort' );
	$per_page = min( (int) $req->get_param( 'per_page' ), 100 );

	$valid_types = nqa_content_types();
	$post_types  = ( $type && in_array( $type, $valid_types, true ) )
	               ? array( $type )
	               : $valid_types;

	// Sort mapping. "relevance" without a search term falls back to modified desc.
	switch ( $sort ) {
		case 'newest':  $orderby = 'modified'; $order = 'DESC'; break;
		case 'oldest':  $orderby = 'modified'; $order = 'ASC';  break;
		case 'az':      $orderby = 'title';    $order = 'ASC';  break;
		default:        $orderby = $q ? 'relevance' : 'modified'; $order = 'DESC'; break;
	}

	$args = array(
		'post_type'           => $post_types,
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	);

	if ( $q ) {
		$args['s'] = $q;
	}

	$tax_query = array();
	if ( $muni ) {
		$tax_query[] = array( 'taxonomy' => 'municipality',   'field' => 'slug', 'terms' => $muni );
	}
	if ( $coll ) {
		$tax_query[] = array( 'taxonomy' => 'nqa_collection', 'field' => 'slug', 'terms' => $coll );
	}
	if ( $decade ) {
		$tax_query[] = array( 'taxonomy' => 'post_tag',        'field' => 'slug', 'terms' => $decade );
	}
	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query;
	}

	$query = new WP_Query( $args );

	$type_labels = array(
		'post'       => 'Article',
		'nqa_person' => 'Person',
		'nqa_org'    => 'Organization',
		'nqa_event'  => 'Event',
		'nqa_place'  => 'Place',
	);

	$posts = array();
	while ( $query->have_posts() ) {
		$query->the_post();
		$pid = get_the_ID();

		$muni_terms = get_the_terms( $pid, 'municipality' );
		$muni_name  = ( $muni_terms && ! is_wp_error( $muni_terms ) )
		              ? $muni_terms[0]->name : '';

		$coll_terms = get_the_terms( $pid, 'nqa_collection' );
		$coll_name  = ( $coll_terms && ! is_wp_error( $coll_terms ) )
		              ? $coll_terms[0]->name : '';

		$tag_terms   = get_the_terms( $pid, 'post_tag' );
		$decade_tags = array();
		if ( $tag_terms && ! is_wp_error( $tag_terms ) ) {
			foreach ( $tag_terms as $t ) {
				if ( preg_match( '/^\d{3}0s$/', $t->slug ) ) {
					$decade_tags[] = $t->name;
				}
			}
		}

		$post_type  = get_post_type();
		$type_label = $type_labels[ $post_type ] ?? ucfirst( str_replace( 'nqa_', '', $post_type ) );

		$posts[] = array(
			'id'         => $pid,
			'type'       => $post_type,
			'type_label' => $type_label,
			'title'      => nqa_decode_entities( get_the_title() ),
			'excerpt'    => nqa_decode_entities( wp_trim_words( get_the_excerpt(), 30, '…' ) ),
			'permalink'  => get_permalink(),
			'muni'       => nqa_decode_entities( $muni_name ),
			'collection' => nqa_decode_entities( $coll_name ),
			'decades'    => array_map( 'nqa_decode_entities', $decade_tags ),
		);
	}
	wp_reset_postdata();

	return rest_ensure_response( array(
		'total' => (int) $query->found_posts,
		'posts' => $posts,
	) );
}

// ── Script enqueue ────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( 'search' ) ) {
		return;
	}

	$js_path = WPMU_PLUGIN_DIR . '/nqa-archive/assets/nqa-search.js';
	wp_enqueue_script(
		'nqa-search',
		WPMU_PLUGIN_URL . '/nqa-archive/assets/nqa-search.js',
		array(),
		file_exists( $js_path ) ? (string) filemtime( $js_path ) : NQA_VERSION,
		true
	);

	wp_localize_script( 'nqa-search', 'nqaSearch', array(
		'endpoint' => esc_url_raw( rest_url( 'nqa/v1/search' ) ),
		'muniUrl'  => esc_url_raw( rest_url( 'wp/v2/municipality' ) ),
		'collUrl'  => esc_url_raw( rest_url( 'wp/v2/nqa_collection' ) ),
	) );
} );
