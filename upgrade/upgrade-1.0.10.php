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

function upgrade_module_1_0_10($module)
{
    return Configuration::updateValue('RC_FANCOURIER_API_MULTIPLICATOR', 1)
        && Configuration::updateValue('RC_FANCOURIER_INCLUDE_DECLARED', 1);
}
