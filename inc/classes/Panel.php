<?php

require_once 'Templater3.php';


/**
 * Class Panel
 */
class Panel {

    const POSITION_TOP    = 1;
    const POSITION_LEFT   = 2;
    const POSITION_RIGHT  = 3;
    const POSITION_BOTTOM = 4;

    const TYPE_TABS  = 10;
    const TYPE_PILLS = 20;

    protected $active_tab     = '';
    protected $title          = '';
    protected $description    = '';
    protected $content        = '';
    protected $resource       = '';
    protected $tabs           = array();
    protected $theme_src      = '';
    protected $theme_location = '';
    protected $position       = self::POSITION_TOP;
    protected $type           = self::TYPE_TABS;


    /**
     * Panel constructor.
     * @param string $resource
     */
    public function __construct($resource) {
        $this->resource = $resource;
        if (isset($_GET[$this->resource])) {
            $this->active_tab = $_GET[$this->resource];
        }
    }


    /**
     * Установка позиции
     * @param  int $position
     * @throws Exception
     */
    public function setPosition($position) {
        $positions = array(
            self::POSITION_TOP,
            self::POSITION_LEFT,
            self::POSITION_RIGHT,
            self::POSITION_BOTTOM
        );
        if (in_array($position, $positions)) {
            $this->position = $position;
        } else {
            throw new Exception('Invalid position');
        }
    }


    /**
     * Установка типа для закладок
     * @param  int $type
     * @throws Exception
     */
    public function setTypeTabs($type) {
        $types = array(
            self::TYPE_TABS,
            self::TYPE_PILLS
        );
        if (in_array($type, $types)) {
            $this->type = $type;
        } else {
            throw new Exception('Invalid type');
        }
    }


    /**
     * @param string $title
     * @param string $description
     */
    public function setTitle($title, $description = '') {
        $this->title       = $title;
        $this->description = $description;
    }


    /**
     * Добавление таба
     * @param string $title
     * @param string $id
     * @param string $url
     * @param bool   $disabled
     */
    public function addTab($title, $id, $url, $disabled = false) {
        $this->tabs[] = array(
            'title'    => $title,
            'id'       => $id,
            'url'      => str_replace('?', '#', $url),
            'disabled' => $disabled
        );
    }


    /**
     * Установка содержимого для контейнера
     * @param string $content
     */
    public function setContent($content) {
        $this->content = $content;
    }


    /**
     * Получение идентификатора активного таба
     * @return string
     */
    public function getActiveTab() {

        if ($this->active_tab == '' && ! empty($this->tabs)) {
            reset($this->tabs);
            $tab = current($this->tabs);
            $this->active_tab = $tab['id'];
        }

        return $this->active_tab;
    }


    /**
     * Создание и возврат контейнера
     * @return string
     * @throws Exception
     */
    public function render() {

        $tpl = new Templater3(DOC_ROOT . "/core2/html/" . THEME . '/html/panel.html');

        $tpl->assign('[RESOURCE]', $this->resource);

        if ($this->position == self::POSITION_BOTTOM) {
            $tpl->content_top->assign('[CONTENT]', $this->content);
        } else {
            $tpl->content_bottom->assign('[CONTENT]', $this->content);
        }


        if ( ! empty($this->title)) {
            $tpl->title->assign('[TITLE]', $this->title);

            if ( ! empty($this->description)) {
                $tpl->title->description->assign('[DESCRIPTION]', $this->description);
            }
        }

        if ($this->active_tab == '' && ! empty($this->tabs)) {
            reset($this->tabs);
            $tab = current($this->tabs);
            $this->active_tab = $tab['id'];
        }

        if ( ! empty($this->tabs)) {
            switch ($this->type) {
                case self::TYPE_TABS :  $type_name = 'tabs'; break;
                case self::TYPE_PILLS : $type_name = 'pills'; break;
                default : throw new Exception('Invalid type'); break;
            }
            $tpl->tabs->assign('[TYPE]', $type_name);

            switch ($this->position) {
                case self::POSITION_TOP :    $position_name = 'top'; break;
                case self::POSITION_LEFT :   $position_name = 'left'; break;
                case self::POSITION_RIGHT :  $position_name = 'right'; break;
                case self::POSITION_BOTTOM : $position_name = 'bottom'; break;
                default : throw new Exception('Invalid position'); break;
            }
            $tpl->tabs->assign('[POSITION]', $position_name);

            foreach ($this->tabs as $tab) {

                if ($tab['disabled']) {
                    $tpl->tabs->elements->tab_disabled->assign('[ID]',    $tab['id']);
                    $tpl->tabs->elements->tab_disabled->assign('[TITLE]', $tab['title']);

                } else {
                    $url   = (strpos($tab['url'], "#") !== false ? $tab['url'] . "&" : $tab['url'] . "#") . "{$this->resource}={$tab['id']}";
                    $class = $this->active_tab == $tab['id'] ? 'active' : '';

                    $tpl->tabs->elements->tab->assign('[ID]',    $tab['id']);
                    $tpl->tabs->elements->tab->assign('[CLASS]', $class);
                    $tpl->tabs->elements->tab->assign('[TITLE]', $tab['title']);
                    $tpl->tabs->elements->tab->assign('[URL]',   $url);
                }
                $tpl->tabs->elements->reassign();
            }
        }

        return $tpl->render();
    }
} 