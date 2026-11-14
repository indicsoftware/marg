<?php
/**
 * Article single-product template (Figma 605:309 desktop with image,
 * 608:791 desktop without image, 550:106 mobile).
 *
 * Loaded by woocommerce/single-product.php inside <main id="main"> with
 * $args = [ 'product_id' => int, 'product' => WC_Product ].
 *
 * One markup serves every breakpoint. Below tablet-lg (1133px) it follows the
 * mobile frame: image on top, a 4-column "header-part" (page range | title)
 * with a 1px red vertical rule, then every block stacked 48px apart. From
 * tablet-lg it follows the desktop frames: an 18-of-24-column body with the
 * title column (cols 1-8), a spacer and the featured image (cols 11-18), the
 * metadata under the title column, the parent magazine link centred, and the
 * "More Such" row over 22 columns (printed after </main>, in footer.php's footer
 * group, so it slides up with the footer). With no featured image the title column
 * and the metadata are centred (cols 6-13), per 608:791.
 *
 * Wrappers that only exist for one layout use `contents` in the other one,
 * so their children join the parent's flex/grid flow (`order` re-sequences
 * them on desktop). Every field is optional: each block, label and rule is
 * omitted when its data is empty.
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductPresenter;
use function TenUpTheme\class_names;

$product_id = (int) ( $args['product_id'] ?? get_the_ID() );
if ( $product_id < 1 ) {
	return;
}

/* ---------------------------------------------------------------------------
 * Data (raw; escaped at output).
 * ------------------------------------------------------------------------ */
$title       = ProductPresenter::title( $product_id );
$permalink   = ProductPresenter::permalink( $product_id );
$authors     = ProductPresenter::authors( $product_id );
$pages       = ProductPresenter::pages( $product_id );
$open_access = ProductPresenter::is_open_access( $product_id );
$price_items = ProductPresenter::price_items( $product_id );
$abstract    = ProductPresenter::description_html( $product_id );
$citation    = ProductPresenter::citation( $product_id );
$actions     = ProductPresenter::actions( $product_id );
$image_id    = ProductPresenter::image_id( $product_id );
$caption     = $image_id ? ProductPresenter::caption( $image_id ) : '';
$bios        = ProductPresenter::author_bios( $product_id );
$tax_groups  = ProductPresenter::taxonomy_groups( $product_id );
$parent      = ProductPresenter::parent_magazine( $product_id );
$related     = array_values(
	array_filter(
		ProductPresenter::related_cards( $product_id ),
		static function ( $card ) {
			return ! empty( $card['id'] );
		}
	)
);

$has_image = $image_id > 0 && wp_attachment_is_image( $image_id );

// Parent issues without a real name (empty, or the placeholder "Volume N Number M") are
// named by their volume / issue instead (Figma 311:67 annotation), so the link does not
// read "from Vol. 66 No. 3, Volume 66 Number 3".
if ( $parent && '' !== $parent['volume_issue'] ) {
	if ( '' === ProductPresenter::issue_name( $parent['id'] ) ) {
		$parent['title']        = $parent['volume_issue'];
		$parent['volume_issue'] = '';
	}
}

// Metadata table: the Figma "LABEL / Value" slot (desktop) is PERMALINK on
// mobile. It shows the DOI when there is one, else the product permalink,
// and gets a "copy" control. Other rows come from the presenter.
$meta_rows      = [];
$permalink_row  = null;
$permalink_link = '';
foreach ( ProductPresenter::metadata_rows( $product_id ) as $meta_row ) {
	if ( 'doi' === $meta_row['key'] ) {
		$permalink_link = $meta_row['value'];
		continue;
	}
	$meta_rows[] = $meta_row;
}
if ( '' === $permalink_link ) {
	$permalink_link = $permalink;
}
if ( '' !== $permalink_link ) {
	$permalink_row = [
		'key'   => 'permalink',
		'label' => __( 'Permalink', 'tenup-theme' ),
		'value' => $permalink_link,
		'url'   => $permalink_link,
	];
	// Insert after title/author rows (Figma order: TITLE, AUTHOR, PERMALINK, PAGES, ...).
	$insert_at = 0;
	foreach ( $meta_rows as $index => $meta_row ) {
		if ( in_array( $meta_row['key'], [ 'title', 'authors' ], true ) ) {
			$insert_at = $index + 1;
		}
	}
	array_splice( $meta_rows, $insert_at, 0, [ $permalink_row ] );
}

