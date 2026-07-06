/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

const withPopularPostsPreview = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/post-template' ) {
			return <BlockEdit { ...props } />;
		}

		const { clientId } = props;

		const orderByPopularity = useSelect(
			( select ) => {
				const be = select( blockEditorStore );
				const parents = be.getBlockParentsByBlockName(
					clientId,
					'core/query'
				);
				const parentId = parents[ parents.length - 1 ];

				if ( ! parentId ) {
					return false;
				}

				return !! be.getBlock( parentId )?.attributes
					?.orderByPopularity;
			},
			[ clientId ]
		);

		// `outstand_popular_posts` is an unknown key to core/post-template, so
		// it falls through to restQueryArgs and reaches rest_{post_type}_query,
		// where PHP applies the same ranking used on the frontend.
		const context = useMemo(
			() => ( {
				...props.context,
				query: {
					...props.context?.query,
					outstand_popular_posts: 1,
				},
			} ),
			[ props.context ]
		);

		if ( ! orderByPopularity ) {
			return <BlockEdit { ...props } />;
		}

		return <BlockEdit { ...props } context={ context } />;
	},
	'withPopularPostsPreview'
);

addFilter(
	'editor.BlockEdit',
	'outstand-query-loop-analytics/preview',
	withPopularPostsPreview
);
