<?php

require_once 'Templater3.php';


/**
 * Class Panel
 */
class Panel {

    protected $active_tab     = '';
    protected $title          = '';
    protected $content        = '';
    protected $resource       = '';
    protected $url            = '';
    protected $tabs           = array();
    protected $theme_src      = '';
    protected $theme_location = '';


    /**
     * Panel constructor.
     * @param string $resource
     * @param string $url
     */
    public function __construct($resource, $url = '') {

        $this->resource = $resource;
        $this->url      = str_replace('?', '#', $url);

        if (isset($_GET[$this->resource])) {
            $this->active_tab = $_GET[$this->resource];
        }
    }


    /**
     * @param string $title
     */
    public function setTitle($title) {
        $this->title = $title;
    }


    /**
     * Добавление таба
     * @param string $title
     * @param string $id
     * @param bool   $disabled
     */
    public function addTab($title, $id, $disabled = false) {
        $this->tabs[] = array(
            'title'    => $title,
            'id'       => $id,
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
     */
    public function render() {

        $tpl = new Templater3(DOC_ROOT . "/core2/html/" . THEME . '/html/panel.html');

        $tpl->assign('[RESOURCE]', $this->resource);
        $tpl->assign('[CONTENT]',  $this->content);

        if ( ! empty($this->title)) {
            $tpl->title->assign('[TITLE]', $this->title);
        }

        if ($this->active_tab == '' && ! empty($this->tabs)) {
            reset($this->tabs);
            $tab = current($this->tabs);
            $this->active_tab = $tab['id'];
        }

        if ( ! empty($this->tabs)) {
            foreach ($this->tabs as $tab) {

                if ($tab['disabled']) {
                    $tpl->tabs->elements->tab_disabled->assign('[ID]',    $tab['id']);
                    $tpl->tabs->elements->tab_disabled->assign('[TITLE]', $tab['title']);

                } else {
                    $url   = (strpos($this->url, "#") !== false ? $this->url . "&" : $this->url . "#") . "{$this->resource}={$tab['id']}";
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