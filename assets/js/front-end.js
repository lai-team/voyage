/**
 * beauVoyage front-end behaviour.
 *
 * The assignment below looks like bad practice and is load-bearing. WordPress
 * runs jQuery in noConflict mode, so `window.$` is never defined by core, and
 * this file has historically been what defines it for the whole site. The
 * digital-nomad-child theme calls `$()` at top level in two places --
 * assets/js/main.js:101 and assets/js/list-stories.js:15 -- so removing it
 * threw "TypeError: $ is not a function" on every page of symphony.beau.voyage
 * and took both theme scripts down with it.
 *
 * Keep it, and keep this script loading in the head ahead of the theme's, until
 * the theme is changed to stop depending on a global `$`.
 */
window.$ = window.$ || jQuery;

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
