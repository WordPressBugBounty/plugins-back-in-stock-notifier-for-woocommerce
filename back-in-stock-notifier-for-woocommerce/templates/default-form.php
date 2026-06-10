<section
	class="cwginstock-subscribe-form <?php echo esc_attr( $variation_class ); ?> <?php echo esc_attr( $dynamic_wrapper_class ); ?>">
	<div class="panel panel-primary cwginstock-panel-primary">
		<div class="panel-heading cwginstock-panel-heading">
			<?php
			/**
			 * Executed Before Heading
			 *
			 * @since 5.6.0
			 */
			do_action( 'cwg_instock_before_heading', $product_id, $variation_id );
			$enable_accessibility_compliance = isset( $get_option['enable_accessibility_compliance'] ) && '1' == $get_option['enable_accessibility_compliance'];
			$heading_id                      = 'cwginstock-form-title-' . intval( $product_id ) . '-' . intval( $variation_id );
			?>
			<h4 id="<?php echo esc_attr( $heading_id ); ?>" style="text-align: center;">
				<?php
				$form_title = esc_html__( 'Email when stock available', 'back-in-stock-notifier-for-woocommerce' );
				echo esc_attr( isset( $get_option['form_title'] ) && '' != $get_option['form_title'] ? $instock_api->sanitize_text_field( $get_option['form_title'] ) : $form_title );
				?>
			</h4>
			<?php
			/**
			 * Executed After Heading
			 *
			 * @since 5.6.0
			 */
			do_action( 'cwg_instock_after_heading', $product_id, $variation_id );
			?>
		</div>
		<div class="panel-body cwginstock-panel-body">
			<?php
			if ( ! isset( $get_option['enable_troubleshoot'] ) || '1' != $get_option['enable_troubleshoot'] ) {
				?>
				<div class="row">
					<div class="col-md-12">
						<div class="col-md-12">
						<?php } ?>
						<div class="cwginstock-subscribe-form__form"
						<?php 
						if ( $enable_accessibility_compliance ) :
							?>
							 role="form" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" aria-label="<?php echo esc_attr__( 'Back in stock subscription form', 'back-in-stock-notifier-for-woocommerce' ); ?>"<?php endif; ?>>
							<div class="form-group center-block">
								<?php
								/**
								 * Executed Before Input Fields
								 *
								 * @since 5.6.0
								 */
								do_action( 'cwg_instock_before_input_fields', $product_id, $variation_id );
								$base_id = 'cwginstock-form-' . intval( $product_id ) . '-' . intval( $variation_id );
								if ( $name_field_visibility ) {
									$name_field_id = $base_id . '-name';
									if ( $enable_accessibility_compliance ) {
										?>
										<label for="<?php echo esc_attr( $name_field_id ); ?>" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0, 0, 0, 0); white-space:nowrap; border:0;">
											<?php echo esc_html__( 'Your name', 'back-in-stock-notifier-for-woocommerce' ); ?>
										</label>
										<?php 
									}
									?>
									<input id="<?php echo esc_attr( $name_field_id ); ?>" type="text" style="width:100%; text-align:center;" class="cwgstock_name"
										name="cwgstock_name"
										placeholder="<?php echo esc_attr( $instock_api->sanitize_text_field( $name_placeholder ) ); ?>"
										value="<?php echo esc_attr( $subscriber_name ); ?>"
										autocomplete="name" />
								<?php } ?>
								<?php
								$email_field_id = $base_id . '-email';
								if ( $enable_accessibility_compliance ) {
									?>
									<label for="<?php echo esc_attr( $email_field_id ); ?>" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0, 0, 0, 0); white-space:nowrap; border:0;">
										<?php echo esc_html__( 'Email address', 'back-in-stock-notifier-for-woocommerce' ); ?>
									</label>
								<?php } ?>
								<input id="<?php echo esc_attr( $email_field_id ); ?>" type="email" style="width:100%; text-align:center;" class="cwgstock_email"
									name="cwgstock_email"
									placeholder="<?php echo esc_attr( $instock_api->sanitize_text_field( $placeholder ) ); ?>"
									value="<?php echo esc_attr( $email ); ?>"
									<?php 
									if ( $enable_accessibility_compliance ) {
										?>
										 required aria-required="true" autocomplete="email" inputmode="email" 
										<?php 
									} else {
										?>
										 autocomplete="email" <?php } ?> />
								<?php 
								if ( $phone_field_visibility ) { 
									$phone_field_id = $base_id . '-phone';
									if ( $enable_accessibility_compliance ) {
										?>
										<label for="<?php echo esc_attr( $phone_field_id ); ?>" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0, 0, 0, 0); white-space:nowrap; border:0;">
											<?php echo esc_html__( 'Phone number', 'back-in-stock-notifier-for-woocommerce' ); ?>
										</label>
										<?php 
									}
									?>
									<input id="<?php echo esc_attr( $phone_field_id ); ?>" type="tel" class="cwgstock_phone" name="cwgstock_phone" autocomplete="tel" inputmode="tel" />
								<?php } ?>
							</div>
						<?php
						/**
						 * Executed after the email input field in the form.
						 *
						 * @since 1.0.0
						 */
						do_action( 'cwg_instock_after_email_field', $product_id, $variation_id );
						?>
						<input type="hidden" class="cwg-product-id" name="cwg-product-id"
							value="<?php echo intval( $product_id ); ?>" />
						<input type="hidden" class="cwg-variation-id" name="cwg-variation-id"
							value="<?php echo intval( $variation_id ); ?>" />
						<input type="hidden" class="cwg-security" name="cwg-security"
							value="<?php echo esc_attr( $security ); ?>" />
						<?php
						/**
						 * Executed After Input Fields
						 *
						 * @since 5.6.0
						 */
						do_action( 'cwg_instock_after_input_fields', $product_id, $variation_id );
						?>
						<div class="form-group center-block" style="text-align:center;">
							<?php
							/**
							 * Executed Before Submit Button
							 *
							 * @since 5.6.0
							 */
							do_action( 'cwginstock_before_submit_button', $product_id, $variation_id );
							$additional_class_name = isset( $get_option['btn_class'] ) && '' != $get_option['btn_class'] ? str_replace( ',', ' ', $get_option['btn_class'] ) : '';
							?>
							<input type="<?php echo $enable_accessibility_compliance ? 'button' : 'submit'; ?>" name="cwgstock_submit"
								class="cwgstock_button <?php echo esc_attr( $additional_class_name ); ?>"
								<?php 
								if ( $enable_accessibility_compliance ) {
									?>
									aria-label="<?php echo esc_attr( $instock_api->sanitize_text_field( $button_label ) ); ?>"<?php } ?>
																  <?php
																	/**
																	 * Submit Attribute
																	 *
																	 * @since 1.0.0
																	 */
																	echo do_shortcode( apply_filters( 'cwgstock_submit_attr', '', $product_id, $variation_id ) );
																	?>
								value="<?php echo esc_attr( $instock_api->sanitize_text_field( $button_label ) ); ?>" />
							<?php
							/**
							 * Executed after the submit button
							 *
							 * @since 1.0.0
							 */
							do_action( 'cwginstock_after_submit_button', $product_id, $variation_id );
							?>

						</div>
						<div class="cwgstock_output"
						<?php 
						if ( $enable_accessibility_compliance ) {
							?>
							 aria-live="polite" aria-atomic="true"<?php } ?>></div>
						</div>
						<?php
						if ( ! isset( $get_option['enable_troubleshoot'] ) || '1' != $get_option['enable_troubleshoot'] ) {
							?>
						</div>
					</div>
				</div>
							<?php
						}
						?>

			<!-- End ROW -->

		</div>
	</div>
</section>
