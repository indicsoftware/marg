<?php
/**
 * Component: button/action circle, KEEP / VIEW / BUY (Figma 605:322, 605:323, I836:567;296:363).
 *
 * 50px (mobile) / 70px (>= tablet-lg) circle via `size-circle`, 1px border,
 * serif caps label (text-medium). Colours: KEEP marg-red, VIEW marg-grey, BUY marg-black.
 * Renders <a> when `url` is given, otherwise <button type="button">.
 * Group circles with: <div class="flex gap-3xsmall">…</div>.
 *
 * Usage:
 * get_template_part( 'partials/components/button-action', null, [
 *     'action'      => 'buy',
 *     'url'         => $buy['url'],
 *     'sr_label'    => sprintf( __( 'Buy print copy of %s', 'tenup-theme' ), $title ),
 * ] );
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type string $action   Required. 'keep' | 'view' | 'buy' (sets colour, default label, data-marg-action).
 *     @type string $label    Visible label. Default KEEP / VIEW / BUY (uppercased by CSS anyway).
 *     @type string $sr_label Accessible name (aria-label), e.g. "Buy print copy of {title}". Optional.
 *     @type string $url      Link target; renders an <a>. Empty renders a <button>.
 *     @type bool   $disabled Render disabled (aria-disabled on <a>, disabled on <button>), 40% opacity.
 *     @type string $title    Optional title/tooltip (e.g. "Coming soon").
 *     @type array  $attrs    Extra attributes (data-*, aria-*, rel…). Escaped by html_attrs().
 *     @type string $class    Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;
use function TenUpTheme\html_attrs;

$args = wp_parse_args(
	$args ?? [],
	[
		'action'   => '',
		'label'    => '',
		'sr_label' => '',
		'url'      => '',
		'disabled' => false,
		'title'    => '',
		'attrs'    => [],
		'class'    => '',
	]
);

$variants = [
	'keep' => [
		'label' => __( 'Keep', 'tenup-theme' ),
		'class' => 'border-marg-red text-marg-red',
	],
	'view' => [
		'label' => __( 'View', 'tenup-theme' ),
		'class' => 'border-marg-grey text-marg-grey',
	],
	'buy'  => [
		'label' => __( 'Buy', 'tenup-theme' ),
		'class' => 'border-marg-black text-marg-black',
	],
];

if ( ! isset( $variants[ $args['action'] ] ) ) {
	return;
}

$variant  = $variants[ $args['action'] ];
$label    = '' !== trim( (string) $args['label'] ) ? $args['label'] : $variant['label'];
$disabled = (bool) $args['disabled'];
$is_link  = '' !== trim( (string) $args['url'] ) && ! $disabled;

$classes = class_names(
	'inline-flex size-circle shrink-0 items-center justify-center rounded-full border font-serif text-medium font-regular uppercase leading-[1.2] whitespace-nowrap no-underline transition-colors',
	'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-current',
	$variant['class'],
	$disabled ? 'cursor-not-allowed opacity-40' : 'hover:bg-current/5',
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
