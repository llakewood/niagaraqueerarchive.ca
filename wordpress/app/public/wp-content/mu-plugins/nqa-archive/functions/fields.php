<?php
/**
 * ACF field groups for the entity post types, registered in code so the schema
 * is version-controlled and deployable. Provenance fields (source/citation/link)
 * and the cross-type `relationship` field reuse their existing names so migrated
 * values carry over. Materials keep their existing DB "Item Details" group.
 *
 * Also wires the environment-provided Google Maps key (constant defined by the
 * separate, gitignored 0-nqa-runtime-config.php) into ACF and centres the map
 * fields on the Niagara region.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Environment secret -> ACF Google Maps key (reference only; never a value).
		if ( defined( 'NQA_GOOGLE_MAPS_KEY' ) && NQA_GOOGLE_MAPS_KEY ) {
			acf_update_setting( 'google_api_key', NQA_GOOGLE_MAPS_KEY );
		}

		$all_types = nqa_content_types();

		// Shared provenance + relationship fields, re-used (by name) in every group.
		$provenance = function ( $prefix ) use ( $all_types ) {
			return array(
				array( 'key' => "field_{$prefix}_source", 'label' => 'Source', 'name' => 'source', 'type' => 'text', 'instructions' => 'Where this information comes from.' ),
				array( 'key' => "field_{$prefix}_citation", 'label' => 'Citation', 'name' => 'citation', 'type' => 'text' ),
				array( 'key' => "field_{$prefix}_link", 'label' => 'Link', 'name' => 'link', 'type' => 'url' ),
				array(
					'key'           => "field_{$prefix}_relationship",
					'label'         => 'Related entries',
					'name'          => 'relationship',
					'type'          => 'relationship',
					'post_type'     => $all_types,
					'filters'       => array( 'search', 'post_type' ),
					'return_format' => 'id',
				),
			);
		};

		$loc = function ( $type ) {
			return array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => $type ) ) );
		};

		// ---- Person ----
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_person',
				'title'    => 'Person details',
				'location' => $loc( 'nqa_person' ),
				'fields'   => array_merge(
					array(
						array( 'key' => 'field_nqa_per_pronouns', 'label' => 'Pronouns', 'name' => 'pronouns', 'type' => 'text' ),
						array( 'key' => 'field_nqa_per_born', 'label' => 'Born', 'name' => 'born', 'type' => 'text', 'instructions' => 'Year or date; "c. 1960" is fine.' ),
						array( 'key' => 'field_nqa_per_died', 'label' => 'Died', 'name' => 'died', 'type' => 'text' ),
						array( 'key' => 'field_nqa_per_aliases', 'label' => 'Aliases / also known as', 'name' => 'aliases', 'type' => 'text' ),
						array( 'key' => 'field_nqa_per_roles', 'label' => 'Roles', 'name' => 'roles', 'type' => 'text', 'instructions' => 'e.g. activist, author, organizer.' ),
					),
					$provenance( 'nqa_per' )
				),
			)
		);

		// ---- Organization ----
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_org',
				'title'    => 'Organization details',
				'location' => $loc( 'nqa_org' ),
				'fields'   => array_merge(
					array(
						array(
							'key'        => 'field_nqa_org_type',
							'label'      => 'Organization type',
							'name'       => 'org_type',
							'type'       => 'select',
							'choices'    => array(
								'non-profit' => 'Non-profit',
								'business'   => 'Business',
								'activist'   => 'Activist group',
								'healthcare' => 'Healthcare',
								'faith'      => 'Faith',
								'arts'       => 'Arts',
								'community'  => 'Community group',
								'indigenous' => 'Indigenous',
								'government' => 'Government',
								'other'      => 'Other',
							),
							'allow_null' => 1,
							'ui'         => 1,
						),
						array(
							'key'           => 'field_nqa_org_status',
							'label'         => 'Status',
							'name'          => 'status',
							'type'          => 'select',
							'choices'       => array( 'active' => 'Active', 'defunct' => 'Defunct', 'hiatus' => 'On hiatus', 'unknown' => 'Unknown' ),
							'default_value' => 'active',
							'allow_null'    => 1,
							'ui'            => 1,
						),
						array( 'key' => 'field_nqa_org_founded', 'label' => 'Founded', 'name' => 'founded', 'type' => 'text' ),
						array( 'key' => 'field_nqa_org_dissolved', 'label' => 'Dissolved', 'name' => 'dissolved', 'type' => 'text' ),
						array( 'key' => 'field_nqa_org_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url' ),
						array( 'key' => 'field_nqa_org_location', 'label' => 'Location', 'name' => 'location', 'type' => 'google_map' ),
						array( 'key' => 'field_nqa_org_contact', 'label' => 'Contact person', 'name' => 'contact_person', 'type' => 'text' ),
						array( 'key' => 'field_nqa_org_email', 'label' => 'Email', 'name' => 'email', 'type' => 'text' ),
						array( 'key' => 'field_nqa_org_phone', 'label' => 'Phone', 'name' => 'phone_number', 'type' => 'text' ),
					),
					$provenance( 'nqa_org' )
				),
			)
		);

		// ---- Event ----
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_event',
				'title'    => 'Event details',
				'location' => $loc( 'nqa_event' ),
				'fields'   => array_merge(
					array(
						array( 'key' => 'field_nqa_evt_start', 'label' => 'Start date', 'name' => 'start_date', 'type' => 'date_picker', 'return_format' => 'Y-m-d' ),
						array( 'key' => 'field_nqa_evt_end', 'label' => 'End date', 'name' => 'end_date', 'type' => 'date_picker', 'return_format' => 'Y-m-d' ),
						array( 'key' => 'field_nqa_evt_recur', 'label' => 'Recurrence', 'name' => 'recurrence', 'type' => 'text', 'instructions' => 'e.g. Annual.' ),
						array( 'key' => 'field_nqa_evt_org', 'label' => 'Organizer', 'name' => 'organizer', 'type' => 'relationship', 'post_type' => array( 'nqa_org' ), 'return_format' => 'id' ),
						array( 'key' => 'field_nqa_evt_venue', 'label' => 'Venue', 'name' => 'venue', 'type' => 'relationship', 'post_type' => array( 'nqa_place' ), 'return_format' => 'id' ),
						array( 'key' => 'field_nqa_evt_loc', 'label' => 'Location', 'name' => 'location', 'type' => 'google_map' ),
					),
					$provenance( 'nqa_evt' )
				),
			)
		);

		// ---- Place ----
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_place',
				'title'    => 'Place details',
				'location' => $loc( 'nqa_place' ),
				'fields'   => array_merge(
					array(
						array(
							'key'        => 'field_nqa_plc_type',
							'label'      => 'Place type',
							'name'       => 'place_type',
							'type'       => 'select',
							'choices'    => array(
								'venue'    => 'Venue',
								'bar'      => 'Bar / club',
								'centre'   => 'Community centre',
								'park'     => 'Park',
								'faith'    => 'Faith institution',
								'business' => 'Business',
								'landmark' => 'Landmark',
								'other'    => 'Other',
							),
							'allow_null' => 1,
							'ui'         => 1,
						),
						array( 'key' => 'field_nqa_plc_address', 'label' => 'Address', 'name' => 'address', 'type' => 'text' ),
						array( 'key' => 'field_nqa_plc_loc', 'label' => 'Location', 'name' => 'location', 'type' => 'google_map' ),
						array( 'key' => 'field_nqa_plc_exists', 'label' => 'Still exists', 'name' => 'still_exists', 'type' => 'true_false', 'ui' => 1 ),
						array( 'key' => 'field_nqa_plc_years', 'label' => 'Years active', 'name' => 'years_active', 'type' => 'text' ),
					),
					$provenance( 'nqa_plc' )
				),
			)
		);

		// ---- Resource (community directory) ----
		// A front-facing support-service listing, not an archive record — so it
		// carries only public contact details (never the personal emails/phones
		// from the outreach sheet; rule #4) and no provenance/relationship fields.
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_resource',
				'title'    => 'Resource details',
				'location' => $loc( 'nqa_resource' ),
				'fields'   => array(
					array( 'key' => 'field_nqa_res_website', 'label' => 'Website', 'name' => 'website', 'type' => 'url' ),
					array( 'key' => 'field_nqa_res_phone', 'label' => 'Phone', 'name' => 'phone_number', 'type' => 'text', 'instructions' => 'Public service line only.' ),
					array( 'key' => 'field_nqa_res_email', 'label' => 'Email', 'name' => 'email', 'type' => 'text', 'instructions' => 'Public/general inbox only — never a personal address.' ),
					array( 'key' => 'field_nqa_res_service_area', 'label' => 'Service area', 'name' => 'service_area', 'type' => 'text', 'instructions' => 'e.g. "Niagara region", "St. Catharines", "Ontario", "Canada-wide".' ),
				),
			)
		);

		// ---- Item Details (core `post` materials) ----
		// Codified from the former DB-only "Item Details" group (same group +
		// field keys) so article provenance fields are version-controlled and
		// deploy to production. `location` is a Google Map — set via the admin
		// picker only (rule #8); it is centred by the acf/load_field filter below.
		acf_add_local_field_group(
			array(
				'key'        => 'group_6834a025610e6',
				'title'      => 'Item Details',
				'location'   => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
				'menu_order' => 2,
				'position'   => 'normal',
				'style'      => 'default',
				'fields'     => array(
					array( 'key' => 'field_6834a02632781', 'label' => 'Location', 'name' => 'location', 'type' => 'google_map' ),
					array( 'key' => 'field_6834a03732782', 'label' => 'Source', 'name' => 'source', 'type' => 'text' ),
					array( 'key' => 'field_6834a04c32783', 'label' => 'Publisher', 'name' => 'publisher', 'type' => 'url' ),
					array( 'key' => 'field_6834a05c32784', 'label' => 'Citation', 'name' => 'citation', 'type' => 'text' ),
					array( 'key' => 'field_6834c970031f6', 'label' => 'Date', 'name' => 'date', 'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'd/m/Y', 'first_day' => 1 ),
					array( 'key' => 'field_68ba1dba55faa', 'label' => 'Link', 'name' => 'link', 'type' => 'url', 'default_value' => 'https://' ),
					array( 'key' => 'field_68ba1de655fab', 'label' => 'Contact Person', 'name' => 'contact_person', 'type' => 'text' ),
					array( 'key' => 'field_68ba1df755fac', 'label' => 'Phone Number', 'name' => 'phone_number', 'type' => 'text' ),
					array( 'key' => 'field_68ba1dff55fad', 'label' => 'Email', 'name' => 'email', 'type' => 'text' ),
				),
			)
		);

		// ---- Cross-post references (core `post`) ----
		// Defined in code (reusing the former DB-only "Cross Post References"
		// group + field keys) so article posts can hold relationships that are
		// version-controlled and deploy to production — and so the value returns
		// as IDs, matching the entity relationship fields. Registering the same
		// key supersedes the DB group and preserves existing values.
		acf_add_local_field_group(
			array(
				'key'      => 'group_68abc015e292f',
				'title'    => 'Cross Post References',
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
				'fields'   => array(
					array(
						'key'           => 'field_68abc016febec',
						'label'         => 'Related entries',
						'name'          => 'relationship',
						'type'          => 'relationship',
						'post_type'     => nqa_content_types(),
						'filters'       => array( 'search', 'post_type' ),
						'return_format' => 'id',
					),
				),
			)
		);

		// ---- nqa_collection taxonomy: featured flag ----
		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_collection_tax',
				'title'    => 'Collection settings',
				'location' => array( array( array(
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => 'nqa_collection',
				) ) ),
				'fields'   => array(
					array(
						'key'           => 'field_nqa_col_featured',
						'label'         => 'Featured collection',
						'name'          => 'nqa_collection_featured',
						'type'          => 'true_false',
						'message'       => 'Show as the featured card on the Collections page',
						'instructions'  => 'Only one collection can be featured at a time. Saving will clear the featured flag from any other collection.',
						'default_value' => 0,
						'ui'            => 0,
					),
				),
			)
		);
	}
);

/**
 * Centre the ACF Google Map "location" field on the Niagara region (approx.
 * regional centroid) at a zoom that frames the 12 municipalities.
 */
// ── ACF JSON sync ─────────────────────────────────────────────────────────────
// Point ACF's JSON save/load to the theme's acf-json/ directory so all field
// groups (CPT fields + page fields) are version-controlled alongside the theme.

$nqa_acf_json = get_template_directory() . '/acf-json';

add_filter( 'acf/settings/save_json', function () use ( $nqa_acf_json ) {
	return $nqa_acf_json;
} );

add_filter( 'acf/settings/load_json', function ( $paths ) use ( $nqa_acf_json ) {
	$paths[] = $nqa_acf_json;
	return $paths;
} );

unset( $nqa_acf_json );

add_filter(
	'acf/load_field/name=location',
	function ( $field ) {
		if ( ( $field['type'] ?? '' ) === 'google_map' ) {
			$field['center_lat'] = '43.10';
			$field['center_lng'] = '-79.20';
			$field['zoom']       = '10';
		}
		return $field;
	}
);
