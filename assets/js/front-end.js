/**
 * beauVoyage front-end behaviour.
 *
 * Previously this file opened with `$ = jQuery.noConflict();` at global scope,
 * which both created an implicit global and stripped `$` from every other
 * script on the page. It was also enqueued with no `jquery` dependency, so on
 * any page where jQuery loaded later the first line threw and nothing below it
 * ran. The dependency is now declared in voyage.php and the handler is scoped.
 */
jQuery( function ( $ ) {
	'use strict';

	if ( typeof bvVar === 'undefined' ) {
		return;
	}

	$( '#delete-subsite-btn' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( ! bvVar.delete_url || ! bvVar.delete_nonce ) {
			return;
		}

		if ( prompt( bvVar.delete_text ) !== 'DELETE' ) {
			alert( bvVar.delete_error_text );
			return;
		}

		// Submit as POST. The endpoint used to accept GET from a URL that was
		// printed into every page, which meant anything that follows links —
		// a prefetcher, a scanner, an unfurling bot — could trigger it.
		var $form = $( '<form>', { method: 'POST', action: bvVar.delete_url } );

		$form.append( $( '<input>', { type: 'hidden', name: 'pms_action', value: 'pms_delete_subsite' } ) );
		$form.append( $( '<input>', { type: 'hidden', name: 'pms_user', value: bvVar.user_id } ) );
		$form.append( $( '<input>', { type: 'hidden', name: 'pms_nonce', value: bvVar.delete_nonce } ) );

		$( 'body' ).append( $form );
		$form.trigger( 'submit' );
	} );

	if ( bvVar.user_email ) {
		$( '#pms_group_name' ).val( bvVar.user_email );
	}

	var pathname = window.location.pathname.split( '/' );
	if ( pathname.length > 1 && pathname[ pathname.length - 2 ] === bvVar.main_tab ) {
		$( '.pms-account-subscription-details-table' ).remove();
	}
} );
