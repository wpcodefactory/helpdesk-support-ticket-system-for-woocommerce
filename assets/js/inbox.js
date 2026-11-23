/**
 * Helpdesk Support Ticket System for WooCommerce - staff inbox.
 *
 * Keyboard shortcuts (j, k, r, n, c, /, ?), reply or private note, Ctrl+Enter to send, drafts kept on this computer,
 * auto-save of status, priority, assignee and tags, the bulk bar and confirmations. Views, filters, pages and tickets
 * open in place: the page is never rendered again, only the parts that change (head, list, conversation, details),
 * and the address follows. Every inbox form is sent in the background. New messages and changes made by teammates
 * show up on their own. Everything also works without this script.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_Inbox || window.WPFactory_WC_STS_Pro_Inbox || {};
	var busy = false;
	var asked = 0; // Pages asked for, in order.
	var shown = 0; // The newest one shown.
	var LIST_KEYS = ["view", "s", "order_id", "prio", "subject", "status", "tag", "sort", "saved", "paged"];
	var PARTS = {
		head: '[data-hd-part="head"]',
		list: '[data-hd-part="list"]',
		convo: '[data-hd-part="convo"]',
		thread: "[data-hd-thread]",
		side: '[data-hd-part="details"]',
	};

	function $(selector, root) {
		return (root || document).querySelector(selector);
	}

	function each(selector, fn, root) {
		Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
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
		var region = $(".toast-region");
		if (!region || !text) {
			return;
		}
		var item = document.createElement("div");
		item.className = "toast" + (error ? " is-error" : " is-success");
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

	// Reply or private note: note mode turns the composer yellow, "Add note" replaces "Send reply".
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
		var submit = form.querySelector(".stsw-reply-submit");
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
			each(
				"select, input[name=stsw_tags]",
				function (el) {
					// Only fields shown for the first time: a refresh must not mark a pending change as saved.
					if (!el.hasAttribute("data-saved")) {
						el.setAttribute("data-saved", el.value);
					}
				},
				form
			);
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
				var names = form.querySelector(".attach-names");
				if (names) {
					names.textContent = Array.prototype.map
						.call(el.files, function (f) {
							return f.name;
						})
						.join(", ");
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
		var msg = button.closest(".msg");
		var body = msg ? msg.querySelector(".msg-body") : null;
		if (!body || msg.querySelector(".msg-editor")) {
			return;
		}
		var editor = document.createElement("div");
		editor.className = "msg-editor";
		var label = document.createElement("label");
		label.className = "screen-reader-text";
		label.setAttribute("for", "hd-edit-" + button.getAttribute("data-id"));
		label.textContent = t.edit;
		var text = document.createElement("textarea");
		text.id = "hd-edit-" + button.getAttribute("data-id");
		text.rows = 4;
		text.value = body.innerText;
		var row = document.createElement("div");
		row.className = "send";
		var cancel = document.createElement("button");
		cancel.type = "button";
		cancel.className = "btn btn-secondary btn-sm";
		cancel.textContent = t.cancel;
		var save = document.createElement("button");
		save.type = "button";
		save.className = "btn btn-primary btn-sm";
		save.textContent = t.save;
		row.appendChild(cancel);
		row.appendChild(save);
		editor.appendChild(label);
		editor.appendChild(text);
		editor.appendChild(row);
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
			window
				.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
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
					var old = msg.querySelector(".msg-history");
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
		var submit = box().querySelector(".stsw-reply-submit");
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
		form.setAttribute("aria-busy", "true");
		window
			.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				form.removeAttribute("aria-busy");
				var ok = json && json.success;
				toast(ok ? json.data.message : (json && json.data && json.data.message) || t.saveError, !ok);
				if (ok) {
					select.setAttribute("data-saved", select.value);
					refresh(["head", "list", "thread", "side"]);
				} else if (select.hasAttribute("data-saved")) {
					select.value = select.getAttribute("data-saved");
				}
			})
			.catch(function () {
				form.removeAttribute("aria-busy");
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
		wait = window.setTimeout(
			function () {
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
			},
			"SELECT" === el.tagName ? 700 : 0
		);
	});

	// Bulk bar: "N selected"; Assign, Close and Delete wait for a selection.
	function refreshBulk() {
		var bulk = $(".stsw-bulk");
		var all = $(".stsw-check-all");
		if (!bulk) {
			return;
		}
		var boxes = bulk.querySelectorAll('input[name="stsw_ids[]"]');
		var checked = bulk.querySelectorAll('input[name="stsw_ids[]"]:checked').length;
		var count = bulk.querySelector("[data-hd-selected]");
		if (count) {
			count.textContent = String(t.selected).replace("%d", checked);
		}
		each(
			"[data-hd-bulk]",
			function (el) {
				el.disabled = 0 === checked;
			},
			bulk
		);
		if (all) {
			all.checked = checked > 0 && checked === boxes.length;
			all.indeterminate = checked > 0 && checked < boxes.length;
		}
	}
	document.addEventListener("change", function (event) {
		if (event.target.classList && event.target.classList.contains("stsw-check-all")) {
			each('input[name="stsw_ids[]"]', function (item) {
				item.checked = event.target.checked;
			});
			refreshBulk();
		} else if ("stsw_ids[]" === event.target.name) {
			refreshBulk();
		}
	});

	/*
	 * Updates in place. `parts` lists what to replace from a fresh copy of the page: head (title and view counts),
	 * list, convo (the conversation card), thread (messages and timeline only), side (the details column).
	 */
	function apply(doc, parts) {
		var page = document.scrollingElement || document.documentElement;
		var top = page.scrollTop;
		var focusId = document.activeElement && document.activeElement.id;
		var editing = $(".msg-editor");
		parts.forEach(function (name) {
			if (("thread" === name || "convo" === name) && editing) {
				return; // Never throw away a message being edited.
			}
			var now = $(PARTS[name]);
			var fresh = $(PARTS[name], doc);
			if (!now || !fresh) {
				return;
			}
			var keepText = null;
			if ("convo" === name) {
				// Keep what is being typed in the reply box if it is the same ticket.
				var next = fresh.querySelector(".stsw-replybox");
				if (area() && box() && next && box().getAttribute("data-ticket") === next.getAttribute("data-ticket")) {
					keepText = area().value;
				}
			}
			now.replaceWith(document.importNode(fresh, true));
			if (null !== keepText && area()) {
				area().value = keepText;
			}
		});
		// The presence slot stays in place; it takes the time of the newest message.
		var presence = $("[data-hd-presence]");
		var freshPresence = $("[data-hd-presence]", doc);
		if (presence && freshPresence && presence.getAttribute("data-ticket") === freshPresence.getAttribute("data-ticket")) {
			presence.setAttribute("data-since", freshPresence.getAttribute("data-since"));
		}
		var grid = $(".inbox");
		var freshGrid = $(".inbox", doc);
		if (grid && freshGrid && (parts.indexOf("convo") !== -1 || parts.indexOf("side") !== -1)) {
			grid.className = freshGrid.className;
		}
		setup();
		page.scrollTop = top;
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
		var mine = ++asked;
		return load(window.location.href.replace(/([?&])stsw_msg=[^&]*&?/, "$1"))
			.then(function (res) {
				// An older answer that arrives late must not undo a newer one.
				if (mine < shown || !$(".inbox", res.doc)) {
					return;
				}
				shown = mine;
				apply(res.doc, parts);
			})
			.catch(function () {});
	}

	/*
	 * Views, filters, pages and tickets open in place: fetch the same address, swap only what changed, keep the
	 * address in the history so reload, back and links still work.
	 */
	function param(url, key) {
		var value = url.searchParams.get(key) || "";
		return "view" === key && "" === value ? "needs_reply" : value;
	}

	function inScreen(url) {
		var here = new window.URL(window.location.href);
		return url.origin === here.origin && url.pathname === here.pathname && url.searchParams.get("page") === here.searchParams.get("page") && !url.searchParams.get("tab") && !url.searchParams.get("action");
	}

	function select(ticket) {
		each(".trow", function (row) {
			var on = row.getAttribute("data-ticket") === ticket;
			row.classList.toggle("selected", on);
			var link = row.querySelector(".stsw-row__link");
			if (link) {
				if (on) {
					link.setAttribute("aria-current", "true");
				} else {
					link.removeAttribute("aria-current");
				}
			}
		});
	}

	function navigate(href, push) {
		var before = new window.URL(window.location.href);
		var after = new window.URL(href, window.location.href);
		after.searchParams.delete("stsw_msg");
		var listChanged = LIST_KEYS.some(function (key) {
			return param(before, key) !== param(after, key);
		});
		var ticketChanged = ["ticket", "stsw_new"].some(function (key) {
			return param(before, key) !== param(after, key);
		});
		var parts = [];
		if (listChanged) {
			parts.push("head", "list");
		}
		if (ticketChanged) {
			parts.push("convo", "side");
			select(after.searchParams.get("ticket") || "");
		}
		if (!parts.length) {
			parts = ["head", "list", "convo", "side"];
		}
		var grid = $(".inbox");
		if (grid) {
			grid.setAttribute("aria-busy", "true");
		}
		if (push) {
			window.history.pushState(null, "", after.toString());
		}
		var mine = ++asked;
		return load(after.toString())
			.then(function (res) {
				if (mine < shown) {
					return;
				}
				shown = mine;
				if (grid) {
					grid.removeAttribute("aria-busy");
				}
				if (!$(".inbox", res.doc)) {
					window.location.href = after.toString();
					return;
				}
				apply(res.doc, parts);
				var note = $(".hd-inbox-wrap > .toast", res.doc);
				if (note) {
					toast(note.textContent.trim(), note.classList.contains("is-error"));
				}
				if (ticketChanged && window.matchMedia && window.matchMedia("(max-width: 780px)").matches) {
					var convo = $(PARTS.convo);
					if (convo) {
						convo.scrollIntoView();
					}
				}
			})
			.catch(function () {
				window.location.href = after.toString();
			});
	}

	function canNavigate() {
		return window.fetch && window.DOMParser && window.history && window.history.pushState && window.URL;
	}

	document.addEventListener("click", function (event) {
		if (event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !canNavigate()) {
			return;
		}
		var link = event.target.closest ? event.target.closest("a[href]") : null;
		if (!link || !link.closest(".hd-inbox-wrap") || link.target || link.hasAttribute("download")) {
			return;
		}
		var url = new window.URL(link.href, window.location.href);
		if (!inScreen(url)) {
			return;
		}
		event.preventDefault();
		navigate(url.toString(), true);
	});

	// Priority and sort: applied as soon as they change; the open ticket stays open.
	function filtersUrl(form) {
		var url = new window.URL(form.getAttribute("action"), window.location.href);
		new window.FormData(form).forEach(function (value, key) {
			if ("" !== value) {
				url.searchParams.set(key, value);
			}
		});
		var ticket = new window.URL(window.location.href).searchParams.get("ticket");
		if (ticket) {
			url.searchParams.set("ticket", ticket);
		}
		return url.toString();
	}
	document.addEventListener("change", function (event) {
		var form = event.target.closest ? event.target.closest("[data-hd-filters]") : null;
		if (form && canNavigate()) {
			navigate(filtersUrl(form), true);
		}
	});
	document.addEventListener("submit", function (event) {
		var form = event.target;
		if (form.hasAttribute && form.hasAttribute("data-hd-filters") && canNavigate()) {
			event.preventDefault();
			navigate(filtersUrl(form), true);
		}
	});

	window.addEventListener("popstate", function () {
		if ($(".inbox") && canNavigate()) {
			navigate(window.location.href, false);
		}
	});

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
				if (!$(".inbox", res.doc)) {
					var page = res.doc.querySelector(".wp-die-message, #error-page p, .toast");
					var why = page ? page.textContent.trim().slice(0, 300) : "";
					toast(why || t.saveError, true);
					return;
				}
				var back = new window.URL(res.url);
				var msg = back.searchParams.get("stsw_msg") || "";
				var before = new window.URL(window.location.href);
				// Keep the list as it was (view, search, filters, page); take only the ticket from the answer.
				var url = new window.URL(window.location.href);
				url.searchParams.delete("stsw_msg");
				["ticket", "stsw_new"].forEach(function (key) {
					if (back.searchParams.get(key)) {
						url.searchParams.set(key, back.searchParams.get(key));
					} else {
						url.searchParams.delete(key);
					}
				});
				var lost = LIST_KEYS.some(function (key) {
					return before.searchParams.get(key) && before.searchParams.get(key) !== back.searchParams.get(key);
				});
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
				shown = ++asked; // Newer than any refresh still on its way.
				apply(res.doc, lost ? ["head", "convo", "side"] : ["head", "list", "convo", "side"]);
				if (lost) {
					refresh(["head", "list"]);
				} else if (moved) {
					select(url.searchParams.get("ticket") || "");
				}
				var note = $(".hd-inbox-wrap > .toast", res.doc);
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
			refresh(["head", "list"]);
			return;
		}
		var data = new window.FormData();
		data.append("action", "wpfactory_wc_sts_presence");
		data.append("_wpnonce", t.nonce);
		data.append("ticket", presence.getAttribute("data-ticket"));
		data.append("since", presence.getAttribute("data-since"));
		data.append("typing", Date.now() - typingSince < 20000 ? "1" : "");
		window
			.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data })
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
					lines.push(
						String(t.replying).replace(
							"%s",
							writers
								.map(function (o) {
									return o.name;
								})
								.join(", ")
						)
					);
				}
				if (viewers.length) {
					lines.push(
						String(t.viewing).replace(
							"%s",
							viewers
								.map(function (o) {
									return o.name;
								})
								.join(", ")
						)
					);
				}
				presence.textContent = lines.join(" ");
				presence.hidden = !presence.textContent;
				if (json.data.changed) {
					refresh(["head", "list", "thread", "side"]).then(function () {
						toast(t.newMessage);
					});
				} else {
					refresh(["head", "list"]);
				}
			})
			.catch(function () {});
	}
	if ($(".inbox") && window.fetch) {
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
	function availability() {
		return $("[data-hd-available]");
	}
	if (window.fetch) {
		var ping = function (value) {
			var data = new window.FormData();
			data.append("action", "wpfactory_wc_sts_available");
			data.append("_wpnonce", t.nonce);
			if (undefined !== value) {
				data.append("available", value ? "1" : "0");
			}
			return window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: data });
		};
		document.addEventListener("change", function (event) {
			if (event.target.hasAttribute && event.target.hasAttribute("data-hd-available")) {
				ping(event.target.checked);
			}
		});
		window.setInterval(function () {
			var available = availability();
			if (!document.hidden && available && available.checked) {
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
			rows[next].focus({ preventScroll: false });
			rows[next].click();
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

	// Menus close when something else is clicked.
	document.addEventListener("click", function (event) {
		var target = event.target;
		if (!target.isConnected || (target.closest && target.closest("dialog")) || $("dialog[open]")) {
			return; // A confirmation was answered: the form it belongs to stays open.
		}
		each(".hd-inbox-wrap details.menu[open]", function (menu) {
			if (!menu.contains(target)) {
				menu.open = false;
			}
		});
	});

	// AI with WPFactory AI credits: the draft goes into the reply box (never sent on its own), the answers below it.
	document.addEventListener("click", function (event) {
		var button = event.target.closest ? event.target.closest("[data-stsw-cai]") : null;
		if (!button) {
			return;
		}
		event.preventDefault();
		var menu = button.closest("[data-stsw-ai-menu]");
		if (menu) {
			menu.open = false;
		}
		var task = button.getAttribute("data-stsw-cai");
		var out = document.getElementById("stsw-credits-ai-out");
		var text = area();
		if ("improve" === task && (!text || !text.value.trim())) {
			if (out) {
				out.classList.add("is-error");
				out.textContent = button.getAttribute("data-empty");
			}
			return;
		}
		var body = new window.FormData();
		body.append("action", "wpfactory_wc_sts_credits_ai");
		if ("improve" === task) {
			body.append("text", text.value);
		}
		body.append("nonce", button.getAttribute("data-nonce"));
		body.append("task", task);
		body.append("ticket", button.getAttribute("data-ticket"));
		button.setAttribute("aria-busy", "true");
		button.disabled = true;
		if (out) {
			out.classList.remove("is-error");
			out.textContent = button.getAttribute("data-working");
		}
		var put = function (value) {
			text.value = value;
			text.dispatchEvent(new Event("input", { bubbles: true }));
			text.focus();
		};
		window
			.fetch(button.getAttribute("data-url"), { method: "POST", body: body, credentials: "same-origin" })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					var failure = new Error((json && json.data && json.data.message) || "");
					failure.link = json && json.data && json.data.link;
					failure.label = json && json.data && json.data.label;
					throw failure;
				}
				if (json.data.pill) {
					// The credits button in the top bar follows the balance.
					var inbox = window.WPFactory_WC_STS_Inbox || window.WPFactory_WC_STS_Pro_Inbox || {};
					document.querySelectorAll(".wpfactory-ai-pill, [data-stsw-credits-pill]").forEach(function (pill) {
						pill.textContent = pill.hasAttribute("data-stsw-credits-pill") && inbox.creditsPill ? String(inbox.creditsPill).replace("%s", json.data.pill) : json.data.pill;
					});
				}
				if ("improve" === task && text) {
					put(json.data.text);
					if (out) {
						out.textContent = "";
					}
				} else if ("draft" === task && text) {
					if (!text.value.trim()) {
						put(json.data.text);
					} else {
						ask(button.getAttribute("data-replace"), t.replaceYes || t.applyYes).then(function (sure) {
							if (sure) {
								put(json.data.text);
							}
						});
					}
					if (out) {
						out.textContent = "";
					}
				} else if (out) {
					out.textContent = json.data.text;
				}
			})
			.catch(function (error) {
				if (out) {
					out.classList.add("is-error");
					out.textContent = error.message || "";
					if (error.link) {
						var link = document.createElement("a");
						link.href = error.link;
						link.textContent = error.label || error.link;
						out.appendChild(document.createTextNode(" "));
						out.appendChild(link);
					}
				}
			})
			.then(function () {
				button.removeAttribute("aria-busy");
				button.disabled = false;
			});
	});

	setup();
})();
