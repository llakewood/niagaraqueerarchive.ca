<?php
/**
 * NQA Submissions — captures Tell Your Story form entries as private,
 * admin-reviewable records and delivers a polished post-submit experience.
 *
 * 1. Registers the `nqa_submission` private CPT (admin-only, no public URL).
 * 2. Hooks into CF7 form #61 on wpcf7_before_send_mail: creates a submission
 *    post with all fields as meta, moves any uploaded file to the media library.
 * 3. Admin meta box: read-only submitted data + editable Status field.
 * 4. Admin list: Submitter, Type, Status columns; "Pending" quick-filter tab.
 * 5. /tell/ page: thank-you panel (replaces form on success) with "what happens
 *    next" and an optional registration CTA. Safety prompt injected above the
 *    description textarea on page load.
 */

defined( 'ABSPATH' ) || exit;

// ── 1. CPT ─────────────────────────────────────────────────────────────────

add_action(
	'init',
	function () {
		register_post_type(
			'nqa_submission',
			array(
				'label'               => 'Submissions',
				'labels'              => array(
					'name'               => 'Submissions',
					'singular_name'      => 'Submission',
					'menu_name'          => 'Submissions',
					'all_items'          => 'All Submissions',
					'edit_item'          => 'Review Submission',
					'view_item'          => 'View Submission',
					'search_items'       => 'Search Submissions',
					'not_found'          => 'No submissions yet.',
					'not_found_in_trash' => 'No submissions in trash.',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor' ),
				'menu_icon'           => 'dashicons-email-alt2',
				'menu_position'       => 25,
			)
		);
	}
);

// ── 2. CF7 capture ─────────────────────────────────────────────────────────

add_action(
	'wpcf7_before_send_mail',
	function ( $contact_form, &$abort, $submission ) {
		if ( ! ( $contact_form instanceof WPCF7_ContactForm ) ) {
			return;
		}
		if ( $contact_form->id() !== 61 ) {
			return;
		}

		$data = $submission->get_posted_data();

		$name        = sanitize_text_field( $data['your-name'] ?? '' );
		$story_title = sanitize_text_field( $data['story-title'] ?? '' );

		// Prefer the submitter's own Title (form field #61); fall back to the
		// name + date stamp so the submission is never left untitled.
		$title = $story_title !== ''
			? $story_title
			: ( $name !== ''
				? $name . ' — ' . current_time( 'Y-m-d' )
				: 'Anonymous — ' . current_time( 'Y-m-d H:i' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'nqa_submission',
				'post_title'   => $title,
				'post_content' => '', // archivist notes (editable in admin)
				'post_status'  => 'private',
			)
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return;
		}

		// Store submitted fields as private meta.
		$meta = array(
			'_nqa_sub_title'  => $story_title,
			'_nqa_sub_name'   => $name ?: '(anonymous)',
			'_nqa_sub_email'  => sanitize_email( $data['your-email'] ?? '' ),
			'_nqa_sub_phone'  => sanitize_text_field( $data['your-telephone'] ?? '' ),
			'_nqa_sub_type'   => sanitize_text_field( is_array( $data['submission_type'] ?? '' ) ? implode( ', ', $data['submission_type'] ) : ( $data['submission_type'] ?? '' ) ),
			'_nqa_sub_story'  => sanitize_textarea_field( $data['your-message'] ?? '' ),
			'_nqa_sub_period' => sanitize_text_field( $data['time-period'] ?? '' ),
			'_nqa_sub_loc'    => sanitize_text_field( $data['location'] ?? '' ),
			'_nqa_sub_credit' => sanitize_text_field( is_array( $data['credit_preference'] ?? '' ) ? implode( ', ', $data['credit_preference'] ) : ( $data['credit_preference'] ?? '' ) ),
			'_nqa_sub_status' => 'pending',
		);
		foreach ( $meta as $key => $val ) {
			update_post_meta( $post_id, $key, $val );
		}

		// Move any uploaded file into the WP media library before CF7 cleans it.
		$uploaded = $submission->uploaded_files();
		$file_raw = $uploaded['file-upload'] ?? null;
		$file_path = is_array( $file_raw ) ? ( $file_raw[0] ?? null ) : $file_raw;

		if ( $file_path && file_exists( $file_path ) ) {
			$attachment_id = nqa_submission_sideload( $file_path, $post_id );
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $post_id, '_nqa_sub_attachment', $attachment_id );
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}
	},
	10,
	3
);

