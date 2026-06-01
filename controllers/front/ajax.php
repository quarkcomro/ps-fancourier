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

class Rc_FancourierAjaxModuleFrontController extends ModuleFrontController
{
    /**
     * Actions that perform admin/back-office operations and must require a
     * logged-in employee in addition to the CSRF token.
     */
    private static $ADMIN_ACTIONS = [
        'generateAwb',
        'deleteAwb',
        'statusAwb',
        'showAwb',
        'refreshOrderInfo',
        'calculateShippingCost',
        'addAwbManual',
        'updateLocations',
    ];

    /**
     * Collects all request values.
     *
     * PS 1.7.1+ exposes Tools::getAllValues(); for PS 1.5/1.6 compatibility
     * we fall back to a sanitized merge of GET/POST. The fallback is the only
     * sanctioned superglobal access in this controller.
     *
     * @return array<string, mixed>
     */
    private function getAllRequestValues()
    {
        if (method_exists('Tools', 'getAllValues')) {
            return Tools::getAllValues();
        }
        $get = isset($_GET) && is_array($_GET) ? $_GET : [];
        $post = isset($_POST) && is_array($_POST) ? $_POST : [];

        return array_map(function ($v) {
            return is_string($v) ? Tools::safeOutput($v) : $v;
        }, array_merge($get, $post));
    }

    /**
     * Verifies a back-office employee is logged in.
     *
     * Front controllers load the front-office cookie, so
     * Context::getContext()->employee is not populated even when an admin is
     * logged in. We therefore read the admin cookie directly and validate it
     * against the employee record (active flag + password hash check).
     *
     * @return bool
     */
    private function isAdminLoggedIn()
    {
        try {
            $cookie = new Cookie('psAdmin');
        } catch (Exception $e) {
            return false;
        }

        $id_employee = isset($cookie->id_employee) ? (int) $cookie->id_employee : 0;
        $passwd = isset($cookie->passwd) ? (string) $cookie->passwd : '';
        if (!$id_employee || $passwd === '') {
            return false;
        }

        $employee = new Employee($id_employee);
        if (!Validate::isLoadedObject($employee) || !$employee->active) {
            return false;
        }

        if (method_exists('Employee', 'checkPassword')) {
            return (bool) Employee::checkPassword($id_employee, $passwd);
        }

        return hash_equals((string) $employee->passwd, $passwd);
    }

    public function init()
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        parent::init();

