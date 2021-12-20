<?php
namespace Core2\Navigation;

/**
 *
 */
class Link {

    private $title    = '';
    private $link     = '';
    private $icon     = '';
    private $id       = '';
    private $class    = '';
    private $position = '';
    private $onclick  = '';


    /**
     * @return string
     */
    public function getTitle():string {

        return $this->title;
    }


    /**
     * @param mixed $title
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
     * @param mixed $link
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
     * @param mixed $icon
     */
    public function setIcon(string $icon): void {

        $this->icon = $icon;
    }


    /**
     * @return mixed
     */
    public function getId() {

        return $this->id;
    }


    /**
     * @param mixed $id
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
    public function getPosition(): string {

        return $this->position;
    }


    /**
     * @param string $position
     */
    public function setPosition(string $position): void {

        $this->position = $position;
    }


    /**
     * @return string
     */
    public function getOnClick(): string {

        return $this->onclick;
    }


    /**
     * @param mixed $onclick
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
            'position' => $this->getPosition(),
            'title'    => $this->getTitle(),
            'link'     => $this->getLink(),
            'icon'     => $this->getIcon(),
            'id'       => $this->getId(),
            'class'    => $this->getClass(),
            'onclick'  => $this->getOnClick(),
        ];
    }
}