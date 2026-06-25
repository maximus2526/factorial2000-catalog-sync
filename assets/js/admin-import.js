(function ($) {
	'use strict';

	if (typeof promXmlImport === 'undefined') {
		return;
	}

	var i18n = promXmlImport.i18n || {};
	var stopImport = false;
	var groupsData = {};

	function t(key) {
		return i18n[key] || '';
	}

	function toggleImportMode(mode) {
		var isVariable = mode === 'variable';

		$('#analyze-xml').toggle(isVariable);
		$('#start-import').toggle(!isVariable);
		$('#groups-analysis-container').hide();
	}

	function buildVaryingLabel(isVarying) {
		return isVarying ? ' ✓ (' + t('varyingYes') + ')' : ' ✗ (' + t('varyingNo') + ')';
	}

	function updateVariationsCount(groupId) {
		var group = groupsData[groupId];
		var $target = $('#calculated_count_' + groupId);

		if (!group || !group.selected_attributes || group.selected_attributes.length === 0) {
			$target.removeClass('prom-xml-group-calculated--warning').html('');
			return;
		}

		$target.removeClass('prom-xml-group-calculated--warning');

		var totalVariations = 1;
		var attrInfo = [];

		group.attributes.forEach(function (attr) {
			if (group.selected_attributes.includes(attr.name) && attr.is_varying) {
				totalVariations *= attr.values.length;
				attrInfo.push(attr.name + ': ' + attr.values.length);
			}
		});

		if (attrInfo.length > 1) {
			$target.html('<strong>' + t('variationsWillCreate') + '</strong> ' + totalVariations + ' (' + attrInfo.join(' × ') + ')');
		} else if (attrInfo.length === 1) {
			$target.html('<strong>' + t('variationsWillCreate') + '</strong> ' + totalVariations);
		} else {
			$target
				.addClass('prom-xml-group-calculated--warning')
				.html('<strong>' + t('variationsWarning') + '</strong>');
		}
	}

	function displayGroups(groups) {
		var $container = $('#groups-list');
		$container.empty();

		Object.keys(groups).forEach(function (groupId) {
			var group = groups[groupId];
			var $groupBox = $('<div>').addClass('prom-xml-group-box');
			var $header = $('<div>').addClass('prom-xml-group-header');

			if (group.image) {
				$header.append(
					$('<img>')
						.addClass('prom-xml-group-image')
						.attr('src', group.image)
						.attr('alt', group.name || '')
				);
			}

			var $info = $('<div>');
			$info.append($('<h4>').addClass('prom-xml-group-title').text(group.name));
			$info.append(
				$('<p>')
					.addClass('prom-xml-group-meta')
					.html('<strong>' + t('groupId') + '</strong> ' + groupId)
			);

			var $variationsInfo = $('<p>')
				.addClass('prom-xml-group-variations')
				.attr('id', 'variations_count_' + groupId)
				.html('<strong>' + t('variationsInXml') + '</strong> ' + group.variations_count);
			$info.append($variationsInfo);

			var $calculatedInfo = $('<p>')
				.addClass('prom-xml-group-calculated')
				.attr('id', 'calculated_count_' + groupId);
			$info.append($calculatedInfo);

			$header.append($info);
			$groupBox.append($header);

			if (group.attributes && group.attributes.length > 0) {
				$groupBox.append(
					$('<p>')
						.addClass('prom-xml-group-attr-label')
						.html('<strong>' + t('selectAttributes') + '</strong>')
				);
				$groupBox.append($('<p>').addClass('prom-xml-group-hint').text(t('attributesHint')));

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
					var $checkboxWrapper = $('<div>').addClass('prom-xml-group-checkbox');
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
						.addClass('prom-xml-group-label' + (attr.is_varying ? '' : ' prom-xml-group-label--disabled'))
						.attr('for', checkboxId)
						.text(attr.name + buildVaryingLabel(attr.is_varying) + ' (' + attr.values.join(', ') + ')');

					$checkboxWrapper.append($checkbox).append($label);
					$groupBox.append($checkboxWrapper);
				});
			} else {
				$groupBox.append($('<p>').addClass('prom-xml-group-empty').text(t('noAttributes')));
			}

			$container.append($groupBox);
			updateVariationsCount(groupId);
		});
	}

	function runImportChunk(formData, options) {
		if (stopImport) {
			$('#import-status').text(t('importStopped'));
			$('#stop-import').hide();
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
					$('#stop-import').hide();
					options.onFinish();
					return;
				}

				var imported = response.data.imported;
				var total = response.data.total;
				var progress = total > 0 ? (imported / total) * 100 : 0;

				$('#import-progress').val(progress);

				if (options.withSelection) {
					$('#import-status').html(t('importedLabel') + ' ' + imported + ' / ' + total);
				} else {
					$('#import-status').text(imported + ' / ' + total + ' ' + t('productsImported'));
				}

				if (!response.data.finished && !stopImport) {
					formData.set('offset', imported);
					runImportChunk(formData, options);
					return;
				}

				if (options.withSelection) {
					$('#import-status').html(t('importFinishedCount') + ' ' + imported + ' ' + t('productsLabel'));
				} else {
					$('#import-status').text(t('importFinished'));
				}

				$('#stop-import').hide();
				options.onFinish();
			},
			error: function () {
				alert(t('importFailed'));
				$('#stop-import').hide();
				options.onFinish();
			},
		});
	}

	function startImport(withSelection) {
		var skuPrefix = $('#import_sku_prefix').val().trim();

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
		formData.append('action', 'prom_xml_import_action');
		formData.append('new_category', $('#new_category').is(':checked') ? '1' : '0');
		formData.append('import_variations', withSelection ? '1' : '0');
		formData.append('sku_prefix', skuPrefix);
		formData.set('offset', '0');

		if (withSelection) {
			formData.append('selected_attributes', JSON.stringify(selectedAttributes));
			$('#groups-analysis-container').hide();
		}

		$('#import-progress-container').show();
		$('#stop-import').show();

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

	$(function () {
		$('input[name="import_mode"]').on('change', function () {
			toggleImportMode($(this).val());
		});

		$('#analyze-xml').on('click', function () {
			var fileInput = $('#import_xml_file')[0];
			var skuPrefix = $('#import_sku_prefix').val().trim();
			var $button = $(this);

			if (!fileInput.files.length) {
				alert(t('selectFile'));
				return;
			}

			if (!skuPrefix) {
				alert(t('enterSkuPrefix'));
				$('#import_sku_prefix').focus();
				return;
			}

			var formData = new FormData($('#xml-import-form')[0]);
			formData.append('action', 'prom_xml_analyze_groups');
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
						displayGroups(response.data.groups);
						$('#analysis-status').html(
							'<p class="prom-xml-status-message--success">' +
								t('analysisDone') +
								' ' +
								Object.keys(groupsData).length +
								'</p>'
						);
						$('#start-import-with-selection').show();
					} else {
						$('#analysis-status').html(
							'<p class="prom-xml-status-message--error">' + response.data.message + '</p>'
						);
					}

					$button.prop('disabled', false);
				},
				error: function () {
					$('#analysis-status').html(
						'<p class="prom-xml-status-message--error">' + t('analysisError') + '</p>'
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
	});
})(jQuery);
