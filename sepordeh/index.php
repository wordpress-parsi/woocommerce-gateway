<?php
/*
Plugin Name: Sepordeh Payment Gateway for WooCommerce
Version: 1.4.0
Description:  This plugin adds <a href="https://sepordeh.com">Sepordeh</a> payment method for WooCommerce
Plugin URI: https://wordpress.org/plugins/sepordeh-woocommerce
Author: Sepordeh.com
Author URI: http://www.Sepordeh.com/
*/

function sepordeh_woocommerce_load_textdomain() {
	load_plugin_textdomain( 'sepordeh-woocommerce', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'sepordeh_woocommerce_load_textdomain' );
include_once("class-gateway-sepordeh.php");
