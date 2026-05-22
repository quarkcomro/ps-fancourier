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

function upgrade_module_1_0_7($module)
{
    return Db::getInstance()->update('carrier', ['url' => $module::$tracking_link], '`external_module_name` = "' . pSQL($module->name) . '"')
        && Configuration::updateValue('RC_FANCOURIER_UPDATETRACKING', 1)
        && Configuration::updateValue('RC_FANCOURIER_UPDTRACKING_EMAIL', 1);
}
