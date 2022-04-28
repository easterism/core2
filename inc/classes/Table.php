<?php
namespace Core2\Classes;
use Core2\Acl;
use Core2\Classes\Table\Exception;
use Core2\Classes\Table\Filter;
use Core2\Classes\Table\Render;
use Core2\Classes\Table\Button;
use Core2\Classes\Table\Column;
use Core2\Classes\Table\Search;
use Laminas\Session\Container as SessionContainer;


require_once 'Acl.php';
require_once 'Table/Render.php';
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
    protected $show_delete              = false;
    protected $show_column_manage       = false;
    protected $show_templates           = false;
    protected $show_number_rows         = true;
    protected $show_service             = true;
    protected $show_header              = true;
    protected $show_footer              = true;
    protected $edit_url                 = '';
    protected $add_url                  = '';
    protected $table_name               = '';
    protected $data                     = [];
    protected $data_rows                = [];
    protected $columns                  = [];
    protected $buttons                  = [];
    protected $search_controls          = [];
    protected $filter_controls          = [];
    protected $records_total            = 0;
    protected $records_total_round      = 0;
    protected $records_total_more       = false;
    protected $records_per_page         = 25;
    protected $records_per_page_default = 25;
    protected $records_seq              = false;
    protected $current_page             = 1;
    protected $is_ajax                  = false;
    protected $is_round_calc            = false;


    /**
     * @var SessionContainer
     */
    protected $session   = null;
    protected $locutions = [];

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

        $this->current_page = isset($_GET["_page_{$this->resource}"]) && $_GET["_page_{$this->resource}"] > 0
            ? (int)$_GET["_page_{$this->resource}"]
            : 1;

        $this->session = new SessionContainer($this->resource);

        if ( ! isset($this->session->table)) {
            $this->session->table = new \stdClass();
        }

        // SEARCH
        if ( ! empty($_POST['search']) && ! empty($_POST['search'][$resource])) {
            foreach ($_POST['search'][$resource] as $nmbr_field => $search_value) {
                $this->setSearch($nmbr_field, $search_value);
            }
        }
        if ( ! empty($_POST['search_clear_' . $this->resource])) {
            $this->clearSearch();
        }

        // FILTER
        if ( ! empty($_POST['filter']) && ! empty($_POST['filter'][$resource])) {
            $all_empty = true;
            foreach ($_POST['filter'][$resource] as $filter) {
                if ($filter !== '') {
                    $all_empty = false;
                    break;
                }
            }
            if ($all_empty) {
                $this->clearFilter();
            } else {
                foreach ($_POST['filter'][$resource] as $nmbr_field => $search_value) {
                    $this->setFilter($nmbr_field, $search_value);
                }
            }
        }
        if ( ! empty($_POST['filter_clear_' . $this->resource])) {
            $this->clearFilter();
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
     * Использование примерного подсчета количества
     * @param bool $is_round_calc
     * @return void
     */
    public function setRoundCalc(bool $is_round_calc) {

        $this->is_round_calc = $is_round_calc;
    }


    /**
     * Установка поисковых значений
     * @param int $nmbr_field
     * @param     $value_field
     * @return void
     */
    public function setSearch(int $nmbr_field, $value_field) {

        if ( ! isset($this->session->table->search)) {
            $this->session->table->search = [];
        }

        $this->session->table->search[$nmbr_field] = $value_field;
    }


    /**
     * Установка значений фильтра
     * @param int $nmbr_field
     * @param     $value_field
     * @return void
     */
    public function setFilter(int $nmbr_field, $value_field) {

        if ( ! isset($this->session->table->filter)) {
            $this->session->table->filter = [];
        }

        $this->session->table->filter[$nmbr_field] = $value_field;
    }


    /**
     * Очистка поиска
     * @return void
     */
    public function clearSearch() {

        $this->session->table->search = [];
    }


    /**
     * Очистка фильтров
     * @return void
     */
    public function clearFilter() {

        $this->session->table->filter = [];
    }


    /**
     * @param int|null $nmbr_control
     * @return mixed
     */
    public function getSearch(int $nmbr_control = null): mixed {

        $search = null;

        if (isset($this->session->table->search)) {
            $search = is_int($nmbr_control)
                ? $this->session->table->search[$nmbr_control] ?? null
                : $this->session->table->search;
        }

        return $search ?: null;
    }


    /**
     * @param int|null $nmbr_control
     * @return mixed
     */
    public function getFilters(int $nmbr_control = null): mixed {

        $filter = null;

        if (isset($this->session->table->filter)) {
            $filter = is_int($nmbr_control)
                ? $this->session->table->filter[$nmbr_control] ?? null
                : $this->session->table->filter;
        }

        return $filter ?: null;
    }


    /**
     * @return string|null
     */
    public function getOrder(): ?string {

        $order_field = null;

        if (isset($this->session->table->order) &&
            $this->session->table->order &&
            isset($this->columns[$this->session->table->order - 1])
        ) {
            $column = $this->columns[$this->session->table->order - 1];

            if ($column instanceof Column && $column->isSorting()) {
                $order_field = $column->getField();
            }
        }

        return $order_field;
    }


    /**
     * @return string|null
     */
    public function getOrderType(): ?string {

        $order = isset($this->session) && isset($this->session->table) && isset($this->session->table->order_type)
            ? $this->session->table->order_type
            : null;

        return $order ?: null;
    }


    /**
     * @return int
     */
    public function getPage(): int {

        return (int)$this->current_page;
    }


    /**
     * @return int
     */
    public function getRecordsPerPage(): int {

        return (int)$this->records_per_page;
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
    public function showHeader() {
        $this->show_header = true;
    }


    /**
     *
     */
    public function hideHeader() {
        $this->show_header = false;
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

        $render = new Render($this->toArray());
        $render->setLocutions($this->locutions);
        return $render->render();
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
        $templates = [];

        $count_pages = ceil($this->records_total / $this->records_per_page);

        if ( ! empty($this->buttons)) {
            foreach ($this->buttons as $button) {
                if ($button instanceof Table\Button) {
                    $toolbar['buttons'][] = $button->toArray();

                } elseif (is_string($button)) {
                    $toolbar['buttons'][] = $button;
                }
            }
        }

        if ($this->add_url) {
            $toolbar['addButton'] = $this->add_url;
        }

        $rows = $this->fetchRows();

        if ( ! empty($rows)) {
            foreach ($rows as $row) {
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


        $per_page_list = [ 25, 50, 100, 1000 ];

        if ($this->records_per_page_default > 0 &&
            ! in_array($this->records_per_page_default, $per_page_list)
        ) {
            $per_page_list[] = $this->records_per_page_default;
        }

        ksort($per_page_list);

        $data = [
            'resource' => $this->resource,
            'show'     => [
                'header'       => $this->show_header,
                'toolbar'      => $this->show_service,
                'footer'       => $this->show_footer,
                'delete'       => $this->show_delete,
                'lineNumbers'  => $this->show_number_rows,
                'selectRows'   => $this->show_select_rows,
                'columnManage' => $this->show_column_manage,
                'templates'    => $this->show_templates,
            ],

            'currentPage'        => $this->current_page,
            'countPages'         => $count_pages,
            'recordsPerPage'     => $this->records_per_page,
            'recordsTotal'       => $this->records_total,
            'recordsTotalMore'   => $this->records_total_more,
            'recordsPerPageList' => $per_page_list,
            'records'            => $records,
        ];


        if ($this->edit_url) {
            $data['recordsEditUrl'] = $this->edit_url;
        }
        if ($this->table_name) {
            $data['tableName'] = $this->table_name;
        }
        if ( ! empty($this->is_ajax)) {
            $data['isAjax'] = $this->is_ajax;
        }
        if ( ! empty($this->is_round_calc)) {
            $data['isRoundCalc']       = $this->is_round_calc;
            $data['recordsTotalRound'] = $this->records_total_round;
        }
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
        if ( ! empty($columns)) {
            $data['columns'] = $columns;
        }

        return $data;
    }


    /**
     * Добавление кнопки
     * @param string $content
     * @return Button
     */
    public function addButton(string $content): Button {
        return $this->buttons[] = new Button($content);
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
     * @throws Exception
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
     * @throws Exception
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
     * Получение данных.
     * @return array
     */
    abstract public function fetchRows(): array;


    /**
     * @param string $locution
     * @param string $text
     */
    public function setLocution(string $locution, string $text) {

        if (isset($this->locutions[$locution])) {
            $this->locutions[$locution] = $text;
        }
    }
}