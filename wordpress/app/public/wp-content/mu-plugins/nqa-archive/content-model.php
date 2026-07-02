<?php
/**
 * The archive's content model: the four entity "authority record" post types
 * (Person / Organization / Event / Place) and the thematic `nqa_collection`
 * taxonomy. Archival materials remain core `post` (classified by format); the
 * shared `municipality` + `post_tag` + `nqa_collection` taxonomies join them.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Entity custom post types.
 * ---------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		$common = array(
			'public'          => true,
			'show_in_rest'    => true,
			'has_archive'     => true,
			'menu_position'   => 20,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
			'taxonomies'      => array( 'municipality', 'post_tag' ),
			'capability_type' => 'post',
			'hierarchical'    => false,
		);

		register_post_type(
			'nqa_person',
			array_merge(
				$common,
				array(
					'labels'      => nqa_cpt_labels( 'Person', 'People' ),
					'description' => 'A person documented in the archive (authority record).',
					'menu_icon'   => 'dashicons-admin-users',
					'rewrite'     => array( 'slug' => 'person', 'with_front' => false ),
				)
			)
		);

		register_post_type(
			'nqa_org',
			array_merge(
				$common,
				array(
					'labels'      => nqa_cpt_labels( 'Organization', 'Organizations' ),
					'description' => 'An organization, group, or business (authority record).',
					'menu_icon'   => 'dashicons-groups',
					'rewrite'     => array( 'slug' => 'organization', 'with_front' => false ),
				)
			)
		);

		register_post_type(
			'nqa_event',
			array_merge(
				$common,
				array(
					'labels'      => nqa_cpt_labels( 'Event', 'Events' ),
					'description' => 'An event or recurring gathering (authority record).',
					'menu_icon'   => 'dashicons-calendar-alt',
					'rewrite'     => array( 'slug' => 'event', 'with_front' => false ),
				)
			)
		);

		register_post_type(
			'nqa_place',
			array_merge(
				$common,
				array(
					'labels'      => nqa_cpt_labels( 'Place', 'Places' ),
					'description' => 'A place or venue (authority record).',
					'menu_icon'   => 'dashicons-location',
					'rewrite'     => array( 'slug' => 'place', 'with_front' => false ),
				)
			)
		);
	}
);

/** Build a standard labels array for a CPT. */
function nqa_cpt_labels( $singular, $plural ) {
	return array(
		'name'          => $plural,
		'singular_name' => $singular,
		'menu_name'     => $plural,
		'add_new_item'  => "Add New {$singular}",
		'edit_item'     => "Edit {$singular}",
		'new_item'      => "New {$singular}",
		'view_item'     => "View {$singular}",
		'view_items'    => "View {$plural}",
		'search_items'  => "Search {$plural}",
		'not_found'     => "No {$plural} found",
		'all_items'     => "All {$plural}",
		'archives'      => "{$singular} Archives",
	);
}

/* -------------------------------------------------------------------------
 * Thematic collection taxonomy (shared by materials + entities). Registered at
 * priority 9 so it exists before anything that queries it on `init`.
 * ---------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		register_taxonomy(
			'nqa_collection',
			nqa_content_types(),
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
	},
	9
);
