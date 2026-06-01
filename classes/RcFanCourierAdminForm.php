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
 * Admin configuration form structure, values, and postProcess.
 */
class RcFanCourierAdminForm
{
    /** @var Rc_Fancourier */
    private $module;

    /**
     * @param Rc_Fancourier $module
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * @return array
     */
    public function getConfigForm()
    {
        $switch_type = 'switch';
        /* Fallback to checkbox for PS 1.5 */
        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            $switch_type = 'radio';
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Active?'),
                        'name' => 'RC_FANCOURIER_ACTIVE',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want to use this module in frontoffice?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('City selector?'),
                        'name' => 'RC_FANCOURIER_CITY_SELECTOR',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want to use a selector instead of free text field for city?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Use select2 for state & city selectors'),
                        'name' => 'RC_FANCOURIER_SELECT2',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Select2 will allow customers to search for their state & city using their keyboard.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Show extra km in city selector?'),
                        'name' => 'RC_FANCOURIER_CITY_EXTRA_KM',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Ex: Arisani (49km)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_METHOD',
                        'class' => 't',
                        'label' => $this->module->l('Calculation method'),
                        'desc' => $this->module->l('API method only works if the customer already has an address. If the user is not logged in, it does not have an address, and there is not data to be sent to fancourier to calculate the shipping cost. To solve this shortcoming, Local Method will be used for all carts with no address linked.'),
                        'values' => [
                            [
                                'id' => 'local',
                                'value' => 'local',
                                'label' => $this->module->l('Local method'),
                            ],
                            [
                                'id' => 'api',
                                'value' => 'api',
                                'label' => $this->module->l('API method'),
                            ],
                            [
                                'id' => 'no',
                                'value' => 'no',
                                'label' => $this->module->l('No calculations'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT1',
                        'label' => $this->module->l('Authentication parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_USERNAME',
                        'label' => $this->module->l('Selfawb username'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_PASSWORD',
                        'label' => $this->module->l('Selfawb password'),
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_SENDER_NAME',
                        'label' => $this->module->l('Sender name'),
                        'desc' => $this->module->l('Used when generating AWB with FANbox drop-off (W option). Defaults to shop name if empty.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_SENDER_PHONE',
                        'label' => $this->module->l('Sender phone'),
                        'desc' => $this->module->l('Used when generating AWB with FANbox drop-off (W option). Defaults to shop phone if empty.'),
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_SENDER_EMAIL',
                        'label' => $this->module->l('Sender email'),
                        'desc' => $this->module->l('Used when generating AWB with FANbox drop-off (W option). Defaults to shop email if empty.'),
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT2',
                        'label' => $this->module->l('Global parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_FREE_FROM',
                        'label' => $this->module->l('Free shipping starting from? (with tax)'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREE_GROUPS',
                        'desc' => $this->module->l('Leaving a field empty or putting "0" will instead use the default value set above ("Free shipping starting from?"). If you want to offer free shipping no matter what the order amount is, put "-1".'),
                        'label' => $this->module->l('Set free shipping per group'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_ADDITIONAL_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when items in cart have additional shipping fees'),
                        'values' => [
                            [
                                'id' => 'add_free_shipping',
                                'value' => 'add_free_shipping',
                                'label' => $this->module->l('Add additional shipping fees to total cost, and allow free shipping'),
                            ],
                            [
                                'id' => 'add_no_free_shipping',
                                'value' => 'add_no_free_shipping',
                                'label' => $this->module->l('Add additional shipping fees to total cost, but do not allow free shipping (only pay extra fees)'),
                            ],
                            [
                                'id' => 'add_no_free_shipping_whole_amount',
                                'value' => 'add_no_free_shipping_whole_amount',
                                'label' => $this->module->l('Add additional shipping fees to total cost, but do not allow free shipping (pay whole shipping amount)'),
                            ],
                            [
                                'id' => 'do_not_add',
                                'value' => 'do_not_add',
                                'label' => $this->module->l('Do not add additional shipping fees'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Automatically Update tracking number'),
                        'name' => 'RC_FANCOURIER_UPDATETRACKING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want to automatically update the tracking number when creating an AWB?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Automatically Update tracking number - send email to customer'),
                        'name' => 'RC_FANCOURIER_UPDTRACKING_EMAIL',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want also want the system to send an email to the customer with the tracking link?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT3',
                        'label' => $this->module->l('Local method parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_LOCAL_INITIALCOST',
                        'label' => $this->module->l('Initial cost'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_LOCAL_COD_TAX',
                        'label' => $this->module->l('Cash on delivery tax'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Hide carriers if there are extra km'),
                        'name' => 'RC_FANCOURIER_KM_HIDE',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('If the cart contains extra km, we do not show Fan Courier carriers'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Hide "Cont colector" carriers if there are extra km'),
                        'name' => 'RC_FANCOURIER_KM_HIDE_CONT_COLECTOR',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('If the cart contains extra km, we do not show Fan Courier carriers that are with "Cont Colector" service. Basically you can disable Cash On Delivery if the cart contains extra km.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KM_COST',
                        'label' => $this->module->l('Cost per extra km'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KM_FIX_COST',
                        'label' => $this->module->l('Add fixed cost if extra km'),
                        'prefix' => $this->module->l('RON'),
                        'desc' => $this->module->l('No matter how many extra km there are, we add this value to the shipping cost.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_COST',
                        'label' => $this->module->l('Cost per extra kg'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_COST_FROM',
                        'label' => $this->module->l('Minimum weight to add cost per extra kg'),
                        'prefix' => $this->module->l('KG'),
                        'desc' => $this->module->l('"Cost per extra kg" will be added only if total order weight is above this value.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_KG_DISABLE_FREE',
                        'label' => $this->module->l('Disable free shipping if weight is bigger than'),
                        'prefix' => $this->module->l('KG'),
                        'desc' => $this->module->l('Free shipping will be disabled if weight is above this value, and the customer will pay the whole cost.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_KG_COST_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when weight exceeds the value above'),
                        'values' => [
                            [
                                'id' => 'extra_kg',
                                'value' => 'extra_kg',
                                'label' => $this->module->l('Only pay for the difference between total weight and "Minimum weight to add cost per extra kg"'),
                            ],
                            [
                                'id' => 'all_kg',
                                'value' => 'all_kg',
                                'label' => $this->module->l('Pay per total weight, without deducting "Minimum weight to add cost per extra kg"'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Fixed cost if extra km - add Cost per extra kg?'),
                        'name' => 'RC_FANCOURIER_KM_FIX_COST_ADD_KG',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('You are using "Fixed cost if extra km"? Shall we also add "Cost per extra kg" to this value?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_EXC_KM_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when Free shipping + Extra KM'),
                        'values' => [
                            [
                                'id' => 'only_extra_km',
                                'value' => 'only_extra_km',
                                'label' => $this->module->l('Only pay extra km'),
                            ],
                            [
                                'id' => 'no_free_shipping',
                                'value' => 'no_free_shipping',
                                'label' => $this->module->l('No free shipping'),
                            ],
                            [
                                'id' => 'free_shipping',
                                'value' => 'free_shipping',
                                'label' => $this->module->l('Free shipping'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_EXC_KG_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when Free shipping + Extra KG'),
                        'values' => [
                            [
                                'id' => 'only_extra_kg',
                                'value' => 'only_extra_kg',
                                'label' => $this->module->l('Only pay extra kg'),
                            ],
                            [
                                'id' => 'no_free_shipping_kg',
                                'value' => 'no_free_shipping',
                                'label' => $this->module->l('No free shipping'),
                            ],
                            [
                                'id' => 'free_shipping_kg',
                                'value' => 'free_shipping',
                                'label' => $this->module->l('Free shipping'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_FREE_COD_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when Free shipping + Cash on delivery tax'),
                        'values' => [
                            [
                                'id' => 'free_shipping_cod',
                                'value' => 'free_shipping',
                                'label' => $this->module->l('Free shipping'),
                            ],
                            [
                                'id' => 'only_pay_cod_tax',
                                'value' => 'only_pay_cod_tax',
                                'label' => $this->module->l('Only pay COD tax'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_ERR_CITY_BHVR',
                        'class' => 't',
                        'label' => $this->module->l('Behaviour when entered city is not found'),
                        'values' => [
                            [
                                'id' => 'no_extra_km',
                                'value' => 'no_extra_km',
                                'label' => $this->module->l('Calculate with 0 extra km'),
                            ],
                            [
                                'id' => 'return_false',
                                'value' => 'return_false',
                                'label' => $this->module->l('Hide this delivery option'),
                            ],
                        ],
                    ],
                    [
                        'col' => 2,
                        'type' => 'textarea',
                        'name' => 'RC_FANCOURIER_PRVG_CITIES',
                        'class' => 't',
                        'label' => $this->module->l('Privileged cities'),
                        'desc' => $this->module->l('Privileged cities can have another initial cost. Each city is entered on a new line (ex: bucuresti). If you also need to include the state, enter it as: state_city (ex: brasov_ghimbav)'),
                        'rows' => 5,
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_PRVG_INITIAL_COST',
                        'label' => $this->module->l('Privileged cities - Initial cost'),
                        'prefix' => $this->module->l('RON'),
                        'desc' => $this->module->l('If selected city is in the list above, then the initial cost used in local method calculations will be this one. Enter -1 to disable (default: -1)'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Use FANBox map'),
                        'name' => 'RC_FANCOURIER_FANBOX_MAP',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('If enabled, show the FANBox map on checkout.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_FANBOX_SHIPPING_COST',
                        'label' => $this->module->l('Shipping cost for FANbox'),
                        'prefix' => $this->module->l('RON'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Enable drop-off at FANbox by default'),
                        'name' => 'RC_FANCOURIER_DEFAULT_DROPOFF_ENABLED',
                        'desc' => $this->module->l('When enabled, the "Predare la FANbox" option will be pre-checked when generating an AWB from the admin.'),
                        'class' => 't',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 4,
                        'type' => 'select',
                        'name' => 'RC_FANCOURIER_DEFAULT_DROPOFF_ID',
                        'label' => $this->module->l('Default drop-off FANbox location'),
                        'desc' => $this->module->l('Pre-selected FANbox drop-off location when generating AWB from admin. The sender will drop off the parcel at this location.'),
                        'options' => [
                            'query' => array_merge(
                                [['id_office' => '', 'strada' => '— ' . $this->module->l('None') . ' —']],
                                RcFanCourierLocation::getFanboxes() ?: []
                            ),
                            'id' => 'id_office',
                            'name' => 'strada',
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT4',
                        'label' => $this->module->l('API method & AWB default parameters'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_CONTACT_PERS',
                        'label' => $this->module->l('Seller contact person'),
                        'desc' => $this->module->l('Tip: If you write "auto", then it will be autocompleted with Employee name'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_PCKG_TYPE',
                        'class' => 't',
                        'label' => $this->module->l('Default packing type'),
                        'desc' => $this->module->l('Can be changed before AWB creation, but it is required to calculate the shipping costs with API method'),
                        'values' => [
                            [
                                'id' => 'one_envelope',
                                'value' => 'one_envelope',
                                'label' => $this->module->l('One envelope per delivery'),
                            ],
                            [
                                'id' => 'one_box',
                                'value' => 'one_box',
                                'label' => $this->module->l('One box per delivery'),
                            ],
                            [
                                'id' => 'envelopes_per_product',
                                'value' => 'envelopes_per_product',
                                'label' => $this->module->l('One envelope per product (unique)'),
                            ],
                            [
                                'id' => 'boxes_per_product',
                                'value' => 'boxes_per_product',
                                'label' => $this->module->l('One box per product (unique)'),
                            ],
                            [
                                'id' => 'envelopes_per_qty',
                                'value' => 'envelopes_per_qty',
                                'label' => $this->module->l('One envelope per product (one for each piece)'),
                            ],
                            [
                                'id' => 'boxes_per_qty',
                                'value' => 'boxes_per_qty',
                                'label' => $this->module->l('One box per product (one for each piece)'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_CNAME_FORMAT',
                        'class' => 't',
                        'label' => $this->module->l('Default customer name format'),
                        'desc' => $this->module->l('Can be changed before AWB creation, but the input field will be autocompleted with the format selected here.'),
                        'values' => [
                            [
                                'id' => 'last_first',
                                'value' => 'last_first',
                                'label' => $this->module->l('LASTNAME FIRSTNAME (COMPANY)'),
                            ],
                            [
                                'id' => 'first_last',
                                'value' => 'first_last',
                                'label' => $this->module->l('FIRSTNAME LASTNAME (COMPANY)'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_COD_WHO_PAYS',
                        'class' => 't',
                        'label' => $this->module->l('Who pays COD tax?'),
                        'values' => [
                            [
                                'id' => 'sender',
                                'value' => 'expeditor',
                                'label' => $this->module->l('Sender'),
                            ],
                            [
                                'id' => 'receiver',
                                'value' => 'destinatar',
                                'label' => $this->module->l('Receiver'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_DLV_WHO_PAYS',
                        'class' => 't',
                        'label' => $this->module->l('Who pays delivery tax?'),
                        'values' => [
                            [
                                'id' => 'sender_dlv',
                                'value' => 'expeditor',
                                'label' => $this->module->l('Sender'),
                            ],
                            [
                                'id' => 'receiver_dlv',
                                'value' => 'destinatar',
                                'label' => $this->module->l('Receiver'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Sender pays taxes if free shipping'),
                        'name' => 'RC_FANCOURIER_FALLBACK_SENDER',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('If the order has free shipping, then COD and delivery taxes should be paid by sender (can be changed before AWB creation).'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Substract shipping fees from COD if receiver pays taxes'),
                        'name' => 'RC_FANCOURIER_SUBSTRACT_SHIPPING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('If receiver will pay the taxes, substract shipping fees from COD and declared value (can be changed before AWB creation).'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Include declared value'),
                        'name' => 'RC_FANCOURIER_INCLUDE_DECLARED',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want to automatically set the declared value when calculating the price with API and when creating an AWB? (can be changed from the AWB creation form)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Show dimension inputs'),
                        'name' => 'RC_FANCOURIER_SHOW_DIM',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Do you want to show dimension inputs in the AWB creation form?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_API_MULTIPLICATOR',
                        'label' => $this->module->l('API Price multiplicator'),
                        'prefix' => $this->module->l('x'),
                        'desc' => $this->module->l('You can multiply the shipping price calculated from API with a number. Default value is 1, which means the original price will be shown to the customer. If you enter 1.19, then the price shown will be the original one + 19%'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_API_FS_LIMIT',
                        'label' => $this->module->l('Free shipping safety limit'),
                        'prefix' => $this->module->l('RON'),
                        'desc' => $this->module->l('Only offer free shipping in API method if shipping tax is smaller than this value. Put 0 to deactivate this feature.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_OBSERVATIONS',
                        'label' => $this->module->l('Default value for "Observations"'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_RESTITUTION',
                        'label' => $this->module->l('Default value for "Restitution"'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_OPTIONS',
                        'label' => $this->module->l('Default values for "Options"'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'label',
                            'id' => 'id',
                            'query' => [
                                [
                                    'id' => 'A',
                                    'val' => 'A',
                                    'label' => $this->module->l('Deschidere la livrare'),
                                ],
                                [
                                    'id' => 'B',
                                    'val' => 'B',
                                    'label' => $this->module->l('oPOD (restituire AWB semnat in original)'),
                                ],
                                [
                                    'id' => 'S',
                                    'val' => 'S',
                                    'label' => $this->module->l('Livrare sambata'),
                                ],
                                [
                                    'id' => 'X',
                                    'val' => 'X',
                                    'label' => $this->module->l('ePOD (semnatura electronica, in PDA)'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_CONTENT',
                        'label' => $this->module->l('Default values for "Content"'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'label',
                            'id' => 'id',
                            'query' => [
                                [
                                    'id' => 'ORDER_REFERENCE',
                                    'val' => 'ORDER_REFERENCE',
                                    'label' => $this->module->l('Order reference'),
                                ],
                                [
                                    'id' => 'INVOICE_NUMBER',
                                    'val' => 'INVOICE_NUMBER',
                                    'label' => $this->module->l('Invoice number'),
                                ],
                                [
                                    'id' => 'PRODUCTS_LIST',
                                    'val' => 'PRODUCTS_LIST',
                                    'label' => $this->module->l('Products list'),
                                ],
                            ],
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_WEIGHT',
                        'label' => $this->module->l('Default value for "weight"'),
                        'desc' => $this->module->l('By default, the weight sent to selfawb is the sum of all product weights. If you fill this field, it will send this value instead.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_ADDED_PERCENT_TOTAL_WEIGHT',
                        'label' => $this->module->l('Add percentage to total weight'),
                        'prefix' => $this->module->l('%'),
                        'desc' => $this->module->l('Adds a percentage to the calculated total weight before sending to selfawb.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_ADDED_NUMBER_TOTAL_WEIGHT',
                        'label' => $this->module->l('Add number to total weight'),
                        'desc' => $this->module->l('Adds a fixed value (kg) to the calculated total weight before sending to selfawb.'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_WIDTH',
                        'label' => $this->module->l('Default value for "width"'),
                        'desc' => $this->module->l('This value will be used as default for box width, if the products don\'t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_LENGTH',
                        'label' => $this->module->l('Default value for "length"'),
                        'desc' => $this->module->l('This value will be used as default for box length, if the products don\'t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_DEFAULT_HEIGHT',
                        'label' => $this->module->l('Default value for "height"'),
                        'desc' => $this->module->l('This value will be used as default for box height, if the products don\'t have dimensions set.'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'RC_FANCOURIER_AWB_FORMAT',
                        'class' => 't',
                        'label' => $this->module->l('AWB Format'),
                        'values' => [
                            [
                                'id' => 'A4',
                                'value' => 'A4',
                                'label' => $this->module->l('A4'),
                            ],
                            [
                                'id' => 'A5',
                                'value' => 'A5',
                                'label' => $this->module->l('A5'),
                            ],
                            [
                                'id' => 'A6',
                                'value' => 'A6',
                                'label' => $this->module->l('A6'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Warning if there is no invoice?'),
                        'name' => 'RC_FANCOURIER_NOINV_WARNING',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Show a warning message in AWB generation form if there is no invoice generated?'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => $switch_type,
                        'label' => $this->module->l('Log errors?'),
                        'name' => 'RC_FANCOURIER_LOG_ERRORS',
                        'class' => 't',
                        'is_bool' => true,
                        'desc' => $this->module->l('Useful for debugging, turn OFF if you are low on disk. All errors will be saved in modules/rc_fancourier/log/errors.txt (Active only for price calculation and AWB generation)'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->module->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->module->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'free',
                        'name' => 'RC_FANCOURIER_FREETEXT5',
                        'label' => $this->module->l('Update Cities & Offices'),
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function getConfigFormForStates()
    {
        $order_states_list = $this->getOrderStates();
        $return = [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Configure order states'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'RC_FANCOURIER_OS_DAYS',
                        'label' => $this->module->l('Days to check'),
                        'desc' => $this->module->l('Only check awbs created in the last X days. Recommended: 14'),
                        'validate' => 'isFloat',
                    ],
                    [
                        'col' => 3,
                        'type' => 'checkbox',
                        'name' => 'RC_FANCOURIER_OS_IGNORE',
                        'label' => $this->module->l('Ignore orders with this states'),
                        'desc' => $this->module->l('Orders which have one of this states will be ignored from the check, so their status will be not modified. We recommend you to ignore canceled or finished orders.'),
                        'multiple' => true,
                        'values' => [
                            'name' => 'name',
                            'id' => 'id_order_state',
                            'query' => $order_states_list,
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                ],
            ],
        ];

        $fan_states = RcFanCourierAwb::getFanStatesList();
        $order_states_list = array_merge([['id_order_state' => 0, 'name' => $this->module->l('- Do not change -')]], $order_states_list);

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
            'label' => $this->module->l('Days to check for bank transfers'),
            'desc' => $this->module->l('Only check transfers made in the last X days. Recommended: 1 if you have a lot of Locations, 7 if you have less Locations'),
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

    /**
     * @return array
     */
    public function getOrderStates()
    {
        return OrderState::getOrderStates(Context::getContext()->language->id);
    }

    /**
     * Set values for the inputs.
     *
     * @return array
     */
    public function getConfigFormValues()
    {
        $context = Context::getContext();
        $modulePath = _PS_MODULE_DIR_ . 'rc_fancourier/';

        $context->smarty->assign([
            'groups' => Group::getGroups($context->language->id),
            'RC_FANCOURIER_FREE_GROUPS' => json_decode(Configuration::get('RC_FANCOURIER_FREE_GROUPS'), true),
            'RC_FANCOURIER_LOC_LUPD' => (Configuration::get('RC_FANCOURIER_LOC_LUPD') ? Configuration::get('RC_FANCOURIER_LOC_LUPD') : $this->module->l('never')),
        ]);
        $return = [
            'RC_FANCOURIER_ACTIVE' => Configuration::get('RC_FANCOURIER_ACTIVE'),
            'RC_FANCOURIER_CITY_SELECTOR' => Configuration::get('RC_FANCOURIER_CITY_SELECTOR'),
            'RC_FANCOURIER_METHOD' => Configuration::get('RC_FANCOURIER_METHOD'),
            'RC_FANCOURIER_USERNAME' => Configuration::get('RC_FANCOURIER_USERNAME'),
            'RC_FANCOURIER_PASSWORD' => Configuration::get('RC_FANCOURIER_PASSWORD'),
            'RC_FANCOURIER_SENDER_NAME' => Configuration::get('RC_FANCOURIER_SENDER_NAME'),
            'RC_FANCOURIER_SENDER_PHONE' => Configuration::get('RC_FANCOURIER_SENDER_PHONE'),
            'RC_FANCOURIER_SENDER_EMAIL' => Configuration::get('RC_FANCOURIER_SENDER_EMAIL'),
            'RC_FANCOURIER_LOCAL_INITIALCOST' => Configuration::get('RC_FANCOURIER_LOCAL_INITIALCOST'),
            'RC_FANCOURIER_LOCAL_COD_TAX' => Configuration::get('RC_FANCOURIER_LOCAL_COD_TAX'),
            'RC_FANCOURIER_KM_COST' => Configuration::get('RC_FANCOURIER_KM_COST'),
            'RC_FANCOURIER_KM_FIX_COST' => Configuration::get('RC_FANCOURIER_KM_FIX_COST'),
            'RC_FANCOURIER_KG_COST' => Configuration::get('RC_FANCOURIER_KG_COST'),
            'RC_FANCOURIER_KG_COST_FROM' => Configuration::get('RC_FANCOURIER_KG_COST_FROM'),
            'RC_FANCOURIER_KM_FIX_COST_ADD_KG' => Configuration::get('RC_FANCOURIER_KM_FIX_COST_ADD_KG'),
            'RC_FANCOURIER_FREE_FROM' => Configuration::get('RC_FANCOURIER_FREE_FROM'),
            'RC_FANCOURIER_ADDED_PERCENT_TOTAL_WEIGHT' => Configuration::get('RC_FANCOURIER_ADDED_PERCENT_TOTAL_WEIGHT'),
            'RC_FANCOURIER_ADDED_NUMBER_TOTAL_WEIGHT' => Configuration::get('RC_FANCOURIER_ADDED_NUMBER_TOTAL_WEIGHT'),
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
            'RC_FANCOURIER_DEFAULT_DROPOFF_ENABLED' => Configuration::get('RC_FANCOURIER_DEFAULT_DROPOFF_ENABLED'),
            'RC_FANCOURIER_DEFAULT_DROPOFF_ID' => Configuration::get('RC_FANCOURIER_DEFAULT_DROPOFF_ID'),
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
            'RC_FANCOURIER_FREETEXT1' => $context->smarty->fetch($modulePath . 'views/templates/admin/form_freetext1.tpl'),
            'RC_FANCOURIER_FREETEXT2' => $context->smarty->fetch($modulePath . 'views/templates/admin/form_freetext2.tpl'),
            'RC_FANCOURIER_FREETEXT3' => $context->smarty->fetch($modulePath . 'views/templates/admin/form_freetext3.tpl'),
            'RC_FANCOURIER_FREETEXT4' => $context->smarty->fetch($modulePath . 'views/templates/admin/form_freetext4.tpl'),
            'RC_FANCOURIER_FREETEXT5' => $context->smarty->fetch($modulePath . 'views/templates/admin/form_freetext5.tpl'),
            'RC_FANCOURIER_FREE_GROUPS' => $context->smarty->fetch($modulePath . 'views/templates/admin/free_s_per_group.tpl'),
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

    /**
     * @return array
     */
    public function getConfigFormValuesForStates()
    {
        $return = [
            'RC_FANCOURIER_OS_DAYS' => Configuration::get('RC_FANCOURIER_OS_DAYS'),
            'RC_FANCOURIER_OS_TRANSFER_DAYS' => Configuration::get('RC_FANCOURIER_OS_TRANSFER_DAYS'),
        ];

        $order_states_ignore = Configuration::get('RC_FANCOURIER_OS_IGNORE');
        $order_states_ignore_array = explode(',', $order_states_ignore);
        $order_states = $this->getOrderStates();
        foreach ($order_states as $order_state) {
            if (in_array($order_state['id_order_state'], $order_states_ignore_array)) {
                $return['RC_FANCOURIER_OS_IGNORE_' . $order_state['id_order_state']] = 1;
            }
        }

        $fan_order_states = RcFanCourierAwb::getFanStatesList();
        foreach (array_keys($fan_order_states) as $id_fan_state) {
            $return['RC_FANCOURIER_OS_MAP_' . $id_fan_state] = Configuration::get('RC_FANCOURIER_OS_MAP_' . $id_fan_state);
        }

        $return['RC_FANCOURIER_OS_MAP_TRANSFER'] = Configuration::get('RC_FANCOURIER_OS_MAP_TRANSFER');

        return $return;
    }

    /**
     * Save form data.
     *
     * @return array Validation errors
     */
    public function postProcess()
    {
        $form = $this->getConfigForm();
        $errors = [];
        foreach ($form['form']['input'] as $key => $array) {
            if (isset($array['validate']) && $array['validate']) {
                $validate = $array['validate'];
                if (Tools::getValue($array['name']) && !Validate::$validate(Tools::getValue($array['name']))) {
                    $errors[] = sprintf($this->module->l('"%s" has to be a float value'), $array['label']);
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
        $groups = Group::getGroups(Context::getContext()->language->id);
        $groups_selected = [];
        foreach ($groups as $key => $group) {
            if (Tools::getValue('RC_FANCOURIER_NO_FREESHIPPING_' . $group['id_group'])) {
                $groups_selected[] = $group['id_group'];
            }
        }
        Configuration::updateValue('RC_FANCOURIER_NO_FREESHIPPING', implode(',', $groups_selected));

        $free_shipping_per_group_array = [];

        $amounts = Tools::getValue('rc_fancourier_free_shipping_group_amount');
        $taxes = Tools::getValue('rc_fancourier_free_shipping_group_tax');

        if (is_array($amounts)) {
            foreach ($amounts as $key => $val) {
                $free_shipping_per_group_array[$key] = [
                    'amount' => (float) $val,
                    'with_tax' => (is_array($taxes) && isset($taxes[$key])) ? (int) $taxes[$key] : 0,
                ];
            }
        }

        if (!empty($free_shipping_per_group_array)) {
            Configuration::updateValue(
                'RC_FANCOURIER_FREE_GROUPS',
                json_encode($free_shipping_per_group_array)
            );
        }

        return $errors;
    }

    /**
     * Save states form data.
     *
     * @return array Validation errors
     */
    public function postProcessStates()
    {
        $form = $this->getConfigFormForStates();
        $errors = [];
        foreach ($form['form']['input'] as $key => $array) {
            if (isset($array['validate']) && $array['validate']) {
                $validate = $array['validate'];
                if (Tools::getValue($array['name']) && !Validate::$validate(Tools::getValue($array['name']))) {
                    $errors[] = sprintf($this->module->l('"%s" has to be a float value'), $array['label']);
                    continue;
                }
            }
            if ($array['name'] != 'RC_FANCOURIER_OS_IGNORE') {
                Configuration::updateValue($array['name'], Tools::getValue($array['name']));
            }
        }
        $order_states = $this->getOrderStates();
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

        return $errors;
    }
}
