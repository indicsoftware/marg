<?php
/**
 * Component: text/lockup (Figma 605:354, 605:316, 836:680, 605:356, 553:268, 605:361, 569:1049).
 *
 * Variants:
 *  - 'stacked' (A): Bold caps label + body text. "ABSTRACT", "ABOUT THE AUTHOR", "EDITOR".
 *        Body is `value` (plain text), `value_html` (sanitised with wp_kses_post) or `items` (linked names).
 *  - 'tags'    (B): Bold caps label + linked items joined by "; " (underlined, text-medium). "SUBJECTS".
 *  - 'row'     (C): Table row, label | value, 1px top rule. MUST be inside a <dl>
 *        (e.g. <dl class="flex flex-col gap-3xsmall">). 4 cols mobile / 8 cols >= tablet-lg.
 * Renders nothing when there is no value / no items.
 *
 * Usage:
 * get_template_part( 'partials/components/text-lockup', null, [
 *     'variant' => 'tags',
 *     'label'   => $group['label'],
 *     'items'   => $group['terms'],          // [ [ 'name' => , 'url' => ], … ]
 * ] );
 * get_template_part( 'partials/components/text-lockup', null, [
 *     'variant'    => 'stacked',
 *     'label'      => __( 'Abstract', 'tenup-theme' ),
 *     'value_html' => ProductPresenter::description_html( $id ),
 * ] );
 * echo '<dl class="flex flex-col gap-3xsmall">';
 * foreach ( ProductPresenter::metadata_rows( $id ) as $row ) {
 *     get_template_part( 'partials/components/text-lockup', null, [ 'variant' => 'row' ] + $row );
 * }
 * echo '</dl>';
 *
 * @package TenUpTheme
 *
 * @var array $args {
 *     @type string $variant    'stacked' (default) | 'tags' | 'row'.
 *     @type string $label      Label text (rendered uppercase by CSS).
 *     @type string $label_tag  Label element for stacked/tags. Default 'h2' (h2-h4, p, span). Rows always use <dt>.
 *     @type string $value      Plain-text value (stacked, row).
 *     @type string $value_html HTML value for stacked (passed through wp_kses_post). Takes precedence over value.
 *     @type string $url        Row only: link the value.
 *     @type array  $items      tags/stacked: [ [ 'name' => string, 'url' => string ], … ]. Unlinked when url is ''.
 *     @type string $separator  tags/stacked items separator. Default '; ' for tags, ', ' for stacked.
 *     @type bool   $copy       Row only: append a copy link/action that copies `value` (PERMALINK/DOI row).
 *     @type string $id         Optional id for the root element.
 *     @type string $class      Extra classes for the root element.
 * }
 */

use function TenUpTheme\class_names;

$args = wp_parse_args(
	$args ?? [],
	[
		'variant'    => 'stacked',
		'label'      => '',
		'label_tag'  => 'h2',
		'value'      => '',
		'value_html' => '',
		'url'        => '',
		'items'      => [],
		'separator'  => null,
		'copy'       => false,
		'id'         => '',
		'class'      => '',
	]
);

$variant   = in_array( $args['variant'], [ 'stacked', 'tags', 'row' ], true ) ? $args['variant'] : 'stacked';
$label     = trim( (string) $args['label'] );
$label_tag = in_array( $args['label_tag'], [ 'h2', 'h3', 'h4', 'p', 'span' ], true ) ? $args['label_tag'] : 'h2';
$value     = trim( (string) $args['value'] );
$html      = trim( (string) $args['value_html'] );
$items     = array_values(
	array_filter(
		(array) $args['items'],
		static function ( $item ) {
			return is_array( $item ) && '' !== trim( (string) ( $item['name'] ?? '' ) );
		}
	)
);
$id_attr   = $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
$label_cls = 'text-trim font-sans text-xsmall font-bold uppercase';

/**
 * Print a list of (optionally linked) names separated by $separator.
 *
 * @param array  $items     Items.
 * @param string $separator Separator.
 * @param string $link_cls  Link classes.
 */
