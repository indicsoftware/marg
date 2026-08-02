<?php
/**
 * Apple Sign In Authentication Provider
 *
 * Handles Apple's OAuth flow. Apple uses a POST callback (not GET) and
 * requires a JWT client secret generated from the private key.
 *
 * Requires the following constants in wp-config.php:
 *   MARG_APPLE_CLIENT_ID      — Services ID (e.g., com.example.auth)
 *   MARG_APPLE_TEAM_ID        — Apple Developer Team ID
 *   MARG_APPLE_KEY_ID         — Key ID from Apple Developer Console
 *   MARG_APPLE_PRIVATE_KEY_PATH — Absolute path to the .p8 private key file
 *
 * @package TenUpTheme
 */

namespace TenUpTheme\AuthProviders;

class AppleAuth {

	/**
	 * Apple OAuth endpoints.
	 */
	const AUTH_URL  = 'https://appleid.apple.com/auth/authorize';
	const TOKEN_URL = 'https://appleid.apple.com/auth/token';

	/**
	 * Register the callback handler.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handle_callback' ] );
	}

	/**
	 * Check if Apple auth is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return defined( 'MARG_APPLE_CLIENT_ID' )
			&& defined( 'MARG_APPLE_TEAM_ID' )
			&& defined( 'MARG_APPLE_KEY_ID' )
			&& defined( 'MARG_APPLE_PRIVATE_KEY_PATH' );
	}

	/**
	 * Get the OAuth callback URL.
	 *
	 * @return string
	 */
	private function get_callback_url() {
		return home_url( '/?marg_auth_callback=apple' );
	}

	/**
	 * Build the Apple Sign In authorization URL.
	 *
	 * @param string $redirect_url URL to redirect to after login.
	 * @return string|\WP_Error
	 */
	public function get_auth_url( $redirect_url = '' ) {
		if ( ! self::is_configured() ) {
			return new \WP_Error( 'not_configured', 'Apple authentication is not configured.' );
		}

		// Store redirect URL and CSRF state in a transient.
		$state = wp_generate_password( 32, false );
		set_transient(
			'marg_apple_state_' . $state,
			[
				'redirect_url' => $redirect_url ?: home_url(),
			],
			600 // 10 minutes.
		);

		$params = [
			'client_id'     => MARG_APPLE_CLIENT_ID,
			'redirect_uri'  => $this->get_callback_url(),
			'response_type' => 'code',
			'scope'         => 'name email',
			'state'         => $state,
			'response_mode' => 'form_post', // Apple sends a POST.
		];

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle the OAuth callback from Apple.
	 *
	 * Apple sends a POST to the callback URL (response_mode=form_post).
	 * Hooked to template_redirect — catches ?marg_auth_callback=apple.
	 */
	public function handle_callback() {
		if ( empty( $_GET['marg_auth_callback'] ) || $_GET['marg_auth_callback'] !== 'apple' ) {
			return;
		}

		// Apple sends POST data.
		$code  = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
		$state = isset( $_POST['state'] ) ? sanitize_text_field( $_POST['state'] ) : '';

		if ( ! empty( $_POST['error'] ) ) {
			$this->render_popup_response( false, sanitize_text_field( $_POST['error'] ) );
			return;
		}

		if ( empty( $code ) || empty( $state ) ) {
			$this->render_popup_response( false, 'Missing authorization code.' );
			return;
		}

		// Verify state (CSRF protection).
		$state_data = get_transient( 'marg_apple_state_' . $state );
		if ( ! $state_data ) {
			$this->render_popup_response( false, 'Invalid or expired state. Please try again.' );
			return;
		}
		delete_transient( 'marg_apple_state_' . $state );

		// Apple sends user info only on the FIRST authorization.
		$apple_user = null;
		if ( ! empty( $_POST['user'] ) ) {
			$apple_user = json_decode( stripslashes( $_POST['user'] ), true );
		}

		// Generate client secret JWT.
		$client_secret = $this->generate_client_secret();
		if ( is_wp_error( $client_secret ) ) {
			$this->render_popup_response( false, $client_secret->get_error_message() );
			return;
		}

		// Exchange code for tokens.
		$tokens = $this->exchange_code( $code, $client_secret );
		if ( is_wp_error( $tokens ) ) {
			$this->render_popup_response( false, $tokens->get_error_message() );
			return;
		}

		// Decode the id_token to get user info.
		$id_token_data = $this->decode_id_token( $tokens['id_token'] );
		if ( is_wp_error( $id_token_data ) ) {
			$this->render_popup_response( false, $id_token_data->get_error_message() );
			return;
		}

		// Find or create WordPress user.
		$user = $this->find_or_create_user( $id_token_data, $apple_user );
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
	 * Generate a JWT client secret for Apple.
	 *
	 * Apple requires a JWT signed with your private key as the client_secret.
	 *
	 * @return string|\WP_Error
	 */
	private function generate_client_secret() {
		$key_path = MARG_APPLE_PRIVATE_KEY_PATH;

		if ( ! file_exists( $key_path ) ) {
			return new \WP_Error( 'key_missing', 'Apple private key file not found.' );
		}

		$private_key = file_get_contents( $key_path );

		if ( ! $private_key ) {
			return new \WP_Error( 'key_error', 'Could not read Apple private key.' );
		}

		$header = [
			'alg' => 'ES256',
			'kid' => MARG_APPLE_KEY_ID,
		];

		$now   = time();
		$claims = [
			'iss' => MARG_APPLE_TEAM_ID,
			'iat' => $now,
			'exp' => $now + ( 86400 * 180 ), // 6 months max.
			'aud' => 'https://appleid.apple.com',
			'sub' => MARG_APPLE_CLIENT_ID,
		];

		// Base64url encode.
		$header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
		$claims_encoded  = $this->base64url_encode( wp_json_encode( $claims ) );
		$signing_input   = $header_encoded . '.' . $claims_encoded;

		// Sign with ES256 (ECDSA using P-256 and SHA-256).
		$key = openssl_pkey_get_private( $private_key );
		if ( ! $key ) {
			return new \WP_Error( 'key_error', 'Invalid Apple private key.' );
		}

		$signature = '';
		$signed    = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );

		if ( ! $signed ) {
			return new \WP_Error( 'sign_error', 'Could not sign the client secret.' );
		}

		// Convert DER signature to raw R+S format for ES256.
		$signature = $this->der_to_raw( $signature );

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Exchange the authorization code for tokens.
	 *
	 * @param string $code          Authorization code.
	 * @param string $client_secret JWT client secret.
	 * @return array|\WP_Error
	 */
	private function exchange_code( $code, $client_secret ) {
		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => [
				'client_id'     => MARG_APPLE_CLIENT_ID,
				'client_secret' => $client_secret,
				'code'          => $code,
				'redirect_uri'  => $this->get_callback_url(),
				'grant_type'    => 'authorization_code',
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'token_error', 'Could not connect to Apple.' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['id_token'] ) ) {
			$error = isset( $body['error'] ) ? $body['error'] : 'Token exchange failed.';
			return new \WP_Error( 'token_error', $error );
		}

		return $body;
	}

	/**
	 * Decode Apple's id_token (JWT) to extract user info.
	 *
	 * We only need the payload claims (sub, email). We trust the token
	 * because it came directly from Apple's token endpoint over HTTPS.
	 *
	 * @param string $id_token JWT id_token.
	 * @return array|\WP_Error Claims array.
	 */
	private function decode_id_token( $id_token ) {
		$parts = explode( '.', $id_token );
		if ( count( $parts ) !== 3 ) {
			return new \WP_Error( 'token_error', 'Invalid id_token format.' );
		}

		$payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );

		if ( empty( $payload['sub'] ) ) {
			return new \WP_Error( 'token_error', 'Could not decode id_token.' );
		}

		return $payload;
	}

