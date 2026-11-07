/**
 * Helpdesk Support Ticket System for WooCommerce - staff inbox.
 *
 * Keyboard shortcuts (j, k, r, n, c, ?), reply or private note mode, drafts kept on this computer, select all and
 * confirmations. Everything also works without this script.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_Inbox || {};
	var box = document.querySelector(".stsw-replybox");
	var area = document.getElementById("stsw-inbox-reply");
	var dialog = document.querySelector(".stsw-shortcuts");

	function typing(el) {
		return el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
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
		box.classList.toggle("is-note", "note" === mode);
		var label = box.querySelector(".stsw-replybox__label");
		if (label) {
			label.textContent = "note" === mode ? t.noteMode : t.replyMode;
		}
		var close = box.querySelector(".stsw-reply-close");
		if (close) {
			close.hidden = "note" === mode;
		}
	}

	if (box) {
		box.addEventListener("change", function (event) {
			if ("stsw_mode" === event.target.name) {
				setMode(event.target.value);
			}
		});
	}

	// Drafts.
	if (box && area && window.localStorage) {
		var key = "stsw-draft-" + box.getAttribute("data-ticket");
		var status = box.querySelector(".stsw-replybox__draft");
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
						if (status) {
							status.textContent = t.draftSaved;
						}
					} else {
						window.localStorage.removeItem(key);
					}
				} catch (e) {}
			}, 600);
		});
	}

	// Select all.
	var all = document.querySelector(".stsw-check-all");
	if (all) {
		all.addEventListener("change", function () {
			Array.prototype.forEach.call(document.querySelectorAll('input[name="stsw_ids[]"]'), function (box) {
				box.checked = all.checked;
			});
		});
	}
	var bulk = document.querySelector(".stsw-bulk");
	if (bulk) {
		bulk.addEventListener("submit", function (event) {
			if (!window.confirm(t.confirmBulk)) {
				event.preventDefault();
			}
		});
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
				var controls = document.getElementById("stsw-controls");
				var status = document.getElementById("stsw-status");
				if (controls && status && window.confirm(t.confirmClose)) {
					status.value = "closed";
					controls.submit();
				}
				break;
			case "?":
				toggleHelp();
				break;
		}
	});
})();
