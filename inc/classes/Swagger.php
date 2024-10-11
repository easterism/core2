<?php

namespace Core2;

require_once 'Db.php';

class Swagger extends Db
{

    public function render()
    {
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        require_once $dir . "/mod/ordering/v1.2.0/ModOrderingApi.php";

        $openapi = \OpenApi\Generator::scan([$dir . "/mod/ordering/v1.2.0/ModOrderingApi.php"],
            ['exclude' => ['vendor'], 'pattern' => '*.php']
        );

        header('Content-Type: application/json');
        echo $openapi->toJson();
        return "";
    }

}