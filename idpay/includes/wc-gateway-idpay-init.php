<?php

if (!defined('ABSPATH')) {
    exit;
}

function wc_gateway_idpay_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', 'wc_add_idpay_gateway');

        function wc_add_idpay_gateway($methods)
        {
            // Registers class WC_IDPAY as a payment method.
            $methods[] = 'WC_IDPay';

            return $methods;
        }

        // Allows the gateway to define some currencies.
        add_filter('woocommerce_currencies', 'wc_idpay_currencies');

        function wc_idpay_currencies($currencies)
        {
            $currencies['IRHR'] = __('Iranian hezar rial', 'woo-idpay-gateway');
            $currencies['IRHT'] = __('Iranian hezar toman', 'woo-idpay-gateway');

            return $currencies;
        }

        // Allows the gateway to define some currency symbols for the defined currency coeds.
        add_filter('woocommerce_currency_symbol', 'wc_idpay_currency_symbol', 10, 2);

        function wc_idpay_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRHR':
                    $currency_symbol = __('IRHR', 'woo-idpay-gateway');
                    break;

                case 'IRHT':
                    $currency_symbol = __('IRHT', 'woo-idpay-gateway');
                    break;
            }

            return $currency_symbol;
        }

        class WC_IDPay extends WC_Payment_Gateway
        {

            /* The API Key */
            protected $api_key;

            /* The sandbox mode.  */
            protected $sandbox;

            /* The payment success message. */
            protected $success_message;

            /* The payment failure message.  */
            protected $failed_message;

            /* The payment endpoint */
            protected $payment_endpoint;

            /* The verify endpoint */
            protected $verify_endpoint;

            /* The Order Status  */
            protected $order_status;


            /* Constructor for the gateway. */
            public function __construct()
            {
                $this->id = 'WC_IDPay';
                $this->method_title = __('IDPay', 'woo-idpay-gateway');
                $this->method_description = __('Redirects customers to IDPay to process their payments.', 'woo-idpay-gateway');
                $this->has_fields = false;
                $this->icon = apply_filters('WC_IDPay_logo', dirname(WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__))) . '/assets/images/logo.png');

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Get setting values.
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');

                $this->api_key = $this->get_option('api_key');
                $this->sandbox = $this->get_option('sandbox');

                $this->order_status = $this->get_option('order_status');

                $this->payment_endpoint = 'https://api.idpay.ir/v1.1/payment';
                $this->verify_endpoint = 'https://api.idpay.ir/v1.1/payment/verify';

                $this->success_message = $this->get_option('success_message');
                $this->failed_message = $this->get_option('failed_message');

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                        $this,
                        'process_admin_options',
                    ));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(
                        $this,
                        'process_admin_options',
                    ));
                }

                add_action('woocommerce_receipt_' . $this->id, array(
                    $this,
                    'idpay_checkout_receipt_page',
                ));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                    $this,
                    'idpay_checkout_return_handler',
                ));
            }

            /* Admin options for the gateway. */
            public function admin_options()
            {
                parent::admin_options();
            }

            /* Processes and saves the gateway options in the admin page.
            @return bool|void */
            public function process_admin_options()
            {
                parent::process_admin_options();
            }

            /* Initiate some form fields for the gateway settings. */
            public function init_form_fields()
            {
                // Populates the inherited property $form_fields.
                $this->form_fields = apply_filters('WC_IDPay_Config', array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woo-idpay-gateway'),
                        'type' => 'checkbox',
                        'label' => 'Enable IDPay gateway',
                        'description' => '',
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('Title', 'woo-idpay-gateway'),
                        'type' => 'text',
                        'description' => __('This gateway title will be shown when a customer is going to to checkout.', 'woo-idpay-gateway'),
                        'default' => __('IDPay payment gateway', 'woo-idpay-gateway'),
                    ),
                    'description' => array(
                        'title' => __('Description', 'woo-idpay-gateway'),
                        'type' => 'textarea',
                        'description' => __('This gateway description will be shown when a customer is going to to checkout.', 'woo-idpay-gateway'),
                        'default' => __('Redirects customers to IDPay to process their payments.', 'woo-idpay-gateway'),
                    ),
                    'webservice_config' => array(
                        'title' => __('Webservice Configuration', 'woo-idpay-gateway'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'api_key' => array(
                        'title' => __('API Key', 'woo-idpay-gateway'),
                        'type' => 'text',
                        'description' => __('You can create an API Key by going to https://idpay.ir/dashboard/web-services', 'woo-idpay-gateway'),
                        'default' => '',
                    ),
                    'sandbox' => array(
                        'title' => __('Sandbox', 'woo-idpay-gateway'),
                        'label' => __('Enable sandbox mode', 'woo-idpay-gateway'),
                        'description' => __('If you check this option, the gateway works in test (sandbox) mode.', 'woo-idpay-gateway'),
                        'type' => 'checkbox',
                        'default' => 'no',
                    ),
                    'order_status' => array(
                        'title' => __('Order status', 'woo-idpay-gateway'),
                        'label' => __('Choose order status', 'woo-idpay-gateway'),
                        'description' => __('You can choose order status after payment.', 'woo-idpay-gateway'),
                        'type' => 'select',
                        'options' => $this->valid_order_statuses(),
                        'default' => 'completed',
                    ),
                    'message_confing' => array(
                        'title' => __('Payment message configuration', 'woo-idpay-gateway'),
                        'type' => 'title',
                        'description' => __('Configure the messages which are displayed when a customer is brought back to the site from the gateway.', 'woo-idpay-gateway'),
                    ),
                    'success_message' => array(
                        'title' => __('Success message', 'woo-idpay-gateway'),
                        'type' => 'textarea',
                        'description' => __('Enter the message you want to display to the customer after a successful payment. You can also choose these placeholders {track_id}, {order_id} for showing the order id and the tracking id respectively.', 'woo-idpay-gateway'),
                        'default' => __('Your payment has been successfully completed. Track id: {track_id}', 'woo-idpay-gateway'),
                    ),
                    'failed_message' => array(
                        'title' => __('Failure message', 'woo-idpay-gateway'),
                        'type' => 'textarea',
                        'description' => __('Enter the message you want to display to the customer after a failure occurred in a payment. You can also choose these placeholders {track_id}, {order_id} for showing the order id and the tracking id respectively.', 'woo-idpay-gateway'),
                        'default' => __('Your payment has failed. Please try again or contact the site administrator in case of a problem.', 'woo-idpay-gateway'),
                    ),
                ));
            }

            /*  Process the payment and return the result. &  see process_order_payment() in the Woocommerce APIs
             * @return array  */
            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);

                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                );
            }

            /* Add IDPay Checkout items to receipt page. */
            public function idpay_checkout_receipt_page($order_id)
            {
                global $woocommerce;

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_IDPay_Currency', $currency, $order_id);

                $api_key = $this->api_key;
                $sandbox = $this->sandbox == 'no' ? 'false' : 'true';

                /** @var \WC_Customer $customer */
                $customer = $woocommerce->customer;

                // Customer information
                $phone = $customer->get_billing_phone();
                $mail = $customer->get_billing_email();
                $first_name = $customer->get_billing_first_name();
                $last_name = $customer->get_billing_last_name();
                $name = $first_name . ' ' . $last_name;

                $amount = $this->wc_idpay_get_amount(intval($order->get_total()), $currency);
                $desc = __('Oder number #', 'woo-idpay-gateway') . $order->get_order_number();
                $callback = add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_idpay'));

                if (empty($amount)) {
                    $notice = __('Selected currency is not supported', 'woo-idpay-gateway'); //todo
                    wc_add_notice($notice, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $data = array(
                    'order_id' => $order_id,
                    'amount' => $amount,
                    'name' => $name,
                    'phone' => $phone,
                    'mail' => $mail,
                    'desc' => $desc,
                    'callback' => $callback,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $api_key,
                    'X-SANDBOX' => $sandbox,
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 30,
                );

                $response = $this->call_gateway_endpoint($this->payment_endpoint, $args);
                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);
                $result = json_decode($result);

                if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
                    $note = '';
                    $note .= __('An error occurred while creating the transaction.', 'woo-idpay-gateway');
                    $note .= '<br/>';
                    $note .= sprintf(__('error status: %s', 'woo-idpay-gateway'), $http_status);

                    if (!empty($result->error_code) && !empty($result->error_message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error code: %s', 'woo-idpay-gateway'), $result->error_code);
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-idpay-gateway'), $result->error_message);
                        $order->add_order_note($note);
                        $notice = $result->error_message;
                        wc_add_notice($notice, 'error');
                    }

                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                // Save ID of this transaction
                IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_id', $result->id);

                // Set remote status of the transaction to 1 as it's primary value.
                IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_status', 1);

                $note = sprintf(__('transaction id: %s', 'woo-idpay-gateway'), $result->id);
                $order->add_order_note($note);
                wp_redirect($result->link);

                exit;
            }

            /* Handles the return from processing the payment. */
            public function idpay_checkout_return_handler()
            {
                global $woocommerce;

                // Check method post or get
                $method = $_SERVER['REQUEST_METHOD'];
                if ($method == 'POST') {
                    $status = sanitize_text_field($_POST['status']);
                    $track_id = sanitize_text_field($_POST['track_id']);
                    $id = sanitize_text_field($_POST['id']);
                    $order_id = sanitize_text_field($_POST['order_id']);
                } elseif ($method == 'GET') {
                    $status = sanitize_text_field($_GET['status']);
                    $track_id = sanitize_text_field($_GET['track_id']);
                    $id = sanitize_text_field($_GET['id']);
                    $order_id = sanitize_text_field($_GET['order_id']);
                }

                if (empty($id) || empty($order_id)) {
                    $this->idpay_display_invalid_order_message();
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }


                $order = wc_get_order($order_id);

                if (empty($order)) {
                    $this->idpay_display_invalid_order_message();
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                    $this->idpay_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }

                if (IdOrder::getOrderMetadata($order_id, 'idpay_transaction_status') >= 100) {
                    $this->idpay_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }

                // Stores order's meta data.
                IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_status', $status);
                IdOrder::updateOrderMetadata($order_id, 'idpay_track_id', $track_id);
                IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_id', $id);
                IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_order_id', $order_id);

                if ($status != 10) {
                    $order->update_status('failed');
                    $this->idpay_display_failed_message($order_id, $status);
                    $order->add_order_note($this->otherStatusMessages($status));
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

               //Check Double Spending and Order valid status
                if ($this->double_spending_occurred($order_id, $id)) {
                    $this->idpay_display_failed_message($order_id, 0);
                    $note = $this->otherStatusMessages(0);
                    $order->add_order_note($note);
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $api_key = $this->api_key;
                $sandbox = $this->sandbox == 'no' ? 'false' : 'true';

                $data = array(
                    'id' => IdOrder::getOrderMetadata($order_id, 'idpay_transaction_id'),
                    'order_id' => $order_id,
                );

                $headers = array(
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $api_key,
                    'X-SANDBOX' => $sandbox,
                );

                $args = array(
                    'body' => json_encode($data),
                    'headers' => $headers,
                    'timeout' => 30,
                );

                $response = $this->call_gateway_endpoint($this->verify_endpoint, $args);
                if (is_wp_error($response)) {
                    $note = $response->get_error_message();
                    $order->add_order_note($note);
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                }

                $http_status = wp_remote_retrieve_response_code($response);
                $result = wp_remote_retrieve_body($response);
                $result = json_decode($result);

                if ($http_status != 200) {
                    $note = '';
                    $note .= __('An error occurred while verifying the transaction.', 'woo-idpay-gateway');
                    $note .= '<br/>';
                    $note .= sprintf(__('error status: %s', 'woo-idpay-gateway'), $http_status);

                    if (!empty($result->error_code) && !empty($result->error_message)) {
                        $note .= '<br/>';
                        $note .= sprintf(__('error code: %s', 'woo-idpay-gateway'), $result->error_code);
                        $note .= '<br/>';
                        $note .= sprintf(__('error message: %s', 'woo-idpay-gateway'), $result->error_message);
                        $notice = $result->error_message;
                        wc_add_notice($notice, 'error');
                    }

                    $order->add_order_note($note);
                    $order->update_status('failed');
                    wp_redirect($woocommerce->cart->get_checkout_url());

                    exit;
                } else {
                    $verify_status = empty($result->status) ? null : $result->status;
                    $verify_track_id = empty($result->track_id) ? null : $result->track_id;
                    $verify_id = empty($result->id) ? null : $result->id;
                    $verify_order_id = empty($result->order_id) ? null : $result->order_id;
                    $verify_amount = empty($result->amount) ? null : $result->amount;
                    $verify_card_no = empty($result->payment->card_no) ? null : $result->payment->card_no;
                    $verify_hashed_card_no = empty($result->payment->hashed_card_no) ? null : $result->payment->hashed_card_no;
                    $verify_date = empty($result->payment->date) ? null : $result->payment->date;

                    // Check status
                    $status_helper = !empty($this->valid_order_statuses()[$this->order_status]) ? $this->order_status : 'completed';
                    $status = ($verify_status >= 100) ? $status_helper : 'failed';

                    // Completed
                    $note = sprintf(__('Transaction payment status: %s', 'woo-idpay-gateway'), $verify_status);
                    $note .= '<br/>';
                    $note .= sprintf(__('IDPay tracking id: %s', 'woo-idpay-gateway'), $verify_track_id);
                    $note .= '<br/>';
                    $note .= sprintf(__('Payer card number: %s', 'woo-idpay-gateway'), $verify_card_no);
                    $note .= '<br/>';
                    $note .= sprintf(__('Payer hashed card number: %s', 'woo-idpay-gateway'), $verify_hashed_card_no);
                    $order->add_order_note($note);

                    // Updates order's meta data after verifying the payment.
                    IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_status', $verify_status);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_track_id', $verify_track_id);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_id', $verify_id);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_order_id', $verify_order_id);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_transaction_amount', $verify_amount);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_payment_card_no', $verify_card_no);
                    IdOrder::updateOrderMetadata($order_id, 'idpay_payment_date', $verify_date);

                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_IDPay_Currency', $currency, $order_id);
                    $amount = $this->wc_idpay_get_amount(intval($order->get_total()), $currency);

                    if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $amount) {
                        $note = __('Error in transaction status or inconsistency with payment gateway information', 'woo-idpay-gateway');
                        $order->add_order_note($note);
                        $status = 'failed';
                    }

                    if ($status == 'failed') {
                        $order->update_status($status);
                        $this->idpay_display_failed_message($order_id);

                        wp_redirect($woocommerce->cart->get_checkout_url());

                        exit;
                    }

                    $order->payment_complete($verify_id);
                    $order->update_status($status);
                    $woocommerce->cart->empty_cart();
                    $this->idpay_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));

                    exit;
                }
            }

            /* Shows an invalid order message. */
            private function idpay_display_invalid_order_message($msgNumber = null)
            {
                $msg = $this->otherStatusMessages($msgNumber);
                $notice = '';
                $notice .= __('There is no order number referenced.', 'woo-idpay-gateway');
                $notice .= '<br/>';
                $notice .= __('Please try again or contact the site administrator in case of a problem.', 'woo-idpay-gateway');
                $notice = $notice . "<br>" . $msg;
                wc_add_notice($notice, 'error');
            }

            /* Shows a success message &  This message is configured at the admin page of the gateway. */
            private function idpay_display_success_message($order_id)
            {
                $track_id = IdOrder::getOrderMetadata($order_id, 'idpay_track_id');
                $notice = wpautop(wptexturize($this->success_message));
                $notice = str_replace("{track_id}", $track_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                wc_add_notice($notice, 'success');
            }

            /* Calls the gateway endpoints. & Tries to get response from the gateway for 4 times.
              @return array|\WP_Error */
            private function call_gateway_endpoint($url, $args)
            {
                $number_of_connection_tries = 4;
                while ($number_of_connection_tries) {
                    $response = wp_safe_remote_post($url, $args);
                    if (is_wp_error($response)) {
                        $number_of_connection_tries--;
                        continue;
                    } else {
                        break;
                    }
                }

                return $response;
            }

            /* Shows a failure message for the unsuccessful payments.
              This message is configured at the admin page of the gateway. */
            private function idpay_display_failed_message($order_id, $msgNumber = null)
            {
                $track_id = IdOrder::getOrderMetadata($order_id, 'idpay_track_id');
                $msg = $this->otherStatusMessages($msgNumber);
                $notice = wpautop(wptexturize($this->failed_message));
                $notice = str_replace("{track_id}", $track_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                $notice = $notice . "<br>" . $msg;
                wc_add_notice($notice, 'error');
            }

            /** Checks if double-spending is occurred */
            private function double_spending_occurred($order_id, $remote_id)
            {
                if (IdOrder::getOrderMetadata($order_id, 'idpay_transaction_id') != $remote_id) {
                    return true;
                }

                return false;
            }

            public function otherStatusMessages($msgNumber = null)
            {
                switch ($msgNumber) {
                    case '1':
                        $msg = 'پرداخت انجام نشده است';
                        break;
                    case '2':
                        $msg = 'پرداخت ناموفق بوده است';
                        break;
                    case '3':
                        $msg = 'خطا رخ داده است';
                        break;
                    case '4':
                        $msg = 'بلوکه شده';
                        break;
                    case '5':
                        $msg = 'برگشت به پرداخت کننده';
                        break;
                    case '6':
                        $msg = 'برگشت خورده سیستمی';
                        break;
                    case '7':
                        $msg = 'انصراف از پرداخت';
                        break;
                    case '8':
                        $msg = 'به درگاه پرداخت منتقل شد';
                        break;
                    case '10':
                        $msg = 'در انتظار تایید پرداخت';
                        break;
                    case '100':
                        $msg = 'پرداخت تایید شده است';
                        break;
                    case '101':
                        $msg = 'پرداخت قبلا تایید شده است';
                        break;
                    case '200':
                        $msg = 'به دریافت کننده واریز شد';
                        break;
                    case '0':
                        $msg = 'سواستفاده از تراکنش قبلی';
                        break;
                    case '404':
                        $msg = 'واحد پول انتخاب شده پشتیبانی نمی شود.';
                        $msgNumber = '404';
                        break;
                    case null:
                        $msg = 'خطا دور از انتظار';
                        $msgNumber = '1000';
                        break;
                }

                return $msg . ' -وضعیت: ' . $msgNumber;
            }

            private function valid_order_statuses()
            {
                return [
                  'completed' => 'completed',
                  'processing' => 'processing',
                ];
            }

            public function wc_idpay_get_amount($amount, $currency)
            {
                switch (strtolower($currency)) {
                    case strtolower('IRR'):
                    case strtolower('RIAL'):
                        return $amount;

                    case strtolower('تومان ایران'):
                    case strtolower('تومان'):
                    case strtolower('IRT'):
                    case strtolower('Iranian_TOMAN'):
                    case strtolower('Iran_TOMAN'):
                    case strtolower('Iranian-TOMAN'):
                    case strtolower('Iran-TOMAN'):
                    case strtolower('TOMAN'):
                    case strtolower('Iran TOMAN'):
                    case strtolower('Iranian TOMAN'):
                        return $amount * 10;

                    case strtolower('IRHR'):
                        return $amount * 1000;

                    case strtolower('IRHT'):
                        return $amount * 10000;

                    default:
                        return 0;
                }
            }
        }
    }
}


/* Add a function when hook 'plugins_loaded' is fired. &  Registers the 'wc_gateway_idpay_init' function to the
   internal hook of Wordpress: 'plugins_loaded'. */
add_action('plugins_loaded', 'wc_gateway_idpay_init');
