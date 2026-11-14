<?php
/**
 * Magazine archive Covers view (Figma 1019:2672 "archive-bp Mobile", mobile only).
 *
 * Rendered inside the [data-archive] root (partials/archive/magazines.php, group/archive,
 * data-view="titles|covers"). Shown only below 744px while data-view="covers" ("Remove
 * the cover option from the tablet breakpoint onwards"): from 744 it is never displayed,
 * whatever the view, so its lazy covers are never fetched there either.
 *
 * - Track (Frame 20 1035:3279): one row of full-height cover cards
 *   (partials/archive/cover-card.php) scrolling sideways with mandatory snapping. It
 *   starts under the 44px header (-mt-header) and is 100svh tall, so the page does not
 *   scroll vertically. Every card is 75% wide and snaps to the left (the "first" slot),
 *   so the next card peeks in; a 25% end spacer (::after) lets the last card reach the
 *   first slot too.
 * - Counter footer (unique footer-cover 1035:3250, mobile values): fixed at the bottom
 *   over the cards, px 12, 0.3px marg-red top hairline, pt 6 / pb 8, centred, gap 4,
 *   bottom-aligned: "Vol." + the volume of the card in the first slot (Sans 24, two
 *   digit windows so "Vol.  1" and "Vol. 67" keep "/67" in place) + "/total" (Sans 14).
 *   Figma puts the baseline 15px above the bottom (pb 8 + the 24px line's untrimmed
 *   descent, about 7px; bar 39px), so the cap-trimmed counter takes pb 8 + 7.
 *   The total is the number of volumes in the archive (ProductTemplates::ARCHIVE_LAST_VOLUME,
 *   67), not the mock's "/40". The digits roll like an odometer (archive-covers.js; no
 *   motion under prefers-reduced-motion). Decorative (aria-hidden): the card links name
 *   their volume. JS only: without JS the counter cannot follow the scroll, so it is not
 *   shown (the track, snapping, lazy covers and links still work from a ?view=covers URL).
 *   In Covers view the site footer is hidden (tailwind.css, "Archive Covers view").
 *
 * Images: every cover is lazy in the markup, whatever the view: the server cannot know
 * the viewport, and browsers fetch eager images even inside display:none, so a
 * ?view=covers link opened at 744px or wider must not load any. archive-covers.js
 * switches the current cover and the next two to eager once the track is visible.
 * Hidden views fetch nothing.
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array[] $rows   ProductPresenter::archive_rows().
 * }
 */

use TenUpTheme\ProductTemplates;

$rows = isset( $args['rows'] ) && is_array( $args['rows'] ) ? array_values( $args['rows'] ) : [];
if ( ! $rows ) {
	return;
}

$volumes = array_map( 'intval', wp_list_pluck( $rows, 'volume' ) );
$total   = defined( ProductTemplates::class . '::ARCHIVE_LAST_VOLUME' ) ? (int) ProductTemplates::ARCHIVE_LAST_VOLUME : 0;
$total   = max( $total, (int) max( $volumes ) );
$first   = (int) ( $rows[0]['volume'] ?? 0 );

/*
 * Odometer windows (archive-covers.js): each digit is a 1lh window over a strip of
 * twenty cells (two runs of 0-9, so a roll can always move one way: 9 -> 0 goes on to
 * the second run, then jumps back without motion). --digit is the cell index; the
 * classes are listed in full so Tailwind generates them. The tens window shows blank
 * cells for 0 ("Vol.  1").
 */
$digit_classes = [ '[--digit:0]', '[--digit:1]', '[--digit:2]', '[--digit:3]', '[--digit:4]', '[--digit:5]', '[--digit:6]', '[--digit:7]', '[--digit:8]', '[--digit:9]' ];
$windows       = [
	'tens' => (int) floor( ( $first % 100 ) / 10 ),
	'ones' => $first % 10,
];
?>
<div class="hidden max-tablet:group-data-[view=covers]/archive:block" data-archive-covers>
	<ol class="-mt-header flex h-svh snap-x snap-mandatory overflow-x-auto overflow-y-hidden overscroll-x-contain [scrollbar-width:none] after:w-1/4 after:shrink-0 [&::-webkit-scrollbar]:hidden" data-archive-cover-track aria-label="<?php esc_attr_e( 'Issue covers', 'tenup-theme' ); ?>">
		<?php
		foreach ( $rows as $index => $row ) {
			get_template_part(
				'partials/archive/cover-card',
				null,
				[
					'row'     => $row,
					'index'   => $index,
					'current' => 0 === $index,
				]
			);
		}
		?>
	</ol>

	<div class="fixed inset-x-0 bottom-0 z-20 hidden px-medium pb-[env(safe-area-inset-bottom)] in-[.js]:block" data-archive-cover-counter aria-hidden="true">
		<div class="flex items-end justify-center gap-3xsmall border-t-(length:--stroke-hairline) border-marg-red pt-2xsmall pb-[calc(var(--spacing-xsmall)_+_7px)] font-sans font-regular text-marg-red">
			<span class="text-trim whitespace-nowrap text-xlarge leading-[1.2]"><?php esc_html_e( 'Vol.', 'tenup-theme' ); ?> <?php foreach ( $windows as $place => $value ) : ?><span class="inline-block h-[1lh] w-[1ch] overflow-hidden text-center align-top tabular-nums"><span class="<?php echo esc_attr( 'block translate-y-[calc(var(--digit)*-1lh)] transition-transform duration-300 ease-out motion-reduce:transition-none ' . $digit_classes[ $value ] ); ?>" data-archive-cover-digit="<?php echo esc_attr( $place ); ?>">
				<?php
				for ( $cell = 0; $cell < 20; $cell++ ) {
					$digit = $cell % 10;
					// Tens: a blank cell for 0 (non-breaking space keeps the 1lh line).
					echo '<span class="block">' . ( 'tens' === $place && 0 === $digit ? '&nbsp;' : esc_html( (string) $digit ) ) . '</span>';
				}
				?>
			</span></span><?php endforeach; ?></span>
			<span class="text-trim text-regular leading-[1.2]">/<?php echo esc_html( (string) $total ); ?></span>
		</div>
	</div>
</div>
