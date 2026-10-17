/**
 * Phone Validator for WPForms - admin screens.
 */
( function () {
	'use strict';

	var config = window.wpfpvAdmin || {};

	// Confirm destructive buttons with a translated message.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-wpfpv-confirm]' ) : null;
		var message;

		if ( ! button ) {
			return;
		}

		message = 'reset' === button.getAttribute( 'data-wpfpv-confirm' ) ? config.resetConfirm : config.clearConfirm;
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	// Random honeypot field name: a letter followed by lowercase letters and digits.
	var generate = document.getElementById( 'wpfpv-hp-generate' );
	var nameInput = document.getElementById( 'wpfpv-hp-name' );

	if ( generate && nameInput ) {
		generate.addEventListener( 'click', function () {
			var letters = 'abcdefghijklmnopqrstuvwxyz';
			var chars = letters + '0123456789';
			var bytes = new Uint8Array( 11 );
			var name = '';
			var i;

			if ( window.crypto && window.crypto.getRandomValues ) {
				window.crypto.getRandomValues( bytes );
			} else {
				for ( i = 0; i < bytes.length; i++ ) {
					bytes[ i ] = Math.floor( Math.random() * 256 );
				}
			}

			name = letters.charAt( bytes[ 0 ] % letters.length );
			for ( i = 1; i < bytes.length; i++ ) {
				name += chars.charAt( bytes[ i ] % chars.length );
			}

			nameInput.value = name;
			nameInput.focus();
		} );
	}

	// Proxy details are only needed for the "another proxy" option.
	var mode = document.getElementById( 'wpfpv-proxy' );
	var rows = document.querySelectorAll( '.wpfpv-custom-proxy' );

	function toggleProxy() {
		var i;
		for ( i = 0; i < rows.length; i++ ) {
			rows[ i ].hidden = ! mode || 'custom' !== mode.value;
		}
	}

	if ( mode && rows.length ) {
		mode.addEventListener( 'change', toggleProxy );
		toggleProxy();
	}

	// Every card has its own Save button for the one form. Remember which card was used
	// and bring it back into view after the page reloads, so nobody has to scroll again.
	var cards = document.querySelectorAll( '[data-wpfpv-card]' );
	var storageKey = 'wpfpvCard';
	var i;

	function remember( index ) {
		try {
			window.sessionStorage.setItem( storageKey, String( index ) );
		} catch ( e ) {
			// Storage can be unavailable; the page then simply opens at the top.
		}
	}

	for ( i = 0; i < cards.length; i++ ) {
		( function ( index ) {
			var button = cards[ index ].querySelector( '.wpfpv-card__footer button' );
			if ( button ) {
				button.addEventListener( 'click', function () {
					remember( index );
				} );
			}
		}( i ) );
	}

	try {
		var saved = window.sessionStorage.getItem( storageKey );
		window.sessionStorage.removeItem( storageKey );

		if ( null !== saved && cards[ Number( saved ) ] && /[?&]settings-updated=/.test( window.location.search ) ) {
			cards[ Number( saved ) ].scrollIntoView( { block: 'center' } );
		}
	} catch ( e ) {
		// Ignore: scrolling back is only a convenience.
	}

	// A switched-off card fades out at once, before the form is saved.
	var toggles = document.querySelectorAll( '.wpfpv-card--toggle .wpfpv-card__head input[type="checkbox"]' );

	function syncCard( input ) {
		var card = input.closest( '.wpfpv-card--toggle' );
		if ( card ) {
			card.classList.toggle( 'is-off', ! input.checked );
		}
	}

	for ( i = 0; i < toggles.length; i++ ) {
		toggles[ i ].addEventListener( 'change', function ( event ) {
			syncCard( event.target );
		} );
	}
}() );
