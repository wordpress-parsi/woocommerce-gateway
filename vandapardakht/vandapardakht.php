<?php

/*
 * Plugin Name: VandaPardakht Payment for WooCommerce
 * Plugin URI: https://www.vandapardakht.com
 * Description: With this Plugin, You can add VandaPardakht Online Payment Gateway to WooCommerce Checkout system and payment methods
 * Version: 1.0
 * Author: ماژول بانک
 * Author URI: https://www.modulebank.ir
 * Text Domain: vandapardakht-payment-for-woocommerce
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function() {
	load_plugin_textdomain('vandapardakht-payment-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
	if (!class_exists('WC_Payment_Gateway')) return;
	class WC_VandaPardakht extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'vandapardakht';
			$this->plugin_name = __('VandaPardakht Payment for WooCommerce', 'vandapardakht-payment-for-woocommerce');
			$this->plugin_desc = __('With this Plugin, You can add VandaPardakht Online Payment Gateway to WooCommerce Checkout system and payment methods', 'vandapardakht-payment-for-woocommerce');
			$this->method_title = __('VandaPardakht Online Payment Gateway', 'vandapardakht-payment-for-woocommerce');
			$this->icon = plugin_dir_url(__FILE__).'images/logo.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->pin = $this->settings['pin'];
			$this->msg_success = $this->settings['msg_success'];
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_vandapardakht_response'));
			add_action('valid-vandapardakht-request', array($this, 'successful_request'));
			add_action('woocommerce_update_options_payment_gateways_vandapardakht', array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_vandapardakht', array($this, 'receipt_page'));
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
						'title' => __('Enable / Disable', 'vandapardakht-payment-for-woocommerce'),
						'type' => 'checkbox',
						'label' => __('Enable or Disable This Payment Mehod', 'vandapardakht-payment-for-woocommerce'),
						'default' => 'yes'
					),
				'title' => array(
						'title' => __('Display Title', 'vandapardakht-payment-for-woocommerce'),
						'type'=> 'text',
						'description' => __('Display Title', 'vandapardakht-payment-for-woocommerce'),
						'default' => __('VandaPardakht Online Payment Gateway', 'vandapardakht-payment-for-woocommerce')
					),
				'description' => array(
						'title' => __('Payment Instruction', 'vandapardakht-payment-for-woocommerce'),
						'type' => 'textarea',
						'description' => __('Payment Instruction', 'vandapardakht-payment-for-woocommerce'),
						'default' => __('Pay by VandaPardakht Online Payment Gateway', 'vandapardakht-payment-for-woocommerce')
					),
				'pin' => array(
						'title' => __('VandaPardakht PIN', 'vandapardakht-payment-for-woocommerce'),
						'type' => 'text',
						'description' => __('Enter VandaPardakht PIN', 'vandapardakht-payment-for-woocommerce')
					),
				'msg_success' => array(
						'title' => __('Success Message', 'vandapardakht-payment-for-woocommerce'),
						'type' => 'text',
						'default' => __('Payment Completed', 'vandapardakht-payment-for-woocommerce'),
						'description' => __('Success Message. Ex : Payment Completed', 'vandapardakht-payment-for-woocommerce')
					),
				);
		}

		public function admin_options()
		{
			echo '<h3>'.__('VandaPardakht Online Payment Gateway', 'vandapardakht-payment-for-woocommerce').'</h3>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		function payment_fields()
		{
			if ($this->description) {
				echo esc_html($this->description);
			}
		}

		function receipt_page($order)
		{
			echo '<p>'.__('Thank you for your order. You will be redirect to the VandaPardakht Online Payment Gateway', 'vandapardakht-payment-for-woocommerce').'</p>';
			echo $this->generate_vandapardakht_form($order);
		}

		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
		}

		function check_vandapardakht_response()
		{
			if (isset($_GET['order_id']))
			{
				$order_id = sanitize_text_field($_GET['order_id']);
				$order = new WC_Order($order_id);
				if (!is_object($order))
				{
					$message = __('Error : Order Not Exists!', 'vandapardakht-payment-for-woocommerce');
				}
				elseif ($order->is_paid())
				{
					$message = __('Error : Order Already Paid!', 'vandapardakht-payment-for-woocommerce');
				}
				else
				{
					$amount = $order->order_total * (get_woocommerce_currency() == 'IRHT' ? 1000 : (get_woocommerce_currency() == 'IRT' ? 1 : 0.1));
					require_once(dirname(__FILE__) . '/vandapardakht_payment_helper.class.php');
					$v = new VandaPardakht_Payment_Helper($this->pin);
					$p = array(
							'price'     => intval($amount),
							'order_id'  => $order_id,
							'vprescode' => get_post_meta($order_id, 'vandapardakht_refid', true)
						);
					foreach ($_GET as $key => $value) {
						$p['bank_return'][$key] = sanitize_text_field($value);
					}
					foreach ($_POST as $key => $value) {
						$p['bank_return'][$key] = sanitize_text_field($value);
					}
					// ? $p['bank_return']['amount'] = $p['amount'];
					$r = $v->paymentVerify($p);
					if ($r)
					{
						$message = $this->msg_success . ' . ' . sprintf(__("RefrenceID : %s", 'vandapardakht-payment-for-woocommerce'), $v->txn_id);
						$order->payment_complete($v->txn_id);
						$order->add_order_note($message, true);
						WC()->cart->empty_cart();
						wc_add_notice($message, 'success');
						wp_redirect($order->get_view_order_url());
						exit;
					}
					else
					{
						$message = $v->error;
						$order->add_order_note($message, true);
					}
				}
			}
			else
			{
				$message = __('System (Permission) Error.', 'vandapardakht-payment-for-woocommerce');
			}
			if (isset($message) && $message) wc_add_notice($message, 'error');
			wp_redirect(WC()->cart->get_checkout_url());
			exit;
		}

		public function generate_vandapardakht_form($order_id)
		{
			$order = new WC_Order($order_id);
			require_once(dirname(__FILE__) . '/vandapardakht_payment_helper.class.php');
			$v = new VandaPardakht_Payment_Helper($this->pin);
			$r = $v->paymentRequest(array(
					'amount'      => intval($order->order_total * (get_woocommerce_currency() == 'IRHT' ? 1000 : (get_woocommerce_currency() == 'IRT' ? 1 : 0.1))),
					'order_id'    => $order_id,
					'name'        => $order->get_formatted_shipping_full_name(),
					'mobile'      => $order->get_billing_phone(),
					'email'       => $order->get_billing_email(),
					'description' => 'WC-Order#'.$order_id,
					'callback'    => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/'),
				));
			if ($r)
			{
				delete_post_meta($order_id, 'vandapardakht_refid');
				update_post_meta($order_id, 'vandapardakht_refid', $r);
				session_write_close();
				echo $v->data->form;
				echo '<script>document.payment.submit();</script>';
			}
			else
			{
				$order->add_order_note(sprintf(__("Erorr : %s", 'vandapardakht-payment-for-woocommerce'), $v->error));
				echo esc_html(sprintf(__("Erorr : %s", 'vandapardakht-payment-for-woocommerce'), $v->error));
			}
		}
	}

	add_action('admin_footer', function() {
		echo '<style>
			.vandapardakht-rate-stars{display:inline-block;color:#ffb900;position:relative;top:3px}
			.vandapardakht-rate-stars svg{fill:#ffb900}
			.vandapardakht-rate-stars svg:hover{fill:#ffb900}
			.vandapardakht-rate-stars svg:hover ~ svg{fill:none}
		</style>';
	}, 666);

	add_filter('plugin_row_meta' , function($meta_fields, $file) {
		if (plugin_basename(__FILE__) == $file) {
			$plugin_url = 'https://wordpress.org/support/plugin/vandapardakht-payment-for-woocommerce/reviews/?rate=5#new-post';
			$meta_fields[] = "<a href='".esc_url($plugin_url)."' target='_blank' title='" . esc_html__('Rate', 'vandapardakht-payment-for-woocommerce') . "'>
			<i class='vandapardakht-rate-stars'>"
			. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
			. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
			. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
			. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
			. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
			. "</i></a>";
		}
		return $meta_fields;
	}, 666, 2);

	add_action('admin_init', function() {
		if (get_option('vandapardakht_do_activation_redirect', false)) {
			delete_option('vandapardakht_do_activation_redirect');
			if (!isset($_GET['activate-multi'])) {
				wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=vandapardakht'));
			}
		}
	}, 666);

	add_filter('admin_footer_text', function() {
		$plugin_url = 'https://wordpress.org/support/plugin/vandapardakht-payment-for-woocommerce/reviews/?rate=5#new-post';
		$link = "<a href='".esc_url($plugin_url)."' target='_blank' title='" . esc_html__('Rate', 'vandapardakht-payment-for-woocommerce') . "'>
		<i class='vandapardakht-rate-stars'>"
		. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
		. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
		. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
		. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
		. "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>"
		. "</i></a>";
		return sprintf(__('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'vandapardakht-payment-for-woocommerce'),
			sprintf('<strong>%s</strong>', esc_html__('VandaPardakht Payment for WooCommerce', 'vandapardakht-payment-for-woocommerce')), $link);
	}, PHP_INT_MAX);

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
		return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=vandapardakht').'">'.__('Settings', 'vandapardakht-payment-for-woocommerce').'</a>'), $links);
	});

	add_filter('woocommerce_payment_gateways', function($methods) {
		$methods[] = 'WC_VandaPardakht';
		return $methods;
	});
}, 666);

register_activation_hook(__FILE__, function() {
	add_option('vandapardakht_do_activation_redirect', true);
});

?>