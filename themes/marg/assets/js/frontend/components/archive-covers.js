/**
 * Magazine archive Covers view (Figma 1019:2672, below 744px only; markup in
 * partials/archive/covers.php and partials/archive/cover-card.php).
 *
 * The track is a horizontal, mandatory-snapping row of equal-width cards (75% of the
 * viewport). The card in the first (left) slot is the current one:
 *  - it carries data-current (the lighter gradient-30 background, "first card");
 *  - the fixed counter footer shows its volume ("Vol. N /67"). When the volume changes
 *    (every 4 issues) the digits roll like an odometer: up when scrolling right, down
 *    when scrolling left. Under prefers-reduced-motion the number swaps without motion;
 *  - its cover and the covers of the next two cards are switched to eager loading
 *    (covers are lazy in the markup and nothing is promoted while the track is hidden,
 *    so Titles view and 744px and up fetch no covers);
 *  - its index is kept in history.state (coverIndex) once the scroll settles, so a Back
 *    navigation that reloads the page (no bfcache) returns to the same card: browsers
 *    restore the window scroll, not an inner scroller's scrollLeft.
 * The current index is derived from scrollLeft / card width on a rAF-throttled passive
 * scroll listener (equal-width cards, so no IntersectionObserver is needed).
 *
 * View switching (archive-view-toggle.js): on 'marg:archive-view' with view 'covers',
 * the track jumps (no animation) to the issue that was open in Titles (detail.index),
 * so the same issue stays in focus across views. The toggle reads the current card
 * ([data-archive-cover][data-current]) for the way back.
 *
 * Progressive enhancement: without this script a ?view=covers URL still shows the
 * track (CSS snapping, native lazy covers, links); the counter is not shown (in-[.js]).
 */

const VIEW_EVENT = 'marg:archive-view';
const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';
const ROLL_MS = 300; // matches duration-300 on the digit strips.
const SETTLE_MS = 200; // quiet scroll time before the position is written to history.state.

/**
 * One odometer digit: a strip of twenty cells (0-9 twice) moved by --digit.
 *
 * @param {HTMLElement} strip [data-archive-cover-digit] element.
 * @param {number}      value Initial digit (0-9).
 * @returns {{roll: function(number, number, boolean): void}} Controller.
 */
function createDigit(strip, value) {
	let cell = value;
	let timer = 0;

	const place = (next, animate) => {
		if (!animate) {
			strip.style.transition = 'none';
		}
		strip.style.setProperty('--digit', String(next));
		if (!animate) {
			// Commit the jump before transitions come back.
			void strip.offsetHeight; // eslint-disable-line no-void
			strip.style.transition = '';
		}
		cell = next;
	};

	// Back to the first run (0-9) without motion, once a roll into the second run ends.
	const settle = () => {
		window.clearTimeout(timer);
		timer = 0;
		if (cell >= 10) {
			place(cell - 10, false);
		}
	};

	return {
		/**
		 * Show a digit.
		 *
		 * @param {number}  next      Digit (0-9).
		 * @param {number}  direction 1 rolls up (scrolling right), -1 rolls down.
		 * @param {boolean} animate   Roll (false: swap without motion).
		 */
		roll(next, direction, animate) {
			settle();
			if (next === cell) {
				return;
			}
			if (!animate) {
				place(next, false);
				return;
			}
			if (direction >= 0) {
				// Up: always increasing cells; 9 -> 0 goes on to the second run's 0.
				place(next > cell ? next : next + 10, true);
				timer = window.setTimeout(settle, ROLL_MS + 50);
			} else {
				// Down: always decreasing cells; 0 -> 9 starts from the second run's 0.
				if (next > cell) {
					place(cell + 10, false);
				}
				place(next, true);
			}
		},
	};
}

/**
 * Wire up the Covers view, if it is on the page.
 */
