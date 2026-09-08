<?php
/**
 * Run with: php themes/marg/tests/PersonTypeConditionsTest.php
 */

$hooks = array();
$term_ids = array();

function add_action( $hook, $callback, $priority = 10 ) {}

function add_filter( $hook, $callback, $priority = 10 ) {
	global $hooks;
	$hooks[$hook] = $callback;
}

function get_term_by( $field, $slug, $taxonomy ) {
	global $term_ids;
	assert_same( 'slug', $field, 'Look up terms by slug.' );
	assert_same( 'person-type', $taxonomy, 'Only resolve Person Type terms.' );
	return isset( $term_ids[$slug] ) ? (object) array( 'term_id' => $term_ids[$slug] ) : false;
}

function is_wp_error( $value ) {
	return false;
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/src/PersonPostType.php';
$person = new \TenUpTheme\PersonPostType();
$person->init();
$resolve = $hooks['acf/load_field'];
$schema = json_decode( file_get_contents( dirname( __DIR__ ) . '/acf-json/group_68590e998f6e2.json' ), true, 512, JSON_THROW_ON_ERROR );
$fields = array_column( $schema['fields'], null, 'name' );
assert_same( 'textarea', $fields['bio']['type'], 'Bio must be plain text.' );
assert_same( '', $fields['bio']['new_lines'], 'Bio must not add HTML formatting.' );
assert_same( 'date_time_picker', $fields['bio_last_updated_on']['type'], 'Capture both date and time.' );
assert_same( 0, $fields['designation']['required'], 'Designation remains optional when its Team condition applies.' );
assert_same( 0, $fields['contributor_type']['required'], 'Contributor Type remains optional when visible.' );
$expected_slugs = array(
	'designation' => array( 'team' ),
	'bio_last_updated_on' => array( 'contributor', 'general-editor' ),
	'contributor_type' => array( 'contributor', 'general-editor' ),
);

foreach ( array(
	array( 'team' => 219, 'contributor' => 220, 'general-editor' => 221 ),
	array( 'team' => 901, 'contributor' => 802, 'general-editor' => 703 ),
	array( 'contributor' => 802 ),
	array(),
) as $term_ids ) {
	foreach ( $expected_slugs as $name => $slugs ) {
		$field = $fields[$name];
		$expected = $field;
		foreach ( $slugs as $index => $slug ) {
			assert_same( $slug, $field['conditional_logic'][$index][0]['value'], 'JSON must store portable slugs.' );
			assert_same( '==contains', $field['conditional_logic'][$index][0]['operator'], 'Preserve multi-select matching.' );
			$expected['conditional_logic'][$index][0]['value'] = isset( $term_ids[$slug] ) ? (string) $term_ids[$slug] : $slug;
		}
		$resolved = $resolve( $field );
		assert_same( $expected, $resolved, 'Only change values; preserve OR groups and all other field settings.' );
		assert_same( $resolved, $resolve( $resolved ), 'Repeated field loading must be safe.' );
	}
}

assert_same( $fields['first_name'], $resolve( $fields['first_name'] ), 'Leave unconditional fields unchanged.' );
$unrelated = $fields['designation'];
$unrelated['conditional_logic'][0][0]['field'] = 'another_taxonomy_field';
$term_ids = array( 'team' => 901 );
assert_same( $unrelated, $resolve( $unrelated ), 'Leave conditions for other fields unchanged.' );

echo "PersonTypeConditions tests passed: portable slugs, changed IDs, missing terms, unchanged rule structure and unrelated fields.\n";
