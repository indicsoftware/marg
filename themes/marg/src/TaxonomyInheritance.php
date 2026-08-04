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
		error_log( "[TaxonomyInheritance] Sync triggered for post {$post_id}" );

		// Only for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			error_log( "[TaxonomyInheritance] Post {$post_id} is not a product, skipping" );
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			error_log( "[TaxonomyInheritance] Autosave detected, skipping" );
			return;
		}

		// Only for Articles. Product category IDs differ between databases, so
		// resolve by the stable category slug.
		if ( ! ProductCategoryResolver::has( $post_id, 'articles' ) ) {
			error_log( "[TaxonomyInheritance] Post {$post_id} is not an Article, skipping" );
			return;
		}

		error_log( "[TaxonomyInheritance] Processing Article {$post_id}" );

		// Get Article's parent Magazine(s)
		$article_details = get_field( 'article_details', $post_id );
		if ( ! $article_details || ! isset( $article_details['parent_publication'] ) ) {
			error_log( "[TaxonomyInheritance] No article_details or parent_publication found for Article {$post_id}" );
			return;
		}

		$parent_publication = $article_details['parent_publication'];
		if ( empty( $parent_publication ) ) {
			error_log( "[TaxonomyInheritance] Parent publication is empty for Article {$post_id}" );
			return;
		}

		// Normalize to array format (ACF relationship with max:1 can return single object or array)
		$parent_magazines = is_array( $parent_publication ) ? $parent_publication : array( $parent_publication );

		error_log( "[TaxonomyInheritance] Found " . count( $parent_magazines ) . " parent magazine(s) for Article {$post_id}" );
		error_log( "[TaxonomyInheritance] Parent publication format: " . ( is_array( $parent_publication ) ? 'array' : 'object' ) );

		// Update each parent Magazine
		foreach ( $parent_magazines as $magazine ) {
			if ( isset( $magazine->ID ) ) {
				error_log( "[TaxonomyInheritance] Updating taxonomies for Magazine {$magazine->ID}" );
				$this->update_magazine_taxonomies( $magazine->ID );
			} else {
				error_log( "[TaxonomyInheritance] WARNING: Invalid magazine object in parent_publication" );
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
		try {
			// Only for products
			if ( get_post_type( $post_id ) !== 'product' ) {
				return;
			}

			// Skip autosaves
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Only for Magazines. Product category IDs differ between databases.
			if ( ! ProductCategoryResolver::has( $post_id, 'magazines' ) ) {
				return;
			}

			error_log( "[TaxonomyInheritance] Syncing Open Access from Magazine {$post_id} to Articles" );

			// Get Magazine's Open Access setting
			$magazine_details = get_field( 'magazine_details', $post_id );
			if ( ! $magazine_details || ! is_array( $magazine_details ) ) {
				error_log( "[TaxonomyInheritance] No magazine_details found for Magazine {$post_id}" );
				return;
			}

			$open_access = isset( $magazine_details['open_access'] ) ? $magazine_details['open_access'] : false;
			$open_access_value = $open_access ? 'Yes' : 'No';

			error_log( "[TaxonomyInheritance] Magazine {$post_id} Open Access: {$open_access_value}" );

			// Get all Articles linked to this Magazine
			$articles = $this->get_articles_for_magazine( $post_id );

			if ( empty( $articles ) ) {
				error_log( "[TaxonomyInheritance] No Articles found for Magazine {$post_id}" );
				return;
			}

			error_log( "[TaxonomyInheritance] Syncing Open Access to " . count( $articles ) . " Article(s)" );

			$success_count = 0;
			$error_count = 0;

			// Update each Article's Open Access field
			foreach ( $articles as $article ) {
				if ( ! isset( $article->ID ) || ! get_post_status( $article->ID ) ) {
					error_log( "[TaxonomyInheritance] WARNING: Skipping invalid Article" );
					$error_count++;
					continue;
				}

				$article_details = get_field( 'article_details', $article->ID );

				if ( ! $article_details || ! is_array( $article_details ) ) {
					$article_details = array();
				}

				// Update open_access field (convert boolean to "Yes"/"No" string for text field)
				$article_details['open_access'] = $open_access_value;

				// Update the article_details field
				$result = update_field( 'article_details', $article_details, $article->ID );

				if ( false === $result ) {
					error_log( "[TaxonomyInheritance] ERROR: Failed to update Open Access for Article {$article->ID}" );
					$error_count++;
					continue;
				}

				// Clear product caches
				wc_delete_product_transients( $article->ID );
				$success_count++;
			}

			error_log( "[TaxonomyInheritance] Open Access sync complete: {$success_count} succeeded, {$error_count} failed" );

		} catch ( Exception $e ) {
			error_log( "[TaxonomyInheritance] EXCEPTION in sync_magazine_open_access_to_articles(): " . $e->getMessage() );
		}
	}

	/**
	 * Update Magazine taxonomies by aggregating from all linked Articles
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return bool True on success, false on failure.
	 */
	private function update_magazine_taxonomies( $magazine_id ) {
		try {
			error_log( "[TaxonomyInheritance] Updating taxonomies for Magazine {$magazine_id}" );

			// Validate Magazine ID
			if ( ! $magazine_id || ! get_post_status( $magazine_id ) ) {
				error_log( "[TaxonomyInheritance] ERROR: Invalid Magazine ID {$magazine_id}" );
				return false;
			}

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
				error_log( "[TaxonomyInheritance] No Articles found for Magazine {$magazine_id}, clearing taxonomies" );
				// No Articles linked - clear all Magazine taxonomies
				return $this->clear_magazine_taxonomies( $magazine_id );
			}

			error_log( "[TaxonomyInheritance] Aggregating taxonomies from " . count( $articles ) . " Article(s)" );

			// Aggregate taxonomies from all Articles (union strategy)
			foreach ( $articles as $article ) {
				if ( ! isset( $article->ID ) || ! get_post_status( $article->ID ) ) {
					error_log( "[TaxonomyInheritance] WARNING: Skipping invalid Article" );
					continue;
				}

				$article_taxonomies = get_field( 'taxonomies', $article->ID );

				if ( ! $article_taxonomies || ! is_array( $article_taxonomies ) ) {
					error_log( "[TaxonomyInheritance] Article {$article->ID} has no taxonomies or invalid format" );
					continue;
				}

				error_log( "[TaxonomyInheritance] Article {$article->ID} has taxonomies: " . json_encode( array_map( 'count', array_filter( $article_taxonomies, 'is_array' ) ) ) );

				// Merge time periods
				if ( isset( $article_taxonomies['time_periods'] ) && is_array( $article_taxonomies['time_periods'] ) ) {
					$aggregated_taxonomies['time_periods'] = array_merge(
						$aggregated_taxonomies['time_periods'],
						array_filter( $article_taxonomies['time_periods'], 'is_numeric' )
					);
				}

				// Merge subjects
				if ( isset( $article_taxonomies['subjects'] ) && is_array( $article_taxonomies['subjects'] ) ) {
					$aggregated_taxonomies['subjects'] = array_merge(
						$aggregated_taxonomies['subjects'],
						array_filter( $article_taxonomies['subjects'], 'is_numeric' )
					);
				}

				// Merge geography
				if ( isset( $article_taxonomies['geography'] ) && is_array( $article_taxonomies['geography'] ) ) {
					$aggregated_taxonomies['geography'] = array_merge(
						$aggregated_taxonomies['geography'],
						array_filter( $article_taxonomies['geography'], 'is_numeric' )
					);
				}

				// Merge tags
				if ( isset( $article_taxonomies['tags'] ) && is_array( $article_taxonomies['tags'] ) ) {
					$aggregated_taxonomies['tags'] = array_merge(
						$aggregated_taxonomies['tags'],
						array_filter( $article_taxonomies['tags'], 'is_numeric' )
					);
				}
			}

			// Remove duplicates and invalid values (keep unique term IDs)
			$aggregated_taxonomies['time_periods'] = array_unique( array_filter( $aggregated_taxonomies['time_periods'], 'is_numeric' ) );
			$aggregated_taxonomies['subjects']     = array_unique( array_filter( $aggregated_taxonomies['subjects'], 'is_numeric' ) );
			$aggregated_taxonomies['geography']    = array_unique( array_filter( $aggregated_taxonomies['geography'], 'is_numeric' ) );
			$aggregated_taxonomies['tags']         = array_unique( array_filter( $aggregated_taxonomies['tags'], 'is_numeric' ) );

			// Reindex arrays (ACF expects sequential integer keys)
			$aggregated_taxonomies['time_periods'] = array_values( $aggregated_taxonomies['time_periods'] );
			$aggregated_taxonomies['subjects']     = array_values( $aggregated_taxonomies['subjects'] );
			$aggregated_taxonomies['geography']    = array_values( $aggregated_taxonomies['geography'] );
			$aggregated_taxonomies['tags']         = array_values( $aggregated_taxonomies['tags'] );

			error_log( "[TaxonomyInheritance] [v4.0] Final aggregated counts: time_periods=" . count( $aggregated_taxonomies['time_periods'] ) . ", subjects=" . count( $aggregated_taxonomies['subjects'] ) . ", geography=" . count( $aggregated_taxonomies['geography'] ) . ", tags=" . count( $aggregated_taxonomies['tags'] ) );

			// Log exact term IDs being synced
			error_log( "[TaxonomyInheritance] [v4.0] Aggregated taxonomy data - time_periods: " . json_encode( $aggregated_taxonomies['time_periods'] ) );
			error_log( "[TaxonomyInheritance] [v4.0] Aggregated taxonomy data - subjects: " . json_encode( $aggregated_taxonomies['subjects'] ) );
			error_log( "[TaxonomyInheritance] [v4.0] Aggregated taxonomy data - geography: " . json_encode( $aggregated_taxonomies['geography'] ) );
			error_log( "[TaxonomyInheritance] [v4.0] Aggregated taxonomy data - tags: " . json_encode( $aggregated_taxonomies['tags'] ) );

			// Update Magazine taxonomy terms using WordPress native functions
			// We bypass ACF and use wp_set_object_terms() directly for better reliability
			$update_errors = array();

			error_log( "[TaxonomyInheritance] [v4.0] Calling wp_set_object_terms() for time_periods" );
			$time_periods_result = wp_set_object_terms( $magazine_id, $aggregated_taxonomies['time_periods'], 'time-period', false );
			error_log( "[TaxonomyInheritance] [v4.0] time_periods wp_set_object_terms() returned: " . ( is_wp_error( $time_periods_result ) ? 'WP_Error: ' . $time_periods_result->get_error_message() : var_export( $time_periods_result, true ) ) );
			if ( is_wp_error( $time_periods_result ) ) {
				$update_errors[] = 'time_periods';
				error_log( "[TaxonomyInheritance] [v4.0] ERROR: Failed to update time_periods for Magazine {$magazine_id}: " . $time_periods_result->get_error_message() );
			}

			error_log( "[TaxonomyInheritance] [v4.0] Calling wp_set_object_terms() for subjects" );
			$subjects_result = wp_set_object_terms( $magazine_id, $aggregated_taxonomies['subjects'], 'subject', false );
			error_log( "[TaxonomyInheritance] [v4.0] subjects wp_set_object_terms() returned: " . ( is_wp_error( $subjects_result ) ? 'WP_Error: ' . $subjects_result->get_error_message() : var_export( $subjects_result, true ) ) );
			if ( is_wp_error( $subjects_result ) ) {
				$update_errors[] = 'subjects';
				error_log( "[TaxonomyInheritance] [v4.0] ERROR: Failed to update subjects for Magazine {$magazine_id}: " . $subjects_result->get_error_message() );
			}

			error_log( "[TaxonomyInheritance] [v4.0] Calling wp_set_object_terms() for geography" );
			$geography_result = wp_set_object_terms( $magazine_id, $aggregated_taxonomies['geography'], 'geographic-area', false );
			error_log( "[TaxonomyInheritance] [v4.0] geography wp_set_object_terms() returned: " . ( is_wp_error( $geography_result ) ? 'WP_Error: ' . $geography_result->get_error_message() : var_export( $geography_result, true ) ) );
			if ( is_wp_error( $geography_result ) ) {
				$update_errors[] = 'geography';
				error_log( "[TaxonomyInheritance] [v4.0] ERROR: Failed to update geography for Magazine {$magazine_id}: " . $geography_result->get_error_message() );
			}

			error_log( "[TaxonomyInheritance] [v4.0] Calling wp_set_object_terms() for tags" );
			$tags_result = wp_set_object_terms( $magazine_id, $aggregated_taxonomies['tags'], 'product_tag', false );
			error_log( "[TaxonomyInheritance] [v4.0] tags wp_set_object_terms() returned: " . ( is_wp_error( $tags_result ) ? 'WP_Error: ' . $tags_result->get_error_message() : var_export( $tags_result, true ) ) );
			if ( is_wp_error( $tags_result ) ) {
				$update_errors[] = 'tags';
				error_log( "[TaxonomyInheritance] [v4.0] ERROR: Failed to update tags for Magazine {$magazine_id}: " . $tags_result->get_error_message() );
			}

			if ( ! empty( $update_errors ) ) {
				error_log( "[TaxonomyInheritance] [v4.0] ERROR: Failed to update taxonomies for Magazine {$magazine_id}: " . implode( ', ', $update_errors ) );
				return false;
			}

			error_log( "[TaxonomyInheritance] [v4.0] All wp_set_object_terms() calls completed successfully" );

			// Verify what's actually in the database after updates
			$verify_time_periods = get_field( 'field_68e48ee04bd49', $magazine_id );
			$verify_subjects = get_field( 'field_68e48f82a6a61', $magazine_id );
			$verify_geography = get_field( 'field_68e48fa2a6a62', $magazine_id );
			$verify_tags = get_field( 'field_68fc6caa6cfe9', $magazine_id );

			error_log( "[TaxonomyInheritance] [v4.0] VERIFICATION - time_periods in DB: " . json_encode( $verify_time_periods ) );
			error_log( "[TaxonomyInheritance] [v4.0] VERIFICATION - subjects in DB: " . json_encode( $verify_subjects ) );
			error_log( "[TaxonomyInheritance] [v4.0] VERIFICATION - geography in DB: " . json_encode( $verify_geography ) );
			error_log( "[TaxonomyInheritance] [v4.0] VERIFICATION - tags in DB: " . json_encode( $verify_tags ) );

			error_log( "[TaxonomyInheritance] [v4.0] Successfully updated all taxonomy terms for Magazine {$magazine_id}" );

			// Clear product caches
			wc_delete_product_transients( $magazine_id );

			return true;

		} catch ( Exception $e ) {
			error_log( "[TaxonomyInheritance] EXCEPTION in update_magazine_taxonomies(): " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get all Articles linked to a specific Magazine
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return array Array of WP_Post objects.
	 */
	private function get_articles_for_magazine( $magazine_id ) {
		error_log( "[TaxonomyInheritance] Searching for Articles linked to Magazine {$magazine_id}" );

		// Query Articles that have this Magazine as parent_publication
		// Uses OR relation to handle both string and integer serialization formats
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => ProductCategoryResolver::slug( 'articles' ),
				),
			),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'article_details_parent_publication',
					'value'   => '"' . $magazine_id . '"',  // String format: s:3:"689"
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'article_details_parent_publication',
					'value'   => 'i:' . $magazine_id . ';',  // Integer format: i:689;
					'compare' => 'LIKE',
				),
			),
		);

		$query = new \WP_Query( $args );

		error_log( "[TaxonomyInheritance] Found " . count( $query->posts ) . " Article(s) for Magazine {$magazine_id}" );
		if ( ! empty( $query->posts ) ) {
			$article_ids = array_map( function( $post ) {
				return $post->ID;
			}, $query->posts );
			error_log( "[TaxonomyInheritance] Article IDs: " . implode( ', ', $article_ids ) );
		}

		return $query->posts;
	}

	/**
	 * Clear all taxonomy terms from a Magazine
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return bool True on success, false on failure.
	 */
	private function clear_magazine_taxonomies( $magazine_id ) {
		try {
			// Validate Magazine ID
			if ( ! $magazine_id || ! get_post_status( $magazine_id ) ) {
				error_log( "[TaxonomyInheritance] ERROR: Invalid Magazine ID {$magazine_id} in clear_magazine_taxonomies()" );
				return false;
			}

			// Clear each taxonomy field individually using ACF field keys
			// Note: ACF returns false for empty taxonomy updates, which is expected behavior
			// We rely on wp_set_object_terms() below for actual clearing
			update_field( 'field_68e48ee04bd49', array(), $magazine_id );  // time_periods
			update_field( 'field_68e48f82a6a61', array(), $magazine_id );  // subjects
			update_field( 'field_68e48fa2a6a62', array(), $magazine_id );  // geography
			update_field( 'field_68fc6caa6cfe9', array(), $magazine_id );  // tags

			// Also clear WordPress taxonomy terms directly
			wp_set_object_terms( $magazine_id, array(), 'time-period', false );
			wp_set_object_terms( $magazine_id, array(), 'subject', false );
			wp_set_object_terms( $magazine_id, array(), 'geographic-area', false );
			wp_set_object_terms( $magazine_id, array(), 'product_tag', false );

			// Clear product caches
			wc_delete_product_transients( $magazine_id );

			error_log( "[TaxonomyInheritance] Successfully cleared all taxonomy fields for Magazine {$magazine_id}" );

			return true;

		} catch ( Exception $e ) {
			error_log( "[TaxonomyInheritance] EXCEPTION in clear_magazine_taxonomies(): " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Add admin notice and CSS/JS to disable Magazine taxonomy fields
	 * Only applies to Magazines, not Articles or Books.
	 *
	 * @return void
	 */
	public function add_magazine_taxonomy_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'product' ) {
			return;
		}

		$magazine_term_id = ProductCategoryResolver::id( 'magazines' );

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
					// Check if the product type field has the current site's Magazines term selected.
					var $typeField = $('.acf-field[data-name="type"]');

					if ($typeField.length) {
						var $select = $typeField.find('select');

						if ($select.length && String($select.val()) === String(<?php echo wp_json_encode( $magazine_term_id ); ?>)) {
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
		if ( ! $post || ! ProductCategoryResolver::has( $post->ID, 'articles' ) ) {
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
