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

			return new WP_Error( 'hma_2fa_user_bad_user_param', 'The user param provided to HM_Accounts_2FA_User was incorrect' );

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

		$this->update_meta( 'hma_2fa_secret', HM_Accounts_2FA::encrypt_secret( $code ) );
	}

	/**
	 * Gets the user's 2fa secret key
	 *
	 * @return string
	 */
	function get_secret() {

		return apply_filters( 'hma_2fa_user_get_secret', HM_Accounts_2FA::decrypt_secret( $this->get_meta( 'hma_2fa_secret' ) ), $this->user_id );
	}

	/**
	 * Set an array of single use secret keys for the user
	 *
	 * @param $secrets
	 */
	function set_single_use_secrets( $secrets ) {

		$secrets = array_map( array( 'HM_Accounts_2FA', 'encrypt_secret' ), $secrets );

		$this->update_meta( 'hma_2fa_single_use_secrets', $secrets );
	}

	/**
	 * Set an array of single use secret keys for the user
	 *
	 * @return array
	 */
	function get_single_use_secrets() {

		$secrets = $this->get_meta( 'hma_2fa_single_use_secrets' );

		return array_map( array( 'HM_Accounts_2FA', 'decrypt_secret' ), $secrets );
	}

	/**
	 * Sets whether or not 2fa is enabled
	 *
	 * @param $bool
	 */
	function set_2fa_enabled( $bool ) {

		$this->update_meta( 'hma_2fa_is_enabled', ( $bool ) ? '1' : '0'  );
	}

	/**
	 * Gets whether or not 2fa is enabled
	 *
	 * @return bool
	 */
	function get_2fa_enabled() {

		return apply_filters( 'hma_2fa_user_get_2fa_enabled', ( $this->get_meta( 'hma_2fa_is_enabled' ) ), $this->user_id );
	}

	/**
	 * Sets whether or not the user is allowed to edit their 2fa settings
	 *
	 * @param $bool
	 */
	function set_2fa_hidden( $bool ) {

		$this->update_meta( 'hma_2fa_is_hidden', ( $bool ) ? '1' : '0'  );
	}

	/**
	 * Gets whether or not the user is allowed to edit their 2fa settings
	 *
	 * @return bool
	 */
	function get_2fa_hidden() {

		return apply_filters( 'hma_2fa_user_get_2fa_hidden', ( $this->get_meta( 'hma_2fa_is_hidden' ) ), $this->user_id );
	}

	/**
	 * Sets the last login time slot for the user
	 *
	 * @param $last_login
	 */
	function set_last_login( $last_login ) {

		$this->update_meta( 'hma_2fa_last_login', $last_login  );
	}

	/**
	 * Gets the last login time slot for the user
	 *
	 * @return mixed
	 */
	function get_last_login() {

		return apply_filters( 'hma_2fa_user_get_last_login', $this->get_meta( 'hma_2fa_last_login' ), $this->user_id );
	}


	/**
	 * Sets the login access token for the user
	 *
	 * @param $last_login
	 */
	function set_login_access_token( $token ) {

		$this->update_meta( 'hma_2fa_login_access_token', HM_Accounts_2FA::encrypt_secret( $token ) );
	}

	/**
	 * Gets the login access token for the user
	 *
	 * @return mixed
	 */
	function get_login_access_token() {

		return apply_filters( 'hma_2fa_user_get_login_access_token', HM_Accounts_2FA::decrypt_secret( $this->get_meta( 'hma_2fa_login_access_token' ) ), $this->user_id );
	}

	/**
	 * Delete the user's login access token
	 *
	 * @return mixed
	 */
	function delete_login_access_token() {

		$this->delete_meta( 'hma_2fa_login_access_token' );
	}

	/**
	 * Verifies a supplied login access token against the one (if any) stored in the user's meta
	 *
	 * @return mixed
	 */
	function verify_login_access_token( $token ) {

		$verified = false;

		if ( $token && $this->get_login_access_token() === $token ) {
			$verified = true;
		}

		return apply_filters( 'hma_2fa_user_verify_login_access_token', $verified );
	}


	/**
	 * Check if a given user has the ability to edit/hide/full on this user
	 *
	 * If current_user is set to false, we assume to be checking the user's caps against themselves
	 *
	 * @param $current_user, $cap
	 *
	 * @return mixed
	 */
	function has_capability( $current_user = false, $cap ) {

		if ( $current_user === false )
			$current_user = $this->user_id;

		switch ( $cap ) {

			case 'edit' :
				$has_cap = ( ! $this->get_2fa_hidden() || user_can( $current_user, 'administrator' ) );
				break;

			case 'hide' :
				$has_cap = user_can( $current_user, 'administrator' );
				break;

			case 'full' :
				$has_cap = user_can( $current_user, 'administrator' );
				break;

			default :
				$has_cap = false;
		}

		return apply_filters( 'hma_2fa_user_has_capability', $has_cap, $cap, $this->user_id, $current_user );
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

	/**
	 * Verifies the supplied code against the user's list of single use secret codes
	 *
	 * @param $code
	 * @return mixed|void
	 */
	function verify_single_use_code( $code ) {

		$verified = false;

		foreach ( $this->get_single_use_secrets() as $secret ) {

			if ( $code && $secret === $code ) {

				$verified = true;
			}
		}

		return apply_filters( 'hma_2fa_user_verify_single_use_code', $verified, $this->user_id );
	}

	/**
	 * Deletes a single use code from the user's list, called directly when the code has been used to authenticate
	 *
	 * @param $code
	 * @return bool
	 */
	function delete_single_use_code( $code ) {

		foreach ( $secrets = $this->get_single_use_secrets() as $key => $secret ) {

			if ( $secret === $code ) {

				unset( $secrets[$key] );
				$this->set_single_use_secrets( $secrets );

				return true;
			}
		}

		return false;
	}

	/**
	 * Authenticates the user (logs them in)
	 */
	function authenticate() {

		wp_set_auth_cookie( $this->user_id );
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