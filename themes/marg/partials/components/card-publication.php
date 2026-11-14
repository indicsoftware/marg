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
 * Unnamed issues (Figma 311:67 annotation): when a magazine's name is empty or the
 * placeholder "Volume N Number M", the title becomes "Vol. N No. M" and the vol/issue part
 * leaves the meta-row (only the date remains). Article cards whose parent issue is unnamed
 * read "from Vol. N No. M" instead of repeating the placeholder.
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
 *     @type bool   $mobile_meta_rows Mobile meta line built from the meta-row values, "Vol. 75 No. 4, March 2023"
 *                                  (magazine carousel, Figma 839:946) instead of $card['mobile_meta']
 *                                  ("Magazine, 2023"). Applied after the unnamed-issue rule. Default false.
 *     @type string $image_size     Image size for wp_get_attachment_image(). Default 'medium_large'.
 *     @type string $class          Extra classes for the root <article>.
 * }
 */

use TenUpTheme\ProductPresenter;
use function TenUpTheme\class_names;

$args = wp_parse_args(
	$args ?? [],
	[
		'card'           => [],
		'variant'        => '',
		'heading_tag'    => 'h3',
		'mobile_details' => false,
		'mobile_byline'    => false,
		'mobile_meta_rows' => false,
		'image_size'       => 'medium_large',
		'class'          => '',
	]
);

$card = is_array( $args['card'] ) ? $args['card'] : [];
if ( empty( $card['id'] ) || empty( $card['title'] ) ) {
	return;
}

/**
 * Whether a magazine has no real name: empty, or the import placeholder "Volume N Number M".
 *
 * @param int $post_id Magazine ID.
 * @return bool
 */
$is_unnamed = static function ( $post_id ) {
	return '' === ProductPresenter::issue_name( (int) $post_id );
};

if ( 'magazine' === ( $card['type'] ?? '' ) ) {
	$vol_issue = ProductPresenter::volume_issue( (int) $card['id'] )['label'];
	if ( '' !== $vol_issue && $is_unnamed( $card['id'] ) ) {
		$card['title'] = $vol_issue;
		// Drop the vol/issue part from the meta-row; the date (meta_secondary) stays.
		if ( (string) ( $card['meta_primary'] ?? '' ) === $vol_issue ) {
			$card['meta_primary'] = '';
		}
	}
} elseif ( 'article' === ( $card['type'] ?? '' ) ) {
	$parent = ProductPresenter::parent_magazine( (int) $card['id'] );
	if ( $parent && '' !== $parent['volume_issue'] && $is_unnamed( $parent['id'] ) ) {
		/* translators: %s: "Vol. X No. Y". */
		$card['meta_secondary'] = sprintf( __( 'from %s', 'tenup-theme' ), $parent['volume_issue'] );
	}
}

if ( $args['mobile_meta_rows'] ) {
	$rows_meta           = implode( ', ', array_filter( [ (string) ( $card['meta_primary'] ?? '' ), (string) ( $card['meta_secondary'] ?? '' ) ] ) );
	$card['mobile_meta'] = '' !== $rows_meta ? $rows_meta : ( $card['mobile_meta'] ?? '' );
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
				<a href="<?php echo esc_url( $url ); ?>" class="hover:underline focus-visible:underline focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current"><?php echo esc_html( $card['title'] ); ?></a>
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
		<div class="<?php echo esc_attr( class_names( 'w-full items-start justify-between gap-xsmall overflow-x-clip border-t border-marg-red pt-2xsmall', $row_vis ) ); ?>">
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
