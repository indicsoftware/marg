<?php
/**
 * Taxonomy Inheritance
 *
 * Automatically syncs taxonomies from Articles to parent Magazines.
 * When an Article is saved, its taxonomy terms (time-period, subject, geographic-area, product_tag)
 * are aggregated with all other Articles linked to the same Magazine(s) and applied to the Magazine.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * TaxonomyInheritance class
 */
class TaxonomyInheritance {

	/**
	 * Initialize taxonomy inheritance
	 *
	 * @return void
	 */
	public function init() {
		// Sync taxonomies from Articles to Magazines after ACF save
		add_action( 'acf/save_post', array( $this, 'sync_article_taxonomies_to_magazines' ), 30 );

		// Sync Open Access from Magazines to Articles after ACF save
		add_action( 'acf/save_post', array( $this, 'sync_magazine_open_access_to_articles' ), 30 );

		// Add admin notice for Magazine taxonomy fields
		add_action( 'admin_head', array( $this, 'add_magazine_taxonomy_notice' ) );

		// Add admin notice for Article Open Access field
		add_action( 'admin_head', array( $this, 'add_article_open_access_notice' ) );
	}

	/**
	 * Sync Article taxonomies to parent Magazine(s)
	 * Triggered when an Article is saved
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_article_taxonomies_to_magazines( $post_id ) {
		// Only for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only for Articles (term ID 202)
		if ( ! has_term( 202, 'product_cat', $post_id ) ) {
			return;
		}

		// Get Article's parent Magazine(s)
		$article_details = get_field( 'article_details', $post_id );
		if ( ! $article_details || ! isset( $article_details['parent_publication'] ) ) {
			return;
		}

		$parent_magazines = $article_details['parent_publication'];
		if ( empty( $parent_magazines ) ) {
			return;
		}

		// Update each parent Magazine
		foreach ( $parent_magazines as $magazine ) {
			if ( isset( $magazine->ID ) ) {
				$this->update_magazine_taxonomies( $magazine->ID );
			}
		}
	}

	/**
	 * Sync Magazine's Open Access setting to all linked Articles
	 * Triggered when a Magazine is saved
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function sync_magazine_open_access_to_articles( $post_id ) {
		// Only for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only for Magazines (term ID 198)
		if ( ! has_term( 198, 'product_cat', $post_id ) ) {
			return;
		}

		// Get Magazine's Open Access setting
		$magazine_details = get_field( 'magazine_details', $post_id );
		if ( ! $magazine_details ) {
			return;
		}

		$open_access = isset( $magazine_details['open_access'] ) ? $magazine_details['open_access'] : false;

		// Get all Articles linked to this Magazine
		$articles = $this->get_articles_for_magazine( $post_id );

		if ( empty( $articles ) ) {
			return;
		}

		// Update each Article's Open Access field
		foreach ( $articles as $article ) {
			$article_details = get_field( 'article_details', $article->ID );

			if ( ! $article_details ) {
				$article_details = array();
			}

			// Update open_access field (convert boolean to "Yes"/"No" string for text field)
			$article_details['open_access'] = $open_access ? 'Yes' : 'No';

			// Update the article_details field
			update_field( 'article_details', $article_details, $article->ID );

			// Clear product caches
			wc_delete_product_transients( $article->ID );
		}
	}

	/**
	 * Update Magazine taxonomies by aggregating from all linked Articles
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return void
	 */
	private function update_magazine_taxonomies( $magazine_id ) {
		// Get all Articles linked to this Magazine
		$articles = $this->get_articles_for_magazine( $magazine_id );

		// Initialize taxonomy arrays
		$aggregated_taxonomies = array(
			'time_periods' => array(),
			'subjects'     => array(),
			'geography'    => array(),
			'tags'         => array(),
		);

		if ( empty( $articles ) ) {
			// No Articles linked - clear all Magazine taxonomies
			$this->clear_magazine_taxonomies( $magazine_id );
			return;
		}

		// Aggregate taxonomies from all Articles (union strategy)
		foreach ( $articles as $article ) {
			$article_taxonomies = get_field( 'taxonomies', $article->ID );

			if ( $article_taxonomies ) {
				// Merge time periods
				if ( isset( $article_taxonomies['time_periods'] ) && is_array( $article_taxonomies['time_periods'] ) ) {
					$aggregated_taxonomies['time_periods'] = array_merge(
						$aggregated_taxonomies['time_periods'],
						$article_taxonomies['time_periods']
					);
				}

				// Merge subjects
				if ( isset( $article_taxonomies['subjects'] ) && is_array( $article_taxonomies['subjects'] ) ) {
					$aggregated_taxonomies['subjects'] = array_merge(
						$aggregated_taxonomies['subjects'],
						$article_taxonomies['subjects']
					);
				}

				// Merge geography
				if ( isset( $article_taxonomies['geography'] ) && is_array( $article_taxonomies['geography'] ) ) {
					$aggregated_taxonomies['geography'] = array_merge(
						$aggregated_taxonomies['geography'],
						$article_taxonomies['geography']
					);
				}

				// Merge tags
				if ( isset( $article_taxonomies['tags'] ) && is_array( $article_taxonomies['tags'] ) ) {
					$aggregated_taxonomies['tags'] = array_merge(
						$aggregated_taxonomies['tags'],
						$article_taxonomies['tags']
					);
				}
			}
		}

		// Remove duplicates (keep unique term IDs)
		$aggregated_taxonomies['time_periods'] = array_unique( $aggregated_taxonomies['time_periods'] );
		$aggregated_taxonomies['subjects']     = array_unique( $aggregated_taxonomies['subjects'] );
		$aggregated_taxonomies['geography']    = array_unique( $aggregated_taxonomies['geography'] );
		$aggregated_taxonomies['tags']         = array_unique( $aggregated_taxonomies['tags'] );

		// Reindex arrays (ACF expects sequential integer keys)
		$aggregated_taxonomies['time_periods'] = array_values( $aggregated_taxonomies['time_periods'] );
		$aggregated_taxonomies['subjects']     = array_values( $aggregated_taxonomies['subjects'] );
		$aggregated_taxonomies['geography']    = array_values( $aggregated_taxonomies['geography'] );
		$aggregated_taxonomies['tags']         = array_values( $aggregated_taxonomies['tags'] );

		// Update Magazine taxonomies field
		update_field( 'taxonomies', $aggregated_taxonomies, $magazine_id );

		// Clear product caches
		wc_delete_product_transients( $magazine_id );
	}

