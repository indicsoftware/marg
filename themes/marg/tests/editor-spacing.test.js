/* global describe, expect, test */

const fs = require('fs');
const path = require('path');

describe('classic editor spacing', () => {
	test('retains the WordPress content margin after the frontend reset loads', () => {
		const editorOverrides = fs.readFileSync(
			path.join(__dirname, '../assets/css/frontend/editor-style-overrides.css'),
			'utf8',
		);
		document.head.innerHTML = '';
		document.body.id = 'tinymce';
		document.body.className = 'mce-content-body wp-editor';
		document.body.innerHTML = '<p>Text</p>';

		for (const css of ['body { margin: 9px 10px; }', 'body { margin: 0; }', editorOverrides]) {
			const style = document.createElement('style');
			style.textContent = css;
			document.head.appendChild(style);
		}

		const computedStyle = window.getComputedStyle(document.body);

		expect(computedStyle.marginTop).toBe('9px');
		expect(computedStyle.marginRight).toBe('10px');
		expect(computedStyle.marginBottom).toBe('9px');
		expect(computedStyle.marginLeft).toBe('10px');
	});
});
