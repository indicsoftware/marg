# ACF schema ownership

The files in `themes/marg/acf-json/` are the deployable source of truth for ACF definitions. ACF registers the content types from these files. Keep the existing keys and slugs when editing definitions.

This directory contains 4 field groups, 2 custom post types, 6 custom taxonomies and one options page. The CPT and taxonomy files were exported from production on 8 September 2026 using ACF's native export functions, preserving their modification timestamps. Existing database definitions remain in place. This change performs no content migration or database deletion.

## Make schema changes

1. Use a development or staging WordPress installation with ACF PRO and the Marg theme. Set `WP_ENVIRONMENT_TYPE` to `local`, `development` or `staging` in that environment's configuration.
2. Pull the latest repository files. Sync available Local JSON definitions in ACF before editing database copies.
3. Make the schema changes in ACF. Saving writes the updated definition to the theme's `acf-json` directory. Check that this directory is writable.
4. Review and commit the JSON diff with any dependent theme changes. Test affected content forms and front-end pages before deploying.
5. Push approved changes to `main` through the normal deployment workflow. Verify the deployed schema using read-only MCP abilities and check that existing content still loads.

Local JSON loads at runtime without a production admin sync. ACF keeps database copies for its editor. Removing a JSON file alone can expose its old database definition again; deleting a content type or field group requires a separately reviewed migration. Renaming a slug also requires a content migration plan.

## Production restrictions

`ACFSchemaPolicy` uses `wp_get_environment_type()`. WordPress defaults to `production` when the environment type is unset. Keep the production host configured as production.

On production, the policy hides the ACF schema menu and sets ACF's schema capability to `do_not_allow`. This prevents direct schema administration, including by Administrators. The policy also denies Novamira's create, edit and delete abilities for field groups, post types, taxonomies and options-page definitions through the WordPress 7.1 `wp_ability_permission_result` filter. WordPress 7.1 or later is required for that MCP restriction; it matches the production version audited for this change.

Content field values remain editable under their existing permissions. Global Settings values retain the separate `manage_woocommerce` capability for Administrators and Shop Managers. People, Events and taxonomy term screens remain available. Schema reads through MCP remain available under Novamira's existing permissions.

## Access limits

These controls prevent routine schema changes through ACF's editor and Novamira's dedicated ACF schema abilities. Trusted code can still call ACF's PHP APIs directly. Generic post-writing abilities, unrestricted PHP execution, SQL access and file-writing tools can bypass this policy. Restrict those tools and deployment credentials to trusted maintainers. This policy does not change Novamira's configuration or revoke any MCP access.

The restriction lives in the Marg theme and applies while that theme is active. New schema-writing abilities introduced by plugin updates need review.

Run the focused checks with:

```sh
php themes/marg/tests/ACFSchemaPolicyTest.php
php themes/marg/tests/AdminMenuCustomizerTest.php
```
