<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 01.12.18
 * Time: 17:47
 */

namespace Core2;

/**
 * Class Cache
 * backward compatibility for Zend\Cache
 * @package Core2
 */
class Cache
{
    private $adapter;
    const NS = 'Core2';

    /**
     * Cache constructor.
     * @link https://docs.zendframework.com/zend-cache/storage/adapter/
     * @param $adapter
     */
    public function __construct($adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * call native methods
     * @link https://docs.zendframework.com/zend-cache/storage/adapter/#the-storageinterface
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->adapter, $name), $arguments);
    }

    public function test($key) {
        return $this->adapter->hasItem($key);
    }

    public function load($key) {
        return $this->adapter->getItem($key);
    }

    public function save($data, $key, $tags = []) {
        $this->adapter->setItem($key, $data);
        if ($tags) $this->adapter->setTags($key, $tags);
    }

    public function clean($mode, $tags = []) {
        if ($tags) $this->adapter->clearByTags($tags);
        else {
            $this->adapter->clearByNamespace(self::NS);
        }
    }

    public function remove($key) {
        if (is_array($key)) $this->adapter->removeItems($key);
        else $this->adapter->removeItem($key);
    }

}