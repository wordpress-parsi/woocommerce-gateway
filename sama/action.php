<?php

add_action('plugins_loaded', function () {
    if (class_exists('WC_Payment_Gateway')) {
        // Registers class WC_Guaranteed_Sama as a payment method.
        add_filter('woocommerce_payment_gateways', function ($methods) {
            $methods[] = 'WC_GSama';

            return $methods;
        });

        // Allows the gateway to define some currencies.
        add_filter('woocommerce_currencies', function ($currencies) {
            $currencies['IRR'] = 'ریال';
            $currencies['IRT'] = 'تومان';
            $currencies['IRHR'] = 'هزار ریال';
            $currencies['IRHT'] = 'هزار تومان';

            return $currencies;
        });

        // Allows the gateway to define some currency symbols for the defined currency coeds.
        add_filter('woocommerce_currency_symbol', function ($currency_symbol, $currency) {
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
        }, 10, 2);

        // Required Gateway Class
        require_once WOO_GSAMA_GATEWAY_DIR.'class-gateway.php';
    }
}, 0);
