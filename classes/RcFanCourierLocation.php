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
 * Cities, offices, locations DB operations.
 */
class RcFanCourierLocation
{
    /**
     * @return array|false
     */
    public static function getLocations()
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_locations` ORDER BY `position` ASC');
    }

    /**
     * @return string|false
     */
    public static function getDefaultClientId()
    {
        return Db::getInstance()->getValue('SELECT `client_id` FROM `' . _DB_PREFIX_ . 'rc_fancourier_locations` ORDER BY `position` ASC');
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public static function buildLocationsArrayFromApi($array)
    {
        $locations = [];
        foreach ($array as $row) {
            $locations[] = [
                'client_id' => $row['id'],
                'label' => $row['name'],
            ];
        }

        return $locations;
    }

    /**
     * @param Address $address_delivery
     *
     * @return string
     */
    public static function getClientIdBasedOnAddress($address_delivery)
    {
        $locations = self::getLocations();
        $location_selected = '';
        foreach ($locations as $location) {
            if (!$location_selected && Tools::strtolower($location['label']) == Tools::strtolower($address_delivery->city)) {
                $location_selected = $location['client_id'];
            }
            if (!$location_selected && Tools::strtolower($location['label']) == Tools::strtolower(State::getNameById($address_delivery->id_state))) {
                $location_selected = $location['client_id'];
            }
        }
        if (!$location_selected) {
            $location_selected = self::getDefaultClientId();
        }

        return $location_selected;
    }

    /**
     * @param int|string $state
     * @param string $city
     *
     * @return int|false
     */
    public static function getExtraKm($state, $city)
    {
        if (is_numeric($state)) {
            $state = State::getNameById($state);
        }
        $extra_km = Db::getInstance()->getValue('SELECT `km` FROM `' . _DB_PREFIX_ . 'rc_fancourier_cities` WHERE `judet` = "' . pSQL($state) . '" AND `localitate` = "' . pSQL($city) . '"');
        if ($extra_km === false) {
            return $extra_km;
        }

        return (int) $extra_km;
    }

    /**
     * @return array|false
     */
    public static function getOffices()
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` ORDER BY `strada` ASC');
    }

    /**
     * @return array|false
     */
    public static function getFanboxes()
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'rc_fancourier_offices` WHERE `type` = "fanbox" ORDER BY `judet` ASC, `strada` ASC'
        );
    }

    /**
     * @return bool
     */
    public static function clearOffices()
    {
        return Db::getInstance()->delete('rc_fancourier_offices');
    }

    /**
     * @param array $offices
     * @param string $type
     *
     * @return bool
     */
    public static function addOffices($offices, $type)
    {
        $return = true;
        $offices_array = [];
        foreach ($offices as $office) {
            if (!isset($offices_array[$office['routingLocation']])) {
                $offices_array[$office['routingLocation']] = $office;
            }
        }
        foreach ($offices_array as $office) {
            $return &= Db::getInstance()->insert('rc_fancourier_offices', [
                'id_office' => pSQL($office['id']),
                'judet' => pSQL($office['address']['county']),
                'localitate' => pSQL($office['address']['locality']),
                'strada' => pSQL($office['routingLocation']),
                'cod_postal' => pSQL($office['address']['zipCode']),
                'agentie' => pSQL($office['address']['locality']),
                'type' => pSQL($type),
                'row' => pSQL(json_encode($office)),
            ]);
        }

        return $return;
    }

    /**
     * @return bool
     */
    public static function clearCities()
    {
        return Db::getInstance()->delete('rc_fancourier_cities');
    }

    /**
     * @param array $cities
     *
     * @return bool
     */
    public static function addCities($cities)
    {
        $return = true;
        $insert_data = [];
        foreach ($cities as $city) {
            if ($city['name'] == 'Bucuresti') {
                for ($sector = 1; $sector <= 6; ++$sector) {
                    $insert_data[] = [
                        'id' => $city['id'] * 10000 + $sector,
                        'judet' => $city['county'],
                        'localitate' => 'Sector ' . $sector,
                        'agentie' => $city['agency'],
                        'km' => $city['exteriorKm'],
                    ];
                }
            } else {
                $insert_data[] = [
                    'id' => $city['id'],
                    'judet' => $city['county'],
                    'localitate' => $city['name'],
                    'agentie' => $city['agency'],
                    'km' => $city['exteriorKm'],
                ];
            }
        }
        $return &= Db::getInstance()->insert('rc_fancourier_cities', $insert_data);

        return (bool) $return;
    }
}
