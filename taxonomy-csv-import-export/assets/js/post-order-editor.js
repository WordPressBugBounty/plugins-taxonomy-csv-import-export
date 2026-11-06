/**
 * Auto "Order" for posts with categories (WP Taxonomy Manager).
 *  - Selecting a category/term fills Order with the next number in that category.
 *  - Shows which order numbers are already used in the selected categories.
 * Works in the block editor (sidebar panel) and the classic editor (box under Order).
 * The order is only replaced when it is 0 or still the number we set ourselves,
 * so a value you typed by hand is never overwritten.
 */
( function () {
	'use strict';

	var cfg = window.taxocsiePostOrderEditor;
	if ( ! cfg || ! cfg.taxes || ! window.wp || ! wp.apiFetch ) {
		return;
	}

	function fetchInfo( taxonomy, term, postId ) {
		var q = '?post_type=' + encodeURIComponent( cfg.postType ) +
			'&taxonomy=' + encodeURIComponent( taxonomy ) +
			'&term=' + encodeURIComponent( term ) +
			'&post_id=' + encodeURIComponent( postId || 0 );
		return wp.apiFetch( { path: '/taxocsie/v1/next-order' + q } );
	}

	var isBlockEditor = !! ( document.body.classList.contains( 'block-editor-page' ) && wp.data && wp.data.select( 'core/editor' ) );

	/* ---------------------------- Block editor ---------------------------- */
	if ( isBlockEditor ) {
		var prev = null;
		var lastAuto = null;
		var busy = false;

		wp.data.subscribe( function () {
			var sel = wp.data.select( 'core/editor' );
			if ( ! sel.getCurrentPostId() || ! sel.getCurrentPostType() ) {
				return;
			}

			var cur = {};
			cfg.taxes.forEach( function ( t ) {
				var v = sel.getEditedPostAttribute( t.restBase );
				cur[ t.name ] = Array.isArray( v ) ? v.slice() : null;
			} );

			if ( prev === null ) {
				prev = cur;
				return;
			}

			var added = null;
			cfg.taxes.forEach( function ( t ) {
				if ( added || ! cur[ t.name ] ) {
					return;
				}
				var before = prev[ t.name ] || [];
				var fresh = cur[ t.name ].filter( function ( id ) {
					return before.indexOf( id ) === -1;
				} );
				if ( fresh.length ) {
					added = { tax: t.name, term: fresh[ fresh.length - 1 ] };
				}
			} );
			prev = cur;

			if ( ! added || busy ) {
				return;
			}

			var order = sel.getEditedPostAttribute( 'menu_order' ) || 0;
			if ( order !== 0 && order !== lastAuto ) {
				return; // set by hand
			}

			busy = true;
			fetchInfo( added.tax, added.term, sel.getCurrentPostId() ).then( function ( r ) {
				lastAuto = r.order;
				wp.data.dispatch( 'core/editor' ).editPost( { menu_order: r.order } );
			} ).catch( function () {} ).then( function () {
				busy = false;
			} );
		} );

		// Sidebar panel: used order numbers per selected category.
		if ( wp.plugins && wp.element && wp.components ) {
			var el = wp.element.createElement;
			var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

			var OrdersPanel = function () {
				var state = wp.element.useState( [] );
				var groups = state[ 0 ];
				var setGroups = state[ 1 ];

				var key = wp.data.useSelect( function ( select ) {
					var s = select( 'core/editor' );
					var parts = [];
					cfg.taxes.forEach( function ( t ) {
						var v = s.getEditedPostAttribute( t.restBase );
						if ( Array.isArray( v ) ) {
							v.forEach( function ( id ) {
								parts.push( t.name + ':' + id );
							} );
						}
					} );
					return parts.join( ',' ) + '|' + s.getCurrentPostId();
				}, [] );

				wp.element.useEffect( function () {
					var alive = true;
					var parts = key.split( '|' );
					var postId = parseInt( parts[ 1 ], 10 ) || 0;
					var pairs = parts[ 0 ] ? parts[ 0 ].split( ',' ) : [];
					Promise.all( pairs.map( function ( p ) {
						var a = p.split( ':' );
						return fetchInfo( a[ 0 ], a[ 1 ], postId ).catch( function () {
							return null;
						} );
					} ) ).then( function ( res ) {
						if ( alive ) {
							setGroups( res.filter( Boolean ) );
						}
					} );
					return function () {
						alive = false;
					};
				}, [ key ] );

				var currentOrder = wp.data.useSelect( function ( select ) {
					return select( 'core/editor' ).getEditedPostAttribute( 'menu_order' ) || 0;
				}, [] );
				var editPost = wp.data.useDispatch( 'core/editor' ).editPost;

				if ( ! Panel ) {
					return null;
				}
				return el( Panel, { name: 'taxocsie-used-orders', title: 'WP Taxonomy Manager', className: 'taxocsie-used-orders' },
					el( 'p', { style: { margin: '0 0 12px', color: '#757575', fontSize: '12px' } }, 'Post order in this category' ),
					el( 'div', { style: { marginBottom: '12px' } },
						el( wp.components.TextControl, {
							label: 'Order for this post',
							type: 'number',
							min: 0,
							value: currentOrder,
							onChange: function ( v ) {
								lastAuto = null; // typed by hand
								editPost( { menu_order: parseInt( v, 10 ) || 0 } );
							},
							__nextHasNoMarginBottom: true
						} )
					),
					groups.length ? groups.map( function ( g, i ) {
						var nums = g.items.map( function ( it ) {
							return it.order;
						} );
						return el( 'div', { key: i, style: { marginBottom: '12px' } },
							el( 'strong', null, g.term + ' — ' + g.items.length + ( g.items.length === 1 ? ' post' : ' posts' ) ),
							g.items.length
								? el( 'div', null,
									el( 'p', { style: { margin: '4px 0' } }, 'Used: ' + nums.join( ', ' ) + ' · Next: ' + g.order ),
									el( 'ol', { style: { margin: 0, paddingLeft: '0', listStyle: 'none', fontSize: '12px' } },
										g.items.map( function ( it ) {
											return el( 'li', { key: it.id }, el( 'code', null, it.order ), ' ' + it.title );
										} )
									)
								)
								: el( 'p', { style: { margin: '4px 0' } }, 'No other posts yet. Next order: 1' )
						);
					} ) : el( 'p', null, 'Select a category to see the orders already used.' )
				);
			};

			wp.plugins.registerPlugin( 'taxocsie-used-orders', { render: OrdersPanel } );
		}
		return;
	}

	/* --------------------------- Classic editor --------------------------- */
	if ( ! window.jQuery ) {
		return;
	}
	var $ = window.jQuery;
	var $order = $( '#menu_order' );
	if ( ! $order.length ) {
		return;
	}
	var lastAutoClassic = null;
	var postId = parseInt( $( '#post_ID' ).val(), 10 ) || 0;
	var $info = $( '<div class="taxocsie-used-orders" style="margin-top:8px;font-size:12px"></div>' ).insertAfter( $order.closest( 'p' ).length ? $order.closest( 'p' ) : $order );

	function render( r ) {
		$info.empty();
		$info.append( $( '<div style="color:#757575;margin-bottom:4px"></div>' ).text( 'WP Taxonomy Manager · Post order' ) );
		$info.append( $( '<strong></strong>' ).text( r.term + ' — ' + r.items.length + ( r.items.length === 1 ? ' post' : ' posts' ) ) );
		if ( r.items.length ) {
			$info.append( $( '<div></div>' ).text( 'Used: ' + r.items.map( function ( i ) { return i.order; } ).join( ', ' ) + ' · Next: ' + r.order ) );
			var $ul = $( '<ul style="margin:4px 0 0"></ul>' );
			r.items.forEach( function ( i ) {
				$ul.append( $( '<li></li>' ).append( $( '<code></code>' ).text( i.order ) ).append( document.createTextNode( ' ' + i.title ) ) );
			} );
			$info.append( $ul );
		}
	}

	cfg.taxes.forEach( function ( t ) {
		$( '#taxonomy-' + t.name ).on( 'change', 'input[type="checkbox"]', function () {
			if ( ! this.checked ) {
				return;
			}
			fetchInfo( t.name, this.value, postId ).then( function ( r ) {
				render( r );
				var cur = parseInt( $order.val(), 10 ) || 0;
				if ( cur === 0 || cur === lastAutoClassic ) {
					lastAutoClassic = r.order;
					$order.val( r.order );
				}
			} );
		} );
	} );
} )();