// Actions: KEEP (only when enabled), VIEW (open/owned), BUY (no access, purchasable).
$show_keep   = ! empty( $actions['keep']['enabled'] );
$view        = is_array( $actions['view'] ?? null ) ? $actions['view'] : null;
$buy         = ( 'buy' === ( $actions['state'] ?? '' ) && ! empty( $actions['buy'] ) ) ? $actions['buy'][0] : null;
$has_buttons = $show_keep || $view || $buy;

$has_price_mobile = (bool) $price_items || $open_access;

$citation_id = 'marg-citation-' . $product_id;
$related_id  = 'marg-related-' . $product_id;

$has_metadata = $bios || $tax_groups || $meta_rows;
$mobile_rule  = '' !== $abstract && $has_metadata;
$desktop_rule = $bios && ( $tax_groups || $meta_rows );

// Desktop columns: 8/2/8 with an image, a single centred 8-col column without.
$column_cls = $has_image
	? 'tablet-lg:col-start-1 tablet-lg:col-span-8'
	: 'tablet-lg:col-start-6 tablet-lg:col-span-8';

/**
 * Print linked person names: "A", "A and B", "A, B and C".
 *
 * @param array $people Person view-models from ProductPresenter.
 */
$print_people = static function ( array $people ) {
	$people = array_values(
		array_filter(
			$people,
			static function ( $person ) {
				return '' !== trim( (string) ( $person['name'] ?? '' ) );
			}
		)
	);
	$count = count( $people );
	foreach ( $people as $index => $person ) {
		if ( $index > 0 ) {
			echo esc_html( $index === $count - 1 ? __( ' and ', 'tenup-theme' ) : ', ' );
		}
		if ( ! empty( $person['url'] ) ) {
			echo '<a class="hover:underline focus-visible:underline" href="' . esc_url( $person['url'] ) . '">' . esc_html( $person['name'] ) . '</a>';
		} else {
			echo esc_html( $person['name'] );
		}
	}
};
?>
<?php
/*
 * Top: the mobile image sits 22px below the 44px sticky header (Figma 550:106: image top
 * at y 66), under the header's 115px white fade as drawn. Without an image (not drawn on
 * mobile) the first text starts at y 115 (44 + 71), where that fade ends, so the title is
 * not washed out at scroll 0. Bottom: the related row is printed after </main> (see the end of
 * this file), so the article keeps the 48px / 72px (gap-12 / gap-y-2xlarge) that separated it from
 * that row; without related cards it has no mobile padding and the drawn ~80px on desktop. Below
 * the related row: no mobile padding, the footer's own pt-jumbo follows (the mobile frame leaves only
 * footer space there); desktop keeps the drawn ~80px.
 * Desktop top: 72px (pt-2xlarge) below the 210.5px header-desktop, as in 605:309 (Frame 6 itemSpacing 72);
 * the no-image article uses the same gap (608:791 still shows the old 200px header and is treated as stale).
 */
