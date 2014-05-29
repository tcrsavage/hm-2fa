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

		return ( $this->get_meta( 'hm_accounts_2fa_is_enabled' ) );
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

		return $this->get_meta( 'hm_accounts_2fa_last_login' );
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

		// When was the last successful login performed ?
		$last_time_slot = $this->get_last_login();

		// Valid code ?
		if ( $time_slot = HM_Accounts_2FA::verify_code( $code, $secret, $last_time_slot ) ) {

			return $time_slot;

		} else {

			return false;
		}
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