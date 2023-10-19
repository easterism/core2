<?php
namespace Core2\Classes\Table;
use Core2\Acl;
use Laminas\Session\Container as SessionContainer;

require_once __DIR__ . '/../Templater3.php';


/**
 *
 */
class Render extends Acl {

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
        'Clear'                                      => 'Очистить',
        'Are you sure you want to delete this post?' => 'Вы действительно хотите удалить эту запись?',
        'You must select at least one record'        => 'Нужно выбрать хотя бы одну запись',
    ];

    private $table = [];


    /**
     * @param array $table
     */
    public function __construct(array $table) {

        parent::__construct();

        $this->theme_src      = DOC_PATH . 'core2/html/' . THEME;
        $this->theme_location = DOC_ROOT . 'core2/html/' . THEME;

        $this->session = new SessionContainer($table['resource']);

        if ( ! isset($this->session->table)) {
            $this->session->table = new \stdClass();
        }

        $this->table = $table;
    }


    /**
     * Рендеринг таблицы
     * @return string
     * @throws \Exception
     */
    public function render(): string {

        if ( ! $this->checkAcl($this->table['resource'], 'list_all') &&
            ! $this->checkAcl($this->table['resource'], 'list_owner')
        ) {
            return '';
        }

        $tpl = new \Templater3($this->theme_location . '/html/table.html');
        $tpl->assign('[THEME_SRC]', $this->theme_src);
        $tpl->assign('[RESOURCE]',  $this->table['resource']);
        $tpl->assign('[IS_AJAX]',   (int)($this->table['isAjax'] ?? 0));
        $tpl->assign('[LOCATION]',  ! empty($this->table['isAjax']) ? $_SERVER['QUERY_STRING'] . "&__{$this->table['resource']}=ajax" : $_SERVER['QUERY_STRING']);


        if ( ! empty($this->table['show'])) {
            if ( ! empty($this->table['show']['toolbar'])) {
                if (count($this->table['records']) == 0 && $this->table['currentPage'] == 1) {
                    $this->table['recordsTotal']      = 0;
                    $this->table['recordsTotalRound'] = 0;
                    $total_records = 0;

                } else {
                    if (isset($this->table['recordsTotalRound']) &&
                        (count($this->table['records']) == 0 || $this->table['recordsPerPage'] == count($this->table['records'])) &&
                        $this->table['recordsTotalRound'] >= $this->table['recordsTotal']
                    ) {
                        $total_records = "~{$this->table['recordsTotalRound']}";
                    } else {
                        $total_records = $this->table['recordsTotal'] ?? 0;
                    }
                }

                $tpl->service->assign('[TOTAL_RECORDS]', $total_records);

                if ( ! empty($this->table['toolbar'])) {

                    if ( ! empty($this->table['toolbar']['buttons'])) {
                        $buttons = [];

                        foreach ($this->table['toolbar']['buttons'] as $button) {
                            if (is_array($button)) {
                                if ( ! is_string($button['content'])) {
                                    continue;
                                }

                                $attributes = [];
                                if ( ! empty($button['attr'])) {
                                    foreach ($button['attr'] as $attr => $value) {
                                        if (is_string($attr) && is_string($value)) {
                                            $attributes[] = "$attr=\"{$value}\"";
                                        }
                                    }
                                }

                                $implode_attributes = implode(' ', $attributes);
                                $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';

                                $buttons[] = "<button{$implode_attributes}>{$button['content']}</button>";

                            } elseif (is_string($button)) {
                                $buttons[] = $button;
                            }
                        }

                        $tpl->service->assign('[BUTTONS]', implode(' ', $buttons));

                    } else {
                        $tpl->service->assign('[BUTTONS]', '');
                    }

                    if ( ! empty($this->table['toolbar']['addButton']) &&
                        ($this->checkAcl($this->table['resource'], 'edit_all') ||
                         $this->checkAcl($this->table['resource'], 'edit_owner')) &&
                        ($this->checkAcl($this->table['resource'], 'read_all') ||
                         $this->checkAcl($this->table['resource'], 'read_owner'))
                    ) {
                        $url = strpos($this->table['toolbar']['addButton'], 'javascript:') === 0
                            ? $this->table['toolbar']['addButton']
                            : str_replace('?', '#', $this->table['toolbar']['addButton']);

                        $tpl->service->add_button->assign('[URL]',      $url);
                        $tpl->service->add_button->assign('[ADD_TEXT]', $this->getLocution('Add'));
                    }

                } else {
                    $tpl->service->assign('[BUTTONS]', '');
                }

                if ( ! empty($this->table['show']['delete']) &&
                    ($this->checkAcl($this->table['resource'], 'delete_all') ||
                     $this->checkAcl($this->table['resource'], 'delete_owner'))
                ) {
                    $delete_text   = $this->getLocution('Delete');
                    $delete_msg    = $this->getLocution('Are you sure you want to delete this post?');
                    $no_select_msg = $this->getLocution('You must select at least one record');

                    $tpl->service->del_button->assign('[DELETE_TEXT]',      $delete_text);
                    $tpl->service->del_button->assign('[DELETE_MSG]',       $delete_msg);
                    $tpl->service->del_button->assign('[DELETE_NO_SELECT]', $no_select_msg);
                }
            }

            if ($this->table['show']['header']) {
                if ($this->table['show']['selectRows'] == true) {
                    $tpl->header->touchBlock('checkboxes');
                }
            }

            if ($this->table['show']['columnManage']) {
                $tpl->controls->touchBlock('column_switcher_control');

                if ( ! empty($this->table['show']['templates'])) {
                    $tpl->column_switcher_container->touchBlock('column_btn_template');
                } else {
                    $tpl->column_switcher_container->touchBlock('column_btn');
                }

                if ( ! empty($this->table['columns'])) {
                    foreach ($this->table['columns'] as $key => $column) {
                        if (is_array($column)) {
                            if (empty($column['field']) || ! is_string($column['field'])) {
                                continue;
                            }

                            if (isset($this->session->table->columns)) {
                                if (empty($this->session->table->columns[$column['field']])) {
                                    $this->table['columns'][$key]['show'] = $column['show'] = false;
                                } else {
                                    $this->table['columns'][$key]['show'] = $column['show'] = true;
                                }
                            }


                            $tpl->column_switcher_container->column_switcher_field->assign('[COLUMN]',  $column['field']);
                            $tpl->column_switcher_container->column_switcher_field->assign('[TITLE]',   $column['title'] ?? '');
                            $tpl->column_switcher_container->column_switcher_field->assign('[CHECKED]', $column['show'] ? 'checked="checked"' : '');
                            $tpl->column_switcher_container->column_switcher_field->reassign();
                        }
                    }
                }
            }


            if ($this->table['show']['footer']) {
                $current_page = $this->table['currentPage'] ?? 1;
                $count_pages  = ! empty($this->table['recordsTotal']) && ! empty($this->table['recordsPerPage'])
                    ? ceil($this->table['recordsTotal'] / $this->table['recordsPerPage'])
                    : 0;

                if ($count_pages > 0) {
                    if (empty($this->table['recordsTotalMore'])) {
                        $tpl_count_pages = $count_pages;

                    } elseif ( ! empty($this->table['recordsTotalRound']) && ! empty($this->table['recordsPerPage'])) {
                        $count_pages     = ceil($this->table['recordsTotalRound'] / $this->table['recordsPerPage']);
                        $tpl_count_pages = "~{$count_pages}";

                    } else {
                        $tpl_count_pages = $count_pages;
                    }

                } else {
                    $tpl_count_pages = 1;
                }

                if ($count_pages > 1 || ! empty($this->table['recordsTotalMore'])) {
                    $tpl->footer->pages->touchBlock('gotopage');
                }

                $tpl->footer->pages->assign('[CURRENT_PAGE]', $current_page);
                $tpl->footer->pages->assign('[COUNT_PAGES]',  $tpl_count_pages);

                if ($current_page > 1) {
                    $tpl->footer->pages->prev->assign('[PREV_PAGE]', $current_page - 1);
                }

                if ($current_page < $count_pages ||
                    (
                        (empty($this->table['recordsPerPage']) && count($this->table['records']) > 0) ||
                        (count($this->table['records']) >= $this->table['recordsPerPage'])
                    )
                ) {
                    $tpl->footer->pages->next->assign('[NEXT_PAGE]', $current_page + 1);
                }


                $recordsPerPage = $this->table['recordsPerPage'] ?? 25;
                $per_page_list  = [];


                if ( ! empty($this->table['recordsPerPageList'])) {
                    foreach ($this->table['recordsPerPageList'] as $per_page_count) {
                        if (is_numeric($per_page_count)) {
                            $per_page_list[$per_page_count] = $per_page_count == 0
                                ? $this->getLocution('All')
                                : $per_page_count;
                        }
                    }
                }

                $tpl->footer->pages->per_page->fillDropDown(
                    'records-per-page-[RESOURCE]',
                    $per_page_list,
                    $recordsPerPage == 1000000000 ? 0 : $recordsPerPage
                );
            }
        }

        if ( ! empty($this->table['search'])) {
            $search_value = ! empty($this->session->table) && ! empty($this->session->table->search)
                ? $this->session->table->search
                : [];

            if ( ! empty($search_value) && count($search_value)) {
                $tpl->controls->search_control->touchBlock('search_clear');
            }

            $tpl->controls->touchBlock('search_control');


            if ( ! empty($this->table['show']['templates'])) {
                $tpl->search_container->touchBlock('search_btn_template');
            } else {
                $tpl->search_container->touchBlock('search_btn');
            }

            foreach ($this->table['search'] as $key => $search) {
                if (is_array($search)) {
                    if (empty($search['type']) || ! is_string($search['type'])) {
                        continue;
                    }

                    $control_value  = $search_value[$key] ?? '';
                    $attributes_str = '';

                    if ( ! empty($search['attr']) && is_array($search['attr'])) {
                        $attributes = [];
                        foreach ($search['attr'] as $attr => $value) {
                            if (is_string($attr) && is_string($value)) {
                                $attributes[] = "$attr=\"{$value}\"";
                            }
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $attributes_str     = $implode_attributes ? ' ' . $implode_attributes : '';
                    }

                    switch ($search['type']) {
                        case 'text' :
                        case 'text_strict' :
                            $tpl->search_container->search_field->text->assign("[KEY]",     $key);
                            $tpl->search_container->search_field->text->assign("[VALUE]",   $control_value);
                            $tpl->search_container->search_field->text->assign("[IN_TEXT]", $attributes_str);
                            break;

                        case 'radio' :
                            $data = $search['data'] ?? [];
                            if ( ! empty($data)) {
                                $data = ['' => $this->getLocution('All')] + $data;
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

                        case 'checkbox' :
                            $data = $search['data'] ?? [];
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

                        case 'number' :
                            $tpl->search_container->search_field->number->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->number->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->number->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->number->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case 'date_one' :
                            $tpl->search_container->search_field->date_one->assign("[KEY]",     $key);
                            $tpl->search_container->search_field->date_one->assign("[VALUE]",   $control_value);
                            $tpl->search_container->search_field->date_one->assign("[IN_TEXT]", $attributes_str);
                            break;

                        case 'date' :
                            $tpl->search_container->search_field->date->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->date->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->date->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->date->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case 'datetime' :
                            $tpl->search_container->search_field->datetime->assign("[KEY]",         $key);
                            $tpl->search_container->search_field->datetime->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->search_container->search_field->datetime->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->search_container->search_field->datetime->assign("[IN_TEXT]",     $attributes_str);
                            break;

                        case 'select' :
                            $data = $search['data'] ?? [];
                            $options = ['' => ''] + $data;
                            $tpl->search_container->search_field->select->assign("[KEY]",      $key);
                            $tpl->search_container->search_field->select->assign("[IN_TEXT]",  $attributes_str);
                            $tpl->search_container->search_field->select->fillDropDown("search-[RESOURCE]-[KEY]", $options, $control_value);
                            break;

                        case 'multiselect' :
                            $data = $search['data'] ?? [];
                            $tpl->search_container->search_field->multiselect->assign("[KEY]",      $key);
                            $tpl->search_container->search_field->multiselect->assign("[IN_TEXT]",  $attributes_str);
                            $tpl->search_container->search_field->multiselect->fillDropDown("search-[RESOURCE]-[KEY]", $data, $control_value);
                            break;
                    }


                    $tpl->search_container->search_field->assign("[#]",        $key);
                    $tpl->search_container->search_field->assign("[OUT_TEXT]", $search['out'] ?? '');
                    $tpl->search_container->search_field->assign('[CAPTION]',  $search['caption'] ?? '');
                    $tpl->search_container->search_field->assign('[TYPE]',     $search['type'] ?? '');
                    $tpl->search_container->search_field->reassign();
                }
            }
        }

        if ( ! empty($this->table['filter'])) {
            $filter_value = ! empty($this->session->table) && ! empty($this->session->table->filter)
                ? $this->session->table->filter
                : [];

            if ( ! empty($filter_value) && count($filter_value)) {
                $tpl->filter_controls->filter_clear->assign('[CLEAR_TEXT]', $this->getLocution('Clear'));
            }


            foreach ($this->table['filter'] as $key => $filter) {
                if (is_array($filter)) {
                    if (empty($filter['type']) || ! is_string($filter['type'])) {
                        continue;
                    }

                    $control_value  = $filter_value[$key] ?? '';
                    $attributes_str = '';

                    if ( ! empty($filter['attr'])) {
                        $attributes = [];
                        foreach ($filter['attr'] as $attr => $value) {
                            if (is_string($attr) && is_string($value)) {
                                $attributes[] = "$attr=\"{$value}\"";
                            }
                        }
                        $implode_attributes = implode(' ', $attributes);
                        $attributes_str     = $implode_attributes ? ' ' . $implode_attributes : '';
                    }

                    switch ($filter['type']) {
                        case 'text' :
                        case 'text_strict' :
                            $tpl->filter_controls->filter_control->text->assign("[KEY]",   $key);
                            $tpl->filter_controls->filter_control->text->assign("[VALUE]", $control_value);
                            $tpl->filter_controls->filter_control->text->assign("[TITLE]", $filter['title'] ?? '');
                            $tpl->filter_controls->filter_control->text->assign("[ATTR]",  $attributes_str);
                            break;

                        case 'radio' :
                            $data = $filter['data'] ?? '';
                            if ( ! empty($data)) {
                                if ( ! empty($filter['title'])) {
                                    $tpl->filter_controls->filter_control->radio->title->assign('[TITLE]', $filter['title']);
                                }

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

                        case 'checkbox' :
                            $data = $filter['data'] ?? '';
                            if ( ! empty($data)) {
                                if ( ! empty($filter['title'])) {
                                    $tpl->filter_controls->filter_control->checkbox->title->assign('[TITLE]', $filter['title']);
                                }

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

                        case 'number' :
                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->number->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->number->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->number->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->number->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->number->assign("[ATTR]",        $attributes_str);
                            break;

                        case 'date_one' :
                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->date_one->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->date_one->assign("[KEY]",   $key);
                            $tpl->filter_controls->filter_control->date_one->assign("[VALUE]", $control_value);
                            $tpl->filter_controls->filter_control->date_one->assign("[ATTR]",  $attributes_str);
                            break;

                        case 'date_month' :
                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->date_month->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->date_month->assign("[KEY]",   $key);
                            $tpl->filter_controls->filter_control->date_month->assign("[VALUE]", $control_value);
                            $tpl->filter_controls->filter_control->date_month->assign("[ATTR]",  $attributes_str);
                            break;

                        case 'date' :
                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->date->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->date->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->date->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->date->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->date->assign("[ATTR]",        $attributes_str);
                            break;

                        case 'datetime' :
                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->datetime->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->datetime->assign("[KEY]",         $key);
                            $tpl->filter_controls->filter_control->datetime->assign("[VALUE_START]", $control_value[0] ?? '');
                            $tpl->filter_controls->filter_control->datetime->assign("[VALUE_END]",   $control_value[1] ?? '');
                            $tpl->filter_controls->filter_control->datetime->assign("[ATTR]",        $attributes_str);
                            break;

                        case 'select' :
                            $data    = $filter['data'] ?? [];
                            $options = ['' => ''] + $data;

                            if ( ! empty($filter['title'])) {
                                $tpl->filter_controls->filter_control->select->title->assign('[TITLE]', $filter['title']);
                            }

                            $tpl->filter_controls->filter_control->select->assign("[KEY]",  $key);
                            $tpl->filter_controls->filter_control->select->assign("[ATTR]", $attributes_str);
                            $tpl->filter_controls->filter_control->select->fillDropDown("filter-[RESOURCE]-[KEY]", $options, $control_value);
                            break;
                    }

                    $tpl->filter_controls->filter_control->assign("[#]",    $key);
                    $tpl->filter_controls->filter_control->assign('[TYPE]', $filter['type']);
                    $tpl->filter_controls->filter_control->reassign();
                }
            }
        }

        if ( ! empty($this->table['columns']) &&
             ! empty($this->table['show']) &&
            $this->table['show']['header']
        ) {
            foreach ($this->table['columns'] as $key => $column) {
                if (is_array($column) && ! empty($column['show'])) {

                    if ($column['type'] == 'money') {
                        $column['attr']['style'] = ! empty($column['attr']['style'])
                            ? "text-align:right;{$column['attr']['style']}"
                            : "text-align:right;";
                    }


                    $column_attributes = [];
                    if ( ! empty($column['attr'])) {
                        foreach ($column['attr'] as $attr => $value) {
                            if (is_string($attr) && is_string($value)) {
                                $column_attributes[] = "$attr=\"{$value}\"";
                            }
                        }
                    }
                    $column_attributes = implode(' ', $column_attributes);

                    if ( ! empty($column['sorting'])) {
                        if (isset($this->session->table->order) && $this->session->table->order == $key + 1) {
                            if ($this->session->table->order_type == "asc") {
                                $tpl->header->cell->sort->touchBlock('order_asc');
                            } elseif ($this->session->table->order_type == "desc") {
                                $tpl->header->cell->sort->touchBlock('order_desc');
                            }
                        }

                        if ( ! empty($column_attributes)) {
                            $tpl->header->cell->sort->assign('<th', "<th {$column_attributes}\"");
                        }

                        $tpl->header->cell->sort->assign('[COLUMN_NUMBER]', ($key + 1));
                        $tpl->header->cell->sort->assign('[CAPTION]',       $column['title'] ?? '');

                    } else {
                        if ( ! empty($column_attributes)) {
                            $tpl->header->cell->no_sort->assign('<th', "<th {$column_attributes}");
                        }
                        $tpl->header->cell->no_sort->assign('[CAPTION]', $column['title'] ?? '');
                    }


                    if ( ! empty($this->table['show']) && ! empty($this->table['show']['lineNumbers'])) {
                        $tpl->header->touchBlock('header_number');
                    }

                    $tpl->header->cell->reassign();
                }
            }
        }

        // Шаблоны поиска
        if ( ! empty($this->table['show']['templates']) && ! empty($this->table['templates'])) {

            $tpl->controls->touchBlock('template_control');

            $templates = array_reverse($this->table['templates']);

            foreach ($templates as $template_id => $user_template) {
                $tpl->templates_container->template_item->assign('[ID]',    $template_id);
                $tpl->templates_container->template_item->assign('[TITLE]', $user_template['title']);
                $tpl->templates_container->template_item->reassign();
            }
        }

        if ( ! empty($this->table['records'])) {
            $row_index  = 1;
            $row_number = ! empty($this->table['currentPage']) &&
                          ! empty($this->table['recordsPerPage']) &&
                          $this->table['currentPage'] > 1
                ? (($this->table['currentPage'] - 1) * $this->table['recordsPerPage']) + 1
                : 1;
            $group_field = $this->table['groupField'] ?? null;
            $group_value = null;

            $show_column = 0;
            foreach($this->table['columns'] as $column) {
                if ($column['show']) {
                    $show_column++;
                }
            }

            foreach ($this->table['records'] as $row) {
                if (is_array($row) && ! empty($row['cells'])) {
                    $row_id = ! empty($row['cells']['id']) && ! empty($row['cells']['id']['value'])
                        ? $row['cells']['id']['value']
                        : 0;


                    if ($group_field &&
                        ! empty($row['cells'][$group_field]) &&
                        array_key_exists('value', $row['cells'][$group_field]) &&
                        $group_value !== $row['cells'][$group_field]['value']
                    ) {
                        $group_value = $row['cells'][$group_field]['value'];
                        $count_cols  = 1;

                        $tpl->rows->assign('<tr', '<tr class="coreui-table-row-group"');

                        if ( ! empty($this->table['show']) && ! empty($this->table['show']['selectRows'])) {
                            $tpl->rows->group->touchBlock('group_checkbox');
                            $count_cols -= 1;
                        }

                        if ( ! empty($this->table['show']) && ! empty($this->table['show']['lineNumbers'])) {
                            $count_cols += 1;
                        }

                        $tpl->rows->group->assign('[COLS]',  $show_column + $count_cols);
                        $tpl->rows->group->assign('[ATTR]',  '');
                        $tpl->rows->group->assign('[VALUE]', $group_value);
                        $tpl->rows->reassign();
                    }

                    $tpl->rows->assign('<tr', '<tr');
                    $tpl->rows->row->assign('[ID]', $row_id);

                    if ( ! empty($this->table['show']) && ! empty($this->table['show']['lineNumbers'])) {
                        $tpl->rows->row->row_number->assign('[#]', $row_number);
                    }

                    $row['attr']['class'] = isset($row['attr']['class'])
                        ? $row['attr']['class'] .= ' row-table'
                        : 'row-table';

                    if ( ! empty($this->table['recordsEditUrl']) &&
                        ($this->checkAcl($this->table['resource'], 'edit_all') ||
                         $this->checkAcl($this->table['resource'], 'edit_owner') ||
                         $this->checkAcl($this->table['resource'], 'read_all') ||
                         $this->checkAcl($this->table['resource'], 'read_owner'))
                    ) {

                        $edit_url = $this->replaceTCOL($row, $this->table['recordsEditUrl']);
                        $edit_url = str_replace('TCOL_#', $row_number - 1, $edit_url);
                        $edit_url = str_replace('[#]', $row_number - 1, $edit_url);

                        $row['attr']['class'] = isset($row['attr']['class'])
                            ? $row['attr']['class'] .= ' edit-row'
                            : 'edit-row';

                        if (strpos($edit_url, 'javascript:') === 0) {
                            $row['attr']['onclick'] = isset($row['attr']['onclick'])
                                ? $row['attr']['onclick'] .= ' ' . substr($edit_url, 11)
                                : substr($edit_url, 11);

                        } else {
                            $row['attr']['onclick'] = isset($row['attr']['onclick'])
                                ? $row['attr']['onclick'] .= " load('{$edit_url}');"
                                : "load('{$edit_url}');";
                        }
                    }

                    foreach ($this->table['columns'] as $column) {
                        if (is_array($column) &&
                            ! empty($column['show']) &&
                            ! empty($column['type']) &&
                            ! empty($column['field'])
                        ) {
                            $cell  = $row['cells'][$column['field']] ?? [];
                            $value = $cell['value'] ?? '';

                            switch ($column['type']) {
                                case 'text':
                                    $tpl->rows->row->col->default->assign('[VALUE]', htmlspecialchars($value));
                                    break;

                                case 'number':
                                    $value = strrev($value);
                                    $value = (string)preg_replace('/(\d{3})(?=\d)(?!\d*\.)/', '$1;psbn&', $value);
                                    $value = strrev($value);
                                    $tpl->rows->row->col->default->assign('[VALUE]', $value);
                                    break;

                                case 'money':
                                    $value    = \Tool::commafy(sprintf("%0.2f", $value));
                                    $options  = $column['options'] ?? [];
                                    $template = $options['tpl'] ?? '<b>[VALUE]</b> <small class=\"text-muted\">[CURRENCY]</small>';
                                    $currency = $options['currency'] ?? $this->table['currency'];

                                    $value = str_replace('[VALUE]', $value, $template);
                                    $value = str_replace('[CURRENCY]', $currency, $value);

                                    $cell['attr']['style'] = ! empty($cell['attr']['style'])
                                        ? "text-align:right;{$cell['attr']['style']}"
                                        : "text-align:right;";


                                    $tpl->rows->row->col->default->assign('[VALUE]', $value);
                                    break;

                                case 'html':
                                    $tpl->rows->row->col->default->assign('[VALUE]', $value);
                                    break;

                                case 'date':
                                    $date = $value ? date($this->date_mask, strtotime($value)) : '';
                                    $tpl->rows->row->col->default->assign('[VALUE]', $date);
                                    break;

                                case 'datetime':
                                    $date = $value ? date($this->datetime_mask, strtotime($value)) : '';
                                    $tpl->rows->row->col->default->assign('[VALUE]', $date);
                                    break;

                                case 'status':
                                    if ($value == 'Y' || $value == 1) {
                                        $img = "<img src=\"{$this->theme_src}/list/img/lightbulb.png\" alt=\"_tr(вкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                    } else {
                                        $img = "<img src=\"{$this->theme_src}/list/img/lightbulb_off.png\" alt=\"_tr(выкл)\" title=\"_tr(вкл)/_tr(выкл)\" data-value=\"{$value}\"/>";
                                    }
                                    $tpl->rows->row->col->default->assign('[VALUE]', $img);
                                    break;

                                case 'switch':
                                    $cell['attr']['onclick'] = "event.cancelBubble = true;";

                                    $options    = $column['options'] ?? [];
                                    $table_name = $this->table['tableName'] ?? '';
                                    $color      = ! empty($options['color']) ? "color-{$options['color']}" : 'color-primary';
                                    $value_y    = $options['value_Y'] ?? 'Y';
                                    $value_n    = $options['value_N'] ?? 'N';
                                    $table      = $options['table'] ?? $table_name;

                                    if ($this->checkAcl($this->table['resource'], 'edit_all')) {
                                        $tpl->rows->row->col->switch->assign('[FIELD]',       $column['field']);
                                        $tpl->rows->row->col->switch->assign('[TABLE_FIELD]', $table ? "{$table}.{$column['field']}" : $column['field']);
                                        $tpl->rows->row->col->switch->assign('[NMBR]',        $row_number);
                                        $tpl->rows->row->col->switch->assign('[CHECKED_Y]',   $value == $value_y ? 'checked="checked"' : '');
                                        $tpl->rows->row->col->switch->assign('[CHECKED_N]',   $value == $value_n ? 'checked="checked"' : '');
                                        $tpl->rows->row->col->switch->assign('[COLOR]',       $color);
                                        $tpl->rows->row->col->switch->assign('[VALUE_Y]',     $value_y);
                                        $tpl->rows->row->col->switch->assign('[VALUE_N]',     $value_n);
                                    } else {
                                        $tpl->rows->row->col->default->assign('[VALUE]', $value == $value_y ? $this->_("Вкл.") : $this->_("Выкл."));
                                    }
                                    break;
                            }

                            // Атрибуты ячейки
                            $attributes = [];
                            if ( ! empty($cell['attr'])) {
                                foreach ($cell['attr'] as $attr => $value) {
                                    if (is_string($attr) && is_string($value)) {
                                        $attributes[] = "$attr=\"{$value}\"";
                                    }
                                }
                            }
                            $implode_attributes = implode(' ', $attributes);
                            $implode_attributes = $implode_attributes ? ' ' . $implode_attributes : '';

                            $tpl->rows->row->col->assign('[ATTR]', $implode_attributes);

                            if (end($this->table['columns']) != $column) $tpl->rows->row->col->reassign();
                        }
                    }


                    if ( ! empty($row['attr'])) {
                        $attribs_string = '';
                        foreach ($row['attr'] as $name => $attr) {
                            if (is_string($name) && is_string($attr)) {
                                $attribs_string .= " {$name}=\"{$attr}\"";
                            }
                        }
                        $tpl->rows->assign('<tr', '<tr ' . $attribs_string);
                    }

                    if ( ! empty($this->table['show']) && ! empty($this->table['show']['selectRows'])) {
                        $tpl->rows->row->checkboxes->assign('[ID]', $row_id);
                        $tpl->rows->row->checkboxes->assign('[#]',  $row_index);
                        $row_index++;
                    }

                    $row_number++;

                    $tpl->rows->reassign();
                }
            }

        } else {
            $tpl->touchBlock('no_rows');
        }

        return $this->minify($tpl->render());
    }


    /**
     * @param array $locutions
     * @return void
     */
    public function setLocutions(array $locutions) {

        if ( ! empty($locutions)) {
            foreach ($locutions as $locution => $text) {
                if (isset($this->locutions[$locution])) {
                    $this->locutions[$locution] = $text;
                }
            }
        }
    }


    /**
     * Сжатие Html
     * @param string $html
     * @return string
     */
    private function minify(string $html): string {

        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        ];

        $replace = [
            '>',
            '<',
            '\\1',
            '',
        ];

        return preg_replace($search, $replace, $html);
    }


    /**
     * @param string $locution
     * @return string
     */
    private function getLocution(string $locution): string {

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
    private function replaceTCOL($row, string $str): string {

        if ( ! empty($row['cells']) && strpos($str, 'TCOL_') !== false) {
            foreach ($row['cells'] as $field => $cell) {
                $value = htmlspecialchars($cell['value'] ?? '');
                $value = addslashes($value);
                $value = str_replace(["\n", "\t"], ' ', $value);
                $value = trim($value);
                $str   = str_replace('[TCOL_' . strtoupper($field) . ']', $value, $str);
            }

            foreach ($row['cells'] as $field => $cell) {
                $value = htmlspecialchars($cell['value'] ?? '');
                $value = addslashes($value);
                $value = str_replace(["\n", "\t"], ' ', $value);
                $value = trim($value);
                $str   = str_replace('TCOL_' . strtoupper($field), $value, $str);
            }
        }

        return $str;
    }
}
