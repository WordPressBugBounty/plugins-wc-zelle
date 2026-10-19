// Copy to clipboard, byte identical in wc-zelle, wc-cashapp and wc-venmo under the shared tab_copy handle, so whichever registers first serves all three.
// Handles both shared markups: .theafricanboss-row/-value/-copy, and the older .copytxt input + .copybtn.

(function () {
	"use strict";

	var RESTORE_AFTER = 2000;

	function strings() {
		return (window.tab_copy || {}).i18n || {};
	}

	function trim(text) {
		return (text || "").replace(/^\s+|\s+$/g, "");
	}

	// navigator.clipboard needs a secure context, so stores still on http:// and older
	// browsers fall back to a throwaway textarea and execCommand
	function writeToClipboard(text) {
		if (window.navigator && navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var field = document.createElement("textarea");

			field.value = text;
			field.setAttribute("readonly", "readonly");
			field.style.position = "fixed";
			field.style.top = "-1000px";
			field.style.opacity = "0";

			document.body.appendChild(field);
			field.select();
			field.setSelectionRange(0, field.value.length);

			var copied = false;
			try {
				copied = document.execCommand("copy");
			} catch (err) {
				copied = false;
			}

			document.body.removeChild(field);

			return copied ? resolve() : reject();
		});
	}

	function feedback(button, message, state) {
		if (!button) {
			return;
		}

		if (!button.getAttribute("data-tab-label")) {
			button.setAttribute("data-tab-label", button.innerText);
		}

		button.innerText = message;
		button.classList.add(state);

		window.setTimeout(function () {
			button.innerText = button.getAttribute("data-tab-label");
			button.classList.remove("success");
			button.classList.remove("error");
		}, RESTORE_AFTER);
	}

	// .theafricanboss-value span in its row, or the .copytxt input beside an older .copybtn
	function valueNear(button) {
		var row = button.closest(".theafricanboss-row");
		if (row) {
			var span = row.getElementsByClassName("theafricanboss-value")[0];
			return span ? trim(span.textContent) : "";
		}

		var parent = button.parentElement;
		var input = parent ? parent.getElementsByClassName("copytxt")[0] : null;
		return input ? trim(input.value) : "";
	}

	function copy(text, button) {
		if (!text) {
			return;
		}

		var i18n = strings();

		writeToClipboard(text)
			.then(function () {
				feedback(button, i18n.copied || "Copied!", "success");
			})
			.catch(function () {
				feedback(button, i18n.failed || "Failed!", "error");
			});
	}

	// Delegated, so it covers rows rebuilt on checkout refresh and values filled in later, without ever binding twice
	document.addEventListener("click", function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}

		var button = target.closest(".theafricanboss-copy") || target.closest(".copybtn");
		if (button) {
			event.preventDefault();
			copy(valueNear(button), button);
			return;
		}

		// Clicking an older readonly .copytxt input copies it too
		var field = target.closest(".copytxt");
		if (field) {
			var parent = field.parentElement;
			copy(trim(field.value), parent ? parent.getElementsByClassName("copybtn")[0] : null);
		}
	});
})();
