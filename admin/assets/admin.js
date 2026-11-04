/**
 * All-IN Crawler & AI Agent Manager admin UI.
 * Author: Eli Shvarzman
 */
( function () {
	'use strict';

	var cfg = window.botctlAdmin || { confirm: 'Are you sure?' };

	// Confirm destructive buttons.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.botctl-confirm' );
		if ( btn && ! window.confirm( cfg.confirm ) ) {
			e.preventDefault();
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
