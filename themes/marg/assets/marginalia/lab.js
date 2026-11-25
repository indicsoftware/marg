/**
 * Marginalia lab control panel (/marginalia-lab/, src/MarginaliaLab.php).
 *
 * Drives the Marginalia iframe in the desktop header (partials/site/header-desktop.php)
 * through window.marginaliaEmbed, which assets/marginalia/index.html exposes. The iframe
 * is same-origin, so the panel tunes the running drawing directly. "Restart drawing"
 * reloads it with the settings in its URL hash (#lab=<JSON>), so it starts the way the
 * homepage would with those settings.
 *
 * Settings live in this page's URL hash (shareable with "Copy link") and in localStorage.
 * Nothing here changes the live homepage: "Copy settings" produces the
 * window.MARGINALIA_CONFIG block a developer pastes into assets/marginalia/index.html.
 */
(function () {
	'use strict';

	const STORE = 'marginaliaLab';
	const LAB = window.MARGINALIA_LAB || {};
	const ENGINE_DEFAULTS = { autoDraw: true, transform: true, ink: '#da100d' };
	const GROUPS = [
		['marks', 'Marks'],
		['scribbles', 'Scribbles'],
		['things', 'Things'],
		['geometry', 'Geometry'],
	];
	const SLIDERS = [
		{
			key: 'pace',
			label: 'Pace',
			hint: 'How fast the hand draws.',
			// Logarithmic: 0.1x to 10x.
			toInput: (v) => Math.round((Math.log10(v) + 1) * 500),
			fromInput: (n) => round(10 ** (n / 500 - 1), 2),
			min: 0,
			max: 1000,
			step: 1,
			format: (v) => `${v < 1 ? v.toFixed(2) : v.toFixed(1)}×`,
		},
		{
			key: 'fullness',
			label: 'Fullness',
			hint: 'How much ink the strip holds.',
			toInput: (v) => v,
			fromInput: (n) => round(n, 2),
			min: 0.1,
			max: 3,
			step: 0.05,
			format: (v) => v.toFixed(2),
		},
		{
			key: 'markSize',
			label: 'Mark size',
			hint: 'Size of new drawings.',
			toInput: (v) => v,
			fromInput: (n) => round(n, 1),
			min: 0.5,
			max: 10,
			step: 0.1,
			format: (v) => v.toFixed(1),
		},
	];

	const frame = document.querySelector('[data-marginalia] iframe');
	let api = null;
	let homepage = null;
	let settings = null;
	let elements = [];
	let reloads = 0;
	let paused = false;
	const controls = {};

	function round(n, places) {
		const f = 10 ** places;
		return Math.round(n * f) / f;
	}

	function copySettings(s) {
		return { ...s, elements: { ...s.elements } };
	}

	function frequency(id, s = settings) {
		const n = s.elements[id];
		return Number.isFinite(n) ? n : 1;
	}

	function el(tag, attrs = {}, children = []) {
		const node = document.createElement(tag);
		for (const [k, v] of Object.entries(attrs)) {
			if (k === 'class') node.className = v;
			else if (k === 'text') node.textContent = v;
			else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
			else if (v !== false && v != null) node.setAttribute(k, v === true ? '' : v);
		}
		for (const child of [].concat(children)) if (child) node.append(child);
		return node;
	}

	/* ------------------------------------------------------------ panel */

	const status = el('p', { class: 'mlab__status', role: 'status', 'aria-live': 'polite' });
	const notice = el('p', { class: 'mlab__notice', hidden: true });
	const body = el('div', { class: 'mlab__body', id: 'mlab-body' });
	const toggle = el('button', {
		type: 'button',
		class: 'mlab__toggle',
		'aria-expanded': 'true',
		'aria-controls': 'mlab-body',
		text: 'Hide',
		onclick: () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'),
	});
	const panel = el('aside', { class: 'mlab', 'aria-labelledby': 'mlab-title' }, [
		el('header', { class: 'mlab__head' }, [
			el('h2', { class: 'mlab__title', id: 'mlab-title', text: 'Marginalia lab' }),
			toggle,
		]),
		body,
	]);

	function setOpen(open) {
		toggle.setAttribute('aria-expanded', String(open));
		toggle.textContent = open ? 'Hide' : 'Show';
		body.hidden = !open;
		panel.classList.toggle('is-collapsed', !open);
	}

	function say(message) {
		status.textContent = '';
		// Re-set a moment later so screen readers announce repeats.
		setTimeout(() => {
			status.textContent = message;
		}, 50);
	}

	function section(title, children, extra) {
		return el('section', { class: 'mlab__section' }, [
			el('div', { class: 'mlab__section-head' }, [el('h3', { text: title }), extra]),
			...children,
		]);
	}

	function sliderRow(def) {
		const id = `mlab-${def.key}`;
		const input = el('input', {
			type: 'range',
			id,
			min: def.min,
			max: def.max,
			step: def.step,
			'aria-describedby': `${id}-hint`,
			oninput: () => update({ [def.key]: def.fromInput(+input.value) }),
			ondblclick: () => update({ [def.key]: homepage[def.key] }, true),
		});
		const output = el('output', { for: id });
		const row = el('div', { class: 'mlab__row' }, [
			el('label', { for: id, text: def.label }),
			input,
			output,
			el('p', { class: 'mlab__hint', id: `${id}-hint`, text: `${def.hint} Double-click to reset.` }),
		]);
		controls[def.key] = () => {
			const v = settings[def.key];
			input.value = def.toInput(v);
			output.textContent = def.format(v);
			row.classList.toggle('is-changed', v !== homepage[def.key]);
			input.setAttribute('aria-valuetext', def.format(v));
		};
		return row;
	}

	function switchButton(key, label, hint) {
		const button = el('button', {
			type: 'button',
			class: 'mlab__switch',
			title: hint,
			onclick: () => update({ [key]: !settings[key] }),
		}, label);
		controls[key] = () => {
			button.setAttribute('aria-pressed', String(settings[key]));
			button.classList.toggle('is-changed', settings[key] !== homepage[key]);
		};
		return button;
	}

	function inkRow() {
		const input = el('input', {
			type: 'color',
			id: 'mlab-ink',
			oninput: () => update({ ink: input.value.toLowerCase() }),
		});
		const output = el('output', { for: 'mlab-ink', class: 'mlab__hex' });
		const reset = el('button', {
			type: 'button',
			class: 'mlab__link',
			text: 'Reset',
			onclick: () => update({ ink: homepage.ink }, true),
		});
		const row = el('div', { class: 'mlab__row mlab__row--ink' }, [
			el('label', { for: 'mlab-ink', text: 'Ink' }),
			input,
			output,
			reset,
		]);
		controls.ink = () => {
			input.value = settings.ink;
			output.textContent = settings.ink;
			row.classList.toggle('is-changed', settings.ink !== homepage.ink);
		};
		return row;
	}

	function elementRow(item) {
		const id = `mlab-el-${item.id}`;
		const input = el('input', {
			type: 'range',
			id,
			min: 0,
			max: 4,
			step: 0.25,
			oninput: () => setFrequency(item.id, +input.value),
			ondblclick: () => setFrequency(item.id, frequency(item.id, homepage)),
		});
		const output = el('output', { for: id });
		const row = el('div', { class: 'mlab__row mlab__row--element' }, [
			el('label', { for: id, text: item.name }),
			input,
			output,
		]);
		controls[`el:${item.id}`] = () => {
			const n = frequency(item.id);
			const text = n === 0 ? 'off' : `${n}×`;
			input.value = n;
			output.textContent = text;
			input.setAttribute('aria-valuetext', n === 0 ? 'off' : `${n} times as often`);
			row.classList.toggle('is-off', n === 0);
			row.classList.toggle('is-changed', n !== frequency(item.id, homepage));
		};
		return row;
	}

	function setFrequency(id, n) {
		const next = { ...settings.elements, [id]: n };
		if (n === 1) delete next[id];
		if (elements.every((item) => frequency(item.id, { elements: next }) === 0)) {
			say('At least one element has to stay on.');
			refresh();
			return;
		}
		update({ elements: next });
	}

	function setGroup(group, n) {
		const next = { ...settings.elements };
		for (const item of elements) {
			if (item.group !== group) continue;
			if (n === null) {
				const base = frequency(item.id, homepage);
				if (base === 1) delete next[item.id];
				else next[item.id] = base;
			} else {
				next[item.id] = n;
			}
		}
		if (elements.every((item) => frequency(item.id, { elements: next }) === 0)) {
			say('At least one element has to stay on.');
			return;
		}
		update({ elements: next });
	}

	function build() {
		const groupSections = GROUPS.filter(([g]) => elements.some((item) => item.group === g)).map(([group, title]) =>
			section(
				title,
				elements.filter((item) => item.group === group).map(elementRow),
				el('span', { class: 'mlab__group-actions' }, [
					el('button', { type: 'button', class: 'mlab__link', text: 'None', 'aria-label': `Turn off all ${title.toLowerCase()}`, onclick: () => setGroup(group, 0) }),
					el('button', { type: 'button', class: 'mlab__link', text: 'Reset', 'aria-label': `Reset ${title.toLowerCase()}`, onclick: () => setGroup(group, null) }),
				])
			)
		);

		const snippet = el('textarea', { class: 'mlab__snippet', readonly: true, rows: 9, 'aria-label': 'Settings for window.MARGINALIA_CONFIG', spellcheck: 'false' });
		controls.snippet = () => {
			snippet.value = configSnippet();
		};

		const pauseButton = el('button', {
			type: 'button',
			class: 'mlab__switch',
			'aria-pressed': 'false',
			title: 'Freeze the drawing to look at it (lab only, not a setting)',
			text: 'Pause',
			onclick: () => {
				paused = !paused;
				pauseButton.setAttribute('aria-pressed', String(paused));
				if (api) api.pause(paused);
			},
		});
		controls.pause = () => pauseButton.setAttribute('aria-pressed', String(paused));

		body.append(
			el('p', { class: 'mlab__intro', text: 'Tune the drawing in the header above. Nothing here changes the live homepage: when you like it, copy the settings and send them to a developer.' }),
			notice,
			section('Drawing', [
				...SLIDERS.map(sliderRow),
				el('div', { class: 'mlab__switches' }, [
					switchButton('autoDraw', 'Auto-draw', 'New drawings appear by themselves'),
					switchButton('transform', 'Transform', 'Drawings break apart and become other drawings. Turn off to hold the drawing still.'),
					pauseButton,
				]),
				inkRow(),
			]),
			el('details', { class: 'mlab__elements' }, [
				el('summary', { text: 'Elements: how often each one appears' }),
				el('p', { class: 'mlab__hint', text: '1× is the artist’s mix, 0 switches an element off. Affects new drawings; restart to see the effect from the start.' }),
				...groupSections,
			]),
			el('div', { class: 'mlab__actions' }, [
				el('button', { type: 'button', class: 'mlab__button', text: 'Restart drawing', onclick: restart }),
				el('button', { type: 'button', class: 'mlab__button', text: 'Clear', onclick: () => { if (api) { api.clear(); say('Cleared.'); } } }),
				el('button', { type: 'button', class: 'mlab__button', text: 'Reset all', onclick: resetAll }),
			]),
			el('div', { class: 'mlab__actions mlab__actions--primary' }, [
				el('button', { type: 'button', class: 'mlab__button mlab__button--primary', text: 'Copy settings', onclick: () => copy(configSnippet(), 'Settings copied. Send them to a developer to put on the homepage.') }),
				el('button', { type: 'button', class: 'mlab__button', text: 'Copy link', onclick: () => copy(location.href, 'Link copied. It opens the lab with these settings.') }),
			]),
			el('details', { class: 'mlab__code' }, [el('summary', { text: 'Show settings' }), snippet]),
			status
		);
	}

	/* ------------------------------------------------------------ state */

	function refresh() {
		for (const fn of Object.values(controls)) fn();
	}

	function update(partial, announce) {
		settings = { ...settings, ...partial };
		if (partial.elements) settings.elements = { ...partial.elements };
		if (api) api.set(partial);
		save();
		refresh();
		if (announce) say('Reset to the homepage setting.');
	}

	function resetAll() {
		settings = copySettings(homepage);
		if (api) api.set(settings);
		save();
		refresh();
		say('All settings are back to the homepage’s.');
	}

	function restart() {
		if (!frame || !frame.dataset.src) return;
		reloads += 1;
		paused = false;
		const hash = encodeURIComponent(JSON.stringify(settings));
		frame.setAttribute('src', `${frame.dataset.src}${frame.dataset.src.includes('?') ? '&' : '?'}lab=${reloads}#lab=${hash}`);
		say('Restarted with these settings.');
	}

	function differs(a, b) {
		return JSON.stringify(normal(a)) !== JSON.stringify(normal(b));
	}

	function normal(s) {
		const out = { ...s, elements: {} };
		for (const item of elements) {
			const n = frequency(item.id, s);
			if (n !== 1) out.elements[item.id] = n;
		}
		return Object.keys(out).sort().reduce((o, k) => ((o[k] = out[k]), o), {});
	}

	function encodeState() {
		return `#s=${encodeURIComponent(JSON.stringify(normal(settings)))}`;
	}

	function save() {
		if (!settings) return;
		const same = !differs(settings, homepage);
		try {
			if (same) localStorage.removeItem(STORE);
			else localStorage.setItem(STORE, JSON.stringify(normal(settings)));
		} catch (e) {
			// Private mode: the URL hash still holds the settings.
		}
		history.replaceState(null, '', same ? location.pathname + location.search : encodeState());
	}

	function saved() {
		const sources = [];
		if (location.hash.startsWith('#s=')) sources.push(() => decodeURIComponent(location.hash.slice(3)));
		sources.push(() => localStorage.getItem(STORE));
		for (const read of sources) {
			try {
				const raw = read();
				if (raw) return sanitize(JSON.parse(raw));
			} catch (e) {
				// Ignore unreadable state and fall through.
			}
		}
		return null;
	}

	// Keep only values the engine accepts, on top of the homepage settings.
	function sanitize(input) {
		const s = copySettings(homepage);
		if (!input || typeof input !== 'object') return s;
		for (const key of ['pace', 'fullness', 'markSize']) {
			if (Number.isFinite(input[key]) && input[key] > 0) s[key] = input[key];
		}
		for (const key of ['autoDraw', 'transform']) {
			if (typeof input[key] === 'boolean') s[key] = input[key];
		}
		if (typeof input.ink === 'string' && /^#[0-9a-f]{6}$/i.test(input.ink)) s.ink = input.ink.toLowerCase();
		if (input.elements && typeof input.elements === 'object') {
			s.elements = {};
			for (const item of elements) {
				const n = input.elements[item.id];
				if (Number.isFinite(n) && n >= 0 && n !== 1) s.elements[item.id] = n;
			}
			if (elements.every((item) => frequency(item.id, s) === 0)) s.elements = { ...homepage.elements };
		}
		return s;
	}

	/* ------------------------------------------------------- copy & paste */

	function num(n, minDecimals = 0) {
		const text = String(round(n, 2));
		return minDecimals && !text.includes('.') ? `${text}.0` : text;
	}

	function configSnippet() {
		const lines = [
			'window.MARGINALIA_CONFIG = {',
			`  pace: ${num(settings.pace, 1)},`,
			`  fullness: ${num(settings.fullness, 1)},`,
			`  markSize: ${num(settings.markSize, 1)},`,
		];
		if (settings.autoDraw !== ENGINE_DEFAULTS.autoDraw) lines.push(`  autoDraw: ${settings.autoDraw},`);
		if (settings.transform !== ENGINE_DEFAULTS.transform) lines.push(`  transform: ${settings.transform},`);
		if (settings.ink !== ENGINE_DEFAULTS.ink) lines.push(`  ink: '${settings.ink}',`);
		const changed = elements.filter((item) => frequency(item.id) !== 1);
		if (changed.length) {
			lines.push('  elements: {');
			for (const item of changed) {
				const note = item.name !== item.id ? ` // ${item.name}` : '';
				lines.push(`    ${item.id}: ${num(frequency(item.id))},${note}`);
			}
			lines.push('  },');
		}
		lines.push('};');
		const file = LAB.configFile ? `// Paste into ${LAB.configFile}, replacing the existing block.\n` : '';
		return file + lines.join('\n');
	}

	async function copy(text, message) {
		try {
			await navigator.clipboard.writeText(text);
			say(message);
			return;
		} catch (e) {
			// Not a secure context or no permission: fall back to a hidden textarea.
		}
		const area = el('textarea', { class: 'mlab__offscreen', readonly: true });
		area.value = text;
		document.body.append(area);
		area.select();
		let ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		area.remove();
		say(ok ? message : 'Could not copy. Open “Show settings” and copy the text by hand.');
	}

	/* ------------------------------------------------------- the iframe */

	function showNotice(text) {
		notice.textContent = text;
		notice.hidden = !text;
	}

	function onFrameLoad() {
		const win = frame.contentWindow;
		const next = win && win.marginaliaEmbed;
		if (!next) return;
		api = next;
		paused = false;

		if (!homepage) {
			homepage = api.homepage();
			elements = api.elements;
			settings = saved() || copySettings(homepage);
			build();
			refresh();
			save();
			// Start the drawing the way these settings would start it.
			if (differs(settings, api.settings())) restart();
		} else {
			api.set(settings);
			refresh();
		}

		showNotice(
			api.reducedMotion()
				? 'Your computer is set to reduce motion, so the drawing stays still here (as it would on the homepage).'
				: ''
		);
	}

	function init() {
		document.body.append(panel);

		if (!frame) {
			body.append(el('p', { class: 'mlab__notice', text: 'The Marginalia drawing is not on this page.' }));
			return;
		}

		frame.addEventListener('load', onFrameLoad);
		if (frame.getAttribute('src')) {
			try {
				if (frame.contentDocument && frame.contentDocument.readyState === 'complete') onFrameLoad();
			} catch (e) {
				// Not loaded yet; the load event will fire.
			}
		}

		const wide = window.matchMedia('(min-width: 1133px)');
		const checkWidth = () => {
			if (!api) {
				body.prepend(notice);
				showNotice(wide.matches ? 'Loading the drawing…' : 'Make the window at least 1133px wide: the drawing only shows in the desktop header.');
			}
		};
		checkWidth();
		wide.addEventListener('change', checkWidth);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
