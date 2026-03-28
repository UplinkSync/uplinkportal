/* global window, document, wp */
( function () {
	'use strict';

	const config = window.UplinkPortalAdminConfig || {};
	const i18n = config.i18n || {};
	const text = ( key, fallback ) => {
		if ( 'string' === typeof i18n[ key ] && '' !== i18n[ key ] ) {
			return i18n[ key ];
		}
		return fallback;
	};

	const closeAllModals = () => {
		document.querySelectorAll( '.uplinkportal-override-modal:not([hidden]), .uplinkportal-plan-modal:not([hidden])' ).forEach( function ( modal ) {
			modal.hidden = true;
		} );
		document.body.classList.remove( 'uplinkportal-modal-open' );
	};

	const toggleBodyModalState = () => {
		const hasOpenModal = document.querySelector( '.uplinkportal-override-modal:not([hidden]), .uplinkportal-plan-modal:not([hidden])' );
		document.body.classList.toggle( 'uplinkportal-modal-open', !! hasOpenModal );
	};

	const closeModal = function ( modal ) {
		if ( ! modal ) {
			return;
		}
		modal.hidden = true;
		toggleBodyModalState();
	};

	const bindOverrideModals = () => {
		const resetModalTabs = ( modal ) => {
			if ( ! modal ) {
				return;
			}

			const firstWindowTab = modal.querySelector( '[data-uplinkportal-window-tab]' );
			if ( firstWindowTab ) {
				firstWindowTab.click();
			}

			const firstProfileTab = modal.querySelector( '[data-uplinkportal-profile-tab]' );
			if ( firstProfileTab ) {
				firstProfileTab.click();
			}
		};

		document.querySelectorAll( '.uplinkportal-open-override[data-override-target]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				const targetId = button.getAttribute( 'data-override-target' );
				if ( ! targetId ) {
					return;
				}
				const modal = document.getElementById( targetId );
				if ( ! modal ) {
					return;
				}
				closeAllModals();
				modal.hidden = false;
				resetModalTabs( modal );
				toggleBodyModalState();
			} );
		} );

		document.querySelectorAll( '.uplinkportal-close-override' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				closeModal( button.closest( '.uplinkportal-override-modal' ) );
			} );
		} );

		document.querySelectorAll( '.uplinkportal-override-modal' ).forEach( function ( modal ) {
			modal.addEventListener( 'click', function ( event ) {
				if ( event.target === modal ) {
					closeModal( modal );
				}
			} );
		} );
	};

	const bindServiceCatalog = () => {
		const planGrid = document.querySelector( '[data-uplinkportal-plan-grid]' );
		if ( ! planGrid ) {
			return;
		}

		const iconMap = {
			portfolio: 'dashicons-portfolio',
			'chart-line': 'dashicons-chart-line',
			building: 'dashicons-building',
			shield: 'dashicons-shield',
			cloud: 'dashicons-cloud',
			'admin-generic': 'dashicons-admin-generic',
		};

		const openPlanModal = ( planKey ) => {
			const modal = document.getElementById( 'uplinkportal-plan-modal-' + planKey );
			if ( ! modal ) {
				return;
			}
			closeAllModals();
			modal.hidden = false;
			toggleBodyModalState();
		};

		const updatePlanPreview = ( shell ) => {
			if ( ! shell ) {
				return;
			}

			const titleField = shell.querySelector( '[data-plan-title-field]' );
			const descField = shell.querySelector( '[data-plan-description-field]' );
			const iconField = shell.querySelector( '[data-plan-icon-field]' );
			const moduleChecks = shell.querySelectorAll( '[data-plan-module-checkbox]' );
			const removeCheck = shell.querySelector( '[data-plan-remove-checkbox]' );
			const titlePreview = shell.querySelector( '[data-plan-title-preview]' );
			const descPreview = shell.querySelector( '[data-plan-description-preview]' );
			const iconPreview = shell.querySelector( '[data-plan-icon-preview]' );
			const countPreview = shell.querySelector( '[data-plan-module-count]' );

			const titleValue = titleField && titleField.value.trim() ? titleField.value.trim() : text( 'untitled_plan', 'Untitled Plan' );
			const descValue = descField && descField.value.trim() ? descField.value.trim() : text( 'plan_default_desc', 'No short description set for this plan yet.' );
			const iconValue = iconField && iconField.value ? iconField.value : 'portfolio';

			let moduleCount = 0;
			moduleChecks.forEach( function ( checkbox ) {
				if ( checkbox.checked ) {
					moduleCount++;
				}
			} );

			if ( titlePreview ) {
				titlePreview.textContent = titleValue;
			}
			if ( descPreview ) {
				descPreview.textContent = descValue;
			}
			if ( countPreview ) {
				countPreview.textContent = String( moduleCount );
			}
			if ( iconPreview ) {
				const iconClass = iconMap[ iconValue ] || iconMap.portfolio;
				iconPreview.className = 'dashicons ' + iconClass;
			}

			shell.classList.toggle( 'is-marked-remove', !! ( removeCheck && removeCheck.checked ) );
		};

		const bindPlanShell = ( shell ) => {
			if ( ! shell || shell.dataset.planShellBound ) {
				return;
			}
			shell.dataset.planShellBound = '1';

			const planKey = shell.getAttribute( 'data-plan-shell' );
			if ( ! planKey ) {
				return;
			}

			shell.querySelectorAll( '[data-uplinkportal-open-plan-modal]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					openPlanModal( planKey );
				} );
			} );

			shell.querySelectorAll( '[data-uplinkportal-close-plan-modal]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					closeModal( button.closest( '.uplinkportal-plan-modal' ) );
				} );
			} );

			const modal = shell.querySelector( '.uplinkportal-plan-modal' );
			if ( modal ) {
				modal.addEventListener( 'click', function ( event ) {
					if ( event.target === modal ) {
						closeModal( modal );
					}
				} );
			}

			shell.querySelectorAll( '[data-plan-title-field], [data-plan-description-field], [data-plan-icon-field], [data-plan-module-checkbox], [data-plan-remove-checkbox]' ).forEach( function ( field ) {
				field.addEventListener( 'input', function () {
					updatePlanPreview( shell );
				} );
				field.addEventListener( 'change', function () {
					updatePlanPreview( shell );
				} );
			} );

			updatePlanPreview( shell );
		};

		planGrid.querySelectorAll( '.uplinkportal-plan-shell' ).forEach( bindPlanShell );

		const addPlanButton = planGrid.querySelector( '[data-uplinkportal-add-plan-card]' );
		if ( addPlanButton ) {
			addPlanButton.addEventListener( 'click', function () {
				const firstShell = planGrid.querySelector( '.uplinkportal-plan-shell' );
				if ( ! firstShell ) {
					return;
				}

				const sourceKey = firstShell.getAttribute( 'data-plan-shell' );
				if ( ! sourceKey ) {
					return;
				}

				const newKey = 'plan_' + Date.now();
				const clone = firstShell.cloneNode( true );

				clone.setAttribute( 'data-plan-shell', newKey );
				clone.removeAttribute( 'data-plan-shell-bound' );

				clone.querySelectorAll( '[name]' ).forEach( function ( input ) {
					const currentName = input.getAttribute( 'name' );
					if ( ! currentName ) {
						return;
					}
					input.setAttribute( 'name', currentName.replace( /plan_templates\[[^\]]+\]/, 'plan_templates[' + newKey + ']' ) );
				} );

				clone.querySelectorAll( '[id]' ).forEach( function ( element ) {
					const currentId = element.getAttribute( 'id' );
					if ( ! currentId ) {
						return;
					}
					element.setAttribute( 'id', currentId.replace( sourceKey, newKey ) );
				} );

				clone.querySelectorAll( '[data-uplinkportal-open-plan-modal]' ).forEach( function ( button ) {
					button.setAttribute( 'data-uplinkportal-open-plan-modal', newKey );
				} );
				clone.querySelectorAll( '[data-uplinkportal-close-plan-modal]' ).forEach( function ( button ) {
					button.setAttribute( 'data-uplinkportal-close-plan-modal', newKey );
				} );
				clone.querySelectorAll( '[data-plan-card]' ).forEach( function ( card ) {
					card.setAttribute( 'data-plan-card', newKey );
				} );

				const keyField = clone.querySelector( 'input[name*="[key]"]' );
				if ( keyField ) {
					keyField.value = newKey;
				}

				const readonlyKey = clone.querySelector( '.uplinkportal-plan-dialog input[readonly]' );
				if ( readonlyKey ) {
					readonlyKey.value = newKey;
				}

				const titleField = clone.querySelector( '[data-plan-title-field]' );
				if ( titleField ) {
					titleField.value = text( 'new_plan_name', 'New Plan' );
				}

				const descField = clone.querySelector( '[data-plan-description-field]' );
				if ( descField ) {
					descField.value = '';
				}

				const iconField = clone.querySelector( '[data-plan-icon-field]' );
				if ( iconField ) {
					iconField.value = 'portfolio';
				}

				clone.querySelectorAll( '[data-plan-module-checkbox], [data-plan-remove-checkbox]' ).forEach( function ( checkbox ) {
					checkbox.checked = false;
				} );

				clone.querySelectorAll( '.uplinkportal-plan-modal' ).forEach( function ( modal ) {
					modal.hidden = true;
				} );

				planGrid.insertBefore( clone, addPlanButton );
				bindPlanShell( clone );
				openPlanModal( newKey );
			} );
		}
	};

		const bindDocumentBrowser = () => {
			document.querySelectorAll( '[data-uplinkportal-doc-browser]' ).forEach( function ( browser ) {
				const folderView = browser.querySelector( '[data-uplinkportal-folder-view]' );
				const workspace = browser.querySelector( '[data-uplinkportal-doc-workspace]' );
				const backButton = browser.querySelector( '[data-uplinkportal-doc-back]' );
				const folderTitle = browser.querySelector( '[data-uplinkportal-folder-title]' );
				const previewTitle = browser.querySelector( '[data-uplinkportal-doc-preview-title]' );
				const previewType = browser.querySelector( '[data-uplinkportal-doc-preview-type]' );
				const previewStatus = browser.querySelector( '[data-uplinkportal-doc-preview-status]' );
				const previewUpdated = browser.querySelector( '[data-uplinkportal-doc-preview-updated]' );
				const previewSummary = browser.querySelector( '[data-uplinkportal-doc-preview-summary]' );
				const previewFrameWrap = browser.querySelector( '[data-uplinkportal-doc-preview-frame-wrap]' );
				const previewFrame = browser.querySelector( '[data-uplinkportal-doc-preview-frame]' );
				const previewFallback = browser.querySelector( '[data-uplinkportal-doc-preview-fallback]' );
				const previewOpen = browser.querySelector( '[data-uplinkportal-doc-preview-open]' );
				const clientInputs = browser.querySelectorAll( '[data-uplinkportal-doc-client]' );
				const uploadForm = browser.querySelector( '.uplinkportal-doc-upload-form' );
				const uploadButton = uploadForm ? uploadForm.querySelector( 'button[type="submit"]' ) : null;
				const uploadFile = uploadForm ? uploadForm.querySelector( 'input[type="file"]' ) : null;
				const deleteButton = browser.querySelector( '[data-uplinkportal-doc-delete-button]' );

				const setBrowserMode = ( showWorkspace ) => {
					if ( folderView ) {
						folderView.hidden = !! showWorkspace;
						folderView.classList.toggle( 'uplinkportal-is-hidden', !! showWorkspace );
					}
					if ( workspace ) {
						workspace.hidden = ! showWorkspace;
						workspace.classList.toggle( 'uplinkportal-is-hidden', ! showWorkspace );
					}
				};

				const hideAllFolderLists = () => {
					browser.querySelectorAll( '[data-folder-list]' ).forEach( function ( list ) {
						list.hidden = true;
						list.classList.add( 'uplinkportal-is-hidden' );
					} );
				};

				const setUploadClient = ( clientId ) => {
					clientInputs.forEach( function ( input ) {
						input.value = clientId ? String( clientId ) : '';
					} );
					if ( uploadButton ) {
						uploadButton.disabled = ! clientId;
					}
					if ( deleteButton ) {
						deleteButton.disabled = ! clientId;
					}
					if ( uploadFile && ! clientId ) {
						uploadFile.value = '';
					}
				};

				const setPreviewState = ( payload ) => {
					const safePayload = payload || {};
					const docUrl = String( safePayload.url || '' ).trim();
					const fallbackMessage = String(
						safePayload.fallbackMessage || text( 'doc_preview_unavailable', 'Preview unavailable for this file. Open it in a new tab.' )
					);

					if ( previewTitle ) {
						previewTitle.textContent = String( safePayload.title || '' );
					}
					if ( previewType ) {
						previewType.textContent = String( safePayload.type || '' );
					}
					if ( previewStatus ) {
						previewStatus.textContent = String( safePayload.status || '' );
					}
					if ( previewUpdated ) {
						previewUpdated.textContent = String( safePayload.updated || '' );
					}
					if ( previewSummary ) {
						previewSummary.textContent = String( safePayload.summary || '' );
					}

					const hasPreviewUrl = docUrl !== '';
					if ( previewFrameWrap ) {
						previewFrameWrap.hidden = ! hasPreviewUrl;
						previewFrameWrap.classList.toggle( 'uplinkportal-is-hidden', ! hasPreviewUrl );
					}
					if ( previewFrame ) {
						if ( hasPreviewUrl ) {
							if ( previewFrame.getAttribute( 'src' ) !== docUrl ) {
								previewFrame.setAttribute( 'src', docUrl );
							}
						} else {
							previewFrame.setAttribute( 'src', '' );
						}
					}
					if ( previewFallback ) {
						previewFallback.hidden = hasPreviewUrl;
						if ( ! hasPreviewUrl ) {
							previewFallback.textContent = fallbackMessage;
						}
					}
					if ( previewOpen ) {
						previewOpen.hidden = ! hasPreviewUrl;
						previewOpen.setAttribute( 'href', hasPreviewUrl ? docUrl : '#' );
					}
				};

				const selectDocument = ( button ) => {
					if ( ! button ) {
						return;
					}

					const list = button.closest( '.uplinkportal-doc-list' );
					if ( list ) {
						list.querySelectorAll( '.uplinkportal-doc-item' ).forEach( function ( item ) {
							item.classList.remove( 'is-active' );
						} );
					}
					button.classList.add( 'is-active' );

					setPreviewState( {
						title: button.getAttribute( 'data-doc-title' ) || '',
						type: button.getAttribute( 'data-doc-type' ) || '',
						status: button.getAttribute( 'data-doc-status' ) || '',
						updated: button.getAttribute( 'data-doc-updated' ) || '',
						summary: button.getAttribute( 'data-doc-summary' ) || '',
						url: button.getAttribute( 'data-doc-url' ) || '',
					} );
				};

				const setEmptyPreviewState = ( emptySummary ) => {
					setPreviewState( {
						title: text( 'select_document', 'Select a document' ),
						type: '',
						status: '',
						updated: '',
						summary: emptySummary,
						url: '',
						fallbackMessage: emptySummary,
					} );
				};

				const selectFolder = ( folderId, folderLabel ) => {
					setBrowserMode( true );
					if ( folderTitle ) {
						folderTitle.textContent = folderLabel || '';
					}

					browser.querySelectorAll( '.uplinkportal-folder-button' ).forEach( function ( button ) {
						button.classList.toggle( 'is-active', button.getAttribute( 'data-folder-target' ) === folderId );
					} );

					hideAllFolderLists();
					let activeList = null;
					browser.querySelectorAll( '[data-folder-list]' ).forEach( function ( list ) {
						const isTarget = list.getAttribute( 'data-folder-list' ) === folderId;
						list.hidden = ! isTarget;
						list.classList.toggle( 'uplinkportal-is-hidden', ! isTarget );
						if ( isTarget ) {
							activeList = list;
						}
					} );

					if ( activeList ) {
						const clientId = activeList.getAttribute( 'data-client-id' ) || '';
						setUploadClient( clientId );
						const firstDoc = activeList.querySelector( '.uplinkportal-doc-item' );
						if ( firstDoc ) {
							selectDocument( firstDoc );
						} else {
							setEmptyPreviewState( text( 'folder_empty', 'No records found in this folder.' ) );
						}
					} else {
						setUploadClient( '' );
					}
				};

				browser.querySelectorAll( '.uplinkportal-folder-button' ).forEach( function ( button ) {
					button.addEventListener( 'click', function () {
						const folderId = button.getAttribute( 'data-folder-target' );
						if ( ! folderId ) {
							return;
						}
						selectFolder( folderId, button.textContent ? button.textContent.trim() : '' );
					} );
				} );

				browser.querySelectorAll( '.uplinkportal-doc-item' ).forEach( function ( button ) {
					button.addEventListener( 'click', function () {
						selectDocument( button );
					} );
				} );

				if ( backButton ) {
					backButton.addEventListener( 'click', function () {
						setBrowserMode( false );
						hideAllFolderLists();
						setUploadClient( '' );
						browser.querySelectorAll( '.uplinkportal-folder-button' ).forEach( function ( button ) {
							button.classList.remove( 'is-active' );
						} );
						setEmptyPreviewState( text( 'doc_preview_placeholder', 'Choose a record to view its summary preview.' ) );
					} );
				}

				setBrowserMode( false );
				hideAllFolderLists();
				setUploadClient( '' );
				setEmptyPreviewState( text( 'doc_preview_placeholder', 'Choose a record to view its summary preview.' ) );
			} );
		};

	const bindCustomizationTab = () => {
		const customization = document.querySelector( '[data-uplinkportal-customization]' );
		if ( ! customization ) {
			return;
		}

		const form = customization.closest( 'form' );
		const trigger = document.getElementById( 'uplinkportal-default-logo-change' );
		const logoInput = document.getElementById( 'uplinkportal-default-company-logo-id' );
		const logoPreview = document.getElementById( 'uplinkportal-default-logo-preview' );
		const countryInput = customization.querySelector( '[data-uplinkportal-country]' );
		const usStateWrap = customization.querySelector( '[data-uplinkportal-us-state-wrap]' );
		const usStateSelect = customization.querySelector( '[data-uplinkportal-us-state]' );
		const regionWrap = customization.querySelector( '[data-uplinkportal-region-wrap]' );
		const regionInput = customization.querySelector( '[data-uplinkportal-region]' );
		const customSmtp = customization.querySelector( '[data-uplinkportal-custom-smtp]' );
		const methodRadios = customization.querySelectorAll( 'input[name="email_delivery_method"]' );

		const normalizeCountry = ( value ) => String( value || '' ).trim().toUpperCase();
		const isUsCountry = ( value ) => [ 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' ].includes( normalizeCountry( value ) );

		const syncCountryFields = () => {
			if ( ! countryInput || ! usStateWrap || ! usStateSelect || ! regionWrap || ! regionInput ) {
				return;
			}

			const isUs = isUsCountry( countryInput.value );
			usStateWrap.hidden = ! isUs;
			regionWrap.hidden = isUs;
			usStateSelect.disabled = ! isUs;
			regionInput.disabled = isUs;
		};

		const selectedEmailMethod = () => {
			let selected = 'wordpress_mail';
			methodRadios.forEach( function ( radio ) {
				if ( radio.checked ) {
					selected = radio.value;
				}
			} );
			return selected;
		};

		const syncEmailFields = () => {
			if ( ! customSmtp ) {
				return;
			}
			const showCustom = selectedEmailMethod() === 'custom_smtp';
			customSmtp.hidden = ! showCustom;
		};

		const resetDayOptionVisibility = ( select ) => {
			Array.from( select.options ).forEach( function ( option ) {
				if ( ! option.value ) {
					return;
				}
				option.hidden = false;
				option.disabled = false;
			} );
		};

		const firstVisibleOptionValue = ( select ) => {
			const first = Array.from( select.options ).find( function ( option ) {
				return !! option.value && ! option.hidden && ! option.disabled;
			} );
			return first ? first.value : '';
		};

		const syncDayTimeOptions = ( dayRow ) => {
			const enabled = dayRow.querySelector( '[data-uplinkportal-hours-enabled]' );
			const open = dayRow.querySelector( '[data-uplinkportal-hours-open]' );
			const close = dayRow.querySelector( '[data-uplinkportal-hours-close]' );
			if ( ! enabled || ! open || ! close || ! enabled.checked ) {
				return;
			}

			resetDayOptionVisibility( open );
			resetDayOptionVisibility( close );

			const closeValue = String( close.value || '' );
			Array.from( open.options ).forEach( function ( option ) {
				if ( ! option.value ) {
					return;
				}
				const isValid = option.value < closeValue;
				option.hidden = ! isValid;
				option.disabled = ! isValid;
			} );

			if ( open.selectedOptions.length < 1 || open.selectedOptions[0].disabled || open.selectedOptions[0].hidden ) {
				const fallbackOpen = firstVisibleOptionValue( open );
				if ( fallbackOpen ) {
					open.value = fallbackOpen;
				}
			}

			const openValue = String( open.value || '' );
			Array.from( close.options ).forEach( function ( option ) {
				if ( ! option.value ) {
					return;
				}
				const isValid = option.value > openValue;
				option.hidden = ! isValid;
				option.disabled = ! isValid;
			} );

			if ( close.selectedOptions.length < 1 || close.selectedOptions[0].disabled || close.selectedOptions[0].hidden ) {
				const fallbackClose = firstVisibleOptionValue( close );
				if ( fallbackClose ) {
					close.value = fallbackClose;
				}
			}
		};

		const updateDayState = ( dayRow ) => {
			const enabled = dayRow.querySelector( '[data-uplinkportal-hours-enabled]' );
			const open = dayRow.querySelector( '[data-uplinkportal-hours-open]' );
			const close = dayRow.querySelector( '[data-uplinkportal-hours-close]' );
			const selects = dayRow.querySelector( '[data-uplinkportal-hours-selects]' );
			const dashes = dayRow.querySelector( '[data-uplinkportal-hours-dashes]' );
			if ( ! enabled || ! open || ! close || ! selects || ! dashes ) {
				return;
			}

			const isEnabled = enabled.checked;
			dayRow.classList.toggle( 'is-enabled', isEnabled );
			selects.hidden = ! isEnabled;
			dashes.hidden = isEnabled;
			selects.classList.toggle( 'uplinkportal-hours-state-hidden', ! isEnabled );
			dashes.classList.toggle( 'uplinkportal-hours-state-hidden', isEnabled );
			open.disabled = ! isEnabled;
			close.disabled = ! isEnabled;

			if ( isEnabled ) {
				syncDayTimeOptions( dayRow );
			} else {
				resetDayOptionVisibility( open );
				resetDayOptionVisibility( close );
			}
		};

		const validateDayRange = ( dayRow, showAlert ) => {
			const enabled = dayRow.querySelector( '[data-uplinkportal-hours-enabled]' );
			const open = dayRow.querySelector( '[data-uplinkportal-hours-open]' );
			const close = dayRow.querySelector( '[data-uplinkportal-hours-close]' );
			if ( ! enabled || ! open || ! close || ! enabled.checked ) {
				return true;
			}

			if ( String( open.value ) >= String( close.value ) ) {
				if ( showAlert ) {
					window.alert( text( 'hours_bad_range', 'Opening time must be earlier than closing time.' ) );
				}
				return false;
			}

			return true;
		};

		customization.querySelectorAll( '[data-uplinkportal-hours-day]' ).forEach( function ( dayRow ) {
			const enabled = dayRow.querySelector( '[data-uplinkportal-hours-enabled]' );
			const open = dayRow.querySelector( '[data-uplinkportal-hours-open]' );
			const close = dayRow.querySelector( '[data-uplinkportal-hours-close]' );
			if ( ! enabled || ! open || ! close ) {
				return;
			}

			open.dataset.previousValue = open.value;
			close.dataset.previousValue = close.value;
			updateDayState( dayRow );

			enabled.addEventListener( 'change', function () {
				updateDayState( dayRow );
			} );

			open.addEventListener( 'change', function () {
				syncDayTimeOptions( dayRow );
				if ( ! validateDayRange( dayRow, true ) ) {
					open.value = open.dataset.previousValue || open.value;
					syncDayTimeOptions( dayRow );
					return;
				}
				open.dataset.previousValue = open.value;
			} );

			close.addEventListener( 'change', function () {
				syncDayTimeOptions( dayRow );
				if ( ! validateDayRange( dayRow, true ) ) {
					close.value = close.dataset.previousValue || close.value;
					syncDayTimeOptions( dayRow );
					return;
				}
				close.dataset.previousValue = close.value;
			} );
		} );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				const submitter = event.submitter || null;
				const sectionKey = submitter && submitter.name === 'customization_section' ? String( submitter.value || '' ) : '';
				if ( sectionKey && sectionKey !== 'operating_hours' ) {
					return;
				}

				let valid = true;
				customization.querySelectorAll( '[data-uplinkportal-hours-day]' ).forEach( function ( dayRow ) {
					if ( ! validateDayRange( dayRow, false ) ) {
						valid = false;
					}
				} );

				if ( ! valid ) {
					event.preventDefault();
					window.alert( text( 'hours_bad_range', 'Opening time must be earlier than closing time.' ) );
				}
			} );
		}

		if ( countryInput ) {
			countryInput.addEventListener( 'change', syncCountryFields );
			countryInput.addEventListener( 'input', syncCountryFields );
		}
		syncCountryFields();

		methodRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', syncEmailFields );
		} );
		syncEmailFields();

		if ( ! trigger || ! logoInput || ! logoPreview || ! wp || ! wp.media ) {
			return;
		}

		let frame = null;
		const renderPreview = ( url ) => {
			if ( ! url ) {
				logoPreview.innerHTML = '<span>' + text( 'logo_no_selection', 'No logo selected yet.' ) + '</span>';
				return;
			}
			logoPreview.innerHTML = '<img src="' + url + '" alt="" />';
		};

		trigger.addEventListener( 'click', function () {
			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: text( 'logo_frame_title', 'Select Company Logo' ),
				button: {
					text: text( 'logo_frame_button', 'Use This Logo' ),
				},
				library: {
					type: [ 'image/png', 'image/svg+xml' ],
				},
				multiple: false,
			} );

			frame.on( 'select', function () {
				const selected = frame.state().get( 'selection' ).first();
				if ( ! selected ) {
					return;
				}

				const attachment = selected.toJSON();
				const mime = attachment && attachment.mime ? String( attachment.mime ) : '';
				const isPng = mime === 'image/png';
				const isSvg = mime === 'image/svg+xml';

				if ( ! isPng && ! isSvg ) {
					window.alert( text( 'logo_bad_type', 'Please choose a PNG or SVG logo.' ) );
					return;
				}

				const sizeBytes = attachment && attachment.filesizeInBytes ? Number( attachment.filesizeInBytes ) : 0;
				if ( sizeBytes > 204800 ) {
					window.alert( text( 'logo_bad_size', 'That image is too large. Please choose a file under 200KB.' ) );
					return;
				}

				const width = attachment && attachment.width ? Number( attachment.width ) : 0;
				const height = attachment && attachment.height ? Number( attachment.height ) : 0;
				if ( ( width > 0 && width > 200 ) || ( height > 0 && height > 200 ) ) {
					window.alert( text( 'logo_bad_dimensions', 'Logo dimensions cannot exceed 200x200 pixels.' ) );
					return;
				}

				logoInput.value = attachment && attachment.id ? String( attachment.id ) : '';
				renderPreview( attachment && attachment.url ? String( attachment.url ) : '' );
			} );

			frame.open();
		} );
	};

	const bindPortalPreviewDraft = () => {
		const previewRoot = document.querySelector( '[data-uplinkportal-portal-preview]' );
		if ( ! previewRoot ) {
			return;
		}

		const applyButton = previewRoot.querySelector( '[data-uplinkportal-preview-apply]' );
		const previewShell = previewRoot.querySelector( '[data-uplinkportal-preview-shell] #uplinkportal-root' );
		const colorField = previewRoot.querySelector( '[data-uplinkportal-preview-color]' );
		const welcomeField = previewRoot.querySelector( '[data-uplinkportal-preview-welcome-input]' );
		const footerField = previewRoot.querySelector( '[data-uplinkportal-preview-footer-input]' );

		if ( ! applyButton || ! previewShell ) {
			return;
		}

			const applyDraft = () => {
				if ( colorField ) {
					previewShell.style.setProperty( '--uplinkportal-brand', String( colorField.value || '#1d4ed8' ) );
				}

			const welcomeTarget = previewShell.querySelector( '[data-uplinkportal-preview-welcome]' );
			if ( welcomeField && welcomeTarget ) {
				welcomeTarget.textContent = String( welcomeField.value || '' ).trim() || text( 'preview_welcome_fallback', 'Welcome to your unified MSP portal.' );
			}

			const footerTarget = previewShell.querySelector( '[data-uplinkportal-preview-footer]' );
			if ( footerField && footerTarget ) {
				const footerText = String( footerField.value || '' ).trim();
				footerTarget.textContent = footerText;
				footerTarget.hidden = footerText === '';
			}
		};

		applyButton.addEventListener( 'click', function () {
			applyDraft();
		} );
	};

	const bindPlanAccordions = () => {
		document.querySelectorAll( '[data-uplinkportal-accordion-trigger]' ).forEach( function ( trigger ) {
			const panelId = trigger.getAttribute( 'aria-controls' );
			if ( ! panelId ) {
				return;
			}
			const panel = document.getElementById( panelId );
			if ( ! panel ) {
				return;
			}

			trigger.addEventListener( 'click', function () {
				const expanded = trigger.getAttribute( 'aria-expanded' ) === 'true';
				trigger.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				panel.hidden = expanded;
			} );
		} );
	};

	const bindMatrixSearch = () => {
		const search = document.querySelector( '[data-uplinkportal-matrix-search]' );
		if ( ! search ) {
			return;
		}
		const table = search.closest( 'form' ) ? search.closest( 'form' ).querySelector( '.uplinkportal-matrix-table' ) : null;
		if ( ! table ) {
			return;
		}
		const matrixRows = table.querySelectorAll( 'tbody tr' );
		search.addEventListener( 'input', function () {
			const query = search.value.trim().toLowerCase();
			matrixRows.forEach( function ( row ) {
				const nameCell = row.querySelector( 'td:first-child' );
				if ( ! nameCell ) {
					return;
				}
				const match = nameCell.textContent.trim().toLowerCase().includes( query );
				row.hidden = ! match;
			} );
		} );
	};

	const bindPreviewRoleSelector = () => {
		const roleSelect = document.querySelector( '[data-uplinkportal-preview-role]' );
		const iframe = document.querySelector( '[data-uplinkportal-preview-iframe]' );
		if ( ! roleSelect || ! iframe ) {
			return;
		}
		const baseUrl = roleSelect.getAttribute( 'data-preview-base-url' ) || '';
		roleSelect.addEventListener( 'change', function () {
			const role = roleSelect.value;
			const url = role ? baseUrl + '&uplinkportal_preview_role=' + encodeURIComponent( role ) : baseUrl;
			iframe.setAttribute( 'src', url );
		} );
	};

	const bindPreviewDeviceSwitcher = () => {
		const shell = document.querySelector( '[data-uplinkportal-preview-iframe]' );
		if ( ! shell ) {
			return;
		}
		const shellWrap = shell.closest( '.uplinkportal-preview-iframe-shell' ) || shell.parentElement;
		const deviceBtns = document.querySelectorAll( '[data-uplinkportal-preview-device]' );
		deviceBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const device = btn.getAttribute( 'data-uplinkportal-preview-device' );
				deviceBtns.forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
				if ( shellWrap ) {
					shellWrap.setAttribute( 'data-uplinkportal-device', device || 'desktop' );
				}
			} );
		} );
	};

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		closeAllModals();
	} );

	closeAllModals();
	bindOverrideModals();
	bindServiceCatalog();
	bindDocumentBrowser();
	bindCustomizationTab();
	bindPortalPreviewDraft();
	bindPlanAccordions();
	bindMatrixSearch();
	bindPreviewRoleSelector();
	bindPreviewDeviceSwitcher();
}() );
