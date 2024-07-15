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

    const SIDE_LEFT  = 'left';
    const SIDE_RIGHT = 'right';

    const TYPE_TABS  = 10;
    const TYPE_PILLS = 20;
    const TYPE_STEPS = 30;

    const WRAPPER_TYPE_CARD = 'card';
    const WRAPPER_TYPE_NONE = 'none';

    protected $active_tab     = '';
    protected $title          = '';
    protected $tabs_width     = 0;
    protected $description    = '';
    protected $content        = '';
    protected $resource       = '';
    protected $tabs           = [];
    protected $back_url       = '';
    protected $is_ajax        = false;
    protected $is_collapsible = false;
    protected $position       = self::POSITION_TOP;
    protected $type           = self::TYPE_TABS;
    protected $controls       = [];
    protected $wrapper_type   = self::WRAPPER_TYPE_CARD;


    /**
     * Panel constructor.
     * @param string $resource
     */
    public function __construct(string $resource = '') {

        $this->resource = $resource ?: crc32(time() . rand(0, 10000));

        if (isset($_GET[$this->resource])) {
            $this->active_tab = $_GET[$this->resource];
        }
    }


    /**
     * Установка позиции
     * @param int $position
     * @throws Exception
     */
    public function setPosition(int $position): void {

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
     * @param int $type
     * @throws Exception
     */
    public function setTypeTabs(int $type): void {

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
    public function setWidthTabs(int $width): void {

        if ($width < 10) {
            throw new Exception('Invalid width');
        }

        $this->tabs_width = (int)$width;
    }


    /**
     * @param string $title
     * @param string $description
     * @param string $back_url
     * @return void
     */
    public function setTitle(string $title, string $description = null, string $back_url = null): void {

        $this->title       = $title;
        $this->description = $description;
        $this->back_url    = str_replace('?', '#', (string)$back_url);
    }


    /**
     * @param bool $is_ajax
     */
    public function setAjax(bool $is_ajax = true): void {

        $this->is_ajax = $is_ajax;
    }


    /**
     * Добавление таба
     * @param string $title
     * @param string $id
     * @param string $url
     * @param array  $options
     */
    public function addTab(string $title, string $id, string $url, array $options = []): void {

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
     * Добавляем кнопку в правой части заголовка панели
     * @param string $id
     * @param string $content
     * @param string $action_on_click
     * @return void
     */
    public function addControls(string $id, string $content, string $action_on_click): void
    {
        $this->controls[] = [
            'id' => $id,
            'content' => htmlspecialchars($content),
            'action_on_click' => $action_on_click,
            'type' => 'button'
        ];
    }

    /**
     * Добавляем кастомный html в правой части заголовка панели
     * @param string $content
     * @return void
     */
    public function addControlsCustom(string $content): void
    {
        $this->controls[] = [
            'content' => htmlspecialchars($content),
            'type' => 'custom'
        ];
    }

    /**
     * Добавляем кнопку коллапса тела панели
     * @param bool $is_collapsible
     * @return void
     */
    public function setCollapse(bool $is_collapsible = true): void
    {
        $this->is_collapsible = $is_collapsible;
    }


    /**
     * Добавление разделителя
     */
    public function addDivider(): void {

        $this->tabs[] = [
            'type' => 'divider'
        ];
    }


    /**
     * Установка содержимого для контейнера
     * @param string $content
     */
    public function setContent(string $content): void {

        $this->content = $content;
    }


    /**
     * Установка правила для отображения обертки в панели
     * @param string $type
     * @return Panel
     */
    public function setWrapperType(string $type): self {

        $this->wrapper_type = $type;
        return $this;
    }


    /**
     * Получение правила для отображения обертки в панели
     * @return string
     */
    public function getWrapperType(): string {

        return $this->wrapper_type;
    }


    /**
     * Установка активного таба по умолчанию
     * @param string $tab_id
     */
    public function setDefaultTab(string $tab_id): void {

        if ( ! isset($_GET[$this->resource])) {
            $this->active_tab = $tab_id;
        }
    }


    /**
     * Получение идентификатора активного таба
     * @return string
     */
    public function getActiveTab(): string {

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
    public function render(): string {

        $tpl = new Templater3(DOC_ROOT . "/core2/html/" . THEME . '/html/panel.html');

        $tpl->assign('[RESOURCE]', $this->resource);

        if ($this->position == self::POSITION_BOTTOM) {
            $tpl->content_top->assign('[CONTENT]', $this->content);

        } else {
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

        switch ($this->wrapper_type) {
            case self::WRAPPER_TYPE_CARD : $wrapper_type = 'default'; break;
            case self::WRAPPER_TYPE_NONE : $wrapper_type = 'none'; break;
            default : throw new Exception('Invalid position'); break;
        }
        $tpl->assign('[WRAPPER_TYPE]', $wrapper_type);

        if ( ! empty($this->tabs)) {
            $tpl->tabs->assign('[STYLES]', $this->tabs_width ? "style=\"min-width:{$this->tabs_width}px;width:{$this->tabs_width}px\"" : '');

            $tabs_load_count = [];
            $tabs_side       = false;

            foreach ($this->tabs as $tab) {

                if ($tab['type'] == 'tab') {
                    $tab_count = null;

                    if (isset($tab['options']['count'])) {
                        $tab_count = $tab['options']['count'];

                    } elseif ( ! empty($tab['options']['load_count'])) {
                        $tabs_load_count[] = [
                            'id'  => $tab['id'],
                            'url' => $tab['options']['load_count'],
                        ];
                    }

                    $class = [];

                    if ( ! empty($tab['options']['side']) && is_string($tab['options']['side']) && ! $tabs_side) {
                        $tabs_side = true;
                        $class[]   = "panel-tab-side-{$tab['options']['side']}";
                    }

                    if (isset($tab['options']['disabled']) && $tab['options']['disabled']) {
                        $tpl->tabs->elements->tab_disabled->assign('[ID]',    $tab['id']);
                        $tpl->tabs->elements->tab_disabled->assign('[TITLE]', $tab['title']);
                        $tpl->tabs->elements->tab_disabled->assign('[CLASS]', implode(' ', $class));

                    } else {
                        $url = (strpos($tab['url'], "#") !== false ? $tab['url'] . "&" : $tab['url'] . "#") . "{$this->resource}={$tab['id']}";

                        if ($this->active_tab == $tab['id']) {
                            $class[] = 'active';
                        }


                        if (isset($tab['options']['onclick']) && $tab['options']['onclick']) {
                            $onclick = $tab['options']['onclick'];

                        } else {
                            if ($this->is_ajax) {
                                $onclick = "CoreUI.panel.loadContent('{$this->resource}', '{$tab['id']}', '{$url}', event);";
                            } else {
                                $onclick = "if (event.button === 0 && ! event.ctrlKey) load('{$url}');";
                            }
                        }

                        $title = $tab_count !== null ? "{$tab['title']} ({$tab_count})" : $tab['title'];

                        $tpl->tabs->elements->tab->assign('[ID]',      $tab['id']);
                        $tpl->tabs->elements->tab->assign('[CLASS]',   implode(' ', $class));
                        $tpl->tabs->elements->tab->assign('[TITLE]',   $title);
                        $tpl->tabs->elements->tab->assign('[ONCLICK]', $onclick);
                        $tpl->tabs->elements->tab->assign('[URL]',     $url);
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

            if ( ! empty($tabs_load_count) && $tpl->issetBlock('load_counts')) {
                $tpl->load_counts->assign('[TABS_LOAD_COUNT]', addslashes(json_encode($tabs_load_count)));
            }
        }
        if ( ! empty($this->controls)) {

            $tpl->title->assign('[TITLE]', $this->title ?: '');

            foreach ($this->controls as $control) {
                switch ($control['type']) {
                    case 'button' :
                        $tpl->title->panel_controls->controls->controls_button->assign('[ID]', $control['id']);
                        $tpl->title->panel_controls->controls->controls_button->assign('[ACTION_ONCLICK]', $control['action_on_click']);
                        $tpl->title->panel_controls->controls->controls_button->assign('[CONTROL_CONTENT]', htmlspecialchars_decode($control['content']));
                        break;

                    case 'custom' :
                        $tpl->title->panel_controls->controls->controls_custom->assign('[CONTROLS_CUSTOM]', htmlspecialchars_decode($control['content']));
                        break;

                    default:
                        throw new Exception('Нет такого типа для controls ' . $control['type']);

                }

                $tpl->title->panel_controls->controls->reassign();
            }
        }
        if ($this->is_collapsible) {
            $tpl->title->panel_controls->collapse->assign('[RESOURCE]', $this->resource);
        }

        return $tpl->render();
    }
} 