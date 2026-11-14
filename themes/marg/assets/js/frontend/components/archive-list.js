/**
 * Magazine archive: mobile open card and desktop header hover image
 * (partials/archive/magazines.php, stage.php, issue-row.php).
 *
 * Below 1133px (and on screens at least 520px tall, where the stage is sticky) the
 * archive is a list of collapsed 18.3px rows scrolling under a sticky "stage": the image
 * strip plus the open card. The row in the last row-height of the stage (hidden behind
 * the open card) is the open one. An IntersectionObserver watches that band and copies
 * the row's data-* attributes (date, vol, title, url, img-*, annotation) into the stage.
 *
 * Also:
 *  - keyboard focus on a row scrolls it to the open position (scroll-margin-top), so the
 *    focused issue is the one shown in the open card (which then draws the focus ring);
 *  - [data-archive-spacer] is sized so the last row can reach the open position;
 *  - the next and previous rows' images are preloaded.
 *
 * At >= 1133px on hover devices, hovering a row (or focusing it from the keyboard) shows
 * its image (open access hover image, else featured image) in the header-desktop
 * container [data-archive-hover-image] (partials/site/header-desktop.php), contained and
 * centred, fading out when no row is hovered. Rows without an image show nothing.
 *
 * Progressive enhancement: without this script the stage shows the first issue and the
 * list is a plain list of links; the desktop header container stays empty.
 */

const MEDIA = '(width < 1133px) and (height >= 520px)';
const HOVER_MEDIA = '(width >= 1133px) and (hover: hover)';

/**
 * Copy a row's image into the strip (or show the placeholder block).
 *
 * @param {HTMLImageElement|null} img         Strip image.
 * @param {HTMLElement|null}      placeholder Placeholder block.
 * @param {DOMStringMap}          data        Row dataset.
 */
function showImage(img, placeholder, data) {
	if (!img) {
		return;
	}

	if (!data.imgSrc) {
		img.hidden = true;
		img.removeAttribute('srcset');
		img.removeAttribute('sizes');
		img.removeAttribute('src');
		if (placeholder) {
			placeholder.hidden = false;
		}
		return;
	}

	if (img.getAttribute('src') !== data.imgSrc) {
		if (data.imgSrcset) {
			img.setAttribute('sizes', data.imgSizes || '');
			img.setAttribute('srcset', data.imgSrcset);
		} else {
			img.removeAttribute('srcset');
			img.removeAttribute('sizes');
		}
		img.setAttribute('width', data.imgWidth || '');
		img.setAttribute('height', data.imgHeight || '');
		img.setAttribute('src', data.imgSrc);
	}
	img.hidden = false;
	if (placeholder) {
		placeholder.hidden = true;
	}
}

/**
 * Desktop: show the hovered / keyboard-focused row's image in the header container.
 *
 * @param {HTMLElement} root [data-archive] section.
 */
