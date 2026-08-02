<?php
/**
 * Person Post Type Functionality
 *
 * Handles custom functionality for the Person post type including
 * automatic title generation from ACF fields.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * PersonPostType class
 */
class PersonPostType {

	/**
	 * Initialize the Person post type functionality
	 *
	 * @return void
	 */
	public function init() {
		// Hook into save_post to generate title from ACF fields
		add_action( 'acf/save_post', array( $this, 'generate_person_title_from_name' ), 20 );

		// Optional: Hide the title field in the editor since it's auto-generated
		add_action( 'admin_head', array( $this, 'hide_person_title_field' ) );
	}

	/**
	 * Generate Person post title from First Name and Last Name ACF fields
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function generate_person_title_from_name( $post_id ) {
		// Check if this is a person post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return;
		}

		// Avoid infinite loops
		remove_action( 'acf/save_post', array( $this, 'generate_person_title_from_name' ), 20 );

		// Get ACF field values
		// Note: You may need to adjust these field names based on your actual ACF field keys
		$first_name = get_field( 'first_name', $post_id );
		$last_name = get_field( 'last_name', $post_id );

		// If we don't have both names, try alternative field names
		if ( empty( $first_name ) || empty( $last_name ) ) {
			// Try with field keys (you'll need to replace these with actual field keys)
			// Example: field_XXXXXXXXX
			// $first_name = get_field( 'field_XXXXXXXXX', $post_id );
			// $last_name = get_field( 'field_XXXXXXXXX', $post_id );
		}

		// Generate the title
		$title = '';
		if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
			$title = $first_name . ' ' . $last_name;
		} elseif ( ! empty( $first_name ) ) {
			$title = $first_name;
		} elseif ( ! empty( $last_name ) ) {
			$title = $last_name;
		}

		// Only update if we have a title to set
		if ( ! empty( $title ) ) {
			$post_data = array(
				'ID'         => $post_id,
				'post_title' => $title,
				'post_name'  => sanitize_title( $title ), // Also update the slug
			);

			// Update the post
			wp_update_post( $post_data );
		}

		// Re-hook this function
		add_action( 'acf/save_post', array( $this, 'generate_person_title_from_name' ), 20 );
	}

	/**
	 * Hide the title field in the Person edit screen
	 * Since the title is auto-generated, we can hide it from the editor
	 *
	 * @return void
	 */
	public function hide_person_title_field() {
		$screen = get_current_screen();

		if ( ! $screen || $screen->post_type !== 'person' ) {
			return;
		}

		$output = '<style>
			/* Hide the title field for Person post type */
			.post-type-person #titlediv {
				display: none;
			}

			/* Optional: Add a notice about auto-generated titles */
			.post-type-person #post-body-content:before {
				content: "Note: The person\'s name will be automatically generated from the First Name and Last Name fields.";
				display: block;
				background: #f0f0f1;
				border-left: 4px solid #2271b1;
				padding: 12px;
				margin-bottom: 20px;
				font-style: italic;
			}
		</style>';

		if ( $screen->base === 'edit' && isset( $_GET['person-type'] ) ) {
			$heading_overrides = array(
				'contributor'     => 'Contributors',
				'general-editor'  => 'General Editors',
				'team'            => 'Team',
			);
			$person_type = sanitize_key( $_GET['person-type'] );
			if ( isset( $heading_overrides[ $person_type ] ) ) {
				$output .= '<script>
					document.addEventListener("DOMContentLoaded", function() {
						var heading = document.querySelector(".wrap .wp-heading-inline");
						if ( heading ) {
							heading.textContent = "' . esc_js( $heading_overrides[ $person_type ] ) . '";
						}
					});
				</script>';
			}
		}

		echo $output;
	}
}