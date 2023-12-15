<?php
/*
Plugin Name: درگاه شرکت پرداخت الکترونیک سپهر برای woo
Plugin URI: http://softiran.org
Description: ساخته شده توسط  <a href="http://www.softiran.org/" target="_blank"> گروه برنامه نویسی سافت ایران</a>درگاه شرکت پرداخت الکترونیک سپهر
Version: 3.0
Author: SOFTIRAN.ORG
Author URI: http://www.softiran.org
Copyright: 2020 softiran.org 
*/

add_action('plugins_loaded', 'woocommerce_pas_init', 0);

function woocommerce_pas_init() 
{
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	if(isset($_GET['msg']) && $_GET['msg']!=''){add_action('the_content', 'showMessagepas');}
	function showMessagepas($content)
	{
			return '<div class="box '.htmlentities($_GET['type']).'-box">'.base64_decode($_GET['msg']).'</div>'.$content;
	}
    class WC_pas extends WC_Payment_Gateway 
	{
		protected $msg = array();
        public function __construct()
		{
            $this->id = 'pas';
            $this->method_title = __('درگاه pas', 'pas');
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/logo.png';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->terminal = trim($this->settings['terminal']);
			$this->vahed = $this->settings['vahed'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->msg['message'] = "";
            $this->msg['class'] = "";
			add_action( 'woocommerce_api_wc_pas', array( $this, 'check_pas_response' ) );
            add_action('valid-pas-request', array($this, 'successful_request'));
			
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) 
			{
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else 
			{
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
			
            add_action('woocommerce_receipt_pas', array($this, 'receipt_page'));
        }

        function init_form_fields()
		{

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('فعال سازی/غیر فعال سازی', 'pas'),
                    'type' => 'checkbox',
                    'label' => __('فعال سازی درگاه پرداخت pas', 'pas'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('عنوان', 'pas'),
                    'type'=> 'text',
                    'description' => __('عنوانی که کاربر در هنگام پرداخت مشاهده می کند', 'pas'),
                    'default' => __('پرداخت اینترنتی pas', 'pas')),
                'description' => array(
                    'title' => __('توضیحات', 'pas'),
                    'type' => 'textarea',
                    'description' => __('توضیحات قابل نمایش به کاربر در هنگام انتخاب درگاه پرداخت', 'pas'),
                    'default' => __('پرداخت از طریق درگاه pas با کارت های عضو شتاب', 'pas')), 
				'terminal' => array(
                    'title' => __('ترمینال', 'pas'),
                    'type' => 'text',
                    'description' => __('ترمینال درگاه پرداخت الکترونیک سپهر')),

				'vahed' => array(
                    'title' => __('واحد پولی'),
                    'type' => 'select',
                    'options' => array(
					'rial' => 'ریال',
					'toman' => 'تومان'
					),
                    'description' => "نیازمند افزونه ریال و تومان هست"),
                
            );


        }

        public function admin_options()
		{
            echo '<h3>'.__('درگاه شرکت پرداخت الکترونیک سپهر', 'pas').'</h3>';
            echo '<p>'.__('درگاه شرکت پرداخت الکترونیک سپهر').'</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
		
        function payment_fields()
		{
            if($this->description) echo wpautop(wptexturize($this->description));
        }

        function receipt_page($order)
		{
            echo '<p>'.__('برای اتصال به درگاه روی «پرداخت» کلیک کنید', 'pas').'</p>';
            echo $this->generate_pas_form($order);
        }

        function process_payment($order_id)
		{
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true )); 
        }

       function check_pas_response()
		{
            global $woocommerce , $wpdb;
			ini_set('display_errors', 1);	
			$order_id = $woocommerce->session->order_id;
			$order = new WC_Order($order_id);
			if ($_SERVER["REQUEST_METHOD"] != "POST") 
			{
				$_POST +=$_GET;
			}
			if($order_id != '')
			{
				if($order->status !='completed')
				{
					if( isset($_POST['respcode']) && $_POST['respcode'] == '0' )
					{
						$postmeta = $wpdb->get_row($wpdb->prepare("SELECT count(*) as cc  FROM $wpdb->postmeta WHERE meta_value = %s LIMIT 1",$_POST['digitalreceipt']));
						if($postmeta->cc <= 0)
						{
							$amount = str_replace(".00", "", $order->order_total);
							if($this->vahed!='rial')
								$amount = $amount * 10;
							
							$terminal = $this->terminal;
							$params ='digitalreceipt='.$_POST['digitalreceipt'].'&Tid='.$terminal;
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice');
							curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							$res = curl_exec($ch);
							curl_close($ch);
							$result = json_decode($res,true);
							 if (strtoupper($result['Status']) == 'OK') 
							 {
								if($result['ReturnId'] == $amount)
								{
									$referenceId = $_POST['digitalreceipt'];
									$invoiceid = $_POST['invoiceid'];
									$tracenumber = $_POST['tracenumber'];
									$rrn = $_POST['rrn'];
									$this->msg['message'] = "پرداخت شما با موفقیت انجام شد<br/> کد سفارش : $invoiceid <br/> کد پیگیری: $tracenumber<br/> کد پیگیری درگاه: $rrn<br/> شناسه یکتا: $referenceId";
									$this->msg['message2'] = "پرداخت شما با موفقیت انجام شد<br/> کد سفارش : $invoiceid <br/> کد پیگیری: $tracenumber<br/> کد پیگیری درگاه: $rrn<br/> شناسه یکتا: $referenceId";
									$this->msg['class'] = 'success';
									$order->payment_complete($referenceId);
									$Note = apply_filters('pas_Return_from_Gateway_Success_Note',$this->msg['message2'], $order_id, $tracenumber);
									$order->add_order_note($Note,1);
									$woocommerce->cart->empty_cart();
									wc_add_notice( $this->msg['message'] , 'success' );
									do_action('pas_Return_from_Gateway_Success', $order_id, $tracenumber);
									wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
									exit;
								}
								else
								{
									$this->msg['class'] = 'error';
									$this->msg['message'] = "مبلغ پرداختی صحیح نیست";
								}
								 
							}else
							{
								switch($result['ReturnId'])
								{
									case '-1' : $err = 'تراکنش پیدا نشد';break;
									case '-2' : $err = 'تراکنش قبلا Reverse شده است';break;
									case '-3' : $err = 'خطا عمومی';break;
									case '-4' : $err = 'امکان انجام درخواست برای این تراکنش وجود ندارد';break;
									case '-5' : $err = 'آدرس IP پذیرنده نامعتبر است';break;
									default  : $err = 'خطای ناشناس : '.$result['ReturnId'];break;
								}
								$this->msg['class'] = 'error';
								$this->msg['message'] = $err;
							}
						}
						else
						{
							$this->msg['class'] = 'error';
							$this->msg['message'] = 'رسید قبلا استفاده شده است' ;
						}
					}
					else
					{
						$this->msg['class'] = 'error';
						$this->msg['message'] = 'پرداخت ناموفق بود' ;
					}
				}
				else
				{
					$this->msg['class'] = 'error';
					$this->msg['message'] = "قبلا اين سفارش به ثبت رسيده يا سفارشي موجود نيست!";
				}
				do_action('pas_Return_from_Gateway_Failed', $order_id, $_POST['invoiceid'], $this->msg['message']);
			}
			 
			wc_add_notice( $this->msg['message'] , 'error' );
			wp_redirect($woocommerce->cart->get_checkout_url());
			exit;
		}
		
        function showMessage($content)
		{
            return '<div class="box '.$this->msg['class'].'-box">'.$this->msg['message'].'</div>'.$content;
        }

        public function generate_pas_form($order_id)
		{
            global $woocommerce;
            $order = new WC_Order($order_id);

			$redirect_url = add_query_arg( 'wc_order', $order_id , WC()->api_request_url('WC_pas') );
			unset( $woocommerce->session->order_id );
			$woocommerce->session->order_id = $order_id;
			
			$amount = str_replace(".00", "", $order->order_total);
			if($this->vahed!='rial')
				$amount = $amount * 10;

			$Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
			$Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
			do_action('pas_Gateway_Payment', $order_id, $Description, $Mobile);
			
			$amount = trim($amount);
			$invoiceNumber = $order_id;
			$merchant = $this->merchant_id;
			$redirectAddress = $redirect_url; 
			$terminal = $this->terminal;

			$params ='terminalID='.$terminal.'&Amount='.$amount.'&callbackURL='.urlencode($redirectAddress).'&invoiceID='.$invoiceNumber;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$res = curl_exec($ch);
			curl_close($ch);
			if($res)
			{
				$res = json_decode($res,true);
				if($res['Status'] == '0')
				{	  
					echo	$setPayment = '<form id="paymentUTLfrm" action="https://sepehr.shaparak.ir:8080" method="POST">
					<input type="hidden" id="TerminalID" name="TerminalID" value="'.$terminal.'">
					<input type="hidden" id="getMethod" name="getMethod" value="1">
					<input type="hidden" id="token" name="token" value="'.$res['Accesstoken'].'">
					<input type="submit" name="pas_submit" class="button alt" id="payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
					<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					</form><br/>';
				}else
				{
					echo ('خطا در ساخت توکن <br/> کد خطا :'.$res['Status']);
					die;
				}
			}
			else
			{
				echo ('پورت 8081 در هاست شما بسته است !');
				die;
			}
        }
		
        function get_pages($title = false, $indent = true) 
		{
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) 
			{
                $prefix = '';
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) 
					{
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }

    function woocommerce_add_pas_gateway($methods) 
	{
        $methods[] = 'WC_pas';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_pas_gateway' );
}

?>