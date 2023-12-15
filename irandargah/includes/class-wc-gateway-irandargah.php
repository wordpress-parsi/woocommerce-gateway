<?php

/**
 * IranDargah Payment Gateway
 *
 * Provides a IranDargah Payment Gateway.
 *
 * @class  woocommerce_irandargah
 * @package WooCommerce
 * @category Payment Gateways
 * @author IranDargah
 */
class WC_Gateway_IranDargah extends WC_Payment_Gateway
{

    /**
     * Version
     *
     * @var string
     */
    public $version;

    /**
     * @access protected
     * @var array $data_to_send
     */
    protected $data_to_send = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version              = WC_GATEWAY_IRANDARGAH_VERSION;
        $this->id                   = 'irandargah';
        $this->method_title         = __('IranDargah', 'irandargah-woocommerce-ipg');
        $this->method_description   = sprintf(__('IranDargah payment gateway for woocommerce.', 'irandargah-woocommerce-ipg'), '<a href="https://irandargah.com">', '</a>');
        $this->icon                 = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__))) . '/assets/images/icon.svg';
        $this->debug_email          = get_option('admin_email');
        $this->available_countries  = array('IR');
        $this->available_currencies = (array) apply_filters('woocommerce_gateway_irandargah_available_currencies', array('IRR', 'IRT'));

        $this->supports = array(
            'products',
        );

        $this->failed_message = "تراکنش انجام نشد";

        $this->init_form_fields();
        $this->init_settings();

        // Setup default merchant data.
        $this->merchant_id = $this->get_option('merchant_id');
        // $this->wsdl_url = 'https://dargaah.com/wsdl';
        $this->url              = 'https://dargaah.com/payment';
        $this->validate_url     = 'https://dargaah.com/verification';
        $this->redirect_url     = 'https://dargaah.com/ird/startpay/%s';
        $this->title            = $this->get_option('title');
        $this->response_url     = add_query_arg('wc-api', 'wc_gateway_irandargah', home_url('/'));
        $this->send_debug_email = 'yes' === $this->get_option('send_debug_email');
        $this->description      = $this->get_option('description');
        $this->enabled          = 'yes' === $this->get_option('enabled') ? 'yes' : 'no';
        $this->enable_logging   = 'yes' === $this->get_option('enable_logging');

        // Setup the test data, if in test mode.
        if ('yes' === $this->get_option('testmode')) {
            $this->url          = 'https://dargaah.com/sandbox/payment';
            $this->validate_url = 'https://dargaah.com/sandbox/verification';
            $this->redirect_url = 'https://dargaah.com/sandbox/ird/startpay/%s';
            $this->add_testmode_admin_settings_notice();
        }
        else {
            $this->send_debug_email = false;
        }

        add_action('woocommerce_api_wc_gateway_irandargah', array($this, 'check_response'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_irandargah', array($this, 'receipt_page'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 2.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'           => array(
                'title'       => __('Enable/Disable', 'irandargah-woocommerce-ipg'),
                'label'       => __('Enable IranDargah', 'irandargah-woocommerce-ipg'),
                'type'        => 'checkbox',
                'description' => __('This controls whether or not this gateway is enabled within WooCommerce.', 'irandargah-woocommerce-ipg'),
                'default'     => 'no',        // User should enter the required information before enabling the gateway.
                'desc_tip'    => true,
            ),
            'title'             => array(
                'title'       => __('Title', 'irandargah-woocommerce-ipg'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'irandargah-woocommerce-ipg'),
                'default'     => 'ایران درگاه',
                'desc_tip'    => true,
            ),
            'description'       => array(
                'title'       => __('Description', 'irandargah-woocommerce-ipg'),
                'type'        => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'irandargah-woocommerce-ipg'),
                'default'     => 'پرداخت با تمام کارت‌های بانکی عضو شتاب',
                'desc_tip'    => true,
            ),
            'testmode'          => array(
                'title'       => __('IranDargah Sandbox', 'irandargah-woocommerce-ipg'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in development mode.', 'irandargah-woocommerce-ipg'),
                'default'     => 'no',
            ),
            'merchant_id'       => array(
                'title'       => __('Merchant ID', 'irandargah-woocommerce-ipg'),
                'type'        => 'text',
                'description' => __('This is the merchant id, received from IranDargah.', 'irandargah-woocommerce-ipg'),
                'default'     => '',
            ),
            'connection_method' => array(
                'title'       => __('Gateway connection method', 'irandargah-woocommerce-ipg'),
                'type'        => 'select',
                'description' => __('connect to irandargah webservice by selecting one of available methods.', 'irandargah-woocommerce-ipg'),
                'options'     => array(
                    null        => __('Choose one', 'irandargah-woocommerce-ipg'),
                    'REST_POST' => __('REST-POST', 'irandargah-woocommerce-ipg'),
                    'REST_GET'  => __('REST-GET', 'irandargah-woocommerce-ipg'),
                    // 'SOAP'      => __('SOAP', 'irandargah-woocommerce-ipg'),
                ),
                'default'     => 'REST_POST',
            ),
            'currency'          => array(
                'title'       => __('Store active curreny', 'irandargah-woocommerce-ipg'),
                'type'        => 'select',
                'description' => __('Select your default active curreny in shop.', 'irandargah-woocommerce-ipg'),
                'options'     => array(
                    null  => __('Choose one', 'irandargah-woocommerce-ipg'),
                    'IRR' => __('IRR', 'irandargah-woocommerce-ipg'),
                    'IRT' => __('IRT', 'irandargah-woocommerce-ipg'),
                ),
                'default'     => in_array(get_woocommerce_currency(), $this->available_currencies) ? get_woocommerce_currency() : null,
            ),
            'over100million'    => array(
                'title'       => __('Do you have product price over 100 million toman?', 'irandargah-woocommerce-ipg'),
                'type'        => 'select',
                'description' => __('If you choose Yes you should check order manually for remaining amount.', 'irandargah-woocommerce-ipg'),
                'options'     => array(
                    null  => __('Choose one', 'irandargah-woocommerce-ipg'),
                    'no'  => __('No', 'irandargah-woocommerce-ipg'),
                    'yes' => __('Yes', 'irandargah-woocommerce-ipg'),
                ),
                'default'     => 'no',
            ),
            'send_debug_email'  => array(
                'title'   => __('Send Debug Emails', 'irandargah-woocommerce-ipg'),
                'type'    => 'checkbox',
                'label'   => __('Send debug e-mails for transactions through the Irandargah gateway (sends on successful transaction as well).', 'irandargah-woocommerce-ipg'),
                'default' => 'yes',
            ),
            'debug_email'       => array(
                'title'       => __('Who Receives Debug E-mails?', 'irandargah-woocommerce-ipg'),
                'type'        => 'text',
                'description' => __('The e-mail address to which debugging error e-mails are sent when in test mode.', 'irandargah-woocommerce-ipg'),
                'default'     => get_option('admin_email'),
            ),
            'enable_logging'    => array(
                'title'   => __('Enable Logging', 'irandargah-woocommerce-ipg'),
                'type'    => 'checkbox',
                'label'   => __('Enable transaction logging for gateway.', 'irandargah-woocommerce-ipg'),
                'default' => 'no',
            ),
        );
    }

    /**
     * Get the required form field keys for setup.
     *
     * @return array
     */
    public function get_required_settings_keys()
    {
        return array(
            'merchant_id'
        );
    }

    /**
     * Determine if the gateway still requires setup.
     *
     * @return bool
     */
    public function needs_setup()
    {
        return !$this->get_option('merchant_id');
    }

    /**
     * add_testmode_admin_settings_notice()
     * Add a notice to the merchant_id fields when in test mode.
     *
     * @since 2.0.0
     */
    public function add_testmode_admin_settings_notice()
    {
        $this->form_fields['merchant_id']['description'] .= '<br /><strong>' . esc_html__('Sandbox is currently in use.', 'irandargah-woocommerce-ipg') . '</strong>';
    }

    /**
     * check_requirements()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     * @return array
     */
    public function check_requirements()
    {
        $errors = [
            // Check if the store currency is supported by Irandargah
            !in_array(get_woocommerce_currency(), $this->available_currencies) ? 'wc-gateway-irandargah-error-invalid-currency' : null,
            // Check if user entered the merchant ID
            'yes' !== $this->get_option('testmode') && empty($this->get_option('merchant_id')) ? 'wc-gateway-irandargah-error-missing-merchant-id' : null
        ];

        return array_filter($errors);
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available()
    {
        if ('yes' === $this->enabled) {
            $errors = $this->check_requirements();
            // Prevent using this gateway on frontend if there are any configuration errors.
            return 0 === count($errors);
        }

        return parent::is_available();
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 2.0.0
     */
    public function admin_options()
    {
        if (in_array(get_woocommerce_currency(), $this->available_currencies)) {
            parent::admin_options();
        }
        else {
            ?>
            <h3>
                <?php esc_html_e('IranDargah', 'irandargah-woocommerce-ipg'); ?>
            </h3>
            <div class="inline error">
                <p><strong>
                        <?php esc_html_e('Gateway Disabled', 'irandargah-woocommerce-ipg'); ?>
                    </strong>
                    <?php echo wp_kses_post(sprintf(__('Choose Iranian Rial or Iranian Toman as your store currency in %1$sGeneral Settings%2$s to enable the IranDargah Gateway.', 'irandargah-woocommerce-ipg'), '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=general')) . '">', '</a>')); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Proccess payment of order.
     *
     * @since 2.0.0
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Generate the IranDargah button link.
     *
     * @since 2.0.0
     */
    public function generate_irandargah_form($order_id)
    {
        global $woocommerce;

        $order = wc_get_order($order_id);

        $amount = intval($order->get_total()) * ($this->get_option('currency') == 'IRT' ? 10 : 1);
        if ($this->get_option('over100million') == 'yes') {
            $amount = ($amount > 999900000) ? 999900000 : $amount;
        }

        $products    = array();
        $order_items = $order->get_items();
        foreach ($order_items as $product) {
            $products[] = $product['name'] . " (" . $product['qty'] . ") ";
        }
        $products = implode(' - ', $products);

        // Construct variables for post
        $this->data_to_send = array(
            // Merchant details
            'merchantID'  => $this->get_option('testmode') == 'no' ? $this->merchant_id : 'TEST',
            'callbackURL' => add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_gateway_irandargah')),
            // Billing details
            'mobile'      => self::get_order_prop($order, 'billing_phone'),
            'orderId'     => self::get_order_prop($order, 'id'),
            'amount'      => $amount,
            'description' => "سفارش شماره: " . self::get_order_prop($order, 'id') . " | خریدار: " . self::get_order_prop($order, 'billing_first_name') . " " . self::get_order_prop($order, 'billing_last_name') . ' | محصولات: ' . $products,
        );

        $response = $this->send_request_to_irandargah_gateway(
            $this->get_option('testmode') == 'yes' ? 'SANDBOX' : $this->get_option('connection_method'),
            'payment',
            $this->data_to_send
        );

        $note = $response->message;
        $order->add_order_note($note);
        if (intval($response->status) != 200) {
            wp_redirect($woocommerce->cart->get_checkout_url());
            exit;
        }
        else if (intval($response->status) == 200) {
            wp_redirect(sprintf($this->redirect_url, $response->authority));
            exit;
        }
    }

    /**
     * Reciept page.
     *
     * Display text and a button to direct the user to IranDargah.
     *
     * @since 2.0.0
     */
    public function receipt_page($order)
    {
        try {
            echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Irandargah.', 'irandargah-woocommerce-ipg') . '</p>';
            $this->generate_irandargah_form($order);
        }
        catch (\Exception $ex) {
            echo __('Error in connecting to gateway', 'irandargah-woocommerce-ipg');
        }
    }

    /**
     * Check IranDargah response.
     *
     * @since 2.0.0
     */
    public function check_response()
    {
        $this->handle_response(
            $this->get_option('connection_method') == 'REST_GET' && $this->get_option('testmode') == 'no' ? stripslashes_deep($_GET) : stripslashes_deep($_POST)
        );

        // Notify IranDargah that information has been received
        header('HTTP/1.0 200 OK');
        flush();
    }

    /**
     * Check IranDargah ITN validity.
     *
     * @param array $data
     * @since 2.0.0
     */
    public function handle_response($data)
    {
        $this->log(
            PHP_EOL
            . '----------'
            . PHP_EOL . 'IranDargah Callback Data has been received'
            . PHP_EOL . '----------'
        );
        $this->log('Get posted data');
        $this->log('IranDargah Data: ' . print_r($data, true));

        $order_id = absint($_GET['wc_order']);
        $order    = wc_get_order($order_id);

        $this->log_order_details($order);

        // Check if order has already been processed
        if ('completed' === self::get_order_prop($order, 'status') || 'processing' === self::get_order_prop($order, 'status')) {
            $this->log('Order has already been processed');
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
        }

        // Check if order cancelled, refunded or failed
        if ($order->get_status() == 'cancelled' || $order->get_status() == 'refunded' || $order->get_status() == 'failed') {
            $this->log('Order has already been cancelled');
            exit;
        }

        if ($data['code'] == 100 and $order->get_status() == 'pending') {
            $this->handle_payment_complete($data, $order);
        }
        elseif ($data['code'] == 101) {
            $this->log('Order has already been processed');
            exit;
        }
        else {
            $this->handle_payment_failed($data, $order);
        }

        $this->log(
            PHP_EOL
            . '----------'
            . PHP_EOL . 'End processing callback data'
            . PHP_EOL . '----------'
        );
    }

    /**
     * Handle logging the order details.
     *
     * @since 2.0.0
     */
    public function log_order_details($order)
    {
        $customer_id = $order->get_user_id();

        $details = "Order Details:"
            . PHP_EOL . 'customer id:' . $customer_id
            . PHP_EOL . 'order id:   ' . $order->get_id()
            . PHP_EOL . 'parent id:  ' . $order->get_parent_id()
            . PHP_EOL . 'status:     ' . $order->get_status()
            . PHP_EOL . 'total:      ' . $order->get_total()
            . PHP_EOL . 'currency:   ' . $order->get_currency()
            . PHP_EOL . 'key:        ' . $order->get_order_key()
            . "";

        $this->log($details);
    }

    /**
     * This function handles payment complete request by IranDargah.
     *
     * @param array $data should be from the Gateway ITN callback.
     * @param WC_Order $order
     */
    public function handle_payment_complete($data, $order)
    {
        global $woocommerce;
        $this->log('- Complete Payment');
        $order->add_order_note(esc_html__('payment has been completed', 'irandargah-woocommerce-ipg'));
        $order->update_meta_data('irandargah_payment_amount', number_format(sanitize_text_field($data['amount']), 0, '.', ','));
        $order_id = self::get_order_prop($order, 'id');

        $price = intval($order->get_total()) * ($this->get_option('currency') == 'IRT' ? 10 : 1);
        if ($this->get_option('over100million') == 'yes') {
            $amount = ($price > 999900000) ? 999900000 : $price;
        }
        else {
            $amount = $price;
        }

        if ($amount != sanitize_text_field($data['amount'])) {
            $order->add_order_note($this->failed_message . '<br />' . __('amount is not equal to order amount', 'irandargah-woocommerce-ipg'));
            // $order->update_status('failed');
            wp_redirect($woocommerce->cart->get_checkout_url());
            exit;
        }

        $this->data_to_send = [
            'merchantID' => $this->get_option('testmode') == 'no' ? $this->get_option('merchant_id') : 'TEST',
            'authority'  => sanitize_text_field($data['authority']),
            'amount'     => $amount,
            'orderId'    => sanitize_text_field($data['orderId']),
        ];

        if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
        }

        if (get_post_meta($order_id, 'irandargah_transaction_status', true) == 100) {
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
        }

        $verification_result = $this->send_request_to_irandargah_gateway(
            $this->get_option('testmode') == 'yes' ? 'SANDBOX' : $this->get_option('connection_method'),
            'verification',
            $this->data_to_send
        );

        if ($verification_result->status == 100) {
            //completed
            $note = sprintf(__('Transaction payment status: %s', 'irandargah-woocommerce-ipg'), $verification_result->message);
            $note .= '<br/>';
            $note .= sprintf(__('Amount of payment: %s ریال', 'irandargah-woocommerce-ipg'), number_format(sanitize_text_field($data['amount']), 0, '.', ','));
            $note .= '<br/>';
            $note .= sprintf(__('Transaction ref id: %s', 'irandargah-woocommerce-ipg'), $verification_result->refId);
            $note .= '<br/>';
            $note .= sprintf(__('Payer card number: %s', 'irandargah-woocommerce-ipg'), $verification_result->cardNumber);
            $order->add_order_note($note);

            update_post_meta($order_id, 'irandargah_transaction_message', $verification_result->message);
            update_post_meta($order_id, 'irandargah_transaction_order_id', $verification_result->orderId);
            update_post_meta($order_id, 'irandargah_transaction_refid', $verification_result->refId);
            update_post_meta($order_id, 'irandargah_transaction_card_no', $verification_result->cardNumber);

            if ($this->get_option('over100million') == 'yes' && sanitize_text_field($data['amount']) == 999900000 && $price > sanitize_text_field($data['amount'])) {
                $order->set_transaction_id($verification_result->refId);
                $order->update_status('on-hold', __('Awaiting remaining payment | ', 'irandargah-woocommerce-ipg'));
            }
            else {
                $order->payment_complete($verification_result->refId);
            }
            $woocommerce->cart->empty_cart();

            do_action('WC_IranDargah_Return_from_Gateway_ReSuccess', $order_id, $verification_result->refId);

            $debug_email = $this->get_option('debug_email', get_option('admin_email'));
            $vendor_name = get_bloginfo('name', 'display');
            $vendor_url  = home_url('/');
            if ($this->send_debug_email) {
                $subject = 'ایران درگاه - تراکنش موفق';
                $body    =
                    "سلام,\n\n"
                    . "تراکنشی از طریق ایران درگاه در فروشگاه شما انجام شد\n"
                    . "------------------------------------------------------------\n"
                    . 'سایت: ' . esc_html($vendor_name) . ' (' . esc_url($vendor_url) . ")\n"
                    . 'شماره سفارش: ' . esc_html($verification_result->orderId) . "\n"
                    . 'شماره پیگیری: ' . esc_html($verification_result->refId) . "\n"
                    . 'وضعیت پرداخت: ' . esc_html($verification_result->message) . "\n"
                    . 'کد وضعیت سفارش: ' . self::get_order_prop($order, 'status');
                wp_mail($debug_email, $subject, $body);
            }

            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
        }
        else {
            $order->add_order_note($this->failed_message . '<br />' . $verification_result->message);
            wp_redirect($woocommerce->cart->get_checkout_url());
            exit;
        }
    }

    /**
     * @param $data
     * @param $order
     */
    public function handle_payment_failed($data, $order)
    {
        global $woocommerce;
        $this->log('- Failed');
        $order->add_order_note($this->failed_message . '<br />' . $data['message']);
        // $order->update_status('pending');

        $debug_email = $this->get_option('debug_email', get_option('admin_email'));
        $vendor_name = get_bloginfo('name', 'display');
        $vendor_url  = home_url('/');
        if ($this->send_debug_email) {
            $subject = 'ایران درگاه - تراکنش ناموفق';
            $body    =
                "سلام,\n\n" .
                "یک تراکنش ناموفق در سایت شما انجام شده است\n" .
                "------------------------------------------------------------\n" .
                'سایت: ' . esc_html($vendor_name) . ' (' . esc_url($vendor_url) . ")\n" .
                'شماره سفارش: ' . self::get_order_prop($order, 'id') . "\n" .
                'شناسه کاربر: ' . self::get_order_prop($order, 'user_id') . "\n" .
                'وضعیت پرداخت: ' . esc_html($data['message']);
            wp_mail($debug_email, $subject, $body);
        }

        wp_redirect($woocommerce->cart->get_checkout_url());
        exit;
    }

    /**
     * Send Request to IranDargah Gateway
     *
     * @since 2.0.0
     *
     * @param string $method
     * @param mixed  $data
     * @return mixed
     */
    private function send_request_to_irandargah_gateway($option, $method, $data)
    {
        global $woocommerce;

        $this->data_to_send = strpos($option, 'GET') ? array_merge($this->data_to_send, ['action' => 'GET']) : $this->data_to_send;

        $response = strpos($option, 'REST') !== false || $option == 'SANDBOX' ? $this->send_curl_request($method == 'payment' ? $this->url : $this->validate_url, $this->data_to_send) : $this->send_soap_request($method == 'payment' ? 'IRDPayment' : 'IRDVerification', $this->data_to_send);

        $order = new WC_Order($this->data_to_send['orderId']);
        if (is_null($response)) {
            if ($method == 'payment') {
                $note = __('Error in sending request for connecting to gateway', 'irandargah-woocommerce-ipg');
                $order->add_order_note($note);
                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }
            else {
                // $order->update_status('failed');
                $note = __('Error in sending request for transaction\'s verification', 'irandargah-woocommerce-ipg');
                $order->add_order_note($note);
                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }
        }

        return $response;
    }

    /**
     * Send curl request
     *
     * @param string $url
     * @param mixed $data
     * @return mixed
     */
    private function send_curl_request($url, $data)
    {
        for ($i = 0; $i < 10; $i++) {
            sleep(1);
            try {

                $args = [
                    'body'      => json_encode($data),
                    'timeout'   => '20',
                    'sslverify' => false,
                    'headers'   => ['Content-Type' => 'application/json'],
                ];

                $response = wp_remote_post($url, $args);
                if (is_wp_error($response) || !isset($response['body'])) {
                    $this->log('Error in sending request');
                    continue;
                }
                else {
                    $body     = wp_remote_retrieve_body($response);
                    $response = json_decode($body);
                    if ($response) {
                        break;
                    }
                }
            }
            catch (Exception $ex) {
                $this->log($ex);
                return false;
            }
        }
        return $response;
    }

    // /**
    //  * Send SOAP request
    //  *
    //  * @param string $method
    //  * @param mixed $data
    //  * @return mixed
    //  */
    // private function send_soap_request($method, $data)
    // {
    //     $client = new SoapClient($this->wsdl_url, ['cache_wsdl' => WSDL_CACHE_NONE]);

    //     for ($i = 0; $i < 10; $i++) {
    //         sleep(1);
    //         try {
    //             $response = $client->__soapCall($method, [$data]);
    //             if ($response) {
    //                 break;
    //             }
    //         }
    //         catch (\SoapFault $fault) {
    //             $this->log($fault->getMessage());
    //             continue;
    //         }
    //     }

    //     return $response;
    // }

    /**
     * Log system processes.
     * @since 2.0.0
     */
    public function log($message)
    {
        if ('yes' === $this->get_option('testmode') || $this->enable_logging) {
            if (empty($this->logger)) {
                $this->logger = new WC_Logger();
            }
            $this->logger->add('irandargah', $message);
        }
    }

    /**
     * Get order property with compatibility check on order getter introduced
     * in WC 3.0.
     *
     * @since 2.0.1
     *
     * @param WC_Order $order Order object.
     * @param string   $prop  Property name.
     *
     * @return mixed Property value
     */
    public static function get_order_prop($order, $prop)
    {
        switch ($prop) {
            case 'order_total':
                $getter = array($order, 'get_total');
                break;
            default:
                $getter = array($order, 'get_' . $prop);
                break;
        }

        return is_callable($getter) ? call_user_func($getter) : $order->{$prop};
    }

    /**
     * Gets user-friendly error message strings from keys
     *
     * @param   string  $key  The key representing an error
     *
     * @return  string        The user-friendly error message for display
     */
    public function get_error_message($key)
    {
        switch ($key) {
            case 'wc-gateway-irandargah-error-invalid-currency':
                return esc_html__('Your store uses a currency that Irandargah doesnt support yet.', 'irandargah-woocommerce-ipg');
            case 'wc-gateway-irandargah-error-missing-merchant-id':
                return esc_html__('You forgot to fill your merchant ID.', 'irandargah-woocommerce-ipg');
            default:
                return '';
        }
    }

    /**
     *  Show possible admin notices
     *
     */
    public function admin_notices()
    {
        // Get requirement errors.
        $errors_to_show = $this->check_requirements();

        // If everything is in place, don't display it.
        if (!count($errors_to_show)) {
            return;
        }

        // If the gateway isn't enabled, don't show it.
        if ("no" === $this->enabled) {
            return;
        }

        // Use transients to display the admin notice once after saving values.
        if (!get_transient('wc-gateway-irandargah-admin-notice-transient')) {
            set_transient('wc-gateway-irandargah-admin-notice-transient', 1, 1);

            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('To use Irandargah as a payment provider, you need to fix the problems below:', 'irandargah-woocommerce-ipg') . '</p>'
                . '<ul style="list-style-type: disc; list-style-position: inside; padding-right: 2em;">'
                . wp_kses_post(
                    array_reduce(
                        $errors_to_show,
                        function ($errors_list, $error_item) {
                            $errors_list = $errors_list . PHP_EOL . ('<li>' . $this->get_error_message($error_item) . '</li>');
                            return $errors_list;
                        },
                        ''
                    )
                )
                . '</ul></p></div>';
        }
    }

    public function order_received_text($text, $order)
    {
        if ($order && $this->id === $order->get_payment_method()) {
            return sprintf(__('<div style="color: green;">Thank you for your payment. Your transaction has been completed.<br> Your transaction id is %s</div>', 'irandargah-woocommerce-ipg'), $order->get_transaction_id());
        }

        return $text;
    }
}