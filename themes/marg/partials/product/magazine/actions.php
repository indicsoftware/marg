<?php
/**
 * Magazine cover-meta actions row (Figma I836:567;296:358 desktop, I838:1042;303:458 mobile)
 * plus the citation panel revealed by CITE.
 *
 * Mobile: [copy-link icon (label visually hidden), CITE] left, circles right.
 * Desktop (>= tablet-lg): copy link | CITE | circles spread with justify-between.
 *
 * Circles (ProductPresenter::actions()):
 *  - KEEP only when keep.enabled (no backend yet; filter marg_keep_enabled).
 *  - VIEW when the visitor has access (open access / owned). Disabled ("Coming soon")
 *    until a PDF or WC download exists; when enabled it carries data-secure-pdf +
 *    data-product-id (digital variation) for the SecurePDFViewer.
 *  - BUY when at least one medium is purchasable. One medium: a direct add-to-cart link.
 *    Two media: BUY opens a popover with one WooCommerce add-to-cart form per medium.
 *    When the magazine or any of its variations is in the cart, BUY renders its "IN CART"
 *    active state (Figma 243:67) linking to the cart (button-action.php does the cart check).
 *
 * Copy link: mobile shows the icon only (838:1042); the "copied!" feedback label is
 * revealed while data-state="copied" (Figma 269:285).
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type int    $product_id Magazine ID.
 *     @type string $title      Magazine title (plain).
 *     @type string $uid        Unique id prefix.
 * }
 */

use TenUpTheme\ProductPresenter;

$product_id = (int) ( $args['product_id'] ?? 0 );
if ( $product_id < 1 ) {
	return;
}
$title    = (string) ( $args['title'] ?? ProductPresenter::title( $product_id ) );
$uid      = (string) ( $args['uid'] ?? 'marg-magazine-' . $product_id );
$actions  = ProductPresenter::actions( $product_id );
$citation = ProductPresenter::citation( $product_id );
$cite_id  = $uid . '-citation';
$buy_id   = $uid . '-buy';
$buy      = array_values( (array) $actions['buy'] );
$view     = $actions['view'];
$keep     = ! empty( $actions['keep']['enabled'] );

$has_circles = $keep || null !== $view || $buy;

/**
 * Price text for a buy entry, e.g. "INR 500".
 *
 * @param array $entry Buy entry.
 * @return string
 */
