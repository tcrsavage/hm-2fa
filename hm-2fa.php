<?php
/*
Plugin Name: HM 2FA
Description: Adds 2 factor authentication to your WordPress site
Author: Human Made Limited
Version: 0.1
Author URI: http://humanmade.co.uk/
*/

define( 'HM_2FA_VERSION', 0.1 );

require_once( 'classes/class-hm-2fa.php' );
require_once( 'classes/class-hm-2fa-user.php' );
require_once( 'inc/base32.php' );

/**
 * Enqueue the profile editing scripts
 */
function hm_2fa_enqueue_profile_edit_scripts( $in_footer = false, $require_jquery = true ) {

	$requires = $require_jquery ? array( 'jquery' ) : array();

	wp_enqueue_script( 'hm_2fa_qr_code', plugins_url( 'inc/jquery.qrcode.min.js', __FILE__ ), $requires, HM_2FA_VERSION, $in_footer );
	wp_enqueue_script( 'hm_2fa_form_controller', plugins_url( 'inc/form_controller.js', __FILE__ ),  array_merge( $requires, array( 'hm_2fa_qr_code' ) ), HM_2FA_VERSION, $in_footer );
}

add_action( 'admin_enqueue_scripts', 'hm_2fa_enqueue_profile_edit_scripts' );

/**
 * Add the 2fa fields to the admin screen
 *
 * @access public
 * @param mixed $user
 * @return void
 */
function hm_2fa_edit_profile_fields( $user ) {

	$user_2fa = HM_2FA_User::get_instance( $user );

	if ( is_wp_error( $user_2fa ) || ! $user_2fa->has_capability( get_current_user_id(), 'edit' ) ) {
		return;
	}

	include( 'templates/profile-fields.php' );
}

add_action( 'show_user_profile', 'hm_2fa_edit_profile_fields' );
add_action( 'edit_user_profile', 'hm_2fa_edit_profile_fields' );

/**
 * Update the user's 2fa settings - assumes nonce screening has already taken place
 *
 * @param $user_id
 */
