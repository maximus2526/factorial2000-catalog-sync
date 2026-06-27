(function () {
	'use strict';

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
			typeof f2csVendor !== 'undefined' && f2csVendor.copiedLabel
				? f2csVendor.copiedLabel
				: '✓ Скопійовано!';

		element.textContent = copiedLabel;
		element.classList.add('is-copied');

		window.setTimeout(function () {
			element.textContent = originalText;
			element.classList.remove('is-copied');
		}, 1500);
	}

	document.addEventListener('DOMContentLoaded', function () {
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
