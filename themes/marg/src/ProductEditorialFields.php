<?php
/**
 * Defaults and validation for the product editorial forms.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Keep editorial fields portable while preserving imported reference values.
 */
class ProductEditorialFields {

	/** Register field-specific hooks. */
	public function init() {
		foreach ( array( 'field_article_page_from', 'field_article_page_to', 'field_print_issn', 'field_digital_issn', 'field_print_sku', 'field_digital_sku', 'field_article_sku', 'field_article_open_access' ) as $key ) {
			add_filter( 'acf/load_value/key=' . $key, array( $this, 'load_value' ), 20, 3 );
		}
		add_filter( 'acf/update_value/key=field_article_open_access', array( $this, 'load_value' ), 20, 3 );
		add_action( 'acf/input/admin_footer', array( $this, 'disable_inherited_toggle' ) );
		add_filter( 'acf/prepare_field/key=field_article_page_from', array( $this, 'show_legacy_pages' ) );
		add_filter( 'acf/validate_value/key=field_magazine_open_access_hover_image', array( $this, 'validate_hover_image' ), 10, 2 );
		add_filter( 'acf/validate_value/key=field_article_details_group', array( $this, 'validate_pages' ), 10, 2 );
		add_filter( 'acf/validate_value/key=field_689d970ca7425', array( $this, 'validate_dates' ), 10, 2 );
		foreach ( array( 'field_article_page_from', 'field_article_page_to', 'field_68515df152c76', 'field_68a845be7acae', 'field_magazine_number_of_photographs', 'field_magazine_number_of_illustrations', 'field_magazine_print_length' ) as $key ) {
			add_filter( 'acf/validate_value/key=' . $key, array( $this, 'validate_integer' ), 10, 2 );
		}
		add_action( 'acf/validate_save_post', array( $this, 'validate_native_product_fields' ) );
	}

	/**
	 * Enforce image width on form saves as well as in the media picker.
	 *
	 * @param mixed $valid Existing validation result.
	 * @param mixed $value Submitted attachment ID.
	 * @return mixed
	 */
	public function validate_hover_image( $valid, $value ) {
		if ( true !== $valid || empty( $value ) ) {
			return $valid;
		}
		$metadata = wp_get_attachment_metadata( (int) $value );
		if ( ! wp_attachment_is_image( (int) $value ) || empty( $metadata['width'] ) || $metadata['width'] < 500 ) {
			return __( 'Choose an Open Access Hover Image at least 500px wide.', 'tenup' );
		}
		return $valid;
	}

	/**
	 * Supply defaults without overwriting existing values or writing during reads.
	 *
	 * @param mixed $value Stored value.
	 * @param mixed $post_id Post ID.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	public function load_value( $value, $post_id, $field ) {
		$key = $field['key'];
		if ( 'field_article_open_access' === $key ) {
			return TaxonomyInheritance::article_open_access( $post_id );
		}
		if ( null !== $value && '' !== $value && false !== $value ) {
			return $value;
		}
		if ( in_array( $key, array( 'field_print_issn', 'field_digital_issn' ), true ) ) {
			$issn = get_field( 'issn', 'option' );
			return $issn[ 'field_print_issn' === $key ? 'print' : 'digital' ] ?? '';
		}
		if ( in_array( $key, array( 'field_article_page_from', 'field_article_page_to' ), true ) ) {
			// ACF supplies an empty default even when no metadata exists yet.
			// Preserve explicitly cleared values, but hydrate missing fields from legacy text.
			$meta_key = 'field_article_page_from' === $key ? 'article_details_page_from' : 'article_details_page_to';
			if ( metadata_exists( 'post', $post_id, $meta_key ) ) {
				return $value;
			}
			$pages = self::legacy_pages( get_post_meta( $post_id, 'article_details_page_number', true ) );
			return $pages[ 'field_article_page_from' === $key ? 0 : 1 ] ?? $value;
		}
		if ( ! is_numeric( $post_id ) || (int) $post_id < 1 ) {
			return $value;
		}
		$sku = get_post_meta( $post_id, '_sku', true );
		if ( 'field_article_sku' === $key ) {
			return $sku ? $sku : 'ARTICLE-' . (int) $post_id;
		}
		return $sku ? $sku . ( 'field_print_sku' === $key ? '-PRINT' : '-DIGITAL' ) : '';
	}

	/** Disable the inherited checkbox for keyboard users as well as mouse users. */
	public function disable_inherited_toggle() {
		?>
		<script>
		(function () {
			function disableToggle() {
				document.querySelectorAll('[data-key="field_article_open_access"] input').forEach(function (input) {
					input.disabled = true;
				});
				document.querySelectorAll('[data-key="field_article_open_access"] .acf-switch').forEach(function (toggle) {
					toggle.setAttribute('aria-disabled', 'true');
					toggle.style.pointerEvents = 'none';
				});
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', disableToggle);
			} else {
				disableToggle();
			}
			if (window.acf) {
				acf.addAction('ready append', disableToggle);
			}
		}());
		</script>
		<?php
	}

