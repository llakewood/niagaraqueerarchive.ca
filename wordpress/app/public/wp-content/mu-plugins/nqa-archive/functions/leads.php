<?php
/**
 * Leads — a read-only researcher's aid that mines the preservation copy
 * (`_nqa_archive_text`) and record bodies for two things. WP-CLI: `wp nqa leads`.
 *
 *   --gaps   Cross-reference gaps: existing records mentioned by name in another
 *            record's text but NOT linked via the `relationship` field. These are
 *            "you should probably link these two" suggestions.
 *
 *   --leads  Content leads: multi-word Title-Case phrases in the preservation
 *            text that match NO existing record — candidate NEW entries. Purely
 *            deterministic (no external API); heuristic, so treat as a worklist.
 *
 * With neither flag, both run. This command NEVER writes — it only reports.
 * Seed any resulting drafts by hand (rule #2). Run on prod:
 *   ./scripts/wp-prod nqa leads
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	class NQA_Leads_CLI {

		/** Phrases that are never useful as new-entry leads. */
		private function stoplist() : array {
			return array_map(
				'strtolower',
				array(
					// Region / geography that recurs everywhere.
					'Niagara', 'Ontario', 'Canada', 'Niagara Region', 'Niagara Falls',
					'St. Catharines', 'Fort Erie', 'Port Colborne', 'Niagara-on-the-Lake',
					// Mastheads / publishers (rule #6 context, not entities).
					'Niagara This Week', 'St. Catharines Standard', 'Niagara Falls Review',
					'Welland Tribune', 'Metroland', 'Metroland Media', 'Village Media',
					// Platforms.
					'Facebook', 'Instagram', 'Twitter', 'YouTube',
					// Generic recurring phrases.
					'Pride Month', 'Pride Niagara', 'Human Rights', 'City Hall',
				)
			);
		}

		/** Month / day names to drop from Title-Case runs. */
		private function calendar_words() : array {
			return array_map(
				'strtolower',
				array(
					'January', 'February', 'March', 'April', 'May', 'June', 'July',
					'August', 'September', 'October', 'November', 'December',
					'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
				)
			);
		}

		/** Reject Title-Case runs that are sentence-boundary bleed or fragments. */
		private function is_noise( string $lc ) : bool {
			if ( str_contains( $lc, '. ' ) ) { return true; }              // crosses a sentence
			if ( str_contains( $lc, 'niagara this week' ) ) { return true; } // masthead bleed
			if ( str_contains( $lc, 'lgbtq' ) ) { return true; }            // too generic alone
			$tokens = preg_split( '/\s+/u', $lc );
			foreach ( $tokens as $t ) {
				if ( in_array( $t, $this->calendar_words(), true ) ) { return true; }
			}
			$lead_stops = array( 'in', 'the', 'on', 'we', 'it', 'a', 'an', 'at', 'for', 'but',
				'and', 'this', 'that', 'these', 'those', 'our', 'his', 'her', 'their',
				'niagara', 'ontario', 'st.', 'city', 'year' );
			return in_array( $tokens[0], $lead_stops, true ); // sentence-start / region fragment
		}

		private function norm( string $s ) : string {
			$s = html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES );
			$s = preg_replace( '/\s+/u', ' ', $s );
			return trim( $s );
		}

		/** The full searchable text for a record: body + preservation copy. */
		private function record_text( int $id ) : string {
			$body = get_post_field( 'post_content', $id );
			$arch = get_post_meta( $id, '_nqa_archive_text', true );
			return $this->norm( (string) $body . "\n" . (string) $arch );
		}

		/**
		 * Index of existing records: normalized name/alias => record ID.
		 * Person parentheticals are treated as aliases (drag/stage names); for
		 * other types only the base title + full title are indexed (a place's
		 * parenthetical is usually a disambiguator, not an alias).
		 */
		private function build_index( array $ids ) : array {
			$index = array();
			foreach ( $ids as $id ) {
				$type  = get_post_type( $id );
				$title = $this->norm( get_the_title( $id ) );
				$names = array( $title );

				if ( preg_match( '/^(.+?)\s*\((.+?)\)\s*$/u', $title, $m ) ) {
					$names[] = trim( $m[1] );
					if ( 'nqa_person' === $type ) {
						$names[] = trim( $m[2] );
					}
				}
				if ( 'nqa_person' === $type ) {
					$aliases = (string) get_field( 'aliases', $id );
					foreach ( preg_split( '/[;,]/', $aliases ) as $a ) {
						$a = trim( $a );
						if ( $a !== '' ) {
							$names[] = $a;
						}
					}
				}

				foreach ( $names as $n ) {
					// Only index reasonably specific names (multi-word or >=5 chars)
					// so short common tokens don't match everything.
					if ( mb_strlen( $n ) >= 5 || str_contains( $n, ' ' ) ) {
						$index[ mb_strtolower( $n ) ] = $id;
					}
				}
			}
			return $index;
		}

		/** Existing relationship links for a record (IDs). */
		private function rel_ids( int $id ) : array {
			$rel = function_exists( 'get_field' ) ? get_field( 'relationship', $id ) : null;
			if ( ! is_array( $rel ) ) {
				return array();
			}
			return array_map( fn( $x ) => is_object( $x ) ? (int) $x->ID : (int) $x, $rel );
		}

		/**
		 * Report gaps + leads over the archive.
		 *
		 * ## OPTIONS
		 *
		 * [--gaps]
		 * : Only report cross-reference gaps.
		 *
		 * [--leads]
		 * : Only report new-entry content leads.
		 *
		 * [--type=<type>]
		 * : Limit the *scanned* records to one type (post|nqa_person|nqa_org|
		 *   nqa_event|nqa_place). The match index always covers all types.
		 *
		 * [--min=<n>]
		 * : Minimum number of records a lead phrase must appear in. Default: 2.
		 *
		 * [--format=<format>]
		 * : table (default) or csv.
		 *
		 * ## EXAMPLES
		 *
		 *     wp nqa leads
		 *     wp nqa leads --gaps
		 *     wp nqa leads --leads --min=3 --format=csv
		 *
		 * @when after_wp_load
		 */
		public function leads( $args, $assoc ) {
			$do_gaps  = isset( $assoc['gaps'] );
			$do_leads = isset( $assoc['leads'] );
			if ( ! $do_gaps && ! $do_leads ) {
				$do_gaps = $do_leads = true; // neither flag → both
			}
			$min    = max( 1, (int) ( $assoc['min'] ?? 2 ) );
			$format = $assoc['format'] ?? 'table';
			$types  = nqa_content_types();
			array_unshift( $types, 'post' );

			$scan_type = $assoc['type'] ?? '';
			if ( $scan_type && ! in_array( $scan_type, $types, true ) ) {
				WP_CLI::error( "Unknown --type: $scan_type" );
			}

			// All records (for the match index) and the subset we actually scan.
			$all_ids = get_posts( array(
				'post_type'      => $types,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );
			$index    = $this->build_index( $all_ids );
			$scan_ids = $scan_type
				? get_posts( array( 'post_type' => $scan_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) )
				: $all_ids;

			if ( $do_gaps ) {
				$this->report_gaps( $scan_ids, $index, $format );
			}
			if ( $do_leads ) {
				if ( $do_gaps ) { WP_CLI::log( '' ); }
				$this->report_leads( $scan_ids, $index, $min, $format );
			}
		}

		/** Cross-reference gaps: named mentions of other records that aren't linked. */
		private function report_gaps( array $scan_ids, array $index, string $format ) : void {
			$rows = array();
			foreach ( $scan_ids as $id ) {
				$text = mb_strtolower( $this->record_text( $id ) );
				if ( $text === '' ) {
					continue;
				}
				$linked    = $this->rel_ids( $id );
				$own_title = mb_strtolower( $this->norm( get_the_title( $id ) ) );

				foreach ( $index as $name => $target ) {
					if ( $target === $id || in_array( $target, $linked, true ) ) {
						continue;
					}
					// Same-title as this record → a duplicate/self, not a cross-ref.
					if ( mb_strtolower( $this->norm( get_the_title( $target ) ) ) === $own_title ) {
						continue;
					}
					// Whole-phrase, word-boundary match.
					if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $name, '/' ) . '(?![\p{L}\p{N}])/u', $text ) ) {
						$rows[] = array(
							'from_id'   => $id,
							'from'      => nqa_preservation_trim_title( $id ),
							'mentions'  => get_the_title( $target ),
							'target_id' => $target,
						);
					}
				}
			}

			WP_CLI::log( sprintf( '=== Cross-reference gaps (%d) — mentioned but not linked ===', count( $rows ) ) );
			if ( ! $rows ) {
				WP_CLI::log( '  none found.' );
				return;
			}
			WP_CLI\Utils\format_items( $format, $rows, array( 'from_id', 'from', 'mentions', 'target_id' ) );
		}

		/** Deterministic content leads: unmatched multi-word Title-Case phrases. */
		private function report_leads( array $scan_ids, array $index, int $min, string $format ) : void {
			$stop = array_merge( $this->stoplist(), $this->calendar_words() );
			$conn = array( 'of', 'and', 'the', 'for', 'at', 'on', 'de', 'la', 'le', 'von', 'van' );

			$doc_freq = array();  // phrase => [ids]
			$display  = array();  // lc phrase => canonical display

			foreach ( $scan_ids as $id ) {
				$text = $this->record_text( $id );
				if ( $text === '' ) {
					continue;
				}
				// Multi-word Title-Case runs (2+ capitalized words, connectors allowed inside).
				if ( ! preg_match_all( '/\b[A-Z][\p{L}’\'\-\.]+(?:\s+(?:' . implode( '|', $conn ) . ')\s+[A-Z][\p{L}’\'\-\.]+|\s+[A-Z][\p{L}’\'\-\.]+)+/u', $text, $mm ) ) {
					continue;
				}
				$seen_here = array();
				foreach ( $mm[0] as $phrase ) {
					$phrase = trim( preg_replace( '/[\s\.,;:]+$/u', '', $phrase ) );
					$lc     = mb_strtolower( $phrase );

					if ( in_array( $lc, $stop, true ) ) { continue; }
					if ( $this->is_noise( $lc ) ) { continue; }
					if ( isset( $seen_here[ $lc ] ) ) { continue; }
					$seen_here[ $lc ] = true;

					// Skip anything that maps to (or overlaps) an existing record.
					$known = false;
					foreach ( $index as $name => $tid ) {
						if ( $lc === $name || str_contains( $lc, $name ) || str_contains( $name, $lc ) ) {
							$known = true;
							break;
						}
					}
					if ( $known ) { continue; }

					$doc_freq[ $lc ][] = $id;
					$display[ $lc ]    = $display[ $lc ] ?? $phrase;
				}
			}

			$rows = array();
			foreach ( $doc_freq as $lc => $ids ) {
				$ids = array_values( array_unique( $ids ) );
				if ( count( $ids ) < $min ) { continue; }
				$rows[] = array(
					'candidate'  => $display[ $lc ],
					'in_records' => count( $ids ),
					'record_ids' => implode( ',', array_slice( $ids, 0, 8 ) ),
				);
			}
			usort( $rows, fn( $a, $b ) => $b['in_records'] <=> $a['in_records'] );

			WP_CLI::log( sprintf( '=== Content leads (%d) — unmatched phrases in >=%d records ===', count( $rows ), $min ) );
			if ( ! $rows ) {
				WP_CLI::log( '  none found. (Lower --min to widen, or capture more preservation text.)' );
				return;
			}
			WP_CLI\Utils\format_items( $format, $rows, array( 'candidate', 'in_records', 'record_ids' ) );
		}
	}

	WP_CLI::add_command( 'nqa leads', array( new NQA_Leads_CLI(), 'leads' ) );
}
