<?php
/**
 * Desktop site header (>= 1133px): Figma "header-desktop" 951:2811.
 *
 * Rendered inside header.php's <header>; hidden below 1133 (the mobile header is
 * partials/site/header-context.php). A 24-column grid with fluid columns, capped
 * at max-w-page (1920) and centred, transparent, in normal flow, not sticky
 * (the Figma frames have no fixed children). Rows are the fixed Figma 1920 values
 * at every desktop width (1133 included):
 *  - main     (951:2810): 8 rows x 52.375 = 419 + the 1px marg-red bottom rule
 *             (drawn full-bleed by header.php on the <header>) = 420.
 *  - magazine (951:2862) / article (951:2887): 4 rows x 52.625 = 210.5, no rule.
 * Variant and data: SiteChrome::header_desktop().
 *
 * main (the magazine archive and every non-product page):
 *  - col 2 / row 2: the current period "1946 – 2015" with the solid down triangle
 *    above it (link/navigation icon-position=top, gap 8). Links to #top on the
 *    archive (aria-current="page"), to the archive elsewhere.
 *  - "2016 – 2026" (col 6), "SEARCH" (col 19) and Shelf / Cart (cols 21-23, row 4,
 *    two equal halves, Cart centred): decorative placeholders exactly as drawn
 *    (30% red, blur(2px) = Figma layer blur 4, invisible icons keep their space).
 *    aria-hidden, not focusable, no fake counts: "Shelf" alone, "Cart(N)" with the
 *    real WooCommerce cart count (just "Cart" when there is no cart).
 *    Mouse hover (hover-capable devices only, CSS only): hovering ANY of them swaps
 *    every placeholder's text for "Upcoming" at once, keeping the same blur and 30% red
 *    (review feedback; Figma has no hover state for them). "Upcoming" is
 *    stacked absolutely over the default text (centred, or left-aligned for Shelf),
 *    so the default text alone sets the footprint and nothing shifts (below 1440,
 *    Cart hides meanwhile, so Shelf's "Upcoming" never runs into it). They take
 *    pointer events for the hover only: no link, no tabindex, cursor default.
 *  - cols 2-6 / rows 4-6: the canvas animation slot (Frame 22, "We are working on an
 *    animation in <canvas>"): Marginalia 2 by Eemon Roy, top-aligned in row 4 (see the markup).
 *  - cols 7-11 / row 6: tagline, Times 18 (.font-serif hairline stroke), bottom-
 *    aligned. One line as drawn (it may run past col 11 into the empty col 12)
 *    from 1524px, where cols 7-12 still hold the 381px line; narrower, it wraps
 *    upwards inside cols 7-11 rather than running into the column-13 image box.
 *  - archive only, cols 13-17 / rows 3-7 (400 x 261.875 at 1920, stretched to the
 *    cell; Figma 951:2797, proportions revised): the hover-image container. CONTRACT: a <div data-archive-hover-image
 *    role="none" aria-hidden="true"> rendered EMPTY. archive-list.js appends and
 *    fades its own <img> inside it; the box only clips (overflow hidden) and gives
 *    the image a positioned, cell-sized parent.
 *
 * magazine: back link (solid left triangle + "1946 – 2015", 18/600, full red) at
 * col 2 / row 2, and the issue's "Vol. 70 No. 2" (35/600, the theme's spaced label)
 * centred and bottom-aligned in cols 10-14 / row 3, as the current page (not a link).
 *
 * article: the same back link at 30% red (as drawn), and "article from Vol. 70
 * No. 2, Ars Botanica" (18/600, lower-case "article") centred and bottom-aligned
 * in cols 8-17 / row 3, linking to the parent magazine. Without a parent, only
 * the back link. Long titles wrap upwards (bottom-aligned, overflow at the top).
 *
 * Link hover (link/navigation state=hover 274:321 / 274:325, updated): the solid
 * triangle swaps for an outline triangle; no blur and the gap stays. Keyboard focus
 * draws the focus outline.
 *
 * @package TenUpTheme
 */

use TenUpTheme\SiteChrome;
use function TenUpTheme\inline_svg;

$marg_header = SiteChrome::header_desktop();
$marg_main   = 'main' === $marg_header['variant'];
$marg_focus  = 'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-marg-red';
$marg_label  = 'text-trim whitespace-nowrap';
$marg_icon   = 'block size-link-icon shrink-0';
$marg_years  = SiteChrome::archive_years( true );

// Decorative placeholders: 30% red, never interactive (hover only reveals "Upcoming").
$marg_placeholder = 'cursor-default select-none self-start whitespace-nowrap text-regular font-semibold text-marg-red-muted';

// Hover targets ([data-soon]): Figma layer blur 4 = CSS blur(2px), kept on hover. Hovering any
// placeholder swaps EVERY placeholder's label for "Upcoming" at once (the grid is group/soonzone).
// Class names are written out in full (not concatenated) so Tailwind's source scan generates them.
$marg_soon = 'blur-[2px]';

