<?php
/**
 * Secure PDF Endpoint
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Secure PDF Endpoint class.
 *
 * Handles secure PDF serving with token validation.
 */
class SecurePDFEndpoint {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		error_log( 'Secure PDF Endpoint: Constructor called' );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'handle_secure_pdf_request' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

		// Also add AJAX endpoint as backup
		add_action( 'wp_ajax_secure_pdf_viewer', [ $this, 'serve_secure_pdf' ] );
		add_action( 'wp_ajax_nopriv_secure_pdf_viewer', [ $this, 'serve_secure_pdf' ] );
	}

	/**
	 * Add rewrite rules for secure PDF endpoint.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		error_log( 'Secure PDF Endpoint: Adding rewrite rules' );
		add_rewrite_rule(
			'^secure-pdf-viewer/?$',
			'index.php?secure_pdf_viewer=1',
			'top'
		);
		error_log( 'Secure PDF Endpoint: Rewrite rule added' );
	}

	/**
	 * Add query vars for secure PDF endpoint.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'secure_pdf_viewer';
		return $vars;
	}

	/**
	 * Handle secure PDF requests.
	 *
	 * @return void
	 */
	public function handle_secure_pdf_request() {
		error_log( 'Secure PDF Endpoint: handle_secure_pdf_request called' );
		error_log( 'Secure PDF Endpoint: secure_pdf_viewer query var: ' . get_query_var( 'secure_pdf_viewer' ) );
		error_log( 'Secure PDF Endpoint: secure_pdf_token query var: ' . get_query_var( 'secure_pdf_token' ) );

		if ( get_query_var( 'secure_pdf_viewer' ) ) {
			error_log( 'Secure PDF Endpoint: Serving secure PDF' );
			$this->serve_secure_pdf();
			exit;
		}
	}

	/**
	 * Serve secure PDF with token validation.
	 *
	 * @return void
	 */
	public function serve_secure_pdf() {
		$token = sanitize_text_field( $_GET['secure_pdf_token'] ?? '' );

		error_log( 'Secure PDF Endpoint: Processing request with token: ' . $token );

		if ( empty( $token ) ) {
			error_log( 'Secure PDF Endpoint: Empty token provided' );
			$this->send_error_response( 'Invalid token', 400 );
		}

		// Get token data from transient
		$token_data = get_transient( 'secure_pdf_token_' . $token );

		if ( false === $token_data ) {
			error_log( 'Secure PDF Endpoint: Token not found or expired: ' . $token );
			$this->send_error_response( 'Token expired or invalid', 401 );
		}

		error_log( 'Secure PDF Endpoint: Token data found, URL: ' . $token_data['url'] );

		// Check if token has expired
		if ( time() > $token_data['expires'] ) {
			delete_transient( 'secure_pdf_token_' . $token );
			$this->send_error_response( 'Token expired', 401 );
		}

		// Verify user is still logged in and has membership
		if ( ! is_user_logged_in() || get_current_user_id() !== $token_data['user_id'] ) {
			$this->send_error_response( 'Unauthorized access', 403 );
		}

		// Disable S3 plugin filters to avoid database errors
		remove_all_filters( 'woocommerce_file_download_path' );
		remove_all_filters( 'wp_get_attachment_url' );

		// Serve the PDF with security headers
		$this->serve_pdf_with_headers( $token_data['url'] );

		// Clean up token after use
		delete_transient( 'secure_pdf_token_' . $token );
	}

	/**
	 * Serve PDF with security headers.
	 *
	 * @param string $pdf_url PDF URL to serve.
	 * @return void
	 */
	private function serve_pdf_with_headers( $pdf_url ) {
		// Handle both local file paths and remote URLs
		if ( strpos( $pdf_url, 'http' ) === 0 ) {
			// Remote URL - use cURL for better error handling
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $pdf_url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
			curl_setopt( $ch, CURLOPT_USERAGENT, 'WordPress Secure PDF Viewer' );
			curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );

			$pdf_content = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$error = curl_error( $ch );
			curl_close( $ch );

			error_log( 'PDF fetch - HTTP Code: ' . $http_code . ', Content-Type: ' . $content_type );

			if ( false === $pdf_content || $http_code !== 200 ) {
				error_log( 'PDF fetch error: ' . $error . ' (HTTP: ' . $http_code . ')' );
				$this->send_error_response( 'PDF not accessible', 404 );
			}
		} else {
			// Local file path
			error_log( 'Serving local PDF file: ' . $pdf_url );

			if ( ! file_exists( $pdf_url ) ) {
				error_log( 'Local PDF file does not exist: ' . $pdf_url );
				$this->send_error_response( 'PDF file not found', 404 );
			}

			if ( ! is_readable( $pdf_url ) ) {
				error_log( 'Local PDF file is not readable: ' . $pdf_url );
				$this->send_error_response( 'PDF file not accessible', 403 );
			}

			$pdf_content = file_get_contents( $pdf_url );

			if ( false === $pdf_content ) {
				error_log( 'Failed to read local PDF file: ' . $pdf_url );
				$this->send_error_response( 'Failed to read PDF file', 500 );
			}
		}

		// Validate PDF content
		if ( empty( $pdf_content ) ) {
			error_log( 'Empty PDF content received' );
			$this->send_error_response( 'Empty PDF content', 400 );
		}

		// Check PDF header
		$pdf_header = substr( $pdf_content, 0, 4 );
		if ( $pdf_header !== '%PDF' ) {
			error_log( 'Invalid PDF header: ' . bin2hex( $pdf_header ) . ' (expected: 25504446)' );
			error_log( 'PDF content preview: ' . substr( $pdf_content, 0, 100 ) );
			$this->send_error_response( 'Invalid PDF format', 400 );
		}

		error_log( 'PDF content size: ' . strlen( $pdf_content ) . ' bytes' );

		// Clear any previous output
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set security headers
		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $pdf_content ) );
		header( 'Content-Disposition: inline; filename="document.pdf"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-XSS-Protection: 1; mode=block' );

		// Prevent right-click and other browser features
		header( 'X-Permitted-Cross-Domain-Policies: none' );

		// Add CORS headers for PDF.js
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET' );
		header( 'Access-Control-Allow-Headers: Range' );

		// Output the PDF content
		echo $pdf_content;
		exit;
	}

	/**
	 * Send error response.
	 *
	 * @param string $message Error message.
	 * @param int $status_code HTTP status code.
	 * @return void
	 */
	private function send_error_response( $message, $status_code ) {
		http_response_code( $status_code );
		header( 'Content-Type: application/json' );
		echo json_encode( [ 'error' => $message ] );
		exit;
	}
}
