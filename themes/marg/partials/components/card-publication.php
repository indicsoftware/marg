<?php
/**
 * Component: card/publication (Figma 608:704 article desktop, 837:654 magazine desktop,
 * 565:961 mobile). Used by both templates' related sections.
 *
 * Responsive in one markup:
 *  - mobile (< tablet-lg): 300px grey cover box, "Article, 2023" meta line, semibold
 *    text-regular title. Byline and meta-row hidden unless `mobile_details`.
 *  - desktop (>= tablet-lg): 375px cover (contain), title (article: text-medium;
 *    magazine: text-xlarge, centred), byline, then a meta-row with a 1px top rule:
 *    meta_primary / meta_secondary on the left, OPEN ACCESS (wins when open access)
 *    or the lowest price on the right.
 * The root sets no width: it stretches in a grid cell / column flex; in a
 * horizontal carousel pass a width
 * (mobile carousel e.g. `w-[250px] shrink-0 snap-start tablet-lg:w-auto`, via `class`).
 *
 * Usage:
 * foreach ( \TenUpTheme\ProductPresenter::related_cards( $id ) as $card ) {
 *     get_template_part( 'partials/components/card-publication', null, [
 *         'card'  => $card,
 *         'class' => 'w-[250px] shrink-0 snap-start tablet-lg:w-auto',
 *     ] );
 * }
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array  $card           Required. ProductPresenter::card() view-model.
 *     @type string $variant        'article' | 'magazine'. Default: $card['type'] ('magazine' when unknown type is not article).
 *     @type string $heading_tag    Title element. Default 'h3'.
 *     @type bool   $mobile_details Show byline + meta-row on mobile too. Default false.
 *     @type bool   $mobile_byline  Mobile only (magazine carousel, Figma 839:972): show the byline as
 *                                  text-small and an "OPEN ACCESS" Sans bold caps line under the title,
 *                                  without the desktop meta-row. Default false.
 *     @type string $image_size     Image size for wp_get_attachment_image(). Default 'medium_large'.
 *     @type string $class          Extra classes for the root <article>.
 * }
 */

use function TenUpTheme\class_names;

$args = wp_parse_args(
	$args ?? [],
	[
		'card'           => [],
		'variant'        => '',
		'heading_tag'    => 'h3',
		'mobile_details' => false,
		'mobile_byline'  => false,
		'image_size'     => 'medium_large',
		'class'          => '',
	]
);

$card = is_array( $args['card'] ) ? $args['card'] : [];
if ( empty( $card['id'] ) || empty( $card['title'] ) ) {
	return;
}

