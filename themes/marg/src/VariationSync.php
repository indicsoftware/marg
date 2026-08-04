<?php
/**
 * Variation Sync
 *
 * Syncs ACF fields from parent Magazine and Book products to WooCommerce variation meta.
 * This allows editors to manage variation data through ACF fields instead of
 * the WooCommerce variations UI.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * VariationSync class
 */
class VariationSync {

	/**
	 * Initialize the Variation Sync
	 *
	 * @return void
	 */
	public function init() {
		// Sync ACF fields to variations after save (Magazines and Books)
		add_action( 'acf/save_post', array( $this, 'sync_product_data_to_variations' ), 25 );

		// Sync ACF fields to simple products after save (Articles)
		add_action( 'acf/save_post', array( $this, 'sync_article_data_to_product' ), 25 );

		// Add UI elements to variation editor
		add_action( 'woocommerce_variation_header', array( $this, 'show_acf_sync_notice' ), 10, 2 );

		// Hide WooCommerce fields that are ACF-managed
		add_action( 'admin_head', array( $this, 'hide_managed_variation_fields' ) );
	}

	/**
	 * Sync ACF product data to variations
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_product_data_to_variations( $post_id ) {
		// Only for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only for Magazines or Books. Product category IDs differ between
		// databases, so resolve by the stable category slugs.
		if ( ! ProductCategoryResolver::has_any( $post_id, array( 'magazines', 'books' ) ) ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$variations = $product->get_children();
		if ( empty( $variations ) ) {
			return;
		}

		// Get appropriate details field based on product type
		if ( ProductCategoryResolver::has( $post_id, 'magazines' ) ) {
			$product_details = get_field( 'magazine_details', $post_id );
			$identifier_key  = 'issn'; // Magazines use ISSN
		} elseif ( ProductCategoryResolver::has( $post_id, 'books' ) ) {
			$product_details = get_field( 'book_details', $post_id );
			$identifier_key  = 'isbn'; // Books use ISBN
		}

		if ( ! $product_details ) {
			return;
		}

		foreach ( $variations as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) {
				continue;
			}

			$medium = $variation->get_attribute( 'pa_medium' );

			if ( $medium === 'print' ) {
				$this->sync_print_fields( $product_details, $variation, $identifier_key );
			} elseif ( $medium === 'digital' ) {
				$this->sync_digital_fields( $product_details, $variation, $identifier_key );
			}
		}

		// Clear product caches
		wc_delete_product_transients( $post_id );
	}

	/**
	 * Sync print variation fields from ACF to WooCommerce
	 *
	 * @param array                 $product_details Product details from ACF (magazine_details or book_details).
	 * @param \WC_Product_Variation $variation       Variation product object.
	 * @param string                $identifier_key  Identifier field name ('issn' or 'isbn').
	 * @return void
	 */
	private function sync_print_fields( $product_details, $variation, $identifier_key = 'issn' ) {
		$print_data = $product_details['print_details'] ?? array();

		// Set WooCommerce fields
		if ( isset( $print_data['sku'] ) && ! empty( $print_data['sku'] ) ) {
			$variation->set_sku( $print_data['sku'] );
		}

		if ( isset( $print_data['regular_price_inr'] ) && $print_data['regular_price_inr'] !== '' ) {
			$variation->set_regular_price( $print_data['regular_price_inr'] );
		}

		if ( isset( $print_data['sale_price_inr'] ) && $print_data['sale_price_inr'] !== '' ) {
			$variation->set_sale_price( $print_data['sale_price_inr'] );
		}

		if ( isset( $print_data['weight'] ) && $print_data['weight'] !== '' ) {
			$variation->set_weight( $print_data['weight'] );
		}

		if ( isset( $print_data['length'] ) && $print_data['length'] !== '' ) {
			$variation->set_length( $print_data['length'] );
		}

		if ( isset( $print_data['width'] ) && $print_data['width'] !== '' ) {
			$variation->set_width( $print_data['width'] );
		}

		if ( isset( $print_data['height'] ) && $print_data['height'] !== '' ) {
			$variation->set_height( $print_data['height'] );
		}

		// Handle stock status and quantity
		$out_of_print = $print_data['out_of_print'] ?? false;
		if ( $out_of_print ) {
			$variation->set_stock_status( 'outofstock' );
			$variation->set_manage_stock( false );
		} else {
			$stock_status = $print_data['stock_status'] ?? 'instock';
			$variation->set_stock_status( $stock_status );

			if ( isset( $print_data['stock_quantity'] ) && $print_data['stock_quantity'] !== '' ) {
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( intval( $print_data['stock_quantity'] ) );
			} else {
				$variation->set_manage_stock( false );
			}
		}

		// Physical product settings
		$variation->set_downloadable( false );
		$variation->set_virtual( false );

		$variation->save();

		// Store custom meta
		update_post_meta( $variation->get_id(), '_out_of_print', $out_of_print ? 'yes' : 'no' );

		// Store identifier (ISSN for magazines, ISBN for books)
		$identifier_field = $identifier_key; // 'issn' or 'isbn'
		if ( isset( $print_data[ $identifier_field ] ) && ! empty( $print_data[ $identifier_field ] ) ) {
			update_post_meta( $variation->get_id(), '_' . $identifier_field, sanitize_text_field( $print_data[ $identifier_field ] ) );
		}

		// Store USD prices if provided (for Price Based on Country plugin)
		if ( isset( $print_data['regular_price_usd'] ) && $print_data['regular_price_usd'] !== '' ) {
			update_post_meta( $variation->get_id(), '_regular_price_usd', floatval( $print_data['regular_price_usd'] ) );
		}

		if ( isset( $print_data['sale_price_usd'] ) && $print_data['sale_price_usd'] !== '' ) {
			update_post_meta( $variation->get_id(), '_sale_price_usd', floatval( $print_data['sale_price_usd'] ) );
		}
	}

