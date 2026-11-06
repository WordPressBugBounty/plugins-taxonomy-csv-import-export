( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'taxocsie/ordered-posts', {
		apiVersion: 2,
		title: __( 'Ordered Posts', 'taxonomy-csv-import-export' ),
		description: __( 'List the posts of one category in the order set with Post Order.', 'taxonomy-csv-import-export' ),
		category: 'widgets',
		icon: 'sort',
		attributes: {
			postType: { type: 'string', default: 'post' },
			taxonomy: { type: 'string', default: 'category' },
			term: { type: 'string', default: '' },
			limit: { type: 'number', default: 10 }
		},
		edit: function ( props ) {
			var attrs = props.attributes;
			var set = props.setAttributes;
			var empty = el( 'p', {}, __( 'Enter a term slug or ID in the block settings.', 'taxonomy-csv-import-export' ) );

			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Ordered posts', 'taxonomy-csv-import-export' ) },
						el( TextControl, {
							label: __( 'Post type', 'taxonomy-csv-import-export' ),
							value: attrs.postType,
							onChange: function ( value ) {
								set( { postType: value } );
							}
						} ),
						el( TextControl, {
							label: __( 'Taxonomy', 'taxonomy-csv-import-export' ),
							value: attrs.taxonomy,
							onChange: function ( value ) {
								set( { taxonomy: value } );
							}
						} ),
						el( TextControl, {
							label: __( 'Term slug or ID', 'taxonomy-csv-import-export' ),
							value: attrs.term,
							onChange: function ( value ) {
								set( { term: value } );
							}
						} ),
						el( TextControl, {
							label: __( 'Number of posts', 'taxonomy-csv-import-export' ),
							type: 'number',
							min: 1,
							max: 100,
							value: attrs.limit,
							onChange: function ( value ) {
								set( { limit: parseInt( value, 10 ) || 10 } );
							}
						} )
					)
				),
				attrs.term
					? el( ServerSideRender, { block: 'taxocsie/ordered-posts', attributes: attrs, EmptyResponsePlaceholder: function () { return empty; } } )
					: empty
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
