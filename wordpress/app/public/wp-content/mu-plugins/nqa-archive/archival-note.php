<?php
/**
 * Staff-only archival notes. Each record's curatorial note lives in its own
 * protected meta field (_nqa_archival_note) — not the public post body — edited
 * via an editor metabox and rendered on the front end ONLY to logged-in staff
 * who can edit the record. Applies to articles (post) and the entity CPTs.
 */

defined( 'ABSPATH' ) || exit;

/** Protected (underscore) meta key: hidden from the default Custom Fields box + REST. */
const NQA_ARCHIVAL_NOTE_META = '_nqa_archival_note';

/** Post types that carry an archival note: articles + the four entity CPTs. */
function nqa_archival_note_types() {
	return nqa_content_types();
}

/** May the current user see staff-only notes for this post? (Can edit it.) */
function nqa_archival_note_can_view( $post_id ) {
	return is_user_logged_in() && current_user_can( 'edit_post', $post_id );
}

/* -------------------------------------------------------------------------
 * Front end: append the note on single views, visible to editors only.
 * ---------------------------------------------------------------------- */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( nqa_archival_note_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id = get_the_ID();
		if ( ! nqa_archival_note_can_view( $id ) ) {
			return $content;
		}
		$note = get_post_meta( $id, NQA_ARCHIVAL_NOTE_META, true );
		if ( ! is_string( $note ) || '' === trim( $note ) ) {
			return $content;
		}
		$content .= '<aside class="nqa-archival-note-box" role="note">'
			. '<span class="nqa-archival-note-box__label">Archival note &middot; staff only</span>'
			. '<div class="nqa-archival-note-box__body">' . wp_kses_post( $note ) . '</div>'
			. '</aside>';
		return $content;
	},
	25
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( nqa_archival_note_types() ) || ! nqa_archival_note_can_view( get_queried_object_id() ) ) {
			return;
		}
		$css = '.nqa-archival-note-box{margin-block-start:2rem;border:2px dashed var(--nqa-violet);'
				. 'background:repeating-linear-gradient(135deg,var(--nqa-cream),var(--nqa-cream) 10px,#f4f1e6 10px,#f4f1e6 20px);'
				. 'padding:1rem 1.2rem}'
			. '.nqa-archival-note-box__label{display:inline-block;margin-bottom:.45rem;'
				. 'font-family:var(--nqa-mono);'
				. 'font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--nqa-violet)}'
			. '.nqa-archival-note-box__body{font-size:.9rem;line-height:1.55;color:#333;font-style:italic}';
		nqa_add_style( $css );
	}
);

/* -------------------------------------------------------------------------
 * Admin: editor metabox to view/edit the note.
 * ---------------------------------------------------------------------- */
add_action(
	'add_meta_boxes',
	function () {
		foreach ( nqa_archival_note_types() as $pt ) {
			add_meta_box(
				'nqa-archival-note-box',
				'Archival note (staff only)',
				'nqa_archival_note_render_metabox',
				$pt,
				'normal',
				'high'
			);
		}
	}
);

function nqa_archival_note_render_metabox( $post ) {
	wp_nonce_field( 'nqa_archival_note_save', 'nqa_archival_note_nonce' );
	$note = (string) get_post_meta( $post->ID, NQA_ARCHIVAL_NOTE_META, true );
	echo '<p style="margin-top:0;color:#555">Internal curatorial note &mdash; sources, verification to-dos, cautions. Shown on the front end <strong>only to logged-in staff</strong>, never to the public. Basic HTML (e.g. <code>&lt;em&gt;</code>, links) is allowed.</p>';
	echo '<textarea name="nqa_archival_note" style="width:100%;min-height:130px;font-size:13px;line-height:1.55" '
		. 'placeholder="Archival note&hellip;">' . esc_textarea( $note ) . '</textarea>';
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['nqa_archival_note_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nqa_archival_note_nonce'] ) ), 'nqa_archival_note_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['nqa_archival_note'] ) ) {
			$note = wp_kses_post( wp_unslash( $_POST['nqa_archival_note'] ) );
			if ( '' === trim( $note ) ) {
				delete_post_meta( $post_id, NQA_ARCHIVAL_NOTE_META );
			} else {
				update_post_meta( $post_id, NQA_ARCHIVAL_NOTE_META, $note );
			}
		}
	}
);
