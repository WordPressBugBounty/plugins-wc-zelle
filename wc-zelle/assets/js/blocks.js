// Zelle payment method for the block checkout. Design 1 (PRO) opens the classic checkout's popup on Place order, with the Store API popup order's number as the memo.
// The popup lives on document.body, outside React, so re-renders cannot wipe it.

(() => {
	const el = window.React,
		wpI18n = window.wp.i18n,
		blocksRegistry = window.wc.wcBlocksRegistry,
		htmlEntities = window.wp.htmlEntities,
		settings = (0, window.wc.wcSettings.getSetting)("zelle_data", {}),
		defaultLabel = (0, wpI18n.__)("Zelle", "wc-zelle"),
		label =
			(0, htmlEntities.decodeEntities)(settings.title) || defaultLabel;

	const i18n = settings.popup_i18n || {};
	const usePopup = !!settings.popup && !!settings.popup_endpoint;

	// Lives outside React
	let host = null;

	// Renewed by every popup response
	let popupNonce = settings.popup_nonce || "";

	// Tolerates PHP notices printed ahead of the JSON, like WooCommerce's checkout.js
	function readJson(response) {
		return response.text().then((text) => {
			try {
				return JSON.parse(text);
			} catch (err) {
				const json = text.match(/\{".*\}/);
				return json ? JSON.parse(json[0]) : null;
			}
		});
	}

	function teardown() {
		if (host && host.parentNode) {
			host.parentNode.removeChild(host);
		}
		host = null;
		document.body.classList.remove("wcz-popup-open");
	}

	// qrcode.js ran before this popup existed, so its QR codes are drawn here with the same id-to-settings convention
	function renderQrCodes(popup) {
		if (typeof window.TABQRCodeGenerator !== "function" || !window.jQuery) {
			return;
		}

		popup.querySelectorAll('[id$="_qrcode"]').forEach((node) => {
			const config = window[node.id];
			if (config && typeof config === "object" && config.url) {
				window.TABQRCodeGenerator(window.jQuery(node), config);
			}
		});
	}

	function showError(popup, message) {
		const error = popup.querySelector(".wcz-popup-error");
		if (!error) {
			return;
		}
		error.textContent = message || "";
		error.hidden = !message;
	}

	// Resolves with the confirmation page URL when the order is already handled, otherwise empty
	function confirmOrder(data, retried) {
		if (!settings.confirm_endpoint || !data.nonce) {
			return Promise.resolve("");
		}

		// The order this popup showed, which the server confirms even if the session has moved on
		const body = new URLSearchParams({
			security: data.nonce,
			order_id: data.order_id || 0,
			order_key: data.order_key || "",
			block: "1",
		});
		return (
			fetch(settings.confirm_endpoint, {
				method: "POST",
				credentials: "same-origin",
				body,
			})
				.then(readJson)
				.then((result) =>
					result && "success" === result.result && result.redirect
						? result.redirect
						: "",
				)
				// It may have gone through with only its response lost, and asking again returns that order
				.catch(() => (retried ? "" : confirmOrder(data, true)))
		);
	}

	// Resolves true to let the checkout run, false when the customer backs out, or { error } for the checkout to show
	function openPopup() {
		return fetch(settings.popup_endpoint, {
			method: "POST",
			credentials: "same-origin",
			body: new URLSearchParams({ security: popupNonce }),
		})
			.then(readJson)
			.then((data) => {
				if (data && data.popup_nonce) {
					popupNonce = data.popup_nonce;
				}

				// Paid or confirmed already: its confirmation page, with the checkout held until it loads
				if (data && data.redirect) {
					window.location.href = data.redirect;
					return new Promise(() => {});
				}

				if (data && data.error) {
					return { error: data.error };
				}

				if (!data || !data.html) {
					return true;
				}

				// No order number means no memo, so place the order and let the confirmation page show it
				if (!data.number) {
					return true;
				}

				// payment_fields() returns the checkout page teaser as well as the
				// dialog, and only the dialog belongs on top of the block checkout
				const parsed = document.createElement("div");
				parsed.innerHTML = data.html;

				const popup = parsed.querySelector(".wcz-popup");
				if (!popup) {
					return true;
				}

				teardown();
				host = document.createElement("div");
				host.appendChild(popup);
				document.body.appendChild(host);

				const number = popup.querySelector("[data-wcz-order-number]");
				if (number) {
					number.textContent = data.number || "";
				}
				if (data.total) {
					popup
						.querySelectorAll("[data-wcz-order-total]")
						.forEach((node) => {
							node.innerHTML = data.total;
						});
				}

				popup.hidden = false;
				popup.setAttribute("aria-hidden", "false");
				document.body.classList.add("wcz-popup-open");

				renderQrCodes(popup);

				// A popup the customer cannot see would strand them, so place the order instead
				if (!popup.getClientRects().length) {
					teardown();
					return true;
				}

				return new Promise((resolve) => {
					const confirmed = popup.querySelector(
						".wcz-popup-confirmed input[type='checkbox']",
					);
					const confirmButton =
						popup.querySelector(".wcz-popup-confirm");

					// Always a deliberate act, never left ticked from a previous open
					if (confirmed) {
						confirmed.checked = false;
						confirmed.addEventListener("change", () => {
							if (confirmed.checked) {
								showError(popup, "");
							}
						});
					}

					function dismiss() {
						teardown();
						resolve(false);
					}

					popup
						.querySelectorAll("[data-wcz-close]")
						.forEach((node) => {
							node.addEventListener("click", (event) => {
								event.preventDefault();
								dismiss();
							});
						});

					document.addEventListener("keydown", function onKey(event) {
						if ("Escape" !== event.key && 27 !== event.keyCode) {
							return;
						}
						document.removeEventListener("keydown", onKey);
						dismiss();
					});

					if (confirmButton) {
						confirmButton.addEventListener("click", (event) => {
							event.preventDefault();

							if (confirmed && !confirmed.checked) {
								showError(
									popup,
									i18n.confirm_required ||
										"Please confirm that you have sent the payment.",
								);
								confirmed.focus();
								return;
							}

							if (confirmButton.classList.contains("wcz-busy")) {
								return;
							}
							confirmButton.classList.add("wcz-busy");

							// A paid order goes straight to its confirmation page; an unpaid one through the Store API checkout, which resumes it
							confirmOrder(data).then((redirect) => {
								if (redirect) {
									window.location.href = redirect;
									return;
								}
								teardown();
								resolve(true);
							});
						});
					}

					(confirmed || confirmButton || popup).focus();
				});
			})
			.catch(() => {
				// Never leave the customer stuck on a checkout that will not proceed
				teardown();
				return true;
			});
	}

	const Content = (props) => {
		const { eventRegistration, emitResponse } = props || {};

		// Holds the checkout until the customer has confirmed in the popup. Returning an
		// error response aborts, which leaves them on the checkout with their cart intact
		el.useEffect(() => {
			if (
				!usePopup ||
				!emitResponse ||
				!eventRegistration ||
				!eventRegistration.onCheckoutValidation
			) {
				return;
			}

			return eventRegistration.onCheckoutValidation(() =>
				openPopup().then((outcome) =>
					true === outcome
						? true
						: {
								type: emitResponse.responseTypes.ERROR,
								errorMessage:
									(outcome && outcome.error) ||
									i18n.cancelled ||
									"Confirm the payment in the window to place your order.",
							},
				),
			);
		}, [eventRegistration, emitResponse]);

		return (0, htmlEntities.decodeEntities)(settings.description || "");
	};

	const Label = (props) => {
		const { PaymentMethodLabel } = props.components;
		return (0, el.createElement)(PaymentMethodLabel, { text: label });
	};

	(0, blocksRegistry.registerPaymentMethod)({
		name: "zelle",
		label: (0, el.createElement)(Label, null),
		content: (0, el.createElement)(Content, null),
		edit: (0, el.createElement)(Content, null),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: { features: settings.supports },
		icon: settings.icon,
	});
})();
