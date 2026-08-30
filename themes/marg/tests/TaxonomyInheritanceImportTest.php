<?php
/**
 * Regression tests for taxonomy inheritance after WP All Import runs.
 *
 * Run with: php tests/TaxonomyInheritanceImportTest.php
 */

$registered_actions = array();
$assigned_terms = array();

class TestWpdb {
	public $prefix = 'wp_';

	public function prepare( $query, $import_id ) {
		return str_replace( '%d', (string) (int) $import_id, $query );
	}

	public function get_col( $query ) {
		if ( false !== strpos( $query, 'import_id = 50' ) ) {
			return array( 101, 999 );
		}

		return array( 999 );
	}
}

class WP_Query {
	public $posts = array();

	public function __construct( $args ) {
		if ( 'ids' === ( $args['fields'] ?? null ) ) {
			$this->posts = array( 201, 202 );
			return;
		}

		$meta_query = $args['meta_query'] ?? array();
		$values     = array_column( array_filter( $meta_query, 'is_array' ), 'value' );
		$values     = implode( ' ', $values );

		if ( false !== strpos( $values, '201' ) ) {
			$this->posts = array( (object) array( 'ID' => 101 ) );
		} elseif ( false !== strpos( $values, '202' ) ) {
			$this->posts = array( (object) array( 'ID' => 102 ) );
		}
	}
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	global $registered_actions;

	$registered_actions[] = array( $hook_name, $callback, $priority, $accepted_args );
}

function get_post_type( $post_id ) {
	return in_array( $post_id, array( 101, 102, 201, 202 ), true ) ? 'product' : 'page';
}

function get_post_status( $post_id ) {
	return in_array( $post_id, array( 101, 102, 201, 202 ), true ) ? 'publish' : false;
}

function has_term( $slug, $taxonomy, $post_id ) {
	if ( 'product_cat' !== $taxonomy ) {
		return false;
	}

	if ( 'articles' === $slug ) {
		return in_array( $post_id, array( 101, 102 ), true );
	}

	return 'magazines' === $slug && in_array( $post_id, array( 201, 202 ), true );
}

function get_field( $field, $post_id ) {
	global $assigned_terms;

	$article_values = array(
		101 => array(
			'time_periods' => array( 11 ),
			'subjects'     => array( 21 ),
			'geography'    => array( 31 ),
			'tags'         => array( 41 ),
		),
		102 => array(
			'time_periods' => array( 12 ),
			'subjects'     => array( 22 ),
			'geography'    => array( 32 ),
			'tags'         => array( 42 ),
		),
	);

	if ( 'taxonomies' === $field ) {
		return $article_values[ $post_id ] ?? false;
	}

	$field_taxonomies = array(
		'field_68e48ee04bd49' => 'time-period',
		'field_68e48f82a6a61' => 'subject',
		'field_68e48fa2a6a62' => 'geographic-area',
		'field_68fc6caa6cfe9' => 'product_tag',
	);

	$taxonomy = $field_taxonomies[ $field ] ?? '';
	return $assigned_terms[ $post_id ][ $taxonomy ] ?? array();
}

function wp_set_object_terms( $post_id, $term_ids, $taxonomy ) {
	global $assigned_terms;

	$assigned_terms[ $post_id ][ $taxonomy ] = $term_ids;
	return $term_ids;
}

function is_wp_error() {
	return false;
}

function wc_delete_product_transients() {}

function update_field() {
	return true;
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

global $wpdb;
$wpdb = new TestWpdb();

require_once dirname( __DIR__ ) . '/src/ProductCategoryResolver.php';
require_once dirname( __DIR__ ) . '/src/TaxonomyInheritance.php';

$inheritance = new \TenUpTheme\TaxonomyInheritance();
$inheritance->init();

$import_hook = null;
foreach ( $registered_actions as $registered_action ) {
	if ( 'pmxi_after_xml_import' === $registered_action[0] ) {
		$import_hook = $registered_action;
		break;
	}
}

assert_same( 20, $import_hook[2] ?? null, 'The completed-import sync should run after WP All Import finishes.' );
assert_same( 2, $import_hook[3] ?? null, 'The completed-import sync should accept WP All Import\'s two hook arguments.' );
assert_same(
	'sync_imported_article_taxonomies_to_magazines',
	is_array( $import_hook[1] ?? null ) ? $import_hook[1][1] : null,
	'WP All Import should invoke the article-to-magazine taxonomy sync.'
);

assert_same(
	true,
	method_exists( $inheritance, 'sync_imported_article_taxonomies_to_magazines' ),
	'The import hook callback should be callable.'
);

$result = $inheritance->sync_imported_article_taxonomies_to_magazines( 50, null );

assert_same(
	array(
		'requested' => 2,
		'updated'   => 2,
		'failed'    => array(),
	),
	$result,
	'An article import should rebuild every Magazine so moved Articles cannot leave stale terms behind.'
);
assert_same( array( 11 ), $assigned_terms[201]['time-period'], 'The first Magazine should inherit its Article time period.' );
assert_same( array( 21 ), $assigned_terms[201]['subject'], 'The first Magazine should inherit its Article subject.' );
assert_same( array( 31 ), $assigned_terms[201]['geographic-area'], 'The first Magazine should inherit its Article geography.' );
assert_same( array( 12 ), $assigned_terms[202]['time-period'], 'The second Magazine should inherit its Article time period.' );

$assigned_terms = array();
$result         = $inheritance->sync_imported_article_taxonomies_to_magazines( 51, null );

assert_same(
	array(
		'requested' => 0,
		'updated'   => 0,
		'failed'    => array(),
	),
	$result,
	'An import without Articles should not rebuild Magazine taxonomies.'
);
assert_same( array(), $assigned_terms, 'An unrelated import should not change Magazine terms.' );

fwrite( STDOUT, "TaxonomyInheritance import tests passed.\n" );
