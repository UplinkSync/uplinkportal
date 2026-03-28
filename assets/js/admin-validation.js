/* global window, document, fetch, URLSearchParams */
( function () {
	'use strict';

	const config = window.UplinkPortalValidation || null;
	if ( ! config ) {
		return;
	}

	const buttons = document.querySelectorAll( '.uplinkportal-test-connection[data-section]' );
	if ( ! buttons.length ) {
		return;
	}

	const escapeHtml = ( value ) => {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	};

	const render = ( resultRoot, payload, isError ) => {
		if ( ! resultRoot ) {
			return;
		}

		if ( ! payload ) {
			resultRoot.innerHTML = '<p>' + escapeHtml( config.i18n.error || 'Connection test failed.' ) + '</p>';
			return;
		}

		const status = payload.status || ( isError ? 'error' : 'warning' );
		const score = Number( payload.score || 0 );
		const icon = status === 'success' ? '✅' : ( status === 'warning' ? '⚠️' : '❌' );
		const summary = status === 'success'
			? 'Connection looks compatible.'
			: ( status === 'warning' ? 'Connection partially validated.' : 'Connection validation failed.' );

		const guidance = Array.isArray( payload.guidance ) ? payload.guidance : [];
		const evidence = Array.isArray( payload.evidence ) ? payload.evidence : [];

		let guidanceHtml = '<ul>';
		guidance.forEach( ( item ) => {
			guidanceHtml += '<li>' + escapeHtml( item ) + '</li>';
		} );
		guidanceHtml += '</ul>';

		let evidenceHtml = '<ul>';
		evidence.forEach( ( item ) => {
			const endpoint = item.endpoint ? ' [' + item.endpoint + ']' : '';
			const http = item.http_status ? ' HTTP ' + item.http_status : '';
			const auth = item.auth ? ' auth=' + item.auth : '';
			const line = ( item.message || item.type || 'probe' ) + endpoint + http + auth;
			evidenceHtml += '<li>' + escapeHtml( line ) + '</li>';
		} );
		evidenceHtml += '</ul>';

		resultRoot.innerHTML = '' +
			'<div class="notice notice-' + ( status === 'success' ? 'success' : ( status === 'warning' ? 'warning' : 'error' ) ) + ' inline">' +
				'<p><strong>' + icon + ' ' + escapeHtml( summary ) + '</strong> ' +
				'Compatibility score: <strong>' + escapeHtml( String( score ) ) + '/100</strong></p>' +
			'</div>' +
			'<div class="uplinkportal-validation-guidance"><h4>Next Steps</h4>' + guidanceHtml + '</div>' +
			'<details class="uplinkportal-validation-diagnostics"><summary>Diagnostics</summary>' + evidenceHtml + '</details>';
	};

	buttons.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const section = button.getAttribute( 'data-section' ) || 'billing';
			const wrapper = button.closest( '.uplinkportal-validation-group' );
			if ( ! wrapper ) {
				return;
			}

			const baseUrlInput = wrapper.querySelector( '.uplinkportal-validation-base-url' );
			const apiKeyInput = wrapper.querySelector( '.uplinkportal-validation-api-key' );
			const oauthRefInput = wrapper.querySelector( '.uplinkportal-validation-oauth-ref' );
			const allowQueryInput = wrapper.querySelector( '.uplinkportal-validation-allow-query' );
			const resultRoot = wrapper.querySelector( '.uplinkportal-validation-result' );

			button.disabled = true;
			if ( resultRoot ) {
				resultRoot.innerHTML = '<p>' + escapeHtml( config.i18n.testing || 'Testing connection...' ) + '</p>';
			}

			const formData = new URLSearchParams();
			formData.append( 'action', 'uplinkportal_validation_test' );
			formData.append( 'nonce', config.nonce || '' );
			formData.append( 'base_url', baseUrlInput ? baseUrlInput.value : '' );
			formData.append( 'api_key', apiKeyInput ? apiKeyInput.value : '' );
			formData.append( 'oauth_connection_ref', oauthRefInput ? oauthRefInput.value : '' );
			formData.append( 'section', section );
			formData.append( 'allow_query_api_key', allowQueryInput && allowQueryInput.checked ? '1' : '0' );

			fetch( config.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: formData.toString(),
				credentials: 'same-origin',
			} )
				.then( ( response ) => response.json() )
				.then( ( payload ) => {
					if ( ! payload || ! payload.success ) {
						render( resultRoot, payload && payload.data ? payload.data : null, true );
						return;
					}
					render( resultRoot, payload.data, false );
				} )
				.catch( () => {
					render( resultRoot, null, true );
				} )
				.finally( () => {
					button.disabled = false;
				} );
		} );
	} );
}() );
