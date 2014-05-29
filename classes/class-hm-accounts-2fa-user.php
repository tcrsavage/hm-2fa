<?php

class HM_Accounts_2FA_User {

	function __construct( $user_id ) {

		$this->user_id = absint( $user_id );
	}

	/**
	 * Shortcut instance getter, also allows for user object or user_id
	 *
	 * @param $user
	 * @return HM_Accounts_2FA_User|WP_Error
	 */
	static function get_instance( $user ) {

		$user_id = ! empty( $user->ID ) ? $user->ID : $user;

		if ( ! get_user_by( 'id', $user_id ) ) {

			return new WP_Error( 'hm_accounts_2fa_user_bad_user_param', 'The user param povided to HM_Accounts_2FA_User was incorrect' );

		} else {

			return new HM_Accounts_2FA_User( $user_id );
		}
	}

	/**
	 * Sets the user's 2fa secret key
	 *
	 * @param $code
	 */
	function set_secret( $code ) {

		$this->update_meta( 'hm_accounts_2fa_secret', HM_Accounts_2FA::encrypt_secret( $code ) );
	}

	/**
	 * Gets the user's 2fa secret key
	 *
	 * @return string
	 */
	function get_secret() {

		return HM_Accounts_2FA::decrypt_secret( $this->get_meta( 'hm_accounts_2fa_secret' ) );
	}

	/**
	 * Set an array of single use secret keys for the user
	 *
	 * @param $secrets
	 */
	function set_single_use_secrets( $secrets ) {

		$secrets = array_map( array( 'HM_Accounts_2FA', 'encrypt_secret' ), $secrets );

		$this->update_meta( 'hm_accounts_2fa_single_use_secrets', $secrets );
	}

	/**
	 * Set an array of single use secret keys for the user
	 *
	 * @return array
	 */
	function get_single_use_secrets() {

		$secrets = $this->get_meta( 'hm_accounts_2fa_single_use_secrets' );

		return array_map( array( 'HM_Accounts_2FA', 'decrypt_secret' ), $secrets );
	}

	/**
	 * Sets whether or not 2fa is enabled
	 *
	 * @param $bool
	 */
	function set_2fa_enabled( $bool ) {

		$this->update_meta( 'hm_accounts_2fa_is_enabled', ( $bool ) ? '1' : '0'  );
	}

	/**
	 * Gets whether or not 2fa is enabled
	 *
	 * @return bool
	 */
	function get_2fa_enabled() {

		return apply_filters( 'hma_2fa_user_get_2fa_enabled', ( $this->get_meta( 'hm_accounts_2fa_is_enabled' ) ), $this->user_id );
	}

	/**
	 * Sets the last login time slot for the user
	 *
	 * @param $last_login
	 */
	function set_last_login( $last_login ) {

		$this->update_meta( 'hm_accounts_2fa_last_login', $last_login  );
	}

	/**
	 * Gets the last login time slot for the user
	 *
	 * @return mixed
	 */
	function get_last_login() {

		return apply_filters( 'hma_2fa_user_get_last_login', $this->get_meta( 'hm_accounts_2fa_last_login' ), $this->user_id );
	}

	/**
	 * Verifies the supplied code against the user's secret and last login time slot
	 *
	 * Returns false on failure or new login time slot on success
	 *
	 * @param $code
	 * @return bool|float
	 */
	function verify_code( $code ) {

		$secret = $this->get_secret();

		$verified = false;

		// When was the last successful login performed ?
		$last_time_slot = $this->get_last_login();

		// Valid code ?
		if ( $time_slot = HM_Accounts_2FA::verify_code( $code, $secret, $last_time_slot ) ) {

			$verified = $time_slot;

		}

		return apply_filters( 'hma_2fa_user_verify_code', $verified, $this->user_id );
	}

	function verify_single_use_code( $code ) {

		$verified = false;

		foreach ( $this->get_single_use_secrets() as $secret ) {

			if ( $secret == $code ) {

				$verified = true;
			}
		}

		return apply_filters( 'hma_2fa_user_verify_single_use_code', $verified, $this->user_id );
	}

	function delete_single_use_code( $code ) {

		foreach ( $secrets = $this->get_single_use_secrets() as $key => $secret ) {

			if ( $secret == $code ) {

				unset( $secrets[$key] );
				$this->set_single_use_secrets( $secrets );

				return true;
			}
		}

		return false;
	}

	/**
	 * Updates the user's meta
	 *
	 * @param $key
	 * @param $value
	 */
	function update_meta( $key, $value ) {

		update_user_meta( $this->user_id, $key, $value );
	}

	/**
	 * Gets the user's meta
	 *
	 * @param $key
	 * @param bool $single
	 * @return mixed
	 */
	function get_meta( $key, $single = true ) {

		return get_user_meta( $this->user_id, $key, $single );
	}

	/**
	 * Deletes the user's meta
	 *
	 * @param $key
	 * @param string $value
	 */
	function delete_meta( $key, $value = '' ) {

		delete_user_meta( $this->user_id, $key, $value );
	}

}