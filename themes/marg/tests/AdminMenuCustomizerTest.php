<?php
/**
 * Regression tests for the theme's admin menu organization.
 *
 * Run with: php tests/AdminMenuCustomizerTest.php
 */

global $menu, $submenu;

$menu = array();
$submenu = array();
$test_capabilities = null;

/**
 * Minimal WordPress function stubs used by AdminMenuCustomizer.
 */
function current_user_can( $capability ) {
	global $test_capabilities;

	return null === $test_capabilities || in_array( $capability, $test_capabilities, true );
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return (string) $value;
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug ) {
	global $submenu;

	$submenu[ $parent_slug ][] = array( $menu_title, $capability, $menu_slug, $page_title );
}

function add_menu_page( $page_title, $menu_title, $capability, $menu_slug ) {
	global $menu;

	$menu[] = array( $menu_title, $capability, $menu_slug, $page_title );
}

function remove_menu_page( $menu_slug ) {
	global $menu;

	foreach ( $menu as $key => $item ) {
		if ( isset( $item[2] ) && $item[2] === $menu_slug ) {
			unset( $menu[ $key ] );
			return $item;
		}
	}

	return false;
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite(
		STDERR,
		$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n"
	);
	exit( 1 );
}

function assert_true( $condition, $message ) {
	assert_same( true, (bool) $condition, $message );
}

require_once dirname( __DIR__ ) . '/src/AdvancedAdminMenuOrganizer.php';
require_once dirname( __DIR__ ) . '/src/AdminMenuCustomizer.php';

$customizer = new \TenUpTheme\AdminMenuCustomizer();
$customizer->create_advanced_menu();
assert_same( 'read', $menu[0][1], 'Advanced should be visible whenever a user can read a moved page.' );

$menu = array(
	2  => array( 'Dashboard', 'read', 'index.php', 'Dashboard' ),
	5  => array( 'Posts', 'edit_posts', 'edit.php', 'Posts' ),
	10 => array( 'Media', 'upload_files', 'upload.php', 'Media' ),
	20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', 'Pages' ),
	21 => array( 'People', 'edit_people', 'edit.php?post_type=person', 'People' ),
	22 => array( 'Products', 'edit_products', 'edit.php?post_type=product', 'Products' ),
	23 => array( 'Orders', 'edit_shop_orders', 'admin.php?page=wc-orders', 'Orders' ),
	24 => array( 'Events', 'edit_events', 'edit.php?post_type=event', 'Events' ),
	25 => array( 'Taxonomies', 'manage_categories', 'taxonomies-menu', 'Taxonomies' ),
	50 => array( 'Novamira', 'manage_options', 'novamira', 'Novamira' ),
	51 => array( 'Custom Reports', 'view_reports', 'custom-reports', 'Custom Reports' ),
	52 => array( 'Marketing', 'manage_woocommerce', 'woocommerce-marketing', 'Marketing' ),
	80 => array( 'Global Settings', 'manage_options', 'global-settings', 'Global Settings' ),
	90 => array( 'Advanced', 'read', 'advanced-menu', 'Advanced' ),
);

$submenu = array(
	'index.php'      => array(
		array( 'Home', 'read', 'index.php', 'Dashboard' ),
		array( 'Updates', 'update_core', 'update-core.php', 'Updates' ),
	),
	'novamira'       => array(
		array( 'Novamira', 'manage_options', 'novamira', 'Novamira' ),
		array( 'Settings', 'manage_options', 'novamira-settings', 'Novamira Settings' ),
	),
	'custom-reports' => array(
		array( 'Reports', 'view_reports', 'custom-reports', 'Reports' ),
		array( 'Scheduled', 'view_reports', 'admin.php?page=custom-reports&view=scheduled', 'Scheduled Reports' ),
	),
	'woocommerce-marketing' => array(
		array( 'Overview', 'manage_woocommerce', 'wc-admin&path=/marketing', 'Marketing Overview' ),
	),
);

$customizer->reorganize_advanced_menu();

$top_level_slugs = array_column( $menu, 2 );

