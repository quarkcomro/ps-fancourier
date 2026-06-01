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

function upgrade_module_2_1_0($module)
{
    return Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart` (
            `id_cart` int(11) NOT NULL,
            `id_locker` varchar(32) NOT NULL,
            `locker_json` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'rc_fancourier_locker_cart`
            ADD PRIMARY KEY (`id_cart`)')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_locker_order` (
            `id_order` int(11) NOT NULL,
            `id_locker` varchar(32) NOT NULL,
            `locker_json` varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'rc_fancourier_locker_order`
            ADD PRIMARY KEY (`id_order`)')
        && $module->registerHook('displayCarrierExtraContent')
        && $module->registerHook('actionValidateStepComplete')
        && $module->registerHook('actionValidateOrder');
}
