/**
 * Helpdesk Support Ticket System for WooCommerce - staff inbox.
 *
 * Keyboard shortcuts (j, k, r, n, c, /, ?), reply or private note tabs, Ctrl+Enter to send, drafts kept on this
 * computer, auto-save of status, priority and assignee, the bulk bar and confirmations. Everything also works without
 * this script.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_Inbox || {};
	var box = document.querySelector(".stsw-replybox");
	var area = document.getElementById("stsw-inbox-reply");
	var dialog = document.querySelector(".stsw-shortcuts");
	var region = document.querySelector(".hd-toast-region");

	function typing(el) {
		return el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
	}

	function toast(text, error) {
		if (!region) {
			return;
		}
		var item = document.createElement("div");
		item.className = "hd-toast " + (error ? "is-error" : "is-success");
		item.textContent = text;
		region.appendChild(item);
		window.setTimeout(function () {
			item.remove();
		}, 4000);
	}

	// Reply or private note.
	function setMode(mode) {
		if (!box) {
			return;
		}
		var radio = box.querySelector('input[name="stsw_mode"][value="' + mode + '"]');
		if (radio) {
			radio.checked = true;
		}
		var note = "note" === mode;
		box.classList.toggle("is-note", note);
		var label = box.querySelector(".stsw-replybox__label");
		if (label) {
			label.textContent = note ? t.noteMode : t.replyMode;
		}
		if (area) {
			area.placeholder = note ? t.noteHolder : t.replyHolder;
		}
		var submit = box.querySelector(".hd-composer__submit");
		if (submit) {
			submit.textContent = submit.getAttribute(note ? "data-note" : "data-reply");
		}
		var close = box.querySelector(".stsw-reply-close");
		if (close) {
			close.hidden = note;
		}
	}

	if (box) {
		box.addEventListener("change", function (event) {
			if ("stsw_mode" === event.target.name) {
				setMode(event.target.value);
			}
			if ("stsw-inbox-files" === event.target.id) {
				var names = box.querySelector(".hd-attach__names");
				if (names) {
					names.textContent = Array.prototype.map.call(event.target.files, function (f) {
						return f.name;
					}).join(", ");
				}
			}
		});
	}

	// Ctrl+Enter (or Cmd+Enter) sends.
	if (area) {
		area.addEventListener("keydown", function (event) {
			if ("Enter" === event.key && (event.ctrlKey || event.metaKey)) {
				event.preventDefault();
				var submit = box.querySelector(".hd-composer__submit");
				if (box.requestSubmit && submit) {
					box.requestSubmit(submit);
				} else if (submit) {
					submit.click();
				}
			}
		});
	}

	// Drafts.
	if (box && area && window.localStorage) {
		var key = "stsw-draft-" + box.getAttribute("data-ticket");
		var draft = box.querySelector(".stsw-replybox__draft");
		var timer = null;
		try {
			if (/[?&]stsw_msg=(sent|noted)/.test(window.location.search)) {
				window.localStorage.removeItem(key);
			} else if (!area.value && window.localStorage.getItem(key)) {
				area.value = window.localStorage.getItem(key);
			}
		} catch (e) {}
		area.addEventListener("input", function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				try {
					if (area.value) {
						window.localStorage.setItem(key, area.value);
						if (draft) {
							draft.textContent = t.draftSaved;
						}
					} else {
						window.localStorage.removeItem(key);
					}
				} catch (e) {}
			}, 600);
		});
	}

	// Auto-save of status, priority and assignee.
	var controls = document.querySelector("[data-hd-autosave]");
	function autosave(select) {
		if (!controls || !window.fetch) {
			return false;
		}
		var data = new window.FormData();
		data.append("action", "wpfactory_wc_sts_inbox_update");
		data.append("_wpnonce", t.nonce);
		data.append("ticket", controls.getAttribute("data-ticket"));
		data.append("field", select.name);
		data.append("value", select.value);
		controls.classList.add("is-saving");
		window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				controls.classList.remove("is-saving");
				var ok = json && json.success;
				toast(ok ? json.data.message : (json && json.data && json.data.message) || t.saveError, !ok);
				if (ok) {
					select.setAttribute("data-saved", select.value);
				} else if (select.hasAttribute("data-saved")) {
					select.value = select.getAttribute("data-saved");
				}
			})
			.catch(function () {
				controls.classList.remove("is-saving");
				toast(t.saveError, true);
			});
		return true;
	}
	if (controls) {
		Array.prototype.forEach.call(controls.querySelectorAll("select"), function (select) {
			select.setAttribute("data-saved", select.value);
			select.addEventListener("change", function () {
				autosave(select);
			});
		});
	}

	// Bulk bar.
	var bulk = document.querySelector(".stsw-bulk");
	var all = document.querySelector(".stsw-check-all");
	function refreshBulk() {
		if (!bulk) {
			return;
		}
		var boxes = bulk.querySelectorAll('input[name="stsw_ids[]"]');
		var checked = bulk.querySelectorAll('input[name="stsw_ids[]"]:checked').length;
		var bar = bulk.querySelector(".hd-bulkbar");
		var count = bulk.querySelector("[data-hd-selected]");
		if (bar) {
			bar.classList.toggle("is-active", checked > 0);
		}
		if (count) {
			count.textContent = checked ? String(t.selected).replace("%d", checked) : t.selectNone;
		}
		if (all) {
			all.checked = checked > 0 && checked === boxes.length;
			all.indeterminate = checked > 0 && checked < boxes.length;
		}
	}
	if (all) {
		all.addEventListener("change", function () {
			Array.prototype.forEach.call(document.querySelectorAll('input[name="stsw_ids[]"]'), function (item) {
				item.checked = all.checked;
			});
			refreshBulk();
		});
	}
	if (bulk) {
		bulk.addEventListener("change", function (event) {
			if ("stsw_ids[]" === event.target.name) {
				refreshBulk();
			}
		});
		bulk.addEventListener("submit", function (event) {
			var by = event.submitter;
			var text = by && "delete:1" === by.value ? t.confirmDelete : t.confirmBulk;
			if (!window.confirm(text)) {
				event.preventDefault();
			}
		});
		refreshBulk();
	}

	// Shortcuts help.
	function toggleHelp() {
		if (!dialog || !dialog.showModal) {
			return;
		}
		if (dialog.open) {
			dialog.close();
		} else {
			dialog.showModal();
		}
	}
	var open = document.querySelector(".stsw-shortcuts-open");
	if (open) {
		open.addEventListener("click", toggleHelp);
	}

	function move(step) {
		var rows = Array.prototype.slice.call(document.querySelectorAll(".stsw-row__link"));
		if (!rows.length) {
			return;
		}
		var current = rows.findIndex(function (row) {
			return row.getAttribute("aria-current") === "true";
		});
		var next = current < 0 ? 0 : Math.min(rows.length - 1, Math.max(0, current + step));
		if (next !== current) {
			window.location.href = rows[next].href;
		}
	}

	document.addEventListener("keydown", function (event) {
		if (event.ctrlKey || event.metaKey || event.altKey || typing(event.target)) {
			return;
		}
		switch (event.key) {
			case "j":
				move(1);
				break;
			case "k":
				move(-1);
				break;
			case "r":
			case "n":
				if (area) {
					event.preventDefault();
					setMode("n" === event.key ? "note" : "reply");
					area.focus();
				}
				break;
			case "c":
				var status = document.getElementById("stsw-status");
				if (controls && status && status.value !== "closed" && window.confirm(t.confirmClose)) {
					status.value = "closed";
					if (!autosave(status)) {
						controls.submit();
					}
				}
				break;
			case "?":
				toggleHelp();
				break;
		}
	});
})();
