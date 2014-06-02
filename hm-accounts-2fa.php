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
function hma_2fa_edit_profile_fields( $user ) {

	$user_2fa = HM_Accounts_2FA_User::get_instance( $user );

	if ( is_wp_error( $user_2fa ) || ! $user_2fa->has_capability( get_current_user_id(), 'edit' ) ) {
		return;
	}

	include( 'templates/profile-fields.php' );
}

add_action( 'show_user_profile', 'hma_2fa_edit_profile_fields' );
add_action( 'edit_user_profile', 'hma_2fa_edit_profile_fields' );

/**
 * Update the user's 2fa settings - assumes nonce screening has already taken place
 *
 * @param $user_id
 */
function hma_2fa_update_user_profile( $user_id ) {

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	// Not enough data, don't process the request
	if ( ! isset( $_POST['hma_2fa_is_enabled'] ) || ! isset( $_POST['hma_2fa_secret'] ) ) {
		return;
	}

	$user_2fa = HM_Accounts_2FA_User::get_instance( $user_id );

	//The current user does not have the capability to edit this user's settings
	if ( ! $user_2fa->has_capability( get_current_user_id(), 'edit' ) ) {
		return;
	}

	$secret        = sanitize_text_field( $_POST['hma_2fa_secret'] );
	$verify_secret = ( ! empty( $_POST['hma_2fa_secret_verify'] ) ) ? sanitize_text_field( $_POST['hma_2fa_secret_verify'] ) : '';
	$single_use    = array_map( 'sanitize_text_field', ! empty( $_POST['hm_accounts_2fa_single_use_secrets'] ) ? $_POST['hm_accounts_2fa_single_use_secrets'] : array() );
	$enabled       = ( ! empty( $_POST['hma_2fa_is_enabled'] ) && $secret && HM_Accounts_2FA::is_encryption_available() );
	$hidden        = ( ! empty( $_POST['hma_2fa_is_hidden'] ) );

	if ( ! HM_Accounts_2FA::verify_code( $verify_secret, $secret, 0, 2 ) && $secret ) {

		HM_Accounts_2FA::add_profile_update_error( 'hma_2fa_invalid_verify_secret', 'Either the 2FA verification code you entered was incorrect, or your device\'s clock is out of sync with the server. Please try again' );
		return;
	}

	if ( isset( $_POST['hma_2fa_is_hidden'] ) && $user_2fa->has_capability( get_current_user_id(), 'hide' ) ) {
		$user_2fa->set_2fa_hidden( $hidden );
	}

	if ( isset( $_POST['hma_2fa_is_enabled'] ) ) {
		$user_2fa->set_2fa_enabled( $enabled );
	}

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
 * Hook into 'authenticate' filter and display 2fa interstitial auth screen
 *
 * @param $user_authenticated
 * @param string $username
 * @param string $password
 * @return WP_Error
 */
function hma_2fa_authenticate_interstitial( $user_authenticated, $username = '', $password = '' ) {

	$user     = get_user_by( 'login', $username );
	$user_2fa = HM_Accounts_2FA_User::get_instance( $user );

	// Bad user/credentials or 2FA isn't enabled - let other hooks handle this case
	if ( ! $user || is_wp_error( $user_authenticated ) || is_wp_error( $user_2fa ) || ! $user_2fa->get_2fa_enabled() ) {
		return $user_authenticated;
	}

	$login_token = $user_2fa->generate_login_access_token();
	$redirect_to  = isset( $_POST['redirect_to'] ) ? sanitize_text_field( $_POST['redirect_to'] ) : admin_url();

	$user_2fa->set_login_access_token( $login_token );

	// Custom html
	if ( $html = hma_2fa_get_custom_interstitial_html( $user_2fa, $login_token, $redirect_to ) ) {

		echo $html;
		exit;
	}

	// Default html
	wp_die( hma_2fa_get_default_interstitial_html( $user_2fa, $login_token, $redirect_to ) );
}

add_action( 'authenticate', 'hma_2fa_authenticate_interstitial', 900, 3 );

/**
 * Get the custom interstitial login form html - if custom html has not been set, we'll fall back to default
 *
 * @param $user_2fa
 * @param $access_token
 * @param $redirect_to
 * @return bool|mixed|string|void
 */
function hma_2fa_get_custom_interstitial_html( $user_2fa, $login_token, $redirect_to ) {

	//Template file has been created for the interstitial screen, use that
	if ( file_exists( $file_path = apply_filters( 'hma_2fa_authenticate_interstitial_template', get_template_directory() . '/login.hma_2fa.php' ) ) ) {

		ob_start();

		include( $file_path );

		return ob_get_clean();

	//Custom html has been defined, use that
	} elseif ( $contents = apply_filters( 'hma_2fa_authenticate_interstitial_html', '', $user_2fa, $login_token, $redirect_to ) ) {

		return $contents;
	}

	return false;
}

/**
 * Get the default interstitial login form html
 *
 * @param $user_2fa
 * @param $access_token
 * @param $redirect_to
 * @return string
 */
function hma_2fa_get_default_interstitial_html( $user_2fa, $login_token, $redirect_to ) {

	ob_start();

	include( 'templates/login-interstitial.php' );

	$html = ob_get_clean();

	return $html;
}

/**
 * Authenticate the user's 2fa login attempt
 */
function hma_2fa_authenticate_login() {

	$args = array();

	$args['user_id']     = ! empty( $_POST['hma_2fa_login_user_id'] ) ? sanitize_text_field( $_POST['hma_2fa_login_user_id'] ) : '';
	$args['redirect_to'] = ! empty( $_POST['redirect_to'] )           ? sanitize_text_field( $_POST['redirect_to'] )           : admin_url();
	$args['referer']     = ! empty( $_POST['referer'] )               ? sanitize_text_field( $_POST['referer'] )               : admin_url();
	$args['login_token'] = ! empty( $_POST['hma_2fa_login_token'] )   ? sanitize_text_field( $_POST['hma_2fa_login_token'] )   : '';
	$args['auth_code']   = ! empty( $_POST['hma_2fa_auth_code'] )     ? sanitize_text_field( $_POST['hma_2fa_auth_code'] )     : '';

	//query arg so static page caching doesn't interfere with displaying error messages if the auth fails
	$args['referer']     = add_query_arg( array( 'submitted' => time() ), $args['referer'] );

	$args = apply_filters( 'hma_2fa_authenticate_login_args', $args );

	$user_2fa = HM_Accounts_2FA_User::get_instance( $args['user_id'] );

	if ( is_wp_error( $user_2fa ) ) {

		HM_Accounts_2FA::add_login_error( 'hma_2fa_invalid_request', 'Invalid auth request' );

		wp_redirect( $args['referer'] );

		exit;
	}

	if ( ! HM_Accounts_2FA::is_encryption_available() ) {

		HM_Accounts_2FA::add_login_error( 'hma_2fa_encryption_not_available', 'Unable to decrypt auth key. Please contact the site administrator' );

		wp_redirect( $args['referer'] );

		exit;
	}

	$authenticated = false;

	// Verify the 2fa code, if verified, a timestamp will be returned with the last login time slot, otherwise false
	if ( ( $time_slot = $user_2fa->verify_code( $args['auth_code'] ) ) && $user_2fa->verify_login_access_token( $args['login_token'] ) ) {

		// Update the last login, mitigates man in the middle attacks as we will ensure a minimum of 30 secs between
		// successful login attempts
		$user_2fa->set_last_login( $time_slot );

		$authenticated = true;

	// 2fa code did not verify, check if it's a valid single use code
	} else if ( $user_2fa->verify_single_use_code( $args['auth_code'] ) && $user_2fa->verify_login_access_token( $args['login_token'] ) ) {

		$user_2fa->delete_single_use_code( $args['auth_code'] );

		$authenticated = true;
	}

	// User has made the request, delete their login access token
	// They will have to put their username and password in again
	$user_2fa->delete_login_access_token();

	//User has the green light to continue, log them in and redirect
	if ( $authenticated === true ) {

		$user_2fa->authenticate();

		wp_redirect( $args['redirect_to'] );

		exit;

	} else {

		HM_Accounts_2FA::add_login_error( 'hma_2fa_invalid_request', 'Invalid 2 factor auth key' );

		wp_redirect( $args['referer'] );

		exit;
	}
}

add_action( 'admin_post_nopriv_hma_2fa_authenticate_login', 'hma_2fa_authenticate_login' );
add_action( 'admin_post_hma_2fa_authenticate_login', 'hma_2fa_authenticate_login' );

/**
 * Hook in to the WordPress login page error messages and display 2fa error messages if applicable
 */
function hma_2fa_display_admin_login_page_errors( WP_Error $errors, $redirect_to ) {

	foreach ( HM_Accounts_2FA::get_login_errors() as $code => $message ) {

		$errors->add( $code, $message );
	}

	//We've shown the errors, delete them from cache
	HM_Accounts_2FA::delete_login_errors();

	return $errors;
}

add_filter( 'wp_login_errors', 'hma_2fa_display_admin_login_page_errors', 10, 2 );

/**
 * Hook in to the WordPress login page error messages and display 2fa error messages if applicable
 */
function hma_2fa_display_admin_profile_update_errors( WP_Error $errors, $redirect_to, $user ) {

	foreach ( HM_Accounts_2FA::get_profile_update_errors() as $code => $message ) {

		$errors->add( $code, $message );
	}

	//We've shown the errors, delete them from cache
	HM_Accounts_2FA::delete_profile_update_errors();

	return $errors;
}

add_filter( 'user_profile_update_errors', 'hma_2fa_display_admin_profile_update_errors', 10, 2, 3 );

/**
 * Clean up the error messages, they only need to be displayed on the first page load after failure
 */
function hma_2fa_clear_errors() {

	HM_Accounts_2FA::delete_login_errors();
	HM_Accounts_2FA::delete_profile_update_errors();
}

add_action( 'login_footer', 'hma_2fa_clear_errors' );
add_action( 'admin_footer', 'hma_2fa_clear_errors' );
add_action( 'wp_footer', 'hma_2fa_clear_errors' );

/**
 * Display an admin warning if encryption isn't available
 */
function hma_2fa_user_admin_notices() {

	if ( HM_Accounts_2FA::is_encryption_available() || ! current_user_can( 'administrator' ) ) {
		return;
	}

	?>
	<div class="error">
		<p>HM Accounts 2FA requires PHP MCrypt or use of custom encryption methods via use of filters in order to function.</p>
	</div>
	<?php
}

add_action( 'all_admin_notices', 'hma_2fa_user_admin_notices' );