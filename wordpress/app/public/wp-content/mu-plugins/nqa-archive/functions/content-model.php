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

/* -------------------------------------------------------------------------
 * Community resource directory post type + its grouping taxonomy.
 *
 * Deliberately NOT an archive "authority record": resources point people to
 * CURRENT support services, so nqa_resource stays outside nqa_content_types()
 * (and therefore out of the archive search / map / collections / stewardship
 * gate). page-attributes gives a menu_order for manual ordering (e.g. pinning
 * crisis lines to the top of a category).
 * ---------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		register_post_type(
			'nqa_resource',
			array(
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => true,
				'menu_position'   => 21,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
				'taxonomies'      => array( 'municipality', 'nqa_resource_cat' ),
				'capability_type' => 'post',
				'hierarchical'    => false,
				'labels'          => nqa_cpt_labels( 'Resource', 'Resources' ),
				'description'     => 'A current community resource or support service (front-facing directory, not an archive record).',
				'menu_icon'       => 'dashicons-sos',
				'rewrite'         => array( 'slug' => 'resource', 'with_front' => false ),
			)
		);
	}
);

// Resource categories (Health, Crisis & Safety, Trans & Two-Spirit, …). Private
// to nqa_resource; registered at priority 9 so it exists before the CPT queries
// it. Term order for the front-end directory is set in presentation/resources.php.
add_action(
	'init',
	function () {
		register_taxonomy(
			'nqa_resource_cat',
			array( 'nqa_resource' ),
			array(
				'labels'            => array(
					'name'          => 'Resource Categories',
					'singular_name' => 'Resource Category',
					'menu_name'     => 'Categories',
					'all_items'     => 'All Categories',
					'edit_item'     => 'Edit Category',
					'add_new_item'  => 'Add New Category',
					'search_items'  => 'Search Categories',
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				// Distinct base so term archives (/resource-category/health/) don't
				// collide with the /resources/ directory page.
				'rewrite'           => array( 'slug' => 'resource-category', 'with_front' => false ),
			)
		);
	},
	9
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
