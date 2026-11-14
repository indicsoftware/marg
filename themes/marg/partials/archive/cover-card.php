<?php
/**
 * Magazine archive Covers view: one cover card (Figma card/cover, symbols 1035:3165
 * type=first-card and 1035:3256 type=default; frame 1019:2672). Mobile only (< 744px).
 *
 * Card: 75% of the track (the viewport) wide, full track height, 0.2px marg-grey right
 * rule, px 6, a column ending in the info panel. Background gradient-40 (marg-grey 40%
 * from 9.615% to transparent, top to bottom); the card in the first (left) slot, the
 * current one, uses gradient-30 ("the gradient in the first card is 10% more
 * transparent than the rest"): a ::before at 75% opacity (0.4 x 0.75 = 0.3).
 * data-current is server-rendered on the first card; archive-covers.js moves it.
 *
 * Cover: the featured image (ProductPresenter::cover_id via archive_row()['cover_id']),
 * 230 x 315 in the 280 card (82.14% wide), object-cover, centred in the space above the
 * panel. Without a cover the same box shows the marg-cover-bg placeholder. On short
 * screens the box shrinks so it stays clear of the 44px header (100svh - panel - 2 x 44).
 *
 * Info panel (white, 158px at 390, p 6, 3-col grid, rows auto auto 1fr auto 54px, row-gap 6,
 * marg-grey 80%): date (col 1) and "Vol. N No. M" (col 2, centred); the title (named
 * issues only, 3 lines max); the serif annotation (when set, 2 lines max). The 54px last
 * row clears the fixed counter footer (partials/archive/covers.php). The type is fluid
 * (tailwind.css "Fluid type scale"), so the panel height is --marg-archive-cover-panel-h
 * (tailwind.css "Type-derived component sizes"): 158px at text-small 12, plus 17/3 px per
 * px of text-small above that, so the three title lines and two annotation lines still
 * fit up to 743px. The cover box reserves the same height.
 *
 * Title and annotation are cap-trimmed at the top only, as the stage panel title
 * (stage.php): line-clamp clips overflow, so a bottom edge trimmed to the baseline would
 * cut the last line's descenders. The annotation's -0.32em bottom margin takes back the
 * untrimmed half-leading + Times descent (~3px), so its last baseline sits where Figma
 * draws it (6px above the 54px row).
 *
 * The whole card is one link to the issue. Accessible name: "Vol. N No. M, <date>:"
 * (sr-only, as issue-row.php) followed by the visible title and annotation.
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array $row     ProductPresenter::archive_row() (archive_rows() item).
 *     @type int   $index   Position in the track (0-based).
 *     @type bool  $current Whether the card starts in the first slot (the first card).
 *     @type bool  $eager   Load the cover eagerly (default false; covers.php keeps all lazy, archive-covers.js promotes).
 *     @type bool  $priority Add fetchpriority="high" on the cover (default false).
 * }
 */

use TenUpTheme\ProductPresenter;
use function TenUpTheme\html_attrs;

$row = isset( $args['row'] ) && is_array( $args['row'] ) ? $args['row'] : [];
if ( empty( $row['id'] ) || empty( $row['url'] ) ) {
	return;
}

$has_title  = ! empty( $row['has_title'] );
$annotation = (string) ( $row['annotation'] ?? '' );
$cover      = ProductPresenter::archive_cover_html( (int) ( $row['cover_id'] ?? 0 ), ! empty( $args['eager'] ), ! empty( $args['priority'] ) );
$sr_label   = trim( implode( ', ', array_filter( [ (string) ( $row['vol_issue'] ?? '' ), (string) ( $row['date'] ?? '' ) ] ) ) );

$li_attrs = [
	'class'              => 'relative h-full w-3/4 shrink-0 snap-start border-r-[0.2px] border-marg-grey before:pointer-events-none before:absolute before:inset-0 before:bg-[linear-gradient(to_bottom,rgba(126,134,153,0.4)_9.615%,rgba(126,134,153,0)_100%)] before:transition-opacity before:duration-200 motion-reduce:before:transition-none data-current:before:opacity-75',
	'data-archive-cover' => true,
	'data-index'         => (string) (int) ( $args['index'] ?? 0 ),
	'data-volume'        => (string) (int) ( $row['volume'] ?? 0 ),
	'data-current'       => ! empty( $args['current'] ),
];
?>
<li<?php echo html_attrs( $li_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html_attrs() escapes. ?>>
	<a href="<?php echo esc_url( $row['url'] ); ?>" class="relative flex h-full flex-col px-2xsmall outline-marg-red focus-visible:outline-1 focus-visible:-outline-offset-1">
		<span class="flex min-h-0 flex-1 items-center justify-center" aria-hidden="true">
			<span class="block aspect-[230/315] w-[min(82.14%,calc((100svh_-_var(--marg-archive-cover-panel-h)_-_2*var(--spacing-header))*230/315))] overflow-hidden bg-marg-cover-bg">
				<?php echo $cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output, escaped by core. ?>
			</span>
		</span>

		<span class="grid h-(--marg-archive-cover-panel-h) shrink-0 grid-cols-3 grid-rows-[auto_auto_minmax(0,1fr)_auto_54px] gap-y-2xsmall bg-white p-2xsmall font-sans text-small leading-[1.2] font-regular break-words text-marg-grey/80">
			<span class="sr-only"><?php echo esc_html( $sr_label . ( $has_title ? ':' : '' ) ); ?></span>
			<span class="text-trim col-start-1 row-start-1 whitespace-nowrap" aria-hidden="true"><?php echo esc_html( $row['date'] ?? '' ); ?></span>
			<span class="text-trim col-start-2 row-start-1 whitespace-nowrap text-center" aria-hidden="true"><?php echo esc_html( $row['vol_issue'] ?? '' ); ?></span>
			<?php if ( $has_title ) : ?>
				<span class="col-span-3 row-start-2 line-clamp-3 self-start [text-box:trim-start_cap_alphabetic]"><?php echo esc_html( $row['title'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $annotation ) : ?>
				<span class="col-span-3 row-start-4 -mb-[0.32em] line-clamp-2 self-start font-serif text-xsmall [text-box:trim-start_cap_alphabetic]"><?php echo esc_html( $annotation ); ?></span>
			<?php endif; ?>
		</span>
	</a>
</li>
