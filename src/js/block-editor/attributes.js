/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';

addFilter(
	'blocks.registerBlockType',
	'outstand-query-loop-analytics/attribute',
	( settings, name ) => {
		if ( name !== 'core/query' ) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				orderByPopularity: {
					type: 'boolean',
					default: false,
				},
				excludeCurrentPost: {
					type: 'boolean',
					default: false,
				},
			},
		};
	}
);
