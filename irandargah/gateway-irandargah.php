<?php

/**
 * Plugin Name: IranDargah Payment Gateway for WooCommerce
 * Plugin URI: https://irandargah.com
 * Description: IPG for woocommerce with IranDargah
 * Author: IranDargah
 * Version: 2.2
 * Requires at least: 6.2
 * Tested up to: 6.4
 * WC tested up to: 8.3
 * WC requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: irandargah-woocommerce-ipg
 *
 */

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

defined('ABSPATH') || exit;

define('WC_GATEWAY_IRANDARGAH_VERSION', '2.2');
define('WC_GATEWAY_IRANDARGAH_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_GATEWAY_IRANDARGAH_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

/**
 * Initialize the gateway.
 * @since 2.0.0
 */
function woocommerce_irandargah_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_basename('includes/class-wc-gateway-irandargah.php');
    load_plugin_textdomain('irandargah-woocommerce-ipg', false, trailingslashit(dirname(plugin_basename(__FILE__))) . '/languages/');
    add_filter('woocommerce_payment_gateways', 'woocommerce_irandargah_add_gateway');
}
add_action('plugins_loaded', 'woocommerce_irandargah_init', 0);

function woocommerce_irandargah_plugin_links($links)
{
    $settings_url = add_query_arg(
        array(
            'page'    => 'wc-settings',
            'tab'     => 'checkout',
            'section' => 'wc_gateway_irandargah',
        ),
        admin_url('admin.php')
    );

    $plugin_links = array(
        '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'irandargah-woocommerce-ipg') . '</a>',
        '<a href="https://docs.irandargah.com">' . esc_html__('Docs', 'irandargah-woocommerce-ipg') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_irandargah_plugin_links');

/**
 * Add the gateway to WooCommerce
 * @since 2.0.0
 */
function woocommerce_irandargah_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_IranDargah';
    return $methods;
}
add_action('woocommerce_blocks_loaded', 'woocommerce_irandargah_woocommerce_blocks_support');


function woocommerce_irandargah_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/includes/class-wc-gateway-irandargah-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_IranDargah_Blocks_Support);
            }
        );
    }
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_irandargah_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'woocommerce_irandargah_declare_hpos_compatibility');

/**
 * Display notice if WooCommerce is not installed.
 *
 */
function woocommerce_irandargah_missing_wc_notice()
{
    if (class_exists('WooCommerce')) {
        // Display nothing if WooCommerce is installed and activated.
        return;
    }

    echo '<div class="error"><p><strong>';
    echo sprintf(
        /* translators: %s WooCommerce download URL link. */
        esc_html__('IranDargah Payment Gateway for WooCommerce requires WooCommerce to be installed and active. You can download %s here.', 'irandargah-woocommerce-ipg'),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    );
    echo '</strong></p></div>';
}
add_action('admin_notices', 'woocommerce_irandargah_missing_wc_notice');