<?php
/**
 * Stewardship & consent — the shared provenance/consent layer that every archive
 * record carries, regardless of how it enters the archive (staff research, a
 * community submission, an interview, or an institutional donation).
 *
 * This is the decoupling layer between the two intake streams: both hand-seeded
 * records and the submission/CSV importers write these same fields, so a record's
 * origin and its consent state are always explicit and never confused.
 *
 *   - `provenance`           — how the record entered the archive (channel).
 *   - `provenance_submitter` — the contributor/source, when not staff research.
 *   - `provenance_date`      — when it was received or recorded.
 *   - `consent_status`       — whether it is cleared to be published.
 *   - `consent_notes`        — who granted/withheld consent, and any limits.
 *
 * Consent gate: a record whose consent is `pending` or `restricted` cannot be
 * published — an attempt is reverted to Draft with an admin notice. This
 * generalizes the per-record consent flags (McTigue #317, Coward #303, Davis's
 * son) into schema, so no submission can be published before it is cleared.
 */

defined( 'ABSPATH' ) || exit;

/** Consent states that block a record from being published. */
function nqa_consent_blocking_states() : array {
	return array( 'pending', 'restricted' );
}

// ── 1. Shared field group (all content types) ───────────────────────────────

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// One group, attached to core `post` + the four entity CPTs via OR rules.
		$location = array_map(
			function ( $type ) {
				return array( array( 'param' => 'post_type', 'operator' => '==', 'value' => $type ) );
			},
			nqa_content_types()
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_nqa_stewardship',
				'title'    => 'Stewardship & consent',
				'location' => $location,
				// Sit below the record's own detail fields but above nothing else.
				'menu_order' => 20,
				'position'   => 'normal',
				'fields'   => array(
					array(
						'key'          => 'field_nqa_provenance',
						'label'        => 'Provenance',
						'name'         => 'provenance',
						'type'         => 'select',
						'instructions' => 'How this record entered the archive. Keeps cited journalism distinct from community-contributed testimony.',
						'choices'      => array(
							'cited-journalism'      => 'Cited journalism / published source',
							'community-submission'  => 'Community submission (Tell Your Story)',
							'oral-history-interview'=> 'Oral history / interview',
							'institutional-donation'=> 'Institutional donation / partner',
							'staff-research'        => 'Staff research',
						),
						'allow_null'   => 1,
						'ui'           => 1,
					),
					array(
						'key'               => 'field_nqa_provenance_submitter',
						'label'             => 'Contributor / source',
						'name'              => 'provenance_submitter',
						'type'              => 'text',
						'instructions'      => 'Who contributed this (person, family, or institution). Leave blank for staff research.',
						'conditional_logic' => array(
							array(
								array( 'field' => 'field_nqa_provenance', 'operator' => '!=', 'value' => 'cited-journalism' ),
								array( 'field' => 'field_nqa_provenance', 'operator' => '!=', 'value' => 'staff-research' ),
							),
						),
					),
					array(
						'key'          => 'field_nqa_provenance_date',
						'label'        => 'Received / recorded',
						'name'         => 'provenance_date',
						'type'         => 'text',
						'instructions' => 'When this was received or recorded. A year is fine.',
					),
					array(
						'key'          => 'field_nqa_consent_status',
						'label'        => 'Consent status',
						'name'         => 'consent_status',
						'type'         => 'select',
						'instructions' => 'Whether this record is cleared to publish. "Pending" or "Restricted" blocks publishing until resolved.',
						'choices'      => array(
							'not-required' => 'Not required (public role / cited source)',
							'pending'      => 'Pending — consent needed before publishing',
							'granted'      => 'Granted',
							'restricted'   => 'Restricted — do not publish',
						),
						'default_value'=> 'not-required',
						'allow_null'   => 0,
						'ui'           => 1,
					),
					array(
						'key'               => 'field_nqa_consent_notes',
						'label'             => 'Consent notes',
						'name'              => 'consent_notes',
						'type'              => 'textarea',
						'rows'              => 2,
						'instructions'      => 'Who granted or withheld consent, when, and any limits (e.g. "photo not for publication").',
						'conditional_logic' => array(
							array(
								array( 'field' => 'field_nqa_consent_status', 'operator' => '!=', 'value' => 'not-required' ),
							),
						),
					),
				),
			)
		);
	}
);

// ── 2. Consent publish-gate ─────────────────────────────────────────────────

/**
 * After ACF has written its fields, block publication of any record whose
 * consent is pending or restricted: revert it to Draft and flag it for the
 * editor. Runs at priority 20 so the just-saved consent value is authoritative.
 */
add_action(
	'acf/save_post',
	function ( $post_id ) {
		static $reentry = false;
		if ( $reentry || ! is_numeric( $post_id ) ) {
			return;
		}
		$post_id = (int) $post_id;

		if ( ! in_array( get_post_type( $post_id ), nqa_content_types(), true ) ) {
			return;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$consent = get_post_meta( $post_id, 'consent_status', true );
		if ( ! in_array( $consent, nqa_consent_blocking_states(), true ) ) {
			return;
		}

		// Revert to draft. Guard against re-entry (wp_update_post → save hooks).
		$reentry = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		$reentry = false;

		set_transient(
			'nqa_consent_block_' . get_current_user_id(),
			$consent,
			60
		);
	},
	20
);

/** Show the consent-block notice after a blocked publish attempt. */
add_action(
	'admin_notices',
	function () {
		$key     = 'nqa_consent_block_' . get_current_user_id();
		$blocked = get_transient( $key );
		if ( ! $blocked ) {
			return;
		}
		delete_transient( $key );

		$msg = 'restricted' === $blocked
			? 'This record is marked <strong>Restricted — do not publish</strong>. It has been kept as a draft.'
			: 'This record\'s consent is still <strong>Pending</strong>. It has been kept as a draft until consent is recorded as Granted.';

		echo '<div class="notice notice-warning"><p>' . wp_kses_post( $msg ) . '</p></div>';
	}
);

// ── 3. Consent column on entity lists (review triage) ───────────────────────

add_action(
	'admin_init',
	function () {
		foreach ( nqa_entity_post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", 'nqa_stewardship_column' );
			add_action( "manage_{$type}_posts_custom_column", 'nqa_stewardship_column_value', 10, 2 );
		}
	}
);

/** Insert a "Consent" column before the date column. */
function nqa_stewardship_column( array $cols ) : array {
	$date = $cols['date'] ?? null;
	unset( $cols['date'] );
	$cols['nqa_consent'] = 'Consent';
	if ( null !== $date ) {
		$cols['date'] = $date;
	}
	return $cols;
}

/** Render the consent chip for a record. */
function nqa_stewardship_column_value( string $col, int $post_id ) : void {
	if ( 'nqa_consent' !== $col ) {
		return;
	}
	$status = get_post_meta( $post_id, 'consent_status', true ) ?: 'not-required';
	$labels = array(
		'not-required' => 'Not required',
		'pending'      => 'Pending',
		'granted'      => 'Granted',
		'restricted'   => 'Restricted',
	);
	$colours = array(
		'not-required' => '#999',
		'pending'      => '#503AA8',
		'granted'      => '#2e7d32',
		'restricted'   => '#b71c1c',
	);
	$colour = $colours[ $status ] ?? '#999';
	echo '<span style="display:inline-block;padding:.15rem .55rem;background:' . esc_attr( $colour ) . ';'
		. 'color:#fff;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">'
		. esc_html( $labels[ $status ] ?? $status ) . '</span>';
}
