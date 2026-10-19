// wc_zelle_popup // wp_localize_script object
//
// Checkout design 1: on Place order, posts the checkout with wc_zelle_draft=1 so WooCommerce creates the draft order, then shows its number and the Zelle details in a popup.
// Confirming submits the checkout normally and WooCommerce resumes that same order. @see WC_Zelle_Draft_Order

jQuery(function ($) {
	"use strict";

	var settings = window.wc_zelle_popup || {};
	var gateway = settings.gateway || "zelle";
	var i18n = settings.i18n || {};
	var popupId = "#wc-" + gateway + "-popup";
	var confirmedSelector = ".wcz-popup-confirmed input[type='checkbox']";

	// Set while the confirmed checkout is submitted, so the popup lets it through
	var submitting = false;

	function getForm() {
		return $("form.checkout").first();
	}

	function getPopup() {
		return $(popupId).first();
	}

	function isPopupOpen() {
		var $popup = getPopup();
		return $popup.length && !$popup.prop("hidden");
	}

	function checkoutUrl() {
		if (settings.checkout) {
			return settings.checkout;
		}
		return window.wc_checkout_params
			? window.wc_checkout_params.checkout_url
			: null;
	}

	function block($el) {
		if ($el.length && $.fn.block) {
			$el.block({
				message: null,
				overlayCSS: { background: "#fff", opacity: 0.6 },
			});
		}
	}

	function unblock($el) {
		if ($el.length && $.fn.unblock) {
			$el.unblock();
		}
	}

	function showError($popup, message) {
		$popup
			.find(".wcz-popup-error")
			.text(message || "")
			.prop("hidden", !message);
	}

	/* Opening and closing */

	function fillPopup($popup, order) {
		$popup.find("[data-wcz-order-number]").text(order.number || "");
	}

	function getConfirmed($popup) {
		return $popup.find(confirmedSelector).first();
	}

	function openPopup($popup) {
		showError($popup, "");
		$popup.find(".wcz-popup-confirm").removeClass("wcz-busy");

		// Cleared on every open, so confirming is always a deliberate act rather than
		// something left ticked from the last time the popup was looked at
		var $confirmed = getConfirmed($popup);
		$confirmed.prop("checked", false);

		$popup.prop("hidden", false).attr("aria-hidden", "false");
		$("body").addClass("wcz-popup-open");

		($confirmed.length
			? $confirmed
			: $popup.find(".wcz-popup-confirm")
		).trigger("focus");
	}

	function hidePopup() {
		var $popup = getPopup();
		if ($popup.length) {
			showError($popup, "");
			$popup.find(".wcz-popup-confirm").removeClass("wcz-busy");
			$popup.prop("hidden", true).attr("aria-hidden", "true");
		}
		$("body").removeClass("wcz-popup-open");
	}

	/* The pending order behind the popup */

	function requestDraftOrder() {
		var $form = getForm();
		var url = checkoutUrl();

		if (!$form.length || !url) {
			return placeOrder();
		}

		block($form);

		$.ajax({
			type: "POST",
			url: url,
			data: $form.serialize() + "&wc_zelle_draft=1",
			dataType: "json",
		})
			.done(function (result) {
				// Placed outright (e.g. a cached page after the popup was turned off): submitting again would hit an emptied cart
				if (result && "success" === result.result && result.redirect) {
					window.location = result.redirect;
					return;
				}

				if (!result || "zelle_draft" !== result.result) {
					// Validation errors, an expired session, anything else: hand it
					// back to WooCommerce so it reports it the way it always does
					return placeOrder();
				}

				var $popup = getPopup();

				try {
					fillPopup($popup, result);
					openPopup($popup);
				} catch (err) {
					return placeOrder();
				}

				// A popup the customer cannot see (theme, missing stylesheet) would strand them, so place the order instead
				if (!$popup.is(":visible")) {
					hidePopup();
					return placeOrder();
				}

				// Left blocked on every path above, because WooCommerce is taking the
				// checkout from here and unblocks it itself
				unblock($form);
			})
			.fail(function () {
				// Never leave the customer stuck: fall back to the normal checkout,
				// which shows the Zelle details on the order confirmation page instead
				placeOrder();
			});
	}

	// Submit the checkout the ordinary way. WooCommerce resumes the pending order
	// created above rather than opening a second one
	function placeOrder() {
		submitting = true;
		getForm().trigger("submit");
	}

	/* Wiring */

	// WooCommerce gives gateways this hook to step into the checkout. Returning false
	// stops its submit, and it covers pressing Enter as well as clicking Place order
	function bindForm() {
		getForm()
			.off("checkout_place_order_" + gateway + ".wcz")
			.on("checkout_place_order_" + gateway + ".wcz", function () {
				if (submitting) {
					submitting = false;
					return;
				}
				if (!getPopup().length) {
					return;
				}

				try {
					requestDraftOrder();
				} catch (err) {
					// Not returning false hands the checkout back to WooCommerce
					unblock(getForm());
					return;
				}

				return false;
			});

		// Only true while this script is running, so the checkout page never promises
		// a popup to somebody whose browser is not going to show them one
		$(".wcz-popup-note").prop("hidden", false);
	}

	bindForm();
	$(document.body).on("updated_checkout", bindForm);

	$(document).on("click", popupId + " [data-wcz-close]", function (event) {
		event.preventDefault();
		hidePopup();
	});

	$(document).on("keydown", function (event) {
		if ("Escape" !== event.key && 27 !== event.keyCode) {
			return;
		}
		if (isPopupOpen()) {
			event.preventDefault();
			hidePopup();
		}
	});

	$(document).on("click", popupId + " .wcz-popup-confirm", function (event) {
		event.preventDefault();

		var $button = $(this);
		var $popup = $button.closest(".wcz-popup");
		var $confirmed = getConfirmed($popup);

		if ($confirmed.length && !$confirmed.prop("checked")) {
			showError(
				$popup,
				i18n.confirm_required ||
					"Please confirm that you have sent the payment.",
			);
			$confirmed.trigger("focus");
			return;
		}

		$button.addClass("wcz-busy");
		placeOrder();
	});

	// Ticking it answers the complaint, so take the complaint back down
	$(document).on("change", popupId + " " + confirmedSelector, function () {
		if (this.checked) {
			showError($(this).closest(".wcz-popup"), "");
		}
	});

	// The order could not be placed after all. Close the popup so the customer can
	// read the notices WooCommerce just put at the top of the checkout form
	$(document.body).on("checkout_error", function () {
		submitting = false;
		hidePopup();
	});

	// The payment box is rebuilt on every refresh; the draft order is kept and updated to the new cart on the next open
	$(document.body).on("updated_checkout", function () {
		if (!isPopupOpen()) {
			hidePopup();
		}
	});
});
