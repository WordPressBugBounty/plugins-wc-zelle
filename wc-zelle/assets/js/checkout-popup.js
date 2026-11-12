// wc_zelle_popup // wp_localize_script object
//
// Checkout design 1: on Place order, posts the checkout with wc_zelle_popup=1 so WooCommerce creates the popup order, then shows its number and the Zelle details in a popup.
// Confirming sends the customer to that same order's confirmation page, without a second checkout submit. @see WC_Zelle_Popup_Order

jQuery(function ($) {
	var settings = window.wc_zelle_popup || {};
	var gateway = settings.gateway || "zelle";
	var i18n = settings.i18n || {};
	var popupId = "#wc-" + gateway + "-popup";
	var confirmedSelector = ".wcz-popup-confirmed input[type='checkbox']";

	// Set while the confirmed checkout is submitted, so the popup lets it through
	var submitting = false;

	// Set while the popup's order is requested, so pressing Enter again does not open a second order
	var requesting = false;

	// From the pending order's response, for the confirm request
	var confirmNonce = "";
	var confirmOrderId = 0;
	var confirmOrderKey = "";

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

	// Same repair as WooCommerce's checkout.js, for PHP notices printed ahead of the JSON
	function jsonFilter(raw, type) {
		if ("json" !== type) {
			return raw;
		}
		try {
			JSON.parse(raw);
			return raw;
		} catch (err) {
			var json = raw.match(/{"result.*}/);
			return json ? json[0] : raw;
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
		if (order.total) {
			$popup.find("[data-wcz-order-total]").html(order.total);
		}
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

	function requestPopupOrder() {
		var $form = getForm();
		var url = checkoutUrl();

		if (requesting) {
			return;
		}

		if (!$form.length || !url) {
			return placeOrder();
		}

		requesting = true;
		block($form);

		$.ajax({
			type: "POST",
			url: url,
			data: $form.serialize() + "&wc_zelle_popup=1",
			dataType: "json",
			dataFilter: jsonFilter,
		})
			.done(function (result) {
				// Placed outright (e.g. a cached page after the popup was turned off): submitting again would hit an emptied cart
				if (result && "success" === result.result && result.redirect) {
					window.location = result.redirect;
					return;
				}

				// Refused, and WooCommerce may have changed the cart while refusing it: its notices are shown, never resubmitted
				if (result && "failure" === result.result) {
					return showCheckoutError(result);
				}

				if (!result || "zelle_popup" !== result.result) {
					return placeOrder();
				}

				var $popup = getPopup();
				confirmNonce = result.nonce || "";
				confirmOrderId = result.order_id || 0;
				confirmOrderKey = result.order_key || "";

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
			})
			.always(function () {
				requesting = false;
			});
	}

	// The pending order is the order: it is confirmed and the customer sent to its confirmation page
	function confirmOrder(retried) {
		if (!settings.confirm || !confirmNonce) {
			return placeOrder();
		}

		$.ajax({
			type: "POST",
			url: settings.confirm,
			data: {
				security: confirmNonce,
				order_id: confirmOrderId,
				order_key: confirmOrderKey,
			},
			dataType: "json",
			dataFilter: jsonFilter,
		})
			.done(function (result) {
				if (result && "success" === result.result && result.redirect) {
					window.location = result.redirect;
					return;
				}
				confirmFailed();
			})
			.fail(function () {
				// It may have gone through with only its response lost, and asking again returns that order
				if (!retried) {
					return confirmOrder(true);
				}
				confirmFailed();
			});
	}

	// The order exists, so the checkout is not submitted again: the customer retries, or closes and places the order
	function confirmFailed() {
		var $popup = getPopup();
		$popup.find(".wcz-popup-confirm").removeClass("wcz-busy");
		showError(
			$popup,
			i18n.confirm_failed ||
				"Your order could not be confirmed. Please try again, or close this window and place your order again.",
		);
	}

	// WooCommerce's checkout.js failure handling, for a refusal of the popup's request. @see wc_checkout_form.submit_error()
	function showCheckoutError(result) {
		var $form = getForm();

		if (true === result.reload) {
			window.location.reload();
			return;
		}
		if (true === result.refresh) {
			$(document.body).trigger("update_checkout");
		}

		var html;
		var $msgs = $();
		if (result.messages) {
			$msgs = $(result.messages).removeAttr("role").attr("tabindex", "-1");
			$msgs.find("li[data-id]").each(function () {
				var $li = $(this);
				$li.html(
					$("<a>", { href: "#" + $li.attr("data-id"), html: $li.html() }),
				);
			});
			html = $('<div role="alert"></div>')
				.append($msgs.clone())
				.prop("outerHTML");
		} else {
			var fallback =
				window.wc_checkout_params &&
				window.wc_checkout_params.i18n_checkout_error;
			html =
				'<div class="woocommerce-error">' +
				(fallback || "Error processing checkout. Please try again.") +
				"</div>";
		}

		$(
			".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success",
		).remove();
		$(".checkout-inline-error-message").remove();
		$form.prepend(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
				html +
				"</div>",
		);
		$form.removeClass("processing");
		unblock($form);
		$form
			.find(".input-text, select, input:checkbox")
			.trigger("validate")
			.trigger("blur");

		// Same inline field errors as WooCommerce
		$msgs.find("li[data-id]").each(function () {
			var id = $(this).attr("data-id");
			var $field = $("#" + id);
			var msg = $(this).text().trim();
			if (1 !== $field.length || !msg) {
				return;
			}
			$field.closest(".form-row").append(
				$('<p class="checkout-inline-error-message"></p>')
					.attr("id", id + "_description")
					.text(msg),
			);
			$field.attr({
				"aria-describedby": id + "_description",
				"aria-invalid": "true",
			});
		});

		var $notices = $(".woocommerce-NoticeGroup-checkout");
		if ($.scroll_to_notices) {
			$.scroll_to_notices($notices.length ? $notices : $form);
		}
		$form
			.find(
				'.woocommerce-error[tabindex="-1"], .wc-block-components-notice-banner.is-error[tabindex="-1"]',
			)
			.trigger("focus");
		$(document.body).trigger("checkout_error", [html]);
	}

	// Submit the checkout the ordinary way, only when the popup cannot work (network error, unreadable answer). WooCommerce
	// resumes the pending order created above rather than opening a second one
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
				// Creating an account: WooCommerce places the order outright, so the account only comes with a real order
				if (getForm().find("input#createaccount").is(":checked")) {
					return;
				}

				try {
					requestPopupOrder();
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

		if ($button.hasClass("wcz-busy")) {
			return;
		}
		$button.addClass("wcz-busy");
		confirmOrder();
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

	// The payment box is rebuilt on every refresh; the popup order is kept and updated to the new cart on the next open
	$(document.body).on("updated_checkout", function () {
		if (!isPopupOpen()) {
			hidePopup();
		}
	});
});
