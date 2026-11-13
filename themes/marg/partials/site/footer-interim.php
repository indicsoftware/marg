<?php
/**
 * Site footer: Figma "Footer-interim" (mobile, 908:214 Archive / 908:230
 * Non-archive) below 1133px and "footer-interim/desktop" (922:6730) from 1133px.
 *
 * One DOM for both layouts:
 *  - Mobile (flex column, px-gutter, pt-jumbo 80, pb-small 10, min-w 280, all
 *    marg-red-muted-40, 40%): 0.5px divider + two-column navigation (pt 15) / logo
 *    96x82 (pt 50) / registration (pt 30, 10px) / address + phones on one line
 *    (pt 22, Times 12) / Terms | Privacy row and the © line (pt 50, gap 4).
 *  - Desktop (24-col page grid, content in cols 2-23 = Figma's 22-col grid,
 *    pt-jumbo 100, row gap 72, full marg-red, no rule): row 1 = archive lockup
 *    (cols 2-8), foundation column (cols 9-16: logo, registration, address left /
 *    phones right), Marg website lockup (cols 17-23); row 2 = Terms, ©, Privacy
 *    centred with a 136px gap.
 *
 * Variants (SiteChrome::footer_variant()):
 *  - archive:     arrow up, "Back to Archives / 1946 – 2015" scrolls to #top
 *                 (header.php: a focusable target at the top of <body>, so focus
 *                 moves there too).
 *  - non-archive: arrow left, links to the magazine archive.
 *
 * Icons are the Figma exports in assets/svg (fill currentColor): 16px mobile
 * files below 1133, the 24px icon/link files above (same glyph size, different box).
 *
 * @package TenUpTheme
 */

use TenUpTheme\SiteChrome;
use function TenUpTheme\inline_svg;

$marg_is_archive = 'archive' === SiteChrome::footer_variant();
$marg_back_href  = $marg_is_archive ? '#top' : SiteChrome::archive_url();
$marg_terms_url  = SiteChrome::terms_url();
$marg_priv_url   = SiteChrome::privacy_url();
$marg_focus      = 'focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-marg-red'; // Full red, not the 30% text colour, so focus stays visible.
$marg_icon_m     = 'size-4 shrink-0 tablet-lg:hidden';
$marg_icon_d     = 'hidden size-6 shrink-0 tablet-lg:block';
$marg_legal      = 'text-trim whitespace-nowrap';
$marg_legal_link = 'underline decoration-1 underline-offset-auto ' . $marg_focus;

