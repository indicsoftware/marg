<?php
/**
 * Magic Link (Passwordless Email) Authentication Provider
 *
 * Generates a secure token, stores it in a WordPress transient,
 * emails a one-click login link, and verifies the token on click.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme\AuthProviders;

class MagicLinkAuth {

	/**
	 * Token expiry in seconds (15 minutes).
	 */
	const TOKEN_EXPIRY = 900;

	/**
	 * Register the template_redirect hook to catch magic link URLs.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_magic_link_callback' ] );
	}

	/**
	 * Send a magic link email to the given address.
	 *
	 * If no user exists with that email, one is created.
	 *
	 * @param string $email       Recipient email.
	 * @param string $redirect_url URL to redirect to after login.
	 * @return true|\WP_Error
	 */
	public function send( $email, $redirect_url = '' ) {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Please enter a valid email address.' );
		}

		// Find or create user.
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			// Auto-create an account so the magic link still works.
			$user_id = wp_create_user( $email, wp_generate_password( 24, true ), $email );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$user = new \WP_User( $user_id );
			$user->set_role( 'customer' );
		}

		// Generate a secure token.
		$token = wp_generate_password( 32, false );

		// Store token data in a transient.
		set_transient(
			'marg_magic_link_' . $token,
			[
				'user_id'      => $user->ID,
				'email'        => $email,
				'redirect_url' => $redirect_url ?: home_url(),
			],
			self::TOKEN_EXPIRY
		);

		// Build the magic link URL.
		$magic_url = add_query_arg( 'marg_magic_link', $token, home_url() );

		// Send the email.
		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( 'Your login link for %s', $site_name );

		$body  = sprintf( "Hi,\n\n" );
		$body .= sprintf( "Click the link below to log in to %s:\n\n", $site_name );
		$body .= $magic_url . "\n\n";
		$body .= sprintf( "This link expires in %d minutes.\n\n", self::TOKEN_EXPIRY / 60 );
		$body .= "If you didn't request this, you can safely ignore this email.\n";

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		$sent = wp_mail( $email, $subject, $body, $headers );

		if ( ! $sent ) {
			// Clean up the token if email fails.
			delete_transient( 'marg_magic_link_' . $token );
			return new \WP_Error( 'email_failed', 'Could not send the login email. Please try again.' );
		}

		return true;
	}

	/**
	 * Handle the magic link callback when a user clicks the link.
	 *
	 * Hooked to template_redirect — checks for ?marg_magic_link= in the URL.
	 */
	public function handle_magic_link_callback() {
		if ( empty( $_GET['marg_magic_link'] ) ) {
			return;
		}

		$token = sanitize_text_field( $_GET['marg_magic_link'] );
		$data  = get_transient( 'marg_magic_link_' . $token );

		if ( ! $data || empty( $data['user_id'] ) ) {
			// Token is invalid or expired — redirect to home with error.
			wp_safe_redirect( add_query_arg( 'marg_auth_error', 'expired_link', home_url() ) );
			exit;
		}

		$user_id = (int) $data['user_id'];
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			delete_transient( 'marg_magic_link_' . $token );
			wp_safe_redirect( add_query_arg( 'marg_auth_error', 'invalid_user', home_url() ) );
			exit;
		}

		// Delete the transient (single-use token).
		delete_transient( 'marg_magic_link_' . $token );

		// Log the user in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		// Redirect to the stored URL.
		$redirect_url = ! empty( $data['redirect_url'] ) ? $data['redirect_url'] : home_url();
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}
}
