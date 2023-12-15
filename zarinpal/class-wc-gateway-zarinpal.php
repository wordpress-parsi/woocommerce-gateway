<?php

if (!defined('ABSPATH')) {
    exit;
}


function Load_ZarinPal_Gateway()
{

    if (!function_exists('Woocommerce_Add_ZarinPal_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_ZPal')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_ZarinPal_Gateway');

        function Woocommerce_Add_ZarinPal_Gateway($methods)
        {
            $methods[] = 'WC_ZPal';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_IR_currency');

        function add_IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);

        function add_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_ZPal extends WC_Payment_Gateway
        {


            private $merchantCode;
            private $failedMassage;
            private $successMassage;

            public function __construct()
            {

                $this->id = 'WC_ZPal';
                $this->method_title = __('پرداخت امن زرین پال', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت زرین پال برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_ZPal_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->merchantCode = $this->settings['merchantcode'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_ZarinPal_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_ZarinPal_Gateway'));


            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_ZPal_Config', array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه زرین پال', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت زرین پال باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت امن زرین پال', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زرین پال', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب زرین پال', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت کد', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه زرین پال', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'zarinwebgate' => array(
                            'title' => __('فعالسازی زرین گیت', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('برای فعالسازی درگاه مستقیم (زرین گیت) باید چک باکس را تیک بزنید', 'woocommerce'),
                            'description' => __('درگاه مستقیم زرین پال', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) زرین پال استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زرین پال ارسال میگردد .', 'woocommerce'),
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

            /**
             * @param $action (PaymentRequest, )
             * @param $params string
             *
             * @return mixed
             */
            public function SendRequestToZarinPal($action, $params)
            {

                try {
                    $number_of_connection_tries = 5;
                    $response = null;
                    while ($number_of_connection_tries > 0) {
                        $response = wp_safe_remote_post('https://api.zarinpal.com/pg/v4/payment/' . $action . '.json', array(
                            'body' => $params,
                            'headers' => array(
                                'Content-Type' => 'application/json',
                                'Content-Length' =>   strlen($params) ,
                                'User-Agent' => 'ZarinPal Rest Api v4'
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
                } catch (Exception $ex) {
                    return false;
                }
            }

            public function Send_to_ZarinPal_Gateway($order_id)
            {


                global $woocommerce;
                $woocommerce->session->order_id_zarinpal = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_ZPal_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="zarinpal-checkout-form" id="zarinpal-checkout-form">
						<input type="submit" name="zarinpal_submit" class="button alt" id="zarinpal-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_ZPal_Form', $form, $order_id, $woocommerce);

                do_action('WC_ZPal_Gateway_Before_Form', $order_id, $woocommerce);
                echo wp_kses($form,array(
                    'form'=>array('action','method','class','id'),
                    'input'=>array('type','name','class','id','value'),
                    'a'=>array('class','href')

                ));
                do_action('WC_ZPal_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = (int)$order->order_total;
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                $strToLowerCurrency = strtolower($currency);

                if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 100;
                }
                /*else if (strtolower($currency) === strtolower('IRR')) {
                   $Amount /= 10;
               }*/


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_ZarinPal_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_ZPal'));

                $products = array();
                $order_items = $order->get_items();
                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name ;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ?: '-';
                $Email = $order->billing_email;
                $Payer = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = (int)$order->get_order_number();

                //Hooks for iranian developer
                $Description = apply_filters('WC_ZPal_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_ZPal_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_ZPal_Email', $Email, $order_id);
                $Payer = apply_filters('WC_ZPal_Paymenter', $Payer, $order_id);
                $ResNumber = apply_filters('WC_ZPal_ResNumber', $ResNumber, $order_id);
                do_action('WC_ZPal_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';
                if(strtolower($currency) === strtolower('IRR')){

                    if($Mobile =="" || $Email==""){

                        $data = array('merchant_id' => $this->merchantCode,
                            'amount' => $Amount,
                            'callback_url' => $CallbackUrl,
                            'description' => $Description,
                            "currency"=> "IRR",

                            "metadata" => [ "order_id" => "سفارش شماره $order_id"]);
                    }else {

                        $data = array("merchant_id" => $this->merchantCode,
                            "amount" => $Amount,
                            "callback_url" => $CallbackUrl,
                            "description" => $Description,
                            "currency"=> "IRR",
                            "metadata" => [ "email" => $Email,"mobile"=>$Mobile,"order_id" => "سفارش شماره $order_id"],
                        );

                    }

                }else if (
                    ($strToLowerCurrency === strtolower('IRT')) ||
                    ($strToLowerCurrency === strtolower('TOMAN')) ||
                    $strToLowerCurrency === strtolower('Iran TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                    $strToLowerCurrency === strtolower('IRHT')  ||
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('IRHR') ||
                    $strToLowerCurrency === strtolower('تومان ایران'
                    )){
                    if($Mobile =="" || $Email==""){


                        $data = array('merchant_id' => $this->merchantCode,
                            'amount' => $Amount,
                            'callback_url' => $CallbackUrl,
                            'description' => $Description,
                            "currency"=> "IRT",

                            "metadata" => [ "order_id" => "سفارش شماره  $order_id"]);
                    }else {

                        $data = array('merchant_id' => $this->merchantCode,
                            'amount' => $Amount,
                            'callback_url' => $CallbackUrl,
                            'description' => $Description,
                            "currency"=> "IRT",

                            "metadata" => [  "email" => $Email,"mobile"=>$Mobile,"order_id" => "سفارش شماره $order_id"]);

                    }

                }



                $result = $this->SendRequestToZarinPal('request', json_encode($data));

                if ($result === false) {
                    echo esc_html('cURL Error #:') ;
                } else if ($result['data']['code'] == 100) {


                    //wp_redirect(sprintf($acczarin, $result['data']["authority"]));
                    header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                    exit;
                } else {
                    //$Message= var_dump($data);
                    $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result['errors']['code'];
                    $Fault = '';
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_ZPal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_ZPal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_ZPal_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_ZarinPal_Gateway()
            {


                $InvoiceNumber = isset($_POST['InvoiceNumber']) ? sanitize_text_field($_POST['InvoiceNumber'] ): '';

                global $woocommerce;


                if (isset($_GET['wc_order'])) {
                    $order_id = sanitize_text_field($_GET['wc_order']) ;
                } else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_zarinpal;
                    unset($woocommerce->session->order_id_zarinpal);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_ZPal_Currency', $currency, $order_id);

                    if ($order->status !== 'completed') {

                        $MerchantCode = $this->merchantCode;

                        if ($_GET['Status'] === 'OK') {

                            $MerchantID = $this->merchantCode;
                            $Amount = (int)$order->order_total;
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            $strToLowerCurrency = strtolower($currency);
                            if (
                                ($strToLowerCurrency === strtolower('IRT')) ||
                                ($strToLowerCurrency === strtolower('TOMAN')) ||
                                $strToLowerCurrency === strtolower('Iran TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                                $strToLowerCurrency === strtolower('IRHT')  ||
                                $strToLowerCurrency === strtolower('تومان') ||
                                $strToLowerCurrency === strtolower('IRHR') ||
                                $strToLowerCurrency === strtolower('تومان ایران'
                                )
                            )  if (strtolower($currency) === strtolower('IRHT')) {
                                $Amount *= 1000;
                            } else if (strtolower($currency) === strtolower('IRHR')) {
                                $Amount *= 100;
                            }
                            //else if (strtolower($currency) === strtolower('IRR')) {
                            //$Amount /= 10;
                            //}

                            $Authority =  sanitize_text_field($_GET['Authority']);


                            $data = array("merchant_id" => $MerchantID, "authority" => $Authority, "amount" => $Amount);
                            $result = $this->SendRequestToZarinPal('verify', json_encode($data));

                            if ($result['data']['code'] == 100) {
                                $Status = 'completed';
                                $Transaction_ID = $result['data']['ref_id'];
                                $Fault = '';
                                $Message = '';
                            } elseif ($result['data']['code'] == 101) {
                                $Message = 'این تراکنش قلا تایید شده است';
                                $Transaction_ID = $result['data']['ref_id'];
                                $Notice = wpautop(wptexturize($Message));
                                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                                exit;
                            } else {
                                $Status = 'failed';
                                $Fault = $result['errors']['code'];
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = 'تراکنش انجام نشد .';
                        }

                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0) {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);



                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_ZPal_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMassage));

                            $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_ZPal_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_ZPal_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }

                        if (($Transaction_ID && ($Transaction_ID != 0))) {
                            $tr_id = ('<br/>توکن : ' . $Transaction_ID);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                        $Note = apply_filters('WC_ZPal_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($Transaction_ID, $Message), $Notice);
                        $Notice = apply_filters('WC_ZPal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_ZPal_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }

                    $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));

                    $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                    $Notice = apply_filters('WC_ZPal_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                    if ($Notice) {
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_ZPal_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_ZPal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_ZPal_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }

        }

    }
}

add_action('plugins_loaded', 'Load_ZarinPal_Gateway', 0);
