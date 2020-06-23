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
    const TYPE_STEPS = 30;

    protected $active_tab     = '';
    protected $title          = '';
    protected $tabs_width     = 0;
    protected $description    = '';
    protected $content        = '';
    protected $resource       = '';
    protected $tabs           = [];
    protected $theme_src      = '';
    protected $theme_location = '';
    protected $back_url       = '';
    protected $position       = self::POSITION_TOP;
    protected $type           = self::TYPE_TABS;


    /**
     * Panel constructor.
     * @param string $resource
     */
    public function __construct($resource = '') {
        $this->resource = $resource ?: crc32(time() . rand(0, 10000));
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
        $positions = [
            self::POSITION_TOP,
            self::POSITION_LEFT,
            self::POSITION_RIGHT,
            self::POSITION_BOTTOM
        ];
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
        $types = [
            self::TYPE_TABS,
            self::TYPE_PILLS,
            self::TYPE_STEPS
        ];
        if (in_array($type, $types)) {
            $this->type = $type;
        } else {
            throw new Exception('Invalid type');
        }
    }


    /**
     * Установка ширины для табов
     * @param int $width
     * @throws Exception
     */
    public function setWidthTabs($width) {

        if ($width < 10) {
            throw new Exception('Invalid width');
        }

        $this->tabs_width = (int)$width;
    }


    /**
     * @param        $title
     * @param string $description
     * @param string $back_url
     */
    public function setTitle($title, $description = '', $back_url = '') {
        $this->title       = $title;
        $this->description = $description;
        $this->back_url    = str_replace('?', '#', $back_url);
    }


    /**
     * Добавление таба
     * @param string $title
     * @param string $id
     * @param string $url
     * @param array  $options
     */
    public function addTab($title, $id, $url, $options = []) {

        $tab_options = is_array($options) ? $options : [];

        // DEPRECATED
        if ($options === true) {
            $tab_options['disabled'] = true;
        }

        $this->tabs[] = [
            'type'    => 'tab',
            'title'   => $title,
            'id'      => $id,
            'url'     => str_replace('?', '#', $url),
            'options' => $tab_options
        ];
    }


    /**
     * Добавление разделителя
     */
    public function addDivider() {

        $this->tabs[] = [
            'type' => 'divider'
        ];
    }


    /**
     * Установка содержимого для контейнера
     * @param string $content
     */
    public function setContent($content) {
        $this->content = $content;
    }


    /**
     * Установка активного таба по умолчанию
     * @param string $tab_id
     */
    public function setDefaultTab($tab_id) {
        if ( ! isset($_GET[$this->resource])) {
            $this->active_tab = $tab_id;
        }
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
            $styles = "";

            if ($this->tabs_width) {
                $margin_width = $this->tabs_width - 1;

                switch ($this->position) {
                    case self::POSITION_LEFT:  $styles = "style=\"margin-left:{$margin_width}px\""; break;
                    case self::POSITION_RIGHT: $styles = "style=\"margin-right:{$margin_width}px\""; break;
                    default: $styles = "";
                }
            }

            $tpl->content_bottom->assign('[STYLES]',  $styles);
            $tpl->content_bottom->assign('[CONTENT]', $this->content);
        }


        if ( ! empty($this->title)) {
            if ( ! empty($this->back_url)) {
                $tpl->title->back_url->assign('[BACK_URL]', $this->back_url);;
            }

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

        switch ($this->type) {
            case self::TYPE_TABS :  $type_name = 'tabs'; break;
            case self::TYPE_PILLS : $type_name = 'pills'; break;
            case self::TYPE_STEPS : $type_name = 'steps'; break;
            default : throw new Exception('Invalid type'); break;
        }
        $tpl->assign('[TYPE]', $type_name);

        switch ($this->position) {
            case self::POSITION_TOP :    $position_name = 'top'; break;
            case self::POSITION_LEFT :   $position_name = 'left'; break;
            case self::POSITION_RIGHT :  $position_name = 'right'; break;
            case self::POSITION_BOTTOM : $position_name = 'bottom'; break;
            default : throw new Exception('Invalid position'); break;
        }
        $tpl->assign('[POSITION]', $position_name);

        if ( ! empty($this->tabs)) {
            $tpl->tabs->assign('[STYLES]', $this->tabs_width ? "style=\"width:{$this->tabs_width}px\"" : '');

            foreach ($this->tabs as $tab) {

                if ($tab['type'] == 'tab') {
                    if (isset($tab['options']['disabled']) && $tab['options']['disabled']) {
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

                } else {
                    if (in_array($this->position, [self::POSITION_RIGHT, self::POSITION_LEFT]) &&
                        $this->type == self::TYPE_TABS
                    ) {
                        $tpl->tabs->elements->touchBlock('divider');
                    }
                }

                $tpl->tabs->elements->reassign();
            }
        }

        return $tpl->render();
    }
} 