assert_true( in_array( 'index.php', $top_level_slugs, true ), 'Dashboard should remain at the top level.' );
assert_true( in_array( 'edit.php', $top_level_slugs, true ), 'Posts should remain at the top level.' );
assert_true( in_array( 'global-settings', $top_level_slugs, true ), 'Global Settings should remain at the top level.' );
assert_true( in_array( 'advanced-menu', $top_level_slugs, true ), 'Advanced should remain at the top level.' );
assert_true( ! in_array( 'novamira', $top_level_slugs, true ), 'An unlisted plugin menu should move below Advanced.' );
assert_true( ! in_array( 'custom-reports', $top_level_slugs, true ), 'Every unlisted top-level menu should move below Advanced.' );

$advanced_items = isset( $submenu['advanced-menu'] ) ? $submenu['advanced-menu'] : array();
$advanced_links = array_column( $advanced_items, 2 );

assert_true( in_array( 'update-core.php', $advanced_links, true ), 'Updates should remain accessible below Advanced.' );
assert_true( in_array( 'admin.php?page=novamira', $advanced_links, true ), 'A moved plugin parent page should remain directly accessible.' );
assert_true( in_array( 'admin.php?page=novamira-settings', $advanced_links, true ), 'A moved plugin child page should remain directly accessible.' );
assert_true( in_array( 'admin.php?page=custom-reports', $advanced_links, true ), 'An unknown plugin parent page should remain directly accessible.' );
assert_true( in_array( 'admin.php?page=custom-reports&view=scheduled', $advanced_links, true ), 'Existing admin URLs and their query parameters should be preserved.' );
assert_true( in_array( 'admin.php?page=wc-admin&path=/marketing', $advanced_links, true ), 'A container menu should link to its first registered child, as WordPress does.' );
assert_true( ! in_array( 'admin.php?page=woocommerce-marketing', $advanced_links, true ), 'A container menu should not expose its non-screen parent slug.' );
assert_same( 1, count( array_keys( $advanced_links, 'admin.php?page=wc-admin&path=/marketing', true ) ), 'A container menu and its first child should not create duplicate links.' );

$advanced_capabilities = array();
foreach ( $advanced_items as $item ) {
	$advanced_capabilities[ $item[2] ] = $item[1];
}

assert_same( 'view_reports', $advanced_capabilities['admin.php?page=custom-reports'], 'Moved pages should retain their original capability.' );
assert_same( 'view_reports', $advanced_capabilities['admin.php?page=custom-reports&view=scheduled'], 'Moved child pages should retain their original capability.' );

$current_screen = (object) array();
assert_same( 'advanced-menu', $customizer->adjust_parent_file( 'novamira' ), 'Moved parent pages should highlight Advanced.' );
assert_same( 'advanced-menu', $customizer->adjust_parent_file( 'custom-reports' ), 'Unknown moved parent pages should highlight Advanced.' );

$_GET['page'] = 'novamira-settings';
assert_same( 'advanced-menu', $customizer->adjust_parent_file( 'novamira' ), 'Moved child pages should highlight Advanced.' );
assert_same( 'admin.php?page=novamira-settings', $customizer->adjust_submenu_file( 'novamira-settings', 'advanced-menu' ), 'Moved child pages should highlight their direct Advanced link.' );
unset( $_GET['page'] );

$test_capabilities = array( 'read' );
$menu = array(
	2  => array( 'Profile', 'read', 'profile.php', 'Profile' ),
	90 => array( 'Advanced', 'read', 'advanced-menu', 'Advanced' ),
);
$submenu = array(
	'index.php' => array(
		array( 'Updates', 'update_core', 'update-core.php', 'Updates' ),
	),
);

$subscriber_customizer = new \TenUpTheme\AdminMenuCustomizer();
$subscriber_customizer->reorganize_advanced_menu();

assert_same( 'profile.php', $submenu['advanced-menu'][0][2], 'Advanced should open the first page the current user can access.' );
$test_capabilities = null;

fwrite( STDOUT, "AdminMenuCustomizer tests passed.\n" );
