<?php
/**
 * ACF Conditional Logic for Taxonomy Field Groups
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Class ACFConditionalLogic
 */
class ACFConditionalLogic {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts for product edit screens
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Debug logging
		error_log( 'ACF Conditional Logic - Hook: ' . $hook );
		
		// Only load on product edit/new screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			error_log( 'ACF Conditional Logic - Not a product edit screen, skipping' );
			return;
		}

		// Check if we're on a product post type
		global $post_type;
		error_log( 'ACF Conditional Logic - Post type: ' . $post_type );
		if ( 'product' !== $post_type ) {
			error_log( 'ACF Conditional Logic - Not a product post type, skipping' );
			return;
		}

		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field' ) ) {
			error_log( 'ACF Conditional Logic - ACF not active, skipping' );
			return;
		}
		
		error_log( 'ACF Conditional Logic - Enqueuing scripts' );

		// Enqueue the conditional logic script (using simple version for testing)
		wp_enqueue_script(
			'marg-acf-conditional-taxonomy',
			get_template_directory_uri() . '/assets/js/admin/acf-conditional-taxonomy-simple.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Localize script with necessary data
		wp_localize_script(
			'marg-acf-conditional-taxonomy',
			'margACFConditional',
			array(
				'typeFieldKey'     => 'field_68ac2f304ad26',
				'taxonomyGroupKey' => 'group_68e48ee0308e8',
				'showForTerms'     => array( 'articles', 'books', 'magazines' ),
				'hideForTerms'     => array( 'subscription-plans' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'marg_acf_conditional_nonce' ),
			)
		);
	}
}
