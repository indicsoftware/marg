/* global describe, expect, test */

const schema = require('../acf-json/group_68ac2f305640f.json');

const topLevelField = (name) => schema.fields.find((field) => field.name === name);
const subField = (groupName, name) =>
	topLevelField(groupName).sub_fields.find((field) => field.name === name);

describe('product schema launch scope', () => {
	test('defines the approved magazine fields and validation', () => {
		const productType = topLevelField('type');
		const publicationDate = subField('magazine_details', 'publication_month_year');

		expect(productType.instructions).not.toContain('Gift Sets');
		expect(subField('magazine_details', 'volume_info')).toBeUndefined();
		expect(subField('magazine_details', 'year')).toMatchObject({
			key: 'field_magazine_year',
			type: 'text',
			readonly: 1,
		});
		expect(subField('magazine_details', 'publication_month_year_reference')).toBeDefined();

		for (const name of ['volume_number', 'issue_number']) {
			expect(subField('magazine_details', name)).toMatchObject({
				type: 'number',
				required: 1,
				min: 1,
				step: 1,
			});
		}

		expect(publicationDate.required).toBe(1);
		expect(publicationDate.instructions).toContain('For older issues');
		expect(publicationDate.instructions).toContain('current issue');
		expect(publicationDate.sub_fields).toEqual(
			expect.arrayContaining([
				expect.objectContaining({
					name: 'start_date',
					required: 1,
					display_format: 'F Y',
					return_format: 'F Y',
				}),
				expect.objectContaining({
					name: 'end_date',
					required: 0,
					display_format: 'F Y',
					return_format: 'F Y',
				}),
			]),
		);

		for (const name of ['number_of_photographs', 'number_of_illustrations', 'print_length']) {
			expect(subField('magazine_details', name)).toMatchObject({
				type: 'number',
				required: 0,
				min: 0,
				step: 1,
			});
		}
		expect(subField('magazine_details', 'open_access_featured_text')).toMatchObject({
			key: 'field_magazine_open_access_featured_text',
			type: 'text',
			required: 0,
		});
		expect(subField('magazine_details', 'sightseeing_scan')).toMatchObject({
			key: 'field_magazine_sightseeing_scan',
			type: 'image',
			return_format: 'id',
		});
	});

	test('defines the approved article relationship, pages, DOI and inherited access fields', () => {
		expect(subField('article_details', 'parent_publication')).toMatchObject({
			key: 'field_6851635e1fcb3',
			required: 1,
			min: 1,
			max: 1,
		});
		expect(subField('article_details', 'page_number')).toBeUndefined();
		for (const [name, key, label] of [
			['page_from', 'field_article_page_from', 'From'],
			['page_to', 'field_article_page_to', 'To'],
		]) {
			expect(subField('article_details', name)).toMatchObject({
				key,
				label,
				type: 'number',
				required: 0,
				min: 1,
				step: 1,
			});
		}
		expect(subField('article_details', 'doi_link')).toMatchObject({
			key: 'field_article_doi_link',
			type: 'url',
			readonly: 1,
		});
		expect(subField('article_details', 'open_access')).toMatchObject({
			type: 'true_false',
			default_value: 0,
			readonly: 1,
			disabled: 1,
		});
	});

	test('leaves parked print commerce fields in place and excludes deferred magazine fields', () => {
		const printDetails = subField('magazine_details', 'print_details');
		expect(printDetails.sub_fields).toEqual(
			expect.arrayContaining([
				expect.objectContaining({ key: 'field_print_price_inr', name: 'regular_price_inr' }),
				expect.objectContaining({ key: 'field_print_stock_status', name: 'stock_status' }),
				expect.objectContaining({ key: 'field_print_out_of_print', name: 'out_of_print' }),
				expect.objectContaining({ key: 'field_print_weight', name: 'weight' }),
			]),
		);
		for (const name of ['hover', 'cta', 'flipthrough', 'cutout', 'gallery']) {
			expect(subField('magazine_details', name)).toBeUndefined();
		}
	});
});
