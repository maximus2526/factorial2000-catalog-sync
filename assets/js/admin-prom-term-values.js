(function ($) {
	'use strict';

	if (typeof f2000csPromTermValues === 'undefined') {
		return;
	}

	var cfg = f2000csPromTermValues;
	var i18n = cfg.i18n || {};

	/**
	 * Initialize the official-value Select2 for the selected Prom category.
	 *
	 * @param {jQuery} $value Select element.
	 */
	function enhance($value) {
		if ($value.data('select2')) {
			return;
		}

		$value.selectWoo({
			width: '100%',
			allowClear: true,
			minimumInputLength: 0,
			placeholder: 'Почніть вводити значення',
			language: {
				noResults: function () {
					return i18n.nothing || '';
				},
				searching: function () {
					return i18n.searching || '';
				}
			},
			ajax: {
				url: cfg.ajaxUrl,
				type: 'GET',
				dataType: 'json',
				delay: 250,
				cache: false,
				data: function (params) {
					return {
						action: 'f2000cs_prom_value_search',
						nonce: cfg.nonce,
						category: $('#f2000cs-prom-term-category').val() || '',
						q: params.term || ''
					};
				},
				processResults: function (response) {
					return { results: response && response.success ? (response.data || []) : [] };
				}
			}
		});
	}

	/**
	 * Add the selected value to the textarea.
	 */
	function addValue() {
		var category = String($('#f2000cs-prom-term-category').val() || '');
		var selected = $('#f2000cs-prom-term-value').data('f2000cs-selected');
		var $map = $('#f2000cs_prom_value_map');

		if (!category || !selected || !selected.value || !$map.length) {
			return;
		}

		var line = category + '=' + selected.value;
		var lines = String($map.val() || '').split(/\r?\n/).filter(function (item) {
			return $.trim(item) !== '' && $.trim(item) !== line;
		});

		lines.push(line);
		$map.val(lines.join('\n'));
	}

	/**
	 * Initialize once selectWoo is available.
	 *
	 * @param {number} attempt Retry counter.
	 */
	function init(attempt) {
		if (!$.fn.selectWoo) {
			if (attempt < 100) {
				window.setTimeout(function () {
					init(attempt + 1);
				}, 100);
			}
			return;
		}

		var $value = $('#f2000cs-prom-term-value');

		if (!$value.length) {
			return;
		}

		enhance($value);

		$('#f2000cs-prom-term-category').on('change', function () {
			$value.val(null).trigger('change');
			$value.removeData('f2000cs-selected');
		});

		$value.on('select2:select', function (event) {
			$value.data('f2000cs-selected', event.params.data);
		});

		$('.f2000cs-prom-term-add').on('click', addValue);
	}

	$(function () {
		init(0);
	});
})(jQuery);
