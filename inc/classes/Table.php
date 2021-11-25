<?php
namespace Core2\Classes;
use Core2\Acl;
use Core2\Classes\Table\Row;
use Core2\Classes\Table\Button;
use Core2\Classes\Table\Column;
use Core2\Classes\Table\Search;
use Laminas\Session\Container as SessionContainer;

require_once 'Templater3.php';

require_once 'Table/Exception.php';
require_once 'Table/Row.php';
require_once 'Table/Cell.php';
require_once 'Table/Button.php';
require_once 'Table/Column.php';
require_once 'Table/Search.php';


/**
 * Class Table
 * @package Core2\Classes
 */
abstract class Table extends Acl {

    protected $resource              = '';
    protected $show_checkboxes       = true;
    protected $show_delete           = false;
    protected $show_columns_switcher = false;
    protected $show_templates        = false;
    protected $edit_url              = '';
    protected $add_url               = '';
    protected $data                  = [];
    protected $data_rows             = [];
    protected $columns               = [];
    protected $buttons               = [];
    protected $search_controls       = [];
    protected $records_total         = 0;
    protected $records_per_page      = 25;
    protected $records_seq           = false;
    protected $current_page          = 1;
    protected $round_record_count    = false;
    protected $is_ajax               = false;


    /**
     * @var SessionContainer
     */
    protected $session        = null;
    protected $is_fetched     = false;
    protected $theme_src      = '';
    protected $theme_location = '';
    protected $date_mask      = "d.m.Y";
    protected $datetime_mask  = "d.m.Y H:i";
    protected $lang           = 'ru';
    protected $locutions      = [
        'ru' => [
            'Search'                                     => 'Поиск',
            'Clear'                                      => 'Очистить',
            'All'                                        => 'Все',
            'Add'                                        => 'Добавить',
            'Delete'                                     => 'Удалить',
            'num'                                        => '№',
            'from'                                       => 'из',
            'off'                                        => 'выкл',
            'on'                                         => 'вкл',
            'Total'                                      => 'Всего',
            'No records'                                 => 'Нет записей',
            'Are you sure you want to delete this post?' => 'Вы действительно хотите удалить эту запись?',
            'You must select at least one record'        => 'Нужно выбрать хотя бы одну запись',
        ],
        'en' => [
            'Search'                                     => 'Search',
            'Clear'                                      => 'Clear',
            'All'                                        => 'All',
            'Add'                                        => 'Add',
            'Delete'                                     => 'Delete',
            'num'                                        => '№',
            'from'                                       => 'from',
            'off'                                        => 'off',
            'on'                                         => 'on',
            'Total'                                      => 'Total',
            'No records'                                 => 'No records',
            'Are you sure you want to delete this post?' => 'Are you sure you want to delete this post?',
            'You must select at least one record'        => 'You must select at least one record',
        ],
    ];

    const SEARCH_SELECT      = 'select';
    const SEARCH_TEXT        = 'text';
    const SEARCH_TEXT_STRICT = 'text_strict';
    const SEARCH_DATE        = 'date';
    const SEARCH_DATETIME    = 'datetime';
    const SEARCH_NUMBER      = 'number';
    const SEARCH_CHECKBOX    = 'checkbox';
    const SEARCH_RADIO       = 'radio';
    const SEARCH_MULTISELECT = 'multiselect';

    const COLUMN_TEXT     = 'text';
    const COLUMN_HTML     = 'html';
    const COLUMN_DATE     = 'date';
    const COLUMN_DATETIME = 'datetime';
    const COLUMN_NUMBER   = 'number';
    const COLUMN_STATUS   = 'status';


