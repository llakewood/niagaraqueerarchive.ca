<?php
/**
 * NQA Newsletter — captures homepage newsletter sign-ups.
 *
 * The [nqa_newsletter] shortcode (shortcodes.php) posts here via admin-post.php.
 * We validate + de-duplicate the address, store it as a private `nqa_subscriber`
 * record (admin-reviewable, no public URL), then redirect back to the form with a
 * status flag the shortcode reads to show a confirmation/error message.
 *
 * The privacy policy (page-fields.php §Newsletter) already discloses that we store
 * the subscriber's email address and honour unsubscribe requests.
 */

defined( 'ABSPATH' ) || exit;

// ── Subscriber CPT ───────────────────────────────────────────────────────────

add_action(
	'init',
	function () {
		register_post_type(
			'nqa_subscriber',
			array(
				'label'               => 'Subscribers',
				'labels'              => array(
					'name'               => 'Subscribers',
					'singular_name'      => 'Subscriber',
					'menu_name'          => 'Subscribers',
					'all_items'          => 'All Subscribers',
					'edit_item'          => 'Subscriber',
					'search_items'       => 'Search Subscribers',
					'not_found'          => 'No subscribers yet.',
					'not_found_in_trash' => 'No subscribers in trash.',
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
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-email',
				'menu_position'       => 26,
			)
		);
	}
);

// ── Form handler (admin-post.php) ────────────────────────────────────────────

add_action( 'admin_post_nqa_newsletter_signup', 'nqa_newsletter_handle' );
add_action( 'admin_post_nopriv_nqa_newsletter_signup', 'nqa_newsletter_handle' );

function nqa_newsletter_handle() : void {
	$back = wp_get_referer() ?: home_url( '/' );
	$back = remove_query_arg( 'nqa_newsletter', $back );

	// Nonce.
	if (
		! isset( $_POST['nqa_newsletter_nonce'] )
		|| ! wp_verify_nonce( $_POST['nqa_newsletter_nonce'], 'nqa_newsletter_signup' )
	) {
		nqa_newsletter_redirect( $back, 'invalid' );
	}

	// Honeypot — bots fill hidden fields; humans leave them empty.
	if ( ! empty( $_POST['nqa_website'] ) ) {
		nqa_newsletter_redirect( $back, 'ok' ); // silently drop
	}

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! $email || ! is_email( $email ) ) {
		nqa_newsletter_redirect( $back, 'invalid' );
	}

	// De-duplicate on the stored address.
	$existing = get_posts(
		array(
			'post_type'      => 'nqa_subscriber',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_nqa_email', 'value' => $email ),
			),
		)
	);
	if ( ! empty( $existing ) ) {
		nqa_newsletter_redirect( $back, 'dupe' );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'nqa_subscriber',
			'post_title'  => $email,
			'post_status' => 'private',
		)
	);
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		nqa_newsletter_redirect( $back, 'invalid' );
	}

	update_post_meta( $post_id, '_nqa_email', $email );
	update_post_meta( $post_id, '_nqa_source', 'homepage' );

	nqa_newsletter_redirect( $back, 'ok' );
}

/** Redirect back to the form with a status flag and anchor, then exit. */
function nqa_newsletter_redirect( string $url, string $status ) : void {
	$url = add_query_arg( 'nqa_newsletter', $status, $url ) . '#newsletter';
	wp_safe_redirect( $url );
	exit;
}

// ── Admin list column ────────────────────────────────────────────────────────

add_filter(
	'manage_nqa_subscriber_posts_columns',
	function ( array $cols ) : array {
		unset( $cols['title'] );
		return array_merge(
			array( 'cb' => $cols['cb'] ?? '<input type="checkbox">' ),
			array( 'nqa_email' => 'Email' ),
			array( 'date' => 'Subscribed' )
		);
	}
);

add_action(
	'manage_nqa_subscriber_posts_custom_column',
	function ( string $col, int $post_id ) : void {
		if ( 'nqa_email' === $col ) {
			$email = get_post_meta( $post_id, '_nqa_email', true );
			echo $email ? esc_html( $email ) : '&mdash;';
		}
	},
	10,
	2
);
