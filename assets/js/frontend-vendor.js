(function () {
	'use strict';

	var STORAGE_PREFIX = 'f2000csVendorPanel:';

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();

			try {
				document.execCommand('copy');
				document.body.removeChild(textarea);
				resolve();
			} catch (error) {
				document.body.removeChild(textarea);
				reject(error);
			}
		});
	}

	function showCopyFeedback(element) {
		var originalText = element.textContent;
		var copiedLabel =
			typeof f2000csVendor !== 'undefined' && f2000csVendor.copiedLabel
				? f2000csVendor.copiedLabel
				: '✓ Скопійовано!';

		element.textContent = copiedLabel;
		element.classList.add('is-copied');

		window.setTimeout(function () {
			element.textContent = originalText;
			element.classList.remove('is-copied');
		}, 1500);
	}

	function storageKey(panel, suffix) {
		var productId = panel.getAttribute('data-product-id') || '0';
		return STORAGE_PREFIX + productId + ':' + suffix;
	}

	function getLabels() {
		return {
			collapse:
				typeof f2000csVendor !== 'undefined' && f2000csVendor.collapseLabel
					? f2000csVendor.collapseLabel
					: 'Згорнути',
			expand:
				typeof f2000csVendor !== 'undefined' && f2000csVendor.expandLabel
					? f2000csVendor.expandLabel
					: 'Розгорнути',
			close:
				typeof f2000csVendor !== 'undefined' && f2000csVendor.closeLabel
					? f2000csVendor.closeLabel
					: 'Закрити',
		};
	}

	function setCollapsed(panel, collapsed) {
		var labels = getLabels();
		var button = panel.querySelector('.f2000cs-vendor-code-footer__btn--collapse');

		panel.classList.toggle('is-collapsed', collapsed);

		if (button) {
			button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			button.textContent = collapsed ? labels.expand : labels.collapse;
			button.title = collapsed ? labels.expand : labels.collapse;
		}

		try {
			window.sessionStorage.setItem(storageKey(panel, 'collapsed'), collapsed ? '1' : '0');
		} catch (e) {
			/* ignore */
		}
	}

	function closePanel(panel) {
		panel.classList.add('is-closed');
		panel.setAttribute('hidden', 'hidden');

		try {
			window.sessionStorage.setItem(storageKey(panel, 'closed'), '1');
		} catch (e) {
			/* ignore */
		}
	}

	function initPanel(panel) {
		var collapsed = false;
		var closed = false;

		try {
			collapsed = window.sessionStorage.getItem(storageKey(panel, 'collapsed')) === '1';
			closed = window.sessionStorage.getItem(storageKey(panel, 'closed')) === '1';
		} catch (e) {
			/* ignore */
		}

		if (closed) {
			closePanel(panel);
			return;
		}

		if (collapsed) {
			setCollapsed(panel, true);
		}

		var collapseBtn = panel.querySelector('.f2000cs-vendor-code-footer__btn--collapse');
		var closeBtn = panel.querySelector('.f2000cs-vendor-code-footer__btn--close');

		if (collapseBtn) {
			collapseBtn.addEventListener('click', function () {
				setCollapsed(panel, !panel.classList.contains('is-collapsed'));
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				closePanel(panel);
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-f2000cs-vendor-panel]').forEach(initPanel);

		document.querySelectorAll('.vendor-code-copy').forEach(function (element) {
			element.addEventListener('click', function () {
				var code = element.getAttribute('data-code');
				if (!code) {
					return;
				}

				copyText(code).then(function () {
					showCopyFeedback(element);
				});
			});
		});
	});
})();
