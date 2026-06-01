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
 * File + DB logging for Fan Courier operations.
 */
class RcFanCourierLogger
{
    /**
     * @param string $error
     * @param string|false $endpoint
     * @param array|false $data
     *
     * @return void
     */
    public static function logError($error, $endpoint = false, $data = false)
    {
        if (Configuration::get('RC_FANCOURIER_LOG_ERRORS')) {
            $text = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $error;
            if ($endpoint) {
                $text .= ' (' . $endpoint . ')';
            }
            if ($data) {
                $safe_data = is_array($data) ? $data : [];
                if (is_array($data)) {
                    foreach (['password', 'token', 'api_key'] as $sensitive) {
                        if (isset($safe_data[$sensitive])) {
                            $safe_data[$sensitive] = '***';
                        }
                    }
                }
                $text .= ' ' . json_encode($safe_data);
            }

            $log_file = dirname(__FILE__) . '/../log/errors.log.php';
            if (!file_exists($log_file)) {
                file_put_contents($log_file, "<?php exit('Access denied'); ?>" . PHP_EOL);
            }
            file_put_contents($log_file, $text . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * @param array $errors
     *
     * @return bool
     */
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
}
