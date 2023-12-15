<?php
/**
 * Plugin Name: IDPay payment gateway for Woocommerce
 * Author: IDPay
 * Description: <a href="https://idpay.ir">IDPay</a> secure payment gateway for Woocommerce.
 * Version: 2.2.5
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Text Domain: woo-idpay-gateway
 * Domain Path: /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to: 7.2
 */



if (! defined('ABSPATH')) {
    exit;
}

function woo_idpay_gateway_load()
{
    $realPath = basename(dirname(__FILE__)) . '/languages';
    load_plugin_textdomain('woo-idpay-gateway', false, $realPath);
}

function checkEnabledHPOS()
{
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil :: class)) {
        $featureId = 'custom_order_tables';
        $f = __FILE__;
        $bool= true;
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility($featureId, $f, $bool);
    }
}

add_action('before_woocommerce_init', 'checkEnabledHPOS');
add_action('init', 'woo_idpay_gateway_load');


require_once(plugin_dir_path(__FILE__) . 'includes/IdOrder.php');
require_once(plugin_dir_path(__FILE__) . 'includes/wc-gateway-idpay-init.php');
