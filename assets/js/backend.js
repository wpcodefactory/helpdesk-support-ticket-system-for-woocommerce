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

	$(".STSWooCommerce .nav-tab-wrapper a").on("click", function (e) {
		e.preventDefault();

		if ($(this).hasClass("proVersion")) {
			//do nothing
		} else {
			var url = $(this).attr("href");
			$(".STSWooCommerce").addClass("loading");
			$("body").load($(this).attr("href"), function () {
				window.history.replaceState("object or string", "Title", url);
			});
		}
	});

	$(".stswaccordion").accordion({
		collapsible: true,
	});

	$(".STSWooCommerce #tabs").tabs();
	$(".STSWooCommerce .subtabs").tabs();
	$(".STSWooCommerce #accordion").accordion();

	$(".STSWooCommerce form").on("submit", function (e) {
		if ($(this).hasClass("STSWooCommercelicense")) {
		} else {
			tinyMCE.triggerSave();
			e.preventDefault();
			$.ajax({
				url: window.location.href,
				data: $(this).serialize(),
				type: "POST",
				beforeSend: function () {
					$(".STSWooCommerce").addClass("loading");
				},
				success: function (data) {
					$("body").html(data);
					$("html, body").animate({ scrollTop: 0 }, "slow");
				},
			});
		}
	});

	/**
	 * On delete button click, delete the response and clear the row from the table - via AJAX call to `response_delete()`.
	 *
	 * @version 2.2.0
	 */
	$(document).on("click", "#deleteResponse a", function (event) {
		event.preventDefault();

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