function initHoverImage(root) {
	const box = document.querySelector('[data-archive-hover-image]');
	const list = root.querySelector('[data-archive-list]');
	if (!box || !list || !window.matchMedia) {
		return;
	}

	const media = window.matchMedia(HOVER_MEDIA);
	let img = null;
	let hovered = null;
	let focused = null;
	let shown = null;

	const ensureImage = () => {
		if (!img) {
			img = document.createElement('img');
			img.alt = '';
			img.decoding = 'async';
			img.className =
				'pointer-events-none block size-full object-contain object-center opacity-0 transition-opacity duration-200 motion-reduce:transition-none';
			box.appendChild(img);
		}
		return img;
	};

	const hide = () => {
		shown = null;
		if (img) {
			img.classList.remove('opacity-100');
			img.classList.add('opacity-0');
		}
	};

	const show = (row) => {
		if (!row || !media.matches || !row.dataset.imgSrc) {
			hide();
			return;
		}
		if (row === shown) {
			return;
		}
		shown = row;
		const data = row.dataset;
		const el = ensureImage();
		const reveal = () => {
			if (shown === row) {
				el.classList.remove('opacity-0');
				el.classList.add('opacity-100');
			}
		};
		if (el.getAttribute('src') === data.imgSrc) {
			reveal();
			return;
		}
		// New image: fade the current one out and fade the new one in once it has loaded,
		// so the previous issue's image never shows under the new row.
		el.classList.remove('opacity-100');
		el.classList.add('opacity-0');
		el.onload = reveal;
		if (data.imgSrcset) {
			el.setAttribute('sizes', data.imgHoverSizes || data.imgSizes || '');
			el.setAttribute('srcset', data.imgSrcset);
		} else {
			el.removeAttribute('srcset');
			el.removeAttribute('sizes');
		}
		el.setAttribute('width', data.imgWidth || '');
		el.setAttribute('height', data.imgHeight || '');
		el.setAttribute('src', data.imgSrc);
		if (el.complete && el.naturalWidth) {
			reveal();
		}
	};

	const update = () => show(hovered || focused);

	const rowOf = (target) =>
		target instanceof Element ? target.closest('[data-archive-row]') : null;

	list.addEventListener('pointerover', (event) => {
		if (event.pointerType && event.pointerType !== 'mouse' && event.pointerType !== 'pen') {
			return;
		}
		const row = rowOf(event.target);
		if (row && row !== hovered) {
			hovered = row;
			update();
		}
	});
	list.addEventListener('pointerleave', () => {
		hovered = null;
		update();
	});
	list.addEventListener('focusin', (event) => {
		const row = rowOf(event.target);
		focused = row && event.target.matches(':focus-visible') ? row : null;
		update();
	});
	list.addEventListener('focusout', (event) => {
		if (!rowOf(event.relatedTarget) || !list.contains(event.relatedTarget)) {
			focused = null;
			update();
		}
	});

	if (typeof media.addEventListener === 'function') {
		media.addEventListener('change', () => {
			hovered = null;
			focused = null;
			hide();
		});
	}
}

/**
 * Start the archive behaviour (mobile open card, desktop hover image).
 */
