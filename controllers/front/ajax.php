<?php
/**
 * 2018-2021 GURU CODERS SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    GURU CODERS SRL <stickyrst@gmail.com>
 * @copyright 2018-2021 GURU CODERS SRL
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Rc_FancourierAjaxModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        Configuration::set('PS_SHOP_ENABLE', 1);
        parent::init();
        if (Tools::getValue('action') == 'getCities') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier_front')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
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
                        'label' => $this->module->getTranslation('choose_city'),
                    ];
                    foreach ($cities as $city) {
                        $cities_array[] = [
                            'name' => $city['localitate'],
                            'label' => (($city['km'] && $show_km) ? $city['localitate'] . ' (' . $city['km'] . 'km)' : $city['localitate']),
                        ];
                    }
                }
            }
            if (isset($cities_array) && sizeof($cities_array)) {
                exit(json_encode(['success' => 1, 'cities' => $cities_array]));
            }
        } elseif (Tools::getValue('action') == 'generateAwb') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            if (method_exists('Tools', 'getAllValues')) {
                $all_values = Tools::getAllValues();
            } else {
                $all_values = $_POST + $_GET;
            }
            exit(json_encode($this->module->generateAwb($all_values)));
        } elseif (Tools::getValue('action') == 'deleteAwb') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            $success = $this->module->deleteAwb(Tools::getValue('awb'));
            if ($success['success']) {
                exit(json_encode([
                    'success' => 1,
                    'message' => $success['message'],
                ]));
            } else {
                exit(json_encode([
                    'success' => 0,
                    'message' => $success['message'],
                ]));
            }
        } elseif (Tools::getValue('action') == 'statusAwb') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            $success = $this->module->statusAwb(Tools::getValue('awb'));
            if ($success['success']) {
                exit(json_encode([
                    'success' => 1,
                    'message' => $success['message'],
                ]));
            } else {
                exit(json_encode([
                    'success' => 0,
                    'message' => $success['message'],
                ]));
            }
        } elseif (Tools::getValue('action') == 'showAwb') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            $pdf = $this->module->showAwb(Tools::getValue('awb'));
            if ($pdf) {
                if (stripos($pdf, 'error') === 0) {
                    exit($pdf);
                } else {
                    $filename = Tools::getValue('awb') . '.pdf';
                    header('Content-type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $filename . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Accept-Ranges: bytes');
                    exit($pdf);
                }
            }
        } elseif (Tools::getValue('action') == 'refreshOrderInfo') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            $state = Tools::getValue('state');
            $city = Tools::getValue('city');
            $extra_km = $this->module->getExtraKm($state, $city);
            exit(json_encode([
                'success' => true,
                'extra_km' => (int) $extra_km,
                'message' => $this->module->getTranslation('data_updated'),
            ]));
        } elseif (Tools::getValue('action') == 'addAwbManual') {
            if (Tools::getValue('rc_fancourier_token') != Tools::encrypt('rc_fancourier')) {
                exit(json_encode(['success' => 0, 'error' => $this->module->getTranslation('incorrect_token')]));
            }
            $awb = Tools::getValue('awb');
            $awb_location = Tools::getValue('awb_location');
            $id_order = Tools::getValue('id_order');
            $add = $this->module->addAwbToOrder($id_order, $awb, $awb_location);
            if ($add) {
                exit(json_encode(['success' => 1, 'awb' => $awb, 'message' => $this->module->getTranslation('awb_generated')]));
            } else {
                exit(json_encode(['success' => 1, 'error' => $this->module->getTranslation('unknown_error')]));
            }
        } elseif (Tools::getValue('action') == 'insertFanboxCartViaMap') {
            $cart = $this->context->cart;

            $locationName = isset($_COOKIE['fancourier_locker_name']) ? $_COOKIE['fancourier_locker_name'] : null; // Cookie set by javascript
            $locationDetails = isset($_COOKIE['fancourier_locker_details']) ? $_COOKIE['fancourier_locker_details'] : null; // Cookie set by javascript

            $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $cart->id . '');

            $locationId = Db::getInstance()->getValue('SELECT `id_office` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` LIKE "%' . pSQL($locationName) . '%"');

            if ($locker) {
                Db::getInstance()->update('rc_fancourier_locker_cart', [
                    'id_locker' => $locationId,
                    'locker_json' => $locationDetails,
                ], 'id_cart = ' . (int) $cart->id);
            } else {
                Db::getInstance()->insert('rc_fancourier_locker_cart', [
                    'id_cart' => $cart->id,
                    'id_locker' => $locationId,
                    'locker_json' => $locationDetails,
                ]);
            }
        } elseif (Tools::getValue('action') == 'insertFanboxCartViaSelect') {
            $cart = $this->context->cart;
            $officeName = Tools::getValue('officeName');

            $locationDetailsSql = 'SELECT `id_office`, `judet`, `localitate` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` = "' . pSQL($officeName) . '"';
            $locationDetails = Db::getInstance()->executeS($locationDetailsSql);

            $result = reset($locationDetails);

            $locationDetailsArray = [
                'locationName' => $officeName,
                'countyName' => $result['judet'],
                'localityName' => $result['localitate'],
            ];

            $locationDetailsJson = json_encode($locationDetailsArray);

            $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $cart->id . '');

            if ($locker) {
                Db::getInstance()->update('rc_fancourier_locker_cart', [
                    'id_locker' => $result['id_office'],
                    'locker_json' => $locationDetailsJson,
                ], 'id_cart = ' . (int) $cart->id);
            } else {
                Db::getInstance()->insert('rc_fancourier_locker_cart', [
                    'id_cart' => $cart->id,
                    'id_locker' => $result['id_office'],
                    'locker_json' => $locationDetailsJson,
                ]);
            }
        } elseif (Tools::getValue('action') == 'updateLocations') {
            $this->module->updateLocations();
            die("DONE");
        }
        exit;
    }
}
