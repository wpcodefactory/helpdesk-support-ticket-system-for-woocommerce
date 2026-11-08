/**
 * Helpdesk Support Ticket System for WooCommerce - live chat widget.
 *
 * Polls every 3 seconds while the page is visible, every 15 seconds while hidden, and stops when the chat window is
 * closed. The session (ticket number and secret) stays in this browser so a reload continues the chat.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var t = window.WPFactory_WC_STS_Chat || window.WPFactory_WC_STS_Pro_Chat || {};
	var root = document.querySelector("[data-stsw-chat]");
	if (!root || !window.fetch) {
		return;
	}
	var launcher = root.querySelector(".stsw-chat__launcher");
	var panel = root.querySelector(".stsw-chat__panel");
	var log = root.querySelector("[data-stsw-chat-log]");
	var start = root.querySelector("[data-stsw-chat-start]");
	var send = root.querySelector("[data-stsw-chat-send]");
	var state = root.querySelector("[data-stsw-chat-state]");
	var offlineNote = root.querySelector("[data-stsw-chat-offline]");
	var errorBox = root.querySelector("[data-stsw-chat-error]");
	var captcha = root.querySelector("[data-stsw-chat-captcha]");
	var STORE = "stsw_chat_session";
	var session = null;
	var nonce = "";
	var last = -1;
	var timer = null;
	var open = false;

	try {
		session = JSON.parse(window.localStorage.getItem(STORE) || "null");
	} catch (e) {}

	function post(action, data) {
		var body = new window.FormData();
		body.append("action", "wpfactory_wc_sts_chat_" + action);
		Object.keys(data || {}).forEach(function (k) {
			body.append(k, data[k]);
		});
		return window.fetch(t.ajaxurl, { method: "POST", credentials: "same-origin", body: body }).then(function (r) {
			return r.json();
		});
	}

	function add(msg) {
		var li = document.createElement("li");
		li.className = "stsw-chat__msg stsw-chat__msg--" + ("store" === msg.by ? "store" : "you");
		var who = document.createElement("span");
		who.className = "screen-reader-text";
		who.textContent = ("store" === msg.by ? msg.name || "" : t.you) + ": ";
		li.appendChild(who);
		li.appendChild(document.createTextNode(msg.text));
		log.appendChild(li);
		log.scrollTop = log.scrollHeight;
	}

	function note(text) {
		var li = document.createElement("li");
		li.className = "stsw-chat__msg stsw-chat__msg--note";
		li.textContent = text;
		log.appendChild(li);
	}

	function schedule() {
		window.clearTimeout(timer);
		if (!open || !session) {
			return;
		}
		timer = window.setTimeout(poll, document.hidden ? 15000 : 3000);
	}

	function poll() {
		if (!session) {
			return;
		}
		post("poll", { ticket: session.ticket, key: session.key, after: last })
			.then(function (json) {
				if (!json || !json.success) {
					if (json && json.data && "session" === json.data.message) {
						forget();
					}
					return;
				}
				json.data.messages.forEach(function (m) {
					add(m);
					last = Math.max(last, m.id);
				});
				if (json.data.closed) {
					note(t.closed);
					forget();
					return;
				}
				schedule();
			})
			.catch(schedule);
	}

	function forget() {
		session = null;
		last = -1;
		try {
			window.localStorage.removeItem(STORE);
		} catch (e) {}
		send.hidden = true;
		start.hidden = false;
	}

	function showChat() {
		start.hidden = true;
		send.hidden = false;
	}

	function status() {
		return post("status").then(function (json) {
			if (json && json.success) {
				nonce = json.data.nonce;
				offlineNote.hidden = json.data.online;
				if (captcha) {
					captcha.hidden = json.data.online;
				}
				state.textContent = "";
			}
		});
	}

	function setOpen(value) {
		open = value;
		panel.hidden = !value;
		launcher.setAttribute("aria-expanded", value ? "true" : "false");
		if (value) {
			if (session) {
				showChat();
				if (last < 0) {
					log.querySelectorAll(".stsw-chat__msg--you, .stsw-chat__msg--note").forEach(function (li) {
						li.remove();
					});
				}
				poll();
				send.querySelector("textarea").focus();
			} else {
				status();
				var name = start.querySelector('[name="name"]');
				var email = start.querySelector('[name="email"]');
				if (t.name && !name.value) {
					name.value = t.name;
				}
				if (t.email && !email.value) {
					email.value = t.email;
				}
				(name.value ? start.querySelector("textarea") : name).focus();
			}
		} else {
			window.clearTimeout(timer);
			launcher.focus();
		}
	}

	launcher.addEventListener("click", function () {
		setOpen(!open);
	});
	root.querySelector("[data-stsw-chat-close]").addEventListener("click", function () {
		setOpen(false);
	});
	panel.addEventListener("keydown", function (event) {
		if ("Escape" === event.key) {
			setOpen(false);
		}
	});
	document.addEventListener("visibilitychange", schedule);

	start.addEventListener("submit", function (event) {
		event.preventDefault();
		errorBox.textContent = "";
		var data = {
			_ajax_nonce: nonce,
			name: start.querySelector('[name="name"]').value,
			email: start.querySelector('[name="email"]').value,
			message: start.querySelector('[name="message"]').value,
			website: start.querySelector('[name="website"]').value
		};
		["cf-turnstile-response", "g-recaptcha-response"].forEach(function (field) {
			var input = start.querySelector('[name="' + field + '"]');
			if (input) {
				data[field] = input.value;
			}
		});
		var button = start.querySelector('[type="submit"]');
		button.disabled = true;
		post("start", data)
			.then(function (json) {
				button.disabled = false;
				if (!json || !json.success) {
					errorBox.textContent = (json && json.data && json.data.message) || t.error;
					return;
				}
				start.reset();
				if (!json.data.online) {
					note(t.received);
					return;
				}
				session = { ticket: json.data.ticket, key: json.data.key };
				try {
					window.localStorage.setItem(STORE, JSON.stringify(session));
				} catch (e) {}
				json.data.messages.forEach(function (m) {
					add(m);
					last = Math.max(last, m.id);
				});
				showChat();
				send.querySelector("textarea").focus();
				schedule();
			})
			.catch(function () {
				button.disabled = false;
				errorBox.textContent = t.error;
			});
	});

	var area = send.querySelector("textarea");
	area.addEventListener("keydown", function (event) {
		if ("Enter" === event.key && !event.shiftKey) {
			event.preventDefault();
			if (send.requestSubmit) {
				send.requestSubmit();
			}
		}
	});
	send.addEventListener("submit", function (event) {
		event.preventDefault();
		var text = area.value.trim();
		if (!text || !session) {
			return;
		}
		area.value = "";
		state.textContent = t.sending;
		post("send", { ticket: session.ticket, key: session.key, message: text })
			.then(function (json) {
				state.textContent = "";
				if (!json || !json.success) {
					area.value = text;
					state.textContent = t.error;
					return;
				}
				poll();
			})
			.catch(function () {
				area.value = text;
				state.textContent = t.error;
			});
	});
})();
