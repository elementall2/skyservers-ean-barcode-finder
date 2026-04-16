<?php
/**
 * Plugin Name:       SkyServers EAN Barcode Finder
 * Plugin URI:        https://sky-servers.com/skyservers-ean-barcode-finder/
 * Description:       Automatically search and assign EAN/GTIN barcodes to WooCommerce products by product name using free barcode databases.
 * Version:           1.3.0
 * Author:            Sky Servers SRL
 * Author URI:        https://sky-servers.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       skyservers-ean-barcode-finder
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 *
 * @package SkyServersEanBarcodeFinder
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'SKYEAN_VERSION', '1.3.0' );
define( 'SKYEAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SKYEAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SKYEAN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class SkyServers_EAN_Barcode_Finder {

	/**
	 * WooCommerce native GTIN meta key (built-in since WooCommerce 8.x).
	 *
	 * @var string
	 */
	const META_KEY = '_global_unique_id';

	/**
	 * Option key for UPCitemdb API key.
	 *
	 * @var string
	 */
	const OPTION_API_KEY = 'skyean_upcitemdb_api_key';

	/**
	 * Single instance of the class.
	 *
	 * @var SkyServers_EAN_Barcode_Finder|null
	 */
	private static $instance = null;

	/**
	 * Stored reference to search filter callbacks for safe removal.
	 *
	 * @var array
	 */
	private $search_filters = array();

	/**
	 * Get single instance.
	 *
	 * @return SkyServers_EAN_Barcode_Finder
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
	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_skyean_search_barcode', array( $this, 'ajax_search_barcode' ) );
		add_action( 'wp_ajax_skyean_save_barcode', array( $this, 'ajax_save_barcode' ) );
		add_action( 'wp_ajax_skyean_get_products', array( $this, 'ajax_get_products' ) );

		// Settings link on plugins page.
		add_filter( 'plugin_action_links_' . SKYEAN_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Translations are loaded automatically by WordPress 4.6+ for plugins
	 * hosted on WordPress.org. No manual load_plugin_textdomain() needed.
	 *
	 * @see https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/
	 */

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=skyservers-ean-barcode-finder' ) ),
			esc_html__( 'Settings', 'skyservers-ean-barcode-finder' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register admin submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Barcode Finder', 'skyservers-ean-barcode-finder' ),
			__( 'Barcode Finder', 'skyservers-ean-barcode-finder' ),
			'manage_woocommerce',
			'skyservers-ean-barcode-finder',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_skyservers-ean-barcode-finder' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'skyean-admin-style',
			SKYEAN_PLUGIN_URL . 'assets/admin-style.css',
			array(),
			SKYEAN_VERSION
		);

		wp_enqueue_script(
			'skyean-admin-script',
			SKYEAN_PLUGIN_URL . 'assets/admin-script.js',
			array( 'jquery' ),
			SKYEAN_VERSION,
			true
		);

		wp_localize_script(
			'skyean-admin-script',
			'skyeanAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'skyean_nonce' ),
				'i18n'    => array(
					'loading'       => __( 'Loading...', 'skyservers-ean-barcode-finder' ),
					'noProducts'    => __( 'No products found.', 'skyservers-ean-barcode-finder' ),
					'loadError'     => __( 'Error loading products.', 'skyservers-ean-barcode-finder' ),
					'timeoutError'  => __( 'Request timed out. Try fewer products per page or a more specific filter.', 'skyservers-ean-barcode-finder' ),
					'serverError'   => __( 'Server error. Check if your hosting blocks long requests.', 'skyservers-ean-barcode-finder' ),
					'retry'         => __( 'Retry', 'skyservers-ean-barcode-finder' ),
					'noResults'     => __( 'No barcode found. You can enter it manually.', 'skyservers-ean-barcode-finder' ),
					'resultsFor'    => __( 'Results for', 'skyservers-ean-barcode-finder' ),
					'savedEan'      => __( 'Saved barcode', 'skyservers-ean-barcode-finder' ),
					'forProduct'    => __( 'for product', 'skyservers-ean-barcode-finder' ),
					'searchError'   => __( 'Search error', 'skyservers-ean-barcode-finder' ),
					'networkError'  => __( 'Network error', 'skyservers-ean-barcode-finder' ),
					'saveError'     => __( 'Save error', 'skyservers-ean-barcode-finder' ),
					'bulkConfirm'   => __( "This will automatically search barcodes for all products without one.\n\nProducts with exactly 1 result will be saved automatically.\nMultiple results require manual selection.\n\nContinue?", 'skyservers-ean-barcode-finder' ),
					'bulkStarted'   => __( 'Automatic search started...', 'skyservers-ean-barcode-finder' ),
					'bulkStopped'   => __( 'Search stopped by user.', 'skyservers-ean-barcode-finder' ),
					'bulkFinished'  => __( 'Automatic search finished!', 'skyservers-ean-barcode-finder' ),
					'searching'     => __( 'Searching', 'skyservers-ean-barcode-finder' ),
					'found'         => __( 'found', 'skyservers-ean-barcode-finder' ),
					'notFound'      => __( 'not found', 'skyservers-ean-barcode-finder' ),
					'toReview'      => __( 'to review', 'skyservers-ean-barcode-finder' ),
					'manualCheck'   => __( 'results (manual check needed)', 'skyservers-ean-barcode-finder' ),
					'stopping'      => __( 'Stopping...', 'skyservers-ean-barcode-finder' ),
					'showing'       => __( 'Showing', 'skyservers-ean-barcode-finder' ),
					'of'            => __( 'of', 'skyservers-ean-barcode-finder' ),
					'products'      => __( 'products', 'skyservers-ean-barcode-finder' ),
					'jumpTo'        => __( 'Go to page', 'skyservers-ean-barcode-finder' ),
					'firstPage'     => __( 'First page', 'skyservers-ean-barcode-finder' ),
					'lastPage'      => __( 'Last page', 'skyservers-ean-barcode-finder' ),
					'prevPage'      => __( 'Previous page', 'skyservers-ean-barcode-finder' ),
					'nextPage'      => __( 'Next page', 'skyservers-ean-barcode-finder' ),
					'stop'          => __( 'Stop', 'skyservers-ean-barcode-finder' ),
				),
			)
		);
	}

	/**
	 * Render the main admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		// Handle settings save.
		if ( isset( $_POST['skyean_save_settings'] ) ) {
			if ( ! isset( $_POST['skyean_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['skyean_settings_nonce'] ) ), 'skyean_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'skyservers-ean-barcode-finder' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'skyservers-ean-barcode-finder' ) );
			}

			$api_key = isset( $_POST['skyean_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['skyean_api_key'] ) ) : '';
			update_option( self::OPTION_API_KEY, $api_key );

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved!', 'skyservers-ean-barcode-finder' ) . '</p></div>';
		}

		$api_key = get_option( self::OPTION_API_KEY, '' );

		// Get product stats.
		$stats = $this->get_product_stats();

		// Load admin template.
		include SKYEAN_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * Get product statistics using WordPress APIs.
	 *
	 * @return array Stats array with total, with_ean, without_ean, percent.
	 */
	private function get_product_stats() {
		$total = wp_count_posts( 'product' );
		$total_published = isset( $total->publish ) ? (int) $total->publish : 0;

		$cache_key = 'skyean_products_with_ean';
		$with_ean  = wp_cache_get( $cache_key, 'skyservers-ean-barcode-finder' );

		if ( false === $with_ean ) {
			global $wpdb;
			$with_ean = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != %s",
					self::META_KEY,
					''
				)
			);
			wp_cache_set( $cache_key, $with_ean, 'skyservers-ean-barcode-finder', 300 );
		}

		return array(
			'total'       => $total_published,
			'with_ean'    => (int) $with_ean,
			'without_ean' => $total_published - (int) $with_ean,
			'percent'     => $total_published > 0 ? round( ( (int) $with_ean / $total_published ) * 100 ) : 0,
		);
	}

	/**
	 * AJAX handler: Get products list.
	 *
	 * @return void
	 */
	public function ajax_get_products() {
		check_ajax_referer( 'skyean_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'skyservers-ean-barcode-finder' ) );
		}

		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		$per_page = min( max( $per_page, 10 ), 200 );
		$filter   = isset( $_POST['filter'] ) ? sanitize_text_field( wp_unslash( $_POST['filter'] ) ) : 'without_ean';
		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$orderby  = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : 'title';
		$order    = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'ASC';

		$order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
		);

		// Sorting.
		if ( 'sku' === $orderby ) {
			$args['meta_key'] = '_sku'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value';
			$args['order']    = $order;
		} elseif ( 'sku_numeric' === $orderby ) {
			$args['meta_key'] = '_sku'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $order;
		} else {
			$args['orderby'] = 'title';
			$args['order']   = $order;
		}

		// Search by title OR SKU.
		if ( ! empty( $search ) ) {
			$this->add_search_filters( $search );
		}

		if ( $category > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,
				),
			);
		}

		// EAN filter.
		if ( 'without_ean' === $filter ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => self::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_KEY,
					'value'   => '',
					'compare' => '=',
				),
			);
		} elseif ( 'with_ean' === $filter ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => self::META_KEY,
					'value'   => '',
					'compare' => '!=',
				),
			);
		}

		$query = new WP_Query( $args );

		// Remove search filters safely.
		$this->remove_search_filters();

		$product_ids = $query->posts;
		$products    = array();

		if ( ! empty( $product_ids ) ) {
			update_meta_cache( 'post', $product_ids );

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$thumb_id  = $product->get_image_id();
				$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

				$ean = '';
				if ( method_exists( $product, 'get_global_unique_id' ) ) {
					$ean = $product->get_global_unique_id();
				}
				if ( empty( $ean ) ) {
					$ean = get_post_meta( $product_id, self::META_KEY, true );
				}

				$products[] = array(
					'id'    => $product_id,
					'name'  => $product->get_name(),
					'sku'   => $product->get_sku(),
					'ean'   => $ean ? $ean : '',
					'thumb' => $thumb_url,
					'edit'  => get_edit_post_link( $product_id, 'raw' ),
				);
			}
		}

		wp_send_json_success(
			array(
				'products'    => $products,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
			)
		);
	}

	/**
	 * Add search filters for title/SKU search.
	 *
	 * @param string $search_term Search term.
	 * @return void
	 */
	private function add_search_filters( $search_term ) {
		$this->search_filters['join'] = function ( $join ) {
			global $wpdb;
			$join .= " LEFT JOIN {$wpdb->postmeta} AS skyean_sku_search ON ({$wpdb->posts}.ID = skyean_sku_search.post_id AND skyean_sku_search.meta_key = '_sku')";
			return $join;
		};

		$this->search_filters['where'] = function ( $where ) use ( $search_term ) {
			global $wpdb;
			$like   = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where .= $wpdb->prepare(
				" AND ({$wpdb->posts}.post_title LIKE %s OR skyean_sku_search.meta_value LIKE %s)",
				$like,
				$like
			);
			return $where;
		};

		$this->search_filters['groupby'] = function () {
			global $wpdb;
			return "{$wpdb->posts}.ID";
		};

		add_filter( 'posts_join', $this->search_filters['join'] );
		add_filter( 'posts_where', $this->search_filters['where'] );
		add_filter( 'posts_groupby', $this->search_filters['groupby'] );
	}

	/**
	 * Remove search filters safely (only our own).
	 *
	 * @return void
	 */
	private function remove_search_filters() {
		if ( ! empty( $this->search_filters['join'] ) ) {
			remove_filter( 'posts_join', $this->search_filters['join'] );
		}
		if ( ! empty( $this->search_filters['where'] ) ) {
			remove_filter( 'posts_where', $this->search_filters['where'] );
		}
		if ( ! empty( $this->search_filters['groupby'] ) ) {
			remove_filter( 'posts_groupby', $this->search_filters['groupby'] );
		}
		$this->search_filters = array();
	}

	/**
	 * AJAX handler: Search barcode for a product.
	 *
	 * @return void
	 */
	public function ajax_search_barcode() {
		check_ajax_referer( 'skyean_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'skyservers-ean-barcode-finder' ) );
		}

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';

		if ( ! $product_id || empty( $product_name ) ) {
			wp_send_json_error( esc_html__( 'Missing data.', 'skyservers-ean-barcode-finder' ) );
		}

		$results = $this->search_ean( $product_name );

		wp_send_json_success(
			array(
				'product_id' => $product_id,
				'results'    => $results,
			)
		);
	}

	/**
	 * Search EAN across multiple sources.
	 *
	 * @param string $product_name Product name to search.
	 * @return array Array of results.
	 */
	private function search_ean( $product_name ) {
		$results = array();

		// 1. Open Food Facts.
		$off_results = $this->search_open_food_facts( $product_name );
		$results     = array_merge( $results, $off_results );

		// 2. UPCitemdb (if API key available).
		$api_key = get_option( self::OPTION_API_KEY, '' );
		if ( ! empty( $api_key ) ) {
			$upc_results = $this->search_upcitemdb( $product_name, $api_key );
			$results     = array_merge( $results, $upc_results );
		}

		// Deduplicate by EAN.
		$unique = array();
		foreach ( $results as $r ) {
			$unique[ $r['ean'] ] = $r;
		}

		return array_values( $unique );
	}

	/**
	 * Search Open Food Facts API.
	 *
	 * @param string $query Search query.
	 * @return array Results.
	 */
	private function search_open_food_facts( $query ) {
		$url = add_query_arg(
			array(
				'search_terms'  => $query,
				'search_simple' => 1,
				'action'        => 'process',
				'json'          => 1,
				'page_size'     => 5,
				'fields'        => 'code,product_name,brands,image_small_url',
			),
			'https://world.openfoodfacts.org/cgi/search.pl'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'SkyServersEanBarcodeFinder/' . SKYEAN_VERSION . ' (WordPress Plugin)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['products'] ) ) {
			return array();
		}

		$results = array();
		foreach ( $body['products'] as $product ) {
			if ( empty( $product['code'] ) || strlen( $product['code'] ) < 8 ) {
				continue;
			}

			$results[] = array(
				'ean'    => sanitize_text_field( $product['code'] ),
				'name'   => isset( $product['product_name'] ) ? sanitize_text_field( $product['product_name'] ) : '',
				'brand'  => isset( $product['brands'] ) ? sanitize_text_field( $product['brands'] ) : '',
				'source' => 'Open Food Facts',
				'image'  => isset( $product['image_small_url'] ) ? esc_url_raw( $product['image_small_url'] ) : '',
			);
		}

		return $results;
	}

	/**
	 * Search UPCitemdb API.
	 *
	 * @param string $query   Search query.
	 * @param string $api_key API key.
	 * @return array Results.
	 */
	private function search_upcitemdb( $query, $api_key ) {
		$response = wp_remote_post(
			'https://api.upcitemdb.com/prod/trial/search',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'user_key'     => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						's'    => $query,
						'type' => 'product',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['items'] ) ) {
			return array();
		}

		$results = array();
		foreach ( $body['items'] as $item ) {
			if ( empty( $item['ean'] ) ) {
				continue;
			}

			$results[] = array(
				'ean'    => sanitize_text_field( $item['ean'] ),
				'name'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
				'brand'  => isset( $item['brand'] ) ? sanitize_text_field( $item['brand'] ) : '',
				'source' => 'UPCitemdb',
				'image'  => ! empty( $item['images'] ) ? esc_url_raw( $item['images'][0] ) : '',
			);
		}

		return array_slice( $results, 0, 5 );
	}

	/**
	 * AJAX handler: Save barcode to product.
	 *
	 * @return void
	 */
	public function ajax_save_barcode() {
		check_ajax_referer( 'skyean_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'skyservers-ean-barcode-finder' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$ean        = isset( $_POST['ean'] ) ? sanitize_text_field( wp_unslash( $_POST['ean'] ) ) : '';

		if ( ! $product_id ) {
			wp_send_json_error( esc_html__( 'Missing product ID.', 'skyservers-ean-barcode-finder' ) );
		}

		// Validate barcode (EAN-8, UPC-12, EAN-13).
		if ( ! empty( $ean ) && ! self::validate_barcode( $ean ) ) {
			wp_send_json_error(
				esc_html__( 'Invalid barcode. Accepted: EAN-8 (8 digits), UPC (12 digits), EAN-13 (13 digits).', 'skyservers-ean-barcode-finder' )
			);
		}

		// Save using WooCommerce product API.
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( esc_html__( 'Product not found.', 'skyservers-ean-barcode-finder' ) );
		}

		if ( method_exists( $product, 'set_global_unique_id' ) ) {
			$product->set_global_unique_id( $ean );
			$product->save();
		} else {
			update_post_meta( $product_id, self::META_KEY, $ean );
		}

		// Invalidate stats cache.
		wp_cache_delete( 'skyean_products_with_ean', 'skyservers-ean-barcode-finder' );

		wp_send_json_success(
			array(
				'product_id' => $product_id,
				'ean'        => $ean,
			)
		);
	}

	/**
	 * Validate barcode checksum (EAN-8, UPC-12, EAN-13).
	 *
	 * @param string $code Barcode to validate.
	 * @return bool True if valid.
	 */
	public static function validate_barcode( $code ) {
		if ( ! preg_match( '/^\d{8}$|^\d{12}$|^\d{13}$/', $code ) ) {
			return false;
		}

		$len = strlen( $code );
		$sum = 0;

		for ( $i = 0; $i < $len - 1; $i++ ) {
			$digit = (int) $code[ $i ];
			if ( 12 === $len ) {
				$sum += $digit * ( 0 === $i % 2 ? 3 : 1 );
			} else {
				$sum += $digit * ( 0 === $i % 2 ? 1 : 3 );
			}
		}

		$check = ( 10 - ( $sum % 10 ) ) % 10;
		return $check === (int) $code[ $len - 1 ];
	}
}

// Initialize.
SkyServers_EAN_Barcode_Finder::get_instance();
