/* global describe, expect, test */

const fs = require('fs');
const path = require('path');

const schemaDirectory = path.join(__dirname, '../acf-json');

const collectFieldKeys = (value, source, occurrences) => {
	if (Array.isArray(value)) {
		value.forEach((item) => collectFieldKeys(item, source, occurrences));
		return;
	}

	if (!value || typeof value !== 'object') {
		return;
	}

	if (typeof value.key === 'string' && value.key.startsWith('field_')) {
		const sources = occurrences.get(value.key) || [];
		sources.push(source);
		occurrences.set(value.key, sources);
	}

	Object.values(value).forEach((item) => collectFieldKeys(item, source, occurrences));
};

describe('ACF local JSON schema', () => {
	test('registers each field key exactly once', () => {
		const occurrences = new Map();
		const schemaFiles = fs
			.readdirSync(schemaDirectory)
			.filter((fileName) => fileName.startsWith('group_') && fileName.endsWith('.json'));

		schemaFiles.forEach((fileName) => {
			const schema = JSON.parse(fs.readFileSync(path.join(schemaDirectory, fileName), 'utf8'));
			collectFieldKeys(schema.fields, fileName, occurrences);
		});

		const duplicateKeys = [...occurrences]
			.filter(([, sources]) => sources.length > 1)
			.map(([key, sources]) => ({ key, sources }))
			.sort((left, right) => left.key.localeCompare(right.key));

		expect(duplicateKeys).toEqual([]);
	});
});
