<?php /* @var HM_2FA_USER $user_2fa */ ?>

<div id="hm-2fa">

	<h3>2 Factor Authentication</h3>

	<table class="form-table">

		<tr>
			<th><label for="hm-2fa-is-enabled">Enable 2 factor authentication</label></th>
			<td>
				<input name="hm_2fa_is_enabled" type="hidden" value="0" />
				<input id="hm-2fa-is-enabled" name="hm_2fa_is_enabled" type="checkbox" <?php checked( $user_2fa->get_2fa_enabled() ); ?> value="1" />
				<?php if (  $user_2fa->get_2fa_enabled()  ) : ?>
					<span class="description">2 factor authentication is currently enabled</span>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ( $user_2fa->has_capability( get_current_user_id(), 'hide' ) ) : ?>

			<tr>
				<th><label for="hm-2fa-is-hidden">Hide 2 factor authentication settings from user</label></th>
				<td>
					<input name="hm_2fa_is_hidden" type="hidden" value="0" />
					<input id="hm-2fa-is-hidden" name="hm_2fa_is_hidden" type="checkbox" <?php checked( $user_2fa->get_2fa_hidden() ); ?> value="1" />
				</td>
			</tr>

		<?php endif; ?>

		<tr id="hm-2fa-secret-settings" style="display: none;">
			<th><label for="hm-2fa-secret">Secret codes</label></th>
			<td id="hm-2fa-secret-settings-fields">

				<div id="hm-2fa-new-secret-steps" style="display: none;">

					<div class="hm-2fa-new-secret-step" id="hm-2fa-new-secret-step-1">

						<h3>Step 1/4</h3>

						<div class="hm-2fa-box">
							<span>Download the <a target="_blank" href="https://support.google.com/accounts/answer/1066447?hl=en">Google Authenticator</a> app to your device and scan the QR code below</span>
						</div>

						<div class="hm-2fa-box">
							<input type="text" name="hm_2fa_secret" id="hm-2fa-secret" placeholder="<?php echo ( $user_2fa->get_secret() ) ? 'It\'s a secret!' : ''; ?>"><br />
							<input type="hidden" name="hm_2fa_secret_confirm" id="hm-2fa-secret-confirm">
						</div>

						<div id="hm-2fa-qr-code" class="hm-2fa-box"></div>

						<div class="hm-2fa-box">
							<button class="button hm-2fa-new-secret-step-forward" id="hm-2fa-new-secret-step-1-forward" value="1">Continue</button>
						</div>

					</div>

					<div class="hm-2fa-new-secret-step" id="hm-2fa-new-secret-step-2" style="display: none;">

						<h3>Step 2/4</h3>

						<div class="hm-2fa-box">
							<span>Type in the code displayed on your Google Authenticator app to confirm that you have completed step 1 correctly</span>
						</div>

						<div class="hm-2fa-box hm-2fa-secret-verify-success" id="hm-2fa-secret-verify-success" style="display: none;">
							<p>
								<strong>The code that you entered was verified!</strong>
							</p>
						</div>

						<div class="hm-2fa-box hm-2fa-secret-verify-error" id="hm-2fa-secret-verify-error" style="display: none;">
							<p>
								<strong>The code that you entered was incorrect: Please make sure that your device's clock is correct and try again.</strong>
							</p>
						</div>

						<div class="hm-2fa-box">
							<input type="text" id="hm-2fa-secret-verify" value="" placeholder="123456" />
							<button class="button" id="hm-2fa-secret-verify-submit" value="2">Verify</button>
							<div class="hm-2fa-secret-verify-spinner" id="hm-2fa-secret-verify-spinner">
								<div class="spinner"></div>
							</div>
						</div>

						<div class="hm-2fa-box">
							<button class="button hm-2fa-new-secret-step-back" id="hm-2fa-new-secret-step-2-back" value="2">Back</button>
							<button class="button hm-2fa-new-secret-step-forward" id="hm-2fa-new-secret-step-2-forward" disabled="disabled" value="2">Continue</button>
						</div>

					</div>

					<div class="hm-2fa-new-secret-step" id="hm-2fa-new-secret-step-3" style="display: none;">

						<h3>Step 3/4</h3>

						<div class="hm-2fa-box">
							<span>Store these single use keys somewhere safe, we recommend printing them off. These are important!</span>
						</div>

						<div class="hm-2fa-box">
							<span class="description">You can use these keys for one time access to your account if you lose/break your device</span>
						</div>

						<div class="hm-2fa-box">
							<div id="hm-2fa-single-use-secrets" class="code hm-2fa-single-use-secrets" style="display: none;"></div>
						</div>

						<div class="hm-2fa-box">
							<button class="button hm-2fa-new-secret-step-back" id="hm-2fa-new-secret-step-3-back" value="3">Back</button>
							<button class="button hm-2fa-new-secret-step-forward" id="hm-2fa-new-secret-step-3-forward" value="3">Continue</button>
						</div>

					</div>

					<div class="hm-2fa-new-secret-step" id="hm-2fa-new-secret-step-4" style="display: none;">

						<h3>Step 4/4</h3>

						<div class="hm-2fa-box">
							<h4>Click 'Update Profile' to save your changes</h4>
						</div>

						<div class="hm-2fa-box">
							<span class="description">You're all done. You just need to submit your changes by clicking 'Update Profile' at the bottom of the page</span>
						</div>

						<div class="hm-2fa-box">
							<button class="button hm-2fa-new-secret-step-back" id="hm-2fa-new-secret-step-2-back" value="4">Back</button>
						</div>

					</div>

				</div>

				<input type="button" id="hm-2fa-generate-secret" class="button button-secondary" value="Generate<?php echo ( $user_2fa->get_secret() ) ? ' new' : ''; ?>"  />

			</td>

			<td id="hm-2fa-secret-settings-ajax-loading" class="spinner" style="display: none;"></td>
		</tr>

	</table>

</div>
