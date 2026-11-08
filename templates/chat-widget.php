<?php
/**
 * Live chat button and window (filled by the chat script). Keep the data-stsw-chat attributes: the script uses them.
 *
 * Override: copy to yourtheme/woocommerce-support/chat-widget.php.
 *
 * @package WPFactory\WC_Support_Ticket_System
 * @version 3.0.0
 *
 * @var string $greeting First message in the window.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="stsw-chat" data-stsw-chat>
	<button type="button" class="stsw-chat__launcher" aria-expanded="false" aria-controls="stsw-chat-panel">
		<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
		<span><?php esc_html_e( 'Chat with us', 'support-ticket-system-for-woocommerce' ); ?></span>
	</button>
	<section class="stsw-chat__panel" id="stsw-chat-panel" role="dialog" aria-modal="false" aria-labelledby="stsw-chat-title" hidden>
		<header class="stsw-chat__head">
			<h2 class="stsw-chat__title" id="stsw-chat-title"><?php esc_html_e( 'Chat with us', 'support-ticket-system-for-woocommerce' ); ?></h2>
			<p class="stsw-chat__state" data-stsw-chat-state></p>
			<button type="button" class="stsw-chat__close" data-stsw-chat-close aria-label="<?php esc_attr_e( 'Close the chat window', 'support-ticket-system-for-woocommerce' ); ?>">&times;</button>
		</header>
		<ol class="stsw-chat__log" data-stsw-chat-log aria-live="polite" aria-label="<?php esc_attr_e( 'Messages', 'support-ticket-system-for-woocommerce' ); ?>">
			<li class="stsw-chat__msg stsw-chat__msg--store"><?php echo esc_html( $greeting ); ?></li>
		</ol>
		<form class="stsw-chat__start" data-stsw-chat-start novalidate>
			<p class="stsw-chat__offline" data-stsw-chat-offline hidden><?php esc_html_e( 'We are not online right now. Leave a message and we will reply by email.', 'support-ticket-system-for-woocommerce' ); ?></p>
			<label for="stsw-chat-name"><?php esc_html_e( 'Your name', 'support-ticket-system-for-woocommerce' ); ?></label>
			<input type="text" id="stsw-chat-name" name="name" autocomplete="name" required />
			<label for="stsw-chat-email"><?php esc_html_e( 'Email', 'support-ticket-system-for-woocommerce' ); ?></label>
			<input type="email" id="stsw-chat-email" name="email" autocomplete="email" required />
			<label for="stsw-chat-first"><?php esc_html_e( 'Message', 'support-ticket-system-for-woocommerce' ); ?></label>
			<textarea id="stsw-chat-first" name="message" rows="3" required></textarea>
			<div class="stsw-chat__hp" aria-hidden="true"><label for="stsw-chat-website">Website</label><input type="text" id="stsw-chat-website" name="website" tabindex="-1" autocomplete="off" /></div>
			<?php
			/**
			 * Inside the chat form, before the button (human check when nobody is online).
			 */
			do_action( 'wpfactory_wc_sts_chat_form_fields' );
			?>
			<p class="stsw-chat__error" role="alert" data-stsw-chat-error></p>
			<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Start chat', 'support-ticket-system-for-woocommerce' ); ?></button>
		</form>
		<form class="stsw-chat__send" data-stsw-chat-send hidden>
			<label for="stsw-chat-message" class="screen-reader-text"><?php esc_html_e( 'Your message', 'support-ticket-system-for-woocommerce' ); ?></label>
			<textarea id="stsw-chat-message" name="message" rows="2" placeholder="<?php esc_attr_e( 'Write a message', 'support-ticket-system-for-woocommerce' ); ?>" required></textarea>
			<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Send', 'support-ticket-system-for-woocommerce' ); ?></button>
		</form>
	</section>
</div>
