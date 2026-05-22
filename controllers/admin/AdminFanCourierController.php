<?php

class AdminFanCourierController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function checkAccess()
    {
        return $this->context->employee->can('view', 'AdminOrders');
    }

    private function getApiUrl()
    {
        return 'https://ecommerce.fancourier.ro';
    }

    protected function sendResponse($data)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    private function normalizeString($str)
    {
        $str = preg_replace('/^(Județul|Municipiul)\s*/i', '', (string)$str);
        $unwanted = [
            'ș' => 's', 'ț' => 't', 'ă' => 'a', 'î' => 'i', 'â' => 'a',
            'Ș' => 'S', 'Ț' => 'T', 'Ă' => 'A', 'Î' => 'I', 'Â' => 'A',
            ',' => '', '.' => ''
        ];
        return strtr(trim($str), $unwanted);
    }

    private function getPackageDimensions($order)
    {
        $items_dimensions = [];

        foreach ($order->getProducts() as $productData) {
            $p = new Product((int)$productData['product_id']);
            $l = (float)$p->depth > 0 ? (float)$p->depth : 10;
            $w = (float)$p->width > 0 ? (float)$p->width : 10;
            $h = (float)$p->height > 0 ? (float)$p->height : 10;

            $qty = (int)$productData['product_quantity'];

            for ($i = 0; $i < $qty; $i++) {
                $items_dimensions[] = [$l, $w, $h];
            }
        }

        if (empty($items_dimensions)) {
            return [10, 10, 10];
        }

        $min_total_volume = PHP_FLOAT_MAX;
        $best_dimensions = [10, 10, 10];

        for ($stack_axis = 0; $stack_axis < 3; $stack_axis++) {
            $curr_l = 0; $curr_w = 0; $curr_h = 0;

            foreach ($items_dimensions as $dims) {
                rsort($dims);

                if ($stack_axis == 0) {
                    $curr_l += $dims[0];
                    $curr_w = max($curr_w, $dims[1]);
                    $curr_h = max($curr_h, $dims[2]);
                } elseif ($stack_axis == 1) {
                    $curr_l = max($curr_l, $dims[0]);
                    $curr_w += $dims[1];
                    $curr_h = max($curr_h, $dims[2]);
                } else {
                    $curr_l = max($curr_l, $dims[0]);
                    $curr_w = max($curr_w, $dims[1]);
                    $curr_h += $dims[2];
                }
            }

            $volume = $curr_l * $curr_w * $curr_h;

            if ($volume < $min_total_volume) {
                $min_total_volume = $volume;
                $best_dimensions = [$curr_l, $curr_w, $curr_h];
            }
        }

        return [(int)round($best_dimensions[0]), (int)round($best_dimensions[1]), (int)round($best_dimensions[2])];
    }

    private function getToken($forceRefresh = false)
    {
        if (!$forceRefresh) {
            $token = Db::getInstance()->getValue("SELECT token FROM `" . _DB_PREFIX_ . "fancourier_auth_info`");
            if ($token) {
                return $token;
            }
        }

        $username = Configuration::get('FAN_USERNAME');
        $password = Configuration::get('FAN_PASSWORD');
        $clientId = Configuration::get('FAN_CLIENT_ID');

        if (!$username || !$password) {
            return false;
        }

        $ch = curl_init($this->getApiUrl() . '/authShop');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $username,
            'password' => $password,
            'clientId' => $clientId,
            'domain'   => Tools::getHttpHost(true)
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $token = $data['data']['token'] ?? ($data['token'] ?? null);

        if ($token) {
            Db::getInstance()->execute("DELETE FROM `" . _DB_PREFIX_ . "fancourier_auth_info`");
            Db::getInstance()->insert('fancourier_auth_info', ['token' => pSQL($token), 'date_add' => date('Y-m-d H:i:s')]);
            return $token;
        }

        return false;
    }

    private function makeRequest($endpoint, $params = [], $method = 'POST', $isRetry = false)
    {
        if (!isset($params['domain'])) {
            $params['domain'] = Tools::getHttpHost(true);
        }

        $token = $this->getToken();

        $ch = curl_init($this->getApiUrl() . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $headers = [
            'Accept: application/json'
        ];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (($httpCode == 401 || stripos($response, 'unauthorized') !== false) && !$isRetry) {
            $newToken = $this->getToken(true);
            if ($newToken && $newToken !== $token) {
                return $this->makeRequest($endpoint, $params, $method, true);
            }
        }

        return $response;
    }

    public function ajaxProcessGenerateAwb()
    {
        try {
            $id_order = (int)Tools::getValue('id_order');
            if (!$id_order) {
                throw new Exception('Lipsă ID Comandă');
            }

            $order = new Order($id_order);
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Comanda nu a fost găsită în PrestaShop.');
            }
            
            $address = new Address($order->id_address_delivery);
            $customer = new Customer($order->id_customer);

            $service_id = (int)Tools::getValue('service_id');
            $packages = (int)Tools::getValue('packages');
            $envelopes = (int)Tools::getValue('envelopes');
            $weight = (float)Tools::getValue('weight');
            $repaymentRon = (float)Tools::getValue('repayment');
            
            $lockerIdRaw = Tools::getValue('locker_id');
            $lockerNameRaw = Tools::getValue('locker_name');

            $isPudo = FanServiceMapper::isPudoService($service_id);
            $useCollector = (Configuration::get('FAN_REFUND_BANK') === 'yes');

            if ($repaymentRon > 0 && $useCollector) {
                $cod_map = FanServiceMapper::getCodServiceMap();
                if (isset($cod_map[$service_id])) {
                    $service_id = $cod_map[$service_id];
                }
            }

            $inputLen = (int)Tools::getValue('length');
            $inputWidth = (int)Tools::getValue('width');
            $inputHeight = (int)Tools::getValue('height');
            
            if ($inputLen <= 0 || $inputWidth <= 0 || $inputHeight <= 0) {
                $dims = FanCalculator::getOptimizedDimensions($order->getProducts());
                $dimL = $dims['l']; $dimW = $dims['w']; $dimH = $dims['h'];
            } else {
                $dimL = $inputLen; $dimW = $inputWidth; $dimH = $inputHeight;
            }

            $rawContent = Tools::getValue('content', "Comanda #$id_order");
            $safeContent = strlen($rawContent) > 200 ? substr($rawContent, 0, 197) . '...' : $rawContent;

            Db::getInstance()->execute('
                INSERT INTO `' . _DB_PREFIX_ . 'fancourier_order` 
                (id_order, service_id, packages, envelopes, weight, length, width, height, repayment, content, locker_id, locker_name, date_add)
                VALUES (
                    ' . (int)$id_order . ', ' . (int)$service_id . ', ' . (int)$packages . ', ' . (int)$envelopes . ',
                    ' . (float)$weight . ', ' . (int)$dimL . ', ' . (int)$dimW . ', ' . (int)$dimH . ', ' . (float)$repaymentRon . ',
                    "' . pSQL($safeContent) . '", "' . pSQL($isPudo ? $lockerIdRaw : '') . '", "' . pSQL($isPudo ? $lockerNameRaw : '') . '", NOW()
                )
                ON DUPLICATE KEY UPDATE
                service_id = ' . (int)$service_id . ', packages = ' . (int)$packages . ', envelopes = ' . (int)$envelopes . ',
                weight = ' . (float)$weight . ', length = ' . (int)$dimL . ', width = ' . (int)$dimW . ', height = ' . (int)$dimH . ',
                repayment = ' . (float)$repaymentRon . ', content = "' . pSQL($safeContent) . '",
                locker_id = "' . pSQL($isPudo ? $lockerIdRaw : '') . '", locker_name = "' . pSQL($isPudo ? $lockerNameRaw : '') . '"
            ');

            $finalPhone = Tools::getValue('recipient_phone');
            if (empty($finalPhone)) {
                $finalPhone = $address->phone_mobile ? $address->phone_mobile : $address->phone;
            }
            
            $phoneDigits = preg_replace('/[^0-9]/', '', (string)$finalPhone);
            if ($isPudo && (empty($finalPhone) || $finalPhone === '0000000000' || strlen($phoneDigits) < 10)) {
                throw new Exception('Pentru livrările în FANbox / CollectPoint, un număr de telefon valid este OBLIGATORIU.');
            }
            
            if (empty($finalPhone)) {
                $finalPhone = '0000000000';
            }

            $finalContactPerson = Tools::getValue('recipientContactPerson');
            if (empty($finalContactPerson)) {
                $finalContactPerson = $address->firstname . ' ' . $address->lastname;
            }

            $finalRecipientName = $address->company ? $address->company : ($address->firstname . ' ' . $address->lastname);

            $defaultCurrency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
            $orderCurrency = new Currency((int)$order->id_currency);
            $declaredValueRon = Tools::convertPriceFull($order->total_products, $orderCurrency, $defaultCurrency);

            $params = [
                'orderId' => $id_order,
                'serviceTypeId' => $service_id,
                'noPackages' => $packages,
                'noEnvelopes' => $envelopes,
                'weight' => $weight,
                'length' => $dimL,
                'width' => $dimW,
                'height' => $dimH,
                'payment' => (Configuration::get('FAN_PAYMENT_DEST') === 'destinatar') ? 1 : 0,
                'repayment' => $repaymentRon,
                'content' => $safeContent,
                'observations' => Tools::getValue('observations'),
                'recipientName' => $finalRecipientName,
                'recipientContactPerson' => $finalContactPerson,
                'recipientPhone' => $finalPhone,
                'recipientEmail' => $customer->email,
                'declaredValue' => (Configuration::get('FAN_INSURANCE') === 'yes') ? $declaredValueRon : 0
            ];

            if ($isPudo) {
                if (empty($lockerIdRaw)) {
                    $lockerDb = Db::getInstance()->getRow('SELECT locker_id FROM `' . _DB_PREFIX_ . 'fancourier_cart_info` WHERE id_cart = ' . (int)$order->id_cart);
                    $lockerIdRaw = (is_array($lockerDb) && isset($lockerDb['locker_id'])) ? $lockerDb['locker_id'] : '';
                }

                if (empty($lockerIdRaw)) {
                    throw new Exception('Eroare: Nu a fost selectat niciun Locker pentru acest serviciu.');
                }

                $params['lockerId'] = $lockerIdRaw;
                
                $lockerInfo = Db::getInstance()->getRow('SELECT locker_address, locker_name FROM `' . _DB_PREFIX_ . 'fancourier_cart_info` WHERE locker_id = "' . pSQL($lockerIdRaw) . '"');
                if ($lockerInfo) {
                    $parts = explode('|', $lockerInfo['locker_address']);
                    $params['recipientCounty'] = !empty($parts[0]) ? $this->normalizeString(trim($parts[0])) : $this->normalizeString(State::getNameById($address->id_state));
                    $params['recipientLocality'] = !empty($parts[1]) ? $this->normalizeString(trim($parts[1])) : $this->normalizeString($address->city);
                    $params['recipientStreet'] = $lockerInfo['locker_name'];
                } else {
                    $params['recipientCounty'] = $this->normalizeString(State::getNameById($address->id_state));
                    $params['recipientLocality'] = $this->normalizeString($address->city);
                    $params['recipientStreet'] = $lockerNameRaw;
                }

            } else {
                $params['recipientCounty'] = $address->id_state ? $this->normalizeString(State::getNameById($address->id_state)) : '';
                $params['recipientLocality'] = $this->normalizeString($address->city);
                $params['recipientStreet'] = $this->normalizeString($address->address1 . ' ' . $address->address2);
                $params['recipientZipCode'] = Tools::getValue('postal_code') ?: $address->postcode;
            }

            if (strtolower($params['recipientCounty']) === 'bucuresti' || strtolower($params['recipientCounty']) === 'b') {
                $params['recipientCounty'] = 'Bucuresti';
                $params['recipientLocality'] = 'Bucuresti'; 
            }

            $response = $this->makeRequest('/generate-awb', $params, 'POST');
            
            if (empty($response)) {
                throw new Exception('API-ul FAN Courier nu a returnat niciun răspuns.');
            }

            $data = json_decode($response, true);
            
            $awb = null;
            if (isset($data['awbNumber'])) $awb = $data['awbNumber'];
            elseif (isset($data['awb'])) $awb = $data['awb'];
            elseif (isset($data['awbNo'])) $awb = $data['awbNo'];

            if ($awb) {
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'fancourier_order` SET awb_number = \'' . pSQL($awb) . '\', service_id = ' . (int)$service_id . ' WHERE id_order = ' . (int)$id_order);
                
                Db::getInstance()->execute('
                    INSERT INTO `' . _DB_PREFIX_ . 'fancourier_order_info` (`id_order`, `fan_AWB`) 
                    VALUES (' . (int)$id_order . ', \'' . pSQL($awb) . '\')
                    ON DUPLICATE KEY UPDATE `fan_AWB` = \'' . pSQL($awb) . '\'
                ');

                $order->shipping_number = $awb;
                $order->update();
                Db::getInstance()->update('order_carrier', ['tracking_number' => pSQL($awb)], 'id_order = ' . (int)$id_order);
                
                $this->sendResponse([
                    'success' => true, 
                    'awb' => $awb, 
                    'message' => 'AWB Generat: ' . $awb
                ]);
            } else {
                $err = $data['errors'] ?? ($data['message'] ?? 'Eroare necunoscută API: ' . strip_tags($response));
                if (is_array($err)) {
                    $flatErrors = [];
                    array_walk_recursive($err, function($v) use (&$flatErrors) { $flatErrors[] = $v; });
                    $err = implode(', ', $flatErrors);
                }
                throw new Exception($err);
            }

        } catch (Exception $e) {
            $this->sendResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessDeleteAwb()
    {
        try {
            $id_order = (int)Tools::getValue('id_order');
            
            $this->makeRequest('/delete-awb', ['orderId' => $id_order], 'POST');

            Db::getInstance()->update('fancourier_order', ['awb_number' => null], 'id_order = ' . $id_order);
            Db::getInstance()->update('fancourier_order_info', ['fan_AWB' => null], 'id_order = ' . $id_order);
            Db::getInstance()->update('order_carrier', ['tracking_number' => ''], 'id_order = ' . $id_order);

            $this->sendResponse(['success' => true, 'message' => 'AWB șters.']);
        } catch (Exception $e) {
            $this->sendResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function processPrintAwb()
    {
        while (ob_get_level()) { ob_end_clean(); }
        $id_order = (int)Tools::getValue('id_order');
        $awb = Tools::getValue('awb');
        if (!$id_order && $awb) { $id_order = (int)Db::getInstance()->getValue('SELECT id_order FROM `' . _DB_PREFIX_ . 'fancourier_order` WHERE awb_number = "' . pSQL($awb) . '"'); }
        if (!$id_order) $id_order = (int)Tools::getValue('order_id');
        if (!$id_order) die('Eroare: ID Comanda lipsa.');
        $params = [ 'orderId' => $id_order, 'labelType' => 'A4' ];
        $response = $this->makeRequest('/print-awb', $params, 'POST');
        $pdfContent = null;
        $json = json_decode($response, true);
        if ($json && isset($json['data']['pdf'])) { $pdfContent = base64_decode($json['data']['pdf']); } 
        elseif ($json && isset($json['pdf'])) { $pdfContent = base64_decode($json['pdf']); }
        elseif (strpos($response, '%PDF') === 0) { $pdfContent = $response; }
        if ($pdfContent) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="AWB-' . ($awb ?: $id_order) . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($pdfContent));
            echo $pdfContent;
            exit;
        } else { die('Eroare la generarea PDF-ului.'); }
    }

    public function ajaxProcessEstimateCost() 
    {
        try {
            $service_id = (int)Tools::getValue('service_id');
            $id_order = (int)Tools::getValue('id_order');

            $order = new Order($id_order);
            $address = new Address($order->id_address_delivery);
            
            $county = $this->normalizeString(State::getNameById($address->id_state));
            $locality = $this->normalizeString($address->city);

            if (strtolower($county) === 'bucuresti' || strtolower($county) === 'b') {
                $county = 'Bucuresti';
                $locality = 'Bucuresti';
            }

            $totalWeight = (float)Tools::getValue('weight') ?: FanCalculator::getOrderWeight($order->getProducts());
            $totalWeight = ceil(max($totalWeight, 1));

            $repaymentRon = (float)Tools::getValue('repayment');

            if ($repaymentRon > 0 && Configuration::get('FAN_REFUND_BANK') === 'yes') {
                $cod_map = FanServiceMapper::getCodServiceMap();
                if (isset($cod_map[$service_id])) {
                    $service_id = $cod_map[$service_id];
                }
            }

            $dims = FanCalculator::getOptimizedDimensions($order->getProducts());
            $dimL = (int)Tools::getValue('length') ?: $dims['l'];
            $dimW = (int)Tools::getValue('width') ?: $dims['w'];
            $dimH = (int)Tools::getValue('height') ?: $dims['h'];
            
            $params = [
                'serviceTypeId' => $service_id,
                'recipientLocality' => $locality,
                'recipientCounty' => $county,
                'noPackages' => 1,
                'noEnvelopes' => 0,
                'weight' => $totalWeight,
                'length' => $dimL,
                'width' => $dimW,
                'height' => $dimH,
                'repayment' => $repaymentRon,
                'declaredValue' => (Configuration::get('FAN_INSURANCE') === 'yes') ? (float)$order->total_products : 0,
                'domain' => Tools::getHttpHost(true)
            ];

            $lockerId = Tools::getValue('locker_id');
            if ($lockerId && $lockerId !== 'undefined') {
                $params['lockerId'] = $lockerId;
            }

            $response = $this->makeRequest('/get-tariff-new', $params, 'POST');
            $data = json_decode($response, true);
            
            if ($data) {
                $api_base_cost = false;
                
                if (isset($data['tariff'])) { 
                    $api_base_cost = (float)$data['tariff']; 
                } elseif (isset($data['data']['total'])) { 
                    $api_base_cost = (float)$data['data']['total']; 
                }

                if ($api_base_cost !== false) {
                    $this->sendResponse([
                        'success' => true,
                        'cost' => number_format($api_base_cost, 2, '.', '')
                    ]);
                } else {
                    $errorMessage = isset($data['message']) ? $data['message'] : '';
                    throw new Exception('API-ul nu a returnat un tarif valid. ' . $errorMessage);
                }
            } else {
                throw new Exception('Eroare la parsarea răspunsului de la FAN Courier.');
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessGetPickupHours()
    {
        try {
            $date = Tools::getValue('date');
            
            if (!$date) {
                throw new Exception('Data este obligatorie.');
            }

            $params = [
                'date' => $date,
                'orderType' => 0, 
                'domain' => Tools::getHttpHost(true)
            ];

            $response = $this->makeRequest('/order-hours', $params, 'POST');
            $data = json_decode($response, true);

            if (!is_array($data)) {
                throw new Exception('Eroare API la preluare ore: ' . substr(strip_tags($response), 0, 150));
            }

            if (isset($data['pickupIntervals'])) {
                $this->sendResponse([
                    'success' => true,
                    'pickupIntervals' => $data['pickupIntervals']
                ]);
            } else {
                $msg = $data['message'] ?? ($data['error'] ?? 'Nu am putut prelua intervalele orare.');
                throw new Exception($msg);
            }

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxProcessCallCourier()
    {
        try {
            $pickupDate = Tools::getValue('pickup_date');
            $pickupStart = Tools::getValue('pickup_start');
            $pickupEnd = Tools::getValue('pickup_end');

            $params = [
                'domain' => Tools::getHttpHost(true)
            ];

            if ($pickupDate && $pickupStart && $pickupEnd) {
                $params['date']      = $pickupDate;
                $params['readyTime'] = $pickupStart;
                $params['endTime']   = $pickupEnd;
            }

            $response = $this->makeRequest('/call-courier', $params, 'POST');
            $data = json_decode($response, true);

            if (!is_array($data)) {
                throw new Exception('Eroare internă API: ' . substr(strip_tags($response), 0, 150));
            }

            if ((isset($data['success']) && $data['success']) || isset($data['id'])) {
                Configuration::updateValue('FAN_LAST_CALL_DATE', date('Y-m-d'));
                
                $courierOrderId = isset($data['id']) ? $data['id'] : (isset($data['data']['id']) ? $data['data']['id'] : '');
                $message = 'Comandă curier plasată cu succes!';
                if (!empty($courierOrderId)) {
                    $message .= ' ID Comandă: ' . $courierOrderId;
                }

                $this->sendResponse(['success' => true, 'message' => $message]);
            } else {
                $err = $data['errors'] ?? ($data['message'] ?? 'Eroare API la chemarea curierului');
                if (is_array($err)) {
                    $err = implode(', ', $err);
                }
                throw new Exception($err);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}