<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: dashboard.
 *
 * The first screen of Helpdesk: counts that open the matching inbox view, the tickets waiting longest, the team
 * member's own tickets, tickets per day, the setup checklist and the AI status.
 *
 * @version 3.1.0
 * @since   3.1.0
 *
 * @author  WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_Dashboard' ) ) :

	/**
	 * WPFactory_WC_STS_Dashboard class.
	 */
	class WPFactory_WC_STS_Dashboard {

		/**
		 * Days in the chart.
		 */
		const DAYS = 14;

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_Dashboard|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_Dashboard
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Core instance.
		 *
		 * @return WPFactory_WC_STS_Inc
		 */
		protected function core() {
			return WPFactory_WC_STS_Inc::get_main_instance();
		}

		/**
		 * Inbox.
		 *
		 * @return WPFactory_WC_STS_Inbox
		 */
		protected function inbox() {
			return WPFactory_WC_STS_Inbox::get_instance();
		}

		/**
		 * Whether the request is the dashboard: the Helpdesk page with no other address part, or tab=dashboard.
		 *
		 * @return bool
		 */
		public static function is_request() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( WPFACTORY_WC_STS_PAGE !== $page ) {
				return false;
			}
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			return 'dashboard' === $tab || ( '' === $tab && 1 === count( $_GET ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Tickets opened per day (site time zone), oldest first.
		 *
		 * @return array Y-m-d => count
		 */
		public function per_day() {
			global $wpdb;
			$days  = array();
			$today = current_datetime()->setTime( 0, 0 );
			for ( $i = self::DAYS - 1; $i >= 0; $i-- ) {
				$days[ $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' ) ] = 0;
			}
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT DATE(post_date) AS d, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'stsw_tickets' AND post_status = 'publish' AND post_date >= %s GROUP BY DATE(post_date)",
					array_key_first( $days ) . ' 00:00:00'
				)
			);
			foreach ( (array) $rows as $row ) {
				if ( isset( $days[ $row->d ] ) ) {
					$days[ $row->d ] = (int) $row->n;
				}
			}
			return $days;
		}

		/**
		 * Counter cards: each opens its inbox view.
		 *
		 * @return array key => { label, count, url, icon, help }
		 */
		public function stats() {
			$inbox = $this->inbox();
			$views = $inbox->get_views();
			$cards = array(
				'needs_reply' => array( 'inbox', __( 'Waiting for your team', 'support-ticket-system-for-woocommerce' ) ),
				'unassigned'  => array( 'userx', __( 'Open tickets nobody owns', 'support-ticket-system-for-woocommerce' ) ),
				'mine'        => array( 'user', __( 'Open tickets assigned to you', 'support-ticket-system-for-woocommerce' ) ),
				'waiting'     => array( 'clock', __( 'Answered, waiting for the customer', 'support-ticket-system-for-woocommerce' ) ),
				'overdue'     => array( 'warn', __( 'Past the reply target', 'support-ticket-system-for-woocommerce' ) ),
				'closed'      => array( 'checkc', __( 'Closed tickets', 'support-ticket-system-for-woocommerce' ) ),
			);
			$out   = array();
			foreach ( $cards as $key => $card ) {
				if ( ! isset( $views[ $key ] ) ) {
					continue; // "Overdue" comes with Pro's reply targets.
				}
				$out[ $key ] = array(
					'label' => $views[ $key ]['label'],
					'count' => $inbox->count( $key ),
					'url'   => $inbox->url( array( 'view' => $key ) ),
					'icon'  => $card[0],
					'help'  => $card[1],
				);
			}
			return $out;
		}

		/**
		 * A few tickets of a view, as rows.
		 *
		 * @param string $view  View key.
		 * @param int    $count Most rows.
		 */
		protected function render_list( $view, $count ) {
			$query = $this->inbox()->query(
				$view,
				array(
					'posts_per_page' => $count,
					'no_found_rows'  => true,
				)
			);
			if ( ! $query->have_posts() ) {
				echo '<p class="hd-dash__empty">' . esc_html( 'needs_reply' === $view ? __( 'Nothing is waiting. Nice work.', 'support-ticket-system-for-woocommerce' ) : __( 'No open tickets assigned to you.', 'support-ticket-system-for-woocommerce' ) ) . '</p>';
				return;
			}
			echo '<ul class="hd-dash__list">';
			foreach ( $query->posts as $post ) {
				$id       = (int) $post->ID;
				$contact  = $this->core()->get_ticket_contact( $id );
				$activity = $this->core()->get_activity( $id );
				$terms    = get_the_terms( $id, 'stsw_tickets_priority' );
				$waited   = $activity['at'] ? human_time_diff( strtotime( $activity['at'] . ' UTC' ) ) : '';
				echo '<li><a class="hd-dash__row" href="' . esc_url(
					$this->inbox()->url(
						array(
							'view'   => $view,
							'ticket' => $id,
						)
					)
				) . '">';
				echo '<span class="hd-dash__who">' . esc_html( $contact['display_name'] ) . '</span>';
				echo '<span class="hd-dash__title">#' . esc_html( $this->core()->get_ticket_number( $id ) ) . ' ' . esc_html( get_the_title( $id ) ) . '</span>';
				echo '<span class="hd-dash__meta">' . ( is_array( $terms ) && $terms ? WPFactory_WC_STS_UI::priority_chip( $terms[0] ) . ' ' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
				/* translators: %s: time, e.g. "3 hours". */
				echo '<span class="hd-muted">' . esc_html( '' !== $waited ? sprintf( __( '%s ago', 'support-ticket-system-for-woocommerce' ), $waited ) : '' ) . '</span></span>';
				echo '</a></li>';
			}
			echo '</ul>';
		}

		/**
		 * Greeting for the time of day.
		 *
		 * @return string
		 */
		protected function greeting() {
			$hour = (int) current_datetime()->format( 'G' );
			$user = wp_get_current_user();
			$name = $user->first_name ? $user->first_name : $user->display_name;
			if ( $hour < 12 ) {
				/* translators: %s: first name. */
				return sprintf( __( 'Good morning, %s', 'support-ticket-system-for-woocommerce' ), $name );
			}
			if ( $hour < 18 ) {
				/* translators: %s: first name. */
				return sprintf( __( 'Good afternoon, %s', 'support-ticket-system-for-woocommerce' ), $name );
			}
			/* translators: %s: first name. */
			return sprintf( __( 'Good evening, %s', 'support-ticket-system-for-woocommerce' ), $name );
		}

		/**
		 * The AI card: what it does and its state.
		 */
		protected function render_ai() {
			$credits = WPFactory_WC_STS_Credits::get_instance();
			if ( ! $credits->library() || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			$on = $credits->tools_on();
			echo '<section class="hd-dash__card hd-dash__ai" data-stsw-dash-ai aria-labelledby="hd-dash-ai"><h2 class="hd-h2" id="hd-dash-ai">' . wp_kses( WPFactory_WC_STS_UI::icon( 'sparkle', 16 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' . esc_html__( 'AI for your team', 'support-ticket-system-for-woocommerce' ) . '</h2>';
			if ( ! $on ) {
				/* translators: %d: number of free credits. */
				echo '<p class="hd-muted">' . esc_html( sprintf( __( 'Draft replies, improve your wording, summarize long tickets, translate messages and answer live chats from your help articles. A free WPFactory AI account gets %d credits a month.', 'support-ticket-system-for-woocommerce' ), $credits->free_monthly() ) ) . '</p>';
				echo '<p><a class="hd-btn hd-btn--primary" href="' . esc_url( $credits->settings_url() ) . '">' . esc_html__( 'Set up AI', 'support-ticket-system-for-woocommerce' ) . '</a></p>';
			} elseif ( ! $credits->handled_elsewhere() && ! $credits->is_connected() ) {
				echo '<p class="hd-notice hd-notice--warning"><span>' . esc_html__( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ) . '</span> <a href="' . esc_url( $credits->screen_url() ) . '">' . esc_html__( 'Connect', 'support-ticket-system-for-woocommerce' ) . '</a></p>';
			} else {
				$label = $credits->is_connected() ? $credits->credits_label( $credits->balance() ) : '';
				echo '<p class="hd-muted">' . esc_html__( 'On. Draft, Improve my reply, Summarize and Translate are in every reply box.', 'support-ticket-system-for-woocommerce' ) . '</p>';
				if ( '' !== $label ) {
					/* translators: %s: credits, e.g. "96 credits". */
					echo '<p><strong>' . esc_html( sprintf( __( '%s left', 'support-ticket-system-for-woocommerce' ), $label ) ) . '</strong> <a href="' . esc_url( $credits->screen_url() ) . '">' . esc_html__( 'Usage and account', 'support-ticket-system-for-woocommerce' ) . '</a></p>';
				}
			}
			echo '</section>';
		}

		/**
		 * The chart: tickets opened per day.
		 */
		protected function render_chart() {
			$days  = $this->per_day();
			$max   = max( 1, max( $days ) );
			$total = array_sum( $days );
			echo '<section class="hd-dash__card" aria-labelledby="hd-dash-chart"><h2 class="hd-h2" id="hd-dash-chart">' . esc_html__( 'New tickets, last 14 days', 'support-ticket-system-for-woocommerce' ) . '</h2>';
			/* translators: %s: number of tickets. */
			echo '<p class="hd-muted">' . esc_html( sprintf( _n( '%s ticket', '%s tickets', $total, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $total ) ) ) . '</p>';
			echo '<ol class="hd-dash__bars" data-stsw-dash-chart>';
			foreach ( $days as $day => $count ) {
				$label = date_i18n( get_option( 'date_format' ), strtotime( $day ) );
				/* translators: 1: date, 2: number of tickets. */
				$title = sprintf( _n( '%1$s: %2$s ticket', '%1$s: %2$s tickets', $count, 'support-ticket-system-for-woocommerce' ), $label, number_format_i18n( $count ) );
				echo '<li title="' . esc_attr( $title ) . '"><span class="hd-dash__bar" style="height:' . esc_attr( (string) max( 2, round( 100 * $count / $max ) ) ) . '%"></span><span class="screen-reader-text">' . esc_html( $title ) . '</span></li>';
			}
			echo '</ol><div class="hd-dash__axis"><span>' . esc_html( date_i18n( 'M j', strtotime( array_key_first( $days ) ) ) ) . '</span><span>' . esc_html__( 'Today', 'support-ticket-system-for-woocommerce' ) . '</span></div></section>';
		}

		/**
		 * The dashboard.
		 */
		public function render() {
			$core = $this->core();
			if ( ! $core->can_manage_tickets() ) {
				echo '<div class="hd-page">';
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to see tickets.', 'support-ticket-system-for-woocommerce' ), true );
				echo '</div>';
				return;
			}
			$actions = '<a class="hd-btn hd-btn--primary" href="' . esc_url( $this->inbox()->url( array( 'view' => 'needs_reply' ) ) ) . '">' . esc_html__( 'Open inbox', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="hd-page hd-dash" data-stsw-dashboard>';
			WPFactory_WC_STS_UI::page_head( $this->greeting(), __( 'Here is your support desk today.', 'support-ticket-system-for-woocommerce' ), $actions );
			$this->inbox()->render_checklist();
			echo '<ul class="hd-dash__stats">';
			foreach ( $this->stats() as $key => $stat ) {
				echo '<li><a class="hd-dash__stat' . ( 'needs_reply' === $key && $stat['count'] > 0 ? ' is-hot' : '' ) . '" href="' . esc_url( $stat['url'] ) . '" data-view="' . esc_attr( $key ) . '"><span class="hd-dash__icon">' . wp_kses( WPFactory_WC_STS_UI::icon( $stat['icon'], 18 ), WPFactory_WC_STS_UI::svg_kses() ) . '</span><span class="hd-dash__label">' . esc_html( $stat['label'] ) . '</span><span class="hd-dash__num">' . esc_html( number_format_i18n( $stat['count'] ) ) . '</span><span class="hd-dash__help">' . esc_html( $stat['help'] ) . '</span></a></li>';
			}
			echo '</ul>';
			echo '<div class="hd-dash__grid"><div class="hd-dash__col">';
			echo '<section class="hd-dash__card" aria-labelledby="hd-dash-wait"><div class="hd-dash__cardhead"><h2 class="hd-h2" id="hd-dash-wait">' . esc_html__( 'Waiting longest', 'support-ticket-system-for-woocommerce' ) . '</h2><a href="' . esc_url( $this->inbox()->url( array( 'view' => 'needs_reply' ) ) ) . '">' . esc_html__( 'See all', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			$this->render_list( 'needs_reply', 5 );
			echo '</section>';
			echo '<section class="hd-dash__card" aria-labelledby="hd-dash-mine"><div class="hd-dash__cardhead"><h2 class="hd-h2" id="hd-dash-mine">' . esc_html__( 'Assigned to you', 'support-ticket-system-for-woocommerce' ) . '</h2><a href="' . esc_url( $this->inbox()->url( array( 'view' => 'mine' ) ) ) . '">' . esc_html__( 'See all', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			$this->render_list( 'mine', 5 );
			echo '</section></div><div class="hd-dash__col">';
			$this->render_chart();
			$this->render_ai();
			/**
			 * More dashboard cards (right column).
			 */
			do_action( 'wpfactory_wc_sts_dashboard_cards' );
			echo '</div></div></div>';
		}
	}

endif;

return WPFactory_WC_STS_Dashboard::get_instance();
