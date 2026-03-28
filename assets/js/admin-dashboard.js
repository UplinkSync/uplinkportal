/* global window, document */
( function () {
	'use strict';

	/**
	 * Activates a dashboard view tab and its associated panel.
	 *
	 * @param {string} viewKey - The view key to activate ('executive'|'finance'|'technician').
	 * @param {NodeList} tabs  - All tab button elements.
	 * @param {NodeList} panels - All panel elements.
	 */
	const activateView = ( viewKey, tabs, panels ) => {
		tabs.forEach( function ( tab ) {
			const key = tab.getAttribute( 'data-uplinkportal-view-tab' );
			const active = key === viewKey;
			tab.classList.toggle( 'is-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );

		panels.forEach( function ( panel ) {
			const key = panel.getAttribute( 'data-uplinkportal-view-panel' );
			panel.hidden = key !== viewKey;
		} );
	};

	/**
	 * Binds the Operations Dashboard role view switcher.
	 *
	 * Reads [data-uplinkportal-view-tab] buttons and [data-uplinkportal-view-panel]
	 * sections. Clicking a tab activates its panel and updates the URL via
	 * history.replaceState so the browser back button works without a full reload.
	 */
	const bindViewSwitcher = () => {
		const switcher = document.querySelector( '[data-uplinkportal-view-switcher]' );
		if ( ! switcher ) {
			return;
		}

		const tabs   = document.querySelectorAll( '[data-uplinkportal-view-tab]' );
		const panels = document.querySelectorAll( '[data-uplinkportal-view-panel]' );

		if ( ! tabs.length || ! panels.length ) {
			return;
		}

		// Activate the tab that matches the current ?view= query arg, defaulting to the first.
		const params      = new window.URLSearchParams( window.location.search );
		const initialView = params.get( 'view' ) || '';
		const firstKey    = ( tabs[ 0 ] && tabs[ 0 ].getAttribute( 'data-uplinkportal-view-tab' ) ) || '';
		const validKeys   = Array.from( tabs ).map( ( t ) => t.getAttribute( 'data-uplinkportal-view-tab' ) );
		const startView   = validKeys.includes( initialView ) ? initialView : firstKey;

		if ( startView ) {
			activateView( startView, tabs, panels );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				const viewKey = tab.getAttribute( 'data-uplinkportal-view-tab' );
				if ( ! viewKey ) {
					return;
				}

				activateView( viewKey, tabs, panels );

				// Update URL without reload so refresh preserves active tab.
				const url = new window.URL( window.location.href );
				url.searchParams.set( 'view', viewKey );
				window.history.replaceState( null, '', url.toString() );
			} );
		} );
	};

	bindViewSwitcher();
}() );
