=== Customer Support Helpdesk & Tickets for WooCommerce ===
Contributors: wpcodefactory
Tags: helpdesk, support ticket, customer support, live chat, knowledge base
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 3.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Customer support for WooCommerce: tickets from My Account and orders, a team inbox with order details, help center and self-hosted live chat.

== Description ==

Customer Support Helpdesk & Tickets for WooCommerce gives your store a support desk that knows your orders. Customers ask for help from My Account or straight from an order. Your team answers from one inbox in wp-admin, with the order and the customer's history next to every conversation. Everything runs on your own site: no outside service, no per-agent fees.

= For your customers =

* **Help in My Account.** A Support tab lists their tickets with a clear status ("New reply", "Waiting for us", "Closed"). A short form asks which order, which items, the reason and the message.
* **Get Help on every order.** The order is already filled in.
* **Where is my order?** Before writing, customers see the order status and tracking (WooCommerce Shipment Tracking and Advanced Shipment Tracking are read automatically), or a simple status timeline.
* **Help articles while they type.** Matching knowledge base articles appear under the message, so many questions answer themselves.
* **Live chat (optional).** A chat button on your store while a team member is available; otherwise visitors leave a message. Every chat is a ticket, so nothing gets lost.
* **Guest tickets (optional).** Customers without an account use their order number and billing email, and follow the ticket through a private link.
* **Files.** Customers attach photos and documents (types, size and number are up to you). Files are stored privately.
* Follows your theme's fonts, colours and buttons, works on phones, and is built for keyboard and screen reader users.

= For your team =

* **Dashboard.** What waits for your team, your own tickets and new tickets per day, on the first screen.
* **One inbox.** Views with counts (Needs reply, Mine, Unassigned, Waiting on customer, Live chats, Closed), priority filters, search by name, email or ticket number, and your own saved views.
* **Everything on one screen.** The conversation, private notes, the customer's lifetime value, orders and other tickets.
* **Fast answers.** Saved replies with placeholders (customer name, order number), links to help articles, drafts kept on your computer, keyboard shortcuts and Ctrl+Enter to send.
* **No double answers.** You see when a colleague is viewing or writing a reply on the same ticket, and when a new message arrives.
* **Organised.** Priorities with colours, subjects that assign tickets to a person, statuses, tags, assignees, bulk actions and duplicate warnings.
* **Status, priority and assignee save as you change them.**
* **Knowledge base.** Articles in categories, edited with the block editor, and a searchable help center with the [stsw_knowledge_base] shortcode.
* **Move in without losing history.** Import tickets, replies and notes from SupportCandy, Fluent Support, Awesome Support and JS Help Desk. A check shows the counts first, and the other plugin's data is never changed.
* **CSV export** of any view.
* **Emails** to the store, the assignee and the customer, with your own texts, a test button and an email health check.
* **AI for your support team** (off until you turn it on), paid with WPFactory AI credits. Create a free WPFactory AI account (email and a 6-digit code) and get 100 free credits a month on a live site, about one credit per draft.
  * Draft a reply from the conversation and the order, in the customer's language.
  * Improve my reply: spelling, grammar and a friendly tone, in the same language.
  * Summarize a long ticket in a few lines.
  * Translate the customer's message into your language.
  * Chat assistant: answers live chats from your help articles, day and night, and hands the chat to your team when they do not cover the question.
  * Drafts are never sent without a person.
* **Setup wizard** on first activation, and privacy tools (personal data export and erase).

= Pro =

