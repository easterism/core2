<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Registry.php';
require_once __DIR__ . '/../inc/classes/Common.php';
require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/I18n.php';
require_once __DIR__ . '/../inc/classes/Core_Db_Adapter_Pdo_Mysql.php';

class Workhorse extends \Common
{

    private $_location;

    public function __construct(?array $params)
    {
        $this->module = strtolower($params['mod']);
        $this->_location = dirname($params['path']);
        Registry::set('context', [$this->module]);
        parent::__construct();
    }

    public function run(\GearmanJob|Job $job, &$log) {

        $handler = $job->handle();

        $workload = json_decode($job->workload());
        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        $_SERVER = get_object_vars($workload->server);
        $id = $_SERVER['SERVER_NAME'] . "|" . $job->unique();
        // Определяем DOCUMENT_ROOT (для прямых вызовов, например cron)
        if (!defined("DOC_ROOT")) {
            define("DOC_ROOT", dirname(str_replace("//", "/", $_SERVER['SCRIPT_FILENAME'])) . "/");
        }
        if (!defined("DOC_PATH")) {
            define("DOC_PATH", substr(DOC_ROOT, strlen(rtrim($_SERVER['DOCUMENT_ROOT'], '/'))) ? : '/');
        }

        $in_job = $this->db->fetchRow("SELECT 1 FROM core_worker_jobs WHERE id=? AND status != 'finish'", $id);
        if ($in_job) {
            //задача уже обрабатывается
            $log[] = "Job {$job->handle()} already in progress";
            return false;
        }
        $job->sendStatus(0, 100);
        $action = $workload->worker;

        $data = [
            'id' => $id,
            'time_start' => (new \DateTime())->format("Y-m-d H:i:s"),
            'handler' => $handler,
            'status' => 'start',
            'executor' => "Mod{$this->module}Worker->$action",
        ];
        $this->db->insert("core_worker_jobs", $data);

        //для того, чтоб модули внутри вызова Workhorse могли получить информацию о текущей задаче
        Registry::set('worker', [
            'request' => $_SERVER['REQUEST_URI'] ?? '',
            'module' => $this->module,
            'action' => $action,
            'job' => $id,
        ]);

        $log[] = "Start $action in context " . $this->module;
        $job->sendStatus(2, 100);

        Registry::set('auth',  $workload->auth);
        $out    = null;
        $error  = null;
        try {
            $out = $this->$action($job, $workload->payload);
            $log[] = "Finish $action in context " . $this->module;
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $job->sendStatus(100, 100);

        $this->db->update("core_worker_jobs", [
            'time_finish' =>  (new \DateTime())->format("Y-m-d H:i:s"),
            'status'    =>    'finish',
            'error'     =>    $error,
            'data'      =>    $out,
        ], [
            $this->db->quoteInto('id = ?', $id),
            $this->db->quoteInto('handler = ?', $handler)
        ]);
        return $out ?: true; //только не false

    }

}