<?php
namespace Core2\Navigation\Dropdown;


/**
 *
 */
class Link {

    private $title    = '';
    private $link     = '';
    private $icon     = '';
    private $id       = '';
    private $class    = '';
    private $onclick  = '';


    /**
     * @return string
     */
    public function getTitle():string {

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
    public function getLink(): string {

        return $this->link;
    }


    /**
     * @param string $link
     */
    public function setLink(string $link): void {

        $this->link = $link;
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
    public function getId() {

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
    public function getOnClick(): string {

        return $this->onclick;
    }


    /**
     * @param string $onclick
     */
    public function setOnClick(string $onclick): void {

        $this->onclick = $onclick;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        return [
            'type'     => 'link',
            'title'    => $this->getTitle(),
            'link'     => $this->getLink(),
            'icon'     => $this->getIcon(),
            'id'       => $this->getId(),
            'class'    => $this->getClass(),
            'onclick'  => $this->getOnClick(),
        ];
    }
}