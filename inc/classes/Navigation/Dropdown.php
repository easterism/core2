<?php
namespace Core2\Navigation;
use Core2\Navigation\Dropdown\Header;
use Core2\Navigation\Dropdown\Divider;
use Core2\Navigation\Dropdown\Link;
use Core2\Navigation\Dropdown\File;


/**
 *
 */
class Dropdown {

    private $title    = '';
    private $position = '';
    private $icon     = '';
    private $class    = '';
    private $items    = [];

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
     * Добавление пункта ссылка
     * @param $title
     * @param $link
     * @return Link
     */
    public function addLink($title, $link): Link {

        require_once 'Dropdown/Link.php';
        $nav_link = new Link();
        $nav_link->setTitle($title);
        $nav_link->setLink($link);

        $this->items[] = $nav_link;

        return $nav_link;
    }


    /**
     * Добавление пункта заголовок
     * @param $title
     * @return Header
     */
    public function addHeader($title): Header {

        require_once 'Dropdown/Header.php';
        $nav_dropdown = new Header();
        $nav_dropdown->setTitle($title);

        $this->items[] = $nav_dropdown;

        return $nav_dropdown;
    }


    /**
     * Добавление пункта разделитель
     * @return Divider
     */
    public function addDivider(): Divider {

        require_once 'Dropdown/Divider.php';
        $nav_divider = new Divider();

        $this->items[] = $nav_divider;

        return $nav_divider;
    }


    /**
     * Добавление пункта файл
     * @param $title
     * @return File
     */
    public function addFile($title): File {

        require_once 'Dropdown/File.php';
        $nav_file = new File();
        $nav_file->setTitle($title);

        $this->items[] = $nav_file;

        return $nav_file;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        $items = [];

        if ( ! empty($this->items)) {
            foreach ($this->items as $item) {
                $items[] = $item->toArray();
            }
        }

        return [
            'type'     => 'dropdown',
            'title'    => $this->getTitle(),
            'icon'     => $this->getIcon(),
            'class'    => $this->getClass(),
            'position' => $this->getPosition(),
            'items'    => $items,
        ];
    }
}