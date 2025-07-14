"use strict";
var cwginstock_ajax_url = cwginstock.ajax_url;
var cwginstock_security_error = cwginstock.security_error;
var cwginstock_userid = cwginstock.user_id;
var cwginstock_emptyname = cwginstock.empty_name;
var cwginstock_emptyemail = cwginstock.empty_email;
var cwginstock_emptyquantity = cwginstock.empty_quantity;
var cwginstock_invalidemail = cwginstock.invalid_email;
// Bot Protection Type
var cwginstock_get_bot_type = cwginstock.get_bot_type;
// Google reCAPTCHA
var cwginstock_recaptcha_enabled = cwginstock.enable_recaptcha;
var cwginstock_recaptcha_site_key = cwginstock.recaptcha_site_key;
var cwginstock_recaptcha_verify_enabled = cwginstock.enable_recaptcha_verify;
var cwginstock_recaptcha_secret_present = cwginstock.recaptcha_secret_present;

// Turnstile Captcha
var cwginstock_turnstile_enabled = cwginstock.enable_turnstile;
var cwginstock_turnstile_site_key = cwginstock.turnstile_site_key;

var cwginstock_is_iagree = cwginstock.is_iagree_enable;
var cwginstock_iagree_error = cwginstock.iagree_error;
var cwginstock_is_v3_recaptcha = cwginstock.is_v3_recaptcha;
var cwginstock_is_popup = cwginstock.is_popup;
var cwginstock_googlerecaptcha_widget_id = null;
var cwginstock_turnstile_widget_id = null;
var cwginstock_gtoken = '';
var cwginstock_iti;
var cwginstock_phone_field = cwginstock.phone_field;
var cwginstock_subscriber_phone = '';
var cwginstock_phone_meta_data = '';
var cwginstock_phone_error = cwginstock.phone_field_error;
var cwginstock_is_phone_field_optional = cwginstock.is_phone_field_optional;
var cwginstock_is_quantity_field_optional = cwginstock.is_quantity_field_optional;
var cwginstock_hide_country_placeholder = cwginstock.hide_country_placeholder;
var cwginstock_default_country_code = cwginstock.default_country_code;

function cwginstock_recaptcha_callback(response) {
	document.getElementsByClassName("cwgstock_button")[0].disabled = false;
	if (cwginstock_recaptcha_verify_enabled == '1' && cwginstock_recaptcha_secret_present == 'yes') {
		document.getElementsByClassName("cwg-security")[0].value = response;
	}
}


function cwginstock_turnstile_callback(response) {
	document.getElementsByClassName("cwgstock_button")[0].disabled = false;
	// if (recaptcha_verify_enabled == '1' && recaptcha_secret_present == 'yes') {
	document.getElementsByClassName("cwg-security")[0].value = response;
	// }
}

