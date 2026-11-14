<?php
/**
 * Component: price/display (Figma col-1 price 605:315, magazine price-row I836:567;296:438,
 * card meta-row right slot 608:704).
 *
 * Renders "PRINT COPY: INR 500 / DIGITAL COPY: INR 400": serif caps labels
 * (text-xsmall), serif italic numerals (text-xlarge), "/" between items.
 * When there are no items and `open_access` is true it renders "OPEN ACCESS"
 * (serif caps). Renders nothing when there is nothing to show.
 *
 * Usage:
 * get_template_part( 'partials/components/price-display', null, [
 *     'items'       => \TenUpTheme\ProductPresenter::price_items( $product_id ),
 *     'open_access' => \TenUpTheme\ProductPresenter::is_open_access( $product_id ),
 * ] );
 *
 * Card usage (lowest price, no medium label):
 * $price = $card['price']; // ProductPresenter::price_summary()
 * get_template_part( 'partials/components/price-display', null, [
 *     'items'       => $price['amount'] ? [ [ 'currency' => $price['currency'], 'amount' => $price['amount'] ] ] : [],
 *     'open_access' => $price['open_access'],
 *     'show_labels' => false,
 * ] );
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type array  $items       List of [ 'label' => 'Print copy:', 'currency' => 'INR', 'amount' => '500' ]
 *                               (ProductPresenter::price_items()). label and currency are optional.
 *     @type bool   $open_access Show "OPEN ACCESS" when there are no items. Default false.
 *     @type bool   $prefer_open_access Show "OPEN ACCESS" even when items exist. Default false.
 *     @type bool   $show_labels Render each item's label ("PRINT COPY:"). Default true.
 *     @type string $align       'start' | 'center' | 'end'. Default 'start'.
 *     @type string $open_access_label Override for the "Open access" text.
 *     @type string $class       Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;

$args = wp_parse_args(
	$args ?? [],
	[
		'items'              => [],
		'open_access'        => false,
		'prefer_open_access' => false,
		'show_labels'        => true,
		'align'              => 'start',
		'open_access_label'  => __( 'Open access', 'tenup-theme' ),
		'class'              => '',
	]
);

$items = array_values(
	array_filter(
		(array) $args['items'],
		static function ( $item ) {
			return is_array( $item ) && isset( $item['amount'] ) && '' !== trim( (string) $item['amount'] );
		}
	)
);

$show_open_access = $args['open_access'] && ( ! $items || $args['prefer_open_access'] );

if ( ! $items && ! $show_open_access ) {
	return;
}

$justify = [
	'start'  => 'justify-start',
	'center' => 'justify-center',
	'end'    => 'justify-end',
];
$root    = class_names(
	'flex flex-wrap items-center gap-x-micro gap-y-3xsmall text-marg-red',
	$justify[ $args['align'] ] ?? 'justify-start',
	$args['class']
);
?>
<?php if ( $show_open_access ) : ?>
	<p class="<?php echo esc_attr( $root ); ?>">
		<span class="text-trim whitespace-nowrap font-serif text-xsmall uppercase"><?php echo esc_html( $args['open_access_label'] ); ?></span>
	</p>
<?php else : ?>
	<p class="<?php echo esc_attr( $root ); ?>">
		<?php foreach ( $items as $index => $item ) : ?>
			<?php if ( $index > 0 ) : ?>
				<span class="text-trim font-serif text-xlarge italic" aria-hidden="true">/</span>
			<?php endif; ?>
			<span class="inline-flex items-center gap-micro whitespace-nowrap">
				<?php if ( $args['show_labels'] && ! empty( $item['label'] ) ) : ?>
					<span class="text-trim font-serif text-xsmall uppercase"><?php echo esc_html( $item['label'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $item['currency'] ) ) : ?>
					<span class="text-trim font-serif text-xsmall uppercase"><?php echo esc_html( $item['currency'] ); ?></span>
				<?php endif; ?>
				<span class="text-trim font-serif text-xlarge italic"><?php echo esc_html( $item['amount'] ); ?></span>
			</span>
		<?php endforeach; ?>
	</p>
<?php endif; ?>