// Default label (sets the footprint) and the stacked "Upcoming" label shown instead on hover.
$marg_soon_default  = 'block text-trim group-has-[[data-soon]:hover]/soonzone:invisible';
$marg_soon_upcoming = 'invisible absolute top-0 text-trim group-has-[[data-soon]:hover]/soonzone:visible';
$marg_soon_centred  = $marg_soon_upcoming . ' left-1/2 -translate-x-1/2';
$marg_upcoming      = __( 'Upcoming', 'tenup-theme' );

$marg_grid = 'group/soonzone mx-auto hidden w-full max-w-page font-sans leading-[1.2] text-marg-red tablet-lg:grid tablet-lg:grid-cols-24 '
	. ( $marg_main ? 'tablet-lg:grid-rows-[repeat(8,52.375px)]' : 'tablet-lg:grid-rows-[repeat(4,52.625px)]' );

?>
<div class="<?php echo esc_attr( $marg_grid ); ?>">
	<?php if ( $marg_main ) : ?>

		<nav class="col-start-2 row-start-2 self-start justify-self-start" aria-label="<?php esc_attr_e( 'Archive periods', 'tenup-theme' ); ?>">
			<a
				href="<?php echo esc_url( $marg_header['is_archive'] ? '#top' : $marg_header['archive_url'] ); ?>"
				class="<?php echo esc_attr( 'group/navlink flex flex-col items-center gap-3xsmall text-regular font-semibold ' . $marg_focus ); ?>"
				<?php echo $marg_header['is_archive'] ? 'aria-current="page"' : ''; ?>
			>
				<?php
				// Hover (link/navigation 274:325): the solid triangle becomes an outline; no blur, gap unchanged.
				echo inline_svg( 'icon-link-triangle-down', [ 'class' => 'block size-link-icon shrink-0 group-hover/navlink:hidden' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs().
				echo inline_svg( 'icon-link-triangle-down-outline', [ 'class' => 'hidden size-link-icon shrink-0 group-hover/navlink:block' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs().
				?>
				<span class="<?php echo esc_attr( $marg_label ); ?>"><span class="sr-only"><?php esc_html_e( 'Archive', 'tenup-theme' ); ?> </span><?php echo esc_html( $marg_years ); ?></span>
			</a>
		</nav>

		<div class="<?php echo esc_attr( 'col-start-6 row-start-2 flex flex-col items-center gap-3xsmall justify-self-start ' . $marg_placeholder . ' ' . $marg_soon ); ?>" aria-hidden="true" data-soon>
			<span class="<?php echo esc_attr( $marg_icon ); ?>"></span>
			<span class="relative">
				<span class="<?php echo esc_attr( $marg_soon_default ); ?>"><?php echo esc_html_x( '2016 – 2026', 'future archive period (en dash with spaces)', 'tenup-theme' ); ?></span>
				<span class="<?php echo esc_attr( $marg_soon_centred ); ?>"><?php echo esc_html( $marg_upcoming ); ?></span>
			</span>
		</div>

		<div class="<?php echo esc_attr( 'col-start-19 row-start-2 flex flex-col items-center gap-3xsmall justify-self-start ' . $marg_placeholder . ' ' . $marg_soon ); ?>" aria-hidden="true" data-soon>
			<span class="<?php echo esc_attr( $marg_icon ); ?>"></span>
			<span class="relative">
				<span class="<?php echo esc_attr( $marg_soon_default . ' uppercase' ); ?>"><?php esc_html_e( 'Search', 'tenup-theme' ); ?></span>
				<span class="<?php echo esc_attr( $marg_soon_centred ); ?>"><?php echo esc_html( $marg_upcoming ); ?></span>
			</span>
		</div>

		<?php
		$marg_cart_count = SiteChrome::cart_count();
		$marg_cart       = null === $marg_cart_count
			? __( 'Cart', 'tenup-theme' )
			/* translators: %d: number of items in the cart (no space before the parenthesis, as drawn). */
			: sprintf( __( 'Cart(%d)', 'tenup-theme' ), $marg_cart_count );
		?>
		<div class="<?php echo esc_attr( 'col-[21/24] row-start-4 flex items-center justify-self-stretch ' . $marg_placeholder ); ?>" aria-hidden="true">
			<?php
			// Each half is a hover target. Below 1440 the pair is narrower than two "Upcoming" labels (87px
			// each), so while the placeholders show "Upcoming" Cart steps aside (invisible, footprint kept)
			// and Shelf's left-aligned "Upcoming" stands for the pair; from 1440 both show.
			?>
			<span class="<?php echo esc_attr( 'relative flex-1 basis-0 text-left ' . $marg_soon ); ?>" data-soon>
				<span class="<?php echo esc_attr( $marg_soon_default ); ?>"><?php esc_html_e( 'Shelf', 'tenup-theme' ); ?></span>
				<span class="<?php echo esc_attr( $marg_soon_upcoming . ' left-0' ); ?>"><?php echo esc_html( $marg_upcoming ); ?></span>
			</span>
			<span class="<?php echo esc_attr( 'relative flex-1 basis-0 text-center max-[1440px]:group-has-[[data-soon]:hover]/soonzone:invisible ' . $marg_soon ); ?>" data-soon>
				<span class="<?php echo esc_attr( $marg_soon_default ); ?>"><?php echo esc_html( $marg_cart ); ?></span>
				<span class="<?php echo esc_attr( $marg_soon_centred ); ?>"><?php echo esc_html( $marg_upcoming ); ?></span>
			</span>
		</div>

		<?php
		// Canvas animation slot (Frame 22, cols 2-6 / rows 4-6, top-aligned: one empty row under "1946 – 2015", per review): Marginalia 2 by
		// Eemon Roy, a living 400x100 red-ink drawing, embedded unmodified from
		// assets/marginalia/index.html in an iframe as its README prescribes. The iframe gets its
		// src only on desktop (assets/js/frontend/components/marginalia.js), and is scaled down to
		// the slot's width below 1920 (5 columns are narrower than 400px there).
		?>
		<div class="col-[2/7] row-[4/7] self-start overflow-hidden" data-marginalia>
			<iframe
				class="block h-[100px] w-[400px] origin-bottom-left border-0 bg-transparent [transform:scale(var(--marginalia-scale,1))]"
				data-src="<?php echo esc_url( get_template_directory_uri() . '/assets/marginalia/index.html' ); ?>"
				title="<?php esc_attr_e( 'Marginalia, a living drawing in red ink', 'tenup-theme' ); ?>"
				width="400"
				height="100"
				tabindex="-1"
			></iframe>
		</div>

		<p class="col-[7/12] row-start-6 flex items-end font-serif text-regular min-[1540px]:whitespace-nowrap">
			<span class="text-trim"><?php esc_html_e( 'Seventy years of the Marg quarterly are open access.', 'tenup-theme' ); ?></span>
		</p>

		<?php if ( $marg_header['show_hover_image'] ) : ?>
			<div class="relative col-[13/18] row-[3/8] overflow-hidden" data-archive-hover-image role="none" aria-hidden="true"></div>
		<?php endif; ?>

	<?php else : ?>

		<?php $marg_is_article = 'article' === $marg_header['variant']; ?>
		<?php // Full-grid subgrid, so the children below place on the header's own column / row lines. ?>
		<nav class="col-[1/-1] row-[1/-1] grid grid-cols-subgrid grid-rows-subgrid" aria-label="<?php esc_attr_e( 'Breadcrumb', 'tenup-theme' ); ?>">
			<a
				href="<?php echo esc_url( $marg_header['archive_url'] ); ?>"
				class="<?php echo esc_attr( 'group/navlink col-start-2 row-start-2 flex items-center gap-micro self-start justify-self-start text-regular font-semibold ' . ( $marg_is_article ? 'text-marg-red-muted ' : '' ) . $marg_focus ); ?>"
			>
				<?php
				// Hover (link/navigation 274:321): the solid triangle becomes an outline; no blur, gap unchanged.
				echo inline_svg( 'icon-link-triangle-left', [ 'class' => 'block size-link-icon shrink-0 group-hover/navlink:hidden' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs().
				echo inline_svg( 'icon-link-triangle-left-outline', [ 'class' => 'hidden size-link-icon shrink-0 group-hover/navlink:block' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs().
				?>
				<span class="<?php echo esc_attr( $marg_label ); ?>"><span class="sr-only"><?php esc_html_e( 'Archive', 'tenup-theme' ); ?> </span><?php echo esc_html( $marg_years ); ?></span>
			</a>

			<?php if ( '' !== $marg_header['title'] ) : ?>
				<?php if ( $marg_is_article ) : ?>
					<p class="col-[8/18] row-start-3 flex items-end justify-center text-center text-regular font-semibold">
						<?php if ( '' !== $marg_header['title_url'] ) : ?>
							<a href="<?php echo esc_url( $marg_header['title_url'] ); ?>" class="<?php echo esc_attr( 'text-trim hover:blur-[1px] ' . $marg_focus ); ?>"><?php echo esc_html( $marg_header['title'] ); ?></a>
						<?php else : ?>
							<span class="text-trim"><?php echo esc_html( $marg_header['title'] ); ?></span>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="col-[10/15] row-start-3 flex items-end justify-center text-center text-xlarge font-semibold">
						<span class="<?php echo esc_attr( 'text-trim' . ( $marg_header['title_is_label'] ? ' whitespace-nowrap' : '' ) ); ?>" aria-current="page"><?php echo esc_html( $marg_header['title'] ); ?></span>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</nav>

	<?php endif; ?>
</div>
