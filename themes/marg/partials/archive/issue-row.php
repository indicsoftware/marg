<?php
/**
 * Magazine archive row: one issue, linking to its product page.
 *
 * Desktop (>= tablet-lg), Figma 852:157 "Publication / Issue header":
 *  - 18-column grid (the list is 18 of 24 page columns), py 8, row-gap 8, no column gap.
 *  - 1px top rule over columns 5-18 (absolute; the list's last row has none below it).
 *  - Row 1: date (cols 5-12, left), "Vol. N" on the first issue of a volume (cols 13-14,
 *    centred), issue number (cols 15-18, right). Row 2 (named issues only): title,
 *    cols 7-18, Sans/Small Title/Semibold. Optional serif annotation in cols 1-3,
 *    bottom aligned. Heights 28 (no title) / 54 (title).
 *  - Hover / focus-visible (State=Hover): date, volume and number hide and a large
 *    "Vol. N No. M" shows centred over cols 13-14. It is an absolute nowrap overlay, so
 *    the row keeps its height (Figma's hover variant grows the row by ~6px).
 * Mobile (< tablet-lg), Figma 866:170 state Inactive: 18.3px row, 0.3px top hairline,
 * one centred muted line: the title (ellipsis) or "Vol. N No. M" for unnamed issues,
 * in Color/mard-red/muted-50 (text-marg-red-muted-50, Figma 866:145 / 866:157).
 * The list's own bottom hairline closes the last row.
 *  - Row height: --archive-row-h from magazines.php, max(18.3px, one line of the fluid
 *    text-small): exactly 18.3px at the 390 and 744 anchors, and it only grows near 1133px
 *    where the fluid text-small line would otherwise overflow the row.
 *  - Strict snapping (Figma 879:1388, tailwind.css "Archive page"): every row is a
 *    snap-start position whose scroll-margin-top puts it in the open position, the last
 *    row-height of the sticky stage. archive-list.js measures the stage and a row and sets
 *    --archive-snap-offset (stage height - row height) on [data-archive]. Before the
 *    script runs the fallback is --marg-archive-open-top (tailwind.css "Type-derived
 *    component sizes"), the same offset derived from the fluid type tokens: 332.3px at
 *    390 and 352.5px at 744 (350.6 - 18.3, 370.8 - 18.3), fluid in between.
 *
 * The <li> carries the data the mobile open card (partials/archive/stage.php: image,
 * annotation) and the desktop header hover image (data-img-*, data-img-hover-sizes) need;
 * archive-list.js reads it. Accessible name: "Vol. N No. M, <date>" (+ the title and the
 * annotation; on mobile the annotation is visually hidden in the row and shown in the strip).
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array $row    ProductPresenter::archive_row() (with volume_label from archive_rows()).
 *     @type bool  $active Whether this is the initially open row (the first one).
 * }
 */

use TenUpTheme\ProductPresenter;
use function TenUpTheme\class_names;
use function TenUpTheme\html_attrs;

$row = isset( $args['row'] ) && is_array( $args['row'] ) ? $args['row'] : [];
if ( empty( $row['id'] ) || empty( $row['url'] ) ) {
	return;
}

$has_title  = ! empty( $row['has_title'] );
$annotation = (string) ( $row['annotation'] ?? '' );
$image      = ProductPresenter::archive_image( $row['image_id'] ?? 0 );

$li_attrs = [
	'class'                => 'relative max-tablet-lg:snap-start max-tablet-lg:[@media(height>=520px)]:in-[.js]:scroll-mt-[var(--archive-snap-offset,var(--marg-archive-open-top))] max-tablet-lg:px-gutter',
	'data-archive-row'     => true,
	'data-active'          => ! empty( $args['active'] ),
	'data-date'            => (string) ( $row['date'] ?? '' ),
	'data-vol'             => (string) ( $row['vol_issue'] ?? '' ),
	'data-title'           => $has_title ? (string) $row['title'] : false,
	'data-url'             => (string) $row['url'],
	'data-img-src'         => $image ? $image['src'] : false,
	'data-img-srcset'      => $image ? $image['srcset'] : false,
	'data-img-sizes'       => $image ? $image['sizes'] : false,
	'data-img-width'       => $image ? (string) $image['width'] : false,
	'data-img-height'      => $image ? (string) $image['height'] : false,
	'data-img-hover-sizes' => $image ? ProductPresenter::archive_hover_sizes( $image ) : false,
	'data-annotation'      => '' !== $annotation ? $annotation : false,
];

