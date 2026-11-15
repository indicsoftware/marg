/**
 * Marginalia 2 (Eemon Roy) in the desktop archive header's canvas slot
 * (partials/site/header-desktop.php, Figma Frame 22).
 *
 * - The iframe only gets its src from 1133px up, where the desktop header is shown, so
 *   phones never download the drawing.
 * - The drawing is a fixed 400x100 strip. Below 1920 the five-column slot is narrower
 *   than 400px, so the iframe is scaled down to the slot width (never up), keeping the
 *   whole drawing visible instead of cropping it. The wrapper's height follows the scale.
 */

const DESKTOP = '(min-width: 1133px)';
const WIDTH = 400;
const HEIGHT = 100;

/**
 * Wire up every Marginalia slot on the page.
 */
export function initMarginalia() {
	const slots = document.querySelectorAll('[data-marginalia]');
	if (!slots.length || !window.matchMedia) {
		return;
	}

	const media = window.matchMedia(DESKTOP);

	slots.forEach((slot) => {
		const frame = slot.querySelector('iframe[data-src]');
		if (!frame) {
			return;
		}

		const fit = () => {
			const scale = Math.min(1, slot.clientWidth / WIDTH) || 1;
			slot.style.setProperty('--marginalia-scale', String(scale));
			slot.style.height = `${HEIGHT * scale}px`;
		};

		const load = () => {
			if (media.matches && !frame.getAttribute('src')) {
				frame.setAttribute('src', frame.dataset.src);
			}
			fit();
		};

		load();
		media.addEventListener('change', load);

		if (window.ResizeObserver) {
			new window.ResizeObserver(fit).observe(slot.parentElement || slot);
		} else {
			window.addEventListener('resize', fit, { passive: true });
		}
	});
}
