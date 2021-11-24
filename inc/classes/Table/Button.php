<?php
namespace Core2\Classes\Table;


/**
 * Class Button
 * @package Core2\Classes\Table
 */
class Button {

    protected $title      = '';
    protected $attributes = [
        'type'  => 'button',
        'class' => 'btn btn-default btn-xs',
    ];


    /**
     * @param string $title
     */
    public function __construct(string $title) {
        $this->title = $title;
    }


    /**
     * @param array $attributes
     * @return Button
     */
    public function setAttribs(array $attributes): Button {
        foreach ($attributes as $attr => $value) {
            $this->attributes[$attr] = $value;
        }
        return $this;
    }



    /**
     * @param  array $attributes
     * @return Button
     */
    public function setAppendAttribs(array $attributes): Button {
        foreach ($attributes as $attr => $value) {
            $this->attributes[$attr] = array_key_exists($attr, $this->attributes)
                ? $this->attributes[$attr] . $value
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
            $this->attributes[$attr] = array_key_exists($attr, $this->attributes)
                ? $value . $this->attributes[$attr]
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
        $this->attributes[$attr] = $value;
        return $this;
    }


    /**
     * @param string $attr
     * @param string $value
     * @return Button
     */
    public function setAppendAttr(string $attr, string $value): Button {
        $this->attributes[$attr] = array_key_exists($attr, $this->attributes)
            ? $this->attributes[$attr] . $value
            : $value;
        return $this;
    }


    /**
     * @param string $attr
     * @param string $value
     * @return Button
     */
    public function setPrependAttr(string $attr, string $value): Button {
        $this->attributes[$attr] = array_key_exists($attr, $this->attributes)
            ? $value . $this->attributes[$attr]
            : $value;
        return $this;
    }


    /**
     * @return string
     */
    public function __toString() {
        return $this->render();
    }


    /**
     * @return string
     */
    public function render(): string {

        $attributes = array();
        foreach ($this->attributes as $attr => $value) {
            $attributes[] = "$attr=\"{$value}\"";
        }

        $implode_attributes = implode(' ', $attributes);
        $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';


        return "<button{$implode_attributes}>{$this->title}</button>";
    }
}