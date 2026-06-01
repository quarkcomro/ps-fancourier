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

function upgrade_module_2_0_4($module)
{
    return Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'rc_fancourier_offices` CHANGE `id_office` `id_office` VARCHAR(32) NOT NULL, ADD `type` VARCHAR(32) NOT NULL AFTER `agentie`, ADD `row` TEXT NOT NULL AFTER `type`, ADD INDEX (`type`);');
}
