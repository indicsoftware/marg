<?php
/**
 * Mobile header context title (Figma "Header-interim / mobile" 881:183).
 *
 * Absolutely centred in the 44px bar, 12px (text-small mobile token), red,
 * cap-trimmed, baseline-aligned with a 3.3px gap, no wrapping. Below 1133px only:
 * from 1133 the desktop header is partials/site/header-desktop.php.
 *  - static variant 897:6278 (product pages): "Archives" (400, link) | title (600).
 *  - archive toggle variants 881:151 / 881:159: "1946–2015 | ARCHIVES", all 600, plus
 *    the Titles/Covers selector below 744px (Covers variants 881:167 / 881:175 in the
 *    Covers view, Figma 1019:2672). On the archive the header does not auto-hide
 *    (880:5044, SiteChrome::header_autohide()).
 *  - everywhere else: the "Archives" link only.
 *
 * Long titles are truncated with an ellipsis (overflow-x: clip keeps the
 * descenders of the cap-trimmed text visible).
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductPresenter;
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
	// Titles/Covers selector, below 744px only ("Remove the cover option from the tablet
	// breakpoint onwards", 1019:2672): an 82px column at the right edge, Sans regular 12,
	// px 16 (fixed, not px-large, whose 24px tablet value would overflow the 82px box).
	// Choosing an option switches the view and closes it. JS only
	// (assets/js/frontend/components/archive-view-toggle.js); without JS it is not
	// rendered. The options are real links (?view=covers / the bare archive URL), so the
	// view also round-trips through the URL. data-view mirrors [data-archive]'s.
	//  - Titles (881:151 closed, 881:159 open): button "Titles" + down arrow at the top
	//    (44px), flipped when open; open adds an 82x36 "Covers" option row below.
	//  - Covers (881:167 closed, 881:175 open): button "Covers" + up arrow at the top.
	//    Open: the "Titles" option takes the top 44px at 30% opacity and the "Covers ↑"
	//    button moves to the 36px row below (visual order only; DOM order is unchanged).
	//    The faded option gets full opacity while focused, so its focus ring stays visible.
	// Without JS (html.no-js) in the Covers view a plain "Titles" link takes the selector's
	// place, so the list stays reachable.
	$marg_view       = ProductPresenter::archive_view();
	$marg_covers_url = add_query_arg( 'view', 'covers', $marg_context['archive_url'] );
	?>
	<?php if ( 'covers' === $marg_view ) : ?>
		<a href="<?php echo esc_url( $marg_context['archive_url'] ); ?>" class="absolute right-0 top-0 hidden h-[44px] w-[82px] items-center px-[16px] font-sans text-small font-regular leading-[1.2] text-marg-red max-tablet:in-[.no-js]:flex <?php echo esc_attr( $marg_focus ); ?> focus-visible:-outline-offset-4"><span class="text-trim"><?php esc_html_e( 'Titles', 'tenup-theme' ); ?></span></a>
	<?php endif; ?>
	<div class="group/view absolute right-0 top-0 hidden w-[82px] flex-col font-sans text-small font-regular leading-[1.2] text-marg-red max-tablet:in-[.js]:flex" data-archive-view data-view="<?php echo esc_attr( $marg_view ); ?>">
		<button type="button" class="flex h-[44px] w-full items-center px-[16px] group-[[data-view=covers][data-open]]/view:h-[36px] <?php echo esc_attr( $marg_focus ); ?> focus-visible:-outline-offset-4" aria-expanded="false" aria-controls="archive-view-options" data-archive-view-toggle>
			<span class="text-trim group-data-[view=covers]/view:hidden"><?php esc_html_e( 'Titles', 'tenup-theme' ); ?></span>
			<span class="text-trim hidden group-data-[view=covers]/view:block"><?php esc_html_e( 'Covers', 'tenup-theme' ); ?></span>
			<?php echo inline_svg( 'icon-arrow-down', [ 'class' => 'block size-[12px] shrink-0 transition-transform duration-200 motion-reduce:transition-none group-data-open/view:rotate-180 group-data-[view=covers]/view:rotate-180' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs(). ?>
			<span class="sr-only"><?php esc_html_e( '(change view)', 'tenup-theme' ); ?></span>
		</button>
		<div id="archive-view-options" class="flex h-[36px] items-center px-[16px] group-data-[view=covers]/view:order-first group-data-[view=covers]/view:h-[44px]" hidden data-archive-view-options>
			<a href="<?php echo esc_url( $marg_covers_url ); ?>" class="text-trim group-data-[view=covers]/view:hidden <?php echo esc_attr( $marg_focus ); ?>" data-archive-view-option="covers"><?php esc_html_e( 'Covers', 'tenup-theme' ); ?></a>
			<a href="<?php echo esc_url( $marg_context['archive_url'] ); ?>" class="text-trim hidden opacity-30 group-data-[view=covers]/view:block focus-visible:opacity-100 <?php echo esc_attr( $marg_focus ); ?>" data-archive-view-option="titles"><?php esc_html_e( 'Titles', 'tenup-theme' ); ?></a>
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
