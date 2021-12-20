<?php
/**
 * Описание отдельного контроллера для REST сервиса
 * User: StepovichPE
 * Date: 04.12.2021
 * Time: 14:41
 */

interface RestController {

    public function setVersion($version);

    public function setResource($resource);

    public function setQuery($query);

    public function setBody($body);

    public function dispatch();

}