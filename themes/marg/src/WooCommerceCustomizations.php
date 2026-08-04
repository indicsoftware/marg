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
		// Auto-set Magazines and Books as variable products
		add_action( 'acf/save_post', array( $this, 'set_magazine_to_variable' ), 20 );

		// Add out of print checkbox for Print variations
		add_action( 'woocommerce_variation_options', array( $this, 'add_out_of_print_checkbox_to_variations' ), 10, 3 );

		// Save out of print checkbox value
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_out_of_print_variation_field' ), 10, 2 );
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
	 * Auto-set Magazine and Book products as variable product type.
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

		// Check if product has a Magazine or Book category. Product category IDs
		// differ between databases, so resolve by the stable category slugs.
		$is_magazine = ProductCategoryResolver::has( $product_id, 'magazines' );
		$is_book     = ProductCategoryResolver::has( $product_id, 'books' );

		if ( ! $is_magazine && ! $is_book ) {
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

		// Create variations for Print and Digital based on product type
		if ( $is_magazine ) {
			$this->create_magazine_variations( $product_id );
		} elseif ( $is_book ) {
			$this->create_book_variations( $product_id );
		}
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

		// Pre-populate ACF fields with defaults
		$this->populate_default_variation_data( $product_id, $product );

		// Clear caches
		wc_delete_product_transients( $product_id );
		clean_post_cache( $product_id );
	}

	/**
	 * Populate default variation data in ACF fields
	 *
	 * @param int         $product_id Product ID.
	 * @param \WC_Product $product    Product object.
	 * @return void
	 */
	private function populate_default_variation_data( $product_id, $product ) {
		// Get global ISSN values from options
		$issn_data   = get_field( 'issn', 'option' );
		$print_issn  = $issn_data['print'] ?? '';
		$digital_issn = $issn_data['digital'] ?? '';

		// Get parent product SKU
		$parent_sku = $product->get_sku();

		// Generate variation SKUs
		$print_sku  = $parent_sku ? $parent_sku . '-PRINT' : '';
		$digital_sku = $parent_sku ? $parent_sku . '-DIGITAL' : '';

		// Get existing magazine_details or create empty array
		$magazine_details = get_field( 'magazine_details', $product_id );
		if ( ! $magazine_details ) {
			$magazine_details = array();
		}

		// Populate Print Details
		if ( ! isset( $magazine_details['print_details'] ) ) {
			$magazine_details['print_details'] = array();
		}
		$magazine_details['print_details']['sku']  = $print_sku;
		$magazine_details['print_details']['issn'] = $print_issn;

		// Populate Digital Details
		if ( ! isset( $magazine_details['digital_details'] ) ) {
			$magazine_details['digital_details'] = array();
		}
		$magazine_details['digital_details']['sku']  = $digital_sku;
		$magazine_details['digital_details']['issn'] = $digital_issn;

		// Update the magazine_details field
		update_field( 'magazine_details', $magazine_details, $product_id );
	}

	/**
	 * Create Print and Digital variations for Book products.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function create_book_variations( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		// If product already has ANY variations, skip entirely
		$existing_variations = $product->get_children();
		if ( ! empty( $existing_variations ) ) {
			return;
		}

		// Get book details to check which variants to create
		$book_details = get_field( 'book_details', $product_id );
		$create_print = isset( $book_details['create_print_variant'] ) ? $book_details['create_print_variant'] : true;
		$create_digital = isset( $book_details['create_digital_variant'] ) ? $book_details['create_digital_variant'] : true;

		// If neither variant is selected, skip
		if ( ! $create_print && ! $create_digital ) {
			return;
		}

		// Use existing pa_medium attribute
		$attribute_taxonomy = 'pa_medium';

		// Build terms array based on selected variants
		$terms = array();
		if ( $create_print ) {
			$terms[] = 'print';
		}
		if ( $create_digital ) {
			$terms[] = 'digital';
		}

		// Assign selected terms to the parent product
		wp_set_object_terms( $product_id, $terms, $attribute_taxonomy );

		// Set the product attribute
		$attributes = $product->get_attributes();
		$attribute  = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute_taxonomy ) );
		$attribute->set_name( $attribute_taxonomy );
		$attribute->set_options( $terms );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes[ $attribute_taxonomy ] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		// Create Print variation if selected
		if ( $create_print ) {
			$print_variation = new \WC_Product_Variation();
			$print_variation->set_parent_id( $product_id );
			$print_variation->set_attributes( array( $attribute_taxonomy => 'print' ) );
			$print_variation->set_status( 'publish' );
			$print_variation->save();
		}

		// Create Digital variation if selected
		if ( $create_digital ) {
			$digital_variation = new \WC_Product_Variation();
			$digital_variation->set_parent_id( $product_id );
			$digital_variation->set_attributes( array( $attribute_taxonomy => 'digital' ) );
			$digital_variation->set_status( 'publish' );
			$digital_variation->save();
		}

		// Pre-populate ACF fields with defaults
		$this->populate_default_book_data( $product_id, $product, $create_print, $create_digital );

		// Clear caches
		wc_delete_product_transients( $product_id );
		clean_post_cache( $product_id );
	}

	/**
	 * Populate default book variation data in ACF fields
	 *
	 * @param int         $product_id     Product ID.
	 * @param \WC_Product $product        Product object.
	 * @param bool        $create_print   Whether to create print variant.
	 * @param bool        $create_digital Whether to create digital variant.
	 * @return void
	 */
	private function populate_default_book_data( $product_id, $product, $create_print = true, $create_digital = true ) {
		// Get parent product SKU
		$parent_sku = $product->get_sku();

		// Get existing book_details or create empty array
		$book_details = get_field( 'book_details', $product_id );
		if ( ! $book_details ) {
			$book_details = array();
		}

		// Populate Print Details (SKU only, no ISBN pre-population) if Print variant selected
		if ( $create_print ) {
			$print_sku = $parent_sku ? $parent_sku . '-PRINT' : '';
			if ( ! isset( $book_details['print_details'] ) ) {
				$book_details['print_details'] = array();
			}
			$book_details['print_details']['sku'] = $print_sku;
		}

		// Populate Digital Details (SKU only, no ISBN pre-population) if Digital variant selected
		if ( $create_digital ) {
			$digital_sku = $parent_sku ? $parent_sku . '-DIGITAL' : '';
			if ( ! isset( $book_details['digital_details'] ) ) {
				$book_details['digital_details'] = array();
			}
			$book_details['digital_details']['sku'] = $digital_sku;
		}

		// Update the book_details field
		update_field( 'book_details', $book_details, $product_id );
	}

}