	/**
	 * Sync digital variation fields from ACF to WooCommerce
	 *
	 * @param array                 $product_details Product details from ACF (magazine_details or book_details).
	 * @param \WC_Product_Variation $variation       Variation product object.
	 * @param string                $identifier_key  Identifier field name ('issn' or 'isbn').
	 * @return void
	 */
	private function sync_digital_fields( $product_details, $variation, $identifier_key = 'issn' ) {
		$digital_data = $product_details['digital_details'] ?? array();

		// Set WooCommerce fields
		if ( isset( $digital_data['sku'] ) && ! empty( $digital_data['sku'] ) ) {
			$variation->set_sku( $digital_data['sku'] );
		}

		if ( isset( $digital_data['regular_price_inr'] ) && $digital_data['regular_price_inr'] !== '' ) {
			$variation->set_regular_price( $digital_data['regular_price_inr'] );
		}

		if ( isset( $digital_data['sale_price_inr'] ) && $digital_data['sale_price_inr'] !== '' ) {
			$variation->set_sale_price( $digital_data['sale_price_inr'] );
		}

		// Digital product settings
		$variation->set_downloadable( true );
		$variation->set_virtual( true );

		// Clear shipping data
		$variation->set_weight( '' );
		$variation->set_length( '' );
		$variation->set_width( '' );
		$variation->set_height( '' );

		// Stock status - digital products are always in stock (no stock management)
		$variation->set_stock_status( 'instock' );
		$variation->set_manage_stock( false );

		// Handle PDF file for digital downloads
		if ( isset( $digital_data['pdf_file'] ) && ! empty( $digital_data['pdf_file'] ) ) {
			$file_id  = intval( $digital_data['pdf_file'] );
			$file_url = wp_get_attachment_url( $file_id );

			if ( $file_url ) {
				$downloads   = array();
				$download_id = md5( $file_url );

				// Get file name from attachment or use Magazine title
				$file_name = get_the_title( $file_id );
				if ( empty( $file_name ) ) {
					$parent_title = get_the_title( $variation->get_parent_id() );
					$file_name    = $parent_title . ' - Digital Edition';
				}

				$downloads[ $download_id ] = array(
					'name' => $file_name,
					'file' => $file_url,
				);

				$variation->set_downloads( $downloads );
			}
		} else {
			// Clear downloads if no PDF
			$variation->set_downloads( array() );
		}

		$variation->save();

		// Store identifier (ISSN for magazines, ISBN for books)
		$identifier_field = $identifier_key; // 'issn' or 'isbn'
		if ( isset( $digital_data[ $identifier_field ] ) && ! empty( $digital_data[ $identifier_field ] ) ) {
			update_post_meta( $variation->get_id(), '_' . $identifier_field, sanitize_text_field( $digital_data[ $identifier_field ] ) );
		}

		// Store USD prices if provided (for Price Based on Country plugin)
		if ( isset( $digital_data['regular_price_usd'] ) && $digital_data['regular_price_usd'] !== '' ) {
			update_post_meta( $variation->get_id(), '_regular_price_usd', floatval( $digital_data['regular_price_usd'] ) );
		}

		if ( isset( $digital_data['sale_price_usd'] ) && $digital_data['sale_price_usd'] !== '' ) {
			update_post_meta( $variation->get_id(), '_sale_price_usd', floatval( $digital_data['sale_price_usd'] ) );
		}
	}

