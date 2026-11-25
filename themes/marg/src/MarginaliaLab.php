<?php
/**
 * Marginalia lab: a private copy of the homepage at /marginalia-lab/ where designers can
 * tune the Marginalia drawing in the desktop header live.
 *
 * - /marginalia-lab/ serves exactly what "/" serves (the front-page magazine archive,
 *   see ProductTemplates::front_page_request()), plus a floating control panel
 *   (assets/marginalia/lab.js + lab.css) that drives the header's Marginalia iframe
 *   through window.marginaliaEmbed (assets/marginalia/index.html).
 * - Only logged-in users who can edit posts (filter tenup_theme_marginalia_lab_capability)
 *   see it; visitors are sent to the login screen. It is noindex and never cached.
 * - Nothing the panel does reaches the live homepage: designers copy the settings and a
 *   developer pastes them into window.MARGINALIA_CONFIG in assets/marginalia/index.html.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Marginalia lab page.
 */
class MarginaliaLab {

	/**
	 * URL path of the lab, relative to the site root.
	 *
	 * @var string
	 */
	const PATH = 'marginalia-lab';

	/**
	 * Query var set by the rewrite rule.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'marg_marginalia_lab';

	/**
	 * Bump when the rewrite rule changes, so it is flushed once on the next request.
	 *
	 * @var string
	 */
	const RULES_VERSION = '1';

	/**
	 * Whether the current main request is the lab.
	 *
	 * @var bool
	 */
	private static $is_lab = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		// Before ProductTemplates::front_page_request() (10), which then serves the archive.
		add_filter( 'request', array( $this, 'request' ), 5 );
		// After ProductTemplates::filter_redirect_canonical() (10), which allows redirects to "/".
		add_filter( 'redirect_canonical', array( $this, 'redirect_canonical' ), 20 );
		add_action( 'template_redirect', array( $this, 'require_login' ), 0 );
		add_filter( 'show_admin_bar', array( $this, 'show_admin_bar' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the current main request is the lab.
	 *
	 * @return bool
	 */
	public static function is_lab() {
		return self::$is_lab;
	}

	/**
	 * Capability needed to open the lab.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability needed to open the Marginalia lab.
		 *
		 * @param string $capability Default 'edit_posts' (Contributors and up).
		 */
		return (string) apply_filters( 'tenup_theme_marginalia_lab_capability', 'edit_posts' );
	}

	/**
	 * Map /marginalia-lab/ to the lab query var, and flush the rules once per RULES_VERSION.
	 *
	 * @return void
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^' . self::PATH . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );

		if ( get_option( 'tenup_theme_marginalia_lab_rules' ) !== self::RULES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'tenup_theme_marginalia_lab_rules', self::RULES_VERSION );
		}
	}

	/**
	 * Allow the lab query var.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Turn a lab request into a bare front-page request, so it renders like "/".
	 *
	 * @param array $query_vars Request query vars.
	 * @return array
	 */
	public function request( $query_vars ) {
		self::$is_lab = false;

		if ( ! is_array( $query_vars ) || empty( $query_vars[ self::QUERY_VAR ] ) ) {
			return $query_vars;
		}

		unset( $query_vars[ self::QUERY_VAR ] );
		self::$is_lab = true;

		return $query_vars;
	}

	/**
	 * Keep the lab at its own URL (the archive's canonical URL is "/").
	 *
	 * @param string|false $redirect_url Redirect target.
	 * @return string|false
	 */
	public function redirect_canonical( $redirect_url ) {
		return self::$is_lab ? false : $redirect_url;
	}

	/**
	 * Send visitors to the login screen and refuse users without the capability.
	 *
	 * @return void
	 */
	public function require_login() {
		if ( ! self::$is_lab ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		if ( ! current_user_can( self::capability() ) ) {
			wp_die(
				esc_html__( 'Your account cannot open the Marginalia lab. Ask an administrator for access.', 'tenup-theme' ),
				esc_html__( 'Marginalia lab', 'tenup-theme' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Hide the admin bar so the lab lays out exactly like the homepage.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function show_admin_bar( $show ) {
		return self::$is_lab ? false : $show;
	}

	/**
	 * Keep the lab out of search engines.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function robots( $robots ) {
		if ( self::$is_lab ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Title the lab tab so it is not mistaken for the live homepage.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function title( $parts ) {
		if ( self::$is_lab ) {
			$parts = array(
				'title' => __( 'Marginalia lab', 'tenup-theme' ),
				'site'  => get_bloginfo( 'name', 'display' ),
			);
		}
		return $parts;
	}

	/**
	 * Load the control panel on the lab only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::$is_lab ) {
			return;
		}

		$dir = TENUP_THEME_PATH . 'assets/marginalia/';
		$url = TENUP_THEME_TEMPLATE_URL . '/assets/marginalia/';

		wp_enqueue_style( 'marginalia-lab', $url . 'lab.css', array(), (string) filemtime( $dir . 'lab.css' ) );
		wp_enqueue_script(
			'marginalia-lab',
			$url . 'lab.js',
			array(),
			(string) filemtime( $dir . 'lab.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'marginalia-lab',
			'MARGINALIA_LAB',
			array(
				'homepageUrl' => home_url( '/' ),
				'configFile'  => 'wp-content/themes/marg/assets/marginalia/index.html',
			)
		);
	}
}
