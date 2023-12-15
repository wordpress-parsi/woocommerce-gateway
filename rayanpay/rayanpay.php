<?php

/*
 Plugin Name: RayanPay Payment Method for WooCommerce
 Description: <b> درگاه پرداخت آنلاین رایان پی برای افزونه ووکامرس وردپرس </b>
 Version: 1.0
 Author: رایان پی
 Author URI: https://rayanpay.com
 Text Domain: rayanpay-payment-for-woocommerce
 Domain Path: /languages
 */


// Plugin Created and Developed By Mr.Fazeli (ModuleBank.ir)


add_action('plugins_loaded', function() {
	load_plugin_textdomain('rayanpay-payment-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
	if (!class_exists('WC_Payment_Gateway')) return;
	class WC_RayanPay extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'rayanpay';
			$this->plugin_name = __('RayanPay Payment Method for WooCommerce', 'rayanpay-payment-for-woocommerce');
			$this->method_title = __('RayanPay Payment Gateway', 'rayanpay-payment-for-woocommerce');
			$this->icon = plugin_dir_url(__FILE__) . 'images/logo.png';
			$this->has_fields = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->merchantid = $this->settings['merchantid'];
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_rayanpay_response'));
			add_action('valid-rayanpay-request', array($this, 'successful_request'));
			add_action('woocommerce_update_options_payment_gateways_rayanpay', array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_rayanpay', array($this, 'receipt_page'));
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => __('Enable / Disable', 'rayanpay-payment-for-woocommerce'),
					'type'    => 'checkbox',
					'label'   => __('Enable or Disable This Payment Mehod', 'rayanpay-payment-for-woocommerce'),
					'default' => 'yes'
				),
				'title'           => array(
					'title'       => __('Display Title', 'rayanpay-payment-for-woocommerce'),
					'type'        => 'text',
					'description' => __('Display Title', 'rayanpay-payment-for-woocommerce'),
					'default'     => __('RayanPay Payment Gateway', 'rayanpay-payment-for-woocommerce')
				),
				'description'     => array(
					'title'       => __('Payment Instruction', 'rayanpay-payment-for-woocommerce'),
					'type'        => 'textarea',
					'description' => __('Payment Instruction', 'rayanpay-payment-for-woocommerce'),
					'default'     => __('Pay by RayanPay Payment Gateway', 'rayanpay-payment-for-woocommerce')
				),
				'merchantid'      => array(
					'title'       => __('RayanPay Merchant ID', 'rayanpay-payment-for-woocommerce'),
					'type'        => 'text',
					'description' => __('Enter RayanPay Merchant ID', 'rayanpay-payment-for-woocommerce')
				),
			);
		}

		public function admin_options()
		{
			echo '<h3>'.__('RayanPay Payment Gateway', 'rayanpay-payment-for-woocommerce').'</h3>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		function payment_fields()
		{
			if($this->description) echo esc_html($this->description);
		}

		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true)); 
		}

		function receipt_page($order_id)
		{
			$order = new WC_Order($order_id);
			try
			{
				$c = new SoapClient('https://pms.rayanpay.com/pg/services/webgate/wsdl');
				$r = $c->PaymentRequest($p = array(
						'MerchantId'  => $this->merchantid,
						'Amount'      => intval(ceil($order->order_total * (get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1)))),
						'CallbackURL' => add_query_arg(array('wc-api' => get_class($this), 'order_id' => $order_id), get_site_url().'/'),
					));
				if ($r->PaymentRequestResult->Status == 100)
				{
					update_post_meta($order_id, 'rayanpay_authority', $r->PaymentRequestResult->Authority);
					$u = 'https://pms.rayanpay.com/pg/startpay/' . $r->PaymentRequestResult->Authority;
					echo '<p>'.__('thank you for your order. you are redirecting to rayanpay gateway. please wait', 'rayanpay-payment-for-woocommerce').'</p>';
					echo '<a class="rayanpay_btn btn button" href="'.esc_url($u).'"> '.__('Pay', 'rayanpay-payment-for-woocommerce').' </a>';
					echo '<script> document.location="'.esc_url($u).'" </script>';
					@header('location: ' . $u);
				}
				else
				{
					throw new exception($this->getRayanPayResponseStatus($r->PaymentRequestResult->Status));
				}
			}
			catch (exception $e)
			{
				$order->add_order_note(__('Erorr', 'rayanpay-payment-for-woocommerce') . ' : ' . $e->getMessage());
				echo '<p><font color="red">'.__('Erorr', 'rayanpay-payment-for-woocommerce').' : '.esc_html($e->getMessage()).'</font></p>';
			}
       }

		function check_rayanpay_response()
		{
			if (isset($_GET['Status'],$_GET['Authority'],$_GET['order_id']))
			{
				$order_id = sanitize_text_field($_GET['order_id']);
				$Status = sanitize_text_field($_GET['Status']);
				$Authority = sanitize_text_field($_GET['Authority']);
				$order = new WC_Order($order_id);
				if (strtoupper($Status) != 'OK')
				{
					$message = __('Payment Cancelled By User', 'rayanpay-payment-for-woocommerce');
					$order->add_order_note($message);
				}
				elseif (!($order && is_object($order)))
				{
					$message = __('Error : Order Not Exists!', 'rayanpay-payment-for-woocommerce');
				}
				elseif($order->is_paid())
				{
					$message = __('Error : Order Already Paid!', 'rayanpay-payment-for-woocommerce');
				}
				else
				{
					try
					{
						$c = new SoapClient('https://pms.rayanpay.com/pg/services/webgate/wsdl');
						$r = $c->PaymentVerification($p = array(
								'MerchantId' => $this->merchantid,
								'Amount'     => intval(ceil($order->order_total * (get_woocommerce_currency() == 'IRHT' ? 10000 : (get_woocommerce_currency() == 'IRT' ? 10 : 1)))),
								'Authority'  => $Authority
							));
						if ($r->PaymentVerificationResult->Status == 100)
						{
							$message = sprintf(__("Payment Completed. OrderID : %s . PaymentRefrenceID : %s", 'rayanpay-payment-for-woocommerce'), $order_id, $r->PaymentVerificationResult->RefID);
							$order->payment_complete();
							$order->add_order_note($message);
							WC()->cart->empty_cart();
							wc_add_notice($message, 'success');
							wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
							exit;
						}
						else
						{
							$message = $this->getRayanPayResponseStatus($r->PaymentVerificationResult->Status);
							$order->add_order_note($message);
						}
					}
					catch (exception $e)
					{
						echo esc_html($e->getMessage());
						exit;
					}
				}
			}
			else
			{
				$message = __('System (Permission) Error!', 'rayanpay-payment-for-woocommerce');
			}
			if (isset($message) && $message) wc_add_notice($message, 'error');
			wp_redirect(WC()->cart->get_checkout_url());
			exit;
		}

		function getRayanPayResponseStatus($code)
		{
			switch($code)
			{
				case -1:   return 'اطلاعات ارسال شده ناقص است';
				case -2:   return 'آی پی یا کد پذیرنده پذیرنده صحیح نیست';
				case -3:   return 'با توجه به محدودیت های شاپرك امكان پرداخت با رقم درخواست شده میسر نمی باشد';
				case -11:  return 'درخواست مورد نظر یافت نشد';
				case -21:  return 'هیچ نوع عملیات مالی برای این تراكنش یافت نشد';
				case -22:  return 'تراكنش نا موفق می باشد';
				case -33:  return 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';
				case -40:  return 'اجازه دسترسی به متد مربوطه وجود ندارد';
				case -41:  return 'اطلاعات ارسال شده مربوط به توضیحات دیتا غیرمعتبر میباشد';
				case -100: return 'تراکنش در انتظار پرداخت';
				case -101: return 'آدرس بازگشت مشتری خالی است';
				case -102: return 'در پرداخت خطایی رخ داده است';
				case -103: return 'وضعیت پرداخت جهت تایید نادرست است';
				case -104: return 'فروشگاهی با شناسه ارسالی یافت نشد';
				case -105: return 'شناسه مرجع تراکنش اشتباه است';
				case -106: return 'خطای تایید پرداخت';
				case -107: return 'وضعیت پرداخت صحیح نیست';
				case -109: return 'فروشگاه غیر فعال است';
				case -110: return 'شناسه ارسال شده نامعتبر است';
				case -111: return 'پرداخت با شناسه ارسالی یافت نشد';
				case -112: return 'فرمت توضیحات اشتباه است';
				case -113: return 'فرمت موبایل اشتباه است.';
				case 100:  return 'تراکنش با موفقیت انجام شد';
				case 'NOK': return 'پرداخت از سوی کاربر لغو شد';
			}
			return 'خطای غیر منتظره . کد '.$code;
		}
	}

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
		return array_merge(array('settings' => '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=rayanpay').'">'.__('Settings', 'rayanpay-payment-for-woocommerce').'</a>'), $links);
	});

	add_filter('woocommerce_payment_gateways', function ($methods) {
		$methods[] = 'WC_RayanPay';
		return $methods;
	}, -666);
}, 666);

?>