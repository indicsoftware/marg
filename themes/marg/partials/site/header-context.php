<?php
/**
 * Mobile header context title (Figma "Header-interim / mobile" 881:183).
 *
 * Absolutely centred in the 44px bar, 12px (text-small mobile token), red,
 * cap-trimmed, baseline-aligned with a 3.3px gap, no wrapping. Below 1133px only:
 * from 1133 the desktop header is partials/site/header-desktop.php.
 *  - static variant 897:6278 (product pages): "Archives" (400, link) | title (600).
 *  - archive toggle variants 881:151 / 881:159: "1946–2015 | ARCHIVES", all 600, plus
 *    the Titles/Covers selector (Covers unavailable until its view is designed). On the
 *    archive the header does not auto-hide (880:5044, SiteChrome::header_autohide()).
 *  - everywhere else: the "Archives" link only.
 *
 * Long titles are truncated with an ellipsis (overflow-x: clip keeps the
 * descenders of the cap-trimmed text visible).
 *
 * @package TenUpTheme
 */

use TenUpTheme\SiteChrome;
use function TenUpTheme\inline_svg;

$marg_context = SiteChrome::header_context();
$marg_item    = 'text-trim shrink-0';
$marg_wrap    = 'absolute left-[calc(50%_-_0.5px)] top-1/2 flex w-max max-w-[calc(100%_-_24px)] -translate-x-1/2 -translate-y-1/2 items-baseline gap-[3.3px] whitespace-nowrap text-center font-sans text-small leading-[1.2] text-marg-red tablet-lg:hidden';
$marg_focus   = 'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current';

if ( 'archive' === $marg_context['type'] ) :
	?>
	<p class="<?php echo esc_attr( $marg_wrap ); ?> font-semibold">
		<span class="<?php echo esc_attr( $marg_item ); ?>"><?php echo esc_html( SiteChrome::archive_years() ); ?></span>
		<span class="<?php echo esc_attr( $marg_item ); ?>" aria-hidden="true">|</span>
		<span class="<?php echo esc_attr( $marg_item ); ?> uppercase"><?php esc_html_e( 'Archives', 'tenup-theme' ); ?></span>
	</p>

	<?php
	// Titles/Covers selector (881:151 closed, 881:159 open): 82x44 button at the right edge,
	// Sans regular 12 + 12px arrow, px 16; open adds an 82x36 option row below (header 80px
	// tall in total). The arrow flips when open. Choosing an option closes it. The Covers
	// view is not designed yet, so Covers is shown but unavailable. JS only
	// (assets/js/frontend/components/archive-view-toggle.js); without JS it is not rendered.
	?>
	<div class="group/view absolute right-0 top-0 hidden w-[82px] font-sans text-small font-regular leading-[1.2] text-marg-red max-tablet-lg:in-[.js]:block" data-archive-view>
		<button type="button" class="flex h-[44px] w-full items-center px-large <?php echo esc_attr( $marg_focus ); ?> focus-visible:-outline-offset-4" aria-expanded="false" aria-controls="archive-view-options" data-archive-view-toggle>
			<span class="text-trim"><?php esc_html_e( 'Titles', 'tenup-theme' ); ?></span>
			<?php echo inline_svg( 'icon-arrow-down', [ 'class' => 'block size-[12px] shrink-0 transition-transform duration-200 motion-reduce:transition-none group-data-open/view:rotate-180' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs(). ?>
			<span class="sr-only"><?php esc_html_e( '(change view)', 'tenup-theme' ); ?></span>
		</button>
		<div id="archive-view-options" class="flex h-[36px] items-center px-large" hidden data-archive-view-options>
			<button type="button" class="text-trim cursor-not-allowed opacity-30 <?php echo esc_attr( $marg_focus ); ?>" aria-disabled="true" data-archive-view-option="covers">
				<?php esc_html_e( 'Covers', 'tenup-theme' ); ?><span class="sr-only"> <?php esc_html_e( '(coming soon)', 'tenup-theme' ); ?></span>
			</button>
		</div>
	</div>
	<?php
	return;
endif;
?>
<nav class="<?php echo esc_attr( $marg_wrap ); ?>" aria-label="<?php esc_attr_e( 'Breadcrumb', 'tenup-theme' ); ?>">
	<a href="<?php echo esc_url( $marg_context['archive_url'] ); ?>" class="<?php echo esc_attr( $marg_item . ' ' . $marg_focus ); ?>"><?php esc_html_e( 'Archives', 'tenup-theme' ); ?></a>
	<?php if ( '' !== $marg_context['title'] ) : ?>
		<span class="<?php echo esc_attr( $marg_item ); ?>" aria-hidden="true">|</span>
		<?php if ( '' !== $marg_context['title_url'] ) : ?>
			<a href="<?php echo esc_url( $marg_context['title_url'] ); ?>" class="text-trim min-w-0 overflow-x-clip text-ellipsis font-semibold <?php echo esc_attr( $marg_focus ); ?>"><?php echo esc_html( $marg_context['title'] ); ?></a>
		<?php else : ?>
			<span class="text-trim min-w-0 overflow-x-clip text-ellipsis font-semibold" aria-current="page"><?php echo esc_html( $marg_context['title'] ); ?></span>
		<?php endif; ?>
	<?php endif; ?>
</nav>
