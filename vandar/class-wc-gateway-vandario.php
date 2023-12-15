<?php

if (!defined('ABSPATH'))
    exit;

function Load_Vandario_Gateway()
{

    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Vandar') && !function_exists('Woocommerce_Add_Vandario_Gateway')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Vandario_Gateway');

        function Woocommerce_Add_Vandario_Gateway($methods)
        {
            $methods[] = 'WC_Vandar';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'IR_currency');

        function IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'IR_currency_symbol', 10, 2);

        function IR_currency_symbol($currency_symbol, $currency)
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

        class WC_Vandar extends WC_Payment_Gateway
        {

            public function __construct()
            {

                $this->id = 'WC_Vandar';
                $this->method_title = __('پرداخت امن وندار', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت وندار برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_vandario_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/Logo.fb897088.svg');

                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->method_title;
                $this->description = $this->settings['description'];

                $this->merchantcode = $this->settings['merchantcode'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '>='))
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                else
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Vandario_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Vandario_Gateway'));
            }


            public function admin_options()
            {


                parent::admin_options();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters(
                     'WC_Vandario_Config',
                     array(
                          'base_confing' => array(
                               'title' => __('تنظیمات پایه ای', 'woocommerce'),
                               'type' => 'title',
                               'description' => '',
                          ),
                          'enabled' => array(
                               'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                               'type' => 'checkbox',
                               'label' => __('فعالسازی درگاه وندار', 'woocommerce'),
                               'description' => __('برای فعالسازی درگاه پرداخت وندار باید چک باکس را تیک بزنید', 'woocommerce'),
                               'default' => 'yes',
                               'desc_tip' => true,
                          ),
                          'title' => array(
                               'title' => __('عنوان درگاه', 'woocommerce'),
                               'type' => 'text',
                               'class' => 'disabled',
                               'custom_attributes' => array('disabled' => true),
                               'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                               'default' => __('پرداخت وندار', 'woocommerce'),
                               'desc_tip' => true,
                          ),
                          'description' => array(
                               'title' => __('توضیحات درگاه', 'woocommerce'),
                               'type' => 'text',
                               'desc_tip' => true,
                               'class' => 'disabled',
                               'custom_attributes' => array('disabled' => true),
                               'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                               'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه وندار', 'woocommerce')
                          ),
                          'account_confing' => array(
                               'title' => __('تنظیمات حساب وندار', 'woocommerce'),
                               'type' => 'title',
                               'description' => '',
                          ),
                          'merchantcode' => array(
                               'title' => __('کلید API', 'woocommerce'),
                               'type' => 'text',
                               'description' => __(' کلید API درگاه وندار', 'woocommerce'),
                               'default' => '',
                               'desc_tip' => true
                          ),
                          'payment_confing' => array(
                               'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                               'type' => 'title',
                               'description' => '',
                          ),
                          'success_massage' => array(
                               'title' => __('پیام پرداخت موفق', 'woocommerce'),
                               'type' => 'textarea',
                               'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) وندار استفاده نمایید .', 'woocommerce'),
                               'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                          ),
                          'failed_massage' => array(
                               'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                               'type' => 'textarea',
                               'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت وندار ارسال میگردد .', 'woocommerce'),
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
            public function curl_post($action, $params)
            {
                $result = wp_remote_post($action, array(
                     'method' => 'POST',
                     'headers'  => array(
                          'Content-type: application/x-www-form-urlencoded',
                          'Accept: application/json'
                     ),
                     'timeout' => 30,
                     'body' => $params
                ));

                if (is_wp_error($result)) {
                    return $result->get_error_message();
                } else {
                    return json_decode(wp_remote_retrieve_body($result), true);
                }
            }

            public function send($api, $amount, $redirect, $mobile = null, $factorNumber = null, $description = null)
            {
                $tempDescription = explode(' ', $description) ;
                $tempDescription = array_slice($tempDescription,0,11);
                $tempDescription = implode(' ',$tempDescription);

                return  $this->curl_post('https://ipg.vandar.io/api/v3/send', [
                     'api_key'          => $api,
                     'amount'       => $amount * 10,
                     'callback_url'     => $redirect,
                     'mobile_number'       => $mobile,
                     'factorNumber' => $factorNumber,
                     'description'  => $tempDescription,
                ]);
            }

            public function verify($api, $token)
            {
                return $this->curl_post('https://ipg.vandar.io/api/v3/verify', [
                     'api_key'     => $api,
                     'token' => $token,
                ]);
            }

            public function Send_to_Vandario_Gateway($order_id)
            {


                global $woocommerce;
                $woocommerce->session->order_id_Vandar = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Vandario_Currency', $currency, $order_id);

                $form = '<form action="" method="POST" class="vandario-checkout-form" id="vandario-checkout-form">
						<input type="submit" name="vandario_submit" class="button alt" id="vandario-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
                     </form><br/>';

                $filtered = _sanitize_text_fields( $form, false );

                $form = apply_filters('WC_Vandario_Form', $filtered, $order_id, $woocommerce);

                do_action('WC_Vandario_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_Vandario_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = (int)$order->get_total();
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                if (
                     strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                )
                    $Amount = $Amount * 1;
                else if (strtolower($currency) == strtolower('IRHT'))
                    $Amount = $Amount * 1000;
                else if (strtolower($currency) == strtolower('IRHR'))
                    $Amount = $Amount * 100;
                else if (strtolower($currency) == strtolower('IRR'))
                    $Amount = $Amount / 10;


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Vandario_gateway', $Amount, $currency);

                $MerchantCode = $this->merchantcode;
                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Vandar'));


                $products = array();
                $order_items = $order->get_items();
                foreach ((array) $order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
                $Email = $order->get_billing_email();
                $Paymenter = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $ResNumber = intval($order->get_order_number());

                //Hooks for iranian developer
                $Description = apply_filters('WC_Vandario_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Vandario_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_Vandario_Email', $Email, $order_id);
                $Paymenter = apply_filters('WC_Vandario_Paymenter', $Paymenter, $order_id);
                $ResNumber = apply_filters('WC_Vandario_ResNumber', $ResNumber, $order_id);
                do_action('WC_Vandario_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                $accvandar = 'https://ipg.vandar.io/v3/%s';


                $result = $this->send($this->merchantcode, $Amount, $CallbackUrl, $mobile = null, $factorNumber = null, $Description);

                if ($result['status']) {

                    if ($result['status'] == 1) {
                        wp_redirect(sprintf($accvandar, $result['token']));
                        exit;
                    } else {
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result['status'];
                        $Fault = '';
                    }
                } else {
                    echo $result->errorMessage;
                }

                if (!empty($result->errorMessage) && $result->errorMessage) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $result->errorMessage);
                    $Note = apply_filters('WC_Vandario_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $result->errorMessage);
                    $Notice = apply_filters('WC_Vandario_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');
                    do_action('WC_Vandario_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_Vandario_Gateway()
            {

                $PST_InvoiceNumber = sanitize_text_field($_POST['InvoiceNumber']);

                $InvoiceNumber = isset($PST_InvoiceNumber) ? $PST_InvoiceNumber : '';

                global $woocommerce;

                $wc_order = sanitize_text_field($_GET['wc_order']);
                if (isset($wc_order))
                    $order_id = $wc_order;
                else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_Vandar;
                    unset($woocommerce->session->order_id_Vandar);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_Vandario_Currency', $currency, $order_id);

                    if ($order->status != 'completed') {

                        $MerchantCode = $this->merchantcode;

                        $MerchantID = $this->merchantcode;
                        $Amount = intval($order->order_total);
                        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                        if (
                             strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                        )
                            $Amount = $Amount * 1;
                        else if (strtolower($currency) == strtolower('IRHT'))
                            $Amount = $Amount * 1000;
                        else if (strtolower($currency) == strtolower('IRHR'))
                            $Amount = $Amount * 100;
                        else if (strtolower($currency) == strtolower('IRR'))
                            $Amount = $Amount / 10;

                        $Authority = sanitize_text_field($_GET['token']);

                        $result = $this->verify($MerchantID, $Authority);

                        if (isset($result['status'])) {
                            if ($result['status'] == 1) {
                                $Status = 'completed';
                                $Transaction_ID = $result['transId'];
                                $Fault = '';
                                $Message = '';
                            } else {
                                $Status = 'failed';
                                $Fault = $result->errors->token[0];
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {
                            $statuss = sanitize_text_field($_GET['status']);
                            if ($statuss == 0) {
                                $Status = 'failed';
                                $Fault = '';
                                $Message = 'تراکنش انجام نشد .';
                            }
                        }

                        if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);


                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_Vandario_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->success_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_Vandario_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Vandario_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } else {


                            $tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>توکن : ' . $Transaction_ID) : '';

                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                            $Note = apply_filters('WC_Vandario_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_Vandario_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_Vandario_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                            wp_redirect(wc_get_checkout_url());
                            exit;
                        }
                    } else {


                        $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                        $Notice = wpautop(wptexturize($this->success_massage));

                        $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                        $Notice = apply_filters('WC_Vandario_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                        if ($Notice)
                            wc_add_notice($Notice, 'success');


                        do_action('WC_Vandario_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    }
                } else {


                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_Vandario_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Vandario_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            }
        }
    }
}

add_action('plugins_loaded', 'Load_Vandario_Gateway', 0);
