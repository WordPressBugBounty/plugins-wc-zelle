// import { sprintf, __ } from "@wordpress/i18n";
const { sprintf, __ } = window.wp.i18n;

// import { decodeEntities } from "@wordpress/html-entities";
const { decodeEntities } = window.wp.htmlEntities;

// import { RawHTML } from "@wordpress/element";
const { RawHTML } = window.wp.element;

// import { sanitizeHTML } from "@woocommerce/utils";
// const { sanitizeHTML } = window.wc.utils;

// import { registerPaymentMethod } from "@woocommerce/blocks-registry";
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

// import { getSetting } from "@woocommerce/settings";
const { getSetting } = window.wc.wcSettings;

const settings = getSetting("zelle_data", {});
console.log("Blocks settings:", settings);

const defaultLabel = __("Zelle", "wc-zelle");

const label = decodeEntities(settings.title) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	// return RawHTML(sanitizeHTML(settings.description || ""));
	return RawHTML(decodeEntities(settings.description || ""));
	// return decodeEntities(settings.description || "");
	// return <RawHTML>{sanitizeHTML(settings.description || "")}</RawHTML>;
	// return (
	// 	<RawHTML>
	// 		{sanitizeHTML(decodeEntities(settings.description || ""))}
	// 	</RawHTML>
	// );
};
// console.log("Content: ", Content);

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = (props) => {
	const { PaymentMethodLabel } = props.components;
	return PaymentMethodLabel({ text: label });
	// return <PaymentMethodLabel text={label} />;
};
// console.log("Label: ", Label);

// const Icon = () => {
// 	return settings.icon ? (
// 		<img src={settings.icon} style={{ float: "right", marginRight: "20px" }} />
// 	) : (
// 		""
// 	);
// };
// const Label = () => {
// 	return (
// 		<span style={{ width: "100%" }}>
// 			{label}
// 			<Icon />
// 		</span>
// 	);
// };

const Zelle = {
	name: "zelle",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};
// const Zelle = {
// 	name: "zelle",
// 	label: Label,
// 	content: Content,
// 	edit: Content,
// 	canMakePayment: () => true,
// 	ariaLabel: Label,
// 	supports: {
// 		features: settings.supports,
// 	},
// };

registerPaymentMethod(Zelle);