export function initArchiveList() {
	const root = document.querySelector('[data-archive]');
	if (!root) {
		return;
	}

	initHoverImage(root);

	if (!('IntersectionObserver' in window) || !window.matchMedia) {
		return;
	}

	const list = root.querySelector('[data-archive-list]');
	const stage = root.querySelector('[data-archive-stage]');
	const panel = stage ? stage.querySelector('[data-archive-panel]') : null;
	if (!list || !panel) {
		return;
	}

	const rows = Array.from(list.querySelectorAll('[data-archive-row]'));
	if (!rows.length) {
		return;
	}

	const spacer = root.querySelector('[data-archive-spacer]');
	const img = stage.querySelector('[data-archive-img]');
	const placeholder = stage.querySelector('[data-archive-placeholder]');
	const annotationEl = stage.querySelector('[data-archive-annotation]');
	const dateEl = panel.querySelector('[data-archive-panel-date]');
	const volEl = panel.querySelector('[data-archive-panel-vol]');
	const titleEl = panel.querySelector('[data-archive-panel-title]');
	const media = window.matchMedia(MEDIA);
	const visible = new Set();
	const preloaded = new Set();

	let active = rows.find((row) => row.hasAttribute('data-active')) || rows[0];
	let observer = null;
	let resizeTimer = 0;

	const preload = (row) => {
		if (!row || preloaded.has(row) || !row.dataset.imgSrc) {
			return;
		}
		preloaded.add(row);
		const image = new Image();
		if (row.dataset.imgSrcset) {
			image.sizes = row.dataset.imgSizes || '';
			image.srcset = row.dataset.imgSrcset;
		}
		image.src = row.dataset.imgSrc;
	};

	const activate = (row) => {
		if (!row) {
			return;
		}
		if (row !== active) {
			active.removeAttribute('data-active');
			row.setAttribute('data-active', '');
			active = row;
		}

		const data = row.dataset;
		const hasTitle = typeof data.title === 'string' && data.title !== '';
		if (dateEl) {
			dateEl.textContent = data.date || '';
		}
		if (volEl) {
			volEl.textContent = hasTitle ? data.vol || '' : '';
			volEl.hidden = !hasTitle;
		}
		if (titleEl) {
			titleEl.textContent = hasTitle ? data.title : data.vol || '';
		}
		if (data.url) {
			panel.setAttribute('href', data.url);
		}
		showImage(img, placeholder, data);
		if (annotationEl) {
			annotationEl.textContent = data.annotation || '';
			annotationEl.hidden = !data.annotation;
		}

		const index = rows.indexOf(row);
		preload(rows[index + 1]);
		preload(rows[index - 1]);
	};

	/**
	 * Geometry of the open position: the last row-height of the stage.
	 *
	 * @returns {{top: number, bottom: number}} Band in viewport px.
	 */
	const band = () => {
		const stageHeight = stage.getBoundingClientRect().height;
		const rowHeight = rows[0].getBoundingClientRect().height;
		return { top: stageHeight - rowHeight, bottom: stageHeight };
	};

	const pick = () => {
		if (!visible.size) {
			return;
		}
		const { top, bottom } = band();
		const centre = (top + bottom) / 2;
		let best = null;
		let bestDistance = Infinity;
		visible.forEach((row) => {
			const rect = row.getBoundingClientRect();
			const distance = Math.abs((rect.top + rect.bottom) / 2 - centre);
			if (distance < bestDistance) {
				best = row;
				bestDistance = distance;
			}
		});
		activate(best);
	};

	const sizeSpacer = () => {
		if (!spacer) {
			return;
		}
		let height = '';
		if (media.matches) {
			// Measure with the current spacer in place (it has no height of its own) and
			// subtract it: collapsing it first would force a layout with a shorter document,
			// which clamps scrollY near the page bottom and makes the page jump on resize
			// (e.g. iOS Safari toolbar show/hide).
			const last = rows[rows.length - 1];
			const doc = document.documentElement;
			const lastBottom = last.getBoundingClientRect().bottom + window.scrollY;
			const after = doc.scrollHeight - lastBottom - spacer.offsetHeight;
			const needed = window.innerHeight - band().bottom - after;
			height = needed > 0 ? `${Math.ceil(needed)}px` : '';
		}
		if (spacer.style.height !== height) {
			spacer.style.height = height;
		}
	};

	const disconnect = () => {
		if (observer) {
			observer.disconnect();
			observer = null;
		}
		visible.clear();
	};

	const connect = () => {
		disconnect();
		if (!media.matches) {
			sizeSpacer();
			return;
		}

		const { top, bottom } = band();
		const below = Math.max(0, window.innerHeight - bottom);
		observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						visible.add(entry.target);
					} else {
						visible.delete(entry.target);
					}
				});
				pick();
			},
			{ rootMargin: `-${Math.floor(top)}px 0px -${Math.floor(below)}px 0px`, threshold: 0 },
		);
		rows.forEach((row) => observer.observe(row));
		sizeSpacer();
	};

	// Keyboard focus: bring the focused row to the open position (its scroll-margin-top).
	list.addEventListener('focusin', (event) => {
		if (!media.matches) {
			return;
		}
		const link = event.target instanceof Element ? event.target.closest('a') : null;
		const row = link ? link.closest('[data-archive-row]') : null;
		if (!row || !link.matches(':focus-visible')) {
			return;
		}
		row.scrollIntoView({ block: 'start' });
	});

	const onResize = () => {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(connect, 150);
	};

	window.addEventListener('resize', onResize, { passive: true });
	window.addEventListener('load', sizeSpacer, { once: true });
	if (typeof media.addEventListener === 'function') {
		media.addEventListener('change', connect);
	}

	preload(rows[1]);
	connect();
}
