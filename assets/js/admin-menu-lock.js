/**
 * Free plan: soft-lock the Pro-only «Export» item of the plugin menu (every admin screen).
 */
( function () {
	'use strict';

	var cfg = ( typeof window.f2000csMenuLock === 'object' && window.f2000csMenuLock ) ? window.f2000csMenuLock : {};

	function lockExportMenu() {
		var tip   = cfg.tip || 'В Pro';
		var links = document.querySelectorAll( '#adminmenu a[href*="page=f2000cs-export"]' );

		links.forEach( function ( link ) {
			var item = link.closest( 'li' ) || link;
			item.classList.add( 'f2000cs-menu-pro-locked' );
			item.setAttribute( 'data-pro-tip', tip );
			item.setAttribute( 'title', tip );
			link.setAttribute( 'title', tip );
			link.setAttribute( 'aria-disabled', 'true' );

			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', lockExportMenu );
	} else {
		lockExportMenu();
	}
} )();
