/**
 * Mobile archive header: Titles/Covers view selector (Figma Header-interim / mobile
 * 881:151 closed, 881:159 open; markup in partials/site/header-context.php).
 *
 * Tap the button to open the option row, choose an option (or tap the button again,
 * press Escape, or tap elsewhere) to close it. The Covers view is not designed yet, so
 * its option is aria-disabled and only closes the selector.
 */

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

	const setOpen = (open) => {
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		options.hidden = !open;
		root.toggleAttribute('data-open', open);
	};

	toggle.addEventListener('click', () => {
		setOpen(toggle.getAttribute('aria-expanded') !== 'true');
	});

	options.addEventListener('click', (event) => {
		if (event.target.closest('[data-archive-view-option]')) {
			setOpen(false);
			toggle.focus();
		}
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
}
