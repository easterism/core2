<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 01.12.23
 * Time: 17:47
 */

namespace Core2;

use Laminas\Config\Reader;
use Laminas\Config\Config as LaminasConfig;

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
        if ($config) $this->_config = new LaminasConfig($config, true);
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

        $reader->setNestSeparator('.');
        if ($section !== 'production') {
            $stage = $reader->fromString($this->stageSection($section));
        } else {
            $out = [];
            foreach ($data['production'] as $key => $value) {
                $value = str_replace('"', '\"', $value);
                $out[] = $key . '="' . $value . '"';
            }
            $data = implode(chr(10), $out);
            $stage = $reader->fromString($data);
        }
        $data = new LaminasConfig($stage, true);
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
                $this->data[$section] = array_merge($prod, current($item));
                break;
            }

            $nest = $this->stageNested($stage);
            $this->data[$section] = array_merge($prod, $nest, $origin);
        }

        $data = $this->data[$section] ?? [];
        $out  = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $out[] = $key . '="' . $value . '"';
            }
        }
        $data = implode(chr(10), $out);
        $this->data = [];
        return $data;
    }

    private function stageNested($stage): array
    {
        foreach ($this->data as $section => $item) {
            if ($stage !== trim($section)) continue;
            $stage = trim(key($item));
            if ($stage == 'production') { //staging production section
                return current($item);
            }
            return array_merge(current($item), $this->stageNested($stage));
        }
        return [];
    }

    public function getData()
    {
        return $this->_config;
    }

    public function merge(\Laminas\Config\Config $config)
    {
        if ($this->_config) $this->_config->merge($config);
        $this->_config = $config;
        return $this->_config;
    }


}