function hm_2fa_update_user_profile( $user_id ) {

	if ( ! $user_id || ! is_numeric( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// Not enough data, don't process the request
	if ( ! isset( $_POST['hm_2fa_is_enabled'] ) || ! isset( $_POST['hm_2fa_secret'] ) ) {
		return;
	}

	$user_2fa = HM_2FA_User::get_instance( $user_id );

	//The current user does not have the capability to edit this user's settings
	if ( ! $user_2fa->has_capability( get_current_user_id(), 'edit' ) ) {
		return;
	}

	$secret        = sanitize_text_field( $_POST['hm_2fa_secret'] );
	$verify_secret = ( ! empty( $_POST['hm_2fa_secret_verify'] ) ) ? sanitize_text_field( $_POST['hm_2fa_secret_verify'] ) : '';
	$single_use    = array_map( 'sanitize_text_field', ! empty( $_POST['hm_2fa_single_use_secrets'] ) ? $_POST['hm_2fa_single_use_secrets'] : array() );
	$enabled       = ( ! empty( $_POST['hm_2fa_is_enabled'] ) && ( $secret || $user_2fa->get_secret() ) && HM_2FA::is_encryption_available() );
	$hidden        = ( ! empty( $_POST['hm_2fa_is_hidden'] ) );
	$password      = ( ! empty( $_POST['hm_2fa_password'] ) ) ? $_POST['hm_2fa_password'] : '' ;

	$user          = wp_get_current_user();

	$pw_auth       = wp_authenticate_username_password( false, $user->user_login, $password );

	if ( is_wp_error( $pw_auth ) ) {

		HM_2FA::add_message( '2FA settings have not been updated: The password you have entered is incorrect', 'profile_update', 'error' );
		return;
	}
	
	if ( ! HM_2FA::verify_code( $verify_secret, $secret, 0, 2 ) && $secret ) {

		HM_2FA::add_message( '2FA settings have not been updated: The verification code you entered was incorrect, or your device\'s clock is out of sync with the server. Please try again', 'profile_update', 'error' );
		return;
	}

	if ( isset( $_POST['hm_2fa_is_hidden'] ) && $user_2fa->has_capability( get_current_user_id(), 'hide' ) ) {
		$user_2fa->set_2fa_hidden( $hidden );
	}

	if ( isset( $_POST['hm_2fa_is_enabled'] ) ) {
		$user_2fa->set_2fa_enabled( $enabled );

		//Clear secrets if 2fa is disabled
		if ( ! $enabled ) {
			$user_2fa->delete_secret();
			$user_2fa->delete_single_use_secrets();
		}
	}

	if ( $secret ) {
		$user_2fa->set_secret( $secret );
	}

	if ( $single_use ) {
		$user_2fa->set_single_use_secrets( $single_use );
	}
}

add_action( 'personal_options_update', 'hm_2fa_update_user_profile' );
add_action( 'edit_user_profile_update', 'hm_2fa_update_user_profile' );

/**
 * Generate a new random 2fa key and qr code string
 */
function hm_2fa_ajax_generate_secret_key() {

	$secret     = HM_2FA::generate_secret();
	$single_use = HM_2FA::generate_single_use_secrets();
	$qr_code    = HM_2FA::generate_qr_code_string( $secret );

	echo json_encode( array(
		'secret'                => $secret,
		'single_use_secrets'    => $single_use,
		'qr_code'               => $qr_code
	) );

	exit;
}

add_action( 'wp_ajax_hm_2fa_generate_secret_key', 'hm_2fa_ajax_generate_secret_key' );

/**
 * Hook into 'authenticate' filter and display 2fa interstitial auth screen
 *
 * @param $user_authenticated
 * @param string $username
 * @param string $password
 * @return WP_Error
 */
function hm_2fa_authenticate_interstitial( $user_authenticated, $username = '', $password = '' ) {

	global $wp_query;
	$wp_query->is_login_interstitial = true;

	$user     = get_user_by( 'login', $username );
	$user_2fa = HM_2FA_User::get_instance( $user );

	// Bad user/credentials or 2FA isn't enabled - let other hooks handle this case
	if ( ! $user || is_wp_error( $user_authenticated ) || is_wp_error( $user_2fa ) || ! $user_2fa->get_2fa_enabled() ) {
		return $user_authenticated;
	}

	$login_token = $user_2fa->generate_login_access_token();
	$redirect_to  = isset( $_POST['redirect_to'] ) ? sanitize_text_field( $_POST['redirect_to'] ) : admin_url();

	$user_2fa->set_login_access_token( $login_token );

	// Custom html
	if ( $html = hm_2fa_get_custom_interstitial_html( $user_2fa, $login_token, $redirect_to ) ) {

		echo $html;
		exit;
	}

	// Default html
	echo hm_2fa_get_default_interstitial_html( $user_2fa, $login_token, $redirect_to );
	exit;
}

add_action( 'authenticate', 'hm_2fa_authenticate_interstitial', 900, 3 );

/**
 * Get the custom interstitial login form html - if custom html has not been set, we'll fall back to default
 *
 * @param $user_2fa
 * @param $access_token
 * @param $redirect_to
 * @return bool|mixed|string|void
 */
function hm_2fa_get_custom_interstitial_html( $user_2fa, $login_token, $redirect_to ) {

	//Template file has been created for the interstitial screen, use that
	if ( file_exists( $file_path = apply_filters( 'hm_2fa_authenticate_interstitial_template', get_template_directory() . '/login.hm_2fa.php' ) ) ) {

		ob_start();

		include( $file_path );

		return ob_get_clean();

	//Custom html has been defined, use that
	} elseif ( $contents = apply_filters( 'hm_2fa_authenticate_interstitial_html', '', $user_2fa, $login_token, $redirect_to ) ) {

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
function hm_2fa_get_default_interstitial_html( $user_2fa, $login_token, $redirect_to ) {

	ob_start();

	include( 'templates/login-interstitial.php' );

	$html = ob_get_clean();

	return $html;
}

/**
 * Authenticate the user's 2fa login attempt
 */
function hm_2fa_authenticate_login() {

	$args = array();

	$args['user_id']     = ! empty( $_POST['hm_2fa_login_user_id'] ) ? sanitize_text_field( $_POST['hm_2fa_login_user_id'] ) : '';
	$args['login_token'] = ! empty( $_POST['hm_2fa_login_token'] )   ? sanitize_text_field( $_POST['hm_2fa_login_token'] )   : '';
	$args['auth_code']   = ! empty( $_POST['hm_2fa_auth_code'] )     ? sanitize_text_field( $_POST['hm_2fa_auth_code'] )     : '';

	$args['redirect_to'] = ! empty( $_POST['redirect_to'] )           ?  $_POST['redirect_to']                                 : admin_url();
	$args['referer']     = ! empty( $_POST['referer'] )               ?  $_POST['referer']                                     : admin_url();

	//query arg so static page caching doesn't interfere with displaying error messages if the auth fails
	$args['referer']     = add_query_arg( array( 'submitted' => time() ), $args['referer'] );

	$args = apply_filters( 'hm_2fa_authenticate_login_args', $args );

	$user_2fa = HM_2FA_User::get_instance( $args['user_id'] );

	if ( is_wp_error( $user_2fa ) ) {

		HM_2FA::add_message( 'Invalid auth request', 'login', 'error' );

		wp_redirect( $args['referer'] );

		exit;
	}

	if ( ! HM_2FA::is_encryption_available() ) {

		HM_2FA::add_message( 'Unable to decrypt auth key. Please contact the site administrator', 'login', 'error' );

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

		$single_use_notice = apply_filters( 'hm_2fa_single_use_key_used_notice', sprintf( 'You have just used a single use code to log in, please update your 2 factor authentication settings <a href="%s">here</a>', admin_url( 'profile.php#hm-2fa' ) ), $user_2fa );

		HM_2FA::add_message( $single_use_notice, 'logged_in', 'error' );

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

		HM_2FA::add_message( 'Invalid 2 factor auth key', 'login', 'error' );

		wp_redirect( $args['referer'] );

		exit;
	}
}

add_action( 'admin_post_nopriv_hm_2fa_authenticate_login', 'hm_2fa_authenticate_login' );
add_action( 'admin_post_hm_2fa_authenticate_login', 'hm_2fa_authenticate_login' );

/**
 * Hook in to the WordPress login page error messages and display 2fa error messages if applicable
 */
function hm_2fa_display_admin_login_page_errors( WP_Error $errors, $redirect_to ) {

	foreach ( HM_2FA::get_messages( 'login', 'error' ) as $key => $error ) {

		$errors->add( 'hm_2fa_login_error', $error['text'] );
	}

	return $errors;
}

add_filter( 'wp_login_errors', 'hm_2fa_display_admin_login_page_messages', 10, 2 );

/**
 * Hook in to the WordPress login page error messages and display 2fa error messages if applicable
 */
function hm_2fa_display_admin_profile_update_errors( WP_Error $errors ) {

	foreach ( HM_2FA::get_messages( 'profile_update', 'error' ) as $key => $error ) {

		$errors->add( 'hm_2fa_profile_update_error', $error['text'] );
	}

	return $errors;
}

add_filter( 'user_profile_update_errors', 'hm_2fa_display_admin_profile_update_errors' );

/**
 * Clean up the messages, they only need to be displayed on the first page load
 */
function hm_2fa_clear_messages() {

	HM_2FA::delete_messages();
}

add_action( 'login_footer', 'hm_2fa_clear_messages' );
add_action( 'admin_footer', 'hm_2fa_clear_messages' );
add_action( 'wp_footer', 'hm_2fa_clear_messages' );

/**
 * Display logged_in_only messages on the admin screen
 */
function hm_2fa_admin_notices() {

	foreach ( HM_2FA::get_messages( 'logged_in' ) as $notice ) : ?>

		<div class="<?php echo $notice['type']; ?>">
			<p><?php echo $notice['text']; ?></p>
		</div>

	<?php endforeach;
}

add_action( 'all_admin_notices', 'hm_2fa_admin_notices' );


/**
 * Add an encryption unavailable message to the admin if encryption isn't available
 */
function hm_2fa_add_encryption_unavailable_message() {

	if ( HM_2FA::is_encryption_available() || ! current_user_can( 'administrator' ) || is_admin() ) {
		return;
	}

	HM_2FA::add_message( 'HM 2FA requires PHP MCrypt or use of custom encryption methods via use of filters in order to function.', 'logged_in', 'error'  );
}

add_action( 'admin_init', 'hm_2fa_add_encryption_unavailable_message' );

// Add a login-interstitial class to the boy on the 2fa auth interstitial
add_filter( 'body_class', function( $classes ) {

	if ( get_query_var( 'is_login_interstitial' ) );
		$classes[] = 'login-interstitial';

	return $classes;

} );