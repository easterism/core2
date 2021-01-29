<?php

use Laminas\ServiceManager\ServiceManager;

/**
 * обратная совместимост с ZF1
 * @deprecated
 * User: easter
 */
class Zend_Registry
{
    private static $_service;

    public static function getInstance() {
        return new Zend_Registry();
    }

    public static function getRealInstance()
    {
        if (self::$_service === null) {
            self::$_service = new ServiceManager();
            self::$_service->setAllowOverride(true); // можем создавать новые сервисы в любое время
        }

        return self::$_service;
    }

    public static function isRegistered($index)
    {
        if (self::$_service === null) {
            return false;
        }
        return self::$_service->has($index);
    }

    public static function get($name) {
        $instance = self::getRealInstance();
        return $instance->get($name);
    }

    public static function set($name, $service) {
        $instance = self::getRealInstance();
        return $instance->setService($name, $service);
    }
}