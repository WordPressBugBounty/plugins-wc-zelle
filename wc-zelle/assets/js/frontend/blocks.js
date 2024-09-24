(() => {
	"use strict";
	const e = window.React,
		t = window.wp.i18n,
		n = window.wc.wcBlocksRegistry,
		i = window.wp.htmlEntities,
		o = (0, window.wc.wcSettings.getSetting)("zelle_data", {}),
		c = (0, t.__)("Zelle", "wc-zelle"),
		a = (0, i.decodeEntities)(o.title) || c,
		s = () => (0, i.decodeEntities)(o.description || ""),
		l = {
			name: "zelle",
			label: (0, e.createElement)((t) => {
				const { PaymentMethodLabel: n } = t.components;
				return (0, e.createElement)(n, { text: a });
			}, null),
			content: (0, e.createElement)(s, null),
			edit: (0, e.createElement)(s, null),
			canMakePayment: () => !0,
			ariaLabel: a,
			supports: { features: o.supports },
			icon: o.icon,
		};
	(0, n.registerPaymentMethod)(l);
})();
