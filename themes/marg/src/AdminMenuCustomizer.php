<?php
/**
 * Admin Menu Customizer
 *
 * Reorganizes the WordPress admin menu by grouping content-related items
 * under a single "Content" menu.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * AdminMenuCustomizer class
 */
class AdminMenuCustomizer {

	/**
	 * Initialize the admin menu customizations
	 *
	 * @return void
	 */
	public function init() {
		// Remove taxonomy capabilities from Contributors
		add_action( 'init', array( $this, 'remove_contributor_taxonomy_caps' ) );

		// Grant WooCommerce product capabilities to Editors
		add_action( 'init', array( $this, 'grant_editor_woocommerce_caps' ) );

		// Grant custom post type capabilities to Editors and Administrators
		add_action( 'init', array( $this, 'grant_custom_post_type_caps' ) );

		// Enable revisions for WooCommerce products
		add_filter( 'woocommerce_register_post_type_product', array( $this, 'enable_product_revisions' ) );

		// Enable revisions for Events post type
		add_action( 'init', array( $this, 'enable_event_revisions' ), 20 );

		// Enable revisions for Person post type
		add_action( 'init', array( $this, 'enable_person_revisions' ), 20 );

		// Restrict global-settings page to Administrators only
		add_action( 'admin_init', array( $this, 'restrict_global_settings_access' ) );

		// Hook into admin_menu with high priority to ensure all menus are registered
		add_action( 'admin_menu', array( $this, 'create_taxonomies_menu' ), 998 );
		add_action( 'admin_menu', array( $this, 'create_advanced_menu' ), 998 );
		add_action( 'admin_menu', array( $this, 'reorganize_taxonomies_menu' ), 999 );
		add_action( 'admin_menu', array( $this, 'reorganize_advanced_menu' ), 999 );
		add_action( 'admin_menu', array( $this, 'customize_products_submenu' ), 999 );
		add_action( 'admin_menu', array( $this, 'customize_people_submenu' ), 1000 );
		add_action( 'admin_menu', array( $this, 'reorder_post_type_menus' ), 9999 );

		// Hide unwanted separators with CSS
		add_action( 'admin_head', array( $this, 'hide_menu_separators_css' ) );

		// Adjust parent file for proper highlighting
		add_filter( 'parent_file', array( $this, 'adjust_parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'adjust_submenu_file' ), 10, 2 );

		// Reorder product meta boxes
		add_action( 'add_meta_boxes', array( $this, 'reorder_product_meta_boxes' ), 100 );

		// Add CSS to reorder product edit screen
		add_action( 'admin_head', array( $this, 'reorder_product_editor_css' ) );

		// Add JavaScript to disable Print fields when "Out of Print" is checked
		add_action( 'admin_head', array( $this, 'out_of_print_conditional_logic_js' ) );

		// Add JavaScript to disable Digital price fields when "Open Access" is checked
		add_action( 'admin_head', array( $this, 'open_access_conditional_logic_js' ) );
	}

	/**
	 * Restrict access to global-settings page to Administrators only
	 *
	 * @return void
	 */
	public function restrict_global_settings_access() {
		// Check if we're on the global-settings page
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'global-settings' ) {
			// Check if user is NOT an administrator
			if ( ! current_user_can( 'administrator' ) ) {
				wp_die(
					__( 'You do not have sufficient permissions to access this page.' ),
					403
				);
			}
		}
	}

	/**
	 * Remove taxonomy management capabilities from Contributors
	 *
	 * @return void
	 */
	public function remove_contributor_taxonomy_caps() {
		$contributor = get_role( 'contributor' );

		if ( $contributor ) {
			// Remove standard WordPress taxonomy capabilities
			$contributor->remove_cap( 'manage_categories' );
			$contributor->remove_cap( 'edit_categories' );
			$contributor->remove_cap( 'delete_categories' );
			$contributor->remove_cap( 'assign_categories' );

			// Remove WooCommerce product taxonomy capabilities
			$contributor->remove_cap( 'manage_product_terms' );
			$contributor->remove_cap( 'edit_product_terms' );
			$contributor->remove_cap( 'delete_product_terms' );
			$contributor->remove_cap( 'assign_product_terms' );

			// Add capabilities to edit all content (not just their own)
			// But NOT published content - this enables "Submit for Review" workflow
			$contributor->add_cap( 'edit_others_posts' );
			$contributor->add_cap( 'edit_others_pages' );

			// Remove these so "Submit for Review" workflow is enforced
			$contributor->remove_cap( 'edit_published_posts' );
			$contributor->remove_cap( 'edit_published_pages' );

			// Add capabilities for custom post types - People
			$contributor->add_cap( 'edit_people' );
			$contributor->add_cap( 'edit_others_people' );
			// Don't allow editing published people - enforce review workflow
			$contributor->remove_cap( 'edit_published_people' );

			// Add capabilities for custom post types - Events
			$contributor->add_cap( 'edit_events' );
			$contributor->add_cap( 'edit_others_events' );
			// Don't allow editing published events - enforce review workflow
			$contributor->remove_cap( 'edit_published_events' );

			// Add capabilities for custom post types - Products (WooCommerce)
			$contributor->add_cap( 'edit_products' );
			$contributor->add_cap( 'edit_others_products' );
			// Don't allow editing published products - enforce review workflow
			$contributor->remove_cap( 'edit_published_products' );
		}
	}

	/**
	 * Grant WooCommerce product management capabilities to Editors
	 *
	 * @return void
	 */
	public function grant_editor_woocommerce_caps() {
		$editor = get_role( 'editor' );

		if ( $editor ) {
			// WooCommerce product capabilities
			$editor->add_cap( 'edit_products' );
			$editor->add_cap( 'read_product' );
			$editor->add_cap( 'delete_products' );
			$editor->add_cap( 'edit_others_products' );
			$editor->add_cap( 'publish_products' );
			$editor->add_cap( 'read_private_products' );
			$editor->add_cap( 'delete_private_products' );
			$editor->add_cap( 'delete_published_products' );
			$editor->add_cap( 'delete_others_products' );
			$editor->add_cap( 'edit_private_products' );
			$editor->add_cap( 'edit_published_products' );

			// Product taxonomy capabilities
			$editor->add_cap( 'manage_product_terms' );
			$editor->add_cap( 'edit_product_terms' );
			$editor->add_cap( 'delete_product_terms' );
			$editor->add_cap( 'assign_product_terms' );
		}
	}

	/**
	 * Grant custom post type capabilities to Editors and Administrators
	 *
	 * @return void
	 */
	public function grant_custom_post_type_caps() {
		$editor = get_role( 'editor' );
		$administrator = get_role( 'administrator' );

		// Custom post type capabilities for People
		$people_caps = array(
			'edit_people',
			'read_people',
			'delete_people',
			'edit_others_people',
			'publish_people',
			'read_private_people',
			'delete_private_people',
			'delete_published_people',
			'delete_others_people',
			'edit_private_people',
			'edit_published_people',
		);

		// Custom post type capabilities for Events
		$events_caps = array(
			'edit_events',
			'read_events',
			'delete_events',
			'edit_others_events',
			'publish_events',
			'read_private_events',
			'delete_private_events',
			'delete_published_events',
			'delete_others_events',
			'edit_private_events',
			'edit_published_events',
		);

		// Grant capabilities to Editors
		if ( $editor ) {
			foreach ( $people_caps as $cap ) {
				$editor->add_cap( $cap );
			}
			foreach ( $events_caps as $cap ) {
				$editor->add_cap( $cap );
			}
		}

		// Grant capabilities to Administrators
		if ( $administrator ) {
			foreach ( $people_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
			foreach ( $events_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * Enable revisions for WooCommerce products
	 *
	 * @param array $args Product post type arguments.
	 * @return array Modified arguments with revisions support.
	 */
	public function enable_product_revisions( $args ) {
		$args['supports'][] = 'revisions';
		return $args;
	}

	/**
	 * Enable revisions for Events post type
	 *
	 * @return void
	 */
	public function enable_event_revisions() {
		add_post_type_support( 'event', 'revisions' );
	}

	/**
	 * Enable revisions for Person post type
	 *
	 * @return void
	 */
	public function enable_person_revisions() {
		add_post_type_support( 'person', 'revisions' );
	}

	/**
	 * Create the Taxonomies menu
	 *
	 * @return void
	 */
	public function create_taxonomies_menu() {
		add_menu_page(
			'Taxonomies',                // Page title
			'Taxonomies',                // Menu title
			'manage_categories',         // Capability
			'taxonomies-menu',           // Menu slug
			'',                         // Function (empty as this is just a parent)
			'dashicons-tag',            // Icon
			25                          // Position (after Comments, before Appearance)
		);
	}

	/**
	 * Reorganize taxonomies menu
	 *
	 * @return void
	 */
	public function reorganize_taxonomies_menu() {
		global $submenu;

		// Remove taxonomies from post type submenus first
		// Categories and Tags (Posts)
		if ( isset( $submenu['edit.php'] ) ) {
			foreach ( $submenu['edit.php'] as $key => $item ) {
				if ( isset( $item[2] ) && (
					strpos( $item[2], 'edit-tags.php?taxonomy=category' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=post_tag' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=subject' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=geographic-area' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=time-period' ) !== false
				) ) {
					unset( $submenu['edit.php'][$key] );
				}
			}
		}

		// Remove taxonomies from Products submenu
		if ( isset( $submenu['edit.php?post_type=product'] ) ) {
			foreach ( $submenu['edit.php?post_type=product'] as $key => $item ) {
				if ( isset( $item[2] ) && (
					strpos( $item[2], 'edit-tags.php?taxonomy=product_cat' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=product_tag' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=subject' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=geographic-area' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=time-period' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=pa_' ) !== false
				) ) {
					unset( $submenu['edit.php?post_type=product'][$key] );
				}
			}
		}

		// Remove taxonomies from Events submenu
		if ( isset( $submenu['edit.php?post_type=event'] ) ) {
			foreach ( $submenu['edit.php?post_type=event'] as $key => $item ) {
				if ( isset( $item[2] ) && (
					strpos( $item[2], 'edit-tags.php?taxonomy=event-type' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=subject' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=geographic-area' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=time-period' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=post_tag' ) !== false
				) ) {
					unset( $submenu['edit.php?post_type=event'][$key] );
				}
			}
		}

		// Remove taxonomies from People submenu
		if ( isset( $submenu['edit.php?post_type=person'] ) ) {
			foreach ( $submenu['edit.php?post_type=person'] as $key => $item ) {
				if ( isset( $item[2] ) && (
					strpos( $item[2], 'edit-tags.php?taxonomy=contributor-type' ) !== false ||
					strpos( $item[2], 'edit-tags.php?taxonomy=person-type' ) !== false
				) ) {
					unset( $submenu['edit.php?post_type=person'][$key] );
				}
			}
		}

		// Remove taxonomies from Media submenu (Subjects for attachments)
		if ( isset( $submenu['upload.php'] ) ) {
			foreach ( $submenu['upload.php'] as $key => $item ) {
				if ( isset( $item[2] ) && (
					strpos( $item[2], 'edit-tags.php?taxonomy=subject' ) !== false
				) ) {
					unset( $submenu['upload.php'][$key] );
				}
			}
		}

		// Add taxonomies in the desired order
		// 1. People Types
		add_submenu_page(
			'taxonomies-menu',
			'People Types',
			'People Types',
			'manage_categories',
			'edit-tags.php?taxonomy=person-type'
		);

		// 2. Contributor Types
		add_submenu_page(
			'taxonomies-menu',
			'Contributor Types',
			'Contributor Types',
			'manage_categories',
			'edit-tags.php?taxonomy=contributor-type'
		);

		// 3. Product Categories
		add_submenu_page(
			'taxonomies-menu',
			'Product Categories',
			'Product Categories',
			'manage_product_terms',
			'edit-tags.php?taxonomy=product_cat'
		);

		// 4. Product Tags
		add_submenu_page(
			'taxonomies-menu',
			'Product Tags',
			'Product Tags',
			'manage_product_terms',
			'edit-tags.php?taxonomy=product_tag'
		);

		// 5. Product Formats (attributes)
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $attribute ) {
				$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
				add_submenu_page(
					'taxonomies-menu',
					$attribute->attribute_label,
					$attribute->attribute_label,
					'manage_product_terms',
					'edit-tags.php?taxonomy=' . $taxonomy
				);
			}
		}

		// 6. Subjects
		add_submenu_page(
			'taxonomies-menu',
			'Subjects',
			'Subjects',
			'manage_categories',
			'edit-tags.php?taxonomy=subject'
		);

		// 7. Geographic Areas
		add_submenu_page(
			'taxonomies-menu',
			'Geographic Areas',
			'Geographic Areas',
			'manage_categories',
			'edit-tags.php?taxonomy=geographic-area'
		);

		// 8. Time Periods
		add_submenu_page(
			'taxonomies-menu',
			'Time Periods',
			'Time Periods',
			'manage_categories',
			'edit-tags.php?taxonomy=time-period'
		);

		// 9. Event Types
		add_submenu_page(
			'taxonomies-menu',
			'Event Types',
			'Event Types',
			'manage_categories',
			'edit-tags.php?taxonomy=event-type'
		);

		// 10. Post Categories
		add_submenu_page(
			'taxonomies-menu',
			'Post Categories',
			'Post Categories',
			'manage_categories',
			'edit-tags.php?taxonomy=category'
		);

		// 11. Post Tags
		add_submenu_page(
			'taxonomies-menu',
			'Post Tags',
			'Post Tags',
			'manage_categories',
			'edit-tags.php?taxonomy=post_tag'
		);

		// Remove the empty parent menu item (first item) from Taxonomies submenu
		if ( isset( $submenu['taxonomies-menu'] ) && ! empty( $submenu['taxonomies-menu'] ) ) {
			unset( $submenu['taxonomies-menu'][0] );
		}
	}

	/**
	 * Create the Advanced menu
	 *
	 * @return void
	 */
	public function create_advanced_menu() {
		add_menu_page(
			'Advanced',                  // Page title
			'Advanced',                  // Menu title
			'manage_options',           // Capability
			'advanced-menu',            // Menu slug
			'',                        // Function (empty as this is just a parent)
			'dashicons-admin-tools',   // Icon
			90                         // Position (near bottom)
		);
	}

	/**
	 * Reorganize advanced/plugin menu items
	 *
	 * @return void
	 */
	public function reorganize_advanced_menu() {
		global $menu, $submenu;

		// Move Updates from Dashboard to Advanced
		if ( isset( $submenu['index.php'] ) ) {
			foreach ( $submenu['index.php'] as $key => $item ) {
				if ( isset( $item[2] ) && $item[2] === 'update-core.php' ) {
					add_submenu_page(
						'advanced-menu',
						'Updates',
						'Updates',
						$item[1],
						'update-core.php'
					);
					unset( $submenu['index.php'][$key] );
					break;
				}
			}
		}

		// Add Appearance as submenu under Advanced
		add_submenu_page(
			'advanced-menu',
			'Appearance',
			'Appearance',
			'switch_themes',
			'themes.php'
		);

		// Add Plugins as submenu under Advanced
		add_submenu_page(
			'advanced-menu',
			'Plugins',
			'Plugins',
			'activate_plugins',
			'plugins.php'
		);

		// Add Users as submenu under Advanced
		add_submenu_page(
			'advanced-menu',
			'Users',
			'Users',
			'list_users',
			'users.php'
		);

		// Add Tools as submenu under Advanced
		add_submenu_page(
			'advanced-menu',
			'Tools',
			'Tools',
			'manage_options',
			'tools.php'
		);

		// Add Settings as submenu under Advanced
		add_submenu_page(
			'advanced-menu',
			'Settings',
			'Settings',
			'manage_options',
			'options-general.php'
		);

		// Remove the original menu items
		remove_menu_page( 'themes.php' );           // Appearance
		remove_menu_page( 'plugins.php' );          // Plugins
		remove_menu_page( 'users.php' );            // Users
		remove_menu_page( 'tools.php' );            // Tools
		remove_menu_page( 'options-general.php' );  // Settings
		remove_menu_page( 'profile.php' );          // Profile

		// Remove WooCommerce top-level menu and add to Advanced
		if ( class_exists( 'WooCommerce' ) ) {
			// Remove WooCommerce top-level menu
			remove_menu_page( 'woocommerce' );

			// Add WooCommerce Settings to Advanced submenu
			add_submenu_page(
				'advanced-menu',
				'WooCommerce',
				'WooCommerce',
				'manage_woocommerce',
				'admin.php?page=wc-settings'
			);
		}

		// Add Analytics (if WooCommerce Analytics exists)
		if ( isset( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[0] ) && strpos( $item[0], 'Analytics' ) !== false ) {
					add_submenu_page(
						'advanced-menu',
						'Analytics',
						'Analytics',
						$item[1],
						$item[2]
					);
					remove_menu_page( $item[2] );
				}
			}
		}

		// Add Coupons (WooCommerce Coupons)
		add_submenu_page(
			'advanced-menu',
			'Coupons',
			'Coupons',
			'manage_woocommerce',
			'edit.php?post_type=shop_coupon'
		);

		// Add ACF (Advanced Custom Fields)
		if ( class_exists( 'ACF' ) ) {
			add_submenu_page(
				'advanced-menu',
				'ACF',
				'ACF',
				'manage_options',
				'edit.php?post_type=acf-field-group'
			);
			remove_menu_page( 'edit.php?post_type=acf-field-group' );
		}

		// Add FileBird
		add_submenu_page(
			'advanced-menu',
			'FileBird',
			'FileBird',
			'manage_options',
			'filebird-settings'
		);
		remove_menu_page( 'filebird-settings' );

		// Add Payments (WooCommerce Payments if exists)
		// Try multiple possible menu slugs for Payments
		$payment_slugs = array(
			'wc-admin&path=/payments/overview',
			'wc-admin&path=/payments',
			'wcpay-overview',
			'wc-payments'
		);

		foreach ( $payment_slugs as $slug ) {
			remove_menu_page( $slug );
		}

		// Also check in the global menu array for any menu with "Payments" in the title
		if ( isset( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[0] ) && ( strpos( $item[0], 'Payments' ) !== false || strpos( $item[0], 'payments' ) !== false ) ) {
					add_submenu_page(
						'advanced-menu',
						'Payments',
						'Payments',
						isset( $item[1] ) ? $item[1] : 'manage_woocommerce',
						isset( $item[2] ) ? $item[2] : 'wc-admin&path=/payments'
					);
					unset( $menu[$key] );
					break;
				}
			}
		}

		// Add Code Snippets - check multiple possible menu slugs
		$snippets_slugs = array( 'snippets', 'code-snippets', 'edit-snippets' );
		$snippets_added = false;

		// Try to add by class existence first
		if ( class_exists( 'Code_Snippets' ) ) {
			add_submenu_page(
				'advanced-menu',
				'Snippets',
				'Snippets',
				'manage_options',
				'snippets'
			);
			$snippets_added = true;
		}

		// Remove all possible snippets menu slugs
		foreach ( $snippets_slugs as $slug ) {
			remove_menu_page( $slug );
		}

		// Also check in the global menu array for any menu with "Snippets" in the title
		if ( isset( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[0] ) && stripos( $item[0], 'Snippets' ) !== false ) {
					if ( ! $snippets_added ) {
						add_submenu_page(
							'advanced-menu',
							'Snippets',
							'Snippets',
							isset( $item[1] ) ? $item[1] : 'manage_options',
							isset( $item[2] ) ? $item[2] : 'snippets'
						);
					}
					unset( $menu[$key] );
					break;
				}
			}
		}

		// Add AltText.ai - check multiple possible menu slugs
		$alttext_slugs = array( 'atai', 'alttext', 'alttext-ai', 'alt-text-ai' );
		$alttext_added = false;

		// Remove all possible AltText.ai menu slugs
		foreach ( $alttext_slugs as $slug ) {
			remove_menu_page( $slug );
		}

		// Also check in the global menu array for any menu with "AltText" or "Alt Text" in the title
		if ( isset( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[0] ) && ( stripos( $item[0], 'AltText' ) !== false || stripos( $item[0], 'Alt Text' ) !== false || stripos( $item[0], 'atai' ) !== false ) ) {
					add_submenu_page(
						'advanced-menu',
						'AltText.ai',
						'AltText.ai',
						isset( $item[1] ) ? $item[1] : 'manage_options',
						isset( $item[2] ) ? $item[2] : 'atai'
					);
					unset( $menu[$key] );
					$alttext_added = true;
					break;
				}
			}
		}

		// If not found yet, add it with the known slug
		if ( ! $alttext_added ) {
			add_submenu_page(
				'advanced-menu',
				'AltText.ai',
				'AltText.ai',
				'manage_options',
				'atai'
			);
		}

		// Add WP Synchro
		add_submenu_page(
			'advanced-menu',
			'WP Synchro',
			'WP Synchro',
			'manage_options',
			'wpsynchro_menu'
		);
		remove_menu_page( 'wpsynchro_menu' );

		// Add WP All Export - check multiple possible menu slugs
		$export_slugs = array( 'pmxe-admin-export', 'pmxe-admin-manage', 'pmxe-admin-home' );
		$export_added = false;

		if ( class_exists( 'PMXE_Plugin' ) ) {
			add_submenu_page(
				'advanced-menu',
				'All Export',
				'All Export',
				'manage_options',
				'admin.php?page=pmxe-admin-export'
			);
			$export_added = true;
		}

		// Remove all possible export menu slugs
		foreach ( $export_slugs as $slug ) {
			remove_menu_page( $slug );
		}

		// Also check in the global menu array for any menu with "Export" in the title
		if ( isset( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( isset( $item[0] ) && ( stripos( $item[0], 'All Export' ) !== false || ( stripos( $item[0], 'Export' ) !== false && stripos( $item[2], 'pmxe' ) !== false ) ) ) {
					if ( ! $export_added ) {
						// Use the original menu slug if available, otherwise use the proper format
						$export_slug = isset( $item[2] ) ? $item[2] : 'admin.php?page=pmxe-admin-export';
						// If it's just a slug without admin.php, add it
						if ( strpos( $export_slug, 'admin.php' ) === false && strpos( $export_slug, '?' ) === false ) {
							$export_slug = 'admin.php?page=' . $export_slug;
						}
						add_submenu_page(
							'advanced-menu',
							'All Export',
							'All Export',
							isset( $item[1] ) ? $item[1] : 'manage_options',
							$export_slug
						);
					}
					unset( $menu[$key] );
					break;
				}
			}
		}

		// Add WP All Import
		if ( class_exists( 'PMXI_Plugin' ) ) {
			add_submenu_page(
				'advanced-menu',
				'All Import',
				'All Import',
				'manage_options',
				'pmxi-admin-home'
			);
			remove_menu_page( 'pmxi-admin-home' );
		}

		// Add WP Pusher
		add_submenu_page(
			'advanced-menu',
			'WP Pusher',
			'WP Pusher',
			'manage_options',
			'wppusher'
		);
		remove_menu_page( 'wppusher' );

		// Add Kinsta Cache
		add_submenu_page(
			'advanced-menu',
			'Kinsta Cache',
			'Kinsta Cache',
			'manage_options',
			'kinsta-tools'
		);
		remove_menu_page( 'kinsta-tools' );

		// Check for any remaining plugin menus and move them
		$plugin_keywords = array( 'Marketing', 'Analytics', 'Payments', 'Snippets', 'AltText', 'Alt Text', 'All Export', 'All Import' );

		if ( isset( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $key => $item ) {
				if ( ! isset( $item[0] ) || ! isset( $item[2] ) ) {
					continue;
				}

				// Skip core WordPress menus we've already handled
				$core_menus = array( 'index.php', 'upload.php', 'edit-comments.php', 'profile.php' );
				if ( in_array( $item[2], $core_menus, true ) ) {
					continue;
				}

				// Check if this menu item matches any of our plugin keywords
				foreach ( $plugin_keywords as $keyword ) {
					if ( stripos( $item[0], $keyword ) !== false ) {
						add_submenu_page(
							'advanced-menu',
							$item[0],
							$item[0],
							$item[1],
							$item[2]
						);
						unset( $menu[$key] );
						break;
					}
				}
			}
		}

		// Remove the empty parent menu item (first item) from Advanced submenu
		if ( isset( $submenu['advanced-menu'] ) && ! empty( $submenu['advanced-menu'] ) ) {
			unset( $submenu['advanced-menu'][0] );
		}
	}

	/**
	 * Customize the Products submenu to add product category links
	 *
	 * @return void
	 */
	public function customize_products_submenu() {
		global $submenu;

		// Product categories to add
		$product_categories = array(
			array(
				'name' => 'Magazines',
				'slug' => 'magazines',
			),
			array(
				'name' => 'Articles',
				'slug' => 'articles',
			),
			array(
				'name' => 'Books',
				'slug' => 'books',
			),
			array(
				'name' => 'Subscription Plans',
				'slug' => 'subscription-plans',
			),
		);

		// Add category submenu items
		foreach ( $product_categories as $category ) {
			add_submenu_page(
				'edit.php?post_type=product',
				$category['name'],
				$category['name'],
				'edit_products',
				'edit.php?post_type=product&product_cat=' . $category['slug']
			);
		}

		// Reorder submenu items to put categories after "All Products"
		if ( isset( $submenu['edit.php?post_type=product'] ) ) {
			$product_submenu = $submenu['edit.php?post_type=product'];
			$new_submenu = array();
			$category_items = array();
			$other_items = array();

			// Separate items
			foreach ( $product_submenu as $key => $item ) {
				if ( $key === 5 ) {
					// All Products - keep first
					$new_submenu[] = $item;
				} elseif ( isset( $item[2] ) && strpos( $item[2], 'product_cat=' ) !== false ) {
					// Product category items
					$category_items[] = $item;
				} else {
					// Other items
					$other_items[] = $item;
				}
			}

			// Add category items
			foreach ( $category_items as $item ) {
				$new_submenu[] = $item;
			}

			// Add remaining items
			foreach ( $other_items as $item ) {
				$new_submenu[] = $item;
			}

			// Replace the submenu
			$submenu['edit.php?post_type=product'] = $new_submenu;
		}
	}

	/**
	 * Customize the People submenu to add person type links
	 *
	 * @return void
	 */
	public function customize_people_submenu() {
		// Remove "Add New Person" submenu
		remove_submenu_page( 'edit.php?post_type=person', 'post-new.php?post_type=person' );

		// Get actual person-type taxonomy terms from the database
		$person_type_terms = get_terms( array(
			'taxonomy'   => 'person-type',
			'hide_empty' => false,
		) );

		// If no terms exist, create default ones or use fallback
		if ( is_wp_error( $person_type_terms ) || empty( $person_type_terms ) ) {
			// Fallback to hardcoded terms if taxonomy terms don't exist
			$person_types = array(
				array( 'name' => 'Contributors', 'slug' => 'contributors' ),
				array( 'name' => 'General Editor', 'slug' => 'general-editor' ),
				array( 'name' => 'Team', 'slug' => 'team' ),
			);
		} else {
			// Use actual taxonomy terms
			$person_types = array();
			foreach ( $person_type_terms as $term ) {
				$person_types[] = array(
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		// Add person type filters as submenu items
		foreach ( $person_types as $person_type ) {
			add_submenu_page(
				'edit.php?post_type=person',
				$person_type['name'],
				$person_type['name'],
				'edit_people', // Use the proper capability for the person post type
				'edit.php?post_type=person&person-type=' . $person_type['slug']
			);
		}
	}

	/**
	 * Reorder post type menus to desired order
	 *
	 * @return void
	 */
	public function reorder_post_type_menus() {
		global $menu;

		if ( ! isset( $menu ) || ! is_array( $menu ) ) {
			return;
		}

		// Create Orders menu item if WooCommerce exists
		$orders_item = null;
		if ( class_exists( 'WooCommerce' ) ) {
			$orders_item = array(
				'Orders',                    // Menu title
				'edit_shop_orders',          // Capability
				'admin.php?page=wc-orders',  // Menu slug/URL
				'Orders',                    // Page title
				'menu-top toplevel_page_wc-orders', // CSS classes
				'toplevel_page_wc-orders',   // ID
				'dashicons-list-view',       // Icon
			);
		}

		// Extract items we want to reorder
		$people_item = null;
		$products_item = null;
		$events_item = null;
		$posts_item = null;
		$pages_item = null;
		$other_items = array();

		foreach ( $menu as $key => $item ) {
			if ( ! isset( $item[2] ) ) {
				$other_items[$key] = $item;
				continue;
			}

			if ( strpos( $item[2], 'edit.php?post_type=person' ) !== false ) {
				$people_item = $item;
			} elseif ( strpos( $item[2], 'edit.php?post_type=product' ) !== false ) {
				$products_item = $item;
			} elseif ( strpos( $item[2], 'edit.php?post_type=event' ) !== false ) {
				$events_item = $item;
			} elseif ( $item[2] === 'edit.php' ) {
				$posts_item = $item;
			} elseif ( $item[2] === 'edit.php?post_type=page' ) {
				$pages_item = $item;
			} else {
				$other_items[$key] = $item;
			}
		}

		// Rebuild menu array with new order
		$new_menu = array();
		$pos = 0;

		// Add items before position 5
		foreach ( $other_items as $key => $item ) {
			if ( $key < 5 ) {
				$new_menu[$pos++] = $item;
			}
		}

		// Add our ordered items (Dashboard, Pages, People, Posts, Products, Orders, Events)
		if ( $pages_item ) {
			$new_menu[$pos++] = $pages_item;
		}
		if ( $people_item ) {
			$new_menu[$pos++] = $people_item;
		}
		if ( $posts_item ) {
			$new_menu[$pos++] = $posts_item;
		}
		if ( $products_item ) {
			$new_menu[$pos++] = $products_item;
		}
		if ( $orders_item ) {
			$new_menu[$pos++] = $orders_item;
		}
		if ( $events_item ) {
			$new_menu[$pos++] = $events_item;
		}

		// Add remaining items after position 5
		foreach ( $other_items as $key => $item ) {
			if ( $key >= 5 ) {
				$new_menu[$pos++] = $item;
			}
		}

		$menu = $new_menu;
	}

	/**
	 * Adjust parent file for proper menu highlighting
	 *
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function adjust_parent_file( $parent_file ) {
		global $current_screen;

		// Check if we're on a taxonomy page
		if ( isset( $current_screen->taxonomy ) ) {
			$taxonomies_to_move = array(
				'category',
				'post_tag',
				'product_cat',
				'product_tag',
				'contributor-type',
				'event-type',
				'geographic-area',
				'person-type',
				'subject',
				'time-period',
			);

			// Check if it's a product attribute taxonomy
			if ( strpos( $current_screen->taxonomy, 'pa_' ) === 0 ) {
				return 'taxonomies-menu';
			}

			if ( in_array( $current_screen->taxonomy, $taxonomies_to_move, true ) ) {
				return 'taxonomies-menu';
			}
		}

		// If we're on WooCommerce Orders page, keep it as its own top-level menu
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' ) {
			return 'admin.php?page=wc-orders';
		}

		// Check if we're on one of the advanced/plugin pages
		$advanced_pages = array(
			'update-core.php',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
			'woocommerce',
			'wc-settings',
			'admin.php?page=wc-settings',
			'woocommerce-marketing',
			'edit.php?post_type=acf-field-group',
			'filebird-settings',
			'snippets',
			'code-snippets',
			'edit-snippets',
			'atai',
			'alttext',
			'alttext-ai',
			'alt-text-ai',
			'wpsynchro_menu',
			'pmxe-admin-export',
			'admin.php?page=pmxe-admin-export',
			'pmxe-admin-manage',
			'pmxe-admin-home',
			'pmxi-admin-home',
			'wppusher',
			'kinsta-tools',
			'wc-admin&path=/payments/overview',
			'wc-admin&path=/payments',
			'wcpay-overview',
			'wc-payments'
		);

		if ( in_array( $parent_file, $advanced_pages, true ) ) {
			return 'advanced-menu';
		}

		// Also check if we're on a WP All Export page via GET parameter
		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'pmxe' ) === 0 ) {
			return 'advanced-menu';
		}

		// Check if parent file contains payments
		if ( strpos( $parent_file, 'payments' ) !== false || strpos( $parent_file, 'Payments' ) !== false ) {
			return 'advanced-menu';
		}

		// Check for WooCommerce admin pages
		if ( isset( $current_screen->id ) && strpos( $current_screen->id, 'woocommerce' ) !== false ) {
			return 'advanced-menu';
		}

		return $parent_file;
	}

	/**
	 * Adjust submenu file for proper submenu highlighting
	 *
	 * @param string $submenu_file The submenu file.
	 * @param string $parent_file The parent file.
	 * @return string
	 */
	public function adjust_submenu_file( $submenu_file, $parent_file ) {
		global $current_screen;

		// If we're on a taxonomy page that we moved to the Taxonomies menu
		if ( isset( $current_screen->taxonomy ) && $parent_file === 'taxonomies-menu' ) {
			return 'edit-tags.php?taxonomy=' . $current_screen->taxonomy;
		}

		// If we're on a filtered product category page, highlight the category submenu item
		if ( $parent_file === 'edit.php?post_type=product' && isset( $_GET['product_cat'] ) ) {
			return 'edit.php?post_type=product&product_cat=' . sanitize_text_field( $_GET['product_cat'] );
		}

		// If we're on a filtered person type page, highlight the person type submenu item
		if ( $parent_file === 'edit.php?post_type=person' && isset( $_GET['person-type'] ) ) {
			return 'edit.php?post_type=person&person-type=' . sanitize_text_field( $_GET['person-type'] );
		}

		// If we're on WooCommerce Orders page, highlight it
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' ) {
			return 'admin.php?page=wc-orders';
		}

		return $submenu_file;
	}

	/**
	 * Hide unwanted menu separators with CSS
	 *
	 * @return void
	 */
	public function hide_menu_separators_css() {
		// Check if current user is NOT an administrator
		$hide_global_settings = ! current_user_can( 'administrator' );

		echo '<style>
			/* Hide separator between Events and Media */
			#menu-posts-event + .wp-menu-separator,
			/* Hide separator between Pages and People */
			#menu-pages + .wp-menu-separator,
			/* Hide separator between Media and Taxonomies */
			#menu-media + .wp-menu-separator {
				display: none !important;
			}

			/* Hide Profile menu */
			#menu-users {
				display: none !important;
			}';

		// Hide Global Settings for non-administrators
		if ( $hide_global_settings ) {
			echo '
			/* Hide Global Settings for non-administrators */
			#toplevel_page_global-settings {
				display: none !important;
			}';
		}

		echo '
			/* Reorder post type menus using flexbox */
			#adminmenu {
				display: flex !important;
				flex-direction: column !important;
			}

			/* Dashboard stays first */
			#menu-dashboard {
				order: 10 !important;
			}

			/* Post types in desired order: Dashboard, Pages, People, Posts, Products, Orders, Events */
			#menu-pages {
				order: 20 !important;
			}

			#menu-posts-person {
				order: 30 !important;
			}

			#menu-posts {
				order: 40 !important;
			}

			#menu-posts-product {
				order: 50 !important;
			}

			#toplevel_page_wc-orders {
				order: 60 !important;
			}

			#menu-posts-event {
				order: 70 !important;
			}

			/* Media after post types */
			#menu-media {
				order: 100 !important;
			}

			/* Custom menus */
			#toplevel_page_taxonomies-menu {
				order: 200 !important;
			}

			#toplevel_page_global-settings {
				order: 210 !important;
			}

			#toplevel_page_advanced-menu {
				order: 220 !important;
			}

			/* Menu separators */
			.wp-menu-separator {
				order: 1000 !important;
			}

			/* Collapse button at the end */
			#collapse-menu {
				order: 9999 !important;
			}
		</style>
		<script>
		jQuery(document).ready(function($) {
			// Backup: Physically reorder menu items in DOM
			// Order: Dashboard, Pages, People, Posts, Products, Orders, Events
			var dashboard = $("#menu-dashboard");
			var pages = $("#menu-pages");
			var people = $("#menu-posts-person");
			var posts = $("#menu-posts");
			var products = $("#menu-posts-product");
			var orders = $("#toplevel_page_wc-orders");
			var events = $("#menu-posts-event");

			if (dashboard.length && pages.length) {
				pages.insertAfter(dashboard);
			}
			if (pages.length && people.length) {
				people.insertAfter(pages);
			}
			if (people.length && posts.length) {
				posts.insertAfter(people);
			}
			if (posts.length && products.length) {
				products.insertAfter(posts);
			}
			if (products.length && orders.length) {
				orders.insertAfter(products);
			}
			if (orders.length && events.length) {
				events.insertAfter(orders);
			}
		});
		</script>';
	}

	/**
	 * Reorder product meta boxes to show description above short description
	 *
	 * @return void
	 */
	public function reorder_product_meta_boxes() {
		global $post, $wp_meta_boxes;

		// Only run on product edit screen
		if ( ! isset( $post->post_type ) || $post->post_type !== 'product' ) {
			return;
		}

		// Move Product Data meta box to high priority (appears first)
		remove_meta_box( 'woocommerce-product-data', 'product', 'normal' );
		add_meta_box(
			'woocommerce-product-data',
			__( 'Product Data', 'woocommerce' ),
			'WC_Meta_Box_Product_Data::output',
			'product',
			'normal',
			'high'
		);

		// Remove the short description (excerpt) meta box from its default position
		remove_meta_box( 'postexcerpt', 'product', 'normal' );

		// Re-add short description with low priority (appears after ACF fields)
		add_meta_box(
			'postexcerpt',
			__( 'Product short description', 'woocommerce' ),
			'post_excerpt_meta_box',
			'product',
			'normal',
			'low'
		);

		// Remove AltText.ai meta box from product pages
		remove_meta_box( 'atai-generate-meta-box', 'product', 'normal' );
		remove_meta_box( 'atai-generate-meta-box', 'product', 'side' );
		remove_meta_box( 'atai-generate-meta-box', 'product', 'advanced' );
	}

	/**
	 * Add CSS to reorder product editor elements
	 *
	 * @return void
	 */
	public function reorder_product_editor_css() {
		global $post;

		// Only on product edit screen
		if ( ! isset( $post->post_type ) || $post->post_type !== 'product' ) {
			return;
		}

		echo '<style>
			/* Use flexbox to reorder product edit screen elements */
			#post-body-content,
			#postbox-container-2 {
				display: flex;
				flex-direction: column;
			}

			/* Desired order:
			   1. Product title
			   2. Product Details (ACF - includes Book/Magazine/Article/Taxonomies Details as conditional group fields)
			   3. Product data
			   4. Product description (main editor)
			   5. Product short description
			   6. Memberships
			   Note: All publication type-specific fields now use ACF native conditional logic
			*/

			/* Product title - order -1 to ensure it comes first */
			#titlediv,
			#titlewrap {
				order: -1 !important;
			}

			/* Product Details (ACF) - order 10 */
			#acf-group_68ac2f305640f {
				order: 10 !important;
			}

			/* Product Data - order 20 */
			#woocommerce-product-data {
				order: 20 !important;
			}

			/* Product description (main editor) - order 30 */
			#postdivrich {
				order: 30 !important;
			}

			/* Product short description - order 40 */
			#postexcerpt {
				order: 40 !important;
			}

			/* Memberships - order 50 */
			#wc_memberships_restrict_product,
			#wc-memberships-product-memberships-data {
				order: 50 !important;
			}

			/* Other meta boxes - order 60 */
			.postbox {
				order: 60 !important;
			}

			/* Hide taxonomy boxes from sidebar */
			#product_catdiv,
			#tagsdiv-product_tag,
			#tagsdiv-geographic-area,
			#tagsdiv-subject,
			#tagsdiv-time-period {
				display: none !important;
			}
		</style>';
	}

	/**
	 * Add JavaScript to disable Print fields when "Out of Print" is checked
	 * Disables price, stock, and dimension fields while keeping SKU and ISSN/ISBN enabled
	 *
	 * @return void
	 */
	public function out_of_print_conditional_logic_js() {
		// Only on product edit pages
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		?>
		<style>
			/* Disabled out of print fields styling */
			.acf-field.acf-disabled-out-of-print {
				opacity: 0.5;
				pointer-events: none;
				position: relative;
			}

			.acf-field.acf-disabled-out-of-print::after {
				content: "Disabled - Out of Print";
				position: absolute;
				top: 5px;
				right: 10px;
				background: #dc3232;
				color: white;
				padding: 2px 8px;
				font-size: 11px;
				border-radius: 3px;
				font-weight: 600;
				z-index: 10;
			}
		</style>

		<script type="text/javascript">
		(function($) {
			'use strict';

			$(document).ready(function() {
				// Magazine Print Details fields to disable
				var magazineFieldKeys = [
					'field_print_price_inr',
					'field_print_sale_price_inr',
					'field_print_price_usd',
					'field_print_sale_price_usd',
					'field_print_stock_status',
					'field_print_stock_quantity',
					'field_print_weight',
					'field_print_length',
					'field_print_width',
					'field_print_height'
				];

				// Book Print Details fields to disable
				var bookFieldKeys = [
					'field_book_print_price_inr',
					'field_book_print_sale_price_inr',
					'field_book_print_price_usd',
					'field_book_print_sale_price_usd',
					'field_book_print_stock_status',
					'field_book_print_stock_quantity',
					'field_book_print_weight',
					'field_book_print_length',
					'field_book_print_width',
					'field_book_print_height'
				];

				/**
				 * Toggle fields enabled/disabled state based on out of print checkbox
				 */
				function toggleFields($checkbox, fieldKeys) {
					var isOutOfPrint = $checkbox.is(':checked');

					fieldKeys.forEach(function(key) {
						var $field = $('.acf-field[data-key="' + key + '"]');

						if (isOutOfPrint) {
							// Disable field
							$field.addClass('acf-disabled-out-of-print');
							$field.find('input, select, textarea').prop('disabled', true);
						} else {
							// Enable field
							$field.removeClass('acf-disabled-out-of-print');
							$field.find('input, select, textarea').prop('disabled', false);
						}
					});
				}

				/**
				 * Initialize out of print logic for Magazine Print Details
				 */
				function initMagazineOutOfPrint() {
					var $checkbox = $('.acf-field[data-key="field_print_out_of_print"] input[type="checkbox"]');

					if ($checkbox.length) {
						// Bind change event
						$checkbox.on('change', function() {
							toggleFields($(this), magazineFieldKeys);
						});

						// Trigger on page load to set initial state
						$checkbox.trigger('change');
					}
				}

				/**
				 * Initialize out of print logic for Book Print Details
				 */
				function initBookOutOfPrint() {
					var $checkbox = $('.acf-field[data-key="field_book_print_out_of_print"] input[type="checkbox"]');

					if ($checkbox.length) {
						// Bind change event
						$checkbox.on('change', function() {
							toggleFields($(this), bookFieldKeys);
						});

						// Trigger on page load to set initial state
						$checkbox.trigger('change');
					}
				}

				// Initialize both
				initMagazineOutOfPrint();
				initBookOutOfPrint();

				// Re-initialize when ACF fields are loaded/updated
				if (typeof acf !== 'undefined') {
					acf.addAction('ready', function() {
						initMagazineOutOfPrint();
						initBookOutOfPrint();
					});
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Add JavaScript to disable Digital fields when "Open Access" is checked
	 * Disables price fields while keeping PDF upload enabled
	 * Only applies to Magazines.
	 *
	 * @return void
	 */
	public function open_access_conditional_logic_js() {
		// Only on product edit pages
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		?>
		<style>
			/* Disabled open access fields styling */
			.acf-field.acf-disabled-open-access {
				opacity: 0.5;
				pointer-events: none;
				position: relative;
			}

			.acf-field.acf-disabled-open-access::after {
				content: "Disabled - Open Access";
				position: absolute;
				top: 5px;
				right: 10px;
				background: #ff8c00;
				color: white;
				padding: 2px 8px;
				font-size: 11px;
				border-radius: 3px;
				font-weight: 600;
				z-index: 10;
			}
		</style>

		<script type="text/javascript">
		(function($) {
			'use strict';

			$(document).ready(function() {
				// Magazine Digital Details fields to disable (excludes PDF upload)
				var magazineDigitalFieldKeys = [
					'field_digital_price_inr',
					'field_digital_sale_price_inr',
					'field_digital_price_usd',
					'field_digital_sale_price_usd'
				];

				/**
				 * Toggle fields enabled/disabled state based on open access checkbox
				 */
				function toggleFields($checkbox, fieldKeys) {
					var isOpenAccess = $checkbox.is(':checked');

					fieldKeys.forEach(function(key) {
						var $field = $('.acf-field[data-key="' + key + '"]');

						if (isOpenAccess) {
							// Disable field
							$field.addClass('acf-disabled-open-access');
							$field.find('input, select, textarea').prop('disabled', true);
						} else {
							// Enable field
							$field.removeClass('acf-disabled-open-access');
							$field.find('input, select, textarea').prop('disabled', false);
						}
					});
				}

				/**
				 * Initialize open access logic for Magazine Digital Details
				 */
				function initMagazineOpenAccess() {
					var $checkbox = $('.acf-field[data-key="field_68ef600be900f"] input[type="checkbox"]');

					if ($checkbox.length) {
						// Bind change event
						$checkbox.on('change', function() {
							toggleFields($(this), magazineDigitalFieldKeys);
						});

						// Trigger on page load to set initial state
						$checkbox.trigger('change');
					}
				}

				// Initialize
				initMagazineOpenAccess();

				// Re-initialize when ACF fields are loaded/updated
				if (typeof acf !== 'undefined') {
					acf.addAction('ready', function() {
						initMagazineOpenAccess();
					});

					acf.addAction('append', function() {
						initMagazineOpenAccess();
					});
				}
			});
		})(jQuery);
		</script>
		<?php
	}
}
