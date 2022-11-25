<?php
namespace Core2\Classes\Table;


/**
 *
 */
class Filter {

    private $title = '';
    private $field = '';
    private $type  = '';
    private $data  = [];
    private $attr  = [];

    protected $available_types = [
        'text',
        'text_strict',
        'number',
        'date_one',
        'date',
        'datetime',
        'radio',
        'checkbox',
        'select',
        'multiselect',
    ];


    /**
     * @param string $field
     * @param string $type
     * @param string $title
     * @throws Exception
     */
    public function __construct(string $field, string $type, string $title) {

        $this->title = $title;
        $this->field = $field;

        $type = strtolower($type);
        if (in_array($type, $this->available_types)) {
            $this->type = strtolower($type);
        } else {
            throw new Exception("Undefined filter type '{$type}'");
        }
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
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }


    /**
     * @param  array $data
     * @return self
     */
    public function setData(array $data): Filter {
        $this->data = $data;
        return $this;
    }


    /**
     * @return string
     */
    public function getField(): string {
        return $this->field;
    }


    /**
     * Установка значения атрибута
     * @param string $name
     * @param string $value
     * @return self
     * @throws Exception
     */
    public function setAttr(string $name, string $value): Filter {

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
     * @return self
     * @throws Exception
     */
    public function setAttrPrepend(string $name, string $value): Filter {

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
     * @return Filter
     * @throws Exception
     */
    public function setAttrAppend(string $name, string $value): Filter {

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
     * @return Filter
     * @throws Exception
     */
    public function setAttributes(array $attributes): Filter {

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
            'field' => $this->field,
            'type'  => $this->type,
            'title' => $this->title,
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }
        if ( ! empty($this->data)) {
            $data['data'] = $this->data;
        }

        return $data;
    }
}