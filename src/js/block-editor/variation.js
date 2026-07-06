/**
 * WordPress dependencies.
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockVariation( 'core/query', {
	name: 'outstand-popular-posts',
	title: __( 'Popular Posts', 'outstand-query-loop-analytics' ),
	description: __(
		'Display the most popular posts based on analytics data.',
		'outstand-query-loop-analytics'
	),
	category: 'theme',
	icon: 'chart-bar',
	allowedControls: [
		'postType',
		'taxQuery',
		'author',
		'search',
		'postCount',
		'offset',
		'pages',
	],
	attributes: {
		namespace: 'outstand/popular-posts',
		orderByPopularity: true,
		query: {
			perPage: 5,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			inherit: false,
		},
	},
	scope: [ 'inserter' ],
	isActive: [ 'namespace' ],
} );
