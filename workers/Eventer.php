<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Db.php';

class Eventer
{

    private $_config;

    public function __construct()
    {
        $this->_config = Registry::get('config');
    }


    /**
     * @param \GearmanJob|Job $job
     * @param array       $log
     */
    public function run(\GearmanJob|Job $job, array &$log) {

        $workload = json_decode($job->workload());

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        $_SERVER = get_object_vars($workload->server);

        $mod     = $workload->payload->mod;
        $context = $workload->payload->context;
        $event  = $workload->payload->event;
        $data   = $workload->payload->data;

        $modController = "Mod" . ucfirst($mod) . "Controller";

        $location = $workload->payload->location;

        $controller_path = $location . "/" . $modController . ".php";

        if (file_exists($controller_path)) {
            $autoload = $location . "/vendor/autoload.php";
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            require_once $controller_path;

            if ( ! in_array('Subscribe', class_implements($modController))) {
                throw new \Exception("$modController has no Subscribe interface");
            }
            $controller = new $modController();
            $res = $controller->listen($context, $event, $data);
            if ($res) {
                $log[] = is_scalar($res) ? $res : json_encode($res);
            }
            return true;
        }
    }
}