	/**
	 * Get all Articles linked to a specific Magazine
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return array Array of WP_Post objects.
	 */
	private function get_articles_for_magazine( $magazine_id ) {
		// Query Articles that have this Magazine as parent_publication
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => 202, // Articles
				),
			),
			'meta_query'     => array(
				array(
					'key'     => 'article_details_parent_publication',
					'value'   => '"' . $magazine_id . '"',
					'compare' => 'LIKE',
				),
			),
		);

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Clear all taxonomy terms from a Magazine
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return void
	 */
	private function clear_magazine_taxonomies( $magazine_id ) {
		$empty_taxonomies = array(
			'time_periods' => array(),
			'subjects'     => array(),
			'geography'    => array(),
			'tags'         => array(),
		);

		update_field( 'taxonomies', $empty_taxonomies, $magazine_id );

		// Also clear WordPress taxonomy terms directly
		wp_set_object_terms( $magazine_id, array(), 'time-period', false );
		wp_set_object_terms( $magazine_id, array(), 'subject', false );
		wp_set_object_terms( $magazine_id, array(), 'geographic-area', false );
		wp_set_object_terms( $magazine_id, array(), 'product_tag', false );

		// Clear product caches
		wc_delete_product_transients( $magazine_id );
	}

	/**
	 * Add admin notice and CSS/JS to disable Magazine taxonomy fields
	 * Only applies to Magazines (term 198), not Articles or Books
	 *
	 * @return void
	 */
	public function add_magazine_taxonomy_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		?>
		<style>
			/* Disable Magazine taxonomy fields (applied via JavaScript only for Magazines) */
			.acf-field[data-name="taxonomies"].magazine-inherited {
				opacity: 0.6;
				pointer-events: none;
				position: relative;
			}

			.acf-field[data-name="taxonomies"].magazine-inherited::before {
				content: "🔒 Auto-inherited from linked Articles";
				display: block;
				padding: 10px;
				background: #f0f6fc;
				border: 1px solid #0969da;
				border-radius: 4px;
				margin-bottom: 15px;
				color: #0969da;
				font-weight: 600;
				pointer-events: auto;
			}
		</style>

		<script type="text/javascript">
		(function($) {
			'use strict';

			$(document).ready(function() {
				/**
				 * Check if current product is a Magazine and apply taxonomy notice
				 */
				function checkAndApplyMagazineTaxonomyNotice() {
					// Check if the product type field has Magazines selected (term ID 198)
					var $typeField = $('.acf-field[data-name="type"]');

					if ($typeField.length) {
						var $select = $typeField.find('select');

						if ($select.length && $select.val() == '198') {
							// This is a Magazine - apply the notice class
							$('.acf-field[data-name="taxonomies"]').addClass('magazine-inherited');
						} else {
							// Not a Magazine - remove the notice class
							$('.acf-field[data-name="taxonomies"]').removeClass('magazine-inherited');
						}
					}
				}

				// Run on page load
				checkAndApplyMagazineTaxonomyNotice();

				// Re-run when ACF fields are loaded/updated
				if (typeof acf !== 'undefined') {
					acf.addAction('ready', function() {
						checkAndApplyMagazineTaxonomyNotice();
					});

					// Watch for changes to the product type field
					acf.addAction('change', function() {
						checkAndApplyMagazineTaxonomyNotice();
					});
				}

				// Also watch for changes on the type field directly
				$(document).on('change', '.acf-field[data-name="type"] select', function() {
					checkAndApplyMagazineTaxonomyNotice();
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Add admin notice and CSS to style Article Open Access field
	 * Field is disabled via ACF's native readonly/disabled properties (text field type)
	 *
	 * @return void
	 */
	public function add_article_open_access_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		global $post;
		if ( ! $post || ! has_term( 202, 'product_cat', $post->ID ) ) {
			return;
		}

		?>
		<style>
			/* Style Article Open Access field (disabled via ACF native properties) */
			.acf-field[data-key="field_article_open_access"] {
				opacity: 0.6;
				pointer-events: none;
				position: relative;
			}

			.acf-field[data-key="field_article_open_access"]::before {
				content: "🔒 Auto-inherited from parent Magazine";
				display: block;
				padding: 10px;
				background: #f0f6fc;
				border: 1px solid #0969da;
				border-radius: 4px;
				margin-bottom: 15px;
				color: #0969da;
				font-weight: 600;
				pointer-events: auto;
			}
		</style>
		<?php
	}
}
