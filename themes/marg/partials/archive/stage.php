<?php
/**
 * Magazine archive, mobile "stage": image strip + open card (below tablet-lg only).
 *
 * Figma: image strip 880:5749 / 880:4949 ("hover image frame", a fixed 252px: image flush
 * at 0,0, max 160px tall, natural proportions, pr 103 so portrait covers stay left; a
 * landscape image may run the full strip width), the annotation row 951:2587 (strip y 170,
 * 390 x 82, full strip width with no padding, content centred; text 951:2585 Times 10/400,
 * lh 1.2, cap-trimmed, full marg-red), gap 8, open card 866:150 / 866:162
 * ("Publication / Issue header / Mobile", state Active, 90.6px).
 *
 * The strip stays 252px even without an annotation (880:4951 draws 240 for that case):
 * the annotation slot is always reserved so the snap line below never moves.
 *
 * The stage is one sticky block at the top of the viewport (it slides under the
 * transparent 44px site header, like the magazine cover band). Every list row stays a
 * collapsed 18.3px row; the row sitting in the last 18.3px of the stage (hidden behind
 * the open card) is the "open" one. assets/js/frontend/components/archive-list.js
 * copies that row's data-* attributes (image, annotation, card text) into this stage as
 * the list scrolls. Without JS (html.no-js) the stage is not sticky and the page does not
 * snap: the stage shows the first issue at the top and the list below it is a plain list
 * of links.
 *
 * Heights are fixed (252 + 8 + 90.6 = 350.6px) because the list's snap offset
 * (scroll-mt-[332.3px] in issue-row.php) and the script's band depend on them.
 *
 * The whole stage is aria-hidden: it repeats the first/open row, which the list
 * already exposes. Its link is pointer-only (tabindex -1); keyboard focus on the row
 * under it is mirrored by the outline rule in tailwind.css ("Archive page").
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array $row ProductPresenter::archive_row() of the initially open issue.
 * }
 */

use TenUpTheme\ProductPresenter;

$row = isset( $args['row'] ) && is_array( $args['row'] ) ? $args['row'] : [];
if ( empty( $row['id'] ) ) {
	return;
}

$image      = ProductPresenter::archive_image( $row['image_id'] ?? 0 );
$has_title  = ! empty( $row['has_title'] );
$annotation = (string) ( $row['annotation'] ?? '' );
?>
<div class="relative top-0 z-[5] -mt-header flex flex-col gap-xsmall bg-white tablet-lg:hidden [@media(height>=520px)]:in-[.js]:sticky" data-archive-stage aria-hidden="true">
	<div class="relative h-[252px] overflow-hidden pr-[103px]" data-archive-strip>
		<?php // max-w-[calc(100%_+_103px)]: the image may overflow the 103px right padding, up to the full strip width (landscape covers, 864:3637). ?>
		<img
			class="block h-auto max-h-[160px] w-auto max-w-[calc(100%_+_103px)]"
			alt=""
			decoding="async"
			data-archive-img
			<?php if ( $image ) : ?>
				src="<?php echo esc_url( $image['src'] ); ?>"
				<?php if ( '' !== $image['srcset'] ) : ?>
					srcset="<?php echo esc_attr( $image['srcset'] ); ?>"
					sizes="<?php echo esc_attr( $image['sizes'] ); ?>"
				<?php endif; ?>
				width="<?php echo esc_attr( $image['width'] ); ?>"
				height="<?php echo esc_attr( $image['height'] ); ?>"
			<?php else : ?>
				hidden
			<?php endif; ?>
		/>
		<span class="block h-[160px] w-[118px] bg-marg-cover-bg" data-archive-placeholder<?php echo $image ? ' hidden' : ''; ?>></span>

		<?php // Annotation row (951:2587): pinned at strip y 170 whatever the image height, full strip width. ?>
		<div class="absolute inset-x-0 top-[170px] flex h-[82px] items-center justify-center overflow-hidden">
			<p class="text-trim m-0 w-full break-words text-center font-serif text-xsmall font-regular leading-[1.2] text-marg-red" data-archive-annotation<?php echo '' === $annotation ? ' hidden' : ''; ?>><?php echo esc_html( $annotation ); ?></p>
		</div>
	</div>

	<a
		href="<?php echo esc_url( $row['url'] ?? '' ); ?>"
		class="flex h-[90.6px] flex-col gap-3xsmall overflow-hidden px-gutter text-marg-red"
		tabindex="-1"
		data-archive-panel
	>
		<span class="relative flex min-h-[12px] shrink-0 overflow-hidden pb-micro font-sans text-small font-semibold">
			<span class="text-trim block w-[130px]" data-archive-panel-date><?php echo esc_html( $row['date'] ?? '' ); ?></span>
			<span class="text-trim absolute left-[47.95%] top-0 block w-[130px] -translate-x-1/2 text-center" data-archive-panel-vol<?php echo $has_title ? '' : ' hidden'; ?>><?php echo esc_html( $has_title ? ( $row['vol_issue'] ?? '' ) : '' ); ?></span>
		</span>
		<span class="block h-(--stroke-hairline) w-full shrink-0 bg-marg-red"></span>
		<span class="block h-[66px] shrink-0 overflow-hidden pt-3xsmall">
			<?php // Trim the top only: line-clamp clips overflow, and a trimmed bottom edge would cut descenders. ?>
			<span class="line-clamp-3 break-words [text-box:trim-start_cap_alphabetic] text-center font-sans text-medium font-regular" data-archive-panel-title><?php echo esc_html( $has_title ? $row['title'] : ( $row['vol_issue'] ?? '' ) ); ?></span>
		</span>
		<span class="block h-(--stroke-hairline) w-full shrink-0 bg-marg-red"></span>
	</a>
</div>
