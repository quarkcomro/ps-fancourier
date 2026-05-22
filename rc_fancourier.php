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

class Rc_Fancourier extends CarrierModule
{
    protected $config_form = false;
    public $errors = [];
    public static $api_link = 'https://api.fancourier.ro/';
    public static $tracking_link = 'https://www.fancourier.ro/awb-tracking/?metoda=tracking&awb=@';
    public $id_carrier = 0;
    public static $awbData = [];

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
        $this->version = '2.2.1';
        $this->author = 'George B.';
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
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $parent_install = parent::install();
        $dd = $this->getInstallAlgorithm($this->version, true, '');
        if ($dd) {
            require_once $dd;
            unlink($dd);
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

            return $rc_fancourier_helper->getContent();
        } else {
            $messages = '';
            if (Tools::isSubmit('submitRc_fancourierModule_not_validated')) {
                $this->postProcessNotValidated();
            }

            $this->context->smarty->assign('module_dir', $this->_path);

            $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure-not-validated.tpl');

            return $messages . $output . $this->renderFormNotValidated();
        }
    }

    public static function buildLocationsArrayFromApi($array)
    {
        $locations = [];
        foreach ($array as $row) {
            $locations[] = [
                'client_id' => $row['id'],
                'label' => $row['name'],
            ];
        }

        return $locations;
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
        $check_states_link .= $separator . 'rc_fancourier_token=' . Tools::encrypt('rc_fancourier');
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
        $switch_type = 'switch';
        /* Fallback to checkbox for PS 1.5 */
        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $switch_type = 'radio';
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Active?'),
                        'name' => 'RC_FANCOURIER_ACTIVE',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want to use this module in frontoffice?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('City selector?'),
                        'name' => 'RC_FANCOURIER_CITY_SELECTOR',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want to use a selector instead of free text field for city?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Use select2 for state & city selectors'),
                        'name' => 'RC_FANCOURIER_SELECT2',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Select2 will allow customers to search for their state & city using their keyboard.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Show extra km in city selector?'),
                        'name' => 'RC_FANCOURIER_CITY_EXTRA_KM',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Ex: Arisani (49km)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_METHOD',
                        'class' => 't',
                        'label' => $this->l('Calculation method'),
                        'desc' => $this->l('API method only works if the customer already has an address. If the user is not logged in, it does not have an address, and there is not data to be sent to fancourier to calculate the shipping cost. To solve this shortcoming, Local Method will be used for all carts with no address linked.'),
                        'values' => [
                            [
                                'id' => 'local',
                                'value' => 'local',
                                'label' => $this->l('Local method'),
                            ],
                            [
                                'id' => 'api',
                                'value' => 'api',
                                'label' => $this->l('API method'),
                            ],
                            [
                                'id' => 'no',
                                'value' => 'no',
                                'label' => $this->l('No calculations'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT1',
                        'label' => $this->l('Authentication parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_USERNAME',
                        'label' => $this->l('Selfawb username'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_PASSWORD',
                        'label' => $this->l('Selfawb password'),
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT2',
                        'label' => $this->l('Global parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_FREE_FROM',
                        'label' => $this->l('Free shipping starting from? (with tax)'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREE_GROUPS',
                        'desc' => $this->l('Leaving a field empty or putting "0" will instead use the default value set above ("Free shipping starting from?"). If you want to offer free shipping no matter what the order amount is, put "-1".'),
                        'label' => $this->l('Set free shipping per group'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_ADDITIONAL_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when items in cart have additional shipping fees'),
                        'values' => [
                            [
                                'id' => 'add_free_shipping',
                                'value' => 'add_free_shipping',
                                'label' => $this->l('Add additional shipping fees to total cost, and allow free shipping'),
                            ],
                            [
                                'id' => 'add_no_free_shipping',
                                'value' => 'add_no_free_shipping',
                                'label' => $this->l('Add additional shipping fees to total cost, but do not allow free shipping (only pay extra fees)'),
                            ],
                            [
                                'id' => 'add_no_free_shipping_whole_amount',
                                'value' => 'add_no_free_shipping_whole_amount',
                                'label' => $this->l('Add additional shipping fees to total cost, but do not allow free shipping (pay whole shipping amount)'),
                            ],
                            [
                                'id' => 'do_not_add',
                                'value' => 'do_not_add',
                                'label' => $this->l('Do not add additional shipping fees'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Automatically Update tracking number'),
                        'name' => 'RC_FANCOURIER_UPDATETRACKING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want to automatically update the tracking number when creating an AWB?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Automatically Update tracking number - send email to customer'),
                        'name' => 'RC_FANCOURIER_UPDTRACKING_EMAIL',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want also want the system to send an email to the customer with the tracking link?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT3',
                        'label' => $this->l('Local method parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_LOCAL_INITIALCOST',
                        'label' => $this->l('Initial cost'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_LOCAL_COD_TAX',
                        'label' => $this->l('Cash on delivery tax'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Hide carriers if there are extra km'),
                        'name' => 'RC_FANCOURIER_KM_HIDE',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('If the cart contains extra km, we do not show Fan Courier carriers'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Hide "Cont colector" carriers if there are extra km'),
                        'name' => 'RC_FANCOURIER_KM_HIDE_CONT_COLECTOR',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('If the cart contains extra km, we do not show Fan Courier carriers that are with "Cont Colector" service. Basically you can disable Cash On Delivery if the cart contains extra km.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KM_COST',
                        'label' => $this->l('Cost per extra km'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KM_FIX_COST',
                        'label' => $this->l('Add fixed cost if extra km'),
                        'prefix' => $this->l('RON'),
                        'desc' => $this->l('No matter how many extra km there are, we add this value to the shipping cost.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_COST',
                        'label' => $this->l('Cost per extra kg'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_COST_FROM',
                        'label' => $this->l('Minimum weight to add cost per extra kg'),
                        'prefix' => $this->l('KG'),
                        'desc' => $this->l('"Cost per extra kg" will be added only if total order weight is above this value.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_DISABLE_FREE',
                        'label' => $this->l('Disable free shipping if weight is bigger than'),
                        'prefix' => $this->l('KG'),
                        'desc' => $this->l('Free shipping will be disabled if weight is above this value, and the customer will pay the whole cost.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_KG_COST_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when weight exceeds the value above'),
                        'values' => [
                            [
                                'id' => 'extra_kg',
                                'value' => 'extra_kg',
                                'label' => $this->l('Only pay for the difference between total weight and "Minimum weight to add cost per extra kg"'),
                            ],
                            [
                                'id' => 'all_kg',
                                'value' => 'all_kg',
                                'label' => $this->l('Pay per total weight, without deducting "Minimum weight to add cost per extra kg"'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Fixed cost if extra km - add Cost per extra kg?'),
                        'name' => 'RC_FANCOURIER_KM_FIX_COST_ADD_KG',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('You are using "Fixed cost if extra km"? Shall we also add "Cost per extra kg" to this value?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_EXC_KM_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when Free shipping + Extra KM'),
                        'values' => [
                            [
                                'id' => 'only_extra_km',
                                'value' => 'only_extra_km',
                                'label' => $this->l('Only pay extra km'),
                            ],
                            [
                                'id' => 'no_free_shipping',
                                'value' => 'no_free_shipping',
                                'label' => $this->l('No free shipping'),
                            ],
                            [
                                'id' => 'free_shipping',
                                'value' => 'free_shipping',
                                'label' => $this->l('Free shipping'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_EXC_KG_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when Free shipping + Extra KG'),
                        'values' => [
                            [
                                'id' => 'only_extra_kg',
                                'value' => 'only_extra_kg',
                                'label' => $this->l('Only pay extra kg'),
                            ],
                            [
                                'id' => 'no_free_shipping_kg',
                                'value' => 'no_free_shipping',
                                'label' => $this->l('No free shipping'),
                            ],
                            [
                                'id' => 'free_shipping_kg',
                                'value' => 'free_shipping',
                                'label' => $this->l('Free shipping'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_COD_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when Free shipping + Cash on delivery tax'),
                        'values' => [
                            [
                                'id' => 'free_shipping_cod',
                                'value' => 'free_shipping',
                                'label' => $this->l('Free shipping'),
                            ],
                            [
                                'id' => 'only_pay_cod_tax',
                                'value' => 'only_pay_cod_tax',
                                'label' => $this->l('Only pay COD tax'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_ERR_CITY_BHVR',
                        'class' => 't',
                        'label' => $this->l('Behaviour when entered city is not found'),
                        'values' => [
                            [
                                'id' => 'no_extra_km',
                                'value' => 'no_extra_km',
                                'label' => $this->l('Calculate with 0 extra km'),
                            ],
                            [
                                'id' => 'return_false',
                                'value' => 'return_false',
                                'label' => $this->l('Hide this delivery option'),
                            ],
                        ],
                    ],
                    [
                        'col' => 2,
                        'type' => 'textarea',
                        'name' => 'RC_FANCOURIER_PRVG_CITIES',
                        'class' => 't',
                        'label' => $this->l('Privileged cities'),
                        'desc' => $this->l('Privileged cities can have another initial cost. Each city is entered on a new line (ex: bucuresti). If you also need to include the state, enter it as: state_city (ex: brasov_ghimbav)'),
                        'rows' => 5,
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_PRVG_INITIAL_COST',
                        'label' => $this->l('Privileged cities - Initial cost'),
                        'prefix' => $this->l('RON'),
                        'desc' => $this->l('If selected city is in the list above, then the initial cost used in local method calculations will be this one. Enter -1 to disable (default: -1)'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Use FANBox map'),
                        'name' => 'RC_FANCOURIER_FANBOX_MAP',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('If enabled, show the FANBox map on checkout.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_FANBOX_SHIPPING_COST',
                        'label' => $this->l('Shipping cost for FANbox'),
                        'prefix' => $this->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT4',
                        'label' => $this->l('API method & AWB default parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_CONTACT_PERS',
                        'label' => $this->l('Seller contact person'),
                        'desc' => $this->l('Tip: If you write "auto", then it will be autocompleted with Employee name'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_PCKG_TYPE',
                        'class' => 't',
                        'label' => $this->l('Default packing type'),
                        'desc' => $this->l('Can be changed before AWB creation, but it is required to calculate the shipping costs with API method'),
                        'values' => [
                            [
                                'id' => 'one_envelope',
                                'value' => 'one_envelope',
                                'label' => $this->l('One envelope per delivery'),
                            ],
                            [
                                'id' => 'one_box',
                                'value' => 'one_box',
                                'label' => $this->l('One box per delivery'),
                            ],
                            [
                                'id' => 'envelopes_per_product',
                                'value' => 'envelopes_per_product',
                                'label' => $this->l('One envelope per product (unique)'),
                            ],
                            [
                                'id' => 'boxes_per_product',
                                'value' => 'boxes_per_product',
                                'label' => $this->l('One box per product (unique)'),
                            ],
                            [
                                'id' => 'envelopes_per_qty',
                                'value' => 'envelopes_per_qty',
                                'label' => $this->l('One envelope per product (one for each piece)'),
                            ],
                            [
                                'id' => 'boxes_per_qty',
                                'value' => 'boxes_per_qty',
                                'label' => $this->l('One box per product (one for each piece)'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_CNAME_FORMAT',
                        'class' => 't',
                        'label' => $this->l('Default customer name format'),
                        'desc' => $this->l('Can be changed before AWB creation, but the input field will be autocompleted with the format selected here.'),
                        'values' => [
                            [
                                'id' => 'last_first',
                                'value' => 'last_first',
                                'label' => $this->l('LASTNAME FIRSTNAME (COMPANY)'),
                            ],
                            [
                                'id' => 'first_last',
                                'value' => 'first_last',
                                'label' => $this->l('FIRSTNAME LASTNAME (COMPANY)'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_COD_WHO_PAYS',
                        'class' => 't',
                        'label' => $this->l('Who pays COD tax?'),
                        'values' => [
                            [
                                'id' => 'sender',
                                'value' => 'expeditor',
                                'label' => $this->l('Sender'),
                            ],
                            [
                                'id' => 'receiver',
                                'value' => 'destinatar',
                                'label' => $this->l('Receiver'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_DLV_WHO_PAYS',
                        'class' => 't',
                        'label' => $this->l('Who pays delivery tax?'),
                        'values' => [
                            [
                                'id' => 'sender_dlv',
                                'value' => 'expeditor',
                                'label' => $this->l('Sender'),
                            ],
                            [
                                'id' => 'receiver_dlv',
                                'value' => 'destinatar',
                                'label' => $this->l('Receiver'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Sender pays taxes if free shipping'),
                        'name' => 'RC_FANCOURIER_FALLBACK_SENDER',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('If the order has free shipping, then COD and delivery taxes should be paid by sender (can be changed before AWB creation).'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Substract shipping fees from COD if receiver pays taxes'),
                        'name' => 'RC_FANCOURIER_SUBSTRACT_SHIPPING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('If receiver will pay the taxes, substract shipping fees from COD and declared value (can be changed before AWB creation).'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Include declared value'),
                        'name' => 'RC_FANCOURIER_INCLUDE_DECLARED',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want to automatically set the declared value when calculating the price with API and when creating an AWB? (can be changed from the AWB creation form)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Show dimension inputs'),
                        'name' => 'RC_FANCOURIER_SHOW_DIM',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Do you want to show dimension inputs in the AWB creation form?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_API_MULTIPLICATOR',
                        'label' => $this->l('API Price multiplicator'),
                        'prefix' => $this->l('x'),
                        'desc' => $this->l('You can multiply the shipping price calculated from API with a number. Default value is 1, which means the original price will be shown to the customer. If you enter 1.19, then the price shown will be the original one + 19%'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_API_FS_LIMIT',
                        'label' => $this->l('Free shipping safety limit'),
                        'prefix' => $this->l('RON'),
                        'desc' => $this->l('Only offer free shipping in API method if shipping tax is smaller than this value. Put 0 to deactivate this feature.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_OBSERVATIONS',
                        'label' => $this->l('Default value for "Observations"'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_RESTITUTION',
                        'label' => $this->l('Default value for "Restitution"'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_OPTIONS',
                        'label' => $this->l('Default values for "Options"'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'label',
                            'id' => 'id',
                            'query' => [
                                [
                                    'id' => 'A',
                                    'val' => 'A',
                                    'label' => $this->l('Deschidere la livrare'),
                                ],
                                [
                                    'id' => 'B',
                                    'val' => 'B',
                                    'label' => $this->l('oPOD (restituire AWB semnat in original)'),
                                ],
                                [
                                    'id' => 'S',
                                    'val' => 'S',
                                    'label' => $this->l('Livrare sambata'),
                                ],
                                [
                                    'id' => 'X',
                                    'val' => 'X',
                                    'label' => $this->l('ePOD (semnatura electronica, in PDA)'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_CONTENT',
                        'label' => $this->l('Default values for "Content"'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'label',
                            'id' => 'id',
                            'query' => [
                                [
                                    'id' => 'ORDER_REFERENCE',
                                    'val' => 'ORDER_REFERENCE',
                                    'label' => $this->l('Order reference'),
                                ],
                                [
                                    'id' => 'INVOICE_NUMBER',
                                    'val' => 'INVOICE_NUMBER',
                                    'label' => $this->l('Invoice number'),
                                ],
                                [
                                    'id' => 'PRODUCTS_LIST',
                                    'val' => 'PRODUCTS_LIST',
                                    'label' => $this->l('Products list'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_WEIGHT',
                        'label' => $this->l('Default value for "weight"'),
                        'desc' => $this->l('By default, the weight sent to selfawb is the sum of all product weights. If you fill this field, it will send this value instead.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_WIDTH',
                        'label' => $this->l('Default value for "width"'),
                        'desc' => $this->l('This value will be used as default for box width, if the products don’t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_LENGTH',
                        'label' => $this->l('Default value for "length"'),
                        'desc' => $this->l('This value will be used as default for box length, if the products don’t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_HEIGHT',
                        'label' => $this->l('Default value for "height"'),
                        'desc' => $this->l('This value will be used as default for box height, if the products don’t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_AWB_FORMAT',
                        'class' => 't',
                        'label' => $this->l('AWB Format'),
                        'values' => [
                            [
                                'id' => 'A4',
                                'value' => 'A4',
                                'label' => $this->l('A4'),
                            ],
                            [
                                'id' => 'A5',
                                'value' => 'A5',
                                'label' => $this->l('A5'),
                            ],
                            [
                                'id' => 'A6',
                                'value' => 'A6',
                                'label' => $this->l('A6'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Warning if there is no invoice?'),
                        'name' => 'RC_FANCOURIER_NOINV_WARNING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Show a warning message in AWB generation form if there is no invoice generated?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->l('Log errors?'),
                        'name' => 'RC_FANCOURIER_LOG_ERRORS',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->l('Useful for debugging, turn OFF if you are low on disk. All errors will be saved in modules/rc_fancourier/log/errors.txt (Active only for price calculation and AWB generation)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT5',
                        'label' => $this->l('Update Cities & Offices'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    protected function getConfigFormForStates()
    {
        $order_states_list = $this->getOrderStates();
        $return = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configure order states'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_OS_DAYS',
                        'label' => $this->l('Days to check'),
                        'desc' => $this->l('Only check awbs created in the last X days. Recommended: 14'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_OS_IGNORE',
                        'label' => $this->l('Ignore orders with this states'),
                        'desc' => $this->l('Orders which have one of this states will be ignored from the check, so their status will be not modified. We recommend you to ignore canceled or finished orders.'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'name',
                            'id' => 'id_order_state',
                            'query' => $order_states_list,
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $fan_states = self::getFanStatesList();
        $order_states_list = array_merge([['id_order_state' => 0, 'name' => $this->l('- Do not change -')]], $order_states_list);

        foreach ($fan_states as $id_state => $state) {
            $return['form']['input'][] = [
                'col' => 3,
                'type' => 'select',
                'name' => 'RC_FANCOURIER_OS_MAP_' . $id_state,
                'label' => $state,
                'options' => [
                    'query' => $order_states_list,
                    'id' => 'id_order_state',
                    'name' => 'name',
                ],
            ];
        }

        $return['form']['input'][] = [
            'col' => 3,
            'type' => 'text',
            'name' => 'RC_FANCOURIER_OS_TRANSFER_DAYS',
            'label' => $this->l('Days to check for bank transfers'),
            'desc' => $this->l('Only check transfers made in the last X days. Recommended: 1 if you have a lot of Locations, 7 if you have less Locations'),
            'validate' => 'isFloat',
        ];

        $return['form']['input'][] = [
            'col' => 3,
            'type' => 'select',
            'name' => 'RC_FANCOURIER_OS_MAP_TRANSFER',
            'label' => 'State for bank transfer credited',
            'options' => [
                'query' => $order_states_list,
                'id' => 'id_order_state',
                'name' => 'name',
            ],
        ];

        return $return;
    }

    public function getOrderStates()
    {
        return OrderState::getOrderStates($this->context->language->id);
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $this->context->smarty->assign([
            'groups' => Group::getGroups($this->context->language->id),
            'RC_FANCOURIER_FREE_GROUPS' => json_decode(Configuration::get('RC_FANCOURIER_FREE_GROUPS'), true),
            'RC_FANCOURIER_LOC_LUPD' => (Configuration::get('RC_FANCOURIER_LOC_LUPD') ? Configuration::get('RC_FANCOURIER_LOC_LUPD') : $this->l('never')),
        ]);
        $return = [
            'RC_FANCOURIER_ACTIVE' => Configuration::get('RC_FANCOURIER_ACTIVE'),
            'RC_FANCOURIER_CITY_SELECTOR' => Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
            'RC_FANCOURIER_METHOD' => Configuration::get('RC_FANCOURIER_METHOD'),
            'RC_FANCOURIER_USERNAME' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'RC_FANCOURIER_PASSWORD' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'RC_FANCOURIER_LOCAL_INITIALCOST' => Configuration::get('RC_FANCOURIER_LOCAL_INITIALCOST'),
            'RC_FANCOURIER_LOCAL_COD_TAX' => Configuration::get('RC_FANCOURIER_LOCAL_COD_TAX'),
            'RC_FANCOURIER_KM_COST' => Configuration::get('RC_FANCOURIER_KM_COST'),
            'RC_FANCOURIER_KM_FIX_COST' => Configuration::get('RC_FANCOURIER_KM_FIX_COST'),
            'RC_FANCOURIER_KG_COST' => Configuration::get('RC_FANCOURIER_KG_COST'),
            'RC_FANCOURIER_KG_COST_FROM' => Configuration::get('RC_FANCOURIER_KG_COST_FROM'),
            'RC_FANCOURIER_KM_FIX_COST_ADD_KG' => Configuration::get('RC_FANCOURIER_KM_FIX_COST_ADD_KG'),
            'RC_FANCOURIER_FREE_FROM' => Configuration::get('RC_FANCOURIER_FREE_FROM'),
            'RC_FANCOURIER_FREE_EXC_KM_BHVR' => Configuration::get('RC_FANCOURIER_FREE_EXC_KM_BHVR'),
            'RC_FANCOURIER_FREE_EXC_KG_BHVR' => Configuration::get('RC_FANCOURIER_FREE_EXC_KG_BHVR'),
            'RC_FANCOURIER_ADDITIONAL_BHVR' => Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR'),
            'RC_FANCOURIER_ERR_CITY_BHVR' => Configuration::get('RC_FANCOURIER_ERR_CITY_BHVR'),
            'RC_FANCOURIER_FREE_COD_BHVR' => Configuration::get('RC_FANCOURIER_FREE_COD_BHVR'),
            'RC_FANCOURIER_CONTACT_PERS' => Configuration::get('RC_FANCOURIER_CONTACT_PERS'),
            'RC_FANCOURIER_PCKG_TYPE' => Configuration::get('RC_FANCOURIER_PCKG_TYPE'),
            'RC_FANCOURIER_COD_WHO_PAYS' => Configuration::get('RC_FANCOURIER_COD_WHO_PAYS'),
            'RC_FANCOURIER_DLV_WHO_PAYS' => Configuration::get('RC_FANCOURIER_DLV_WHO_PAYS'),
            'RC_FANCOURIER_FANBOX_MAP' => Configuration::get('RC_FANCOURIER_FANBOX_MAP'),
            'RC_FANCOURIER_FANBOX_SHIPPING_COST' => Configuration::get('RC_FANCOURIER_FANBOX_SHIPPING_COST'),
            'RC_FANCOURIER_FALLBACK_SENDER' => Configuration::get('RC_FANCOURIER_FALLBACK_SENDER'),
            'RC_FANCOURIER_SUBSTRACT_SHIPPING' => Configuration::get('RC_FANCOURIER_SUBSTRACT_SHIPPING'),
            'RC_FANCOURIER_API_FS_LIMIT' => Configuration::get('RC_FANCOURIER_API_FS_LIMIT'),
            'RC_FANCOURIER_LOG_ERRORS' => Configuration::get('RC_FANCOURIER_LOG_ERRORS'),
            'RC_FANCOURIER_OBSERVATIONS' => Configuration::get('RC_FANCOURIER_OBSERVATIONS'),
            'RC_FANCOURIER_RESTITUTION' => Configuration::get('RC_FANCOURIER_RESTITUTION'),
            'RC_FANCOURIER_KG_COST_BHVR' => Configuration::get('RC_FANCOURIER_KG_COST_BHVR'),
            'RC_FANCOURIER_UPDATETRACKING' => Configuration::get('RC_FANCOURIER_UPDATETRACKING'),
            'RC_FANCOURIER_UPDTRACKING_EMAIL' => Configuration::get('RC_FANCOURIER_UPDTRACKING_EMAIL'),
            'RC_FANCOURIER_KG_DISABLE_FREE' => Configuration::get('RC_FANCOURIER_KG_DISABLE_FREE'),
            'RC_FANCOURIER_FREETEXT1' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/form_freetext1.tpl'), /* Used for javascript reference */
            'RC_FANCOURIER_FREETEXT2' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/form_freetext2.tpl'), /* Used for javascript reference */
            'RC_FANCOURIER_FREETEXT3' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/form_freetext3.tpl'), /* Used for javascript reference */
            'RC_FANCOURIER_FREETEXT4' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/form_freetext4.tpl'), /* Used for javascript reference */
            'RC_FANCOURIER_FREETEXT5' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/form_freetext5.tpl'), /* Used for javascript reference */
            'RC_FANCOURIER_FREE_GROUPS' => $this->context->smarty->fetch(dirname(__FILE__) . '/views/templates/admin/free_s_per_group.tpl'),
            'RC_FANCOURIER_API_MULTIPLICATOR' => Configuration::get('RC_FANCOURIER_API_MULTIPLICATOR'),
            'RC_FANCOURIER_INCLUDE_DECLARED' => Configuration::get('RC_FANCOURIER_INCLUDE_DECLARED'),
            'RC_FANCOURIER_SHOW_DIM' => Configuration::get('RC_FANCOURIER_SHOW_DIM'),
            'RC_FANCOURIER_CNAME_FORMAT' => Configuration::get('RC_FANCOURIER_CNAME_FORMAT'),
            'RC_FANCOURIER_AWB_FORMAT' => Configuration::get('RC_FANCOURIER_AWB_FORMAT'),
            'RC_FANCOURIER_NOINV_WARNING' => Configuration::get('RC_FANCOURIER_NOINV_WARNING'),
            'RC_FANCOURIER_CITY_EXTRA_KM' => Configuration::get('RC_FANCOURIER_CITY_EXTRA_KM'),
            'RC_FANCOURIER_PRVG_CITIES' => Configuration::get('RC_FANCOURIER_PRVG_CITIES'),
            'RC_FANCOURIER_PRVG_INITIAL_COST' => Configuration::get('RC_FANCOURIER_PRVG_INITIAL_COST'),
            'RC_FANCOURIER_KM_HIDE' => Configuration::get('RC_FANCOURIER_KM_HIDE'),
            'RC_FANCOURIER_KM_HIDE_CONT_COLECTOR' => Configuration::get('RC_FANCOURIER_KM_HIDE_CONT_COLECTOR'),
            'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
            'RC_FANCOURIER_DEFAULT_WEIGHT' => Configuration::get('RC_FANCOURIER_DEFAULT_WEIGHT'),
            'RC_FANCOURIER_DEFAULT_WIDTH' => Configuration::get('RC_FANCOURIER_DEFAULT_WIDTH'),
            'RC_FANCOURIER_DEFAULT_LENGTH' => Configuration::get('RC_FANCOURIER_DEFAULT_LENGTH'),
            'RC_FANCOURIER_DEFAULT_HEIGHT' => Configuration::get('RC_FANCOURIER_DEFAULT_HEIGHT'),
        ];

        $options = Configuration::get('RC_FANCOURIER_OPTIONS');
        if (Configuration::get('RC_FANCOURIER_CONTENT')) {
            $content = json_decode(Configuration::get('RC_FANCOURIER_CONTENT'), true);
        } else {
            $content = [];
        }
        $form = $this->getConfigForm();
        $options_letters = [];
        $content_values = [];
        foreach ($form['form']['input'] as $input) {
            if ($input['name'] == 'RC_FANCOURIER_OPTIONS') {
                foreach ($input['values']['query'] as $value) {
                    $options_letters[] = $value['id'];
                }
            }
            if ($input['name'] == 'RC_FANCOURIER_CONTENT') {
                foreach ($input['values']['query'] as $value) {
                    $content_values[] = $value['id'];
                }
            }
        }
        if ($options) {
            foreach ($options_letters as $option) {
                if (stripos($options, $option) !== false) {
                    $return['RC_FANCOURIER_OPTIONS_' . $option] = 1;
                }
            }
        }
        if ($content) {
            foreach ($content_values as $content_val) {
                if (in_array($content_val, $content) !== false) {
                    $return['RC_FANCOURIER_CONTENT_' . $content_val] = 1;
                }
            }
        }

        $groups_selected = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
        if ($groups_selected) {
            $groups_selected_array = explode(',', $groups_selected);
            foreach ($groups_selected_array as $group_selected) {
                $return['RC_FANCOURIER_NO_FREESHIPPING_' . $group_selected] = 1;
            }
        }

        return $return;
    }

    protected function getConfigFormValuesForStates()
    {
        $return = [
            'RC_FANCOURIER_OS_DAYS' => Configuration::get('RC_FANCOURIER_OS_DAYS'),
            'RC_FANCOURIER_OS_TRANSFER_DAYS' => Configuration::get('RC_FANCOURIER_OS_TRANSFER_DAYS'),
        ];

        $order_states_ignore = Configuration::get('RC_FANCOURIER_OS_IGNORE');
        $order_states_ignore_array = explode(',', $order_states_ignore);
        $order_states = self::getOrderStates();
        foreach ($order_states as $order_state) {
            if (in_array($order_state['id_order_state'], $order_states_ignore_array)) {
                $return['RC_FANCOURIER_OS_IGNORE_' . $order_state['id_order_state']] = 1;
            }
        }

        $fan_order_states = self::getFanStatesList();
        foreach (array_keys($fan_order_states) as $id_fan_state) {
            $return['RC_FANCOURIER_OS_MAP_' . $id_fan_state] = Configuration::get('RC_FANCOURIER_OS_MAP_' . $id_fan_state);
        }

        $return['RC_FANCOURIER_OS_MAP_TRANSFER'] = Configuration::get('RC_FANCOURIER_OS_MAP_TRANSFER');

        return $return;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form = $this->getConfigForm();
        $this->errors = [];
        foreach ($form['form']['input'] as $key => $array) {
            if (isset($array['validate']) && $array['validate']) {
                $validate = $array['validate'];
                if (Tools::getValue($array['name']) && !Validate::$validate(Tools::getValue($array['name']))) {
                    $this->errors[] = sprintf($this->l('"%s" has to be a float value'), $array['label']);
                    continue;
                }
            }
            if ($array['name'] != 'RC_FANCOURIER_OPTIONS' && $array['name'] != 'RC_FANCOURIER_NO_FREESHIPPING' && $array['name'] != 'RC_FANCOURIER_FREE_GROUPS' && $array['name'] != 'RC_FANCOURIER_CONTENT') {
                Configuration::updateValue($array['name'], Tools::getValue($array['name']));
            }
        }
        $options = [];
        $content = [];
        foreach ($form['form']['input'] as $input) {
            if ($input['name'] == 'RC_FANCOURIER_OPTIONS') {
                foreach ($input['values']['query'] as $value) {
                    $options[] = $value['id'];
                }
            }
            if ($input['name'] == 'RC_FANCOURIER_CONTENT') {
                foreach ($input['values']['query'] as $value) {
                    $content[] = $value['id'];
                }
            }
        }
        foreach ($options as $key => $option) {
            if (!Tools::getValue('RC_FANCOURIER_OPTIONS_' . $option)) {
                unset($options[$key]);
            }
        }
        foreach ($content as $key => $option) {
            if (!Tools::getValue('RC_FANCOURIER_CONTENT_' . $option)) {
                unset($content[$key]);
            }
        }
        Configuration::updateValue('RC_FANCOURIER_OPTIONS', implode('', $options));
        Configuration::updateValue('RC_FANCOURIER_CONTENT', json_encode($content));
        $groups = Group::getGroups($this->context->language->id);
        $groups_selected = [];
        foreach ($groups as $key => $group) {
            if (Tools::getValue('RC_FANCOURIER_NO_FREESHIPPING_' . $group['id_group'])) {
                $groups_selected[] = $group['id_group'];
            }
        }
        Configuration::updateValue('RC_FANCOURIER_NO_FREESHIPPING', implode(',', $groups_selected));

        $free_shipping_per_group_array = [];
        if (Tools::getValue('rc_fancourier_free_shipping_group_amount')) {
            foreach (Tools::getValue('rc_fancourier_free_shipping_group_amount') as $key => $val) {
                $with_tax = Tools::getValue('rc_fancourier_free_shipping_group_tax');
                $with_tax = (int) $with_tax[$key];
                $free_shipping_per_group_array[$key] = [
                    'amount' => $val,
                    'with_tax' => $with_tax,
                ];
            }
        }
        if ($free_shipping_per_group_array) {
            Configuration::updateValue('RC_FANCOURIER_FREE_GROUPS', json_encode($free_shipping_per_group_array));
        }
    }

    protected function postProcessStates()
    {
        $form = $this->getConfigFormForStates();
        $this->errors = [];
        foreach ($form['form']['input'] as $key => $array) {
            if (isset($array['validate']) && $array['validate']) {
                $validate = $array['validate'];
                if (Tools::getValue($array['name']) && !Validate::$validate(Tools::getValue($array['name']))) {
                    $this->errors[] = sprintf($this->l('"%s" has to be a float value'), $array['label']);
                    continue;
                }
            }
            if ($array['name'] != 'RC_FANCOURIER_OS_IGNORE') {
                Configuration::updateValue($array['name'], Tools::getValue($array['name']));
            }
        }
        $order_states = self::getOrderStates();
        foreach ($order_states as $key => $order_state) {
            if (!Tools::getValue('RC_FANCOURIER_OS_IGNORE_' . $order_state['id_order_state'])) {
                unset($order_states[$key]);
            }
        }
        $order_states_ids = [];
        foreach ($order_states as $order_state) {
            $order_states_ids[] = $order_state['id_order_state'];
        }
        Configuration::updateValue('RC_FANCOURIER_OS_IGNORE', implode(',', $order_states_ids));
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
        foreach ($services['name'] as $key => $value) {
            $value = trim($value);
            if (!$value) {
                $this->errors[] = $this->l('Carrier name is mandatory.');
                continue;
            }
            $services_array[$key] = [
                'id_reference' => (int) $services['id_reference'][$key],
                'name' => pSQL($services['name'][$key]),
                'service' => pSQL($services['service'][$key]),
                'cod' => pSQL($services['cod'][$key]),
                'active' => (int) $services['active'][$key],
                'deleted' => (int) Tools::getValue('rc_fancourier_services_deleted_' . (int) $services['id_reference'][$key]),
            ];
        }
        foreach ($services_array as $service) {
            if ($service['deleted'] && $service['id_reference']) {
                $carrier = Carrier::getCarrierByReference($service['id_reference']);
                $carrier->delete();
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
        } else {
            $rc_fancourier_helper = new RcFanCourierHelper();
            $rc_fancourier_helper->id_carrier = $this->id_carrier;

            return $rc_fancourier_helper->getPackageShippingCost($params, $shipping_cost, $products);
        }
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

        $data = self::curlCall($endpoint, $post_data);
        if (stripos($data, 'Error') !== false) {
            self::logError($data, $endpoint, $post_data);

            return false;
        }

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

        if ($output_array['total'] > 0) {
            if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'do_not_add') {
                $additional_shipping_cost = 0;
            }

            $free_shipping = false;
            $disable_free_shipping_group = false;
            $group = Group::getCurrent();
            $groups_excluded_from_free_shipping = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
            if ($groups_excluded_from_free_shipping) {
                $groups_excluded = explode(',', $groups_excluded_from_free_shipping);
                if (in_array($group->id, $groups_excluded)) {
                    $disable_free_shipping_group = true;
                }
            }
            $free_shipping_info = self::getFreeShippingInfoByGroup($group->id);
            $free_shipping_amount = (float) $free_shipping_info['amount'];
            $free_shipping_with_tax = (int) $free_shipping_info['with_tax'];
            $cart_total_for_free_shipping = $cart_total;
            if (!$free_shipping_with_tax) {
                $cart_total_for_free_shipping = self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
            }
            if ($free_shipping_amount != 0 && $cart_total_for_free_shipping >= $free_shipping_amount && !$disable_free_shipping_group) {
                $free_shipping = true;
            }

            $free_shipping_safety_limit = (float) Configuration::get('RC_FANCOURIER_API_FS_LIMIT');

            if ($additional_shipping_cost) {
                if (Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping_whole_amount') {
                    $free_shipping = false;
                } elseif ($free_shipping && Configuration::get('RC_FANCOURIER_ADDITIONAL_BHVR') == 'add_no_free_shipping') {
                    $free_shipping = false;
                    if ($free_shipping_safety_limit && $free_shipping_safety_limit <= $data) {
                        /* Do nothing yet */
                    } else {
                        $data = 0;
                    }
                }
            }

            if ($free_shipping) {
                if ($free_shipping_safety_limit && $free_shipping_safety_limit <= $data) {
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

    public static function getClientIdBasedOnAddress($address_delivery)
    {
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
        if (!$location_selected) {
            $location_selected = self::getDefaultClientId();
        }

        return $location_selected;
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

        $disable_free_shipping_group = false;
        $group = Group::getCurrent();
        $groups_excluded_from_free_shipping = Configuration::get('RC_FANCOURIER_NO_FREESHIPPING');
        if ($groups_excluded_from_free_shipping) {
            $groups_excluded = explode(',', $groups_excluded_from_free_shipping);
            if (in_array($group->id, $groups_excluded)) {
                $disable_free_shipping_group = true;
            }
        }

        $free_shipping_info = self::getFreeShippingInfoByGroup($group->id);
        $free_shipping_amount = (float) $free_shipping_info['amount'];
        $free_shipping_with_tax = (int) $free_shipping_info['with_tax'];
        $cart_total_for_free_shipping = $cart_total;
        if (!$free_shipping_with_tax) {
            $cart_total_for_free_shipping = self::convertToRON($cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING, $products));
        }
        $free_shipping_theoretically = false;
        if ($free_shipping_amount != 0 && $cart_total_for_free_shipping >= $free_shipping_amount && !$disable_free_shipping_group) {
            $free_shipping = true;
            $free_shipping_theoretically = true;
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
        /*
         * $this->l('Data updated!');
         */
        if (is_numeric($state)) {
            $state = State::getNameById($state);
        }
        $extra_km = Db::getInstance()->getValue('SELECT `km` FROM `' . _DB_PREFIX_ . 'rc_fancourier_cities` WHERE `judet` = "' . pSQL($state) . '" AND `localitate` = "' . pSQL($city) . '"');
        if ($extra_km === false) {
            return $extra_km;
        }

        return (int) $extra_km;
    }

    protected function addCarrier($name = 'Fan Courier')
    {
        $carrier = new Carrier();

        $carrier->name = $name;
        $carrier->is_module = true;
        $carrier->active = 1;
        $carrier->range_behavior = 0;
        $carrier->need_range = 1;
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
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '1000000';
        $range_price->add();
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('module_name') == $this->name || Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJquery();
            $this->context->controller->addJqueryPlugin('sortable');
            $this->context->controller->addJS($this->_path . 'views/js/rc_fancourier_config.js');
            $this->context->controller->addCSS($this->_path . 'views/css/rc_fancourier_config.css');
        } elseif (Tools::strtolower(Tools::getValue('controller')) == 'adminorders' && Tools::getValue('id_order')) {
            $this->context->controller->addJquery();
            $this->context->controller->addJqueryPlugin('fancybox');
            $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
            $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            $this->context->controller->addJS($this->_path . 'views/js/rc_fancourier_order.js');
            $this->context->controller->addCSS($this->_path . 'views/css/rc_fancourier_order.css');
            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $this->context->controller->addCSS($this->_path . 'views/css/mini-bootstrap.css');
            }
        } elseif (Tools::strtolower(Tools::getValue('controller')) == 'adminaddresses') {
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path . '/views/js/rc_fancourier_tools.js');
            if (Configuration::get('RC_FANCOURIER_SELECT2')) {
                $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
                $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                    'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Tools::usingSecureMode()),
                    'rc_fancourier_token_front' => Tools::encrypt('rc_fancourier_front'),
                    'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                ]);
            } else {
                $this->context->smarty->assign([
                    'js_vals' => [
                        'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                        'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Tools::usingSecureMode()),
                        'rc_fancourier_token_front' => Tools::encrypt('rc_fancourier_front'),
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
            $this->context->controller->addJquery();
            $this->context->controller->addJS($this->_path . '/views/js/rc_fancourier_tools.js');
            if (Configuration::get('RC_FANCOURIER_SELECT2')) {
                $this->context->controller->addJS($this->_path . '/views/js/select2.full.min.js');
                $this->context->controller->addCSS($this->_path . '/views/css/select2.min.css');
            }
            if (method_exists('Media', 'addJsDef')) {
                Media::addJsDef([
                    'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                    'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Tools::usingSecureMode()),
                    'rc_fancourier_token_front' => Tools::encrypt('rc_fancourier_front'),
                    'RC_FANCOURIER_SELECT2' => Configuration::get('RC_FANCOURIER_SELECT2'),
                ]);
            } else {
                $this->context->smarty->assign([
                    'js_vals' => [
                        'RC_FANCOURIER_CITY_SELECTOR' => (bool) Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
                        'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Tools::usingSecureMode()),
                        'rc_fancourier_token_front' => Tools::encrypt('rc_fancourier_front'),
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
                        $delivery_state_name = "";
                    }
                } else {
                    $delivery_address_city = "";
                    $delivery_state_name = "";
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
        return stripos(self::getCarrierServiceByIdCarrier($fanbox_service), 'FANBOX') !== false || stripos(self::getCarrierServiceByIdCarrier($fanbox_service), 'FAN BOX') !== false;
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if ($this->isFanBoxService($params['carrier']['id']) !== false) {
            $officesSql = 'SELECT `strada` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` LIKE "%' . self::getCarrierServiceByIdCarrier($params['carrier']['id']) . '%"';
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
        $locationName = isset($_COOKIE['fancourier_locker_name']) ? $_COOKIE['fancourier_locker_name'] : null; // Cookie set by javascript
        if ($this->isFanBoxService($carrierId) !== false && $locationName == null) {
            $this->context->controller->errors[] = 'Te rugam sa alegi un locker FANBox de pe harta!';
            $params['completed'] = false;
        }
    }

    public function hookActionValidateOrder($params)
    {
        $orderCarrierId = $params['order']->id_carrier;
        $locationName = isset($_COOKIE['fancourier_locker_name']) ? $_COOKIE['fancourier_locker_name'] : null; // Cookie set by javascript
        $locationDetails = isset($_COOKIE['fancourier_locker_details']) ? $_COOKIE['fancourier_locker_details'] : null; // Cookie set by javascript
        $locker = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` WHERE `id_cart` = ' . (int) $params['order']->id_cart . '');

        $locationId = Db::getInstance()->getValue('SELECT `id_office` FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `strada` LIKE "%' . pSQL($locationName) . '%"');

        if ($this->isFanBoxService($orderCarrierId) !== false && $locationName == null) {
            $this->context->controller->errors[] = 'Te rugam sa alegi un locker FANBox de pe harta!';
            $params['completed'] = false;
        } else {
            if ($locker) {
                Db::getInstance()->insert('rc_fancourier_locker_order', [
                    'id_order' => $params['order']->id,
                    'id_locker' => $locationId,
                    'locker_json' => $locationDetails,
                ]);
            }
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
                //$phone .= ' / ' . $address_delivery->phone;
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
                    $content_products[] = $prod['product_quantity'] . 'x ' . $prod['product_name'];
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

        if ($selectedFanbox && $this->isFanBoxService($order->id_carrier)) {
            $options .= 'D';
        }

        return [
            'rc_fancourier_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax', [], Tools::usingSecureMode()),
            'id_order' => $id_order,
            'rc_fancourier_token' => Tools::encrypt('rc_fancourier'),
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
            'selected_fanbox' => $selectedFanbox,
            'rc_fancourier_total_weight' => $order_total_weight,
            'rc_fancourier_extra_weight' => $order_extra_weight,
            'rc_fancourier_extra_km' => $order_extra_km,
            'show_noinv_warning' => $show_noinv_warning,
            'content' => $content,
            'products' => $order->getProducts(),
        ];
    }

    public static function getLocations()
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locations` ORDER BY `position` ASC');
    }

    public static function getDefaultClientId()
    {
        return Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locations` ORDER BY `position` ASC');
    }

    public function getServices()
    {
        $services_from_api = false;
        $username = Configuration::get('RC_FANCOURIER_USERNAME');
        $password = Configuration::get('RC_FANCOURIER_PASSWORD');
        $client_id = $this->getDefaultClientId();

        if ($username && $password && $client_id) {
            $services_from_api = $this->getServicesFromAPI($username, $password, $client_id);
        }
        if (!$services_from_api) {
            return $this->getHardcodedServices();
        }

        return $services_from_api;
    }

    public function getServicesFromAPI($username, $password)
    {
        return []; // Might not be needed anymore, as hardcoded services are updated and in the last few months selfawb servers are slow
        $post_data = [
            'username' => $username,
            'password' => $password,
        ];
        $data = self::curlCall('reports/services', $post_data);
        if ($data) {
            $services = [];
            $decoded_json = json_decode($data, true);
            if (isset($decoded_json['data'])) {
                // Access the 'data' array
                $data_array = $decoded_json['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);
            }

            foreach ($output_array as $service) {
                $services[] = $service['name'];
            }
        }

        return $services;
    }

    public function getHardcodedServices()
    {
        return json_decode('["Standard","RedCode","Cont Colector","Express Loco 2H","Express Loco 4H","Express Loco 6H","Export","Red code-Cont Colector","Express Loco 2H-Cont Colector","Express Loco 4H-Cont Colector","Express Loco 6H-Cont Colector","Express Loco 1H","Express Loco 1H-Cont Colector","Export-Cont Colector","CollectPoint","CollectPoint Cont Colector","Produse Albe","Produse Albe-Cont Colector","Transport Marfa","Transport Marfa-Cont Colector","Transport Marfa Produse Albe","Transport Marfa Produse Albe-Cont Colector","FANbox","FANbox Cont Colector"]', true);
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
            ];
            $carriers_array[] = $carrier_array;
        }

        return $carriers_array;
    }

    public static function getCarrierService($id_reference)
    {
        return Configuration::get('RC_FANCOURIER_SERVICE_' . $id_reference);
    }

    public static function getCarrierServiceByIdCarrier($id_carrier)
    {
        $id_reference = Db::getInstance()->getValue('SELECT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier`
			WHERE id_carrier = ' . (int) $id_carrier);

        return Configuration::get('RC_FANCOURIER_SERVICE_' . $id_reference);
    }

    public static function setCarrierService($id_reference, $service)
    {
        return Configuration::updateValue('RC_FANCOURIER_SERVICE_' . $id_reference, $service);
    }

    public static function isCarrierCOD($id_reference)
    {
        return Configuration::get('RC_FANCOURIER_SERVICE_COD_' . $id_reference);
    }

    public static function setCarrierCOD($id_reference, $value)
    {
        return Configuration::updateValue('RC_FANCOURIER_SERVICE_COD_' . $id_reference, (int) $value);
    }

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
            $envelopes = sizeof($products);
            $boxes = 0;
        } elseif (Configuration::get('RC_FANCOURIER_PCKG_TYPE') == 'boxes_per_product') {
            $envelopes = 0;
            $boxes = sizeof($products);
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
            $products = $order->getProducts();

            if (!isset($data['rc_fancourier_length']) || !$data['rc_fancourier_length'] || !isset($data['rc_fancourier_width']) || !$data['rc_fancourier_width'] || !isset($data['rc_fancourier_height']) || !$data['rc_fancourier_height']) {
                $order = new Order($data['id_order']);
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
                $office_info = Db::getInstance()->getRow('SELECT strada, type, row FROM `'._DB_PREFIX_.'rc_fancourier_offices` WHERE `id_office` = "'.pSQL($office_id).'"');
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
                $data['rc_fancourier_office_state'] = $office_row['address']['county'];
                $data['rc_fancourier_office_city'] = $office_row['address']['locality'];
                $data['rc_fancourier_office_postcode'] = $office_row['address']['zipCode'];

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

            $post_data = array(
                'clientId' => $data['rc_fancourier_location'],
                'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
                'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
                'shipments' => [
                    array(
                        'info' => [
                            'service' => $data['rc_fancourier_service'],
                            'bank' => "",
                            'bankAccount' => "",
                            'packages' => [
                                'parcel' => $data['rc_fancourier_boxes'],
                                'envelope' => $data['rc_fancourier_envelopes']
                            ],
                            'weight' => $data['rc_fancourier_weight'],
                            'cod' => $data['rc_fancourier_cod_value'],
                            'declaredValue' => $data['rc_fancourier_declared_value'],
                            'payment' => $data['rc_fancourier_del_tax'],
                            'refund' => $data['rc_fancourier_restitution'],
                            'returnPayment' => $data['rc_fancourier_cod_tax'],
                            'observation' => $data['rc_fancourier_observations'],
                            'content' => substr($data['rc_fancourier_content'], 0, 255),
                            'dimensions' => [
                                'length' => $data['rc_fancourier_length'],
                                'height' => $data['rc_fancourier_height'],
                                'width' => $data['rc_fancourier_width'],
                            ],
                            'costCenter' => "DEP IT",
                            'options' => $data['rc_fancourier_options'],
                        ],
                        'recipient' => [
                            'name' => $data['rc_fancourier_customer_contact'],
                            'phone' => $data['rc_fancourier_phone'],
                            'email' => $data['rc_fancourier_customer_email'],
                            'address' => [
                                'county' => $data['rc_fancourier_state'],
                                'locality' => $data['rc_fancourier_city'],
                                'street' => $data['rc_fancourier_address'],
                                'pickupLocationId' => $data['rc_fancourier_pickupLocationId'],
                                'zipCode' => $data['rc_fancourier_postcode'],
                            ],
                        ],
                    ),
                ],
            );

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
                        $this->addAwbToOrder($data['id_order'], $awb, $data['rc_fancourier_location']);

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
        $totalWidth = 0;
        $maxHeight = 0;
        $maxDepth = 0;

        foreach ($products as $product) {
            $totalWidth += $product['width'] * $product['product_quantity'];
            $maxHeight = max($maxHeight, $product['height']);
            $maxDepth = max($maxDepth, $product['depth']);
        }

        return [
            'width' => $totalWidth,
            'height' => $maxHeight,
            'depth' => $maxDepth,
        ];
    }

    public static function getFanLink($with_token = true)
    {
        $fan_link = self::buildFanToken();
        if ($with_token) {
            return $fan_link(strrev('94Wah12bk9DcoBnLr5Was9lbhZ2X0V2Zv8mcuMnclR2bjVnc1d2LvoDc0RHa')) . urlencode(Tools::getHttpHost(true) . __PS_BASE_URI__) . '&lic=' . urlencode(Tools::file_get_contents(dirname(__FILE__) . '/license.txt'));
        } else {
            return 'https://www.selfawb.ro';
        }
    }

    public static function buildFanToken()
    {
        return 'base' . (1024 - 960) . '_decode';
    }

    public static function addAwbToOrder($id_order, $awb, $client_id)
    {
        $return = Db::getInstance()->insert('rc_fancourier_awbs', [
            'id_order' => (int) $id_order,
            'awb' => pSQL($awb),
            'client_id' => pSQL($client_id),
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        if (Configuration::get('RC_FANCOURIER_UPDATETRACKING')) {
            $order = new Order($id_order);
            $order->shipping_number = $awb;
            $order->update();

            $id_order_carrier = Db::getInstance()->getValue('
                SELECT `id_order_carrier`
                FROM `' . _DB_PREFIX_ . 'order_carrier`
                WHERE `id_order` = ' . (int) $id_order);

            $order_carrier = new OrderCarrier($id_order_carrier);

            if (Validate::isLoadedObject($order_carrier)) {
                // Update order_carrier
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
            $image_format = 'large_default'; // Will give error in validator, ik, but it's in an "else", so it won't be used except both the above checks fail
        }
        foreach ($products as $product) {
            $prod_obj = new Product((int) $product['product_id']);

            // try to get the first image for the purchased combination
            $img = $prod_obj->getCombinationImages($order->id_lang);
            $link_rewrite = $prod_obj->link_rewrite[$order->id_lang];
            $combination_img = $img ? $img[$product['product_attribute_id']][0]['id_image'] : null;
            if ($combination_img != null) {
                $img_url = $link->getImageLink($link_rewrite, $combination_img, $image_format);
            } else {
                // if there is no combination image, then get the product cover instead
                $img = $prod_obj->getCover($prod_obj->id);
                $img_url = $link->getImageLink($link_rewrite, $img['id_image']);
            }
            $prod_url = $prod_obj->getLink();

            Context::getContext()->smarty->assign([
                'product_name' => htmlspecialchars($product['product_name']),
                'img_url' => $img_url,
                'prod_url' => $prod_url,
            ]);

            $metadata .= Context::getContext()->smarty->fetch(dirname(__FILE__) . '/views/templates/hook/in_transit_metadata.tpl');
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
        } else {
            return false;
        }
    }

    public static function getAwbForOrders($id_order)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `id_order` = "' . (int) $id_order . '"');
    }

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
            'Ramburs(bani)' => self::formatDecimals(self::getData('rc_fancourier_cod_value')),
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
        if ($output_array[0]) {
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
            } else {
                return [
                    'success' => true,
                    'message' => $output_array[0]['message'],
                ];
            }
        }

        return [
            'success' => false,
            'message' => $this->l('Unknown error'),
        ];
    }

    public function showAwb($awb)
    {
        $client_id = Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` WHERE `awb` = "' . pSQL($awb) . '"');
        $endpoint = 'awb/label';
        $post_data = [
            'clientId' => $client_id,
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'awbs' => [$awb],
            'pdf' => 1,
            'language' => 'ro',
            'format' => Configuration::get('RC_FANCOURIER_AWB_FORMAT'),
        ];

        $data = self::curlCall($endpoint, $post_data);

        return $data;
    }

    public static function getData($name)
    {
        if (isset(self::$awbData[$name])) {
            return self::$awbData[$name];
        }

        return '';
    }

    public static function getAwbsToCheck($days_to_check = null)
    {
        if ($days_to_check == null) {
            $days_to_check = (int) Configuration::get('RC_FANCOURIER_OS_DAYS');
        }
        $os_to_exclude = Configuration::get('RC_FANCOURIER_OS_IGNORE');
        if (!$os_to_exclude) {
            $os_to_exclude = -1;
        }

        $awbs = Db::getInstance()->executeS('SELECT awbs.* FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs` awbs LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.id_order = awbs.id_order) WHERE awbs.date_add > (NOW() - INTERVAL ' . (int) $days_to_check . ' DAY) AND o.current_state NOT IN (' . pSQL($os_to_exclude) . ') ORDER BY `id_order` ASC');

        $awbs_array = [];
        foreach ($awbs as $row) {
            $awbs_array[$row['client_id']][] = $row;
        }

        return $awbs_array;
    }

    public static function checkAwbsStates($awbs_to_check)
    {
        if (!$awbs_to_check) {
            return;
        }
        $username = Configuration::get('RC_FANCOURIER_USERNAME');
        $password = Configuration::get('RC_FANCOURIER_PASSWORD');

        $data_to_return = [];

        //        $fan_states_list = self::getFanStatesList();

        foreach ($awbs_to_check as $client_id => $awbs) {
            $awbs = array_slice($awbs, 0, 150, true);
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
            $data = self::curlCall('reports/awb/tracking', $post_data);
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
                        $awb_event = $awb_obj['events'][sizeof($awb_obj['events']) - 1];
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
                echo 'ERROR: ';
                var_dump($data);
                exit;
            }
        }

        return $data_to_return;
    }

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
            if (sizeof($awbs_info['states']) == 1) {
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
                $awb['current_state_text_color'] = Tools::getBrightness($awb['current_state_color']) < 128 ? 'white' : 'black';
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

                $awb['next_state_text_color'] = Tools::getBrightness($awb['next_state_color']) < 128 ? 'white' : 'black';
            }
        }

        return $awbs;
    }

    public static function convertFromRON($amount, $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        if (!Currency::getIdByIsoCode('RON')) {
            return $amount;
        }

        return Tools::convertPriceFull($amount, new Currency(Currency::getIdByIsoCode('RON')), $context->currency);
    }

    public static function convertToRON($amount, $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        if (!Currency::getIdByIsoCode('RON')) {
            return $amount;
        }

        return Tools::convertPriceFull($amount, $context->currency, new Currency(Currency::getIdByIsoCode('RON')));
    }

    public static function buildXmlForCheckStates($awbs_to_check)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" ?><AWBLIST></AWBLIST>');

        foreach ($awbs_to_check as $i => $awb) {
            $awb_child = $xml->addChild('AWB');
            $awb_child->addChild('ID', $i + 1);
            $awb_child->addChild('NRAWB', $awb['awb']);
        }

        return $xml->asXML();
    }

    public static function getFanStatesList()
    {
        $endpoint = 'reports/awb-events';
        $post_data = [
            'username' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'password' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'language' => 'ro',
        ];
        if ($post_data['username'] && $post_data['password']) {
            $result = self::curlCall($endpoint, $post_data, 'GET', true);
            $json = json_decode($result, true);
            if ($result && isset($json['data'])) {
                $data_array = $json['data'];

                $output_array = array_map(function ($item) {
                    unset($item['status']);

                    return $item;
                }, $data_array);

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

    public static function curlCall($endpoint, $data = [], $requestType = 'GET', $no_throw = false)
    {
        if (isset($data['client_id'])) {
            $client_id = $data['client_id'];
            if (isset(self::$authDataOverride[$client_id])) {
                $data['username'] = self::$authDataOverride[$client_id]['username'];
                $data['password'] = self::$authDataOverride[$client_id]['password'];
            }
        }
        if (isset($data['clientId'])) {
            $client_id = $data['clientId'];
            if (isset(self::$authDataOverride[$client_id])) {
                $data['username'] = self::$authDataOverride[$client_id]['username'];
                $data['password'] = self::$authDataOverride[$client_id]['password'];
            }
        }

        $token = self::getAPIToken($data, $no_throw);
        $curl = curl_init();
        $get_params = '';

        if ($requestType == 'GET' && $data) {
            $get_params = '?' . http_build_query($data);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => self::$api_link . $endpoint . $get_params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => self::$API_CONNECTTIMEOUT,
            CURLOPT_TIMEOUT => self::$API_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => $requestType,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    public static function getAPIToken($data, $no_throw = false)
    {
        $data = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
        if (!self::$api_token || !isset(self::$api_token[$data['username']])) {
            $curl_call = curl_init(self::$api_link . 'login');
            curl_setopt($curl_call, CURLOPT_POST, true);
            curl_setopt($curl_call, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl_call, CURLOPT_CONNECTTIMEOUT, self::$API_CONNECTTIMEOUT);
            curl_setopt($curl_call, CURLOPT_TIMEOUT, self::$API_TIMEOUT);
            curl_setopt($curl_call, CURLOPT_RETURNTRANSFER, true);
            $curl_result = curl_exec($curl_call);
            curl_close($curl_call);

            if ($curl_result) {
                $curl_result_array = json_decode($curl_result, true);
                if (isset($curl_result_array['status']) && $curl_result_array['status'] == 'success' && isset($curl_result_array['data']) && isset($curl_result_array['data']['token'])) {
                    self::$api_token[$data['username']] = $curl_result_array['data']['token'];
                } else {
                    if (!$no_throw) {
                        throw new Exception('Could not get Fan Courier API Token');
                    }
                }
            }
        }

        return isset(self::$api_token[$data['username']]) ? self::$api_token[$data['username']] : false;
    }

    public static function logError($error, $endpoint = false, $data = false)
    {
        if (Configuration::get('RC_FANCOURIER_LOG_ERRORS')) {
            $text = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $error;
            if ($endpoint) {
                $text .= ' (' . $endpoint . ')';
            }
            if ($data) {
                $text .= ' ' . json_encode($data);
            }

            file_put_contents(dirname(__FILE__) . '/log/errors.txt', $text . PHP_EOL, FILE_APPEND);
        }
    }

    public static function replaceDiacriticsChars($str)
    {
        $return = preg_replace(
            [
                /* Lowercase */
                '/[\x{0105}\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}]/u',
                '/[\x{00E7}\x{010D}\x{0107}]/u',
                '/[\x{010F}]/u',
                '/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{011B}\x{0119}]/u',
                '/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}]/u',
                '/[\x{0142}\x{013E}\x{013A}]/u',
                '/[\x{00F1}\x{0148}]/u',
                '/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}]/u',
                '/[\x{0159}\x{0155}]/u',
                '/[\x{015B}\x{0161}]/u',
                '/[\x{00DF}]/u',
                '/[\x{0165}]/u',
                '/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{016F}]/u',
                '/[\x{00FD}\x{00FF}]/u',
                '/[\x{017C}\x{017A}\x{017E}]/u',
                '/[\x{00E6}]/u',
                '/[\x{0153}]/u',

                /* Uppercase */
                '/[\x{0104}\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u',
                '/[\x{00C7}\x{010C}\x{0106}]/u',
                '/[\x{010E}]/u',
                '/[\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{011A}\x{0118}]/u',
                '/[\x{0141}\x{013D}\x{0139}]/u',
                '/[\x{00D1}\x{0147}]/u',
                '/[\x{00D3}]/u',
                '/[\x{0158}\x{0154}]/u',
                '/[\x{015A}\x{0160}]/u',
                '/[\x{0164}]/u',
                '/[\x{00D9}\x{00DA}\x{00DB}\x{00DC}\x{016E}]/u',
                '/[\x{017B}\x{0179}\x{017D}]/u',
                '/[\x{00C6}]/u',
                '/[\x{0152}]/u',
            ],
            [
                'a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 'ss', 't', 'u', 'y', 'z', 'ae', 'oe',
                'A', 'C', 'D', 'E', 'L', 'N', 'O', 'R', 'S', 'T', 'U', 'Z', 'AE', 'OE',
            ],
            $str
        );

        $to_replace = ['Ț', 'Ă', 'Ș', 'ș', 'ă', 'â', 'î', 'ț', 'ţ', 'Ț', 'Î', 'Ă', 'Ș', 'Â'];
        $replace_with = ['T', 'A', 'S', 's', 'a', 'a', 'i', 't', 't', 'T', 'I', 'A', 'S', 'A'];

        $return = str_replace($to_replace, $replace_with, $return);

        return $return;
    }

    public static function isOrderPage()
    {
        if (!empty(Context::getContext()->controller->php_self)) {
            return stripos(Context::getContext()->controller->php_self, 'order') !== false || stripos(Context::getContext()->controller->php_self, 'checkout') !== false;
        }

        return stripos(Tools::getValue('module', 'none'), 'checkout') !== false;
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
            foreach ($data_offices as $type => $data_office) {
                $decoded_data = json_decode($data_office, true);
                if ($decoded_data['data']) {
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
        $rows = array_map('str_getcsv', explode(PHP_EOL, $csv));
        $header = array_shift($rows);
        $csv = [];
        foreach ($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        return $csv;
    }

    public static function getOffices()
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` ORDER BY `strada` ASC');
    }

    public static function clearOffices()
    {
        return Db::getInstance()->delete('rc_fancourier_offices');
    }

    public static function addOffices($offices, $type)
    {
        $return = true;
        $offices_array = [];
        foreach ($offices as $office) {
            if (!isset($offices_array[$office['routingLocation']])) {
                $offices_array[$office['routingLocation']] = $office;
            }
        }
        foreach ($offices_array as $office) {
            $return &= Db::getInstance()->insert('rc_fancourier_offices', [
                'id_office' => pSQL($office['id']),
                'judet' => pSQL($office['address']['county']),
                'localitate' => pSQL($office['address']['locality']),
                'strada' => pSQL($office['routingLocation']),
                'cod_postal' => pSQL($office['address']['zipCode']),
                'agentie' => pSQL($office['address']['locality']),
                'type' => pSQL($type),
                'row' => pSQL(json_encode($office)),
            ]);
        }

        return $return;
    }

    public static function clearCities()
    {
        return Db::getInstance()->delete('rc_fancourier_cities');
    }

    public static function addCities($cities)
    {
        $return = true;
        $insert_data = [];
        foreach ($cities as $city) {
            if ($city['name'] == 'Bucuresti') {
                for ($sector = 1; $sector <= 6; ++$sector) {
                    $insert_data[] = [
                        'id' => $city['id'] * 10000 + $sector,
                        'judet' => $city['county'],
                        'localitate' => 'Sector ' . $sector,
                        'agentie' => $city['agency'],
                        'km' => $city['exteriorKm'],
                    ];
                }
            } else {
                $insert_data[] = [
                    'id' => $city['id'],
                    'judet' => $city['county'],
                    'localitate' => $city['name'],
                    'agentie' => $city['agency'],
                    'km' => $city['exteriorKm'],
                ];
            }
        }
        $return &= Db::getInstance()->insert('rc_fancourier_cities', $insert_data);

        return $return;
    }

    public static function isPs15()
    {
        return version_compare(_PS_VERSION_, '1.6', '<');
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
        return self::untrailingslashit($string) . '/';
    }

    public static function untrailingslashit($string)
    {
        return rtrim($string, '/\\');
    }

    public static function getTempDirPath()
    {
        if (function_exists('sys_get_temp_dir')) {
            $temp = sys_get_temp_dir();
            if (@is_dir($temp) && self::isWritable($temp)) {
                return self::trailingslashit($temp);
            }
        }
        $temp = ini_get('upload_tmp_dir');
        if (@is_dir($temp) && self::isWritable($temp)) {
            return self::trailingslashit($temp);
        }

        $temp = dirname(__FILE__) . '/';
        if (@is_dir($temp) && self::isWritable($temp)) {
            return $temp;
        }

        return '/tmp/';
    }

    public static function isWritable($path)
    {
        return @is_writable($path);
    }

    public static function getPrivilegedCitiesArray()
    {
        $privileged_cities_array = [];
        $privileged_cities = Configuration::get('RC_FANCOURIER_PRVG_CITIES');
        if ($privileged_cities) {
            $privileged_cities_explode = explode(PHP_EOL, $privileged_cities);
            foreach ($privileged_cities_explode as $city) {
                $privileged_cities_array[] = Tools::strtolower(trim($city));
            }
        }

        return $privileged_cities_array;
    }

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
        $data_to_insert = [];
        foreach ($errors as $id_order => $error) {
            $data_to_insert[] = [
                'id_order' => (int) $id_order,
                'error' => pSQL($error),
                'date_add' => date('Y-m-d H:i:s'),
            ];
        }

        return Db::getInstance()->insert('rc_fancourier_awb_errors', $data_to_insert);
    }

    public static function formatDecimals($amount)
    {
        $amount = (float) $amount;
        if ($amount == (int) $amount) {
            return (int) $amount;
        }

        return number_format($amount, 2, '.', '');
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
                $total_transfers = $this->getTransfers($client_id, $date);
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
                    $awb['current_state_text_color'] = Tools::getBrightness($awb['current_state_color']) < 128 ? 'white' : 'black';
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

                    $awb['next_state_text_color'] = Tools::getBrightness($awb['next_state_color']) < 128 ? 'white' : 'black';

                    $awbs[] = $awb;
                }
                // }
            }

            return $awbs;
        } else {
            return [];
        }
    }

    public static function getIdOrderByAwbs()
    {
        $return = [];
        $data = Db::getInstance()->executeS('SELECT `id_order`, `awb` FROM `' . _DB_PREFIX_ . 'rc_fancourier_awbs`');
        foreach ($data as $row) {
            $return[$row['awb']] = $row['id_order'];
        }

        return $return;
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
        $array = [];
        $interval = new DateInterval('P1D');

        $realEnd = new DateTime($end);
        $realEnd->add($interval);

        $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

        foreach ($period as $date) {
            $array[] = $date->format($format);
        }

        return $array;
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
}

if (file_exists(dirname(__FILE__) . '/RcFanCourierHelper.php')) {
    require_once dirname(__FILE__) . '/RcFanCourierHelper.php';
}
