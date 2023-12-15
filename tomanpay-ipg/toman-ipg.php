<?php

/*
 * Plugin Name: TomanPay IPG Method for WooCommerce
 * Plugin URI: https://tomanpay.net
 * Description: TomanPay IPG Method for WooCommerce
 * Version: 1.0
 * Author: ماژول بانک
 * Author URI: https://www.modulebank.ir
 * Text Domain: toman-ipg-for-woocommerce
 * Domain Path: /languages
 */

add_action('woocommerce_init', function() {
	if (isset($_GET['tipgmsg'],$_GET['tipgmt'])) {
		$msg_type = sanitize_text_field($_GET['tipgmt']);
		$msg = sanitize_text_field($_GET['tipgmsg']);
		$msg = str_replace(' -::- ', '<br>', $msg);
		wc_add_notice($msg, $msg_type);
	}
}, 666);

if (!function_exists('woocommerce_toman_ipg_init')) {
	function woocommerce_toman_ipg_init() {
		load_plugin_textdomain('toman-ipg-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
		if (!class_exists('WC_Payment_Gateway')) return;
		class WC_TomanIPG extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'tomanipg';
				$this->now = time();
				$this->plugin_name = __('TomanPay IPG Method for WooCommerce', 'toman-ipg-for-woocommerce');
				$this->method_title = __('TomanPay IPG Method', 'toman-ipg-for-woocommerce');
				$this->icon = plugin_dir_url(__FILE__) . 'images/logo.png';
				$this->has_fields = false;
				$this->init_form_fields();
				$this->init_settings();
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];
				$this->username = $this->settings['username'];
				$this->password = $this->settings['password'];
				$this->clientid = $this->settings['clientid'];
				$this->clientsc = $this->settings['clientsc'];
				$this->staging  = $this->settings['staging'];
				add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_tomanipg_response'));
				add_action('valid-tomanipg-request', array($this, 'successful_request'));
				add_action('woocommerce_update_options_payment_gateways_tomanipg', array($this, 'process_admin_options'));
				add_action('woocommerce_receipt_tomanipg', array($this, 'receipt_page'));
			}

			function init_form_fields() {
				$this->form_fields = array(
					'enabled'     => array(
						'title'   => __('Enable / Disable', 'toman-ipg-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable or Disable This Payment Mehod', 'toman-ipg-for-woocommerce'),
						'default' => 'yes'
					),
					'title'           => array(
						'title'       => __('Display Title', 'toman-ipg-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Display Title', 'toman-ipg-for-woocommerce'),
						'default'     => __('TomanPay IPG', 'toman-ipg-for-woocommerce')
					),
					'description'     => array(
						'title'       => __('Payment Instruction', 'toman-ipg-for-woocommerce'),
						'type'        => 'textarea',
						'description' => __('Payment Instruction', 'toman-ipg-for-woocommerce'),
						'default'     => __('Pay via TomanPay IPG', 'toman-ipg-for-woocommerce')
					),
					'username'        => array(
						'title'       => __('TomanPay IPG Username', 'toman-ipg-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay IPG Username', 'toman-ipg-for-woocommerce')
					),
					'password'        => array(
						'title'       => __('TomanPay IPG Password', 'toman-ipg-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay IPG Password', 'toman-ipg-for-woocommerce')
					),
					'clientid'        => array(
						'title'       => __('TomanPay IPG Client ID', 'toman-ipg-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay IPG Client ID', 'toman-ipg-for-woocommerce')
					),
					'clientsc'        => array(
						'title'       => __('TomanPay IPG Client Secret', 'toman-ipg-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter TomanPay IPG Client Secret', 'toman-ipg-for-woocommerce')
					),
					'staging'     => array(
						'title'   => __('Enable Test Mode', 'toman-ipg-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable Staging Mode', 'toman-ipg-for-woocommerce'),
						'default' => 'no'
					),
				);
			}

			public function admin_options() {
				echo '<h3>'.__('TomanPay IPG Method', 'toman-ipg-for-woocommerce').'</h3>';
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function payment_fields() {
				if($this->description) echo wpautop(wptexturize($this->description));
			}

			function process_payment($order_id) {
				$order = new WC_Order($order_id);
				return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
			}

			function get_error($errors) {
				$output = '';
				if (is_string($errors)) {
					$output = $errors . '<br>';
				} elseif(is_array($errors)) {
					foreach ($errors as $error) {
						if (is_string($error)) {
							$output .= $error . '<br>';
						} elseif (is_array($error) && isset($err['description'])) {
							$output .= $err['description'] . '<br>';
						} elseif (is_array($error)) {
							foreach ($error as $err) {
								if (is_string($err)) {
									$output .= $err . '<br>';
								} elseif (is_array($err) && isset($err['description'])) {
									$output .= $err['description'] . '<br>';
								}
							}
						}
					}
				}
				return $output ?: 'UnExpected Error!';
			}

			function token() {
				if (get_option('tomanipg_token_time') > $this->now && $tmp = get_option('tomanipg_token_access')) {
					return array(true, $tmp);
				}
				if ($this->staging == 'yes') {
					$url = 'https://auth.qbitpay.org/oauth2/token/';
				} else {
					$url = 'https://accounts.qbitpay.org/oauth2/token/';
				}
				$body = array(
						'grant_type'    => 'password',
						'client_id'     => $this->clientid,
						'client_secret' => $this->clientsc,
						'username'      => $this->username,
						'password'      => $this->password,
						'scope'         => 'payment.create'
					);
				$header = array(
						'Content-Type' => 'application/x-www-form-urlencoded'
					);
				$result = wp_remote_post($url, array('body' => http_build_query($body), 'headers' => $header, 'sslverify' => false));
				if (is_wp_error($result)) {
					return array(false, $result->get_error_message());
				} else {
					$token = json_decode($result['body']);
					if (is_object($token) && isset($token->access_token, $token->expires_in)) {
						update_option('tomanipg_token_access', $token->access_token);
						update_option('tomanipg_token_refresh', $token->refresh_token);
						update_option('tomanipg_token_time', $this->now + $token->expires_in);
						return array(true, $token->access_token);
					} else {
						return array(false, __('Erorr Connecting TomanPay IPG OAuth Token Service', 'toman-ipg-for-woocommerce'));
					}
				}
			}

			function receipt_page($order_id) {
				$token = $this->token();
				if ($token[0] === true) {
					$order = new WC_Order($order_id);
					$currency_rate = get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1);
					if ($this->staging == 'yes') {
						$url = 'https://ipg-staging.toman.ir';
					} else {
						$url = 'https://ipg.toman.ir';
					}
					$body = array(
							'amount'       => intval(ceil($order->get_total() * $currency_rate)),
							'tracker_id'   => $order_id,
							'callback_url' => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/')
						);
					$header = array(
							'Authorization' => 'Bearer ' . $token[1],
							'Content-Type'  => 'application/json',
							'Accept'        => 'application/json'
						);
					$result = wp_remote_post($url.'/payments', array('body' => wp_json_encode($body), 'headers' => $header, 'sslverify' => false));
					if (is_wp_error($result)) {
						$message = __('Erorr', 'toman-ipg-for-woocommerce') . ' : ' . $result->get_error_message();
						$order->add_order_note($message);
						echo '<div class="woocommerce-error" style="padding:10px;margin:10px">' . nl2br(esc_html(str_replace('<br>', "\r\n", $message))) . '</div>';
					} else {
						$payment = json_decode($result['body'], true);
						if (is_array($payment) && isset($payment['uuid'], $payment['tracker_id'])) {
							$uuid = $payment['uuid'];
							$payment_url = $url . '/payments/' . $uuid . '/redirect';
							update_post_meta($order_id, 'tomanipg_uuid', $uuid);
							session_write_close();
							echo '<p>'.__('thank you for your order. you are redirecting to TomanPay online payemnt gateway (toman.ir). please wait', 'toman-ipg-for-woocommerce').'</p>';
							echo '<a href="'.esc_url($payment_url).'">'.__('Pay', 'toman-ipg-for-woocommerce').'</a>';
							echo '<script>document.location = "'.esc_url($payment_url).'";</script>';
						} else {
							update_post_meta($order_id, 'tomanipg_payment_error', $payment);
							$message = __('Erorr Connecting TomanPay IPG Payments Service', 'toman-ipg-for-woocommerce');
							$message .= '<br>' . $this->get_error($payment);
							$order->add_order_note($message);
							echo '<div class="woocommerce-error" style="padding:10px;margin:10px">' . nl2br(esc_html(str_replace('<br>', "\r\n", $message))) . '</div>';
						}
					}
				} else {
					$message = __('Erorr', 'toman-ipg-for-woocommerce') . ' : ' . $token[1];
					$order->add_order_note($message);
					echo '<div class="woocommerce-error" style="padding:10px;margin:10px">' . nl2br(esc_html(str_replace('<br>', "\r\n", $message))) . '</div>';
				}
			}

			function check_tomanipg_response() {
				if (isset($_GET['order_id'])) {
					$order_id = sanitize_text_field($_GET['order_id']);
				} elseif (isset($_POST['tracker_id'])) {
					$order_id = sanitize_text_field($_POST['tracker_id']);
				} else {
					$order_id = 0;
				}
				$order = new WC_Order($order_id);
				if (!($order && is_object($order))) {
					$message = __('Error : Order Not Exists!', 'toman-ipg-for-woocommerce');
				} elseif($order->is_paid()) {
					$message = __('Error : Order Already Paid!', 'toman-ipg-for-woocommerce');
				} else {
					$token = $this->token();
					if ($token[0] === true) {
						$currency_rate = get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1);
						if ($this->staging == 'yes') {
							$url = 'https://ipg-staging.toman.ir';
						} else {
							$url = 'https://ipg.toman.ir';
						}
						$uuid = get_post_meta($order_id, 'tomanipg_uuid', true);
						$header = array(
								'Authorization' => 'Bearer ' . $token[1],
							);
						$result = wp_remote_post($url.'/payments/'.$uuid.'/verify', array('body' => '', 'headers' => $header, 'sslverify' => false));
						if (is_wp_error($result)) {
							$message = __('Erorr', 'toman-ipg-for-woocommerce') . ' : ' . $result->get_error_message();
							$order->add_order_note($message);
						} else {
							if (wp_remote_retrieve_response_code($result) == 204) {
								$message = sprintf(__('Payment Completed. PaymentRefrenceID : %s', 'toman-ipg-for-woocommerce'), $uuid);
								$order->payment_complete($uuid);
								$order->add_order_note($message);
								WC()->cart->empty_cart();
								wc_add_notice($message, 'success');
								wp_redirect(add_query_arg(array('wc_status'=>'success', 'tipgmsg'=>$message, 'tipgmt'=>'success'), $this->get_return_url($order)));
								exit;
							} else {
								$message = __('Payment Failed or Cancelled by user', 'toman-ipg-for-woocommerce');
								$message .= '<br>' . $this->get_error(json_decode($result['body'], true));
								$order->add_order_note($message);
							}
						}
					} else {
						$message = __('Erorr', 'toman-ipg-for-woocommerce') . ' : ' . $token[1];
						$order->add_order_note($message);
					}
				}
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
			if (get_option('tomanipg_do_activation_redirect', false)) {
				delete_option('tomanipg_do_activation_redirect');
				if (!isset($_GET['activate-multi'])) {
					wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=tomanipg'));
				}
			}
		}, 666);

		add_action('admin_footer', function() {
			echo '<style type="text/css" media="screen">
				.toman-ipg-rate-stars{display:inline-block;color:#ffb900;position:relative;top:3px;}
				.toman-ipg-rate-stars svg{fill:#ffb900;}
				.toman-ipg-rate-stars svg:hover{fill:#ffb900}
				.toman-ipg-rate-stars svg:hover ~ svg{fill:none;}
			</style>';
		});

		add_filter('plugin_row_meta' , function($meta_fields, $file) {
			if (plugin_basename(__FILE__) == $file) {
				$plugin_url = 'https://wordpress.org/support/plugin/toman-ipg-for-woocommerce/reviews/?rate=5#new-post';
				$meta_fields[] = '<a href="' . esc_url($plugin_url) .'" target="_blank" title="' . __('Rate This Plugin', 'toman-ipg-for-woocommerce') . '">
				<i class="toman-ipg-rate-stars">'
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
			return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=tomanipg').'">'.__('Settings', 'toman-ipg-for-woocommerce').'</a>'), $links);
		});

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
			return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=tomanipg').'">'.__('Settings', 'toman-ipg-for-woocommerce').'</a>'), $links);
		});

		add_filter('woocommerce_payment_gateways', function($methods) {
			$methods[] = 'WC_TomanIPG';
			return $methods;
		});
	}
	add_action('plugins_loaded', 'woocommerce_toman_ipg_init', 666);
}

register_activation_hook(__FILE__, function() {
	add_option('tomanipg_do_activation_redirect', true);
});

?>