<?php

if (!defined('ABSPATH')) {
    exit;
}


function Load_Sepal_Gateway()
{

    if (!function_exists('Woocommerce_Add_Sepal_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_Sepal')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Sepal_Gateway');

        function Woocommerce_Add_Sepal_Gateway($methods)
        {
            $methods[] = 'WC_Sepal';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'Sepal_add_IR_currency');

        function Sepal_add_IR_currency($currencies)
        {
            $currencies['IRR'] = __('Rial', 'sepal-woocommerce');
            $currencies['IRT'] = __('Toman', 'sepal-woocommerce');
            $currencies['IRHR'] = __('Thousand Rial', 'sepal-woocommerce');
            $currencies['IRHT'] = __('Thousand Toman', 'sepal-woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'Sepal_add_IR_currency_symbol', 10, 2);

        function Sepal_add_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = __('Rial', 'sepal-woocommerce');
                    break;
                case 'IRT':
                    $currency_symbol = __('Toman', 'sepal-woocommerce');
                    break;
                case 'IRHR':
                    $currency_symbol = __('Thousand Rial', 'sepal-woocommerce');
                    break;
                case 'IRHT':
                    $currency_symbol = __('Thousand Toman', 'sepal-woocommerce');
                    break;
            }
            return $currency_symbol;
        }

        class WC_Sepal extends WC_Payment_Gateway
        {


            private $apiKey;
            private $failedMassage;
            private $successMassage;

            public function __construct()
            {

                $this->id = 'WC_Sepal';
                $this->plugin_name = __('sepal-woocommerce', 'sepal-woocommerce');
                $this->plugin_desc = __('sepal-woocommerce-desc', 'sepal-woocommerce');
                $this->method_title = __('Sepal Payment Gateway', 'sepal-woocommerce');
                $this->method_description = __('Sepal Payment Gateway Settings', 'sepal-woocommerce');
                $this->icon = apply_filters('WC_Sepal_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->apiKey = $this->settings['apiKey'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Sepal_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Sepal_Gateway'));


            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_Sepal_Config', array(
                        'base_config' => array(
                            'title' => __('General Settings', 'sepal-woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('Enable Payment Method', 'sepal-woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('Enable Sepal Payment', 'sepal-woocommerce'),
                            'description' => __('To Enable Payment via Sepal Check this parameter', 'sepal-woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('Display Title', 'sepal-woocommerce'),
                            'type' => 'text',
                            'description' => __('Display Title to user', 'sepal-woocommerce'),
                            'default' => __('Sepal Payment Gateway', 'sepal-woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('Payment Instruction', 'sepal-woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('Payment Instruction display to user', 'sepal-woocommerce'),
                            'default' => __('Pay Online by Iranian Shetab Card via Sepal Payment Gateway', 'sepal-woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('Sepal Settings', 'sepal-woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'apiKey' => array(
                            'title' => __('API Key', 'sepal-woocommerce'),
                            'type' => 'text',
                            'description' => __('Sepal API Key', 'sepal-woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_config' => array(
                            'title' => __('Payment Settings', 'sepal-woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('Complete Payment Message', 'sepal-woocommerce'),
                            'type' => 'textarea',
                            'description' => __('Message display to user after payment complete. you can use {payment_number} shortcode to show transaction trace number.', 'sepal-woocommerce'),
                            'default' => __('Payment Completed. Thank You.', 'sepal-woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('Fail Payment Message', 'sepal-woocommerce'),
                            'type' => 'textarea',
                            'description' => __('Message display to user after fail payment. you can use {fault}  shortcode to show error.', 'sepal-woocommerce'),
                            'default' => __('Payment Failed. try again or contact site administrator', 'sepal-woocommerce'),
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
            public function SendRequestToSepal($action, $params)
            {
                try {
                    $url = 'https://sepal.ir/api/' . $action . '.json';
                    $result = wp_remote_post($url, array(
                            'body' => wp_json_encode($params),
                            'headers' => $h = array('Content-Type' => 'application/json'),
                            'sslverify' => false
                        ));
                    if (!is_wp_error($result)) {
                        return json_decode($result['body'], true);
                    }
                } catch (Exception $ex) { }
                return false;
            }

            public function Send_to_Sepal_Gateway($order_id)
            {


                global $woocommerce;
                $woocommerce->session->order_id_sepal = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Sepal_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="sepal-checkout-form" id="sepal-checkout-form">
                        <input type="submit" name="sepal_submit" class="button alt" id="sepal-payment-button" value="' . __('Pay', 'sepal-woocommerce') . '"/>
                        <a class="button cancel" href="' . esc_url($woocommerce->cart->get_checkout_url()) . '">' . __('Return', 'sepal-woocommerce') . '</a>
                        </form><br/>';
                $form = apply_filters('WC_Sepal_Form', $form, $order_id, $woocommerce);

                do_action('WC_Sepal_Gateway_Before_Form', $order_id, $woocommerce);
                echo esc_html($form);
                do_action('WC_Sepal_Gateway_After_Form', $order_id, $woocommerce);


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
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('تومان ایران'
                    )
                ) {
                    $Amount *= 10;
                } else if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 10000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRR')) {
                    $Amount *= 1;
                }


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Sepal_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Sepal'));

                $products = array();
                $order_items = $order->get_items();
                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = __('Order Number', 'sepal-woocommerce').' : ' . $order->get_order_number() . ' | '.__('Customer', 'sepal-woocommerce').' : ' . $order->billing_first_name . ' ' . $order->billing_last_name ;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ?: '-';
                $Email = $order->billing_email;
                $Payer = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = (int)$order->get_order_number();

                //Hooks for iranian developer
                $Description = apply_filters('WC_Sepal_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Sepal_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_Sepal_Email', $Email, $order_id);
                $Payer = apply_filters('WC_Sepal_Paymenter', $Payer, $order_id);
                $ResNumber = apply_filters('WC_Sepal_ResNumber', $ResNumber, $order_id);
                do_action('WC_Sepal_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';


                $data = array(
                    'apiKey' => $this->apiKey,
                    'amount' => $Amount,
                    'callbackUrl' => $CallbackUrl,
                    'invoiceNumber' => $order_id,
                    'description' => $Description,
                );
                $result = $this->SendRequestToSepal('request', ($data)); // json_encode
                if ($result === false) {
                    echo 'cURL Error';
                } else if ($result['status']) {
                    $paymentUrl = 'https://sepal.ir/payment/'.$result['paymentNumber'];
                    wp_redirect($paymentUrl);
                    exit;
                } else {
                    $Message = __('Transaction Failed', 'sepal-woocommerce').' : ' . $result['message'];
                    $Fault = $result['message'];
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('Error Connecting to gateway : %s', 'sepal-woocommerce'), $Message);
                    $Note = apply_filters('WC_Sepal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('Error Connecting to gateway : <br/>%s', 'sepal-woocommerce'), $Message);
                    $Notice = apply_filters('WC_Sepal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_Sepal_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_Sepal_Gateway()
            {


                $InvoiceNumber = isset($_POST['invoiceNumber']) ? sanitize_text_field($_POST['invoiceNumber']) : '';

                global $woocommerce;


                if (isset($_GET['wc_order'])) {
                    $order_id = sanitize_text_field($_GET['wc_order']);
                } else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_sepal;
                    unset($woocommerce->session->order_id_sepal);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_Sepal_Currency', $currency, $order_id);

                    if ($order->status !== 'completed') {

                        if (isset($_POST['status']) && intval($_POST['status']) == 1) {

                            $apiKey = $this->apiKey;
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
                                $strToLowerCurrency === strtolower('تومان') ||
                                $strToLowerCurrency === strtolower('تومان ایران'
                                )
                            ) {
                                $Amount *= 10;
                            } else if (strtolower($currency) === strtolower('IRHT')) {
                                $Amount *= 10000;
                            } else if (strtolower($currency) === strtolower('IRHR')) {
                                $Amount *= 1000;
                            } else if (strtolower($currency) === strtolower('IRR')) {
                                $Amount *= 1;
                            }

                            $paymentNumber = sanitize_text_field($_POST['paymentNumber']);
                            $data = array(
                                'apiKey' => $apiKey,
                                'paymentNumber' => $paymentNumber,
                                'invoiceNumber' => $order_id,
                            );
                            $result = $this->SendRequestToSepal('verify', ($data)); // json_encode

                            if ($result['status']) {
                                $Status = 'completed';
                                $Fault = '';
                                $Message = '';
                            } else {
                                $Status = 'failed';
                                $Fault = $result['message'];
                                $Message = __('Payment Failed', 'sepal-woocommerce');
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = __('Payment Cancelled', 'sepal-woocommerce');
                        }

                        if ($Status === 'completed' && isset($paymentNumber) && $paymentNumber !== 0) {
                            update_post_meta($order_id, '_transaction_id', $paymentNumber);


                            $order->payment_complete($paymentNumber);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('Payment Completed .<br/> Trace Number : %s', 'sepal-woocommerce'), $paymentNumber);
                            $Note = apply_filters('WC_Sepal_Return_from_Gateway_Success_Note', $Note, $order_id, $paymentNumber);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMassage));

                            $Notice = str_replace('{paymentNumber}', $paymentNumber, $Notice);

                            $Notice = apply_filters('WC_Sepal_Return_from_Gateway_Success_Notice', $Notice, $order_id, $paymentNumber);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Sepal_Return_from_Gateway_Success', $order_id, $paymentNumber);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }

                        if (($paymentNumber && ($paymentNumber != 0))) {
                            $tr_id = ('<br/>'.__('Payment Number', 'sepal-woocommerce').' : ' . $paymentNumber);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('Error CallBack from Gateway : %s %s', 'sepal-woocommerce'), $Message, $tr_id);

                        $Note = apply_filters('WC_Sepal_Return_from_Gateway_Failed_Note', $Note, $order_id, $paymentNumber, $Fault);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{paymentNumber}', '{fault}'), array($paymentNumber, $Message), $Notice);
                        $Notice = apply_filters('WC_Sepal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $paymentNumber, $Fault);
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_Sepal_Return_from_Gateway_Failed', $order_id, $paymentNumber, $Fault);

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }

                    $paymentNumber = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));

                    $Notice = str_replace('{paymentNumber}', $paymentNumber, $Notice);

                    $Notice = apply_filters('WC_Sepal_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $paymentNumber);
                    if ($Notice) {
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_Sepal_Return_from_Gateway_ReSuccess', $order_id, $paymentNumber);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault = __('Order ID NOT EXISTS', 'sepal-woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_Sepal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_Sepal_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }

        }

    }
}

add_action('plugins_loaded', 'Load_Sepal_Gateway', 666);
