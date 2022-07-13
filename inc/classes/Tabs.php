<?php
namespace Core2;
require_once 'Templater3.php';


/**
 * Class Tabs
 * @package Core2
 */
class Tabs {

    const POSITION_TOP    = 1;
    const POSITION_LEFT   = 2;
    const POSITION_RIGHT  = 3;
    const POSITION_BOTTOM = 4;

    const TYPE_TABS  = 10;
    const TYPE_PILLS = 20;
    const TYPE_STEPS = 30;

    protected $active_tab  = '';
    protected $title       = '';
    protected $description = '';
    protected $content     = '';
    protected $resource    = '';
    protected $tabs        = [];
    protected $is_ajax     = false;
    protected $position    = self::POSITION_TOP;
    protected $type        = self::TYPE_TABS;


    /**
     * @param string $resource
     */
    public function __construct(string $resource) {
        $this->resource = $resource;

        if (isset($_GET[$this->resource])) {
            $this->active_tab = $_GET[$this->resource];
        }
    }


    /**
     * Установка позиции
     * @param  int $position
     * @throws \Exception
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
            throw new \Exception('Invalid position');
        }
    }


    /**
     * Установка типа для закладок
     * @param  int $type
     * @throws \Exception
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
            throw new \Exception('Invalid type');
        }
    }


    /**
     * @param string $title
     * @param string $description
     */
    public function setTitle(string $title, string $description = ''): void {
        $this->title       = $title;
        $this->description = $description;
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
     * @throws \Exception
     */
    public function render(): string {

        $tpl = new \Templater3(DOC_ROOT . "/core2/html/" . THEME . '/html/tabs.html');

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

        switch ($this->type) {
            case self::TYPE_TABS :  $type_name = 'tabs'; break;
            case self::TYPE_PILLS : $type_name = 'pills'; break;
            case self::TYPE_STEPS : $type_name = 'steps'; break;
            default : throw new \Exception('Invalid type'); break;
        }
        $tpl->assign('[TYPE]', $type_name);

        switch ($this->position) {
            case self::POSITION_TOP :    $position_name = 'top'; break;
            case self::POSITION_LEFT :   $position_name = 'left'; break;
            case self::POSITION_RIGHT :  $position_name = 'right'; break;
            case self::POSITION_BOTTOM : $position_name = 'bottom'; break;
            default : throw new \Exception('Invalid position'); break;
        }
        $tpl->assign('[POSITION]', $position_name);

        if ( ! empty($this->tabs)) {
            foreach ($this->tabs as $tab) {

                if ($tab['type'] == 'tab') {
                    if (isset($tab['options']['disabled']) && $tab['options']['disabled']) {
                        $tpl->tabs->elements->tab_disabled->assign('[ID]',    $tab['id']);
                        $tpl->tabs->elements->tab_disabled->assign('[TITLE]', $tab['title']);

                    } else {
                        $url   = (strpos($tab['url'], "#") !== false ? $tab['url'] . "&" : $tab['url'] . "#") . "{$this->resource}={$tab['id']}";
                        $class = $this->active_tab == $tab['id'] ? 'active' : '';

                        if (isset($tab['options']['onclick']) && $tab['options']['onclick']) {
                            $onclick = $tab['options']['onclick'];

                        } else {
                            if ($this->is_ajax) {
                                $onclick = "CoreUI.tabs.loadContent('{$this->resource}', '{$tab['id']}', '{$url}', event);";
                            } else {
                                $onclick = "if (event.button === 0 && ! event.ctrlKey) load('{$url}');";
                            }
                        }

                        $tpl->tabs->elements->tab->assign('[ID]',      $tab['id']);
                        $tpl->tabs->elements->tab->assign('[CLASS]',   $class);
                        $tpl->tabs->elements->tab->assign('[TITLE]',   $tab['title']);
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
        }

        return $tpl->render();
    }
} 