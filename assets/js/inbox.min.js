/**
 * Helpdesk Support Ticket System for WooCommerce - staff inbox.
 *
 * Keyboard shortcuts (j, k, r, n, c, /, ?), reply or private note tabs, Ctrl+Enter to send, drafts kept on this
 * computer, auto-save of status, priority, assignee and tags, the bulk bar and confirmations. Every inbox form is sent
 * in the background: the list, the ticket and the counts update in place, with no page reload and no jump. New
 * messages and changes made by teammates show up on their own. Everything also works without this script.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_Inbox || window.WPFactory_WC_STS_Pro_Inbox || {};
	var region = document.querySelector(".hd-toast-region");
	var busy = false;

	function $(selector, root) {
		return (root || document).querySelector(selector);
	}

	function box() {
		return $(".stsw-replybox");
	}

	function area() {
		return document.getElementById("stsw-inbox-reply");
	}

	function controls() {
		return $("[data-hd-autosave]");
	}

	function typing(el) {
		return el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
	}

	function ask(text, yes) {
		return window.stswUI ? window.stswUI.ask(text, yes) : Promise.resolve(window.confirm(text));
	}

	function tell(text) {
		return window.stswUI ? window.stswUI.tell(text) : Promise.resolve(window.alert(text));
	}

	function gate(event, text, yes, before) {
		if (window.stswUI) {
			return window.stswUI.gate(event, text, yes, before);
		}
		if (!window.confirm(text)) {
			event.preventDefault();
			return false;
		}
		if (before) {
			before();
		}
		return true;
	}

	function toast(text, error) {
		if (!region || !text) {
			return;
		}
		var item = document.createElement("div");
		item.className = "hd-toast " + (error ? "is-error" : "is-success");
		item.setAttribute("role", error ? "alert" : "status");
		item.textContent = text;
		region.appendChild(item);
		window.setTimeout(function () {
			item.remove();
		}, 5000);
	}

	function draftKey() {
		var form = box();
		return form ? "stsw-draft-" + form.getAttribute("data-ticket") : "";
	}

	// Reply or private note.
	function setMode(mode) {
		var form = box();
		if (!form) {
			return;
		}
		var radio = form.querySelector('input[name="stsw_mode"][value="' + mode + '"]');
		if (radio) {
			radio.checked = true;
		}
		var note = "note" === mode;
		form.classList.toggle("is-note", note);
		var label = form.querySelector(".stsw-replybox__label");
		if (label) {
			label.textContent = note ? t.noteMode : t.replyMode;
		}
		if (area()) {
			area().placeholder = note ? t.noteHolder : t.replyHolder;
		}
		var submit = form.querySelector(".hd-composer__submit");
		if (submit) {
			submit.textContent = submit.getAttribute(note ? "data-note" : "data-reply");
		}
		var close = form.querySelector(".stsw-reply-close");
		if (close) {
			close.hidden = note;
		}
	}

	// Things to set up each time the ticket is shown (first load and after an update in place).
	function setup() {
		var text = area();
		var key = draftKey();
		if (text && key && window.localStorage) {
			try {
				if (/[?&]stsw_msg=(sent|noted)/.test(window.location.search)) {
					window.localStorage.removeItem(key);
				} else if (!text.value && window.localStorage.getItem(key)) {
					text.value = window.localStorage.getItem(key);
				}
			} catch (e) {}
		}
		var form = controls();
		if (form) {
			Array.prototype.forEach.call(form.querySelectorAll("select, input[name=stsw_tags]"), function (el) {
				// Only fields shown for the first time: a refresh must not mark a pending change as saved.
				if (!el.hasAttribute("data-saved")) {
					el.setAttribute("data-saved", el.value);
				}
			});
		}
		refreshBulk();
	}

	document.addEventListener("change", function (event) {
		var el = event.target;
		var form = box();
		if (form && form.contains(el)) {
			if ("stsw_mode" === el.name) {
				setMode(el.value);
			}
			if ("stsw-inbox-files" === el.id) {
				var names = form.querySelector(".hd-attach__names");
				if (names) {
					names.textContent = Array.prototype.map.call(el.files, function (f) {
						return f.name;
					}).join(", ");
				}
			}
		}
	});

	// Edit a reply or note: the earlier text is kept (shown under the message).
	document.addEventListener("click", function (event) {
		var button = event.target.closest ? event.target.closest("[data-hd-edit]") : null;
		if (!button || !window.fetch) {
			return;
		}
		var msg = button.closest(".hd-msg");
		var body = msg ? msg.querySelector(".hd-msg__body") : null;
		if (!body || msg.querySelector(".hd-msg__editor")) {
			return;
		}
		var editor = document.createElement("div");
		editor.className = "hd-msg__editor";
		var label = document.createElement("label");
		label.className = "screen-reader-text";
		label.setAttribute("for", "hd-edit-" + button.getAttribute("data-id"));
		label.textContent = t.edit;
		var text = document.createElement("textarea");
		text.id = "hd-edit-" + button.getAttribute("data-id");
		text.rows = 4;
		text.value = body.innerText;
		var save = document.createElement("button");
		save.type = "button";
		save.className = "hd-btn hd-btn--primary hd-btn--sm";
		save.textContent = t.save;
		var cancel = document.createElement("button");
		cancel.type = "button";
		cancel.className = "hd-btn hd-btn--sm";
		cancel.textContent = t.cancel;
		editor.appendChild(label);
		editor.appendChild(text);
		editor.appendChild(save);
		editor.appendChild(cancel);
		body.hidden = true;
		body.parentNode.insertBefore(editor, body.nextSibling);
		text.focus();
		cancel.addEventListener("click", function () {
			editor.remove();
			body.hidden = false;
			button.focus();
		});
		save.addEventListener("click", function () {
			var data = new window.FormData();
			data.append("action", "wpfactory_wc_sts_message_edit");
			data.append("_ajax_nonce", t.nonce);
			data.append("type", button.getAttribute("data-hd-edit"));
			data.append("id", button.getAttribute("data-id"));
			data.append("content", text.value);
			save.disabled = true;
			window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
				.then(function (r) {
					return r.json();
				})
				.then(function (json) {
					save.disabled = false;
					if (!json || !json.success) {
						tell((json && json.data && json.data.message) || t.saveError);
						return;
					}
					body.innerHTML = json.data.html;
					var old = msg.querySelector(".hd-history");
					if (old) {
						old.remove();
					}
					if (json.data.history) {
						body.insertAdjacentHTML("afterend", json.data.history);
					}
					editor.remove();
					body.hidden = false;
					button.focus();
					refresh(["list"]);
				})
				.catch(function () {
					save.disabled = false;
					tell(t.saveError);
				});
		});
	});

	// Confirmations that name the change.
	document.addEventListener("submit", function (event) {
		var form = event.target;
		if (form === box() && "1" === form.getAttribute("data-hd-confirm-send") && area() && area().value.trim()) {
			var note = form.querySelector('input[name="stsw_mode"][value="note"]');
			var isNote = note && note.checked;
			gate(event, isNote ? t.confirmNote : t.confirmSend, isNote ? t.addNote : t.sendReply);
			return;
		}
		if (form.classList && form.classList.contains("stsw-bulk")) {
			var by = event.submitter;
			var trash = by && "delete:1" === by.value;
			var count = form.querySelectorAll('input[name="stsw_ids[]"]:checked').length;
			var what = by ? by.textContent.trim() : "";
			var text = trash ? String(t.confirmDeleteN).replace("%d", count) : String(t.confirmBulkN).replace("%1$s", what).replace("%2$d", count);
			gate(event, text, trash ? t.trashYes : t.applyYes);
		}
	});

	// Ctrl+Enter (or Cmd+Enter) sends.
	document.addEventListener("keydown", function (event) {
		var text = area();
		if (event.target !== text || "Enter" !== event.key || !(event.ctrlKey || event.metaKey)) {
			return;
		}
		event.preventDefault();
		var submit = box().querySelector(".hd-composer__submit");
		if (box().requestSubmit && submit) {
			box().requestSubmit(submit);
		} else if (submit) {
			submit.click();
		}
	});

	// Drafts, and who is typing.
	var timer = null;
	var typingSince = 0;
	document.addEventListener("input", function (event) {
		var text = area();
		if (event.target !== text) {
			return;
		}
		typingSince = Date.now();
		if (!window.localStorage) {
			return;
		}
		window.clearTimeout(timer);
		timer = window.setTimeout(function () {
			try {
				var key = draftKey();
				var draft = $(".stsw-replybox__draft");
				if (text.value) {
					window.localStorage.setItem(key, text.value);
					if (draft) {
						draft.textContent = t.draftSaved;
					}
				} else {
					window.localStorage.removeItem(key);
				}
			} catch (e) {}
		}, 600);
	});

	// Auto-save of status, priority, assignee and tags; the list then shows the change.
	function autosave(select) {
		var form = controls();
		if (!form || !window.fetch) {
			return false;
		}
		var data = new window.FormData();
		data.append("action", "wpfactory_wc_sts_inbox_update");
		data.append("_wpnonce", t.nonce);
		data.append("ticket", form.getAttribute("data-ticket"));
		data.append("field", select.name);
		data.append("value", select.value);
		form.classList.add("is-saving");
		window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				form.classList.remove("is-saving");
				var ok = json && json.success;
				toast(ok ? json.data.message : (json && json.data && json.data.message) || t.saveError, !ok);
				if (ok) {
					select.setAttribute("data-saved", select.value);
					refresh(["list", "rail", "thread", "side"]);
				} else if (select.hasAttribute("data-saved")) {
					select.value = select.getAttribute("data-saved");
				}
			})
			.catch(function () {
				form.classList.remove("is-saving");
				toast(t.saveError, true);
			});
		return true;
	}
	var wait = null;
	document.addEventListener("change", function (event) {
		var form = controls();
		var el = event.target;
		if (!form || !form.contains(el) || !(el.tagName === "SELECT" || "stsw_tags" === el.name)) {
			return;
		}
		// Arrow keys on a closed select change it at every step: wait until the choice settles.
		window.clearTimeout(wait);
		wait = window.setTimeout(function () {
			if ("stsw_status" === el.name && "closed" === el.value && el.getAttribute("data-saved") !== "closed") {
				ask(t.confirmClose, t.closeYes).then(function (sure) {
					if (sure) {
						autosave(el);
					} else {
						el.value = el.getAttribute("data-saved");
					}
				});
				return;
			}
			autosave(el);
		}, "SELECT" === el.tagName ? 700 : 0);
	});

	// Bulk bar.
	function refreshBulk() {
		var bulk = $(".stsw-bulk");
		var all = $(".stsw-check-all");
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
	document.addEventListener("change", function (event) {
		if (event.target.classList && event.target.classList.contains("stsw-check-all")) {
			Array.prototype.forEach.call(document.querySelectorAll('input[name="stsw_ids[]"]'), function (item) {
				item.checked = event.target.checked;
			});
			refreshBulk();
		} else if ("stsw_ids[]" === event.target.name) {
			refreshBulk();
		}
	});

	/*
	 * Updates in place. `parts` lists what to replace from a fresh copy of the page: rail (view counts), list,
	 * thread (messages and timeline), side (customer and order cards), detail (the whole ticket).
	 */
	var PARTS = {
		rail: ".hd-rail",
		list: ".hd-list",
		thread: ".hd-thread",
		side: ".hd-side",
		detail: ".hd-detail",
	};

	function keepScroll() {
		var saved = [[document.scrollingElement || document.documentElement, 0]];
		Array.prototype.forEach.call(document.querySelectorAll(".hd-list, .hd-detail, .hd-list__items, .hd-ticket__main, .hd-thread, .hd-side"), function (el) {
			saved.push([el, 0]);
		});
		saved.forEach(function (pair) {
			pair[1] = pair[0].scrollTop;
		});
		return function () {
			saved.forEach(function (pair) {
				var el = pair[0].isConnected ? pair[0] : null;
				if (!el && pair[0].className) {
					el = document.querySelector("." + String(pair[0].className).split(" ").join("."));
				}
				if (el) {
					el.scrollTop = pair[1];
				}
			});
		};
	}

	function apply(doc, parts) {
		var restore = keepScroll();
		var focusId = document.activeElement && document.activeElement.id;
		var editing = $(".hd-msg__editor");
		parts.forEach(function (name) {
			if (("thread" === name || "detail" === name) && editing) {
				return; // Never throw away a message being edited.
			}
			var now = $(PARTS[name]);
			var fresh = $(PARTS[name], doc);
			if (now && fresh) {
				if ("detail" === name) {
					// Keep what is being typed in the reply box if it is the same ticket.
					var oldText = area();
					var sameTicket = box() && fresh.querySelector(".stsw-replybox") && box().getAttribute("data-ticket") === fresh.querySelector(".stsw-replybox").getAttribute("data-ticket");
					var keepText = oldText && sameTicket ? oldText.value : null;
					now.innerHTML = fresh.innerHTML;
					if (null !== keepText && area()) {
						area().value = keepText;
					}
				} else {
					now.innerHTML = fresh.innerHTML;
				}
			}
		});
		var presence = $("[data-hd-presence]");
		var freshPresence = $("[data-hd-presence]", doc);
		if (presence && freshPresence) {
			presence.setAttribute("data-since", freshPresence.getAttribute("data-since"));
		}
		setup();
		restore();
		if (focusId && document.getElementById(focusId) && document.activeElement !== document.getElementById(focusId)) {
			document.getElementById(focusId).focus({ preventScroll: true });
		}
	}

	function load(url) {
		return window.fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "stsw-inbox" } }).then(function (r) {
			return r.text().then(function (html) {
				return { url: r.url, doc: new window.DOMParser().parseFromString(html, "text/html") };
			});
		});
	}

	function refresh(parts) {
		if (!window.fetch || !window.DOMParser || busy) {
			return Promise.resolve();
		}
		return load(window.location.href.replace(/([?&])stsw_msg=[^&]*&?/, "$1"))
			.then(function (res) {
				if ($(".hd-inbox", res.doc)) {
					apply(res.doc, parts);
				}
			})
			.catch(function () {});
	}

	// Every inbox form (reply, note, close, bulk, refund, credit, payment link, merge...) is sent in the background.
	window.addEventListener("submit", function (event) {
		var form = event.target;
		if (event.defaultPrevented || !window.fetch || !window.DOMParser || !form.closest || !form.closest(".hd-inbox-wrap")) {
			return;
		}
		var action = form.getAttribute("action") || "";
		if ("post" !== (form.getAttribute("method") || "").toLowerCase() || action.indexOf("admin-post.php") < 0 || form.hasAttribute("data-hd-reload")) {
			return;
		}
		event.preventDefault();
		var by = event.submitter && event.submitter.form === form ? event.submitter : null;
		var data = new window.FormData(form);
		if (by && by.name) {
			data.append(by.name, by.value);
		}
		var reply = form === box();
		var buttons = form.querySelectorAll("button, input[type=submit]");
		Array.prototype.forEach.call(buttons, function (b) {
			b.disabled = true;
		});
		form.setAttribute("aria-busy", "true");
		busy = true;
		var done = function () {
			busy = false;
			form.removeAttribute("aria-busy");
			Array.prototype.forEach.call(buttons, function (b) {
				b.disabled = false;
			});
		};
		window
			.fetch(action, { method: "POST", credentials: "same-origin", body: data }) // Not form.action: a field is named "action".
			.then(function (r) {
				return r.text().then(function (html) {
					return { url: r.url, doc: new window.DOMParser().parseFromString(html, "text/html") };
				});
			})
			.then(function (res) {
				done();
				if (!$(".hd-inbox", res.doc)) {
					var page = res.doc.querySelector(".wp-die-message, #error-page p, .hd-toast");
					var why = page ? page.textContent.trim().slice(0, 300) : "";
					toast(why || t.saveError, true);
					return;
				}
				var url = new window.URL(res.url);
				var msg = url.searchParams.get("stsw_msg") || "";
				url.searchParams.delete("stsw_msg");
				var before = new window.URL(window.location.href);
				var moved = before.searchParams.get("ticket") !== url.searchParams.get("ticket") || before.searchParams.get("stsw_new") !== url.searchParams.get("stsw_new");
				if (window.history && window.history.replaceState) {
					window.history[moved ? "pushState" : "replaceState"](null, "", url.toString());
				}
				if (reply && /^(sent|noted|sent_closed)$/.test(msg)) {
					try {
						window.localStorage.removeItem(draftKey());
					} catch (e) {}
					if (area()) {
						area().value = "";
					}
				}
				apply(res.doc, ["rail", "list", "detail"]);
				var note = $(".hd-inbox-wrap > .hd-toast", res.doc);
				if (note) {
					toast(note.textContent.trim(), note.classList.contains("is-error"));
				}
				if (moved) {
					var title = document.getElementById("hd-ticket-title");
					if (title) {
						title.setAttribute("tabindex", "-1");
						title.focus({ preventScroll: true });
					}
				} else if (reply && area()) {
					area().focus({ preventScroll: true });
				}
			})
			.catch(function () {
				done();
				toast(t.saveError, true);
			});
	});

	// Live updates: who else is on this ticket, new messages (shown in place) and list changes, every 15 seconds.
	function beat() {
		var presence = $("[data-hd-presence]");
		if (!presence) {
			refresh(["rail", "list"]);
			return;
		}
		var data = new window.FormData();
		data.append("action", "wpfactory_wc_sts_presence");
		data.append("_wpnonce", t.nonce);
		data.append("ticket", presence.getAttribute("data-ticket"));
		data.append("since", presence.getAttribute("data-since"));
		data.append("typing", Date.now() - typingSince < 20000 ? "1" : "");
		window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					return;
				}
				var lines = [];
				var writers = json.data.others.filter(function (o) {
					return o.typing;
				});
				var viewers = json.data.others.filter(function (o) {
					return !o.typing;
				});
				if (writers.length) {
					lines.push(String(t.replying).replace("%s", writers.map(function (o) {
						return o.name;
					}).join(", ")));
				}
				if (viewers.length) {
					lines.push(String(t.viewing).replace("%s", viewers.map(function (o) {
						return o.name;
					}).join(", ")));
				}
				presence.textContent = lines.join(" ");
				presence.hidden = !presence.textContent;
				if (json.data.changed) {
					refresh(["rail", "list", "thread", "side"]).then(function () {
						toast(t.newMessage);
					});
				} else {
					refresh(["rail", "list"]);
				}
			})
			.catch(function () {});
	}
	if ($(".hd-inbox") && window.fetch) {
		window.setInterval(function () {
			if (!document.hidden) {
				beat();
			}
		}, 15000);
		if ($("[data-hd-presence]")) {
			window.setTimeout(beat, 1500);
		}
	}

	// Live chat availability switch, and a heartbeat so visitors see the team as online.
	var available = $("[data-hd-available]");
	if (available && window.fetch) {
		var ping = function (value) {
			var data = new window.FormData();
			data.append("action", "wpfactory_wc_sts_available");
			data.append("_wpnonce", t.nonce);
			if (undefined !== value) {
				data.append("available", value ? "1" : "0");
			}
			return window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data });
		};
		available.addEventListener("change", function () {
			ping(available.checked);
		});
		window.setInterval(function () {
			if (!document.hidden && available.checked) {
				ping();
			}
		}, 60000);
	}

	// Shortcuts help.
	function toggleHelp() {
		var dialog = $(".stsw-shortcuts");
		if (!dialog || !dialog.showModal) {
			return;
		}
		if (dialog.open) {
			dialog.close();
		} else {
			dialog.showModal();
		}
	}
	document.addEventListener("click", function (event) {
		if (event.target.closest && event.target.closest(".stsw-shortcuts-open")) {
			toggleHelp();
		}
	});

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
		if (event.ctrlKey || event.metaKey || event.altKey || typing(event.target) || $("dialog[open]")) {
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
				if (area()) {
					event.preventDefault();
					setMode("n" === event.key ? "note" : "reply");
					area().focus();
				}
				break;
			case "c":
				var status = document.getElementById("stsw-status");
				if (controls() && status && status.value !== "closed") {
					ask(t.confirmClose, t.closeYes).then(function (sure) {
						if (!sure) {
							return;
						}
						status.value = "closed";
						if (!autosave(status)) {
							controls().submit();
						}
					});
				}
				break;
			case "?":
				toggleHelp();
				break;
		}
	});

	setup();
})();
