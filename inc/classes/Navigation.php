<?php
namespace Core2;

require_once 'Db.php';
require_once 'Navigation/Divider.php';
require_once 'Navigation/Link.php';
require_once 'Navigation/Dropdown.php';

use Core2\Navigation\Divider;
use Core2\Navigation\Dropdown;
use Core2\Navigation\Link;
use \Templater3;


/**
 *
 */
class Navigation extends Db {

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


    /**
     * @param $name
     * @param $mod_controller
     * @return array
     * @throws Zend_Config_Exception
     */
    public function setModuleNavigation($name): void {

        $config_module = $this->getModuleConfig($name);

        if ( ! empty($config_module) &&
            ! empty($config_module->system) &&
            ! empty($config_module->system->nav)
        ) {
            $navigations = $config_module->system->nav->toArray();

            if ( ! empty($navigations)) {
                foreach ($navigations as $key => $nav) {
                    if ( ! empty($nav['type'])) {
                        $nav['position'] = $nav['position'] ?? '';

                        switch ($nav['type']) {
                            case 'link':
                                $nav['title'] = $nav['title'] ?? '';
                                $nav['link']  = $nav['link'] ?? '#';

                                $nav_link = $this->addLink($nav['title'], $nav['link'], $nav['position']);

                                if ( ! empty($nav['icon'])) {
                                    $nav_link->setIcon($nav['icon']);
                                }
                                if ( ! empty($nav['id'])) {
                                    $nav_link->setId($nav['id']);
                                }
                                if ( ! empty($nav['class'])) {
                                    $nav_link->setClass($nav['class']);
                                }
                                if ( ! empty($nav['onclick'])) {
                                    $nav_link->setOnClick($nav['onclick']);
                                }
                                break;

                            case 'divider':
                                $this->addDivider($nav['position']);
                                break;

                            case 'dropdown':
                                $nav['title'] = $nav['title'] ?? '';
                                $nav['items'] = $nav['items'] ?? [];

                                $nav_list = $this->addDropdown($nav['title'], $nav['position']);

                                if ( ! empty($nav['icon'])) {
                                    $nav_list->setIcon($nav['icon']);
                                }
                                if ( ! empty($nav['class'])) {
                                    $nav_list->setClass($nav['class']);
                                }

                                if ( ! empty($nav['items'])) {
                                    foreach ($nav['items'] as $item) {

                                        switch ($item['type']) {
                                            case 'link':
                                                $item['title'] = $item['title'] ?? '';
                                                $item['link']  = $item['link'] ?? '#';

                                                $item_link = $nav_list->addLink($item['title'], $item['link']);

                                                if ( ! empty($item['id'])) {
                                                    $item_link->setId($item['id']);
                                                }
                                                if ( ! empty($item['class'])) {
                                                    $item_link->setClass($item['class']);
                                                }
                                                if ( ! empty($item['icon'])) {
                                                    $item_link->setIcon($item['icon']);
                                                }
                                                if ( ! empty($item['onclick'])) {
                                                    $item_link->setOnClick($item['onclick']);
                                                }
                                                break;

                                            case 'header':
                                                $item['title'] = $item['title'] ?? '';
                                                $nav_list->addHeader($item['title']);
                                                break;

                                            case 'divider':
                                                $nav_list->addDivider();
                                                break;

                                            case 'file':
                                                $item['title'] = $item['title'] ?? '';
                                                $item_file = $nav_list->addFile($item['title']);

                                                if ( ! empty($item['id'])) {
                                                    $item_file->setId($item['id']);
                                                }
                                                if ( ! empty($item['class'])) {
                                                    $item_file->setClass($item['class']);
                                                }
                                                if ( ! empty($item['icon'])) {
                                                    $item_file->setIcon($item['icon']);
                                                }
                                                if ( ! empty($item['onchange'])) {
                                                    $item_file->setOnChange($item['onchange']);
                                                }
                                                break;
                                        }
                                    }
                                }
                                break;
                        }
                    }
                }
            }
        }

    }


