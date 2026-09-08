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

## Interim launch decisions

Use `Backend-SourceOfTruth-1.xlsx` and the decisions agreed on 8 September 2026. Exclude a row only when Field Status is Parked. Ignore the For Marg column when determining scope. Existing parked fields and data remain unchanged. Gift sets is also excluded.

All ACF fields are optional, superseding the workbook's Required column. Conditional visibility remains unchanged. Format, positive-number and image-size checks still apply to supplied values. Article parent selection allows zero or one Magazine. The custom ACF submission checks also allow an empty native product title and cover; WordPress and WooCommerce retain their own save behavior.

Person Type stays multi-select. Bio is a plain textarea. Bio Last Updated On captures date and time, displays `d/m/Y H:i` and returns `Y-m-d H:i:s`. Designation is shown for Team; Contributor Type is shown for Contributor and General Editor.

Magazine volume, issue and publication dates are optional. When both dates are provided, End cannot precede Start. The separate read-only Year field is reserved for migration from the old CMS. It does not reuse Publication month and year (Reference). Volume Info is removed from the form; its database values are retained.

Magazine additions are photograph and illustration counts, Print Length, Open Access Featured Text and Sightseeing Scan. Use WooCommerce's native product image for covers and Article featured images. A supplied magazine cover must be at least 1000px wide. Article galleries and the other parked media fields are deferred.

Open Access Hover Image is optional for every Magazine. A supplied image must be at least 500px wide and retains its natural proportions. The theme validates image width during form submission, because ACF's media-picker constraint alone does not reject undersized existing attachments on save. This field stores an attachment ID; adding it does not implement a front-end hover effect.

Magazine print pricing, stock, Out of Print and shipping weight are in scope because their Field Status is Added. These fields already exist. The workbook labels shipping weight in kilograms, while the existing ACF field stores grams and the sync converts grams to the WooCommerce unit. Preserve this previously approved behavior until a separate unit change and data migration are agreed.

Article From and To are independently optional positive integers. When both are provided, To must be at least From. The theme reads unambiguous legacy `article_details_page_number` strings such as `pp. 10-11` and `p. 12` into the new controls only when the new metadata does not yet exist. Original text is never deleted. Unparseable references remain available in field instructions for manual review. Saving the form stores the new numeric values. Explicitly clearing them leaves them blank on later loads.

Migration targets use these ACF keys:

| Value | Field key | Stored metadata name |
|---|---|---|
| Year | `field_magazine_year` | `magazine_details_year` |
| Photograph count | `field_magazine_number_of_photographs` | `magazine_details_number_of_photographs` |
| Illustration count | `field_magazine_number_of_illustrations` | `magazine_details_number_of_illustrations` |
| Print Length | `field_magazine_print_length` | `magazine_details_print_length` |
| Open Access Featured Text | `field_magazine_open_access_featured_text` | `magazine_details_open_access_featured_text` |
| Open Access Hover Image | `field_magazine_open_access_hover_image` | `magazine_details_open_access_hover_image` |
| Sightseeing Scan | `field_magazine_sightseeing_scan` | `magazine_details_sightseeing_scan` |
| From page | `field_article_page_from` | `article_details_page_from` |
| To page | `field_article_page_to` | `article_details_page_to` |
| DOI Link | `field_article_doi_link` | `article_details_doi_link` |

DOI Link is a read-only URL field for CrossRef-assigned values. This change adds no CrossRef API integration. The import process must populate it and the separate Year field.

ISSN stays editable, defaults from Global Settings when blank, and preserves overrides during variation creation. New magazine variant SKUs use the parent's SKU with `-PRINT` or `-DIGITAL`. New Article SKUs default to `ARTICLE-{ID}`; existing imported SKUs are preserved.

Magazine Open Access defaults to No. The Article toggle is disabled and derives its value from the current parent, including when read after an API/import changes the relationship. Normal ACF saves also persist the inherited boolean. Direct attempts to write an independent Article Open Access value are replaced with the parent's value. Consumers needing current inheritance should use ACF's value API rather than raw Article metadata.

The page-order, date-order, integer and image-size checks run through ACF's editor validation hooks. Imports and custom API callers that bypass ACF form validation must validate their input separately. Bulk migration and third-party save paths require staging checks before use.

Run the focused checks with:

```sh
php themes/marg/tests/ACFSchemaPolicyTest.php
php themes/marg/tests/AdminMenuCustomizerTest.php
php themes/marg/tests/PersonTypeConditionsTest.php
php themes/marg/tests/ProductEditorialFieldsTest.php
npm run test -w tenup-theme -- --runInBand
```
