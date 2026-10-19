// Zelle payment method for the block checkout. Design 1 (PRO) opens the classic checkout's popup on Place order, with the Store API draft order's number as the memo.
// The popup lives on document.body, outside React, so re-renders cannot wipe it.

(() => {
	"use strict";

	const el = window.React,
		wpI18n = window.wp.i18n,
		blocksRegistry = window.wc.wcBlocksRegistry,
		htmlEntities = window.wp.htmlEntities,
		settings = (0, window.wc.wcSettings.getSetting)("zelle_data", {}),
		defaultLabel = (0, wpI18n.__)("Zelle", "wc-zelle"),
		label = (0, htmlEntities.decodeEntities)(settings.title) || defaultLabel;

	const i18n = settings.popup_i18n || {};
	const usePopup = !!settings.popup && !!settings.popup_endpoint;

	// Lives outside React. Also holds the sender value between the popup closing and
	// onPaymentSetup handing it to the Store API
	let host = null;
	let sender = "";

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

	// Resolves true to let the checkout run, false when the customer backs out
	function openPopup() {
		return fetch(settings.popup_endpoint, {
			method: "POST",
			credentials: "same-origin",
		})
			.then((response) => response.json())
			.then((data) => {
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
						".wcz-popup-confirmed input[type='checkbox']"
					);
					const confirmButton = popup.querySelector(".wcz-popup-confirm");
					const senderField = popup.querySelector("#zelle_sender");

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

					popup.querySelectorAll("[data-wcz-close]").forEach((node) => {
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
										"Please confirm that you have sent the payment."
								);
								confirmed.focus();
								return;
							}

							sender = senderField ? senderField.value : "";
							teardown();
							resolve(true);
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
				openPopup().then((proceed) =>
					proceed
						? true
						: {
								type: emitResponse.responseTypes.ERROR,
								errorMessage:
									i18n.cancelled ||
									"Confirm the payment in the window to place your order.",
						  }
				)
			);
		}, [eventRegistration, emitResponse]);

		// The Store API copies payment data into $_POST, so the gateway sees this like a classic checkout field
		el.useEffect(() => {
			if (!emitResponse || !eventRegistration || !eventRegistration.onPaymentSetup) {
				return;
			}

			return eventRegistration.onPaymentSetup(() => ({
				type: emitResponse.responseTypes.SUCCESS,
				meta: { paymentMethodData: { zelle_sender: sender } },
			}));
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
