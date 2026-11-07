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
	});
	$(".stswproaccordion3").accordion({
		header: "h4",
		active: false,
		collapsible: true,
	});

	// Forms post normally; the server saves, then redirects back with a notice.
	// Only prevent double submits here.
	$(".STSWooCommercenew_response, .STSWooCommercenew_ticket").on("submit", function () {
		$(this).find('[type="submit"]').prop("disabled", true);
		$(".stswproaccordion").addClass("loading");
	});
})(jQuery);
