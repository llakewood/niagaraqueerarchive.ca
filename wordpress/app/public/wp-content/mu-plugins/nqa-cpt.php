<?php
/**
 * Plugin Name: NQA – Custom Post Types (entities)
 * Description: Registers the archive's entity "authority record" post types — Person,
 *              Organization, Event, Place — and shares the municipality + tag taxonomies
 *              with them. Archival materials remain as core `post` (classified by format).
 * Version:     1.0.0
 *
 * Tracked in git; contains no secrets.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {

	$common = array(
		'public'             => true,
		'show_in_rest'       => true,
		'has_archive'        => true,
		'menu_position'      => 20,
		'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
		'taxonomies'         => array( 'municipality', 'post_tag' ),
		'capability_type'    => 'post',
		'hierarchical'       => false,
	);

	register_post_type( 'nqa_person', array_merge( $common, array(
		'labels'      => nqa_cpt_labels( 'Person', 'People' ),
		'description' => 'A person documented in the archive (authority record).',
		'menu_icon'   => 'dashicons-admin-users',
		'rewrite'     => array( 'slug' => 'person', 'with_front' => false ),
	) ) );

	register_post_type( 'nqa_org', array_merge( $common, array(
		'labels'      => nqa_cpt_labels( 'Organization', 'Organizations' ),
		'description' => 'An organization, group, or business (authority record).',
		'menu_icon'   => 'dashicons-groups',
		'rewrite'     => array( 'slug' => 'organization', 'with_front' => false ),
	) ) );

	register_post_type( 'nqa_event', array_merge( $common, array(
		'labels'      => nqa_cpt_labels( 'Event', 'Events' ),
		'description' => 'An event or recurring gathering (authority record).',
		'menu_icon'   => 'dashicons-calendar-alt',
		'rewrite'     => array( 'slug' => 'event', 'with_front' => false ),
	) ) );

	register_post_type( 'nqa_place', array_merge( $common, array(
		'labels'      => nqa_cpt_labels( 'Place', 'Places' ),
		'description' => 'A place or venue (authority record).',
		'menu_icon'   => 'dashicons-location',
		'rewrite'     => array( 'slug' => 'place', 'with_front' => false ),
	) ) );
} );

/**
 * Build a standard labels array for a CPT.
 */
function nqa_cpt_labels( $singular, $plural ) {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'menu_name'          => $plural,
		'add_new_item'       => "Add New {$singular}",
		'edit_item'          => "Edit {$singular}",
		'new_item'           => "New {$singular}",
		'view_item'          => "View {$singular}",
		'view_items'         => "View {$plural}",
		'search_items'       => "Search {$plural}",
		'not_found'          => "No {$plural} found",
		'all_items'          => "All {$plural}",
		'archives'           => "{$singular} Archives",
	);
}

/** Convenience: the archive entity post types (entities only, not materials). */
function nqa_entity_post_types() {
	return array( 'nqa_person', 'nqa_org', 'nqa_event', 'nqa_place' );
}
