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
 *  - < tablet-lg (mobile layout; tablet token values from 744): a sticky stage (image strip + open card,
 *    partials/archive/stage.php) over a list of collapsed 18.3px rows (--archive-row-h,
 *    issue-row.php) that snap strictly to the line under the open card (Figma 879:1388,
 *    "like the iPhone time picker": every scroll inside the list comes to rest with a row
 *    in the open position; tailwind.css "Archive page"). The list starts one row higher
 *    than the stage bottom, so the open row sits hidden behind the open card. A spacer
 *    (sized by archive-list.js) lets the last row reach that line before the footer; the
 *    footer group's bottom edge is the one snap position past the list, so the footer
 *    stays reachable.
 *  - Measured geometry: archive-list.js sets --archive-stage-h (stage height) and
 *    --archive-snap-offset (stage height - row height) on the root from the DOM, so the
 *    snap offset, the gradient and the open-card band follow the stage whatever its
 *    (fluid-type) height. Before the script runs the fallbacks are the CSS-derived
 *    --marg-archive-stage-h / --marg-archive-open-top (tailwind.css "Type-derived
 *    component sizes"), which hit the Figma values at 390 / 744.
 *  - >= tablet-lg: no stage. The list is 18 of 24 page columns (left 8.333%, width 75%),
 *    starting 1px above the header's bottom edge (Figma 848:2819: header 0-420, list y 419),
 *    so the first row's top rule sits on the header's 1px bottom rule; 100px (jumbo) above
 *    the footer. The Marg logo sits at its drawn position (Figma 880:4407: x calc(87.5% + 18px),
 *    y 674 = 254px below the header's bottom edge) and scrolls with the page; it is not
 *    sticky. List and logo sit in a max-w-page (1920) centred
 *    wrapper so their columns match the header-desktop and footer grids above 1920.
 *  - >= tablet-lg, hover devices: hovering or keyboard-focusing a row shows its image in
 *    the header-desktop container [data-archive-hover-image] (archive-list.js).
 *  - < tablet, with JS and a viewport at least 520px tall (where the stage is sticky): a
 *    fixed background gradient (Figma 1019:2596) runs from the open card's bottom line to
 *    the viewport bottom, behind the stage, the header, the list and the footer.
 *  - < tablet, Covers view (Figma 1019:2672): the root carries data-view="covers"
 *    (ProductPresenter::archive_view(), from ?view=covers; archive-view-toggle.js flips it
 *    without a reload). The stage, list and gradient hide and partials/archive/covers.php
 *    shows its horizontal track instead; the site footer hides too (tailwind.css "Archive
 *    Covers view"). From tablet up the Titles layout always shows, whatever data-view says.
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array[] $rows ProductPresenter::archive_rows().
 * }
 */

use TenUpTheme\ProductPresenter;
use function TenUpTheme\inline_svg;

$rows = isset( $args['rows'] ) && is_array( $args['rows'] ) ? array_values( $args['rows'] ) : [];

$years = array_values( array_filter( wp_list_pluck( $rows, 'year' ) ) );
if ( $years ) {
	/* translators: 1: first year, 2: last year, e.g. "Archives, 1946–2025". */
	$heading = sprintf( __( 'Archives, %1$s–%2$s', 'tenup-theme' ), min( $years ), max( $years ) );
} else {
	$heading = __( 'Archives', 'tenup-theme' );
}

// 'titles' | 'covers' (?view=covers). Guarded so this partial works before archive_view() exists.
$view = method_exists( ProductPresenter::class, 'archive_view' ) ? (string) ProductPresenter::archive_view() : 'titles';
$view = 'covers' === $view ? 'covers' : 'titles';

// Hide a Titles-view element below tablet while the Covers view is shown.
$titles_only = 'max-tablet:group-data-[view=covers]/archive:hidden';

$logo = function_exists( 'TenUpTheme\inline_svg' )
	? inline_svg(
		'marg-logo',
		[
			'class' => 'block aspect-[124.14/106.43] h-auto w-full',
		]
	)
	: '';
?>
<section class="group/archive relative text-marg-red [--archive-row-h:max(18.3px,var(--text-small)*1.2)] tablet-lg:pb-jumbo" data-archive data-view="<?php echo esc_attr( $view ); ?>" aria-labelledby="marg-archive-heading">
	<h1 id="marg-archive-heading" class="sr-only"><?php echo esc_html( $heading ); ?></h1>

	<?php if ( ! $rows ) : ?>
		<div class="mx-auto w-full max-w-page">
			<p class="text-trim px-gutter py-xlarge font-sans text-regular tablet-lg:ml-[calc(100%/12)] tablet-lg:px-0">
				<?php esc_html_e( 'No issues found.', 'tenup-theme' ); ?>
			</p>
		</div>
	<?php else : ?>
		<?php get_template_part( 'partials/archive/stage', null, [ 'row' => $rows[0] ] ); ?>

		<?php
		/*
		 * Figma 1019:2596 "Rectangle 9": 20% grey gradient from the open card's bottom line
		 * (the stage bottom: --archive-stage-h, measured by archive-list.js; fallback the
		 * CSS-derived --marg-archive-stage-h: 350.6px at 390, 370.8px at 744, fluid in
		 * between, see stage.php) to the viewport bottom. Only where the stage is sticky
		 * (html.js, height >= 520, < tablet-lg).
		 * -z-1 paints it on the canvas, under every in-flow box: the white stage and header
		 * cover its top and the white site footer covers it, so it never tints the footer.
		 */
		?>
		<div class="<?php echo esc_attr( 'pointer-events-none fixed inset-x-0 top-[var(--archive-stage-h,var(--marg-archive-stage-h))] bottom-0 -z-1 hidden bg-[linear-gradient(to_bottom,transparent_0%,var(--color-marg-grey)_90.385%)] opacity-20 max-tablet-lg:[@media(height>=520px)]:in-[.js]:block max-tablet:group-data-[view=covers]/archive:hidden!' ); ?>" data-archive-gradient aria-hidden="true"></div>

		<div class="<?php echo esc_attr( 'relative mx-auto w-full max-w-page tablet-lg:-mt-px ' . $titles_only ); ?>">
			<ol class="-mt-[var(--archive-row-h,18.3px)] tablet-lg:mt-0 tablet-lg:ml-[calc(100%/12)] tablet-lg:w-3/4" data-archive-list aria-label="<?php esc_attr_e( 'Issues', 'tenup-theme' ); ?>">
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

		<?php
		get_template_part(
			'partials/archive/covers',
			null,
			[
				'rows' => $rows,
			]
		);
		?>
	<?php endif; ?>
</section>
