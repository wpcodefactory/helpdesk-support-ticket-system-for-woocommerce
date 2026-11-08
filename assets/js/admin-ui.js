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

	var t = window.WPFactory_WC_STS_UI || window.WPFactory_WC_STS_Pro_UI || {};
	var each = function (selector, fn, root) {
		Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
	};

	/*
	 * In-page dialogs in place of the browser's confirm() and alert(): they name the exact change, work with the
	 * keyboard (Tab stays inside, Escape cancels) and return focus to where it was.
	 */
	function dialog(text, yes, no) {
		return new Promise(function (resolve) {
			var back = document.activeElement;
			var box = document.createElement("dialog");
			if (!box.showModal) {
				resolve(no ? window.confirm(text) : (window.alert(text), true)); // Very old browsers only.
				return;
			}
			var id = "hd-dialog-" + String(Date.now());
			box.className = "hd-dialog hd-ask wpf-hd";
			box.setAttribute("role", "alertdialog");
			box.setAttribute("aria-modal", "true");
			box.setAttribute("aria-labelledby", id);
			var msg = document.createElement("p");
			msg.id = id;
			msg.className = "hd-ask__text";
			msg.textContent = text;
			var row = document.createElement("div");
			row.className = "hd-ask__actions";
			var ok = document.createElement("button");
			ok.type = "button";
			ok.className = "hd-btn hd-btn--primary";
			ok.textContent = yes;
			var cancel = null;
			if (no) {
				cancel = document.createElement("button");
				cancel.type = "button";
				cancel.className = "hd-btn";
				cancel.textContent = no;
				row.appendChild(cancel);
			}
			row.appendChild(ok);
			box.appendChild(msg);
			box.appendChild(row);
			(document.querySelector(".wpf-hd") || document.body).appendChild(box);
			var done = function (value) {
				box.close();
				box.remove();
				if (back && back.focus) {
					back.focus({ preventScroll: true });
				}
				resolve(value);
			};
			ok.addEventListener("click", function () {
				done(true);
			});
			if (cancel) {
				cancel.addEventListener("click", function () {
					done(false);
				});
			}
			box.addEventListener("cancel", function (event) {
				event.preventDefault();
				done(!no);
			});
			box.showModal();
			ok.focus();
		});
	}

	window.stswUI = {
		/** Asks; resolves true when the person confirms. `yes` is the confirm button text. */
		ask: function (text, yes) {
			return dialog(text, yes || t.yes || "Continue", t.no || "Cancel");
		},
		/** Tells something; resolves when closed. */
		tell: function (text) {
			return dialog(text, t.ok || "OK", null);
		},
		/**
		 * Holds a form submit until the person confirms, then sends it again with the same button.
		 * Returns true when the submit may go ahead now (already confirmed).
		 */
		gate: function (event, text, yes, before) {
			var form = event.target;
			if (form.stswConfirmed) {
				return true;
			}
			event.preventDefault();
			var by = event.submitter && event.submitter.form === form ? event.submitter : null;
			window.stswUI.ask(text, yes).then(function (sure) {
				if (!sure) {
					return;
				}
				if (before) {
					before();
				}
				form.stswConfirmed = true;
				if (form.requestSubmit) {
					form.requestSubmit(by || undefined);
				} else {
					form.submit();
				}
				form.stswConfirmed = false;
			});
			return false;
		},
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

	// Menus built on <details>: Escape closes them and returns focus to their button.
	document.addEventListener("keydown", function (event) {
		if ("Escape" !== event.key) {
			return;
		}
		var open = event.target.closest ? event.target.closest("details.hd-menu[open]") : null;
		if (open) {
			open.open = false;
			open.querySelector("summary").focus();
		}
	});

	// Confirmations.
	document.addEventListener("submit", function (event) {
		var form = event.target;
		var kind = form.getAttribute && form.getAttribute("data-hd-confirm");
		if (!kind) {
			return;
		}
		var text = "reply" === kind ? t.confirmReply : "term" === kind ? t.confirmTerm : kind;
		if (text) {
			window.stswUI.gate(event, text, form.getAttribute("data-hd-confirm-yes") || t.deleteYes);
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
			var section = settings.querySelector("[data-hd-section]");
			if (section) {
				section.value = id;
			}
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

	// "Insert article" and similar pickers add their value at the cursor.
	document.addEventListener("change", function (event) {
		var select = event.target;
		if (!select.matches || !select.matches("[data-hd-insert-select]") || !select.value) {
			return;
		}
		var area = document.getElementById(select.getAttribute("data-hd-insert-select"));
		if (area) {
			var start = area.selectionStart || area.value.length;
			var text = (start && !/\s$/.test(area.value.slice(0, start)) ? " " : "") + select.value + " ";
			area.value = area.value.slice(0, start) + text + area.value.slice(area.selectionEnd || start);
			area.focus();
			area.selectionStart = area.selectionEnd = start + text.length;
		}
		select.value = "";
	});

	// Importers: dry run first, then batches until nothing is left.
	var importer = document.querySelector("[data-hd-import]");
	if (importer && window.fetch) {
		var call = function (source, step) {
			var data = new window.FormData();
			data.append("action", "wpfactory_wc_sts_import");
			data.append("_ajax_nonce", importer.getAttribute("data-nonce"));
			data.append("source", source);
			data.append("step", step);
			return window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data }).then(function (r) {
				return r.json();
			});
		};
		importer.addEventListener("click", function (event) {
			var button = event.target.closest("[data-hd-dry], [data-hd-run]");
			if (!button) {
				return;
			}
			var row = button.closest("[data-hd-source]");
			var source = row.getAttribute("data-hd-source");
			var out = row.querySelector("[data-hd-result]");
			var run = row.querySelector("[data-hd-run]");
			button.disabled = true;
			if (button.hasAttribute("data-hd-dry")) {
				out.textContent = t.checking;
				call(source, "dry").then(function (json) {
					button.disabled = false;
					if (!json || !json.success) {
						out.textContent = (json && json.data && json.data.message) || t.importError;
						return;
					}
					var d = json.data;
					out.textContent = t.dryResult.replace("%1$d", d.tickets).replace("%2$d", d["new"]).replace("%3$d", d.done).replace("%4$d", d.replies).replace("%5$d", d.notes).replace("%6$d", d.files);
					run.hidden = !d["new"];
				});
				return;
			}
			var total = 0;
			var loop = function () {
				call(source, "run").then(function (json) {
					if (!json || !json.success) {
						button.disabled = false;
						out.textContent = (json && json.data && json.data.message) || t.importError;
						return;
					}
					total += json.data.imported;
					out.textContent = t.importing.replace("%1$d", total).replace("%2$d", json.data.left);
					if (json.data.left > 0 && json.data.imported + json.data.skipped > 0) {
						loop();
					} else {
						out.textContent = t.imported.replace("%d", total);
						run.hidden = true;
					}
				}).catch(function () {
					button.disabled = false;
					out.textContent = t.importError;
				});
			};
			loop();
		});
	}

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
