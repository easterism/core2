<?php

namespace Core2;

class Router
{

    public static $route = [];

    public function __construct() {
        $this->routeParse();
        return self::$route;
    }

    /**
     * Основной роутер
     */
    private function routeParse() {
        $temp  = explode("/", DOC_PATH);
        $temp2 = explode("/", $_SERVER['REQUEST_URI']);
        foreach ($temp as $k => $v) {
            if (isset($temp2[$k]) && $temp2[$k] == $v) {
                unset($temp2[$k]);
            }
        }
        reset($temp2);
        if (current($temp2) === 'api') {
            unset($temp2[key($temp2)]);
        } //TODO do it for SOAP

        $route = array(
            'module'  => '',
            'action'  => 'index',
            'params'  => array(),
            'query'   => $_SERVER['QUERY_STRING']
        );

        $co = count($temp2);
        if ($co) {
            if ($co > 1) {
                $i = 0;
                //если мы здесь, значит хотим вызвать API
                foreach ($temp2 as $k => $v) {
                    if ($i == 0) {
                        $route['api'] = strtolower($v);
                        $_GET['module'] = $route['api']; //DEPRECATED
                    }
                    elseif ($i == 1) {
                        if (!$v) $v = 'index';
                        $vv  = explode("?", $v);
                        $route['action'] = strtolower($vv[0]);
                    }
                    else {
                        if (!ceil($i%2)) {
                            $v = explode("?", $v);
                            if (isset($v[1])) {
                                $route['params'][$v[0]] = '';
                                $_GET[$v[0]] = ''; //DEPRECATED
                            } else {
                                if (isset($temp2[$k + 1])) {
                                    $vv          = explode("?", $temp2[$k + 1]);
                                    $route['params'][$v[0]] = $vv[0];
                                    $_GET[$v[0]] = $vv[0]; //DEPRECATED

                                } else {
                                    $route['params'][$v[0]] = '';
                                    $_GET[$v[0]] = ''; //DEPRECATED
                                }
                            }
                        }
                    }
                    $i++;
                }
            } else {
                //в адресе нет глубины
                $vv  = explode("?", current($temp2));
                if (!empty($vv[1])) {
                    parse_str($vv[1], $_GET);
                }
                $route['module'] = $vv[0];
                if (!$route['module'] || $route['module'] == 'index.php') { //DEPRECATED
                    // FIXME Убрать модуль и экшен по умолчанию
                    $route['module'] = !empty($_GET['module']) ? $_GET['module'] : 'admin';
                }
                $route['action'] = !empty($_GET['action']) ? $_GET['action'] : 'index';
            }
        }
        self::$route = $route;
        Registry::set('route', $route);
    }

}