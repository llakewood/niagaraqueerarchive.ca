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
	}
);

/**
 * Centre the ACF Google Map "location" field on the Niagara region (approx.
 * regional centroid) at a zoom that frames the 12 municipalities.
 */
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
