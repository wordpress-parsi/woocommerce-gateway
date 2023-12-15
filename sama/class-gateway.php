<?php

class WC_GSama extends WC_Payment_Gateway
{
    protected string $api_key;

    public string $before_payment_description;

    protected string $success_message;

    protected string $failed_message;

    protected string $version;

    public function __construct()
    {
        // Gateway Info
        $this->id = 'WC_GSama';
        $this->method_title = 'پرداخت امن سما + ضمانت خرید';
        $this->method_description = 'ضمانت 15 روزۀ بازگشت 100 درصد وجه';
        $this->has_fields = false;
        $this->icon = apply_filters(
            'WC_GSama_logo',
            plugins_url('/assets/images/logo.png', __FILE__)
        );

        $this->version = '1.1.0';

        // Get setting values.
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->before_payment_description = $this->get_option(
            'before_payment_description'
        );
        $this->success_message = $this->get_option('success_message');
        $this->failed_message = $this->get_option('failed_message');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action(
                'woocommerce_update_options_payment_gateways_'.$this->id,
                [$this, 'process_admin_options']
            );
        } else {
            add_action('woocommerce_update_options_payment_gateways', [
                $this,
                'process_admin_options',
            ]);
        }

        add_action('woocommerce_receipt_'.$this->id, [
            $this,
            'checkout_receipt_page',
        ]);
        add_action('woocommerce_api_'.strtolower(get_class($this)), [
            $this,
            'sama_checkout_return_handler',
        ]);