	/**
	 * Find an existing WP user by Apple ID or email, or create a new one.
	 *
	 * @param array      $id_token_data Decoded id_token claims.
	 * @param array|null $apple_user    User info from first authorization (name, email).
	 * @return \WP_User|\WP_Error
	 */
	private function find_or_create_user( $id_token_data, $apple_user = null ) {
		$apple_id = $id_token_data['sub'];
		$email    = isset( $id_token_data['email'] ) ? sanitize_email( $id_token_data['email'] ) : '';

		// First, try to find by Apple ID.
		$users = get_users( [
			'meta_key'   => 'marg_apple_id',
			'meta_value' => $apple_id,
			'number'     => 1,
		] );

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		// Try to find by email (account linking).
		if ( $email ) {
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				update_user_meta( $user->ID, 'marg_apple_id', $apple_id );
				return $user;
			}
		}

		// Create new user.
		// Apple may use a private relay email (e.g., xyz@privaterelay.appleid.com).
		$user_email = $email ?: $apple_id . '@apple.local';
		$user_id    = wp_create_user( $user_email, wp_generate_password( 24, true ), $user_email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'customer' );

		// Set display name from the user info Apple sends on first auth.
		if ( $apple_user && ! empty( $apple_user['name'] ) ) {
			$first = isset( $apple_user['name']['firstName'] ) ? sanitize_text_field( $apple_user['name']['firstName'] ) : '';
			$last  = isset( $apple_user['name']['lastName'] ) ? sanitize_text_field( $apple_user['name']['lastName'] ) : '';
			$name  = trim( $first . ' ' . $last );

			if ( $name ) {
				wp_update_user( [
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => $first,
					'last_name'    => $last,
				] );
			}
		}

		update_user_meta( $user_id, 'marg_apple_id', $apple_id );

		return new \WP_User( $user_id );
	}

	/**
	 * Render an HTML page that posts a message back to the opener window.
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

	/**
	 * Base64url encode.
	 *
	 * @param string $data Data to encode.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Convert a DER-encoded ECDSA signature to raw R+S format.
	 *
	 * @param string $der DER-encoded signature.
	 * @return string Raw 64-byte signature (R+S).
	 */
	private function der_to_raw( $der ) {
		$pos = 0;
		$pos++; // Skip sequence tag (0x30).
		$pos++; // Skip total length.

		$pos++; // Skip integer tag (0x02) for R.
		$r_len = ord( $der[ $pos ] );
		$pos++;
		$r = substr( $der, $pos, $r_len );
		$pos += $r_len;

		$pos++; // Skip integer tag (0x02) for S.
		$s_len = ord( $der[ $pos ] );
		$pos++;
		$s = substr( $der, $pos, $s_len );

		// Pad/trim to 32 bytes each.
		$r = str_pad( ltrim( $r, "\x00" ), 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( ltrim( $s, "\x00" ), 32, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}
}
