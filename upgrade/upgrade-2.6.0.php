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
 * @author    GURU CODERS SRL <stickyrst@gmail.com>
 * @copyright 2018-2026 GURU CODERS SRL
 * @license   Commercial
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_0($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'rc_fancourier_awbs';

    $col_exists = $db->getValue('
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = "' . pSQL($table) . '"
          AND COLUMN_NAME = "weights_json"
    ');

    if (!$col_exists) {
        return $db->execute('ALTER TABLE `' . $table . '` ADD COLUMN `weights_json` TEXT NULL AFTER `client_id`');
    }

    return true;
}
