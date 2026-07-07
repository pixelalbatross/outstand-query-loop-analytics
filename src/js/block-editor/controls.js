/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const VARIATION_NAMESPACE = 'outstand/popular-posts';

const withExcludeCurrentControl = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, attributes, setAttributes } = props;

		if (
			name !== 'core/query' ||
			attributes?.namespace !== VARIATION_NAMESPACE
		) {
			return <BlockEdit { ...props } />;
		}

		const { excludeCurrentPost = false } = attributes;

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Popular Posts',
							'outstand-query-loop-analytics'
						) }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Exclude current post',
								'outstand-query-loop-analytics'
							) }
							help={ __(
								'Omit the post being viewed from the results.',
								'outstand-query-loop-analytics'
							) }
							checked={ excludeCurrentPost }
							onChange={ ( value ) =>
								setAttributes( { excludeCurrentPost: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	},
	'withExcludeCurrentControl'
);

addFilter(
	'editor.BlockEdit',
	'outstand-query-loop-analytics/controls',
	withExcludeCurrentControl
);
