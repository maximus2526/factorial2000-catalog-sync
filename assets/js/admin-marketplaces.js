(function ($) {
	'use strict';

	if (typeof f2000csMarketplaces === 'undefined') {
		return;
	}

	var cfg = f2000csMarketplaces;
	var i18n = cfg.i18n || {};

	/**
	 * Drive the chunked feed build: one AJAX request per slice until the server says it is done.
	 */
	function run($button, restart) {
		var feed = $button.data('feed');
		var $progress = $button.closest('p').find('.f2000cs-mp-progress');

		$button.prop('disabled', true);
		$progress.text(i18n.running || '');

		$.post(cfg.ajaxUrl, {
			action: 'f2000cs_marketplace_step',
			nonce: cfg.nonce,
			feed: feed,
			restart: restart ? '1' : '0'
		})
			.done(function (response) {
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? response.data.message : (i18n.failed || '');
					$progress.text(message);
					$button.prop('disabled', false);
					return;
				}

				var data = response.data;

				if (data.status === 'running') {
					$progress.text((i18n.running || '') + ' ' + (data.exported || 0) + ' ' + (i18n.offers || ''));
					run($button, false);
					return;
				}

				if (data.status === 'locked') {
					$progress.text(i18n.waiting || '');
					window.setTimeout(function () {
						run($button, false);
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
				$button.prop('disabled', false);
			})
			.fail(function () {
				$progress.text(i18n.failed || '');
				$button.prop('disabled', false);
			});
	}

	$(document).on('click', '.f2000cs-mp-build', function () {
		var $button = $(this);

		run($button, String($button.data('resume')) !== '1');
	});
})(jQuery);
