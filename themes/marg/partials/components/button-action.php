<?php
/**
 * Component: button/action circle, KEEP / VIEW / BUY (Figma 605:322, 605:323, I836:567;296:363;
 * states on the components page frame 234:6).
 *
 * 50px (mobile) / 60px (tablet) / 70px (>= tablet-lg) circle, 1px border, serif caps
 * label (text-medium). The box is --marg-action-circle (tailwind.css "Type-derived
 * component sizes"): the stepped size-circle token, grown with the fluid text-medium
 * where the label would otherwise touch the border (VIEW is ~2.6em wide). Colours: KEEP marg-red, VIEW marg-grey, BUY marg-black.
 * Hover (234:2, 243:61, 243:59): solid fill in the stroke colour with a white label
 * (not on disabled buttons). Active (234:4 "IN SHELF", 243:67 "IN CART"): no border,
 * no fill, Sans bold caps text-xsmall label in marg-red, centred in the same circle box.
 * BUY switches to its active "IN CART" state on its own when `product_id` (or any of
 * its variations) is in the WooCommerce cart; the active BUY links to the cart.
 * Renders <a> when `url` is given, otherwise <button type="button">.
 * Group circles with: <div class="flex gap-3xsmall">…</div>.
 *
 * Usage:
 * get_template_part( 'partials/components/button-action', null, [
 *     'action'      => 'buy',
 *     'url'         => $buy['url'],
 *     'product_id'  => $product_id,
 *     'sr_label'    => sprintf( __( 'Buy print copy of %s', 'tenup-theme' ), $title ),
 * ] );
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type string    $action       Required. 'keep' | 'view' | 'buy' (sets colour, default label, data-marg-action).
 *     @type string    $label        Visible label. Default KEEP / VIEW / BUY (uppercased by CSS anyway).
 *     @type string    $sr_label     Accessible name (aria-label), e.g. "Buy print copy of {title}". Optional.
 *     @type string    $url          Link target; renders an <a>. Empty renders a <button>.
 *     @type bool      $disabled     Render disabled (aria-disabled on <a>, disabled on <button>), 40% opacity.
 *     @type string    $title        Optional title/tooltip (e.g. "Coming soon").
 *     @type int       $product_id   BUY only: product (or variation) ID whose presence in the cart
 *                                   switches the button to "IN CART". Default 0 (no cart check).
 *     @type bool|null $active       Force the active state on (true) or off (false). Default null:
 *                                   automatic (BUY + product in cart). KEEP has no backend yet.
 *     @type string    $active_label Active label. Default "In cart" (BUY) / "In shelf" (KEEP).
 *     @type string    $active_url   Active BUY link. Default wc_get_cart_url().
 *     @type array     $attrs        Extra attributes (data-*, aria-*, rel…). Escaped by html_attrs().
 *                                   Ignored in the active state, which is a plain link / text.
 *     @type string    $class        Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;
use function TenUpTheme\html_attrs;

$args = wp_parse_args(
	$args ?? [],
	[
		'action'       => '',
		'label'        => '',
		'sr_label'     => '',
		'url'          => '',
		'disabled'     => false,
		'title'        => '',
		'product_id'   => 0,
		'active'       => null,
		'active_label' => '',
		'active_url'   => '',
		'attrs'        => [],
		'class'        => '',
	]
);

$variants = [
	'keep' => [
		'label'        => __( 'Keep', 'tenup-theme' ),
		'active_label' => __( 'In shelf', 'tenup-theme' ),
		'class'        => 'border-marg-red text-marg-red',
		'hover'        => 'hover:bg-marg-red hover:text-white',
	],
	'view' => [
		'label'        => __( 'View', 'tenup-theme' ),
		'active_label' => '',
		'class'        => 'border-marg-grey text-marg-grey',
		'hover'        => 'hover:bg-marg-grey hover:text-white',
	],
	'buy'  => [
		'label'        => __( 'Buy', 'tenup-theme' ),
		'active_label' => __( 'In cart', 'tenup-theme' ),
		'class'        => 'border-marg-black text-marg-black',
		'hover'        => 'hover:bg-marg-black hover:text-white',
	],
];

if ( ! isset( $variants[ $args['action'] ] ) ) {
	return;
}

$variant  = $variants[ $args['action'] ];
$label    = '' !== trim( (string) $args['label'] ) ? $args['label'] : $variant['label'];
$disabled = (bool) $args['disabled'];

/*
 * Active state. BUY is active when the product, or any of its variations, is in the
 * cart. WC()->cart is null outside the front end (REST, cron, admin) and before
 * wp_loaded, so every step is guarded.
 */
