<?php
/**
 * Intake importers — turn contributed material into draft archive records without
 * ever altering the contributor's words.
 *
 *   1. Submission → draft record: converts an `nqa_submission` (Tell Your Story
 *      form #61 capture) into a DRAFT record of the archivist's chosen type. The
 *      submitter's account is copied VERBATIM into the body; provenance is set to
 *      community-submission and consent to Pending, so the stewardship gate holds
 *      the record as a draft until an archivist records consent.
 *
 *   (CSV importer for bulk locations/people is added in a later section.)
 *
 * Nothing here publishes anything — every path produces a draft for human review.
 */

defined( 'ABSPATH' ) || exit;

// ── Core conversion (pure function, unit-testable) ──────────────────────────

/**
 * Create a DRAFT archive record from a stored submission, preserving the
 * submitter's words verbatim. Idempotent: returns the existing record if this
 * submission has already been converted.
 *
 * @return int|WP_Error  New (or existing) record ID, or WP_Error on failure.
 */
function nqa_create_record_from_submission( int $submission_id, string $type ) {
	if ( 'nqa_submission' !== get_post_type( $submission_id ) ) {
		return new WP_Error( 'nqa_not_submission', 'Not a submission.' );
	}
	if ( ! in_array( $type, nqa_content_types(), true ) ) {
		return new WP_Error( 'nqa_bad_type', 'Unknown record type.' );
	}

	// Idempotency — never create a second record from the same submission.
	$existing = (int) get_post_meta( $submission_id, '_nqa_created_record', true );
	if ( $existing && get_post_status( $existing ) ) {
		return $existing;
	}

	$name  = get_post_meta( $submission_id, '_nqa_sub_name', true );
	$story = get_post_meta( $submission_id, '_nqa_sub_story', true );
	$email = get_post_meta( $submission_id, '_nqa_sub_email', true );
	$credit= get_post_meta( $submission_id, '_nqa_sub_credit', true );
	$loc   = get_post_meta( $submission_id, '_nqa_sub_loc', true );
	$att   = (int) get_post_meta( $submission_id, '_nqa_sub_attachment', true );

	// Title: the submitter's name for a person record; a neutral working title
	// otherwise (the archivist renames on review).
	$title = ( 'nqa_person' === $type && $name && '(anonymous)' !== $name )
		? $name
		: 'Submission ' . $submission_id . ' — needs a title';

	$record_id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => $title,
			// VERBATIM: the submitter's account, exactly as received. Never rewritten.
			'post_content' => (string) $story,
		),
		true
	);

	if ( is_wp_error( $record_id ) ) {
		return $record_id;
	}

	// Stewardship: mark origin and hold behind the consent gate.
	update_field( 'field_nqa_provenance', 'community-submission', $record_id );
	update_field( 'field_nqa_provenance_submitter', $name ?: '(anonymous)', $record_id );
	update_field( 'field_nqa_provenance_date', get_the_date( 'Y-m-d', $submission_id ), $record_id );
	update_field( 'field_nqa_consent_status', 'pending', $record_id );
	update_field(
		'field_nqa_consent_notes',
		sprintf(
			'From Tell Your Story submission #%d. Credit preference: %s. Contact: %s. Confirm consent before publishing.',
			$submission_id,
			$credit ?: 'not specified',
			$email ?: 'none given'
		),
		$record_id
	);

	// Best-effort municipality match from the free-text location field.
	if ( $loc ) {
		$term = get_term_by( 'name', $loc, 'municipality' ) ?: get_term_by( 'slug', sanitize_title( $loc ), 'municipality' );
		if ( $term ) {
			wp_set_object_terms( $record_id, array( (int) $term->term_id ), 'municipality', false );
		}
	}

	// Carry any uploaded asset over as the featured image.
	if ( $att && get_post( $att ) ) {
		set_post_thumbnail( $record_id, $att );
	}

	// Two-way link between submission and the record it produced.
	update_post_meta( $record_id, '_nqa_from_submission', $submission_id );
	update_post_meta( $submission_id, '_nqa_created_record', $record_id );
	update_post_meta(
		$record_id,
		'_nqa_archival_note',
		sprintf( 'Created from community submission #%d. The body is the contributor\'s own words — do not rewrite them. Verify factual details and record consent (currently Pending) before publishing.', $submission_id )
	);

	return (int) $record_id;
}

