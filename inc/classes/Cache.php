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
    private $adapter_name; //может пригодиться
    private $namespace = 'Core2';

    /**
     * Cache constructor.
     * @link https://docs.zendframework.com/zend-cache/storage/adapter/
     * @param $adapter
     */
    public function __construct($adapter, $adapter_name)
    {
        $this->adapter = $adapter;
        $this->adapter_name = $adapter_name;

        $this->namespace = $this->adapter->getOptions()->getNamespace();
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

    /**
     * @param $key
     * @return mixed
     */
    public function test($key) {
        return $this->adapter->hasItem($key);
    }

    /**
     * add tags to the key
     * @param $key
     * @param $tags
     * @return void
     */
    public function setTags($key, $tags) {
        if (method_exists($this->adapter,'setTags')) return $this->adapter->setTags($key, $tags);
        //TODO сделать тэгирование
    }

    public function clearByTags($tags) {
        if (method_exists($this->adapter,'clearByTags')) $this->adapter->clearByTags($tags);
        else {
            //TODO сделать очистку по тэгам
            $this->adapter->clearByNamespace($this->namespace);
        }
    }

    /**
     * @param $key
     * @return bool
     */
    public function load($key) {
        $data = $this->adapter->getItem($key);
        if (!$data) $data = false; //совместимость с проверкой от zf1
        return $data;
    }

    /**
     * @param $data
     * @param $key
     * @param array $tags
     */
    public function save($data, $key, $tags = []) {
        $this->adapter->setItem($key, $data);
        if ($tags) $this->setTags($key, $tags);
    }

    /**
     * @param $mode
     * @param array $tags
     */
    public function clean($key = '', $tags = []) {
        if ($tags) {
            $this->clearByTags($tags);
        }
        else {
            if ($key) $this->adapter->removeItem($key);
            else $this->adapter->clearByNamespace($this->namespace);
        }
    }

    /**
     * @param $key
     */
    public function remove($key) {
        if (is_array($key)) $this->adapter->removeItems($key);
        else $this->adapter->removeItem($key);
    }


    /**
     * @return string
     */
    public function getAdapterName(): string {
        return (string)$this->adapter_name;
    }
}