/* global jQuery, wp, document, window */
( function ( $ ) {
	'use strict';

	const onboardingRoot = document.querySelector( '.uplinkportal-onboarding' );
	if ( onboardingRoot && window.sessionStorage && window.matchMedia ) {
		const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		const currentStepRaw = onboardingRoot.getAttribute( 'data-current-step' );
		const currentStep = currentStepRaw ? parseInt( currentStepRaw, 10 ) : 1;
		const storageKey = 'uplinkportalOnboardingStep';

		if ( ! reducedMotion.matches && ! Number.isNaN( currentStep ) ) {
			const previousStepRaw = window.sessionStorage.getItem( storageKey );
			const previousStep = previousStepRaw ? parseInt( previousStepRaw, 10 ) : NaN;
			const isBackward = ! Number.isNaN( previousStep ) && currentStep < previousStep;

			onboardingRoot.classList.add( 'motion-enabled' );
			onboardingRoot.classList.add( isBackward ? 'is-step-backward' : 'is-step-forward' );

			window.requestAnimationFrame( function () {
				onboardingRoot.classList.add( 'is-animated' );
			} );

			const navButtons = onboardingRoot.querySelectorAll( '.uplinkportal-onboarding-back, .uplinkportal-onboarding-actions .button-primary' );
			navButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					onboardingRoot.classList.add( 'is-leaving' );
				} );
			} );
		}

		window.sessionStorage.setItem( storageKey, String( currentStep ) );
	}

	const selectButton = document.getElementById( 'uplinkportal-select-logo' );
	const input = document.getElementById( 'uplinkportal-company-logo-id' );
	const preview = document.getElementById( 'uplinkportal-logo-preview' );

	let frame = null;

	if ( selectButton && input && preview && wp && wp.media ) {
		selectButton.addEventListener( 'click', function () {
			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: ( window.UplinkPortalOnboarding && window.UplinkPortalOnboarding.title ) || 'Select Company Logo',
				button: {
					text: ( window.UplinkPortalOnboarding && window.UplinkPortalOnboarding.button ) || 'Use This Logo',
				},
				library: {
					type: [ 'image/png', 'image/svg+xml' ],
				},
				multiple: false,
			} );

			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				if ( ! attachment || ! attachment.id ) {
					return;
				}

				input.value = String( attachment.id );
				if ( attachment.url ) {
					preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;" />';
				}
			} );

			frame.open();
		} );
	}

	const emailMethodSelect = document.querySelector( '[data-uplinkportal-email-delivery]' );
	const smtpFields = document.querySelector( '[data-uplinkportal-email-fields="custom_smtp"]' );

	if ( emailMethodSelect && smtpFields ) {
		const toggleEmailFields = function () {
			const showSmtp = emailMethodSelect.value === 'custom_smtp';
			smtpFields.hidden = ! showSmtp;
		};

		toggleEmailFields();
		emailMethodSelect.addEventListener( 'change', toggleEmailFields );
	}
}( jQuery ) );
