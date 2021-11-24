<?php
namespace Core2;
use Core2\Navigation\Divider;
use Core2\Navigation\Dropdown;
use Core2\Navigation\Link;


/**
 *
 */
class Navigation {

    /**
     * @var array
     */
    private $_items = [];


    /**
     * Добавление ссылки
     * @param string $title
     * @param string $link
     * @param string $position
     * @return Link
     */
    public function addLink(string $title, string $link, string $position = ''): Link {

        require_once 'Navigation/Link.php';
        $nav_link = new Link();
        $nav_link->setTitle($title);
        $nav_link->setLink($link);

        if ($position) {
            $nav_link->setPosition($position);
        }

        $this->_items[] = $nav_link;

        return $nav_link;
    }


    /**
     * Добавление разделителя
     * @param string $position
     * @return Divider
     */
    public function addDivider(string $position = ''): Divider {

        require_once 'Navigation/Divider.php';
        $nav_divider = new Divider();

        if ($position) {
            $nav_divider->setPosition($position);
        }

        $this->_items[] = $nav_divider;

        return $nav_divider;
    }


    /**
     * Добавление выпадающего списка
     * @param string $title
     * @param string $position
     * @return Dropdown
     */
    public function addDropdown(string $title, string $position = ''): Dropdown {

        require_once 'Navigation/Dropdown.php';
        $nav_dropdown = new Dropdown();
        $nav_dropdown->setTitle($title);

        if ($position) {
            $nav_dropdown->setPosition($position);
        }

        $this->_items[] = $nav_dropdown;

        return $nav_dropdown;
    }


    /**
     * Конвертация данных в массив
     * @return array
     */
    public function toArray(): array {

        $data = [];

        if ( ! empty($this->_items)) {
            foreach ($this->_items as $item) {
                $data[] = $item->toArray();
            }
        }

        return $data;
    }
}