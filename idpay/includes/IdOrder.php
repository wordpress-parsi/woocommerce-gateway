<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class IdOrder
{

    public static function enabledHPOS($orderId)
    {
        return OrderUtil::custom_orders_table_usage_is_enabled() && self::isIdOrder($orderId);
    }

    public static function getOrder($orderId)
    {
        if (self::enabledHPOS($orderId)) {
            return wc_get_order($orderId);
        }

        return get_post($orderId);
    }

    public static function getOrderMetadata($orderId, $key)
    {
        if (self::enabledHPOS($orderId)) {
            $order = self::getOrder($orderId);

            return $order->get_meta($key);
        }

        return get_post_meta($orderId, $key, true);
    }

    public static function addOrderMetadata($orderId, $key, $value)
    {
        if (self::enabledHPOS($orderId)) {
            $order = self::getOrder($orderId);
            $order->add_meta_data($key, $value);

            return $order->save();
        }

        return add_post_meta($orderId, $key, $value);
    }

    public static function deleteOrderMetadata($orderId, $key)
    {
        if (self::enabledHPOS($orderId)) {
            $order = self::getOrder($orderId);
            $order->delete_meta_data($key);

            return $order->save();
        }

        return delete_post_meta($orderId, $key);
    }

    public static function updateOrderMetadata($orderId, $key, $value)
    {
        if (self::enabledHPOS($orderId)) {
            $order = self::getOrder($orderId);
            $order->update_meta_data($key, $value);

            return $order->save();
        }

        return update_post_meta($orderId, $key, $value);
    }

    public static function isIdOrder($orderId)
    {
        return OrderUtil::is_order($orderId, wc_get_order_types());
    }
}
