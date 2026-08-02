<?php
/**
 * ACF Duplicate Field Groups Cleanup Utility
 *
 * This script identifies and helps remove duplicate ACF field groups.
 *
 * Usage:
 * - Visit: /wp-admin/admin-ajax.php?action=acf_cleanup_duplicates&cleanup_key=marg2024
 * - Add &confirm=yes to actually delete duplicates (otherwise it just shows what would be deleted)
 */

// Check if we're in WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
	// Try to load WordPress
	$wp_load_paths = array(
		__DIR__ . '/../../../wp-load.php',
		__DIR__ . '/../../../../wp-load.php',
		__DIR__ . '/../../../../../wp-load.php',
	);

	$wp_loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$wp_loaded = true;
			break;
		}
	}

	if ( ! $wp_loaded ) {
		die( 'Error: Could not load WordPress.' );
	}
}

// Check if ACF is active
if ( ! class_exists( 'ACF' ) ) {
	die( 'Error: Advanced Custom Fields plugin is not active.' );
}

/**
 * Get all ACF field groups and identify duplicates
 */
function identify_duplicate_field_groups() {
	global $wpdb;

	// Get all ACF field groups
	$args = array(
		'post_type'      => 'acf-field-group',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'acf-disabled', 'trash' ),
		'orderby'        => 'ID',
		'order'          => 'ASC',
	);

	$field_groups = get_posts( $args );

	if ( empty( $field_groups ) ) {
		return array();
	}

	$groups_by_key = array();
	$duplicates = array();

	// Group field groups by their key
	foreach ( $field_groups as $post ) {
		$field_group = acf_get_field_group( $post->ID );

		if ( ! $field_group || ! isset( $field_group['key'] ) ) {
			continue;
		}

		$key = $field_group['key'];

		if ( ! isset( $groups_by_key[ $key ] ) ) {
			$groups_by_key[ $key ] = array();
		}

		$groups_by_key[ $key ][] = array(
			'ID'          => $post->ID,
			'post_title'  => $post->post_title,
			'post_status' => $post->post_status,
			'post_date'   => $post->post_date,
			'key'         => $key,
			'title'       => $field_group['title'],
			'modified'    => get_post_meta( $post->ID, '_acf_modified', true ),
		);
	}

	// Identify groups that have duplicates
	foreach ( $groups_by_key as $key => $groups ) {
		if ( count( $groups ) > 1 ) {
			// Sort by post date to keep the oldest (original) one
			usort( $groups, function( $a, $b ) {
				return strcmp( $a['post_date'], $b['post_date'] );
			});

			$duplicates[ $key ] = array(
				'keep'   => $groups[0], // Keep the oldest one
				'delete' => array_slice( $groups, 1 ), // Delete the rest
			);
		}
	}

	return $duplicates;
}

/**
 * Display the cleanup results
 */
