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
	static function verify_code( $code, $secret, $last_login ) {

		// Did the user enter 6 digits ?
		if ( strlen( $code ) != 6) {
			return false;
		} else {
			$code = intval( $code );
		}

		$firstcount = -1;
		$lastcount  =  1;
		$verified   =  false;

		$tm = floor( time() / 30 );

		$secretkey = Base32::decode( $secret );

		// Keys from 30 seconds before and after are valid aswell.
		for ( $i = $firstcount; $i <= $lastcount; $i++) {

			// Pack time into binary string
			$time = chr( 0 ) . chr( 0 ) . chr( 0 ) . chr( 0 ) . pack( 'N*', $tm + $i );

			// Hash it with users secret key
			$hm = hash_hmac( 'SHA1', $time, $secretkey, true );

			// Use last nipple of result as index/offset
			$offset = ord(substr( $hm, -1 ) ) & 0x0F;

			// grab 4 bytes of the result
			$hashpart = substr( $hm, $offset, 4 );

			// Unpak binary value
			$value = unpack( "N", $hashpart );

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

				// Return timeslot in which login happened.
				$verified =  $tm + $i;

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
	 * Generate a login token
	 *
	 * @return string
	 */
	static function generate_user_login_secret( $user_id = null ) {

		return apply_filters( 'hma_2fa_generate_user_login_secret',  self::generate_secret( 64 ), $user_id );
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

		$encrypted = trim( base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, self::get_encryption_secret(), $string, MCRYPT_MODE_ECB, mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB ), MCRYPT_RAND ) ) ) );

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

		$decrypted = trim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, self::get_encryption_secret(), base64_decode( $string ), MCRYPT_MODE_ECB, mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB ), MCRYPT_RAND ) ) );

		return apply_filters( 'hma_2fa_decrypt_secret', $decrypted, $string );
	}

	/**
	 * Get the secret key used for encrypting/decrypting our data
	 *
	 * @return string
	 */
	static function get_encryption_secret() {

		if ( defined( 'HMA_2FA_ENCRYPTION_SECRET' ) ) {

			$secret = HMA_2FA_ENCRYPTION_SECRET;

		} else {

			$secret = substr( SECURE_AUTH_KEY, 0, 31 );
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

		$qr_code =  "otpauth://totp/"
			. rawurlencode( wp_get_current_user()->user_login ) . "?secret="
			. $secret . "&issuer=" . rawurlencode( get_bloginfo( 'name' ) );

		return apply_filters( 'hma_2fa_generate_qr_code_string', $qr_code, $secret );
	}
}