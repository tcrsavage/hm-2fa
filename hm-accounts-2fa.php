<?php

/*
Plugin Name: HM Accounts 2FA
Description: Adds 2 factor authentication to your WordPress site, working as a standalone plugin through cross integration with hm-accounts
Author: Human Made Limited
Version: 0.1
Author URI: http://humanmade.co.uk/
*/

require_once( 'classes/class-hm-accounts-2fa.php' );
require_once( 'classes/class-hm-accounts-2fa-user.php' );
require_once( 'inc/base32.php' );

/**
 * Enqueue the admin scripts
 */
function hma_2fa_admin_scripts() {

	wp_enqueue_script( 'hma_2fa_qr_code', plugins_url( 'inc/jquery.qrcode.min.js', __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'hma_2fa_form_controller', plugins_url( 'inc/form_controller.js', __FILE__ ) );
}

add_action( 'admin_enqueue_scripts', 'hma_2fa_admin_scripts' );

/**
 * Add the 2fa fields to the admin screen
 *
 * @access public
 * @param mixed $user
 * @return void
 */
function hma_2fa_admin_fields( $user ) {

	$user_2fa = HM_Accounts_2FA_User::get_instance( $user );

	if ( is_wp_error( $user_2fa ) )
		return; ?>

	<div id="hma-2fa">

		<h3>2 Factor Authentication</h3>

		<table class="form-table">

			<tr>
				<th><label for="hma-2fa-is-enabled">Enable 2 factor authentication</label></th>
				<td>
					<input name="hma_2fa_is_enabled" type="hidden" value="0" />
					<input id="hma-2fa-is-enabled" name="hma_2fa_is_enabled" type="checkbox" <?php checked( $user_2fa->get_2fa_enabled() ); ?> value="1" />
				</td>
			</tr>

			<tr id="hma-2fa-secret-settings" style="display: none;">
				<th><label for="hma-2fa-secret">Secret code</label></th>
				<td id="hma-2fa-secret-settings-fields">
					<input type="text" name="hma_2fa_secret" id="hma-2fa-secret" placeholder="<?php echo ( $user_2fa->get_secret() ) ? 'It\'s a secret!' : ''; ?>"><br />

					<div id="hma-2fa-qr-code" style="margin: 10px 1px 1px 1px;"></div>

					<div id="hma-2fa-single-use-secrets" style="margin: 10px 1px 1px 1px; display: none;">
						<span class="description">These are your single use secret keys, save them, print them off and store somewhere safe. These will be your only way of accessing your account if you lose your phone</span>
					</div>

					<span class="description"></span> <br />

					<input type="button" id="hma-2fa-genarate-secret" value="Generate<?php echo ( $user_2fa->get_secret() ) ? ' new' : ''; ?>"  />
				</td>

				<td id="hma-2fa-secret-settings-ajax-loading" style="display: none;">
					<div class="spinner" style="display: block; float: left; margin: 0;"></div>
				</td>
			</tr>

		</table>

	</div>

	<?php
}

add_action( 'show_user_profile', 'hma_2fa_admin_fields' );
add_action( 'edit_user_profile', 'hma_2fa_admin_fields' );

/**
 * Update the user's 2fa settings - assumes nonce screening has already taken place
 *
 * @param $user_id
 */
function hma_2fa_update_user_profile( $user_id ) {

	if ( ! $user_id )
		$user_id = get_current_user_id();

	if ( ! isset( $_POST['hma_2fa_is_enabled'] ) || ! isset( $_POST['hma_2fa_secret'] ) )
		return;

	$user_2fa   = HM_Accounts_2FA_User::get_instance( $user_id );

	$secret     = sanitize_text_field( $_POST['hma_2fa_secret'] );
	$single_use = array_map( 'sanitize_text_field', ! empty( $_POST['hm_accounts_2fa_single_use_secrets'] ) ? $_POST['hm_accounts_2fa_single_use_secrets'] : array() );
	$enabled    = ( ! empty( $_POST['hma_2fa_is_enabled'] ) && $secret );

	$user_2fa->set_2fa_enabled( $enabled );

	if ( $secret ) {
		$user_2fa->set_secret( $secret );
	}

	if ( $single_use ) {
		$user_2fa->set_single_use_secrets( $single_use );
	}
}

add_action( 'personal_options_update', 'hma_2fa_update_user_profile' );
add_action( 'edit_user_profile_update', 'hma_2fa_update_user_profile' );
add_action( 'hma_update_user_profile_completed', 'hma_2fa_update_user_profile' );

/**
 * Hook into 'authenticate' filter and apply 2fa screening if the user has 2fa enabled
 *
 * @param $user_authenticated
 * @param string $username
 * @param string $password
 * @return WP_Error
 */
function hma_2fa_authenticate_code( $user_authenticated, $username = '', $password = '' ) {

	$user     = get_user_by( 'login', $username );
	$user_2fa = HM_Accounts_2FA_User::get_instance( $user );

	// Bad user/credentials or 2FA isn't enabled - let other hooks handle this case
	if ( ! $user || is_wp_error( $user_authenticated ) || is_wp_error( $user_2fa ) || ! $user_2fa->get_2fa_enabled() ) {
		return $user_authenticated;
	}

	$access_token = HM_Accounts_2FA::generate_secret( 32 );
	$redirect_to  = isset( $_POST['redirect_to'] ) ? sanitize_text_field( $_POST['redirect_to'] ) : admin_url();

	$user_2fa->set_login_access_token( $access_token );

	ob_start(); ?>

		<div>
			<p>
				<span>This account has 2 factor authentication enabled. Please supply a 2 factor auth key</span>
			</p>

			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
				<input type="hidden" name="hma_2fa_login_user_id" value="<?php echo esc_attr( $user_2fa->user_id ); ?>" />
				<input type="hidden" name="hma_2fa_login_token" value="<?php echo esc_attr( $access_token ); ?>" />
				<input type="text" name="hma_2fa_auth_code" style="width: 150px; height: 18px; padding: 3px; font-size: 18px;" value="" />

				<input type="hidden" name="action" value="hma_2fa_authenticate_login" >
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
				<input type="hidden" name="referer" value="<?php echo esc_url( wp_get_referer() ); ?>" />
				<input type="submit" class="button" value="Submit" />
			</form>

		</div>

	<?php $contents = ob_get_clean();

	wp_die( $contents );
}

add_action( 'authenticate', 'hma_2fa_authenticate_code', 900, 3 );

/**
 * Generate a new random 2fa key and qr code string
 */
function hma_2fa_ajax_generate_secret_key() {

	$secret     = HM_Accounts_2FA::generate_secret();
	$single_use = HM_Accounts_2FA::generate_single_use_secrets();
	$qr_code    = HM_Accounts_2FA::generate_qr_code_string( $secret );

	echo json_encode( array(
		'secret'                => $secret,
		'single_use_secrets'    => $single_use,
		'qr_code'               => $qr_code
	) );

	exit;
}

add_action( 'wp_ajax_hma_2fa_generate_secret_key', 'hma_2fa_ajax_generate_secret_key' );


/**
 * Authenticate the user's 2fa login attempt
 */
function hma_2fa_authenticate_login() {

	$user_id     = ! empty( $_POST['hma_2fa_login_user_id'] ) ? sanitize_text_field( $_POST['hma_2fa_login_user_id'] ) : '';
	$redirect_to = ! empty( $_POST['redirect_to'] ) ? sanitize_text_field( $_POST['redirect_to'] ) : admin_url();
	$referer     = ! empty( $_POST['referer'] ) ? sanitize_text_field( $_POST['referer'] ) : admin_url();
	$login_token = ! empty( $_POST['hma_2fa_login_token'] ) ? sanitize_text_field( $_POST['hma_2fa_login_token'] ) : '';
	$auth_code   = ! empty( $_POST['hma_2fa_auth_code'] ) ? sanitize_text_field( $_POST['hma_2fa_auth_code'] ) : '';

	$user_2fa = HM_Accounts_2FA_User::get_instance( $user_id );


	if ( is_wp_error( $user_2fa ) ) {

		wp_die( 'Invalid user' );
	}

	$authenticated = false;

	// Verify the 2fa code, if verified, a timestamp will be returned with the last login time slot, otherwise false
	if ( $time_slot = $user_2fa->verify_code( $auth_code ) && $user_2fa->verify_login_access_token( $login_token ) ) {

		// Update the last login, mitigates man in the middle attacks as we will ensure a minimum of 30 secs between
		// successful login attempts
		$user_2fa->set_last_login( $time_slot );

		$authenticated = true;

	// 2fa code did not verify, check if it's a valid single use code
	} else if ( $user_2fa->verify_single_use_code( $auth_code ) && $user_2fa->verify_login_access_token( $login_token ) ) {

		$user_2fa->delete_single_use_code( $auth_code );

		$authenticated = true;
	}

	// User has made the request, delete their login access token
	// They will have to put their username and password in again
	$user_2fa->delete_login_access_token();

	//User has the green light to continue, log them in and redirect
	if ( $authenticated === true ) {

		$user_2fa->authenticate();

		wp_redirect( $redirect_to );

		exit;

	} else {

		wp_redirect( $referer );

		exit;
	}

}

add_action( 'admin_post_nopriv_hma_2fa_authenticate_login', 'hma_2fa_authenticate_login' );
add_action( 'admin_post_hma_2fa_authenticate_login', 'hma_2fa_authenticate_login' );