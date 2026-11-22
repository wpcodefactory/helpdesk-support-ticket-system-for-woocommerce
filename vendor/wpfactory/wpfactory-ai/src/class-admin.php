<?php
/**
 * WPFactory > AI Credits (Settings > AI Credits when no WPFactory plugin made that menu): connect the site (email, then a 6-digit code), see credits and usage per plugin,
 * buy credits, disconnect. Shown once at least one plugin registered a product.
 *
 * @package WPFactory\AI
 */

namespace WPFactory\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen and the credits pill style.
 */
final class Admin {

	const SLUG = 'wpfactory-ai';

	/**
	 * The WPFactory top-level menu (wpfactory/wpfactory-admin-menu).
	 */
	const WPFACTORY_MENU = 'wpfactory';

	/**
	 * The screen's page hook, once added.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * The screen's parent menu, once added.
	 *
	 * @var string
	 */
	private static $parent = '';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_old_address' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * The screen's address. admin.php?page= opens it under either parent menu.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Adds the screen: under the WPFactory menu when a WPFactory plugin made it, else under Settings.
	 */
	public static function menu() {
		global $admin_page_hooks;
		if ( ! wpfactory_ai()->products() ) {
			return;
		}
		$parent = empty( $admin_page_hooks[ self::WPFACTORY_MENU ] ) ? 'options-general.php' : self::WPFACTORY_MENU;

		self::$parent = (string) apply_filters( 'wpfactory_ai_menu_parent', $parent );

		$hook = add_submenu_page(
			self::$parent,
			__( 'AI Credits', 'wpfactory-ai' ),
			__( 'AI Credits', 'wpfactory-ai' ),
			(string) apply_filters( 'wpfactory_ai_capability', 'manage_options' ),
			self::SLUG,
			array( __CLASS__, 'render' )
		);

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Sends the old Settings address (options-general.php?page=wpfactory-ai: bookmarks, older links) to the screen
	 * when it lives under another menu. WordPress refuses that address before admin_init, so this runs on
	 * admin_page_access_denied, just before its "not allowed" message. Users who cannot open the screen still get that message.
	 */
	public static function redirect_old_address() {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a redirect to the same screen; nothing is saved.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'options-general.php' !== $pagenow || self::SLUG !== $page || '' === self::$hook || 'options-general.php' === self::$parent ) {
			return;
		}
		$args = array();
		foreach ( wp_unslash( $_GET ) as $key => $value ) {
			if ( is_scalar( $value ) ) {
				$args[ sanitize_key( $key ) ] = rawurlencode( sanitize_text_field( (string) $value ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Styles and the connect script, on the screen only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		self::enqueue_pill_style();
		wp_register_script( 'wpfactory-ai-admin', false, array( 'wp-api-fetch' ), Client::VERSION, true );
		wp_enqueue_script( 'wpfactory-ai-admin' );
		wp_add_inline_script(
			'wpfactory-ai-admin',
			'window.wpfactoryAi = ' . wp_json_encode(
				array(
					/* translators: %s: email address */
					'sent'       => __( 'We sent a 6-digit code to %s. It works for 10 minutes.', 'wpfactory-ai' ),
					'error'      => __( 'Something went wrong. Please try again.', 'wpfactory-ai' ),
					'disconnect' => __( 'Disconnect this site from WPFactory AI? AI features that use credits stop until you connect again.', 'wpfactory-ai' ),
				)
			) . ';' . self::script(),
			'after'
		);
	}

	/**
	 * The small credits pill style, on any admin page that shows a pill.
	 */
	public static function enqueue_pill_style() {
		if ( wp_style_is( 'wpfactory-ai', 'enqueued' ) ) {
			return;
		}
		wp_register_style( 'wpfactory-ai', false, array(), Client::VERSION );
		wp_enqueue_style( 'wpfactory-ai' );
		wp_add_inline_style(
			'wpfactory-ai',
			'.wpfactory-ai-pill{display:inline-block;padding:2px 10px;border-radius:999px;background:#f0f6fc;color:#0a4b78;border:1px solid #c5d9ed;font-size:12px;line-height:20px;text-decoration:none;white-space:nowrap}'
			. '.wpfactory-ai-pill:hover,.wpfactory-ai-pill:focus{background:#e5f0fa;color:#0a4b78}'
			. '.wpfactory-ai .card{max-width:640px}.wpfactory-ai .wpfactory-ai-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}'
			. '.wpfactory-ai .wpfactory-ai-code{width:9em;font-size:18px;letter-spacing:4px;text-align:center}'
			. '.wpfactory-ai .wpfactory-ai-big{font-size:24px;font-weight:600}.wpfactory-ai ul.wpfactory-ai-list{margin:0}'
		);
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! Rest::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage WPFactory AI.', 'wpfactory-ai' ) );
		}
		$client = wpfactory_ai();
		$info   = $client->connection();
		echo '<div class="wrap wpfactory-ai">';
		echo '<h1>' . esc_html__( 'AI Credits', 'wpfactory-ai' ) . '</h1>';
		echo '<div class="wpfactory-ai-message notice inline" hidden><p></p></div>';
		// Back from the Stripe page: only changes which note is shown.
		$back = isset( $_GET['wpfactory_ai_checkout'] ) ? sanitize_key( wp_unslash( $_GET['wpfactory_ai_checkout'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action.
		if ( 'done' === $back ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Thank you! Your credits are added within a few seconds. Reload this page if you do not see them yet.', 'wpfactory-ai' ) . '</p></div>';
		} elseif ( 'cancelled' === $back ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'The payment was cancelled. Nothing was charged.', 'wpfactory-ai' ) . '</p></div>';
		}
		if ( $info['connected'] ) {
			self::render_connected( $client, $info );
		} else {
			self::render_connect( $info );
		}
		echo '</div>';
	}

	/**
	 * Connected: credits, usage, buy, disconnect.
	 *
	 * @param Client              $client Client.
	 * @param array<string,mixed> $info   Connection.
	 */
	private static function render_connected( $client, array $info ) {
		$products = $client->products();
		echo '<div class="card">';
		/* translators: %s: email address */
		echo '<p>' . wp_kses( sprintf( __( 'This site is connected to WPFactory AI as %s.', 'wpfactory-ai' ), '<strong>' . esc_html( (string) $info['email'] ) . '</strong>' ), array( 'strong' => array() ) ) . '</p>';
		// One page for the whole account (every site and plugin), signed in with the same email.
		$account_url = $client->endpoint() . '/account';

		echo '<p><a href="' . esc_url( $account_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'See all your sites, usage and purchases', 'wpfactory-ai' ) . '</a> <span class="description">';
		/* translators: %s: web address of the WPFactory AI account page, like ai.wpfactory.com */
		echo esc_html( sprintf( __( '(%s, opens in a new tab)', 'wpfactory-ai' ), (string) wp_parse_url( $account_url, PHP_URL_HOST ) ) ) . '</span></p>';

		// The big number is what this site can spend: paid credits plus each plugin's free credits.
		$paid  = null;
		$free  = 0;
		$lines = array();
		foreach ( $products as $id => $product ) {
			$b = $client->balance( $id, true );
			if ( is_wp_error( $b ) ) {
				continue;
			}
			$paid = (int) $b['paid'];
			if ( (int) $b['free'] > 0 ) {
				$free += (int) $b['free'];
				/* translators: 1: number of credits, 2: plugin name, 3: date */
				$lines[] = sprintf( __( '%1$s free credits for %2$s, until %3$s', 'wpfactory-ai' ), number_format_i18n( (int) $b['free'] ), $product['name'], date_i18n( get_option( 'date_format' ), (int) strtotime( (string) $b['free_expires_at'] ) - 1 ) );
			}
		}
		if ( null !== $paid ) {
			$total = $paid + $free;
			/* translators: %s: number of credits */
			echo '<p class="wpfactory-ai-big">' . esc_html( sprintf( _n( '%s credit', '%s credits', $total, 'wpfactory-ai' ), number_format_i18n( $total ) ) ) . '</p>';
			/* translators: %s: number of credits */
			$lines[] = sprintf( _n( '%s paid credit, for every WPFactory plugin on every site of this account. Paid credits never expire.', '%s paid credits, for every WPFactory plugin on every site of this account. Paid credits never expire.', $paid, 'wpfactory-ai' ), number_format_i18n( $paid ) );
		}
		if ( $lines ) {
			echo '<ul class="wpfactory-ai-list">';
			foreach ( $lines as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
			echo '<p class="description">' . esc_html__( '1 credit = 1 US cent. Free credits are used first.', 'wpfactory-ai' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="card" id="wpfactory-ai-buy"><h2>' . esc_html__( 'Buy credits', 'wpfactory-ai' ) . '</h2>';
		$packs = $client->packs();
		if ( is_wp_error( $packs ) || empty( $packs['open'] ) ) {
			echo '<p>' . esc_html__( 'Buying credits opens soon.', 'wpfactory-ai' ) . '</p>';
		} else {
			echo '<p class="wpfactory-ai-row">';
			foreach ( $packs['packs'] as $pack ) {
				/* translators: 1: price in US dollars, 2: number of credits */
				$label = sprintf( __( '$%1$s for %2$s credits', 'wpfactory-ai' ), number_format_i18n( (int) $pack['usd'] ), number_format_i18n( (int) $pack['credits'] ) );
				echo '<button type="button" class="button" data-wpfactory-ai-pack="' . esc_attr( (string) (int) $pack['usd'] ) . '">' . esc_html( $label ) . '</button>';
			}
			echo '</p>';
			echo '<p class="description">' . esc_html__( 'Secure payment by Stripe. Paid credits never expire. Purchases are final.', 'wpfactory-ai' ) . '</p>';
		}
		echo '</div>';

		$usage = $client->usage();
		echo '<div class="card"><h2>' . esc_html__( 'This month', 'wpfactory-ai' ) . '</h2>';
		if ( is_wp_error( $usage ) || empty( $usage['by_product'] ) ) {
			echo '<p>' . esc_html__( 'No AI use yet this month.', 'wpfactory-ai' ) . '</p>';
		} else {
			echo '<ul class="wpfactory-ai-list">';
			foreach ( (array) $usage['by_product'] as $row ) {
				$name = isset( $products[ $row['product'] ] ) ? $products[ $row['product'] ]['name'] : (string) $row['product'];
				/* translators: %s: number of AI requests */
				$calls = sprintf( _n( '%s request', '%s requests', (int) $row['calls'], 'wpfactory-ai' ), number_format_i18n( (int) $row['calls'] ) );
				/* translators: %s: number of credits */
				$spent = sprintf( _n( '%s credit', '%s credits', (int) $row['credits'], 'wpfactory-ai' ), number_format_i18n( (int) $row['credits'] ) );
				/* translators: 1: plugin name, 2: how many requests, 3: how many credits */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$s, %3$s', 'wpfactory-ai' ), $name, $calls, $spent ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<p><button type="button" class="button-link button-link-delete" data-wpfactory-ai="disconnect">' . esc_html__( 'Disconnect this site', 'wpfactory-ai' ) . '</button></p>';
	}

	/**
	 * Not connected: email, then the code.
	 *
	 * @param array<string,mixed> $info Connection.
	 */
	private static function render_connect( array $info ) {
		echo '<div class="card">';
		if ( ! empty( $info['address_changed'] ) ) {
			echo '<p><strong>' . esc_html__( 'This site\'s address changed since it was connected. Connect it again to use AI credits here.', 'wpfactory-ai' ) . '</strong></p>';
		} elseif ( ! empty( $info['needs_reconnect'] ) ) {
			echo '<p><strong>' . esc_html__( 'The connection to WPFactory AI ended. Connect again to keep using AI credits.', 'wpfactory-ai' ) . '</strong></p>';
		}
		echo '<p>' . esc_html__( 'Use AI in your WPFactory plugins with credits, without an AI account or key of your own. Free and Pro plugins alike. 1 credit = 1 US cent, and every account gets free credits each month.', 'wpfactory-ai' ) . '</p>';
		$email = '' !== (string) $info['pending_email'] ? (string) $info['pending_email'] : (string) $info['email'];
		if ( '' === $email ) {
			$user  = wp_get_current_user();
			$email = (string) $user->user_email;
		}
		echo '<form data-wpfactory-ai="email" class="wpfactory-ai-row">';
		echo '<label class="screen-reader-text" for="wpfactory-ai-email">' . esc_html__( 'Email', 'wpfactory-ai' ) . '</label>';
		echo '<input type="email" id="wpfactory-ai-email" name="email" class="regular-text" required value="' . esc_attr( $email ) . '">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Email me a code', 'wpfactory-ai' ) . '</button>';
		echo '</form>';
		echo '<form data-wpfactory-ai="code" class="wpfactory-ai-row" hidden>';
		echo '<input type="hidden" name="email" value="">';
		echo '<label class="screen-reader-text" for="wpfactory-ai-code">' . esc_html__( '6-digit code', 'wpfactory-ai' ) . '</label>';
		echo '<input type="text" id="wpfactory-ai-code" name="code" class="wpfactory-ai-code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="000000">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Connect', 'wpfactory-ai' ) . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Nothing is sent to WPFactory until you connect. After that, only what a plugin asks the AI is sent, and WPFactory AI keeps numbers only (tokens, credits, time), never your content.', 'wpfactory-ai' ) . '</p>';
		$terms   = (string) apply_filters( 'wpfactory_ai_terms_url', 'https://wpfactory.com/terms-conditions/#wpfactory-ai' );
		$privacy = (string) apply_filters( 'wpfactory_ai_privacy_url', 'https://wpfactory.com/privacy-policy/#wpfactory-ai' );
		if ( '' !== $terms || '' !== $privacy ) {
			echo '<p class="description">';
			if ( '' !== $terms ) {
				echo '<a href="' . esc_url( $terms ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms', 'wpfactory-ai' ) . '</a> ';
			}
			if ( '' !== $privacy ) {
				echo '<a href="' . esc_url( $privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy', 'wpfactory-ai' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * The connect script (vanilla JavaScript, no build step).
	 *
	 * @return string
	 */
	private static function script() {
		return <<<'JS'
(function () {
	var root = document.querySelector('.wpfactory-ai');
	if (!root || !window.wp || !wp.apiFetch) { return; }
	var box = root.querySelector('.wpfactory-ai-message');
	function say(text, bad) {
		box.querySelector('p').textContent = text;
		box.className = 'wpfactory-ai-message notice inline ' + (bad ? 'notice-error' : 'notice-success');
		box.hidden = false;
	}
	function post(path, data) {
		return wp.apiFetch({ path: '/wpfactory-ai/v1/' + path, method: 'POST', data: data || {} });
	}
	root.addEventListener('submit', function (e) {
		var form = e.target.closest('form[data-wpfactory-ai]');
		if (!form) { return; }
		e.preventDefault();
		var button = form.querySelector('button');
		button.disabled = true;
		var step = form.getAttribute('data-wpfactory-ai');
		var done = step === 'email'
			? post('connect/start', { email: form.email.value.trim() }).then(function () {
				var code = root.querySelector('form[data-wpfactory-ai="code"]');
				code.email.value = form.email.value.trim();
				code.hidden = false;
				code.code.focus();
				say(window.wpfactoryAi.sent.replace('%s', form.email.value.trim()));
			})
			: post('connect/verify', { email: form.email.value, code: form.code.value }).then(function () {
				window.location.reload();
			});
		done.catch(function (err) { say((err && err.message) || window.wpfactoryAi.error, true); })
			.then(function () { button.disabled = false; });
	});
	root.addEventListener('click', function (e) {
		var pack = e.target.closest('[data-wpfactory-ai-pack]');
		if (!pack) { return; }
		root.querySelectorAll('[data-wpfactory-ai-pack]').forEach(function (b) { b.disabled = true; });
		post('checkout', { pack: parseInt(pack.getAttribute('data-wpfactory-ai-pack'), 10) })
			.then(function (res) { window.location.href = res.url; })
			.catch(function (err) {
				say((err && err.message) || window.wpfactoryAi.error, true);
				root.querySelectorAll('[data-wpfactory-ai-pack]').forEach(function (b) { b.disabled = false; });
			});
	});
	root.addEventListener('click', function (e) {
		var button = e.target.closest('[data-wpfactory-ai="disconnect"]');
		if (!button || !window.confirm(window.wpfactoryAi.disconnect)) { return; }
		button.disabled = true;
		post('disconnect').then(function () { window.location.reload(); })
			.catch(function (err) { say((err && err.message) || window.wpfactoryAi.error, true); button.disabled = false; });
	});
})();
JS;
	}
}
