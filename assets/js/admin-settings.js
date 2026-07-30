/**
 * Admin settings page behaviour for the per-supplier price adjustment controls.
 *
 * - The whole price adjustment block is shown only when price updates are
 *   allowed (the "Не оновлювати ціну" checkbox is unchecked).
 * - The "Додати / Відняти" direction switch is hidden for the "Маржа" type,
 *   since margin can only ever increase the price (never negative).
 * - The unit label next to the value field switches between "%" and the
 *   store currency symbol depending on the selected adjustment type.
 * - The value field gets a max="99.99" constraint while "Маржа" is selected,
 *   since margin must stay below 100%.
 */
( function () {
	'use strict';

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

		var row = wrap.closest( 'tr' );

		var update = function () {
			if ( row ) {
				row.style.display = checkbox.checked ? 'none' : '';
			} else {
				wrap.style.display = checkbox.checked ? 'none' : '';
			}
		};

		checkbox.addEventListener( 'change', update );
		update();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		for ( var i = 1; i <= 5; i++ ) {
			setupPriceAdjustControls( i );
			toggleAdjustBlock( i );
		}
	} );
} )();
