jQuery( document ).ready( function() {

	var hm2FAFormController = new function() {

		var self = this;

		self.init =  function() {

			//Show the secret code settings on init if 2fa is enabled
			if ( self.isEnabled() ) {
				self.showSecretSettings();
			}

			//Show/hide the secret code settings depending on whether or not 2fa is enabled
			jQuery( '#hm-2fa-is-enabled' ).on( 'change', function() {

				if ( jQuery( this ).is( ':checked' ) ) {

					self.showSecretSettings();

				} else {

					self.hideSecretSettings();
				}
			} );

			//Catch a generate secret button click and generate the new secret + run ui change scripts
			jQuery( '#hm-2fa-generate-secret' ).click( function() {

				jQuery( '#hm-2fa-secret-settings-fields' ).hide();
				jQuery( '#hm-2fa-secret-settings-ajax-loading' ).show();

				self.generateNewSecret( function( response ) {

					jQuery( '#hm-2fa-secret-settings-ajax-loading' ).hide();
					jQuery( '#hm-2fa-secret-settings-fields' ).show();

					self.setQRCodeHtml( response.qr_code );

					self.setSingleUseSecretsHtml( response.single_use_secrets );

					self.showNewSecretFields();

					jQuery( '#hm-2fa-secret' ).val( response.secret).focus();

					jQuery( '#hm-2fa-generate-secret' ).hide();

				} );
			} );

			//Catch a step forward button click
			jQuery( '.hm-2fa-new-secret-step-forward' ).click( function( e ) {

				e.preventDefault();

				var step = parseInt( jQuery( this ).val() ) + 1;

				self.goToNewSecretStep( step );

			} );

			//Catch a step back button click
			jQuery( '.hm-2fa-new-secret-step-back' ).click( function( e ) {

				e.preventDefault();

				var step = parseInt( jQuery( this ).val() ) - 1;

				self.goToNewSecretStep( step );
			} );

			//Catch a verify key button click
			jQuery( '#hm-2fa-secret-verify-submit' ).click( function( e ) {

				e.preventDefault();

				var successEle = jQuery( '#hm-2fa-secret-verify-success' );
				var errorEle = jQuery( '#hm-2fa-secret-verify-error' );

				successEle.hide();
				errorEle.hide();

				jQuery( '#hm-2fa-new-secret-step-2' ).find( '#hm-2fa-secret-verify-spinner' ).css( 'display', 'inline-block' );

				self.verifySecret( function( payload ) {

					if ( payload && payload.verified ) {

						successEle.show();
						self.confirmSecret();
						self.goToNewSecretStep( 3 );

					} else {
						errorEle.show();
					}

					jQuery( '#hm-2fa-new-secret-step-2' ).find( '#hm-2fa-secret-verify-spinner' ).hide();

				} );

			} );

		};

		//Confirm the secret code if it's been verified, this stops any issues with partially completed 2fa regeneration
		self.confirmSecret = function() {

			jQuery( '#hm-2fa-secret-confirm').val( jQuery( '#hm-2fa-secret').val() );

			jQuery( '#hm-2fa-new-secret-step-2-forward' ).removeAttr( 'disabled' );
		};

		//Go to the next step in setting up a new secret
		self.goToNewSecretStep = function( step ) {

			jQuery( '.hm-2fa-new-secret-step' ).hide();

			var ele = jQuery( '#hm-2fa-new-secret-step-' + step ).show();

			if ( ele.find( 'input' ) ) {

				ele.find( 'input' ).first().focus();

			} else if ( ele.find( 'button' ) ) {

				ele.find( 'button' ).last().focus();
			}
		};

		//Is 2fa enabled for this user
		self.isEnabled = function() {

			return jQuery( '#hm-2fa-is-enabled' ).is( ':checked' );
		};

		//Show the 'secret settings' fields
		self.showSecretSettings = function() {

			jQuery( '#hm-2fa-secret-settings' ).show();
		};

		//Hide the 'secret settings' fields
		self.hideSecretSettings = function() {

			jQuery( '#hm-2fa-secret-settings' ).hide();
		};

		//Show new secret fields
		self.showNewSecretFields = function() {

			jQuery( '#hm-2fa-new-secret-steps' ).show();
		};

		//Hide the new secret fields
		self.hideNewSecretFields = function() {

			jQuery( '#hm-2fa-new-secret-steps' ).hide();
		};

		//Set the qr code container html to show the specified qr code
		self.setQRCodeHtml = function( code ) {

			jQuery( '#hm-2fa-qr-code' ).html( '' ).qrcode( code );
		};

		//Load the specified single use secrets into their container
		self.setSingleUseSecretsHtml = function( secrets ) {

			var ul = jQuery( '<ul></ul>' );

			var container = jQuery( '#hm-2fa-single-use-secrets' );

			jQuery.each( secrets, function( index, secret ) {

				var input = jQuery( '<input type="hidden" name="hm_2fa_single_use_secrets[]" />' ).val( secret );
				var li    = jQuery( '<li></li>' ).append( '<span>' + secret + '</span>').append( input )

				ul.append( li );
			} );

			container.find( 'ul' ).remove();
			container.show().append( ul );
		};

		//Verify a code against a secret key via ajax
		self.verifySecret = function( callback ) {

			var data = {
				action               : 'hm_2fa_ajax_verify_secret_key',
				hm_2fa_secret        : jQuery( '#hm-2fa-secret').val(),
				hm_2fa_secret_verify : jQuery( '#hm-2fa-secret-verify').val()

			};

			jQuery.post( ajaxurl, data, function( response ) {

				if ( typeof( callback ) !== 'undefined' ) {

					callback( response )
				}

			}, 'json' );

		};

		//Generate a new secret key, single use keys and qr code via ajax
		self.generateNewSecret = function( callback ) {

			var data = {
				action  : 'hm_2fa_generate_secret_key'
			};

			jQuery.post( ajaxurl, data, function( response ) {

				if ( typeof( callback ) !== 'undefined' ) {

					callback( response );
				}

			}, 'json' );
		}
	};

	hm2FAFormController.init();
} );