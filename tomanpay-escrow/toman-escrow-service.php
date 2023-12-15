<?php

/*
 * Plugin Name: TomanPay Escrow Service Payment Method for WooCommerce
 * Plugin URI: https://tomanpay.net
 * Description: TomanPay Escrow Service Payment Method for WooCommerce
 * Version: 1.0.1
 * Author: ماژول بانک
 * Author URI: https://www.modulebank.ir
 * Text Domain: toman-escrow-service-payment-for-woocommerce
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

add_action('woocommerce_init', function() {
	if (isset($_GET['tipgmsg'],$_GET['tipgmt'])) {
		$msg_type = sanitize_text_field($_GET['tipgmt']);
		$msg = sanitize_text_field($_GET['tipgmsg']);
		$msg = str_replace(' -::- ', '<br>', $msg);
		wc_add_notice($msg, $msg_type);
	}
}, 666);

if (!function_exists('woocommerce_toman_escrow_service_init')) {
	function woocommerce_toman_escrow_service_init()
	{
		load_plugin_textdomain('toman-escrow-service-payment-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
		if (!class_exists('WC_Payment_Gateway')) return;
		class WC_TomanEscrowService extends WC_Payment_Gateway
		{
			public function __construct()
			{
				$this->id = 'tomanescrowservice';
				$this->plugin_name = __('TomanPay Escrow Service Payment Method for WooCommerce', 'toman-escrow-service-payment-for-woocommerce');
				$this->method_title = __('TomanPay Escrow Service Payment Method', 'toman-escrow-service-payment-for-woocommerce');
				$this->icon = plugin_dir_url(__FILE__) . 'images/logo.png';
				$this->has_fields = false;
				$this->init_form_fields();
				$this->init_settings();
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];
				$this->shopslug = $this->settings['shopslug'];
				$this->password = $this->settings['password'];
				add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_tomanescrowservice_response'));
				add_action('valid-tomanescrowservice-request', array($this, 'successful_request'));
				add_action('woocommerce_update_options_payment_gateways_tomanescrowservice', array($this, 'process_admin_options'));
				add_action('woocommerce_receipt_tomanescrowservice', array($this, 'receipt_page'));
			}

			function init_form_fields()
			{
				$this->form_fields = array(
					'enabled'     => array(
						'title'   => __('Enable / Disable', 'toman-escrow-service-payment-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable or Disable This Payment Mehod', 'toman-escrow-service-payment-for-woocommerce'),
						'default' => 'yes'
					),
					'title'           => array(
						'title'       => __('Display Title', 'toman-escrow-service-payment-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Display Title', 'toman-escrow-service-payment-for-woocommerce'),
						'default'     => __('TomanPay Escrow Service Payment Method', 'toman-escrow-service-payment-for-woocommerce')
					),
					'description'     => array(
						'title'       => __('Payment Instruction', 'toman-escrow-service-payment-for-woocommerce'),
						'type'        => 'textarea',
						'description' => __('Payment Instruction', 'toman-escrow-service-payment-for-woocommerce'),
						'default'     => __('Pay via TomanPay Escrow Service Payment Method', 'toman-escrow-service-payment-for-woocommerce')
					),
					'shopslug'        => array(
						'title'       => __('TomanPay Escrow Service Shop Slug', 'toman-escrow-service-payment-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay Escrow Service Shop Slug', 'toman-escrow-service-payment-for-woocommerce')
					),
					'password'        => array(
						'title'       => __('TomanPay Escrow Service Password', 'toman-escrow-service-payment-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay Escrow Service Password', 'toman-escrow-service-payment-for-woocommerce')
					),
				);
			}

			public function admin_options()
			{
				echo '<h3>'.__('TomanPay Escrow Service Payment Method', 'toman-escrow-service-payment-for-woocommerce').'</h3>';
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function payment_fields()
			{
				if($this->description) echo wpautop(wptexturize($this->description));
			}

			function process_payment($order_id)
			{
				$order = new WC_Order($order_id);
				return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
			}

			function receipt_page($order_id)
			{
				$order = new WC_Order($order_id);
				$currency_rate = get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1);
				$total = 0;
				$products = array();
				foreach ($order->get_items() as $item_key => $product_item)
				{
					$product = $product_item->get_product();
					$total += $product_item->get_total();
					$products[] = array(
							'name'        => $product_item->get_name(),
							//'slug'        => $product->get_ID(),
							'price'       => $product_item->get_total() * $currency_rate / $product_item->get_quantity(), // $product->get_price()
							'quantity'    => $product_item->get_quantity()
						);
				}
				if ($shipping_price = $order->get_shipping_total())
				{
					$total += $shipping_price;
					$products[] = array(
							'name'        => __('Shipping', 'toman-escrow-service-payment-for-woocommerce'),
							//'slug'        => __('shippingprice', 'toman-escrow-service-payment-for-woocommerce'),
							'price'       => $order->get_shipping_total() * $currency_rate,
							'quantity'    => 1
						);
				}
				$other_fees = $order->get_total() - $total;
				if ($other_fees)
				{
					$products[] = array(
							'name'        => __('Other', 'toman-escrow-service-payment-for-woocommerce'),
							//'slug'        => __('otherfees', 'toman-escrow-service-payment-for-woocommerce'),
							'price'       => $other_fees * $currency_rate,
							'quantity'    => 1
						);
				}
				$url = 'https://escrow-api.toman.ir/api/v1/users/me/shops/'. $this->shopslug .'/deals';
				$post = array(
						'res_number'  => $order_id,
						'items'       => $products,
						'return_to'   => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/'),
					);
				$result = wp_remote_post($url, array('body' => wp_json_encode($post), 'headers' => $h = array('Authorization' => 'Basic '.base64_encode($this->shopslug.':'.$this->password), 'Plugin-Version' => 'WordPress#WooCommerce', 'Content-Type' => 'application/json', 'Accept' => 'application/json'), 'sslverify' => false));
				if (is_wp_error($result))
				{
					$order->add_order_note(__('Erorr', 'toman-escrow-service-payment-for-woocommerce') . ' : ' . $result->get_error_message());
					echo esc_html($result->get_error_message());
				}
				else
				{
					$deal = json_decode($result['body']);
					if (is_object($deal) && isset($deal->trace_number))
					{
						$trace_number = $deal->trace_number;
						//?$res_number = $deal->res_number;
						//?$lead_time = $deal->lead_time;
						$payment_url = 'https://escrow.toman.ir/basket-info?dealid=' . $trace_number;
						update_post_meta($order_id, 'tomanescrowservice_trace_number', $trace_number);
						session_write_close();
						echo '<p>'.__('thank you for your order. you are redirecting to TomanPay Escrow Service gateway. please wait', 'toman-escrow-service-payment-for-woocommerce').'</p>';
						echo '<a href="'.esc_url($payment_url).'">'.__('Pay', 'toman-escrow-service-payment-for-woocommerce').'</a>';
						echo '<script>document.location = "'.esc_url($payment_url).'";</script>';
					}
					else
					{
						$message = __('Erorr Connecting TomanPay Escrow Service gateway', 'toman-escrow-service-payment-for-woocommerce');
						$order->add_order_note($message);
						echo esc_html($message);
					}
				}
			}

			function check_tomanescrowservice_response()
			{
				if (isset($_POST['state'],$_POST['res_number'],$_POST['trace_number'],$_POST['payable_amount']))
				{
					$state = sanitize_text_field($_POST['state']);
					$order_id = sanitize_text_field($_POST['res_number']);
					$trace_number = sanitize_text_field($_POST['trace_number']);
					$payable_amount = sanitize_text_field($_POST['payable_amount']);
					$order = new WC_Order($order_id);
					if (!($order && is_object($order)))
					{
						$message = __('Error : Order Not Exists!', 'toman-escrow-service-payment-for-woocommerce');
					}
					elseif($order->is_paid())
					{
						$message = __('Error : Order Already Paid!', 'toman-escrow-service-payment-for-woocommerce');
					}
					elseif ($state != 'funded')
					{
						$message = sprintf(__('Payment Cancelled By User (state : %s)', 'toman-escrow-service-payment-for-woocommerce'), $state);
						$order->add_order_note($message);
					}
					else
					{
						$deal_trace_number = get_post_meta($order_id, 'tomanescrowservice_trace_number', true);
						$amount = $order->get_total() * (get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1));
						if ($payable_amount >= $amount)
						{
							$url = 'https://escrow-api.toman.ir/api/v1/users/me/shops/' . $this->shopslug . '/deals/' . $deal_trace_number . '/verify';
							$result = wp_remote_request($url, array('method' => 'PATCH', 'headers' => $h = array('Authorization' => 'Basic '.base64_encode($this->shopslug.':'.$this->password), 'Content-Type' => 'application/json', 'Accept' => 'application/json'), 'sslverify' => false));
							if (is_wp_error($result))
							{
								$message = __('Erorr Connecting TomanPay Escrow Service gateway', 'toman-escrow-service-payment-for-woocommerce');
								$order->add_order_note($message);
							}
							else
							{
								$deal = json_decode($result['body']);
								if (wp_remote_retrieve_response_code($result) == 204)
								{
									update_post_meta($order->get_ID(), 'tomanescrowservice_verify_tries', 2);
									$message = sprintf(__('Payment Completed. PaymentRefrenceID : %s', 'toman-escrow-service-payment-for-woocommerce'), $trace_number);
									$order->payment_complete($trace_number);
									$order->add_order_note($message);
									WC()->cart->empty_cart();
									wc_add_notice($message, 'success');
									wp_redirect(add_query_arg(array('wc_status'=>'success', 'tipgmsg'=>$message, 'tipgmt'=>'success'), $this->get_return_url($order)));
									exit;
								}
								else
								{
									$message = __('Erorr Invalid Response from TomanPay Escrow Service gateway', 'toman-escrow-service-payment-for-woocommerce');
									$order->add_order_note($message);
									$message = __('Payment Failed or Cancelled by user', 'toman-escrow-service-payment-for-woocommerce');
									$message .= '<br>' . $result['body'];
									$order->add_order_note($message);
								}
							}
						}
						else
						{
							$message = sprintf(__('Paid Price (%s) and Order Price (%s) are not the same', 'toman-escrow-service-payment-for-woocommerce'), number_format($payable_amount), number_format($amount));
							$order->add_order_note($message);
							if ($order->get_status() != 'pending')
							{
								$order->update_status('on-hold');
							}
						}
					}
				}
				else
				{
					$message = __('System (Permission) Error!', 'toman-escrow-service-payment-for-woocommerce');
				}
				if (isset($message) && $message) wc_add_notice($message, 'error');
				$url = wc_get_checkout_url();
				if (isset($message) && $message) {
					wc_add_notice($message, 'error');
					$url = add_query_arg(array('tipgmsg'=>str_replace('<br>', ' -::- ', $message), 'tipgmt'=>'error'), $url);
				}
				wp_redirect($url);
				exit;
			}
		}

		add_action('admin_init', function() {
			if (get_option('tomanescrowservice_do_activation_redirect', false)) {
				delete_option('tomanescrowservice_do_activation_redirect');
				if (!isset($_GET['activate-multi'])) {
					wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=tomanescrowservice'));
				}
			}
		}, 666);

		add_action('admin_footer', function() {
			echo '<style type="text/css" media="screen">
				.toman-escrow-service-payment-rate-stars{display:inline-block;color:#ffb900;position:relative;top:3px;}
				.toman-escrow-service-payment-rate-stars svg{fill:#ffb900;}
				.toman-escrow-service-payment-rate-stars svg:hover{fill:#ffb900}
				.toman-escrow-service-payment-rate-stars svg:hover ~ svg{fill:none;}
			</style>';
		});

		add_filter('plugin_row_meta' , function($meta_fields, $file) {
			if (plugin_basename(__FILE__) == $file) {
				$plugin_url = 'https://wordpress.org/support/plugin/tomanpay-escrow-service-payment-method-for-woocommerce/reviews/?rate=5#new-post';
				$meta_fields[] = '<a href="' . esc_url($plugin_url) .'" target="_blank" title="' . __('Rate This Plugin', 'toman-escrow-service-payment-for-woocommerce') . '">
				<i class="toman-escrow-service-payment-rate-stars">'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-star"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
				. '</i></a>';
			}
			return $meta_fields;
		}, 10, 2);

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
			return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=tomanescrowservice').'">'.__('Settings', 'toman-escrow-service-payment-for-woocommerce').'</a>'), $links);
		});

		add_filter('woocommerce_payment_gateways', function($methods) {
			$methods[] = 'WC_TomanEscrowService';
			return $methods;
		});
	}
	add_action('plugins_loaded', 'woocommerce_toman_escrow_service_init', 666);
}

register_activation_hook(__FILE__, function() {
	add_option('tomanescrowservice_do_activation_redirect', true);
});

?>