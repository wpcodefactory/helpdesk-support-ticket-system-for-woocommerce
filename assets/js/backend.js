/**
 * Helpdesk Support Ticket System for WooCommerce - Backend JS
 *
 * @author WPFactory
 */

(function ($) {
	"use strict";

	$(
		"#toplevel_page_support-ticket-system-woocommerce .wp-submenu > li:nth-child(4)",
	).addClass("proVersion proSpan");
	$(
		"#toplevel_page_support-ticket-system-woocommerce .wp-submenu > li:nth-child(3)",
	).addClass("proVersion proSpan");
	$(".proVersion").click(function (e) {
		e.preventDefault();
		$("#STSWooCommerceModal").slideDown();
	});

	$("#STSWooCommerceModal .close").click(function (e) {
		e.preventDefault();
		$("#STSWooCommerceModal").fadeOut();
	});

	var modal = document.getElementById("STSWooCommerceModal");

	// When the user clicks anywhere outside of the modal, close it
	window.onclick = function (event) {
		if (event.target == modal) {
			modal.style.display = "none";
		}
	};

	// Pro tabs open the Pro modal; other tabs are normal links.
	$(".STSWooCommerce .nav-tab-wrapper a.proVersion").on("click", function (e) {
		e.preventDefault();
	});

	$(".stswaccordion").accordion({
		collapsible: true,
	});

	$(".STSWooCommerce #tabs").tabs();
	$(".STSWooCommerce .subtabs").tabs();
	$(".STSWooCommerce #accordion").accordion();

	// The settings form posts normally (the page reloads with a "Settings saved." notice).
	$(".STSWooCommerce form").on("submit", function () {
		$(".STSWooCommerce").addClass("loading");
	});

	/**
	 * On delete button click, delete the response and clear the row from the table - via AJAX call to `response_delete()`.
	 *
	 * @version 2.2.0
	 */
	$(document).on("click", "#deleteResponse a", function (event) {
		event.preventDefault();

		if (!window.confirm(WPFactory_WC_STS_Backend.confirmDelete)) {
			return;
		}

		var ajax_options = {
			action: "wpfactory_wc_sts_response_delete",
			nonce: WPFactory_WC_STS_Backend.responseDeleteNonce,
			id: $(this).attr("id"),
		};

		$.post(ajaxurl, ajax_options, function (data) {
			$("tr." + data).remove(); // remove row of the deleted item
		});
	});
})(jQuery);
