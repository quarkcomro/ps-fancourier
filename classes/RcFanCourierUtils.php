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
 * Pure utility functions — diacritics, currency, dates, paths.
 */
class RcFanCourierUtils
{
    /**
     * @param string $str
     *
     * @return string
     */
    public static function replaceDiacriticsChars($str)
    {
        $return = preg_replace(
            [
                /* Lowercase */
                '/[\x{0105}\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}]/u',
                '/[\x{00E7}\x{010D}\x{0107}]/u',
                '/[\x{010F}]/u',
                '/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{011B}\x{0119}]/u',
                '/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}]/u',
                '/[\x{0142}\x{013E}\x{013A}]/u',
                '/[\x{00F1}\x{0148}]/u',
                '/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}]/u',
                '/[\x{0159}\x{0155}]/u',
                '/[\x{015B}\x{0161}]/u',
                '/[\x{00DF}]/u',
                '/[\x{0165}]/u',
                '/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{016F}]/u',
                '/[\x{00FD}\x{00FF}]/u',
                '/[\x{017C}\x{017A}\x{017E}]/u',
                '/[\x{00E6}]/u',
                '/[\x{0153}]/u',

                /* Uppercase */
                '/[\x{0104}\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}]/u',
                '/[\x{00C7}\x{010C}\x{0106}]/u',
                '/[\x{010E}]/u',
                '/[\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{011A}\x{0118}]/u',
                '/[\x{0141}\x{013D}\x{0139}]/u',
                '/[\x{00D1}\x{0147}]/u',
                '/[\x{00D3}]/u',
                '/[\x{0158}\x{0154}]/u',
                '/[\x{015A}\x{0160}]/u',
                '/[\x{0164}]/u',
                '/[\x{00D9}\x{00DA}\x{00DB}\x{00DC}\x{016E}]/u',
                '/[\x{017B}\x{0179}\x{017D}]/u',
                '/[\x{00C6}]/u',
                '/[\x{0152}]/u',
            ],
            [
                'a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 'ss', 't', 'u', 'y', 'z', 'ae', 'oe',
                'A', 'C', 'D', 'E', 'L', 'N', 'O', 'R', 'S', 'T', 'U', 'Z', 'AE', 'OE',
            ],
            $str
        );

        $to_replace = ['Ț', 'Ă', 'Ș', 'ș', 'ă', 'â', 'î', 'ț', 'ţ', 'Ț', 'Î', 'Ă', 'Ș', 'Â'];
        $replace_with = ['T', 'A', 'S', 's', 'a', 'a', 'i', 't', 't', 'T', 'I', 'A', 'S', 'A'];

        $return = str_replace($to_replace, $replace_with, $return);

        return $return;
    }

    /**
     * @return bool
     */
    public static function isOrderPage()
    {
        if (!empty(Context::getContext()->controller->php_self)) {
            return stripos(Context::getContext()->controller->php_self, 'order') !== false || stripos(Context::getContext()->controller->php_self, 'checkout') !== false;
        }

        return stripos(Tools::getValue('module', 'none'), 'checkout') !== false;
    }

    /**
     * @param string $csv
     *
     * @return array
     */
    public static function parseCsv($csv)
    {
        $rows = array_map('str_getcsv', explode(PHP_EOL, $csv));
        $header = array_shift($rows);
        $csv = [];
        foreach ($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        return $csv;
    }

    /**
     * @return bool
     */
    public static function isPs15()
    {
        return version_compare(_PS_VERSION_, '1.6', '<');
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function trailingslashit($string)
    {
        return self::untrailingslashit($string) . '/';
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function untrailingslashit($string)
    {
        return rtrim($string, '/\\');
    }

    /**
     * @return string
     */
    public static function getTempDirPath()
    {
        if (function_exists('sys_get_temp_dir')) {
            $temp = sys_get_temp_dir();
            if (@is_dir($temp) && self::isWritable($temp)) {
                return self::trailingslashit($temp);
            }
        }
        $temp = ini_get('upload_tmp_dir');
        if (@is_dir($temp) && self::isWritable($temp)) {
            return self::trailingslashit($temp);
        }

        $temp = dirname(__FILE__) . '/';
        if (@is_dir($temp) && self::isWritable($temp)) {
            return $temp;
        }

        return '/tmp/';
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isWritable($path)
    {
        return @is_writable($path);
    }

    /**
     * @return array
     */
    public static function getPrivilegedCitiesArray()
    {
        $privileged_cities_array = [];
        $privileged_cities = Configuration::get('RC_FANCOURIER_PRVG_CITIES');
        if ($privileged_cities) {
            $privileged_cities_explode = explode(PHP_EOL, $privileged_cities);
            foreach ($privileged_cities_explode as $city) {
                $privileged_cities_array[] = Tools::strtolower(trim($city));
            }
        }

        return $privileged_cities_array;
    }

    /**
     * @param float|int $amount
     *
     * @return int|string
     */
    public static function formatDecimals($amount)
    {
        $amount = (float) $amount;
        if ($amount == (int) $amount) {
            return (int) $amount;
        }

        return number_format($amount, 2, '.', '');
    }

    /**
     * @param string $hex
     *
     * @return float|int|string
     */
    public static function getBrightness($hex)
    {
        if (method_exists('Tools', 'getBrightness')) {
            return Tools::getBrightness($hex);
        }

        /* For newer versions of PrestaShop */
        if (Tools::strtolower($hex) == 'transparent') {
            return '129';
        }

        $hex = str_replace('#', '', $hex);

        if (Tools::strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    }

    /**
     * @param string $start
     * @param string $end
     * @param string $format
     *
     * @return array
     */
    public static function getDatesFromRange($start, $end, $format = 'Y-m-d')
    {
        $array = [];
        $interval = new DateInterval('P1D');

        $realEnd = new DateTime($end);
        $realEnd->add($interval);

        $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

        foreach ($period as $date) {
            $array[] = $date->format($format);
        }

        return $array;
    }

    /**
     * @param float|int $amount
     * @param Context|null $context
     *
     * @return float|int
     */
    public static function convertFromRON($amount, $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        if (!Currency::getIdByIsoCode('RON')) {
            return $amount;
        }

        return Tools::convertPriceFull($amount, new Currency(Currency::getIdByIsoCode('RON')), $context->currency);
    }

    /**
     * @param float|int $amount
     * @param Context|null $context
     *
     * @return float|int
     */
    public static function convertToRON($amount, $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        if (!Currency::getIdByIsoCode('RON')) {
            return $amount;
        }

        return Tools::convertPriceFull($amount, $context->currency, new Currency(Currency::getIdByIsoCode('RON')));
    }
}
