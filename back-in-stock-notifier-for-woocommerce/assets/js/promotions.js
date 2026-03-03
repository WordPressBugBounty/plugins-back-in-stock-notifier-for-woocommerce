/**
 * Back In Stock Notifier — Promotions Page JS
 *
 * Two-level filtering with styled buttons:
 *  Level 1: Type buttons — All / Add-ons / Pro Plugins
 *  Level 2: Subcategory buttons — context-switches based on active type
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
			if (activeType === 'pro') {
				$addonSubcats.slideUp(150);
				$proSubcats.slideDown(150);
			} else {
				// "all" or "addon" — show addon subcats
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

		/* ── Feed refresh ────────────────────────────────────────── */
		$('#cwg-refresh-feed').on('click', function () {
			var $btn    = $(this),
				$notice = $('#cwg-promo-notice');

			if ($btn.hasClass('refreshing')) {
				return;
			}

			$btn.addClass('refreshing').prop('disabled', true);
			$btn.find('.cwg-btn-text').text(cwgPromotions.i18n.refreshing);
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
					$btn.removeClass('refreshing').prop('disabled', false);
					$btn.find('.cwg-btn-text').text(cwgPromotions.i18n.refresh_feed);
				}
			});
		});
	});

})(jQuery);
