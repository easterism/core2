<?php
namespace Core2\Classes\Table;



/**
 * Class Search
 * @package Core2\Classes\Table
 */
class Search {

    private $caption    = '';
    private $field      = '';
    private $type       = '';
    private $data       = [];
    private $out        = '';
    private $attr       = [];
    private $value_type = self::TYPE_STRING;

    const TYPE_STRING = 'string';
    const TYPE_INT    = 'int';

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
        'select2',
        'multiselect',
        'multiselect2',
        'autocomplete',
        'autocomplete_table',
    ];


    /**
     * @param string $caption
     * @param string $field
     * @param string $type
     * @throws Exception
     */
    public function __construct(string $caption, string $field, string $type) {

        $this->caption = $caption;
        $this->field   = $field;

        $type = strtolower($type);
        if (in_array($type, $this->available_types)) {
            $this->type = strtolower($type);
        } else {
            throw new Exception("Undefined search type '{$type}'");
        }
    }


    /**
     * @return string
     */
    public function getCaption(): string {
        return $this->caption;
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
     * @return string
     */
    public function getOut(): string {
        return $this->out;
    }


    /**
     * @param  array $data
     * @return self
     */
    public function setData(array $data): Search {
        $this->data = $data;
        return $this;
    }


    /**
     * @return string
     */
    public function getValueType(): string {
        return $this->value_type;
    }


    /**
     * @param string $type
     * @return $this
     */
    public function setValueType(string $type): Search {
        $this->value_type = $type;
        return $this;
    }


    /**
     * @return string
     */
    public function getField(): string {
        return $this->field;
    }


    /**
     * @param string $out
     * @return self
     */
    public function setOut(string $out): Search {
        $this->out = $out;
        return $this;
    }


    /**
     * Установка значения атрибута
     * @param string $name
     * @param string $value
     * @return Search
     * @throws Exception
     */
    public function setAttr(string $name, string $value): Search {

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
     * @return Search
     * @throws Exception
     */
    public function setAttrPrepend(string $name, string $value): Search {

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
     * @return Search
     * @throws Exception
     */
    public function setAttrAppend(string $name, string $value): Search {

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
     * @return Search
     * @throws Exception
     */
    public function setAttributes(array $attributes): Search {

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
            'caption' => $this->caption,
            'field'   => $this->field,
            'type'    => $this->type,
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }
        if ( ! empty($this->out)) {
            $data['out'] = $this->out;
        }
        if ( ! empty($this->data)) {
            $data['data'] = $this->data;
        }

        return $data;
    }
}