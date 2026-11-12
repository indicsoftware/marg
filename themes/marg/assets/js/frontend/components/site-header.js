/**
 * Site header: hide on scroll down, reveal on scroll up
 * (Figma "Header-interim / mobile" 881:183: "Scroll down hides and scroll up reveals").
 *
 * Works on the [data-site-header] element rendered by header.php:
 * - data-scrolled: set once the page has scrolled (drives the white fade on
 * pages with data-fade="scrolled").
 * - data-hidden: set while the header should be hidden (only when the header
 * carries data-autohide). The CSS applies it below 1133px only, and never while
 * the header has focus-within; this script also never hides it near the top or
 * while it holds focus, and reveals it as soon as something inside gets focus.
 *
 * The transition is CSS (transition-transform, disabled under
 * prefers-reduced-motion). Without JS the header simply stays visible.
 */

/** Scroll down by at least this much (px) to hide; smaller moves are jitter. */
const HIDE_DELTA = 6;

/**
 * Scroll up by at least this much (px) to reveal. Larger than half a snap row,
 * so a scroll-snap settling back after a downward scroll (magazine archive
 * mobile rows are about 18px) does not bring the header back.
 */
const REVEAL_DELTA = 24;

/**
 * Bind the scroll behaviour once.
 */
export function initSiteHeader() {
	const header = document.querySelector('[data-site-header]');
	if (!header) {
		return;
	}

	const autohide = header.hasAttribute('data-autohide');
	let lastY = Math.max(0, window.scrollY);
	let ticking = false;

	const setHidden = (hidden) => {
		header.toggleAttribute('data-hidden', hidden);
	};

	const update = () => {
		ticking = false;
		const y = Math.max(0, window.scrollY);

		header.toggleAttribute('data-scrolled', y > 0);

		if (!autohide) {
			return;
		}

		// Never hidden near the top (within one header height) or while it holds focus.
		if (y <= header.offsetHeight || header.contains(header.ownerDocument.activeElement)) {
			setHidden(false);
			lastY = y;
			return;
		}

		const delta = y - lastY;
		if (delta > 0 ? delta < HIDE_DELTA : -delta < REVEAL_DELTA) {
			// Keep lastY so slow scrolling still accumulates past the threshold.
			return;
		}

		setHidden(delta > 0);
		lastY = y;
	};

	const onScroll = () => {
		if (!ticking) {
			ticking = true;
			window.requestAnimationFrame(update);
		}
	};

	window.addEventListener('scroll', onScroll, { passive: true });
	header.addEventListener('focusin', () => setHidden(false));

	update();
}