?>
<footer class="site-footer bg-white text-marg-red-muted-40 tablet-lg:text-marg-red" role="contentinfo">
	<div class="mx-auto flex w-full min-w-[280px] max-w-page flex-col px-gutter pt-jumbo pb-small font-sans tablet-lg:grid tablet-lg:grid-cols-24 tablet-lg:gap-y-[72px] tablet-lg:px-0 tablet-lg:pb-[10px]">

		<?php
		// Footer navigation (908:216): mobile 2 columns under a 0.5px divider; desktop cells of row 1.
		// Matched to the Figma renders: the title is cap-trimmed, the arrow row text is not (centred in its line box,
		// 33px columns on mobile), and on desktop the 16px fixed line height of the 18px title pulls the arrow row
		// 3px up (row top 122.5, not 125.5).
		?>
		<nav class="flex gap-xsmall border-t-[0.5px] border-current pt-[15px] tablet-lg:contents" aria-label="<?php esc_attr_e( 'Footer', 'tenup-theme' ); ?>">
			<a href="<?php echo esc_url( $marg_back_href ); ?>" class="<?php echo esc_attr( 'flex min-w-0 flex-1 flex-col gap-2xsmall text-regular font-semibold tablet-lg:col-[2/9] tablet-lg:row-start-1 tablet-lg:self-start ' . $marg_focus ); ?>">
				<span class="text-trim leading-[16px] tablet-lg:-mb-[3px]"><?php esc_html_e( 'Back to Archives', 'tenup-theme' ); ?></span>
				<span class="flex items-center gap-micro">
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG files; root attributes escaped by html_attrs().
					echo inline_svg( $marg_is_archive ? 'footer-mobile-arrow-up' : 'footer-mobile-nonarchive-arrow-left', [ 'class' => $marg_icon_m ] );
					echo inline_svg( $marg_is_archive ? 'icon-link-arrow-up' : 'icon-link-arrow-left', [ 'class' => $marg_icon_d ] );
					// phpcs:enable
					?>
					<span class="whitespace-nowrap leading-[1.2]"><?php echo esc_html( SiteChrome::archive_years( true ) ); ?></span>
				</span>
			</a>

			<a href="<?php echo esc_url( SiteChrome::MARG_ART_URL ); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr( 'flex min-w-0 flex-1 flex-col items-end gap-2xsmall text-right text-regular font-semibold tablet-lg:col-[17/24] tablet-lg:row-start-1 tablet-lg:self-start ' . $marg_focus ); ?>">
				<span class="text-trim leading-[16px] tablet-lg:-mb-[3px]"><?php esc_html_e( 'Currently at Marg', 'tenup-theme' ); ?></span>
				<span class="flex items-center justify-end gap-micro">
					<span class="whitespace-nowrap uppercase leading-[1.2]">marg-art.org</span>
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG files; root attributes escaped by html_attrs().
					echo inline_svg( 'footer-mobile-arrow-diagonal', [ 'class' => $marg_icon_m ] );
					echo inline_svg( 'icon-link-arrow-diagonal', [ 'class' => $marg_icon_d ] );
					// phpcs:enable
					?>
				</span>
				<span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'tenup-theme' ); ?></span>
			</a>
		</nav>

		<?php // Foundation column (908:219-908:223 mobile, 922:6689 desktop). ?>
		<div class="flex flex-col items-center pt-[50px] tablet-lg:col-[9/17] tablet-lg:row-start-1 tablet-lg:pt-0">
			<?php
			// Logo: 96x82 (mobile 922:6605); desktop frame 112.43x98.16 = the same vector plus 8px padding (922:6690).
			echo inline_svg( 'footer-mobile-marg-logo', [ 'class' => 'box-content h-[82px] w-[96px] shrink-0 tablet-lg:p-[8px]' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG file; root attributes escaped by html_attrs().
			?>

			<?php // Registration (908:221 / 922:6693): two centred lines, broken before "Maharashtra" on mobile (908:222) and before "under" on desktop.
			// Desktop (922:6695): Times 16 / 1.2, cap-trimmed both ends, directly under the logo frame; the Sans run is an inline-block aligned top so its own baseline does not grow line 1. ?>
			<p class="w-full pt-[30px] text-center font-serif text-[10px] leading-[12px] tablet-lg:pt-0 tablet-lg:text-small tablet-lg:leading-[1.2] tablet-lg:text-trim">
				<span class="font-sans font-semibold uppercase tracking-[1px] tablet-lg:inline-block tablet-lg:align-top tablet-lg:text-[13px] tablet-lg:font-bold tablet-lg:tracking-[1.56px]"><?php esc_html_e( 'The Marg Foundation', 'tenup-theme' ); ?></span>
				<?php esc_html_e( 'is a registered trust', 'tenup-theme' ); ?><br class="max-tablet-lg:hidden">
				<?php esc_html_e( 'under the', 'tenup-theme' ); ?><br class="tablet-lg:hidden">
				<?php esc_html_e( 'Maharashtra Public Trusts Act.', 'tenup-theme' ); ?>
			</p>

			<?php // Address and telephone (908:223 / 922:6697): mobile 4 centred lines; desktop address left, phones right, 8px (3xsmall) below the registration (group 980:2935), trimmed at the top only (54px block, so row 2 sits 72px below its box as in Figma). ?>
			<address class="w-full pt-[22px] not-italic tablet-lg:pt-3xsmall">
				<p class="text-trim text-center font-serif text-small leading-[1.2] tablet-lg:flex tablet-lg:items-start tablet-lg:justify-between tablet-lg:text-left">
					<span class="tablet-lg:block tablet-lg:[text-box:trim-start_cap_alphabetic]">
						<?php echo implode( '<br>', array_map( 'esc_html', SiteChrome::address_lines() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each line escaped. ?>
					</span><br class="tablet-lg:hidden">
					<span class="tablet-lg:block tablet-lg:[text-box:trim-start_cap_alphabetic] tablet-lg:text-right">
						<?php
						$marg_phones = SiteChrome::phones();
						foreach ( $marg_phones as $marg_i => $marg_phone ) :
							if ( $marg_i > 0 ) :
								?>
								<span class="tablet-lg:hidden" aria-hidden="true"> / </span><br class="max-tablet-lg:hidden">
								<?php
							endif;
							?>
							<a href="<?php echo esc_url( $marg_phone['href'], [ 'tel' ] ); ?>" class="whitespace-nowrap <?php echo esc_attr( $marg_focus ); ?>"><?php echo esc_html( $marg_phone['label'] ); ?></a>
							<?php
						endforeach;
						?>
					</span>
				</p>
			</address>
		</div>

		<?php // Legal and copyright (922:7017 mobile, 922:6725 desktop). ?>
		<div class="grid grid-cols-2 gap-x-xsmall gap-y-3xsmall pt-[50px] text-xsmall font-semibold leading-[1.2] tablet-lg:col-[2/24] tablet-lg:row-start-2 tablet-lg:flex tablet-lg:items-start tablet-lg:justify-center tablet-lg:gap-x-[136px] tablet-lg:pt-0">
			<?php if ( '' !== $marg_terms_url ) : ?>
				<a href="<?php echo esc_url( $marg_terms_url ); ?>" class="<?php echo esc_attr( $marg_legal . ' justify-self-start ' . $marg_legal_link ); ?>"><?php esc_html_e( 'Terms & Conditions', 'tenup-theme' ); ?></a>
			<?php else : ?>
				<span class="<?php echo esc_attr( $marg_legal ); ?> justify-self-start"><?php esc_html_e( 'Terms & Conditions', 'tenup-theme' ); ?></span>
			<?php endif; ?>

			<p class="<?php echo esc_attr( $marg_legal ); ?> col-span-2 row-start-2 text-center"><?php echo esc_html( SiteChrome::copyright() ); ?></p>

			<?php if ( '' !== $marg_priv_url ) : ?>
				<a href="<?php echo esc_url( $marg_priv_url ); ?>" class="<?php echo esc_attr( $marg_legal . ' justify-self-end text-right ' . $marg_legal_link ); ?>"><?php esc_html_e( 'Privacy Policy', 'tenup-theme' ); ?></a>
			<?php else : ?>
				<span class="<?php echo esc_attr( $marg_legal ); ?> justify-self-end text-right"><?php esc_html_e( 'Privacy Policy', 'tenup-theme' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</footer>
