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
 * AWB CRUD, tracking, state management.
 */
class RcFanCourierAwb
{
    /** @var array */
    public static $awbData = [];

    /**
     * @param int $id_order
     * @param string $awb
     * @param string $client_id
     * @param array|null $weights array of per-package weights (kg) for compound AWBs
     *
     * @return bool
     */
    public static function addAwbToOrder($id_order, $awb, $client_id, $weights = null)
    {
        $row = [
            'id_order' => (int) $id_order,
            'awb' => pSQL($awb),
            'client_id' => pSQL($client_id),
            'date_add' => date('Y-m-d H:i:s'),
        ];
        if (is_array($weights) && !empty($weights)) {
            $row['weights_json'] = pSQL(json_encode(array_values(array_map('floatval', $weights))));
        }
        $return = Db::getInstance()->insert('rc_fancourier_awbs', $row);

        if (Configuration::get('RC_FANCOURIER_UPDATETRACKING')) {
            $order = new Order($id_order);
            $order->shipping_number = $awb;
            $order->update();

            $id_order_carrier = Db::getInstance()->getValue('
                SELECT `id_order_carrier`
                FROM `' . _DB_PREFIX_ . 'order_carrier`
                WHERE `id_order` = ' . (int) $id_order);

            $order_carrier = new OrderCarrier((int) $id_order_carrier);

            if (Validate::isLoadedObject($order_carrier)) {
                $order_carrier->tracking_number = pSQL($awb);
                if ($order_carrier->update() && Configuration::get('RC_FANCOURIER_UPDTRACKING_EMAIL')) {
                    if (!empty($awb)) {
                        if (self::sendInTransitEmail($order, $awb)) {
                            $customer = new Customer((int) $order->id_customer);
                            $carrier = new Carrier((int) $order->id_carrier, $order->id_lang);

                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', [
                                'order' => $order,
                                'customer' => $customer,
                                'carrier' => $carrier,
                            ], null, false, true, false, $order->id_shop);

                            return true;
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param Order $order
     * @param string $awb
     *
     * @return bool
     */
    public static function sendInTransitEmail($order, $awb)
    {
        $customer = new Customer((int) $order->id_customer);
        $carrier = new Carrier((int) $order->id_carrier, $order->id_lang);
        $address = new Address((int) $order->id_address_delivery);

        if (!Validate::isLoadedObject($customer)) {
            throw new PrestaShopException('Can\'t load Customer object');
        }
        if (!Validate::isLoadedObject($carrier)) {
            throw new PrestaShopException('Can\'t load Carrier object');
        }
        if (!Validate::isLoadedObject($address)) {
            throw new PrestaShopException('Can\'t load Address object');
        }

        $products = $order->getCartProducts();
        $link = Context::getContext()->link;

        $metadata = '';
        $image_format = '';
        if (method_exists('ImageType', 'getFormattedName')) {
            $image_format = ImageType::getFormattedName('large');
        } elseif (method_exists('ImageType', 'getFormatedName')) {
            $image_format = ImageType::getFormatedName('large');
        } else {
            $image_format = 'large_default';
        }
        foreach ($products as $product) {
            $prod_obj = new Product((int) $product['product_id']);

            $img = $prod_obj->getCombinationImages($order->id_lang);
            $link_rewrite = $prod_obj->link_rewrite[$order->id_lang];
            $combination_img = $img ? $img[$product['product_attribute_id']][0]['id_image'] : null;
            if ($combination_img != null) {
                $img_url = $link->getImageLink($link_rewrite, $combination_img, $image_format);
            } else {
                $img = $prod_obj->getCover($prod_obj->id);
                $img_url = $link->getImageLink($link_rewrite, $img['id_image']);
            }
            $prod_url = $prod_obj->getLink();

            Context::getContext()->smarty->assign([
                'product_name' => htmlspecialchars($product['product_name']),
                'img_url' => $img_url,
                'prod_url' => $prod_url,
            ]);

            $metadata .= Context::getContext()->smarty->fetch(_PS_MODULE_DIR_ . 'rc_fancourier/views/templates/hook/in_transit_metadata.tpl');
        }

        $templateVars = [
            '{followup}' => str_replace('@', $awb, $carrier->url),
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{id_order}' => $order->id,
            '{shipping_number}' => $awb,
            '{order_name}' => $order->getUniqReference(),
            '{carrier}' => $carrier->name,
            '{address1}' => $address->address1,
            '{country}' => $address->country,
            '{postcode}' => $address->postcode,
            '{city}' => $address->city,
            '{meta_products}' => $metadata,
        ];

        if (@Mail::Send(
            (int) $order->id_lang,
            'in_transit',
            Mail::l('Package in transit', (int) $order->id_lang),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            true,
            (int) $order->id_shop
        )) {
            return true;
        }

        return false;
    }

    /**
     * @param int $id_order
     *
     * @return array|false
     */
    public static function getAwbForOrders($id_order)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `id_order` = "' . (int) $id_order . '"');
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public static function parseData($data)
    {
        self::$awbData = $data;
        $options = '';
        if (is_array(self::getData('rc_fancourier_options')) && in_array('D', self::getData('rc_fancourier_options'))) {
            $office_id = self::getData('rc_fancourier_office_address');
            $office_address = Db::getInstance()->getValue('SELECT strada FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `id_office` = "' . pSQL($office_id) . '"');
            self::$awbData['rc_fancourier_address'] = $office_address;
            self::$awbData['rc_fancourier_state'] = self::getData('rc_fancourier_office_state');
            self::$awbData['rc_fancourier_city'] = self::getData('rc_fancourier_office_city');
            self::$awbData['rc_fancourier_postcode'] = self::getData('rc_fancourier_office_postcode');
        }
        if (self::getData('rc_fancourier_options')) {
            if (is_array(self::getData('rc_fancourier_options'))) {
                $options = implode('', self::getData('rc_fancourier_options'));
            } else {
                $options = self::getData('rc_fancourier_options');
            }
        }
        $packing = '';
        $personal_data = '';
        if (is_array(self::getData('rc_fancourier_options')) && in_array('A', self::getData('rc_fancourier_options'))) {
            $packing_data = self::getData('rc_fancourier_packing');
            if ($packing_data) {
                $packing_array = [];
                $packing_keys = [
                    'name',
                    'description',
                    'code',
                    'quantity',
                    'decl_value',
                ];
                foreach ($packing_data['name'] as $key => $value) {
                    $packing_line = [];
                    foreach ($packing_keys as $packing_key) {
                        $packing_line[] = str_replace(['/', '|'], '-', $packing_data[$packing_key][$key]);
                    }
                    $packing_array[] = implode('/', $packing_line);
                }
                $packing = implode('|', $packing_array);
            }
            $personal_data = self::getData('rc_fancourier_id_series') . '|' . self::getData('rc_fancourier_id_number');
        }
        $data_array = [
            'Tip serviciu' => self::getData('rc_fancourier_service'),
            'Banca' => '',
            'IBAN' => '',
            'Nr. Plicuri' => (int) self::getData('rc_fancourier_envelopes'),
            'Nr. Colete' => (int) self::getData('rc_fancourier_boxes'),
            'Greutate' => self::getData('rc_fancourier_weight'),
            'Plata expeditie' => self::getData('rc_fancourier_del_tax'),
            'Ramburs(bani)' => RcFanCourierUtils::formatDecimals(self::getData('rc_fancourier_cod_value')),
            'Plata ramburs la' => self::getData('rc_fancourier_cod_tax'),
            'Valoare declarata' => self::getData('rc_fancourier_declared_value'),
            'Persoana contact expeditor' => self::getData('rc_fancourier_contact_person'),
            'Observatii' => self::getData('rc_fancourier_observations'),
            'Continut' => self::getData('rc_fancourier_content'),
            'Nume destinatar' => self::getData('rc_fancourier_customer_name'),
            'Persoana contact' => self::getData('rc_fancourier_customer_contact'),
            'Telefon' => self::getData('rc_fancourier_phone'),
            'Fax' => '',
            'Email' => self::getData('rc_fancourier_customer_email'),
            'Judet' => self::getData('rc_fancourier_state'),
            'Localitatea' => self::getData('rc_fancourier_city'),
            'Strada' => self::getData('rc_fancourier_address'),
            'Nr' => '',
            'Cod postal' => self::getData('rc_fancourier_postcode'),
            'Bloc' => '',
            'Scara' => '',
            'Etaj' => '',
            'Apartament' => '',
            'Inaltime pachet' => self::getData('rc_fancourier_height'),
            'Latime pachet' => self::getData('rc_fancourier_width'),
            'Lungime pachet' => self::getData('rc_fancourier_length'),
            'Restituire' => self::getData('rc_fancourier_restitution'),
            'Centru Cost' => '',
            'Optiuni' => $options,
            'Packing' => $packing,
            'Date personale' => $personal_data,
        ];

        $return = [];
        foreach ($data_array as $key => $value) {
            $return['"' . $key . '"'] = '"' . addslashes($value) . '"';
        }

        return $return;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public static function getData($name)
    {
        if (isset(self::$awbData[$name])) {
            return self::$awbData[$name];
        }

        return '';
    }

    /**
     * @param int|null $days_to_check
     *
     * @return array
     */
    public static function getAwbsToCheck($days_to_check = null)
    {
        if ($days_to_check == null) {
            $days_to_check = (int) Configuration::get('RC_FANCOURIER_OS_DAYS');
        }
        $os_to_exclude = Configuration::get('RC_FANCOURIER_OS_IGNORE');
        if (!$os_to_exclude) {
            $os_to_exclude = '-1';
        }
        $os_to_exclude_safe = implode(',', array_map('intval', explode(',', $os_to_exclude)));

        $awbs = Db::getInstance()->executeS('SELECT awbs.* FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` awbs LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.id_order = awbs.id_order) WHERE awbs.date_add > (NOW() - INTERVAL ' . (int) $days_to_check . ' DAY) AND o.current_state NOT IN (' . $os_to_exclude_safe . ') ORDER BY `id_order` ASC');

        $awbs_array = [];
        foreach ($awbs as $row) {
            $awbs_array[$row['client_id']][] = $row;
        }

        return $awbs_array;
    }

    /**
     * @param array $awbs_to_check
     *
     * @return array|void
     */
    public static function checkAwbsStates($awbs_to_check)
    {
        if (!$awbs_to_check) {
            return;
        }
        $username = Configuration::get('RC_FANCOURIER_USERNAME');
        $password = Configuration::get('RC_FANCOURIER_PASSWORD');

        $data_to_return = [];

        foreach ($awbs_to_check as $client_id => $awbs) {
            $awbs = array_slice($awbs, 0, 100, true);
            $id_orders_by_awb = [];
            foreach ($awbs as $awb) {
                $id_orders_by_awb[$awb['awb']] = $awb['id_order'];
            }

            $post_data = [
                'username' => $username,
                'password' => $password,
                'clientId' => $client_id,
                'awb' => array_keys($id_orders_by_awb),
                'language' => 'ro',
            ];
            $data = RcFanCourierApiClient::curlCall('reports/awb/tracking', $post_data);
            $output_array = [];
            $json_data = json_decode($data, true);
            if (isset($json_data['data'])) {
                $data_array = $json_data['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);
            }
            if ($output_array) {
                foreach ($output_array as $awb_obj) {
                    if (isset($awb_obj['events']) && is_array($awb_obj['events'])) {
                        $awb_event = $awb_obj['events'][count($awb_obj['events']) - 1];
                        $status_livrare = isset($awb_event['name']) ? $awb_event['name'] : '';
                        $id_status_livrare = isset($awb_event['id']) ? $awb_event['id'] : 0;
                        $data_to_return[] = [
                            'awb' => $awb_obj['awbNumber'],
                            'state' => $id_status_livrare,
                            'status' => $status_livrare,
                            'id_order' => $id_orders_by_awb[$awb_obj['awbNumber']],
                        ];
                    } else {
                        $data_to_return[] = [
                            'awb' => $awb_obj['awbNumber'],
                            'state' => 0,
                            'status' => '-',
                            'id_order' => $id_orders_by_awb[$awb_obj['awbNumber']],
                        ];
                    }
                }
            } else {
                RcFanCourierLogger::logError('Unexpected API response in checkAwbsStates', 'awb-tracking', is_array($data) ? $data : ['raw' => substr((string) $data, 0, 500)]);
            }
        }

        return $data_to_return;
    }

    /**
     * @param array $awbs
     *
     * @return array
     */
    public static function changeAwbsState($awbs)
    {
        $order_states = OrderState::getOrderStates(Context::getContext()->language->id);
        $order_states_array = [];
        foreach ($order_states as $os) {
            $order_states_array[$os['id_order_state']] = $os;
        }
        $awbs_grouped_by_order = [];
        foreach ($awbs as $awb_info) {
            if (!isset($awbs_grouped_by_order[$awb_info['id_order']])) {
                $awbs_grouped_by_order[$awb_info['id_order']] = [
                    'awbs' => [],
                    'states' => [],
                ];
            }
            $awbs_grouped_by_order[$awb_info['id_order']]['awbs'][] = $awb_info;
            if (!isset($awbs_grouped_by_order[$awb_info['id_order']]['states'][$awb_info['state']])) {
                $awbs_grouped_by_order[$awb_info['id_order']]['states'][$awb_info['state']] = 1;
            }
        }
        $orders_to_change_status = [];
        foreach ($awbs_grouped_by_order as $id_order => $awbs_info) {
            if (count($awbs_info['states']) == 1) {
                $orders_to_change_status[] = $id_order;
            }
        }
        foreach ($awbs as &$awb) {
            $order_info = Db::getInstance()->getRow('SELECT awbs.`id_order`, o.`reference`, o.`current_state` as `id_current_state`, o.`date_add` as `order_date`, IF((a.`company` IS NULL OR a.`company` = ""), CONCAT(a.`firstname`, " ", a.`lastname`), a.`company`) as `customer_name` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` awbs LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = awbs.`id_order`) LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = o.`id_address_delivery`) WHERE awbs.`awb` = "' . pSQL($awb['awb']) . '"');
            if ($order_info) {
                $awb['id_order'] = $order_info['id_order'];
                $awb['reference'] = $order_info['reference'];
                $awb['id_current_state'] = $order_info['id_current_state'];
                $awb['order_date'] = $order_info['order_date'];
                $awb['customer_name'] = $order_info['customer_name'];
                $awb['current_state'] = $order_states_array[$order_info['id_current_state']]['name'];
                $awb['current_state_color'] = $order_states_array[$order_info['id_current_state']]['color'];
                $awb['current_state_text_color'] = RcFanCourierUtils::getBrightness($awb['current_state_color']) < 128 ? 'white' : 'black';
                $awb['next_state'] = '';
                $awb['next_state_color'] = $awb['current_state_text_color'];

                $id_next_order_state = (int) Configuration::get('RC_FANCOURIER_OS_MAP_' . $awb['state']);
                if ($id_next_order_state && $id_next_order_state != $awb['id_current_state'] && in_array($awb['id_order'], $orders_to_change_status)) {
                    $order = new Order($awb['id_order']);
                    if ($order->current_state == $awb['id_current_state']) {
                        $order_state = new OrderState($id_next_order_state);

                        $history = new OrderHistory();
                        $history->id_order = $order->id;
                        $history->id_employee = 0;

                        $use_existings_payment = false;
                        if (!$order->hasInvoice()) {
                            $use_existings_payment = true;
                        }
                        $history->changeIdOrderState((int) $order_state->id, $order, $use_existings_payment);

                        $carrier = new Carrier($order->id_carrier, $order->id_lang);
                        $templateVars = [];
                        if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                            $templateVars = ['{followup}' => str_replace('@', $order->shipping_number, $carrier->url)];
                        }

                        if ($history->addWithemail(true, $templateVars)) {
                            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                                foreach ($order->getProducts() as $product) {
                                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                                        StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                                    }
                                }
                            }
                        }

                        $awb['next_state'] = $order_states_array[$id_next_order_state]['name'];
                        $awb['next_state_color'] = $order_states_array[$id_next_order_state]['color'];
                    }
                }

                $awb['next_state_text_color'] = RcFanCourierUtils::getBrightness($awb['next_state_color']) < 128 ? 'white' : 'black';
            }
        }

        return $awbs;
    }

    /**
     * @param array $awbs_to_check
     *
     * @return string
     */
    public static function buildXmlForCheckStates($awbs_to_check)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" ?><AWBLIST></AWBLIST>');

        foreach ($awbs_to_check as $i => $awb) {
            $awb_child = $xml->addChild('AWB');
            $awb_child->addChild('ID', (string) ($i + 1));
            $awb_child->addChild('NRAWB', $awb['awb']);
        }

        return $xml->asXML();
    }

    /**
     * @return array
     */
    public static function getFanStatesList()
    {
        $endpoint = 'reports/awb-events';
        $post_data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'language' => 'ro',
        ];
        if ($post_data['username'] && $post_data['password']) {
            $result = RcFanCourierApiClient::curlCall($endpoint, $post_data, 'GET', true);
            $json = json_decode($result, true);
            if ($result && isset($json['data'])) {
                $data_array = $json['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);

                $result_array = [];
                foreach ($output_array as $states) {
                    $result_array[$states['id']] = $states['name'];
                }

                if (!empty($post_data['username']) && !empty($post_data['password'])) {
                    return $result_array;
                }
            }
        }

        return [
            'C0' => 'Expeditie ridicata',
            'C1' => 'Expeditie preluate spre livrare',
            'H10' => 'Expeditie in tranzit spre depozitul de destinatie',
            'H11' => 'Expeditie descarcata in depozitulul de destinatie',
            'H2' => 'Expeditie in tranzit',
            'H3' => 'Expeditie sortata pe banda',
            'H4' => 'Expeditie sortata pe banda',
            'H12' => 'Expeditie in depozit',
            'H13' => 'Expeditie in depozit',
            'H15' => 'Expeditie in depozit',
            'H17' => 'Expeditie in depozitul de destinatie',
            'S1' => 'Expeditie in livrare',
            'S2' => 'Livrat',
            'S3' => 'Avizat',
            'S4' => 'Adresa incompleta',
            'S5' => 'Adresa gresita, destinatar mutat',
            'S6' => 'Refuz primire',
            'S7' => 'Refuz plata transport',
            'S8' => 'Livrare din sediul FAN Courier',
            'S9' => 'Redirectionat',
            'S10' => 'Adresa gresita, fara telefon',
            'S11' => 'Avizat si trimis SMS',
            'S12' => 'Contactat; livrare ulterioara',
            'S14' => 'Restrictii acces la adresa',
            'S15' => 'Refuz predare ramburs',
            'S16' => 'Retur la termen',
            'S19' => 'Adresa incompleta - trimis SMS',
            'S20' => 'Adresa incompleta, fara telefon',
            'S21' => 'Avizat, lipsa persoana de contact',
            'S22' => 'Avizat, nu are bani de rbs',
            'S24' => 'Avizat, nu are imputernicire/CI',
            'S25' => 'Adresa gresita - trimis SMS',
            'S27' => 'Adresa gresita, nr telefon gresit',
            'S28' => 'Adresa incompleta,nr telefon gresit',
            'S30' => 'Nu raspunde la telefon',
            'S33' => 'Retur solicitat',
            'S35' => 'Retrimis in livrare',
            'S37' => 'Despagubit',
            'S38' => 'AWB neexpediat',
            'S42' => 'Adresa gresita',
            'S43' => 'Retur',
            'S46' => 'Predat punct Livrare',
            'S47' => 'Predat partener extern',
            'S49' => 'Activitate suspendata',
            'S50' => 'Refuz confirmare',
            'H0' => 'Expeditie in tranzit spre depozitul de destinatie',
            'H1' => 'Expeditie descarcata in depozitulul de destinatie',
            'S54' => 'Locker plin',
            'S55' => 'Dimensiune caseta depasita',
            'S64' => 'Lipsa acte vama',
        ];
    }

    /**
     * @return array
     */
    public static function getIdOrderByAwbs()
    {
        $return = [];
        $data = Db::getInstance()->executeS('SELECT `id_order`, `awb` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs`');
        foreach ($data as $row) {
            $return[$row['awb']] = $row['id_order'];
        }

        return $return;
    }
}
