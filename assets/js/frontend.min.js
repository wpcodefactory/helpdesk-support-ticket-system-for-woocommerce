/**
 * Helpdesk Support Ticket System for WooCommerce - customer screens.
 *
 * Progressive enhancement: without scripts every step of the ask flow is visible and works. With scripts the steps
 * show one at a time, only the chosen order's items are offered, step changes are announced and forms are not sent
 * twice.
 *
 * @author WPFactory
 */

(function () {
	"use strict";

	var texts = window.WPFactory_WC_STS_Frontend || {};

	function each(list, fn) {
		Array.prototype.forEach.call(list, fn);
	}

	function initAsk(form) {
		var steps = form.querySelectorAll(".stsw-step");
		var next = form.querySelector("[data-stsw-next]");
		var back = form.querySelector("[data-stsw-back]");
		var submit = form.querySelector("[data-stsw-submit]");
		var live = form.querySelector(".stsw-live");
		var current = 0;

		function selectedOrder() {
			var checked = form.querySelector('input[name="ticketorder"]:checked');
			var fixed = form.querySelector('input[type="hidden"][name="ticketorder"]');
			return checked ? checked.value : fixed ? fixed.value : "";
		}

		function syncItems() {
			var order = selectedOrder();
			each(form.querySelectorAll(".stsw-items"), function (group) {
				var show = group.getAttribute("data-order") === order;
				group.hidden = !show;
				if (!show) {
					each(group.querySelectorAll("input"), function (input) {
						input.checked = false;
					});
				}
			});
		}

		function valid(step) {
			var fields = step.querySelectorAll("input, select, textarea");
			for (var i = 0; i < fields.length; i++) {
				if (fields[i].willValidate && !fields[i].checkValidity()) {
					fields[i].reportValidity();
					return false;
				}
			}
			return true;
		}

		function show(index, focus) {
			current = index;
			each(steps, function (step, i) {
				step.hidden = i !== index;
			});
			back.hidden = index === 0;
			next.hidden = index === steps.length - 1;
			submit.hidden = index !== steps.length - 1;
			if (live) {
				live.textContent = steps[index].querySelector("legend").textContent.trim();
			}
			if (focus) {
				var first = steps[index].querySelector("input:not([type=hidden]), select, textarea");
				(first || steps[index]).focus();
			}
		}

		if (steps.length < 2 || !next || !back) {
			syncItems();
			return;
		}

		form.classList.add("stsw-js");
		each(form.querySelectorAll('input[name="ticketorder"]'), function (input) {
			input.addEventListener("change", syncItems);
		});
		next.addEventListener("click", function () {
			if (valid(steps[current])) {
				show(current + 1, true);
			}
		});
		back.addEventListener("click", function () {
			show(current - 1, true);
		});
		syncItems();
		show(0, false);
	}

	function preventDoubleSubmit(form) {
		form.addEventListener("submit", function (event) {
			if (form.getAttribute("data-stsw-sent")) {
				event.preventDefault();
				return;
			}
			form.setAttribute("data-stsw-sent", "1");
			each(form.querySelectorAll('[type="submit"]'), function (button) {
				button.setAttribute("aria-disabled", "true");
			});
			if (texts.sending) {
				var live = form.querySelector(".stsw-live");
				if (live) {
					live.textContent = texts.sending;
				}
			}
		});
	}

	// File drop zones: highlight while dragging, list the chosen files.
	function initDrop(zone) {
		var input = zone.querySelector('input[type="file"]');
		var text = zone.querySelector(".stsw-drop__text");
		var empty = text ? text.textContent : "";
		["dragenter", "dragover"].forEach(function (name) {
			zone.addEventListener(name, function () {
				zone.classList.add("is-over");
			});
		});
		["dragleave", "drop"].forEach(function (name) {
			zone.addEventListener(name, function () {
				zone.classList.remove("is-over");
			});
		});
		input.addEventListener("change", function () {
			if (text) {
				text.textContent = input.files.length ? Array.prototype.map.call(input.files, function (f) {
					return f.name;
				}).join(", ") : empty;
			}
		});
	}

	// Knowledge base articles that match what the customer types.
	function initSuggest(box) {
		var form = box.closest("form");
		var list = box.querySelector("ul");
		var live = form.querySelector("[data-stsw-suggest-live]");
		var fields = form.querySelectorAll('[name="title"], [name="content"]');
		var timer = null;
		var last = "";
		if (!texts.ajaxurl || !window.fetch) {
			return;
		}
		function run() {
			var q = Array.prototype.map.call(fields, function (f) {
				return f.value;
			}).join(" ").trim();
			if (q.length < 4 || q === last) {
				return;
			}
			last = q;
			window.fetch(texts.ajaxurl + "?action=wpfactory_wc_sts_kb_suggest&q=" + encodeURIComponent(q.slice(0, 300)), { credentials: "same-origin" })
				.then(function (r) {
					return r.json();
				})
				.then(function (json) {
					var items = json && json.success ? json.data : [];
					list.textContent = "";
					items.forEach(function (item) {
						var li = document.createElement("li");
						var a = document.createElement("a");
						a.href = item.url;
						a.target = "_blank";
						a.rel = "noopener";
						a.textContent = item.title;
						var span = document.createElement("span");
						span.textContent = item.excerpt;
						li.appendChild(a);
						li.appendChild(span);
						list.appendChild(li);
					});
					box.hidden = !items.length;
					if (live) {
						live.textContent = items.length ? texts.suggestFound.replace("%d", items.length) : "";
					}
				})
				.catch(function () {});
		}
		each(fields, function (field) {
			field.addEventListener("input", function () {
				window.clearTimeout(timer);
				timer = window.setTimeout(run, 600);
			});
		});
	}

	// "Where is my order?": show what the store knows about the chosen order before the message.
	function initTrack(form) {
		var panels = form.querySelectorAll("[data-stsw-track]");
		var reason = form.querySelector('[name="stsw_reason"]');
		if (!panels.length || !reason) {
			return;
		}
		function sync() {
			var checked = form.querySelector('input[name="ticketorder"]:checked');
			var fixed = form.querySelector('input[type="hidden"][name="ticketorder"]');
			var order = checked ? checked.value : fixed ? fixed.value : "";
			each(panels, function (panel) {
				panel.hidden = !("where" === reason.value && panel.getAttribute("data-stsw-track") === order);
			});
		}
		form.addEventListener("change", sync);
		sync();
	}

	// Fields that belong to one reason (Pro: return or exchange choice).
	function initReasonFields(form) {
		var reason = form.querySelector('[name="stsw_reason"]');
		var fields = form.querySelectorAll("[data-stsw-for-reason]");
		if (!reason || !fields.length) {
			return;
		}
		function sync() {
			each(fields, function (field) {
				field.hidden = field.getAttribute("data-stsw-for-reason") !== reason.value;
			});
		}
		reason.addEventListener("change", sync);
		sync();
	}

	/*
	 * Order or general question: an order asks the reason, a general question the topic. Extra fields with conditions
	 * show only when the chosen order, items, topic or reason match. Hidden fields are switched off (not required,
	 * not sent).
	 */
	function initWhen(form) {
		var reason = form.querySelector('[name="stsw_reason"]');
		var topic = form.querySelector('[name="stsw_tickets_subject"]');
		function order() {
			var checked = form.querySelector('input[name="ticketorder"]:checked');
			var fixed = form.querySelector('input[type="hidden"][name="ticketorder"]');
			return checked ? checked.value : fixed ? fixed.value : "";
		}
		function toggle(box, on) {
			box.hidden = !on;
			each(box.querySelectorAll("input, select, textarea"), function (field) {
				field.disabled = !on;
			});
		}
		function sync() {
			var isOrder = "" !== order() && "0" !== order();
			each(form.querySelectorAll("[data-stsw-when]"), function (box) {
				toggle(box, (box.getAttribute("data-stsw-when") === "order") === isOrder);
			});
			// What the chosen items (or, with none ticked, all items of the order) are.
			var group = form.querySelector('.stsw-items[data-order="' + order() + '"]');
			var boxes = group ? group.querySelectorAll('input[name="stsw_items[]"]') : [];
			var ticked = group ? group.querySelectorAll('input[name="stsw_items[]"]:checked') : [];
			var match = [];
			each(ticked.length ? ticked : boxes, function (input) {
				match = match.concat((input.getAttribute("data-stsw-match") || "").split(" "));
			});
			each(form.querySelectorAll("[data-stsw-if-items], [data-stsw-if-subject], [data-stsw-if-reason]"), function (box) {
				var on = true;
				var items = box.getAttribute("data-stsw-if-items");
				var subjects = box.getAttribute("data-stsw-if-subject");
				var reasons = box.getAttribute("data-stsw-if-reason");
				if (items) {
					on = on && items.split(" ").some(function (token) {
						return match.indexOf(token) > -1;
					});
				}
				if (subjects) {
					on = on && !!topic && !topic.disabled && subjects.split(" ").indexOf(topic.value) > -1;
				}
				if (reasons) {
					on = on && !!reason && !reason.disabled && reasons.split(" ").indexOf(reason.value) > -1;
				}
				toggle(box, on);
			});
		}
		form.addEventListener("change", sync);
		sync();
	}

	/*
	 * Small screens: themes such as Storefront fix a bar to the bottom of the screen. While the form is on screen,
	 * its buttons sit just above that bar, on the page's own background colour.
	 */
	function initBottomBar(form) {
		function background(el) {
			for (; el && el !== document.documentElement; el = el.parentElement) {
				var c = window.getComputedStyle(el).backgroundColor;
				if (c && "transparent" !== c && !/rgba\(.*,\s*0\)$/.test(c)) {
					return c;
				}
			}
			return window.getComputedStyle(document.body).backgroundColor || "#fff";
		}
		function measure() {
			var bar = 0;
			each(document.querySelectorAll("body *"), function (el) {
				if (el.offsetParent === null && "fixed" !== window.getComputedStyle(el).position) {
					return;
				}
				var style = window.getComputedStyle(el);
				if ("fixed" !== style.position || "none" === style.display || "hidden" === style.visibility || form.contains(el) || el.contains(form)) {
					return;
				}
				var r = el.getBoundingClientRect();
				if (r.height > 0 && r.height < window.innerHeight / 3 && r.bottom >= window.innerHeight - 2 && r.width > window.innerWidth / 2) {
					bar = Math.max(bar, window.innerHeight - r.top);
				}
			});
			form.style.setProperty("--stsw-bar", bar + "px");
			form.style.setProperty("--stsw-bg", background(form));
			form.classList.toggle("stsw-ask--barred", bar > 0);
		}
		measure();
		window.addEventListener("resize", measure);
		if (window.IntersectionObserver) {
			new window.IntersectionObserver(function (entries) {
				form.classList.toggle("stsw-ask--onscreen", entries[0].isIntersecting);
			}).observe(form);
		} else {
			form.classList.add("stsw-ask--onscreen");
		}
	}

	function init() {
		each(document.querySelectorAll(".stsw-ask"), initBottomBar);
		each(document.querySelectorAll(".stsw-ask"), initWhen);
		each(document.querySelectorAll(".stsw-ask"), initTrack);
		each(document.querySelectorAll(".stsw-ask"), initReasonFields);
		each(document.querySelectorAll(".stsw-drop__zone"), initDrop);
		each(document.querySelectorAll("[data-stsw-suggest]"), initSuggest);
		each(document.querySelectorAll(".stsw-ask"), initAsk);
		each(document.querySelectorAll(".stsw form, .wpfactory-wc-sts-guest-lookup"), preventDoubleSubmit);
		// After sending: the confirmation is scrolled into view and gets the focus (also on small screens).
		var sent = document.querySelector(".stsw-sent");
		if (sent) {
			sent.scrollIntoView({ block: "center" });
			sent.focus({ preventScroll: true });
		}
	}

	if ("loading" === document.readyState) {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
