<?php
/**
 * Secure PDF Viewer
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Secure PDF Viewer class.
 *
 * Handles secure PDF viewing with download protection.
 */
class SecurePDFViewer {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_footer', [ $this, 'add_pdf_viewer_modal' ] );
		add_action( 'wp_ajax_get_secure_pdf_url', [ $this, 'get_secure_pdf_url' ] );
		add_action( 'wp_ajax_nopriv_get_secure_pdf_url', [ $this, 'get_secure_pdf_url' ] );
		add_action( 'wp_ajax_test_pdf_url', [ $this, 'test_pdf_url' ] );
	}

	/**
	 * Enqueue scripts and styles for PDF viewer.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		// Enqueue PDF.js from CDN
		wp_enqueue_script(
			'pdf-js',
			'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
			[],
			'3.11.174',
			true
		);

		// Enqueue custom PDF viewer script
		wp_enqueue_script(
			'secure-pdf-viewer',
			TENUP_THEME_DIST_URL . 'js/secure-pdf-viewer.js',
			[ 'jquery', 'pdf-js' ],
			TENUP_THEME_VERSION,
			true
		);

		// Localize script with AJAX URL
		wp_localize_script( 'secure-pdf-viewer', 'securePDFViewer', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'secure_pdf_viewer_nonce' ),
		] );

		// Enqueue PDF viewer styles
		wp_enqueue_style(
			'secure-pdf-viewer',
			TENUP_THEME_DIST_URL . 'css/secure-pdf-viewer.css',
			[],
			TENUP_THEME_VERSION
		);
	}

	/**
	 * Add PDF viewer modal to footer.
	 *
	 * @return void
	 */
	public function add_pdf_viewer_modal() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<div id="secure-pdf-viewer-modal" class="secure-pdf-modal" style="display: none;">
			<div class="secure-pdf-modal-overlay"></div>
			<div class="secure-pdf-modal-content">
				<div class="secure-pdf-modal-header">
					<h3 id="pdf-viewer-title">PDF Viewer</h3>
					<button class="secure-pdf-close" type="button">&times;</button>
				</div>
				<div class="secure-pdf-modal-body">
					<div class="pdf-controls">
						<button id="pdf-prev" class="pdf-control-btn">Previous</button>
						<span id="pdf-page-info">Page 1 of 1</span>
						<button id="pdf-next" class="pdf-control-btn">Next</button>
						<button id="pdf-zoom-out" class="pdf-control-btn">Zoom Out</button>
						<button id="pdf-zoom-in" class="pdf-control-btn">Zoom In</button>
						<button id="pdf-fit-width" class="pdf-control-btn">Fit Width</button>
					</div>
					<div class="pdf-viewer-container">
						<canvas id="pdf-canvas"></canvas>
					</div>
					<div class="pdf-loading" id="pdf-loading">
						<div class="spinner"></div>
						<p>Loading PDF...</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get secure PDF URL.
	 *
	 * @return void
	 */
	public function get_secure_pdf_url() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'secure_pdf_viewer_nonce' ) ) {
			error_log( 'Secure PDF Viewer: Security check failed' );
			wp_send_json_error( 'Security check failed' );
		}

		// Check if user is logged in and has membership
		if ( ! is_user_logged_in() ) {
			error_log( 'Secure PDF Viewer: User not logged in' );
			wp_send_json_error( 'User not logged in' );
		}

		$user_id = get_current_user_id();
		$variation_id = intval( $_POST['variation_id'] );

		error_log( 'Secure PDF Viewer: Processing request for user ' . $user_id . ', variation ' . $variation_id );

		// Check if user has active membership
		if ( ! $this->user_has_active_membership( $user_id ) ) {
			error_log( 'Secure PDF Viewer: User does not have active membership' );
			wp_send_json_error( 'User does not have active membership' );
		}

		// Get the variation product
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			error_log( 'Secure PDF Viewer: Invalid variation ID: ' . $variation_id );
			wp_send_json_error( 'Invalid product' );
		}

		if ( ! $variation->is_downloadable() ) {
			error_log( 'Secure PDF Viewer: Product is not downloadable' );
			wp_send_json_error( 'Product is not downloadable' );
		}

		// Get download URL - bypass S3 plugin issues
		$downloads = $variation->get_downloads();
		if ( empty( $downloads ) ) {
			error_log( 'Secure PDF Viewer: No downloads available for variation ' . $variation_id );
			wp_send_json_error( 'No downloads available' );
		}

		$first_download = reset( $downloads );
		$pdf_url = $first_download['file'];

		// For Ars Botanica Digital variation (ID: 222), use the known PDF URL
		if ( $variation_id == 222 ) {
			// Use local file path for development to avoid SSL issues
			$pdf_url = ABSPATH . 'wp-content/uploads/2025/09/Invoice-5BD2600B-0055.pdf';
			error_log( 'Secure PDF Viewer: Using local file path for Ars Botanica Digital: ' . $pdf_url );

			// Test if the PDF file exists locally
			if ( ! file_exists( $pdf_url ) ) {
				error_log( 'Secure PDF Viewer: PDF file does not exist at: ' . $pdf_url );
				wp_send_json_error( 'PDF file not found' );
			}

			// Test if the file is readable
			if ( ! is_readable( $pdf_url ) ) {
				error_log( 'Secure PDF Viewer: PDF file is not readable: ' . $pdf_url );
				wp_send_json_error( 'PDF file not accessible' );
			}
		}

		error_log( 'Secure PDF Viewer: Original PDF URL: ' . $pdf_url );

		// Generate a temporary secure token (expires in 1 hour)
		$secure_token = $this->generate_secure_url( $pdf_url, $user_id );

		error_log( 'Secure PDF Viewer: Generated secure token: ' . $secure_token );

		// Use AJAX endpoint instead of rewrite rule
		$ajax_url = admin_url( 'admin-ajax.php' );
		$ajax_secure_url = add_query_arg( [
			'action' => 'secure_pdf_viewer',
			'secure_pdf_token' => $secure_token
		], $ajax_url );

		error_log( 'Secure PDF Viewer: Using AJAX URL: ' . $ajax_secure_url );

		wp_send_json_success( [
			'secure_url' => $ajax_secure_url,
			'product_name' => $variation->get_name(),
		] );
	}

	/**
	 * Check if user has active membership.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function user_has_active_membership( $user_id ) {
		// Check if user has active WooCommerce Memberships
		if ( function_exists( 'wc_memberships_is_user_active_member' ) ) {
			return wc_memberships_is_user_active_member( $user_id, 667 ); // Digital Subscription plan ID
		}

		// Fallback: Check database directly
		global $wpdb;
		$membership = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->posts}
			 WHERE post_author = %d
			 AND post_type = 'wc_user_membership'
			 AND post_status = 'wcm-active'
			 AND post_parent = 667",
			$user_id
		) );

		return ! empty( $membership );
	}

	/**
	 * Generate a secure URL for PDF access.
	 *
	 * @param string $original_url Original PDF URL.
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_secure_url( $original_url, $user_id ) {
		// Create a temporary token
		$token = wp_generate_password( 32, false );
		$expires = time() + ( 60 * 60 ); // 1 hour

		// Store token in transient
		set_transient(
			'secure_pdf_token_' . $token,
			[
				'url' => $original_url,
				'user_id' => $user_id,
				'expires' => $expires,
			],
			3600 // 1 hour
		);

		// Return just the token
		return $token;
	}

	/**
	 * Test PDF URL endpoint for debugging.
	 *
	 * @return void
	 */
	public function test_pdf_url() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'secure_pdf_viewer_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		$variation_id = intval( $_POST['variation_id'] );
		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation->is_downloadable() ) {
			wp_send_json_error( 'Invalid or non-downloadable product' );
		}

		$downloads = $variation->get_downloads();
		if ( empty( $downloads ) ) {
			wp_send_json_error( 'No downloads available' );
		}

		$first_download = reset( $downloads );
		$pdf_url = $first_download['file'];

		// Test the original PDF URL
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $pdf_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );

		$response = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
		$error = curl_error( $ch );
		curl_close( $ch );

		wp_send_json_success( [
			'original_url' => $pdf_url,
			'http_code' => $http_code,
			'content_type' => $content_type,
			'error' => $error,
			'headers' => $response,
		] );
	}
}