[Customer Support Helpdesk & Tickets for WooCommerce Pro](https://wpfactory.com/item/helpdesk-support-ticketing-system-for-woocommerce) adds tools that act on orders and automate the desk:

* Order panel in the ticket with refunds, store credit, coupons, free replacement orders, returns and exchanges, payment links, status changes and resent emails. Every money action is off until you turn it on, limited to the roles you choose, confirmed with the exact amount and recorded on the ticket.
* Workflow rules, automatic closing, reply targets with an Overdue view, proactive tickets (failed payment, stuck orders, refund requests).
* Email piping with several mailboxes (brands), satisfaction ratings and reports.
* More AI: suggested resolutions on orders, and your own key (Anthropic, OpenAI, Gemini) or an AI plugin you already use instead of WPFactory AI credits.
* Companies with shared tickets, paid or priority support sold as products, tickets opened by your team, merging.
* REST API, signed webhooks and an MCP server for AI assistants.

== External services ==

The plugin works without outside services. These optional features connect to one when you turn them on:

* Human check (Settings > Ticket form > Spam protection, off by default). With Cloudflare Turnstile, the visitor's browser loads the check from challenges.cloudflare.com and the site sends the answer and the visitor's IP address to Cloudflare to verify it. Terms: https://www.cloudflare.com/website-terms/ Privacy: https://www.cloudflare.com/privacypolicy/ . With Google reCAPTCHA, the same happens with www.google.com/recaptcha. Terms: https://policies.google.com/terms Privacy: https://policies.google.com/privacy .
* AI with WPFactory AI credits (off by default). This plugin can use WPFactory AI (https://ai.wpfactory.com), a service by WPFactory, for its AI features, paid with WPFactory credits. Nothing is sent until a site administrator connects the site on the AI Credits screen with an email address and a 6-digit code sent to it. When connecting, the site address and that email address are sent. After that, the text of each AI request the plugin makes is sent when a user starts it (for example the ticket conversation when a team member clicks "Draft a reply with AI"); WPFactory AI passes it to Anthropic, its AI provider, to produce the answer. WPFactory AI keeps only numbers about each request (tokens, credits, time), never its content. When the chat assistant is turned on (Settings > AI, off by default), each live chat message from a visitor and the matching help articles are sent the same way, without a team member clicking. Terms: https://wpfactory.com/terms-conditions/#wpfactory-ai Privacy: https://wpfactory.com/privacy-policy/#wpfactory-ai

== Installation ==

1. Install the plugin from Plugins > Add New, or upload it to `/wp-content/plugins`.
2. Activate it. WooCommerce must be active.
3. The setup wizard opens once: choose the My Account labels, your ticket subjects and who gets alerts. You can run it again from Settings.
4. Find everything under Helpdesk in the admin menu.

== Frequently Asked Questions ==

= Where do customers open tickets? =
In My Account > Support (you can rename it), with the Get Help button on each order, or on any page with the [stsw_user_tickets] shortcode. Guests can use their order number and billing email when guest tickets are on.

= Does it need an outside service or an account somewhere? =
No. Tickets, chats, articles and files stay in your WordPress database and uploads folder.

= How does the live chat know if someone is online? =
A team member turns on "Available for chat" in the inbox. Visitors can chat while someone is available and active in the last 5 minutes; otherwise they get a short form that opens a ticket.

= Can I move my tickets from another helpdesk plugin? =
Yes, from SupportCandy, Fluent Support, Awesome Support and JS Help Desk (Helpdesk > Import and export). Run the check first; nothing in the other plugin is changed and tickets already imported are skipped. Attached files stay in the old plugin.

= How many agents can I have? =
As many as you like. Shop managers and administrators can always answer; you can also give one role access.

= Does it work with HPOS? =
Yes, it uses the WooCommerce order API and works with High-Performance Order Storage.

= Is it accessible? =
The customer and inbox screens are built for keyboard use and screen readers, with visible focus, labelled controls and announced updates.

= I don't see the Support page in My Account. =
It is set up automatically. If it still does not show, go to Settings > Permalinks and press Save Changes once.

= What does Pro add? =
Order actions (refunds, credit, replacements, returns), automation, email piping, AI, ratings, companies, paid support and the API. See the Pro section above.

= Where do I report security bugs found in this plugin? =
Please report security bugs found in the source code of the plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/b7fe7602-463e-4f5a-bf83-ed6fa9065b00). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Screenshots ==

1. The inbox: views, ticket list, conversation and customer panel.
2. My Account > Support for the customer.
3. Opening a ticket, with help articles suggested.
4. Settings.
5. The setup wizard.

== Changelog ==

= 3.1.0 - 23/11/2026 =
* New - Knowledge base: "Write articles with AI" drafts help articles from your store (products, refund and terms pages, shipping zones, the topics customers ask about most). Drafts are never published on their own.
* Improved - Inbox: the AI button now sits next to Reply / Private note, and the reply box tools line up on one row.
* Improved - New look for every admin screen: one clean layout of cards, a top bar with the Needs reply count, a three-column inbox that switches views and tickets without reloading, and Settings in seven tabs (Basics, Emails, Team, Ticket fields, Orders and channels, AI, Advanced). Every setting keeps its value.
* New - Dashboard: Helpdesk opens on counters that lead to each inbox view, the tickets waiting longest, your own tickets, new tickets per day, the setup checklist and the AI status.
* New - Support page: the setup wizard and Settings > General can put the support form on one of your pages, or create a Support page, so the page is never empty.
* Improved - Inbox: status, priority, assignee and tags moved to a Ticket details card next to the conversation; messages use the full width; the AI tools are one AI menu; Ctrl+Enter is shown as a tip on Send reply.
* New - AI tools in the reply box: Improve my reply and Translate, next to Draft a reply and Summarize.
* New - AI chat assistant in the free version: answers live chats from your help articles and hands over to your team (off by default).
* New - Settings > AI shows what AI does, and says right away when AI is on but the site is not connected to WPFactory AI yet, with Connect.
* New - With AI on, the AI tools show in the reply box before the site is connected; a click explains how to connect.
* New - Pro features page: what Helpdesk Pro adds, with a link (information only, everything in this plugin works without Pro).
* Improved - Helpdesk is now in the WPFactory menu instead of a menu of its own.
* Fix - Clicking a section in Settings no longer scrolls the plugin header and tabs out of view.

= 3.0.0 - 22/11/2026 =
* New - AI with WPFactory AI credits, free and Pro: create a free WPFactory AI account (email and a 6-digit code) and the site is connected, then "Draft a reply with AI" and "Summarize" are paid with credits (about 1 credit per draft with the fast model; 100 free credits a month on a live site, buy more when needed). The reply box and the setup wizard point to it. The credits link shows in the Helpdesk header. Uses the shared WPFactory AI library 1.5.0.
* New - Simpler Helpdesk menu: Helpdesk opens the Inbox and shows the number of open tickets; Inbox, Saved replies, Knowledge base and Settings, in the same order as the header. Priorities, Subjects and Statuses are in Settings > Ticket fields, import and export in Settings > Tools. Old addresses still work.
* New - Guests can ask for a one-time code by email and see all their tickets.
* New - Custom fields: date, number, several choices and file (private, same rules as attachments).
* New - Public ticket numbers: prefix, sequential from a start number or random; existing tickets keep their number.
* New - Optional confirmation before sending a reply or private note.
* New - Tickets waiting for a reply in the admin bar (can be turned off).
* New - Blocked senders (addresses and domains) for forms, chat, guest codes and email piping.
* New - Optional Cloudflare Turnstile or Google reCAPTCHA on the guest form and the chat (off by default).
* New - Optional: delete a ticket's files when it closes, or from the ticket.
* New - Ticket files from older versions are hidden from the Media Library too.
* New - Edit history of replies and notes, shown to staff.
* New - Theme template overrides for every customer screen (yourtheme/woocommerce-support/).
* New - Data retention: anonymise or delete closed tickets after a number of days (off by default).
* New - Email health checks that scheduled tasks run on time.
* New - First reply and close times stored in seconds; older tickets are filled once.
* New - Read-only REST API: customers read their own tickets, staff read all.
* New - Email codes with a fallback ({customer_first_name|there}) and a plain link code for use inside links.
* New - Optional emails when the team closes a ticket or changes its status.
* Improved - The My Account ticket list has pages instead of stopping at 100 tickets.
* Improved - Settings save only the section on screen.
* Improved - The chat button picks readable text and focus colours for any theme colour.
* Improved - Faster ticket screens on large stores (index on the message tables).
* Fix - Security - A guest chat no longer sends store email to an address nobody confirmed; the human check applies to guests in the chat.
* Fix - Security - Guest tickets and guest replies are rate limited.
* Fix - Security - Password-protected help articles no longer appear in search and suggestions.
* Fix - Security - A text answer could show the file name of another ticket's attachment.
* Fix - My Account orders column uses the current WooCommerce hook (no deprecation notice).
* Dev - The "Go Pro" plugin link is plain text now (no colour); one dismissible note about Pro.
* Dev - WC tested up to: 11.3.

= 2.3.0 - Not released on its own (part of 3.0.0) =
* New - Display name: Customer Support Helpdesk & Tickets for WooCommerce. The admin menu is now Helpdesk.
* New - Redesigned admin: header with tabs, three-pane inbox with auto-saving status, priority and assignee, duplicate warning, bulk bar, reply or private note tabs, customer panel; new Priorities, Subjects, Statuses and Saved replies screens with drag ordering; settings with grouped sections and switches.
* New - Setup wizard on first activation.
* New - Redesigned My Account > Support: friendly statuses, confirmation banner, chat-style conversation, file drop zone.
* New - Knowledge base with help center, suggested articles in the ticket form and Insert article in replies.
* New - Self-hosted live chat with an Available switch for the team.
* New - Collision detection, saved views with counts, ticket tags and CSV export.
* New - Importers from SupportCandy, Fluent Support, Awesome Support and JS Help Desk.
* New - "Where is my order?" step with tracking or a status timeline.
* New - Help tab in My Account with a three step form (order, items and reason, message), confirmation, close and reopen. Blocks for the help center, the form and one ticket.
* New - Staff inbox with views, private notes, saved replies, drafts, bulk actions and keyboard shortcuts. The old ticket screens open the inbox.
* New - Tickets box on the order screen and a Tickets column on the orders list.
* New - Setup checklist and Email health check.
* Improved - Customer screens follow the theme's fonts, colours and buttons and work on phones; jQuery UI and Font Awesome are no longer loaded for customers.
* New - Tickets linked to an order, private attachments, priorities with colours, subjects with automatic assignment, assigning tickets to staff, private notes and assignee emails.
* New - Guest tickets (off by default).
* New - Saved replies.
* New - Extra fields on the ticket form (text, dropdown, checkbox).
* Improved - Attachment limits (number of files, size, types) are enforced, and staff can attach files to replies.
* Fix - Security - Shortcodes typed by a customer in a ticket no longer run.
* Fix - Security - Only store staff (Shop Manager, Administrator) can open tickets in wp-admin; Editors could read every ticket.
* Fix - Store reply emails now contain the reply.
* Fix - Replying to a notification email now reaches the right person.
* Fix - Customers with any account role now see their own tickets.
* Fix - My Account > Tickets works right after install or update, no permalink flush needed.
* Fix - The "Get Help" button and email links work with plain permalinks.
* Fix - Shop managers can save the settings.
* Fix - Store replies were labelled as the customer's own messages; line breaks and apostrophes now display correctly.
* Fix - Running the free and Pro versions together created every ticket and email twice; the free version now pauses when Pro is active.
* New - Close a ticket with your reply; a customer reply reopens a closed ticket; your reply moves an open ticket to In Progress.
* New - "Send test email" button in Settings > Notifications.
* New - Tickets are included in Tools > Export Personal Data and Erase Personal Data; suggested privacy policy text.
* New - Optional "Remove data on delete" setting (off by default).
* Improved - Plugin styles and scripts load only where they are needed.
* Dev - Settings, tickets, statuses and replies are kept on update; one-time update step runs once per version.


= 2.2.0 - 25/09/2026 =
* Fix - "Delete response" user verification fixed.
* Dev - Output escaped.
* Dev - Input sanitized.
* Dev - Nonces added.
* Dev - AJAX action names prefixed.
* Dev - JS and CSS files minified and moved to the `assets` folder.
* Dev - "Signup" notification removed.
* Dev - Deactivation survey removed.
* Dev - Admin promo footer removed.
* Dev - Pro version link updated.
* Dev - Major code refactoring.
* Dev - Coding standards improved.
* Dev - WPFactory Admin Menu - Library updated (to v1.1.2).
* Dev - WPFactory Cross-Selling - Library updated (to v1.1.6).
* Tested up to: 7.1.
* WC tested up to: 11.1.
* Requires at least: 6.2.
* Requires PHP: 7.4.

= 2.1.6 - 24/05/2026 =
* Tested up to: 7.0.
* WC tested up to: 10.7.

= 2.1.5 - 19/02/2026 =
* Fix - readme.txt - Plugin Pro version invalid link.

= 2.1.4 - 08/02/2026 =
* Dev - Code refactoring.
* WC tested up to: 10.5.

= 2.1.3 - 14/01/2026 =
* Fix - Security - "Broken Access Control" vulnerability fixed.

= 2.1.2 - 26/12/2025 =
* Fix - Security - "Missing Authorization" vulnerability fixed.
* Fix - JS response delete - Clear the table row.
* Dev - Code refactoring and cleanup.
* Dev - Coding standards improved.

= 2.1.1 - 23/12/2025 =
* WC tested up to: 10.4.
* Tested up to: 6.9.

= 2.1.0 - 29/10/2025 =
* Fix - Security - "Missing Authorization" vulnerability fixed.
* Fix - Admin ticket list - "User" column value fixed.
* Fix - Admin meta boxes - Ticket Info - "User" row value fixed.
* Fix - Save fields - "User" DB column value fixed.
* Fix - Notify user on WP edit - "User" value fixed.
* Fix - Text typo fixed.
* Dev - Code refactoring and cleanup.
* Dev - Coding standards improved.
* WC tested up to: 10.3.

= 2.0.3 - 13/10/2025 =
* WPFactory Admin Menu - Library updated (to v1.0.8).
* WPFactory Recommendations - Library updated (to v1.0.6).
* WC tested up to: 10.2.

= 2.0.2 - 10/06/2025 =
* Fix - Fix plugin icon.

= 2.0.1 - 06/06/2025 =
* Dev - Updated plugin icon and banner.

= 2.0.0 - 20/05/2025 =
* Fix - Translations - Text domain fixed.
* Dev - Plugin settings copied to the "WPFactory" menu.
* Dev - "Recommendations" added.
* Dev - Code refactoring and cleanup.
* WC tested up to: 9.8.
* Tested up to: 6.8.

= 1.5 =
* Fix query bug on Tickets section in my account page.

= 1.4 =
* Fix query bug on Tickets section in my account page.
* Fix who sent the ticket response when from admin in tickets edit screen.

= 1.3 =
* Fix CSS issues.

= 1.2 =
* Check woo HPOS compatibility.

= 1.1 =
* Check compatibility and fix localization.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 3.0.0 =
A large update: new admin and customer screens, knowledge base, live chat, guest codes, ticket numbers and more. Settings, tickets and replies are kept. Back up your site before updating, as for any major update.

= 1.5 =
Fix query bug on Tickets section in my account page.
