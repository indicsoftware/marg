<?php
/**
 * Disable Comments
 *
 * Completely disables and removes all comments functionality from WordPress.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * DisableComments class
 */
class DisableComments {

	/**
	 * Initialize the comments disabler
	 *
	 * @return void
	 */
	public function init() {
		// Admin-related hooks
		add_action( 'admin_init', array( $this, 'disable_comments_admin' ) );
		add_action( 'admin_menu', array( $this, 'remove_comments_menu' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'remove_comments_admin_bar' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widget' ) );

		// Frontend hooks
		add_action( 'init', array( $this, 'remove_comment_support' ) );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		// Hide existing comments
		add_filter( 'comments_number', array( $this, 'hide_comments_number' ) );
		add_filter( 'get_comments_number', '__return_zero' );

		// Remove comments feed
		add_action( 'template_redirect', array( $this, 'disable_comments_feed' ) );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		// Remove comments from REST API
		add_filter( 'rest_endpoints', array( $this, 'remove_comments_from_rest_api' ) );

		// Remove Recent Comments widget
		add_action( 'widgets_init', array( $this, 'remove_recent_comments_widget' ) );

		// Remove comment-related meta boxes
		add_action( 'add_meta_boxes', array( $this, 'remove_comment_meta_boxes' ), 999 );

		// Disable X-Pingback header
		add_filter( 'wp_headers', array( $this, 'remove_x_pingback' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'remove_xmlrpc_pingback' ) );

		// Remove comment-reply script
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_comment_reply_script' ), 999 );
	}

	/**
	 * Disable comments in admin
	 *
	 * @return void
	 */
	public function disable_comments_admin() {
		// Redirect any user trying to access comments page
		global $pagenow;

		if ( $pagenow === 'edit-comments.php' || $pagenow === 'options-discussion.php' ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// Remove comments metabox from dashboard
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );

		// Disable support for comments and trackbacks in post types
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Remove comments menu from admin
	 *
	 * @return void
	 */
	public function remove_comments_menu() {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * Remove comments from admin bar
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar object.
	 * @return void
	 */
	public function remove_comments_admin_bar( $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'new-comment' );
	}

	/**
	 * Remove dashboard widget
	 *
	 * @return void
	 */
	public function remove_dashboard_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Remove comment support from all post types
	 *
	 * @return void
	 */
	public function remove_comment_support() {
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}

		// Close comments on frontend
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
	}

	/**
	 * Hide comments number
	 *
	 * @param string $count The comments count.
	 * @return string
	 */
	public function hide_comments_number( $count ) {
		return '';
	}

	/**
	 * Disable comments RSS feed
	 *
	 * @return void
	 */
	public function disable_comments_feed() {
		if ( is_comment_feed() ) {
			wp_die( esc_html__( 'Comments are disabled on this site.', 'tenup-theme' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Remove comments from REST API
	 *
	 * @param array $endpoints The REST API endpoints.
	 * @return array
	 */
	public function remove_comments_from_rest_api( $endpoints ) {
		if ( isset( $endpoints['/wp/v2/comments'] ) ) {
			unset( $endpoints['/wp/v2/comments'] );
		}
		if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] ) ) {
			unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	/**
	 * Remove Recent Comments widget
	 *
	 * @return void
	 */
	public function remove_recent_comments_widget() {
		unregister_widget( 'WP_Widget_Recent_Comments' );

		// Remove inline styles used by Recent Comments widget
		global $wp_widget_factory;
		if ( isset( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] ) ) {
			remove_action( 'wp_head', array( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ) );
		}
	}

	/**
	 * Remove comment meta boxes
	 *
	 * @return void
	 */
	public function remove_comment_meta_boxes() {
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
			remove_meta_box( 'commentsdiv', $post_type, 'normal' );
		}
	}

	/**
	 * Remove X-Pingback header
	 *
	 * @param array $headers The headers array.
	 * @return array
	 */
	public function remove_x_pingback( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Remove pingback from XML-RPC methods
	 *
	 * @param array $methods The XML-RPC methods.
	 * @return array
	 */
	public function remove_xmlrpc_pingback( $methods ) {
		if ( isset( $methods['pingback.ping'] ) ) {
			unset( $methods['pingback.ping'] );
		}
		if ( isset( $methods['pingback.extensions.getPingbacks'] ) ) {
			unset( $methods['pingback.extensions.getPingbacks'] );
		}
		return $methods;
	}

	/**
	 * Remove comment reply script
	 *
	 * @return void
	 */
	public function remove_comment_reply_script() {
		wp_deregister_script( 'comment-reply' );
	}
}