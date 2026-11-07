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

	function init() {
		each(document.querySelectorAll(".stsw-drop__zone"), initDrop);
		each(document.querySelectorAll("[data-stsw-suggest]"), initSuggest);
		each(document.querySelectorAll(".stsw-ask"), initAsk);
		each(document.querySelectorAll(".stsw form, .wpfactory-wc-sts-guest-lookup"), preventDoubleSubmit);
		var sent = document.querySelector(".stsw-sent");
		if (sent) {
			sent.focus();
		}
	}

	if ("loading" === document.readyState) {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
