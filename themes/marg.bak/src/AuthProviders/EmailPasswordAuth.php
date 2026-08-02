<?php
/**
 * Email + Password Authentication Provider
 *
 * @package TenUpTheme
 */

namespace TenUpTheme\AuthProviders;

class EmailPasswordAuth {

	/**
	 * Log in a user with email and password.
	 *
	 * @param string $email    User email.
	 * @param string $password User password.
	 * @return \WP_User|\WP_Error
	 */
	public function login( $email, $password ) {
		$credentials = [
			'user_login'    => sanitize_email( $email ),
			'user_password' => $password,
			'remember'      => true,
		];

		$user = wp_signon( $credentials, is_ssl() );

		return $user;
	}

	/**
	 * Register a new user with email and password.
	 *
	 * @param string $name     Display name.
	 * @param string $email    User email.
	 * @param string $password User password.
	 * @return int|\WP_Error User ID on success, WP_Error on failure.
	 */
	public function register( $name, $email, $password ) {
		$email = sanitize_email( $email );
		$name  = sanitize_text_field( $name );

		// Validate email.
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Please enter a valid email address.' );
		}

		// Check if email already exists.
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'email_exists', 'An account with this email already exists. Try logging in.' );
		}

		// Validate password strength.
		if ( strlen( $password ) < 8 ) {
			return new \WP_Error( 'weak_password', 'Password must be at least 8 characters.' );
		}

		// Create user.
		$user_id = wp_create_user( $email, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Set display name.
		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => explode( ' ', $name )[0],
			'last_name'    => implode( ' ', array_slice( explode( ' ', $name ), 1 ) ),
		] );

		// Set role to customer (WooCommerce).
		$user = new \WP_User( $user_id );
		$user->set_role( 'customer' );

		// Auto-login the user.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		// Send notification email.
		wp_new_user_notification( $user_id, null, 'user' );

		return $user_id;
	}
}
