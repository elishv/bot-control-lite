/**
 * All-IN Crawler & AI Agent Manager admin UI.
 * Author: Eli Shvarzman
 */
( function () {
	'use strict';

	var cfg = window.botctlAdmin || { confirm: 'Are you sure?' };

	// Confirm destructive buttons. data-confirm overrides the generic message.
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) {
			return;
		}
		var btn = e.target.closest( '.botctl-confirm' );
		if ( ! btn ) {
			return;
		}
		var msg = btn.getAttribute( 'data-confirm' ) || cfg.confirm;
		if ( ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );

	// The review notice "X" uses the same persistent dismiss as the Dismiss link.
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) {
			return;
		}
		var dismiss = e.target.closest( '.notice-dismiss' );
		var notice = dismiss ? dismiss.closest( '#botctl-review-notice' ) : null;
		if ( ! notice ) {
			return;
		}
		var link = notice.querySelector( 'a.botctl-review-dismiss' );
		if ( link ) {
			window.location.href = link.href;
		}
	} );

	// "Leave a review" opens WordPress.org in a new tab and quietly records "done"
	// so the notice never comes back for this admin.
	document.addEventListener( 'click', function ( e ) {
		var go = e.target.closest ? e.target.closest( 'a.botctl-review-go' ) : null;
		if ( ! go || ! go.dataset.done ) {
			return;
		}
		fetch( go.dataset.done, { credentials: 'same-origin', redirect: 'manual' } );
		var notice = go.closest( '#botctl-review-notice' );
		if ( notice ) {
			notice.style.display = 'none';
		}
	} );

	// Custom bots: add / remove rows.
	var body = document.getElementById( 'botctl-custom-body' );
	var tpl = document.getElementById( 'botctl-custom-template' );
	var add = document.getElementById( 'botctl-add-row' );

	if ( body && tpl && add ) {
		var counter = body.querySelectorAll( 'tr' ).length;

		add.addEventListener( 'click', function () {
			var html = tpl.innerHTML.replace( /__INDEX__/g, 'n' + ( counter++ ) );
			var tmp = document.createElement( 'tbody' );
			tmp.innerHTML = html.trim();
			var row = tmp.querySelector( 'tr' );
			if ( row ) {
				body.appendChild( row );
				var first = row.querySelector( 'input[type="text"]' );
				if ( first ) {
					first.focus();
				}
			}
		} );

		body.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.botctl-remove-row' );
			if ( rm ) {
				e.preventDefault();
				rm.closest( 'tr' ).remove();
			}
		} );
	}

	// Bot rules: live filter.
	var filter = document.querySelector( '.botctl-filter' );
	if ( filter ) {
		filter.addEventListener( 'input', function () {
			var q = filter.value.trim().toLowerCase();
			document.querySelectorAll( '.botctl-bot-row' ).forEach( function ( row ) {
				row.style.display = ! q || row.getAttribute( 'data-search' ).indexOf( q ) !== -1 ? '' : 'none';
			} );
			document.querySelectorAll( '.botctl-details' ).forEach( function ( d ) {
				if ( q ) {
					var visible = d.querySelectorAll( '.botctl-bot-row:not([style*="none"])' ).length;
					d.open = visible > 0;
					d.style.display = visible > 0 ? '' : 'none';
				} else {
					d.style.display = '';
				}
			} );
		} );
	}
} )();
