<?php
/**
 * WooCommerce Customizations
 *
 * Customizes WooCommerce functionality including:
 * - Removing grouped and external/affiliate product types
 * - Removing upsells functionality
 * - Hiding shipping options for downloadable simple products
 * - Auto-setting Articles category products as simple and downloadable
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * WooCommerceCustomizations class
 */
class WooCommerceCustomizations {

	/**
	 * Initialize the WooCommerce customizations
	 *
	 * @return void
	 */
	public function init() {
		// Remove unwanted product types from selector
		add_filter( 'woocommerce_product_type_selector', array( $this, 'remove_unwanted_product_types' ) );

		// Remove unwanted product type options
		add_filter( 'product_type_options', array( $this, 'remove_unwanted_product_options' ) );

		// Add admin styles to hide unwanted product elements
		add_action( 'admin_head', array( $this, 'hide_unwanted_product_elements' ) );

		// Remove unwanted from product type query
		add_filter( 'woocommerce_product_type_query', array( $this, 'filter_product_type_query' ), 10, 2 );

		// Prevent unwanted products in REST API (optional - disabled by default)
		// add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'prevent_unwanted_in_api' ), 10, 3 );

		// Remove unwanted product data tabs
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'remove_unwanted_product_data_tabs' ) );

		// Add JavaScript to remove unwanted options from any dynamic interfaces
		add_action( 'admin_footer', array( $this, 'remove_unwanted_product_js' ) );

		// Remove upsells functionality
		$this->remove_upsells_functionality();

		// Clear shipping data for downloadable products
		// add_action( 'woocommerce_admin_process_product_object', array( $this, 'clear_shipping_for_downloadable' ) );

		// Auto-set simple downloadable for Articles category
		// add_action( 'woocommerce_admin_process_product_object', array( $this, 'auto_set_articles_as_downloadable' ), 5 );

		// Add out of print checkbox for Print variations
		add_action( 'woocommerce_variation_options', array( $this, 'add_out_of_print_checkbox_to_variations' ), 10, 3 );

		// Save out of print checkbox value
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_out_of_print_variation_field' ), 10, 2 );
	}

	/**
	 * Remove unwanted product types from the product type selector dropdown
	 *
	 * @param array $types Product types array.
	 * @return array
	 */
	public function remove_unwanted_product_types( $types ) {
		// Remove grouped product type
		if ( isset( $types['grouped'] ) ) {
			unset( $types['grouped'] );
		}

		// Remove external/affiliate product type
		if ( isset( $types['external'] ) ) {
			unset( $types['external'] );
		}

		return $types;
	}

	/**
	 * Remove unwanted product type options from product data metabox
	 *
	 * @param array $options Product type options.
	 * @return array
	 */
	public function remove_unwanted_product_options( $options ) {
		// Remove external-specific options
		if ( isset( $options['external_url'] ) ) {
			unset( $options['external_url'] );
		}

		return $options;
	}

	/**
	 * Hide unwanted product elements via CSS
	 *
	 * @return void
	 */
	public function hide_unwanted_product_elements() {
		// Only on product edit pages
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		?>
		<style type="text/css">
			/* Hide grouped product option in type selector if it somehow still appears */
			#product-type option[value="grouped"],
			#product-type option[value="external"] {
				display: none !important;
			}

			/* Hide grouped product data tab */
			.product_data_tabs .grouped_tab,
			#grouped_product_data {
				display: none !important;
			}

			/* Hide external/affiliate product data tab */
			.product_data_tabs .external_tab,
			#external_product_data {
				display: none !important;
			}

			/* Hide grouped and external products in any filters or lists */
			.subsubsub li.grouped,
			.subsubsub li.external {
				display: none !important;
			}

			/* Hide grouped and external product options in bulk edit */
			.inline-edit-product select[name="_product_type"] option[value="grouped"],
			.inline-edit-product select[name="_product_type"] option[value="external"] {
				display: none !important;
			}

			/* Hide grouped and external option in quick edit */
			.quick-edit-row select[name="_product_type"] option[value="grouped"],
			.quick-edit-row select[name="_product_type"] option[value="external"] {
				display: none !important;
			}

			/* Hide external product URL and button text fields */
			.show_if_external {
				display: none !important;
			}

			/* Hide upsells field in Linked Products tab */
			.options_group .form-field._upsell_ids_field,
			label[for="upsell_ids"],
			#upsell_ids,
			.form-field._upsell_ids_field {
				display: none !important;
			}

			/* Hide upsells section on single product pages */
			.up-sells.upsells.products,
			.upsells.products,
			section.up-sells {
				display: none !important;
			}

			/* Hide shipping options for downloadable products */
			.product-type-simple.downloadable-product .shipping_tab_options,
			.product-type-simple.downloadable-product ._weight_field,
			.product-type-simple.downloadable-product ._length_field,
			.product-type-simple.downloadable-product ._width_field,
			.product-type-simple.downloadable-product ._height_field,
			.product-type-simple.downloadable-product .dimensions_field,
			.product-type-simple.downloadable-product .wc-shipping-class-field,
			.product-type-simple.downloadable-product .product_shipping_class {
				display: none !important;
			}
		</style>
		<?php

		if ( $screen->base === 'edit' && isset( $_GET['product_cat'] ) ) {
			$category_slug = sanitize_title( wp_unslash( $_GET['product_cat'] ) );
			$term = get_term_by( 'slug', $category_slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				printf(
					'<script>document.addEventListener("DOMContentLoaded",function(){var heading=document.querySelector(".wrap .wp-heading-inline");if(heading){heading.textContent=%s;}});</script>',
					wp_json_encode( $term->name )
				);
			}
		}
	}

	/**
	 * Remove unwanted product data tabs
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function remove_unwanted_product_data_tabs( $tabs ) {
		// Remove the grouped tab
		if ( isset( $tabs['grouped'] ) ) {
			unset( $tabs['grouped'] );
		}

		// Remove the external/affiliate tab
		if ( isset( $tabs['external'] ) ) {
			unset( $tabs['external'] );
		}

		return $tabs;
	}

	/**
	 * Filter product type query to exclude unwanted products
	 *
	 * @param array  $args Query arguments.
	 * @param string $type Query type.
	 * @return array
	 */
	public function filter_product_type_query( $args, $type = '' ) {
		// If querying product types, exclude grouped and external
		if ( isset( $args['type'] ) ) {
			if ( $args['type'] === 'grouped' || $args['type'] === 'external' ) {
				$args['type'] = 'simple'; // Fallback to simple
			}
		}

		return $args;
	}

	/**
	 * Add JavaScript to remove unwanted options from dynamic interfaces
	 *
	 * @return void
	 */
	public function remove_unwanted_product_js() {
		// Only on product edit pages
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var VARIABLE_CATEGORY_SLUGS = ['books', 'magazines'];
			var ARTICLE_CATEGORY_SLUGS = ['articles'];

			function slugifyCategoryText(text) {
				if (!text) {
					return '';
				}

				return text
					.toString()
					.toLowerCase()
					.replace(/\([^)]+\)/g, '')
					.replace(/&/g, 'and')
					.replace(/[^a-z0-9]+/g, '-')
					.replace(/^-+|-+$/g, '');
			}

			function getCheckboxLabelText($checkbox) {
				var $label = $checkbox.next('label');
				if (!$label.length) {
					$label = $checkbox.siblings('label').first();
				}
				if (!$label.length) {
					$label = $checkbox.parent().find('label').first();
				}
				return $label.length ? $label.text() : '';
			}

			function extractCategorySlugFromCheckbox($checkbox) {
				return slugifyCategoryText(getCheckboxLabelText($checkbox));
			}

			function setProductType(type) {
				var $productType = $('#product-type');
				if (!$productType.length) {
					return;
				}

				if ($productType.val() !== type) {
					$productType.val(type).trigger('change');
				}
			}

			function categoryTextMatchesSlugList(text, slugs) {
				if (!text) {
					return false;
				}

				var slug = slugifyCategoryText(text);
				return slug && slugs.indexOf(slug) !== -1;
			}

			function isCategorySelected(slugs) {
				var matchFound = false;

				function checkCheckbox($checkbox) {
					var labelText = getCheckboxLabelText($checkbox);
					if (categoryTextMatchesSlugList(labelText, slugs)) {
						matchFound = true;
						return true;
					}

					return false;
				}

				$('#product_catchecklist input[type="checkbox"]:checked').each(function() {
					if (checkCheckbox($(this))) {
						return false;
					}
				});

				if (matchFound) {
					return true;
				}

				$('#taxonomy-product_cat input[type="checkbox"]:checked').each(function() {
					if (checkCheckbox($(this))) {
						return false;
					}
				});

				if (matchFound) {
					return true;
				}

				$('.categorychecklist input[type="checkbox"]:checked').each(function() {
					if (checkCheckbox($(this))) {
						return false;
					}
				});

				if (matchFound) {
					return true;
				}

				$('.wc-product-search').each(function() {
					if (matchFound) {
						return false;
					}

					var select2Data = null;
					try {
						if (typeof $(this).select2 === 'function' && $(this).data('select2')) {
							select2Data = $(this).select2('data');
						}
					} catch (error) {
						select2Data = null;
					}

					if (select2Data && Array.isArray(select2Data)) {
						select2Data.forEach(function(item) {
							if (matchFound) {
								return;
							}

							if (item && item.text && categoryTextMatchesSlugList(item.text, slugs)) {
								matchFound = true;
							}
						});
					}
				});

				return matchFound;
			}

			function checkVariableCategoriesAndSetProductType() {
				console.log('Checking for variable product categories...');
				var shouldBeVariable = isCategorySelected(VARIABLE_CATEGORY_SLUGS);

				if (!shouldBeVariable) {
					var $publicationType = $('select[name="acf[field_68ac2f304ad26]"]');
					if ($publicationType.length) {
						var selectedText = $publicationType.find('option:selected').text();
						console.log('ACF Publication Type selected:', selectedText);
						if (categoryTextMatchesSlugList(selectedText, VARIABLE_CATEGORY_SLUGS)) {
							shouldBeVariable = true;
							console.log('Match found in ACF field.');
						}
					}
				}

				if (shouldBeVariable) {
					console.log('Setting product type to variable.');
					setProductType('variable');
				} else {
					console.log('No variable category match found.');
				}
			}

			// Remove grouped and external options from product type selector
			$('#product-type option[value="grouped"]').remove();
			$('#product-type option[value="external"]').remove();

			// Remove grouped tab if it exists
			$('.product_data_tabs .grouped_tab').remove();
			$('#grouped_product_data').remove();

			// Remove external tab if it exists
			$('.product_data_tabs .external_tab').remove();
			$('#external_product_data').remove();

			// Remove from any dynamically created selects
			$(document).on('DOMNodeInserted', function(e) {
				if ($(e.target).is('select')) {
					$(e.target).find('option[value="grouped"]').remove();
					$(e.target).find('option[value="external"]').remove();
				}
			});

			// Handle WooCommerce product type change event
			$('#product-type').on('change', function() {
				if ($(this).val() === 'grouped' || $(this).val() === 'external') {
					$(this).val('simple').trigger('change');
				}
			});

			// Remove grouped and external from bulk edit and quick edit
			$('.inline-edit-product select[name="_product_type"] option[value="grouped"]').remove();
			$('.inline-edit-product select[name="_product_type"] option[value="external"]').remove();
			$('.quick-edit-row select[name="_product_type"] option[value="grouped"]').remove();
			$('.quick-edit-row select[name="_product_type"] option[value="external"]').remove();

			// Hide external product fields
			$('.show_if_external').hide();

			// Hide upsells field in Linked Products tab
			$('.form-field._upsell_ids_field').hide();
			$('label[for="upsell_ids"]').parent().hide();
			$('#upsell_ids').closest('.form-field').hide();

			// Remove upsells select2 if it exists
			if ($('#upsell_ids').length) {
				$('#upsell_ids').select2('destroy').hide();
			}

			// Hide upsells on any tab change
			$('.product_data_tabs').on('click', 'li', function() {
				setTimeout(function() {
					$('.form-field._upsell_ids_field').hide();
					$('label[for="upsell_ids"]').parent().hide();
					$('#upsell_ids').closest('.form-field').hide();
				}, 100);
			});

			// Hide shipping options for downloadable products
			function toggleShippingOptionsForDownloadable() {
				var $body = $('body');
				var isDownloadable = $('#_downloadable').is(':checked');
				var productType = $('#product-type').val();

				if (isDownloadable && productType === 'simple') {
					// Add class to body for CSS targeting
					$body.addClass('downloadable-product');

					// Hide shipping-related fields
					$('._weight_field').hide();
					$('._length_field').hide();
					$('._width_field').hide();
					$('._height_field').hide();
					$('.dimensions_field').hide();
					$('.wc-shipping-class-field').hide();
					$('.product_shipping_class').hide();
					$('.shipping_tab_options').hide();

					// Hide Shipping tab if it exists
					$('.shipping_options').hide();
				} else {
					// Remove class from body
					$body.removeClass('downloadable-product');

					// Show shipping-related fields
					$('._weight_field').show();
					$('._length_field').show();
					$('._width_field').show();
					$('._height_field').show();
					$('.dimensions_field').show();
					$('.wc-shipping-class-field').show();
					$('.product_shipping_class').show();
					$('.shipping_tab_options').show();

					// Show Shipping tab
					$('.shipping_options').show();
				}
			}

			// Run on page load
			toggleShippingOptionsForDownloadable();

			// Run when downloadable checkbox changes
			$('#_downloadable').on('change', function() {
				var isChecked = $(this).is(':checked');

				// Switch to General tab when downloadable is checked
				if (isChecked) {
					$('.general_options a').trigger('click');
				}

				toggleShippingOptionsForDownloadable();
			});

			// Run when product type changes
			$('#product-type').on('change', function() {
				toggleShippingOptionsForDownloadable();
			});

			// Run when switching tabs
			$('.product_data_tabs').on('click', 'li', function() {
				setTimeout(function() {
					toggleShippingOptionsForDownloadable();
				}, 50);
			});

			// Auto-set simple downloadable for Articles category
			function checkArticlesCategoryAndSetDownloadable() {
				var hasArticles = isCategorySelected(ARTICLE_CATEGORY_SLUGS);

				if (!hasArticles) {
					var $publicationType = $('select[name="acf[field_68ac2f304ad26]"]');
					if ($publicationType.length) {
						var selectedText = $publicationType.find('option:selected').text();
						if (categoryTextMatchesSlugList(selectedText, ARTICLE_CATEGORY_SLUGS)) {
							hasArticles = true;
						}
					}
				}

				if (!hasArticles) {
					return;
				}

				var $productType = $('#product-type');
				if ($productType.length && $productType.val() !== 'simple') {
					$productType.val('simple').trigger('change');
				}

				setTimeout(function() {
					var $downloadable = $('#_downloadable');
					if (!$downloadable.length) {
						return;
					}

					if (!$downloadable.is(':checked')) {
						$downloadable.prop('disabled', false);
						$downloadable.prop('checked', true);
						$downloadable.trigger('change');
					}
				}, 500);
			}

			// Run on page load with multiple attempts
			setTimeout(function() {
				checkArticlesCategoryAndSetDownloadable();
				checkVariableCategoriesAndSetProductType();
			}, 500);

			setTimeout(function() {
				checkArticlesCategoryAndSetDownloadable();
				checkVariableCategoriesAndSetProductType();
			}, 1000);

			setTimeout(function() {
				checkArticlesCategoryAndSetDownloadable();
				checkVariableCategoriesAndSetProductType();
			}, 2000);

			// Run when categories change (checkbox) - use multiple selectors
			$(document).on('change', '#product_catchecklist input[type="checkbox"]', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 100);
				setTimeout(checkVariableCategoriesAndSetProductType, 100);
			});

			$(document).on('change', '#taxonomy-product_cat input[type="checkbox"]', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 100);
				setTimeout(checkVariableCategoriesAndSetProductType, 100);
			});

			$(document).on('change', '.categorychecklist input[type="checkbox"]', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 100);
				setTimeout(checkVariableCategoriesAndSetProductType, 100);
			});

			// Run when Select2 categories change
			$(document).on('change', 'select.wc-product-search', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 100);
				setTimeout(checkVariableCategoriesAndSetProductType, 100);
			});

			// Listen for any category-related clicks
			$(document).on('click', '#product_catchecklist-pop label', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 200);
				setTimeout(checkVariableCategoriesAndSetProductType, 200);
			});

			// Listen for ACF Publication Type field changes (the actual field that controls article selection)
			$(document).on('change', 'select[name="acf[field_68ac2f304ad26]"]', function() {
				setTimeout(checkArticlesCategoryAndSetDownloadable, 100);
			});

			// Also listen for ACF's own events if ACF is available
			if (typeof acf !== 'undefined') {
				acf.addAction('ready', function() {
					checkArticlesCategoryAndSetDownloadable();
					checkVariableCategoriesAndSetProductType();
				});
			}

			// Show/hide "Out of print" checkbox based on Medium attribute
			function toggleOutOfPrintCheckbox() {
				$('.woocommerce_variation').each(function() {
					var $variation = $(this);
					var $outOfPrintField = $variation.find('.variable_out_of_print_field');

					// Find the Medium attribute select in this variation (note: underscore not hyphen)
					var $mediumSelect = $variation.find('select[name^="attribute_pa_medium"]');

					if (!$mediumSelect.length) {
						// If no select found, check for hidden input (if attribute is already set)
						var $mediumInput = $variation.find('input[name^="attribute_pa_medium"]');
						if ($mediumInput.length) {
							var mediumValue = $mediumInput.val();
							if (mediumValue === 'print') {
								$outOfPrintField.show();
							} else {
								$outOfPrintField.hide();
							}
						} else {
							// No medium attribute found, hide by default
							$outOfPrintField.hide();
						}
						return;
					}

					var selectedMedium = $mediumSelect.val();

					// Show checkbox only for Print medium
					if (selectedMedium === 'print') {
						$outOfPrintField.show();
					} else {
						$outOfPrintField.hide();
					}
				});
			}

			// Run on page load
			setTimeout(toggleOutOfPrintCheckbox, 500);

			// Run when variations are loaded/reloaded
			$('#variable_product_options').on('woocommerce_variations_loaded', function() {
				setTimeout(toggleOutOfPrintCheckbox, 100);
			});

			// Run when a variation is added
			$('#variable_product_options').on('woocommerce_variations_added', function() {
				setTimeout(toggleOutOfPrintCheckbox, 100);
			});

			// Run when Medium attribute changes (note: underscore not hyphen)
			$(document).on('change', 'select[name^="attribute_pa_medium"]', function() {
				setTimeout(toggleOutOfPrintCheckbox, 100);
			});

			// Run when variation panel is opened
			$(document).on('click', '.woocommerce_variation h3', function() {
				setTimeout(toggleOutOfPrintCheckbox, 100);
			});
		});
		</script>
		<?php
	}

	/**
	 * Optional: Prevent unwanted products in REST API
	 * Uncomment the add_filter line in init() to enable this
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Product       $product  Product object.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function prevent_unwanted_in_api( $response, $product, $request ) {
		// If creating/updating a product
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$data = $response->get_data();

			// If type is grouped or external, change to simple
			if ( isset( $data['type'] ) && in_array( $data['type'], array( 'grouped', 'external' ), true ) ) {
				$data['type'] = 'simple';
				$response->set_data( $data );
			}
		}

		return $response;
	}

	/**
	 * Convert existing unwanted products to simple products.
	 *
	 * @param string $type Product type to convert ('grouped', 'external', or 'all').
	 * @return array
	 */
	public function convert_unwanted_to_simple( $type = 'all' ) {
		$results = array(
			'converted' => 0,
			'errors'    => 0,
			'products'  => array(),
		);

		$types_to_convert = array();

		if ( 'all' === $type ) {
			$types_to_convert = array( 'grouped', 'external' );
		} elseif ( in_array( $type, array( 'grouped', 'external' ), true ) ) {
			$types_to_convert = array( $type );
		} else {
			return $results;
		}

		foreach ( $types_to_convert as $product_type ) {
			$args = array(
				'type'   => $product_type,
				'limit'  => -1,
				'return' => 'objects',
			);

			$products = wc_get_products( $args );

			foreach ( $products as $product ) {
				try {
					$product_id = $product->get_id();
					wp_set_object_terms( $product_id, 'simple', 'product_type' );

					if ( 'grouped' === $product_type ) {
						delete_post_meta( $product_id, '_children' );
					} elseif ( 'external' === $product_type ) {
						delete_post_meta( $product_id, '_product_url' );
						delete_post_meta( $product_id, '_button_text' );
					}

					$results['converted']++;
					$results['products'][] = array(
						'id'       => $product_id,
						'name'     => $product->get_name(),
						'old_type' => $product_type,
					);
				} catch ( \Exception $e ) {
					$results['errors']++;
				}
			}
		}

		wc_delete_product_transients();

		return $results;
	}

	/**
	 * Remove upsells functionality hooks.
	 *
	 * @return void
	 */
	public function remove_upsells_functionality() {
		add_action( 'init', function() {
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		}, 20 );

		add_filter( 'woocommerce_product_get_upsell_ids', '__return_empty_array', 999 );
		add_filter( 'woocommerce_product_variation_get_upsell_ids', '__return_empty_array', 999 );

		add_action( 'woocommerce_admin_process_product_object', array( $this, 'remove_upsell_ids_on_save' ) );
		add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'remove_upsells_from_api' ), 10, 3 );
		add_filter( 'woocommerce_rest_pre_insert_product_object', array( $this, 'prevent_upsells_via_api' ), 10, 2 );
	}

	/**
	 * Clear upsell IDs when saving a product.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function remove_upsell_ids_on_save( $product ) {
		$product->set_upsell_ids( array() );
	}

	/**
	 * Strip upsell IDs from REST API responses.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Product       $product  Product object.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function remove_upsells_from_api( $response, $product, $request ) {
		$data = $response->get_data();

		if ( isset( $data['upsell_ids'] ) ) {
			$data['upsell_ids'] = array();
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Block upsells from being set via REST API.
	 *
	 * @param \WC_Product      $product Product object.
	 * @param \WP_REST_Request $request Request object.
	 * @return \WC_Product
	 */
	public function prevent_upsells_via_api( $product, $request ) {
		$product->set_upsell_ids( array() );
		return $product;
	}

	/**
	 * Clear all existing upsell relationships.
	 *
	 * @return array
	 */
	public function clear_all_upsells() {
		$results = array(
			'cleared'  => 0,
			'errors'   => 0,
			'products' => array(),
		);

		$args = array(
			'limit'  => -1,
			'return' => 'objects',
		);

		$products = wc_get_products( $args );

		foreach ( $products as $product ) {
			$upsell_ids = $product->get_upsell_ids();
			if ( empty( $upsell_ids ) ) {
				continue;
			}

			try {
				$product->set_upsell_ids( array() );
				$product->save();
				delete_post_meta( $product->get_id(), '_upsell_ids' );

				$results['cleared']++;
				$results['products'][] = array(
					'id'             => $product->get_id(),
					'name'           => $product->get_name(),
					'old_upsell_ids' => $upsell_ids,
				);
			} catch ( \Exception $e ) {
				$results['errors']++;
			}
		}

		wc_delete_product_transients();

		return $results;
	}

	/**
	 * Clear shipping data for downloadable products.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function clear_shipping_for_downloadable( $product ) {
		if ( $product->is_type( 'simple' ) && $product->is_downloadable() ) {
			$product->set_weight( '' );
			$product->set_length( '' );
			$product->set_width( '' );
			$product->set_height( '' );
			$product->set_shipping_class_id( 0 );
		}
	}

	/**
	 * Auto-set Articles products as simple downloadable.
	 *
	 * @param \WC_Product $product Product object.
	 * @return void
	 */
	public function auto_set_articles_as_downloadable( $product ) {
		$product_id = $product->get_id();
		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );

		if ( is_wp_error( $categories ) || ! in_array( 'Articles', $categories, true ) ) {
			return;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			wp_set_object_terms( $product_id, 'simple', 'product_type' );
		}

		$product->set_downloadable( true );
	}

	/**
	 * Add out of print checkbox to variation options for Print variations.
	 *
	 * @param int     $loop           Variation loop counter.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 * @return void
	 */
	public function add_out_of_print_checkbox_to_variations( $loop, $variation_data, $variation ) {
		$out_of_print = get_post_meta( $variation->ID, '_out_of_print', true );
		?>
		<label class="tips variable_out_of_print_field" data-tip="<?php esc_attr_e( 'Mark this print variation as out of print', 'woocommerce' ); ?>">
			<?php esc_html_e( 'Out of print', 'woocommerce' ); ?>
			<input type="checkbox" class="checkbox variable_out_of_print" name="variable_out_of_print[<?php echo esc_attr( $loop ); ?>]" <?php checked( $out_of_print, 'yes' ); ?> />
		</label>
		<?php
	}

	/**
	 * Save out of print checkbox value for variations.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop counter.
	 * @return void
	 */
	public function save_out_of_print_variation_field( $variation_id, $loop ) {
		if ( isset( $_POST['variable_out_of_print'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_out_of_print', 'yes' );
		} else {
			update_post_meta( $variation_id, '_out_of_print', 'no' );
		}
	}
}
