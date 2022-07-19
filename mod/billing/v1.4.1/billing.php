<?php

header("Content-Type: text/html; charset=UTF-8");


$system = ! empty($_GET['system']) ? $_GET['system'] : '';
$apikey = ! empty($_GET['apikey']) ? $_GET['apikey'] : '';
$host   = ! empty($_GET['host'])   ? $_GET['host']   : '';
$action = ! empty($_GET['action']) ? $_GET['action'] : '';


//$logger = new Logger('/var/log/xxxxxxxxxx/billing.log');
//$logger->info($host .  ' ' . $system . ' ' . $_SERVER['REQUEST_METHOD'], $_REQUEST);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'notify') {
        switch ($system) {
            case 'webpay':
                $additional_params = $_POST;
                $additional_params['action'] = 'notify';
                $billing    = new Billing_Gateway($host, $apikey);
                $result_raw = $billing->sendOperation($system, $additional_params, 'post');

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

    } else {
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
    }


} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    switch ($system) {
        case 'webpay':
            $additional_params = $_GET;
            switch ($action) {
                case 'success':
                    $billing = new Billing_Gateway($host, $apikey);
                    echo $billing->processOperation($system, $additional_params);
                    break;

                case 'cancel':
                    $billing = new Billing_Gateway($host, $apikey);
                    echo $billing->processOperation($system, $additional_params);
                    break;
            }
            break;
    }
}





/**
 * Class Billing_Gateway
 */
class Billing_Gateway {

    private $host;
    private $apikey;


    /**
     * Billing_Gateway constructor.
     * @param string $host
     * @param string $apikey
     */
    public function __construct($host, $apikey) {
        $this->host   = $host;
        $this->apikey = $apikey;
    }


    /**
     * @param string $system_name
     * @param array  $additional_payment
     * @return string
     */
    public function processOperation($system_name, $additional_payment = array()) {

        $result_raw = $this->sendOperation($system_name, $additional_payment);
        $result     = json_decode($result_raw, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($result['status']) && $result['status'] == 'success') {
                $content = "<script>window.location.href = '{$this->host}/index.php#module=billing';</script>" .
                    "Перенаправление на <a href=\"{$this->host}/index.php#module=billing\">{$this->host}/index.php#module=billing</a>";
            } else {
                $content = isset($result['error_code'])
                    ? '<h3>Ошибка.</h3><br> ' . $result['message']
                    : '<h3>Ошибка.</h3><br> Проверьте правильность указанного адреса.';
            }
        } else {
            $content = '<h3>Сервис временно недоступен, попробуйте пожалуйста позже.</h3><br>' . $result_raw;
        }
        return $content;
    }


    /**
     * @param string $system
     * @param array  $additional_params
     * @param string $method
     * @return string
     */
    public function sendOperation($system, $additional_params = array(), $method = 'get') {

        $headers = array(
            "Core-Billing-Hash: " . md5_file(__FILE__)
        );

        $params = $additional_params;
        $params['system'] = $system;
        $params['apikey'] = $this->apikey;

        if ($method == 'post') {
            $result = Curl_Gateway::post($this->host . '/api/billing/operations', $params, $headers);
        } else {
            $result = Curl_Gateway::get($this->host . '/api/billing/operations', $params, $headers);
        }
        return $result;
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

/**
 * Class Logger
 */
class Logger {
    private $stream;

    /**
     * Logger constructor.
     * @param $file
     */
    public function __construct($file) {
        $this->stream = @fopen($file, 'a');
    }


    /**
     * @param string $header_string
     * @param array  $params
     * @return bool|int
     */
    public function info($header_string, $params = array()) {
        return $this->write("[" . date('Y-m-d h:i:s') . "] access.INFO: " . $header_string .  " " . json_encode($params) .  "\r\n");
    }


    /**
     * @param $string
     * @return bool|int
     */
    private function write($string) {
        if ($this->stream) {
            return @fwrite($this->stream, $string);
        } else {
            return false;
        }
    }
}