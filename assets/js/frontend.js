/**
 * Helpdesk Support Ticket System for WooCommerce - Frontend JS
 *
 * @author WPFactory
 */

(function ($) {
	"use strict";

	$(".stswproaccordion,.stswproaccordion2").accordion({
		collapsible: true,
		active: false,
		heightStyle: "content",
	});
	$(".stswproaccordion3").accordion({
		header: "h4",
		active: false,
		collapsible: true,
		heightStyle: "content",
	});

	// Forms post normally; the server saves, then redirects back with a notice. Only prevent double submits here.
	$('form[class$="new_response"], form[class$="new_ticket"], .wpfactory-wc-sts-guest-lookup').on("submit", function () {
		$(this).find('[type="submit"]').prop("disabled", true).attr("aria-disabled", "true");
	});
})(jQuery);
