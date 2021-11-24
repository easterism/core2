<?php
namespace Core2\Classes\Table;


/**
 * Class Cell
 * @package Core2\Classes\Table
 */
class Cell {

    protected $value = '';
    protected $attr  = [];


    /**
     * @param mixed $value
     */
    public function __construct($value) {
        $this->value = (string)$value;
    }


    /**
     * @return string
     */
    public function __toString() {
        return $this->value;
    }


    /**h
     * @param mixed $value
     */
    public function setValue($value) {
        $this->value = (string)$value;
    }


    /**
     * @return string
     */
    public function getValue(): string {
        return $this->value;
    }


    /**
     * Установка значения атрибута
     * @param string $name
     * @param string $value
     * @throws Exception
     */
    public function setAttr(string $name, string $value) {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))
        ) {
            $this->attr[$name] = $value;

        } else {
            throw new Exception("Attribute not valid type. Need string or numeric");
        }
    }


    /**
     * Установка значения в начале атрибута
     * @param string $name
     * @param string $value
     * @throws Exception
     */
    public function setPrependAttr(string $name, string $value) {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))
        ) {
            if (array_key_exists($name, $this->attr)) {
                $this->attr[$name] = $value . $this->attr[$name];
            } else {
                $this->attr[$name] = $value;
            }

        } else {
            throw new Exception("Attribute not valid type. Need string or numeric");
        }
    }


    /**
     * Установка значения в конце атрибута
     * @param string $name
     * @param string $value
     * @throws Exception
     */
    public function setAppendAttr(string $name, string $value) {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))
        ) {
            if (array_key_exists($name, $this->attr)) {
                $this->attr[$name] .= $value;
            } else {
                $this->attr[$name] = $value;
            }

        } else {
            throw new Exception("Attribute not valid type. Need string or numeric");
        }
    }


    /**
     * Установка атрибутов
     * @param array $attributes
     * @throws Exception
     */
    public function setAttribs(array $attributes) {
        foreach ($attributes as $name => $value) {
            if (is_string($name) && is_string($value) ) {
                $this->attr[$name] = $value;

            } else {
                throw new Exception("Attribute not valid type. Need string");
            }
        }
    }


    /**
     * Установка атрибутов в начале
     * @param array $attributes
     * @throws Exception
     */
    public function setPrependAttribs(array $attributes) {
        foreach ($attributes as $name => $value) {
            if (is_string($name) && is_string($value) ) {
                if (array_key_exists($name, $this->attr)) {
                    $this->attr[$name] = $value . $this->attr[$name];
                } else {
                    $this->attr[$name] = $value;
                }

            } else {
                throw new Exception("Attribute not valid type. Need string");
            }
        }
    }


    /**
     * Установка значения в конце атрибута
     * @param array $attributes
     * @throws Exception
     */
    public function setAppendAttribs(array $attributes) {
        foreach ($attributes as $name => $value) {
            if (is_string($name) && is_string($value) ) {
                if (array_key_exists($name, $this->attr)) {
                    $this->attr[$name] .= $value;
                } else {
                    $this->attr[$name] = $value;
                }

            } else {
                throw new Exception("Attribute not valid type. Need string");
            }
        }
    }


    /**
     * Получение всех атрибутов
     * @return array
     */
    public function getAttribs(): array {
        return $this->attr;
    }
}