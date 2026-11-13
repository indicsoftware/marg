<?php
/**
 * Magazine archive page body (inside <main id="main">).
 *
 * Figma: 848:2819 "archive-bp Desktop large" (1920); 880:5747, 864:3637 and 880:4951
 * "archive-bp Mobile" (390): three states of the same scrolling list.
 * Loaded by woocommerce/taxonomy-product_cat-magazines.php with
 * $args = [ 'rows' => ProductPresenter::archive_rows() ].
 *
 * Layout:
 *  - < tablet-lg (mobile token mode, incl. 744): a sticky stage (image strip + open card,
 *    partials/archive/stage.php) over a list of collapsed 18.3px rows that snap to the
 *    line under the open card. The list starts one row higher than the stage bottom, so
 *    the open row sits hidden behind the open card. A spacer (sized by archive-list.js)
 *    lets the last row reach that line before the footer.
 *  - >= tablet-lg: no stage. The list is 18 of 24 page columns (left 8.333%, width 75%),
 *    starting 1px above the header's bottom edge (Figma 848:2819: header 0-420, list y 419),
 *    so the first row's top rule sits on the header's 1px bottom rule; 100px (jumbo) above
 *    the footer. The Marg logo sits at its drawn position (Figma 880:4407: x calc(87.5% + 18px),
 *    y 674 = 254px below the header's bottom edge) and scrolls with the page; it is not
 *    sticky. List and logo sit in a max-w-page (1920) centred
 *    wrapper so their columns match the header-desktop and footer grids above 1920.
 *  - >= tablet-lg, hover devices: hovering or keyboard-focusing a row shows its image in
 *    the header-desktop container [data-archive-hover-image] (archive-list.js).
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array[] $rows ProductPresenter::archive_rows().
 * }
 */

use function TenUpTheme\inline_svg;

$rows = isset( $args['rows'] ) && is_array( $args['rows'] ) ? array_values( $args['rows'] ) : [];

$years = array_values( array_filter( wp_list_pluck( $rows, 'year' ) ) );
if ( $years ) {
	/* translators: 1: first year, 2: last year, e.g. "Archives, 1946–2025". */
	$heading = sprintf( __( 'Archives, %1$s–%2$s', 'tenup-theme' ), min( $years ), max( $years ) );
} else {
	$heading = __( 'Archives', 'tenup-theme' );
}

$logo = function_exists( 'TenUpTheme\inline_svg' )
	? inline_svg(
		'marg-logo-880-4407',
		[
			'class' => 'block aspect-[124.14/106.43] h-auto w-full',
		]
	)
	: '';
?>
<section class="relative text-marg-red tablet-lg:pb-jumbo" data-archive aria-labelledby="marg-archive-heading">
	<h1 id="marg-archive-heading" class="sr-only"><?php echo esc_html( $heading ); ?></h1>

	<?php if ( ! $rows ) : ?>
		<div class="mx-auto w-full max-w-page">
			<p class="text-trim px-gutter py-xlarge font-sans text-regular tablet-lg:ml-[calc(100%/12)] tablet-lg:px-0">
				<?php esc_html_e( 'No issues found.', 'tenup-theme' ); ?>
			</p>
		</div>
	<?php else : ?>
		<?php get_template_part( 'partials/archive/stage', null, [ 'row' => $rows[0] ] ); ?>

		<div class="relative mx-auto w-full max-w-page tablet-lg:-mt-px">
			<ol class="-mt-[18.3px] tablet-lg:mt-0 tablet-lg:ml-[calc(100%/12)] tablet-lg:w-3/4" data-archive-list aria-label="<?php esc_attr_e( 'Issues', 'tenup-theme' ); ?>">
				<?php
				foreach ( $rows as $index => $row ) {
					get_template_part(
						'partials/archive/issue-row',
						null,
						[
							'row'    => $row,
							'active' => 0 === $index,
						]
					);
				}
				?>
			</ol>
			<span class="mx-gutter block h-(--stroke-hairline) bg-marg-red tablet-lg:hidden" aria-hidden="true"></span>
			<div class="tablet-lg:hidden" data-archive-spacer aria-hidden="true"></div>

			<?php if ( '' !== $logo ) : ?>
				<?php // Figma 880:4407: drawn at x 1698 / y 674 in the 1920 frame (254px below the header rule). Not sticky. ?>
				<div class="pointer-events-none absolute top-px left-[calc(87.5%_+_18px)] hidden w-(--archive-logo-w) [--archive-logo-w:clamp(72px,6.466vw,124.14px)] tablet-lg:block" aria-hidden="true">
					<div class="mt-[254px]">
						<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned SVG, attributes escaped by html_attrs(). ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
