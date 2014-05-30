<?php /* @var HM_ACCOUNTS_2FA_USER $user_2fa */ ?>
<?php /* @var string $access_token */ ?>
<?php /* @var string $redirect_to */ ?>

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