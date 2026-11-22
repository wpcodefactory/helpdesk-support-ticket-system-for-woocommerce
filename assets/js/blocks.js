/**
 * Helpdesk Support Ticket System for WooCommerce - blocks (rendered on the server; the editor shows a placeholder).
 *
 * @author WPFactory
 */

(function (blocks, element) {
	"use strict";

	var t = window.WPFactory_WC_STS_Blocks || {};
	var el = element.createElement;

	function placeholder(title, description) {
		return function () {
			return el(
				"div",
				{ className: "components-placeholder", style: { minHeight: "120px" } },
				el("div", { className: "components-placeholder__label" }, title),
				el("div", { className: "components-placeholder__instructions" }, description + " " + (t.note || ""))
			);
		};
	}

	var defs = [
		["help-center", t.help, t.helpD, "sos"],
		["ask-form", t.ask, t.askD, "format-chat"],
		["ticket", t.ticket, t.ticketD, "admin-comments"],
	];

	defs.forEach(function (def) {
		blocks.registerBlockType("wpfactory-wc-sts/" + def[0], {
			apiVersion: 2,
			title: def[1],
			description: def[2],
			icon: def[3],
			category: "widgets",
			supports: { html: false, multiple: false },
			edit: placeholder(def[1], def[2]),
			save: function () {
				return null;
			},
		});
	});
})(window.wp.blocks, window.wp.element);
