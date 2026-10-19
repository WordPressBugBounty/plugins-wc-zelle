// QR code bootstrapper, shared by wc-zelle, wc-cashapp and wc-venmo.
//
// This file is identical in all three plugins and is registered under one shared,
// version stamped handle, so only one copy loads when several of them are active on
// the same checkout. Keep the three copies in sync: if this file ever changes, bump
// the version in the handle in every plugin's enqueue, otherwise whichever plugin
// registers first silently serves its older copy to the others.
//
// Each plugin renders its QR container as <div id="wc_<gateway>_qrcode"> and localizes
// its settings under that exact same name (window.wc_zelle_qrcode, and so on), so this
// can find every container on the page without knowing which plugins are installed -
// and a fourth plugin following the same convention is picked up for free.
//
// Nothing here references a localized object directly. The previous per-plugin versions
// did, which meant a missing localize was a ReferenceError that killed the whole file
// rather than just skipping that one QR code.

jQuery(function ($) {
	"use strict";

	function render() {
		if (typeof TABQRCodeGenerator !== "function") {
			return;
		}

		$('[id$="_qrcode"]').each(function () {
			var settings = window[this.id];

			// Only containers that have a matching localized settings object with a
			// payment url in it, so an unrelated element whose id happens to end in
			// _qrcode is left alone
			if (settings && typeof settings === "object" && settings.url) {
				TABQRCodeGenerator($(this), settings);
			}
		});
	}

	render();
	$("body").on("updated_checkout", render);
});
