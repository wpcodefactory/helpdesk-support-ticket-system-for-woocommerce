<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: dashboard.
 *
 * The first screen of Helpdesk: how many tickets need a reply, the queue (each view opens in the inbox), the tickets
 * waiting longest, new tickets per day, the setup checklist and the AI status.
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
		 * A duration, short: "45m", "2h 10m", "1d 4h".
		 *
		 * @param int $seconds Seconds.
		 *
		 * @return string
		 */
		public static function duration( $seconds ) {
			$seconds = max( 0, (int) $seconds );
			$days    = intdiv( $seconds, DAY_IN_SECONDS );
			$hours   = intdiv( $seconds % DAY_IN_SECONDS, HOUR_IN_SECONDS );
			$minutes = intdiv( $seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
			if ( $days ) {
				/* translators: 1: days, 2: hours. Short units. */
				return $hours ? sprintf( __( '%1$dd %2$dh', 'support-ticket-system-for-woocommerce' ), $days, $hours ) : sprintf( /* translators: %d: days. */ __( '%dd', 'support-ticket-system-for-woocommerce' ), $days );
			}
			if ( $hours ) {
				/* translators: 1: hours, 2: minutes. Short units. */
				return $minutes ? sprintf( __( '%1$dh %2$dm', 'support-ticket-system-for-woocommerce' ), $hours, $minutes ) : sprintf( /* translators: %d: hours. */ __( '%dh', 'support-ticket-system-for-woocommerce' ), $hours );
			}
			/* translators: %d: minutes. Short unit. */
			return $minutes ? sprintf( __( '%dm', 'support-ticket-system-for-woocommerce' ), $minutes ) : __( 'Under 1m', 'support-ticket-system-for-woocommerce' );
		}

		/**
		 * Median of numbers (the lower middle value for an even count), or null without numbers.
		 *
		 * @param int[] $values Numbers.
		 *
		 * @return int|null
		 */
		public static function median( $values ) {
			$values = array_values( array_map( 'intval', (array) $values ) );
			if ( ! $values ) {
				return null;
			}
			sort( $values );
			return $values[ (int) floor( ( count( $values ) - 1 ) / 2 ) ];
		}

		/**
		 * Seconds to the first team reply of each ticket that has one (tickets the team started do not count).
		 *
		 * @param int[] $ids Ticket IDs.
		 *
		 * @return int[]
		 */
		public static function first_reply_seconds( $ids ) {
			$out = array();
			foreach ( $ids as $id ) {
				// Stored seconds (3.0+, older tickets filled once); worked out from the first reply date otherwise.
				$seconds = (string) get_post_meta( $id, WPFactory_WC_STS_Lifecycle::META_FIRST_SECONDS, true );
				$reply   = (string) get_post_meta( $id, WPFactory_WC_STS_Inc::META_FIRST_REPLY, true );
				if ( '' !== $seconds ) {
					$out[] = (int) $seconds;
				} elseif ( '' !== $reply && ! get_post_meta( $id, WPFactory_WC_STS_Inc::META_STARTED_BY, true ) ) {
					$out[] = max( 0, strtotime( $reply . ' UTC' ) - strtotime( get_post_field( 'post_date_gmt', $id ) . ' UTC' ) );
				}
			}
			return $out;
		}

		/**
		 * Median first reply of the tickets opened in the last 7 days, or null when none was answered.
		 *
		 * @return int|null Seconds.
		 */
		public function median_first_reply_week() {
			$ids = get_posts(
				array(
					'post_type'      => 'stsw_tickets',
					'post_status'    => 'publish',
					'posts_per_page' => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one week of tickets, IDs only.
					'fields'         => 'ids',
					'date_query'     => array(
						array(
							'column' => 'post_date_gmt',
							'after'  => gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ),
						),
					),
				)
			);
			return self::median( self::first_reply_seconds( $ids ) );
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
		 * Counters: each opens its inbox view.
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
		 * "Your queue": one row per open view, with its count.
		 *
		 * @param array $stats Counters from stats().
		 */
		protected function render_queue( $stats ) {
			echo '<section class="card" aria-labelledby="hd-dash-queue"><div class="card-head"><h2 class="card-title" id="hd-dash-queue">' . esc_html__( 'Your queue', 'support-ticket-system-for-woocommerce' ) . '</h2></div>';
			foreach ( array( 'needs_reply', 'overdue', 'unassigned', 'mine', 'waiting' ) as $key ) {
				if ( ! isset( $stats[ $key ] ) ) {
					continue;
				}
				$stat = $stats[ $key ];
				$tone = 'neutral';
				if ( $stat['count'] > 0 && 'needs_reply' === $key ) {
					$tone = 'warn';
				} elseif ( $stat['count'] > 0 && 'overdue' === $key ) {
					$tone = 'bad';
				}
				echo '<div class="row" data-view="' . esc_attr( $key ) . '"><div><p class="row-title">' . esc_html( $stat['label'] ) . ' ' . WPFactory_WC_STS_UI::chip( number_format_i18n( $stat['count'] ), $tone ) . '</p><div class="row-meta">' . esc_html( $stat['help'] ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
				/* translators: %s: view name, e.g. "Needs reply". */
				echo '<a class="btn btn-text btn-sm" href="' . esc_url( $stat['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Open %s', 'support-ticket-system-for-woocommerce' ), $stat['label'] ) ) . '">' . esc_html__( 'Open', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			}
			echo '</section>';
		}

		/**
		 * "Waiting longest": tickets that need a reply, oldest customer message first.
		 *
		 * @param int $count Most rows.
		 */
		protected function render_waiting( $count ) {
			$inbox = $this->inbox();
			$core  = $this->core();
			$query = $inbox->query(
				'needs_reply',
				array(
					'posts_per_page' => $count,
					'no_found_rows'  => true,
				)
			);
			echo '<section class="card" aria-labelledby="hd-dash-wait"><div class="card-head"><h2 class="card-title" id="hd-dash-wait">' . esc_html__( 'Waiting longest', 'support-ticket-system-for-woocommerce' ) . '</h2><a class="btn btn-text btn-sm" href="' . esc_url( $inbox->url( array( 'view' => 'needs_reply' ) ) ) . '">' . esc_html__( 'See all', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			if ( ! $query->have_posts() ) {
				echo '<div class="empty"><p class="row-title">' . esc_html__( 'Nothing is waiting', 'support-ticket-system-for-woocommerce' ) . '</p>' . esc_html__( 'Every customer has an answer. Nice work.', 'support-ticket-system-for-woocommerce' ) . '</div></section>';
				return;
			}
			$ids     = wp_list_pluck( $query->posts, 'ID' );
			$overdue = array();
			if ( isset( $inbox->get_views()['overdue'] ) ) {
				// Past the team's reply target: the ticket is in the Overdue view.
				$overdue = array_map(
					'intval',
					$inbox->query(
						'overdue',
						array(
							'post__in'       => $ids,
							'posts_per_page' => count( $ids ),
							'fields'         => 'ids',
							'no_found_rows'  => true,
						)
					)->posts
				);
			}
			foreach ( $query->posts as $post ) {
				$id       = (int) $post->ID;
				$contact  = $core->get_ticket_contact( $id );
				$activity = $core->get_activity( $id );
				$agent    = $core->get_ticket_agent_id( $id );
				$meta     = array( $contact['display_name'] );
				if ( $activity['at'] ) {
					$meta[] = human_time_diff( strtotime( $activity['at'] . ' UTC' ) );
				}
				$meta[] = $agent ? $core->get_user_name( $agent ) : __( 'Nobody assigned', 'support-ticket-system-for-woocommerce' );
				$url    = $inbox->url(
					array(
						'view'   => 'needs_reply',
						'ticket' => $id,
					)
				);
				echo '<div class="row"><div><p class="row-title">#' . esc_html( $core->get_ticket_number( $id ) ) . ' ' . esc_html( get_the_title( $id ) ) . '</p><div class="row-meta">' . esc_html( implode( ' · ', array_filter( $meta ) ) ) . '</div></div><div class="row-actions">';
				if ( in_array( $id, $overdue, true ) ) {
					echo WPFactory_WC_STS_UI::chip( __( 'Overdue', 'support-ticket-system-for-woocommerce' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
				}
				/* translators: %s: ticket number. */
				echo '<a class="btn btn-secondary btn-sm" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Reply to ticket #%s', 'support-ticket-system-for-woocommerce' ), $core->get_ticket_number( $id ) ) ) . '">' . esc_html__( 'Reply', 'support-ticket-system-for-woocommerce' ) . '</a></div></div>';
			}
			echo '</section>';
		}

		/**
		 * The AI card: what it does and its state.
		 */
		protected function render_ai() {
			$credits = WPFactory_WC_STS_Credits::get_instance();
			if ( ! $credits->library() || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
				return;
			}
			$on    = $credits->tools_on();
			$label = $on && $credits->is_connected() ? $credits->credits_label( $credits->balance() ) : '';
			echo '<section class="card" data-stsw-dash-ai aria-labelledby="hd-dash-ai"><div class="card-head"><h2 class="card-title" id="hd-dash-ai">' . esc_html__( 'AI for your team', 'support-ticket-system-for-woocommerce' ) . '</h2>' . ( '' !== $label ? WPFactory_WC_STS_UI::chip( $label, 'info' ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
			if ( ! $on ) {
				/* translators: %d: number of free credits. */
				echo '<div class="card-body small muted">' . esc_html( sprintf( __( 'Draft replies, improve your wording, summarize long tickets, translate messages and answer live chats from your help articles. A free WPFactory AI account gets %d credits a month.', 'support-ticket-system-for-woocommerce' ), $credits->free_monthly() ) ) . '</div>';
				echo '<div class="card-foot"><span></span><a class="btn btn-secondary btn-sm" href="' . esc_url( $credits->settings_url() ) . '">' . esc_html__( 'Set up AI', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			} elseif ( ! $credits->handled_elsewhere() && ! $credits->is_connected() ) {
				echo '<div class="card-body"><div class="banner banner-warn"><span>' . esc_html__( 'AI replies are on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( $credits->screen_url() ) . '">' . esc_html__( 'Connect', 'support-ticket-system-for-woocommerce' ) . '</a></div></div>';
			} else {
				echo '<div class="card-body small muted">' . esc_html__( 'Draft, Improve my reply, Summarize and Translate are in every reply box.', 'support-ticket-system-for-woocommerce' ) . '</div>';
				if ( $credits->is_connected() ) {
					$cost = 'best' === (string) get_option( 'wpfactory_wc_sts_ai_quality', 'fast' ) ? __( 'About 11 credits per draft', 'support-ticket-system-for-woocommerce' ) : __( 'About 1 credit per draft', 'support-ticket-system-for-woocommerce' );
					echo '<div class="card-foot"><span class="small muted">' . esc_html( $cost ) . '</span><a class="btn btn-text btn-sm" href="' . esc_url( $credits->screen_url() ) . '">' . esc_html__( 'Usage', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
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
			echo '<section class="card" aria-labelledby="hd-dash-chart"><div class="card-head"><h2 class="card-title" id="hd-dash-chart">' . esc_html__( 'New tickets', 'support-ticket-system-for-woocommerce' ) . '</h2><span class="small muted">' . esc_html__( 'Last 14 days', 'support-ticket-system-for-woocommerce' ) . '</span></div><div class="card-body">';
			/* translators: %s: number of tickets. */
			echo '<div class="stat-num" data-stsw-dash-total aria-label="' . esc_attr( sprintf( _n( '%s new ticket', '%s new tickets', $total, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $total ) ) ) . '">' . esc_html( number_format_i18n( $total ) ) . '</div>';
			echo '<div class="bars" role="list" data-stsw-dash-chart aria-label="' . esc_attr__( 'New tickets per day, last 14 days', 'support-ticket-system-for-woocommerce' ) . '">';
			foreach ( $days as $day => $count ) {
				$label = date_i18n( get_option( 'date_format' ), strtotime( $day ) );
				/* translators: 1: date, 2: number of tickets. */
				$title = sprintf( _n( '%1$s: %2$s ticket', '%1$s: %2$s tickets', $count, 'support-ticket-system-for-woocommerce' ), $label, number_format_i18n( $count ) );
				echo '<span role="listitem" title="' . esc_attr( $title ) . '" aria-label="' . esc_attr( $title ) . '"' . ( $count ? ' style="height:' . esc_attr( (string) max( 2, round( 100 * $count / $max ) ) ) . '%"' : ' class="zero"' ) . '></span>';
			}
			echo '</div><div class="axis"><span>' . esc_html( date_i18n( 'M j', strtotime( array_key_first( $days ) ) ) ) . '</span><span>' . esc_html__( 'Today', 'support-ticket-system-for-woocommerce' ) . '</span></div></div></section>';
		}

		/**
		 * The dashboard.
		 */
		public function render() {
			$core = $this->core();
			if ( ! $core->can_manage_tickets() ) {
				echo '<div class="page narrow">';
				WPFactory_WC_STS_UI::notice( __( 'You are not allowed to see tickets.', 'support-ticket-system-for-woocommerce' ), true );
				echo '</div>';
				return;
			}
			$stats  = $this->stats();
			$needs  = isset( $stats['needs_reply'] ) ? (int) $stats['needs_reply']['count'] : 0;
			$lead   = array();
			$median = $this->median_first_reply_week();
			if ( null !== $median ) {
				/* translators: %s: duration, e.g. "2h 10m". */
				$lead[] = sprintf( __( 'Median first reply in the last 7 days: %s.', 'support-ticket-system-for-woocommerce' ), self::duration( $median ) );
			}
			if ( isset( $stats['unassigned'] ) ) {
				$free = (int) $stats['unassigned']['count'];
				/* translators: %s: number of tickets. */
				$lead[] = $free ? sprintf( _n( '%s ticket has nobody assigned.', '%s tickets have nobody assigned.', $free, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $free ) ) : __( 'Every open ticket has someone assigned.', 'support-ticket-system-for-woocommerce' );
			}
			/* translators: %s: number of tickets. */
			$title   = $needs ? sprintf( _n( '%s ticket needs a reply', '%s tickets need a reply', $needs, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $needs ) ) : __( 'No tickets need a reply', 'support-ticket-system-for-woocommerce' );
			$actions = '<a class="btn btn-primary" href="' . esc_url( $this->inbox()->url( array( 'view' => 'needs_reply' ) ) ) . '">' . esc_html__( 'Open inbox', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="page narrow" data-stsw-dashboard>';
			WPFactory_WC_STS_UI::page_head( $title, implode( ' ', $lead ), $actions, wp_date( __( 'l, F j', 'support-ticket-system-for-woocommerce' ) ) );
			echo '<div class="grid"><div class="stack">';
			$this->inbox()->render_checklist();
			$this->render_queue( $stats );
			$this->render_waiting( 5 );
			echo '</div><div class="stack">';
			$this->render_chart();
			$this->render_ai();
			/**
			 * More dashboard cards (side column). Print each as a `section.card`.
			 */
			do_action( 'wpfactory_wc_sts_dashboard_cards' );
			echo '</div></div></div>';
		}
	}

endif;

return WPFactory_WC_STS_Dashboard::get_instance();
