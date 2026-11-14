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

/**
 * Inline a theme SVG from assets/svg/{name}.svg.
 *
 * The files are the Figma exports with the hard-coded #DA100D fill swapped for
 * currentColor, so colour (and the 30% muted red) comes from the text colour
 * class on the element or an ancestor. Read-only: the file is read once per
 * request and nothing is written.
 *
 * The root <svg> keeps its own attributes (width, height, viewBox, fill...) except
 * Figma's inline style, and $attrs are merged over them (null removes one).
 * Decorative by default (aria-hidden, focusable=false); pass 'aria-label' to
 * expose it as role="img". Figma layer ids on <g>/<path> are dropped so the same
 * icon can appear twice on a page without duplicate ids (clipPath ids are kept,
 * they are referenced by clip-path).
 *
 * @param string               $name  File name without extension, [a-z0-9-] only.
 * @param array<string, mixed> $attrs Root attributes, escaped by html_attrs().
 * @return string SVG markup ('' if the file is missing). Theme-owned file: echo as is.
 */
function inline_svg( string $name, array $attrs = [] ): string {
	static $files = [];

	if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $name ) ) {
		return '';
	}

	if ( ! array_key_exists( $name, $files ) ) {
		$path = get_template_directory() . '/assets/svg/' . $name . '.svg';
		$svg  = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$svg  = (string) preg_replace( '/(<(?:g|path)\b[^>]*?)\s+id="[^"]*"/i', '$1', $svg );

		$files[ $name ] = false !== stripos( $svg, '<svg' ) ? trim( $svg ) : '';
	}

	if ( '' === $files[ $name ] ) {
		return '';
	}

	$labelled = isset( $attrs['aria-label'] ) && '' !== (string) $attrs['aria-label'];
	$attrs    = array_merge(
		[
			'aria-hidden' => $labelled ? null : 'true',
			'focusable'   => 'false',
			'role'        => $labelled ? 'img' : null,
		],
		$attrs
	);

	return (string) preg_replace_callback(
		'/<svg\b([^>]*)>/i',
		static function ( array $match ) use ( $attrs ): string {
			$root = [];
			if ( preg_match_all( '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)="([^"]*)"/', $match[1], $pairs, PREG_SET_ORDER ) ) {
				foreach ( $pairs as $pair ) {
					$root[ $pair[1] ] = html_entity_decode( $pair[2], ENT_QUOTES | ENT_XML1, 'UTF-8' );
				}
			}
			unset( $root['style'] );

			return '<svg' . html_attrs( array_merge( $root, $attrs ) ) . '>';
		},
		$files[ $name ],
		1
	);
}
