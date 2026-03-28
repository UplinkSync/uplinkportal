/* global window, document */
( function () {
	'use strict';

	window.uplinkPortal = window.uplinkPortal || {};

	const root = document.getElementById( 'uplinkportal-root' );
	if ( ! root ) {
		return;
	}
	root.classList.add( 'is-js' );
	const portalConfig = window.UplinkPortalPortal || {};
	const restDataBase = typeof portalConfig.rest_data_base === 'string' ? portalConfig.rest_data_base : '';
	const restNonce = typeof portalConfig.rest_nonce === 'string' ? portalConfig.rest_nonce : '';

	const links = root.querySelectorAll( '.uplinkportal-sections__link[href^="#uplinkportal-section-"]' );
	if ( ! links.length ) {
		return;
	}

	const activate = ( targetId ) => {
		links.forEach( ( link ) => {
			const isActive = link.getAttribute( 'href' ) === '#' + targetId;
			link.classList.toggle( 'is-active', isActive );
			link.setAttribute( 'aria-current', isActive ? 'page' : 'false' );
		} );

		root.querySelectorAll( '.uplinkportal-section' ).forEach( ( section ) => {
			const isActive = section.id === targetId;
			section.classList.toggle( 'is-active', isActive );
			section.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
		} );
	};

	links.forEach( ( link ) => {
		link.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			const targetId = link.getAttribute( 'href' ).replace( '#', '' );
			activate( targetId );
		} );
	} );

	const hashTarget = window.location.hash ? window.location.hash.replace( '#', '' ) : '';
	if ( hashTarget && root.querySelector( '#' + hashTarget ) ) {
		activate( hashTarget );
		return;
	}

	const firstActiveLink = root.querySelector( '.uplinkportal-sections__link.is-active[href^="#uplinkportal-section-"]' );
	if ( firstActiveLink ) {
		activate( firstActiveLink.getAttribute( 'href' ).replace( '#', '' ) );
	}

	const moduleStates = root.querySelectorAll( '[data-uplinkportal-module-state][data-module-key]' );
	if ( ! moduleStates.length ) {
		return;
	}

	const setVisible = ( element, isVisible ) => {
		if ( ! element ) {
			return;
		}
		element.hidden = ! isVisible;
	};

	const toSummary = ( record ) => {
		if ( ! record || typeof record !== 'object' ) {
			return '';
		}
		const skipKeys = [ 'id', 'user_id', 'provider' ];
		const parts = [];
		Object.keys( record ).forEach( ( key ) => {
			if ( skipKeys.includes( key ) ) {
				return;
			}
			const value = record[ key ];
			if ( value === null || typeof value === 'undefined' || value === '' ) {
				return;
			}
			parts.push( key.replace( /_/g, ' ' ) + ': ' + String( value ) );
		} );
		return parts.join( ' | ' );
	};

	const renderRows = ( list, rows ) => {
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		rows.forEach( ( row, index ) => {
			const item = document.createElement( 'li' );
			item.className = 'uplinkportal-module-list__item';

			const heading = document.createElement( 'strong' );
			heading.textContent = row && row.id ? String( row.id ) : 'Record ' + String( index + 1 );
			item.appendChild( heading );

			const summary = toSummary( row );
			if ( summary ) {
				const details = document.createElement( 'p' );
				details.textContent = summary;
				item.appendChild( details );
			}

			list.appendChild( item );
		} );
	};

	const loadModuleState = ( state ) => {
		const moduleKey = state.getAttribute( 'data-module-key' );
		const fallback = state.querySelector( '[data-uplinkportal-module-fallback]' );
		const loading = state.querySelector( '[data-uplinkportal-module-loading]' );
		const error = state.querySelector( '[data-uplinkportal-module-error]' );
		const empty = state.querySelector( '[data-uplinkportal-module-empty]' );
		const list = state.querySelector( '[data-uplinkportal-module-list]' );

		setVisible( fallback, false );
		setVisible( loading, true );
		setVisible( error, false );
		setVisible( empty, false );
		setVisible( list, false );

		if ( ! moduleKey || ! restDataBase || ! restNonce ) {
			setVisible( loading, false );
			setVisible( error, true );
			return;
		}

		fetch( restDataBase + encodeURIComponent( moduleKey ), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': restNonce
			}
		} )
			.then( async ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( ( payload ) => {
				const rows = payload && Array.isArray( payload.data ) ? payload.data : [];
				setVisible( loading, false );
				if ( ! rows.length ) {
					setVisible( empty, true );
					return;
				}
				renderRows( list, rows );
				setVisible( list, true );
			} )
			.catch( () => {
				setVisible( loading, false );
				setVisible( error, true );
			} );
	};

	moduleStates.forEach( ( state ) => {
		loadModuleState( state );
	} );
}() );