?>
<article class="<?php echo esc_attr( class_names( 'mx-auto flex w-full max-w-page flex-col gap-12 tablet-lg:grid tablet-lg:grid-cols-24 tablet-lg:gap-x-0 tablet-lg:gap-y-2xlarge tablet-lg:pt-2xlarge', $has_image ? 'pt-[22px]' : 'pt-[71px]', $related ? 'pb-12 tablet-lg:pb-2xlarge' : 'tablet-lg:pb-20' ) ); ?>">

	<?php /* ---------------------------------------------------------------- body (Figma "body", 18 cols) */ ?>
	<div class="flex w-full flex-col gap-12 tablet:mx-auto tablet:max-w-[642px] tablet-lg:col-span-18 tablet-lg:col-start-4 tablet-lg:mx-0 tablet-lg:grid tablet-lg:max-w-none tablet-lg:grid-cols-18 tablet-lg:gap-x-0 tablet-lg:gap-y-12">

		<?php /* ------------------------------------------------ title column (col-1-8) */ ?>
		<div class="<?php echo esc_attr( class_names( 'flex flex-col gap-12 px-gutter tablet-lg:row-start-1 tablet-lg:gap-2xlarge tablet-lg:self-start', $column_cls ) ); ?>">

			<?php /* header-part: mobile 4-col grid with vertical rule; desktop `contents`. */ ?>
			<div class="relative grid grid-cols-4 grid-rows-[minmax(91px,auto)_auto] tablet-lg:contents">
				<span class="pointer-events-none absolute inset-y-0 left-[17.5%] w-px bg-marg-red tablet-lg:hidden" aria-hidden="true"></span>

				<?php /* Heading block (Figma col-1 / heading-responsive). */ ?>
				<div class="contents tablet-lg:order-1 tablet-lg:flex tablet-lg:flex-col tablet-lg:gap-medium">
					<?php if ( '' !== $pages['label'] ) : ?>
						<p class="text-trim col-start-1 row-start-1 w-[70%] self-start break-words pr-micro font-sans text-small font-bold tablet-lg:w-auto tablet-lg:pr-0">
							<?php echo esc_html( $pages['label'] ); ?>
						</p>
					<?php endif; ?>

					<div class="col-span-3 col-start-2 row-start-1 flex flex-col gap-medium self-start pb-6 tablet-lg:pb-0">
						<h1 class="text-trim break-words font-sans text-xlarge font-regular"><?php echo esc_html( $title ); ?></h1>
						<?php if ( $authors ) : ?>
							<p class="text-trim break-words font-sans text-medium font-semibold tablet-lg:text-regular">
								<span class="sr-only"><?php esc_html_e( 'By', 'tenup-theme' ); ?> </span>
								<?php $print_people( $authors ); ?>
							</p>
						<?php endif; ?>
					</div>

					<?php if ( $price_items || $open_access ) : ?>
						<div class="hidden tablet-lg:block">
							<?php
							get_template_part(
								'partials/components/price-display',
								null,
								[
									'items'       => $price_items,
									'open_access' => $open_access,
									'align'       => 'end',
								]
							);
							?>
						</div>
					<?php endif; ?>
				</div>

				<?php /* Actions (Figma 605:318 desktop row; 564:646 mobile set1 + set2). */ ?>
				<div class="col-span-3 col-start-2 row-start-2 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-y-[53px] tablet-lg:order-4 tablet-lg:flex tablet-lg:min-h-circle tablet-lg:justify-between tablet-lg:gap-medium">
					<div class="col-span-2 row-start-2 flex items-center gap-small tablet-lg:contents">
						<?php
						get_template_part(
							'partials/components/link-action',
							null,
							[
								'action'      => 'share',
								'url'         => $permalink,
								'share_title' => $title,
								'class'       => 'min-w-[100px] tablet-lg:min-w-0',
							]
						);
						get_template_part(
							'partials/components/link-action',
							null,
							[
								'action'    => 'cite',
								'label'     => __( 'Cite', 'tenup-theme' ),
								'uppercase' => true,
								'controls'  => '' !== $citation ? $citation_id : '',
							]
						);
						?>
					</div>

					<div class="col-start-1 row-start-1 flex flex-col items-start gap-small tablet-lg:hidden">
						<p class="text-trim whitespace-nowrap font-serif text-regular"><?php esc_html_e( 'Digital Article', 'tenup-theme' ); ?></p>
						<?php
						if ( $has_price_mobile ) {
							get_template_part(
								'partials/components/price-display',
								null,
								[
									'items'       => $price_items,
									'open_access' => $open_access,
									'show_labels' => false,
								]
							);
						}
						?>
					</div>

					<?php if ( $has_buttons ) : ?>
						<div class="col-start-2 row-start-1 flex items-center gap-small tablet-lg:gap-3xsmall">
							<?php
							if ( $show_keep ) {
								get_template_part(
									'partials/components/button-action',
									null,
									[
										'action'   => 'keep',
										/* translators: %s: article title. */
										'sr_label' => sprintf( __( 'Keep %s', 'tenup-theme' ), $title ),
										'attrs'    => [ 'data-product-id' => $product_id ],
									]
								);
							}

							if ( $view ) {
								$view_enabled = ! empty( $view['enabled'] );
								get_template_part(
									'partials/components/button-action',
									null,
									[
										'action'   => 'view',
										/* translators: %s: article title. */
										'sr_label' => sprintf( __( 'View %s', 'tenup-theme' ), $title ),
										'url'      => $view_enabled ? (string) ( $view['url'] ?? '' ) : '',
										'disabled' => ! $view_enabled,
										'title'    => $view_enabled ? '' : __( 'Coming soon', 'tenup-theme' ),
										'attrs'    => [
											'data-secure-pdf' => $view_enabled ? true : false,
											'data-product-id' => (int) ( $view['product_id'] ?? $product_id ),
											'data-pdf-id'     => ! empty( $view['pdf_id'] ) ? (int) $view['pdf_id'] : false,
										],
									]
								);
							} elseif ( $buy ) {
								// A real WooCommerce add-to-cart POST (works without JS; WC's
								// form handler adds the product and the notice shows on reload).
								?>
								<form class="contents" method="post" action="<?php echo esc_url( $permalink ); ?>" enctype="multipart/form-data">
									<?php
									get_template_part(
										'partials/components/button-action',
										null,
										[
											'action'     => 'buy',
											// Switches to "IN CART" (Figma 243:67) once the article is in the cart.
											'product_id' => $product_id,
											'sr_label'   => trim(
												sprintf(
													/* translators: 1: article title, 2: currency, 3: price. */
													__( 'Buy %1$s %2$s %3$s', 'tenup-theme' ),
													$title,
													(string) ( $buy['currency'] ?? '' ),
													(string) ( $buy['amount'] ?? '' )
												)
											),
											'attrs'      => [
												'type'                 => 'submit',
												'name'                 => 'add-to-cart',
												'value'                => $product_id,
												'data-add-to-cart-url' => (string) ( $buy['url'] ?? '' ),
											],
										]
									);
									?>
								</form>
								<?php
							}
							?>
						</div>
					<?php else : ?>
						<?php // Keep Figma's three-slot justify-between on desktop so CITE stays mid-row, not flush right. ?>
						<span class="hidden tablet-lg:block" aria-hidden="true"></span>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $citation ) : ?>
					<div id="<?php echo esc_attr( $citation_id ); ?>" class="col-span-3 col-start-2 row-start-3 flex flex-col items-start gap-xsmall border-t border-marg-red/30 pt-medium tablet-lg:order-5 tablet-lg:-mt-12" hidden>
						<p class="break-words font-serif text-regular"><?php echo esc_html( $citation ); ?></p>
						<?php
						get_template_part(
							'partials/components/link-action',
							null,
							[
								'action' => 'copy',
								'label'  => __( 'copy citation', 'tenup-theme' ),
								'copy'   => $citation,
							]
						);
						?>
					</div>
				<?php endif; ?>
			</div>

			<?php
			// ABSTRACT (desktop: inside the title column; mobile: after header-part).
			get_template_part(
				'partials/components/text-lockup',
				null,
				[
					'variant'    => 'stacked',
					'label'      => __( 'Abstract', 'tenup-theme' ),
					'value_html' => $abstract,
					'class'      => 'tablet-lg:order-2',
				]
			);
			?>

			<p class="text-trim hidden font-serif text-regular tablet-lg:order-3 tablet-lg:block">
				<?php esc_html_e( 'This is a Digital article. You can read it on the Marg website using any device.', 'tenup-theme' ); ?>
			</p>
		</div>

		<?php /* ------------------------------------------------ media column (col-11-18 / mobile top) */ ?>
		<?php if ( $has_image ) : ?>
			<?php // m-0! beats WP global styles' unlayered `:where(figure){margin:0 0 1em}`, which outranks Preflight's layered reset. ?>
			<figure class="order-first m-0! px-[6px] tablet-lg:order-none tablet-lg:col-span-8 tablet-lg:col-start-11 tablet-lg:row-start-1 tablet-lg:px-0">
				<div class="flex flex-col gap-2xsmall border-b border-marg-grey pb-6 tablet-lg:border-0 tablet-lg:pb-0">
					<?php
					echo wp_get_attachment_image(
						$image_id,
						'large',
						false,
						[
							'class'         => 'block h-[328px] w-full object-contain tablet-lg:h-auto',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'decoding'      => 'async',
							'sizes'         => '(min-width: 1920px) 641px, (min-width: 1133px) 34vw, (min-width: 744px) 642px, 100vw',
						]
					);
					?>
					<?php if ( '' !== $caption ) : ?>
						<figcaption class="text-trim break-words font-sans text-xsmall text-marg-grey"><?php echo esc_html( $caption ); ?></figcaption>
					<?php endif; ?>
				</div>
			</figure>
		<?php endif; ?>

		<?php /* ------------------------------------------------ metadata (Figma Frame 2) */ ?>
		<?php if ( $has_metadata ) : ?>
			<div class="<?php echo esc_attr( class_names( 'flex flex-col gap-12 px-gutter tablet-lg:row-start-2', $column_cls ) ); ?>">
				<?php if ( $mobile_rule ) : ?>
					<hr class="w-full border-marg-red/30 tablet-lg:hidden">
				<?php endif; ?>

				<?php
				if ( $bios ) {
					$bio_html = '';
					foreach ( $bios as $person ) {
						if ( count( $bios ) > 1 ) {
							$bio_html .= '<p class="font-semibold">' . esc_html( $person['name'] ) . '</p>';
						}
						$bio_html .= wpautop( esc_html( $person['bio'] ) );
					}
					get_template_part(
						'partials/components/text-lockup',
						null,
						[
							'variant'    => 'stacked',
							'label'      => count( $bios ) > 1 ? __( 'About the authors', 'tenup-theme' ) : __( 'About the author', 'tenup-theme' ),
							'value_html' => $bio_html,
						]
					);
				}
				?>

				<?php if ( $desktop_rule ) : ?>
					<hr class="hidden w-full border-marg-red/30 tablet-lg:block">
				<?php endif; ?>

				<?php
				foreach ( $tax_groups as $group ) {
					get_template_part(
						'partials/components/text-lockup',
						null,
						[
							'variant' => 'tags',
							'label'   => $group['label'],
							'items'   => $group['terms'],
						]
					);
				}
				?>

				<?php if ( $meta_rows ) : ?>
					<dl class="flex flex-col gap-3xsmall">
						<?php foreach ( $meta_rows as $meta_row ) : ?>
							<?php if ( 'permalink' === $meta_row['key'] ) : ?>
								<div class="grid grid-cols-4 gap-x-[12px] border-t border-marg-red pt-3xsmall tablet-lg:grid-cols-8">
									<dt class="text-trim col-span-2 self-start font-sans text-xsmall font-bold uppercase tablet-lg:col-span-3"><?php echo esc_html( $meta_row['label'] ); ?></dt>
									<dd class="col-span-2 col-start-3 flex min-w-0 items-start justify-between gap-3xsmall font-sans text-small font-semibold tablet-lg:col-span-5 tablet-lg:col-start-4">
										<a class="text-trim min-w-0 truncate hover:underline" href="<?php echo esc_url( $meta_row['url'] ); ?>"><?php echo esc_html( $meta_row['value'] ); ?></a>
										<button type="button" class="flex shrink-0 opacity-30 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current" data-marg-action="copy" data-marg-copy="<?php echo esc_attr( $meta_row['value'] ); ?>" data-marg-copied-label="<?php esc_attr_e( 'copied!', 'tenup-theme' ); ?>">
											<span class="text-trim" data-marg-label aria-live="polite"><?php esc_html_e( 'copy', 'tenup-theme' ); ?></span><span class="sr-only"> <?php echo esc_html( $meta_row['label'] ); ?></span>
										</button>
									</dd>
								</div>
							<?php else : ?>
								<?php get_template_part( 'partials/components/text-lockup', null, [ 'variant' => 'row' ] + $meta_row ); ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php /* ------------------------------------------------ parent magazine link (Figma 605:637 / 582:338) */ ?>
		<?php if ( $parent && ! empty( $parent['url'] ) ) : ?>
			<div class="px-gutter tablet-lg:col-span-18 tablet-lg:col-start-1">
				<a href="<?php echo esc_url( $parent['url'] ); ?>" class="group relative grid min-h-[119px] grid-cols-4 tablet-lg:flex tablet-lg:min-h-0 tablet-lg:justify-center">
					<span class="pointer-events-none absolute inset-y-0 left-[17.5%] w-px bg-marg-red tablet-lg:hidden" aria-hidden="true"></span>
					<span class="col-span-3 col-start-2 flex flex-col items-start gap-4 tablet-lg:items-center tablet-lg:gap-medium">
						<span class="flex flex-col gap-2xsmall font-sans text-small tablet-lg:items-center tablet-lg:text-center">
							<span class="text-trim">
								<?php
								if ( '' !== $parent['volume_issue'] ) {
									/* translators: %s: volume and issue, e.g. "Vol. 70 No. 2". */
									echo esc_html( sprintf( __( 'This article is from %s,', 'tenup-theme' ), $parent['volume_issue'] ) );
								} else {
									esc_html_e( 'This article is from', 'tenup-theme' );
								}
								?>
							</span>
							<span class="text-trim break-words font-semibold group-hover:underline group-focus-visible:underline"><?php echo esc_html( $parent['title'] ); ?></span>
						</span>
						<?php if ( ! empty( $parent['cover_id'] ) ) : ?>
							<?php
							echo wp_get_attachment_image(
								(int) $parent['cover_id'],
								'medium',
								false,
								[
									'class'   => 'block aspect-[204/280] h-[71px] w-auto object-cover tablet-lg:aspect-auto tablet-lg:h-[172px] tablet-lg:w-[125px]',
									'alt'     => '',
									'loading' => 'lazy',
									'sizes'   => '(min-width: 1133px) 125px, 52px',
								]
							);
							?>
						<?php else : ?>
							<span class="block aspect-[204/280] h-[71px] bg-marg-cover-bg tablet-lg:aspect-auto tablet-lg:h-[172px] tablet-lg:w-[125px]" aria-hidden="true"></span>
						<?php endif; ?>
					</span>
				</a>
			</div>
		<?php endif; ?>
	</div>
