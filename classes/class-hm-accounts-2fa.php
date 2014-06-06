<?php

class HM_Accounts_2FA {

	/**
	 * Verifies a supplied 2fa code against a supplied 2fa secret
	 *
	 * @param $code
	 * @param $secret
	 * @param $last_login
	 * @return bool|float
	 */
	static function verify_code( $code, $secret, $last_login, $grace_period_minutes = 0.5 ) {

		// Did the user enter 6 digits ?
		if ( strlen( $code ) != 6 ) {

			return false;

		} else {

			$code = intval( $code );
		}

		$verified     = false;
		$tm           = floor( time() / 30 );
		$secret_key   = Base32::decode( $secret );

		$start_period = - ( $grace_period_minutes * 2 );
		$end_period   =   ( $grace_period_minutes * 2 );

		// Keys from 30 seconds before and after are valid aswell.
		for ( $i = $start_period; $i <= $end_period; $i++ ) {

			// Pack time into binary string
			$time = chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . pack( 'N*', $tm + $i );

			// Hash it with users secret key
			$hm = hash_hmac( 'SHA1', $time, $secret_key, true );

			// Use last nipple of result as index/offset
			$offset = ord( substr( $hm, -1 ) ) & 0x0F;

			// grab 4 bytes of the result
			$hash_part = substr( $hm, $offset, 4 );

			// Unpack binary value
			$value = unpack( "N", $hash_part );
			$value = $value[1];

			// Only 32 bits
			$value = $value & 0x7FFFFFFF;
			$value = $value % 1000000;

			if ( $value === $code ) {

				// Check for replay (Man-in-the-middle) attack.
				// Since this is not Star Trek, time can only move forward,
				// meaning current login attempt has to be in the future compared to
				// last successful login.
				if ( $last_login >= ( $tm+$i ) ) {
					error_log( "Google Authenticator: Man-in-the-middle attack detected (Could also be 2 legit login attempts within the same 30 second period)");
					return false;
				}

				// Return time slot in which login happened.
				$verified = $tm + $i;

				break;
			}
		}

		return apply_filters( 'hma_2fa_verify_code', $verified, $code, $secret, $last_login );
	}

	/**
	 * Generates a random secret string
	 *
	 * @return string
	 */
	static function generate_secret( $char_count = 16 ) {

		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // allowed characters in Base32
		$secret = '';

		for ( $i = 0; $i < $char_count; $i++ ) {
			$secret .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return apply_filters( 'hma_2fa_generate_secret', $secret, $char_count );
	}

	/**
	 * Generate 5 single use secret strings for use if standard 2fa isn't available to the user
	 *
	 * @return array
	 */
	static function generate_single_use_secrets() {

		$secrets = array();

		for ( $i = 0; $i < 5; $i++ ) {

			$secrets[] = self::generate_secret( 32 );
		}

		return apply_filters( 'hma_2fa_generate_single_use_secrets', $secrets );
	}

	/**
	 * Encrypt a secret string for storage
	 *
	 * @param $string
	 * @return mixed|string|void
	 */
	static function encrypt_secret( $string ) {

		if ( $string === '' )
			return $string;

		$encrypted = '';

		if ( function_exists( 'mcrypt_encrypt' ) ) {

			$encrypted = trim( base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, self::get_encryption_secret(),
					$string, MCRYPT_MODE_ECB,
					mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB ), MCRYPT_RAND ) )
			) );
		}

		return apply_filters( 'hma_2fa_encrypt_secret', $encrypted, $string );
	}

	/**
	 * Decrypt a secret string after pulling from storage
	 *
	 * @param $string
	 * @return mixed|string|void
	 */
	static function decrypt_secret( $string ) {

		if ( $string === '' )
			return $string;

		$decrypted = '';

		if ( function_exists( 'mcrypt_decrypt' ) ) {

			$decrypted = trim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, self::get_encryption_secret(),
				base64_decode( $string ), MCRYPT_MODE_ECB,
				mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB ), MCRYPT_RAND )
			) );
		}

		return apply_filters( 'hma_2fa_decrypt_secret', $decrypted, $string );
	}

	/**
	 * Check if we are able to encrypt and decrypt
	 *
	 * @param $string
	 * @return bool
	 */
	static function is_encryption_available() {
		return ( function_exists( 'mcrypt_encrypt' ) && function_exists( 'mcrypt_decrypt' ) )
			|| ( has_filter( 'hma_2fa_encrypt_secret' ) && has_filter( 'hma_2fa_decrypt_secret' ) );
	}

	/**
	 * Get the secret key used for encrypting/decrypting our data
	 *
	 * @return string
	 */
	static function get_encryption_secret() {

		if ( defined( 'HMA_2FA_ENCRYPTION_SECRET' ) && HMA_2FA_ENCRYPTION_SECRET ) {

			$secret = HMA_2FA_ENCRYPTION_SECRET;

		} else {

			$salt   = wp_salt( 'auth' );
			$secret = substr( $salt, 0, 31 );
		}

		return apply_filters( 'hma_2fa_get_encryption_secret', $secret );
	}

	/**
	 * Generates a qr code string from a secret string
	 *
	 * @param $secret
	 * @return string
	 */
	static function generate_qr_code_string( $secret ) {

		$qr_code = "otpauth://totp/"
			     . rawurlencode( wp_get_current_user()->user_login ) . "?secret="
			     . $secret . "&issuer=" . rawurlencode( get_bloginfo( 'name' ) );

		return apply_filters( 'hma_2fa_generate_qr_code_string', $qr_code, $secret );
	}

	/**
	 * Get the current messages for the user set via cookie
	 *
	 * @param string $context
	 * @param string $type
	 * @return mixed|void
	 */
	static function get_messages( $context = '', $type = '' ) {

		$cookie   = ! empty( $_COOKIE['hma_2fa_messages'] ) ? json_decode( base64_decode( $_COOKIE['hma_2fa_messages'] ) ) : array();
		$messages = is_array( $cookie ) ? $cookie : array();

		//Turn JSON object to array
		foreach( $messages as $key => $message ) {

			$messages[$key] = (array) $message;
		}

		//Filter context
		if ( $context ) {

			foreach ( $messages as $key => $message ) {
				if ( $message['context'] !== $context ) {
					unset( $messages[$key] );
				}
			}
		}

		//Filter type
		if ( $type ) {

			foreach ( $messages as $key => $message ) {
				if ( $message['type'] !== $type ) {
					unset( $messages[$key] );
				}
			}
		}

		$messages = array_values( $messages );

		return apply_filters( 'hma_2fa_get_messages', $messages, $context, $type );
	}

	/**
	 * Add a message for the current user via cookie
	 *
	 * @param $text
	 * @param string $context
	 * @param string $type
	 */
	static function add_message( $text, $context = '', $type = '' ) {

		$messages   = self::get_messages();
		$messages[] = (object) array( 'text' => $text, 'type' => $type, 'context' => $context );

		do_action( 'hma_2fa_add_message', $text, $context = '', $type = 'error' );

		$encoded = base64_encode( json_encode( $messages ) );

		setcookie( 'hma_2fa_messages', $encoded, strtotime( '+1 week' ), COOKIEPATH );

		//Hack so that WordPress update profile can show the error - there's no page load to initialise the cookie
		$_COOKIE['hma_2fa_messages'] = $encoded;

	}

	/**
	 * Delete the message cookie
	 */
	static function delete_messages() {

		?>
		<script type="text/javascript">
			document.cookie = 'hma_2fa_messages=""; path=<?php echo COOKIEPATH; ?>';
		</script>
		<?php
	}

}