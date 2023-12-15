<?php

if (!defined('ABSPATH'))
    exit;

function Load_PayFa_Gateway()
{

    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_PAYFA') && !function_exists('Woocommerce_Add_PayFa_Gateway')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_PayFa_Gateway');

        function Woocommerce_Add_PayFa_Gateway($methods)
        {
            $methods[] = 'WC_PAYFA';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_PayFaIR_currency');

        function add_PayFaIR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_PayfaIR_currency_symbol', 10, 2);

        function add_PayfaIR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency)
            {
                case 'IRR':
                    $currency_symbol = 'ریال';
                break;

                case 'IRT':

                    $currency_symbol = 'تومان';
                break;
            }

            return $currency_symbol;
        }

        class WC_PAYFA extends WC_Payment_Gateway
        {

            public function __construct()
            {

                $this->id = 'WC_PAYFA';
                $this->method_title = __('درگاه پرداخت پی فا', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت پی فا برای افزونه ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_PAYFA_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->merchantcode = $this->settings['merchantcode'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
                {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                }
                else
                {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_PayFa_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_PayFa_Gateway'));
            }


            public function admin_options()
            {
                parent::admin_options();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_PAYFA_Config', array(
                        
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه پی فا', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت پی فا باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('درگاه پرداخت پی فا', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت آنلاین به وسیله کلیه کارت های عضو شتاب از طریق درگاه پی فا', 'woocommerce')
                        ),
                        'merchantcode' => array(
                            'title' => __('کد API', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('کد API دریافتی از پنل کاربری پی فا', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_confing' => array(
                            'title' => __('تنظیمات پیغام های پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {trans_id} برای نمایش کد رهگیری پی فا استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد . کد رهگیری : {trans_id} ', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {error_text} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت پی فا ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            public function SendRequestToPayFa($action, $params)
            {
                try
                {
                    $ch = curl_init('https://payment.payfa.com/v1/api/payment/'.$action);
                    curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $result = curl_exec($ch);
                    curl_close($ch);
					$content = "\r\n$ch / ".json_encode($params)."/ $result";
					//file_put_contents('payfa_log.txt', $content, FILE_APPEND | LOCK_EX);
                    return json_decode($result);
                }
                catch (Exception $ex)
                {
                    return false;
                }
            }

            public function Send_to_PayFa_Gateway($order_id)
            {
                global $woocommerce;
                
                $woocommerce->session->order_id_payfa = $order_id;
                
                $order = new WC_Order($order_id);
                $currency = $order->get_order_currency();
                $currency = apply_filters('WC_PAYFA_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="payfa-checkout-form" id="payfa-checkout-form">
						<input type="submit" name="payfa_submit" class="button alt" id="payfa-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_PAYFA_Form', $form, $order_id, $woocommerce);

                do_action('WC_PAYFA_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_PAYFA_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = intval($order->order_total);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
               
                if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                )
                    $Amount = $Amount * 1;
                else if (strtolower($currency) == strtolower('IRR'))
                    $Amount = $Amount / 10;


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_PayFa_gateway', $Amount, $currency);
                //$Amount = 500;

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_PAYFA'));

                $data = array('api' => $this->merchantcode, 'amount' => ($Amount*10) , 'callback' => $CallbackUrl, 'invoice_id' => $order_id);

                $result = $this->SendRequestToPayFa('request' , $data);

                if ($result === false)
                {
                    echo "cURL Error #:" . $err;
                }
                else
                {
                    if (isset($result->status) && $result->status > 1)
                    {
                        wp_redirect('https://payment.payfa.com/v1/api/payment/gateway/'.$result->status);
                        exit;
                    }
                    else
                    {
                        //$Message = ' تراکنش ناموفق بود- کد خطا : 0x01';
                        $Message = 'پرداخت ناموفق '.$result->msg.' کد خطای  '.$result->status;
                        $Fault = '';
                    }
                }


                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_PAYFA_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_PAYFA_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_PAYFA_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_PayFa_Gateway()
            {
                global $woocommerce;
                
                $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : '';                

                if ($payment_id)
                {
                    //var_dump($_GET);
                    if (isset($_GET['wc_order'])) {
                       $order_id = $_GET['wc_order'];
                    } else if ($InvoiceNumber) {
                        $order_id = $InvoiceNumber;
                    } else {
                        $order_id = $woocommerce->session->order_id_payfa;
                        unset($woocommerce->session->order_id_payfa);
                    }
                    
                    //echo "order_id:".$order_id;                    
                    //$order_id = $woocommerce->session->order_id_payfa;
                    //unset($woocommerce->session->order_id_payfa);
                    $order = new WC_Order($order_id);                   
                    
                    $currency = $order->get_order_currency();
                    $currency = apply_filters('WC_PAYFA_Currency', $currency, $order_id);

                    if ($order->status != 'completed')
                    {
                        $Amount = intval($order->order_total);
                        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                        if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                        )
                            $Amount = $Amount * 1;
                        else if (strtolower($currency) == strtolower('IRR'))
                            $Amount = $Amount / 10;

                        $data = array('api' => $this->merchantcode, 'payment_id' => $payment_id);
                        $result = $this->SendRequestToPayFa('verify', $data);
                        
                        if (isset($result->status) && $result->status == '0')
                        {
                            /*if($result->amount == $order->total){*/
                                $Status = 'completed';
                                $Transaction_ID = $result->transid;
                                $Fault = '';
                                $Message = '';    
                            /*}
                            else{
                                 $Status = 'failed';
                                $Fault = $result->status;
                                $Message = 'خطا در پرداخت : مبلغ تراکنش پرداختی با مبلغ سفارش مغایرت دارد';
                            }*/
                        }
                        else
                        {
                            $Status = 'failed';
                            $Fault = $result->status;
                            $Message = 'تراکنش ناموفق بود';
                        }
                    

                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0)
                        {
                            
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);

                            $order->payment_complete($Transaction_ID);
                            
                            $woocommerce->cart->empty_cart();
                            

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_PAYFA_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->success_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_PAYFA_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_PAYFA_Return_from_Gateway_Success', $order_id, $Transaction_ID);
                            
                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } 
                        else
                        {
                            $tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>توکن : ' . $Transaction_ID) : '';

                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                            $Note = apply_filters('WC_PAYFA_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_PAYFA_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_PAYFA_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                            wp_redirect($woocommerce->cart->get_checkout_url());
                            exit;
                        }

                    }
                    else
                    {
                        $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                        $Notice = wpautop(wptexturize($this->success_massage));

                        $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                        $Notice = apply_filters('WC_PAYFA_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                        if ($Notice)
                            wc_add_notice($Notice, 'success');

                        do_action('WC_PAYFA_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    }
                }
                else
                {


                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_PAYFA_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_PAYFA_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            }

        }

    }
}

add_action('plugins_loaded', 'Load_PayFa_Gateway', 0);