$active = null !== $args['active'] ? (bool) $args['active'] : false;
if ( null === $args['active'] && 'buy' === $args['action'] && (int) $args['product_id'] > 0 && function_exists( 'WC' ) && WC() && WC()->cart ) {
	$cart_ids   = [ (int) $args['product_id'] ];
	$cart_owner = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $args['product_id'] ) : null;
	if ( $cart_owner ) {
		$cart_ids = array_merge( $cart_ids, array_map( 'intval', (array) $cart_owner->get_children() ) );
	}
	foreach ( (array) WC()->cart->get_cart() as $cart_item ) {
		if ( in_array( (int) ( $cart_item['product_id'] ?? 0 ), $cart_ids, true ) || in_array( (int) ( $cart_item['variation_id'] ?? 0 ), $cart_ids, true ) ) {
			$active = true;
			break;
		}
	}
}
$active_label = '' !== trim( (string) $args['active_label'] ) ? $args['active_label'] : $variant['active_label'];
$active       = $active && '' !== $active_label;

if ( $active ) {
	$active_url = (string) $args['active_url'];
	if ( '' === $active_url && 'buy' === $args['action'] && function_exists( 'wc_get_cart_url' ) ) {
		$active_url = (string) wc_get_cart_url();
	}
	$is_link = '' !== trim( $active_url );
	$attrs   = [
		'class'            => class_names(
			'inline-flex size-(--marg-action-circle) shrink-0 items-center justify-center font-sans text-xsmall font-bold uppercase leading-[1.2] whitespace-nowrap text-marg-red no-underline',
			'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current',
			$is_link ? 'hover:underline' : '',
			$args['class']
		),
		'data-marg-action' => $args['action'],
		'data-state'       => 'active',
		'href'             => $is_link ? $active_url : false,
		'aria-label'       => '' !== (string) $args['sr_label'] ? sprintf(
			/* translators: 1: active label, e.g. "In cart", 2: original accessible name, e.g. "Buy print copy of Ars Botanica". */
			__( '%1$s: %2$s', 'tenup-theme' ),
			$active_label,
			$args['sr_label']
		) : false,
	];
	$tag     = $is_link ? 'a' : 'span';
	?>
	<<?php echo tag_escape( $tag ); ?><?php echo html_attrs( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html_attrs(). ?>><span class="text-trim"><?php echo esc_html( $active_label ); ?></span></<?php echo tag_escape( $tag ); ?>>
	<?php
	return;
}

$is_link = '' !== trim( (string) $args['url'] ) && ! $disabled;

$classes = class_names(
	'inline-flex size-(--marg-action-circle) shrink-0 items-center justify-center rounded-full border font-serif text-medium font-regular uppercase leading-[1.2] whitespace-nowrap no-underline transition-colors',
	'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current',
	$variant['class'],
	$disabled ? 'cursor-not-allowed opacity-40' : $variant['hover'],
	$args['class']
);

$attrs = array_merge(
	[
		'class'            => $classes,
		'data-marg-action' => $args['action'],
		'aria-label'       => $args['sr_label'],
		'title'            => $args['title'],
	],
	$is_link
		? [ 'href' => $args['url'] ]
		: [
			'type'          => 'button',
			'disabled'      => $disabled,
			'aria-disabled' => $disabled ? 'true' : false,
		],
	(array) $args['attrs']
);

$tag = $is_link ? 'a' : 'button';
?>
<<?php echo tag_escape( $tag ); ?><?php echo html_attrs( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html_attrs(). ?>><span class="text-trim"><?php echo esc_html( $label ); ?></span></<?php echo tag_escape( $tag ); ?>>
