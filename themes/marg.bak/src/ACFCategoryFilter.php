<?php
/**
 * ACF Category Filter
 *
 * Filters out unwanted categories from ACF taxonomy fields
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * ACFCategoryFilter class
 */
class ACFCategoryFilter {

	/**
	 * Initialize the ACF category filter
	 *
	 * @return void
	 */
	public function init() {
		// Filter ACF taxonomy field results
		add_filter( 'acf/fields/taxonomy/query', array( $this, 'filter_uncategorized_category' ), 10, 3 );
		add_filter( 'acf/fields/taxonomy/result', array( $this, 'filter_uncategorized_result' ), 10, 4 );

		// For select/checkbox/radio fields that use product_cat
		add_filter( 'acf/load_field', array( $this, 'filter_field_choices' ) );
	}

	/**
	 * Filter out Uncategorized category from ACF taxonomy query
	 *
	 * @param array $args The WP_Term_Query arguments.
	 * @param array $field The field array.
	 * @param int   $post_id The post ID.
	 * @return array
	 */
	public function filter_uncategorized_category( $args, $field, $post_id ) {
		// Only filter product_cat taxonomy fields
		if ( isset( $field['taxonomy'] ) && $field['taxonomy'] === 'product_cat' ) {
			// Get the Uncategorized category
			$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );

			if ( $uncategorized ) {
				// Exclude the Uncategorized category by ID
				if ( ! isset( $args['exclude'] ) ) {
					$args['exclude'] = array();
				}

				if ( ! is_array( $args['exclude'] ) ) {
					$args['exclude'] = array( $args['exclude'] );
				}

				$args['exclude'][] = $uncategorized->term_id;
			}
		}

		return $args;
	}

	/**
	 * Filter out Uncategorized from displayed results
	 *
	 * @param string $text The text displayed for this term.
	 * @param object $term The term object.
	 * @param array  $field The field array.
	 * @param int    $post_id The post ID.
	 * @return string
	 */
	public function filter_uncategorized_result( $text, $term, $field, $post_id ) {
		// Hide Uncategorized category from results
		if ( isset( $field['taxonomy'] ) && $field['taxonomy'] === 'product_cat' ) {
			if ( $term->slug === 'uncategorized' ) {
				return ''; // Return empty string to hide it
			}
		}

		return $text;
	}

	/**
	 * Filter field choices to remove Uncategorized
	 *
	 * @param array $field The field array.
	 * @return array
	 */
	public function filter_field_choices( $field ) {
		// Check if this is the Publication Type field from Product Details group
		if ( isset( $field['key'] ) && $field['key'] === 'field_68ac2f304ad26' ) {
			// This is the Publication Type field
			if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				// Remove Uncategorized if it exists
				if ( isset( $field['choices']['uncategorized'] ) ) {
					unset( $field['choices']['uncategorized'] );
				}

				// Also check by different keys
				foreach ( $field['choices'] as $key => $value ) {
					if ( strtolower( $value ) === 'uncategorized' || $key === 'uncategorized' ) {
						unset( $field['choices'][$key] );
					}
				}
			}
		}

		// Also filter any product_cat taxonomy field
		if ( isset( $field['type'] ) && $field['type'] === 'taxonomy' &&
		     isset( $field['taxonomy'] ) && $field['taxonomy'] === 'product_cat' ) {

			// Add inline JavaScript to hide Uncategorized option
			add_action( 'admin_footer', function() {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Hide Uncategorized option in select fields
					$('select option').filter(function() {
						return $(this).text() === 'Uncategorized' || $(this).val() === 'uncategorized';
					}).remove();

					// Hide Uncategorized checkbox/radio
					$('input[type="checkbox"], input[type="radio"]').each(function() {
						var $label = $(this).closest('label');
						if ($label.text().indexOf('Uncategorized') !== -1) {
							$label.hide();
						}
					});

					// For Select2 fields
					$(document).on('select2:open', function() {
						setTimeout(function() {
							$('.select2-results__option').filter(function() {
								return $(this).text() === 'Uncategorized';
							}).hide();
						}, 0);
					});
				});
				</script>
				<?php
			}, 999 );
		}

		return $field;
	}
}