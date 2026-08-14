<?php
/**
 * Regression test for classic-editor stylesheet registration.
 *
 * Run with: php tests/ThemeCoreEditorStyleTest.php
 */

$registered_editor_styles = array();

function add_theme_support() {}

function remove_theme_support() {}

function register_nav_menus() {}

function esc_html__( $text ) {
	return $text;
}

function add_editor_style( $stylesheets ) {
	global $registered_editor_styles;

	$registered_editor_styles = (array) $stylesheets;
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

require_once dirname( __DIR__ ) . '/src/ThemeCore.php';

$theme_core = new \TenUpTheme\ThemeCore();
$theme_core->theme_setup();

assert_same(
	array(
		'dist/css/frontend.css',
		'dist/css/editor-style-overrides.css',
	),
	$registered_editor_styles,
	'The editor spacing override must load after the frontend stylesheet.'
);

fwrite( STDOUT, "ThemeCore editor-style test passed.\n" );
