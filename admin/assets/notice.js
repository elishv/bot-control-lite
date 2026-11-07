/**
 * Persist the fresh-install watch-only notice dismissal.
 * Author: Eli Shvarzman
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest || ! window.botctlNotice ) {
			return;
		}
		var dismiss = e.target.closest( '.notice-dismiss' );
		if ( ! dismiss || ! dismiss.closest( '#botctl-watch-notice' ) ) {
			return;
		}
		var body = 'action=botctl_dismiss_watch&_ajax_nonce=' + encodeURIComponent( botctlNotice.nonce );
		if ( window.fetch ) {
			window.fetch( botctlNotice.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body
			} );
		}
	} );
}() );
