(function () {
	'use strict';

	var root = document.getElementById('prom-xml-support');
	if (!root || typeof promXmlSupport === 'undefined') {
		return;
	}

	var closeBtn = root.querySelector('[data-prom-support-close]');
	var fabBtn = root.querySelector('[data-prom-support-open]');
	var copyBtn = root.querySelector('[data-prom-support-copy]');

	function setCollapsed(collapsed) {
		root.classList.toggle('is-collapsed', collapsed);
	}

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

	if (closeBtn) {
		closeBtn.addEventListener('click', function () {
			setCollapsed(true);
		});
	}

	if (fabBtn) {
		fabBtn.addEventListener('click', function () {
			setCollapsed(false);
		});
	}

	if (copyBtn) {
		copyBtn.addEventListener('click', function () {
			var cardNumber = promXmlSupport.cardNumber || '';

			copyText(cardNumber).then(function () {
				var originalText = copyBtn.textContent;
				copyBtn.textContent = promXmlSupport.copiedLabel || 'Copied';
				copyBtn.classList.add('is-copied');

				window.setTimeout(function () {
					copyBtn.textContent = originalText;
					copyBtn.classList.remove('is-copied');
				}, 1500);
			});
		});
	}
})();
