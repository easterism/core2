<?php

use Laminas\Session\Container as SessionContainer;

/**
 * обратная совместимост с ZF1
 * @deprecated
 * User: easter
 * Date: 19.05.17
 * Time: 20:08
 */
class Zend_Session_Namespace extends SessionContainer
{
    public function __construct($key)
    {
        parent::__construct($key);
    }
}