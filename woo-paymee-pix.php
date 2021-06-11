<?php
/**
 * Plugin Name:  PayMee Pix For WooCommerce
 * Plugin URI:   https://www.paymee.com.br/
 * Description:  Plugin WooCommerce para receber seus pagamentos via PIX.
 * Author:       Raioweb.com.br | Albreis - Design & Programação
 * Author URI:   https://github.com/paymeebrasil/woocommerce-paymee
 * Requires PHP: 7.2
 * Version:      1.0.0
 * License:      GPLv2 or later
 * Text Domain:  woo-paymee-pix
 * @package Woo_PayMee_Pix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Woo_PayMee_Pix' ) ) :

	/**
	 * WooCommerce PayMee main class.
	 */
	class WC_Woo_PayMee_Pix {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.1.0';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin public actions.
		 */
		private function __construct() {
			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$this->includes();

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_filter( 'woocommerce_available_payment_gateways', array( $this, 'hides_when_is_outside_brazil' ) );
				add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'stop_cancel_unpaid_orders' ), 10, 2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

				if ( is_admin() ) {
					add_action( 'admin_notices', array( $this, 'ecfb_missing_notice' ) );
				}
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Get templates path.
		 *
		 * @return string
		 */
		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		/**
		 * Action links.
		 *
		 * @param array $links Action links.
		 *
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links   = array();
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymee_pix' ) ) . '">' . __( 'Settings', 'woo-paymee' ) . '</a>';

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Includes.
		 */
		private function includes() {
			include_once dirname( __FILE__ ) . '/includes/class-wc-paymee-pix-api.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-paymee-pix-gateway.php';
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param  array $methods WooCommerce payment methods.
		 *
		 * @return array          Payment methods with PayMee.
		 */
		public function add_gateway( $methods ) {
			$methods[] = 'WC_Woo_PayMee_Pix_Gateway';
			return $methods;
		}

		/**
		 * Hides the PayMee with payment method with the customer lives outside Brazil.
		 *
		 * @param   array $available_gateways Default Available Gateways.
		 *
		 * @return  array                     New Available Gateways.
		 */
		public function hides_when_is_outside_brazil( $available_gateways ) {
			if ( isset( $_REQUEST['country'] ) && 'BR' !== $_REQUEST['country'] ) {
				unset( $available_gateways['paymee_pix'] );
			}
			return $available_gateways;
		}

		/**
		 * Stop cancel unpaid PayMee orders.
		 *
		 * @param  bool     $cancel Check if need cancel the order.
		 * @param  WC_Order $order  Order object.
		 *
		 * @return bool
		 */
		public function stop_cancel_unpaid_orders( $cancel, $order ) {
			$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

			if ( 'paymee_pix' === $payment_method ) {
				return false;
			}
			return $cancel;
		}

		/**
		 * WooCommerce Extra Checkout Fields for Brazil notice.
		 */
		public function ecfb_missing_notice() {
			$settings = get_option( 'woocommerce_paymee_pix_settings', array( 'method' => '' ) );

			if (!class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
				include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-ecfb.php';
			}
		}

		/**
		 * WooCommerce missing notice.
		 */
		public function woocommerce_missing_notice() {
			include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-woocommerce.php';
		}

	}

	add_action( 'plugins_loaded', array( 'WC_Woo_PayMee_Pix', 'get_instance' ) );

endif;

function pix_reload() {
	$id = isset($_REQUEST['param']) ? trim($_REQUEST['param']) : "";
	$status = 0;
	if(!empty($id)){
		$order = new WC_Order($id);
		$status = $order->get_status();
	}
	wp_send_json($status);
	wp_die();
}

add_action('wp_ajax_pix_reload', 'pix_reload');
add_action('wp_ajax_nopriv_pix_reload', 'pix_reload');