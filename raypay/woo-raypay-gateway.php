<?php
/**
 * Plugin Name: RayPay payment gateway for Woocommerce
 * Author: SaminRay
 * Description: <a href="https://raypay.ir">RayPay</a> secure payment gateway for Woocommerce.
 * Version: 1.0
 * Author URI: https://saminray.com
 * Author Email: info@saminray.com
 * Text Domain: woo-raypay-gateway
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load plugin textdomain.
 *
 * @since 1.0
 */
function woo_raypay_gateway_load_textdomain() {
	load_plugin_textdomain( 'woo-raypay-gateway', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'woo_raypay_gateway_load_textdomain' );

require_once( plugin_dir_path( __FILE__ ) . 'includes/wc-gateway-raypay-helpers.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/wc-gateway-raypay-init.php' );
