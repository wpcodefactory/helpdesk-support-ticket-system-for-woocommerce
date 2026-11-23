<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - Core: knowledge base.
 *
 * Articles (`stsw_kb_article`) in categories (`stsw_kb_category`), a help center shortcode with search, suggested
 * articles while a customer writes a ticket, and "Insert article" in the inbox composer. Shared by both editions.
 *
 * @version 2.3.0
 * @since   2.3.0
 *
 * @author WPFactory
 *
 * @package WPFactory\WC_Support_Ticket_System
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_WC_STS_KB' ) ) :

	/**
	 * WPFactory_WC_STS_KB class.
	 */
	class WPFactory_WC_STS_KB {

		/**
		 * Post type.
		 */
		const POST_TYPE = 'stsw_kb_article';

		/**
		 * Taxonomy.
		 */
		const TAXONOMY = 'stsw_kb_category';

		/**
		 * Option: suggest articles in the ticket form ('' off, anything else on; on when never saved).
		 */
		const OPTION_SUGGEST = 'wpfactory_wc_sts_kb_suggest';

		/**
		 * Option: base of the article addresses.
		 */
		const OPTION_SLUG = 'wpfactory_wc_sts_kb_slug';

		/**
		 * Page that shows the help center (search, categories, articles).
		 */
		const OPTION_PAGE = 'wpfactory_wc_sts_kb_page';

		/**
		 * Loaded instance.
		 *
		 * @var WPFactory_WC_STS_KB|null
		 */
		protected static $instance = null;

		/**
		 * Returns the loaded instance.
		 *
		 * @return WPFactory_WC_STS_KB
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			self::$instance = $this;
			add_action( 'init', array( $this, 'register' ) );
			add_action( 'template_redirect', array( $this, 'archive_to_page' ) );
			add_action( 'wpfactory_wc_sts_wizard_done', array( $this, 'ensure_page' ) );
			add_shortcode( 'stsw_knowledge_base', array( $this, 'shortcode' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_kb_suggest', array( $this, 'ajax_suggest' ) );
			add_action( 'wp_ajax_nopriv_wpfactory_wc_sts_kb_suggest', array( $this, 'ajax_suggest' ) );
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'header_tab' ) );
			add_action( 'wpfactory_wc_sts_render_tab_kb', array( $this, 'render_admin' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_kb_ai_draft', array( $this, 'ajax_ai_draft' ) );
			add_action( 'wpfactory_wc_sts_inbox_reply_tools', array( $this, 'render_insert' ), 5 );
			add_action( 'wpfactory_wc_sts_ask_after_message', array( $this, 'render_suggest_box' ) );
			add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
			add_filter( 'wpfactory_wc_sts_settings_general', array( $this, 'settings' ) );
		}

		/**
		 * Whether suggestions are on.
		 *
		 * @return bool
		 */
		public function suggest_enabled() {
			return '' !== (string) get_option( self::OPTION_SUGGEST, '1' );
		}

		/**
		 * Address base of articles.
		 *
		 * @return string
		 */
		public function get_slug() {
			$slug = sanitize_title( (string) get_option( self::OPTION_SLUG, '' ) );
			return '' !== $slug ? $slug : 'help-center';
		}

		/**
		 * Registers articles and categories.
		 */
		public function register() {
			$caps = WPFactory_WC_STS_Inc::get_main_instance()->get_ticket_capabilities( 'stsw_tickets' );
			register_taxonomy(
				self::TAXONOMY,
				self::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => __( 'Article categories', 'support-ticket-system-for-woocommerce' ),
						'singular_name' => __( 'Article category', 'support-ticket-system-for-woocommerce' ),
						'add_new_item'  => __( 'Add category', 'support-ticket-system-for-woocommerce' ),
					),
					'public'            => true,
					'hierarchical'      => true,
					'show_ui'           => true,
					'show_in_menu'      => false,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => $this->get_slug() . '/topic' ),
					'capabilities'      => array(
						'manage_terms' => 'stsw_tickets',
						'edit_terms'   => 'stsw_tickets',
						'delete_terms' => 'stsw_tickets',
						'assign_terms' => 'stsw_tickets',
					),
				)
			);
			register_post_type(
				self::POST_TYPE,
				array(
					'labels'              => array(
						'name'          => __( 'Articles', 'support-ticket-system-for-woocommerce' ),
						'singular_name' => __( 'Article', 'support-ticket-system-for-woocommerce' ),
						'add_new'       => __( 'Add article', 'support-ticket-system-for-woocommerce' ),
						'add_new_item'  => __( 'Add article', 'support-ticket-system-for-woocommerce' ),
						'edit_item'     => __( 'Edit article', 'support-ticket-system-for-woocommerce' ),
						'not_found'     => __( 'No articles yet.', 'support-ticket-system-for-woocommerce' ),
						'search_items'  => __( 'Search articles', 'support-ticket-system-for-woocommerce' ),
					),
					'public'              => true,
					'exclude_from_search' => false,
					// With a help center page the page has the address; the archive only exists as a fallback.
					'has_archive'         => $this->get_page_id() ? false : $this->get_slug(),
					'show_ui'             => true,
					'show_in_menu'        => false,
					'show_in_rest'        => true,
					'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
					'capability_type'     => 'page',
					'capabilities'        => $caps,
					'map_meta_cap'        => true,
					'rewrite'             => array(
						'slug'       => $this->get_slug(),
						'with_front' => false,
					),
				)
			);
			// New article addresses work without a visit to Settings > Permalinks.
			$rules = $this->get_slug() . ( $this->get_page_id() ? '|page' : '' );
			if ( get_option( 'wpfactory_wc_sts_kb_rules' ) !== $rules ) {
				update_option( 'wpfactory_wc_sts_kb_rules', $rules, false );
				flush_rewrite_rules( false );
			}
		}

		/**
		 * The help center page, if it is published and holds the shortcode.
		 *
		 * @return int Page ID or 0.
		 */
		public function get_page_id() {
			$id = absint( get_option( self::OPTION_PAGE, 0 ) );
			return $id && 'publish' === get_post_status( $id ) && has_shortcode( (string) get_post_field( 'post_content', $id ), 'stsw_knowledge_base' ) ? $id : 0;
		}

		/**
		 * Makes sure a help center page exists: an existing page with the shortcode, or a new "Help center" page at the
		 * help center address.
		 *
		 * @return int Page ID.
		 */
		public function ensure_page() {
			$id = $this->get_page_id();
			if ( $id ) {
				return $id;
			}
			global $wpdb;
			$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID LIMIT 1", '%' . $wpdb->esc_like( '[stsw_knowledge_base' ) . '%' )
			);
			if ( ! $id ) {
				$id = (int) wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => __( 'Help center', 'support-ticket-system-for-woocommerce' ),
						'post_name'    => $this->get_slug(),
						'post_content' => '<!-- wp:shortcode -->
[stsw_knowledge_base]
<!-- /wp:shortcode -->',
					)
				);
			}
			if ( $id ) {
				update_option( self::OPTION_PAGE, $id, false );
				delete_option( 'wpfactory_wc_sts_kb_rules' ); // Rewrite rules are rebuilt on the next request.
			}
			return $id;
		}

		/**
		 * The article archive shows the help center: it sends visitors to the help center page (created when missing).
		 */
		public function archive_to_page() {
			if ( ! is_post_type_archive( self::POST_TYPE ) ) {
				return;
			}
			$id = $this->ensure_page();
			if ( $id ) {
				// ensure_page() cleared the stored addresses: the next request rebuilds them without the archive, so the
				// page answers at this address. A temporary redirect, so browsers do not keep it.
				wp_safe_redirect( get_permalink( $id ), 302 );
				exit;
			}
		}

		/**
		 * Keeps the plugin menu open on the article screens.
		 *
		 * @param string $parent_file Parent menu file.
		 *
		 * @return string
		 */
		public function highlight_menu( $parent_file ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			return ( $screen && ( self::POST_TYPE === $screen->post_type || self::TAXONOMY === $screen->taxonomy ) ) ? WPFACTORY_WC_STS_PAGE : $parent_file;
		}

		/**
		 * Settings fields (General tab).
		 *
		 * @param array $fields Fields.
		 *
		 * @return array
		 */
		public function settings( $fields ) {
			$fields[ '@' . self::OPTION_SUGGEST ] = array(
				'type'        => 'checkbox',
				'label'       => __( 'Suggest help articles in the ticket form', 'support-ticket-system-for-woocommerce' ),
				'description' => __( 'While customers describe their problem, matching knowledge base articles appear under the message.', 'support-ticket-system-for-woocommerce' ),
				'default'     => '1',
			);
			$fields[ '@' . self::OPTION_SLUG ]    = array(
				'type'        => 'text',
				'label'       => __( 'Help center address', 'support-ticket-system-for-woocommerce' ),
				'placeholder' => 'help-center',
				'description' => __( 'Articles are published under this address, for example /help-center/returns/. Add the [stsw_knowledge_base] shortcode to any page for a searchable help center.', 'support-ticket-system-for-woocommerce' ),
			);
			return $fields;
		}

		/**
		 * Header tab.
		 *
		 * @param array $tabs Tabs.
		 *
		 * @return array
		 */
		public function header_tab( $tabs ) {
			$tabs['kb'] = __( 'Knowledge base', 'support-ticket-system-for-woocommerce' );
			return $tabs;
		}

		/**
		 * Published articles matching words, best first.
		 *
		 * @param string $words Words.
		 * @param int    $limit Maximum.
		 *
		 * @return WP_Post[]
		 */
		public function search( $words, $limit = 5 ) {
			$words = trim( wp_strip_all_tags( (string) $words ) );
			if ( strlen( $words ) < 3 ) {
				return array();
			}
			// Long messages: search on the longest words only, so a full paragraph still finds articles.
			$terms = array_filter(
				preg_split( '/[^\p{L}\p{N}]+/u', $words ),
				function ( $w ) {
					return function_exists( 'mb_strlen' ) ? mb_strlen( $w ) > 3 : strlen( $w ) > 3;
				}
			);
			usort(
				$terms,
				function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);
			$found = array();
			foreach ( array_merge( array( $words ), array_slice( array_unique( $terms ), 0, 4 ) ) as $search ) {
				if ( count( $found ) >= $limit ) {
					break;
				}
				$posts = get_posts(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'publish',
						'has_password'   => false,
						's'              => $search,
						'posts_per_page' => $limit,
						'orderby'        => 'relevance',
					)
				);
				foreach ( $posts as $post ) {
					$found[ $post->ID ] = $post;
				}
				if ( strlen( $words ) > 60 && $search === $words ) {
					continue;
				}
			}
			return array_slice( array_values( $found ), 0, $limit );
		}

		/**
		 * One article for the suggestion box.
		 *
		 * @param WP_Post $post Article.
		 *
		 * @return array
		 */
		public function to_array( $post ) {
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 22 );
			return array(
				'id'      => (int) $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'excerpt' => html_entity_decode( $excerpt, ENT_QUOTES, 'UTF-8' ),
			);
		}

		/**
		 * Suggestions while a customer types (logged in or not; read-only, published articles only).
		 */
		public function ajax_suggest() {
			if ( ! $this->suggest_enabled() ) {
				wp_send_json_success( array() );
			}
			$words = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only search.
			$words = function_exists( 'mb_substr' ) ? mb_substr( $words, 0, 300 ) : substr( $words, 0, 300 );
			wp_send_json_success( array_map( array( $this, 'to_array' ), $this->search( $words, 3 ) ) );
		}

		/**
		 * Empty suggestion box under the message field (filled by the script).
		 */
		public function render_suggest_box() {
			if ( ! $this->suggest_enabled() || ! wp_count_posts( self::POST_TYPE )->publish ) {
				return;
			}
			echo '<div class="stsw-suggest" data-stsw-suggest hidden><p class="stsw-suggest__title" id="stsw-suggest-title">' . esc_html__( 'These articles might answer your question', 'support-ticket-system-for-woocommerce' ) . '</p><ul aria-labelledby="stsw-suggest-title"></ul></div>';
			echo '<p class="screen-reader-text" aria-live="polite" data-stsw-suggest-live></p>';
		}

		/**
		 * "Insert article" in the inbox composer: adds the title and link at the cursor.
		 *
		 * @param int $ticket_id Ticket ID.
		 */
		public function render_insert( $ticket_id ) {
			unset( $ticket_id );
			$articles = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'has_password'   => false,
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			if ( ! $articles ) {
				return;
			}
			echo '<label class="screen-reader-text" for="stsw-insert-article">' . esc_html__( 'Insert a link to an article', 'support-ticket-system-for-woocommerce' ) . '</label>';
			echo '<select id="stsw-insert-article" data-hd-insert-select="stsw-inbox-reply"><option value="">' . esc_html__( 'Insert article', 'support-ticket-system-for-woocommerce' ) . '</option>';
			foreach ( $articles as $article ) {
				echo '<option value="' . esc_attr( get_the_title( $article ) . ': ' . get_permalink( $article ) ) . '">' . esc_html( get_the_title( $article ) ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Knowledge base tab: the help center address, the articles (search, status filter) and the categories.
		 */
		public function render_admin() {
			$articles = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 200,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters only.
			$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
			$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$status   = in_array( $status, array( 'publish', 'draft' ), true ) ? $status : '';
			$new      = '<a class="btn btn-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . self::POST_TYPE ) ) . '">' . esc_html__( 'New article', 'support-ticket-system-for-woocommerce' ) . '</a>';
			$manage   = admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . self::POST_TYPE );
			$center   = $this->get_page_id() ? get_permalink( $this->get_page_id() ) : home_url( '/' . $this->get_slug() . '/' );
			$path     = (string) wp_parse_url( $center, PHP_URL_PATH );
			$count    = count( $articles );
			$heading  = $count ? sprintf( /* translators: %s: number of articles. */ _n( '%s help article', '%s help articles', $count, 'support-ticket-system-for-woocommerce' ), number_format_i18n( $count ) ) : __( 'No help articles yet', 'support-ticket-system-for-woocommerce' );
			$statuses = array(
				''        => __( 'All', 'support-ticket-system-for-woocommerce' ),
				'publish' => __( 'Published', 'support-ticket-system-for-woocommerce' ),
				'draft'   => __( 'Draft', 'support-ticket-system-for-woocommerce' ),
			);
			echo '<div class="page narrow" data-stsw-kb>';
			WPFactory_WC_STS_UI::page_head( $heading, __( 'Answers customers find on their own. Articles are suggested while customers write a ticket, and you can link them in replies.', 'support-ticket-system-for-woocommerce' ), $new, __( 'Knowledge base', 'support-ticket-system-for-woocommerce' ) );
			echo '<div class="stack">';
			echo '<div class="banner banner-info" data-stsw-kb-center><span>' . ( $this->get_page_id() ? esc_html__( 'Your help center is live at', 'support-ticket-system-for-woocommerce' ) : esc_html__( 'Your help center address is', 'support-ticket-system-for-woocommerce' ) ) . ' <b>' . esc_html( '' !== $path ? $path : $center ) . '</b></span><a class="btn btn-text btn-sm" href="' . esc_url( $center ) . '">' . esc_html__( 'View page', 'support-ticket-system-for-woocommerce' ) . '</a></div>';
			echo '<div class="grid"><section class="card" aria-label="' . esc_attr__( 'Articles', 'support-ticket-system-for-woocommerce' ) . '">';
			if ( ! $articles ) {
				echo '<div class="empty"><p class="row-title">' . esc_html__( 'No articles yet', 'support-ticket-system-for-woocommerce' ) . '</p>' . esc_html__( 'Start with the questions you answer most: shipping times, returns, and how to track an order.', 'support-ticket-system-for-woocommerce' ) . '</div>';
			} else {
				echo '<form method="get" class="list-tools" role="search" data-hd-filter="hd-kb-rows"><input type="hidden" name="page" value="' . esc_attr( WPFACTORY_WC_STS_PAGE ) . '" /><input type="hidden" name="tab" value="kb" />';
				echo '<input type="search" class="input" id="hd-kb-q" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search articles', 'support-ticket-system-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'Search articles', 'support-ticket-system-for-woocommerce' ) . '" />';
				echo '<select class="select kb-status" id="hd-kb-status" name="status" aria-label="' . esc_attr__( 'Status', 'support-ticket-system-for-woocommerce' ) . '">';
				foreach ( $statuses as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select><noscript><button type="submit" class="btn btn-secondary btn-sm">' . esc_html__( 'Filter', 'support-ticket-system-for-woocommerce' ) . '</button></noscript></form><div id="hd-kb-rows">';
				$shown = 0;
				foreach ( $articles as $article ) {
					$cats      = get_the_terms( $article, self::TAXONOMY );
					$cats      = $cats && ! is_wp_error( $cats ) ? implode( ', ', wp_list_pluck( $cats, 'name' ) ) : '';
					$published = 'publish' === $article->post_status;
					$filter    = $published ? 'publish' : 'draft';
					$name      = get_the_title( $article ) ? get_the_title( $article ) : __( '(no title)', 'support-ticket-system-for-woocommerce' );
					$match     = ( '' === $search || false !== stripos( $name . ' ' . $cats, $search ) ) && ( '' === $status || $status === $filter );
					$shown    += $match ? 1 : 0;
					$obj       = get_post_status_object( $article->post_status );
					/* translators: %s: time, e.g. "3 days". */
					$meta = array_filter( array( $cats, $published ? sprintf( __( 'Updated %s ago', 'support-ticket-system-for-woocommerce' ), human_time_diff( strtotime( $article->post_modified_gmt . ' UTC' ) ) ) : __( 'Not published yet', 'support-ticket-system-for-woocommerce' ) ) );
					echo '<div class="row" data-hd-filter-text="' . esc_attr( strtolower( $name . ' ' . $cats ) ) . '" data-hd-filter-status="' . esc_attr( $filter ) . '"' . ( $match ? '' : ' hidden' ) . '><div><p class="row-title">' . esc_html( $name ) . '</p><div class="row-meta">' . esc_html( implode( ' · ', $meta ) );
					if ( $published ) {
						echo ' · <a href="' . esc_url( get_permalink( $article ) ) . '">' . esc_html__( 'View', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html( $name ) . '</span></a>';
					}
					echo '</div></div><div class="row-actions">' . WPFactory_WC_STS_UI::chip( $published ? __( 'Published', 'support-ticket-system-for-woocommerce' ) : ( $obj ? $obj->label : $article->post_status ), $published ? 'ok' : 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
					/* translators: %s: article title. */
					echo '<a class="btn btn-text btn-sm" href="' . esc_url( (string) get_edit_post_link( $article->ID ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'support-ticket-system-for-woocommerce' ), $name ) ) . '">' . esc_html__( 'Edit', 'support-ticket-system-for-woocommerce' ) . '</a></div></div>';
				}
				echo '<div class="empty" data-hd-filter-none' . ( $shown ? ' hidden' : '' ) . '><p class="row-title">' . esc_html__( 'No articles match', 'support-ticket-system-for-woocommerce' ) . '</p><a class="btn btn-text btn-sm" href="' . esc_url( WPFactory_WC_STS_UI::url( 'kb' ) ) . '">' . esc_html__( 'Show all', 'support-ticket-system-for-woocommerce' ) . '</a></div></div>';
			}
			echo '</section>';
			$terms = get_terms(
				array(
					'taxonomy'   => self::TAXONOMY,
					'hide_empty' => false,
				)
			);
			echo '<div class="stack">';
			$this->render_ai_card( $articles );
			echo '<section class="card" aria-labelledby="hd-kb-cats" data-stsw-kb-cats><div class="card-head"><h2 class="card-title" id="hd-kb-cats">' . esc_html__( 'Categories', 'support-ticket-system-for-woocommerce' ) . '</h2><a class="btn btn-text btn-sm" href="' . esc_url( $manage ) . '">' . esc_html__( 'Manage', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html__( 'categories', 'support-ticket-system-for-woocommerce' ) . '</span></a></div>';
			if ( ! $terms || is_wp_error( $terms ) ) {
				echo '<div class="card-body small muted">' . esc_html__( 'No categories yet. Group articles by topic, for example Orders, Shipping or Returns.', 'support-ticket-system-for-woocommerce' ) . '</div>';
			} else {
				foreach ( $terms as $term ) {
					/* translators: %s: number of articles. */
					echo '<div class="row"><p class="row-title small">' . esc_html( $term->name ) . '</p><span class="small muted" aria-label="' . esc_attr( sprintf( _n( '%s article', '%s articles', (int) $term->count, 'support-ticket-system-for-woocommerce' ), number_format_i18n( (int) $term->count ) ) ) . '">' . esc_html( number_format_i18n( (int) $term->count ) ) . '</span></div>';
				}
			}
			echo '</section></div></div></div></div>';
		}

		/**
		 * Who may draft articles with AI: people who can add help articles.
		 *
		 * @return bool
		 */
		protected function can_draft() {
			$type = get_post_type_object( self::POST_TYPE );
			return $type && current_user_can( $type->cap->create_posts );
		}

		/**
		 * Topics worth an article: the store's ticket topics and the usual questions, minus titles that already exist.
		 *
		 * @param WP_Post[] $articles Existing articles.
		 *
		 * @return string[]
		 */
		protected function suggested_topics( $articles ) {
			$have   = array_map( 'strtolower', wp_list_pluck( $articles, 'post_title' ) );
			$topics = array();
			$terms  = get_terms(
				array(
					'taxonomy'   => 'stsw_tickets_subject',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 6,
				)
			);
			foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
				$topics[] = $term->name;
			}
			$topics = array_merge(
				$topics,
				array(
					__( 'Shipping and delivery times', 'support-ticket-system-for-woocommerce' ),
					__( 'Returns and refunds', 'support-ticket-system-for-woocommerce' ),
					__( 'Track your order', 'support-ticket-system-for-woocommerce' ),
					__( 'Payment methods', 'support-ticket-system-for-woocommerce' ),
				)
			);
			$out    = array();
			foreach ( array_unique( $topics ) as $topic ) {
				if ( ! in_array( strtolower( $topic ), $have, true ) ) {
					$out[] = $topic;
				}
			}
			return array_slice( $out, 0, 4 );
		}

		/**
		 * Side card: AI writes help articles from the store's own information, and the chat assistant answers from them.
		 *
		 * @param WP_Post[] $articles Existing articles.
		 */
		protected function render_ai_card( $articles ) {
			if ( ! $this->can_draft() || ! class_exists( 'WPFactory_WC_STS_Credits' ) ) {
				return;
			}
			$credits = WPFactory_WC_STS_Credits::get_instance();
			$on      = $credits->tools_on();
			/** This filter is documented in includes/class-wpfactory-wc-sts-credits.php */
			$uses    = (bool) apply_filters( 'wpfactory_wc_sts_ai_uses_credits', ! $credits->handled_elsewhere() && $credits->is_enabled() );
			$balance = $uses ? $credits->balance() : null;
			if ( ! $on ) {
				$chip = WPFactory_WC_STS_UI::chip( __( 'Off', 'support-ticket-system-for-woocommerce' ), 'neutral' );
			} elseif ( null !== $balance ) {
				$chip = WPFactory_WC_STS_UI::chip( $credits->credits_label( $balance ), 'info' );
			} else {
				$chip = WPFactory_WC_STS_UI::chip( __( 'On', 'support-ticket-system-for-woocommerce' ), 'ok' );
			}
			echo '<section class="card kb-ai" aria-labelledby="hd-kb-ai" data-stsw-kb-ai>';
			echo '<div class="card-head"><h2 class="card-title" id="hd-kb-ai">' . wp_kses( WPFactory_WC_STS_UI::icon( 'sparkle', 16 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' . esc_html__( 'Write articles with AI', 'support-ticket-system-for-woocommerce' ) . '</h2>' . $chip . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip escaped.
			echo '<div class="card-body">';
			echo '<p class="small kb-ai-lead">' . esc_html__( 'AI drafts help articles from your own store: your products, your refund and terms pages, your shipping zones and the topics customers ask about most. Every article is saved as a draft for you to check.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			echo '<p class="small muted kb-ai-lead">' . esc_html__( 'Once published, the chat assistant answers customers from your articles day and night, and hands over to your team when they do not cover a question.', 'support-ticket-system-for-woocommerce' ) . '</p>';
			if ( $on ) {
				echo '<form class="kb-ai-form" data-stsw-kb-ai-form data-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wpfactory_wc_sts_kb_ai_draft' ) ) . '" data-working="' . esc_attr__( 'Writing the draft...', 'support-ticket-system-for-woocommerce' ) . '">';
				echo '<div class="field"><label class="label" for="hd-kb-ai-topic">' . esc_html__( 'Topic', 'support-ticket-system-for-woocommerce' ) . '</label><input type="text" class="input" id="hd-kb-ai-topic" name="topic" required maxlength="120" placeholder="' . esc_attr__( 'For example: Returns and refunds', 'support-ticket-system-for-woocommerce' ) . '" /></div>';
				$topics = $this->suggested_topics( $articles );
				if ( $topics ) {
					echo '<div class="kb-ai-topics" role="group" aria-label="' . esc_attr__( 'Suggested topics', 'support-ticket-system-for-woocommerce' ) . '">';
					foreach ( $topics as $topic ) {
						echo '<button type="button" class="btn btn-text btn-sm" data-stsw-kb-ai-topic="' . esc_attr( $topic ) . '">' . esc_html( $topic ) . '</button>';
					}
					echo '</div>';
				}
				echo '<div class="kb-ai-out" aria-live="polite" data-stsw-kb-ai-out></div>';
				echo '<button type="submit" class="btn btn-primary">' . esc_html__( 'Draft article', 'support-ticket-system-for-woocommerce' ) . '</button>';
				echo '</form>';
			}
			echo '</div><div class="card-foot">';
			if ( ! $on ) {
				/* translators: %s: number of free credits. */
				echo '<span class="small muted">' . esc_html( sprintf( __( 'Free WPFactory AI account: %s credits every month.', 'support-ticket-system-for-woocommerce' ), number_format_i18n( $credits->free_monthly() ) ) ) . '</span>';
				echo '<a class="btn btn-primary btn-sm" href="' . esc_url( $credits->settings_url() ) . '">' . esc_html__( 'Turn on AI', 'support-ticket-system-for-woocommerce' ) . '</a>';
			} elseif ( $uses && $credits->library() ) {
				echo '<span class="small muted">' . esc_html__( 'Each draft uses AI credits.', 'support-ticket-system-for-woocommerce' ) . '</span>';
				echo '<a class="btn btn-text btn-sm" href="' . esc_url( $credits->library()->buy_url() ) . '">' . esc_html__( 'Buy credits', 'support-ticket-system-for-woocommerce' ) . '</a>';
			} else {
				echo '<span class="small muted">' . esc_html__( 'Drafts use the AI service set in Settings > AI.', 'support-ticket-system-for-woocommerce' ) . '</span>';
			}
			echo '</div></section>';
		}

		/**
		 * Shortens text to plain words.
		 *
		 * @param string $text Text or HTML.
		 * @param int    $max  Most characters.
		 *
		 * @return string
		 */
		protected function clip( $text, $max ) {
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
			return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		}

		/**
		 * What the AI may use: store name and country, products, refund and terms pages, shipping zones, common
		 * ticket topics, reasons, and the titles of existing articles. No customer data.
		 *
		 * @return string
		 */
		public function store_context() {
			$nl    = "\n";
			$lines = array( 'Store: ' . get_bloginfo( 'name' ) . ' (' . home_url( '/' ) . ')' );
			if ( function_exists( 'get_woocommerce_currency' ) && function_exists( 'WC' ) && WC()->countries ) {
				$lines[] = 'Currency: ' . get_woocommerce_currency() . '. Store country: ' . WC()->countries->get_base_country();
			}
			if ( function_exists( 'wc_get_products' ) ) {
				$products = wc_get_products(
					array(
						'status' => 'publish',
						'limit'  => 40,
					)
				);
				if ( $products ) {
					$lines[] = $nl . 'Products:';
					foreach ( $products as $product ) {
						$cats    = wp_strip_all_tags( (string) wc_get_product_category_list( $product->get_id(), ', ' ) );
						$lines[] = '- ' . $product->get_name() . ( '' !== (string) $product->get_price() ? ', price ' . wc_format_decimal( $product->get_price(), 2 ) : '' ) . ( $cats ? ', category ' . $cats : '' ) . ( $product->get_short_description() ? ': ' . $this->clip( $product->get_short_description(), 160 ) : '' );
					}
				}
			}
			$pages = array(
				'woocommerce_refund_returns_page_id' => 'Refund and returns policy',
				'woocommerce_terms_page_id'          => 'Terms and conditions',
			);
			foreach ( $pages as $option => $label ) {
				$page = get_post( (int) get_option( $option ) );
				if ( $page && 'publish' === $page->post_status && '' !== trim( $page->post_content ) ) {
					$lines[] = $nl . $label . ':' . $nl . $this->clip( $page->post_content, 3000 );
				}
			}
			if ( class_exists( 'WC_Shipping_Zones' ) ) {
				$zones = array();
				foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
					$methods = array();
					foreach ( $zone['shipping_methods'] as $method ) {
						if ( 'yes' === $method->enabled ) {
							$methods[] = $method->get_title();
						}
					}
					$zones[] = '- ' . $zone['zone_name'] . ( $methods ? ': ' . implode( ', ', $methods ) : '' );
				}
				if ( $zones ) {
					$lines[] = $nl . 'Shipping zones and methods:' . $nl . implode( $nl, $zones );
				}
			}
			$terms = get_terms(
				array(
					'taxonomy'   => 'stsw_tickets_subject',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 10,
				)
			);
			if ( $terms && ! is_wp_error( $terms ) ) {
				$lines[] = $nl . 'Topics customers ask about most: ' . implode( ', ', wp_list_pluck( $terms, 'name' ) );
			}
			$lines[] = 'Reasons customers pick when asking about an order: ' . implode( ', ', WPFactory_WC_STS_Inc::get_main_instance()->get_reasons() );
			$titles  = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => 50,
					'fields'         => 'ids',
				)
			);
			if ( $titles ) {
				$lines[] = 'Help articles that already exist: ' . implode( '; ', array_map( 'get_the_title', $titles ) );
			}
			$out = implode( $nl, $lines );
			return function_exists( 'mb_substr' ) ? mb_substr( $out, 0, 12000 ) : substr( $out, 0, 12000 );
		}

		/**
		 * AJAX: drafts a help article about a topic from the store's information, saved as a draft (never published).
		 */
		public function ajax_ai_draft() {
			check_ajax_referer( 'wpfactory_wc_sts_kb_ai_draft', 'nonce' );
			$credits = class_exists( 'WPFactory_WC_STS_Credits' ) ? WPFactory_WC_STS_Credits::get_instance() : null;
			if ( ! $credits || ! $this->can_draft() ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'support-ticket-system-for-woocommerce' ) ), 403 );
			}
			$topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
			if ( '' === $topic ) {
				wp_send_json_error( array( 'message' => __( 'Write a topic first.', 'support-ticket-system-for-woocommerce' ) ) );
			}
			if ( ! $credits->tools_on() ) {
				wp_send_json_error(
					array(
						'message' => __( 'AI is off. Turn it on in Settings > AI.', 'support-ticket-system-for-woocommerce' ),
						'link'    => $credits->settings_url(),
						'label'   => __( 'Turn on AI', 'support-ticket-system-for-woocommerce' ),
					)
				);
			}
			if ( ! $credits->handled_elsewhere() && ! $credits->is_connected() ) {
				wp_send_json_error(
					array(
						'message' => __( 'AI is on, but this site is not connected to WPFactory AI yet.', 'support-ticket-system-for-woocommerce' ),
						'link'    => $credits->screen_url(),
						'label'   => __( 'Connect', 'support-ticket-system-for-woocommerce' ),
					)
				);
			}
			$language = function_exists( 'locale_get_display_language' ) ? locale_get_display_language( get_locale(), 'en' ) : get_locale();
			$system   = 'You write help center articles for the online store "' . get_bloginfo( 'name' ) . '". Use ONLY the store information given. Never invent prices, delivery times, return windows, fees, policies, addresses or contact details: where the store information does not say, write a short placeholder in square brackets, for example [Add your return window]. Never promise a response time. Write in ' . $language . ', friendly and plain, 150 to 400 words. Output the article title on the first line (plain text), then the body in simple HTML using only p, h2, ul, ol, li and strong.';
			$prompt   = 'Store information:' . "\n" . $this->store_context() . "\n\n" . 'Write a help article about: ' . $topic;
			$result   = $credits->ai_complete( $system, $prompt, 'kb-article' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$result = trim( (string) $result );
			$parts  = preg_split( '/\R/', $result, 2 );
			$title  = trim( wp_strip_all_tags( (string) preg_replace( '/^#+\s*/', '', (string) $parts[0] ) ) );
			$body   = wp_kses_post( trim( (string) ( $parts[1] ?? '' ) ) );
			if ( '' === $body ) {
				$body  = wp_kses_post( $result );
				$title = $topic;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE,
					'post_status'  => 'draft',
					'post_title'   => '' !== $title ? $title : $topic,
					'post_content' => $body,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				wp_send_json_error( array( 'message' => $id->get_error_message() ) );
			}
			wp_send_json_success(
				array(
					/* translators: %s: article title. */
					'message' => sprintf( __( 'Draft saved: %s. Check it, then publish.', 'support-ticket-system-for-woocommerce' ), get_the_title( $id ) ),
					'link'    => get_edit_post_link( $id, 'raw' ),
					'label'   => __( 'Edit draft', 'support-ticket-system-for-woocommerce' ),
				)
			);
		}

		/**
		 * Help center: search, categories and articles.
		 *
		 * @return string HTML.
		 */
		public function shortcode() {
			WPFactory_WC_STS_Init::enqueue_frontend_assets();
			$query    = sanitize_text_field( wp_unslash( $_GET['kb'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search.
			$results  = array();
			$sections = array();
			$articles = array();
			if ( '' !== $query ) {
				$results = array_map( array( $this, 'to_array' ), $this->search( $query, 20 ) );
			} else {
				$cats = get_terms(
					array(
						'taxonomy'   => self::TAXONOMY,
						'hide_empty' => true,
						'parent'     => 0,
					)
				);
				if ( $cats && ! is_wp_error( $cats ) ) {
					foreach ( $cats as $cat ) {
						$sections[] = array(
							'name'     => $cat->name,
							'url'      => (string) get_term_link( $cat ),
							'articles' => array_map(
								array( $this, 'to_array' ),
								get_posts(
									array(
										'post_type'      => self::POST_TYPE,
										'post_status'    => 'publish',
										'has_password'   => false,
										'posts_per_page' => 6,
										'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
											array(
												'taxonomy' => self::TAXONOMY,
												'terms'    => $cat->term_id,
											),
										),
									)
								)
							),
						);
					}
				} else {
					$articles = array_map(
						array( $this, 'to_array' ),
						get_posts(
							array(
								'post_type'      => self::POST_TYPE,
								'post_status'    => 'publish',
								'has_password'   => false,
								'posts_per_page' => 20,
							)
						)
					);
				}
			}
			return WPFactory_WC_STS_Templates::get(
				'help-center',
				array(
					'query'      => $query,
					'results'    => $results,
					'sections'   => $sections,
					'articles'   => $articles,
					'ticket_url' => add_query_arg( 'stsw_view', 'new', WPFactory_WC_STS_Inc::get_main_instance()->get_customer_tickets_url() ),
				)
			);
		}
	}

endif;

return WPFactory_WC_STS_KB::get_instance();
