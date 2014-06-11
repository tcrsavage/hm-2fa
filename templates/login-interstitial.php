<?php /* @var HM_2FA_USER $user_2fa */ ?>
<?php /* @var string $login_token */ ?>
<?php /* @var string $redirect_to */ ?>

<?php login_header(); ?>

		<form name="loginform" id="loginform" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" style="padding-bottom: 25px">
			<p>
				<label for="user_login">Auth key<br />
				<input type="text" id="hm-2fa-auth-code" name="hm_2fa_auth_code" value="" />
			</p>

			<p class="submit">
				<input type="hidden" name="hm_2fa_login_user_id" value="<?php echo esc_attr( $user_2fa->user_id ); ?>" />
				<input type="hidden" name="hm_2fa_login_token" value="<?php echo esc_attr( $login_token ); ?>" />


				<input type="hidden" name="action" value="hm_2fa_authenticate_login" >
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
				<input type="hidden" name="referer" value="<?php echo esc_url( wp_get_referer() ); ?>" />

				<input type="submit" id="wp-submit" class="button button-primary button-large" value="Authenticate" />
			</p>

		</form>

		<script type="text/javascript">
			function wp_attempt_focus(){
				setTimeout( function(){ try{
					d = document.getElementById( 'hm-2fa-auth-code' );

					console.log( d );
					d.focus();
					d.select();
				} catch(e){}
				}, 200);
			}
			wp_attempt_focus();
		</script>

<?php login_footer(); ?>