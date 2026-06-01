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

function upgrade_module_1_3_2($module)
{
    return Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_awb_errors` (
            `id` int(11) NOT NULL,
            `id_order` int(11) NOT NULL,
            `error` TEXT NOT NULL,
            `date_add` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        ALTER TABLE `' . _DB_PREFIX_ . 'rc_fancourier_awb_errors`
            ADD PRIMARY KEY (`id`);

        ALTER TABLE `' . _DB_PREFIX_ . 'rc_fancourier_awb_errors`
            MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;');
}
