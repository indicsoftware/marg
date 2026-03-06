<?php
/**
 * OTP (Mobile + SMS) Authentication Provider
 *
 * Generates a 6-digit OTP, stores it in a WordPress transient,
 * sends it via MSG91 SMS API, and verifies the code on submission.
 *
 * Requires the following constants in wp-config.php:
 *   MARG_MSG91_AUTH_KEY
 *   MARG_MSG91_TEMPLATE_ID
 *
 * @package TenUpTheme
 */

namespace TenUpTheme\AuthProviders;

class OTPAuth {

	/**
	 * OTP expiry in seconds (5 minutes).
	 */
	const OTP_EXPIRY = 300;

	/**
	 * Send an OTP to the given phone number.
	 *
	 * @param string $phone Phone number in E.164 format (e.g., +919876543210).
	 * @return true|\WP_Error
	 */
	public function send( $phone ) {
		$phone = $this->sanitize_phone( $phone );

		if ( ! $phone ) {
			return new \WP_Error( 'invalid_phone', 'Please enter a valid phone number.' );
		}

		// Generate a 6-digit OTP.
		$otp = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		// Store OTP in a transient (single-use, 5-min expiry).
		$phone_hash = $this->hash_phone( $phone );
		set_transient(
			'marg_otp_' . $phone_hash,
			[
				'otp'   => wp_hash( $otp ),
				'phone' => $phone,
			],
			self::OTP_EXPIRY
		);

		// Send via SMS gateway.
		$sent = $this->send_sms( $phone, $otp );

		if ( is_wp_error( $sent ) ) {
			delete_transient( 'marg_otp_' . $phone_hash );
			return $sent;
		}

		return true;
	}

	/**
	 * Verify an OTP for the given phone number.
	 *
	 * If valid, finds or creates a WordPress user and logs them in.
	 *
	 * @param string $phone Phone number in E.164 format.
	 * @param string $otp   The 6-digit OTP entered by the user.
	 * @return array|\WP_Error User data on success, WP_Error on failure.
	 */
	public function verify( $phone, $otp ) {
		$phone = $this->sanitize_phone( $phone );

		if ( ! $phone ) {
			return new \WP_Error( 'invalid_phone', 'Invalid phone number.' );
		}

		$phone_hash = $this->hash_phone( $phone );
		$data       = get_transient( 'marg_otp_' . $phone_hash );

		if ( ! $data || empty( $data['otp'] ) ) {
			return new \WP_Error( 'expired_otp', 'OTP has expired. Please request a new one.' );
		}

		// Verify the OTP hash.
		if ( ! wp_check_password( $otp, $data['otp'] ) ) {
			return new \WP_Error( 'invalid_otp', 'Invalid OTP. Please try again.' );
		}

		// Delete the transient (single-use).
		delete_transient( 'marg_otp_' . $phone_hash );

		// Find or create user by phone.
		$user = $this->get_user_by_phone( $phone );

		if ( ! $user ) {
			$user = $this->create_user_for_phone( $phone );

			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		// Log the user in.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		return [
			'user_id'   => $user->ID,
			'user_name' => $user->display_name,
		];
	}

	/**
	 * Send an SMS via MSG91.
	 *
	 * @param string $phone Phone number in E.164 format.
	 * @param string $otp   The OTP to send.
	 * @return true|\WP_Error
	 */
	private function send_sms( $phone, $otp ) {
		if ( ! defined( 'MARG_MSG91_AUTH_KEY' ) || ! defined( 'MARG_MSG91_TEMPLATE_ID' ) ) {
			// Fallback: log OTP for development environments.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'MARG OTP for %s: %s', $phone, $otp ) );
				return true;
			}
			return new \WP_Error( 'sms_not_configured', 'SMS service is not configured.' );
		}

		$response = wp_remote_post( 'https://control.msg91.com/api/v5/otp', [
			'headers' => [
				'authkey'      => MARG_MSG91_AUTH_KEY,
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( [
				'template_id' => MARG_MSG91_TEMPLATE_ID,
				'mobile'      => ltrim( $phone, '+' ),
				'otp'         => $otp,
			] ),
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'MSG91 API error: ' . $response->get_error_message() );
			return new \WP_Error( 'sms_failed', 'Could not send the OTP. Please try again.' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || ( isset( $body['type'] ) && $body['type'] === 'error' ) ) {
			$msg = isset( $body['message'] ) ? $body['message'] : 'SMS sending failed';
			error_log( 'MSG91 API error: ' . $msg );
			return new \WP_Error( 'sms_failed', 'Could not send the OTP. Please try again.' );
		}

		return true;
	}

	/**
	 * Find a WordPress user by phone number.
	 *
	 * @param string $phone Phone number.
	 * @return \WP_User|null
	 */
	private function get_user_by_phone( $phone ) {
		$users = get_users( [
			'meta_key'   => 'marg_phone_number',
			'meta_value' => $phone,
			'number'     => 1,
		] );

		return ! empty( $users ) ? $users[0] : null;
	}

	/**
	 * Create a new WordPress user for the given phone number.
	 *
	 * @param string $phone Phone number.
	 * @return \WP_User|\WP_Error
	 */
	private function create_user_for_phone( $phone ) {
		$username = 'phone_' . preg_replace( '/[^0-9]/', '', $phone );
		$email    = $username . '@phone.local';

		$user_id = wp_create_user( $username, wp_generate_password( 24, true ), $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = new \WP_User( $user_id );
		$user->set_role( 'customer' );

		update_user_meta( $user_id, 'marg_phone_number', $phone );

		return $user;
	}

	/**
	 * Sanitize and validate a phone number.
	 *
	 * Accepts formats like +919876543210, 919876543210, 09876543210, 9876543210.
	 * Normalizes to E.164 format with +91 prefix.
	 *
	 * @param string $phone Raw phone input.
	 * @return string|false Normalized phone number or false if invalid.
	 */
	private function sanitize_phone( $phone ) {
		// Strip whitespace, dashes, parentheses.
		$phone = preg_replace( '/[\s\-\(\)]/', '', $phone );

		// If it starts with +, keep as-is for validation.
		// If it starts with 0, replace with +91.
		// If it's 10 digits, prepend +91.
		if ( preg_match( '/^\+91\d{10}$/', $phone ) ) {
			return $phone;
		}
		if ( preg_match( '/^91\d{10}$/', $phone ) ) {
			return '+' . $phone;
		}
		if ( preg_match( '/^0\d{10}$/', $phone ) ) {
			return '+91' . substr( $phone, 1 );
		}
		if ( preg_match( '/^\d{10}$/', $phone ) ) {
			return '+91' . $phone;
		}

		// Accept other international numbers.
		if ( preg_match( '/^\+\d{10,15}$/', $phone ) ) {
			return $phone;
		}

		return false;
	}

	/**
	 * Hash a phone number for use as a transient key.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function hash_phone( $phone ) {
		return md5( $phone );
	}
}
