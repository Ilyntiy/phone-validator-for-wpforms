/**
 * Phone Validator for WPForms: browser check.
 *
 * Adds a hidden field to every WPForms form in the visitor's browser. The value is
 * "<load>-<send>": the times (seconds since 1970, base 36) the form was loaded and
 * the moment it is sent. The server decides what to do with it.
 */
( function () {
	'use strict';

	if ( typeof window === 'undefined' || typeof document === 'undefined' ) {
		return;
	}

	var config = window.wpfpvGuardConfig;
	if ( ! config || ! config.name ) {
		return;
	}

	var SELECTOR = 'form.wpforms-form';

	function encode() {
		return Math.floor( Date.now() / 1000 ).toString( 36 );
	}

	function getField( form ) {
		var input = null;
		var inputs = form.getElementsByTagName( 'input' );
		var i;

		for ( i = 0; i < inputs.length; i++ ) {
			if ( inputs[ i ].name === config.name ) {
				input = inputs[ i ];
				break;
			}
		}

		if ( ! input ) {
			var loaded = encode();

			input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = config.name;
			input.value = loaded + '-' + loaded;
			input.setAttribute( 'data-loaded', loaded );
			form.appendChild( input );
		}

		return input;
	}

	function refresh( form ) {
		var input = getField( form );
		var loaded = input.getAttribute( 'data-loaded' ) || encode();

		input.value = loaded + '-' + encode();
	}

	function scan( root ) {
		var forms = root.querySelectorAll ? root.querySelectorAll( SELECTOR ) : [];
		var i;

		for ( i = 0; i < forms.length; i++ ) {
			getField( forms[ i ] );
		}

		if ( root.matches && root.matches( SELECTOR ) ) {
			getField( root );
		}
	}

	function formOf( target ) {
		if ( ! target || ! target.closest ) {
			return null;
		}
		return target.closest( SELECTOR );
	}

	function init() {
		scan( document );

		// Forms added later (popups, page builders, AJAX).
		if ( 'MutationObserver' in window ) {
			new MutationObserver( function ( mutations ) {
				var i;
				var j;

				for ( i = 0; i < mutations.length; i++ ) {
					for ( j = 0; j < mutations[ i ].addedNodes.length; j++ ) {
						if ( 1 === mutations[ i ].addedNodes[ j ].nodeType ) {
							scan( mutations[ i ].addedNodes[ j ] );
						}
					}
				}
			} ).observe( document.documentElement, { childList: true, subtree: true } );
		}

		// Stamp the send time before WPForms reads the form (capture phase).
		document.addEventListener( 'submit', function ( event ) {
			var form = formOf( event.target );
			if ( form ) {
				refresh( form );
			}
		}, true );

		document.addEventListener( 'click', function ( event ) {
			var button = event.target && event.target.closest ? event.target.closest( 'button, input[type="submit"]' ) : null;
			var form = button ? formOf( button ) : null;

			if ( form && ( ! button.type || 'submit' === button.type ) ) {
				refresh( form );
			}
		}, true );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
