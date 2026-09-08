<?php
/**
 * Run with: php themes/marg/tests/ACFSchemaPolicyTest.php
 */

$hooks = array();
$environment = 'production';

function wp_get_environment_type() {
	global $environment;
	return $environment;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	global $hooks;
	$hooks[$hook] = $callback;
}

function __return_false() {
	return false;
}

function __( $message, $domain ) {
	return $message;
}

class WP_Error {
	public function __construct( public $code, public $message = '', public $data = array() ) {}
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/src/ACFSchemaPolicy.php';
$policy = new \TenUpTheme\ACFSchemaPolicy();

foreach ( array( 'local', 'development', 'staging' ) as $environment ) {
	$policy->init();
	assert_same( array(), $hooks, 'Schema editing must remain available outside production.' );
}

$environment = 'production';
$policy->init();
assert_same(
	array( 'acf/settings/show_admin', 'acf/settings/capability', 'wp_ability_permission_result' ),
	array_keys( $hooks ),
	'The policy must only affect schema administration and ability permissions, not value loading or saving.'
);
assert_same( false, $hooks['acf/settings/show_admin'](), 'Hide the production schema menu.' );
assert_same( 'do_not_allow', $hooks['acf/settings/capability'](), 'Deny direct schema administration even to administrators.' );

foreach ( array( 'create', 'edit', 'delete' ) as $operation ) {
	foreach ( array( 'fieldgroup', 'post-type', 'taxonomy', 'options-page' ) as $type ) {
		$result = $hooks['wp_ability_permission_result']( true, "novamira/acf-$operation-$type" );
		assert_same( WP_Error::class, get_class( $result ), 'All 12 schema-write abilities must be denied.' );
		assert_same( 'marg_acf_schema_locked', $result->code, 'Return an actionable schema-lock error.' );
		assert_same( 403, $result->data['status'], 'Schema writes must return forbidden.' );
	}
}

$denied = new WP_Error( 'existing_denial' );
foreach ( array( 'novamira/acf-read-values', 'novamira/acf-write-values', 'novamira/acf-get-fieldgroup-schema', 'novamira/acf-list-post-types', 'novamira/acf-list-taxonomies', 'novamira/acf-get-options-page', 'novamira/update-post' ) as $ability ) {
	foreach ( array( true, false, $denied ) as $permission ) {
		assert_same( $permission, $hooks['wp_ability_permission_result']( $permission, $ability ), 'Preserve existing permissions for content and schema reads.' );
	}
}

$schemas = array();
foreach ( glob( dirname( __DIR__ ) . '/acf-json/*.json' ) as $file ) {
	$schema = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
	assert_same( basename( $file, '.json' ), $schema['key'], 'Preserve ACF keys as filenames.' );
	$schemas[$schema['key']] = $schema;
}
assert_same( 13, count( $schemas ), 'Track 4 field groups, 2 CPTs, 6 taxonomies and the options page.' );
assert_same( 'event', $schemas['post_type_683d648e31fe0']['post_type'], 'Preserve the event slug.' );
assert_same( 'person', $schemas['post_type_68524a7250d7a']['post_type'], 'Preserve the person slug.' );
assert_same( 'manage_woocommerce', $schemas['ui_options_page_68eda9e90623b']['capability'], 'Administrators and Shop Managers must retain Global Settings value access.' );

echo "ACFSchemaPolicy tests passed (4 environments, 12 schema-write denials, unchanged content permissions, 13 JSON definitions).\n";
