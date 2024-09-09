<?php

namespace Core2;

class Theme
{
    private static array $_tpl  = [];
    private static array $model = [];

    public static function get($name) {
        $tpl = isset(self::$_tpl[$name]) ? self::$_tpl[$name] : '';
        return $tpl;
    }

    public static function setModel(string $theme, string $model_json): void
    {

        self::$model = json_decode($model_json, true);

        foreach (self::$model as $key => $path) {
            if (!str_starts_with($path, "/")) {
                $path = __DIR__ . "/../../html/$theme/$path";
            }
            self::$_tpl[$key] = $path;
        }
    }

    public static function getModel(): array {
        return self::$model;
    }
}