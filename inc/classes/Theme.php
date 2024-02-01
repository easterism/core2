<?php

namespace Core2;

class Theme
{
    private static $_tpl = [];

    public static function get($name) {
        $tpl = isset(self::$_tpl[$name]) ? self::$_tpl[$name] : '';
        return $tpl;
    }

    public static function set($theme = 'default', $json) {
        $tpl = json_decode($json, true);
        foreach ($tpl as $key => $path) {
            if (strpos($path, "/") !== 0) {
                $path = __DIR__ . "/../../html/$theme/$path";
            }
            self::$_tpl[$key] = $path;
        }
    }
}