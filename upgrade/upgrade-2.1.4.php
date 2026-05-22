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

function upgrade_module_2_1_4($module)
{
    Configuration::updateValue('RC_FANCOURIER_SHOW_DIM', true);
    Configuration::updateValue('RC_FANCOURIER_FANBOX_MAP', true);
    Configuration::updateValue('RC_FANCOURIER_FANBOX_SHIPPING_COST', Configuration::get('RC_FANCOURIER_LOCAL_INITIALCOST'));

    return true;
}
