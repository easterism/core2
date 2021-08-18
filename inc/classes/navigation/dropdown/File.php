<?php
namespace Core2\Navigation\Dropdown;


/**
 *
 */
class File {

    private $title    = '';
    private $icon     = '';
    private $id       = '';
    private $class    = '';
    private $onchange = '';


    /**
     * @return string
     */
    public function getTitle(): string {

        return $this->title;
    }


    /**
     * @param string $title
     */
    public function setTitle(string $title): void {

        $this->title = $title;
    }


    /**
     * @return string
     */
    public function getIcon(): string {

        return $this->icon;
    }


    /**
     * @param string $icon
     */
    public function setIcon(string $icon): void {

        $this->icon = $icon;
    }


    /**
     * @return string
     */
    public function getId(): string {

        return $this->id;
    }


    /**
     * @param string $id
     */
    public function setId(string $id): void {

        $this->id = $id;
    }


    /**
     * @return string
     */
    public function getClass(): string {

        return $this->class;
    }


    /**
     * @param string $class
     */
    public function setClass(string $class): void {

        $this->class = $class;
    }


    /**
     * @return string
     */
    public function getOnChange(): string {

        return $this->onchange;
    }


    /**
     * @param string $onchange
     */
    public function setOnChange(string $onchange): void {

        $this->onchange = $onchange;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        return [
            'type'     => 'file',
            'title'    => $this->getTitle(),
            'icon'     => $this->getIcon(),
            'id'       => $this->getId(),
            'class'    => $this->getClass(),
            'onchange' => $this->getOnChange(),
        ];
    }
}