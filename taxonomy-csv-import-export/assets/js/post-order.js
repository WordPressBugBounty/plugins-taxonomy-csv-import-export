( function ( $ ) {
	'use strict';

	var cfg = window.taxocsiePostOrder;
	if ( ! cfg ) {
		return;
	}

	// Filter form: submit on change, clearing dependent fields.
	$( '#taxocsie-po-filters' ).on( 'change', '[data-autosubmit], #taxocsie-po-term', function () {
		var $form = $( '#taxocsie-po-filters' );
		var reset = ( $( this ).data( 'reset' ) || '' ).toString().split( ',' );
		reset.forEach( function ( name ) {
			if ( name ) {
				$form.find( '[name="' + name + '"]' ).val( '' ).prop( 'disabled', true );
			}
		} );
		$form.trigger( 'submit' );
	} );

	var $rows = $( '#taxocsie-po-rows' );
	if ( ! $rows.length ) {
		return;
	}
	var $status = $( '#taxocsie-po-status' );
	var timer;

	function say( text, isError ) {
		clearTimeout( timer );
		$status.text( text ).toggleClass( 'is-error', !! isError );
		if ( text && ! isError && text === cfg.saved ) {
			timer = setTimeout( function () {
				$status.text( '' );
			}, 2000 );
		}
	}

	function request( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data ) );
	}

	function renumber() {
		$rows.children( 'tr' ).each( function ( i ) {
			$( this ).find( '.taxocsie-po-num' ).val( i + 1 );
		} );
		$( '#taxocsie-po-empty' ).prop( 'hidden', $rows.children( 'tr' ).length > 0 );
	}

	function saveOrder() {
		var ids = $rows.children( 'tr' ).map( function () {
			return $( this ).data( 'id' );
		} ).get();
		if ( ! ids.length ) {
			return;
		}
		say( cfg.saving );
		request( 'taxocsie_post_order_save', { ids: ids } ).done( function ( r ) {
			say( r && r.success ? cfg.saved : cfg.error, ! ( r && r.success ) );
		} ).fail( function () {
			say( cfg.error, true );
		} );
	}

	// Shared with the Pro script that adds drag and drop, quick sort and undo.
	window.taxocsiePO = {
		$rows: $rows,
		renumber: renumber,
		saveOrder: saveOrder
	};

	// Type a number to move a post to that position.
	$rows.on( 'change', '.taxocsie-po-num', function () {
		var $tr = $( this ).closest( 'tr' );
		var count = $rows.children( 'tr' ).length;
		var position = parseInt( this.value, 10 );
		if ( isNaN( position ) ) {
			renumber();
			return;
		}
		position = Math.max( 1, Math.min( count, position ) );
		var $others = $rows.children( 'tr' ).not( $tr );
		if ( position > $others.length ) {
			$rows.append( $tr );
		} else {
			$others.eq( position - 1 ).before( $tr );
		}
		renumber();
		saveOrder();
	} );

	// Rename in place (Enter or leaving the field).
	$rows.on( 'focus', '.taxocsie-po-title', function () {
		$( this ).data( 'orig', this.value );
	} );
	$rows.on( 'keydown', '.taxocsie-po-title, .taxocsie-po-num', function ( e ) {
		if ( 13 === e.which ) {
			e.preventDefault();
			this.blur();
		}
	} );
	$rows.on( 'blur', '.taxocsie-po-title', function () {
		var $input = $( this );
		var title = $.trim( this.value );
		if ( title === $input.data( 'orig' ) ) {
			return;
		}
		if ( ! title ) {
			this.value = $input.data( 'orig' );
			return;
		}
		say( cfg.saving );
		request( 'taxocsie_post_order_title', { id: $input.closest( 'tr' ).data( 'id' ), title: title } ).done( function ( r ) {
			if ( r && r.success ) {
				$input.data( 'orig', title );
				say( cfg.saved );
			} else {
				$input.val( $input.data( 'orig' ) );
				say( cfg.error, true );
			}
		} ).fail( function () {
			$input.val( $input.data( 'orig' ) );
			say( cfg.error, true );
		} );
	} );

	// Move to Trash, then close the gap in the numbering.
	$rows.on( 'click', '.taxocsie-po-trash', function () {
		if ( ! window.confirm( cfg.confirm ) ) {
			return;
		}
		var $tr = $( this ).closest( 'tr' );
		say( cfg.saving );
		request( 'taxocsie_post_order_trash', { id: $tr.data( 'id' ) } ).done( function ( r ) {
			if ( r && r.success ) {
				$tr.remove();
				renumber();
				saveOrder();
			} else {
				say( cfg.error, true );
			}
		} ).fail( function () {
			say( cfg.error, true );
		} );
	} );
} )( jQuery );
