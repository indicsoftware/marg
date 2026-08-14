<?php
/**
 * Google OAuth Authentication Provider
 *
 * Handles the Google OAuth 2.0 flow: build authorize URL, exchange code
 * for tokens, fetch user profile, find/create WP user, and set auth cookie.
 *
 * Requires the following constants in wp-config.php:
 *   MARG_GOOGLE_CLIENT_ID
 *   MARG_GOOGLE_CLIENT_SECRET
 *
 * @package TenUpTheme
 */

namespace TenUpTheme\AuthProviders;

class GoogleAuth {

	/**
	 * Google OAuth endpoints.
	 */
	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const USER_URL  = 'https://www.googleapis.com/oauth2/v2/userinfo';

	/**
	 * Register the callback handler.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_callback' ] );
	}

	/**
	 * Check if Google auth is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return defined( 'MARG_GOOGLE_CLIENT_ID' ) && defined( 'MARG_GOOGLE_CLIENT_SECRET' );
	}

	/**
	 * Get the OAuth callback URL.
	 *
	 * @return string
	 */
	private function get_callback_url() {
		return home_url( '/?marg_auth_callback=google' );
	}

	/**
	 * Build the Google OAuth authorization URL.
	 *
	 * @param string $redirect_url URL to redirect to after login.
	 * @return string|\WP_Error
	 */
	public function get_auth_url( $redirect_url = '' ) {
		if ( ! self::is_configured() ) {
			return new \WP_Error( 'not_configured', 'Google authentication is not configured.' );
		}

		// Store redirect URL and CSRF state in a transient.
		$state = wp_generate_password( 32, false );
		set_transient(
			'marg_google_state_' . $state,
			[
				'redirect_url' => $redirect_url ?: home_url(),
			],
			600 // 10 minutes.
		);

		$params = [
			'client_id'     => MARG_GOOGLE_CLIENT_ID,
			'redirect_uri'  => $this->get_callback_url(),
			'response_type' => 'code',
			'scope'         => 'openid email profile',
			'state'         => $state,
			'prompt'        => 'select_account',
		];

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle the OAuth callback from Google.
	 *
	 * Hooked to template_redirect — catches ?marg_auth_callback=google in the URL.
	 */
	public function handle_callback() {
		if ( empty( $_GET['marg_auth_callback'] ) || $_GET['marg_auth_callback'] !== 'google' ) {
			return;
		}

		// Check for errors from Google.
		if ( ! empty( $_GET['error'] ) ) {
			$this->render_popup_response( false, sanitize_text_field( $_GET['error'] ) );
			return;
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';

		if ( empty( $code ) || empty( $state ) ) {
			$this->render_popup_response( false, 'Missing authorization code.' );
			return;
		}

		// Verify state (CSRF protection).
		$state_data = get_transient( 'marg_google_state_' . $state );
		if ( ! $state_data ) {
			$this->render_popup_response( false, 'Invalid or expired state. Please try again.' );
			return;
		}
		delete_transient( 'marg_google_state_' . $state );

		// Exchange code for tokens.
		$tokens = $this->exchange_code( $code );
		if ( is_wp_error( $tokens ) ) {
			$this->render_popup_response( false, $tokens->get_error_message() );
			return;
		}

		// Fetch user profile.
		$profile = $this->fetch_profile( $tokens['access_token'] );
		if ( is_wp_error( $profile ) ) {
			$this->render_popup_response( false, $profile->get_error_message() );
			return;
		}

		// Find or create WordPress user.
		$user = $this->find_or_create_user( $profile );
		if ( is_wp_error( $user ) ) {
			$this->render_popup_response( false, $user->get_error_message() );
			return;
		}

		// Log the user in.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect_url = ! empty( $state_data['redirect_url'] ) ? $state_data['redirect_url'] : home_url();

		$this->render_popup_response( true, '', $redirect_url );
	}

	/**
	 * Exchange the authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return array|\WP_Error Token data.
	 */
	private function exchange_code( $code ) {
		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => [
				'code'          => $code,
				'client_id'     => MARG_GOOGLE_CLIENT_ID,
				'client_secret' => MARG_GOOGLE_CLIENT_SECRET,
				'redirect_uri'  => $this->get_callback_url(),
				'grant_type'    => 'authorization_code',
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'token_error', 'Could not connect to Google.' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error = isset( $body['error_description'] ) ? $body['error_description'] : 'Token exchange failed.';
			return new \WP_Error( 'token_error', $error );
		}

		return $body;
	}

	/**
	 * Fetch the user's profile from Google.
	 *
	 * @param string $access_token OAuth access token.
	 * @return array|\WP_Error Profile data with 'id', 'email', 'name', 'picture'.
	 */
	private function fetch_profile( $access_token ) {
		$response = wp_remote_get( self::USER_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'profile_error', 'Could not fetch Google profile.' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['email'] ) ) {
			return new \WP_Error( 'profile_error', 'Could not retrieve email from Google.' );
		}

		return $body;
	}

	/**
	 * Find an existing WP user by Google ID or email, or create a new one.
	 *
	 * @param array $profile Google profile data.
	 * @return \WP_User|\WP_Error
	 */
	private function find_or_create_user( $profile ) {
		$google_id = $profile['id'];
		$email     = sanitize_email( $profile['email'] );
		$name      = isset( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : '';

		// First, try to find by Google ID.
		$users = get_users( [
			'meta_key'   => 'marg_google_id',
			'meta_value' => $google_id,
			'number'     => 1,
		] );

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		// Try to find by email (account linking).
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			// Link Google ID to existing account.
			update_user_meta( $user->ID, 'marg_google_id', $google_id );
			return $user;
		}

		// Create new user.
		$user_id = wp_create_user( $email, wp_generate_password( 24, true ), $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'customer' );

		// Set display name.
		if ( $name ) {
			wp_update_user( [
				'ID'           => $user_id,
				'display_name' => $name,
				'first_name'   => explode( ' ', $name )[0],
			] );
		}

		// Store Google ID.
		update_user_meta( $user_id, 'marg_google_id', $google_id );

		return new \WP_User( $user_id );
	}

	/**
	 * Render an HTML page that posts a message back to the opener window and closes itself.
	 *
	 * @param bool   $success      Whether auth succeeded.
	 * @param string $error        Error message (if any).
	 * @param string $redirect_url Redirect URL on success.
	 */
	private function render_popup_response( $success, $error = '', $redirect_url = '' ) {
		$data = [
			'type'         => 'marg-auth-callback',
			'success'      => $success,
			'error'        => $error,
			'redirect_url' => $redirect_url,
		];
		?>
		<!DOCTYPE html>
		<html>
		<head><title><?php esc_html_e( 'Authenticating...', 'tenup-theme' ); ?></title></head>
		<body>
		<p><?php echo $success ? esc_html__( 'Login successful! This window will close.', 'tenup-theme' ) : esc_html( $error ); ?></p>
		<script>
			if (window.opener) {
				window.opener.postMessage(<?php echo wp_json_encode( $data ); ?>, <?php echo wp_json_encode( home_url() ); ?>);
				window.close();
			} else {
				window.location.href = <?php echo wp_json_encode( $redirect_url ?: home_url() ); ?>;
			}
		</script>
		</body>
		</html>
		<?php
		exit;
	}
}
