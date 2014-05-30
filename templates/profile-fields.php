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
