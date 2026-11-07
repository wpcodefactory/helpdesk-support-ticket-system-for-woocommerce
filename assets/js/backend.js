/**
 * Helpdesk Support Ticket System for WooCommerce - Backend JS
 *
 * @author WPFactory
 */

(function ($) {
	"use strict";

	var settings = window.WPFactory_WC_STS_Backend || {};

	// Priority colour picker.
	if ($.fn.wpColorPicker) {
		$(".stsw-color-field").wpColorPicker();
	}

	// Delete a reply or a private note.
	$(document).on("click", ".wpfactory-wc-sts-delete", function (event) {
		event.preventDefault();
		var button = $(this);
		var isNote = "note" === button.data("type");
		if (!window.confirm(isNote ? settings.confirmDeleteNote : settings.confirmDelete)) {
			return;
		}
		$.post(
			window.ajaxurl,
			{
				action: isNote ? "wpfactory_wc_sts_note_delete" : "wpfactory_wc_sts_response_delete",
				nonce: settings.responseDeleteNonce,
				id: button.data("id"),
			},
			function (response) {
				if (response && response.success) {
					button.closest("tr, li").remove();
				}
			}
		);
	});

	// Insert a canned reply into the reply box.
	$(document).on("change", ".wpfactory-wc-sts-canned", function () {
		var option = $(this).find("option:selected");
		var target = $("#" + $(this).data("target"));
		var text = option.data("text");
		if (!text || !target.length) {
			return;
		}
		if ($.trim(target.val()) && !window.confirm(settings.confirmReplace)) {
			$(this).val("");
			return;
		}
		target.val(String(text)).trigger("focus");
		$(this).val("");
	});
})(jQuery);
