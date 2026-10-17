/**
 * Phone Validator for WPForms - input mask.
 *
 * Mask syntax: "*" is any digit, digits are fixed, everything else is formatting,
 * e.g. "+1 *** *** ****". The same value is verified again on the server.
 */
( function ( root, factory ) {
	'use strict';
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.WPFPVMask = factory();
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	/**
	 * Parse a mask string into tokens.
	 *
	 * @param {string} raw Mask such as "+1 *** *** ****".
	 * @return {{raw: string, tokens: Array, prefix: string, code: string}} Parsed mask.
	 */
	function parseMask( raw ) {
		var tokens = [];
		var prefix = '';
		var code = '';
		var prefixOpen = true;
		var codeOpen = true;
		var i, ch;

		for ( i = 0; i < raw.length; i++ ) {
			ch = raw.charAt( i );
			if ( ch === '*' ) {
				tokens.push( { type: 'any' } );
				prefixOpen = false;
				codeOpen = false;
			} else if ( /\d/.test( ch ) ) {
				tokens.push( { type: 'fixed', value: ch } );
				if ( prefixOpen ) {
					prefix += ch;
				}
				if ( codeOpen ) {
					code += ch;
				}
			} else {
				tokens.push( { type: 'literal', value: ch } );
				if ( code ) {
					codeOpen = false;
				}
			}
		}

		return { raw: raw, tokens: tokens, prefix: prefix, code: code };
	}

	/**
	 * Apply "from => to" rules to the digits typed without a plus sign.
	 *
	 * @param {string} digits Digits.
	 * @param {Array}  rules  Array of [from, to] pairs.
	 * @return {string} Converted digits.
	 */
	function applyRules( digits, rules ) {
		var i;
		for ( i = 0; i < rules.length; i++ ) {
			if ( digits.indexOf( rules[ i ][ 0 ] ) === 0 ) {
				return rules[ i ][ 1 ] + digits.slice( rules[ i ][ 0 ].length );
			}
		}
		return digits;
	}

	/**
	 * First mask whose fixed leading digits agree with the typed digits.
	 *
	 * @param {Array}  masks  Parsed masks.
	 * @param {string} digits Typed digits.
	 * @return {Object|null} Mask or null.
	 */
	function chooseMask( masks, digits ) {
		var i, prefix;
		for ( i = 0; i < masks.length; i++ ) {
			prefix = masks[ i ].prefix;
			if ( prefix.indexOf( digits ) === 0 || digits.indexOf( prefix ) === 0 ) {
				return masks[ i ];
			}
		}
		return null;
	}

	/**
	 * Format a value with the masks.
	 *
	 * @param {string} value Current field value.
	 * @param {Array}  masks Parsed masks (the first one is the default).
	 * @param {Array}  rules Conversion rules, applied only to numbers typed without "+".
	 * @return {string} Formatted value.
	 */
	function format( value, masks, rules ) {
		var plus = value.charAt( 0 ) === '+';
		var digits = value.replace( /\D/g, '' );
		var mask, out, pending, di, i, token, digit;

		if ( ! masks.length ) {
			return value;
		}
		if ( ! digits ) {
			return plus ? '+' : '';
		}

		if ( ! plus ) {
			digits = applyRules( digits, rules || [] );
		}

		mask = chooseMask( masks, digits );
		if ( ! mask ) {
			// The number was typed without the country code: use the first mask.
			mask = masks[ 0 ];
			digits = mask.code + digits;
		}

		out = '';
		pending = '';
		di = 0;

		for ( i = 0; i < mask.tokens.length; i++ ) {
			token = mask.tokens[ i ];

			if ( token.type === 'literal' ) {
				pending += token.value;
				continue;
			}

			// Skip typed digits that cannot stand at a fixed position.
			while ( di < digits.length && token.type === 'fixed' && digits.charAt( di ) !== token.value ) {
				di++;
			}
			if ( di >= digits.length ) {
				break;
			}

			digit = digits.charAt( di );
			out += pending + digit;
			pending = '';
			di++;
		}

		return out;
	}

	/**
	 * Index after the n-th digit of a string.
	 *
	 * @param {string} text Text.
	 * @param {number} n    Digit count.
	 * @return {number} Caret position.
	 */
	function caretAfterDigits( text, n ) {
		var count = 0;
		var i;
		if ( n <= 0 ) {
			return 0;
		}
		for ( i = 0; i < text.length; i++ ) {
			if ( /\d/.test( text.charAt( i ) ) ) {
				count++;
				if ( count === n ) {
					return i + 1;
				}
			}
		}
		return text.length;
	}

	/**
	 * Bind the mask to a field.
	 *
	 * @param {HTMLInputElement} input  Field.
	 * @param {Array}            masks  Parsed masks.
	 * @param {Array}            rules  Conversion rules.
	 */
	function attach( input, masks, rules ) {
		var longest = 0;
		var i;

		for ( i = 0; i < masks.length; i++ ) {
			longest = Math.max( longest, masks[ i ].raw.length );
		}

		input.setAttribute( 'inputmode', 'tel' );
		input.setAttribute( 'maxlength', String( longest ) );
		if ( ! input.getAttribute( 'placeholder' ) ) {
			input.setAttribute( 'placeholder', masks[ 0 ].raw.replace( /\*/g, '_' ) );
		}

		function onInput() {
			var value = input.value;
			var position = input.selectionStart;
			var atEnd = position === null || position === value.length;
			var digitsBefore = value.slice( 0, position === null ? 0 : position ).replace( /\D/g, '' ).length;
			var formatted = format( value, masks, rules );

			if ( formatted === value ) {
				return;
			}

			input.value = formatted;
			if ( document.activeElement === input && typeof input.setSelectionRange === 'function' ) {
				position = atEnd ? formatted.length : caretAfterDigits( formatted, digitsBefore );
				input.setSelectionRange( position, position );
			}
		}

		// Backspace over a separator removes the digit before it instead of doing nothing.
		function onKeyDown( event ) {
			var isBackspace = event.key === 'Backspace' || event.keyCode === 8;
			var start = input.selectionStart;
			var p;

			if ( ! isBackspace || start === null || start !== input.selectionEnd || start === 0 ) {
				return;
			}

			p = start;
			while ( p > 0 && /\D/.test( input.value.charAt( p - 1 ) ) ) {
				p--;
			}
			if ( p !== start ) {
				input.setSelectionRange( p, p );
			}
		}

		input.addEventListener( 'input', onInput );
		input.addEventListener( 'keydown', onKeyDown );

		if ( input.value ) {
			onInput();
		}
	}

	/**
	 * Is this a plain phone input that the mask may control?
	 * Smart (country selector) and WPForms masked fields format themselves.
	 *
	 * @param {HTMLInputElement} input Field.
	 * @return {boolean} True when the mask can be attached.
	 */
	function isPlainPhoneInput( input ) {
		return ! (
			input.hasAttribute( 'data-wpfpv-mask' ) ||
			input.hasAttribute( 'data-inputmask-mask' ) ||
			input.classList.contains( 'wpforms-smart-phone-field' ) ||
			( input.closest && input.closest( '.iti' ) )
		);
	}

	/**
	 * Find the phone inputs of WPForms forms and attach the mask.
	 *
	 * @param {Object} config { masks: string[], rules: Array }.
	 */
	function init( config ) {
		var masks, inputs, i;

		if ( ! config || ! config.masks || ! config.masks.length ) {
			return;
		}

		masks = config.masks.map( parseMask );
		inputs = document.querySelectorAll( '.wpforms-field-phone input[type="tel"]' );

		for ( i = 0; i < inputs.length; i++ ) {
			if ( isPlainPhoneInput( inputs[ i ] ) ) {
				inputs[ i ].setAttribute( 'data-wpfpv-mask', '1' );
				attach( inputs[ i ], masks, config.rules || [] );
			}
		}
	}

	return {
		parseMask: parseMask,
		applyRules: applyRules,
		format: format,
		caretAfterDigits: caretAfterDigits,
		init: init
	};
} ) );

( function () {
	'use strict';

	if ( typeof window === 'undefined' || typeof document === 'undefined' || ! window.WPFPVMask ) {
		return;
	}

	var api = window.WPFPVMask;

	function start() {
		api.init( window.wpfpvMaskConfig );
	}

	// Run after WPForms has initialised its own fields (smart phone fields are skipped).
	if ( document.readyState === 'complete' ) {
		start();
	} else {
		window.addEventListener( 'load', start );
	}

	if ( window.jQuery ) {
		window.jQuery( document ).on( 'wpformsReady', start );
	}
}() );
