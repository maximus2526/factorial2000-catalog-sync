(function ($) {
	'use strict';

	if (typeof f2000csPromCategory === 'undefined' || !$.fn.selectWoo) {
		return;
	}

	var cfg = f2000csPromCategory;
	var i18n = cfg.i18n || {};

	/**
	 * One result line: the category path and its Prom id. Built with .text(): names come from a file.
	 *
	 * @param {Object} item Select2 item {id, text, path}.
	 * @return {jQuery|string}
	 */
	function format(item) {
		if (!item.id) {
			return item.text;
		}

		var path = item.path || item.text;
		var $line = $('<span class="f2000cs-prom-cat-opt"></span>').text(path);

		if (item.path) {
			$line.append($('<small></small>').text('#' + item.id));
		}

		if ($(item.element).attr('data-unknown') === '1') {
			$line.addClass('f2000cs-prom-cat-unknown');
		}

		return $line;
	}

	/**
	 * Turn a plain select into a searchable Prom category picker.
	 *
	 * @param {jQuery} $select Select with the .f2000cs-prom-cat class.
	 */
	function enhance($select) {
		if ($select.data('select2')) {
			return;
		}

		$select.selectWoo({
			width: '100%',
			allowClear: true,
			placeholder: $select.data('placeholder') || '',
			minimumInputLength: 2,
			templateResult: format,
			templateSelection: function (item) {
				return item.id && item.path ? item.path + ' (#' + item.id + ')' : item.text;
			},
			language: {
				inputTooShort: function () {
					return i18n.tooShort || '';
				},
				noResults: function () {
					return i18n.nothing || '';
				},
				searching: function () {
					return i18n.searching || '';
				}
			},
			ajax: {
				url: cfg.ajaxUrl,
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function (params) {
					return { action: 'f2000cs_prom_category_search', nonce: cfg.nonce, q: params.term || '' };
				},
				processResults: function (response) {
					var items = response && response.success ? response.data || [] : [];

					return {
						results: items.map(function (item) {
							return { id: item.id, text: item.path, path: item.path };
						})
					};
				}
			}
		});
	}

	function init() {
		$('select.f2000cs-prom-cat').each(function () {
			enhance($(this));
		});
	}

	$(init);
})(jQuery);
