<?php
/**
 * NQA Member Access — subscriber registration with manual approval.
 *
 * New registrations are held in a `nqa_pending` role (no read access) until
 * an archivist manually promotes them to Subscriber. This gives the archive
 * oversight of who accesses gated fields (active location data, contact info)
 * while keeping the invite open to community members, researchers, and partners.
 *
 * Flow:
 *   1. Visitor registers via standard WP registration form.
 *   2. Role immediately set to `nqa_pending` (no capabilities).
 *   3. User receives a "pending approval" confirmation email.
 *   4. Admin receives a notification with a direct link to approve.
 *   5. Admin changes role to Subscriber in Users admin.
 *   6. User receives an approval email with a login link.
 */

defined( 'ABSPATH' ) || exit;

// ── Pending role ───────────────────────────────────────────────────────────

add_action(
	'init',
	function () {
		if ( ! get_role( 'nqa_pending' ) ) {
			add_role( 'nqa_pending', 'Pending Member', array() );
		}
	}
);

// ── Set new registrants to pending ────────────────────────────────────────

add_action(
	'user_register',
	function ( int $user_id ) {
		$user = new WP_User( $user_id );
		$user->set_role( 'nqa_pending' );
	}
);

// ── Override WP's new-user email to mention pending status ────────────────

add_filter(
	'wp_new_user_notification_email',
	function ( array $email, WP_User $user, string $blogname ) : array {
		$email['subject'] = sprintf( 'Welcome to %s — your account is pending approval', $blogname );
		$email['message'] = sprintf(
			"Hi %s,\r\n\r\n"
			. "Thank you for registering with the %s.\r\n\r\n"
			. "Your account is pending approval by an archivist. This is a brief step "
			. "we take to ensure our community remains safe. You'll receive a separate "
			. "email as soon as your account is approved — usually within a day or two.\r\n\r\n"
			. "Your username: %s\r\n\r\n"
			. "If you have questions, reply to this email or contact us through the archive.\r\n\r\n"
			. "— The Niagara Queer Archive team",
			$user->display_name,
			$blogname,
			$user->user_login
		);
		return $email;
	},
	10,
	3
);

// ── Replace admin new-user notification with an approval prompt ───────────

add_filter(
	'wp_new_user_notification_email_admin',
	function ( array $email, WP_User $user, string $blogname ) : array {
		$approve_url = add_query_arg(
			array( 'user_id' => $user->ID ),
			admin_url( 'user-edit.php' )
		);
		$email['subject'] = sprintf( '[%s] New member registration — approval required', $blogname );
		$email['message'] = sprintf(
			"A new member has registered and is waiting for approval.\r\n\r\n"
			. "Name:     %s\r\n"
			. "Email:    %s\r\n"
			. "Username: %s\r\n"
			. "Joined:   %s\r\n\r\n"
			. "To approve, visit their profile and change the Role to \"Subscriber\":\r\n"
			. "%s\r\n\r\n"
			. "Until approved, the account has no access to gated content.",
			$user->display_name,
			$user->user_email,
			$user->user_login,
			current_time( 'Y-m-d H:i' ),
			$approve_url
		);
		return $email;
	},
	10,
	3
);

// ── Send approval email when role changes from pending → subscriber ────────

add_action(
	'set_user_role',
	function ( int $user_id, string $role, array $old_roles ) : void {
		if ( $role !== 'subscriber' || ! in_array( 'nqa_pending', $old_roles, true ) ) {
			return;
		}
		$user     = get_userdata( $user_id );
		$blogname = get_option( 'blogname' );
		wp_mail(
			$user->user_email,
			sprintf( 'You\'re approved — %s', $blogname ),
			sprintf(
				"Hi %s,\r\n\r\n"
				. "Your %s account has been approved. You can now log in to view "
				. "location details and other member information:\r\n\r\n"
				. "%s\r\n\r\n"
				. "Thank you for joining the archive community. If you have records, "
				. "photographs, or memories to contribute, visit the Contribute page "
				. "once you're logged in.\r\n\r\n"
				. "— The Niagara Queer Archive team",
				$user->display_name,
				$blogname,
				wp_login_url()
			)
		);
	},
	10,
	3
);

// ── Admin Users list: flag pending members ────────────────────────────────

// Add a "Status" column to the Users table.
add_filter(
	'manage_users_columns',
	function ( array $cols ) : array {
		$cols['nqa_status'] = 'NQA Status';
		return $cols;
	}
);

add_filter(
	'manage_users_custom_column',
	function ( string $output, string $col, int $user_id ) : string {
		if ( $col !== 'nqa_status' ) {
			return $output;
		}
		$user = get_userdata( $user_id );
		if ( in_array( 'nqa_pending', (array) $user->roles, true ) ) {
			return '<span style="display:inline-block;padding:.2rem .55rem;background:#503AA8;'
				. 'color:#fff;font-size:.75rem;font-weight:700;letter-spacing:.08em;'
				. 'text-transform:uppercase;">Pending</span>';
		}
		if ( in_array( 'subscriber', (array) $user->roles, true ) ) {
			return '<span style="color:#555;font-size:.8rem;">Approved</span>';
		}
		return '';
	},
	10,
	3
);

// Quick-filter link: "Pending members" in the Users table role tabs.
add_filter(
	'views_users',
	function ( array $views ) : array {
		$count = count(
			get_users( array( 'role' => 'nqa_pending', 'fields' => 'ID', 'number' => 500 ) )
		);
		if ( $count > 0 ) {
			$current = isset( $_GET['role'] ) && $_GET['role'] === 'nqa_pending' ? ' class="current"' : '';
			$url     = add_query_arg( 'role', 'nqa_pending', admin_url( 'users.php' ) );
			$views['nqa_pending'] = sprintf(
				'<a href="%s"%s>Pending approval <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$current,
				$count
			);
		}
		return $views;
	}
);

// ── Redirect pending members who try to access the WP dashboard ───────────

add_action(
	'admin_init',
	function () : void {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( $user && in_array( 'nqa_pending', (array) $user->roles, true ) ) {
			wp_safe_redirect( home_url( '/?nqa_pending=1' ) );
			exit;
		}
	}
);

// ── Front-end notice for pending members ──────────────────────────────────

add_action(
	'wp_footer',
	function () : void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! in_array( 'nqa_pending', (array) $user->roles, true ) ) {
			return;
		}
		echo '<div class="nqa-pending-notice" role="status">'
			. '<strong>Account pending approval.</strong> '
			. 'An archivist will review your registration shortly. '
			. 'You\'ll receive an email once approved.'
			. '</div>';
		echo '<style>.nqa-pending-notice{position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);'
			. 'padding:.65rem 1.25rem;background:var(--nqa-violet,#503AA8);color:#fff;'
			. 'font-size:.875rem;border:2px solid #fff;box-shadow:4px 4px 0 rgba(0,0,0,.25);'
			. 'z-index:9999;max-width:90vw;text-align:center}</style>';
	}
);