    /**
     * @param string $resource
     */
	public function __construct(string $resource) {

        parent::__construct();

        $this->resource = $resource;
        $this->lang     = 'ru';

        $this->theme_src      = DOC_PATH . 'core2/html/' . THEME;
        $this->theme_location = DOC_ROOT . 'core2/html/' . THEME;

        $this->current_page = isset($_GET["_page_{$this->resource}"]) && $_GET["_page_{$this->resource}"] > 0
            ? (int)$_GET["_page_{$this->resource}"]
            : 1;

        $this->session = new SessionContainer($this->resource);

        if ( ! isset($this->session->table)) {
            $this->session->table = new \stdClass();
        }

        // SEARCH
        if ( ! empty($_POST['search']) && ! empty($_POST['search'][$resource])) {
            $this->session->table->search = $_POST['search'][$resource];
        }
        if ( ! empty($_POST['search_clear_' . $this->resource])) {
            $this->session->table->search = [];
        }


        // RECORDS PER PAGE
        if (isset($_POST["count_{$this->resource}"])) {
            $this->session->table->records_per_page = abs((int)$_POST["count_{$this->resource}"]);
        }

        if (isset($this->session->table->records_per_page)) {
            $this->records_per_page = $this->session->table->records_per_page;
            $this->records_per_page = $this->records_per_page === 0
                ? 1000000000
                : $this->records_per_page;
        }

        // ORDERING
        if ( ! empty($_POST['order_' . $resource])) {
            $order = $_POST['order_' . $resource];

            if (empty($this->session->table->order)) {
                $this->session->table->order      = $order;
                $this->session->table->order_type = "asc";

            } else {
                if ($order == $this->session->table->order) {
                    if ($this->session->table->order_type == "asc") {
                        $this->session->table->order_type = "desc";

                    } elseif ($this->session->table->order_type == "desc") {
                        $this->session->table->order      = "";
                        $this->session->table->order_type = "";

                    } elseif ($this->session->table->order_type == "") {
                        $this->session->table->order_type = "asc";
                    }

                } else {
                    $this->session->table->order      = $order;
                    $this->session->table->order_type = "asc";
                }
            }
        }


        // Из class.list
        // Нужно для удаления
        $sess = new SessionContainer('List');
        $tmp        = ! empty($sess->{$this->resource}) ? $sess->{$this->resource} : [];
        $tmp['loc'] = $this->is_ajax ? $_SERVER['QUERY_STRING'] . "&__{$this->resource}=ajax" : $_SERVER['QUERY_STRING'];
        $sess->{$this->resource} = $tmp;
    }


    /**
     * @param string $edit_url
     */
    public function setEditUrl(string $edit_url) {
        $this->edit_url = $edit_url;
    }


    /**
     * @param string $add_url
     */
    public function setAddUrl(string $add_url) {
        $this->add_url = $add_url;
    }


    /**
     * @param bool $is_ajax
     */
    public function setAjax(bool $is_ajax = true) {

        $this->is_ajax = $is_ajax;
    }


    /**
     *
     */
    public function showCheckboxes() {
        $this->show_checkboxes = true;
    }


    /**
     *
     */
    public function hideCheckboxes() {
        $this->show_checkboxes = false;
    }


    /**
     *
     */
    public function showDelete() {
        $this->show_delete = true;
    }


    /**
     *
     */
    public function hideDelete() {
        $this->show_delete = false;
    }


