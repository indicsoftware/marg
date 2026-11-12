<?php
/**
 * Mobile header context title (Figma "Header-interim / mobile" 881:183).
 *
 * Absolutely centred in the 44px bar, 12px (text-small mobile token), red,
 * cap-trimmed, baseline-aligned with a 3.3px gap, no wrapping. Below 1133px only:
 * the desktop header stays blank.
 *  - static variant 897:6278 (product pages): "Archives" (400, link) | title (600).
 *  - archive toggle variants 881:151: "1946–2015 | ARCHIVES", all 600. The
 *    Titles/Covers toggle is not built (the Covers view is not designed).
 *  - everywhere else: the "Archives" link only.
 *
 * Long titles are truncated with an ellipsis (overflow-x: clip keeps the
 * descenders of the cap-trimmed text visible).
 *
 * @package TenUpTheme
 */

use TenUpTheme\SiteChrome;

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
