<?php
namespace Core2\Classes\Table;



/**
 * Class Column
 * @package Core2\Classes\Table
 */
class Column {

    protected $title      = '';
    protected $field      = '';
    protected $type       = '';
    protected $attr       = [];
    protected $is_sorting = true;


    /**
     * @param string $title
     * @param string $field
     * @param string $type
     */
    public function __construct(string $title, string $field, string $type) {
        $this->title = $title;
        $this->field = $field;
        $this->type  = $type;
    }


    /**
     * @return string
     */
    public function getField(): string {
        return $this->field;
    }


    /**
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }


    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }


    /**
     * @param string $name
     * @return string|bool
     */
    public function getAttr(string $name) {
        if (array_key_exists($name, $this->attr)) {
            return $this->attr[$name];
        } else {
            return false;
        }
    }


    /**
     * Получение всех атрибутов
     * @return array
     */
    public function getAttributes(): array {
        return $this->attr;
    }


    /**
     * Установка значения атрибута
     * @param string $name
     * @param string $value
     * @return self
     *@throws Exception
     */
    public function setAttr(string $name, string $value) {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))) {
            $this->attr[$name] = $value;

        } else {
            throw new Exception("Attribute not valid type. Need string");
        }
        return $this;
    }


    /**
     * Установка значения в начале атрибута
     * @param string $name
     * @param string $value
     * @return self
     *@throws Exception
     */
    public function setAttrPrepend(string $name, string $value): Column {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))) {
            if (array_key_exists($name, $this->attr)) {
                $this->attr[$name] = $value . $this->attr[$name];
            } else {
                $this->attr[$name] = $value;
            }

        } else {
            throw new Exception("Attribute not valid type. Need string");
        }
        return $this;
    }


    /**
     * Установка значения в конце атрибута
     * @param string $name
     * @param string $value
     * @return self
     *@throws Exception
     */
    public function setAttrAppend(string $name, string $value): Column {
        if ((is_string($name) || is_numeric($name)) &&
            (is_string($value) || is_numeric($value))) {
            if (array_key_exists($name, $this->attr)) {
                $this->attr[$name] .= $value;
            } else {
                $this->attr[$name] = $value;
            }

        } else {
            throw new Exception("Attribute not valid type. Need string");
        }
        return $this;
    }


    /**
     * @param array $attributes
     * @return self
     * @throws Exception
     */
    public function setAttributes(array $attributes): Column {
        foreach ($attributes as $name => $value) {
            $this->setAttr($name, $value);
        }
        return $this;
    }


    /**
     * @param bool $is_sort
     * @return self
     */
    public function sorting(bool $is_sort = true): Column {
        $this->is_sorting = (bool)$is_sort;
        return $this;
    }


    /**
     * @return bool
     */
    public function isSorting(): bool {
        return $this->is_sorting;
    }


    /**
     * Преобразование в массив
     * @return array
     */
    public function toArray(): array {

        $data = [
            'title'   => $this->title,
            'field'   => $this->field,
            'type'    => $this->type,
            'sorting' => $this->is_sorting,
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }

        return $data;
    }
}