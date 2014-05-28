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

		$tm = floor( time() / 30 );

		$secretkey = Base32::decode( $secret );

		// Keys from 30 seconds before and after are valid aswell.
		for ( $i=$firstcount; $i<=$lastcount; $i++) {

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
					error_log("Google Authenticator: Man-in-the-middle attack detected (Could also be 2 legit login attempts within the same 30 second period)");
					return false;
				}

				// Return timeslot in which login happened.
				return $tm + $i;
			}
		}

		return false;
	}

	/**
	 * Generates a random secret string
	 *
	 * @return string
	 */
	static function generate_secret() {

		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // allowed characters in Base32
		$secret = '';

		for ( $i = 0; $i < 16; $i++ ) {
			$secret .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $secret;
	}

	/**
	 * Generates a qr code string from a secret string
	 *
	 * @param $secret
	 * @return string
	 */
	static function generate_qr_code_string( $secret ) {

		return "otpauth://totp/"
			. rawurlencode( wp_get_current_user()->user_login ) . "?secret="
			. $secret . "&issuer=" . rawurlencode( get_bloginfo( 'name' ) );

	}
}