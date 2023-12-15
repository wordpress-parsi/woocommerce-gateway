<?php

/*
Plugin Name: sepal
Version: 1.0
Description:  sepal-woocommerce-desc
Plugin URI: https://sepal.ir/plugins
Author: Sepal
Author URI: https://sepal.ir
Text Domain: sepal
Domain Path: /languages
*/

load_plugin_textdomain('sepal-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
include_once("gateway-sepal.php");
