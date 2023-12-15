<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize the IDPAY gateway.
 *
 * When the internal hook 'plugins_loaded' is fired, this function would be
 * executed and after that, a Woocommerce hook (woocommerce_payment_gateways)
 * which defines a new gateway, would be triggered.
 *
 * Therefore whenever all plugins are loaded, the IDPAY gateway would be
 * initialized.
 *
 * Also another Woocommerce hooks would be fired in this process:
 *  - woocommerce_currencies
 *  - woocommerce_currency_symbol
 *
 * The two above hooks allows the gateway to define some currencies and their
 * related symbols.
 */
function gateway_raypay_init()
{

    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'add_raypay_gateway');

        function add_raypay_gateway($methods)
        {
            // Registers class WC_RayPAY as a payment method.
            $methods[] = 'WOO_RayPay_Gateway';

            return $methods;
        }

        // Allows the gateway to define some currencies.
        add_filter('woocommerce_currencies', 'raypay_currencies');

        function raypay_currencies($currencies)
        {
            $currencies['IRHR'] = __('Iranian hezar rial', 'woo-raypay-gateway');
            $currencies['IRHT'] = __('Iranian hezar toman', 'woo-raypay-gateway');

            return $currencies;
        }

        // Allows the gateway to define some currency symbols for the defined currency coeds.
        add_filter('woocommerce_currency_symbol', 'raypay_currency_symbol', 10, 2);

        function raypay_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {

                case 'IRHR':
                    $currency_symbol = __('IRHR', 'woo-raypay-gateway');
                    break;

                case 'IRHT':
                    $currency_symbol = __('IRHT', 'woo-raypay-gateway');
                    break;
            }

            return $currency_symbol;
        }

        class WOO_RayPay_Gateway extends WC_Payment_Gateway
        {
            /**
             * The UserID
             *
             * @var string
             */
            protected $user_id;

            /**
             * The Acceptor Code
             *
             * @var string
             */
            protected $acceptor_code;

            /**
             * The payment success message.
             *
             * @var string
             */
            protected $success_message;

            /**
             * The payment failure message.
             *
             * @var string
             */
            protected $failed_message;

            /**
             * The payment endpoint
             *
             * @var string
             */
            protected $payment_endpoint;

            /**
             * The verify endpoint
             *
             * @var string
             */
            protected $verify_endpoint;


            /**
             * Constructor for the gateway.
             */
            public function __construct()
            {
                $this->id = 'WOO_RayPay_Gateway';
                $this->method_title = __('RayPay', 'woo-raypay-gateway');
                $this->method_description = __('Redirects customers to RayPay to process their payments.', 'woo-raypay-gateway');
                $this->has_fields = FALSE;
                $this->icon = apply_filters('WOO_RayPay_Gateway_logo', dirname(WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__))) . '/assets/images/logo.png');

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Get setting values.
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');

                $this->user_id = $this->get_option('user_id');
                $this->acceptor_code = $this->get_option('acceptor_code');

               $this->payment_endpoint = 'http://185.165.118.211:14000/raypay/api/v1/Payment/getPaymentTokenWithUserID';
                $this->verify_endpoint = 'http://185.165.118.211:14000/raypay/api/v1/Payment/checkInvoice';

                $this->success_message = $this->get_option('success_message');
                $this->failed_message = $this->get_option('failed_message');

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options',));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options',));
                }

                add_action('woocommerce_receipt_' . $this->id, array($this, 'raypay_checkout_receipt_page',));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'raypay_checkout_return_handler',));

            }

            /**
             * Admin options for the gateway.
             */
            public function admin_options()
            {
                parent::admin_options();
            }

            /**
             * Processes and saves the gateway options in the admin page.
             *
             * @return bool|void
             */
            public function process_admin_options()
            {
                parent::process_admin_options();
            }

            /**
             * Initiate some form fields for the gateway settings.
             */
            public function init_form_fields()
            {
                // Populates the inherited property $form_fields.
                $this->form_fields = apply_filters('WOO_RayPay_Gateway_Config', array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woo-raypay-gateway'),
                        'type' => 'checkbox',
                        'label' => 'Enable RayPay gateway',
                        'description' => '',
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('Title', 'woo-raypay-gateway'),
                        'type' => 'text',
                        'description' => __('This gateway title will be shown when a customer is going to to checkout.', 'woo-raypay-gateway'),
                        'default' => __('RayPay payment gateway', 'woo-raypay-gateway'),
                    ),
                    'description' => array(
                        'title' => __('Description', 'woo-raypay-gateway'),
                        'type' => 'textarea',
                        'description' => __('This gateway description will be shown when a customer is going to to checkout.', 'woo-raypay-gateway'),
                        'default' => __('Redirects customers to RayPay to process their payments.', 'woo-raypay-gateway'),
                    ),
                    'webservice_config' => array(
                        'title' => __('Webservice Configuration', 'woo-raypay-gateway'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'user_id' => array(
                        'title' => __('User ID', 'woo-raypay-gateway'),
                        'type' => 'text',
                        'description' => __('You can receive your User ID from RayPay panel https://panel.raypay.ir', 'woo-raypay-gateway'),
                        'default' => '20064',
                    ),
                    'acceptor_code' => array(
                        'title' => __('Acceptor Code', 'woo-raypay-gateway'),
                        'type' => 'text',
                        'description' => __('You can receive your Acceptor Code from RayPay panel https://panel.raypay.ir', 'woo-raypay-gateway'),
                        'default' => '220000000003751',
                    ),
                    'message_confing' => array(
                        'title' => __('Payment message configuration', 'woo-raypay-gateway'),
                        'type' => 'title',
                        'description' => __('Configure the messages which are displayed when a customer is brought back to the site from the gateway.', 'woo-raypay-gateway'),
                    ),
                    'success_message' => array(
                        'title' => __('Success message', 'woo-raypay-gateway'),
                        'type' => 'textarea',
                        'description' => __('Enter the message you want to display to the customer after a successful payment. You can also choose these placeholders {track_id}, {order_id} for showing the order id and the tracking id respectively.', 'woo-raypay-gateway'),
                        'default' => __('Your payment has been successfully completed. Track id: {track_id}', 'woo-raypay-gateway'),
                    ),
                    'failed_message' => array(
                        'title' => __('Failure message', 'woo-raypay-gateway'),
                        'type' => 'textarea',
                        'description' => __('Enter the message you want to display to the customer after a failure occurred in a payment. You can also choose these placeholders {track_id}, {order_id} for showing the order id and the tracking id respectively.', 'woo-raypay-gateway'),
                        'default' => __('Your payment has failed. Please try again or contact the site administrator in case of a problem.', 'woo-raypay-gateway'),
                    ),
                ));
            }


            /**
             * Process the payment and return the result.
             *
             * see process_order_payment() in the Woocommerce APIs
             *
             * @param int $order_id
             *
             * @return array
             */
            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);

                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(TRUE),
                );
            }

            /**
             * Add RayPay Checkout items to receipt page.
             */
            public function raypay_checkout_receipt_page($order_id)
            {
                global $woocommerce;

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WOO_RayPay_Gateway_Currency', $currency, $order_id);

                $user_id = $this->user_id;
                $acceptor_code = $this->acceptor_code;


                /** @var \WC_Customer $customer */
                $customer = $woocommerce->customer;

                // Customer information
                $phone = $customer->get_billing_phone();
                $mail = $customer->get_billing_email();
                $invoice_id = round(microtime(true)*1000) ;
                $first_name = $customer->get_billing_first_name();
                $last_name = $customer->get_billing_last_name();
                $name = $first_name . ' ' . $last_name;

//                $amount = raypay_get_amount(intval($order->get_total()), $currency);
                $amount = raypay_get_amount($order->get_total(), $currency);
                $callback = add_query_arg('wc_order', $order_id, WC()->api_request_url('woo_raypay_gateway'));
                $callback .= "&";

                if (empty($amount)) {
                    $notice = __('Selected currency is not supported', 'woo-raypay-gateway'); //todo
                    wc_add_notice($notice, 'error');
                    wp_redirect(wc_get_checkout_url());


                    return FALSE;

                }

                $data = array(
                    'amount' => strval($amount),
                    'invoiceID' => strval($invoice_id),
                    'userID' => $user_id,
                    'redirectUrl' => $callback,
                    'factorNumber' => strval($order_id),
                    'acceptorCode' => $acceptor_code,
                    'mobile' => $phone,
                    'email' => $mail,
                    'fullName' => $name,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 15,
                );


                $response = $this->call_gateway_endpoint($this->payment_endpoint, $args);

                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect(wc_get_checkout_url());
//                    wp_redirect($woocommerce->cart->get_checkout_url());

                    return FALSE;

                }
                $http_status = wp_remote_retrieve_response_code($response);


                $result = wp_remote_retrieve_body($response);

                $result = json_decode($result);

                if ($http_status != 200 || empty($result) || empty($result->Data)) {
                    $note = '';
                    $note .= __('An error occurred while creating the transaction.', 'woo-raypay-gateway');
//                    $note .= '<br/>';
//                    $note .= sprintf(__('error status: %s', 'woo-raypay-gateway'), $http_status);

                    if (!empty($result->Message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-raypay-gateway'), $result->Message);
                        $order->add_order_note($note);
                        $notice = $result->Message;
                        wc_add_notice($notice, 'error');
                    }

                    wp_redirect(wc_get_checkout_url());
                    return FALSE;

                }

                $access_token = $result->Data->Accesstoken;
                $terminal_id = $result->Data->TerminalID;

                // Save AccessToken of this transaction
                update_post_meta($order_id, 'raypay_access_token', $access_token);

                update_post_meta($order_id, 'raypay_terminal_id', $terminal_id);

                update_post_meta($order_id, 'raypay_invoice_id', $invoice_id);

                // Set remote status of the transaction to 1 as it's primary value.
                update_post_meta($order_id, 'raypay_transaction_status', 1);

               // $note = sprintf(__('transaction id: %s', 'woo-raypay-gateway'), $access_token);
               // $order->add_order_note($note);
                raypay_send_data_shaparak($access_token , $terminal_id);
                return FALSE;

            }

            /**
             * Handles the return from processing the payment.
             */
            public function raypay_checkout_return_handler()
            {
                global $woocommerce;

                // Check order_id in query string
                 $order_id = sanitize_text_field($_GET['wc_order']);

                if (empty($order_id)) {
                    $this->raypay_display_invalid_order_message();
                    wp_redirect(wc_get_checkout_url());

                    return FALSE;
                }


                $order = wc_get_order($order_id);

                if (empty($order)) {
                    $this->raypay_display_invalid_order_message();
                    wp_redirect(wc_get_checkout_url());

                    return FALSE;
                }


                if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                    $this->raypay_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    return FALSE;
                }

                if (get_post_meta($order_id, 'raypay_transaction_status', TRUE) >= 100) {
                    $this->raypay_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    return FALSE;
                }

                // Stores order's meta data.

                update_post_meta($order_id, 'raypay_transaction_order_id', $order_id);



                $invoice_id = get_post_meta($order_id, 'raypay_invoice_id', TRUE);
                $verify_url = add_query_arg('pInvoiceID', $invoice_id, $this->verify_endpoint);


                $data = array(
                    'order_id' => $order_id,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 15,
                );


                $response = $this->call_gateway_endpoint($verify_url, $args);
                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect(wc_get_checkout_url());
                    return FALSE;
                }

                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);
                $result = json_decode($result);

                if ($http_status != 200) {
                    $note = '';
                    $note .= __('An error occurred while verifying the transaction.', 'woo-raypay-gateway');


                    if (!empty($result->Message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-raypay-gateway'), $result->Message);
                        $notice = $result->Message;
                        wc_add_notice($notice, 'error');
                    }

                    $order->add_order_note($note);
                    $order->update_status('failed');
                    wp_redirect(wc_get_checkout_url());
                    return FALSE;
                } else {
                    $state = $result->Data->State;

                    //$verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
                   // $verify_id = empty($result->id) ? NULL : $result->id;
                    $verify_order_id = $result->Data->FactorNumber;
                    $verify_amount = $result->Data->Amount;

                    //check type of product for definition order status
                    $has_downloadable = $order->has_downloadable_item();
                    $status_helper = ($has_downloadable) ? 'completed' : 'processing';
                    $status = ($state == 1) ? $status_helper : 'failed';

                    //completed
                    $note = sprintf(__('Transaction payment status: %s', 'woo-raypay-gateway'), $state);
                    $note .= '<br/>';
                    $order->add_order_note($note);

                    // Updates order's meta data after verifying the payment.
                    update_post_meta($order_id, 'raypay_transaction_status', $state);
                    update_post_meta($order_id, 'raypay_transaction_order_id', $verify_order_id);
                    update_post_meta($order_id, 'raypay_transaction_amount', $verify_amount);


                    if ($state == 0) {
                        $order->update_status('failed');
                        $this->raypay_display_failed_message($order_id);

                        wp_redirect(wc_get_checkout_url());

                        return FALSE;
                    } elseif ($status == 'processing' or $status == 'completed') {

                        //$order->payment_complete($order_id);
                        $order->update_status($status);
                        $woocommerce->cart->empty_cart();
                        $this->raypay_display_success_message($order_id);
                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        return FALSE;
                    }
                }
            }

            /**
             * Shows an invalid order message.
             *
             * @see raypay_checkout_return_handler().
             */
            private function raypay_display_invalid_order_message($msgNumber = null)
            {
                $msg = $this->otherStatusMessages($msgNumber);
                $notice = '';
                $notice .= __('There is no order number referenced.', 'woo-raypay-gateway');
                $notice .= '<br/>';
                $notice .= __('Please try again or contact the site administrator in case of a problem.', 'woo-raypay-gateway');
                $notice = $notice . "<br>" . $msg;
                wc_add_notice($notice, 'error');
            }

            /**
             * Shows a success message
             *
             * This message is configured at the admin page of the gateway.
             *
             * @see raypay_checkout_return_handler()
             *
             * @param $order_id
             */
            private function raypay_display_success_message($order_id)
            {
                $notice = $this->success_message;
                wc_add_notice($notice, 'success');
            }

            /**
             * Calls the gateway endpoints.
             *
             * Tries to get response from the gateway for 1 times.
             *
             * @param $url
             * @param $args
             *
             * @return array|\WP_Error
             */
            private function call_gateway_endpoint($url, $args)
            {
                $number_of_connection_tries = 1;
                while ($number_of_connection_tries) {
                    $response = wp_remote_post($url, $args);
                    if (is_wp_error($response)) {
                        $number_of_connection_tries--;
                        continue;
                    } else {
                        break;
                    }
                }

                return $response;
            }

            /**
             * Shows a failure message for the unsuccessful payments.
             *
             * This message is configured at the admin page of the gateway.
             *
             * @see raypay_checkout_return_handler()
             *
             * @param $order_id
             */
            private function raypay_display_failed_message($order_id)
            {
                $notice = $this->failed_message;
                wc_add_notice($notice, 'error');
            }
        }

    }
}


/**
 * Add a function when hook 'plugins_loaded' is fired.
 *
 * Registers the 'gateway_raypay_init' function to the
 * internal hook of Wordpress: 'plugins_loaded'.
 *
 * @see gateway_raypay_init()
 */
add_action('plugins_loaded', 'gateway_raypay_init');
