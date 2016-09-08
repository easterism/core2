<?php

header("Content-Type: text/html; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if ( ! empty($_POST['billing_vars']) && !empty($_POST['billing_vars']['action'])) {
            $action = $_POST['billing_vars']['action'];
        } else {
            throw new Exception("<h3>Ошибка платежа!</h3><br> Не указан адрес патежной системы");
        }

        $form = "<form id=\"billing-form\" action=\"{$action}\" method=\"post\">";
        foreach ($_POST as $k => $value) {
            if ($k != 'billing_vars') {
                if (is_array($value)) {
                    foreach ($value as $k2 => $value2) {
                        if (is_array($value2)) {
                            foreach ($value2 as $k3 => $value3) {
                                if ( ! is_array($value3)) {
                                    $form .= "<input type=\"hidden\" name=\"{$k}[{$k2}][{$k3}]\" value=\"{$value3}\">";
                                }
                            }
                        } else {
                            $form .= "<input type=\"hidden\" name=\"{$k}[{$k2}]\" value=\"{$value2}\">";
                        }
                    }
                } else {
                    $form .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$value}\">";
                }
            }
        }
        $form .= '</form>';
        $scripts = "<script>document.getElementById('billing-form').submit();</script>";
        $content = $form . $scripts;

    } catch(Exception $e) {
        $content = $e->getMessage();
    }
    echo $content;


} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $action      = ! empty($_GET['action'])   ? $_GET['action']   : '';
    $system_name = ! empty($_GET['system'])   ? $_GET['system']   : '';
    $protocol    = ! empty($_GET['protocol']) ? $_GET['protocol'] : '';
    $host        = ! empty($_GET['host'])     ? $_GET['host']     : '';
    $apikey      = ! empty($_GET['apikey'])   ? $_GET['apikey']   : '';

    if ( ! in_array($protocol, array('http', 'https'))) {
        $protocol = 'http';
    }

    switch ($system_name) {
        case 'webpay':
            if (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], -11, 10) == '.webpay.by') {
                switch ($action) {
                    case 'success':
                        $additional_params = array(
                            'operation_num'  => ! empty($_GET['wsb_order_num']) ? $_GET['wsb_order_num'] : '',
                            'transaction_id' => ! empty($_GET['wsb_tid'])       ? $_GET['wsb_tid']       : '',
                        );

                        $billing = new Billing_Gateway($protocol, $host, $apikey);
                        echo $billing->processSuccess($system_name, $additional_params);
                        break;

                    case 'cancel':
                        $additional_params = array(
                            'operation_num' => ! empty($_GET['wsb_order_num']) ? $_GET['wsb_order_num']  : ''
                        );

                        $billing = new Billing_Gateway($protocol, $host, $apikey);
                        echo $billing->processCancel($system_name, $additional_params);
                        break;

                    case 'notify':
                        $additional_params = array(
                            'operation_num'  => ! empty($_GET['wsb_order_num']) ? $_GET['wsb_order_num']  : '',
                            'transaction_id' => ! empty($_GET['wsb_tid'])       ? $_GET['wsb_tid']        : '',
                        );

                        $billing    = new Billing_Gateway($protocol, $host, $apikey);
                        $result_raw = $billing->successOperation($system_name, $additional_params);

                        $result = json_decode($result_raw, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            if (isset($result['status']) && $result['status'] == 'success') {
                                header("HTTP/1.1 200 OK");
                            } else {
                                header("HTTP/1.1 400 Bad Request");
                            }
                            echo json_encode($result);

                        } else {
                            header("HTTP/1.1 500 Internal Server Error");
                            echo $result_raw;
                        }
                        break;
                }
            }
            break;
    }
}





/**
 * Class Billing_Gateway
 */
class Billing_Gateway {

    private $protocol;
    private $host;
    private $apikey;


    /**
     * Billing_Gateway constructor.
     * @param string $protocol
     * @param string $host
     * @param string $apikey
     */
    public function __construct($protocol, $host, $apikey) {
        $this->protocol = $protocol;
        $this->host     = $host;
        $this->apikey   = $apikey;
    }


    /**
     * @param string $system_name
     * @param array  $additional_payment
     * @return string
     */
    public function processSuccess($system_name, $additional_payment = array()) {

        $result_raw = $this->successOperation($system_name, $additional_payment);
        $result     = json_decode($result_raw, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($result['status']) && $result['status'] == 'success') {
                $content = "<script>window.location.href = '{$this->protocol}://{$this->host}/index.php#module=billing';</script>" .
                           "Перенаправление на <a href=\"{$this->protocol}://{$this->host}/index.php#module=billing\">{$this->protocol}://{$this->host}/index.php#module=billing</a>";
            } else {
                $content = isset($result['error_code'])
                    ? '<h3>Ошибка.</h3><br> ' . $result['message']
                    : '<h3>Ошибка.</h3><br> Проверьте правильность указанного адреса.';
            }
        } else {
            $content = '<h3>Сервис временно недоступен, попробуйте пожалуйста позже.</h3><br> ' . $result_raw;
        }
        echo $content;
    }


    /**
     * @param string $system_name
     * @param array  $additional_payment
     * @return string
     */
    public function processCancel($system_name, $additional_payment = array()) {

        $result_raw = $this->cancelOperation($system_name, $additional_payment);
        $result     = json_decode($result_raw, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($result['status']) && $result['status'] == 'success') {
                $content = "<script>window.location.href = '{$this->protocol}://{$this->host}/index.php#module=billing';</script>" .
                           "Перенаправление на <a href=\"{$this->protocol}://{$this->host}/index.php#module=billing\">{$this->protocol}://{$this->host}/index.php#module=billing</a>";
            } else {
                $content = isset($result['error_code'])
                    ? '<h3>Ошибка.</h3><br> ' . $result['message']
                    : '<h3>Ошибка.</h3><br> Проверьте правильность указанного адреса.';
            }
        } else {
            $content = '<h3>Сервис временно недоступен, попробуйте пожалуйста позже.</h3><br>'. $result_raw;
        }
        echo $content;
    }


    /**
     * @param string $system_name
     * @param array  $additional_params
     * @return string
     */
    public function successOperation($system_name, $additional_params = array()) {

        $headers = array(
            'Core2-apikey: ' . $this->apikey,
            "Core-Billing-Hash: " . md5_file(__FILE__)
        );

        $params = $additional_params;
        $params['system_name'] = $system_name;

        return Curl_Gateway::post($this->protocol . '://' . $this->host . '/api/billing/success', $params, $headers);
    }


    /**
     * @param string $system_name
     * @param array  $additional_params
     * @return string
     */
    private function cancelOperation($system_name, $additional_params = array()) {

        $headers = array(
            'Core2-apikey: ' . $this->apikey,
            "Core-Billing-Hash: " . md5_file(__FILE__)
        );

        $params = $additional_params;
        $params['system_name'] = $system_name;

        return Curl_Gateway::post($this->protocol . '://' . $this->host . '/api/billing/cancel', $params, $headers);
    }
}



/**
 * Class Curl_Gateway
 */
class Curl_Gateway {

    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    public static function get($url, $params = array(), $headers = array()) {

        return self::request('get', $url, $params, $headers);
    }


    /**
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    public static function post($url, $params = array(), $headers = array()) {

        return self::request('post', $url, $params, $headers);
    }


    /**
     * @param string $method
     * @param string $url
     * @param array  $params
     * @param array  $headers
     * @return string
     */
    private static function request($method, $url, $params = array(), $headers = array()) {

        $ch = curl_init();

        if ( ! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= ! empty($params) ? '?' . http_build_query($params) : '';
        }

        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}