	/**
	 * Sync ACF article data to simple product
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_article_data_to_product( $post_id ) {
		// Only for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only for Articles. Product category IDs differ between databases.
		if ( ! ProductCategoryResolver::has( $post_id, 'articles' ) ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return;
		}

		// Get article details from ACF
		$article_details = get_field( 'article_details', $post_id );
		if ( ! $article_details || ! isset( $article_details['product_data'] ) ) {
			return;
		}

		$product_data = $article_details['product_data'];

		// Set WooCommerce fields
		if ( isset( $product_data['sku'] ) && ! empty( $product_data['sku'] ) ) {
			$product->set_sku( $product_data['sku'] );
		}

		if ( isset( $product_data['regular_price_inr'] ) && $product_data['regular_price_inr'] !== '' ) {
			$product->set_regular_price( $product_data['regular_price_inr'] );
		}

		if ( isset( $product_data['sale_price_inr'] ) && $product_data['sale_price_inr'] !== '' ) {
			$product->set_sale_price( $product_data['sale_price_inr'] );
		}

		// Digital product settings
		$product->set_downloadable( true );
		$product->set_virtual( true );

		// Clear shipping data
		$product->set_weight( '' );
		$product->set_length( '' );
		$product->set_width( '' );
		$product->set_height( '' );

		// Stock status - articles are always in stock (no stock management)
		$product->set_stock_status( 'instock' );
		$product->set_manage_stock( false );

		// Handle PDF file for downloads
		if ( isset( $product_data['pdf_file'] ) && ! empty( $product_data['pdf_file'] ) ) {
			$file_id  = intval( $product_data['pdf_file'] );
			$file_url = wp_get_attachment_url( $file_id );

			if ( $file_url ) {
				$downloads   = array();
				$download_id = md5( $file_url );

				// Get file name from attachment or use Article title
				$file_name = get_the_title( $file_id );
				if ( empty( $file_name ) ) {
					$file_name = get_the_title( $post_id );
				}

				$downloads[ $download_id ] = array(
					'name' => $file_name,
					'file' => $file_url,
				);

				$product->set_downloads( $downloads );
			}
		} else {
			// Clear downloads if no PDF
			$product->set_downloads( array() );
		}

		$product->save();

		// Store USD prices if provided (for Price Based on Country plugin)
		if ( isset( $product_data['regular_price_usd'] ) && $product_data['regular_price_usd'] !== '' ) {
			update_post_meta( $post_id, '_regular_price_usd', floatval( $product_data['regular_price_usd'] ) );
		}

		if ( isset( $product_data['sale_price_usd'] ) && $product_data['sale_price_usd'] !== '' ) {
			update_post_meta( $post_id, '_sale_price_usd', floatval( $product_data['sale_price_usd'] ) );
		}

		// Clear product caches
		wc_delete_product_transients( $post_id );
	}

	/**
	 * Show admin notice in variation panel
	 *
	 * @param \WC_Product_Variation $variation Variation object.
	 * @param int                   $loop      Variation loop index.
	 * @return void
	 */
	public function show_acf_sync_notice( $variation, $loop ) {
		$parent_id = $variation->get_parent_id();
		if ( ! ProductCategoryResolver::has_any( $parent_id, array( 'magazines', 'books' ) ) ) {
			return;
		}

		echo '<div style="padding: 8px; background: #f0f0f1; margin: 10px 0; border-left: 4px solid #2271b1;">';
		echo '<strong>Note:</strong> Price, SKU, and inventory data for this variation are managed via ACF fields on the parent product.';
		echo '</div>';
	}

	/**
	 * Hide WooCommerce variation fields that are ACF-managed
	 *
	 * @return void
	 */
	public function hide_managed_variation_fields() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		global $post;
		if ( ! $post || ! ProductCategoryResolver::has_any( $post->ID, array( 'magazines', 'books' ) ) ) {
			return;
		}

		?>
		<style>
			/* Hide variation fields that are ACF-managed */
			.woocommerce_variation .form-row.form-row-first:has(input[name^="variable_sku"]),
			.woocommerce_variation .form-row.form-row-full.variable_pricing:has(input[name^="variable_regular_price"]),
			.woocommerce_variation .form-row.dimensions_field {
				opacity: 0.5;
				pointer-events: none;
				position: relative;
			}

			.woocommerce_variation .form-row.form-row-first:has(input[name^="variable_sku"])::after,
			.woocommerce_variation .form-row.form-row-full.variable_pricing:has(input[name^="variable_regular_price"])::after,
			.woocommerce_variation .form-row.dimensions_field::after {
				content: "Managed by ACF";
				display: block;
				color: #666;
				font-size: 11px;
				font-style: italic;
				position: absolute;
				top: 0;
				right: 0;
			}
		</style>
		<?php
	}
}
