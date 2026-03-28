/* global document */
( function () {
	'use strict';

	const checkboxes = document.querySelectorAll( '.uplinkportal-module-checkbox[data-module-key]' );
	const syncModuleState = ( moduleKey, enabled ) => {
		const trigger = document.querySelector( '[data-module-config-trigger="' + moduleKey + '"]' );
		if ( trigger ) {
			trigger.disabled = ! enabled;
		}
	};

	const bindModuleTabs = () => {
		document.querySelectorAll( '[data-uplinkportal-window-tabs]' ).forEach( ( windowShell ) => {
			const tabs = windowShell.querySelectorAll( '[data-uplinkportal-window-tab]' );
			const panels = windowShell.querySelectorAll( '[data-uplinkportal-window-panel]' );
			if ( tabs.length <= 1 || ! panels.length ) {
				return;
			}

			const activate = ( key ) => {
				tabs.forEach( ( tab ) => {
					const tabKey = tab.getAttribute( 'data-uplinkportal-window-tab' );
					const active = ( tabKey === key );
					tab.classList.toggle( 'is-active', active );
					tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				panels.forEach( ( panel ) => {
					const panelKey = panel.getAttribute( 'data-uplinkportal-window-panel' );
					panel.hidden = ( panelKey !== key );
				} );
			};

			const first = tabs[ 0 ];
			if ( first ) {
				const firstKey = first.getAttribute( 'data-uplinkportal-window-tab' );
				if ( firstKey ) {
					activate( firstKey );
				}
			}

			tabs.forEach( ( tab ) => {
				tab.addEventListener( 'click', () => {
					const key = tab.getAttribute( 'data-uplinkportal-window-tab' );
					if ( key ) {
						activate( key );
					}
				} );
			} );
		} );
	};

	const bindProfileSwitchers = () => {
		document.querySelectorAll( '[data-uplinkportal-profile-switcher]' ).forEach( ( switcher ) => {
			const tabs = switcher.querySelectorAll( '[data-uplinkportal-profile-tab]' );
			const panelRoot = switcher.parentElement ? switcher.parentElement.querySelector( '.uplinkportal-database-profile-panels' ) : null;
			const panels = panelRoot ? panelRoot.querySelectorAll( '[data-uplinkportal-profile-panel]' ) : [];
			if ( tabs.length <= 1 || ! panels.length ) {
				return;
			}

			const activate = ( key ) => {
				tabs.forEach( ( tab ) => {
					const tabKey = tab.getAttribute( 'data-uplinkportal-profile-tab' );
					const active = ( tabKey === key );
					tab.classList.toggle( 'is-active', active );
					tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				panels.forEach( ( panel ) => {
					const panelKey = panel.getAttribute( 'data-uplinkportal-profile-panel' );
					panel.hidden = ( panelKey !== key );
				} );
			};

			const first = tabs[ 0 ];
			if ( first ) {
				const firstKey = first.getAttribute( 'data-uplinkportal-profile-tab' );
				if ( firstKey ) {
					activate( firstKey );
				}
			}

			tabs.forEach( ( tab ) => {
				tab.addEventListener( 'click', () => {
					const key = tab.getAttribute( 'data-uplinkportal-profile-tab' );
					if ( key ) {
						activate( key );
					}
				} );
			} );
		} );
	};

	const bindDatabaseViewSwitchers = () => {
		document.querySelectorAll( '[data-uplinkportal-database-view-switcher]' ).forEach( ( switcher ) => {
			const tabs = switcher.querySelectorAll( '[data-uplinkportal-database-view-tab]' );
			const root = switcher.parentElement;
			const panels = root ? root.querySelectorAll( '[data-uplinkportal-database-view-panel]' ) : [];
			if ( tabs.length <= 1 || ! panels.length ) {
				return;
			}

			const activate = ( key ) => {
				tabs.forEach( ( tab ) => {
					const tabKey = tab.getAttribute( 'data-uplinkportal-database-view-tab' );
					const active = ( tabKey === key );
					tab.classList.toggle( 'is-active', active );
					tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				panels.forEach( ( panel ) => {
					const panelKey = panel.getAttribute( 'data-uplinkportal-database-view-panel' );
					panel.hidden = ( panelKey !== key );
				} );
			};

			const first = tabs[ 0 ];
			if ( first ) {
				const firstKey = first.getAttribute( 'data-uplinkportal-database-view-tab' );
				if ( firstKey ) {
					activate( firstKey );
				}
			}

			tabs.forEach( ( tab ) => {
				tab.addEventListener( 'click', () => {
					const key = tab.getAttribute( 'data-uplinkportal-database-view-tab' );
					if ( key ) {
						activate( key );
					}
				} );
			} );
		} );
	};

	if ( checkboxes.length ) {
		checkboxes.forEach( ( checkbox ) => {
			const moduleKey = checkbox.getAttribute( 'data-module-key' );
			if ( ! moduleKey ) {
				return;
			}

			syncModuleState( moduleKey, checkbox.checked );

			checkbox.addEventListener( 'change', () => {
				syncModuleState( moduleKey, checkbox.checked );
			} );
		} );
	}

	document.querySelectorAll( '[data-module-config-trigger][disabled]' ).forEach( ( button ) => {
		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			return;
		} );
	} );

	bindModuleTabs();
	bindProfileSwitchers();
	bindDatabaseViewSwitchers();
}() );