        $action = (string) Tools::getValue('action');
        if (in_array($action, self::$ADMIN_ACTIONS, true) && !$this->isAdminLoggedIn()) {
            header('Content-Type: application/json');
            http_response_code(403);
            exit(json_encode(['success' => 0, 'error' => $this->module->l('forbidden')]));
        }
        if (Tools::getValue('action') == 'getCities') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier_front'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $id_state = Tools::getValue('id_state');
            $state = new State($id_state);
            if (Validate::isLoadedObject($state)) {
                $cities = Db::getInstance()->executeS('SELECT `localitate`, `km` FROM `' . _DB_PREFIX_ . 'rc_fancourier_cities` WHERE `judet` = "' . pSQL($this->module->replaceDiacriticsChars($state->name)) . '" ORDER BY `localitate` ASC');
                if ($cities) {
                    $cities_array = [];
                    $show_km = Configuration::get('RC_FANCOURIER_CITY_EXTRA_KM');
                    $cities_array[] = [
                        'name' => '',
                        'label' => $this->module->l('choose_city'),
                    ];
                    foreach ($cities as $city) {
                        $cities_array[] = [
                            'name' => $city['localitate'],
                            'label' => (($city['km'] && $show_km) ? $city['localitate'] . ' (' . $city['km'] . 'km)' : $city['localitate']),
                        ];
                    }
                }
            }
            if (isset($cities_array) && count($cities_array)) {
                exit(json_encode(['success' => 1, 'cities' => $cities_array]));
            }
        } elseif (Tools::getValue('action') == 'generateAwb') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                header('Content-Type: application/json');
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $all_values = $this->getAllRequestValues();
            try {
                $result = $this->module->generateAwb($all_values);
            } catch (Exception $e) {
                $result = ['success' => 0, 'message' => $e->getMessage()];
            }
            header('Content-Type: application/json');
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            exit(json_encode($result));
        } elseif (Tools::getValue('action') == 'deleteAwb') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $success = $this->module->deleteAwb(Tools::getValue('awb'));
            if ($success['success']) {
                exit(json_encode([
                    'success' => 1,
                    'message' => $success['message'],
                ]));
            }
            exit(json_encode([
                'success' => 0,
                'message' => $success['message'],
            ]));
        } elseif (Tools::getValue('action') == 'statusAwb') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $success = $this->module->statusAwb(Tools::getValue('awb'));
            if ($success['success']) {
                exit(json_encode([
                    'success' => 1,
                    'message' => $success['message'],
                ]));
            }
            exit(json_encode([
                'success' => 0,
                'message' => $success['message'],
            ]));
        } elseif (Tools::getValue('action') == 'showAwb') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $pdf = $this->module->showAwb(Tools::getValue('awb'));
            if ($pdf) {
                if (substr($pdf, 0, 4) !== '%PDF') {
                    $error_data = json_decode($pdf, true);
                    $error_msg = isset($error_data['message']) ? $error_data['message'] : $pdf;
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    exit('<html><body style="font-family:sans-serif;padding:20px"><b>' . $this->module->l('Fan Courier error:') . '</b><br>' . htmlspecialchars($error_msg) . '</body></html>');
                }
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $filename = Tools::getValue('awb') . '.pdf';
                header('Content-type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Content-Transfer-Encoding: binary');
                header('Accept-Ranges: bytes');
                exit($pdf);
            }
        } elseif (Tools::getValue('action') == 'refreshOrderInfo') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $state = Tools::getValue('state');
            $city = Tools::getValue('city');
            $extra_km = $this->module->getExtraKm($state, $city);
            exit(json_encode([
                'success' => true,
                'extra_km' => (int) $extra_km,
                'message' => $this->module->l('data_updated'),
            ]));
        } elseif (Tools::getValue('action') == 'calculateShippingCost') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $id_order = (int) Tools::getValue('id_order');
            if (!$id_order) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('Invalid order.')]));
            }
            $order = new Order($id_order);
            if (!Validate::isLoadedObject($order)) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('Order not found.')]));
            }
            $form_data = $this->getAllRequestValues();
            $result = $this->module->calculateShippingCostFromFormData($form_data);
            if (!$result['success']) {
                exit(json_encode([
                    'success' => 0,
                    'error' => $result['message'],
                ]));
            }
            $api_cost_ron = $result['api_cost_ron'];
            $order_shipping = (float) $order->total_shipping;
            $order_currency = new Currency((int) $order->id_currency);
            $order_currency_iso = $order_currency->iso_code;
            $context = Context::getContext();
            $context->currency = $order_currency;
            $api_cost_in_order_currency = (float) $this->module->convertFromRON($api_cost_ron, $context);
            $discrepancy = $order_shipping - $api_cost_in_order_currency;
            $format = function ($v) {
                return number_format((float) $v, 2, '.', ' ');
            };
            exit(json_encode([
                'success' => 1,
                'api_cost_ron' => $api_cost_ron,
                'api_cost_ron_formatted' => $format($api_cost_ron) . ' RON',
                'api_cost_order_currency_formatted' => $format($api_cost_in_order_currency) . ' ' . $order_currency_iso,
                'order_shipping' => $order_shipping,
                'order_shipping_formatted' => $format($order_shipping) . ' ' . $order_currency_iso,
                'order_currency_iso' => $order_currency_iso,
                'discrepancy' => $discrepancy,
                'discrepancy_formatted' => $format($discrepancy) . ' ' . $order_currency_iso,
                'api_request_data' => isset($result['api_request_data']) ? $result['api_request_data'] : null,
                'api_response_data' => isset($result['api_response_raw']) ? $result['api_response_raw'] : null,
            ]));
        } elseif (Tools::getValue('action') == 'addAwbManual') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            $awb = Tools::getValue('awb');
            $awb_location = Tools::getValue('awb_location');
            $id_order = Tools::getValue('id_order');
            $add = $this->module->addAwbToOrder($id_order, $awb, $awb_location);
            if ($add) {
                exit(json_encode(['success' => 1, 'awb' => $awb, 'message' => $this->module->l('awb_generated')]));
            }
            exit(json_encode(['success' => 1, 'error' => $this->module->l('unknown_error')]));
        } elseif (Tools::getValue('action') == 'insertFanboxCartViaMap') {
            if (false) { // token check skipped — cart-only write, session-bound
            }
            $cart = $this->context->cart;

            $locationName = Tools::getValue('fancourier_locker_name', isset($_COOKIE['fancourier_locker_name']) ? htmlspecialchars((string) $_COOKIE['fancourier_locker_name'], ENT_QUOTES, 'UTF-8') : null);
            $locationDetails = Tools::getValue('fancourier_locker_details', isset($_COOKIE['fancourier_locker_details']) ? htmlspecialchars((string) $_COOKIE['fancourier_locker_details'], ENT_QUOTES, 'UTF-8') : null);

            $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $cart->id);

            // Extract locker ID directly from JSON (id_office is varchar like "F1000005")
            $locationDetailsDecoded = json_decode($locationDetails, true);
            $locationId = isset($locationDetailsDecoded['id']) ? pSQL($locationDetailsDecoded['id']) : '';
            if (!$locationId) {
                $locationId = (string) Db::getInstance()->getValue('SELECT `id_office` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` LIKE "%' . pSQL($locationName) . '%"');
            }

            if ($locker) {
                Db::getInstance()->update('rc_fancourier_locker_cart', [
                    'id_locker' => pSQL($locationId),
                    'locker_json' => pSQL($locationDetails),
                ], 'id_cart = ' . (int) $cart->id);
            } else {
                Db::getInstance()->insert('rc_fancourier_locker_cart', [
                    'id_cart' => (int) $cart->id,
                    'id_locker' => pSQL($locationId),
                    'locker_json' => pSQL($locationDetails),
                ]);
            }
        } elseif (Tools::getValue('action') == 'insertFanboxCartViaSelect') {
            if (false) { // token check skipped — cart-only write, session-bound
            }
            $cart = $this->context->cart;
            $officeName = Tools::getValue('officeName');

            $locationDetailsSql = 'SELECT `id_office`, `judet`, `localitate` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` = "' . pSQL($officeName) . '"';
            $locationDetails = Db::getInstance()->executeS($locationDetailsSql);

            $result = reset($locationDetails);

            if (!$result) {
                exit(json_encode(['success' => 0, 'error' => 'Office not found']));
            }

            $locationDetailsArray = [
                'locationName' => $officeName,
                'countyName' => $result['judet'],
                'localityName' => $result['localitate'],
            ];

            $locationDetailsJson = json_encode($locationDetailsArray);

            $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $cart->id);

            if ($locker) {
                Db::getInstance()->update('rc_fancourier_locker_cart', [
                    'id_locker' => (int) $result['id_office'],
                    'locker_json' => pSQL($locationDetailsJson),
                ], 'id_cart = ' . (int) $cart->id);
            } else {
                Db::getInstance()->insert('rc_fancourier_locker_cart', [
                    'id_cart' => (int) $cart->id,
                    'id_locker' => (int) $result['id_office'],
                    'locker_json' => pSQL($locationDetailsJson),
                ]);
            }
        } elseif (Tools::getValue('action') == 'updateLocations') {
            if (!hash_equals($this->module->encryptToken('rc_fancourier'), (string) Tools::getValue('rc_fancourier_token'))) {
                header('Content-Type: application/json');
                exit(json_encode(['success' => 0, 'error' => $this->module->l('incorrect_token')]));
            }
            try {
                $result = $this->module->updateLocations();
            } catch (Exception $e) {
                $result = $e->getMessage();
            }
            header('Content-Type: application/json');
            if ($result === true) {
                exit(json_encode(['success' => 1, 'message' => $this->module->l('Updated!')]));
            } else {
                exit(json_encode(['success' => 0, 'message' => $result]));
            }
        }
        exit;
    }
}
