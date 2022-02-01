<?php
namespace Core2\Classes;
use Core2\Acl;
use Core2\Classes\Table\Exception;
use Core2\Classes\Table\Filter;
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
require_once 'Table/Filter.php';


/**
 * Class Table
 * @package Core2\Classes
 */
abstract class Table extends Acl {

    protected $resource                 = '';
    protected $show_select_rows         = true;
    protected $show_delete        = false;
    protected $show_column_manage = false;
    protected $show_templates     = false;
    protected $show_number_rows         = true;
    protected $show_service             = true;
    protected $show_header              = true;
    protected $show_footer              = true;
    protected $edit_url                 = '';
    protected $add_url                  = '';
    protected $data                     = [];
    protected $data_rows                = [];
    protected $columns                  = [];
    protected $buttons                  = [];
    protected $search_controls          = [];
    protected $filter_controls          = [];
    protected $records_total            = 0;
    protected $records_per_page         = 25;
    protected $records_per_page_default = 25;
    protected $records_seq              = false;
    protected $current_page             = 1;
    protected $round_record_count       = false;
    protected $is_ajax                  = false;


    /**
     * @var SessionContainer
     */
    protected $session        = null;
    protected $theme_src      = '';
    protected $theme_location = '';
    protected $date_mask      = "d.m.Y";
    protected $datetime_mask  = "d.m.Y H:i";
    protected $locutions      = [
        'All'                                        => 'Все',
        'Add'                                        => 'Добавить',
        'Delete'                                     => 'Удалить',
        'Are you sure you want to delete this post?' => 'Вы действительно хотите удалить эту запись?',
        'You must select at least one record'        => 'Нужно выбрать хотя бы одну запись',
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

    const FILTER_SELECT      = 'select';
    const FILTER_TEXT        = 'text';
    const FILTER_TEXT_STRICT = 'text_strict';
    const FILTER_DATE        = 'date';
    const FILTER_DATETIME    = 'datetime';
    const FILTER_NUMBER      = 'number';
    const FILTER_CHECKBOX    = 'checkbox';
    const FILTER_RADIO       = 'radio';

    const COLUMN_TEXT     = 'text';
    const COLUMN_HTML     = 'html';
    const COLUMN_DATE     = 'date';
    const COLUMN_DATETIME = 'datetime';
    const COLUMN_NUMBER   = 'number';
    const COLUMN_STATUS   = 'status';
    const COLUMN_SWITCH   = 'switch';


    /**
     * @param string $resource
     */
	public function __construct(string $resource) {

        parent::__construct();

        $this->resource = $resource;

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

        // FILTER
        if ( ! empty($_POST['filter']) && ! empty($_POST['filter'][$resource])) {
            $this->session->table->filter = $_POST['filter'][$resource];
        }
        if ( ! empty($_POST['filter_clear_' . $this->resource])) {
            $this->session->table->filter = [];
        }


        // RECORDS PER PAGE
        if (isset($_POST["count_{$this->resource}"])) {
            $this->session->table->records_per_page = abs((int)$_POST["count_{$this->resource}"]);
        }

        // COLUMNS
        if (isset($_POST["columns_{$this->resource}"]) && is_array($_POST["columns_{$this->resource}"])) {
            $columns = $_POST["columns_{$this->resource}"];

            $this->session->table->columns = [];

            if ( ! empty($columns)) {
                foreach ($columns as $column) {
                    if (is_string($column)) {
                        $this->session->table->columns[$column] = true;
                    }
                }
            }
        }

        if (isset($this->session->table->records_per_page) && $this->session->table->records_per_page >= 0) {
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
     * Установка количества строк на странице
     * @param int $count
     * @throws Exception
     */
    public function setRecordsPerPage(int $count) {

        if ($count < 0) {
            throw new Exception('Задано некорректное значение');
        }

        $this->records_per_page_default = $count;

        if ( ! isset($this->session->table->records_per_page)) {
            $this->records_per_page = $count === 0 ? 1000000000 : $count;
        }
    }


    /**
     *
     */
    public function showCheckboxes() {
        $this->show_select_rows = true;
    }


    /**
     *
     */
    public function hideCheckboxes() {
        $this->show_select_rows = false;
    }


    /**
     *
     */
    public function showNumberRows() {
        $this->show_number_rows = true;
    }


    /**
     *
     */
    public function hideNumberRows() {
        $this->show_number_rows = false;
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
     *
     */
    public function showService() {
        $this->show_service = true;
    }


    /**
     *
     */
    public function hideService() {
        $this->show_service = false;
    }


    /**
     *
     */
    public function showFooter() {
        $this->show_footer = true;
    }


    /**
     *
     */
    public function hideFooter() {
        $this->show_footer = false;
    }


    /**
     * @deprecated used showColumnManage()
     */
    public function showColumnsSwitcher() {
        $this->show_column_manage = true;
    }


    /**
     *
     */
    public function showColumnManage() {
        $this->show_column_manage = true;
    }


    /**
     *
     */
    public function hideColumnManage() {
        $this->show_column_manage = false;
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

        if ( ! empty($this->buttons)) {
            $buttons = [];

            foreach ($this->buttons as $button) {
                if ($button instanceof Button) {
                    $attributes = [];
                    foreach ($button->getAttributes() as $attr => $value) {
                        $attributes[] = "$attr=\"{$value}\"";
                    }

                    $implode_attributes = implode(' ', $attributes);
                    $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';

                    $buttons[] = "<button{$implode_attributes}>{$this->title}</button>";

                } elseif (is_string($button)) {
                    $buttons[] = $button;
                }
            }

            $tpl->assign('[BUTTONS]', implode(' ', $buttons));

        } else {
            $tpl->assign('[BUTTONS]', '');
        }


        if ($this->show_service) {
            if ($this->add_url &&
                ($this->checkAcl($this->resource, 'edit_all') ||
                 $this->checkAcl($this->resource, 'edit_owner')) &&
                ($this->checkAcl($this->resource, 'read_all') ||
                 $this->checkAcl($this->resource, 'read_owner'))
            ) {
                $tpl->service->add_button->assign('[URL]',      str_replace('?', '#', $this->add_url));
                $tpl->service->add_button->assign('[ADD_TEXT]', $this->getLocution('Add'));
            }

            if ($this->show_delete &&
                ($this->checkAcl($this->resource, 'delete_all') ||
                 $this->checkAcl($this->resource, 'delete_owner'))
            ) {
                $delete_text   = $this->getLocution('Delete');
                $delete_msg    = $this->getLocution('Are you sure you want to delete this post?');
                $no_select_msg = $this->getLocution('You must select at least one record');

                $tpl->service->del_button->assign('[DELETE_TEXT]',      $delete_text);
                $tpl->service->del_button->assign('[DELETE_MSG]',       $delete_msg);
                $tpl->service->del_button->assign('[DELETE_NO_SELECT]', $no_select_msg);
            }
        }

        if ($this->show_select_rows == true) {
            $tpl->header->touchBlock('checkboxes');
        }

        if ( ! empty($this->search_controls)) {
            $search_value = ! empty($this->session->table) && ! empty($this->session->table->search)
                ? $this->session->table->search
                : [];

            if ( ! empty($search_value) && count($search_value)) {
                $tpl->controls->search_control->touchBlock('search_clear');
            }

            $tpl->controls->touchBlock('search_control');

            foreach ($this->search_controls as $key => $search) {
                if ($search instanceof Search) {
                    $control_value     = $search_value[$key] ?? '';
                    $search_attributes = $search->getAttributes();
                    $attributes_str    = '';

                    if ( ! empty($search_attributes)) {
                        $attributes = [];
                        foreach ($search_attributes as $attr => $value) {
                            $attributes[] = "$attr=\"{$value}\"";
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $attributes_str     = $implode_attributes ? ' ' . $implode_attributes : '';
                    }

                    switch ($search->getType()) {
                        case self::SEARCH_TEXT :
                        case self::SEARCH_TEXT_STRICT :
                            $tpl->search_container->search_field->text->assign("[KEY]",     $key);
                            $tpl->search_container->search_field->text->assign("[VALUE]",   $control_value);
                            $tpl->search_container->search_field->text->assign("[IN_TEXT]", $attributes_str);
                            break;

                        case self::SEARCH_RADIO :
                            $data = $search->getData();
                            if ( ! empty($data)) {
                                $data  = array('' => $this->getLocution('All')) + $data;
                                foreach ($data as $radio_value => $radio_title) {
                                    $tpl->search_container->search_field->radio->assign("[KEY]",     $key);
                                    $tpl->search_container->search_field->radio->assign("[VALUE]",   $radio_value);
                                    $tpl->search_container->search_field->radio->assign("[TITLE]",   $radio_title);
                                    $tpl->search_container->search_field->radio->assign("[IN_TEXT]", $attributes_str);

                                    $is_checked = $control_value == $radio_value
                                        ? 'checked="checked"'
                                        : '';
                                    $tpl->search_container->search_field->radio->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->search_container->search_field->radio->reassign();
                                }
                            }
                            break;

                        case self::SEARCH_CHECKBOX :
                            $data = $search->getData();
                            if ( ! empty($data)) {
                                foreach ($data as $checkbox_value => $checkbox_title) {
                                    $tpl->search_container->search_field->checkbox->assign("[KEY]",     $key);
                                    $tpl->search_container->search_field->checkbox->assign("[VALUE]",   $checkbox_value);
                                    $tpl->search_container->search_field->checkbox->assign("[TITLE]",   $checkbox_title);
                                    $tpl->search_container->search_field->checkbox->assign("[IN_TEXT]", $attributes_str);

                                    $is_checked = is_array($control_value) && in_array($checkbox_value, $control_value)
                                        ? 'checked="checked"'
                                        : '';
                                    $tpl->search_container->search_field->checkbox->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->search_container->search_field->checkbox->reassign();
                                }
                            }
                            break;

                        case self::SEARCH_NUMBER :
                            $tpl->search_container->search_field->number->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->number->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->number->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->number->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case self::SEARCH_DATE :
                            $tpl->search_container->search_field->date->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->date->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->date->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->date->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case self::SEARCH_DATETIME :
                            $tpl->search_container->search_field->datetime->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->datetime->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->datetime->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->datetime->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case self::SEARCH_SELECT :
                            $data    = $search->getData();
                            $options = ['' => ''] + $data;
                            $tpl->search_container->search_field->select->assign("[KEY]",      $key);
                            $tpl->search_container->search_field->select->assign("[IN_TEXT]",  $attributes_str);
                            $tpl->search_container->search_field->select->fillDropDown("search-[RESOURCE]-[KEY]", $options, $control_value);
                            break;

                        case self::SEARCH_MULTISELECT :
                            $data = $search->getData();
                            $tpl->search_container->search_field->multiselect->assign("[KEY]",      $key);
                            $tpl->search_container->search_field->multiselect->assign("[IN_TEXT]",  $attributes_str);
                            $tpl->search_container->search_field->multiselect->fillDropDown("search-[RESOURCE]-[KEY]", $data, $control_value);
                            break;
                    }


                    $tpl->search_container->search_field->assign("[#]",        $key);
                    $tpl->search_container->search_field->assign("[OUT_TEXT]", $search->getOut());
                    $tpl->search_container->search_field->assign('[CAPTION]',  $search->getCaption());
                    $tpl->search_container->search_field->assign('[TYPE]',     $search->getType());
                    $tpl->search_container->search_field->reassign();
                }
            }
        }

        if ( ! empty($this->filter_controls)) {
            $filter_value = ! empty($this->session->table) && ! empty($this->session->table->filter)
                ? $this->session->table->filter
                : [];

            if ( ! empty($filter_value) && count($filter_value)) {
                $tpl->filter_controls->touchBlock('filter_clear');
            }

            foreach ($this->filter_controls as $key => $filter) {
                if ($filter instanceof Filter) {
                    $control_value     = $filter_value[$key] ?? '';
                    $filter_attributes = $filter->getAttributes();
                    $attributes_str    = '';

                    if ( ! empty($filter_attributes)) {
                        $attributes = [];
                        foreach ($filter_attributes as $attr => $value) {
                            $attributes[] = "$attr=\"{$value}\"";
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $attributes_str     = $implode_attributes ? ' ' . $implode_attributes : '';
                    }

                    switch ($filter->getType()) {
                        case self::FILTER_TEXT :
                        case self::FILTER_TEXT_STRICT :
                            $tpl->filter_controls->filter_control->text->assign("[KEY]",   $key);
                            $tpl->filter_controls->filter_control->text->assign("[VALUE]", $control_value);
                            $tpl->filter_controls->filter_control->text->assign("[TITLE]", $filter->getTitle());
                            $tpl->filter_controls->filter_control->text->assign("[ATTR]",  $attributes_str);
                        break;

                        case self::FILTER_RADIO :
                            $data = $filter->getData();
                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->radio->title->assign('[TITLE]', $filter->getTitle());
                            }

                            if ( ! empty($data)) {
                                foreach ($data as $radio_value => $radio_title) {
                                    $is_checked = $control_value == $radio_value
                                        ? 'checked="checked"'
                                        : '';
                                    $is_active = $control_value == $radio_value
                                        ? 'active'
                                        : '';

                                    $tpl->filter_controls->filter_control->radio->item->assign("[KEY]",        $key);
                                    $tpl->filter_controls->filter_control->radio->item->assign("[VALUE]",      $radio_value);
                                    $tpl->filter_controls->filter_control->radio->item->assign("[TITLE]",      $radio_title);
                                    $tpl->filter_controls->filter_control->radio->item->assign("[ATTR]",       $attributes_str);
                                    $tpl->filter_controls->filter_control->radio->item->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->filter_controls->filter_control->radio->item->assign("[IS_ACTIVE]",  $is_active);
                                    $tpl->filter_controls->filter_control->radio->item->reassign();
                                }
                            }
                            break;

                        case self::FILTER_CHECKBOX :
                            $data = $filter->getData();
                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->checkbox->title->assign('[TITLE]', $filter->getTitle());
                            }

                            if ( ! empty($data)) {
                                foreach ($data as $checkbox_value => $checkbox_title) {
                                    $is_checked = is_array($control_value) && in_array($checkbox_value, $control_value)
                                        ? 'checked="checked"'
                                        : '';
                                    $is_active = is_array($control_value) && in_array($checkbox_value, $control_value)
                                        ? 'active'
                                        : '';

                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[KEY]",        $key);
                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[VALUE]",      $checkbox_value);
                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[TITLE]",      $checkbox_title);
                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[ATTR]",       $attributes_str);
                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[IS_CHECKED]", $is_checked);
                                    $tpl->filter_controls->filter_control->checkbox->item->assign("[IS_ACTIVE]",  $is_active);
                                    $tpl->filter_controls->filter_control->checkbox->item->reassign();
                                }
                            }
                            break;

                        case self::FILTER_NUMBER :
                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->number->title->assign('[TITLE]', $filter->getTitle());
                            }

                            $tpl->filter_controls->filter_control->number->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->number->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->number->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->number->assign("[ATTR]",        $attributes_str);
                            break;

                        case self::FILTER_DATE :
                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->date->title->assign('[TITLE]', $filter->getTitle());
                            }

                            $tpl->filter_controls->filter_control->date->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->date->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->date->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->date->assign("[ATTR]",        $attributes_str);
                            break;

                        case self::FILTER_DATETIME :
                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->datetime->title->assign('[TITLE]', $filter->getTitle());
                            }

                            $tpl->filter_controls->filter_control->datetime->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->datetime->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->datetime->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->datetime->assign("[ATTR]",        $attributes_str);
                            break;

                        case self::FILTER_SELECT :
                            $data    = $filter->getData();
                            $options = ['' => ''] + $data;

                            if ($filter->getTitle()) {
                                $tpl->filter_controls->filter_control->select->title->assign('[TITLE]', $filter->getTitle());
                            }

                            $tpl->filter_controls->filter_control->select->assign("[KEY]",  $key);
                            $tpl->filter_controls->filter_control->select->assign("[ATTR]", $attributes_str);
                            $tpl->filter_controls->filter_control->select->fillDropDown("filter-[RESOURCE]-[KEY]", $options, $control_value);
                            break;
                    }

                    $tpl->filter_controls->filter_control->assign("[#]",    $key);
                    $tpl->filter_controls->filter_control->assign('[TYPE]', $filter->getType());
                    $tpl->filter_controls->filter_control->reassign();
                }
            }
        }


        if ($this->show_column_manage) {
            $tpl->controls->touchBlock('column_switcher_control');

            foreach ($this->columns as $key => $column) {
                if ($column instanceof Column) {
                    $field = $column->getField();

                    if (isset($this->session->table->columns)) {
                        if (empty($this->session->table->columns[$field])) {
                            $column->hide();
                        } else {
                            $column->show();
                        }
                    }


                    $tpl->column_switcher_container->column_switcher_field->assign('[COLUMN]',  $field);
                    $tpl->column_switcher_container->column_switcher_field->assign('[TITLE]',   $column->getTitle());
                    $tpl->column_switcher_container->column_switcher_field->assign('[CHECKED]', $column->isShow() ? 'checked="checked"' : '');
                    $tpl->column_switcher_container->column_switcher_field->reassign();
                }
            }
        }


        foreach ($this->columns as $key => $column) {
            if ($column instanceof Column && $column->isShow()) {
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


                if ($this->show_number_rows) {
                    $tpl->header->touchBlock('header_number');
                }

                $tpl->header->cell->reassign();
            }
        }


        $this->fetchData();

        if ($this->show_service) {
            $tpl->service->assign('[TOTAL_RECORDS]', ($this->round_record_count ? '~' : '') . $this->records_total);
        }

        if ( ! empty($this->data_rows)) {
            $row_index  = 1;
            $row_number = $this->current_page > 1
                ? (($this->current_page - 1) * $this->records_per_page) + 1
                : 1;

            foreach ($this->data_rows as $row) {
                $tpl->row->assign('[ID]', $row->id);

                if ($this->show_number_rows) {
                    $tpl->row->row_number->assign('[#]', $row_number);
                }

                if ($this->edit_url &&
                    ($this->checkAcl($this->resource, 'edit_all') ||
                         $this->checkAcl($this->resource, 'edit_owner') ||
                     $this->checkAcl($this->resource, 'read_all') ||
                     $this->checkAcl($this->resource, 'read_owner'))
                ) {
                    $edit_url = $this->replaceTCOL($row, $this->edit_url);
                    $row->setAttrAppend('class', 'edit-row');

                    if (strpos($edit_url, 'javascript:') === 0) {
                        $row->setAttrAppend('onclick', substr($edit_url, 11));
                    } else {
                        $edit_url = str_replace('?', '#', $edit_url);
                        $row->setAttrAppend('onclick', "load('{$edit_url}');");
                    }
                }

                foreach ($this->columns as $column) {
                    if ($column instanceof Column && $column->isShow()) {
                        $cell  = $row->{$column->getField()};
                        $value = $cell->getValue();

                        switch ($column->getType()) {
                            case self::COLUMN_TEXT:
                                $tpl->row->col->default->assign('[VALUE]', htmlspecialchars($value));
                                break;

                            case self::COLUMN_NUMBER:
                                $value = strrev($value);
                                $value = (string)preg_replace('/(\d{3})(?=\d)(?!\d*\.)/', '$1;psbn&', $value);
                                $value = strrev($value);
                                $tpl->row->col->default->assign('[VALUE]', $value);
                                break;

                            case self::COLUMN_HTML:
                                $tpl->row->col->default->assign('[VALUE]', $value);
                                break;

                            case self::COLUMN_DATE:
                                $date = $value ? date($this->date_mask, strtotime($value)) : '';
                                $tpl->row->col->default->assign('[VALUE]', $date);
                                break;

                            case self::COLUMN_DATETIME:
                                $date = $value ? date($this->datetime_mask, strtotime($value)) : '';
                                $tpl->row->col->default->assign('[VALUE]', $date);
                                break;

                            case self::COLUMN_STATUS:
                                if ($value == 'Y' || $value == 1) {
                                    $img = "<img src=\"{$this->theme_src}/list/img/lightbulb.png\" alt=\"_tr(вкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                } else {
                                    $img = "<img src=\"{$this->theme_src}/list/img/lightbulb_off.png\" alt=\"_tr(выкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                }
                                $tpl->row->col->default->assign('[VALUE]', $img);
                                break;

                            case self::COLUMN_SWITCH:
                                $cell->setAttr('onclick', "event.cancelBubble = true;");

                                $options = $column->getOptions();
                                $color   = ! empty($options['color']) ? "color-{$options['color']}" : 'color-primary';
                                $value_y = $options['value_Y'] ?? 'Y';
                                $value_n = $options['value_N'] ?? 'N';

                                $tpl->row->col->switch->assign('[TABLE]',     $options['table'] ?? '');
                                $tpl->row->col->switch->assign('[FIELD]',     $column->getField());
                                $tpl->row->col->switch->assign('[NMBR]',      $row_number);
                                $tpl->row->col->switch->assign('[CHECKED_Y]', $value == $value_y ? 'checked="checked"' : '');
                                $tpl->row->col->switch->assign('[CHECKED_N]', $value == $value_n ? 'checked="checked"' : '');
                                $tpl->row->col->switch->assign('[COLOR]',     $color);
                                $tpl->row->col->switch->assign('[VALUE_Y]',   $value_y);
                                $tpl->row->col->switch->assign('[VALUE_N]',   $value_n);
                                break;
                        }

                        // Атрибуты ячейки
                        $column_attributes = $cell->getAttributes();
                        $attributes        = [];
                        foreach ($column_attributes as $attr => $value) {
                            $attributes[] = "$attr=\"{$value}\"";
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';

                        $tpl->row->col->assign('[ATTR]', $implode_attributes);

                        if (end($this->columns) != $column) $tpl->row->col->reassign();
                    }
                }


                $attribs = $row->getAttributes();

                if ( ! empty($attribs)) {
                    $attribs_string = '';
                    foreach ($attribs as $name => $attr) {
                        $attribs_string .= " {$name}=\"{$attr}\"";
                    }
                    $tpl->row->assign('<tr', '<tr ' . $attribs_string);
                }

                if ($this->show_select_rows) {
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


        if ($this->show_footer) {

            // Pagination
            $count_pages = ceil($this->records_total / $this->records_per_page);

            if ($count_pages > 1) {
                $tpl->footer->pages->touchBlock('gotopage');
            }

            $tpl->footer->pages->assign('[CURRENT_PAGE]', $this->current_page);
            $tpl->footer->pages->assign('[COUNT_PAGES]',  $count_pages);

            if ($this->current_page > 1) {
                $tpl->footer->pages->prev->assign('[PREV_PAGE]', $this->current_page - 1);
            }
            if ($this->current_page < $count_pages) {
                $tpl->footer->pages->next->assign('[NEXT_PAGE]', $this->current_page + 1);
            }


            $per_page_list = [
                '25'   => '25',
                '50'   => '50',
                '100'  => '100',
                '1000' => '1000',
            ];

            if ( ! isset($per_page_list[$this->records_per_page_default]) &&
                $this->records_per_page_default > 0
            ) {
                $per_page_list[$this->records_per_page_default] = $this->records_per_page_default;
            }

            ksort($per_page_list);

            $per_page_list[0] = $this->getLocution('All');

            $tpl->footer->pages->per_page->fillDropDown(
                'records-per-page-[RESOURCE]',
                $per_page_list,
                $this->records_per_page == 1000000000 ? 0 : $this->records_per_page
            );
        }

        return $tpl->render();
    }


    /**
     * Получение данных по таблице
     * @return array
     */
    public function toArray(): array {

        $toolbar   = [];
        $filter    = [];
        $search    = [];
        $columns   = [];
        $records   = [];
        $events    = [];
        $templates = [];

        $count_pages = ceil($this->records_total / $this->records_per_page);

        if ( ! empty($this->buttons)) {
            foreach ($this->buttons as $button) {
                if ($button instanceof Table\Button) {
                    $toolbar['buttons'][] = $button->toArray();
                }
            }
        }

        if ($this->add_url) {
            $toolbar['btnAdd'] = true;
            $events['onAdd'] = "load('{$this->add_url}')";
        }

        if ($this->show_delete) {
            $toolbar['btnDelete'] = true;
            $events['onDelete'] = "CoreUI.table.deleteRows('{$this->resource}')";
        }

        if ($this->edit_url) {
            $events['onClickRow']       = "load('{$this->edit_url}')";
            $events['onSorting']        = "";
            $events['onSearch']         = "";
            $events['onFastSearch']     = "";
            $events['onSaveTemplate']   = "";
            $events['onSelectTemplate'] = "";
        }

        if ( ! empty($this->data_rows)) {
            foreach ($this->data_rows as $row) {
                if ($row instanceof Table\Row) {
                    $records[] = $row->toArray();
                }
            }
        }

        if ( ! empty($this->columns)) {
            foreach ($this->columns as $column) {
                if ($column instanceof Table\Column) {
                    $columns[] = $column->toArray();
                }
            }
        }

        if ( ! empty($this->search_controls)) {
            foreach ($this->search_controls as $search_control) {
                if ($search_control instanceof Table\Search) {
                    $search[] = $search_control->toArray();
                }
            }
        }

        if ( ! empty($this->filter_controls)) {
            foreach ($this->filter_controls as $filter_control) {
                if ($filter_control instanceof Table\Filter) {
                    $filter[] = $filter_control->toArray();
                }
            }
        }

        $data = [
            'resource' => $this->resource,
            'show'     => [
                'header'          => $this->show_header,
                'toolbar'         => true,
                'footer'          => $this->show_footer,
                'lineNumbers'     => $this->show_number_rows,
                'selectRows'      => $this->show_select_rows,
                'columnsSwitcher' => $this->show_column_manage,
                'templates'       => $this->show_templates,
            ],

            'currentPage'        => $this->current_page,
            'countPages'         => $count_pages,
            'recordsPerPage'     => $this->records_per_page,
            'recordsTotal'       => $this->records_total,
            'recordsPerPageList' => [ 25, 50, 100, 1000, ],
        ];

        if ( ! empty($filter)) {
            $data['filter'] = $filter;
        }
        if ( ! empty($search)) {
            $data['search'] = $search;
        }
        if ( ! empty($templates)) {
            $data['templates'] = $templates;
        }
        if ( ! empty($toolbar)) {
            $data['toolbar'] = $toolbar;
        }
        if ( ! empty($events)) {
            $data['events'] = $events;
        }
        if ( ! empty($columns)) {
            $data['columns'] = $columns;
        }
        if ( ! empty($records)) {
            $data['records'] = $records;
        }

        return $data;
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
     * Добавление поля для фильтрации
     * @param string $field
     * @param string $type
     * @param string $title
     * @return void
     * @throws Exception
     */
    public function addFilter(string $field, string $type = self::FILTER_TEXT, string $title = ''): Filter {

        $filter = new Filter($field, $type, $title);

        $this->filter_controls[] = $filter;
        return $filter;
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
     * @param string $text
     */
    public function setLocution(string $locution, string $text) {

        if (isset($this->locutions[$locution])) {
            $this->locutions[$locution] = $text;
        }
    }


    /**
     * @param string $locution
     * @return string
     */
    protected function getLocution(string $locution): string {

        return isset($this->locutions[$locution])
            ? htmlspecialchars($this->locutions[$locution])
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