export function initArchiveCovers() {
	const covers = document.querySelector('[data-archive] [data-archive-covers]');
	if (!covers) {
		return;
	}
	const track = covers.querySelector('[data-archive-cover-track]');
	const cards = track ? Array.from(track.querySelectorAll('[data-archive-cover]')) : [];
	if (!cards.length) {
		return;
	}

	const reducedMotion = window.matchMedia(REDUCED_MOTION);
	const volumeOf = (card) => parseInt(card.dataset.volume, 10) || 0;

	let current = Math.max(
		0,
		cards.findIndex((card) => card.hasAttribute('data-current')),
	);
	let volume = volumeOf(cards[current]);
	let width = 0;
	let frame = 0;

	// Counter digits (tens: cell 0 is blank, so "Vol.  1"; ones).
	const counter = covers.querySelector('[data-archive-cover-counter]');
	const tensStrip = counter && counter.querySelector('[data-archive-cover-digit="tens"]');
	const onesStrip = counter && counter.querySelector('[data-archive-cover-digit="ones"]');
	const tens = tensStrip ? createDigit(tensStrip, Math.floor((volume % 100) / 10)) : null;
	const ones = onesStrip ? createDigit(onesStrip, volume % 10) : null;

	const showVolume = (next, direction, animate) => {
		if (tens) {
			tens.roll(Math.floor((next % 100) / 10), direction, animate);
		}
		if (ones) {
			ones.roll(next % 10, direction, animate);
		}
	};
	showVolume(volume, 1, false);

	/*
	 * The current cover and the next two load ahead of the swipe (they are lazy in the
	 * markup). Only called while the track is visible (width > 0): browsers fetch eager
	 * images even inside display:none.
	 */
	const preload = (index) => {
		[index, index + 1, index + 2].forEach((i) => {
			const img = cards[i] && cards[i].querySelector('img[loading="lazy"]');
			if (img) {
				img.loading = 'eager';
			}
		});
	};

	// Keep the current card in history.state for a Back navigation without bfcache.
	let saveTimer = 0;
	const remember = () => {
		window.clearTimeout(saveTimer);
		saveTimer = window.setTimeout(() => {
			try {
				const state =
					window.history.state && typeof window.history.state === 'object'
						? window.history.state
						: {};
				if (state.coverIndex !== current) {
					window.history.replaceState({ ...state, coverIndex: current }, '');
				}
			} catch (error) {
				// History API unavailable: the position is simply not kept.
			}
		}, SETTLE_MS);
	};

	/**
	 * Make a card the current one (first slot).
	 *
	 * @param {number}  index   Card index.
	 * @param {boolean} animate Roll the counter (false when jumping between views).
	 */
	const setCurrent = (index, animate) => {
		if (index === current) {
			return;
		}
		cards[current].removeAttribute('data-current');
		cards[index].setAttribute('data-current', '');
		current = index;

		const next = volumeOf(cards[index]);
		if (next !== volume) {
			showVolume(next, next > volume ? 1 : -1, animate && !reducedMotion.matches);
			volume = next;
		}
		preload(index);
	};

	const measure = () => {
		({ width } = cards[0].getBoundingClientRect());
	};

	const update = () => {
		frame = 0;
		if (width <= 0) {
			measure();
		}
		if (width <= 0) {
			// Hidden (Titles view, or 744px and up).
			return;
		}
		const index = Math.round(Math.abs(track.scrollLeft) / width);
		setCurrent(Math.min(Math.max(index, 0), cards.length - 1), true);
		remember();
	};

	track.addEventListener(
		'scroll',
		() => {
			if (!frame) {
				frame = window.requestAnimationFrame(update);
			}
		},
		{ passive: true },
	);

	const onResize = () => {
		measure();
		if (width > 0) {
			// Keep the current card in the first slot when the card width changes.
			track.scrollLeft = current * width;
		}
	};
	if (typeof window.ResizeObserver === 'function') {
		new window.ResizeObserver(onResize).observe(track);
	} else {
		window.addEventListener('resize', onResize, { passive: true });
	}

	// Titles -> Covers: start from the issue that was open in Titles.
	document.addEventListener(VIEW_EVENT, (event) => {
		const detail = event.detail || {};
		if (detail.view !== 'covers') {
			return;
		}
		const index =
			Number.isInteger(detail.index) && cards[detail.index] ? detail.index : current;
		measure();
		if (width > 0) {
			track.scrollLeft = index * width;
		}
		setCurrent(index, false);
		if (width > 0) {
			preload(index);
		}
	});

	measure();
	if (width > 0) {
		// Back to the card kept in history.state (a reload of this entry), if any.
		let saved = -1;
		try {
			const { state } = window.history;
			saved = state && Number.isInteger(state.coverIndex) ? state.coverIndex : -1;
		} catch (error) {
			saved = -1;
		}
		if (saved > 0 && cards[saved]) {
			track.scrollLeft = saved * width;
			setCurrent(saved, false);
		}
		update();
		preload(current);
	}
}
