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
		};

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

			jQuery( '#hm-2fa-new-secret-fields' ).show();
		}

		self.hideNewSecretFields = function() {

			jQuery( '#hm-2fa-new-secret-fields' ).hide();
		}

		self.setAjaxLoading = function( bool ) {

			if ( bool ) {

				jQuery( '#hm-2fa-secret-settings-fields' ).hide();
				jQuery( '#hm-2fa-secret-settings-ajax-loading' ).show();

			} else {

				jQuery( '#hm-2fa-secret-settings-ajax-loading' ).hide();
				jQuery( '#hm-2fa-secret-settings-fields' ).show();
			}

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

		self.generateNewSecret = function() {

			self.setAjaxLoading( true );

			var data = {
				action  : 'hm_2fa_generate_secret_key'
			};

			jQuery.post( ajaxurl, data, function( response ) {

				self.setAjaxLoading( false );

				if ( ! response )
					return;

				self.setQRCodeHtml( response.qr_code );

				self.setSingleUseSecretsHtml( response.single_use_secrets );

				self.showNewSecretFields();

				jQuery( '#hm-2fa-secret' ).val( response.secret ).focus();

				jQuery( '#hm-2fa-generate-secret' ).hide();

			} , 'json' );
		}
	};

	hm2FAFormController.init();
} );