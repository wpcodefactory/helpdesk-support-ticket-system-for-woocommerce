/**
 * Helpdesk Support Ticket System for WooCommerce - Backend JS
 *
 * @author WPFactory
 */

(function ($) {
	"use strict";

	var settings = window.WPFactory_WC_STS_Backend || {};

	// The plugin's in-page dialog where it is loaded.
	function ask(text) {
		return window.stswUI ? window.stswUI.ask(text) : Promise.resolve(window.confirm(text));
	}

	// Priority colour picker.
	if ($.fn.wpColorPicker) {
		$(".stsw-color-field").wpColorPicker();
	}

	// Delete a reply or a private note.
	$(document).on("click", ".wpfactory-wc-sts-delete", function (event) {
		event.preventDefault();
		var button = $(this);
		var isNote = "note" === button.data("type");
		ask(isNote ? settings.confirmDeleteNote : settings.confirmDelete).then(function (sure) {
			if (!sure) {
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
	});

	// Insert a canned reply into the reply box.
	$(document).on("change", ".wpfactory-wc-sts-canned", function () {
		var option = $(this).find("option:selected");
		var target = $("#" + $(this).data("target"));
		var text = option.data("text");
		if (!text || !target.length) {
			return;
		}
		var select = $(this);
		var put = function () {
			target.val(String(text)).trigger("focus");
			select.val("");
		};
		if (!$.trim(target.val())) {
			put();
			return;
		}
		ask(settings.confirmReplace).then(function (sure) {
			if (sure) {
				put();
			} else {
				select.val("");
			}
		});
	});
})(jQuery);
