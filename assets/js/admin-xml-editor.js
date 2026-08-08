(function ($) {
	'use strict';

	if (typeof f2000csXmlEditor === 'undefined') {
		return;
	}

	var cfg = f2000csXmlEditor;

	var PAGE_SIZE = 200;

	// -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- state

	var state = {
		token: '',
		categories: [],        // flat list: { id, name, parent, count, has_children }
		checked: {},           // explicitly checked category ids (top-level; children are visual cascade)
		expanded: {},          // expanded category ids
		childrenContainers: {},// category id => children <div> reference
		chevrons: {},          // category id => chevron element reference
		excluded: {},          // offer ids unchecked inside checked categories
		extra: {},             // offer ids checked outside checked categories
		rows: {},              // loaded offer rows: id => { category_id, available, ... }
		page: 1,
		hasMore: false,
		productsTotal: 0
	};

	function t(key) {
		return cfg.i18n[key] || key;
	}

	function escapeHtml(value) {
		return $('<div>').text(value === null || typeof value === 'undefined' ? '' : String(value)).html();
	}

	function showStatus(type, html) {
		$('#f2000cs-xml-editor-status')
			.removeClass('notice-error notice-success notice-warning notice-info')
			.addClass('notice-' + type)
			.html('<p>' + html + '</p>')
			.prop('hidden', false);
	}

	function hideStatus() {
		$('#f2000cs-xml-editor-status').prop('hidden', true);
	}

	function setBusy($button, busy) {
		if (busy) {
			$button.data('f2000cs-label', $button.text()).prop('disabled', true).text(t('loading'));
		} else {
			$button.prop('disabled', false).text($button.data('f2000cs-label') || $button.text());
		}
	}

	function getConditions() {
		return {
			only_in_stock: $('#f2000cs-xml-editor-instock').is(':checked') ? '1' : '0',
			min_price: parseFloat($('#f2000cs-xml-editor-min-price').val()) || 0,
			max_price: parseFloat($('#f2000cs-xml-editor-max-price').val()) || 0
		};
	}

	function getSearch() {
		return $('#f2000cs-xml-editor-search').val().trim();
	}

	// ------------------------------------------------------------- source

	/** Reset the editor back to its initial (empty) state so the user can
	 *  load a different source without refreshing the page. */
	function resetEditor() {
		state.token = '';
		state.categories = [];
		state.checked = {};
		state.expanded = {};
		state.childrenContainers = {};
		state.chevrons = {};
		state.excluded = {};
		state.extra = {};
		state.rows = {};
		state.page = 1;
		state.hasMore = false;
		state.productsTotal = 0;

		$('#f2000cs-xml-editor').prop('hidden', true);
		$('#f2000cs-xml-editor-reset').prop('hidden', true);
		$('#f2000cs-xml-editor-tree').empty();
		$('#f2000cs-xml-editor-products').html('<p class="f2000cs-xml-editor__empty">' + t('selectCategory') + '</p>');
		$('#f2000cs-xml-editor-result').prop('hidden', true).empty();
		hideStatus();
		updateSelectedCount();
		updateGenerateButtonState();
	}

	function loadSource() {
		var url = $('#f2000cs-xml-editor-url').val().trim();
		var fileInput = $('#f2000cs-xml-editor-file')[0];
		var formData = new FormData();

		formData.append('action', 'f2000cs_xml_editor');
		formData.append('sub', 'load');
		formData.append('nonce', cfg.nonce);

		if (fileInput && fileInput.files && fileInput.files.length) {
			formData.append('source_type', 'file');
			formData.append('xml_file', fileInput.files[0]);
		} else {
			if (!url) {
				showStatus('error', t('enterUrl'));
				return;
			}
			formData.append('source_type', 'url');
			formData.append('source_url', url);
		}

		hideStatus();
		setBusy($('#f2000cs-xml-editor-load'), true);

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				setBusy($('#f2000cs-xml-editor-load'), false);

				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? escapeHtml(response.data.message) : t('errorGeneric');
					console.error('f2000cs XML editor: load failed', response);
					showStatus('error', message);
					return;
				}

				state.token = response.data.token;
				state.categories = response.data.categories || [];
				state.checked = {};
				state.expanded = {};
				state.childrenContainers = {};
				state.chevrons = {};
				state.excluded = {};
				state.extra = {};
				state.rows = {};
				state.page = 1;
				state.hasMore = false;
				state.productsTotal = 0;

				$('#f2000cs-xml-editor').prop('hidden', false);
				$('#f2000cs-xml-editor-reset').prop('hidden', false);
				renderTree();
				updateSelectedCount();
				var loadedMsg = t('loaded') + ' ' + escapeHtml(response.data.source) + ' — ' + response.data.total_offers + ' ' + t('products');
				if (response.data.warning) {
					loadedMsg += ' <br><small>' + escapeHtml(response.data.warning) + '</small>';
				}
				showStatus('success', loadedMsg);
			},
			error: function () {
				setBusy($('#f2000cs-xml-editor-load'), false);
				showStatus('error', t('errorGeneric'));
			}
		});
	}

	// ------------------------------------------------------------- tree

	function childrenOf(categoryId) {
		return state.categories.filter(function (category) {
			return category.parent === categoryId;
		});
	}

	/**
	 * Collect all descendant category ids for a given category.
	 */
	function getAllDescendantIds(categoryId) {
		var ids = [];
		var queue = [categoryId];

		while (queue.length) {
			var current = queue.pop();

			state.categories.forEach(function (cat) {
				if (cat.parent === current) {
					ids.push(cat.id);
					queue.push(cat.id);
				}
			});
		}

		return ids;
	}

	/**
	 * Check or uncheck a category.  When checked, all descendants are
	 * visually checked and removed from the explicit selection (the parent
	 * covers them).  When unchecked, all descendants are unselected too.
	 */
	function setCategoryChecked(id, checked) {
		$('#f2000cs-xml-editor-tree')
			.find('.f2000cs-xml-editor__tree-check')
			.filter(function () {
				 // Use the raw attribute to keep type as string (jQuery .data()
				 // converts numeric strings to numbers).
				return $(this).attr('data-f2000cs-id') === id;
			})
			.prop('checked', checked);

		getAllDescendantIds(id).forEach(function (descendantId) {
			$('#f2000cs-xml-editor-tree')
				.find('.f2000cs-xml-editor__tree-check')
				.filter(function () {
					return $(this).attr('data-f2000cs-id') === descendantId;
				})
				.prop('checked', checked);
			// Parent covers children → remove explicit entry.
			delete state.checked[descendantId];
		});

		if (checked) {
			state.checked[id] = true;
		} else {
			delete state.checked[id];
		}
	}

	/**
	 * Set every tree checkbox to the given state (used for "select all").
	 */
	function updateTreeCheckboxes(checked) {
		$('#f2000cs-xml-editor-tree')
			.find('.f2000cs-xml-editor__tree-check')
			.prop('checked', checked);
	}

	function renderTree() {
		var $tree = $('#f2000cs-xml-editor-tree').empty();
		state.childrenContainers = {};
		state.chevrons = {};

		childrenOf('').forEach(function (category) {
			renderTreeNode(category, $tree);
		});

		$tree.find('.f2000cs-xml-editor__chevron').on('click', function (event) {
			event.stopPropagation();
			toggleCategoryExpanded($(this).data('f2000cs-id'));
		});

		$tree.find('.f2000cs-xml-editor__tree-check').on('change', function () {
			var id = $(this).attr('data-f2000cs-id');
			setCategoryChecked(id, $(this).is(':checked'));
			$('#f2000cs-xml-editor-cats-count').text(Object.keys(state.checked).length);
			updateSelectedCount();
			loadProducts(1);
		});
	}

	function renderTreeNode(category, $parent) {
		var children = childrenOf(category.id);
		var hasChildren = category.has_children || children.length > 0;

		var $item = $('<div class="f2000cs-xml-editor__tree-item"></div>');
		var $chevron = $('<span class="dashicons f2000cs-xml-editor__chevron" data-f2000cs-id="' + escapeHtml(category.id) + '"></span>');
		$chevron.addClass(hasChildren ? 'dashicons-arrow-right-alt2' : 'f2000cs-xml-editor__chevron--placeholder');

		var $check = $('<input type="checkbox" class="f2000cs-xml-editor__tree-check" data-f2000cs-id="' + escapeHtml(category.id) + '" />');
		var $icon = $('<span class="dashicons dashicons-category f2000cs-xml-editor__folder"></span>');
		var $name = $('<span class="f2000cs-xml-editor__tree-name"></span>').text(category.name);
		var $count = $('<span class="f2000cs-xml-editor__tree-count"></span>').text(category.count);

		// Categories with zero offers are shown as inactive — they canʼt
		// produce any products and would only waste a server round-trip.
		if (!category.count) {
			$check.prop('disabled', true);
			$item.addClass('f2000cs-xml-editor__tree-item--empty');
		}

		$item.append($chevron, $check, $icon, $name, $count);
		$parent.append($item);

		if (hasChildren) {
			var $children = $('<div class="f2000cs-xml-editor__tree-children is-hidden"></div>');
			children.forEach(function (child) {
				renderTreeNode(child, $children);
			});
			$parent.append($children);

			state.childrenContainers[category.id] = $children;
			state.chevrons[category.id] = $chevron;
		}
	}

	function toggleCategoryExpanded(id) {
		state.expanded[id] = !state.expanded[id];

		var $chevron = state.chevrons[id];
		if ($chevron) {
			$chevron.toggleClass('is-open', !!state.expanded[id]);
		}

		var $container = state.childrenContainers[id];
		if ($container) {
			$container.toggleClass('is-hidden', !state.expanded[id]);
		}
	}

	function setAllCategoriesExpanded(expanded) {
		state.expanded = {};

		Object.keys(state.childrenContainers).forEach(function (id) {
			state.expanded[id] = expanded;
			state.childrenContainers[id].toggleClass('is-hidden', !expanded);
		});

		Object.keys(state.chevrons).forEach(function (id) {
			state.chevrons[id].toggleClass('is-open', expanded);
		});
	}

	// ------------------------------------------------------------- products

	function loadProducts(page) {
		var categoryIds = Object.keys(state.checked);

		if (page === 1) {
			state.rows = {};
			state.page = 1;
			state.productsTotal = 0;
			$('#f2000cs-xml-editor-products').empty();
			$('#f2000cs-xml-editor-load-more').prop('hidden', true);
		}

		if (!categoryIds.length) {
			$('#f2000cs-xml-editor-products').html('<p class="f2000cs-xml-editor__empty">' + t('selectCategory') + '</p>');
			updateProductsCount(0);
			$('#f2000cs-xml-editor-select-all-products').prop('checked', false);
			return;
		}

		var conditions = getConditions();
		var search = getSearch();

		$('#f2000cs-xml-editor-products').append('<p class="f2000cs-xml-editor__loading">' + t('loading') + '</p>');

		$.post(cfg.ajaxUrl, {
			action: 'f2000cs_xml_editor',
			sub: 'offers',
			nonce: cfg.nonce,
			token: state.token,
			category_ids: categoryIds,
			page: page,
			search: search,
			only_in_stock: conditions.only_in_stock,
			min_price: conditions.min_price,
			max_price: conditions.max_price
		}, function (response) {
			$('#f2000cs-xml-editor-products .f2000cs-xml-editor__loading').remove();

			if (!response || !response.success) {
				console.error('f2000cs XML editor: offers request failed', {
					category_ids: categoryIds,
					page: page,
					search: search,
					response: response
				});
				showStatus('error', response && response.data && response.data.message ? escapeHtml(response.data.message) : t('errorGeneric'));
				return;
			}

			state.page = response.data.page;
			state.hasMore = response.data.has_more;
			state.productsTotal = response.data.total;

			renderProducts(response.data.offers);
			updateProductsCount(response.data.total);
			syncSelectAllProductsState();

			// Keep the "Показати ще" button inside the scroll area so the user
			// sees it immediately after the last product row.
			if (state.hasMore) {
				$('#f2000cs-xml-editor-products').append($('#f2000cs-xml-editor-load-more'));
			}
			$('#f2000cs-xml-editor-load-more').prop('hidden', !state.hasMore);

			if (!response.data.offers.length) {
				$('#f2000cs-xml-editor-products').append('<p class="f2000cs-xml-editor__empty">' + t('noProducts') + '</p>');
			}
		});
	}

	function renderProducts(offers) {
		var $list = $('#f2000cs-xml-editor-products');

		offers.forEach(function (offer) {
			state.rows[offer.id] = offer;

			var $row = $('<div class="f2000cs-xml-editor__product"></div>');
			var $check = $('<input type="checkbox" class="f2000cs-xml-editor__product-check" data-f2000cs-id="' + escapeHtml(offer.id) + '" />');
			$check.prop('checked', isOfferIncluded(offer));

			var $thumb = $('<img class="f2000cs-xml-editor__product-thumb" alt="" loading="lazy" />');
			if (offer.image) {
				$thumb.attr('src', offer.image);
			} else {
				$thumb.addClass('f2000cs-xml-editor__product-thumb--empty');
			}
			$thumb.on('error', function () {
				$(this).addClass('f2000cs-xml-editor__product-thumb--empty');
			});

			var $body = $('<div class="f2000cs-xml-editor__product-body"></div>');
			$body.append($('<div class="f2000cs-xml-editor__product-title"></div>').text(offer.title));

			var $meta = $('<div class="f2000cs-xml-editor__product-meta"></div>');
			if (offer.price) {
				$meta.append($('<span class="f2000cs-xml-editor__product-price"></span>').text(offer.price.toFixed(2) + ' грн'));
			}
			$meta.append(
				$('<span class="f2000cs-xml-editor__badge"></span>')
					.addClass(offer.available ? 'f2000cs-xml-editor__badge--in' : 'f2000cs-xml-editor__badge--out')
					.text(offer.available ? t('inStock') : t('outOfStock'))
			);
			if (offer.vendor_code) {
				$meta.append($('<span></span>').text(t('vendor') + ': ' + offer.vendor_code));
			}
			$meta.append($('<span></span>').text(t('sku') + ': ' + offer.id));

			$body.append($meta);
			$row.append($check, $thumb, $body);
			$list.append($row);
		});

		$list.find('.f2000cs-xml-editor__product-check').on('change', function () {
			var id = $(this).data('f2000cs-id');
			var offer = state.rows[id];

			if (!offer) {
				return;
			}

			if ($(this).is(':checked')) {
				if (state.checked[offer.category_id]) {
					delete state.excluded[id];
				} else {
					state.extra[id] = true;
				}
			} else {
				if (state.checked[offer.category_id]) {
					state.excluded[id] = true;
				} else {
					delete state.extra[id];
				}
			}

			updateSelectedCount();
			syncSelectAllProductsState();
		});
	}

	function isOfferIncluded(offer) {
		if (state.extra[offer.id]) {
			return true;
		}

		if (!isCategoryUnderChecked(offer.category_id)) {
			return false;
		}

		return !state.excluded[offer.id];
	}

	/**
	 * Whether a category (or any of its ancestors) is checked.  Uncategorized
	 * offers (category_id = '') map to the synthetic `__none__` entry.
	 */
	function isCategoryUnderChecked(categoryId) {
		if ('' === categoryId) {
			categoryId = '__none__';
		}

		while (categoryId) {
			if (state.checked[categoryId]) {
				return true;
			}

			var cat = state.categories.find(function (c) {
				return c.id === categoryId;
			});

			categoryId = cat ? cat.parent : '';
		}

		return false;
	}

	function syncSelectAllProductsState() {
		var ids = Object.keys(state.rows);

		if (!ids.length) {
			$('#f2000cs-xml-editor-select-all-products').prop('checked', false);
			return;
		}

		var allIncluded = ids.every(function (id) {
			return isOfferIncluded(state.rows[id]);
		});

		$('#f2000cs-xml-editor-select-all-products').prop('checked', allIncluded);
	}

	// ------------------------------------------------------------- select all

	function selectAllCategories() {
		var hasChecked = Object.keys(state.checked).length > 0;
		var shouldCheck = !hasChecked;

		state.checked = {};
		updateTreeCheckboxes(shouldCheck);

		if (shouldCheck) {
			// Check every root category; the cascade handles its descendants.
			state.categories.forEach(function (cat) {
				if (!cat.parent) {
					state.checked[cat.id] = true;
				}
			});
		}

		$('#f2000cs-xml-editor-select-all-cats').prop('checked', shouldCheck);
		$('#f2000cs-xml-editor-cats-count').text(Object.keys(state.checked).length);
		updateSelectedCount();
		loadProducts(1);
	}

	function setAllProductsSelected(selected) {
		if (selected) {
			state.excluded = {};
			syncProductCheckboxes();
			updateSelectedCount();
			syncSelectAllProductsState();
			return;
		}

		var categoryIds = Object.keys(state.checked);

		if (!categoryIds.length) {
			return;
		}

		var conditions = getConditions();

		$.post(cfg.ajaxUrl, {
			action: 'f2000cs_xml_editor',
			sub: 'offer_ids',
			nonce: cfg.nonce,
			token: state.token,
			category_ids: categoryIds,
			only_in_stock: conditions.only_in_stock,
			min_price: conditions.min_price,
			max_price: conditions.max_price
		}, function (response) {
			if (!response || !response.success) {
				showStatus('error', response && response.data && response.data.message ? escapeHtml(response.data.message) : t('errorGeneric'));
				return;
			}

			state.excluded = {};

			(response.data.offer_ids || []).forEach(function (id) {
				state.excluded[id] = true;
			});

			syncProductCheckboxes();
			updateSelectedCount();
			syncSelectAllProductsState();
		});
	}

	function syncProductCheckboxes() {
		$('#f2000cs-xml-editor-products').find('.f2000cs-xml-editor__product-check').each(function () {
			var offer = state.rows[$(this).data('f2000cs-id')];
			if (offer) {
				$(this).prop('checked', isOfferIncluded(offer));
			}
		});
	}

	// ------------------------------------------------------------- counters

	/**
	 * Estimated selected offer count.  Only counts a category when its
	 * parent is NOT also checked (parent.count already covers its subtree
	 * because the server expands descendants).
	 */
	function updateSelectedCount() {
		var count = 0;

		state.categories.forEach(function (cat) {
			if (!state.checked[cat.id]) {
				return;
			}

			// Skip if the parent is also checked — its count already covers us.
			if (cat.parent && state.checked[cat.parent]) {
				return;
			}

			count += cat.count;
		});

		count += Object.keys(state.extra).length - Object.keys(state.excluded).length;
		count = Math.max(0, count);

		$('#f2000cs-xml-editor-selected').text(count);
		updateGenerateButtonState();
	}

	function updateProductsCount(total) {
		var loaded = Object.keys(state.rows).length;
		$('#f2000cs-xml-editor-products-count').text(loaded + ' / ' + total);
	}

	function updateGenerateButtonState() {
		var hasSelection = Object.keys(state.checked).length > 0 || Object.keys(state.extra).length > 0;
		$('#f2000cs-xml-editor-generate').prop('disabled', !hasSelection);
	}

	// ------------------------------------------------------------- generate

	function generate() {
		var categoryIds = Object.keys(state.checked);
		var extraIds = Object.keys(state.extra);
		var excludedIds = Object.keys(state.excluded);

		if (!categoryIds.length && !extraIds.length) {
			return;
		}

		var conditions = getConditions();

		hideStatus();
		$('#f2000cs-xml-editor-result').prop('hidden', true);
		setBusy($('#f2000cs-xml-editor-generate'), true);

		$.post(cfg.ajaxUrl, {
			action: 'f2000cs_xml_editor',
			sub: 'generate',
			nonce: cfg.nonce,
			token: state.token,
			category_ids: categoryIds,
			extra_offer_ids: extraIds,
			excluded_ids: excludedIds,
			only_in_stock: conditions.only_in_stock,
			min_price: conditions.min_price,
			max_price: conditions.max_price,
			keep_oldprice: $('#f2000cs-xml-editor-keep-oldprice').is(':checked') ? '1' : '0'
		}, function (response) {
			setBusy($('#f2000cs-xml-editor-generate'), false);

			if (!response || !response.success) {
				console.error('f2000cs XML editor: generate failed', response);
				showStatus('error', response && response.data && response.data.message ? escapeHtml(response.data.message) : t('errorGeneric'));
				return;
			}

			var $result = $('#f2000cs-xml-editor-result').empty().prop('hidden', false);
			$result.append($('<p><strong></strong></p>').find('strong').text(t('ready') + ' ' + response.data.count + ' ' + t('products')).end());
			$result.append(
				$('<p></p>').append(
					$('<a class="button button-primary" target="_blank" rel="noopener"></a>')
						.attr('href', response.data.download_url)
						.text(t('download') + ' ' + response.data.file_name)
				)
			);

			showStatus('success', t('done'));
		});
	}

	// ------------------------------------------------------------- bindings

	function init() {
		$('#f2000cs-xml-editor-load').on('click', loadSource);
		$('#f2000cs-xml-editor-reset').on('click', resetEditor);
		$('#f2000cs-xml-editor-generate').on('click', generate);
		$('#f2000cs-xml-editor-load-more').on('click', function () {
			loadProducts(state.page + 1);
		});
		$('#f2000cs-xml-editor-select-all-cats').on('change', selectAllCategories);
		$('#f2000cs-xml-editor-select-all-products').on('change', function () {
			setAllProductsSelected($(this).is(':checked'));
		});
		$('#f2000cs-xml-editor-expand-all').on('click', function () {
			setAllCategoriesExpanded(true);
		});
		$('#f2000cs-xml-editor-collapse-all').on('click', function () {
			setAllCategoriesExpanded(false);
		});

		['#f2000cs-xml-editor-instock', '#f2000cs-xml-editor-min-price', '#f2000cs-xml-editor-max-price'].forEach(function (selector) {
			$(selector).on('change', function () {
				if (state.token) {
					loadProducts(1);
				}
			});
		});

		var searchTimer = null;
		$('#f2000cs-xml-editor-search').on('input', function () {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				if (state.token) {
					loadProducts(1);
				}
			}, 300);
		});
	}

	init();
})(jQuery);
