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

function upgrade_module_1_1_0($module)
{
    return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rc_fancourier_offices` (
      `id_office` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `judet` varchar(255) NOT NULL,
      `localitate` varchar(255) NOT NULL,
      `strada` varchar(255) NOT NULL,
      `cod_postal` varchar(255) NOT NULL,
      `agentie` varchar(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;') && $module->updateLocations();
}