// ── Admin meta box on the submission screen ─────────────────────────────────

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'nqa_submission_convert',
			'Create archive record',
			'nqa_submission_convert_box',
			'nqa_submission',
			'side',
			'high'
		);
	}
);

function nqa_submission_convert_box( WP_Post $post ) : void {
	$created = (int) get_post_meta( $post->ID, '_nqa_created_record', true );

	if ( $created && get_post_status( $created ) ) {
		printf(
			'<p>Draft record created: <strong><a href="%s">%s</a></strong>.</p>'
			. '<p class="description">Its consent is set to <em>Pending</em>, so it can\'t be published until you record consent.</p>',
			esc_url( get_edit_post_link( $created ) ),
			esc_html( get_the_title( $created ) )
		);
		return;
	}

	wp_nonce_field( 'nqa_convert', 'nqa_convert_nonce' );

	$labels = array(
		'nqa_person' => 'Person',
		'nqa_org'    => 'Organization',
		'nqa_event'  => 'Event',
		'nqa_place'  => 'Place',
		'post'       => 'Article / material',
	);

	echo '<p><label for="nqa_convert_type"><strong>Record type</strong></label><br>';
	echo '<select name="nqa_convert_type" id="nqa_convert_type" style="width:100%;margin-top:.25rem">';
	foreach ( $labels as $val => $label ) {
		echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';

	echo '<p><label><input type="checkbox" name="nqa_convert_go" value="1"> '
		. 'Create draft record on Update</label></p>';
	echo '<p class="description">Copies the story <strong>verbatim</strong> into the new record, '
		. 'sets provenance to <em>community submission</em>, and holds it as a draft with consent <em>Pending</em>.</p>';
}

add_action(
	'save_post_nqa_submission',
	function ( int $post_id ) : void {
		if (
			empty( $_POST['nqa_convert_go'] )
			|| ! isset( $_POST['nqa_convert_nonce'] )
			|| ! wp_verify_nonce( $_POST['nqa_convert_nonce'], 'nqa_convert' )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! current_user_can( 'edit_posts' )
		) {
			return;
		}
		if ( get_post_meta( $post_id, '_nqa_created_record', true ) ) {
			return; // already converted
		}

		$type   = sanitize_key( $_POST['nqa_convert_type'] ?? '' );
		$result = nqa_create_record_from_submission( $post_id, $type );

		if ( is_wp_error( $result ) ) {
			set_transient( 'nqa_convert_err_' . get_current_user_id(), $result->get_error_message(), 60 );
			return;
		}

		// Advance the submission's own review status to "accepted".
		update_post_meta( $post_id, '_nqa_sub_status', 'accepted' );
		set_transient( 'nqa_convert_ok_' . get_current_user_id(), $result, 60 );
	}
);

// ── Admin notice after a conversion ─────────────────────────────────────────

add_action(
	'admin_notices',
	function () {
		$uid = get_current_user_id();

		$ok = get_transient( 'nqa_convert_ok_' . $uid );
		if ( $ok ) {
			delete_transient( 'nqa_convert_ok_' . $uid );
			printf(
				'<div class="notice notice-success is-dismissible"><p>'
				. 'Draft record created from this submission: <a href="%s">%s</a>. '
				. 'Consent is <strong>Pending</strong> — record consent before publishing.'
				. '</p></div>',
				esc_url( get_edit_post_link( (int) $ok ) ),
				esc_html( get_the_title( (int) $ok ) )
			);
		}

		$err = get_transient( 'nqa_convert_err_' . $uid );
		if ( $err ) {
			delete_transient( 'nqa_convert_err_' . $uid );
			echo '<div class="notice notice-error"><p>Could not create record: ' . esc_html( $err ) . '</p></div>';
		}
	}
);

// ── CSV importer (WP-CLI) ────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/** Columns accepted per record type (beyond the shared columns). */
	function nqa_csv_type_columns() : array {
		return array(
			'nqa_person' => array( 'pronouns', 'born', 'died', 'aliases', 'roles' ),
			'nqa_org'    => array( 'org_type', 'status', 'founded', 'dissolved', 'website', 'contact_person', 'email', 'phone_number' ),
			'nqa_place'  => array( 'place_type', 'address', 'years_active', 'still_exists' ),
			'nqa_event'  => array( 'recurrence', 'start_date', 'end_date' ),
			'post'       => array(),
		);
	}

	class NQA_Import_CLI {

		/**
		 * Bulk-import archive records from a CSV. Every row becomes a DRAFT.
		 *
		 * The CSV's first row is headers. `title` is required. Other recognized
		 * columns: content, source, citation, link, municipality, tags (comma or
		 * semicolon separated), collection, provenance, consent_status,
		 * consent_notes, seed — plus type-specific columns (e.g. born/died/roles
		 * for people; org_type/founded for orgs; place_type/address for places;
		 * start_date/recurrence for events). A `type` column sets each row's post
		 * type unless --type is given. The Google Map `location` field is never
		 * set from CSV (rule #8) — use `address` text instead.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to the CSV file.
		 *
		 * [--type=<type>]
		 * : Force all rows to this type (post|nqa_person|nqa_org|nqa_event|nqa_place).
		 *   Overrides any per-row `type` column.
		 *
		 * [--dry-run]
		 * : Parse and validate only; write nothing.
		 *
		 * ## EXAMPLES
		 *
		 *     wp nqa import-csv places.csv --type=nqa_place
		 *     wp nqa import-csv mixed.csv --dry-run
		 *
		 * @when after_wp_load
		 */
		public function import_csv( $args, $assoc ) {
			list( $file ) = $args;
			if ( ! is_readable( $file ) ) {
				WP_CLI::error( "Cannot read file: $file" );
			}
			if ( ! function_exists( 'update_field' ) ) {
				WP_CLI::error( 'ACF is not available.' );
			}

			$forced_type = $assoc['type'] ?? '';
			$dry         = ! empty( $assoc['dry-run'] );
			$types       = nqa_content_types();
			$type_cols   = nqa_csv_type_columns();

			if ( $forced_type && ! in_array( $forced_type, $types, true ) ) {
				WP_CLI::error( "Unknown --type: $forced_type" );
			}

			$fh = fopen( $file, 'r' );
			$headers = fgetcsv( $fh );
			if ( ! $headers ) {
				WP_CLI::error( 'Empty CSV.' );
			}
			$headers = array_map( fn( $h ) => strtolower( trim( $h ) ), $headers );

			$created = 0;
			$updated = 0;
			$skipped = 0;
			$rownum  = 1;

			while ( ( $data = fgetcsv( $fh ) ) !== false ) {
				$rownum++;
				if ( count( array_filter( $data, fn( $v ) => '' !== trim( (string) $v ) ) ) === 0 ) {
					continue; // blank line
				}
				$row = array_combine( $headers, array_map( fn( $v ) => trim( (string) $v ), array_pad( $data, count( $headers ), '' ) ) );

				$title = $row['title'] ?? '';
				if ( '' === $title ) {
					WP_CLI::warning( "Row $rownum: no title — skipped." );
					$skipped++;
					continue;
				}

				$type = $forced_type ?: ( $row['type'] ?? '' );
				if ( ! in_array( $type, $types, true ) ) {
					WP_CLI::warning( "Row $rownum ($title): missing/unknown type — skipped." );
					$skipped++;
					continue;
				}

				$seed = $row['seed'] ?? ( 'nqa-csv-' . sanitize_title( $type . '-' . $title ) );

				if ( $dry ) {
					WP_CLI::log( sprintf( '  [dry] row %d: %s (%s) seed=%s', $rownum, $title, $type, $seed ) );
					$created++;
					continue;
				}

				$existing = get_posts( array(
					'post_type' => $type, 'post_status' => 'any', 'meta_key' => '_nqa_seed',
					'meta_value' => $seed, 'numberposts' => 1, 'fields' => 'ids',
				) );

				$postarr = array(
					'post_type'    => $type,
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_content' => $row['content'] ?? '',
				);

				if ( $existing ) {
					$postarr['ID'] = $existing[0];
					$id = wp_update_post( $postarr );
					$updated++;
				} else {
					$id = wp_insert_post( $postarr );
					update_post_meta( $id, '_nqa_seed', $seed );
					$created++;
				}
				if ( is_wp_error( $id ) ) {
					WP_CLI::warning( "Row $rownum ($title): " . $id->get_error_message() );
					continue;
				}

				$this->apply_row( (int) $id, $type, $row, $type_cols );
				WP_CLI::log( sprintf( '  #%d  %-40s  [%s]', $id, nqa_preservation_trim_title( $id ), $existing ? 'updated' : 'created' ) );
			}
			fclose( $fh );

			WP_CLI::success( $dry
				? "Dry run: $created valid rows, $skipped skipped."
				: "Imported: $created created, $updated updated, $skipped skipped. All drafts." );
		}

		/** Apply recognized column values to a record. */
		private function apply_row( int $id, string $type, array $row, array $type_cols ) : void {
			$set = fn( $name, $key ) => isset( $row[ $name ] ) && '' !== $row[ $name ] ? update_field( $key, $row[ $name ], $id ) : null;

			// Shared provenance/citation.
			foreach ( array( 'source', 'citation', 'link' ) as $f ) {
				$set( $f, $f );
			}

			// Stewardship (default provenance staff-research, consent not-required).
			update_field( 'field_nqa_provenance', ( $row['provenance'] ?? '' ) ?: 'staff-research', $id );
			update_field( 'field_nqa_consent_status', ( $row['consent_status'] ?? '' ) ?: 'not-required', $id );
			$set( 'consent_notes', 'field_nqa_consent_notes' );

			// Type-specific columns (skip `location` always — rule #8).
			foreach ( $type_cols[ $type ] as $col ) {
				if ( 'still_exists' === $col ) {
					if ( isset( $row['still_exists'] ) && '' !== $row['still_exists'] ) {
						update_field( 'still_exists', in_array( strtolower( $row['still_exists'] ), array( '1', 'yes', 'true', 'y' ), true ), $id );
					}
					continue;
				}
				$set( $col, $col );
			}

			// Taxonomies.
			if ( ! empty( $row['municipality'] ) ) {
				$m = $row['municipality'];
				$term = get_term_by( 'slug', sanitize_title( $m ), 'municipality' ) ?: get_term_by( 'name', $m, 'municipality' );
				if ( $term ) {
					wp_set_object_terms( $id, array( (int) $term->term_id ), 'municipality', false );
				}
			}
			if ( ! empty( $row['tags'] ) ) {
				$tags = preg_split( '/[;,]/', $row['tags'] );
				wp_set_object_terms( $id, array_map( 'trim', array_filter( $tags ) ), 'post_tag', false );
			}
			if ( ! empty( $row['collection'] ) ) {
				$slugs = array();
				foreach ( preg_split( '/[;,]/', $row['collection'] ) as $c ) {
					$c = trim( $c );
					$term = get_term_by( 'slug', sanitize_title( $c ), 'nqa_collection' ) ?: get_term_by( 'name', $c, 'nqa_collection' );
					if ( $term ) {
						$slugs[] = (int) $term->term_id;
					}
				}
				if ( $slugs ) {
					wp_set_object_terms( $id, $slugs, 'nqa_collection', false );
				}
			}
		}
	}

	WP_CLI::add_command( 'nqa import-csv', array( new NQA_Import_CLI(), 'import_csv' ) );
}
