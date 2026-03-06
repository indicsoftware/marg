<?php
/**
 * Auth AJAX Handlers
 *
 * Registers and handles all AJAX endpoints for the authentication system.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

use TenUpTheme\AuthProviders\EmailPasswordAuth;
use TenUpTheme\AuthProviders\MagicLinkAuth;
use TenUpTheme\AuthProviders\OTPAuth;
use TenUpTheme\AuthProviders\GoogleAuth;
use TenUpTheme\AuthProviders\AppleAuth;

class AuthAjaxHandlers {

	/**
	 * Initialize AJAX hooks.
	 */
	public function __construct() {
		// Email + Password.
		add_action( 'wp_ajax_nopriv_marg_email_login', [ $this, 'handle_email_login' ] );
		add_action( 'wp_ajax_nopriv_marg_email_register', [ $this, 'handle_email_register' ] );

		// Magic Link.
		add_action( 'wp_ajax_nopriv_marg_magic_link_send', [ $this, 'handle_magic_link_send' ] );

		// OTP (Mobile).
		add_action( 'wp_ajax_nopriv_marg_otp_send', [ $this, 'handle_otp_send' ] );
		add_action( 'wp_ajax_nopriv_marg_otp_verify', [ $this, 'handle_otp_verify' ] );

		// Social OAuth (get redirect URL).
		add_action( 'wp_ajax_nopriv_marg_social_auth_url', [ $this, 'handle_social_auth_url' ] );
	}

	/**
	 * Handle email + password login.
	 */
	public function handle_email_login() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error( [ 'message' => 'Please enter both email and password.' ] );
		}

		// Rate limiting: 10 attempts per IP per hour.
		if ( $this->is_rate_limited( 'login_' . $this->get_client_ip(), 10, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( [ 'message' => 'Too many login attempts. Please try again later.' ] );
		}

		$provider = new EmailPasswordAuth();
		$user     = $provider->login( $email, $password );

		if ( is_wp_error( $user ) ) {
			$this->increment_rate_limit( 'login_' . $this->get_client_ip(), HOUR_IN_SECONDS );
			wp_send_json_error( [ 'message' => 'Invalid email or password.' ] );
		}

		$redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();

		wp_send_json_success( [
			'message'      => 'Login successful!',
			'redirect_url' => $redirect_url,
			'user_name'    => $user->display_name,
		] );
	}

	/**
	 * Handle email + password registration.
	 */
	public function handle_email_register() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( empty( $name ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( [ 'message' => 'Please fill in all fields.' ] );
		}

		// Rate limiting: 5 registrations per IP per hour.
		if ( $this->is_rate_limited( 'register_' . $this->get_client_ip(), 5, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( [ 'message' => 'Too many registration attempts. Please try again later.' ] );
		}

		$provider = new EmailPasswordAuth();
		$user_id  = $provider->register( $name, $email, $password );

		if ( is_wp_error( $user_id ) ) {
			$this->increment_rate_limit( 'register_' . $this->get_client_ip(), HOUR_IN_SECONDS );
			wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
		}

		$redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();

		wp_send_json_success( [
			'message'      => 'Account created successfully!',
			'redirect_url' => $redirect_url,
		] );
	}

	/**
	 * Handle magic link send request.
	 */
	public function handle_magic_link_send() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		if ( empty( $email ) ) {
			wp_send_json_error( [ 'message' => 'Please enter your email address.' ] );
		}

		// Rate limiting: 5 magic link sends per email per hour.
		if ( $this->is_rate_limited( 'magic_' . $email, 5, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ] );
		}

		$redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();

		$provider = new MagicLinkAuth();
		$result   = $provider->send( $email, $redirect_url );

		if ( is_wp_error( $result ) ) {
			$this->increment_rate_limit( 'magic_' . $email, HOUR_IN_SECONDS );
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$this->increment_rate_limit( 'magic_' . $email, HOUR_IN_SECONDS );

		wp_send_json_success( [
			'message' => 'Magic link sent! Check your email.',
			'email'   => $email,
		] );
	}

	/**
	 * Handle OTP send request.
	 */
	public function handle_otp_send() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

		if ( empty( $phone ) ) {
			wp_send_json_error( [ 'message' => 'Please enter your phone number.' ] );
		}

		// Rate limiting: 3 OTP sends per phone per hour.
		if ( $this->is_rate_limited( 'otp_send_' . $phone, 3, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( [ 'message' => 'Too many OTP requests. Please try again later.' ] );
		}

		$provider = new OTPAuth();
		$result   = $provider->send( $phone );

		$this->increment_rate_limit( 'otp_send_' . $phone, HOUR_IN_SECONDS );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message' => 'OTP sent to your phone.',
		] );
	}

	/**
	 * Handle OTP verification request.
	 */
	public function handle_otp_verify() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
		$otp   = isset( $_POST['otp'] ) ? sanitize_text_field( $_POST['otp'] ) : '';

		if ( empty( $phone ) || empty( $otp ) ) {
			wp_send_json_error( [ 'message' => 'Please enter the OTP.' ] );
		}

		// Rate limiting: 5 verify attempts per phone per 5 minutes.
		if ( $this->is_rate_limited( 'otp_verify_' . $phone, 5, 5 * MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( [ 'message' => 'Too many attempts. Please request a new OTP.' ] );
		}

		$provider = new OTPAuth();
		$result   = $provider->verify( $phone, $otp );

		if ( is_wp_error( $result ) ) {
			$this->increment_rate_limit( 'otp_verify_' . $phone, 5 * MINUTE_IN_SECONDS );
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();

		wp_send_json_success( [
			'message'      => 'Login successful!',
			'redirect_url' => $redirect_url,
			'user_name'    => $result['user_name'],
		] );
	}

	/**
	 * Handle social auth URL request (returns the OAuth redirect URL).
	 */
	public function handle_social_auth_url() {
		if ( ! $this->verify_nonce() ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh and try again.' ] );
		}

		$provider     = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();

		if ( $provider === 'google' ) {
			$auth = new GoogleAuth();
			$url  = $auth->get_auth_url( $redirect_url );
		} elseif ( $provider === 'apple' ) {
			$auth = new AppleAuth();
			$url  = $auth->get_auth_url( $redirect_url );
		} else {
			wp_send_json_error( [ 'message' => 'Unknown provider.' ] );
			return;
		}

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( [ 'message' => $url->get_error_message() ] );
		}

		wp_send_json_success( [ 'auth_url' => $url ] );
	}

	/**
	 * Verify the auth nonce.
	 *
	 * @return bool
	 */
	private function verify_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		return wp_verify_nonce( $nonce, 'marg_auth_nonce' );
	}

	/**
	 * Check if an action is rate limited.
	 *
	 * @param string $key     Rate limit key.
	 * @param int    $max     Maximum attempts.
	 * @param int    $window  Time window in seconds.
	 * @return bool
	 */
	private function is_rate_limited( $key, $max, $window ) {
		$transient_key = 'marg_rate_' . md5( $key );
		$attempts      = (int) get_transient( $transient_key );
		return $attempts >= $max;
	}

	/**
	 * Increment the rate limit counter.
	 *
	 * @param string $key    Rate limit key.
	 * @param int    $window Time window in seconds.
	 */
	private function increment_rate_limit( $key, $window ) {
		$transient_key = 'marg_rate_' . md5( $key );
		$attempts      = (int) get_transient( $transient_key );
		set_transient( $transient_key, $attempts + 1, $window );
	}

	/**
	 * Get the client's IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return trim( $ips[0] );
		}
		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}
}
