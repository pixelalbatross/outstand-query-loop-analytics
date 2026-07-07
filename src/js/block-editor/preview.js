/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as editorStore } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

const withPopularPostsPreview = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/post-template' ) {
			return <BlockEdit { ...props } />;
		}

		const { clientId } = props;

		const { orderByPopularity, excludeCurrentPost } = useSelect(
			( select ) => {
				const be = select( blockEditorStore );
				const parents = be.getBlockParentsByBlockName(
					clientId,
					'core/query'
				);
				const parentId = parents[ parents.length - 1 ];

				if ( ! parentId ) {
					return {
						orderByPopularity: false,
						excludeCurrentPost: false,
					};
				}

				const parentAttributes = be.getBlock( parentId )?.attributes;

				return {
					orderByPopularity: !! parentAttributes?.orderByPopularity,
					excludeCurrentPost: !! parentAttributes?.excludeCurrentPost,
				};
			},
			[ clientId ]
		);

		// The current post ID lets the canvas mirror the frontend exclusion.
		// Falls back to 0 outside a post-editing context (e.g. Site Editor).
		const currentPostId = useSelect(
			( select ) => select( editorStore )?.getCurrentPostId?.() ?? 0,
			[]
		);

		// Unknown keys to core/post-template fall through to restQueryArgs and
		// reach rest_{post_type}_query, where PHP applies the same ranking and
		// exclusion used on the frontend.
		const context = useMemo( () => {
			const query = { ...props.context?.query };

			if ( orderByPopularity ) {
				query.outstand_popular_posts = 1;
			}

			if ( excludeCurrentPost && currentPostId ) {
				query.outstand_exclude_current = currentPostId;
			}

			return { ...props.context, query };
		}, [
			props.context,
			orderByPopularity,
			excludeCurrentPost,
			currentPostId,
		] );

		if ( ! orderByPopularity && ! excludeCurrentPost ) {
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