$print_items = static function ( $items, $separator, $link_cls ) {
	$last = count( $items ) - 1;
	foreach ( $items as $index => $item ) {
		$name = trim( (string) $item['name'] );
		$url  = trim( (string) ( $item['url'] ?? '' ) );
		echo '<span class="inline">';
		if ( '' !== $url ) {
			echo '<a class="' . esc_attr( $link_cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		} else {
			echo esc_html( $name );
		}
		if ( $index < $last ) {
			echo esc_html( $separator );
		}
		echo '</span> ';
	}
};

if ( 'row' === $variant ) :
	if ( '' === $value ) {
		return;
	}
	?>
	<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> class="<?php echo esc_attr( class_names( 'grid grid-cols-4 gap-x-[12px] border-t border-marg-red pt-3xsmall tablet-lg:grid-cols-8', $args['class'] ) ); ?>">
		<dt class="<?php echo esc_attr( class_names( $label_cls, 'col-span-2 self-start tablet-lg:col-span-3' ) ); ?>"><?php echo esc_html( $label ); ?></dt>
		<dd class="col-span-2 col-start-3 flex min-w-0 items-start justify-between gap-3xsmall font-sans text-small font-semibold tablet-lg:col-span-5 tablet-lg:col-start-4">
			<span class="text-trim min-w-0 break-words">
				<?php if ( '' !== trim( (string) $args['url'] ) ) : ?>
					<a class="underline decoration-1 underline-offset-2" href="<?php echo esc_url( $args['url'] ); ?>"><?php echo esc_html( $value ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $value ); ?>
				<?php endif; ?>
			</span>
			<?php
			if ( $args['copy'] ) {
				get_template_part(
					'partials/components/link-action',
					null,
					[
						'action'     => 'copy',
						'copy'       => $value,
						/* translators: %s: row label, e.g. Permalink. */
						'label'      => sprintf( __( 'Copy %s', 'tenup-theme' ), $label ),
						'hide_label' => true,
					]
				);
			}
			?>
		</dd>
	</div>
	<?php
	return;
endif;

if ( 'tags' === $variant ) :
	if ( ! $items ) {
		return;
	}
	$separator = null === $args['separator'] ? '; ' : (string) $args['separator'];
	?>
	<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> class="<?php echo esc_attr( class_names( 'flex flex-col gap-xsmall', $args['class'] ) ); ?>">
		<?php if ( '' !== $label ) : ?>
			<<?php echo tag_escape( $label_tag ); ?> class="<?php echo esc_attr( $label_cls ); ?>"><?php echo esc_html( $label ); ?></<?php echo tag_escape( $label_tag ); ?>>
		<?php endif; ?>
		<p class="text-trim font-sans text-medium font-regular break-words">
			<?php $print_items( $items, $separator, 'underline decoration-1 decoration-solid underline-offset-[0.15em] [text-decoration-skip-ink:none] hover:no-underline' ); ?>
		</p>
	</div>
	<?php
	return;
endif;

// Stacked (A).
if ( '' === $html && '' === $value && ! $items ) {
	return;
}
$separator = null === $args['separator'] ? ', ' : (string) $args['separator'];
?>
<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> class="<?php echo esc_attr( class_names( 'flex flex-col gap-2xsmall', $args['class'] ) ); ?>">
	<?php if ( '' !== $label ) : ?>
		<<?php echo tag_escape( $label_tag ); ?> class="<?php echo esc_attr( $label_cls ); ?>"><?php echo esc_html( $label ); ?></<?php echo tag_escape( $label_tag ); ?>>
	<?php endif; ?>
	<?php if ( '' !== $html ) : ?>
		<?php // Block (not flex) so text-trim reaches the first and last lines of nested paragraphs. ?>
		<div class="text-trim font-sans text-regular font-regular break-words [&>*+*]:mt-2xsmall [&_a]:underline">
			<?php echo wp_kses_post( $html ); ?>
		</div>
	<?php elseif ( $items ) : ?>
		<p class="text-trim font-sans text-regular font-regular break-words">
			<?php $print_items( $items, $separator, 'hover:underline' ); ?>
		</p>
	<?php else : ?>
		<p class="text-trim font-sans text-regular font-regular break-words"><?php echo esc_html( $value ); ?></p>
	<?php endif; ?>
</div>