    /**
     * Рендеринг таблицы
     * @return string
     * @throws \Exception
     */
    public function render(): string {

        if ( ! $this->checkAcl($this->resource, 'list_all') &&
             ! $this->checkAcl($this->resource, 'list_owner')
        ) {
            return '';
        }

        $tpl = new \Templater3($this->theme_location . '/html/table.html');
        $tpl->assign('[THEME_SRC]', $this->theme_src);
        $tpl->assign('[RESOURCE]',  $this->resource);
        $tpl->assign('[IS_AJAX]',   (int)$this->is_ajax);
        $tpl->assign('[LOCATION]',  $this->is_ajax ? $_SERVER['QUERY_STRING'] . "&__{$this->resource}=ajax" : $_SERVER['QUERY_STRING']);
        $tpl->assign('[BUTTONS]',   implode('', $this->buttons));

        if ($this->add_url &&
            ($this->checkAcl($this->resource, 'edit_all') ||
             $this->checkAcl($this->resource, 'edit_owner')) &&
            ($this->checkAcl($this->resource, 'read_all') ||
             $this->checkAcl($this->resource, 'read_owner'))
        ) {
            $tpl->add_button->assign('[URL]', str_replace('?', '#', $this->add_url));
        }

        if ($this->show_delete &&
            ($this->checkAcl($this->resource, 'delete_all') ||
             $this->checkAcl($this->resource, 'delete_owner'))
        ) {
            $delete_msg    = $this->getLocution('Are you sure you want to delete this post?');
            $no_select_msg = $this->getLocution('You must select at least one record');

            $tpl->del_button->assign('[DELETE_MSG]',       $delete_msg);
            $tpl->del_button->assign('[DELETE_NO_SELECT]', $no_select_msg);
        }

        if ($this->show_checkboxes == true) {
            $tpl->header->touchBlock('checkboxes');
        }

        if ( ! empty($this->search_controls)) {
            $search_value = ! empty($this->session->table) && ! empty($this->session->table->search)
                ? $this->session->table->search
                : [];

            if ( ! empty($search_value) && count($search_value)) {
                $tpl->search->touchBlock('clear');
            }

            foreach ($this->search_controls as $key => $search) {
                if ($search instanceof Search) {
                    $control_value = $search_value[$key] ?? '';

                    switch ($search->getType()) {
                        case self::SEARCH_TEXT :
                            $tpl->search->field->text->assign("[KEY]",     $key);
                            $tpl->search->field->text->assign("[VALUE]",   $control_value);
                            $tpl->search->field->text->assign("[IN_TEXT]", $search->getIn());
                            break;

                        case self::SEARCH_RADIO :
                            $data = $search->getData();
                            if ( ! empty($data)) {
                                $data  = array('' => $this->getLocution('All')) + $data;
                                foreach ($data as $radio_value => $radio_title) {
                                    $tpl->search->field->radio->assign("[KEY]",     $key);
                                    $tpl->search->field->radio->assign("[VALUE]",   $radio_value);
                                    $tpl->search->field->radio->assign("[TITLE]",   $radio_title);
                                    $tpl->search->field->radio->assign("[IN_TEXT]", $search->getIn());

                                    $is_checked = $control_value == $radio_value
                                        ? 'checked="checked"'
                                        : '';
                                    $tpl->search->field->radio->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->search->field->radio->reassign();
                                }
                            }
                            break;

                        case self::SEARCH_CHECKBOX :
                            $data = $search->getData();
                            if ( ! empty($data)) {
                                foreach ($data as $checkbox_value => $checkbox_title) {
                                    $tpl->search->field->checkbox->assign("[KEY]",     $key);
                                    $tpl->search->field->checkbox->assign("[VALUE]",   $checkbox_value);
                                    $tpl->search->field->checkbox->assign("[TITLE]",   $checkbox_title);
                                    $tpl->search->field->checkbox->assign("[IN_TEXT]", $search->getIn());

                                    $is_checked = is_array($control_value) && in_array($checkbox_value, $control_value)
                                        ? 'checked="checked"'
                                        : '';
                                    $tpl->search->field->checkbox->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->search->field->checkbox->reassign();
                                }
                            }
                            break;

                        case self::SEARCH_DATE :
                            $tpl->search->field->date->assign("[KEY]",         $key);
                            $tpl->search->field->date->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search->field->date->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search->field->date->assign("[IN_TEXT]",     $search->getIn());
                            break;

                        case self::SEARCH_DATETIME :
                            $tpl->search->field->datetime->assign("[KEY]",         $key);
                            $tpl->search->field->datetime->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search->field->datetime->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search->field->datetime->assign("[IN_TEXT]",     $search->getIn());
                            break;

                        case self::SEARCH_SELECT :
                            $data    = $search->getData();
                            $options = ['' => ''] + $data;
                            $tpl->search->field->select->assign("[KEY]",      $key);
                            $tpl->search->field->select->assign("[IN_TEXT]",  $search->getIn());
                            $tpl->search->field->select->fillDropDown("search-[RESOURCE]-[KEY]", $options, $control_value);
                            break;

                        case self::SEARCH_MULTISELECT :
                            $data = $search->getData();
                            $tpl->search->field->multiselect->assign("[KEY]",      $key);
                            $tpl->search->field->multiselect->assign("[IN_TEXT]",  $search->getIn());
                            $tpl->search->field->multiselect->fillDropDown("search-[RESOURCE]-[KEY]", $data, $control_value);
                            break;
                    }


                    $tpl->search->field->assign("[#]",        $key);
                    $tpl->search->field->assign("[OUT_TEXT]", $search->getOut());
                    $tpl->search->field->assign('[CAPTION]',  $search->getCaption());
                    $tpl->search->field->assign('[TYPE]',     $search->getType());
                    $tpl->search->field->reassign();
                }
            }
        }


        foreach ($this->columns as $key => $column) {
            if ($column instanceof Column) {
                if ($column->isSorting()) {
                    if (isset($this->session->table->order) && $this->session->table->order == $key + 1) {
                        if ($this->session->table->order_type == "asc") {
                            $tpl->header->cell->sort->touchBlock('order_asc');
                        } elseif ($this->session->table->order_type == "desc") {
                            $tpl->header->cell->sort->touchBlock('order_desc');
                        }
                    }

                    $width = $column->getAttr('width');
                    if ($width) {
                        $tpl->header->cell->sort->assign('<th', "<th width=\"{$width}\"");
                    }

                    $tpl->header->cell->sort->assign('[COLUMN_NUMBER]', ($key + 1));
                    $tpl->header->cell->sort->assign('[CAPTION]',       $column->getTitle());

                } else {
                    $width = $column->getAttr('width');
                    if ($width) {
                        $tpl->header->cell->no_sort->assign('<th', "<th width=\"{$width}\"");
                    }
                    $tpl->header->cell->no_sort->assign('[CAPTION]', $column->getTitle());
                }

                $tpl->header->cell->reassign();
            }
        }


        $this->fetchData();
        $tpl->assign('[TOTAL_RECORDS]', ($this->round_record_count ? '~' : '') . $this->records_total);

        if ( ! empty($this->data_rows)) {
            $row_index  = 1;
            $row_number = $this->current_page > 1
                ? (($this->current_page - 1) * $this->records_per_page) + 1
                : 1;

            foreach ($this->data_rows as $row) {
                $tpl->row->assign('[ID]', $row->id);
                $tpl->row->assign('[#]',  $row_number);


                if ($this->edit_url &&
                    ($this->checkAcl($this->resource, 'edit_all') ||
                         $this->checkAcl($this->resource, 'edit_owner') ||
                     $this->checkAcl($this->resource, 'read_all') ||
                     $this->checkAcl($this->resource, 'read_owner'))
                ) {
                    $edit_url = $this->replaceTCOL($row, $this->edit_url);
                    $row->setAppendAttr('class', 'edit-row');

                    if (strpos($edit_url, 'javascript:') === 0) {
                        $row->setAppendAttr('onclick', substr($edit_url, 11));
                    } else {
                        $edit_url = str_replace('?', '#', $edit_url);
                        $row->setAppendAttr('onclick', "load('{$edit_url}');");
                    }
                }

                foreach ($this->columns as $column) {
                    if ($column instanceof Column) {
                        $cell  = $row->{$column->getField()};
                        $value = $cell->getValue();

                        switch ($column->getType()) {
                            case self::COLUMN_TEXT:
                                $tpl->row->col->assign('[VALUE]', htmlspecialchars($value));
                                break;

                            case self::COLUMN_NUMBER:
                                $value = strrev($value);
                                $value = (string)preg_replace('/(\d{3})(?=\d)(?!\d*\.)/', '$1;psbn&', $value);
                                $value = strrev($value);
                                $tpl->row->col->assign('[VALUE]', $value);
                                break;

                            case self::COLUMN_HTML:
                                $tpl->row->col->assign('[VALUE]', $value);
                                break;

                            case self::COLUMN_DATE:
                                $date = $value ? date($this->date_mask, strtotime($value)) : '';
                                $tpl->row->col->assign('[VALUE]', $date);
                                break;

                            case self::COLUMN_DATETIME:
                                $date = $value ? date($this->datetime_mask, strtotime($value)) : '';
                                $tpl->row->col->assign('[VALUE]', $date);
                                break;

                            case self::COLUMN_STATUS:
                                if ($value == 'Y' || $value == 1) {
                                    $img = "<img src=\"{$this->theme_src}/list/img/lightbulb.png\" alt=\"_tr(вкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                } else {
                                    $img = "<img src=\"{$this->theme_src}/list/img/lightbulb_off.png\" alt=\"_tr(выкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                }
                                $tpl->row->col->assign('[VALUE]', $img);
                                break;
                        }

                        // Атрибуты ячейки
                        $column_attributes = $cell->getAttribs();
                        $attributes        = array();
                        foreach ($column_attributes as $attr => $value) {
                            $attributes[] = "$attr=\"{$value}\"";
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';

                        $tpl->row->col->assign('<td>', "<td{$implode_attributes}>");

                        if (end($this->columns) != $column) $tpl->row->col->reassign();
                    }
                }


                $attribs = $row->getAttribs();

                if ( ! empty($attribs)) {
                    $attribs_string = '';
                    foreach ($attribs as $name => $attr) {
                        $attribs_string .= " {$name}=\"{$attr}\"";
                    }
                    $tpl->row->assign('<tr', '<tr ' . $attribs_string);
                }

                if ($this->show_checkboxes) {
                    $tpl->row->checkboxes->assign('[ID]', $row->id);
                    $tpl->row->checkboxes->assign('[#]',  $row_index);
                    $row_index++;
                }

                $row_number++;

                $tpl->row->reassign();
            }

        } else {
            $tpl->touchBlock('no_rows');
        }



        // Pagination
        $count_pages = ceil($this->records_total / $this->records_per_page);
        $tpl->pages->assign('[CURRENT_PAGE]', $this->current_page);
        $tpl->pages->assign('[COUNT_PAGES]',  $count_pages);

        if ($count_pages > 1 || $this->records_per_page > 25) {
            $tpl->pages->touchBlock('gotopage');
            $tpl->pages->touchBlock('per_page');

            if ($this->current_page > 1) {
                $tpl->pages->prev->assign('[PREV_PAGE]', $this->current_page - 1);
            }
            if ($this->current_page < $count_pages) {
                $tpl->pages->next->assign('[NEXT_PAGE]', $this->current_page + 1);
            }
        }

        $tpl->pages->fillDropDown(
            'records-per-page-[RESOURCE]',
            [
                '25'   => '25',
                '50'   => '50',
                '100'  => '100',
                '1000' => '1000',
                '0'   => $this->getLocution('All'),
            ], $this->records_per_page == 1000000000 ? 0 : $this->records_per_page
        );

        return $tpl->render();
    }


    /**
     * Получение данных по таблице
     * @return array
     */
    public function toArray(): array {

        return [
            'resource'              => $this->resource,
            'show_checkboxes'       => $this->show_checkboxes,
            'show_delete'           => $this->show_delete,
            'show_templates'        => $this->show_templates,
            'show_columns_switcher' => $this->show_columns_switcher,
            'edit_url'              => $this->edit_url,
            'add_url'               => $this->add_url,
            'columns'               => $this->columns,
            'buttons'               => $this->buttons,
            'search'                => $this->search_controls,
            'records_per_page'      => $this->records_per_page,
            'records_total'         => $this->records_total,
            'current_page'          => $this->current_page,
            'data'                  => $this->data_rows,
        ];
    }


    /**
     * Добавление кнопки
     * @param string $title
     * @return Button
     */
    public function addButton(string $title): Button {
        return $this->buttons[] = new Button($title);
    }


    /**
     * Добавление своего контрола
     * @param string $html
     */
    public function addCustomControl(string $html) {
        $this->buttons[] = $html;
    }


    /**
     * Добавление колонки
     * @param string $title
     * @param string $field
     * @param string $type
     * @param string $width OPTIONAL parameter width column
     * @return Column
     * @throws \Exception
     */
    public function addColumn(string $title, string $field, string $type = self::COLUMN_TEXT, string $width = ''): Column {

        $column = new Column($title, $field, strtolower($type));

        if ($width) {
            $column->setAttr('width', $width);
        }

        $this->columns[] = $column;
        return $column;
    }


    /**
     * Добавление поля для поиска
     * @param string $title caption
     * @param string $field destination field name
     * @param string $type  type of search field
     * @return Search
     * @throws \Exception
     */
    public function addSearch(string $title, string $field, string $type = self::SEARCH_TEXT): Search {

        $search = new Search($title, $field, $type);

        $this->search_controls[] = $search;
        return $search;
    }


    /**
     * Исходные данные
     * @param mixed $data
     */
    public function setData($data) {
        $this->data = $data;
    }


    /**
     * Получение данных.
     * @return array
     */
    abstract public function fetchData(): array;


    /**
     * @param string $locution
     * @return string
     */
    protected function getLocution(string $locution): string {
        return isset($this->locutions[$this->lang][$locution])
            ? htmlspecialchars($this->locutions[$this->lang][$locution])
            : htmlspecialchars($locution);
    }


    /**
     * Замена TCOL_ на значение указанного поля
     * @param array|Row  $row Данные
     * @param string     $str Строка с TCOL_ вставками
     * @return string
     */
    protected function replaceTCOL($row, string $str): string {

        if (strpos($str, 'TCOL_') !== false) {
            foreach ($row as $field => $value) {
                $value = htmlspecialchars($value);
                $value = addslashes($value);
                $str   = str_replace('TCOL_' . strtoupper($field), $value, $str);
            }
            return $str;
        } else {
            return $str;
        }
    }
}