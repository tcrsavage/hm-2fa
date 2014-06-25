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

			jQuery( '#hm-2fa-generate-secret' ).click( function() {

				self.generateNewSecret();
			} );

			jQuery( '.hm-2fa-new-secret-step-forward' ).click( function( e ) {

				e.preventDefault();

				var step = parseInt( jQuery( this ).val() ) + 1;

				self.goToNewSecretStep( step );

			} );

			jQuery( '.hm-2fa-new-secret-step-back' ).click( function( e ) {

				e.preventDefault();

				var step = parseInt( jQuery( this ).val() ) - 1;

				self.goToNewSecretStep( step );
			} );

			jQuery( '#hm-2fa-secret-verify-submit' ).click( function( e ) {

				e.preventDefault();

				var successEle = jQuery( '#hm-2fa-secret-verify-success' );
				var errorEle = jQuery( '#hm-2fa-secret-verify-error' );

				successEle.hide();
				errorEle.hide();

				self.verifySecret( function( payload ) {

					if ( payload && payload.verified ) {

						successEle.show();
						self.confirmSecret();

					} else {
						errorEle.show();
					}

				} );

			} );

		};

		self.confirmSecret = function() {

			jQuery( '#hm-2fa-secret-confirm').val( jQuery( '#hm-2fa-secret').val() );

			jQuery( '#hm-2fa-new-secret-step-2-forward' ).removeAttr( 'disabled' );
		}

		self.goToNewSecretStep = function( step ) {

			jQuery( '.hm-2fa-new-secret-step' ).hide();

			var ele = jQuery( '#hm-2fa-new-secret-step-' + step ).show();

			if ( ele.find( 'input' ) ) {

				ele.find( 'input' ).first().focus();

			} else if ( ele.find( 'button' ) ) {

				ele.find( 'button' ).last().focus();
			}
		}

		self.isEnabled = function() {

			return jQuery( '#hm-2fa-is-enabled' ).is( ':checked' );
		}

		self.showSecretSettings = function() {

			jQuery( '#hm-2fa-secret-settings' ).show();
		}

		self.hideSecretSettings = function() {

			jQuery( '#hm-2fa-secret-settings' ).hide();
		}

		self.showNewSecretFields = function() {

			jQuery( '#hm-2fa-new-secret-steps' ).show();
		}

		self.hideNewSecretFields = function() {

			jQuery( '#hm-2fa-new-secret-steps' ).hide();
		}

		self.setQRCodeHtml = function( code ) {

			jQuery( '#hm-2fa-qr-code' ).html( '' ).qrcode( code );
		}

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
		}

		self.verifySecret = function( callback ) {

			jQuery( '#hm-2fa-new-secret-step-2' ).find( '#hm-2fa-secret-verify-spinner' ).css( 'display', 'inline-block' );

			var data = {
				action               : 'hm_2fa_ajax_verify_secret_key',
				hm_2fa_secret        : jQuery( '#hm-2fa-secret').val(),
				hm_2fa_secret_verify : jQuery( '#hm-2fa-secret-verify').val()

			};

			jQuery.post( ajaxurl, data, function( response ) {

				jQuery( '#hm-2fa-new-secret-step-2' ).find( '#hm-2fa-secret-verify-spinner' ).hide();

				if ( typeof( callback ) !== 'undefined' ) {

					callback( response )
				}

			}, 'json' );

		}

		self.generateNewSecret = function() {

			jQuery( '#hm-2fa-secret-settings-fields' ).hide();
			jQuery( '#hm-2fa-secret-settings-ajax-loading' ).show();

			var data = {
				action  : 'hm_2fa_generate_secret_key'
			};

			jQuery.post( ajaxurl, data, function( response ) {

				jQuery( '#hm-2fa-secret-settings-ajax-loading' ).hide();
				jQuery( '#hm-2fa-secret-settings-fields' ).show();

				if ( ! response )
					return;

				self.setQRCodeHtml( response.qr_code );

				self.setSingleUseSecretsHtml( response.single_use_secrets );

				self.showNewSecretFields();

				jQuery( '#hm-2fa-secret' ).val( response.secret).focus();

				jQuery( '#hm-2fa-generate-secret' ).hide();

			}, 'json' );
		}
	};

	hm2FAFormController.init();
} );