</article>
<?php
/*
 * Related "More Such" (Figma 608:740 / 582:350). Printed in footer.php's footer group, before the
 * site footer, so it slides up into place together with the footer (608:740 revision: "the 'more
 * such' in every article and mag page will slide from the bottom along with the footer"). It sits
 * after </main> as a labelled complementary landmark. Same 24-col page grid as the article
 * (cols 2-23), 80px above the footer on desktop.
 */
if ( $related ) {
	add_action(
		'tenup_theme_before_footer',
		static function () use ( $related, $related_id ) {
			?>
			<aside class="mx-auto w-full max-w-page tablet-lg:grid tablet-lg:grid-cols-24 tablet-lg:pb-20" aria-labelledby="<?php echo esc_attr( $related_id ); ?>">
				<div class="flex min-w-0 flex-col gap-6 px-gutter tablet-lg:col-span-22 tablet-lg:col-start-2 tablet-lg:gap-xsmall">
					<?php
					get_template_part(
						'partials/components/separator-section',
						null,
						[
							'label' => __( 'More Such', 'tenup-theme' ),
							'id'    => $related_id,
						]
					);
					?>
					<ul class="-mr-gutter flex snap-x snap-mandatory gap-2xsmall overflow-x-auto pr-gutter [scrollbar-width:none] tablet-lg:mr-0 tablet-lg:grid tablet-lg:grid-cols-3 tablet-lg:gap-x-[5.68%] tablet-lg:overflow-visible tablet-lg:pr-0">
						<?php foreach ( $related as $card ) : ?>
							<li class="flex w-[250px] shrink-0 snap-start tablet-lg:w-auto">
								<?php
								get_template_part(
									'partials/components/card-publication',
									null,
									[
										'card'    => $card,
										'variant' => 'article',
										'class'   => 'w-full',
									]
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</aside>
			<?php
		}
	);
}
