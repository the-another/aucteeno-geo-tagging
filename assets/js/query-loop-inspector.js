/**
 * Aucteeno Geo-Tagging — Query Loop Inspector.
 *
 * Adds two block attributes (geoTaggingEnabled, geoTaggingAffectsBots) to
 * the aucteeno/query-loop block at runtime via blocks.registerBlockType, and
 * injects a matching Inspector panel via editor.BlockEdit HOC filter.
 */

import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const BLOCK_NAME = 'aucteeno/query-loop';
const FILTER_NAMESPACE = 'aucteeno-geo-tagging';

/**
 * Register the two new block attributes on aucteeno/query-loop.
 *
 * @param {Object} settings Block settings object.
 * @param {string} name     Block name.
 * @return {Object} Modified settings.
 */
function addGeoTaggingAttributes( settings, name ) {
	if ( name !== BLOCK_NAME ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			geoTaggingEnabled: {
				type: 'boolean',
				default: false,
			},
			geoTaggingAffectsBots: {
				type: 'boolean',
				default: false,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	`${ FILTER_NAMESPACE }/add-attributes`,
	addGeoTaggingAttributes
);

/**
 * Inject the Inspector panel via BlockEdit HOC.
 */
const withGeoTaggingInspector = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== BLOCK_NAME ) {
			return createElement( BlockEdit, props );
		}

		const { attributes, setAttributes } = props;
		const { geoTaggingEnabled, geoTaggingAffectsBots } = attributes;

		return createElement(
			Fragment,
			null,
			createElement( BlockEdit, props ),
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						title: __( 'Geo-Tagging', 'aucteeno-geo-tagging' ),
						initialOpen: false,
					},
					createElement( ToggleControl, {
						label: __(
							'Enable geo-tagging',
							'aucteeno-geo-tagging'
						),
						help: __(
							"When enabled, the visitor's Cloudflare-detected country and region override any manually set location filters. The editor preview reflects your own location.",
							'aucteeno-geo-tagging'
						),
						checked: !! geoTaggingEnabled,
						onChange: ( value ) =>
							setAttributes( { geoTaggingEnabled: value } ),
					} ),
					geoTaggingEnabled &&
						createElement( ToggleControl, {
							label: __(
								'Also apply to bots',
								'aucteeno-geo-tagging'
							),
							help: __(
								'By default, detected bots (search crawlers, social previewers) bypass geo-tagging so they see the default content. Enable this to apply geo-tagging to bots too.',
								'aucteeno-geo-tagging'
							),
							checked: !! geoTaggingAffectsBots,
							onChange: ( value ) =>
								setAttributes( {
									geoTaggingAffectsBots: value,
								} ),
						} )
				)
			)
		);
	};
}, 'withGeoTaggingInspector' );

addFilter(
	'editor.BlockEdit',
	`${ FILTER_NAMESPACE }/with-inspector`,
	withGeoTaggingInspector
);
