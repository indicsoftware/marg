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

		// Remove upsells functionality
		$this->remove_upsells_functionality();

		// Clear shipping data for downloadable products
		// add_action( 'woocommerce_admin_process_product_object', array( $this, 'clear_shipping_for_downloadable' ) );

		// Auto-set simple downloadable for Articles category
		// add_action( 'woocommerce_admin_process_product_object', array( $this, 'auto_set_articles_as_downloadable' ), 5 );

		// Auto-set Magazines as variable products
		add_action( 'acf/save_post', array( $this, 'set_magazine_to_variable' ), 20 );

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

	/**
	 * Auto-set Magazine products as variable product type.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function set_magazine_to_variable( $product_id ) {
		// Verify this is a product
		if ( get_post_type( $product_id ) !== 'product' ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if product has Magazine category (term ID 198)
		if ( ! has_term( 198, 'product_cat', $product_id ) ) {
			return;
		}

		// Check if already variable
		$is_already_variable = has_term( 'variable', 'product_type', $product_id );

		if ( ! $is_already_variable ) {
			// Convert to variable product
			$result = wp_set_object_terms( $product_id, 'variable', 'product_type' );

			if ( is_wp_error( $result ) ) {
				return;
			}

			// Clear caches
			wc_delete_product_transients( $product_id );
			clean_post_cache( $product_id );
			wp_cache_delete( $product_id, 'product_type' );
			delete_transient( 'wc_product_type_' . $product_id );
		}

		// Create variations for Print and Digital
		$this->create_magazine_variations( $product_id );
	}

	/**
	 * Create Print and Digital variations for Magazine products.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function create_magazine_variations( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		// If product already has ANY variations, skip entirely
		$existing_variations = $product->get_children();
		if ( ! empty( $existing_variations ) ) {
			return;
		}

		// Use existing pa_medium attribute
		$attribute_taxonomy = 'pa_medium';

		// Assign both Print and Digital terms to the parent product
		wp_set_object_terms( $product_id, array( 'print', 'digital' ), $attribute_taxonomy );

		// Set the product attribute
		$attributes = $product->get_attributes();
		$attribute  = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute_taxonomy ) );
		$attribute->set_name( $attribute_taxonomy );
		$attribute->set_options( array( 'print', 'digital' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes[ $attribute_taxonomy ] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		// Create Print variation
		$print_variation = new \WC_Product_Variation();
		$print_variation->set_parent_id( $product_id );
		$print_variation->set_attributes( array( $attribute_taxonomy => 'print' ) );
		$print_variation->set_status( 'publish' );
		$print_variation->save();

		// Create Digital variation
		$digital_variation = new \WC_Product_Variation();
		$digital_variation->set_parent_id( $product_id );
		$digital_variation->set_attributes( array( $attribute_taxonomy => 'digital' ) );
		$digital_variation->set_status( 'publish' );
		$digital_variation->save();

		// Clear caches
		wc_delete_product_transients( $product_id );
		clean_post_cache( $product_id );
	}

}
