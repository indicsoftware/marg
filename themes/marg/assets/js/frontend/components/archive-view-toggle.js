/**
 * Mobile archive header: Titles/Covers view selector (Figma Header-interim / mobile
 * 881:151 / 881:159 Titles, 881:167 / 881:175 Covers; markup in
 * partials/site/header-context.php). Shown below 744px only.
 *
 * Tap the button to open the option row; tap the button again, press Escape or tap
 * elsewhere to close it. Choosing the other view's option switches the view without a
 * reload:
 *  - data-view="titles|covers" is set on the archive root [data-archive] (which shows
 *    the list or the covers track, partials/archive/magazines.php / covers.php) and on
 *    the selector (labels, arrow and option order);
 *  - the URL keeps the view (?view=covers, removed for Titles) via history.replaceState,
 *    so a reload or a shared link restores it (ProductPresenter::archive_view());
 *  - a 'marg:archive-view' CustomEvent is dispatched on document with
 *    detail { view: 'titles' | 'covers', index } where index is the position of the issue
 *    in focus in the view being left (the open row in Titles, the card in the first slot
 *    in Covers; -1 when unknown), so the other view can start from the same issue
 *    (archive-list.js, archive-covers.js).
 * The options are links (?view=covers / the bare archive URL), so they still work if the
 * click is not intercepted.
 */

const VIEW_EVENT = 'marg:archive-view';

/**
 * Index of the issue in focus in the current view.
 *
 * @param {HTMLElement} archive [data-archive] root.
 * @param {string}      view    Current view.
 * @returns {number} 0-based index, -1 when unknown.
 */
function focusedIndex(archive, view) {
	// Rows and cards are rendered from the same rows in the same order, so positions match.
	const items = Array.from(
		archive.querySelectorAll(view === 'covers' ? '[data-archive-cover]' : '[data-archive-row]'),
	);
	const flag = view === 'covers' ? 'data-current' : 'data-active';
	return items.findIndex((item) => item.hasAttribute(flag));
}

/**
 * Wire up the selector, if it is on the page.
 */
export function initArchiveViewToggle() {
	const root = document.querySelector('[data-archive-view]');
	if (!root) {
		return;
	}

	const toggle = root.querySelector('[data-archive-view-toggle]');
	const options = root.querySelector('[data-archive-view-options]');
	if (!toggle || !options) {
		return;
	}

	const archive = document.querySelector('[data-archive]');

	const setOpen = (open) => {
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		options.hidden = !open;
		root.toggleAttribute('data-open', open);
	};

	/**
	 * Switch to a view (no reload).
	 *
	 * @param {string} view 'titles' | 'covers'.
	 */
	const setView = (view) => {
		const current = archive ? archive.dataset.view || 'titles' : root.dataset.view || 'titles';
		root.dataset.view = view;
		if (!archive || current === view) {
			return;
		}

		const index = focusedIndex(archive, current);
		archive.dataset.view = view;

		try {
			const url = new URL(window.location.href);
			if (view === 'covers') {
				url.searchParams.set('view', 'covers');
			} else {
				url.searchParams.delete('view');
			}
			window.history.replaceState(window.history.state, '', url.toString());
		} catch (error) {
			// URL or History API unavailable: the view still switches, it just is not kept.
		}

		document.dispatchEvent(new CustomEvent(VIEW_EVENT, { detail: { view, index } }));
	};

	toggle.addEventListener('click', () => {
		setOpen(toggle.getAttribute('aria-expanded') !== 'true');
	});

	options.addEventListener('click', (event) => {
		const option = event.target.closest('[data-archive-view-option]');
		if (!option) {
			return;
		}
		const view = option.dataset.archiveViewOption === 'covers' ? 'covers' : 'titles';
		if (archive) {
			event.preventDefault();
			setView(view);
		}
		setOpen(false);
		toggle.focus();
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
			setOpen(false);
			toggle.focus();
		}
	});

	document.addEventListener('click', (event) => {
		if (toggle.getAttribute('aria-expanded') === 'true' && !root.contains(event.target)) {
			setOpen(false);
		}
	});

	// Keep the selector in step if another script changes the view.
	document.addEventListener(VIEW_EVENT, (event) => {
		const view = event.detail && event.detail.view;
		if ((view === 'titles' || view === 'covers') && root.dataset.view !== view) {
			root.dataset.view = view;
		}
	});
}
