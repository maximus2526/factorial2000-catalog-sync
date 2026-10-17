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

	enhanceTags($(document));

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
})(jQuery);
