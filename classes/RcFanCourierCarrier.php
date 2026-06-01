<?php

/**
 * 2018-2026 GURU CODERS SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    GURU CODERS SRL <office@gurucoders.ro>
 * @copyright 2018-2026 GURU CODERS SRL
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Carrier configuration — service map, COD, free-from thresholds.
 */
class RcFanCourierCarrier
{
    /**
     * @param int $id_reference
     *
     * @return string|false
     */
    public static function getCarrierService($id_reference)
    {
        return Configuration::get('RC_FANCOURIER_SERVICE_' . $id_reference);
    }

    /**
     * @param int $id_carrier
     *
     * @return string|false
     */
    public static function getCarrierServiceByIdCarrier($id_carrier)
    {
        $id_reference = Db::getInstance()->getValue('SELECT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier`
			WHERE id_carrier = ' . (int) $id_carrier);

        return Configuration::get('RC_FANCOURIER_SERVICE_' . $id_reference);
    }

    /**
     * @param int $id_reference
     * @param string $service
     *
     * @return bool
     */
    public static function setCarrierService($id_reference, $service)
    {
        return Configuration::updateValue('RC_FANCOURIER_SERVICE_' . $id_reference, $service);
    }

    /**
     * @param int $id_reference
     *
     * @return string|false
     */
    public static function isCarrierCOD($id_reference)
    {
        return Configuration::get('RC_FANCOURIER_SERVICE_COD_' . $id_reference);
    }

    /**
     * @param int $id_reference
     * @param int $value
     *
     * @return bool
     */
    public static function setCarrierCOD($id_reference, $value)
    {
        return Configuration::updateValue('RC_FANCOURIER_SERVICE_COD_' . $id_reference, (int) $value);
    }

    /**
     * Returns per-carrier free shipping threshold (Configuration, same pattern as COD).
     *
     * @param int $id_reference Carrier reference ID
     *
     * @return string|null Amount or null if not set (use group/default)
     */
    public static function getCarrierFreeFrom($id_reference)
    {
        $value = Configuration::get('RC_FANCOURIER_FREE_FROM_' . (int) $id_reference);
        if ($value === false || $value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    /**
     * Saves per-carrier free shipping threshold (Configuration, same pattern as setCarrierCOD).
     *
     * @param int $id_reference Carrier reference ID
     * @param string|null $value Amount or null/empty to use group/default
     *
     * @return bool
     */
    public static function setCarrierFreeFrom($id_reference, $value)
    {
        $value = ($value !== null && trim((string) $value) !== '') ? trim((string) $value) : '';

        return Configuration::updateValue('RC_FANCOURIER_FREE_FROM_' . (int) $id_reference, $value);
    }

    /**
     * @param array $products
     *
     * @return array
     */
    public static function getEnvelopesAndBoxes($products)
    {
        $envelopes = 0;
        $boxes = 0;

        $pieces_in_products = 0;
        foreach ($products as $product) {
            if (isset($product['cart_quantity'])) {
                $pieces_in_products += (float) $product['cart_quantity'];
            } else {
                $pieces_in_products += (float) $product['product_quantity'];
            }
        }

        if (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'one_envelope') {
            $envelopes = 1;
            $boxes = 0;
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'one_box') {
            $envelopes = 0;
            $boxes = 1;
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'envelopes_per_product') {
            $envelopes = count($products);
            $boxes = 0;
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'boxes_per_product') {
            $envelopes = 0;
            $boxes = count($products);
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'envelopes_per_qty') {
            $envelopes = $pieces_in_products;
            $boxes = 0;
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'boxes_per_qty') {
            $envelopes = 0;
            $boxes = $pieces_in_products;
        }

        return [
            'envelopes' => $envelopes,
            'boxes' => $boxes,
        ];
    }

    /**
     * @return array
     */
    public static function getPaymentMethodsInfo()
    {
        $payment_methods = PaymentModule::getInstalledPaymentModules();
        $return = [];

        foreach ($payment_methods as $payment_method) {
            $id_module = (int) $payment_method['id_module'];
            $return[$payment_method['id_module']] = [
                'id_module' => (int) $payment_method['id_module'],
                'name' => $payment_method['name'],
                'service' => Configuration::get('RC_FANCOURIER_PM_SRV_' . $id_module),
                'set_cod' => Configuration::get('RC_FANCOURIER_PM_COD_' . $id_module),
            ];
        }

        return $return;
    }
}