/** Copy a CF7 temp file into the WP media library and return the attachment ID. */
function nqa_submission_sideload( string $tmp_path, int $parent_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$filename = basename( $tmp_path );
	$upload   = wp_upload_bits( $filename, null, file_get_contents( $tmp_path ) );

	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'upload_failed', $upload['error'] );
	}

	$mime = wp_check_filetype( $filename )['type'] ?: 'application/octet-stream';

	$attachment_id = wp_insert_attachment(
		array(
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_mime_type' => $mime,
			'post_status'    => 'private',
		),
		$upload['file'],
		$parent_id
	);

	if ( ! is_wp_error( $attachment_id ) ) {
		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);
	}

	return $attachment_id;
}

// ── 3. Admin meta box ───────────────────────────────────────────────────────

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'nqa_submission_data',
			'Submitted Information',
			'nqa_submission_meta_box',
			'nqa_submission',
			'normal',
			'high'
		);
	}
);

function nqa_submission_meta_box( WP_Post $post ) : void {
	wp_nonce_field( 'nqa_sub_status_save', 'nqa_sub_nonce' );

	$fields = array(
		'Title'           => get_post_meta( $post->ID, '_nqa_sub_title', true ),
		'Submitter'       => get_post_meta( $post->ID, '_nqa_sub_name', true ),
		'Email'           => get_post_meta( $post->ID, '_nqa_sub_email', true ),
		'Phone'           => get_post_meta( $post->ID, '_nqa_sub_phone', true ),
		'Type'            => get_post_meta( $post->ID, '_nqa_sub_type', true ),
		'Time period'     => get_post_meta( $post->ID, '_nqa_sub_period', true ),
		'Location noted'  => get_post_meta( $post->ID, '_nqa_sub_loc', true ),
		'Credit as'       => get_post_meta( $post->ID, '_nqa_sub_credit', true ),
	);
	$story  = get_post_meta( $post->ID, '_nqa_sub_story', true );
	$status = get_post_meta( $post->ID, '_nqa_sub_status', true ) ?: 'pending';
	$att_id = (int) get_post_meta( $post->ID, '_nqa_sub_attachment', true );

	echo '<style>'
		. '.nqa-sub-table{width:100%;border-collapse:collapse;margin-bottom:1rem}'
		. '.nqa-sub-table th{width:9rem;text-align:left;font-weight:600;padding:.4rem .5rem;'
			. 'font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:#503AA8;'
			. 'vertical-align:top}'
		. '.nqa-sub-table td{padding:.4rem .5rem;border-bottom:1px solid #eee;font-size:.9rem}'
		. '.nqa-sub-story{background:#FBFAF3;border:1px solid #ddd;padding:.75rem;'
			. 'font-size:.9rem;line-height:1.6;white-space:pre-wrap;margin-bottom:1rem}'
		. '.nqa-sub-status-row{display:flex;align-items:center;gap:.75rem;margin-top:1rem}'
		. '</style>';

	echo '<table class="nqa-sub-table"><tbody>';
	foreach ( $fields as $label => $val ) {
		if ( $val === '' || $val === null ) { continue; }
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	echo '</tbody></table>';

	if ( $story ) {
		echo '<p style="font-weight:600;font-size:.8rem;text-transform:uppercase;'
			. 'letter-spacing:.06em;color:#503AA8;margin-bottom:.35rem">Story / Description</p>';
		echo '<div class="nqa-sub-story">' . esc_html( $story ) . '</div>';
	}

	if ( $att_id ) {
		$url  = wp_get_attachment_url( $att_id );
		$name = nqa_decode_entities( get_the_title( $att_id ) );
		if ( $url ) {
			echo '<p style="margin-bottom:1rem"><strong>Attached file:</strong> '
				. '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $name ?: basename( $url ) ) . '</a></p>';
		}
	}

	$status_options = array(
		'pending'   => 'Pending',
		'reviewing' => 'In Review',
		'accepted'  => 'Accepted',
		'declined'  => 'Declined',
	);

	echo '<div class="nqa-sub-status-row">'
		. '<label for="nqa_sub_status" style="font-weight:600">Status</label>'
		. '<select name="nqa_sub_status" id="nqa_sub_status">';
	foreach ( $status_options as $val => $label ) {
		echo '<option value="' . esc_attr( $val ) . '"' . selected( $status, $val, false ) . '>'
			. esc_html( $label ) . '</option>';
	}
	echo '</select>'
		. '<p class="description">Use <em>Archivist notes</em> below to record your review, questions, and next steps.</p>'
		. '</div>';
}

add_action(
	'save_post_nqa_submission',
	function ( int $post_id ) : void {
		if (
			! isset( $_POST['nqa_sub_nonce'] )
			|| ! wp_verify_nonce( $_POST['nqa_sub_nonce'], 'nqa_sub_status_save' )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
		) {
			return;
		}
		if ( isset( $_POST['nqa_sub_status'] ) ) {
			$allowed = array( 'pending', 'reviewing', 'accepted', 'declined' );
			$val     = sanitize_text_field( $_POST['nqa_sub_status'] );
			if ( in_array( $val, $allowed, true ) ) {
				update_post_meta( $post_id, '_nqa_sub_status', $val );
			}
		}
	}
);

// ── 4. Admin list columns ───────────────────────────────────────────────────

add_filter(
	'manage_nqa_submission_posts_columns',
	function ( array $cols ) : array {
		unset( $cols['date'] );
		return array_merge(
			array( 'cb' => $cols['cb'] ?? '<input type="checkbox">' ),
			array(
				'title'       => 'Submission',
				'nqa_sub_who' => 'Submitter',
				'nqa_sub_type'=> 'Type',
				'nqa_sub_sta' => 'Status',
			),
			array( 'date' => 'Received' )
		);
	}
);

add_action(
	'manage_nqa_submission_posts_custom_column',
	function ( string $col, int $post_id ) : void {
		$status_colours = array(
			'pending'   => '#503AA8',
			'reviewing' => '#e8b800',
			'accepted'  => '#2e7d32',
			'declined'  => '#b71c1c',
		);
		switch ( $col ) {
			case 'nqa_sub_who':
				echo esc_html( get_post_meta( $post_id, '_nqa_sub_name', true ) ?: '(anonymous)' );
				break;
			case 'nqa_sub_type':
				echo esc_html( get_post_meta( $post_id, '_nqa_sub_type', true ) ?: '—' );
				break;
			case 'nqa_sub_sta':
				$s      = get_post_meta( $post_id, '_nqa_sub_status', true ) ?: 'pending';
				$labels = array( 'pending' => 'Pending', 'reviewing' => 'In Review', 'accepted' => 'Accepted', 'declined' => 'Declined' );
				$colour = $status_colours[ $s ] ?? '#503AA8';
				echo '<span style="display:inline-block;padding:.15rem .55rem;background:' . esc_attr( $colour ) . ';'
					. 'color:#fff;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">'
					. esc_html( $labels[ $s ] ?? $s ) . '</span>';
				break;
		}
	},
	10,
	2
);

// "Pending" quick-filter tab in the Submissions list.
add_filter(
	'views_edit-nqa_submission',
	function ( array $views ) : array {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_nqa_sub_status' AND meta_value = 'pending'"
		);
		if ( $count > 0 ) {
			$current = ( isset( $_GET['nqa_status'] ) && $_GET['nqa_status'] === 'pending' ) ? ' class="current"' : '';
			$url     = add_query_arg( array( 'post_type' => 'nqa_submission', 'nqa_status' => 'pending' ), admin_url( 'edit.php' ) );
			$views['nqa_pending'] = sprintf(
				'<a href="%s"%s>Pending <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$current,
				$count
			);
		}
		return $views;
	}
);

