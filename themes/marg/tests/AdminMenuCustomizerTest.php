<?php
/**
 * Run with: php themes/marg/tests/AdminMenuCustomizerTest.php
 */

$hooks = array();
$capabilities = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	global $hooks;
	$hooks[$hook][] = $callback[1];
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	add_action( $hook, $callback, $priority, $args );
}

function current_user_can( $capability ) {
	global $capabilities;
	return in_array( $capability, $capabilities, true );
}

function __( $message ) {
	return $message;
}

function wp_die( $message, $code ) {
	throw new RuntimeException( $message, $code );
}

function remove_submenu_page( $parent, $slug ) {}

function get_terms( $args ) {
	return array( (object) array( 'name' => 'Team', 'slug' => 'team' ) );
}

function is_wp_error( $value ) {
	return false;
}

function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug ) {
	global $submenu;
	$submenu[$parent][] = array( $menu_title, $capability, $slug );
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/src/AdminMenuCustomizer.php';
$customizer = new \TenUpTheme\AdminMenuCustomizer();
$customizer->init();
assert_same(
	array( 'create_taxonomies_menu', 'reorganize_taxonomies_menu', 'customize_products_submenu', 'customize_people_submenu', 'reorder_post_type_menus' ),
	$hooks['admin_menu'],
	'Only content-specific menu customizations should remain.'
);
assert_same( false, in_array( 'hide_menu_separators_css', $hooks['admin_head'], true ), 'Native menu layout must not be overridden.' );

$menu = array();
$submenu = array();
$native = array( 'index.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php', 'novamira', 'edit.php?post_type=acf-field-group' );
foreach ( $native as $index => $slug ) {
	$menu[$index + 10] = array( $slug, 'manage_options', $slug );
	$submenu[$slug] = array( array( 'Settings', 'manage_options', $slug . '?settings=1' ) );
}
$submenu['index.php'][] = array( 'Updates', 'update_core', 'update-core.php' );
$original_submenu = $submenu;
$customizer->reorder_post_type_menus();
assert_same( $native, array_column( $menu, 2 ), 'Native and plugin menus must remain at the top level.' );
assert_same( $original_submenu, $submenu, 'Native submenu links and permissions must remain unchanged.' );
foreach ( $native as $slug ) {
	$_GET['page'] = 'novamira-settings';
	assert_same( $slug, $customizer->adjust_parent_file( $slug ), 'Native parent highlighting must be preserved.' );
	assert_same( 'child', $customizer->adjust_submenu_file( 'child', $slug ), 'Native child highlighting must be preserved.' );
}

assert_same( false, in_array( 'grant_custom_post_type_caps', $hooks['init'], true ), 'Do not grant unused People or Events capabilities.' );
$customizer->customize_people_submenu();
assert_same(
	array( array( 'Team', 'edit_posts', 'edit.php?post_type=person&person-type=team' ) ),
	$submenu['edit.php?post_type=person'],
	'People submenu permissions must match the standard post capability.'
);
foreach ( array( 'post_type_68524a7250d7a', 'post_type_683d648e31fe0' ) as $key ) {
	$schema = json_decode( file_get_contents( dirname( __DIR__ ) . '/acf-json/' . $key . '.json' ), true, 512, JSON_THROW_ON_ERROR );
	assert_same( false, $schema['rename_capabilities'], 'People and Events must retain standard post capabilities.' );
	assert_same( 'post', $schema['singular_capability_name'], 'Keep standard singular capability names.' );
	assert_same( 'posts', $schema['plural_capability_name'], 'Keep standard plural capability names.' );
}

$_GET['page'] = 'global-settings';
foreach ( array( 'administrator', 'shop_manager', 'editor', 'author', 'contributor' ) as $role ) {
	$allowed = in_array( $role, array( 'administrator', 'shop_manager' ), true );
	$capabilities = $allowed ? array( 'manage_woocommerce' ) : array( 'edit_posts' );
	$denied = false;
	try {
		$customizer->restrict_global_settings_access();
	} catch ( RuntimeException $error ) {
		assert_same( 403, $error->getCode(), 'Access denial must return 403.' );
		$denied = true;
	}
	assert_same( ! $allowed, $denied, 'Incorrect Global Settings access for ' . $role );
}

fwrite( STDOUT, "AdminMenuCustomizer tests passed: native menus, highlighting, and Global Settings permissions.\n" );
