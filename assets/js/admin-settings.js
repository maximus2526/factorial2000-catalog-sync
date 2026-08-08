/**
 * Admin settings: price adjust controls, extra-supplier repeater,
 * Free/Pro locks, export menu soft-lock, trial countdown.
 */
( function () {
	'use strict';

	var cfg = ( typeof window.f2000csAdmin === 'object' && window.f2000csAdmin ) ? window.f2000csAdmin : {};

	function setupPriceAdjustControls( index ) {
		var typeSelect  = document.getElementById( 'f2000cs_price_adjust_type_' + index );
		var directionEl = document.getElementById( 'f2000cs_price_adjust_direction_' + index + '_wrap' );
		var valueInput  = document.getElementById( 'f2000cs_price_adjust_value_' + index );
		var unitEl      = document.getElementById( 'f2000cs_price_adjust_unit_' + index );
		var wrap        = document.getElementById( 'f2000cs_price_adjust_' + index + '_wrap' );

		if ( ! typeSelect || ! wrap ) {
			return;
		}

		var percentUnit  = wrap.getAttribute( 'data-percent-unit' ) || '%';
		var currencyUnit = wrap.getAttribute( 'data-currency-unit' ) || '';

		var updateForType = function () {
			var isMargin = 'margin' === typeSelect.value;
			var isFixed  = 'fixed' === typeSelect.value;

			if ( directionEl ) {
				directionEl.style.display = isMargin ? 'none' : '';
			}

			if ( unitEl ) {
				unitEl.textContent = isFixed ? currencyUnit : percentUnit;
			}

			if ( valueInput ) {
				if ( isMargin ) {
					valueInput.setAttribute( 'max', '99.99' );
				} else {
					valueInput.removeAttribute( 'max' );
				}
			}
		};

		typeSelect.addEventListener( 'change', updateForType );
		updateForType();
	}

	function toggleAdjustBlock( index ) {
		var checkbox = document.getElementById( 'f2000cs_skip_price_' + index );
		var wrap     = document.getElementById( 'f2000cs_price_adjust_' + index + '_wrap' );

		if ( ! checkbox || ! wrap ) {
			return;
		}

		var priceField = wrap.closest( '.f2000cs-supplier-card__price' );
		var row        = wrap.closest( 'tr' );
		var target     = priceField || row || wrap;

		var update = function () {
			target.style.display = checkbox.checked ? 'none' : '';
		};

		checkbox.addEventListener( 'change', update );
		update();
	}

	function bindSupplierCardControls( card ) {
		var slot = card.getAttribute( 'data-slot' );
		if ( ! slot || slot === '__INDEX__' ) {
			return;
		}

		setupPriceAdjustControls( slot );
		toggleAdjustBlock( slot );
	}

	function getUsedExtraSlots( list ) {
		var used = {};
		list.querySelectorAll( '.f2000cs-supplier-card:not([hidden])' ).forEach( function ( card ) {
			var slot = parseInt( card.getAttribute( 'data-slot' ), 10 );
			if ( slot >= 2 ) {
				used[ slot ] = true;
			}
		} );
		return used;
	}

	function getScanMax( root ) {
		var max = parseInt( root.getAttribute( 'data-scan-max' ) || '200', 10 );
		return max > 0 ? max : 200;
	}

	function nextFreeSlot( list, root ) {
		var used = getUsedExtraSlots( list );
		var scanMax = getScanMax( root || document.getElementById( 'f2000cs-suppliers' ) || { getAttribute: function () { return '200'; } } );
		for ( var i = 2; i <= scanMax; i++ ) {
			if ( ! used[ i ] ) {
				return i;
			}
		}
		return 0;
	}

	function updateAddButtonState( root, list ) {
		var addBtn = root.querySelector( '#f2000cs-add-supplier' );
		var canAdd = root.getAttribute( 'data-can-add' ) === '1';
		var next = nextFreeSlot( list, root );

		if ( ! addBtn ) {
			return;
		}

		if ( ! canAdd ) {
			addBtn.disabled = true;
			addBtn.setAttribute( 'aria-disabled', 'true' );
			return;
		}

		addBtn.disabled = ! next;
		addBtn.setAttribute( 'aria-disabled', next ? 'false' : 'true' );
	}

	function clearSupplierCard( card ) {
		card.querySelectorAll( 'input[type="text"], input[type="number"]' ).forEach( function ( el ) {
			el.value = el.type === 'number' ? '0' : '';
		} );

		card.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( el ) {
			el.checked = false;
		} );

		card.querySelectorAll( 'select' ).forEach( function ( el ) {
			el.value = 'markup';
		} );

		card.querySelectorAll( 'input[type="radio"][value="add"]' ).forEach( function ( el ) {
			el.checked = true;
		} );
	}

	/**
	 * Keep emptied fields in the form (hidden) so Settings API clears options on save.
	 * Slot 1 is never removable.
	 */
	function removeSupplierCard( card, root, list ) {
		var slot = parseInt( card.getAttribute( 'data-slot' ), 10 );
		if ( slot === 1 || card.getAttribute( 'data-removable' ) === '0' ) {
			return;
		}

		clearSupplierCard( card );
		card.hidden = true;
		card.classList.add( 'is-removed' );
		card.setAttribute( 'aria-hidden', 'true' );

		// Move to end so visible cards stay grouped.
		list.appendChild( card );
		updateAddButtonState( root, list );
	}

	function replaceIndexTokens( node, index ) {
		var attrs = [ 'name', 'id', 'for', 'data-slot' ];

		if ( node.nodeType === 1 ) {
			attrs.forEach( function ( attr ) {
				if ( node.hasAttribute( attr ) ) {
					node.setAttribute( attr, node.getAttribute( attr ).split( '__INDEX__' ).join( String( index ) ) );
				}
			} );

			if ( node.classList && node.classList.contains( 'f2000cs-supplier-card__title' ) ) {
				node.textContent = node.textContent.split( '__INDEX__' ).join( String( index ) );
			}

			if ( node.tagName === 'INPUT' && node.getAttribute( 'placeholder' ) ) {
				node.setAttribute(
					'placeholder',
					node.getAttribute( 'placeholder' ).split( '__INDEX__' ).join( String( index ) )
				);
			}
		}

		node.childNodes.forEach( function ( child ) {
			if ( child.nodeType === 1 ) {
				replaceIndexTokens( child, index );
			} else if ( child.nodeType === 3 && child.textContent.indexOf( '__INDEX__' ) !== -1 ) {
				child.textContent = child.textContent.split( '__INDEX__' ).join( String( index ) );
			}
		} );
	}

	function addSupplierCard( root, list ) {
		if ( root.getAttribute( 'data-can-add' ) !== '1' ) {
			return;
		}

		var index = nextFreeSlot( list, root );
		if ( ! index ) {
			return;
		}

		// Reuse a previously removed card for this slot if present.
		var existing = list.querySelector( '.f2000cs-supplier-card[data-slot="' + index + '"]' );
		if ( existing ) {
			clearSupplierCard( existing );
			existing.hidden = false;
			existing.classList.remove( 'is-removed' );
			existing.removeAttribute( 'aria-hidden' );
			list.appendChild( existing );
			bindSupplierCardControls( existing );
			updateAddButtonState( root, list );
			return;
		}

		var tpl = document.getElementById( 'f2000cs-supplier-card-template' );
		if ( ! tpl || ! tpl.content ) {
			return;
		}

		var clone = tpl.content.cloneNode( true );
		var card = clone.querySelector( '.f2000cs-supplier-card' );
		if ( ! card ) {
			return;
		}

		replaceIndexTokens( card, index );
		list.appendChild( card );
		bindSupplierCardControls( card );
		updateAddButtonState( root, list );
	}

	function setupSuppliersRepeater() {
		var root = document.getElementById( 'f2000cs-suppliers' );
		if ( ! root ) {
			return;
		}

		var list = document.getElementById( 'f2000cs-suppliers-list' );
		var addBtn = document.getElementById( 'f2000cs-add-supplier' );
		if ( ! list ) {
			return;
		}

		list.querySelectorAll( '.f2000cs-supplier-card' ).forEach( function ( card ) {
			bindSupplierCardControls( card );
		} );

		list.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.f2000cs-supplier-card__remove' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();
			var card = btn.closest( '.f2000cs-supplier-card' );
			if ( card ) {
				removeSupplierCard( card, root, list );
			}
		} );

		if ( addBtn ) {
			addBtn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( addBtn.disabled || root.getAttribute( 'data-can-add' ) !== '1' ) {
					return;
				}
				addSupplierCard( root, list );
			} );
		}

		updateAddButtonState( root, list );
	}

	function lockProRows() {
		if ( cfg.isPro || ! Array.isArray( cfg.lockedIds ) ) {
			return;
		}

		var tip = cfg.proTip || 'Pro';

		cfg.lockedIds.forEach( function ( id ) {
			var row = document.getElementById( id );
			if ( ! row || row.tagName !== 'TR' ) {
				return;
			}

			row.classList.add( 'f2000cs-pro-locked' );
			row.setAttribute( 'data-pro-tip', tip );
			row.setAttribute( 'title', tip );

			row.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( el ) {
				el.setAttribute( 'disabled', 'disabled' );
				el.setAttribute( 'tabindex', '-1' );
			} );
		} );
	}

	function lockExportMenu() {
		if ( cfg.isPro ) {
			return;
		}

		var tip = cfg.exportTip || 'В Pro';
		var links = document.querySelectorAll( '#adminmenu a[href*="page=f2000cs-export"]' );

		links.forEach( function ( link ) {
			var item = link.closest( 'li' ) || link;
			item.classList.add( 'f2000cs-menu-pro-locked' );
			item.setAttribute( 'data-pro-tip', tip );
			item.setAttribute( 'title', tip );
			link.setAttribute( 'title', tip );
			link.setAttribute( 'aria-disabled', 'true' );

			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
			} );
		} );
	}

	function formatTrialLabel( days, hours, minutes ) {
		var template = ( cfg.i18n && cfg.i18n.trialLeft ) ? cfg.i18n.trialLeft : 'залишилось %1$d дн. %2$d год. %3$d хв.';
		return template
			.replace( '%1$d', String( days ) )
			.replace( '%2$d', String( hours ) )
			.replace( '%3$d', String( minutes ) );
	}

	function startTrialCountdown() {
		var banner = document.querySelector( '.f2000cs-trial-countdown' );
		if ( ! banner ) {
			return;
		}

		var ends = parseInt( banner.getAttribute( 'data-ends' ) || cfg.trialEnds || '0', 10 );
		if ( ! ends ) {
			return;
		}

		var label = banner.querySelector( '.f2000cs-trial-countdown__label' );
		if ( ! label ) {
			return;
		}

		var tick = function () {
			var remaining = ends - Math.floor( Date.now() / 1000 );
			if ( remaining <= 0 ) {
				label.textContent = ( cfg.i18n && cfg.i18n.trialEnded ) ? cfg.i18n.trialEnded : 'Тріал закінчився';
				banner.classList.remove( 'notice-warning' );
				banner.classList.add( 'notice-info' );
				return;
			}

			var days = Math.floor( remaining / 86400 );
			var hours = Math.floor( ( remaining % 86400 ) / 3600 );
			var minutes = Math.floor( ( remaining % 3600 ) / 60 );
			label.textContent = formatTrialLabel( days, hours, minutes );
			window.setTimeout( tick, 30000 );
		};

		tick();
	}

	function setupImageQualitySlider() {
		var range = document.getElementById( 'f2000cs_img_quality_range' );
		var label = document.getElementById( 'f2000cs_img_quality_value' );
		if ( ! range || ! label ) {
			return;
		}

		var sync = function () {
			label.textContent = range.value;
		};

		range.addEventListener( 'input', sync );
		range.addEventListener( 'change', sync );
		sync();
	}

	function setupImageMaxDimensionControls() {
		var range = document.getElementById( 'f2000cs_img_max_dimension_range' );
		var number = document.getElementById( 'f2000cs_img_max_dimension_number' );
		if ( ! range || ! number ) {
			return;
		}

		// Slider is the quick-set control; the number input stays authoritative
		// so exact values (e.g. 1200) can be typed even between slider steps.
		range.addEventListener( 'input', function () {
			number.value = range.value;
		} );

		number.addEventListener( 'input', function () {
			var value = parseInt( number.value, 10 );
			if ( ! isNaN( value ) ) {
				range.value = value;
			}
		} );
	}

	function setupSyncModeWarning() {
		var radios = document.getElementsByName( 'use_background' );
		var warning = document.getElementById( 'f2000cs-sync-warning' );
		if ( ! radios.length || ! warning ) {
			return;
		}

		var update = function () {
			var syncMode = false;
			for ( var i = 0; i < radios.length; i++ ) {
				if ( radios[ i ].checked ) {
					syncMode = ( radios[ i ].value === 'no' );
				}
			}
			warning.hidden = ! syncMode;
		};

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', update );
		}
		update();
	}

	function setupDocsScrollSpy() {
		var nav = document.getElementById( 'f2000cs-docs-nav' );
		if ( ! nav ) {
			return;
		}

		var links = nav.querySelectorAll( '.f2000cs-docs-nav__item' );
		var sections = [];

		links.forEach( function ( link ) {
			var href = link.getAttribute( 'href' );
			if ( href && href[ 0 ] === '#' ) {
				var section = document.getElementById( href.slice( 1 ) );
				if ( section ) {
					sections.push( { link: link, section: section } );
				}
			}
		} );

		if ( ! sections.length ) {
			return;
		}

		// Sort by DOM order so we can find the last visible section.
		sections.sort( function ( a, b ) {
			return a.section.compareDocumentPosition( b.section ) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
		} );

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					entry.target._f2000cs_isect = entry.isIntersecting;
				} );

				var active = null;
				// Pick the first visible section (scrolling down) or the last visible (scrolling up).
				for ( var i = 0; i < sections.length; i++ ) {
					if ( sections[ i ].section._f2000cs_isect ) {
						active = sections[ i ].link;
						break;
					}
				}

				if ( active ) {
					links.forEach( function ( l ) { l.classList.remove( 'is-active' ); } );
					active.classList.add( 'is-active' );

					// Expand parent if not already visible in scroll.
					var parent = active.parentElement;
					if ( parent && parent.tagName === 'UL' ) {
						var parentLink = parent.previousElementSibling;
						if ( parentLink && parentLink.matches( '.f2000cs-docs-nav__item' ) ) {
							parentLink.classList.add( 'is-active' );
						}
					}

					// Scroll nav to keep active item visible.
					active.scrollIntoView( { block: 'nearest', behavior: 'instant' } );
				}
			},
			{ rootMargin: '-10% 0px -80% 0px', threshold: 0 }
		);

		sections.forEach( function ( s ) {
			s.section._f2000cs_isect = false;
			observer.observe( s.section );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		setupSuppliersRepeater();
		lockProRows();
		lockExportMenu();
		startTrialCountdown();
		setupImageQualitySlider();
		setupImageMaxDimensionControls();
		setupSyncModeWarning();
		setupDocsScrollSpy();
	} );
} )();
