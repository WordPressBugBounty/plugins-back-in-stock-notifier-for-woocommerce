"use strict";
var get_bot_type = cwginstock.get_bot_type;
var recaptcha_enabled = cwginstock.enable_recaptcha;
var is_v3_recaptcha = cwginstock.is_v3_recaptcha;
var recaptcha_site_key = cwginstock.recaptcha_site_key;
// turnstile
var turnstile_enabled = cwginstock.enable_turnstile;
var turnstile_site_key = cwginstock.turnstile_site_key;

var gtoken = '';

if (typeof wc_bulk_variations_params !== 'undefined') {
	document.addEventListener(
		'click',
		function (event) {
			if (event.target.matches('.cwg_popup_submit')) {
				console.log(event.target.dataset.product_id);
				console.log('Captured click before stopPropagation!');
				var e = event.target;
				jQuery.blockUI({ message: null });
				var product_id = e.dataset.product_id;
				var variation_id = e.dataset.variation_id;
				var quantity = e.dataset.quantity
				var security = e.dataset.security

				var data = {
					action: 'cwg_trigger_popup_ajax',
					product_id: product_id,
					variation_id: variation_id,
					quantity: quantity,
					security: security
				};
				if (get_bot_type == 'recaptcha' && recaptcha_enabled == '1' && is_v3_recaptcha == 'yes') {
					popup_notifier.popup_generate_v3_response(this);
				} else {
					popup_notifier.perform_ajax(data);
				}
				return false;
			}
		},
		true // This enables the capturing phase
	);
}

var popup_notifier = {
	init: function () {
		jQuery(document).on(
			'click',
			'.cwg_popup_submit',
			function () {

				jQuery.blockUI({ message: null });
				var current = jQuery(this);
				var product_id = current.attr('data-product_id');
				var variation_id = current.attr('data-variation_id');
				var quantity = current.attr('data-quantity');
				var security = current.attr('data-security');

				var data = {
					action: 'cwg_trigger_popup_ajax',
					product_id: product_id,
					variation_id: variation_id,
					quantity: quantity,
					security: security
				};
				if (get_bot_type == 'recaptcha' && recaptcha_enabled == '1' && is_v3_recaptcha == 'yes') {
					popup_notifier.popup_generate_v3_response(this);
				} else {
					popup_notifier.perform_ajax(data);
				}
				return false;
			}
		);
	},
	popup_generate_v3_response: function (currentel) {
		if (get_bot_type == 'recaptcha' && recaptcha_enabled == '1' && is_v3_recaptcha == 'yes') {
			grecaptcha.ready(
				function () {
					grecaptcha.execute(recaptcha_site_key, { action: 'popup_form' }).then(
						function (token) {
							console.log(token);

							var current = jQuery(currentel);
							var product_id = current.attr('data-product_id');
							var variation_id = current.attr('data-variation_id');
							var quantity = current.attr('data-quantity');

							var data = {
								action: 'cwg_trigger_popup_ajax',
								product_id: product_id,
								variation_id: variation_id,
								quantity: quantity,
								security: token
							};
							popup_notifier.perform_ajax(data);
							gtoken = token;
						}
					);
				}
			);
		}
	},
	/**
	 * Render the returned form inside a native modal (no SweetAlert).
	 * Used when a custom popup design is selected in settings.
	 *
	 * @since 7.4.0
	 */
	render_native_modal: function (html, design) {
		// Remove any modal left over from a previous open.
		jQuery('.cwg-modal-overlay').remove();

		var skin = 'cwg-modal-' + String(design).replace('modal_', '');
		var overlay = document.createElement('div');
		overlay.className = 'cwg-modal-overlay ' + skin;
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');

		var box = document.createElement('div');
		box.className = 'cwg-modal-box';

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'cwg-modal-close';
		close.innerHTML = '&times;';
		close.setAttribute('aria-label', (typeof cwginstock !== 'undefined' && cwginstock.popup_close_label) ? cwginstock.popup_close_label : 'Close');

		var content = document.createElement('div');
		content.className = 'cwg-modal-content';
		content.innerHTML = html;

		box.appendChild(close);
		box.appendChild(content);
		overlay.appendChild(box);
		document.body.appendChild(overlay);

		function closeModal() {
			overlay.classList.remove('cwg-modal-visible');
			jQuery(document).trigger('cwginstock_popup_close_callback');
			setTimeout(function () { jQuery(overlay).remove(); }, 250);
			document.removeEventListener('keydown', onKey);
		}
		function onKey(e) { if (e.key === 'Escape') { closeModal(); } }

		close.addEventListener('click', closeModal);
		overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeModal(); } });
		document.addEventListener('keydown', onKey);

		// Render bot protection widgets inside the modal, same as the SweetAlert flow.
		if ('recaptcha' == get_bot_type) {
			if ('1' == recaptcha_enabled) {
				jQuery(overlay).find('.g-recaptcha').before('<div id="cwg-google-recaptcha"></div>');
				jQuery(overlay).find('.g-recaptcha').remove();
			}
		} else if ('1' == turnstile_enabled && typeof turnstile !== 'undefined') {
			try {
				turnstile.render(
					jQuery(overlay).find('.cf-turnstile')[0],
					{
						sitekey: turnstile_site_key,
						theme: 'light',
						callback: function (token) { cwginstock_turnstile_callback(token); }
					}
				);
			} catch (e) { }
		}

		// Trigger the transition on the next frame so it animates in.
		requestAnimationFrame(function () { overlay.classList.add('cwg-modal-visible'); });
		jQuery(document).trigger('cwginstock_popup_open_callback');
	},

	perform_ajax: function (data) {
		jQuery.ajax(
			{
				type: "post",
				url: cwginstock.default_ajax_url,
				data: data,
				success: function (msg) {
					jQuery.unblockUI();
					var popup_design = (typeof cwginstock !== 'undefined' && cwginstock.popup_design) ? cwginstock.popup_design : 'sweetalert';
					if (popup_design !== 'sweetalert') {
						popup_notifier.render_native_modal(msg, popup_design);
						return;
					}
					Swal.fire(
						{
							html: msg,
							showCloseButton: true,
							showConfirmButton: false,
							willOpen: function () {
								if ('recaptcha' == get_bot_type) {
									if ('1' == recaptcha_enabled) {
										jQuery('.g-recaptcha').before('<div id="cwg-google-recaptcha"></div>');
										jQuery('.g-recaptcha').remove();
									}
								} else {
									if ('1' == turnstile_enabled) {
										turnstile.render(
											'.cf-turnstile',
											{
												sitekey: turnstile_site_key,
												theme: 'light',
												callback: function (token) {
													cwginstock_turnstile_callback(token);
												},
											}
										);
									}
								}
							},
							didOpen: function () {
								jQuery(document).trigger('cwginstock_popup_open_callback');
							},
							willClose: function () {
								jQuery(document).trigger('cwginstock_popup_close_callback');
							},
						}
					);

				},
				error: function (request, status, error) {
					jQuery.unblockUI();
				}
			}
		);
	},
};
popup_notifier.init();


jQuery(document).on(
	'cwginstock_popup_open_callback',
	function () {
		instock_notifier.onloadcallback();
		instock_notifier.initialize_phone();
	}
);

jQuery(document).on(
	'cwginstock_popup_close_callback',
	function () {
		instock_notifier.resetcallback();
	}
);