function display_cleanup_results( $duplicates, $confirm = false ) {
	echo "<html><head><title>ACF Field Groups Cleanup</title>";
	echo "<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
		h1 { color: #23282d; }
		.duplicate-group { background: #f1f1f1; padding: 15px; margin: 20px 0; border-left: 4px solid #d63638; }
		.keep { background: #e7f7e7; border-left-color: #00a32a; }
		.delete { background: #fcf0f1; margin: 10px 0; padding: 10px; }
		.info { color: #666; font-size: 13px; }
		.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; margin: 10px 5px; }
		.button-danger { background: #d63638; }
		.summary { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
		th { background: #f9f9f9; }
		.status-acf-disabled { color: #996800; }
		.status-trash { color: #d63638; }
	</style></head><body>";

	echo "<h1>ACF Field Groups Duplicate Cleanup</h1>";

	if ( empty( $duplicates ) ) {
		echo "<div class='summary' style='border-left: 4px solid #00a32a;'>";
		echo "<h2 style='color: #00a32a;'>✓ No Duplicates Found</h2>";
		echo "<p>All ACF field groups are unique. No cleanup needed!</p>";
		echo "</div>";
		echo "</body></html>";
		return;
	}

	$total_to_delete = 0;
	foreach ( $duplicates as $groups ) {
		$total_to_delete += count( $groups['delete'] );
	}

	echo "<div class='summary'>";
	echo "<h2>Summary</h2>";
	echo "<p><strong>" . count( $duplicates ) . "</strong> field group(s) have duplicates</p>";
	echo "<p><strong>" . $total_to_delete . "</strong> duplicate field group(s) will be deleted</p>";
	echo "</div>";

	if ( $confirm ) {
		echo "<div class='summary' style='border-left: 4px solid #d63638;'>";
		echo "<h2 style='color: #d63638;'>⚠️ Deletion Mode Active</h2>";
		echo "<p>The duplicates listed below will be permanently deleted.</p>";
		echo "</div>";

		$deleted_count = 0;
		foreach ( $duplicates as $key => $groups ) {
			foreach ( $groups['delete'] as $group ) {
				wp_delete_post( $group['ID'], true ); // Force delete
				$deleted_count++;
			}
		}

		echo "<div class='summary' style='border-left: 4px solid #00a32a;'>";
		echo "<h2 style='color: #00a32a;'>✓ Cleanup Complete</h2>";
		echo "<p>Successfully deleted <strong>" . $deleted_count . "</strong> duplicate field group(s).</p>";
		echo "<p><a href='" . admin_url( 'edit.php?post_type=acf-field-group' ) . "' class='button'>View Field Groups</a></p>";
		echo "</div>";
	} else {
		echo "<h2>Duplicate Field Groups Found:</h2>";

		foreach ( $duplicates as $key => $groups ) {
			echo "<div class='duplicate-group'>";
			echo "<h3>Field Group Key: <code>" . esc_html( $key ) . "</code></h3>";

			// Show the one to keep
			echo "<div class='keep'>";
			echo "<h4>✓ KEEP (Original):</h4>";
			echo "<table>";
			echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th></tr>";
			$keep = $groups['keep'];
			echo "<tr>";
			echo "<td>#" . $keep['ID'] . "</td>";
			echo "<td><strong>" . esc_html( $keep['post_title'] ) . "</strong></td>";
			echo "<td class='status-" . $keep['post_status'] . "'>" . $keep['post_status'] . "</td>";
			echo "<td>" . $keep['post_date'] . "</td>";
			echo "</tr>";
			echo "</table>";
			echo "</div>";

			// Show the ones to delete
			echo "<div>";
			echo "<h4>✗ DELETE (Duplicates):</h4>";
			foreach ( $groups['delete'] as $delete ) {
				echo "<div class='delete'>";
				echo "<table>";
				echo "<tr>";
				echo "<td>ID: #" . $delete['ID'] . "</td>";
				echo "<td>Title: <strong>" . esc_html( $delete['post_title'] ) . "</strong></td>";
				echo "<td class='status-" . $delete['post_status'] . "'>Status: " . $delete['post_status'] . "</td>";
				echo "<td>Created: " . $delete['post_date'] . "</td>";
				echo "</tr>";
				echo "</table>";
				echo "</div>";
			}
			echo "</div>";

			echo "</div>";
		}

		echo "<div class='summary' style='margin-top: 40px; text-align: center;'>";
		echo "<h3>Ready to clean up?</h3>";
		echo "<p>Review the duplicates above. The oldest version of each field group will be kept.</p>";
		echo "<a href='?action=acf_cleanup_duplicates&cleanup_key=marg2024&confirm=yes' class='button button-danger' onclick='return confirm(\"Are you sure you want to delete " . $total_to_delete . " duplicate field group(s)? This cannot be undone.\")'>Delete All Duplicates</a>";
		echo "<a href='" . admin_url( 'edit.php?post_type=acf-field-group' ) . "' class='button'>Cancel</a>";
		echo "</div>";
	}

	echo "</body></html>";
}

// Handle AJAX request
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
	add_action( 'wp_ajax_acf_cleanup_duplicates', function() {
		// Security check
		if ( ! isset( $_GET['cleanup_key'] ) || $_GET['cleanup_key'] !== 'marg2024' ) {
			wp_die( 'Invalid cleanup key' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		$confirm = isset( $_GET['confirm'] ) && $_GET['confirm'] === 'yes';
		$duplicates = identify_duplicate_field_groups();
		display_cleanup_results( $duplicates, $confirm );

		die();
	});
}

// If accessed directly via WordPress
if ( ! defined( 'DOING_AJAX' ) && current_user_can( 'manage_options' ) ) {
	$confirm = isset( $_GET['confirm'] ) && $_GET['confirm'] === 'yes';
	$duplicates = identify_duplicate_field_groups();
	display_cleanup_results( $duplicates, $confirm );
}