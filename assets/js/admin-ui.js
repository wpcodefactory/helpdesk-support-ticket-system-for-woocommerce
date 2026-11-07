/**
 * Helpdesk Support Ticket System for WooCommerce - admin design behaviour.
 *
 * Settings sub-navigation and save bar, drag or keyboard ordering of priorities, subjects and statuses,
 * placeholder chips, confirmations and the setup wizard preview. Every screen also works without this script.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_UI || {};
	var each = function (selector, fn, root) {
		Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
	};

	each(".wpf-hd", function (el) {
		el.classList.add("hd-js");
	});

	function typing(el) {
		return el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
	}

	// "/" focuses the ticket search.
	document.addEventListener("keydown", function (event) {
		if ("/" !== event.key || event.ctrlKey || event.metaKey || event.altKey || typing(event.target)) {
			return;
		}
		var search = document.getElementById("hd-search");
		if (search) {
			event.preventDefault();
			search.focus();
		}
	});

	// Confirmations.
	document.addEventListener("submit", function (event) {
		var form = event.target;
		var kind = form.getAttribute && form.getAttribute("data-hd-confirm");
		if (!kind) {
			return;
		}
		var text = "reply" === kind ? t.confirmReply : t.confirmTerm;
		if (text && !window.confirm(text)) {
			event.preventDefault();
		}
	});

	// Placeholder chips insert at the cursor.
	document.addEventListener("click", function (event) {
		var chip = event.target.closest ? event.target.closest("[data-hd-insert]") : null;
		if (!chip) {
			return;
		}
		var area = document.getElementById(chip.getAttribute("data-hd-target"));
		if (!area) {
			return;
		}
		var tag = chip.getAttribute("data-hd-insert");
		var start = area.selectionStart || 0;
		var end = area.selectionEnd || 0;
		area.value = area.value.slice(0, start) + tag + area.value.slice(end);
		area.focus();
		area.selectionStart = area.selectionEnd = start + tag.length;
	});

	// Settings: one panel at a time, unsaved changes state.
	var settings = document.querySelector("[data-hd-settings]");
	if (settings) {
		var links = settings.querySelectorAll("[data-hd-panel]");
		var show = function (id, focus) {
			var panel = document.getElementById(id);
			if (!panel || !panel.classList.contains("hd-panel")) {
				id = links.length ? links[0].getAttribute("data-hd-panel") : "";
				panel = document.getElementById(id);
			}
			each(".hd-panel", function (p) {
				p.classList.toggle("is-active", p === panel);
			}, settings);
			Array.prototype.forEach.call(links, function (link) {
				if (link.getAttribute("data-hd-panel") === id) {
					link.setAttribute("aria-current", "true");
				} else {
					link.removeAttribute("aria-current");
				}
			});
			if (focus && panel) {
				panel.focus();
			}
		};
		show(window.location.hash.replace("#", ""), false);
		settings.addEventListener("click", function (event) {
			var link = event.target.closest("[data-hd-panel]");
			if (!link) {
				return;
			}
			event.preventDefault();
			var id = link.getAttribute("data-hd-panel");
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, "", "#" + id);
			}
			show(id, true);
		});
		var bar = settings.querySelector(".hd-savebar");
		var state = settings.querySelector("[data-hd-state]");
		var dirty = false;
		var markDirty = function (event) {
			if (dirty || (event && false === event.isTrusted)) {
				return;
			}
			dirty = true;
			bar.classList.add("is-dirty");
			state.textContent = t.unsaved;
		};
		settings.addEventListener("input", markDirty);
		settings.addEventListener("change", markDirty);
		settings.addEventListener("submit", function () {
			dirty = false;
		});
		window.addEventListener("beforeunload", function (event) {
			if (dirty) {
				event.preventDefault();
				event.returnValue = "";
			}
		});
		// Classic editors (TinyMCE) do not fire input events on the form.
		if (window.tinymce && window.tinymce.on) {
			window.tinymce.on("AddEditor", function (e) {
				e.editor.on("keyup", function () {
					markDirty();
				});
			});
		}
	}

	// Ordering of priorities, subjects and statuses: drag the row, or focus its handle and use the arrow keys.
	each(".hd-sortable", function (list) {
		var live = list.parentNode.querySelector(".hd-live");
		var dragged = null;
		var save = function () {
			var data = new window.FormData();
			data.append("action", "wpfactory_wc_sts_term_order");
			data.append("nonce", t.nonce);
			data.append("taxonomy", list.getAttribute("data-taxonomy"));
			each(".hd-sortable__row", function (row) {
				data.append("ids[]", row.getAttribute("data-id"));
			}, list);
			window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data });
		};
		var announce = function (row) {
			var rows = Array.prototype.slice.call(list.querySelectorAll(".hd-sortable__row"));
			if (live) {
				live.textContent = String(t.moved).replace("%d", rows.indexOf(row) + 1);
			}
		};
		list.addEventListener("dragstart", function (event) {
			dragged = event.target.closest(".hd-sortable__row");
			if (dragged) {
				dragged.classList.add("is-dragging");
				event.dataTransfer.effectAllowed = "move";
			}
		});
		list.addEventListener("dragover", function (event) {
			var over = event.target.closest(".hd-sortable__row");
			if (!dragged || !over || over === dragged) {
				return;
			}
			event.preventDefault();
			each(".is-over", function (row) {
				row.classList.remove("is-over");
			}, list);
			over.classList.add("is-over");
		});
		list.addEventListener("drop", function (event) {
			var over = event.target.closest(".hd-sortable__row");
			if (!dragged || !over || over === dragged) {
				return;
			}
			event.preventDefault();
			var rows = Array.prototype.slice.call(list.children);
			list.insertBefore(dragged, rows.indexOf(dragged) < rows.indexOf(over) ? over.nextSibling : over);
			save();
			announce(dragged);
		});
		list.addEventListener("dragend", function () {
			each(".is-over, .is-dragging", function (row) {
				row.classList.remove("is-over", "is-dragging");
			}, list);
			dragged = null;
		});
		list.addEventListener("keydown", function (event) {
			var handle = event.target.closest(".hd-drag");
			if (!handle || ("ArrowUp" !== event.key && "ArrowDown" !== event.key)) {
				return;
			}
			event.preventDefault();
			var row = handle.closest(".hd-sortable__row");
			var sibling = "ArrowUp" === event.key ? row.previousElementSibling : row.nextElementSibling;
			if (!sibling) {
				return;
			}
			list.insertBefore(row, "ArrowUp" === event.key ? sibling : sibling.nextSibling);
			handle.focus();
			save();
			announce(row);
		});
	});

	// Wizard: live preview of the labels.
	each("[data-hd-preview]", function (input) {
		var out = document.querySelector('[data-hd-preview-out="' + input.getAttribute("data-hd-preview") + '"]');
		if (!out) {
			return;
		}
		input.addEventListener("input", function () {
			out.textContent = input.value;
		});
	});
})();
