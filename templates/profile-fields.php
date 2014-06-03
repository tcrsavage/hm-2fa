<?php /* @var HM_ACCOUNTS_2FA_USER $user_2fa */ ?>

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

		<?php if ( $user_2fa->has_capability( get_current_user_id(), 'hide' ) ) : ?>

			<tr>
				<th><label for="hma-2fa-is-hidden">Hide 2 factor authentication settings from user</label></th>
				<td>
					<input name="hma_2fa_is_hidden" type="hidden" value="0" />
					<input id="hma-2fa-is-hidden" name="hma_2fa_is_hidden" type="checkbox" <?php checked( $user_2fa->get_2fa_hidden() ); ?> value="1" />
				</td>
			</tr>

		<?php endif; ?>

		<tr id="hma-2fa-secret-settings" style="display: none;">
			<th><label for="hma-2fa-secret">Secret codes</label></th>
			<td id="hma-2fa-secret-settings-fields">

				<div id="hma-2fa-new-secret-fields" style=" margin-bottom: 10px; display: none;">

					<h4 style="margin-top: 10px;">1. Download the <a href="https://support.google.com/accounts/answer/1066447?hl=en">Google Authenticator</a> app to your device and scan the QR code below</h4>

					<input type="text" name="hma_2fa_secret" id="hma-2fa-secret" placeholder="<?php echo ( $user_2fa->get_secret() ) ? 'It\'s a secret!' : ''; ?>"><br />

					<div id="hma-2fa-qr-code" style="margin: 10px 1px 1px 1px;"></div>

					<h4>2. Store these single use keys somewhere safe, we recommend printing them off</h4>
					<span class="description">You can use these keys for one time access to your account if you lose/break your device</span>


					<div id="hma-2fa-single-use-secrets" style="margin-top: 10px; display: none;">
					</div>

					<h4>3. Type in the code displayed on your Google Authenticator app to confirm that you have completed step 1 correctly</h4>
					<span class="description">You will have 2 minutes after entering this key to submit the update to your profile. Make sure the clock on your device is correct</span>

					<div style="margin-top: 10px;">
						<input type="text" name="hma_2fa_secret_verify" id="hma-2fa-secret-confirm" />
					</div>

				</div>

				<div>
					<input type="button" id="hma-2fa-generate-secret" class="button button-secondary" value="Generate<?php echo ( $user_2fa->get_secret() ) ? ' new' : ''; ?>"  /> <br />
				</div>

			</td>

			<td id="hma-2fa-secret-settings-ajax-loading" style="display: none;">
				<div class="spinner" style="display: block; float: left; margin: 0;"></div>
			</td>
		</tr>

	</table>

</div>
