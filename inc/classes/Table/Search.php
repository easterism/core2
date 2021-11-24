<?php
namespace Core2\Classes\Table;



/**
 * Class Search
 * @package Core2\Classes\Table
 */
class Search {

    protected $caption = '';
    protected $field   = '';
    protected $type    = '';
    protected $data    = [];
    protected $in      = '';
    protected $out     = '';

    protected $available_types = [
        'text',
        'date',
        'datetime',
        'radio',
        'checkbox',
        'select',
        'multiselect',
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
    public function getIn(): string {
        return $this->in;
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
    public function getField(): string {
        return $this->field;
    }


    /**
     * @param string $in
     * @return self
     */
    public function setIn(string $in): Search {
        $this->in = $in;
        return $this;
    }


    /**
     * @param string $out
     * @return self
     */
    public function setOut(string $out): Search {
        $this->out = $out;
        return $this;
    }
}