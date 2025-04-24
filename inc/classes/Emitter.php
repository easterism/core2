<?php
namespace Core2;
require_once 'Db.php';

/**
 * Class Emitter
 * @package Core2
 */
class Emitter extends Db {

    private $events = [];
    private $subscribers = [];


    /**
     * Emitter constructor.
     * @param \Common $that
     * @param         $module_id
     */
    public function __construct() {
        parent::__construct();
        $this->module = 'admin';
        $mods = $this->dataModules->getIds();
        $out  = [];

        foreach ($mods as $id => $mod) {
            $modController = "Mod" . ucfirst($mod) . "Controller";

            $location = $this->getModuleLocation($mod);

            $controller_path = $location . "/" . $modController . ".php";

            if (file_exists($controller_path)) {
                $autoload = $location . "/vendor/autoload.php";
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
                require_once $controller_path;
                $iface = class_implements($modController);

                if ( ! in_array('Subscribe', class_implements($modController))) continue;
                $this->subscribers[$mod] = new $modController();
            }
        }
    }


    /**
     * ищет событие у подписчиков
     * @return array результат от всех подписчиков
     * @throws \Zend_Exception
     * @throws \Exception
     */
    public function emit($module, $event_name, $data): array {

        $out  = [];
        foreach ($this->subscribers as $mod => $controller) {
            //TODO запустить паралельно
            $res = $controller->listen($module, $event_name, $data);
            if ($res) $out[$mod] = $res;
        }
//        $this->log->info(is_array($data) ? json_encode($data) : $data, ['module' => $module, 'event' => $event_name]);
        return $out;
    }

}