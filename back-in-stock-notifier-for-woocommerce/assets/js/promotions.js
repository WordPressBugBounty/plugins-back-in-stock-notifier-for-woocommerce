/**
 * Back In Stock Notifier - Promotions Page JS
 *
 * Two-level filtering with styled buttons:
 *  Level 1: Type buttons - All / Add-ons / Pro Plugins
 *  Level 2: Subcategory buttons - context-switches based on active type
 *    - "All" type: shows addon subcats, filters only addon cards
 *    - "Add-ons" type: shows addon subcats (General, Utilities, etc.)
 *    - "Pro Plugins" type: shows pro subcats (auto-generated: Products, Pricing, etc.)
 *
 * @since 7.2.0
 */
(function ($) {
	'use strict';

	$(function () {

		var activeType     = 'all';
		var activeAddonCat = 'all';
		var activeProCat   = 'all';

		var $cards         = $('#cwg-promo-grid .cwg-promo-card');
		var $addonSubcats  = $('#cwg-subcats-addon');
		var $proSubcats    = $('#cwg-subcats-pro');

		/* ── Apply combined filter ──────────────────────────────── */
		function applyFilters() {
			$cards.each(function () {
				var $card    = $(this);
				var cardType = $card.data('type');
				var cardCat  = $card.data('category');
				var show     = true;

				// Type filter
				if (activeType !== 'all' && cardType !== activeType) {
					show = false;
				}

				// Subcategory filter
				if (show) {
					if (cardType === 'addon') {
						if (activeAddonCat !== 'all' && cardCat !== activeAddonCat) {
							// Filter addon by addon subcategory (when type is 'all' or 'addon')
							if (activeType === 'all' || activeType === 'addon') {
								show = false;
							}
						}
					} else if (cardType === 'pro') {
						if (activeProCat !== 'all' && cardCat !== activeProCat) {
							// Filter pro by pro subcategory (when type is 'all' or 'pro')
							if (activeType === 'pro') {
								show = false;
							}
						}
					}
				}

				$card.toggleClass('cwg-hidden', !show);
			});
		}

		/* ── Switch subcategory panels based on type ─────────── */
		function switchSubcatPanels() {
			if (activeType === 'free' || activeType === 'codecanyon') {
				// These types have no subcategories of their own.
				$addonSubcats.slideUp(150);
				$proSubcats.slideUp(150);
			} else if (activeType === 'pro') {
				$addonSubcats.slideUp(150);
				$proSubcats.slideDown(150);
			} else {
				// "all" or "addon" - show addon subcats
				$proSubcats.slideUp(150);
				$addonSubcats.slideDown(150);
			}
		}

		/* ── Type Buttons ──────────────────────────────────────── */
		$('.cwg-type-btn').on('click', function () {
			var $btn = $(this);
			activeType = $btn.data('type');

			$('.cwg-type-btn').removeClass('active');
			$btn.addClass('active');

			// Reset subcategory filters when switching type
			if (activeType === 'pro') {
				activeProCat = 'all';
				$proSubcats.find('.cwg-subcat-btn').removeClass('active');
				$proSubcats.find('.cwg-subcat-btn[data-category="all"]').addClass('active');
			} else {
				activeAddonCat = 'all';
				$addonSubcats.find('.cwg-subcat-btn').removeClass('active');
				$addonSubcats.find('.cwg-subcat-btn[data-category="all"]').addClass('active');
			}

			switchSubcatPanels();
			applyFilters();
		});

		/* ── Subcategory Buttons ────────────────────────────────── */
		$(document).on('click', '.cwg-subcat-btn', function () {
			var $btn = $(this);
			var cat  = $btn.data('category');
			var forType = $btn.data('for');

			// Update active state within same panel
			$btn.closest('.cwg-promo-subcats').find('.cwg-subcat-btn').removeClass('active');
			$btn.addClass('active');

			if (forType === 'pro') {
				activeProCat = cat;
			} else {
				activeAddonCat = cat;
			}

			applyFilters();
		});

		/* ── One click install for free WordPress.org plugins ────── */

		// Core marks itself busy during an install and warns on navigation.
		// Clear that before we move the user, otherwise the browser shows
		// "Leave site? Changes you made may not be saved".
		function cwgReleaseUpdateLock() {
			if (typeof wp !== 'undefined' && wp.updates) {
				wp.updates.ajaxLocked = false;
				if (wp.updates.queue) {
					wp.updates.queue = [];
				}
			}
			$(window).off('beforeunload');
		}

		function cwgSetActivateButton($btn, activateUrl) {
			var $link = $('<a/>', {
				'class': 'cwg-promo-card-btn cwg-promo-card-btn--free',
				href: activateUrl
			}).html('<span class="dashicons dashicons-yes"></span> ' + cwgPromotions.i18n.activate);
			$btn.replaceWith($link);
		}

		$(document).on('click', '.cwg-install-plugin', function (e) {
			e.preventDefault();

			var $btn = $(this),
				slug = $btn.data('slug');

			if (!slug || $btn.hasClass('cwg-installing')) {
				return;
			}
			if (typeof wp === 'undefined' || !wp.updates || !wp.updates.installPlugin) {
				window.location.href = 'plugin-install.php?s=' + encodeURIComponent(slug) + '&tab=search&type=term';
				return;
			}

			$btn.addClass('cwg-installing').prop('disabled', true)
				.html('<span class="dashicons dashicons-update"></span> ' + cwgPromotions.i18n.installing);

			wp.updates.installPlugin({
				slug: slug,
				success: function (response) {
					cwgReleaseUpdateLock();
					$btn.removeClass('cwg-installing');

					var activateUrl = (response && response.activateUrl) ? response.activateUrl : $btn.data('activate-url');
					if (activateUrl) {
						// Let the user click Activate instead of redirecting for
						// them, which is what WordPress itself does.
						cwgSetActivateButton($btn, activateUrl);
					} else {
						$btn.html('<span class="dashicons dashicons-yes"></span> ' + cwgPromotions.i18n.installed);
					}
				},
				error: function (response) {
					cwgReleaseUpdateLock();
					$btn.removeClass('cwg-installing');

					var msg = (response && response.errorMessage) ? String(response.errorMessage) : '';
					var alreadyThere = /already exists|already installed|destination folder/i.test(msg);
					var activateUrl  = $btn.data('activate-url');

					if (alreadyThere && activateUrl) {
						// Installed under a different folder name, offer Activate.
						cwgSetActivateButton($btn, activateUrl);
						return;
					}
					if (alreadyThere) {
						$btn.prop('disabled', false)
							.html('<span class="dashicons dashicons-info"></span> ' + cwgPromotions.i18n.already_installed);
						return;
					}

					$btn.prop('disabled', false)
						.html('<span class="dashicons dashicons-warning"></span> ' + cwgPromotions.i18n.install_fail);
					if (msg) {
						window.console && window.console.log(msg);
					}
				}
			});
		});

		/* ── Click to copy the promotion code ────────────────────── */
		$(document).on('click', '.cwg-promo-offer-code', function () {
			var $el  = $(this),
				code = $el.data('code'),
				original = $el.text();

			function done() {
				$el.addClass('cwg-copied').text(cwgPromotions.i18n.copied);
				setTimeout(function () { $el.removeClass('cwg-copied').text(original); }, 1600);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(done);
				return;
			}
			// Fallback for browsers without the clipboard API.
			var $tmp = $('<input>').val(code).appendTo('body').select();
			try { document.execCommand('copy'); done(); } catch (e) { }
			$tmp.remove();
		});

		/* ── Feed refresh ────────────────────────────────────────── */
		$('#cwg-refresh-feed').on('click', function () {
			var $btn    = $(this),
				$text   = $btn.find('.cwg-btn-text'),
				$notice = $('#cwg-promo-notice'),
				originalText = $text.text();

			if ($btn.hasClass('refreshing')) {
				return;
			}

			// Lock width to avoid layout jump while refreshing
			$btn.css('min-width', $btn.outerWidth() + 'px');
			$btn.addClass('refreshing').prop('disabled', true);
			$text.text(cwgPromotions.i18n.refreshing);
			$notice.hide();

			$.ajax({
				url:      cwgPromotions.ajax_url,
				type:     'POST',
				dataType: 'json',
				data: {
					action:   cwgPromotions.action,
					security: cwgPromotions.nonce
				},
				success: function (response) {
					if (response.success) {
						$notice.removeClass('notice-error')
							   .addClass('notice-success')
							   .find('p')
							   .text(response.data.message);
						$notice.slideDown(200);
						setTimeout(function () {
							window.location.reload();
						}, 2000);
					} else {
						$notice.removeClass('notice-success')
							   .addClass('notice-error')
							   .find('p')
							   .text(response.data ? response.data.message : cwgPromotions.i18n.error);
						$notice.slideDown(200);
					}
				},
				error: function () {
					$notice.removeClass('notice-success')
						   .addClass('notice-error')
						   .find('p')
						   .text(cwgPromotions.i18n.error);
					$notice.slideDown(200);
				},
				complete: function () {
					$btn.removeClass('refreshing').prop('disabled', false).css('min-width', '');
					$text.text(originalText);
				}
			});
		});
	});

})(jQuery);
