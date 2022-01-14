<?php
namespace Core2\Classes\Table;

require_once 'Cell.php';


/**
 * Class Row
 * @package Core2\Classes\Table
 */
class Row implements \Iterator {

    private $cells = [];
    private $attr  = [];


    /**
     * Row constructor.
     * @param array|Row $row
     */
    public function __construct($row) {

        foreach ($row as $key => $cell) {
            $this->cells[$key] = new Cell($cell);
        }
    }


    /**
     * Get cell class
     * @param string $field
     * @return Cell|string
     */
    public function __get(string $field) {

        if ( ! array_key_exists($field, $this->cells)) {
            $this->cells[$field] = new Cell('');
        }

        return $this->cells[$field];
    }


    /**
     * Set value in cell
     * @param string $field
     * @param string $value
     */
    public function __set(string $field, string $value) {

        if (array_key_exists($field, $this->cells)) {
            $this->cells[$field]->setValue($value);
        } else {
            $this->cells[$field] = new Cell($value);
        }
    }


    /**
     * Check cell
     * @param string $field
     * @return bool
     */
    public function __isset(string $field) {
        return isset($this->cells[$field]);
    }


    /**
     * Установка значения атрибута
     * @param string $name
     * @param string $value
     */
    public function setAttr(string $name, string $value) {

        $this->attr[$name] = $value;
    }


    /**
     * Установка значения в начале атрибута
     * @param string $name
     * @param string $value
     * @return Row
     */
    public function setAttrPrepend(string $name, string $value): Row {

        if (array_key_exists($name, $this->attr)) {
            $this->attr[$name] = $value . $this->attr[$name];
        } else {
            $this->attr[$name] = $value;
        }

        return $this;
    }


    /**
     * Установка значения в конце атрибута
     * @param string $name
     * @param string $value
     * @return Row
     */
    public function setAttrAppend(string $name, string $value): Row {

        if (array_key_exists($name, $this->attr)) {
            $this->attr[$name] .= $value;
        } else {
            $this->attr[$name] = $value;
        }

        return $this;
    }


    /**
     * Установка атрибутов
     * @param array $attributes
     * @return Row
     * @throws Exception
     */
    public function setAttributes(array $attributes): Row {

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


    public function rewind() {
        return reset($this->cells);
    }

    public function key() {
        return key($this->cells);
    }

    public function current() {
        return current($this->cells);
    }

    public function valid() {
        return key($this->cells) !== null;
    }

    public function next() {
        return next($this->cells);
    }


    /**
     * Преобразование в массив
     * @return array
     */
    public function toArray(): array {

        $cells = [];

        if ( ! empty($this->cells)) {
            foreach ($this->cells as $field => $cell) {
                if ($cell instanceof Cell) {
                    $cells[$field] = $cell->toArray();
                }
            }
        }

        $data = [
            'cells' => $cells,
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }

        return $data;
    }
}