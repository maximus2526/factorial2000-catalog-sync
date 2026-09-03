(function ($) {
	'use strict';

	if (typeof f2000csImport === 'undefined') {
		return;
	}

	var i18n = f2000csImport.i18n || {};
	var stopImport = false;
	var groupsData = {};
	var importSession = '';

	function t(key) {
		return i18n[key] || '';
	}

	function getImportSource() {
		return $('input[name="import_source"]:checked').val() || 'url';
	}

	function toggleImportSource(source) {
		var isUrl = source === 'url';

		$('#import-source-url-row').prop('hidden', !isUrl).toggleClass('is-hidden', !isUrl);
		$('#import-source-file-row').prop('hidden', isUrl).toggleClass('is-hidden', isUrl);

		if (isUrl) {
			$('#import_xml_file').val('');
		}

		importSession = '';
	}

	function switchImportTab(tab) {
		$('.f2000cs-import-tabs__tab').each(function () {
			var isActive = $(this).data('f2000cs-tab') === tab;
			$(this).toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
		});

		$('[data-f2000cs-panel]').each(function () {
			var isActive = $(this).data('f2000cs-panel') === tab;
			$(this).toggleClass('is-hidden', !isActive).prop('hidden', !isActive);
		});
	}

	function validateImportSource() {
		var source = getImportSource();

		if (source === 'url') {
			if (!$('#import_xml_url').val().trim()) {
				alert(t('enterUrl'));
				$('#import_xml_url').focus();
				return false;
			}
			return true;
		}

		var fileInput = $('#import_xml_file')[0];
		if (!fileInput || !fileInput.files.length) {
			alert(t('selectFile'));
			return false;
		}

		return true;
	}

	function setVisible( $el, visible ) {
		$el.toggleClass( 'is-hidden', ! visible ).prop( 'hidden', ! visible );
		if ( visible ) {
			$el.show();
		} else {
			$el.hide();
		}
	}

	function showImportProgress() {
		var $box = $( '#import-progress-container' );
		// Remove [hidden] fully — UA stylesheet uses display:none !important on it.
		$box
			.addClass( 'is-active' )
			.removeClass( 'is-complete is-hidden' )
			.removeAttr( 'hidden' )
			.prop( 'hidden', false )
			.css( 'display', '' );
		updateImportProgress( 0 );
		$( '#import-status' ).text( '' );
	}

	function updateImportProgress( percent ) {
		var value = Math.max( 0, Math.min( 100, Math.round( percent ) ) );
		$( '#import-progress' ).val( value );
		$( '#import-progress-percent' ).text( value + '%' );
		$( '#import-progress-container' ).toggleClass( 'is-complete', value >= 100 );
	}

	function toggleImportMode(mode) {
		var isVariable = mode === 'variable';

		setVisible( $( '#analyze-xml' ), isVariable );
		setVisible( $( '#start-import' ), ! isVariable );
		$( '#groups-analysis-container' ).hide();
		setVisible( $( '#start-import-with-selection' ), false );
	}

	function buildVaryingLabel(isVarying) {
		return isVarying ? ' ✓ (' + t('varyingYes') + ')' : ' ✗ (' + t('varyingNo') + ')';
	}

	function updateVariationsCount(groupId) {
		var group = groupsData[groupId];
		var $target = $('#calculated_count_' + groupId);

		if (!group || !group.selected_attributes || group.selected_attributes.length === 0) {
			$target.removeClass('f2000cs-group-calculated--warning').html('');
			return;
		}

		$target.removeClass('f2000cs-group-calculated--warning');

		var attrInfo = [];

		group.attributes.forEach(function (attr) {
			if (group.selected_attributes.includes(attr.name) && attr.is_varying) {
				attrInfo.push(attr.name + ': ' + attr.values.length);
			}
		});

		// Import creates one WC variation per offer in XML, not a cartesian product.
		var offersCount = parseInt(group.variations_count, 10) || 0;

		if (attrInfo.length > 0) {
			$target.html(
				'<strong>' +
					t('variationsWillCreate') +
					'</strong> ' +
					offersCount +
					' (' +
					attrInfo.join(', ') +
					')'
			);
		} else {
			$target
				.addClass('f2000cs-group-calculated--warning')
				.html('<strong>' + t('variationsWarning') + '</strong>');
		}
	}

	function displayGroups(groups) {
		var $container = $('#groups-list');
		$container.empty();

		Object.keys(groups).forEach(function (groupId) {
			var group = groups[groupId];
			var $groupBox = $('<div>').addClass('f2000cs-group-box');
			var $header = $('<div>').addClass('f2000cs-group-header');

			if (group.image) {
				$header.append(
					$('<img>')
						.addClass('f2000cs-group-image')
						.attr('src', group.image)
						.attr('alt', group.name || '')
				);
			}

			var $info = $('<div>');
			$info.append($('<h4>').addClass('f2000cs-group-title').text(group.name));
			$info.append(
				$('<p>')
					.addClass('f2000cs-group-meta')
					.append($('<strong>').text(t('groupId') + ' '))
					.append(document.createTextNode(String(groupId)))
			);

			var $variationsInfo = $('<p>')
				.addClass('f2000cs-group-variations')
				.attr('id', 'variations_count_' + groupId)
				.html('<strong>' + t('variationsInXml') + '</strong> ' + group.variations_count);
			$info.append($variationsInfo);

			var $calculatedInfo = $('<p>')
				.addClass('f2000cs-group-calculated')
				.attr('id', 'calculated_count_' + groupId);
			$info.append($calculatedInfo);

			$header.append($info);
			$groupBox.append($header);

			if (group.attributes && group.attributes.length > 0) {
				$groupBox.append(
					$('<p>')
						.addClass('f2000cs-group-attr-label')
						.html('<strong>' + t('selectAttributes') + '</strong>')
				);
				$groupBox.append($('<p>').addClass('f2000cs-group-hint').text(t('attributesHint')));

				if (!groupsData[groupId].selected_attributes) {
					var firstVaryingAttr = group.attributes.find(function (attr) {
						return attr.is_varying;
					});

					if (firstVaryingAttr) {
						groupsData[groupId].selected_attributes = [firstVaryingAttr.name];
					}
				}

				group.attributes.forEach(function (attr, index) {
					var checkboxId = 'attr_' + groupId + '_' + index;
					var isDefaultSelected =
						attr.is_varying && groupsData[groupId].selected_attributes.includes(attr.name);
					var $checkboxWrapper = $('<div>').addClass('f2000cs-group-checkbox');
					var $checkbox = $('<input>').attr({
						type: 'checkbox',
						name: 'group_attr_' + groupId + '[]',
						id: checkboxId,
						value: attr.name,
						checked: isDefaultSelected,
						disabled: !attr.is_varying,
					});

					$checkbox.on('change', function () {
						var selected = [];
						$('input[name="group_attr_' + groupId + '[]"]:checked').each(function () {
							selected.push($(this).val());
						});
						groupsData[groupId].selected_attributes = selected;
						updateVariationsCount(groupId);
					});

					var $label = $('<label>')
						.addClass('f2000cs-group-label' + (attr.is_varying ? '' : ' f2000cs-group-label--disabled'))
						.attr('for', checkboxId)
						.text(attr.name + buildVaryingLabel(attr.is_varying) + ' (' + attr.values.join(', ') + ')');

					$checkboxWrapper.append($checkbox).append($label);
					$groupBox.append($checkboxWrapper);
				});
			} else {
				$groupBox.append($('<p>').addClass('f2000cs-group-empty').text(t('noAttributes')));
			}

			$container.append($groupBox);
			updateVariationsCount(groupId);
		});
	}

	function runImportChunk(formData, options) {
		var stopButton = options.stopButtonId || '#stop-import';

		if (stopImport) {
			$('#import-status').text(options.stoppedMessage || t('importStopped'));
			setVisible( $( stopButton ), false );
			options.onFinish();
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				if (!response.success) {
					alert(t('errorPrefix') + ' ' + response.data.message);
					setVisible( $( stopButton ), false );
					options.onFinish();
					return;
				}

				if (response.data.import_session) {
					importSession = response.data.import_session;
					formData.set('import_session', importSession);
				}

				var imported = response.data.imported;
				var total = response.data.total;
				var progress = total > 0 ? (imported / total) * 100 : 0;

				updateImportProgress( response.data.finished ? 100 : progress );

				if (options.onProgress) {
					options.onProgress(response.data, imported, total);
				} else if (options.withSelection) {
					$('#import-status').text(t('importedLabel') + ' ' + imported + ' / ' + total);
				} else {
					$('#import-status').text(imported + ' / ' + total + ' ' + t('productsImported'));
				}

				if (!response.data.finished) {
					if (stopImport) {
						$('#import-status').text(options.stoppedMessage || t('importStopped'));
						setVisible( $( stopButton ), false );
						options.onFinish();
						return;
					}

					formData.set('offset', imported);
					runImportChunk(formData, options);
					return;
				}

				updateImportProgress( 100 );

				if (options.onFinishStatus) {
					options.onFinishStatus(response.data, imported);
				} else if (options.withSelection) {
					$('#import-status').text(t('importFinishedCount') + ' ' + imported + ' ' + t('productsLabel'));
				} else {
					$('#import-status').text(t('importFinished'));
				}

				importSession = '';
				setVisible( $( stopButton ), false );
				options.onFinish();
			},
			error: function () {
				alert(t('importFailed'));
				setVisible( $( stopButton ), false );
				options.onFinish();
			},
		});
	}

	function startImport(withSelection) {
		var skuPrefix = $('#import_sku_prefix').val().trim();

		if (!validateImportSource()) {
			return;
		}

		if (!skuPrefix) {
			alert(withSelection ? t('enterSkuPrefix') : t('enterSkuBeforeImport'));
			$('#import_sku_prefix').focus();
			return;
		}

		if (withSelection) {
			var selectedAttributes = {};
			var hasEmptySelection = false;

			Object.keys(groupsData).forEach(function (groupId) {
				var selected = groupsData[groupId].selected_attributes || [];
				selectedAttributes[groupId] = selected;

				if (
					selected.length === 0 &&
					groupsData[groupId].attributes.some(function (attr) {
						return attr.is_varying;
					})
				) {
					hasEmptySelection = true;
				}
			});

			if (hasEmptySelection) {
				alert(t('selectAttributeGroup'));
				return;
			}
		}

		stopImport = false;

		var formData = new FormData($('#xml-import-form')[0]);
		formData.append('action', 'f2000cs_import_action');
		formData.append('new_category', $('#new_category').is(':checked') ? '1' : '0');
		formData.append('new_category_subcats', ($('#new_category').is(':checked') && $('#new_category_subcats').is(':checked')) ? '1' : '0');
		formData.append('import_variations', withSelection ? '1' : '0');
		formData.append('sku_prefix', skuPrefix);
		formData.set('offset', '0');

		if (importSession) {
			formData.set('import_session', importSession);
		}

		if (withSelection) {
			formData.append('selected_attributes', JSON.stringify(selectedAttributes));
			$('#groups-analysis-container').hide();
		}

		showImportProgress();
		setVisible( $( '#stop-import' ), true );

		if (withSelection) {
			$('#start-import-with-selection').prop('disabled', true);
		} else {
			$('#start-import').prop('disabled', true);
		}

		runImportChunk(formData, {
			withSelection: withSelection,
			onFinish: function () {
				if (withSelection) {
					$('#start-import-with-selection').prop('disabled', false);
				} else {
					$('#start-import').prop('disabled', false);
				}
			},
		});
	}

	function startUpdateFields() {
		var skuPrefix = $('#import_sku_prefix').val().trim();
		var stats = { updated: 0, notFound: 0 };

		if (!validateImportSource()) {
			return;
		}

		if (!skuPrefix) {
			alert(t('enterSkuBeforeUpdate'));
			$('#import_sku_prefix').focus();
			return;
		}

		if ($('.f2000cs-update-field:checked').length === 0) {
			alert(t('selectUpdateField'));
			return;
		}

		stopImport = false;

		var formData = new FormData($('#xml-import-form')[0]);
		formData.append('action', 'f2000cs_update_fields_action');
		formData.append('sku_prefix', skuPrefix);
		formData.set('offset', '0');

		if (importSession) {
			formData.set('import_session', importSession);
		}

		showImportProgress();
		setVisible( $( '#stop-update-fields' ), true );
		$('#start-update-fields').prop('disabled', true);

		runImportChunk(formData, {
			stopButtonId: '#stop-update-fields',
			stoppedMessage: t('updateFieldsStopped'),
			onProgress: function (data, imported, total) {
				stats.updated += parseInt(data.updated, 10) || 0;
				stats.notFound += parseInt(data.not_found, 10) || 0;
				$('#import-status').text(imported + ' / ' + total + ' ' + t('fieldsProcessed'));
			},
			onFinishStatus: function () {
				var summary = t('updateFieldsSummary')
					.replace('%1$d', String(stats.updated))
					.replace('%2$d', String(stats.notFound));
				$('#import-status').text(t('updateFieldsFinished') + ' ' + summary);
			},
			onFinish: function () {
				$('#start-update-fields').prop('disabled', false);
			},
		});
	}

	function toggleNewCategorySubcats(enabled) {
		var $subcats = $('#new_category_subcats');
		$subcats.prop('disabled', !enabled);
		if (!enabled) {
			$subcats.prop('checked', false);
		}
	}

	$(function () {
		toggleImportSource(getImportSource());
		toggleImportMode($('input[name="import_mode"]:checked').val() || 'simple');
		toggleNewCategorySubcats($('#new_category').is(':checked'));

		$('input[name="import_source"]').on('change', function () {
			toggleImportSource($(this).val());
		});

		$('#new_category').on('change', function () {
			toggleNewCategorySubcats($(this).is(':checked'));
		});

		$('.f2000cs-import-tabs__tab').on('click', function () {
			switchImportTab($(this).data('f2000cs-tab'));
		});

		$('#update_fields_select_all').on('change', function () {
			$('.f2000cs-update-field').prop('checked', $(this).is(':checked'));
		});

		$('.f2000cs-update-field').on('change', function () {
			var $fields = $('.f2000cs-update-field');
			var allChecked = $fields.length > 0 && $fields.filter(':checked').length === $fields.length;
			$('#update_fields_select_all').prop('checked', allChecked);
		});

		$('input[name="import_mode"]').on('change', function () {
			toggleImportMode($(this).val());
		});

		$('#analyze-xml').on('click', function () {
			var skuPrefix = $('#import_sku_prefix').val().trim();
			var $button = $(this);

			if (!validateImportSource()) {
				return;
			}

			if (!skuPrefix) {
				alert(t('enterSkuPrefix'));
				$('#import_sku_prefix').focus();
				return;
			}

			importSession = '';

			var formData = new FormData($('#xml-import-form')[0]);
			formData.append('action', 'f2000cs_analyze_groups');
			formData.append('sku_prefix', skuPrefix);

			$('#analysis-status').html('<p>' + t('analyzing') + '</p>');
			$('#groups-analysis-container').show();
			$('#groups-list').empty();
			$button.prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						groupsData = response.data.groups;
						if (response.data.import_session) {
							importSession = response.data.import_session;
						}
						displayGroups(response.data.groups);
						$('#analysis-status').html(
							'<p class="f2000cs-status-message--success">' +
								t('analysisDone') +
								' ' +
								Object.keys(groupsData).length +
								'</p>'
						);
						setVisible( $( '#start-import-with-selection' ), true );
					} else {
						$('#analysis-status').html(
							'<p class="f2000cs-status-message--error">' + response.data.message + '</p>'
						);
					}

					$button.prop('disabled', false);
				},
				error: function () {
					$('#analysis-status').html(
						'<p class="f2000cs-status-message--error">' + t('analysisError') + '</p>'
					);
					$button.prop('disabled', false);
				},
			});
		});

		$('#start-import').on('click', function () {
			startImport(false);
		});

		$('#start-import-with-selection').on('click', function () {
			startImport(true);
		});

		$('#stop-import').on('click', function () {
			stopImport = true;
		});

		$('#start-update-fields').on('click', function () {
			startUpdateFields();
		});

		$('#stop-update-fields').on('click', function () {
			stopImport = true;
		});
	});

	// ----------------------------------------------------------------
	// Import resumption
	// ----------------------------------------------------------------

	var $resume = $('.f2000cs-import-resume');

	if ($resume.length) {
		var pending = $resume.data('f2000cs-resume') || {};

		$resume.find('.f2000cs-import-resume__btn').on('click', function () {
			if (!pending.session) {
				return;
			}

			// Pre-fill the form with the saved context so the user sees
			// what they're resuming.
			$('#import_sku_prefix').val(pending.context.sku_prefix || '');
			$('input[name="import_mode"][value="' +
				(pending.context.import_variations ? 'variable' : 'simple') + '"]')
				.prop('checked', true);
			$('#new_category').prop('checked', !!pending.context.new_category);
			$('#new_category_subcats')
				.prop('disabled', !pending.context.new_category)
				.prop('checked', !!pending.context.new_category && !!pending.context.new_category_subcats);

			// Lock the form so the source can't be changed mid-resume.
			// The stop buttons stay active so a resumed import can be
			// cancelled without reloading the page.
			$('#xml-import-form input, #xml-import-form select, #xml-import-form button:not(.f2000cs-import-resume__btn):not(#stop-import):not(#stop-update-fields)')
				.prop('disabled', true).addClass('f2000cs-import--resume-locked');

			// Rebuild FormData for chunked resume via runImportChunk.
			importSession = pending.session;
			stopImport    = false;

			var formData = new FormData();
			formData.set('action',               'f2000cs_import_action');
			formData.set('import_session',       pending.session);
			formData.set('offset',               String(pending.offset || 0));
			formData.set('sku_prefix',           pending.context.sku_prefix || '');
			formData.set('import_variations',    pending.context.import_variations ? '1' : '0');
			formData.set('new_category',         pending.context.new_category ? '1' : '0');
			formData.set('new_category_subcats', (pending.context.new_category && pending.context.new_category_subcats) ? '1' : '0');
			formData.set('selected_attributes',  JSON.stringify(pending.context.selected_attributes || {}));
			formData.set('f2000cs_import_nonce', $('[name="f2000cs_import_nonce"]').val() || $resume.data('f2000cs-nonce') || '');

			if (pending.context.source) {
				formData.set('import_source', 'url');
				formData.set('import_xml_url', pending.context.source);
			}

			$resume.remove();
			showImportProgress();
			if (pending.offset > 0 && pending.total > 0) {
				updateImportProgress( ( pending.offset / pending.total ) * 100 );
				$('#import-status').text(pending.offset + ' / ' + pending.total + ' ' + t('productsImported'));
			}
			setVisible($('#start-import'), false);
			setVisible($('#stop-import'), true);

			runImportChunk(formData, {
				withSelection: !!pending.context.import_variations,
				onFinish: function () {
					setVisible($('#stop-import'), false);
					if (pending.context.import_variations) {
						setVisible($('#analyze-xml'), true);
					} else {
						setVisible($('#start-import'), true);
					}
					$('#xml-import-form input, #xml-import-form select, #xml-import-form button')
						.prop('disabled', false)
						.removeClass('f2000cs-import--resume-locked');
				},
			});
		});

		$resume.find('.f2000cs-import-resume__discard').on('click', function () {
			if (pending.session && pending.session.length) {
				$.post(ajaxurl, {
					action:             'f2000cs_import_discard',
					import_session:     pending.session,
					f2000cs_import_nonce: $resume.data('f2000cs-nonce')
				});
			}
			$resume.remove();
		});
	}
})(jQuery);
