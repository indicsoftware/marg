<?php
/**
 * Advanced admin menu organization.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Moves unlisted top-level admin menus below Advanced.
 */
class AdvancedAdminMenuOrganizer {

	/**
	 * Direct links for moved pages, keyed by their original slugs.
	 *
	 * @var array<string, string>
	 */
	private $moved_links = array();

	/**
	 * Move every top-level menu that the theme did not explicitly place.
	 * Child menus become direct links so their original callbacks and
	 * capabilities remain intact.
	 * @param array<int|string, array<int, string>>              $menu Top-level admin menu.
	 * @param array<string, array<int, array<int, string>>>      $submenu Admin submenus.
	 * @return void
	 */
	public function organize( &$menu, &$submenu ) {
		if ( ! is_array( $menu ) ) {
			return;
		}

		if ( ! isset( $submenu['advanced-menu'] ) || ! is_array( $submenu['advanced-menu'] ) ) {
			$submenu['advanced-menu'] = array();
		}

		$submenu['advanced-menu'] = array_values(
			array_filter(
				$submenu['advanced-menu'],
				function ( $item ) {
					return ! isset( $item[2] ) || 'advanced-menu' !== $item[2];
				}
			)
		);

		$this->move_dashboard_updates( $submenu );
		$allowed_slugs = $this->get_top_level_allowlist();

		foreach ( $menu as $key => $item ) {
			if ( ! isset( $item[2] ) || $this->is_separator( $item ) ) {
				continue;
			}

			$menu_slug = (string) $item[2];
			if ( in_array( $menu_slug, $allowed_slugs, true ) ) {
				continue;
			}

			$menu_title = $this->get_title( $item, $menu_slug );
			$capability = isset( $item[1] ) ? $item[1] : 'manage_options';
			$page_title = isset( $item[3] ) ? $item[3] : $menu_title;
			$children    = isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] )
				? $submenu[ $menu_slug ]
				: array();
			$destination = $this->get_top_level_link( $menu_slug, $children );

			$this->add_link(
				$submenu['advanced-menu'],
				$menu_title,
				$capability,
				$destination,
				$page_title
			);
			$this->remember_link( $menu_slug, $destination );
			$this->move_child_links( $children, $menu_title, $capability, $destination, $submenu['advanced-menu'] );