$sr_label = trim( implode( ', ', array_filter( [ (string) ( $row['vol_issue'] ?? '' ), (string) ( $row['date'] ?? '' ) ] ) ) );
?>
<li<?php echo html_attrs( $li_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html_attrs() escapes. ?>>
	<a
		href="<?php echo esc_url( $row['url'] ); ?>"
		class="archive-row-link group/row relative flex h-[var(--archive-row-h,18.3px)] items-center text-marg-red outline-marg-red focus-visible:outline-1 focus-visible:outline-offset-2 tablet-lg:grid tablet-lg:h-auto tablet-lg:grid-cols-18 tablet-lg:items-start tablet-lg:gap-y-3xsmall tablet-lg:py-3xsmall"
	>
		<span class="pointer-events-none absolute left-0 right-0 top-0 block h-(--stroke-hairline) bg-marg-red tablet-lg:left-[22.222%] tablet-lg:h-px" aria-hidden="true"></span>

		<span class="sr-only"><?php echo esc_html( $sr_label . ( $has_title ? ':' : '' ) ); ?></span>

		<?php // Desktop columns (decorative duplicates of the screen-reader label). ?>
		<span class="text-trim col-[5/span_8] row-start-1 hidden font-sans text-small font-semibold group-hover/row:invisible group-focus-visible/row:invisible tablet-lg:block" aria-hidden="true"><?php echo esc_html( $row['date'] ?? '' ); ?></span>
		<?php if ( ! empty( $row['volume_label'] ) ) : ?>
			<span class="text-trim col-[13/span_2] row-start-1 hidden whitespace-nowrap text-center font-sans text-small font-semibold group-hover/row:invisible group-focus-visible/row:invisible tablet-lg:block" aria-hidden="true"><?php echo esc_html( $row['volume_label'] ); ?></span>
		<?php endif; ?>
		<span class="text-trim col-[15/span_4] row-start-1 hidden text-right font-sans text-small font-semibold group-hover/row:invisible group-focus-visible/row:invisible tablet-lg:block" aria-hidden="true"><?php echo esc_html( (string) ( $row['issue'] ?? '' ) ); ?></span>
		<span class="text-trim invisible absolute left-[72.222%] top-[calc(var(--spacing-3xsmall)_+_6px)] hidden -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-sans text-medium font-semibold group-hover/row:visible group-focus-visible/row:visible tablet-lg:block" aria-hidden="true"><?php echo esc_html( $row['vol_issue'] ?? '' ); ?></span>

		<?php if ( $has_title ) : ?>
			<span class="text-trim col-[7/span_12] row-start-2 hidden break-words font-sans text-medium font-semibold tablet-lg:block"><?php echo esc_html( $row['title'] ); ?></span>
			<span class="block w-full truncate text-center font-sans text-small font-regular text-marg-red-muted-50 tablet-lg:hidden"><?php echo esc_html( $row['title'] ); ?></span>
		<?php else : ?>
			<span class="block w-full truncate text-center font-sans text-small font-regular text-marg-red-muted-50 tablet-lg:hidden" aria-hidden="true"><?php echo esc_html( $row['vol_issue'] ?? '' ); ?></span>
		<?php endif; ?>

		<?php if ( '' !== $annotation ) : ?>
			<span class="<?php echo esc_attr( class_names( 'text-trim col-[1/span_3] row-start-1 self-end break-words text-center font-serif text-small font-regular max-tablet-lg:sr-only', $has_title ? 'row-span-2' : '' ) ); ?>"><?php echo esc_html( $annotation ); ?></span>
		<?php endif; ?>
	</a>
</li>
