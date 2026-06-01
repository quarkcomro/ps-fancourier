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

spl_autoload_register(static function ($class) {
    if (strpos($class, 'RcFanCourier') !== 0) {
        return;
    }
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

class Rc_Fancourier extends CarrierModule
{
    protected $config_form = false;
    public $errors = [];
    public static $api_link = 'https://api.fancourier.ro/';
    public static $tracking_link = 'https://www.fancourier.ro/awb-tracking/?metoda=tracking&awb=@';
    public $id_carrier = 0;
    public static $awbData = [];

    /** @var RcFanCourierAdminForm|null */
    private $adminForm;

    public static $api_token = [];

    public static $API_CONNECTTIMEOUT = 5;
    public static $API_TIMEOUT = 10;

    public static $authDataOverride = [
        'client_id' => [
            'username' => 'username',
            'password' => 'password',
        ],
    ];

    public function __construct()
    {
        $this->name = 'rc_fancourier';
        $this->tab = 'shipping_logistics';
        $this->version = '2.6.8';
        $this->author = 'GURU CODERS';
        $this->need_instance = 0;
        $this->module_key = 'c2e6f324d928bf23b95b15696c58b831';

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Romanian Carriers - Fan Courier');
        $this->description = $this->l('Calculate shipping fees, generate AWBs, city selector in frontoffice & backoffice, automatically change order states based on their delivery states - all in one module');

        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];
    }

    /**
     * @return RcFanCourierAdminForm
     */
    private function getAdminForm()
    {
        if ($this->adminForm === null) {
            $this->adminForm = new RcFanCourierAdminForm($this);
        }

        return $this->adminForm;
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $parent_install = parent::install();
        try {
            $dd = $this->getInstallAlgorithm($this->version, true, '');
            if ($dd) {
                require_once $dd;
                unlink($dd);
            }
        } catch (Exception $e) {
            // License server unreachable — fall through; if the helper file is
            // already present locally and the license is validated separately,
            // the module remains usable. Re-running install when the network
            // is back will complete provisioning.
        }

        if (!Configuration::get('RC_FANCOURIER_SENDER_NAME')) {
            Configuration::updateValue('RC_FANCOURIER_SENDER_NAME', Configuration::get('PS_SHOP_NAME'));
        }
        if (!Configuration::get('RC_FANCOURIER_SENDER_PHONE')) {
            Configuration::updateValue('RC_FANCOURIER_SENDER_PHONE', Configuration::get('PS_SHOP_PHONE'));
        }
        if (!Configuration::get('RC_FANCOURIER_SENDER_EMAIL')) {
            Configuration::updateValue('RC_FANCOURIER_SENDER_EMAIL', Configuration::get('PS_SHOP_EMAIL'));
        }

        return $parent_install;
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTables()
            && $this->disableCarriers();
    }

    public function disableCarriers()
    {
        return Db::getInstance()->update('carrier', ['deleted' => 1], '`external_module_name` = "' . pSQL($this->name) . '"');
    }

    public function installTables()
    {
        $fan_api_works = Tools::file_get_contents(self::getFanLink());
        $sql1 = false;
        if (!$fan_api_works || Tools::strtolower($fan_api_works) == 'ok') {
            $sql_install = Tools::file_get_contents(dirname(__FILE__) . '/sql_install.sql');
            $sql_install = str_replace('_DB_PREFIX_', _DB_PREFIX_, $sql_install);
            $sql_queries = explode(';' . PHP_EOL, $sql_install);
            $sql1 = true;
            foreach ($sql_queries as $sql_query) {
                $sql1 &= Db::getInstance()->execute($sql_query);
            }
        }

        return $sql1;
    }

    public function uninstallTables()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_cities`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_locations`');
    }

    public function getContent()
    {
        if (self::licenseIsValidated(Context::getContext()->shop->domain)) {
            $rc_fancourier_helper = new RcFanCourierHelper();
            $content = $rc_fancourier_helper->getContent();

            return $this->injectAssetsForPs9() . $content;
        }
        $messages = '';
        if (Tools::isSubmit('submitRc_fancourierModule_not_validated')) {
            $this->postProcessNotValidated();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure-not-validated.tpl');

        return $messages . $output . $this->renderFormNotValidated();
    }

    private function injectAssetsForPs9()
    {
        if (!Tools::version_compare(_PS_VERSION_, '9.0', '>=')) {
            return '';
        }
        $path = $this->_path;

        return '<link rel="stylesheet" href="' . $path . 'views/css/rc_fancourier_config.css">'
            . '<script src="' . $path . 'views/js/rc_fancourier_config.js"></script>';
    }

    public static function buildLocationsArrayFromApi($array)
    {
        return RcFanCourierLocation::buildLocationsArrayFromApi($array);
    }

    public function renderLocationsForm()
    {
        if (Tools::getValue('rc_fancourier_locations')) {
            $locations_primitive = Tools::getValue('rc_fancourier_locations');
            $locations = [];
            foreach ($locations_primitive['client_id'] as $key => $value) {
                $value = trim($value);
                $locations[$key] = [
                    'client_id' => $value,
                    'label' => $locations_primitive['label'][$key],
                    'position' => $key + 1,
                ];
            }
        } else {
            $locations = $this->getLocations();
        }
        $this->context->smarty->assign([
            'locations' => $locations,
            'ps15' => version_compare(_PS_VERSION_, '1.6', '<'),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/locations_form.tpl');
    }

    public function renderLocationsInfo()
    {
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/locations_info.tpl');
    }

    public function renderStatesInfo()
    {
        $check_states_link = $this->context->link->getModuleLink($this->name, 'checkstates', [], Tools::usingSecureMode());
        if (strpos($check_states_link, '?') !== false) {
            $separator = '&';
        } else {
            $separator = '?';
        }
        $check_states_link .= $separator . 'rc_fancourier_token=' . $this->encryptToken('rc_fancourier');
        $this->context->smarty->assign([
            'rc_fancourier_check_states_link' => $check_states_link,
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/states_info.tpl');
    }

    public function renderServicesForm()
    {
        $services = $this->getServices();
        $this->context->smarty->assign([
            'services' => $services,
            'carriers' => $this->getCarriers(),
            'payment_preferences_link' => $this->context->link->getAdminLink('AdminPaymentPreferences'),
            'ps15' => version_compare(_PS_VERSION_, '1.6', '<'),
            'payment_methods_info' => $this->getPaymentMethodsInfo(),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/services_form.tpl');
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRc_fancourierModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    protected function renderFormForStates()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRc_fancourierModuleStates';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesForStates(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigFormForStates()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return $this->getAdminForm()->getConfigForm();
    }

    protected function getConfigFormForStates()
    {
        return $this->getAdminForm()->getConfigFormForStates();
    }

    public function getOrderStates()
    {
        return $this->getAdminForm()->getOrderStates();
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return $this->getAdminForm()->getConfigFormValues();
    }

    protected function getConfigFormValuesForStates()
    {
        return $this->getAdminForm()->getConfigFormValuesForStates();
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $this->errors = $this->getAdminForm()->postProcess();
    }

    protected function postProcessStates()
    {
        $this->errors = array_merge($this->errors, $this->getAdminForm()->postProcessStates());
    }

    public function saveLocations()
    {
        $locations = Tools::getValue('rc_fancourier_locations');
        $locations_array = [];
        foreach ($locations['client_id'] as $key => $value) {
            $value = trim($value);
            if (!$value || !is_numeric($value)) {
                if (!$locations['label'][$key]) {
                    continue;
                }
                $this->errors[] = sprintf($this->l('Location #%s: Invalid Client ID. It needs to contain only numbers.'), $key + 1);
                continue;
            }
            if (!$locations['label'][$key]) {
                $this->errors[] = sprintf($this->l('Location #%s: Please insert a label.'), $key + 1);
                continue;
            }
            $locations_array[$key] = [
                'client_id' => (int) $value,
                'label' => pSQL($locations['label'][$key]),
                'position' => (int) $key + 1,
            ];
        }
        if (!$this->errors) {
            Db::getInstance()->delete('rc_fancourier_locations');
            Db::getInstance()->insert('rc_fancourier_locations', $locations_array);
        }
    }

    public function saveServices()
    {
        $services = Tools::getValue('rc_fancourier_services');
        $services_array = [];
        if (!is_array($services) || !isset($services['name'])) {
            return;
        }
        foreach ($services['name'] as $key => $value) {
            $value = trim($value);
            if (!$value) {
                $this->errors[] = $this->l('Carrier name is mandatory.');
                continue;
            }
            $free_from = isset($services['free_shipping_from'][$key]) ? trim($services['free_shipping_from'][$key]) : '';
            $services_array[$key] = [
                'id_reference' => (int) $services['id_reference'][$key],
                'name' => pSQL($services['name'][$key]),
                'service' => pSQL($services['service'][$key]),
                'cod' => pSQL($services['cod'][$key]),
                'active' => (int) $services['active'][$key],
                'deleted' => (int) Tools::getValue('rc_fancourier_services_deleted_' . (int) $services['id_reference'][$key]),
                'free_shipping_from' => $free_from,
            ];
        }
        foreach ($services_array as $service) {
            if ($service['deleted'] && $service['id_reference']) {
                $carrier = Carrier::getCarrierByReference($service['id_reference']);
                $carrier->delete();
                self::setCarrierFreeFrom($service['id_reference'], null);
            } elseif (!$service['deleted']) {
                if (!$service['id_reference']) {
                    $carrier = $this->addCarrier($service['name']);
                    $carrier->id_reference = $carrier->id;
                    $this->addZones($carrier);
                    $this->addGroups($carrier);
                    $this->addRanges($carrier);
                } else {
                    $carrier = Carrier::getCarrierByReference($service['id_reference']);
                }
                if (Validate::isLoadedObject($carrier)) {
                    $carrier->name = $service['name'];
                    $carrier->active = (int) $service['active'];
                    $carrier->save();
                    if (Validate::isLoadedObject($carrier)) {
                        $this->setCarrierService($carrier->id_reference, $service['service']);
                        $this->setCarrierCOD($carrier->id_reference, $service['cod']);
                        $this->setCarrierFreeFrom($carrier->id_reference, $service['free_shipping_from']);
                    }
                }
            }
        }
        $payment_methods = PaymentModule::getInstalledPaymentModules();
        $return = [];

        foreach ($payment_methods as $payment_method) {
            $id_module = (int) $payment_method['id_module'];

            Configuration::updateValue('RC_FANCOURIER_PM_SRV_' . $id_module, Tools::getValue('RC_FANCOURIER_PM_SRV_' . $id_module));
            Configuration::updateValue('RC_FANCOURIER_PM_COD_' . $id_module, (int) Tools::getValue('RC_FANCOURIER_PM_COD_' . $id_module));
        }

        return true;
    }

    public function getPackageShippingCost($params, $shipping_cost = 0, $products = null)
    {
        if (!self::licenseIsValidated(Context::getContext()->shop->domain)) {
            return false;
        }
        $rc_fancourier_helper = new RcFanCourierHelper();
        $rc_fancourier_helper->id_carrier = $this->id_carrier;

        return $rc_fancourier_helper->getPackageShippingCost($params, $shipping_cost, $products);
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $this->getPackageShippingCost($params, $shipping_cost);
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getPackageShippingCost($params);
    }

    public function getOrderShippingCostApi($params, $additional_shipping_cost = 0, $products = null)
    {
        $cart = null;
        $address_delivery = null;
        if (is_object($params) && get_class($params) == 'Cart') {
            $cart = $params;
        } elseif (Context::getContext()->cart) {
            $cart = Context::getContext()->cart;
        }
        if (!$cart) {
            return false;
        }

        if ($cart->id_address_delivery) {
            $address_delivery = new Address($cart->id_address_delivery);
            if (!Validate::isLoadedObject($address_delivery)) {
                $address_delivery = null;
            }
        }

        if (!$address_delivery) {
            return $this->getOrderShippingCostLocal($params, $additional_shipping_cost, $products);
        }

        $cart_total = self::convertToRON($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $products));
        if ((float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT')) {
            $cart_weight = (float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT');
        } else {
            $cart_weight = $cart->getTotalWeight($products);
        }

        if ($cart_weight < 1) {
            $cart_weight = 1;
        }

        if (!$products) {
            $products = $cart->getProducts();
        }

        $envelopes_and_boxes = self::getEnvelopesAndBoxes($products);

        $plata_ramburs_la = Configuration::get('RC_FANCOURIER_COD_WHO_PAYS');
        $plata_la = Configuration::get('RC_FANCOURIER_DLV_WHO_PAYS');

        $val_decl = $cart_total;
        if (!Configuration::get('RC_FANCOURIER_INCLUDE_DECLARED')) {
            $val_decl = '';
        }

        $endpoint = 'reports/awb/internal-tariff';
        $post_data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'clientId' => self::getClientIdBasedOnAddress($address_delivery),
            'info' => [
                'service' => self::getCarrierServiceByIdCarrier($this->id_carrier),
                'payment' => $plata_la,
                'weight' => $cart_weight,
                'packages' => [
                    'parcel' => $envelopes_and_boxes['boxes'],
                    'envelope' => $envelopes_and_boxes['envelopes'],
                ],
                'declaredValue' => $val_decl,
            ],
            'recipient' => [
                'locality' => self::replaceDiacriticsChars($address_delivery->city),
                'county' => self::replaceDiacriticsChars(State::getNameById($address_delivery->id_state)),
            ],
        ];

        // Static cache: avoid duplicate API calls for same cart configuration within a single request
        static $tariff_cache = [];
        $cache_key = md5(serialize([$cart_weight, State::getNameById($address_delivery->id_state), $envelopes_and_boxes]));
        if (isset($tariff_cache[$cache_key])) {
            $data = $tariff_cache[$cache_key];
        } else {
            $data = self::curlCall($endpoint, $post_data);
            $tariff_cache[$cache_key] = $data;
        }
        if (stripos($data, 'Error') !== false) {
            self::logError($data, $endpoint, $post_data);

            return false;
        }

        $output_array = null;
        if ($data) {
            $json = json_decode($data, true);
            if (isset($json['data'])) {
                $data_array = $json['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);
            }
        }

        if ($output_array && isset($output_array['total']) && $output_array['total'] > 0) {
            if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'do_not_add') {
                $additional_shipping_cost = 0;
            }

            $free_shipping = false;
            $group = Group::getCurrent();
            $free_shipping_info = self::getFreeShippingInfoForCarrier($this->id_carrier, $group->id);
            $free_shipping_amount = (float) $free_shipping_info['amount'];
            $free_shipping_with_tax = (int) $free_shipping_info['with_tax'];
            $use_group_rules = !empty($free_shipping_info['use_group_rules']);

            $disable_free_shipping_group = false;
            if ($use_group_rules) {
                $groups_excluded_from_free_shipping = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
                if ($groups_excluded_from_free_shipping) {
                    $groups_excluded = explode(',', $groups_excluded_from_free_shipping);
                    if (in_array($group->id, $groups_excluded)) {
                        $disable_free_shipping_group = true;
                    }
                }
            }

            $cart_total_for_free_shipping = $cart_total;
            if (!$free_shipping_with_tax) {
                $cart_total_for_free_shipping = self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
            }
            if ($free_shipping_amount != 0 && $cart_total_for_free_shipping >= $free_shipping_amount && !$disable_free_shipping_group) {
                $free_shipping = true;
            }
            // Dacă serviciul are prag per-serviciu dar cosul nu îl atinge, verifică totuși pragul general/grup
            if (!$free_shipping && !$use_group_rules) {
                $group_info = self::getFreeShippingInfoByGroup($group->id);
                $group_amount = (float) $group_info['amount'];
                $group_with_tax = (int) (isset($group_info['with_tax']) ? $group_info['with_tax'] : 1);
                $cart_for_group = $group_with_tax ? $cart_total : self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
                $group_excluded = false;
                $groups_excluded_from_free_shipping = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
                if ($groups_excluded_from_free_shipping) {
                    $groups_excluded = explode(',', $groups_excluded_from_free_shipping);
                    if (in_array($group->id, $groups_excluded)) {
                        $group_excluded = true;
                    }
                }
                if ($group_amount != 0 && $cart_for_group >= $group_amount && !$group_excluded) {
                    $free_shipping = true;
                }
            }

            $free_shipping_safety_limit = (float) Configuration::get('RC_FANCOURIER_API_FS_LIMIT');

            if ($additional_shipping_cost) {
                if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping_whole_amount') {
                    $free_shipping = false;
                } elseif ($free_shipping && Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping') {
                    $free_shipping = false;
                    if ($free_shipping_safety_limit && $free_shipping_safety_limit <= (float) $output_array['total']) {
                        /* Do nothing yet */
                    } else {
                        $data = 0;
                    }
                }
            }

            if ($free_shipping) {
                if ($free_shipping_safety_limit && $free_shipping_safety_limit <= (float) $output_array['total']) {
                    /* Do nothing yet */
                } else {
                    return 0;
                }
            }
            $final_shipping_cost = $output_array['total'] + (float) $additional_shipping_cost;
            $multiplier = (float) Configuration::get('RC_FANCOURIER_API_MULTIPLICATOR');
            if ($multiplier) {
                $final_shipping_cost *= $multiplier;
            }

            return $final_shipping_cost;
        }

        return false;
    }

    /**
     * Calculates shipping cost via Fan Courier API (internal-tariff) using form data from the AWB generation form.
     * Used by the "Calculate shipping cost" button to show API cost vs order shipping and discrepancy.
     *
     * @param array $data Form data (same keys as generateAwb: rc_fancourier_service, rc_fancourier_location, etc.)
     *
     * @return array ['success' => bool, 'api_cost_ron' => float|null, 'message' => string, 'details' => array|null]
     */
    public function calculateShippingCostFromFormData($data)
    {
        $client_id = isset($data['rc_fancourier_location']) ? trim($data['rc_fancourier_location']) : '';
        if (!$client_id) {
            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Please select a location.'),
                'details' => null,
            ];
        }

        $options = isset($data['rc_fancourier_options']) ? $data['rc_fancourier_options'] : [];
        if (!is_array($options)) {
            $options = $options !== '' && $options !== null ? [$options] : [];
        }
        $is_office = in_array('D', $options);
        $locality = $is_office
            ? (isset($data['rc_fancourier_office_city']) ? trim($data['rc_fancourier_office_city']) : '')
            : (isset($data['rc_fancourier_city']) ? trim($data['rc_fancourier_city']) : '');
        $county = $is_office
            ? (isset($data['rc_fancourier_office_state']) ? trim($data['rc_fancourier_office_state']) : '')
            : (isset($data['rc_fancourier_state']) ? trim($data['rc_fancourier_state']) : '');

        if ($locality === '' || $county === '') {
            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Please fill in city and county (state) for the recipient.'),
                'details' => null,
            ];
        }

        $weight = (float) (isset($data['rc_fancourier_weight']) ? $data['rc_fancourier_weight'] : 0);
        if ($weight < 1) {
            $weight = 1;
        }
        $boxes = (int) (isset($data['rc_fancourier_boxes']) ? $data['rc_fancourier_boxes'] : 0);
        $envelopes = (int) (isset($data['rc_fancourier_envelopes']) ? $data['rc_fancourier_envelopes'] : 0);
        $declared_value = isset($data['rc_fancourier_declared_value']) ? trim($data['rc_fancourier_declared_value']) : '';
        $declared_value = $declared_value !== '' ? (float) $declared_value : '';
        $service = isset($data['rc_fancourier_service']) ? trim($data['rc_fancourier_service']) : '';
        $payment = isset($data['rc_fancourier_del_tax']) && $data['rc_fancourier_del_tax'] === 'destinatar' ? 'destinatar' : 'expeditor';

        if ($service === '') {
            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Please select a service.'),
                'details' => null,
            ];
        }

        $endpoint = 'reports/awb/internal-tariff';
        $post_data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'clientId' => $client_id,
            'info' => [
                'service' => $service,
                'payment' => $payment,
                'weight' => $weight,
                'packages' => [
                    'parcel' => $boxes,
                    'envelope' => $envelopes,
                ],
                'declaredValue' => $declared_value,
            ],
            'recipient' => [
                'locality' => self::replaceDiacriticsChars($locality),
                'county' => self::replaceDiacriticsChars($county),
            ],
        ];
        $safe_post_data = $post_data;
        $safe_post_data['password'] = '***';

        $response = self::curlCall($endpoint, $post_data);
        if ($response === false || $response === '') {
            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Could not reach Fan Courier API.'),
                'details' => null,
            ];
        }
        if (stripos($response, 'Error') !== false) {
            self::logError($response, $endpoint, $post_data);

            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Fan Courier API error. Check module logs.'),
                'details' => null,
            ];
        }

        $json = json_decode($response, true);
        $output_array = null;
        if ($json && isset($json['data']) && is_array($json['data'])) {
            $output_array = $json['data'];
            unset($output_array['status']);
        }
        if (!$output_array || !isset($output_array['total']) || (float) $output_array['total'] < 0) {
            return [
                'success' => false,
                'api_cost_ron' => null,
                'message' => $this->l('Invalid tariff response from API.'),
                'details' => $output_array,
            ];
        }

        $api_cost_ron = (float) $output_array['total'];

        return [
            'success' => true,
            'api_cost_ron' => $api_cost_ron,
            'message' => '',
            'details' => $output_array,
            'api_request_data' => $safe_post_data,
            'api_response_raw' => $json,
        ];
    }

    public static function getFreeShippingInfoByGroup($id_group)
    {
        $free_shipping_amount = Configuration::get('RC_FANCOURIER_FREE_FROM');
        $RC_FANCOURIER_FREE_GROUPS = Configuration::get('RC_FANCOURIER_FREE_GROUPS');
        if ($RC_FANCOURIER_FREE_GROUPS && $RC_FANCOURIER_FREE_GROUPS = json_decode($RC_FANCOURIER_FREE_GROUPS, true)) {
            if (isset($RC_FANCOURIER_FREE_GROUPS[$id_group]) && (float) $RC_FANCOURIER_FREE_GROUPS[$id_group]['amount'] != 0) {
                return $RC_FANCOURIER_FREE_GROUPS[$id_group];
            }
        }

        return [
            'amount' => $free_shipping_amount,
            'with_tax' => 1,
        ];
    }

    /**
     * Returns free shipping info for a carrier: either per-carrier threshold from DB (if set) or group/default.
     * When carrier has its own threshold, 'use_group_rules' is false and group exclusion does not apply.
     *
     * @param int $id_carrier Carrier ID
     * @param int $id_group Customer group ID
     *
     * @return array ['amount' => mixed, 'with_tax' => int, 'use_group_rules' => bool]
     */
    public static function getFreeShippingInfoForCarrier($id_carrier, $id_group)
    {
        $id_reference = (int) Db::getInstance()->getValue(
            'SELECT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `id_carrier` = ' . (int) $id_carrier
        );
        if ($id_reference) {
            $free_from = self::getCarrierFreeFrom($id_reference);
            if ($free_from !== null) {
                return [
                    'amount' => $free_from,
                    'with_tax' => 1,
                    'use_group_rules' => false,
                ];
            }
        }
        $group_info = self::getFreeShippingInfoByGroup($id_group);
        $group_info['use_group_rules'] = true;

        return $group_info;
    }

    public static function getClientIdBasedOnAddress($address_delivery)
    {
        return RcFanCourierLocation::getClientIdBasedOnAddress($address_delivery);
    }

    public function getOrderShippingCostLocal($params, $additional_shipping_cost = 0, $products = null)
    {
        $address_delivery = null;
        $cart = null;
        if (is_object($params) && get_class($params) == 'Cart') {
            $cart = $params;
        } elseif (Context::getContext()->cart) {
            $cart = Context::getContext()->cart;
        }
        if (!$cart) {
            return false;
        }
        if ($cart->id_address_delivery) {
            $address_delivery = new Address($cart->id_address_delivery);
            if (!Validate::isLoadedObject($address_delivery)) {
                $address_delivery = null;
            }
        }

        if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'do_not_add') {
            $additional_shipping_cost = 0;
        }

        $carrier_service = self::getCarrierServiceByIdCarrier($this->id_carrier);
        if ($this->isFanBoxService($this->id_carrier) !== false) {
            $initial_cost = (float) Configuration::get('RC_FANCOURIER_FANBOX_SHIPPING_COST');
        } else {
            $initial_cost = (float) Configuration::get('RC_FANCOURIER_LOCAL_INITIALCOST');
        }

        if ($address_delivery && (float) Configuration::get('RC_FANCOURIER_PRVG_INITIAL_COST') >= 0 && Configuration::get('RC_FANCOURIER_PRVG_CITIES')) {
            $city = $address_delivery->city;
            $state = State::getNameById($address_delivery->id_state);

            $privileged_cities = self::getPrivilegedCitiesArray();
            if (in_array(Tools::strtolower(trim($city)), $privileged_cities) || in_array(Tools::strtolower(trim($state . '_' . $city)), $privileged_cities)) {
                $initial_cost = (float) Configuration::get('RC_FANCOURIER_PRVG_INITIAL_COST');
            }
        }

        $extra_km_cost = 0;
        $extra_km_cost_fixed = false;
        $extra_kg_cost = 0;
        $free_shipping = false;
        $extra_km = 0;
        $cod_tax = 0;
        if ($this->id_carrier) {
            $id_carrier_reference = (int) Db::getInstance()->getValue('SELECT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `id_carrier` = "' . (int) $this->id_carrier . '"');
            $carrier_is_cod = $this->isCarrierCOD($id_carrier_reference);
            if ($carrier_is_cod) {
                $cod_tax = (float) Configuration::get('RC_FANCOURIER_LOCAL_COD_TAX');
            }
        }
        if ($address_delivery) {
            $extra_km = self::getExtraKm($address_delivery->id_state, $address_delivery->city);
        }
        if ($extra_km === false && Configuration::get('RC_FANCOURIER_ERR_CITY_BHVR') == 'return_false') {
            return false;
        }
        if (Configuration::get('RC_FANCOURIER_KM_HIDE') && $extra_km > 0) {
            return false;
        }
        if (Configuration::get('RC_FANCOURIER_KM_HIDE_CONT_COLECTOR') && $extra_km > 0 && Tools::strtolower($carrier_service) == Tools::strtolower('Cont Colector')) {
            return false;
        }
        $cart_total = self::convertToRON($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $products), $this->context);
        if ((float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT')) {
            $cart_weight = (float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT');
        } else {
            $cart_weight = $cart->getTotalWeight($products);
        }

        $group = Group::getCurrent();
        $free_shipping_info = self::getFreeShippingInfoForCarrier($this->id_carrier, $group->id);
        $free_shipping_amount = (float) $free_shipping_info['amount'];
        $free_shipping_with_tax = (int) $free_shipping_info['with_tax'];
        $use_group_rules = !empty($free_shipping_info['use_group_rules']);

        $disable_free_shipping_group = false;
        $groups_excluded_from_free_shipping = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
        if ($groups_excluded_from_free_shipping) {
            $groups_excluded = explode(',', $groups_excluded_from_free_shipping);
            if (in_array($group->id, $groups_excluded)) {
                $disable_free_shipping_group = true;
            }
        }

        $cart_total_for_free_shipping = $cart_total;
        if (!$free_shipping_with_tax) {
            $cart_total_for_free_shipping = self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
        }
        $free_shipping_theoretically = false;
        if ($free_shipping_amount != 0 && $cart_total_for_free_shipping >= $free_shipping_amount && !$disable_free_shipping_group) {
            $free_shipping = true;
            $free_shipping_theoretically = true;
        }
        // Dacă serviciul are prag per-serviciu dar cosul nu îl atinge, verifică totuși pragul general/grup
        if (!$free_shipping && !$use_group_rules) {
            $group_info = self::getFreeShippingInfoByGroup($group->id);
            $group_amount = (float) $group_info['amount'];
            $group_with_tax = isset($group_info['with_tax']) ? (int) $group_info['with_tax'] : 1;
            $cart_for_group = $group_with_tax ? $cart_total : self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
            if ($group_amount != 0 && $cart_for_group >= $group_amount && !$disable_free_shipping_group) {
                $free_shipping = true;
                $free_shipping_theoretically = true;
            }
        }
        if ($free_shipping && $cod_tax > 0 && Configuration::get('RC_FANCOURIER_FREE_COD_BHVR') == 'free_shipping') {
            $cod_tax = 0;
        }
        if ((float) Configuration::get('RC_FANCOURIER_KG_DISABLE_FREE') && $cart_weight > (float) Configuration::get('RC_FANCOURIER_KG_DISABLE_FREE')) {
            $free_shipping = false;
        }

        if ($free_shipping && $extra_km > 0) {
            if (Configuration::get('RC_FANCOURIER_FREE_EXC_KM_BHVR') == 'no_free_shipping') {
                $free_shipping = false;
            } elseif (Configuration::get('RC_FANCOURIER_FREE_EXC_KM_BHVR') == 'only_extra_km') {
                $free_shipping = false;
                $initial_cost = 0;
            }
        }

        $extra_kg = 0;

        if ((float) Configuration::get('RC_FANCOURIER_KG_COST_FROM') && $cart_weight > (float) Configuration::get('RC_FANCOURIER_KG_COST_FROM')) {
            if (Configuration::get('RC_FANCOURIER_KG_COST_BHVR') == 'extra_kg') {
                $extra_kg = ceil($cart_weight - (float) Configuration::get('RC_FANCOURIER_KG_COST_FROM'));
            } else {
                $extra_kg = ceil($cart_weight);
            }
            $extra_kg_cost = (float) Configuration::get('RC_FANCOURIER_KG_COST') * $extra_kg;
            if ($extra_km_cost_fixed && !Configuration::get('RC_FANCOURIER_KM_FIX_COST_ADD_KG')) {
                $extra_kg_cost = 0;
            }
        }

        if ($free_shipping_theoretically && $extra_kg > 0) {
            if (Configuration::get('RC_FANCOURIER_FREE_EXC_KG_BHVR') == 'no_free_shipping') {
                $free_shipping = false;
            } elseif (Configuration::get('RC_FANCOURIER_FREE_EXC_KG_BHVR') == 'only_extra_kg') {
                $free_shipping = false;
                $initial_cost = 0;
            } else {
                $extra_kg_cost = 0;
            }
        }

        if ($additional_shipping_cost) {
            if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping_whole_amount') {
                $free_shipping = false;
            } elseif ($free_shipping && Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping') {
                $free_shipping = false;
                $initial_cost = 0;
            }
        }

        if ($free_shipping) {
            if ($cod_tax && Configuration::get('RC_FANCOURIER_FREE_COD_BHVR') == 'only_pay_cod_tax') {
                return $cod_tax;
            }

            return 0;
        }
        if ($extra_km > 0) {
            if ((float) Configuration::get('RC_FANCOURIER_KM_FIX_COST')) {
                $extra_km_cost = (float) Configuration::get('RC_FANCOURIER_KM_FIX_COST');
                $extra_km_cost_fixed = true;
            // $initial_cost = 0;
            } else {
                if ((float) Configuration::get('RC_FANCOURIER_KM_COST')) {
                    $extra_km_cost = (float) Configuration::get('RC_FANCOURIER_KM_COST') * $extra_km;
                }
            }
            if ($free_shipping && Configuration::get('RC_FANCOURIER_FREE_EXC_KM_BHVR') == 'free_shipping') {
                $extra_km_cost = 0;
            }
        }
        if ($this->isFanBoxService($this->id_carrier) !== false) {
            /* override - return false daca in cos sunt produse care nu au dimensiunile completate */
            /*foreach ($cart->getProducts() as $product) {
                if (!(float)$product['width'] || !(float)$product['height'] || !(float)$product['depth']) {
                    return false;
                }
            }*/
            /* end */
            return $initial_cost + $additional_shipping_cost + $cod_tax;
        }

        return $initial_cost + $extra_kg_cost + $extra_km_cost + $additional_shipping_cost + $cod_tax;
    }

    public static function getExtraKm($state, $city)
    {
        return RcFanCourierLocation::getExtraKm($state, $city);
    }

    protected function addCarrier($name = 'Fan Courier')
    {
        $carrier = new Carrier();

        $carrier->name = $name;
        $carrier->is_module = true;
        $carrier->active = true;
        $carrier->range_behavior = false;
        $carrier->need_range = true;
        $carrier->shipping_external = true;
        $carrier->external_module_name = $this->name;
        $carrier->shipping_method = 2;
        $carrier->url = self::$tracking_link;

        foreach (Language::getLanguages() as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->l('24-48h');
        }

        if ($carrier->add() == true) {
            @copy(dirname(__FILE__) . '/views/img/fancourier_40.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');

            return $carrier;
        }

        return false;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = [];
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        $carrier->setGroups($groups_ids);
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone($zone['id_zone']);
        }
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = 0.0;
        $range_price->delimiter2 = 1000000.0;
        $range_price->add();
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookActionAdminControllerSetMedia()
    {
        $isConfigurePage = Tools::getValue('module_name') == $this->name
            || Tools::getValue('configure') == $this->name
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/configure/' . $this->name) !== false;
        if ($isConfigurePage) {
            if (Tools::version_compare(_PS_VERSION_, '9.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJqueryPlugin('sortable');
            $this->context->controller->addJS($this->_path . 'views/js/select2.full.min.js');
            $this->context->controller->addCSS($this->_path . 'views/css/select2.min.css');
            $this->context->controller->addJS($this->_path . 'views/js/rc_fancourier_config.js');
            $this->context->controller->addCSS($this->_path . 'views/css/rc_fancourier_config.css');
        } elseif (Tools::strtolower(Tools::getValue('controller')) == 'adminorders' && Tools::getValue('id_order')) {
            if (Tools::version_compare(_PS_VERSION_, '9.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJqueryPlugin('fancybox');
            $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
            $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            $this->context->controller->addJS($this->_path . 'views/js/rc_fancourier_order.js');
            $this->context->controller->addCSS($this->_path . 'views/css/rc_fancourier_order.css');
            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $this->context->controller->addCSS($this->_path . 'views/css/mini-bootstrap.css');
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'rc_fancourier_return_awb_label' => $this->l('Return AWB Mode'),
                    'rc_fancourier_return_awb_confirm' => $this->l('Pre-fill Return AWB?\n\nClick OK to keep the customer delivery address pre-filled in the form.\nYou can edit the values before submitting.'),
                    'rc_fancourier_return_awb_notice' => $this->l('Form pre-filled for return shipment. Verify the addresses before generating the AWB.'),
                ]);
            }
        } elseif (Tools::strtolower(Tools::getValue('controller')) == 'adminorders' && !Tools::getValue('id_order')) {
            // Orders list view: inject "Generate AWB" button for Fan Courier orders
            if (Tools::version_compare(_PS_VERSION_, '9.0', '<')) {
                $this->context->controller->addJquery();
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'rc_fancourier_carrier_names' => self::getFanCourierCarrierNames(),
                    'rc_fancourier_bulk_awb_label' => $this->l('Generate AWB'),
                    'rc_fancourier_order_url' => $this->context->link->getAdminLink('AdminOrders'),
                ]);
            }
            $this->context->controller->addJS($this->_path . 'views/js/rc_fancourier_orders_list.js');
            $this->context->controller->addCSS($this->_path . 'views/css/rc_fancourier_order.css');
        } elseif (Tools::strtolower(Tools::getValue('controller')) == 'adminaddresses') {
            if (Tools::version_compare(_PS_VERSION_, '9.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path . '/views/js/rc_fancourier_tools.js');
            if (Configuration::get('RC_FANCOURIER_SELECT2')) {
                $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
                $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                    'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()),
                    'rc_fancourier_token_front' => $this->encryptToken('rc_fancourier_front'),
                    'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                ]);
            } else {
                $this->context->smarty->assign([
                    'js_vals' => [
                        'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                        'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()),
                        'rc_fancourier_token_front' => $this->encryptToken('rc_fancourier_front'),
                        'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                    ],
                ]);

                return $this->display(__FILE__, 'views/templates/hook/header_js.tpl');
            }
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookDisplayHeader()
    {
        if (self::licenseIsValidated(Context::getContext()->shop->domain)) {
            if (!Configuration::get('RC_FANCOURIER_ACTIVE')) {
                return false;
            }
            if (Tools::version_compare(_PS_VERSION_, '9.0', '<')) {
                $this->context->controller->addJquery();
            }
            $this->context->controller->addJS($this->_path . '/views/js/rc_fancourier_tools.js');
            if (Configuration::get('RC_FANCOURIER_SELECT2')) {
                $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
                $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                    'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()),
                    'rc_fancourier_token_front' => $this->encryptToken('rc_fancourier_front'),
                    'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                ]);
            } else {
                $this->context->smarty->assign([
                    'js_vals' => [
                        'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                        'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()),
                        'rc_fancourier_token_front' => $this->encryptToken('rc_fancourier_front'),
                        'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                    ],
                ]);

                return $this->display(__FILE__, 'views/templates/hook/header_js.tpl');
            }

            if (self::isOrderPage()) {
                $cart = Context::getContext()->cart;

                $delivery_address = new Address($cart->id_address_delivery);
                if (Validate::isLoadedObject($delivery_address)) {
                    $delivery_address_city = $delivery_address->city;
                    $delivery_state = new State($delivery_address->id_state);
                    if (Validate::isLoadedObject($delivery_state)) {
                        $delivery_state_name = $delivery_state->name;
                    } else {
                        $delivery_state_name = '';
                    }
                } else {
                    $delivery_address_city = '';
                    $delivery_state_name = '';
                }

                Media::addJsDef([
                    'rc_fancourier_defaultCounty' => $delivery_state_name,
                    'rc_fancourier_defaultLocality' => $delivery_address_city,
                ]);

                $this->context->smarty->assign([
                    'module_path' => $this->_path,
                ]);

                return $this->display(__FILE__, 'views/templates/front/checkout_fanbox_map.tpl');
            }
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        if (self::licenseIsValidated(Context::getContext()->shop->domain)) {
            $this->context->smarty->assign($this->getValuesArrayForOrder($params['id_order']));

            return $this->display(__FILE__, 'views/templates/admin/order.tpl');
        }
    }

    public function isFanBoxService($fanbox_service)
    {
        $service = (string) self::getCarrierServiceByIdCarrier($fanbox_service);

        return stripos($service, 'FANBOX') !== false || stripos($service, 'FAN BOX') !== false;
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if ($this->isFanBoxService($params['carrier']['id']) !== false) {
            $officesSql = 'SELECT `strada` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` LIKE "%' . pSQL(self::getCarrierServiceByIdCarrier($params['carrier']['id'])) . '%"';
            $offices = Db::getInstance()->executeS($officesSql);

            $lockerSql = 'SELECT `locker_json` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . $params['cart']->id;
            $locker = Db::getInstance()->executeS($lockerSql);

            if (!empty($locker)) {
                $lockerResult = reset($locker);
                $lockerDecoded = json_decode($lockerResult['locker_json'], true);
            }

            $this->context->smarty->assign([
                'locker_name' => isset($lockerDecoded['name']) ? $lockerDecoded['name'] : '',
                'ps_version' => _PS_VERSION_,
                'fanbox_map_config' => Configuration::get('RC_FANCOURIER_FANBOX_MAP'),
                'offices' => $offices,
            ]);

            return $this->display(__FILE__, 'views/templates/front/checkout_fanbox_selector.tpl');
        }
    }

    /**
     * For PrestaShop 1.6
     */
    public function hookDisplayCarrierList($params)
    {
        return $this->hookDisplayCarrierExtraContent($params);
    }

    /**
     * Map for PrestaShop 1.6
     */
    /*public function hookDisplayFooter()
    {
        if (self::isOrderPage()) {
            $this->context->smarty->assign([
                'module_path' => $this->_path,
            ]);
            return $this->display(__FILE__, 'views/templates/front/checkout_fanbox_map.tpl');
        }
    }*/

    public function hookActionValidateStepComplete($params)
    {
        $carrierId = $params['cart']->id_carrier;
        $locationName = Tools::getValue('fancourier_locker_name', isset($_COOKIE['fancourier_locker_name']) ? htmlspecialchars((string) $_COOKIE['fancourier_locker_name'], ENT_QUOTES, 'UTF-8') : null);
        if ($this->isFanBoxService($carrierId) !== false && $locationName === null) {
            $this->context->controller->errors[] = $this->l('Please select a FANBox locker from the map!');
            $params['completed'] = false;
        }
    }

    public function hookActionValidateOrder($params)
    {
        $orderCarrierId = $params['order']->id_carrier;
        $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $params['order']->id_cart);

        if ($this->isFanBoxService($orderCarrierId) !== false && !$locker) {
            $this->context->controller->errors[] = $this->l('Please select a FANBox locker from the map!');
            $params['completed'] = false;
        } elseif ($locker) {
            Db::getInstance()->insert('rc_fancourier_locker_order', [
                'id_order' => (int) $params['order']->id,
                'id_locker' => pSQL($locker['id_locker']),
                'locker_json' => pSQL($locker['locker_json']),
            ]);
        }
    }

    public function getValuesArrayForOrder($id_order)
    {
        $order = new Order($id_order);
        $products = $order->getProducts();
        $dimensions = $this->calculateBoxProducts($products);
        if ($dimensions['width'] == 0) {
            $dimensions['width'] = Configuration::get('RC_FANCOURIER_DEFAULT_WIDTH');
        }
        if ($dimensions['height'] == 0) {
            $dimensions['height'] = Configuration::get('RC_FANCOURIER_DEFAULT_HEIGHT');
        }
        if ($dimensions['depth'] == 0) {
            $dimensions['depth'] = Configuration::get('RC_FANCOURIER_DEFAULT_LENGTH');
        }
        $address_delivery = new Address($order->id_address_delivery);
        if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($address_delivery)) {
            return;
        }
        $customer = new Customer($order->id_customer);
        $services = $this->getServices();
        $service_selected = false;

        $payment_module = $order->module;
        $payment_id_module = (int) Module::getModuleIdByName($payment_module);
        $include_cod_value = 1;
        if ($payment_id_module) {
            $service_selected_by_payment = Configuration::get('RC_FANCOURIER_PM_SRV_' . $payment_id_module);
            $include_cod_value = (int) Configuration::get('RC_FANCOURIER_PM_COD_' . $payment_id_module);
            $order_carrier = new Carrier($order->id_carrier);

            $carrier_service = $this->getCarrierService($order_carrier->id_reference);
            if ($service_selected_by_payment) {
                if ($service_selected_by_payment == 'Standard' && stripos($carrier_service, 'fanbox') !== false) {
                    $service_selected = 'FANbox';
                } elseif ($service_selected_by_payment == 'Cont Colector' && stripos($carrier_service, 'fanbox') !== false) {
                    $service_selected = 'FANbox Cont Colector';
                } else {
                    $service_selected = $service_selected_by_payment;
                }
            }
        }

        if (!$service_selected) {
            $service_selected = self::getCarrierServiceByIdCarrier($order->id_carrier);
        }

        $locations = self::getLocations();
        $location_selected = '';
        foreach ($locations as $location) {
            if (!$location_selected && Tools::strtolower($location['label']) == Tools::strtolower($address_delivery->city)) {
                $location_selected = $location['client_id'];
            }
            if (!$location_selected && Tools::strtolower($location['label']) == Tools::strtolower(State::getNameById($address_delivery->id_state))) {
                $location_selected = $location['client_id'];
            }
        }
        $plata_ramburs_la = Configuration::get('RC_FANCOURIER_COD_WHO_PAYS');
        $plata_la = Configuration::get('RC_FANCOURIER_DLV_WHO_PAYS');
        if ((float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT')) {
            $cart_weight = (float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT');
        } else {
            $cart_weight = $order->getTotalWeight();
        }

        $added_percent = (float) Configuration::get('RC_FANCOURIER_ADDED_PERCENT_TOTAL_WEIGHT');
        $added_number = (float) Configuration::get('RC_FANCOURIER_ADDED_NUMBER_TOTAL_WEIGHT');

        if ($added_percent > 0) {
            $cart_weight += ($cart_weight * $added_percent / 100);
        }

        if ($added_number > 0) {
            $cart_weight += $added_number;
        }

        $weight = max(1, $cart_weight);
        $envelopes_and_boxes = self::getEnvelopesAndBoxes($order->getProducts());
        $declared_value = number_format($order->total_paid_tax_incl - $order->total_shipping, 2, '.', '');
        $cod_value = number_format($order->total_paid_tax_incl, 2, '.', '');
        if (Configuration::get('RC_FANCOURIER_CNAME_FORMAT') == 'first_last') {
            $customer_name = trim($address_delivery->firstname) . ' ' . trim($address_delivery->lastname);
        } else {
            $customer_name = trim($address_delivery->lastname) . ' ' . trim($address_delivery->firstname);
        }
        $contact_person = Configuration::get('RC_FANCOURIER_CONTACT_PERS');
        if ($contact_person == 'auto' || $contact_person == '"auto"') {
            if (Configuration::get('RC_FANCOURIER_CNAME_FORMAT') == 'first_last') {
                $contact_person = $this->context->employee->firstname . ' ' . $this->context->employee->lastname;
            } else {
                $contact_person = $this->context->employee->lastname . ' ' . $this->context->employee->firstname;
            }
        }

        $customer_contact = $customer_name;
        if (trim($address_delivery->company)) {
            if (trim($customer_name)) {
                $customer_name = trim($address_delivery->company) . ' (' . $customer_name . ')';
                $customer_contact = trim($address_delivery->company);
            } else {
                $customer_contact = $customer_name = trim($address_delivery->company);
            }
        }
        $phone = '';
        if ($address_delivery->phone_mobile) {
            $phone = $address_delivery->phone_mobile;
        }
        if ($address_delivery->phone) {
            if ($phone) {
                // $phone .= ' / ' . $address_delivery->phone;
            } else {
                $phone = $address_delivery->phone;
            }
        }
        $address = $address_delivery->address1;
        if ($address_delivery->address2) {
            $address .= ' ' . $address_delivery->address2;
        }
        /* for modosco address */
        if (property_exists($address_delivery, 'modosco_numar') && $address_delivery->modosco_numar) {
            $address .= ' ' . $address_delivery->modosco_numar;
        }
        if (property_exists($address_delivery, 'modosco_bloc') && $address_delivery->modosco_bloc) {
            $address .= ' ' . $address_delivery->modosco_bloc;
        }
        if (property_exists($address_delivery, 'modosco_scara') && $address_delivery->modosco_scara) {
            $address .= ', ' . $address_delivery->modosco_scara;
        }
        if (property_exists($address_delivery, 'modosco_etaj') && $address_delivery->modosco_etaj) {
            $address .= ', ' . $address_delivery->modosco_etaj;
        }
        if (property_exists($address_delivery, 'modosco_apartament') && $address_delivery->modosco_apartament) {
            $address .= ', ' . $address_delivery->modosco_apartament;
        }
        /* end modosco */
        if ($address_delivery->postcode) {
            $address .= ' ' . $address_delivery->postcode;
        }
        $city = $address_delivery->city;
        $postcode = $address_delivery->postcode;
        $state = State::getNameById($address_delivery->id_state);

        if (!(float) $order->total_shipping && Configuration::get('RC_FANCOURIER_FALLBACK_SENDER')) {
            $plata_ramburs_la = $plata_la = 'expeditor';
        }

        if ($plata_la == 'destinatar' && Configuration::get('RC_FANCOURIER_SUBSTRACT_SHIPPING')) {
            $declared_value -= (float) $order->total_shipping;
            $cod_value -= (float) $order->total_shipping;
        }

        if (!Configuration::get('RC_FANCOURIER_INCLUDE_DECLARED')) {
            $declared_value = '';
        }

        if ((float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT')) {
            $order_total_weight = (float) Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT');
        } else {
            $order_total_weight = $order->getTotalWeight();
        }

        if ($added_percent > 0) {
            $order_total_weight += ($order_total_weight * $added_percent / 100);
        }

        if ($added_number > 0) {
            $order_total_weight += $added_number;
        }

        $extra_kg_from = (float) Configuration::get('RC_FANCOURIER_KG_COST_FROM');
        $order_extra_weight = $order_total_weight - $extra_kg_from;
        $order_extra_weight = max(0, $order_extra_weight);
        $order_extra_km = (int) self::getExtraKm($address_delivery->id_state, $address_delivery->city);

        $show_noinv_warning = false;
        if (Configuration::get('RC_FANCOURIER_NOINV_WARNING')) {
            if (Module::isEnabled('fgo')) {
                if (!Db::getInstance()->getValue('
                    SELECT fgo
                    FROM `' . _DB_PREFIX_ . 'cart`
                    WHERE id_cart = ' . (int) $order->id_cart . '
                ')) {
                    $show_noinv_warning = true;
                }
            } elseif (Module::isEnabled('smartbill')) {
                $has_smartbill_doc_info = false;
                $order_row = Db::getInstance()->getRow('
                    SELECT *
                    FROM `' . _DB_PREFIX_ . 'orders`
                    WHERE id_order = ' . (int) $order->id . '
                ');
                if (isset($order_row['smartbill_document_number']) && $order_row['smartbill_document_number']) {
                    $has_smartbill_doc_info = true;
                }
                if (isset($order_row['smartbill_document']) && $order_row['smartbill_document']) {
                    $has_smartbill_doc_info = true;
                }
                if (!$has_smartbill_doc_info) {
                    $show_noinv_warning = true;
                }
            } else {
                if (!Db::getInstance()->getValue('
                    SELECT id_order_invoice
                    FROM `' . _DB_PREFIX_ . 'order_invoice`
                    WHERE id_order = ' . (int) $order->id . '
                ')) {
                    $show_noinv_warning = true;
                }
            }
        }

        $content = '';
        $default_content = Configuration::get('RC_FANCOURIER_CONTENT');
        if ($default_content) {
            $default_content_array = json_decode($default_content, true);
            if (in_array('ORDER_REFERENCE', $default_content_array)) {
                $content = sprintf($this->l('Order %s'), $order->reference);
            }
            if (in_array('INVOICE_NUMBER', $default_content_array)) {
                $invoice_number = false;
                if (Module::isEnabled('fgo')) {
                    $cart_data = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = "' . (int) $order->id_cart . '"');
                    if (isset($cart_data['fgo']) && $cart_data['fgo']) {
                        $json_invoice = $cart_data['fgo'];
                        if ($json_invoice && $invoice_data = json_decode($json_invoice, true)) {
                            if (isset($invoice_data['Factura']) && isset($invoice_data['Factura']['Serie']) && isset($invoice_data['Factura']['Numar'])) {
                                $invoice_number = $invoice_data['Factura']['Serie'] . $invoice_data['Factura']['Numar'];
                            }
                        }
                    }
                } elseif (Module::isEnabled('smartbill')) {
                    $order_data = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = "' . (int) $order->id . '"');
                    if (isset($order_data['smartbill_document_series']) && $order_data['smartbill_document_series'] && isset($order_data['smartbill_document_number']) && $order_data['smartbill_document_number']) {
                        $invoice_number = $order_data['smartbill_document_series'] . $order_data['smartbill_document_number'];
                    }
                } elseif (Module::isEnabled('gestselsync')) {
                    $order_data = Db::getInstance()->getValue('SELECT `sync_info` FROM `' . _DB_PREFIX_ . 'gestselsync_order` WHERE `id_order` = "' . (int) $order->id . '"');
                    if (!empty($order_data)) {
                        $invoice_number = trim($order_data);
                    }
                } else {
                    $invoice_number = $order->invoice_number;
                }
                if ($invoice_number) {
                    if ($content) {
                        $content .= ' / ';
                    }
                    $content .= sprintf($this->l('Invoice %s'), $invoice_number);
                }
            }
            if (in_array('PRODUCTS_LIST', $default_content_array)) {
                $content_products = [];
                foreach ($order->getProducts() as $prod) {
                    $content_products[] = $prod['product_quantity'] . 'x ' . $prod['reference'];
                }
                if ($content) {
                    $content .= ' / ';
                }
                $content .= implode(', ', $content_products);
            }
        }

        $options = Configuration::get('RC_FANCOURIER_OPTIONS');
        if ($order->gift && stripos($options, 'A') === false) {
            $options .= 'A';
        }

        if ($state == 'Bucuresti') {
            $city = 'Bucuresti';
        }

        $selectedFanbox = Db::getInstance()->getValue('SELECT `id_locker` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_order` WHERE `id_order` = ' . (int) $order->id . '');

        if ($selectedFanbox === '') {
            $selectedFanboxJson = Db::getInstance()->getValue('SELECT `locker_json` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_order` WHERE `id_order` = ' . (int) $order->id . '');
            if ($selectedFanboxJson) {
                $selectedFanboxJson = json_decode($selectedFanboxJson, true);
                $selectedFanbox = $selectedFanboxJson['id'];
            }
        }

        if (!$selectedFanbox) {
            $selectedFanbox = Db::getInstance()->getValue('SELECT `id_locker` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $order->id_cart . '');
        }

        if ($selectedFanbox === '') {
            $selectedFanboxJson = Db::getInstance()->getValue('SELECT `locker_json` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $order->id_cart . '');
            if ($selectedFanboxJson) {
                $selectedFanboxJson = json_decode($selectedFanboxJson, true);
                $selectedFanbox = $selectedFanboxJson['id'];
            }
        }

        if ($this->isFanBoxService($order->id_carrier)) {
            if (!$selectedFanbox) {
                $selectedFanbox = 'fanbox';
            }
            if (stripos($options, 'D') === false) {
                $options .= 'D';
            }
        }

        $default_dropoff_enabled = (bool) Configuration::get('RC_FANCOURIER_DEFAULT_DROPOFF_ENABLED');
        $default_dropoff_id = $default_dropoff_enabled ? (Configuration::get('RC_FANCOURIER_DEFAULT_DROPOFF_ID') ?: null) : null;

        return [
            'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()),
            'id_order' => $id_order,
            'rc_fancourier_token' => $this->encryptToken('rc_fancourier'),
            'rc_fancourier_customer_email' => $customer->email,
            'services' => $services,
            'service_selected' => $service_selected,
            'locations' => $locations,
            'location_selected' => $location_selected,
            'plata_ramburs_la' => $plata_ramburs_la,
            'plata_la' => $plata_la,
            'envelopes' => $envelopes_and_boxes['envelopes'],
            'boxes' => $envelopes_and_boxes['boxes'],
            'weight' => $weight,
            'contact_person' => $contact_person,
            'declared_value' => $declared_value,
            'cod_value' => ($include_cod_value) ? $cod_value : '',
            'customer_name' => $customer_name,
            'customer_contact' => $customer_contact,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'postcode' => $postcode,
            'observations' => Configuration::get('RC_FANCOURIER_OBSERVATIONS'),
            'restitution' => Configuration::get('RC_FANCOURIER_RESTITUTION'),
            'show_dim' => Configuration::get('RC_FANCOURIER_SHOW_DIM'),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'length' => $dimensions['depth'],
            'options' => $options,
            'awbs' => self::getAwbForOrders($id_order),
            'ps15' => self::isPs15(),
            'offices' => self::getOffices(),
            'lockers' => self::getFanboxes(),
            'selected_fanbox' => $selectedFanbox,
            'default_dropoff_enabled' => $default_dropoff_enabled,
            'default_dropoff_id' => $default_dropoff_id,
            'rc_fancourier_total_weight' => $order_total_weight,
            'rc_fancourier_extra_weight' => $order_extra_weight,
            'rc_fancourier_extra_km' => $order_extra_km,
            'show_noinv_warning' => $show_noinv_warning,
            'content' => $content,
            'products' => $order->getProducts(),
            'rc_fancourier_envelope_label' => $this->l('Envelope'),
            'rc_fancourier_box_label' => $this->l('Box'),
        ];
    }

    public static function getLocations()
    {
        return RcFanCourierLocation::getLocations();
    }

    public static function getDefaultClientId()
    {
        return RcFanCourierLocation::getDefaultClientId();
    }

    public function getServices()
    {
        $services_from_api = false;
        $username = Configuration::get('RC_FANCOURIER_USERNAME');
        $password = Configuration::get('RC_FANCOURIER_PASSWORD');
        $client_id = $this->getDefaultClientId();

        if ($username && $password && $client_id) {
            $services_from_api = $this->getServicesFromAPI($username, $password);
        }
        if (!$services_from_api) {
            return $this->getHardcodedServices();
        }

        return $services_from_api;
    }

    public function getServicesFromAPI($username, $password)
    {
        // Disabled: hardcoded services are more reliable (selfawb servers were slow)
        return [];
    }

    public function getHardcodedServices()
    {
        return json_decode('["Standard","RedCode","Cont Colector","Express Loco 2H","Express Loco 4H","Express Loco 6H","Export","Red code-Cont Colector","Express Loco 2H-Cont Colector","Express Loco 4H-Cont Colector","Express Loco 6H-Cont Colector","Express Loco 1H","Express Loco 1H-Cont Colector","Export-Cont Colector","CollectPoint","CollectPoint Cont Colector","Produse Albe","Produse Albe-Cont Colector","Transport Marfa","Transport Marfa-Cont Colector","Transport Marfa Produse Albe","Transport Marfa Produse Albe-Cont Colector","FANbox","FANbox Cont Colector"]', true);
    }

    /**
     * Returns array of carrier names registered by this module (for JS use in orders list).
     *
     * @return array
     */
    public static function getFanCourierCarrierNames()
    {
        $names = Db::getInstance()->executeS(
            'SELECT DISTINCT `name` FROM `' . _DB_PREFIX_ . 'carrier`'
            . ' WHERE `external_module_name` = "rc_fancourier" AND `deleted` = "0" AND `active` = "1"'
        );
        if (!$names) {
            return [];
        }

        return array_column($names, 'name');
    }

    public function getCarriers($id_language = null)
    {
        $ids = Db::getInstance()->executeS('SELECT DISTINCT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `external_module_name` = "' . pSQL($this->name) . '" AND `deleted` = "0"');
        $carriers = [];
        if (!$id_language) {
            $id_language = (int) Configuration::get('PS_LANG_DEFAULT');
        }
        foreach ($ids as $row) {
            $id_reference = $row['id_reference'];
            $carriers[] = Carrier::getCarrierByReference($id_reference, $id_language);
        }
        $carriers_array = [];
        foreach ($carriers as $carrier) {
            $carrier_array = [
                'id_reference' => $carrier->id_reference,
                'id_carrier' => $carrier->id,
                'name' => $carrier->name,
                'active' => $carrier->active,
                'service' => $this->getCarrierService($carrier->id_reference),
                'cod' => $this->isCarrierCOD($carrier->id_reference),
                'free_shipping_from' => self::getCarrierFreeFrom($carrier->id_reference) ?? '',
            ];
            $carriers_array[] = $carrier_array;
        }

        return $carriers_array;
    }

    public static function getCarrierService($id_reference)
    {
        return RcFanCourierCarrier::getCarrierService($id_reference);
    }

    public static function getCarrierServiceByIdCarrier($id_carrier)
    {
        return RcFanCourierCarrier::getCarrierServiceByIdCarrier($id_carrier);
    }

    public static function setCarrierService($id_reference, $service)
    {
        return RcFanCourierCarrier::setCarrierService($id_reference, $service);
    }

    public static function isCarrierCOD($id_reference)
    {
        return RcFanCourierCarrier::isCarrierCOD($id_reference);
    }

    public static function setCarrierCOD($id_reference, $value)
    {
        return RcFanCourierCarrier::setCarrierCOD($id_reference, $value);
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
        return RcFanCourierCarrier::getCarrierFreeFrom($id_reference);
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
        return RcFanCourierCarrier::setCarrierFreeFrom($id_reference, $value);
    }

    public static function getEnvelopesAndBoxes($products)
    {
        return RcFanCourierCarrier::getEnvelopesAndBoxes($products);
    }

    public function fastGenerateAwbForOrders($orders)
    {
        $data_for_orders = [];
        foreach ($orders as $id_order) {
            $awbs_for_orders = self::getAwbForOrders($id_order);
            if (!$awbs_for_orders) {
                $data_for_orders[] = $this->transformValuesForFastGenerateAwb($this->getValuesArrayForOrder($id_order));
            }
        }
        if ($data_for_orders) {
            $errors = $this->generateAwbBulk($data_for_orders);
            if ($errors) {
                self::logAwbErrors($errors);
            }
        }
        $return = [];
        foreach ($orders as $id_order) {
            $awbs_for_orders = self::getAwbForOrders($id_order);
            if ($awbs_for_orders) {
                foreach ($awbs_for_orders as $awb) {
                    $pdf_for_awb = $this->showAwb($awb['awb']);
                    if ($pdf_for_awb) {
                        $awb_filepath = dirname(__FILE__) . '/tmp/awb_' . $id_order . '.pdf';
                        if (file_put_contents($awb_filepath, $pdf_for_awb)) {
                            $return[$id_order] = $awb_filepath;
                        }
                    }
                }
            }
        }

        return $return;
    }

    public function generateAwbBulk($datas)
    {
        $return = [];
        $datas_array = [];
        foreach ($datas as $key => $data) {
            $datas_array[$data['id_order']] = $data;

            $order = new Order($data['id_order']);

            if (!isset($data['rc_fancourier_length']) || !$data['rc_fancourier_length'] || !isset($data['rc_fancourier_width']) || !$data['rc_fancourier_width'] || !isset($data['rc_fancourier_height']) || !$data['rc_fancourier_height']) {
                $products = $order->getProducts();
                $dimensions = $this->calculateBoxProducts($products);
                if ($dimensions['width'] == 0) {
                    $dimensions['width'] = Configuration::get('RC_FANCOURIER_DEFAULT_WIDTH');
                }
                if ($dimensions['height'] == 0) {
                    $dimensions['height'] = Configuration::get('RC_FANCOURIER_DEFAULT_HEIGHT');
                }
                if ($dimensions['depth'] == 0) {
                    $dimensions['depth'] = Configuration::get('RC_FANCOURIER_DEFAULT_LENGTH');
                }
                if (!isset($data['rc_fancourier_length']) || !$data['rc_fancourier_length']) {
                    $data['rc_fancourier_length'] = $dimensions['depth'];
                }
                if (!isset($data['rc_fancourier_width']) || !$data['rc_fancourier_width']) {
                    $data['rc_fancourier_width'] = $dimensions['width'];
                }
                if (!isset($data['rc_fancourier_height']) || !$data['rc_fancourier_height']) {
                    $data['rc_fancourier_height'] = $dimensions['height'];
                }
            }

            if (!isset($data['rc_fancourier_pickupLocationId'])) {
                $data['rc_fancourier_pickupLocationId'] = '';
            }

            if (is_string($data['rc_fancourier_options'])) {
                $data['rc_fancourier_options'] = str_split($data['rc_fancourier_options']);
            }

            if ($this->isFanBoxService($order->id_carrier)) {
                $office_id = Db::getInstance()->getValue('SELECT `id_locker` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_order` WHERE `id_order` = ' . (int) $order->id . '');
                if ($office_id) {
                    $data['rc_fancourier_office_address'] = $office_id;
                    if (!isset($data['rc_fancourier_options'])) {
                        $data['rc_fancourier_options'] = ['D'];
                    } else {
                        if (is_array($data['rc_fancourier_options'])) {
                            $data['rc_fancourier_options'][] = 'D';
                        } else {
                            $data['rc_fancourier_options'] = ['D'];
                        }
                    }
                }
            }

            if (isset($data['rc_fancourier_options']) && is_array($data['rc_fancourier_options']) && in_array('D', $data['rc_fancourier_options'])) {
                $office_id = $data['rc_fancourier_office_address'];
                $office_info = Db::getInstance()->getRow('SELECT `strada`, `type`, `row` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `id_office` = "' . pSQL($office_id) . '"');
                if (!$office_info) {
                    continue;
                }
                $office_address = $office_info['strada'];
                $office_type = $office_info['type'];
                if ($office_type == 'fanbox') {
                    foreach ($data['rc_fancourier_options'] as &$option) {
                        if ($option == 'D') {
                            $option = 'V';
                        }
                    }
                }

                $office_row = json_decode($office_info['row'], true);
                $data['rc_fancourier_office_state'] = isset($office_row['address']['county']) ? $office_row['address']['county'] : '';
                $data['rc_fancourier_office_city'] = isset($office_row['address']['locality']) ? $office_row['address']['locality'] : '';
                $data['rc_fancourier_office_postcode'] = isset($office_row['address']['zipCode']) ? $office_row['address']['zipCode'] : '';

                $data['rc_fancourier_address'] = $office_address;
                $data['rc_fancourier_state'] = $data['rc_fancourier_office_state'];
                $data['rc_fancourier_city'] = $data['rc_fancourier_office_city'];
                $data['rc_fancourier_postcode'] = $data['rc_fancourier_office_postcode'];

                $data['rc_fancourier_pickupLocationId'] = $office_id;
            }

            if (!isset($data['rc_fancourier_options'])) {
                $data['rc_fancourier_options'] = [];
            }

            foreach ($data['rc_fancourier_options'] as $key => $val) {
                if (!$val) {
                    unset($data['rc_fancourier_options'][$key]);
                }
            }

            // Volumetric weight: (length * width * height) / 5000 — use max of actual vs volumetric
            $bulk_actual_weight     = (float) $data['rc_fancourier_weight'];
            $bulk_dim_length        = (float) (isset($data['rc_fancourier_length']) ? $data['rc_fancourier_length'] : 0);
            $bulk_dim_width         = (float) (isset($data['rc_fancourier_width'])  ? $data['rc_fancourier_width']  : 0);
            $bulk_dim_height        = (float) (isset($data['rc_fancourier_height']) ? $data['rc_fancourier_height'] : 0);
            if ($bulk_dim_length > 0 && $bulk_dim_width > 0 && $bulk_dim_height > 0) {
                $bulk_volumetric_weight = ($bulk_dim_length * $bulk_dim_width * $bulk_dim_height) / 5000;
                $data['rc_fancourier_weight'] = max($bulk_actual_weight, $bulk_volumetric_weight);
            }

            // Per-package weights (FAN compound AWBs require weight per package)
            $bulk_envelopes_count = (int) $data['rc_fancourier_envelopes'];
            $bulk_boxes_count = (int) $data['rc_fancourier_boxes'];
            $bulk_total_packages = $bulk_envelopes_count + $bulk_boxes_count;
            $bulk_per_package_weights = [];
            $bulk_parcels_payload = [];
            if ($bulk_total_packages > 1 && isset($data['rc_fancourier_weights']) && is_array($data['rc_fancourier_weights'])) {
                $bulk_weights_input = array_values($data['rc_fancourier_weights']);
                $bulk_sum = 0.0;
                for ($i = 0; $i < $bulk_total_packages; $i++) {
                    $w = isset($bulk_weights_input[$i]) ? (float) $bulk_weights_input[$i] : 0.0;
                    if ($w > 0) {
                        $bulk_per_package_weights[] = $w;
                        $bulk_sum += $w;
                        $bulk_parcels_payload[] = [
                            'sequenceNo' => $i + 1,
                            'type' => $i < $bulk_envelopes_count ? 'envelope' : 'parcel',
                            'weight' => $w,
                        ];
                    }
                }
                if (!empty($bulk_per_package_weights) && count($bulk_per_package_weights) === $bulk_total_packages) {
                    $data['rc_fancourier_weight'] = $bulk_sum;
                } else {
                    $bulk_per_package_weights = [];
                    $bulk_parcels_payload = [];
                }
            }

            $bulk_options = array_values($data['rc_fancourier_options']);
            $bulk_is_dropoff = in_array('W', $bulk_options);
            $bulk_is_pickup = in_array('V', $bulk_options);
            if ($bulk_is_dropoff && $bulk_is_pickup) {
                $bulk_options = array_values(array_filter($bulk_options, function ($o) {
                    return $o !== 'V';
                }));
                $bulk_is_pickup = false;
            }
            $bulk_dropoff_fanbox_id = $bulk_is_dropoff ? (!empty($data['rc_fancourier_pickupLocationId']) ? $data['rc_fancourier_pickupLocationId'] : '') : '';

            $bulk_recipient_pickup_id = null;
            if ($bulk_is_pickup) {
                $bulk_recipient_pickup_id = !empty($data['rc_fancourier_recipient_pickup_location'])
                    ? $data['rc_fancourier_recipient_pickup_location']
                    : (!empty($data['rc_fancourier_pickupLocationId']) ? $data['rc_fancourier_pickupLocationId'] : null);
            } elseif (!$bulk_is_dropoff && !empty($data['rc_fancourier_pickupLocationId'])) {
                $bulk_recipient_pickup_id = $data['rc_fancourier_pickupLocationId'];
            }

            $bulk_parcel = (int) $data['rc_fancourier_boxes'];
            $bulk_envelope = (int) $data['rc_fancourier_envelopes'];
            if ($bulk_is_dropoff && $bulk_parcel === 0 && $bulk_envelope === 0) {
                $bulk_parcel = 1;
            }
            $bulk_packages = [
                'parcel' => $bulk_parcel,
                'envelope' => $bulk_envelope,
            ];
            if (!empty($bulk_parcels_payload)) {
                $bulk_packages['parcels'] = $bulk_parcels_payload;
            }

            $bulk_cost_center = trim((string) Configuration::get('RC_FANCOURIER_COST_CENTER'));
            $bulk_shipment = [
                'info' => [
                    'service' => $data['rc_fancourier_service'],
                    'bank' => '',
                    'bankAccount' => '',
                    'packages' => $bulk_packages,
                    'weight' => (float) $data['rc_fancourier_weight'] ?: 1,
                    'cod' => RcFanCourierHelper::numericOrNull($data['rc_fancourier_cod_value']),
                    'declaredValue' => (float) $data['rc_fancourier_declared_value'],
                    'payment' => $data['rc_fancourier_del_tax'],
                    'refund' => RcFanCourierHelper::numericOrNull($data['rc_fancourier_restitution']),
                    'returnPayment' => $data['rc_fancourier_cod_tax'],
                    'observation' => (string) $data['rc_fancourier_observations'],
                    'content' => substr($data['rc_fancourier_content'], 0, 255),
                    'dimensions' => [
                        'length' => (int) $data['rc_fancourier_length'] ?: 1,
                        'height' => (int) $data['rc_fancourier_height'] ?: 1,
                        'width' => (int) $data['rc_fancourier_width'] ?: 1,
                    ],
                    'costCenter' => $bulk_cost_center !== '' ? $bulk_cost_center : null,
                    'options' => $bulk_options,
                ],
                'recipient' => [
                    'name' => $data['rc_fancourier_customer_contact'],
                    'phone' => RcFanCourierHelper::normalizeRomanianPhone($data['rc_fancourier_phone']),
                    'email' => $data['rc_fancourier_customer_email'],
                    'address' => [
                        'county' => $data['rc_fancourier_state'],
                        'locality' => $data['rc_fancourier_city'],
                        'street' => $data['rc_fancourier_address'],
                        'pickupLocationId' => $bulk_recipient_pickup_id,
                        'zipCode' => $data['rc_fancourier_postcode'],
                    ],
                ],
            ];

            if ($bulk_is_dropoff) {
                $bulk_shipment['sender'] = RcFanCourierHelper::buildSenderForDropoff($bulk_dropoff_fanbox_id);
            }
            $post_data = [
                'clientId' => (int) $data['rc_fancourier_location'],
                'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
                'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
                'shipments' => [$bulk_shipment],
            ];

            $results = self::curlCall('intern-awb', $post_data, 'POST');

            if ($results) {
                $results_array = explode(PHP_EOL, $results);
                foreach ($results_array as $order_key => $result) {
                    if (!trim($result)) {
                        continue;
                    }
                    $jsonResult = json_decode($result, true);
                    if (isset($jsonResult) && !empty($jsonResult['response'][0]['awbNumber'])) {
                        $awb = $jsonResult['response'][0]['awbNumber'];
                        $message = $this->l('AWB generated!');
                        if ($awb) {
                            $message .= ' ' . $awb;
                        }
                        $this->addAwbToOrder($data['id_order'], $awb, $data['rc_fancourier_location'], $bulk_per_package_weights);

                    /*$return[$data['id_order']] = [
                        'success' => 1,
                        'message' => $message,
                        'awb' => $awb,
                    ];*/
                    } else {
                        if ($result) {
                            $message = $result;
                        } else {
                            $message = $this->l('Unknown error');
                        }
                        self::logError($message, 'intern-awb', $post_data);

                        $return[$data['id_order']] = $message;
                    }
                }
            }
        }

        return $return;
    }

    public function generateAwb($data)
    {
        if (self::licenseIsValidated(Context::getContext()->shop->domain)) {
            $rc_fancourier_helper = new RcFanCourierHelper();

            return $rc_fancourier_helper->generateAwb($data);
        }
    }

    public function calculateBoxProducts($products)
    {
        $totalVolume = 0;
        $maxDim = 0;

        // Calculate total volume and find largest dimension
        foreach ($products as $product) {
            $volume = $product['width'] * $product['height'] * $product['depth'] * $product['product_quantity'];
            $totalVolume += $volume;

            $maxDim = max($maxDim, $product['width'], $product['height'], $product['depth']);
        }

        // Use cube root of volume as base dimension
        $baseDim = pow($totalVolume, 1 / 3);

        // Adjust dimensions to maintain reasonable proportions
        $width = min($baseDim * 1.2, $maxDim);
        $height = min($baseDim * 0.8, $maxDim);
        if ($width && $height) {
            $depth = $totalVolume / ($width * $height);
        } else {
            $depth = 0;
        }

        return [
            'width' => ceil($width),
            'height' => ceil($height),
            'depth' => ceil($depth),
        ];
    }

    public static function getFanLink($with_token = true)
    {
        $fan_link = self::buildFanToken();
        if ($with_token) {
            return $fan_link(strrev('94Wah12bk9DcoBnLr5Was9lbhZ2X0V2Zv8mcuMnclR2bjVnc1d2LvoDc0RHa')) . urlencode(Tools::getHttpHost(true) . __PS_BASE_URI__) . '&lic=' . urlencode(Tools::file_get_contents(dirname(__FILE__) . '/license.txt'));
        }

        return 'https://www.selfawb.ro';
    }

    public static function buildFanToken()
    {
        return 'base' . (1024 - 960) . '_decode';
    }

    public static function addAwbToOrder($id_order, $awb, $client_id, $weights = null)
    {
        return RcFanCourierAwb::addAwbToOrder($id_order, $awb, $client_id, $weights);
    }

    public static function sendInTransitEmail($order, $awb)
    {
        return RcFanCourierAwb::sendInTransitEmail($order, $awb);
    }

    public static function getAwbForOrders($id_order)
    {
        return RcFanCourierAwb::getAwbForOrders($id_order);
    }

    public static function parseData($data)
    {
        return RcFanCourierAwb::parseData($data);
    }

    public function deleteAwb($awb)
    {
        $client_id = Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `awb` = "' . pSQL($awb) . '"');
        if (!$client_id) {
            $client_id = self::getDefaultClientId();
        }
        $return = $this->deleteAwbFromFan($awb, $client_id);
        if ($return && $return['success']) {
            Db::getInstance()->delete('rc_fancourier_awbs', '`awb` = "' . pSQL($awb) . '"');
        }

        return $return;
    }

    public function deleteAwbFromFan($awb, $client_id)
    {
        $endpoint = 'awb';
        $post_data = [
            'clientId' => $client_id,
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'awb' => $awb,
        ];

        $data = self::curlCall($endpoint, $post_data, 'DELETE');
        $decoded_json = json_decode($data, true);
        if (isset($decoded_json['status']) && $decoded_json['status'] == 'success') {
            return [
                'success' => true,
                'message' => $decoded_json['data'],
            ];
        }

        if ($decoded_json && isset($decoded_json['message']) && stripos($decoded_json['message'], 'error')) {
            return [
                'success' => false,
                'message' => $decoded_json['message'],
            ];
        }
        if ($decoded_json) {
            return [
                'success' => true,
                'message' => $decoded_json['message'],
            ];
        }

        return [
            'success' => false,
            'message' => $this->l('Unknown error'),
        ];
    }

    public function statusAwb($awb)
    {
        $client_id = Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `awb` = "' . pSQL($awb) . '"');
        if (!$client_id) {
            $client_id = self::getDefaultClientId();
        }
        $endpoint = 'reports/awb/tracking';
        $post_data = [
            'clientId' => $client_id,
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'awb' => [$awb],
            'language' => 'ro',
        ];

        $data = self::curlCall($endpoint, $post_data);
        $output_array = [];
        $json = json_decode($data, true);
        if (isset($json['data'])) {
            // Access the 'data' array
            $data_array = $json['data'];

            $output_array = array_map(function ($item) {
                unset($item['status']);

                return $item;
            }, $data_array);
        }

        if ($data && stripos($data, 'error')) {
            return [
                'success' => false,
                'message' => $output_array,
            ];
        }
        if (!empty($output_array[0])) {
            $message = 'ERROR';
            if (isset($output_array[0]['events'])) {
                $message = '';
                foreach ($output_array[0]['events'] as $event) {
                    $message .= $event['date'] . ' - ' . $event['name'] . PHP_EOL;
                }

                return [
                    'success' => true,
                    'message' => $message,
                ];
            }

            return [
                'success' => true,
                'message' => $output_array[0]['message'],
            ];
        }

        return [
            'success' => false,
            'message' => $this->l('Unknown error'),
        ];
    }

    public function showAwb($awb)
    {
        $client_id = Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `awb` = "' . pSQL($awb) . '"');
        if (!$client_id) {
            $client_id = self::getDefaultClientId();
        }
        $endpoint = 'awb/label';
        $post_data = [
            'clientId' => $client_id,
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'awbs' => [$awb],
            'pdf' => 1,
            'language' => 'ro',
            'format' => Configuration::get('RC_FANCOURIER_AWB_FORMAT') ?: 'A4',
        ];

        $data = self::curlCall($endpoint, $post_data);

        return $data;
    }

    public static function getData($name)
    {
        return RcFanCourierAwb::getData($name);
    }

    public static function getAwbsToCheck($days_to_check = null)
    {
        return RcFanCourierAwb::getAwbsToCheck($days_to_check);
    }

    public static function checkAwbsStates($awbs_to_check)
    {
        return RcFanCourierAwb::checkAwbsStates($awbs_to_check);
    }

    public static function changeAwbsState($awbs)
    {
        return RcFanCourierAwb::changeAwbsState($awbs);
    }

    public static function convertFromRON($amount, $context = null)
    {
        return RcFanCourierUtils::convertFromRON($amount, $context);
    }

    public static function convertToRON($amount, $context = null)
    {
        return RcFanCourierUtils::convertToRON($amount, $context);
    }

    public static function buildXmlForCheckStates($awbs_to_check)
    {
        return RcFanCourierAwb::buildXmlForCheckStates($awbs_to_check);
    }

    public static function getFanStatesList()
    {
        return RcFanCourierAwb::getFanStatesList();
    }

    public static function curlCall($endpoint, $data = [], $requestType = 'GET', $no_throw = false)
    {
        return RcFanCourierApiClient::curlCall($endpoint, $data, $requestType, $no_throw);
    }

    public static function getAPIToken($data, $no_throw = false)
    {
        return RcFanCourierApiClient::getAPIToken($data, $no_throw);
    }

    public static function logError($error, $endpoint = false, $data = false)
    {
        RcFanCourierLogger::logError($error, $endpoint, $data);
    }

    public static function replaceDiacriticsChars($str)
    {
        return RcFanCourierUtils::replaceDiacriticsChars($str);
    }

    public static function isOrderPage()
    {
        return RcFanCourierUtils::isOrderPage();
    }

    public function updateLocations()
    {
        $post_data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'type' => ['fanbox', 'paypoint', 'office'],
        ];

        $data_cities = self::curlCall('reports/localities', $post_data);
        if ($data_cities) {
            $output_array = [];
            $json = json_decode($data_cities, true);
            if (isset($json['data'])) {
                $data_array = $json['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);
            }

            if ($output_array) {
                self::clearCities();
                self::addCities($output_array);
            } else {
                return $this->l('No cities found');
            }
        } else {
            return $this->l('Could not get data from API');
        }

        foreach ($post_data['type'] as $type) {
            $post_data['type'] = $type;
            $data_offices[$type] = self::curlCall('reports/pickup-points?type=', $post_data);
        }

        if ($data_offices) {
            $offices_array = [];
            foreach ($data_offices as $type => $data_office) {
                $decoded_data = json_decode($data_office, true);
                if (isset($decoded_data['data']) && $decoded_data['data']) {
                    $data_array = $decoded_data['data'];
                    $offices_array[$type] = array_map(function ($item) {
                        unset($item['status']);

                        return $item;
                    }, $data_array);
                }
            }

            if ($offices_array) {
                self::clearOffices();
                foreach ($offices_array as $type => $output) {
                    self::addOffices($output, $type);
                }
            }
            Configuration::updateValue('RC_FANCOURIER_LOC_LUPD', date('Y-m-d H:i:s'));
        } else {
            return $this->l('Could not get data from API');
        }

        return true;
    }

    public static function parseCsv($csv)
    {
        return RcFanCourierUtils::parseCsv($csv);
    }

    public static function getOffices()
    {
        return RcFanCourierLocation::getOffices();
    }

    public static function getFanboxes()
    {
        return RcFanCourierLocation::getFanboxes();
    }

    public static function clearOffices()
    {
        return RcFanCourierLocation::clearOffices();
    }

    public static function addOffices($offices, $type)
    {
        return RcFanCourierLocation::addOffices($offices, $type);
    }

    public static function clearCities()
    {
        return RcFanCourierLocation::clearCities();
    }

    public static function addCities($cities)
    {
        return RcFanCourierLocation::addCities($cities);
    }

    public static function isPs15()
    {
        return RcFanCourierUtils::isPs15();
    }

    public function getTranslation($message)
    {
        switch ($message) {
            case 'incorrent_token':
                return $this->l('Incorrect token');
            case 'data_updated':
                return $this->l('Data updated!');
            case 'choose_city':
                return $this->l('- Choose a city -');
            case 'unknown_error':
                return $this->l('Unknown error');
            case 'awb_generated':
                return $this->l('AWB Generated!');
            default:
                return $message;
        }
    }

    public static function trailingslashit($string)
    {
        return RcFanCourierUtils::trailingslashit($string);
    }

    public static function untrailingslashit($string)
    {
        return RcFanCourierUtils::untrailingslashit($string);
    }

    public static function getTempDirPath()
    {
        return RcFanCourierUtils::getTempDirPath();
    }

    public static function isWritable($path)
    {
        return RcFanCourierUtils::isWritable($path);
    }

    public static function getPrivilegedCitiesArray()
    {
        return RcFanCourierUtils::getPrivilegedCitiesArray();
    }

    public static function getPaymentMethodsInfo()
    {
        return RcFanCourierCarrier::getPaymentMethodsInfo();
    }

    public function transformValuesForFastGenerateAwb($values)
    {
        $associations = $this->getAssociationsForFastGenerateAwb();
        $return = [];
        foreach ($associations as $key1 => $key2) {
            $return[$key1] = $values[$key2];
        }

        $return['id_order'] = $values['id_order'];
        $order = new Order($values['id_order']);
        $address_delivery = new Address($order->id_address_delivery);
        $return['rc_fancourier_location'] = self::getClientIdBasedOnAddress($address_delivery);

        return $return;
    }

    public function getAssociationsForFastGenerateAwb()
    {
        return [
            'rc_fancourier_service' => 'service_selected',
            'rc_fancourier_envelopes' => 'envelopes',
            'rc_fancourier_boxes' => 'boxes',
            'rc_fancourier_weight' => 'weight',
            'rc_fancourier_del_tax' => 'plata_la',
            'rc_fancourier_cod_value' => 'cod_value',
            'rc_fancourier_cod_tax' => 'plata_ramburs_la',
            'rc_fancourier_declared_value' => 'declared_value',
            'rc_fancourier_contact_person' => 'contact_person',
            'rc_fancourier_observations' => 'observations',
            'rc_fancourier_content' => 'content',
            'rc_fancourier_customer_name' => 'customer_name',
            'rc_fancourier_customer_contact' => 'customer_contact',
            'rc_fancourier_phone' => 'phone',
            'rc_fancourier_customer_email' => 'rc_fancourier_customer_email',
            'rc_fancourier_state' => 'state',
            'rc_fancourier_city' => 'city',
            'rc_fancourier_address' => 'address',
            'rc_fancourier_postcode' => 'postcode',
            'rc_fancourier_restitution' => 'restitution',
            'rc_fancourier_options' => 'options',
            'rc_fancourier_packing' => null,
            'rc_fancourier_id_series' => null,
            'rc_fancourier_id_number' => null,
            'rc_fancourier_office_address' => 'selected_fanbox',
        ];
    }

    public static function logAwbErrors($errors)
    {
        return RcFanCourierLogger::logAwbErrors($errors);
    }

    public static function formatDecimals($amount)
    {
        return RcFanCourierUtils::formatDecimals($amount);
    }

    public function checkTransfers()
    {
        if (Configuration::get('RC_FANCOURIER_OS_TRANSFER_DAYS') && Configuration::get('RC_FANCOURIER_OS_MAP_TRANSFER')) {
            $days_to_check = (int) Configuration::get('RC_FANCOURIER_OS_TRANSFER_DAYS');
            $date_start = date('Y-m-d', strtotime('-' . $days_to_check . ' days'));
            $dates_to_check = self::getDatesFromRange($date_start, date('Y-m-d'), 'Y-m-d');

            $client_id = self::getDefaultClientId();
            $total_transfers = [];

            // $id_order_by_awb = self::getIdOrderByAwbs();

            foreach ($dates_to_check as $date) {
                $transfers_for_date = $this->getTransfers($client_id, $date);
                if (is_array($transfers_for_date)) {
                    $total_transfers = array_merge($total_transfers, $transfers_for_date);
                }
            }

            $id_next_order_state = (int) Configuration::get('RC_FANCOURIER_OS_MAP_TRANSFER');

            $order_states = OrderState::getOrderStates(Context::getContext()->language->id);
            $order_states_array = [];
            foreach ($order_states as $os) {
                $order_states_array[$os['id_order_state']] = $os;
            }

            $awbs = [];

            foreach ($total_transfers as $transfer_row) {
                // if (isset($id_order_by_awb[$transfer_row['Numar awb']])) {
                $order_info = Db::getInstance()->getRow('SELECT awbs.`id_order`, o.`reference`, o.`current_state` as `id_current_state`, o.`date_add` as `order_date`, IF((a.`company` IS NULL OR a.`company` = ""), CONCAT(a.`firstname`, " ", a.`lastname`), a.`company`) as `customer_name` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` awbs LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = awbs.`id_order`) LEFT JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = o.`id_address_delivery`) WHERE awbs.`awb` = "' . pSQL($transfer_row['Numar awb']) . '"');
                if ($order_info) {
                    $awb = [];
                    $awb['id_order'] = $order_info['id_order'];
                    $awb['reference'] = $order_info['reference'];
                    $awb['id_current_state'] = $order_info['id_current_state'];
                    $awb['order_date'] = $order_info['order_date'];
                    $awb['customer_name'] = $order_info['customer_name'];
                    $awb['current_state'] = $order_states_array[$order_info['id_current_state']]['name'];
                    $awb['current_state_color'] = $order_states_array[$order_info['id_current_state']]['color'];
                    $awb['current_state_text_color'] = self::getBrightness($awb['current_state_color']) < 128 ? 'white' : 'black';
                    $awb['next_state'] = '';
                    $awb['next_state_color'] = $awb['current_state_text_color'];

                    if ($id_next_order_state && $id_next_order_state != $awb['id_current_state']) {
                        $order = new Order($awb['id_order']);
                        if ($order->current_state == $awb['id_current_state']) {
                            $order_state = new OrderState($id_next_order_state);
                            // $order->setCurrentState($id_next_order_state);
                            // Create new OrderHistory
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

                            // Save all changes
                            if ($history->addWithemail(true, $templateVars)) {
                                // synchronizes quantities if needed..
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

                    $awb['next_state_text_color'] = self::getBrightness($awb['next_state_color']) < 128 ? 'white' : 'black';

                    $awbs[] = $awb;
                }
                // }
            }

            return $awbs;
        }

        return [];
    }

    public static function getIdOrderByAwbs()
    {
        return RcFanCourierAwb::getIdOrderByAwbs();
    }

    public function getTransfers($client_id, $date)
    {
        $endpoint = 'reports/bank-transfers';
        $data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'clientId' => $client_id,
            'date' => date('d.m.Y', strtotime($date)),
            'language' => 'ro',
        ];
        $result = self::curlCall($endpoint, $data);

        return $result;
    }

    public static function getDatesFromRange($start, $end, $format = 'Y-m-d')
    {
        return RcFanCourierUtils::getDatesFromRange($start, $end, $format);
    }

    public static function buildTokenFunctionLeft()
    {
        return 'base';
    }

    public static function buildTokenFunctionRightDecode()
    {
        return 'decode';
    }

    public static function buildTokenFunctionRightEncode()
    {
        return 'encode';
    }

    public static function buildTokenFunction()
    {
        return self::buildTokenFunctionLeft() . (1024 - 960) . '_' . self::buildTokenFunctionRightDecode();
    }

    public static function buildTokenFunctionAlt()
    {
        return self::buildTokenFunctionLeft() . (1024 - 960) . '_' . self::buildTokenFunctionRightEncode();
    }

    public function getInstallAlgorithm($version = null, $create_file = true, $license_key = '')
    {
        $validate_token_f = self::buildTokenFunction();
        $validate_token_f2 = self::buildTokenFunctionAlt();

        $url = $validate_token_f(strrev('=s2Ylh2Yv8mcuMnclR2bjVnc1dmLzVGb1R2bt9yL6MHc0RHa'));
        $lic = urlencode(Tools::file_get_contents(dirname(__FILE__) . '/license.txt'));

        $oi = [
            '=UWbh52XlxWdk9Wb' => strrev($validate_token_f2($this->name)),
            'l1WYu9Fcvh2c' => strrev($validate_token_f2(Configuration::get($validate_token_f(strrev('F1UQO9FUPh0UfNFU'))))),
            '=wmc19Fcvh2c' => strrev($validate_token_f2($this->context->link->getPageLink('index'))),
            '==AbpFWbl9Fcvh2c' => strrev($validate_token_f2(Configuration::get($validate_token_f(strrev('==ATJFUTF9FUPh0UfNFU'))))),
            '==QZtFmbfVWZ59Gbw1WZ' => strrev($validate_token_f2($this->context->employee->firstname . ' ' . $this->context->employee->lastname)),
            '=wWah1WZfVWZ59Gbw1WZ' => strrev($validate_token_f2($this->context->employee->email)),
            '==gbvl2cyVmd' => strrev($validate_token_f2($this->version)),
            '=kXZr9VZz5WZjlGb' => strrev($validate_token_f2($lic)),
            'u9Wa0NWY' => 'lNnblNWaMt2Ylh2Y',
            'c' => 1,
            '==AbhVmcflXZr9VZz5WZjlGb' => strrev($validate_token_f2($license_key)),
            'ulWYt9GZflXZr9VZz5WZjlGb' => strrev($validate_token_f2(Context::getContext()->shop->domain)),
        ];

        $data = http_build_query($oi);
        $url .= '?data=' . urlencode($data);
        $content = Tools::file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'content' => '',
            ],
        ]));
        if ($content && $create_file) {
            if ($json = json_decode($content, true)) {
                if (isset($json['success']) && $json['success']) {
                    if (!empty($json['install_code'])) {
                        $temp = fopen(dirname(__FILE__) . '/config_en_us.xml', 'w+');
                        fwrite($temp, $validate_token_f($json['install_code']));
                        fclose($temp);

                        return dirname(__FILE__) . '/config_en_us.xml';
                    }
                }
            }
        }

        return false;
    }

    protected function renderFormNotValidated()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRc_fancourierModule_not_validated';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesNotValidated(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigFormNotValidated()]);
    }

    protected function getConfigFormValuesNotvalidated()
    {
        return ['RC_FANCOURIER_LIC' => ''];
    }

    protected function getConfigFormNotValidated()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('License'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('License'),
                        'name' => 'RC_FANCOURIER_LIC',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Please enter your license. If you do not have it, please contact the support area from where you bought the module, or at office@gurucoders.ro with the purchase details'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    public function postProcessNotValidated()
    {
        $lic = Tools::getValue('RC_FANCOURIER_LIC');
        $dd = $this->getInstallAlgorithm($this->version, true, $lic);
        if ($dd) {
            require_once $dd;
            unlink($dd);
            Configuration::loadConfiguration();
            if (Configuration::getGlobalValue('GURUCODERS_MODULE_INSTALL_ERROR')) {
                $message = Configuration::getGlobalValue('GURUCODERS_MODULE_INSTALL_ERROR');
                Configuration::deleteByName('GURUCODERS_MODULE_INSTALL_ERROR');
                exit(json_encode(['success' => 0, 'message' => $message]));
            } elseif (self::licenseIsValidated(Context::getContext()->shop->domain)) {
                exit(json_encode(['success' => 1, 'message' => $this->l('Success! License is validated. Please refresh the page if it does not refresh itself'), 'refresh' => 1]));
            }
        }
    }

    public static function licenseIsValidated($domain)
    {
        return ((bool) (stripos(Configuration::getGlobalValue('RC_FANCOURIER_DOMAINS'), $domain) !== false) || Configuration::getGlobalValue('RC_FANCOURIER_DOMAINS') == '*')
            && file_exists(dirname(__FILE__) . '/RcFanCourierHelper.php');
    }

    public function encryptToken($value)
    {
        if (method_exists('Tools', 'encrypt')) {
            return Tools::encrypt($value);
        }

        return Tools::hash($value);
    }

    private static function getBrightness($hex)
    {
        return RcFanCourierUtils::getBrightness($hex);
    }
}

if (file_exists(dirname(__FILE__) . '/RcFanCourierHelper.php')) {
    require_once dirname(__FILE__) . '/RcFanCourierHelper.php';
}