	/**
	 * Parse only unambiguous legacy page ranges; retain the original metadata.
	 *
	 * @param string $text Imported page text.
	 * @return array
	 */
	public static function legacy_pages( $text ) {
		if ( ! preg_match( '/^\s*(?:pp?\.?\s*)?([1-9][0-9]*)(?:\s*[-–—]\s*([1-9][0-9]*))?\s*$/u', (string) $text, $matches ) ) {
			return array();
		}
		$from = (int) $matches[1];
		$to   = isset( $matches[2] ) ? (int) $matches[2] : $from;
		return $to >= $from ? array( $from, $to ) : array();
	}

	/**
	 * Show the original reference when automatic conversion is unsafe.
	 *
	 * @param array $field Field definition.
	 * @return array
	 */
	public function show_legacy_pages( $field ) {
		$legacy = get_post_meta( acf_get_form_data( 'post_id' ), 'article_details_page_number', true );
		if ( '' !== $legacy && ! self::legacy_pages( $legacy ) ) {
			/* translators: %s: preserved page range text from the legacy CMS. */
			$field['instructions'] .= ' ' . sprintf( __( 'Review the preserved legacy page range: %s', 'tenup' ), esc_html( $legacy ) );
		}
		return $field;
	}

	/**
	 * Check page order when both optional endpoints are supplied.
	 *
	 * @param mixed $valid Existing validation result.
	 * @param array $value Article group submitted by ACF.
	 * @return mixed
	 */
	public function validate_pages( $valid, $value ) {
		if ( true !== $valid ) {
			return $valid;
		}
		$from = $value['field_article_page_from'] ?? '';
		$to   = $value['field_article_page_to'] ?? '';
		if ( '' === (string) $from || '' === (string) $to ) {
			return $valid;
		}
		if ( (int) $to < (int) $from ) {
			return __( 'To must be greater than or equal to From.', 'tenup' );
		}
		return $valid;
	}

	/**
	 * Keep optional publication ranges in chronological order.
	 *
	 * @param mixed $valid Existing validation result.
	 * @param array $value Publication date group.
	 * @return mixed
	 */
	public function validate_dates( $valid, $value ) {
		$start = $value['field_689d970ca7426'] ?? '';
		$end   = $value['field_689d970ca7427'] ?? '';
		if ( true === $valid && $start && $end && strcmp( $end, $start ) < 0 ) {
			return __( 'The publication End date must be on or after the Start date.', 'tenup' );
		}
		return $valid;
	}

	/**
	 * ACF's step setting constrains the browser; also enforce whole numbers on save.
	 *
	 * @param mixed $valid Existing validation result.
	 * @param mixed $value Number submitted by ACF.
	 * @return mixed
	 */
	public function validate_integer( $valid, $value ) {
		if ( true === $valid && '' !== (string) $value && ! ctype_digit( (string) $value ) ) {
			return __( 'Enter a whole number.', 'tenup' );
		}
		return $valid;
	}

	/** Validate native fields in the same ACF editor submission. */
	public function validate_native_product_fields() {
		// ACF verifies the form nonce before firing this validation action.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$type = isset( $_POST['acf']['field_68ac2f304ad26'] ) ? (int) $_POST['acf']['field_68ac2f304ad26'] : 0;
		if ( ! $type ) {
			return;
		}
		if ( ProductCategoryResolver::id( 'magazines' ) !== $type ) {
			return;
		}
		$post_id  = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;
		$image_id = isset( $_POST['_thumbnail_id'] ) ? max( 0, (int) $_POST['_thumbnail_id'] ) : get_post_thumbnail_id( $post_id );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $image_id ) {
			return;
		}
		$metadata = wp_get_attachment_metadata( $image_id );
		if ( ! wp_attachment_is_image( $image_id ) || empty( $metadata['width'] ) || $metadata['width'] < 1000 ) {
			acf_add_validation_error( '_thumbnail_id', __( 'Choose a magazine cover image at least 1000px wide.', 'tenup' ) );
		}
	}
}
