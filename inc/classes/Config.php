<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 01.12.23
 * Time: 17:47
 */

namespace Core2;

use Laminas\Config\Reader;

/**
 * Class Config
 * backward compatibility for Zend_Config
 * @package Core2
 */
class Config
{
    private $_config;
    private $data = [];

    /**
     * Cache constructor.
     * @link https://docs.zendframework.com/zend-cache/storage/adapter/
     * @param $adapter
     */
    public function __construct(array $config = [])
    {
        if ($config) $this->_config = new \Laminas\Config\Config($config, true);
    }

    public function __get($name)
    {
        return $this->_config->$name;
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function readIni($filename, $section = 'production')
    {
        $reader = new Reader\Ini();
        $reader->setProcessSections(true);
        $reader->setNestSeparator(':');
        $data = $reader->fromFile($filename);
        if (!isset($data['production'])) throw new \Exception("production section not found", 404);
        $this->data = $data;
        if ($section !== 'production') {
            $reader->setNestSeparator('.');
            $stage = $reader->fromString($this->stageSection($section));
            $data = new \Laminas\Config\Config($stage);
        }
        return $data;
    }

    private function stageSection($section)
    {
        if (!isset($this->data['production'])) throw new \Exception("production section not found", 404);
        $prod = $this->data['production'];
        foreach ($this->data as $key => $item) {
            if ($section !== trim($key)) continue;
            $stage = trim(key($item));
            $origin = current($item);
            if ($stage == 'production') { //staging production section
                return array_merge($prod, current($item));
            }

            $nest = $this->stageNested($stage);
            $this->data[$section] = array_merge($prod, $nest, $origin);
        }
        $data       = isset($this->data[$section]) ? $this->data[$section] : [];
        $out = [];
        foreach ($data as $key => $value) {
            $out[] = $key . '="' . $value . '"';
        }
        $data = implode(chr(10), $out);
        $this->data = [];
        return $data;
    }

    private function stageNested($stage)
    {
        foreach ($this->data as $section => $item) {
            if ($stage !== trim($section)) continue;
            $stage = trim(key($item));
            if ($stage == 'production') { //staging production section
                return current($item);
            }
            return array_merge(current($item), $this->stageNested($stage));
        }
    }

    public function merge(\Laminas\Config\Config $config)
    {
        if ($this->_config) $this->_config->merge($config);
        $this->_config = $config;
        return $this->_config;
    }


}