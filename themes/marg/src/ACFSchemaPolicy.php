<?php
/**
 * Keep production ACF schema changes in version control.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Restrict schema administration without restricting content values.
 */
class ACFSchemaPolicy {

	/**
	 * Register before ACF initializes its admin post types.
	 *
	 * @return void
	 */
	public function init() {
		if ( 'production' !== wp_get_environment_type() ) {
			return;
		}

		add_filter( 'acf/settings/show_admin', '__return_false' );
		add_filter( 'acf/settings/capability', array( $this, 'schema_capability' ) );
		add_filter( 'wp_ability_permission_result', array( $this, 'restrict_schema_ability' ), 10, 2 );
	}

	/**
	 * Deny direct schema admin access, including for administrators.
	 *
	 * @return string
	 */
	public function schema_capability() {
		return 'do_not_allow';
	}

	/**
	 * Novamira checks its own permissions independently of ACF's admin capability.
	 * Requires the WordPress 7.1 ability permission filter.
	 *
	 * @param bool|\WP_Error $permission Existing permission result.
	 * @param string         $name       Ability name.
	 * @return bool|\WP_Error
	 */
	public function restrict_schema_ability( $permission, $name ) {
		if ( preg_match( '~^novamira/acf-(create|edit|delete)-(fieldgroup|post-type|taxonomy|options-page)$~', $name ) ) {
			return new \WP_Error(
				'marg_acf_schema_locked',
				__( 'ACF schema changes are disabled on production. Update Local JSON in development and deploy the reviewed changes.', 'tenup' ),
				array( 'status' => 403 )
			);
		}

		return $permission;
	}
}