// Filter query when ?nqa_status=pending.
add_action(
	'pre_get_posts',
	function ( WP_Query $q ) : void {
		if (
			! is_admin()
			|| ! $q->is_main_query()
			|| $q->get( 'post_type' ) !== 'nqa_submission'
			|| empty( $_GET['nqa_status'] )
		) {
			return;
		}
		$allowed = array( 'pending', 'reviewing', 'accepted', 'declined' );
		$status  = sanitize_text_field( $_GET['nqa_status'] );
		if ( in_array( $status, $allowed, true ) ) {
			$q->set(
				'meta_query',
				array( array( 'key' => '_nqa_sub_status', 'value' => $status ) )
			);
		}
	}
);

// ── 5. /tell/ page: thank-you panel + safety prompt ────────────────────────

add_action(
	'wp_footer',
	function () : void {
		if ( ! is_page( 49 ) ) {
			return;
		}

		$register_url = wp_registration_url();
		$login_url    = wp_login_url( get_permalink() );
		$is_member    = current_user_can( 'read' );
		?>
		<template id="nqa-thankyou-tpl">
			<div class="nqa-thankyou" role="status" aria-live="polite">
				<div class="nqa-thankyou__icon" aria-hidden="true">✦</div>
				<h2 class="nqa-thankyou__heading">Thank you for your contribution.</h2>
				<p class="nqa-thankyou__lead">Your submission has been received. An archivist will review it carefully.</p>

				<ol class="nqa-thankyou__steps">
					<li><strong>Review</strong> — An archivist reads your submission, usually within a few days.</li>
					<li><strong>Follow-up</strong> — If we have questions or need more detail, we'll reach out at the email you provided.</li>
					<li><strong>Archiving</strong> — With your permission, your contribution will be added to the archive and credited as you chose.</li>
				</ol>

				<?php if ( ! $is_member ) : ?>
				<div class="nqa-thankyou__cta">
					<p><strong>Want to stay in the loop?</strong> Register for a free account to receive updates about your contribution and access member-only details in the archive.</p>
					<a class="nqa-thankyou__btn" href="<?php echo esc_url( $register_url ); ?>">Register free →</a>
					<span class="nqa-thankyou__or">Already have one? <a href="<?php echo esc_url( $login_url ); ?>">Log in</a></span>
				</div>
				<?php else : ?>
				<p class="nqa-thankyou__member">You're logged in — we'll be in touch at your registered email address.</p>
				<?php endif; ?>

				<p class="nqa-thankyou__contact">
					Questions? <a href="/contact-us/">Reach out any time.</a>
				</p>
			</div>
		</template>

		<style>
		.nqa-thankyou{max-width:54ch;margin:3rem auto;padding:2.5rem 2rem;
			background:var(--nqa-cream,#FBFAF3);border:2px solid var(--nqa-ink,#111);
			box-shadow:8px 8px 0 var(--nqa-violet,#503AA8);text-align:left}
		.nqa-thankyou__icon{font-size:2rem;color:var(--nqa-violet,#503AA8);
			margin-bottom:.75rem;display:block}
		.nqa-thankyou__heading{font-size:1.5rem;font-weight:700;margin:0 0 .5rem;
			color:var(--nqa-ink,#111)}
		.nqa-thankyou__lead{font-size:1.05rem;margin-bottom:1.5rem;color:#444}
		.nqa-thankyou__steps{padding-left:1.25rem;margin:0 0 1.75rem;
			display:flex;flex-direction:column;gap:.75rem;font-size:.95rem;line-height:1.6}
		.nqa-thankyou__cta{background:var(--nqa-violet,#503AA8);color:#fff;
			padding:1.25rem 1.5rem;margin:1.5rem 0;border:2px solid var(--nqa-ink,#111)}
		.nqa-thankyou__cta p{margin:0 0 1rem;font-size:.95rem}
		.nqa-thankyou__btn{display:inline-block;padding:.55rem 1.25rem;
			background:var(--nqa-yellow,#FFEE58);color:var(--nqa-ink,#111);
			font-weight:700;text-decoration:none;border:2px solid var(--nqa-ink,#111);
			transition:transform .1s ease}
		.nqa-thankyou__btn:hover{transform:translate(-2px,-2px)}
		.nqa-thankyou__or{display:block;margin-top:.65rem;font-size:.85rem;opacity:.85}
		.nqa-thankyou__or a{color:var(--nqa-yellow,#FFEE58)}
		.nqa-thankyou__member{font-size:.9rem;color:#555;margin:1rem 0}
		.nqa-thankyou__contact{font-size:.85rem;margin:.75rem 0 0;color:#666}
		.nqa-thankyou__contact a{color:var(--nqa-violet,#503AA8)}
		.nqa-form-safety{font-size:.85rem;color:#555;font-style:italic;
			margin:.35rem 0 .85rem;line-height:1.5;padding-left:.1rem}
		</style>

		<script>
		(function () {
			// Safety note: inject below the description textarea on page load.
			document.addEventListener('DOMContentLoaded', function () {
				var ta = document.querySelector('.wpcf7 textarea[name="your-message"]');
				if (!ta) { return; }
				var wrap = ta.closest('label') || ta.parentNode;
				var note = document.createElement('p');
				note.className = 'nqa-form-safety';
				note.textContent = 'If your submission names living people, please consider whether they are publicly out. A note in your description helps us handle their information with care.';
				wrap.after(note);
			});

			// Thank-you panel: swap in after successful submission.
			document.addEventListener('wpcf7mailsent', function (e) {
				var tpl = document.getElementById('nqa-thankyou-tpl');
				if (!tpl) { return; }
				var panel = tpl.content.cloneNode(true);

				// Find the form's parent column and replace content.
				var form  = e.target;
				var col   = form.closest('.wp-block-column') || form.closest('.wp-block-group') || form.parentNode;

				// Clear the column and insert the thank-you panel.
				while (col.firstChild) { col.removeChild(col.firstChild); }
				col.appendChild(panel);
				col.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		})();
		</script>
		<?php
	},
	20
);