    /**
     * @param $navigate_item
     * @return string
     * @throws Exception
     */
    public function renderNavigateItem($navigate_item) {

        if (empty($navigate_item['type'])) {
            return '';
        }

        $html = '';
        switch ($navigate_item['type']) {
            case 'divider':
                $html = file_get_contents(Theme::get("html-navigation-divider"));
                break;

            case 'link':
                $link = ! empty($navigate_item['link'])
                    ? $navigate_item['link']
                    : '#';
                $on_click = ! empty($navigate_item['onclick'])
                    ? $navigate_item['onclick']
                    : "if (event.button === 0 && ! event.ctrlKey) load('{$link}');";

                $tpl = new Templater3(Theme::get("html-navigation-link"));
                $tpl->assign('[TITLE]',   ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                $tpl->assign('[ICON]',    ! empty($navigate_item['icon']) ? $navigate_item['icon'] : '');
                $tpl->assign('[CLASS]',   ! empty($navigate_item['class']) ? $navigate_item['class'] : '');
                $tpl->assign('[ID]',      ! empty($navigate_item['id']) ? $navigate_item['id'] : '');
                $tpl->assign('[LINK]',    $link);
                $tpl->assign('[ONCLICK]', $on_click);
                $html = $tpl->render();
                break;

            case 'dropdown':
                $tpl = new Templater3(Theme::get("html-navigation-dropdown"));
                $tpl->assign('[TITLE]', ! empty($navigate_item['title']) ? $navigate_item['title'] : '');
                $tpl->assign('[ICON]',  ! empty($navigate_item['icon'])  ? $navigate_item['icon']  : '');
                $tpl->assign('[CLASS]', ! empty($navigate_item['class']) ? $navigate_item['class'] : '');

                if ( ! empty($navigate_item['items'])) {
                    foreach ($navigate_item['items'] as $list_item) {

                        switch ($list_item['type']) {
                            case 'link':
                                $link = ! empty($list_item['link'])
                                    ? $list_item['link']
                                    : '#';
                                $on_click = ! empty($list_item['onclick'])
                                    ? $list_item['onclick']
                                    : "if (event.button === 0 && ! event.ctrlKey) load('{$link}');";

                                $tpl->item->link->assign('[TITLE]',   ! empty($list_item['title']) ? $list_item['title'] : '');
                                $tpl->item->link->assign('[ICON]',    ! empty($list_item['icon']) ? $list_item['icon'] : '');
                                $tpl->item->link->assign('[CLASS]',   ! empty($list_item['class']) ? $list_item['class'] : '');
                                $tpl->item->link->assign('[ID]',      ! empty($list_item['id']) ? $list_item['id'] : '');
                                $tpl->item->link->assign('[LINK]',    $link);
                                $tpl->item->link->assign('[ONCLICK]', $on_click);
                                break;

                            case 'file':
                                $on_change = ! empty($list_item['onchange'])
                                    ? $list_item['onchange']
                                    : "";

                                $tpl->item->file->assign('[TITLE]',    ! empty($list_item['title']) ? $list_item['title'] : '');
                                $tpl->item->file->assign('[ICON]',     ! empty($list_item['icon']) ? $list_item['icon'] : '');
                                $tpl->item->file->assign('[CLASS]',    ! empty($list_item['class']) ? $list_item['class'] : '');
                                $tpl->item->file->assign('[ID]',       ! empty($list_item['id']) ? $list_item['id'] : '');
                                $tpl->item->file->assign('[ONCHANGE]', $on_change);
                                break;

                            case 'divider':
                                $tpl->item->touchBlock('divider');
                                break;

                            case 'header':
                                $tpl->item->header->assign('[TITLE]', ! empty($list_item['title']) ? $list_item['title'] : '');
                                break;
                        }

                        $tpl->item->reassign();
                    }
                }

                $html = $tpl->render();
                break;
        }

        return $html;
    }

}