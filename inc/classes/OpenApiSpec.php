<?php

namespace Core2;
require_once 'Db.php';
use OpenApi\Attributes as OAT;

#[OAT\Info(
    version: '2.9.0',
    description: '',
    title: 'CORE2',
    contact: new OAT\Contact(
        name: 'mister easter',
        email: 'easter.by@gmail.com'
    )
)]
class OpenApiSpec extends Db
{
    private $_apis = [__FILE__];

    public function __construct()
    {
        parent::__construct();
        $mods     = $this->dataModules->getModuleList();
        foreach ($mods as $k => $data) {
            $location      = $this->getModuleLocation($data['module_id']);
            $modController = "Mod" . ucfirst(strtolower($data['module_id'])) . "Api";
            if ( file_exists($location . "/$modController.php")) {
                require_once $location . "/$modController.php";
                $this->_apis[] = $location . "/$modController.php";
            }
        }
    }

    public function render()
    {

        $openapi = \OpenApi\Generator::scan($this->_apis,
            ['exclude' => ['vendor'], 'pattern' => '*.php']
        );

        header('Content-Type: application/json');
        echo $openapi->toJson();
        return "";
    }
}