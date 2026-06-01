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
 * Fan Courier REST API client — curl, auth tokens.
 */
class RcFanCourierApiClient
{
    /** @var string */
    public static $api_link = 'https://api.fancourier.ro/';

    /** @var int */
    public static $API_CONNECTTIMEOUT = 5;

    /** @var int */
    public static $API_TIMEOUT = 10;

    /** @var array */
    public static $api_token = [];

    /** @var array */
    public static $authDataOverride = [
        'client_id' => [
            'username' => 'username',
            'password' => 'password',
        ],
    ];

    /**
     * @param string $endpoint
     * @param array $data
     * @param string $requestType
     * @param bool $no_throw
     *
     * @return string|bool
     */
    public static function curlCall($endpoint, $data = [], $requestType = 'GET', $no_throw = false)
    {
        if (isset($data['client_id'])) {
            $client_id = $data['client_id'];
            if (isset(self::$authDataOverride[$client_id])) {
                $data['username'] = self::$authDataOverride[$client_id]['username'];
                $data['password'] = self::$authDataOverride[$client_id]['password'];
            }
        }
        if (isset($data['clientId'])) {
            $client_id = $data['clientId'];
            if (isset(self::$authDataOverride[$client_id])) {
                $data['username'] = self::$authDataOverride[$client_id]['username'];
                $data['password'] = self::$authDataOverride[$client_id]['password'];
            }
        }

        $token = self::getAPIToken($data, $no_throw);
        $curl = curl_init();
        $get_params = '';

        if ($requestType == 'GET' && $data) {
            $get_params = '?' . http_build_query($data);
        }

        $curl_opts = [
            CURLOPT_URL => self::$api_link . $endpoint . $get_params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => self::$API_CONNECTTIMEOUT,
            CURLOPT_TIMEOUT => self::$API_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => $requestType,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ];
        if ($requestType !== 'GET') {
            $json_body = json_encode($data);
            $curl_opts[CURLOPT_POSTFIELDS] = $json_body;
            $curl_opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $curl_opts);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    /**
     * @param array $data
     * @param bool $no_throw
     *
     * @return string|bool
     */
    public static function getAPIToken($data, $no_throw = false)
    {
        $data = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
        if (!self::$api_token || !isset(self::$api_token[$data['username']])) {
            $curl_call = curl_init(self::$api_link . 'login');
            curl_setopt($curl_call, CURLOPT_POST, true);
            curl_setopt($curl_call, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl_call, CURLOPT_CONNECTTIMEOUT, self::$API_CONNECTTIMEOUT);
            curl_setopt($curl_call, CURLOPT_TIMEOUT, self::$API_TIMEOUT);
            curl_setopt($curl_call, CURLOPT_RETURNTRANSFER, true);
            $curl_result = curl_exec($curl_call);
            curl_close($curl_call);

            if ($curl_result) {
                $curl_result_array = json_decode($curl_result, true);
                if (isset($curl_result_array['status']) && $curl_result_array['status'] == 'success' && isset($curl_result_array['data']) && isset($curl_result_array['data']['token'])) {
                    self::$api_token[$data['username']] = $curl_result_array['data']['token'];
                } else {
                    if (!$no_throw) {
                        throw new Exception('Could not get Fan Courier API Token');
                    }
                }
            }
        }

        return isset(self::$api_token[$data['username']]) ? self::$api_token[$data['username']] : false;
    }
}
