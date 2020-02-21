<?php
namespace Core2;

/**
 * Class Emitter
 * @package Core2
 */
class Emitter {

    private $module;
    private $events = [];
    private $that;


    /**
     * Emitter constructor.
     * @param \Common $that
     * @param         $module_id
     */
    public function __construct(\Common $that, $module_id) {

        $this->module = $module_id;
        $this->that   = $that;
    }


    /**
     * @param string $event_name
     * @param array  $data
     */
    public function addEvent($event_name, $data = []) {

        if ( ! array_key_exists($event_name, $this->events)) {
            $this->events[$event_name] = $data;
        }
    }


    /**
     * @return array
     * @throws \Zend_Exception
     * @throws \Exception
     */
    public function emit() {

        $mods = $this->that->modAdmin->dataModules->getIds();
        $auth = \Zend_Registry::get('auth');
        $out  = [];

        foreach ($mods as $id => $mod) {
            if ( ! empty($auth->MOBILE) && $auth->MOBILE) {
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

                if ( ! in_array('Subscribe', class_implements($modController))) continue;
                $obj = new $modController();

                foreach ($this->events as $event => $data) {
                    $res = $obj->listen($this->module, $event, $data);
                    if ($res) $out[] = $res;
                }
            }
        }

        return $out;
    }
}