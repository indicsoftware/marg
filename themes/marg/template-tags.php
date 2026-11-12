<?php
/**
 * Template Tags
 *
 * @package TenUpTheme
 *
 * This file contains **only** pure functions that relate to templating.
 *
 * Rules:
 * - Functions in this file **must be pure** (i.e., they must not cause side effects).
 * - No hooks, filters, or global state modifications should be added here.
 * - If a function has side effects (e.g., enqueuing scripts, modifying post data, adding filters),
 *   it should be encapsulated within a class in the `src/` directory.
 *
 * A pure function:
 * - Given the same input, it always returns the same output.
 * - Does not modify external state (no global variables, no database writes, etc.).
 * - Does not rely on WordPress hooks or filters.
 *
 * Example of an allowed function:
 * ```php
 * function get_custom_excerpt( string $content, int $length = 50 ): string {
 *     return wp_trim_words( $content, $length );
 * }
 * ```
 *
 * Example of a function **that does not belong here**:
 * ```php
 * function modify_post_title( string $title ): string {
 *     return 'My Great ' . $title;
 * }
 * add_filter( 'the_title', 'modify_post_title' );
 * ```
 *
 * Keeping this file limited to pure functions ensures maintainability and a clear separation of concerns.
 */

namespace TenupTheme;

/**
 * Join CSS class names, dropping empty values and duplicates.
 *
 * Accepts strings, arrays of strings, or [ 'class' => bool ] maps.
 *
 * @param mixed ...$classes Class names.
 * @return string Unescaped class string (escape with esc_attr() at output).
 */
function class_names( ...$classes ): string {
	$out = [];
	foreach ( $classes as $class ) {
		if ( is_array( $class ) ) {
			foreach ( $class as $key => $value ) {
				if ( is_string( $key ) ) {
					if ( $value ) {
						$out[] = $key;
					}
				} elseif ( is_string( $value ) ) {
					$out[] = $value;
				}
			}
		} elseif ( is_string( $class ) ) {
			$out[] = $class;
		}
	}
	$out = preg_split( '/\s+/', trim( implode( ' ', $out ) ) );
	return implode( ' ', array_unique( array_filter( (array) $out ) ) );
}

/**
 * Build an escaped HTML attribute string (with a leading space).
 *
 * true renders a bare attribute, false/null/'' skip it (except '' for alt),
 * arrays are joined with spaces. href/src/action values are esc_url()'d.
 *
 * @param array<string, mixed> $attrs Attributes.
 * @return string Escaped, ready to echo.
 */
function html_attrs( array $attrs ): string {
	$out = '';
	foreach ( $attrs as $name => $value ) {
		$name = preg_replace( '/[^a-zA-Z0-9_:\-.]/', '', (string) $name );
		if ( '' === $name || null === $value || false === $value || ( '' === $value && 'alt' !== $name ) ) {
			continue;
		}
		if ( true === $value ) {
			$out .= ' ' . $name;
			continue;
		}
		if ( is_array( $value ) ) {
			$value = implode( ' ', array_filter( array_map( 'strval', $value ) ) );
		}
		$value = in_array( $name, [ 'href', 'src', 'action', 'formaction' ], true ) ? esc_url( (string) $value ) : esc_attr( (string) $value );
		$out  .= ' ' . $name . '="' . $value . '"';
	}
	return $out;
}