function cwgdetectTurnstileByHiddenInput(timeout = 4000) {
	const interval = 100;
	let waited = 0;

	const checker = setInterval(() => {
		const input = document.querySelector('input[name="cf-turnstile-response"][id^="cf-chl-widget-"]');

		if (input) {
			clearInterval(checker);
			console.log('Turnstile rendered');
		} else if (waited >= timeout) {
			clearInterval(checker);

			console.warn('Turnstile did NOT render (hidden input not found).');
			//add fallback code where it is not loaded
			const implicit_cfparent = document.querySelector('.cf-turnstile');

			if (implicit_cfparent) {
				const newDiv = document.createElement('div');
				newDiv.id = 'cwg-turnstile-captcha';
				implicit_cfparent.replaceWith(newDiv);
				instock_notifier.turnstilecallback();
			}

		}
		waited += interval;
	}, interval);
}
if (cwginstock_get_bot_type == 'turnstile' && cwginstock_turnstile_enabled == '1') {
	window.addEventListener('load', cwgdetectTurnstileByHiddenInput);
}
var instock_notifier = {
	init: function () {
		if (cwginstock_is_popup == 'no') {
			instock_notifier.generate_v3_response();
		}
		jQuery(document).on('click', '.cwgstock_button', this.submit_form);
		jQuery(".single_variation_wrap").on("show_variation", this.perform_upon_show_variation);
		if (cwginstock_phone_field == '1') {
			instock_notifier.initialize_phone();
		}
	},

	initialize_phone: function () {
		var input = document.querySelector(".cwgstock_phone");
		if (input) {
			cwginstock_iti = window.intlTelInput(
				input,
				{
					allowDropdown: true,
					formatOnDisplay: true,
					autoHideDialCode: false,
					separateDialCode: true,
					initialCountry: cwginstock_default_country_code,
					customPlaceholder: function (selectedCountryPlaceholder, selectedCountryData) {
						cwginstock_default_country_code = cwginstock_default_country_code.toLowerCase();
						if (cwginstock_default_country_code == selectedCountryData.iso2 && cwginstock.hide_country_placeholder == '2') {
							if (cwginstock.custom_country_placeholder != '') {
								return cwginstock.custom_country_placeholder;
							}
							return selectedCountryPlaceholder;
						} else {
							return '';
						}
					}
				}
			);

		}
	},
	perform_upon_show_variation: function (event, variation) {
		var vid = variation.variation_id;
		jQuery('.cwginstock-subscribe-form').hide(); // remove existing form
		jQuery('.cwginstock-subscribe-form-' + vid).show(); // add subscribe form to show
		if (cwginstock_get_bot_type == 'recaptcha') {
			if (cwginstock_recaptcha_enabled == '1') {
				instock_notifier.onloadcallback();
			}
		} else {
			if (cwginstock_turnstile_enabled == '1') {
				instock_notifier.turnstilecallback();
			}
		}

		if (cwginstock_phone_field == '1') {
			instock_notifier.initialize_phone();
		}
	},
	generate_v3_response: function () {
		if (cwginstock_get_bot_type == 'recaptcha' && cwginstock_recaptcha_enabled == '1' && cwginstock_is_v3_recaptcha == 'yes') {
			grecaptcha.ready(
				function () {
					grecaptcha.execute(cwginstock_recaptcha_site_key, { action: 'subscribe_form' }).then(
						function (token) {
							var hasClass = document.getElementsByClassName("cwg-security");
							if (hasClass.length > 0) {
								document.getElementsByClassName("cwg-security")[0].value = token;
								document.getElementsByClassName("cwgstock_button")[0].disabled = false;
								cwginstock_gtoken = token;
							}
						}
					);
				}
			);
		}
	},
	is_email: function (email) {
		var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
		return regex.test(email);
	},
	submit_form: function (e) {
		e.preventDefault();
		var submit_button_obj = jQuery(this);
		var subscriber_name = jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_name').val();
		var email_id = jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_email').val();
		var quantity = jQuery(this).closest('.cwginstock-subscribe-form').find('.add_quantity_field').val();
		if (quantity === '' || quantity <= 0) {
			if (cwginstock_is_quantity_field_optional == '2') {
				jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
				jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_emptyquantity + "</div>");
				return false;
			}
		}
		// Customised for Phone Field
		if (cwginstock_phone_field == '1') {
			var subscriber_phone = cwginstock_iti.getNumber();// jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_phone').val();
			cwginstock_phone_meta_data = cwginstock_iti.getSelectedCountryData();
			//console.log(phone_meta_data);
			if (!cwginstock_iti.isValidNumber()) {

				var errorCode = cwginstock_iti.getValidationError();
				console.log(errorCode);
				var errorMsg = cwginstock_phone_error[errorCode];
				if (errorCode == -99) {
					errorMsg = cwginstock_phone_error[0];
				}
				if ((errorCode != -99 && cwginstock_is_phone_field_optional == '1') || cwginstock_is_phone_field_optional == '2') {
					jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
					jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + errorMsg + "</div>");
					return false;
				}
			}
		}
		var product_id = jQuery(this).closest('.cwginstock-subscribe-form').find('.cwg-product-id').val();
		var var_id = jQuery(this).closest('.cwginstock-subscribe-form').find('.cwg-variation-id').val();
		if (subscriber_name == '') {
			jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
			jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_emptyname + "</div>");
			return false;

		} else if (email_id == '') {
			jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
			jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_emptyemail + "</div>");
			return false;
		} else {
			// check is valid email
			if (!instock_notifier.is_email(email_id)) {
				jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
				jQuery(this).closest('.cwginstock-subscribe_form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_invalidemail + "</div>");
				return false;
			}

			if (cwginstock_is_iagree == '1') {
				if (!jQuery(this).closest('.cwginstock-subscribe-form').find('.cwg_iagree_checkbox_input').is(':checked')) {
					jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn();
					jQuery(this).closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_iagree_error + "</div>");
					return false;
				}
			}
			var security = jQuery(this).closest('.cwginstock-subscribe-form').find('.cwg-security').val();
			var data = {
				action: 'cwginstock_product_subscribe',
				product_id: product_id,
				variation_id: var_id,
				subscriber_name: subscriber_name,
				subscriber_phone: subscriber_phone,
				subscriber_phone_meta: JSON.stringify(cwginstock_phone_meta_data),
				user_email: email_id,
				user_id: cwginstock_userid,
				security: security,
				dataobj: cwginstock,
				custom_quantity: quantity
			};

			// jQuery.blockUI({message: null});
			if (jQuery.fn.block) {
				submit_button_obj.closest('.cwginstock-subscribe-form').block({ message: null });
			} else {
				var overlay = jQuery('<div id="cwg-bis-overlay"> </div>');
				overlay.appendTo(submit_button_obj.closest('.cwginstock-subscribe-form'));
			}

			// perform ajax functionality
			instock_notifier.perform_ajax(data, submit_button_obj);
		}
	},

	recaptcha_callback: function (response) {

		var hasClass = document.getElementsByClassName("cwg-security");
		if (hasClass.length > 0) {
			document.getElementsByClassName("cwgstock_button")[0].disabled = false;
			if (cwginstock_recaptcha_verify_enabled == '1' && cwginstock_recaptcha_secret_present == 'yes') {
				document.getElementsByClassName("cwg-security")[0].value = response;
			}
		}
	},
	turnstile_callback: function (response) {
		var hasClass = document.getElementsByClassName("cwg-security");
		if (hasClass.length > 0) {
			document.getElementsByClassName("cwgstock_button")[0].disabled = false;
			// if (recaptcha_verify_enabled == '1' && recaptcha_secret_present == 'yes') {
			document.getElementsByClassName("cwg-security")[0].value = response;
			// }
		}
	},
	onloadcallback: function () {
		if (cwginstock_get_bot_type == 'recaptcha') {
			if (cwginstock_recaptcha_enabled == '1') {
				if (cwginstock_is_v3_recaptcha == 'no') {
					if (jQuery('#cwg-google-recaptcha').length) {
						if (cwginstock_googlerecaptcha_widget_id === null) {
							cwginstock_googlerecaptcha_widget_id = grecaptcha.render(
								'cwg-google-recaptcha',
								{
									'sitekey': cwginstock_recaptcha_site_key,
									'callback': this.recaptcha_callback,
								}
							);
						} else {
							grecaptcha.reset(cwginstock_googlerecaptcha_widget_id);
							this.recaptcha_callback();
							cwginstock_googlerecaptcha_widget_id = null;
							instock_notifier.onloadcallback();
						}
					}
				} else {
					instock_notifier.generate_v3_response();
				}
			}
		}
	},
	turnstilecallback: function () {
		if (cwginstock_get_bot_type == 'turnstile') {
			if (cwginstock_turnstile_enabled == '1') {
				if (jQuery('#cwg-turnstile-captcha').length) {
					if (cwginstock_turnstile_widget_id === null) {
						cwginstock_turnstile_widget_id = turnstile.render(
							'#cwg-turnstile-captcha',
							{
								sitekey: cwginstock_turnstile_site_key,
								theme: 'light',
								callback: this.turnstile_callback,
							}
						);
					} else {
						turnstile.reset(cwginstock_turnstile_widget_id);
						this.turnstile_callback();
						cwginstock_turnstile_widget_id = null;
						instock_notifier.turnstilecallback();
					}
				}
			}
		}
	},
	resetcallback: function () {
		if (cwginstock_get_bot_type == 'recaptcha') {
			if (cwginstock_recaptcha_enabled == '1') {
				if (cwginstock_is_v3_recaptcha == 'no') {
					grecaptcha.reset();
					document.getElementsByClassName("cwgstock_button")[0].disabled = true;
				} else {
					instock_notifier.generate_v3_response();
				}
			}
		} else {
			if (cwginstock_turnstile_enabled == '1') {
				turnstile.reset();
				document.getElementsByClassName("cwgstock_button")[0].disabled = true;
			}
		}
	},
	perform_ajax: function (data, submit_button_obj) {

		jQuery.ajax(
			{
				type: "post",
				url: cwginstock_ajax_url,
				data: data,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', cwginstock.security);
				},
				success: function (msg) {
					msg = msg.msg;
					submit_button_obj.closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn(2000);
					submit_button_obj.closest('.cwginstock-subscribe-form').find('.cwgstock_output').html(msg);
					// jQuery.unblockUI();
					if (jQuery.fn.block) {
						submit_button_obj.closest('.cwginstock-subscribe-form').unblock();
					} else {
						submit_button_obj.closest('.cwginstock-subscribe-form').find('#cwg-bis-overlay').fadeOut(
							400,
							function () {
								submit_button_obj.closest('.cwginstock-subscribe-form').find('#cwg-bis-overlay').remove();
							}
						);
					}
					instock_notifier.resetcallback();
					jQuery(document).trigger('cwginstock_success_ajax', data);
				},
				error: function (request, status, error) {
					if (typeof request.responseJSON !== 'undefined') {
						if (request.responseJSON.hasOwnProperty('code')) {
							if (typeof request.responseJSON.code !== 'undefined') {
								if ((request.responseJSON.code == 'rest_cookie_invalid_nonce') || (request.responseJSON.code == 'cwg_nonce_verify_failed')) {
									request.responseText = -1;
								}
							}
						}
					}
					if (request.responseText === '-1' || request.responseText === -1) {
						submit_button_obj.closest('.cwginstock-subscribe-form').find('.cwgstock_output').fadeIn(2000);
						submit_button_obj.closest('.cwginstock-subscribe-form').find('.cwgstock_output').html("<div class='cwginstockerror' style='color:red;'>" + cwginstock_security_error + "</div>");
					}
					// jQuery.unblockUI();
					if (jQuery.fn.block) {
						submit_button_obj.closest('.cwginstock-subscribe-form').unblock();
					} else {
						submit_button_obj.closest('.cwginstock-subscribe-form').find('#cwg-bis-overlay').fadeOut(
							400,
							function () {
								submit_button_obj.closest('.cwginstock-subscribe-form').find('#cwg-bis-overlay').remove();
							}
						);
					}
					instock_notifier.resetcallback();
					jQuery(document).trigger('cwginstock_error_ajax', data);
				}
			}
		);
	},
};

instock_notifier.init();
