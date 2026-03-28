/* global document, window, UplinkPortalIntegrationWizard */
( function () {
	'use strict';

	const backendConfig = (
		( 'undefined' !== typeof UplinkPortalIntegrationWizard )
		&& UplinkPortalIntegrationWizard
	) ? UplinkPortalIntegrationWizard : {};
	const backendI18n = backendConfig.i18n ? backendConfig.i18n : {};
	const text = ( key, fallback ) => {
		if ( 'string' === typeof backendI18n[ key ] && '' !== backendI18n[ key ] ) {
			return backendI18n[ key ];
		}
		return fallback;
	};

	const urlInput = document.getElementById( 'uplinkportal-backend-base-url' );

	const modeInputs = document.querySelectorAll(
		'input[name="uplinkportal_settings[backend][mode]"], input[name="uplinkportal_settings[backend_mode]"]'
	);
	if ( urlInput && modeInputs.length ) {
		const syncUrlState = () => {
			let selectedMode = 'self_hosted';
			modeInputs.forEach( ( input ) => {
				if ( input.checked ) {
					selectedMode = input.value;
				}
			} );

			urlInput.disabled = ( 'managed_service' === selectedMode );
		};

		modeInputs.forEach( ( input ) => {
			input.addEventListener( 'change', syncUrlState );
		} );

		syncUrlState();
	}

	const backendForm = document.querySelector( 'form[action="options.php"]' );
	const monitoringModeInputs = document.querySelectorAll( 'input[name="uplinkportal_settings[security][monitoring][mode]"]' );
	const monitoringWebhookWrap = document.querySelector( '[data-uplinkportal-monitoring-webhook-wrap]' );
	const monitoringWebhookInput = document.querySelector( '[data-uplinkportal-monitoring-webhook-input]' );
	const monitoringHostedNote = document.querySelector( '[data-uplinkportal-monitoring-hosted-note]' );
	const monitoringNoneNote = document.querySelector( '[data-uplinkportal-monitoring-none-note]' );
	if ( monitoringModeInputs.length ) {
		const selectedMonitoringMode = () => {
			let selected = 'none';
			monitoringModeInputs.forEach( ( input ) => {
				if ( input.checked ) {
					selected = input.value;
				}
			} );
			return selected;
		};

		const syncMonitoringState = () => {
			const mode = selectedMonitoringMode();
			const isWebhook = ( 'generic_webhook' === mode );
			const isHosted = ( 'hosted_subscription' === mode );
			const isNone = ( 'none' === mode );

			if ( monitoringWebhookWrap ) {
				monitoringWebhookWrap.hidden = ! isWebhook;
			}
			if ( monitoringWebhookInput ) {
				monitoringWebhookInput.disabled = ! isWebhook;
			}
			if ( monitoringHostedNote ) {
				monitoringHostedNote.hidden = ! isHosted;
			}
			if ( monitoringNoneNote ) {
				monitoringNoneNote.hidden = ! isNone;
			}
		};

		monitoringModeInputs.forEach( ( input ) => {
			input.addEventListener( 'change', syncMonitoringState );
		} );
		syncMonitoringState();

		if ( backendForm ) {
			backendForm.addEventListener( 'submit', ( event ) => {
				const mode = selectedMonitoringMode();
				if ( 'generic_webhook' === mode ) {
					const webhookUrl = monitoringWebhookInput ? String( monitoringWebhookInput.value || '' ).trim() : '';
					if ( '' === webhookUrl ) {
						event.preventDefault();
						window.alert(
							text(
								'monitoring_webhook_required',
								'Enter a valid webhook URL or select a different monitoring mode before saving.'
							)
						);
						if ( monitoringWebhookInput ) {
							monitoringWebhookInput.focus();
						}
						return;
					}
				}

				if ( 'none' === mode ) {
					const confirmed = window.confirm(
						text(
							'monitoring_none_confirm',
							'External monitoring is disabled. This can increase security and compliance risk. Do you want to continue saving with No Monitoring selected?'
						)
					);
					if ( ! confirmed ) {
						event.preventDefault();
					}
				}
			} );
		}
	}

	const templateSelectors = document.querySelectorAll( '[data-uplinkportal-provider-template]' );
	const providerCatalogEndpoint = ( 'string' === typeof backendConfig.providers_endpoint ) ? backendConfig.providers_endpoint : '';
	const providerCatalogNonce = ( 'string' === typeof backendConfig.rest_nonce ) ? backendConfig.rest_nonce : '';
	const providerCatalogCache = {};
	const lockedProviderHelperText = text(
		'locked_provider_helper',
		'This provider is available via a Module add-on. Keep existing settings, but changes require an active add-on.'
	);
	const lockedProviderWarningText = text(
		'locked_provider_warning',
		"Provider is locked. Profile remains Draft and automations can't be enabled."
	);
	const graceProviderWarningTemplate = text(
		'grace_provider_warning',
		'Add-on in grace until %s. Existing automations continue during grace; profile stays Draft until reactivated.'
	);
	const formatGraceUntil = ( rawDate ) => {
		const value = String( rawDate || '' ).trim();
		if ( '' === value ) {
			return '';
		}

		const parsed = new Date( value.replace( ' ', 'T' ) + 'Z' );
		if ( Number.isNaN( parsed.getTime() ) ) {
			return value;
		}

		return parsed.toISOString().replace( 'T', ' ' ).slice( 0, 16 ) + ' UTC';
	};
	const buildGraceWarningText = ( rawDate ) => {
		const formatted = formatGraceUntil( rawDate );
		if ( '' === formatted ) {
			return '';
		}

		return graceProviderWarningTemplate.replace( '%s', formatted );
	};
	const secretManagerSelect = document.querySelector( '[data-uplinkportal-secret-manager-provider]' );
	const secretManagerLegacyInput = document.querySelector( 'input[name="uplinkportal_settings[secret_manager][provider]"]' );
	const vaultConfigWrap = document.querySelector( '[data-uplinkportal-vault-config-wrap]' );
	if ( vaultConfigWrap && secretManagerSelect ) {
		const syncVaultState = () => {
			const selectedOption = secretManagerSelect.options[ secretManagerSelect.selectedIndex ] || null;
			const providerMode = String( secretManagerSelect.value || '' );
			const enabled = ( 'vault' === providerMode );
			vaultConfigWrap.hidden = ! enabled;

			const vaultInputs = vaultConfigWrap.querySelectorAll( '[data-uplinkportal-vault-input]' );
			vaultInputs.forEach( ( input ) => {
				input.disabled = ! enabled;
			} );

			if ( secretManagerLegacyInput ) {
				secretManagerLegacyInput.value = enabled ? 'vault' : 'none';
			}

			const helperNode = document.querySelector( '[data-uplinkportal-secret-provider-helper]' );
			const warningNode = document.querySelector( '[data-uplinkportal-secret-locked-provider-warning]' );
			const graceNode = document.querySelector( '[data-uplinkportal-secret-grace-provider-warning]' );
			const isLocked = !! ( selectedOption && '1' === String( selectedOption.getAttribute( 'data-provider-locked' ) || '' ) );
			const helperText = isLocked && selectedOption
				? (
					String( selectedOption.getAttribute( 'data-helper-text' ) || '' )
					|| lockedProviderHelperText
				)
				: '';

			if ( helperNode ) {
				helperNode.hidden = '' === helperText;
				helperNode.textContent = helperText;
			}

			if ( warningNode ) {
				warningNode.hidden = ! isLocked;
				const warningCopy = warningNode.querySelector( 'p' );
				if ( warningCopy ) {
					warningCopy.textContent = lockedProviderWarningText;
				}
			}

			if ( graceNode ) {
				const graceUntil = selectedOption ? String( selectedOption.getAttribute( 'data-grace-until' ) || '' ) : '';
				const graceCopy = graceNode.querySelector( 'p' );
				const graceText = buildGraceWarningText( graceUntil );
				graceNode.hidden = '' === graceText;
				if ( graceCopy ) {
					graceCopy.textContent = graceText;
				}
			}
		};

		secretManagerSelect.addEventListener( 'change', syncVaultState );
		syncVaultState();
	}

	const getProfileAuthType = ( card ) => {
		if ( ! card ) {
			return '';
		}
		const checked = card.querySelector( '[data-uplinkportal-profile-auth]:checked' );
		return checked ? String( checked.value || '' ) : '';
	};
	const setProfileAuthType = ( card, rawAuthType ) => {
		if ( ! card ) {
			return;
		}
		const normalizedRaw = String( rawAuthType || '' ).toLowerCase();
		let normalized = 'api_key';
		if ( normalizedRaw.indexOf( 'oauth' ) !== -1 ) {
			normalized = 'oauth';
		} else if ( normalizedRaw === 'hybrid' ) {
			normalized = 'hybrid';
		}

		const targets = card.querySelectorAll( '[data-uplinkportal-profile-auth]' );
		targets.forEach( ( input ) => {
			input.checked = ( input.value === normalized );
		} );
	};
	const syncProfileConnectionState = ( card ) => {
		if ( ! card ) {
			return;
		}
		const providerInput = card.querySelector( '[data-uplinkportal-provider-template]' );
		const connectionWrap = card.querySelector( '[data-uplinkportal-profile-connection]' );
		if ( ! providerInput || ! connectionWrap ) {
			return;
		}
		const authType = getProfileAuthType( card );
		const shouldShow = ( 'custom' === String( providerInput.value || '' ) && 'api_key' === authType );
		connectionWrap.hidden = ! shouldShow;
	};
	const getSelectedProviderOption = ( card ) => {
		if ( ! card ) {
			return null;
		}
		const providerInput = card.querySelector( '[data-uplinkportal-provider-template]' );
		if ( ! providerInput ) {
			return null;
		}
		const index = providerInput.selectedIndex;
		if ( index < 0 ) {
			return null;
		}

		return providerInput.options[ index ] || null;
	};
	const syncLockedProfileState = ( card ) => {
		if ( ! card ) {
			return false;
		}
		const selectedOption = getSelectedProviderOption( card );
		const lifecycleState = card.querySelector( '[data-uplinkportal-profile-state]' );
		const helperNode = card.querySelector( '[data-uplinkportal-provider-helper]' );
		const warningNode = card.querySelector( '[data-uplinkportal-locked-provider-warning]' );
		const graceNode = card.querySelector( '[data-uplinkportal-grace-provider-warning]' );
		const isLocked = !! ( selectedOption && '1' === String( selectedOption.getAttribute( 'data-provider-locked' ) || '' ) );

		if ( lifecycleState ) {
			const draftOption = lifecycleState.querySelector( 'option[value="draft"]' );
			const reviewedOption = lifecycleState.querySelector( 'option[value="reviewed"]' );
			const activeOption = lifecycleState.querySelector( 'option[value="active"]' );
			if ( reviewedOption ) {
				reviewedOption.disabled = isLocked;
			}
			if ( activeOption ) {
				activeOption.disabled = isLocked;
			}
			if ( draftOption ) {
				draftOption.disabled = false;
			}
			if ( isLocked ) {
				lifecycleState.value = 'draft';
			}
		}

		const helperText = isLocked && selectedOption
			? ( String( selectedOption.getAttribute( 'data-helper-text' ) || '' ) || lockedProviderHelperText )
			: '';
		if ( helperNode ) {
			helperNode.hidden = '' === helperText;
			helperNode.textContent = helperText;
		}
		if ( warningNode ) {
			warningNode.hidden = ! isLocked;
			const warningCopy = warningNode.querySelector( 'p' );
			if ( warningCopy ) {
				warningCopy.textContent = lockedProviderWarningText;
			}
		}
		if ( graceNode ) {
			const graceUntil = selectedOption ? String( selectedOption.getAttribute( 'data-grace-until' ) || '' ) : '';
			const graceCopy = graceNode.querySelector( 'p' );
			const graceText = buildGraceWarningText( graceUntil );
			graceNode.hidden = '' === graceText;
			if ( graceCopy ) {
				graceCopy.textContent = graceText;
			}
		}

		return isLocked;
	};
	const syncFileStorageProfileState = () => {
		const documentCard = document.querySelector( '[data-uplinkportal-model="documents"]' );
		if ( ! documentCard ) {
			return;
		}

		const providerInput = documentCard.querySelector( '[data-uplinkportal-provider-template]' );
		if ( ! providerInput ) {
			return;
		}

		const isPrivateBackendStorage = 'private_backend_storage' === String( providerInput.value || '' );
		const endpointFields = documentCard.querySelectorAll( '[data-uplinkportal-endpoint-field]' );
		endpointFields.forEach( ( field ) => {
			field.hidden = isPrivateBackendStorage;
		} );

		const secureStoragePanel = document.querySelector( '[data-uplinkportal-secure-storage-panel]' );
		if ( secureStoragePanel ) {
			secureStoragePanel.hidden = ! isPrivateBackendStorage;
		}
	};
	const syncLockedAutomationState = () => {
		const lockedModels = {};

		document.querySelectorAll( '[data-uplinkportal-profile-card]' ).forEach( ( card ) => {
			const modelId = String( card.getAttribute( 'data-uplinkportal-model' ) || '' );
			if ( '' === modelId ) {
				return;
			}
			if ( syncLockedProfileState( card ) ) {
				lockedModels[ modelId ] = true;
			}
		} );

		document.querySelectorAll( '[data-uplinkportal-automation-lock-callout]' ).forEach( ( callout ) => {
			const modelId = String( callout.getAttribute( 'data-uplinkportal-automation-lock-callout' ) || '' );
			callout.hidden = ! lockedModels[ modelId ];
			const copy = callout.querySelector( 'p' );
			if ( copy ) {
				copy.textContent = lockedProviderWarningText;
			}
		} );

		document.querySelectorAll( '[data-uplinkportal-recipe-enabled]' ).forEach( ( checkbox ) => {
			const recipeRow = checkbox.closest( '[data-uplinkportal-recipe-model]' );
			if ( ! recipeRow ) {
				return;
			}
			const modelId = String( recipeRow.getAttribute( 'data-uplinkportal-recipe-model' ) || '' );
			const isLockedModel = !! lockedModels[ modelId ];
			checkbox.disabled = isLockedModel;
			if ( isLockedModel ) {
				checkbox.checked = false;
			}
		} );
	};
	const populateProviderCatalogForCard = ( card, providers ) => {
		if ( ! card || ! Array.isArray( providers ) ) {
			return;
		}
		const providerInput = card.querySelector( '[data-uplinkportal-provider-template]' );
		if ( ! providerInput ) {
			return;
		}

		const currentValue = String( providerInput.value || '' );
		const existingCustomOption = Array.from( providerInput.options ).find( ( option ) => 'custom' === option.value );
		let hasCurrentValue = false;

		providerInput.innerHTML = '';

		providers.forEach( ( provider ) => {
			if ( ! provider || 'object' !== typeof provider ) {
				return;
			}

			const providerId = String( provider.id || '' );
			const providerLabel = String( provider.label || providerId );
			if ( '' === providerId ) {
				return;
			}

			const rawTypes = Array.isArray( provider.auth_types ) ? provider.auth_types : [];
			const authTypes = rawTypes.map( ( type ) => String( type || '' ) ).filter( ( type ) => '' !== type );
			const primaryAuthType = authTypes.length ? authTypes[ 0 ] : 'api_key';
			const defaults = ( provider.defaults && 'object' === typeof provider.defaults ) ? provider.defaults : {};
			const scopes = Array.isArray( defaults.scopes ) ? defaults.scopes.map( ( scope ) => String( scope || '' ) ).filter( ( scope ) => '' !== scope ) : [];
			const endpointAlias = String( defaults.endpoint_alias || '' );
			const retentionPolicy = String( defaults.retention_policy || '' );
			const locked = !! provider.locked;
			const helperText = String( provider.helper_text || '' ) || lockedProviderHelperText;
			const graceUntil = provider.grace_info && 'object' === typeof provider.grace_info
				? String( provider.grace_info.grace_until || '' )
				: '';

			const option = document.createElement( 'option' );
			option.value = providerId;
			option.textContent = locked ? providerLabel + ' (Locked)' : providerLabel;
			option.setAttribute( 'data-auth-type', primaryAuthType );
			option.setAttribute( 'data-auth-types', authTypes.join( ',' ) );
			option.setAttribute( 'data-endpoint-alias', endpointAlias );
			option.setAttribute( 'data-retention-policy', retentionPolicy );
			option.setAttribute( 'data-scopes', scopes.join( ', ' ) );
			option.setAttribute( 'data-provider-locked', locked ? '1' : '0' );
			option.setAttribute( 'data-helper-text', helperText );
			option.setAttribute( 'data-grace-until', graceUntil );

			if ( locked && providerId !== currentValue ) {
				option.disabled = true;
			}
			if ( providerId === currentValue ) {
				hasCurrentValue = true;
			}
			providerInput.appendChild( option );
		} );

		if ( '' !== currentValue && 'custom' !== currentValue && ! hasCurrentValue ) {
			const recovered = document.createElement( 'option' );
			recovered.value = currentValue;
			recovered.textContent = currentValue + ' (Locked)';
			recovered.setAttribute( 'data-auth-type', 'api_key' );
			recovered.setAttribute( 'data-auth-types', 'api_key' );
			recovered.setAttribute( 'data-endpoint-alias', '' );
			recovered.setAttribute( 'data-retention-policy', 'default' );
			recovered.setAttribute( 'data-scopes', '' );
			recovered.setAttribute( 'data-provider-locked', '1' );
			recovered.setAttribute( 'data-helper-text', lockedProviderHelperText );
			recovered.setAttribute( 'data-grace-until', '' );
			providerInput.appendChild( recovered );
			hasCurrentValue = true;
		}

		const customOption = existingCustomOption ? existingCustomOption.cloneNode( true ) : document.createElement( 'option' );
		if ( ! existingCustomOption ) {
			customOption.value = 'custom';
			customOption.textContent = 'Custom Provider';
			customOption.setAttribute( 'data-auth-type', 'api_key' );
			customOption.setAttribute( 'data-auth-types', 'api_key' );
			customOption.setAttribute( 'data-endpoint-alias', '' );
			customOption.setAttribute( 'data-retention-policy', 'default' );
			customOption.setAttribute( 'data-scopes', '' );
			customOption.setAttribute( 'data-provider-locked', '0' );
			customOption.setAttribute( 'data-helper-text', '' );
			customOption.setAttribute( 'data-grace-until', '' );
		}
		providerInput.appendChild( customOption );

		if ( hasCurrentValue ) {
			providerInput.value = currentValue;
		} else if ( 'custom' === currentValue ) {
			providerInput.value = 'custom';
		}
	};
	const fetchProviderCatalogForCard = ( card ) => {
		if ( ! card || ! providerCatalogEndpoint || 'function' !== typeof window.fetch ) {
			return Promise.resolve();
		}
		const moduleSlug = String(
			card.getAttribute( 'data-uplinkportal-provider-module' )
			|| card.getAttribute( 'data-uplinkportal-model' )
			|| ''
		);
		const providerProfile = String(
			card.getAttribute( 'data-uplinkportal-provider-profile' )
			|| ''
		);
		if ( '' === moduleSlug ) {
			return Promise.resolve();
		}

		const contextClientId = String(
			card.getAttribute( 'data-uplinkportal-client-id' )
			|| backendConfig.entitlements_client_id
			|| ''
		);
		const cacheKey = moduleSlug + '::' + contextClientId + '::' + providerProfile;
		if ( providerCatalogCache[ cacheKey ] ) {
			populateProviderCatalogForCard( card, providerCatalogCache[ cacheKey ] );
			return Promise.resolve();
		}

		let requestUrl = providerCatalogEndpoint + '?module=' + encodeURIComponent( moduleSlug );
		if ( '' !== contextClientId ) {
			requestUrl += '&client_id=' + encodeURIComponent( contextClientId );
		}
		if ( '' !== providerProfile ) {
			requestUrl += '&profile=' + encodeURIComponent( providerProfile );
		}
		const requestHeaders = {};
		if ( providerCatalogNonce ) {
			requestHeaders[ 'X-WP-Nonce' ] = providerCatalogNonce;
		}

		return window.fetch( requestUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: requestHeaders,
		} ).then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( 'provider_catalog_failed' );
			}
			return response.json();
		} ).then( ( payload ) => {
			const providers = payload && Array.isArray( payload.providers ) ? payload.providers : null;
			if ( ! providers ) {
				throw new Error( 'provider_catalog_invalid' );
			}
			providerCatalogCache[ cacheKey ] = providers;
			populateProviderCatalogForCard( card, providers );
		} ).catch( () => {} );
	};

	const applyTemplateDefaults = ( select ) => {
		const card = select.closest( '[data-uplinkportal-profile-card]' );
		if ( ! card ) {
			return;
		}

		const customRow = card.querySelector( '[data-uplinkportal-provider-custom-row]' );
		const isCustom = ( 'custom' === select.value );
		if ( customRow ) {
			customRow.hidden = ! isCustom;
		}
		if ( isCustom ) {
			syncProfileConnectionState( card );
			syncLockedProfileState( card );
			syncFileStorageProfileState();
			return;
		}

		const selectedOption = select.options[ select.selectedIndex ];
		if ( ! selectedOption ) {
			return;
		}

		const aliasInput = card.querySelector( '[data-uplinkportal-profile-alias]' );
		const retentionInput = card.querySelector( '[data-uplinkportal-profile-retention]' );
		const scopesInput = card.querySelector( '[data-uplinkportal-profile-scopes]' );

		setProfileAuthType( card, selectedOption.getAttribute( 'data-auth-type' ) || '' );
		if ( aliasInput ) {
			aliasInput.value = selectedOption.getAttribute( 'data-endpoint-alias' ) || aliasInput.value;
		}
		if ( retentionInput ) {
			retentionInput.value = selectedOption.getAttribute( 'data-retention-policy' ) || retentionInput.value;
		}
		if ( scopesInput ) {
			scopesInput.value = selectedOption.getAttribute( 'data-scopes' ) || scopesInput.value;
		}
		syncProfileConnectionState( card );
		syncLockedProfileState( card );
		syncFileStorageProfileState();
	};

	if ( templateSelectors.length ) {
		templateSelectors.forEach( ( select ) => {
			select.addEventListener( 'change', () => {
				applyTemplateDefaults( select );
				syncLockedAutomationState();
			} );
			applyTemplateDefaults( select );
		} );
	}

	document.querySelectorAll( '[data-uplinkportal-profile-card]' ).forEach( ( card ) => {
		card.querySelectorAll( '[data-uplinkportal-profile-auth]' ).forEach( ( input ) => {
			input.addEventListener( 'change', () => syncProfileConnectionState( card ) );
		} );
		syncProfileConnectionState( card );
		fetchProviderCatalogForCard( card ).finally( () => {
			const select = card.querySelector( '[data-uplinkportal-provider-template]' );
			if ( select ) {
				applyTemplateDefaults( select );
			}
			syncLockedAutomationState();
			syncFileStorageProfileState();
		} );
	} );
	syncLockedAutomationState();
	syncFileStorageProfileState();

	document.querySelectorAll( '.uplinkportal-validation-allow-query' ).forEach( ( checkbox ) => {
		const row = checkbox.closest( 'label' );
		if ( ! row ) {
			return;
		}
		const hiddenInput = row.parentElement ? row.parentElement.querySelector( '[data-uplinkportal-validation-allow-query-hidden]' ) : null;
		if ( ! hiddenInput ) {
			return;
		}

		const syncAllowQueryHidden = () => {
			hiddenInput.value = checkbox.checked ? '1' : '0';
		};

		checkbox.addEventListener( 'change', syncAllowQueryHidden );
		syncAllowQueryHidden();
	} );

	const wizardRoot = document.querySelector( '[data-uplinkportal-integration-wizard]' );
	const wizardConfig = (
		( 'undefined' !== typeof UplinkPortalIntegrationWizard )
		&& UplinkPortalIntegrationWizard
		&& UplinkPortalIntegrationWizard.models
	) ? UplinkPortalIntegrationWizard : null;

	if ( ! wizardRoot || ! wizardConfig ) {
		return;
	}

	const modelSelect = wizardRoot.querySelector( '[data-uplinkportal-wizard-model]' );
	const providerSelect = wizardRoot.querySelector( '[data-uplinkportal-wizard-provider]' );
	const applyButton = wizardRoot.querySelector( '[data-uplinkportal-wizard-apply]' );
	const statusNode = wizardRoot.querySelector( '[data-uplinkportal-wizard-status]' );
	if ( ! modelSelect || ! providerSelect || ! applyButton ) {
		return;
	}

	const models = wizardConfig.models || {};
	const i18n = wizardConfig.i18n || {};

	const setStatus = ( message ) => {
		if ( statusNode ) {
			statusNode.textContent = message || '';
		}
	};

	const populateModels = () => {
		modelSelect.innerHTML = '';
		Object.keys( models ).forEach( ( modelId ) => {
			const option = document.createElement( 'option' );
			option.value = modelId;
			option.textContent = ( models[ modelId ] && models[ modelId ].label ) ? models[ modelId ].label : modelId;
			modelSelect.appendChild( option );
		} );
	};

	const populateProviders = () => {
		providerSelect.innerHTML = '';
		const selectedModel = modelSelect.value;
		const providerMap = ( models[ selectedModel ] && models[ selectedModel ].providers ) ? models[ selectedModel ].providers : {};
		Object.keys( providerMap ).forEach( ( providerId ) => {
			const provider = providerMap[ providerId ];
			const option = document.createElement( 'option' );
			option.value = providerId;
			option.textContent = provider.label || providerId;
			providerSelect.appendChild( option );
		} );
		if ( ! providerSelect.options.length ) {
			setStatus( i18n.no_providers || '' );
		} else {
			setStatus( '' );
		}
	};

	const applyWizardSelection = () => {
		const selectedModel = modelSelect.value;
		const selectedProvider = providerSelect.value;
		if ( ! selectedModel || ! selectedProvider ) {
			setStatus( i18n.incomplete_selection || '' );
			return;
		}

		const profileCard = document.querySelector( '[data-uplinkportal-model="' + selectedModel + '"]' );
		if ( ! profileCard ) {
			setStatus( i18n.incomplete_selection || '' );
			return;
		}

		const profileProvider = profileCard.querySelector( '[data-uplinkportal-provider-template]' );
		if ( ! profileProvider ) {
			setStatus( i18n.incomplete_selection || '' );
			return;
		}

		profileProvider.value = selectedProvider;
		profileProvider.dispatchEvent( new Event( 'change' ) );
		profileCard.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		setStatus( i18n.applied || '' );
	};

	populateModels();
	if ( ! modelSelect.options.length ) {
		setStatus( i18n.no_models || '' );
		return;
	}
	populateProviders();

	modelSelect.addEventListener( 'change', populateProviders );
	applyButton.addEventListener( 'click', applyWizardSelection );
}() );