$price_text = static function ( $entry ) {
	return trim( ( $entry['currency'] ?? '' ) . ' ' . ( $entry['amount'] ?? '' ) );
};
?>
<div class="flex w-full flex-col gap-medium">
	<div class="flex w-full flex-wrap items-center justify-between gap-x-small gap-y-xsmall">
		<div class="flex items-center gap-small tablet-lg:contents">
			<?php
			get_template_part(
				'partials/components/link-action',
				null,
				[
					'action'      => 'copy',
					'label'       => __( 'copy link', 'tenup-theme' ),
					'url'         => ProductPresenter::permalink( $product_id ),
					'share_title' => $title,
					// Mobile shows the icon only (Figma 838:1042); keep the label for screen readers,
					// and reveal it for the "copied!" feedback.
					'class'       => 'max-tablet-lg:min-w-[100px] max-tablet-lg:[&:not([data-state=copied])_[data-marg-label]]:sr-only',
				]
			);
			if ( '' !== $citation ) {
				get_template_part(
					'partials/components/link-action',
					null,
					[
						'action'    => 'cite',
						'label'     => __( 'cite', 'tenup-theme' ),
						'uppercase' => true,
						'controls'  => $cite_id,
					]
				);
			}
			?>
		</div>

		<?php if ( $has_circles ) : ?>
			<div class="flex items-center justify-end gap-[8px]">
				<?php
				if ( $keep ) {
					get_template_part(
						'partials/components/button-action',
						null,
						[
							'action'   => 'keep',
							/* translators: %s: magazine title. */
							'sr_label' => sprintf( __( 'Keep %s', 'tenup-theme' ), $title ),
							'attrs'    => [ 'data-product-id' => $product_id ],
						]
					);
				}

				if ( null !== $view ) {
					$view_enabled = ! empty( $view['enabled'] );
					get_template_part(
						'partials/components/button-action',
						null,
						[
							'action'   => 'view',
							/* translators: %s: magazine title. */
							'sr_label' => sprintf( __( 'View digital copy of %s', 'tenup-theme' ), $title ),
							'url'      => (string) ( $view['url'] ?? '' ),
							'disabled' => ! $view_enabled,
							'title'    => $view_enabled ? '' : __( 'Coming soon', 'tenup-theme' ),
							'attrs'    => $view_enabled ? [
								'data-secure-pdf' => true,
								'data-product-id' => (int) $view['product_id'],
							] : [],
						]
					);
				}

				if ( 1 === count( $buy ) ) {
					$entry = $buy[0];
					get_template_part(
						'partials/components/button-action',
						null,
						[
							'action'     => 'buy',
							/* translators: 1: "Buy print copy" / "Buy digital copy", 2: magazine title, 3: price e.g. INR 500. */
							'sr_label'   => trim( sprintf( __( '%1$s of %2$s, %3$s', 'tenup-theme' ), $entry['label'], $title, $price_text( $entry ) ), ', ' ),
							'url'        => $entry['url'],
							'product_id' => $product_id,
							'attrs'      => [
								'rel'             => 'nofollow',
								'data-product-id' => $product_id,
								'data-variation'  => (int) $entry['variation_id'],
							],
						]
					);
				} elseif ( count( $buy ) > 1 ) {
					get_template_part(
						'partials/components/button-action',
						null,
						[
							'action'     => 'buy',
							/* translators: %s: magazine title. */
							'sr_label'   => sprintf( __( 'Buy %s: choose print or digital copy', 'tenup-theme' ), $title ),
							'product_id' => $product_id,
							'attrs'      => [
								'popovertarget' => $buy_id,
								'aria-haspopup' => 'dialog',
							],
						]
					);
				}
				?>
			</div>
		<?php else : ?>
			<?php // Keep Figma's three-slot justify-between on desktop so CITE stays mid-row, not flush right. ?>
			<span class="hidden tablet-lg:block" aria-hidden="true"></span>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $citation ) : ?>
		<div id="<?php echo esc_attr( $cite_id ); ?>" class="flex w-full flex-col items-start gap-2xsmall border-t border-marg-red pt-3xsmall" hidden>
			<p class="w-full font-sans text-small font-regular [overflow-wrap:anywhere]"><?php echo esc_html( $citation ); ?></p>
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

	<?php if ( count( $buy ) > 1 ) : ?>
		<div id="<?php echo esc_attr( $buy_id ); ?>" popover role="dialog" aria-labelledby="<?php echo esc_attr( $buy_id . '-label' ); ?>" class="m-auto w-[min(calc(100%-24px),420px)] border border-marg-red bg-white p-medium text-marg-red backdrop:bg-black/40">
			<div class="flex flex-col gap-medium">
				<div class="flex items-start justify-between gap-small">
					<p id="<?php echo esc_attr( $buy_id . '-label' ); ?>" class="text-trim font-sans text-xsmall font-bold uppercase">
						<?php
						/* translators: %s: magazine title. */
						echo esc_html( sprintf( __( 'Buy %s', 'tenup-theme' ), $title ) );
						?>
					</p>
					<button type="button" popovertarget="<?php echo esc_attr( $buy_id ); ?>" popovertargetaction="hide" class="text-trim font-sans text-xsmall font-bold underline focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current"><?php esc_html_e( 'close', 'tenup-theme' ); ?></button>
				</div>
				<ul class="flex flex-col gap-3xsmall">
					<?php foreach ( $buy as $entry ) : ?>
						<li>
							<form method="post" action="<?php echo esc_url( ProductPresenter::permalink( $product_id ) ); ?>" class="flex items-center justify-between gap-small border-t border-marg-red pt-3xsmall">
								<p class="flex flex-wrap items-baseline gap-x-micro font-serif">
									<span class="text-xsmall uppercase"><?php echo esc_html( 'print' === $entry['medium'] ? __( 'Print copy:', 'tenup-theme' ) : __( 'Digital copy:', 'tenup-theme' ) ); ?></span>
									<?php if ( '' !== (string) $entry['currency'] ) : ?>
										<span class="text-xsmall uppercase"><?php echo esc_html( $entry['currency'] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== (string) $entry['amount'] ) : ?>
										<span class="text-xlarge italic"><?php echo esc_html( $entry['amount'] ); ?></span>
									<?php endif; ?>
								</p>
								<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>">
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
								<input type="hidden" name="variation_id" value="<?php echo esc_attr( (int) $entry['variation_id'] ); ?>">
								<input type="hidden" name="attribute_pa_medium" value="<?php echo esc_attr( $entry['medium'] ); ?>">
								<input type="hidden" name="quantity" value="1">
								<?php
								// Same circle as the row's BUY (hover: black fill, white label); IN CART per medium.
								get_template_part(
									'partials/components/button-action',
									null,
									[
										'action'     => 'buy',
										'sr_label'   => $entry['label'] . ', ' . $price_text( $entry ),
										'product_id' => (int) $entry['variation_id'],
										'attrs'      => [ 'type' => 'submit' ],
									]
								);
								?>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>
</div>
