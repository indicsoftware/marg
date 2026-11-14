<?php
/**
 * "Articles in this issue" row (Figma 836:590 desktop, 838:2481 mobile). Output inside an <ol>.
 *
 * Mobile: 4-col grid. Col 1 = page range (70%) + 1px vertical rule; cols 2-4 = title + authors,
 * with the thumbnail as a 30% opacity square overlay in the middle third, behind the text.
 * Desktop (>= tablet-lg): 7-col grid with a top rule, py 12, min-height 189. Col 1 = page range,
 * cols 2-5 = title + authors (py 20), cols 6-7 = thumbnail (empty when there is no image),
 * contained in its cell rather than filling it (Figma revision on I836:650;280:67).
 * The title link is stretched over the whole row.
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array $row ProductPresenter::issue_articles() row.
 * }
 */

$row = is_array( $args['row'] ?? null ) ? $args['row'] : [];
if ( empty( $row['id'] ) || '' === trim( (string) ( $row['title'] ?? '' ) ) ) {
	return;
}

$url      = (string) ( $row['url'] ?? '' );
$pages    = (string) ( $row['pages'] ?? '' );
$authors  = (string) ( $row['authors'] ?? '' );
$image_id = (int) ( $row['image_id'] ?? 0 );
?>
<li class="group relative isolate grid min-h-[92px] grid-cols-4 tablet-lg:min-h-[189px] tablet-lg:grid-cols-7 tablet-lg:border-t tablet-lg:border-marg-red tablet-lg:py-[12px]">
	<?php
	if ( $image_id ) {
		echo wp_get_attachment_image(
			$image_id,
			'medium',
			false,
			[
				'class'   => 'pointer-events-none absolute -z-10 left-1/2 top-0 aspect-square h-auto w-1/4 object-cover opacity-30 tablet-lg:left-[calc(500%/7)] tablet-lg:top-[12px] tablet-lg:aspect-auto tablet-lg:h-[calc(100%-24px)] tablet-lg:w-[calc(200%/7)] tablet-lg:object-contain tablet-lg:opacity-100',
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '(min-width: 1133px) 160px, 92px',
			]
		);
	}
	?>
	<div class="relative after:absolute after:inset-y-0 after:left-[70%] after:w-px after:bg-marg-red tablet-lg:after:hidden">
		<?php if ( '' !== $pages ) : ?>
			<p class="text-trim w-[70%] max-w-[75px] pr-micro font-sans text-small font-bold tablet-lg:w-full tablet-lg:max-w-none">
				<span class="sr-only"><?php esc_html_e( 'Pages:', 'tenup-theme' ); ?> </span><?php echo esc_html( $pages ); ?>
			</p>
		<?php endif; ?>
	</div>
	<div class="col-span-3 flex flex-col gap-xsmall tablet-lg:col-span-4 tablet-lg:py-[20px]">
		<h3 class="text-trim break-words font-sans text-medium font-semibold">
			<?php if ( '' !== $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="after:absolute after:inset-0 after:z-10 group-hover:underline focus-visible:outline-none focus-visible:after:outline-1 focus-visible:after:outline-offset-2 focus-visible:after:outline-current"><?php echo esc_html( $row['title'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $row['title'] ); ?>
			<?php endif; ?>
		</h3>
		<?php if ( '' !== $authors ) : ?>
			<p class="text-trim break-words font-sans text-regular font-regular"><?php echo esc_html( $authors ); ?></p>
		<?php endif; ?>
	</div>
</li>
