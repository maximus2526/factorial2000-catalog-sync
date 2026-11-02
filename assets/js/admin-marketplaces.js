(function ($) {
	'use strict';

	if (typeof f2000csMarketplaces === 'undefined') {
		return;
	}

	var cfg = f2000csMarketplaces;
	var i18n = cfg.i18n || {};

	/**
	 * Drive the chunked feed build: one AJAX request per slice until the server says it is done.
	 *
	 * @param {jQuery} $button Clicked button.
	 * @param {string} mode    'start' a fresh build, 'resume' the running one, or 'force'
	 *                         a fresh build that is published even if the feed shrank.
	 */
	function run($button, mode) {
		var feed = $button.data('feed');
		var $progress = $button.closest('p').find('.f2000cs-mp-progress');
		var $buttons = $button.closest('p').find('.f2000cs-mp-build');

		$buttons.prop('disabled', true);
		$progress.text(i18n.running || '');

		$.post(cfg.ajaxUrl, {
			action: 'f2000cs_marketplace_step',
			nonce: cfg.nonce,
			feed: feed,
			restart: mode === 'start' ? '1' : '0',
			// Follow-up requests must never start a new build: if another process
			// finished the one we were waiting for, the server reports its result.
			resume: mode === 'resume' ? '1' : '0',
			force: mode === 'force' ? '1' : '0'
		})
			.done(function (response) {
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : (i18n.failed || '');
					$progress.text(message);
					$buttons.prop('disabled', false);
					return;
				}

				var data = response.data;

				if (data.status === 'running') {
					$progress.text((i18n.running || '') + ' ' + (data.exported || 0) + ' ' + (i18n.offers || ''));
					run($button, 'resume');
					return;
				}

				if (data.status === 'locked') {
					$progress.text(i18n.waiting || '');
					window.setTimeout(function () {
						run($button, 'resume');
					}, 3000);
					return;
				}

				if (data.status === 'done') {
					$progress.text((i18n.done || '') + ' ' + (data.exported || 0) + ' ' + (i18n.offers || ''));
					window.setTimeout(function () {
						window.location.reload();
					}, 800);
					return;
				}

				$progress.text(data.message || (i18n.failed || ''));

				// A shrunken feed was held back: reload to show the «publish anyway» button.
				if (data.drop) {
					window.setTimeout(function () {
						window.location.reload();
					}, 2500);
					return;
				}

				$buttons.prop('disabled', false);
			})
			.fail(function () {
				$progress.text(i18n.failed || '');
				$buttons.prop('disabled', false);
			});
	}

	$(document).on('click', '.f2000cs-mp-build', function () {
		var $button = $(this);
		var mode = 'start';

		if (String($button.data('force')) === '1') {
			mode = 'force';
		} else if (String($button.data('resume')) === '1') {
			mode = 'resume';
		}

		run($button, mode);
	});

	// Preset selects: show the free input only for the "custom" choice.
	$(document).on('change', '.f2000cs-mp-preset', function () {
		var $custom = $('#' + $(this).data('custom-input'));

		if ($custom.length) {
			$custom.prop('hidden', $(this).val() !== '__custom__');
		}
	});

	/**
	 * Show a settings row only while its controlling field has the expected value.
	 */
	function toggleConditionalRows() {
		$('.f2000cs-mp-show-if').each(function () {
			var $row = $(this);
			var field = String($row.attr('data-show-if-field') || '');
			var expected = String($row.attr('data-show-if-value') || '');
			var $control = $('#f2000cs-mp-' + field);
			var value = '';

			if ($control.is(':checkbox')) {
				value = $control.is(':checked') ? '1' : '0';
			} else {
				value = String($control.val() || '');
			}

			$row.toggle(value === expected);
		});
	}

	$(document).on('change', 'input[name^="mp["], select[name^="mp["]', toggleConditionalRows);
	$(toggleConditionalRows);

	/**
	 * Select2 with free input for brand names: pick a suggestion or type a new name.
	 *
	 * @param {jQuery} $scope Container.
	 */
	function enhanceTags($scope) {
		if (!$.fn.selectWoo) {
			return;
		}

		$scope.find('select.f2000cs-mp-tags').each(function () {
			var $select = $(this);

			if ($select.data('select2')) {
				return;
			}

			$select.selectWoo({
				width: '100%',
				tags: true,
				allowClear: true,
				placeholder: $select.data('placeholder') || '',
				createTag: function (params) {
					var term = $.trim(params.term).replace(/[=\r\n]+/g, ' ');

					return term === '' ? null : { id: term, text: term };
				}
			});
		});
	}

	// The library can arrive after this file when scripts are deferred or reordered: wait for it.
	(function waitForSelectWoo(attempt) {
		if ($.fn.selectWoo) {
			enhanceTags($(document));
		} else if (attempt < 100) {
			window.setTimeout(function () {
				waitForSelectWoo(attempt + 1);
			}, 100);
		}
	})(0);

	// "left = right" repeaters.
	$(document).on('click', '.f2000cs-mp-row-add', function () {
		var $table = $('.f2000cs-mp-pairs[data-key="' + $(this).data('target') + '"]');
		var $body = $table.find('tbody');
		var $last = $body.find('tr').last();
		var index = $body.find('tr').length;

		// Clone a row without Select2 on it, then enhance the copy.
		$last.find('select.f2000cs-mp-tags').each(function () {
			if ($(this).data('select2')) {
				$(this).selectWoo('destroy');
			}
		});

		var $row = $last.clone();

		$row.find('input, select').each(function () {
			this.name = this.name.replace(/\[\d+\]\[(l|r)\]$/, '[' + index + '][$1]');

			if (this.tagName === 'SELECT') {
				$(this).val('');
			} else {
				this.value = '';
			}
		});
		$body.append($row);
		enhanceTags($last);
		enhanceTags($row);
		$row.find('select').first().selectWoo('open');
	});

	$(document).on('click', '.f2000cs-mp-row-remove', function () {
		var $body = $(this).closest('tbody');

		if ($body.find('tr').length > 1) {
			$(this).closest('tr').remove();
		} else {
			$(this).closest('tr').find('input').val('');
			$(this).closest('tr').find('select').val('').trigger('change');
		}
	});

	// Brand table search.
	$(document).on('input', '.f2000cs-mp-brand-filter', function () {
		var q = $(this).val().toLowerCase();

		$('.f2000cs-mp-brand-table tbody tr').each(function () {
			var name = String($(this).data('name') || '');

			// The rows for other brands (no data-name) stay visible: they are being edited.
			$(this).toggle(name === '' || name.indexOf(q) !== -1);
		});
	});

	// Category table search.
	$(document).on('input', '.f2000cs-mp-cat-filter', function () {
		var q = $(this).val().toLowerCase();

		$('.f2000cs-mp-cat-table tbody tr').each(function () {
			$(this).toggle(String($(this).data('name')).indexOf(q) !== -1);
		});
	});

	// Prom recommended attributes: category -> Prom characteristic -> site attribute.
	var promAttributesBusy = false;

	function promAttributeMap($dialog) {
		var map = {};

		$dialog.find('[data-f2000cs-prom-row]').each(function () {
			var $row = $(this);
			var category = String($row.attr('data-category') || '');
			var prom = String($row.attr('data-prom') || '');
			var value = $.trim(String($row.find('select.f2000cs-mp-attr-select').val() || ''));

			if (category && prom && value) {
				if (!map[category]) {
					map[category] = {};
				}
				map[category][prom] = value;
			}
		});

		return map;
	}

	function promAttributeSync($dialog) {
		var $input = $dialog.closest('form').find('[data-f2000cs-prom-map]');

		if ($input.length) {
			$input.val(JSON.stringify(promAttributeMap($dialog)));
		}
	}

	function promAttributeSnapshot($dialog) {
		var values = {};

		$dialog.find('[data-f2000cs-prom-row]').each(function () {
			var $row = $(this);
			var key = String($row.attr('data-category')) + '\x1f' + String($row.attr('data-prom'));

			values[key] = String($row.find('select.f2000cs-mp-attr-select').val() || '');
		});

		return values;
	}

	function promAttributeStatus($row) {
		var $select = $row.find('select.f2000cs-mp-attr-select');
		var value = String($select.val() || '');
		var auto = String($row.attr('data-auto') || '');
		var status = 'none';

		if (value !== '') {
			status = auto !== '' && value === auto ? 'auto' : 'manual';
		}

		$row.attr('data-status', status);
		$row.find('[data-f2000cs-status]')
			.removeClass('is-auto is-manual is-none')
			.addClass('is-' + status)
			.text((i18n[status] || status));
	}

	function promAttributeInit($dialog) {
		if ($.fn.selectWoo) {
			$dialog.find('select.f2000cs-mp-attr-select').each(function () {
				var $select = $(this);

				if ($select.data('select2')) {
					return;
				}

				$select.selectWoo({
					width: '100%',
					allowClear: true,
					placeholder: $select.data('placeholder') || 'Наш атрибут',
					tags: true,
					createTag: function (params) {
						var term = $.trim(params.term);

						return term === '' ? null : { id: 'custom:' + term, text: term + ' (кастомний)' };
					}
				});
			});
		}

		$dialog.off('change.f2000cs', 'select.f2000cs-mp-attr-select').on('change.f2000cs', 'select.f2000cs-mp-attr-select', function () {
			var $row = $(this).closest('[data-f2000cs-prom-row]');

			promAttributeStatus($row);
			promAttributeSync($dialog);
		});
	}

	function promAttributeFilter($dialog) {
		var q = $.trim(String($dialog.find('.f2000cs-prom-search').val() || '')).toLowerCase();
		var onlyEmpty = $dialog.find('.f2000cs-prom-only-empty').prop('checked');

		$dialog.find('[data-f2000cs-prom-group]').each(function () {
			var $group = $(this);
			var $rows = $group.nextUntil('[data-f2000cs-prom-group]');
			var groupText = String($group.attr('data-search') || '');
			var groupMatch = q === '' || groupText.indexOf(q) !== -1;
			var anyRowMatch = false;
			var anyEmpty = false;

			$rows.each(function () {
				var $row = $(this);
				var rowText = String($row.attr('data-search') || '');
				var matches = q === '' || groupMatch || rowText.indexOf(q) !== -1;
				var isEmpty = $row.is('[data-f2000cs-prom-empty]');
				var status = String($row.attr('data-status') || '');

				if (matches) {
					anyRowMatch = true;
				}
				if (status === 'none') {
					anyEmpty = true;
				}

				if (isEmpty) {
					$row.toggle(matches && !onlyEmpty);
				} else {
					$row.toggle(matches && (!onlyEmpty || status === 'none'));
				}
			});

			$group.toggle((q === '' || groupMatch || anyRowMatch) && (!onlyEmpty || anyEmpty));
		});
	}

	function promAttributeRestore($dialog) {
		var values = $dialog.data('f2000cs-initialValues') || {};

		$dialog.find('[data-f2000cs-prom-row]').each(function () {
			var $row = $(this);
			var key = String($row.attr('data-category')) + '\x1f' + String($row.attr('data-prom'));
			var $select = $row.find('select.f2000cs-mp-attr-select');

			if (Object.prototype.hasOwnProperty.call(values, key)) {
				$select.val(values[key]);
			}

			promAttributeStatus($row);
		});

		var $input = $dialog.closest('form').find('[data-f2000cs-prom-map]');

		if ($input.length) {
			$input.val($dialog.data('f2000cs-initialMap') || '');
		}
	}

	function promAttributeOpen($dialog) {
		var $input = $dialog.closest('form').find('[data-f2000cs-prom-map]');

		$dialog.data('f2000cs-initialMap', $input.length ? String($input.val() || '') : '');
		promAttributeInit($dialog);
		$dialog.data('f2000cs-initialValues', promAttributeSnapshot($dialog));
		promAttributeSync($dialog);
		promAttributeFilter($dialog);

		if ($dialog[0] && typeof $dialog[0].showModal === 'function' && !$dialog[0].open) {
			$dialog[0].showModal();
		} else {
			$dialog.attr('open', 'open');
		}

		$dialog.find('.f2000cs-prom-search').trigger('focus');
	}

	function promAttributeClose($dialog, restore) {
		if (restore) {
			promAttributeRestore($dialog);
		}

		if ($dialog[0] && typeof $dialog[0].close === 'function' && $dialog[0].open) {
			$dialog[0].close();
		} else {
			$dialog.removeAttr('open');
		}
	}

	function promAttributeCollectIds() {
		var ids = [];
		var push = function (value) {
			var id = String(value || '').replace(/[^0-9]/g, '');

			if (id && ids.indexOf(id) === -1) {
				ids.push(id);
			}
		};

		$('input[name^="mp_map[portal_category_rules]"], select[name^="mp_map[portal_category_rules]"]').each(function () {
			push($(this).val());
		});

		$('select[name="mp[default_portal_category_id]"]').each(function () {
			push($(this).val());
		});

		var extra = String($('textarea[name="mp_extra[portal_category_rules]"]').val() || '');

		extra.replace(/=(\d+)/g, function (all, id) {
			push(id);
			return all;
		});

		return ids;
	}

	$(document).on('click', '.f2000cs-mp-open-attributes', function () {
		var id = String($(this).attr('data-f2000cs-target') || '');
		var $dialog = $('#' + id);

		if ($dialog.length) {
			promAttributeOpen($dialog);
		}
	});

	$(document).on('click', '[data-f2000cs-close], .f2000cs-prom-cancel', function () {
		promAttributeClose($(this).closest('.f2000cs-prom-attrs-dialog'), true);
	});

	$(document).on('click', '.f2000cs-prom-attrs-dialog', function (event) {
		if (event.target === this) {
			promAttributeClose($(this), true);
		}
	});

	$(document).on('cancel', '.f2000cs-prom-attrs-dialog', function (event) {
		event.preventDefault();
		promAttributeClose($(this), true);
	});

	$(document).on('click', '.f2000cs-prom-save', function () {
		var $dialog = $(this).closest('.f2000cs-prom-attrs-dialog');
		var $form = $dialog.closest('form');

		promAttributeSync($dialog);
		promAttributeClose($dialog, false);

		if ($form.length && typeof $form[0].requestSubmit === 'function') {
			$form[0].requestSubmit();
		} else if ($form.length) {
			$form.trigger('submit');
		}
	});

	$(document).on('click', '.f2000cs-prom-auto', function () {
		var $dialog = $(this).closest('.f2000cs-prom-attrs-dialog');

		$dialog.find('[data-f2000cs-prom-row]').each(function () {
			var $row = $(this);
			var $select = $row.find('select.f2000cs-mp-attr-select');
			var auto = String($row.attr('data-auto') || '');

			if (String($select.val() || '') === '' && auto !== '') {
				$select.val(auto).trigger('change');
			}
		});

		promAttributeSync($dialog);
		promAttributeFilter($dialog);
	});

	$(document).on('input', '.f2000cs-prom-search, .f2000cs-prom-only-empty', function () {
		promAttributeFilter($(this).closest('.f2000cs-prom-attrs-dialog'));
	});

	$(document).on('keydown', '.f2000cs-prom-search', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
		}
	});

	$(document).on('change', '.f2000cs-prom-only-empty', function () {
		promAttributeFilter($(this).closest('.f2000cs-prom-attrs-dialog'));
	});

	$(document).on('click', '.f2000cs-prom-pull', function () {
		var $button = $(this);
		var $dialog = $button.closest('.f2000cs-prom-attrs-dialog');
		var oldText = $button.text();
		var ids = promAttributeCollectIds();
		var map = promAttributeMap($dialog);

		if (promAttributesBusy) {
			return;
		}

		promAttributesBusy = true;
		$button.prop('disabled', true).text(i18n.pulling || oldText);
		promAttributeSync($dialog);

		$.post(cfg.ajaxUrl, {
			action: cfg.promRowsAction || 'f2000cs_marketplace_prom_rows',
			nonce: cfg.promRowsNonce || '',
			ids: ids,
			map: JSON.stringify(map)
		})
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					window.alert(i18n.pullFailed || '');
					return;
				}

				var data = response.data;

				$dialog.find('[data-f2000cs-prom-rows]').html(data.rows || '');
				$dialog.find('[data-f2000cs-prom-summary], [data-f2000cs-prom-summary-field]').text(data.summary || '');
				$dialog.closest('td').find('[data-f2000cs-prom-map]').val(data.map || '');
				$dialog.data('f2000cs-initialMap', data.map || '');
				promAttributeInit($dialog);
				$dialog.data('f2000cs-initialValues', promAttributeSnapshot($dialog));
				promAttributeFilter($dialog);
			})
			.fail(function () {
				window.alert(i18n.pullFailed || '');
			})
			.always(function () {
				promAttributesBusy = false;
				$button.prop('disabled', false).text(oldText);
			});
	});
})(jQuery);
