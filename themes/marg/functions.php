<?php
/**
 * WP Theme constants and setup functions
 *
 * @package TenUpTheme
 */

// Useful global constants.
define( 'TENUP_THEME_VERSION', '0.1.0' );
define( 'TENUP_THEME_TEMPLATE_URL', get_template_directory_uri() );
define( 'TENUP_THEME_PATH', get_template_directory() . '/' );
define( 'TENUP_THEME_DIST_PATH', TENUP_THEME_PATH . 'dist/' );
define( 'TENUP_THEME_DIST_URL', TENUP_THEME_TEMPLATE_URL . '/dist/' );
define( 'TENUP_THEME_INC', TENUP_THEME_PATH . 'src/' );
define( 'TENUP_THEME_BLOCK_DIR', TENUP_THEME_PATH . 'blocks/' );
define( 'TENUP_THEME_BLOCK_DIST_DIR', TENUP_THEME_PATH . 'dist/blocks/' );

$is_local_env = in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
$is_local_url = strpos( home_url(), '.test' ) || strpos( home_url(), '.local' );
$is_local     = $is_local_env || $is_local_url;

if ( $is_local && file_exists( __DIR__ . '/dist/fast-refresh.php' ) ) {
	require_once __DIR__ . '/dist/fast-refresh.php';

	if ( function_exists( 'TenUpToolkit\set_dist_url_path' ) ) {
		TenUpToolkit\set_dist_url_path( basename( __DIR__ ), TENUP_THEME_DIST_URL, TENUP_THEME_DIST_PATH );
	}
}

// Require Composer autoloader if it exists.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	require_once __DIR__ . '/template-tags.php';

	$theme_core = new \TenUpTheme\ThemeCore();
	$theme_core->setup();

	// Initialize WooCommerce Memberships integration
	add_action( 'init', function() {
		new \TenUpTheme\WooCommerceMemberships();
		new \TenUpTheme\WooCommerceMembershipsStyles();
		new \TenUpTheme\WooCommerceMembershipsScripts();
		new \TenUpTheme\SecurePDFViewer();
		new \TenUpTheme\SecurePDFEndpoint();
		new \TenUpTheme\ACFConditionalLogic();
	}, 20 );
} else {
	// Basic autoloader for theme classes when vendor is not available
	spl_autoload_register( function ( $class ) {
		$namespace = 'TenUpTheme\\';

		if ( strpos( $class, $namespace ) !== 0 ) {
			return;
		}

		$class = str_replace( $namespace, '', $class );
		$class = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
		$file = __DIR__ . '/src/' . $class . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	});

	require_once __DIR__ . '/template-tags.php';
}

// Initialize Admin Menu Customizer independently of vendor autoloader
require_once __DIR__ . '/src/AdminMenuCustomizer.php';
$admin_menu_customizer = new \TenUpTheme\AdminMenuCustomizer();
$admin_menu_customizer->init();

// Initialize Disable Comments independently of vendor autoloader
require_once __DIR__ . '/src/DisableComments.php';
$disable_comments = new \TenUpTheme\DisableComments();
$disable_comments->init();

// Initialize Disable Product Reviews independently of vendor autoloader
require_once __DIR__ . '/src/DisableProductReviews.php';
$disable_product_reviews = new \TenUpTheme\DisableProductReviews();
$disable_product_reviews->init();

// Initialize Dashboard Documentation independently of vendor autoloader
require_once __DIR__ . '/src/DashboardDocumentation.php';
$dashboard_documentation = new \TenUpTheme\DashboardDocumentation();
$dashboard_documentation->init();

// Initialize Person Post Type functionality independently of vendor autoloader
require_once __DIR__ . '/src/PersonPostType.php';
$person_post_type = new \TenUpTheme\PersonPostType();
$person_post_type->init();

// Initialize ACF Category Filter to hide Uncategorized
require_once __DIR__ . '/src/ACFCategoryFilter.php';
$acf_category_filter = new \TenUpTheme\ACFCategoryFilter();
$acf_category_filter->init();

// Initialize WooCommerce Customizations to remove grouped product type
require_once __DIR__ . '/src/WooCommerceCustomizations.php';
$woocommerce_customizations = new \TenUpTheme\WooCommerceCustomizations();
$woocommerce_customizations->init();

// Temporary: ACF Duplicate Cleanup (remove after cleanup)
// if ( is_admin() ) {
// 	require_once __DIR__ . '/acf-cleanup-duplicates.php';
// }
