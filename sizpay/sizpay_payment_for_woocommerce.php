<?php

/*
Plugin Name: SizPay Payment Api for WooCommerce
Plugin URI: https://sizpay.ir
Description: <b> SizPay Payment Api for WooCommerce </b>
Version: 2.0
Author: sizpay Devops
Author URI: https://doc.sizpay.ir
 */

function woocommerce_sizpay_init()
{
	load_plugin_textdomain('sizpay-payment-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
	if (!class_exists('WC_Payment_Gateway')) return;
	class WC_SizPay extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'sizpay';
			$this->method_title = __('SizPay Payment Gateway', 'sizpay-payment-for-woocommerce');
			$this->icon = plugin_dir_url(__FILE__).'images/logo.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->sizPayKey = $this->settings['sizPayKey'];
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_sizpay_response'));
			add_action('valid-sizpay-request', array($this, 'successful_request'));
			add_action('woocommerce_update_options_payment_gateways_sizpay', array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_sizpay', array($this, 'receipt_page'));
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable / Disable', 'sizpay-payment-for-woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enable or Disable This Payment Mehod', 'sizpay-payment-for-woocommerce'),
					'default' => 'yes'),
				'title' => array(
					'title' => __('Display Title', 'sizpay-payment-for-woocommerce'),
					'type'=> 'text',
					'description' => __('Display Title', 'sizpay-payment-for-woocommerce'),
					'default' => __('SizPay Payment Gateway', 'sizpay-payment-for-woocommerce')),
				'description' => array(
					'title' => __('Payment Instruction', 'sizpay-payment-for-woocommerce'),
					'type' => 'textarea',
					'description' => __('Payment Instruction', 'sizpay-payment-for-woocommerce'),
					'default' => __('Pay by SizPay Payment Gateway', 'sizpay-payment-for-woocommerce')),

				'sizPayKey' => array(
					'title' => __('SizPay sizPayKey', 'sizpay-payment-for-woocommerce'),
					'type' => 'text',
					'description' => __('Enter SizPay sizPayKey', 'sizpay-payment-for-woocommerce')),

				);
		}

		public function admin_options()
		{
			echo '<h3>'.__('SizPay Payment Gateway', 'sizpay-payment-for-woocommerce').'</h3>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}
		 

		public function CallSizpayApi($action, $params)
        {
                try 
				{
                    $number_of_connection_tries = 5;
                    $response = null;
                    while ($number_of_connection_tries > 0) {
                        $response = wp_safe_remote_post('https://rt.sizpay.ir/api/PaymentSimple/' . $action, array(
                            'body' => $params,
                            'headers' => array(
                                'Content-Type' => 'application/json',
                                'Content-Length' =>   strlen($params) ,
                                'User-Agent' => 'Sizpay Rest Api v1'
                            )
                        ));
                        if (is_wp_error($response)) {
                            $number_of_connection_tries--;
                            continue;
                        } else {
                            break;
                        }
                    }

                    $body = wp_remote_retrieve_body($response);

                    return json_decode($body, true);
                } 
				catch (Exception $ex) {
                    return false;
                }
        }

		function payment_fields()
		{
			if($this->description) echo wpautop(wptexturize($this->description));
		}

		function receipt_page($order)
		{
			echo '<p>'.__('thank you for your order. please note the order id. and then click on pay button', 'sizpay-payment-for-woocommerce').'</p>';
			echo $this->generate_sizpay_form($order);
       }

		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
		}

		function check_sizpay_response()
		{
			global $woocommerce;
			if (isset($_REQUEST['ResCod']))
			{
				$order_id = $_GET['order_id'];
				$order = new WC_Order($order_id);
				if(!$order)
				{
					$message = __('Error : Order Not Exists!', 'sizpay-payment-for-woocommerce');
				}
				elseif($order->status == 'completed')
				{
					$message = __('Error : Order Already Paid!', 'sizpay-payment-for-woocommerce');
				}
				else
				{
					if (in_array($_REQUEST['ResCod'], array('0', '00')))
					{						
						$data = array(
								'sizPayKey'  => $this->sizPayKey,
								'Token'       => $_REQUEST['Token']
						);                            
						$result = $this->CallSizpayApi('ConfirmSimple', json_encode($data));
           
						if ($result === false) {
					            echo esc_html('cURL Error occured on confirm, check internet connections') ;
					    } else if (isset($result['ResCod']) &&  in_array($result['ResCod'], array('0', '00')) ) 
						{
							$message = sprintf(__("Payment Completed. OrderID : %s . PaymentRefrenceID : %s", 'sizpay-payment-for-woocommerce'), $order_id, $result['RefNo']);
							$order->payment_complete();
							$order->add_order_note($message);
							$woocommerce->cart->empty_cart();
							wc_add_notice($message, 'success');
							wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
							exit;
						}
						else
						{
							$message = @$result['Message'];
							$order->add_order_note($message);
						}						
					}
					else
					{
						$message = @$_REQUEST['Message'];
						$order->add_order_note($message);
					}
				}
			}
			else
			{
				$message = __('System (Permission) Error.', 'sizpay-payment-for-woocommerce');
			}
			if (isset($message) && $message) wc_add_notice($message, 'error');
			wp_redirect($woocommerce->cart->get_checkout_url());
			exit;
		}

		public function generate_sizpay_form($order_id)
		{
			global $woocommerce;
			$order = new WC_Order($order_id);
			$products = array();
			$order_items = $order->get_items();
			foreach ($order_items as $product)
			{
				$products[] = $product[ 'name' ] . ' (' . $product[ 'qty' ] . ') ';
			}
			$desc   = 'لیست محصولات : ' . implode( ' - ', $products );
			
			$Amount = (int)$order->order_total;
			if (get_woocommerce_currency() == 'IRHT') {
                    $Amount *= 10000;
            } else if (get_woocommerce_currency() == 'IRT') {
                    $Amount *= 10;
            }		 
			 $data = array(
					'sizPayKey'  => $this->sizPayKey,
					'Amount'      => $Amount, 
					'OrderID'     => time(),
					'ReturnURL'   => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/'),
					'InvoiceNo'   => $order_id,
					'DocDate'     => '',
					'SignData'    => '',
					'ExtraInf'    => $desc,
					'AppExtraInf' => array(
					'PayerNm'     => $order->get_formatted_shipping_full_name(),
					'PayerMobile' => $order->get_billing_phone(),
					'PayerEmail'  => $order->get_billing_email(),
					'Descr'       => '',
					'PayTitleID'  => 0
					));
                            
            $result = $this->CallSizpayApi('GetTokenSimple', json_encode($data));
           
			if ($result === false) {
                    echo esc_html('cURL Error occured, check internet connections') ;
            } else if (isset($result['ResCod']) &&  in_array($result['ResCod'], array('0', '00')) ) 
			{
				update_post_meta($order_id, 'sizpay_token', $result['Token']);
				echo '<form name="frmSizPayPayment" method="post" action="https://rt.sizpay.ir/Route/Payment">';
				echo '<input type="hidden" name="token" value="'.$result['Token'].'" />';
				echo '<input class="sizpay_btn btn button" type="submit" value="'.__('Pay', 'sizpay-payment-for-woocommerce').'" /></form>';
				echo '<script>document.frmSizPayPayment.submit();</script>';
            } else {
                    
					if (isset($result['Message'])) {
						$error = $result['Message'];
					} else 
					{
						$error = 'خطای غیرمنتظره در اتصال به درگاه پرداخت!';
					}
				echo 'خطا : ' . $error;
            }
		}
	}
            
	function woocommerce_add_sizpay_gateway($methods)
	{
		$methods[] = 'WC_SizPay';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_sizpay_gateway');
}
add_action('plugins_loaded', 'woocommerce_sizpay_init', 666);

?>