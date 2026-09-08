<?php
/** Run with: php themes/marg/tests/ProductEditorialFieldsTest.php */

$hooks = array();
$meta = array();
$errors = array();
$options = array( 'print' => '1234-5678', 'digital' => '8765-4321' );
$saved = array();

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	global $hooks;
	$hooks[$hook] = $callback;
}
function __( $text, $domain = '' ) { return $text; }
function get_post_meta( $id, $key, $single = true ) { global $meta; return $meta[$id][$key] ?? ''; }
function metadata_exists( $type, $id, $key ) { global $meta; return array_key_exists( $key, $meta[$id] ?? array() ); }
function get_field( $key, $id ) {
	global $options, $magazine;
	return 'option' === $id ? $options : $magazine;
}
function update_field( $key, $value, $id ) { global $saved; $saved[$key] = $value; }
function get_post_type( $id ) { return 'product'; }
function get_term_by( $field, $slug, $taxonomy ) { return (object) array( 'term_id' => 'magazines' === $slug ? 10 : 20 ); }
function has_term( $slug, $taxonomy, $id ) { return ( 'magazines' === $slug && 2 === $id ) || ( 'articles' === $slug && 1 === $id ); }
function is_wp_error( $value ) { return false; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function get_post_thumbnail_id( $id ) { return 8; }
function wp_get_attachment_metadata( $id ) { return array( 'width' => 8 === $id ? 1000 : 500 ); }
function wp_attachment_is_image( $id ) { return $id > 0; }
function acf_add_validation_error( $input, $message ) { global $errors; $errors[$input] = $message; }
function acf_get_form_data( $key ) { return 1; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function same( $expected, $actual ) {
	if ( $expected !== $actual ) { throw new RuntimeException( 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ); }
}

require_once dirname( __DIR__ ) . '/src/ProductCategoryResolver.php';
require_once dirname( __DIR__ ) . '/src/TaxonomyInheritance.php';
require_once dirname( __DIR__ ) . '/src/ProductEditorialFields.php';
require_once dirname( __DIR__ ) . '/src/WooCommerceCustomizations.php';
$fields = new \TenUpTheme\ProductEditorialFields();
$fields->init();
$load = fn( $key, $value = null ) => $hooks['acf/load_value/key=' . $key]( $value, 1, array( 'key' => $key ) );

foreach ( array( 'pp. 10-11' => array( 10, 11 ), 'p. 12' => array( 12, 12 ), '112–125' => array( 112, 125 ), '45' => array( 45, 45 ), '12-10' => array(), 'iv–vi' => array(), '10, 12' => array() ) as $text => $expected ) {
	same( $expected, $fields::legacy_pages( $text ) );
}
$meta[1]['article_details_page_number'] = 'pp. 10-11';
same( 10, $load( 'field_article_page_from' ) );
same( 11, $load( 'field_article_page_to' ) );
same( 10, $load( 'field_article_page_from', '' ) );
$meta[1]['article_details_page_from'] = '';
same( '', $load( 'field_article_page_from', '' ) );
unset( $meta[1]['article_details_page_from'] );
same( 25, $load( 'field_article_page_to', 25 ) );
$meta[1]['article_details_page_number'] = 'iv–vi';
same( null, $load( 'field_article_page_from' ) );
same( true, str_contains( $fields->show_legacy_pages( array( 'instructions' => '' ) )['instructions'], 'iv–vi' ) );
same( '1234-5678', $load( 'field_print_issn' ) );
same( 'custom-issn', $load( 'field_print_issn', 'custom-issn' ) );
same( '8765-4321', $load( 'field_digital_issn' ) );
same( 'ARTICLE-1', $load( 'field_article_sku' ) );
$meta[1]['_sku'] = 'MARG-70';
same( 'MARG-70-PRINT', $load( 'field_print_sku' ) );
same( 'MARG-70-DIGITAL', $load( 'field_digital_sku' ) );
same( 'imported-sku', $load( 'field_article_sku', 'imported-sku' ) );

$meta[1]['article_details_parent_publication'] = array( 2 );
$meta[2]['magazine_details_open_access'] = '0';
same( 0, $load( 'field_article_open_access', 'Yes' ) );
same( 0, $hooks['acf/update_value/key=field_article_open_access']( 1, 1, array( 'key' => 'field_article_open_access' ) ) );
$meta[2]['magazine_details_open_access'] = '1';
same( 1, $load( 'field_article_open_access', 'No' ) );
$inheritance = new \TenUpTheme\TaxonomyInheritance();
$inheritance->sync_article_open_access( 1 );
same( 1, $saved['field_article_open_access'] );
$meta[1]['article_details_parent_publication'] = array();
same( 0, $load( 'field_article_open_access', 1 ) );

same( true, $fields->validate_pages( true, array() ) );
same( true, $fields->validate_pages( true, array( 'field_article_page_from' => '12', 'field_article_page_to' => '12' ) ) );
foreach ( array( array( 'field_article_page_from' => '12' ), array( 'field_article_page_to' => '12' ), array( 'field_article_page_from' => '12', 'field_article_page_to' => '10' ) ) as $value ) {
	same( true, is_string( $fields->validate_pages( true, $value ) ) );
}
same( 'prior error', $fields->validate_pages( 'prior error', array() ) );
same( true, $fields->validate_dates( true, array( 'field_689d970ca7426' => '20260901' ) ) );
same( true, is_string( $fields->validate_dates( true, array( 'field_689d970ca7426' => '20260901', 'field_689d970ca7427' => '20260801' ) ) ) );
same( true, $fields->validate_integer( true, '0' ) );
same( true, $fields->validate_integer( true, '' ) );
same( true, is_string( $fields->validate_integer( true, '1.5' ) ) );

$_POST = array( 'acf' => array( 'field_68ac2f304ad26' => '10' ), 'post_ID' => 1, 'post_title' => 'Magazine', '_thumbnail_id' => '8' );
$fields->validate_native_product_fields();
same( array(), $errors );
$_POST['_thumbnail_id'] = 9;
$fields->validate_native_product_fields();
same( true, isset( $errors['_thumbnail_id'] ) );
$errors = array();
$_POST['acf']['field_68ac2f304ad26'] = 20;
$fields->validate_native_product_fields();
same( array(), $errors );

// First-time variation creation must not discard an editor's ISSN override.
$magazine = array( 'print_details' => array( 'issn' => 'print-override' ), 'digital_details' => array( 'issn' => 'digital-override' ) );
$product = new class { public function get_sku() { return 'PARENT'; } };
$method = new ReflectionMethod( \TenUpTheme\WooCommerceCustomizations::class, 'populate_default_variation_data' );
$method->invoke( new \TenUpTheme\WooCommerceCustomizations(), 1, $product );
same( 'print-override', $saved['magazine_details']['print_details']['issn'] );
same( 'digital-override', $saved['magazine_details']['digital_details']['issn'] );
same( 'PARENT-DIGITAL', $saved['magazine_details']['digital_details']['sku'] );

echo "ProductEditorialFields tests passed: legacy pages, defaults, inherited access, validation and ISSN overrides.\n";