			unset( $menu[ $key ] );
		}

		$this->prioritize_accessible_link( $submenu['advanced-menu'] );
		$menu = array_values( $menu );
	}

	/**
	 * Get the direct destination for a moved slug.
	 * @param string $menu_slug Original menu slug.
	 * @return string|null
	 */
	public function get_moved_link( $menu_slug ) {
		return isset( $this->moved_links[ $menu_slug ] ) ? $this->moved_links[ $menu_slug ] : null;
	}

	/**
	 * Explicit menu destinations that stay at the top level.
	 * @return array<int, string>
	 */
	private function get_top_level_allowlist() {
		$slugs = array(
			'index.php',
			'edit.php?post_type=page',
			'edit.php?post_type=person',
			'edit.php',
			'edit.php?post_type=product',
			'admin.php?page=wc-orders',
			'edit.php?post_type=event',
			'upload.php',
			'taxonomies-menu',
			'global-settings',
			'advanced-menu',
		);

		return array_values( array_unique( (array) apply_filters( 'tenup_theme_admin_menu_allowlist', $slugs ) ) );
	}

	/**
	 * Move Updates out of Dashboard while preserving its capability.
	 * @param array<string, array<int, array<int, string>>> $submenu Admin submenus.
	 * @return void
	 */
	private function move_dashboard_updates( &$submenu ) {
		if ( ! isset( $submenu['index.php'] ) || ! is_array( $submenu['index.php'] ) ) {
			return;
		}

		foreach ( $submenu['index.php'] as $key => $item ) {
			if ( ! isset( $item[2] ) || 'update-core.php' !== $item[2] ) {
				continue;
			}

			$this->add_link(
				$submenu['advanced-menu'],
				$this->get_title( $item, 'Updates' ),
				isset( $item[1] ) ? $item[1] : 'update_core',
				'update-core.php',
				isset( $item[3] ) ? $item[3] : 'Updates'
			);
			$this->remember_link( 'update-core.php', 'update-core.php' );
			unset( $submenu['index.php'][ $key ] );
		}

		$submenu['index.php'] = array_values( $submenu['index.php'] );
	}

	/**
	 * Flatten a moved menu's children below Advanced.
	 * @param array<int, array<int, string>> $children Child menu items.
	 * @param string                         $parent_title Parent menu title.
	 * @param string                         $parent_capability Parent capability.
	 * @param string                         $parent_destination Parent direct link.
	 * @param array<int, array<int, string>> $advanced_items Advanced submenu items.
	 * @return void
	 */
	private function move_child_links( $children, $parent_title, $parent_capability, $parent_destination, &$advanced_items ) {
		foreach ( $children as $child_item ) {
			if ( ! isset( $child_item[2] ) ) {
				continue;
			}

			$child_slug        = (string) $child_item[2];
			$child_destination = $this->get_direct_link( $child_slug );

			if ( $child_destination === $parent_destination ) {
				continue;
			}

			$child_title = $this->get_title( $child_item, $child_slug );
			$this->add_link(
				$advanced_items,
				$parent_title . ' — ' . $child_title,
				isset( $child_item[1] ) ? $child_item[1] : $parent_capability,
				$child_destination,
				isset( $child_item[3] ) ? $child_item[3] : $child_title
			);
			$this->remember_link( $child_slug, $child_destination );
		}
	}

	/**
	 * Add a unique raw link without re-registering the original screen.
	 * @param array<int, array<int, string>> $advanced_items Advanced submenu items.
	 * @param string                         $menu_title Menu title.
	 * @param string                         $capability Required capability.
	 * @param string                         $destination Direct admin URL.
	 * @param string                         $page_title Page title.
	 * @return void
	 */
	private function add_link( &$advanced_items, $menu_title, $capability, $destination, $page_title ) {
		foreach ( $advanced_items as $existing_item ) {
			if ( isset( $existing_item[2] ) && $existing_item[2] === $destination ) {
				return;
			}
		}

		$advanced_items[] = array( $menu_title, $capability, $destination, $page_title );
	}

	/**
	 * Ensure Advanced opens a page the current user can access.
	 * @param array<int, array<int, string>> $advanced_items Advanced submenu items.
	 * @return void
	 */
	private function prioritize_accessible_link( &$advanced_items ) {
		foreach ( $advanced_items as $key => $item ) {
			if ( ! isset( $item[1] ) || ! current_user_can( $item[1] ) ) {
				continue;
			}

			if ( 0 !== $key ) {
				unset( $advanced_items[ $key ] );
				array_unshift( $advanced_items, $item );
				$advanced_items = array_values( $advanced_items );
			}

			return;
		}
	}

	/**
	 * Resolve the destination WordPress uses for a top-level menu.
	 * @param string                         $menu_slug Top-level menu slug.
	 * @param array<int, array<int, string>> $children Registered child items.
	 * @return string
	 */
	private function get_top_level_link( $menu_slug, $children ) {
		if ( isset( $children[0][2] ) ) {
			return $this->get_direct_link( (string) $children[0][2] );
		}

		return $this->get_direct_link( $menu_slug );
	}

	/**
	 * Convert a registered slug into the direct URL WordPress uses.
	 *
	 * @param string $menu_slug Registered menu slug.
	 * @return string
	 */
	private function get_direct_link( $menu_slug ) {
		if (
			preg_match( '#^(?:https?:)?//#i', $menu_slug ) ||
			0 === strpos( $menu_slug, '#' ) ||
			preg_match( '/^[^?]+\.php(?:\?|$)/', $menu_slug )
		) {
			return $menu_slug;
		}

		return 'admin.php?page=' . $menu_slug;
	}

	/**
	 * Remember a moved slug and any page parameter it contains.
	 *
	 * @param string $menu_slug Original registered slug.
	 * @param string $destination Direct admin URL.
	 * @return void
	 */
	private function remember_link( $menu_slug, $destination ) {
		if ( ! isset( $this->moved_links[ $menu_slug ] ) ) {
			$this->moved_links[ $menu_slug ] = $destination;
		}

		if ( preg_match( '/(?:^|[?&])page=([^&]+)/', $destination, $matches ) ) {
			$page_slug = rawurldecode( $matches[1] );
			if ( ! isset( $this->moved_links[ $page_slug ] ) ) {
				$this->moved_links[ $page_slug ] = $destination;
			}
		}
	}

	/**
	 * Get a readable label from a WordPress menu array.
	 *
	 * @param array<int, string> $item Menu item.
	 * @param string             $fallback Fallback label.
	 * @return string
	 */
	private function get_title( $item, $fallback ) {
		$title = isset( $item[0] ) ? trim( wp_strip_all_tags( (string) $item[0] ) ) : '';

		return '' !== $title ? $title : $fallback;
	}

	/**
	 * Determine whether an item is only a visual separator.
	 *
	 * @param array<int, string> $item Menu item.
	 * @return bool
	 */
	private function is_separator( $item ) {
		$slug    = isset( $item[2] ) ? (string) $item[2] : '';
		$classes = isset( $item[4] ) ? (string) $item[4] : '';

		return 0 === strpos( $slug, 'separator' ) || false !== strpos( $classes, 'wp-menu-separator' );
	}
}
