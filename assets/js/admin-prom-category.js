(function ($) {
	'use strict';

	if (typeof f2000csPromCategory === 'undefined') {
		return;
	}

	var cfg = f2000csPromCategory;
	var i18n = cfg.i18n || {};
	var timer = null;
	var request = null;

	/**
	 * Remove the open result list of an input.
	 *
	 * @param {jQuery} $wrap Picker wrapper.
	 */
	function close($wrap) {
		$wrap.find('.f2000cs-prom-cat-list').remove();
	}

	/**
	 * Put a chosen category into the input.
	 *
	 * @param {jQuery} $input Input.
	 * @param {string} id     Category id.
	 * @param {string} path   Category path.
	 */
	function choose($input, id, path) {
		var $wrap = $input.closest('.f2000cs-prom-cat-wrap');

		$input.val(id).data('saved', id).trigger('change');
		$wrap.find('.f2000cs-prom-cat-name').text(path).removeClass('f2000cs-prom-cat-unknown');
		close($wrap);
	}

	/**
	 * Show results under the input. Text is set with .text(): category names come from a file.
	 *
	 * @param {jQuery} $input Input.
	 * @param {Array}  items  [{id, path}].
	 */
	function render($input, items) {
		var $wrap = $input.closest('.f2000cs-prom-cat-wrap');
		var $list = $('<ul class="f2000cs-prom-cat-list" role="listbox"></ul>');

		close($wrap);

		if (!items.length) {
			$list.append($('<li></li>').text(i18n.nothing || ''));
		}

		items.forEach(function (item, index) {
			var $li = $('<li role="option"></li>')
				.toggleClass('is-active', index === 0)
				.attr('data-id', item.id)
				.attr('data-path', item.path)
				.text(item.path)
				.append($('<small></small>').text('#' + item.id));

			$list.append($li);
		});

		// Fixed to the viewport: the category table scrolls inside a box that would clip it.
		var rect = $input[0].getBoundingClientRect();
		var below = window.innerHeight - rect.bottom;

		$list.css({
			position: 'fixed',
			left: Math.max(4, rect.left),
			minWidth: rect.width
		});

		if (below < 220 && rect.top > below) {
			$list.css({ bottom: window.innerHeight - rect.top + 2, maxHeight: Math.min(300, rect.top - 8) });
		} else {
			$list.css({ top: rect.bottom + 2, maxHeight: Math.min(300, below - 8) });
		}

		$wrap.append($list);
	}

	/**
	 * Ask the server for categories matching the input.
	 *
	 * @param {jQuery} $input Input.
	 */
	function search($input) {
		var query = $.trim($input.val());

		if (query.length < 2 && !/^\d+$/.test(query)) {
			close($input.closest('.f2000cs-prom-cat-wrap'));
			return;
		}

		if (request) {
			request.abort();
		}

		request = $.get(cfg.ajaxUrl, { action: 'f2000cs_prom_category_search', nonce: cfg.nonce, q: query })
			.done(function (response) {
				if (response && response.success && $input.is(':focus')) {
					render($input, response.data || []);
				}
			});
	}

	// The list is fixed to the viewport, so it would drift away from its input on scroll.
	document.addEventListener('scroll', function (event) {
		if (!$(event.target).closest('.f2000cs-prom-cat-list').length) {
			$('.f2000cs-prom-cat-list').remove();
		}
	}, true);

	$(document)
		.on('focus', '.f2000cs-prom-cat', function () {
			var $input = $(this);

			if ($input.data('saved') === undefined) {
				$input.data('saved', $input.val());
			}
		})
		.on('input', '.f2000cs-prom-cat', function () {
			var $input = $(this);

			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				search($input);
			}, 250);
		})
		.on('keydown', '.f2000cs-prom-cat', function (event) {
			var $wrap = $(this).closest('.f2000cs-prom-cat-wrap');
			var $items = $wrap.find('.f2000cs-prom-cat-list li[data-id]');
			var $active = $items.filter('.is-active');
			var index = $items.index($active);

			if (!$items.length) {
				return;
			}

			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				index = event.key === 'ArrowDown' ? Math.min($items.length - 1, index + 1) : Math.max(0, index - 1);
				$items.removeClass('is-active').eq(index).addClass('is-active')[0].scrollIntoView({ block: 'nearest' });
			} else if (event.key === 'Enter') {
				// Never submit the settings form while choosing.
				event.preventDefault();
				choose($(this), String($active.data('id')), String($active.data('path')));
			} else if (event.key === 'Escape') {
				close($wrap);
			}
		})
		.on('mousedown', '.f2000cs-prom-cat-list li[data-id]', function (event) {
			// mousedown, not click: the input's blur would close the list first.
			event.preventDefault();
			var $li = $(this);
			choose($li.closest('.f2000cs-prom-cat-wrap').find('.f2000cs-prom-cat'), String($li.data('id')), String($li.data('path')));
		})
		.on('blur', '.f2000cs-prom-cat', function () {
			var $input = $(this);
			var $wrap = $input.closest('.f2000cs-prom-cat-wrap');
			var value = $.trim($input.val());

			close($wrap);

			// Search words that were not turned into a choice must not be saved.
			if (value !== '' && !/^\d+$/.test(value)) {
				$input.val($input.data('saved') || '');
				return;
			}

			if (value === '') {
				$wrap.find('.f2000cs-prom-cat-name').text('').removeClass('f2000cs-prom-cat-unknown');
				$input.data('saved', '');
			} else if (value !== String($input.data('saved'))) {
				// A number typed by hand: show whether Prom knows it.
				$.get(cfg.ajaxUrl, { action: 'f2000cs_prom_category_search', nonce: cfg.nonce, q: value }).done(function (response) {
					var match = (response && response.success ? response.data : []).filter(function (item) {
						return String(item.id) === value;
					})[0];

					$wrap.find('.f2000cs-prom-cat-name')
						.text(match ? match.path : (i18n.unknown || ''))
						.toggleClass('f2000cs-prom-cat-unknown', !match);
				});
				$input.data('saved', value);
			}
		});
})(jQuery);
