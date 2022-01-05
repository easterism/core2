<?php
namespace Core2\Classes\Table;


/**
 * Class Cell
 * @package Core2\Classes\Table
 */
class Cell {

    private $value = '';
    private $attr  = [];


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
     * @return Cell
     * @throws Exception
     */
    public function setAttr(string $name, string $value): Cell {

        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))
        ) {
            $this->attr[$name] = $value;

        } else {
            throw new Exception("Attribute not valid type. Need string or numeric");
        }

        return $this;
    }


    /**
     * Установка значения в начале атрибута
     * @param string $name
     * @param string $value
     * @return Cell
     * @throws Exception
     */
    public function setAttrPrepend(string $name, string $value): Cell {

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

        return $this;
    }


    /**
     * Установка значения в конце атрибута
     * @param string $name
     * @param string $value
     * @return Cell
     * @throws Exception
     */
    public function setAttrAppend(string $name, string $value): Cell {

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

        return $this;
    }


    /**
     * Установка атрибутов
     * @param array $attributes
     * @return Cell
     * @throws Exception
     */
    public function setAttributes(array $attributes): Cell {

        foreach ($attributes as $name => $value) {
            if (is_string($name) && is_string($value) ) {
                $this->attr[$name] = $value;

            } else {
                throw new Exception("Attribute not valid type. Need string");
            }
        }

        return $this;
    }


    /**
     * Получение всех атрибутов
     * @return array
     */
    public function getAttributes(): array {
        return $this->attr;
    }


    /**
     * @param string $name
     * @return string
     */
    public function getAttr(string $name): ?string {

        if (array_key_exists($name, $this->attr)) {
            return $this->attr[$name];
        } else {
            return null;
        }
    }


    /**
     * Преобразование в массив
     * @return array
     */
    public function toArray(): array {

        $data = [
            'value' => $this->value,
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }

        return $data;
    }
}