$variant     = in_array( $args['variant'], [ 'article', 'magazine' ], true ) ? $args['variant'] : ( 'article' === ( $card['type'] ?? '' ) ? 'article' : 'magazine' );
$is_magazine = 'magazine' === $variant;
$heading_tag = in_array( $args['heading_tag'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? $args['heading_tag'] : 'h3';
$url         = (string) ( $card['url'] ?? '' );
$image_id    = (int) ( $card['image_id'] ?? 0 );
$price       = is_array( $card['price'] ?? null ) ? $card['price'] : [];
$detail_vis  = $args['mobile_details'] ? '' : ( $args['mobile_byline'] ? 'text-small tablet-lg:text-regular' : 'hidden tablet-lg:block' );
$row_vis     = $args['mobile_details'] ? 'flex' : 'hidden tablet-lg:flex';
$has_meta    = ! empty( $card['meta_primary'] ) || ! empty( $card['meta_secondary'] ) || ! empty( $price['amount'] ) || ! empty( $price['open_access'] );
?>
<article class="<?php echo esc_attr( class_names( 'flex min-w-0 flex-col items-start gap-xlarge text-marg-red tablet-lg:items-center', $args['class'] ) ); ?>">
	<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" class="relative block h-[300px] w-full overflow-hidden bg-marg-cover-bg tablet-lg:h-[375px] tablet-lg:bg-transparent" tabindex="-1" aria-hidden="true">
	<?php else : ?>
		<div class="relative block h-[300px] w-full overflow-hidden bg-marg-cover-bg tablet-lg:h-[375px] tablet-lg:bg-transparent" aria-hidden="true">
	<?php endif; ?>
		<div class="<?php echo esc_attr( class_names( 'absolute inset-y-[10px] flex items-center justify-center tablet-lg:inset-y-0', $is_magazine ? 'inset-x-[10px] tablet-lg:inset-x-0' : 'inset-x-[15px] tablet-lg:inset-x-large' ) ); ?>">
			<?php if ( $image_id ) : ?>
				<?php
				echo wp_get_attachment_image(
					$image_id,
					$args['image_size'],
					false,
					[
						'class'   => 'size-full object-contain',
						'alt'     => '',
						'loading' => 'lazy',
						'sizes'   => '(min-width: 1133px) 30vw, 250px',
					]
				);
				?>
			<?php else : ?>
				<span class="block h-full aspect-[220/301.4] max-w-full bg-marg-cover-bg"></span>
			<?php endif; ?>
		</div>
	<?php if ( $url ) : ?>
		</a>
	<?php else : ?>
		</div>
	<?php endif; ?>

	<div class="<?php echo esc_attr( class_names( 'flex w-full flex-col gap-xsmall', $is_magazine ? 'tablet-lg:items-center tablet-lg:text-center' : '' ) ); ?>">
		<?php if ( ! empty( $card['mobile_meta'] ) ) : ?>
			<p class="text-trim font-sans text-small font-regular tablet-lg:hidden"><?php echo esc_html( $card['mobile_meta'] ); ?></p>
		<?php endif; ?>
		<<?php echo tag_escape( $heading_tag ); ?> class="<?php echo esc_attr( class_names( 'text-trim break-words font-sans text-regular font-semibold', $is_magazine ? 'tablet-lg:text-xlarge' : 'tablet-lg:text-medium' ) ); ?>">
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="hover:underline"><?php echo esc_html( $card['title'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $card['title'] ); ?>
			<?php endif; ?>
		</<?php echo tag_escape( $heading_tag ); ?>>
		<?php if ( ! empty( $card['byline'] ) ) : ?>
			<p class="<?php echo esc_attr( class_names( 'text-trim break-words font-sans font-regular', $args['mobile_byline'] && ! $args['mobile_details'] ? '' : 'text-regular', $detail_vis ) ); ?>"><?php echo esc_html( $card['byline'] ); ?></p>
		<?php endif; ?>
		<?php if ( $args['mobile_byline'] && ! $args['mobile_details'] && ! empty( $price['open_access'] ) ) : ?>
			<p class="text-trim font-sans text-xsmall font-bold uppercase tablet-lg:hidden"><?php esc_html_e( 'Open access', 'tenup-theme' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $has_meta ) : ?>
		<div class="<?php echo esc_attr( class_names( 'w-full items-start justify-between gap-xsmall overflow-hidden border-t border-marg-red pt-2xsmall', $row_vis ) ); ?>">
			<div class="flex min-w-0 flex-col gap-3xsmall">
				<?php if ( ! empty( $card['meta_primary'] ) ) : ?>
					<p class="text-trim font-sans text-regular font-semibold"><?php echo esc_html( $card['meta_primary'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $card['meta_secondary'] ) ) : ?>
					<p class="text-trim break-words font-sans text-small font-regular"><?php echo esc_html( $card['meta_secondary'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
			get_template_part(
				'partials/components/price-display',
				null,
				[
					'items'              => ! empty( $price['amount'] ) ? [
						[
							'currency' => $price['currency'] ?? '',
							'amount'   => $price['amount'],
						],
					] : [],
					'open_access'        => ! empty( $price['open_access'] ),
					'show_labels'        => false,
					'prefer_open_access' => true,
					'align'              => 'end',
					'class'              => 'shrink-0 flex-nowrap',
				]
			);
			?>
		</div>
	<?php endif; ?>
</article>
