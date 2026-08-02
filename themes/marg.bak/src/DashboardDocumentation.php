<?php
/**
 * Dashboard Documentation Widget
 *
 * Replaces default dashboard widgets with user documentation.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * DashboardDocumentation class
 */
class DashboardDocumentation {

	/**
	 * Initialize the dashboard customization
	 *
	 * @return void
	 */
	public function init() {
		// Remove default dashboard widgets
		add_action( 'wp_dashboard_setup', array( $this, 'remove_default_widgets' ), 999 );

		// Add custom documentation widget
		add_action( 'wp_dashboard_setup', array( $this, 'add_documentation_widget' ) );
	}

	/**
	 * Remove all default WordPress dashboard widgets
	 *
	 * @return void
	 */
	public function remove_default_widgets() {
		global $wp_meta_boxes;

		// Remove default WordPress widgets
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );          // At a Glance
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );           // Activity
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );          // Quick Draft
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );              // WordPress Events and News
		remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );        // Site Health Status

		// Remove WooCommerce widgets
		remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
		remove_meta_box( 'woocommerce_dashboard_recent_reviews', 'dashboard', 'normal' );
		remove_meta_box( 'wc_admin_dashboard_setup', 'dashboard', 'normal' );

		// Remove other plugin widgets that might exist
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_secondary', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );

		// Remove welcome panel
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	/**
	 * Add custom documentation widget
	 *
	 * @return void
	 */
	public function add_documentation_widget() {
		wp_add_dashboard_widget(
			'user_documentation_widget',
			'User Documentation',
			array( $this, 'render_documentation_widget' )
		);

		// Make the widget full-width by moving it to the top of normal column
		global $wp_meta_boxes;
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$user_doc_widget = array( 'user_documentation_widget' => $normal_dashboard['user_documentation_widget'] );
		$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $user_doc_widget, $normal_dashboard );
	}

	/**
	 * Render the documentation widget content
	 *
	 * @return void
	 */
	public function render_documentation_widget() {
		echo '<div class="user-documentation-content">';
		echo '<p>Placeholder for User Documentation</p>';
		echo '</div>';

		// Add some basic styling
		$this->add_widget_styles();
	}

	/**
	 * Basic markdown parser for documentation
	 *
	 * @param string $content Markdown content.
	 * @return string HTML content.
	 */
	private function parse_markdown( $content ) {
		// Convert headers
		$content = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content );
		$content = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content );
		$content = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $content );

		// Convert bold
		$content = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content );

		// Convert italic
		$content = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $content );

		// Convert inline code
		$content = preg_replace( '/`(.+?)`/', '<code>$1</code>', $content );

		// Convert links
		$content = preg_replace( '/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $content );

		// Convert unordered lists
		$content = preg_replace_callback( '/^\- (.+)$/m', function( $matches ) {
			static $in_list = false;
			$item = '<li>' . $matches[1] . '</li>';

			if ( ! $in_list ) {
				$in_list = true;
				return '<ul>' . $item;
			}
			return $item;
		}, $content );

		// Close any open lists
		$content = preg_replace( '/(<\/li>)(?!\s*<li>)/', '$1</ul>', $content );

		// Convert line breaks to paragraphs
		$paragraphs = explode( "\n\n", $content );
		$html = '';
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( empty( $paragraph ) ) {
				continue;
			}
			// Don't wrap if already has HTML tags
			if ( preg_match( '/^<(h[1-6]|ul|ol|div)/', $paragraph ) ) {
				$html .= $paragraph . "\n";
			} else {
				$html .= '<p>' . $paragraph . '</p>' . "\n";
			}
		}

		return $html;
	}

	/**
	 * Add custom styles for the documentation widget
	 *
	 * @return void
	 */
	private function add_widget_styles() {
		echo '<style>
			/* Make widget span across multiple postbox containers */
			#postbox-container-1 {
				width: 100% !important;
			}

			#postbox-container-2,
			#postbox-container-3,
			#postbox-container-4 {
				display: none !important;
			}

			#user_documentation_widget {
				width: 100% !important;
			}

			#user_documentation_widget .inside {
				padding: 20px;
				font-size: 14px;
				line-height: 1.6;
			}

			.user-documentation-content h1 {
				font-size: 24px;
				margin-bottom: 15px;
				border-bottom: 2px solid #0073aa;
				padding-bottom: 10px;
			}

			.user-documentation-content h2 {
				font-size: 20px;
				margin-top: 25px;
				margin-bottom: 12px;
				color: #0073aa;
			}

			.user-documentation-content h3 {
				font-size: 16px;
				margin-top: 20px;
				margin-bottom: 10px;
				color: #555;
			}

			.user-documentation-content p {
				margin-bottom: 12px;
			}

			.user-documentation-content ul {
				margin-bottom: 15px;
				margin-left: 20px;
			}

			.user-documentation-content li {
				margin-bottom: 8px;
			}

			.user-documentation-content code {
				background: #f5f5f5;
				padding: 2px 6px;
				border-radius: 3px;
				font-family: monospace;
				font-size: 13px;
			}

			.user-documentation-content a {
				color: #0073aa;
				text-decoration: none;
			}

			.user-documentation-content a:hover {
				text-decoration: underline;
			}

			/* Hide welcome panel */
			#welcome-panel {
				display: none !important;
			}
		</style>';
	}
}
