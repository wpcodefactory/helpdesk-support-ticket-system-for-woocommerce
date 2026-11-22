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
	var greeting = root.querySelector("[data-stsw-chat-greeting]");
	var greetingText = greeting ? greeting.textContent : "";
	var startButton = root.querySelector("[data-stsw-chat-start-button]");
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

	// The button takes the theme's accent colour. Pick the text colour with the higher contrast (WCAG 2.2 AA: 4.5:1),
	// and add an edge and a dark focus ring when the accent is too light against the page (3:1 for controls).
	function luminance(rgb) {
		var m = String(rgb).match(/[\d.]+/g);
		if (!m || m.length < 3) {
			return null;
		}
		var c = m.slice(0, 3).map(function (v) {
			v = Number(v) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrast(a, b) {
		return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
	}
	function fitColours() {
		var accent = luminance(window.getComputedStyle(launcher).backgroundColor);
		if (null === accent) {
			return;
		}
		var dark = "#101828";
		var darkLum = luminance("rgb(16, 24, 40)");
		var onAccent = contrast(accent, 1) >= contrast(accent, darkLum) ? "#fff" : dark;
		if (Math.max(contrast(accent, 1), contrast(accent, darkLum)) < 4.5) {
			// Mid tones: pure black or white always reaches 4.5:1.
			onAccent = contrast(accent, 1) >= contrast(accent, 0) ? "#fff" : "#000";
		}
		root.style.setProperty("--stsw-chat-on-accent", onAccent);
		if (contrast(accent, 1) < 3) {
			root.style.setProperty("--stsw-chat-edge", dark);
			root.style.setProperty("--stsw-chat-focus", dark);
		}
	}
	fitColours();

	// A random id for this browser's chat, so attempt limits count each visitor, not a shared address.
	function clientId() {
		var id = "";
		try {
			id = window.localStorage.getItem("stsw_chat_client") || "";
			if (!id) {
				id = Math.random().toString(36).slice(2) + Date.now().toString(36);
				window.localStorage.setItem("stsw_chat_client", id);
			}
		} catch (e) {}
		return id;
	}

	function post(action, data) {
		var body = new window.FormData();
		body.append("action", "wpfactory_wc_sts_chat_" + action);
		body.append("client", clientId());
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
				// Nobody online: the greeting says so and the button leaves a message.
				if (greeting) {
					greeting.textContent = json.data.online ? greetingText : t.away;
				}
				if (startButton) {
					startButton.textContent = json.data.online ? t.startBtn : t.sendMsg;
				}
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
					// The form closes; the confirmation takes its place and gets the focus.
					start.hidden = true;
					note(t.received);
					var done = log.lastElementChild;
					done.setAttribute("tabindex", "-1");
					done.setAttribute("role", "status");
					done.focus();
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
