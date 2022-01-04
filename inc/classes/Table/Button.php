<?php
namespace Core2\Classes\Table;


/**
 * Class Button
 * @package Core2\Classes\Table
 */
class Button {

    protected $title = '';
    protected $attr  = [
        'type'  => 'button',
        'class' => 'btn btn-default',
    ];


    /**
     * @param string $title
     */
    public function __construct(string $title) {
        $this->title = $title;
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
     * @param array $attributes
     * @return Button
     */
    public function setAttribs(array $attributes): Button {
        foreach ($attributes as $attr => $value) {
            $this->attr[$attr] = $value;
        }
        return $this;
    }



    /**
     * @param  array $attributes
     * @return Button
     */
    public function setAppendAttribs(array $attributes): Button {
        foreach ($attributes as $attr => $value) {
            $this->attr[$attr] = array_key_exists($attr, $this->attr)
                ? $this->attr[$attr] . $value
                : $value;
        }
        return $this;
    }



    /**
     * @param  array $attributes
     * @return Button
     */
    public function setPrependAttribs(array $attributes): Button {
        foreach ($attributes as $attr => $value) {
            $this->attr[$attr] = array_key_exists($attr, $this->attr)
                ? $value . $this->attr[$attr]
                : $value;
        }
        return $this;
    }


    /**
     * @param string $attr
     * @param string $value
     * @return Button
     */
    public function setAttr(string $attr, string $value): Button {
        $this->attr[$attr] = $value;
        return $this;
    }


    /**
     * @param string $attr
     * @param string $value
     * @return Button
     */
    public function setAppendAttr(string $attr, string $value): Button {
        $this->attr[$attr] = array_key_exists($attr, $this->attr)
            ? $this->attr[$attr] . $value
            : $value;
        return $this;
    }


    /**
     * @param string $attr
     * @param string $value
     * @return Button
     */
    public function setPrependAttr(string $attr, string $value): Button {
        $this->attr[$attr] = array_key_exists($attr, $this->attr)
            ? $value . $this->attr[$attr]
            : $value;
        return $this;
    }


    /**
     * Преобразование в массив
     * @return array
     */
    public function toArray(): array {

        $data = [
            'title' => $this->title
        ];

        if ( ! empty($this->attr)) {
            $data['attr'] = $this->attr;
        }

        return $data;
    }
}