        // Hook to run when payment gateway settings are saved
        $gateway_id = $this->id;
        add_action('woocommerce_update_options_payment_gateways_'.$gateway_id, [
            $this, 'payment_gateway_save_settings',
        ]);
    }

    public function validate_api_key_field(string $key, string $api_key)
    {
        $api_key = trim($api_key);

        // Validate the api key using sama health check web service
        $result = $this->api_key_is_valid($api_key);

        if (true === $result) {
            // Api key is valid, just return it
            return $api_key;
        } else {
            // Api key is not valid, display an error message
            $this->errors[] = 'توکن وارد شده معتبر نیست، لطفا از صحت توکن اطمینان حاصل کنید و یا با پشتیبانی سما تماس بگیرید';

            return '';
        }
    }

    public function payment_gateway_save_settings()
    {
        $this->display_errors();
    }

    public function api_key_is_valid($api_key)
    {
        $healthcheck_url = 'https://app.sama.ir/api/stores/services/deposits/health/';
        $response = wp_remote_get(
            $healthcheck_url,
            [
                'headers' => [
                    'Authorization' => 'Api-Key '.$api_key,
                    'Content-Type' => 'application/json',
                    'X-API-Client-Version' => 'woocommerce/'.$this->version,
                ],
            ]
        );
        if (is_array($response) && !is_wp_error($response)) {
            $body = $response['body'];

            return json_decode($body, true)['is_valid'];
        }

        // If the request fails, consider the value not valid
        return false;
    }

    public function init_form_fields()
    {
        $this->form_fields = apply_filters('WC_GSama_Config', [
            'enabled' => [
                'title' => 'فعال / غیرفعال',
                'type' => 'checkbox',
                'label' => 'درگاه پرداخت را فعال یا غیرفعال کنید.',
                'default' => 'yes',
            ],
            'title' => [
                'title' => 'عنوان درگاه',
                'type' => 'text',
                'description' => 'عنوان درگاه را وارد کنید.',
                'default' => 'پرداخت امن سما + ضمانت خرید',
            ],
            'description' => [
                'title' => 'توضیحات درگاه',
                'type' => 'textarea',
                'description' => 'توضیحات درگاه را وارد کنید.',
                'default' => 'ضمانت 15 روزۀ بازگشت 100 درصد وجه',
            ],
            'api_key' => [
                'title' => 'کلید وب سرویس',
                'type' => 'text',
                'description' => 'کلید وب سرویس را وارد کنید.',
            ],
            'before_payment_description' => [
                'title' => 'توضیحات قبل از پرداخت',
                'type' => 'textarea',
                'description' => 'توضیحات قبل از پرداخت را وارد کنید.',
                'default' => 'با درگاه پرداخت امن سما می توانید از ضمانت 15 روزه خرید با امکان تعویض کالا یا بازپرداخت سریع وجه در صورت وجود مشکل برخوردار شوید.',
            ],

            'success_message' => [
                'title' => 'پیام پرداخت موفق',
                'type' => 'textarea',
                'description' => 'پیام پرداخت موفق را وارد کنید. شماره سفارش: {order_id} کد پیگیری: {track_id}',
                'default' => 'پرداخت موفق بود. کد پیگیری: {track_id}',
            ],
            'failed_message' => [
                'title' => 'پیام پرداخت ناموفق',
                'type' => 'textarea',
                'description' => 'پیام پرداخت موفق را وارد کنید. شماره سفارش: {order_id} خطا: {errro}',
                'default' => 'پرداخت نا موفق بود. {error}',
            ],
        ]);
    }

    public function process_payment($order_id): array
    {
        $order = new WC_Order($order_id);

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    public function checkout_receipt_page($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        $currency = apply_filters(
            'WC_GSama_Currency',
            $order->get_currency(),
            $order_id
        );

        $amount = self::getAmountOrder(intval($order->get_total()), $currency);

        if (empty($amount)) {
            $notice = 'واحد پولی پشتیبانی نمی شود';
            wc_add_notice($notice, 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $client_id = sha1(
            $order->get_customer_id().
                '_'.
                $order_id.
                '_'.
                $amount.
                '_'.
                time()
        );

        $response = wp_remote_post(
            'https://app.sama.ir/api/stores/services/deposits/guaranteed/',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Api-Key '.$this->api_key,
                    'X-API-Client-Version' => 'woocommerce/'.$this->version,
                ],
                'body' => json_encode([
                    'price' => $amount,
                    'client_id' => $client_id,
                    'buyer_phone' => empty($order->get_shipping_phone())
                        ? $order->get_billing_phone()
                        : $order->get_shipping_phone(),
                    'callback_url' => add_query_arg(
                        'wc_order',
                        $order_id,
                        WC()->api_request_url('wc_gsama')
                    ),
                ]),
                'data_format' => 'body',
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            $error = 'خطایی در اتصال با درگاه پرداخت رخ داد.';
            wc_add_notice($error, 'error');
            $order->add_order_note($error);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (201 != wp_remote_retrieve_response_code($response)) {
            if ('validation_error' == $data->code) {
                $error = '';
                foreach ($data->extra as $item) {
                    $error .= $item->error.' ';
                    wc_add_notice($item->error, 'error');
                }
                $order->add_order_note($error);
            } else {
                $error = $data->detail;
                wc_add_notice($error, 'error');
                $order->add_order_note($error);
            }

            wp_redirect(wc_get_checkout_url());
            exit;
        }

        update_post_meta($order_id, 'gsama_transaction_id', $data->uid);
        update_post_meta($order_id, 'gsama_transaction_price', $data->price);
        update_post_meta($order_id, 'gsama_transaction_fee', $data->fee);
        update_post_meta(
            $order_id,
            'gsama_transaction_total_price',
            $data->total_price
        );
        update_post_meta($order_id, 'gsama_transaction_status', 201);
        update_post_meta($order_id, 'gsama_transaction_client_id', $client_id);

        $note = 'کاربر به درگاه پرداخت ارجاع شد. شناسه تراکنش: '.$data->uid;
        $order->add_order_note($note);
        wp_redirect($data->web_view_link);

        exit;
    }

    public function sama_checkout_return_handler()
    {
        global $woocommerce;

        $order_id = intval($_GET['wc_order']);
        $order = wc_get_order($order_id);

        if (empty($order)) {
            $notice = 'سفارش وجود ندارد.';
            wc_add_notice($notice, 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if (
            'completed' == $order->get_status()
            || 'processing' == $order->get_status()
        ) {
            $this->display_success_message($order_id);
            wp_redirect(
                add_query_arg(
                    'wc_status',
                    'success',
                    $this->get_return_url($order)
                )
            );
            exit;
        }

        $saved_transaction_id = get_post_meta(
            $order_id,
            'gsama_transaction_id',
            true
        );
        $saved_transaction_status = get_post_meta(
            $order_id,
            'gsama_transaction_status',
            true
        );
        $saved_payment_price = get_post_meta(
            $order_id,
            'gsama_transaction_price',
            true
        );
        $saved_payment_fee = get_post_meta(
            $order_id,
            'gsama_transaction_fee',
            true
        );
        $saved_payment_total_price = get_post_meta(
            $order_id,
            'gsama_transaction_total_price',
            true
        );
        $saved_client_id = get_post_meta(
            $order_id,
            'gsama_transaction_client_id',
            true
        );

        $request_id = sanitize_text_field($_GET['request_id'] ?? '');
        $process_id = sanitize_text_field($_GET['process_id'] ?? '');
        $reference_number = sanitize_text_field(
            $_GET['reference_number'] ?? ''
        );

        if (empty($saved_client_id)) {
            $notice = 'درخواست نامعتبر است.';
            wc_add_notice($notice, 'error');
            $order->add_order_note($notice, 1);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if (
            empty($request_id)
            or empty($process_id)
            or empty($reference_number)
        ) {
            $notice = 'پرداخت ناموفق بود.';
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, '', $notice);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $response = wp_remote_post(
            'https://app.sama.ir/api/stores/services/deposits/guaranteed/payment/verify/',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Api-Key '.$this->api_key,
                    'X-API-Client-Version' => 'woocommerce/'.$this->version,
                ],
                'body' => json_encode([
                    'request_id' => $request_id,
                    'client_id' => $saved_client_id,
                ]),
                'data_format' => 'body',
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            for ($i = 0; $i <= 5; ++$i) {
                $response = wp_remote_post(
                    'https://app.sama.ir/api/stores/services/deposits/guaranteed/payment/verify/',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Api-Key '.$this->api_key,
                            'X-API-Client-Version' => 'woocommerce/'.$this->version,
                        ],
                        'body' => json_encode([
                            'request_id' => $request_id,
                            'client_id' => $saved_client_id,
                        ]),
                        'data_format' => 'body',
                        'timeout' => 15,
                    ]
                );
                if (!is_wp_error($response)) {
                    break;
                }
            }
        }

        if (is_wp_error($response)) {
            $notice = 'خطایی در اتصال با درگاه پرداخت رخ داد.';
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, '', $notice);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (200 != wp_remote_retrieve_response_code($response)) {
            $error = $data->detail;
            $notice = wpautop(wptexturize($this->failed_message));
            $notice = str_replace('{error}', $error, $notice);
            $notice = str_replace('{order_id}', $order_id, $notice);

            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, $error, '');
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($saved_payment_price != $data->price) {
            $notice = 'مبلغ تراکنش با مبلغ سفارش مطابقت ندارد.';
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, '', $notice);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if ($saved_transaction_id != $data->uid) {
            $notice = 'تراکنش تایید نشد.';
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, '', $notice);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        if (!$data->is_paid or $data->payment->is_failed) {
            $notice = 'تراکنش تایید نشد.';
            $order->add_order_note($notice, 1);
            $this->display_failed_message($order_id, '', $notice);
            $order->update_status('failed');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        update_post_meta($order_id, 'gsama_transaction_status', 200);
        update_post_meta(
            $order_id,
            'gsama_reference_number',
            $reference_number
        );
        update_post_meta($order_id, 'gsama_payment_id', $data->payment->id);

        $new_status = $this->checkDownloadableItem($order)
            ? 'completed'
            : 'processing';

        $notice = wpautop(wptexturize($this->success_message));
        $notice = str_replace('{track_id}', $reference_number, $notice);
        $notice = str_replace('{order_id}', $order_id, $notice);

        $this->display_success_message($order_id);
        $order->add_order_note($notice, 1);
        $order->payment_complete($reference_number);
        $order->update_status($new_status);
        $woocommerce->cart->empty_cart();
        wp_redirect(
            add_query_arg('wc_status', 'success', $this->get_return_url($order))
        );
        exit;
    }

    private function display_success_message($order_id, $default_notice = '')
    {
        $track_id = get_post_meta($order_id, 'gsama_reference_number', true);
        $notice = wpautop(wptexturize($this->success_message));
        if (empty($notice)) {
            $notice = $default_notice;
        }
        $notice = str_replace('{track_id}', $track_id, $notice);
        $notice = str_replace('{order_id}', $order_id, $notice);
        wc_add_notice($notice, 'success');
    }

    private function display_failed_message(
        $order_id,
        $error = '',
        $default_notice = ''
    ) {
        $notice = wpautop(wptexturize($this->failed_message));
        if (empty($notice)) {
            $notice = $default_notice;
        }
        $notice = str_replace('{error}', $error, $notice);
        $notice = str_replace('{order_id}', $order_id, $notice);
        wc_add_notice($notice, 'error');
    }

    public function checkDownloadableItem($order): bool
    {
        foreach ($order->get_items() as $item) {
            if ($item->is_type('line_item')) {
                $product = $item->get_product();
                if (
                    $product
                    && ($product->is_downloadable() || $product->has_file())
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getAmountOrder($amount, $currency)
    {
        switch ($currency) {
            case 'IRR':
                return $amount;
            case 'IRT':
                return $amount * 10;
            case 'IRHR':
                return $amount * 1000;
            case 'IRHT':
                return $amount * 10000;
            default:
                return 0;
        }
    }
}
