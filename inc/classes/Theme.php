<?php

namespace Core2;

class Theme
{
    private static array $_tpl  = [];
    private static array $model = [];


    /**
     * @param string $name
     * @return mixed|string
     */
    public static function get(string $name) {

        return self::$_tpl[$name] ?? '';
    }


    /**
     * @return array
     */
    public static function getModel(): array {
        return self::$model;
    }


    /**
     * @param string $theme
     * @param string $model_json
     * @return void
     */
    public static function setModel(string $theme, string $model_json): void {

        self::$model = json_decode($model_json, true);

        foreach (self::$model as $key => $path) {
            if ( ! str_starts_with($path, "/")) {
                $path = __DIR__ . "/../../html/$theme/$path";
            }
            self::$_tpl[$key] = $path;
        }
    }
}