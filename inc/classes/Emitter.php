<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 07.03.18
 * Time: 17:45
 */

namespace Core2;


class Emitter
{
    private $module;
    private $events = [];
    private $that;

    public function __construct(\Common $that, $module_id)
    {
        $this->module = $module_id;
        $this->that = $that;
    }

    public function addEvent($event_name) {
        if (!in_array($event_name, $this->events)) $this->events[] = $event_name;
    }

    public function emit() {
        $mods = $this->that->modAdmin->dataModules->getIds();
        $auth = \Zend_Registry::get('auth');
        foreach ($mods as $id => $mod) {
            if ($mod === $this->module) continue;
            if ($auth->MOBILE) {
                $modController = "Mobile" . ucfirst($mod) . "Controller";
            } else {
                $modController = "Mod" . ucfirst($mod) . "Controller";
            }
            $location = $this->that->getModuleLocation($mod);
            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $controller_path = $location . "/" . $modController . ".php";
            if (file_exists($controller_path)) {
                require_once $controller_path;
                $iface = class_implements($modController);
                if (!in_array('Subscribe', class_implements($modController))) continue;
                $obj = new $modController();
                foreach ($this->events as $event) {
                    $obj->listen($this->module, $event);
                }
            }
        }
    }
}