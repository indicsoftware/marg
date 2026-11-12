<?php
/**
 * Magazine (issue) single-product template.
 *
 * Figma: 834:370 "magazine-bp Desktop large" (1920) and 836:918 "magazine-bp Mobile" (390).
 * Loaded by woocommerce/single-product.php inside <main id="main"> with
 * $args = [ 'product_id' => int, 'product' => WC_Product ].
 *
 * Layout:
 *  - < tablet-lg (mobile, tablet portrait): stacked. Dark cover band (only when a cover
 *    exists) -> cover meta (h1, date, actions) -> issue details -> article list -> related carousel.
 *  - >= tablet-lg: 24-col page grid. Cover header in cols 2-15 (itself a 14-col grid: cover
 *    cols 1-7, meta column cols 9-13/14), details + article list in cols 17-23, then the
 *    full-bleed related section.
 *
 * Every field is optional: each block is omitted when its data is empty.
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductPresenter;

$product_id = (int) ( $args['product_id'] ?? get_the_ID() );
if ( $product_id < 1 ) {
	return;
}

$title       = ProductPresenter::title( $product_id );
$subtitle    = ProductPresenter::subtitle( $product_id );
$date        = ProductPresenter::publication_date( $product_id );
$cover_id    = ProductPresenter::cover_id( $product_id );
$specs       = ProductPresenter::physical_specs( $product_id );
$description = ProductPresenter::description_html( $product_id );
$editors     = ProductPresenter::editors( $product_id );
$articles    = ProductPresenter::issue_articles( $product_id );
$related     = ProductPresenter::related_cards( $product_id, 3 );
$open_access = ProductPresenter::is_open_access( $product_id );
$price_items = ProductPresenter::price_items( $product_id );

$uid          = 'marg-magazine-' . $product_id;
$date_attr    = '' !== $date['ymd'] ? substr( $date['ymd'], 0, 4 ) . '-' . substr( $date['ymd'], 4, 2 ) : '';
$title_prefix = '' !== $subtitle ? rtrim( $title, " \t:" ) . ':' : $title;

$editor_groups = [];
if ( ! empty( $editors['general'] ) ) {
	$editor_groups[] = [
		'label' => _n( 'Editor', 'Editors', count( $editors['general'] ), 'tenup-theme' ),
		'items' => $editors['general'],
	];
}
if ( ! empty( $editors['guest'] ) ) {
	$editor_groups[] = [
		'label' => _n( 'Guest editor', 'Guest editors', count( $editors['guest'] ), 'tenup-theme' ),
		'items' => $editors['guest'],
	];
}

$has_details = $specs || '' !== $description || $editor_groups;
?>
<article class="mx-auto w-full max-w-page text-marg-red" aria-labelledby="<?php echo esc_attr( $uid . '-title' ); ?>">

	<?php // No bottom padding on the row: the last article row meets the related rule (Figma 836:914). The cover header carries pb-xlarge for issues with a short right column. ?>
	<div class="tablet-lg:grid tablet-lg:grid-cols-24 tablet-lg:items-start tablet-lg:pt-[112px]">

		<?php /* Publication cover header (836:567 desktop, 838:1042 mobile). */ ?>
		<header class="tablet-lg:col-[2/16] tablet-lg:grid tablet-lg:grid-cols-14 tablet-lg:pb-xlarge">
			<?php if ( $cover_id ) : ?>
				<?php
				// m-0! beats WP global styles' unlayered `:where(figure){margin:0 0 1em}`.
				// Mobile: the band slides under the transparent site header (Figma 836:919), unless a WooCommerce notice sits above it.
				?>
				<figure class="m-0! flex justify-center max-tablet-lg:in-[.woocommerce-notices-wrapper:empty+.marg-product-template]:-mt-header! bg-linear-to-b from-[#1e1e1e] to-[rgba(30,30,30,0.8)] px-gutter py-jumbo tablet-lg:col-span-7 tablet-lg:block tablet-lg:bg-none tablet-lg:p-0">
					<?php
					echo wp_get_attachment_image(
						$cover_id,
						'large',
						false,
						[
							'class'         => 'block aspect-[350/480.392] h-auto w-[275px] max-w-full object-cover tablet-lg:w-full',
							/* translators: %s: magazine title. */
							'alt'           => sprintf( __( 'Cover of %s', 'tenup-theme' ), $title ),
							'sizes'         => '(min-width: 1920px) 560px, (min-width: 1133px) 30vw, 275px',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
						]
					);
					?>
				</figure>
			<?php else : ?>
				<div class="hidden aspect-[350/480.392] w-full bg-marg-cover-bg tablet-lg:col-span-7 tablet-lg:block" aria-hidden="true"></div>
			<?php endif; ?>

			<div class="flex flex-col gap-3xlarge px-gutter py-3xlarge tablet:mx-auto tablet:max-w-[640px] tablet-lg:col-[9/15] tablet-lg:mx-0 tablet-lg:max-w-none tablet-lg:items-start tablet-lg:justify-between tablet-lg:gap-[24px] tablet-lg:p-0 desktop-lg:col-[9/14]">
				<div class="flex flex-col items-center gap-small text-center tablet-lg:contents">
					<h1 id="<?php echo esc_attr( $uid . '-title' ); ?>" class="text-trim w-full break-words text-center font-sans text-xlarge font-semibold">
						<?php if ( '' !== $subtitle ) : ?>
							<?php echo esc_html( $title_prefix ); ?><br><?php echo esc_html( $subtitle ); ?>
						<?php else : ?>
							<?php echo esc_html( $title ); ?>
						<?php endif; ?>
					</h1>
					<?php if ( '' !== $date['label'] ) : ?>
						<p class="text-trim w-full text-center font-sans text-regular font-semibold tablet-lg:order-first tablet-lg:min-h-[50px]">
							<?php if ( '' !== $date_attr ) : ?>
								<time datetime="<?php echo esc_attr( $date_attr ); ?>"><?php echo esc_html( $date['label'] ); ?></time>
							<?php else : ?>
								<?php echo esc_html( $date['label'] ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<?php
				// Price row: desktop only in the design (mobile frame 836:918 has none).
				get_template_part(
					'partials/components/price-display',
					null,
					[
						'items'       => $price_items,
						'open_access' => $open_access,
						// Small caps sit on the italic numerals' baseline (Figma I836:567;296:438).
						'class'       => 'hidden tablet-lg:flex tablet-lg:items-baseline tablet-lg:*:items-baseline',
					]
				);

				get_template_part(
					'partials/product/magazine/actions',
					null,
					[
						'product_id' => $product_id,
						'title'      => $title,
						'uid'        => $uid,
					]
				);
				?>
			</div>
		</header>

		<?php if ( $has_details || $articles ) : ?>
			<div class="flex flex-col gap-3xlarge px-gutter tablet:mx-auto tablet:max-w-[640px] tablet-lg:col-[17/24] tablet-lg:mx-0 tablet-lg:max-w-none tablet-lg:gap-[72px] tablet-lg:px-0">

				<?php if ( $has_details ) : ?>
					<div class="flex flex-col gap-3xlarge tablet-lg:gap-[24px]">
						<?php if ( $specs || '' !== $description ) : ?>
							<div class="flex flex-col gap-3xlarge tablet-lg:gap-2xsmall">
								<?php if ( $specs ) : ?>
									<p class="text-trim font-sans text-xsmall font-bold uppercase"><?php echo esc_html( implode( ', ', $specs ) ); ?></p>
								<?php endif; ?>
								<?php
								get_template_part(
									'partials/components/text-lockup',
									null,
									[
										'variant'    => 'stacked',
										'label'      => __( 'About this issue', 'tenup-theme' ),
										'label_tag'  => 'h2',
										'value_html' => $description,
										'class'      => 'tablet-lg:[&>:first-child]:sr-only',
									]
								);
								?>
							</div>
						<?php endif; ?>

						<?php if ( $editor_groups ) : ?>
							<div class="flex flex-col gap-large tablet-lg:gap-[24px]">
								<?php
								foreach ( $editor_groups as $group ) {
									get_template_part(
										'partials/components/text-lockup',
										null,
										[
											'variant'   => 'stacked',
											'label'     => $group['label'],
											'label_tag' => 'p',
											'items'     => $group['items'],
										]
									);
								}
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $articles ) : ?>
					<section class="flex flex-col gap-3xlarge tablet-lg:gap-2xsmall" aria-labelledby="<?php echo esc_attr( $uid . '-articles' ); ?>">
						<h2 id="<?php echo esc_attr( $uid . '-articles' ); ?>" class="border-t border-marg-red py-2xsmall font-sans text-xsmall font-bold uppercase tablet-lg:border-t-0 tablet-lg:p-0">
							<span class="text-trim block"><?php esc_html_e( 'Articles in this issue', 'tenup-theme' ); ?></span>
						</h2>
						<ol class="flex flex-col gap-[24px] tablet-lg:gap-0">
							<?php
							foreach ( $articles as $row ) {
								get_template_part( 'partials/product/magazine/article-row', null, [ 'row' => $row ] );
							}
							?>
						</ol>
					</section>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	// Related publications (837:651 desktop, 839:941 mobile carousel).
	$related = array_values(
		array_filter(
			$related,
			static function ( $card ) {
				return ! empty( $card['id'] ) && ! empty( $card['title'] );
			}
		)
	);
	if ( $related ) :
		?>
		<section class="flex flex-col gap-medium px-gutter pb-large pt-xlarge tablet:mx-auto tablet:max-w-[640px] tablet-lg:mx-0 tablet-lg:max-w-none tablet-lg:gap-[64px] tablet-lg:pb-[35px] tablet-lg:pt-0" aria-labelledby="<?php echo esc_attr( $uid . '-related' ); ?>">
			<?php
			get_template_part(
				'partials/components/separator-section',
				null,
				[
					'label' => __( 'More such', 'tenup-theme' ),
					'id'    => $uid . '-related',
					// Figma: mobile pt 8 (839:942); desktop label 16px at 30% red, 24px below the rule (837:653).
					// Only the rule is faded to 30%: the label keeps the component's contrast-safe colour.
					'class' => 'pt-xsmall! tablet-lg:px-[calc(100%/24)] tablet-lg:pt-medium! tablet-lg:border-marg-red/30 tablet-lg:*:text-small',
				]
			);
			?>
			<ul class="-mx-gutter flex snap-x snap-mandatory scroll-px-gutter items-start gap-medium overflow-x-auto overscroll-x-contain px-gutter [scrollbar-width:none] [&::-webkit-scrollbar]:hidden tablet-lg:mx-0 tablet-lg:grid tablet-lg:grid-cols-3 tablet-lg:gap-x-[2.27%] tablet-lg:overflow-visible tablet-lg:px-[calc(100%/24)]">
				<?php foreach ( $related as $card ) : ?>
					<?php
					// Mobile meta line is "Vol. 75 No. 4, March 2023" for magazines (Figma 839:946).
					$mobile_meta         = implode( ', ', array_filter( [ $card['meta_primary'] ?? '', $card['meta_secondary'] ?? '' ] ) );
					$card['mobile_meta'] = '' !== $mobile_meta ? $mobile_meta : ( $card['mobile_meta'] ?? '' );
					?>
					<li class="w-[224px] shrink-0 snap-start tablet-lg:w-auto">
						<?php
						get_template_part(
							'partials/components/card-publication',
							null,
							[
								'card'          => $card,
								'variant'       => 'magazine',
								// Mobile shows optional "edited by" + OPEN ACCESS lines (Figma 839:972).
								'mobile_byline' => true,
							]
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</article>
