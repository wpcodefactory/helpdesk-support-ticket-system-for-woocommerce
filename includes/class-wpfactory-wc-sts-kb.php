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
			add_shortcode( 'stsw_knowledge_base', array( $this, 'shortcode' ) );
			add_action( 'wp_ajax_wpfactory_wc_sts_kb_suggest', array( $this, 'ajax_suggest' ) );
			add_action( 'wp_ajax_nopriv_wpfactory_wc_sts_kb_suggest', array( $this, 'ajax_suggest' ) );
			add_filter( 'wpfactory_wc_sts_header_tabs', array( $this, 'header_tab' ) );
			add_action( 'wpfactory_wc_sts_render_tab_kb', array( $this, 'render_admin' ) );
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
					'has_archive'         => $this->get_slug(),
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
			if ( get_option( 'wpfactory_wc_sts_kb_rules' ) !== $this->get_slug() ) {
				update_option( 'wpfactory_wc_sts_kb_rules', $this->get_slug(), false );
				flush_rewrite_rules( false );
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
			echo '<select id="stsw-insert-article" class="hd-pill hd-pill--ghost" data-hd-insert-select="stsw-inbox-reply"><option value="">' . esc_html__( 'Insert article', 'support-ticket-system-for-woocommerce' ) . '</option>';
			foreach ( $articles as $article ) {
				echo '<option value="' . esc_attr( get_the_title( $article ) . ': ' . get_permalink( $article ) ) . '">' . esc_html( get_the_title( $article ) ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Knowledge base tab: articles with their category and status.
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
			$new      = '<a class="hd-btn hd-btn--primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . self::POST_TYPE ) ) . '">' . wp_kses( WPFactory_WC_STS_UI::icon( 'plus', 16 ), WPFactory_WC_STS_UI::svg_kses() ) . ' ' . esc_html__( 'New article', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '<div class="hd-page">';
			WPFactory_WC_STS_UI::page_head( __( 'Knowledge base', 'support-ticket-system-for-woocommerce' ), __( 'Answers customers can read on their own. Articles are suggested while customers write a ticket, and you can link them in replies.', 'support-ticket-system-for-woocommerce' ), $new );
			echo '<p class="hd-help">';
			echo esc_html__( 'Help center:', 'support-ticket-system-for-woocommerce' ) . ' <a href="' . esc_url( home_url( '/' . $this->get_slug() . '/' ) ) . '">' . esc_html( home_url( '/' . $this->get_slug() . '/' ) ) . '</a> · <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . self::POST_TYPE ) ) . '">' . esc_html__( 'Manage categories', 'support-ticket-system-for-woocommerce' ) . '</a>';
			echo '</p>';
			if ( ! $articles ) {
				echo '<section class="hd-card hd-emptystate"><span class="hd-emptystate__icon">' . wp_kses( WPFactory_WC_STS_UI::icon( 'book', 22 ), WPFactory_WC_STS_UI::svg_kses() ) . '</span><h2 class="hd-h2">' . esc_html__( 'No articles yet', 'support-ticket-system-for-woocommerce' ) . '</h2><p class="hd-muted">' . esc_html__( 'Start with the questions you answer most: shipping times, returns, and how to track an order.', 'support-ticket-system-for-woocommerce' ) . '</p></section></div>';
				return;
			}
			echo '<section class="hd-card hd-card--list"><table class="hd-table"><thead><tr><th scope="col">' . esc_html__( 'Title', 'support-ticket-system-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Category', 'support-ticket-system-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Status', 'support-ticket-system-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Updated', 'support-ticket-system-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $articles as $article ) {
				$cats = get_the_terms( $article, self::TAXONOMY );
				$obj  = get_post_status_object( $article->post_status );
				echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $article->ID ) ) . '"><strong>' . esc_html( get_the_title( $article ) ? get_the_title( $article ) : __( '(no title)', 'support-ticket-system-for-woocommerce' ) ) . '</strong></a>';
				if ( 'publish' === $article->post_status ) {
					echo ' <a class="hd-help hd-inline-link" href="' . esc_url( get_permalink( $article ) ) . '">' . esc_html__( 'View', 'support-ticket-system-for-woocommerce' ) . '<span class="screen-reader-text"> ' . esc_html( get_the_title( $article ) ) . '</span></a>';
				}
				echo '</td><td>' . esc_html( $cats && ! is_wp_error( $cats ) ? implode( ', ', wp_list_pluck( $cats, 'name' ) ) : '' ) . '</td><td><span class="hd-status">' . esc_html( $obj ? $obj->label : $article->post_status ) . '</span></td><td>' . esc_html( human_time_diff( strtotime( $article->post_modified_gmt . ' UTC' ) ) ) . '</td></tr>';
			}
			echo '</tbody></table></section></div>';
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
