<?php

namespace Core2;

require_once("HttpException.php");

use BadMethodCallException;
use Exception;
use ModAdminApi;

class Api extends Acl
{

    /**
     * свойства текущего запроса
     * @var array
     */
    private static array $route = [];

    /**
     * параметры запроса
     * @param array $route
     */
    public function __construct(array $route) {
        parent::__construct();
        self::$route = $route;
    }

    /**
     * @return mixed
     */
    public function dispatchApi(): mixed {

        $module = self::$route['api'];
        $action = self::$route['action'];
        $resource = "{$module}_{$action}";

        try {
            if ($_SERVER['REQUEST_METHOD'] == 'DELETE' && !empty(self::$route['query']) && !empty(self::$route['query']['table'])) {
                //fallback для уствревших модулей при удалении из браузера
                $del_table = (string)self::$route['query']['table'];
                $del_field = (string)(self::$route['query']['field'] ?? '');
                if (str_starts_with($del_table, 'mod_') || str_starts_with($del_table, 'core_')) {
                    //удаляют запись из таблицы
                    $route = self::$route;
                    $route['params'] = [
                        '_resource' => key($route['params']),
                        '_field' => $del_table . "." . $del_field,
                        '_value' => $route['query']['value']
                    ];
                    $route['query'] = '';
                    Registry::set('route', $route);
                    $module = 'admin';
                    $action = 'index';
                }
            }
            Registry::set('context', array($module, $action));

            if ($module == 'admin') {
                require_once 'core2/mod/admin/ModAdminApi.php';
                $coreController = new ModAdminApi();
                $action         = "action_" . $action;
                if (method_exists($coreController, $action)) {
                    $out = $coreController->$action();

                    if (is_array($out)) {
                        $out = json_encode($out);
                    }

                    return $out;
                } else {
                    $msg = sprintf($this->translate->tr("Метод %s не существует"), $action);
                    throw new BadMethodCallException($msg, 404);
                }
            }

            $this->checkModule($module, $action);

            $location      = $this->getModuleLocation($module);
            $modController = "Mod" . ucfirst(strtolower($module)) . "Api";
            $this->requireController($location, $modController);
            $modController = new $modController();

            $action        = "action_" . $action;
            if (method_exists($modController, $action)) {
                $out = $modController->$action();
                if (is_array($out)) {
                    $out = json_encode($out);
                }
                return $out;
            } else {
                $msg = sprintf($this->translate->tr("Метод %s не существует"), $action);
                throw new BadMethodCallException($msg, 404);
            }

        } catch (HttpException $e) {
            return Error::catchJsonException([
                'msg'  => $e->getMessage(),
                'code' => $e->getErrorCode(),
            ], $e->getCode() ?: 500);

        }
    }

    /**
     * Проверка наличия и целостности файла контроллера
     *
     * @param $location - путь до файла
     * @param $apiController - название файла контроллера
     *
     * @throws Exception
     */
    private function requireController(string $location, string $apiController): void {
        $controller_path = $location . "/" . $apiController . ".php";
        if (!file_exists($controller_path)) {
            $msg = sprintf($this->translate->tr("Модуль %s не найден"), $apiController);
            throw new Exception($msg, 404);
        }
        $autoload = $location . "/vendor/autoload.php";
        if (file_exists($autoload)) { //подключаем автозагрузку если есть
            require_once $autoload;
        }
        require_once $controller_path; // подлючаем контроллер
        if (!is_subclass_of($apiController, "CommonApi")) {
            $msg = sprintf($this->translate->tr("Класс %s не наследует CommonApi"), $apiController);
            throw new Exception($msg, 500);
        }
        if (!class_exists($apiController)) {
            $msg = sprintf($this->translate->tr("Модуль %s сломан"), $location);
            throw new Exception($msg, 500);
        }
    }

}