<?php
namespace Core2\Classes\Table;
use Core2\Classes\Table;


require_once __DIR__ . '/../Table.php';


/**
 *
 */
class Data extends Table {

    protected $is_fetched = false;


    /**
     * Получение данных.
     * @return array
     * @deprecated fetchRows
     */
    public function fetchData(): array {

        $this->preFetchRows();
        return $this->fetchRows();
    }


    /**
     * Получение данных.
     * @return array
     */
    public function fetchRows(): array {

        $this->preFetchRows();

        if ( ! $this->is_fetched && ! empty($this->data) && is_array($this->data)) {
            $this->is_fetched = true;

            $this->records_total = count($this->data);

            if ( ! empty($this->filter_controls) && ! empty($this->session->table->filter)) {
                $this->data = $this->filterData($this->data, $this->filter_controls, $this->session->table->filter);
            }

            if ( ! empty($this->search_controls) && ! empty($this->session->table->search)) {
                $this->data = $this->searchData($this->data, $this->search_controls, $this->session->table->search);
            }

            if (isset($this->session->table->order) &&
                $this->session->table->order &&
                isset($this->columns[$this->session->table->order - 1])
            ) {
                $column = $this->columns[$this->session->table->order - 1];

                if ($column instanceof Column && $column->isSorting()) {
                    $order_field = $column->getField();

                    $first_row = current($this->data);

                    if (isset($first_row[$order_field])) {
                        $this->data = $this->orderData($this->data, $order_field, $this->session->table->order_type);
                    }
                }
            }


            $this->records_total = count($this->data);

            if ($this->records_total > (($this->current_page - 1) * $this->records_per_page) - $this->records_per_page) {
                $data_result = $this->pageData($this->data, $this->records_per_page, $this->current_page);

                if ( ! empty($data_result)) {
                    foreach ($data_result as $key => $row) {
                        $this->data_rows[] = new Row($row);
                    }
                }

            } else {
                $this->data_rows = [];
            }
        }

        return $this->data_rows;
    }


    /**
     * Фильтрация
     * @param array $data
     * @param array $filter_rules
     * @param array $filter_data
     * @return array
     */
    private function filterData(array $data, array $filter_rules, array $filter_data): array {

        foreach ($data as $key => $row) {

            foreach ($filter_data as $key2 => $filter_value) {
                $filter_column = $filter_rules[$key2];

                if ($filter_column instanceof Filter) {
                    if ($filter_value == '') {
                        continue;
                    }

                    $filter_field = $filter_column->getField();

                    if ( ! array_key_exists($filter_field, $row)) {
                        continue;
                    }

                    switch ($filter_column->getType()) {
                        case 'date':
                        case 'datetime':
                            if (is_array($filter_value)) {
                                if ($filter_value[0] && ! $filter_value[1]) {
                                    if (strtotime($row[$filter_field]) < strtotime($filter_value[0])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ( ! $filter_value[0] && $filter_value[1]) {
                                    if (strtotime($row[$filter_field]) > strtotime($filter_value[1])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ($filter_value[0] && $filter_value[1]) {
                                    if (strtotime($row[$filter_field]) < strtotime($filter_value[0]) ||
                                        strtotime($row[$filter_field]) > strtotime($filter_value[1])
                                    ) {
                                        unset($data[$key]);
                                        continue 2;
                                    }
                                }
                            }
                            break;

                        case 'number':
                            if (is_array($filter_value)) {
                                if ($filter_value[0] && ! $filter_value[1]) {
                                    if ($row[$filter_field] < $filter_value[0]) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ( ! $filter_value[0] && $filter_value[1]) {
                                    if ($row[$filter_field] > $filter_value[1]) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ($filter_value[0] && $filter_value[1]) {
                                    if ($row[$filter_field] < $filter_value[0] ||
                                        $row[$filter_field] > $filter_value[1]
                                    ) {
                                        unset($data[$key]);
                                        continue 2;
                                    }
                                }
                            }
                            break;

                        case 'radio':
                        case 'select':
                        case 'text_strict':
                            if ($row[$filter_field] != $filter_value) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;

                        case 'checkbox':
                            if ( ! in_array('', $filter_value) && ! in_array($row[$filter_field], $filter_value)) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;

                        case 'text':
                            if (mb_stripos($row[$filter_field], $filter_value, null, 'utf8') === false) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;
                    }
                }
            }
        }

        return $data;
    }


    /**
     * Поиск
     * @param array $data
     * @param array $search_rules
     * @param array $search_data
     * @return array
     */
    private function searchData(array $data, array $search_rules, array $search_data): array {

        foreach ($data as $key => $row) {

            foreach ($search_data as $key2 => $search_value) {
                $search_column = $search_rules[$key2];

                if ($search_column instanceof Search) {
                    if ($search_value == '') {
                        continue;
                    }

                    $search_field = $search_column->getField();

                    if ( ! array_key_exists($search_field, $row)) {
                        continue;
                    }

                    switch ($search_column->getType()) {
                        case 'date':
                            if (is_array($search_value)) {
                                $search_date = substr($row[$search_field], 0, 10);

                                if ($search_value[0] && ! $search_value[1]) {
                                    if (strtotime($search_date) < strtotime($search_value[0])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ( ! $search_value[0] && $search_value[1]) {
                                    if (strtotime($search_date) > strtotime($search_value[1])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ($search_value[0] && $search_value[1]) {
                                    if (strtotime($search_date) < strtotime($search_value[0]) ||
                                        strtotime($search_date) > strtotime($search_value[1])
                                    ) {
                                        unset($data[$key]);
                                        continue 2;
                                    }
                                }
                            }
                            break;

                        case 'datetime':
                            if (is_array($search_value)) {
                                if ($search_value[0] && ! $search_value[1]) {
                                    if (strtotime($row[$search_field]) < strtotime($search_value[0])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ( ! $search_value[0] && $search_value[1]) {
                                    if (strtotime($row[$search_field]) > strtotime($search_value[1])) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ($search_value[0] && $search_value[1]) {
                                    if (strtotime($row[$search_field]) < strtotime($search_value[0]) ||
                                        strtotime($row[$search_field]) > strtotime($search_value[1])
                                    ) {
                                        unset($data[$key]);
                                        continue 2;
                                    }
                                }
                            }
                            break;

                        case 'number':
                            if (is_array($search_value)) {
                                if ($search_value[0] && ! $search_value[1]) {
                                    if ($row[$search_field] < $search_value[0]) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ( ! $search_value[0] && $search_value[1]) {
                                    if ($row[$search_field] > $search_value[1]) {
                                        unset($data[$key]);
                                        continue 2;
                                    }

                                } elseif ($search_value[0] && $search_value[1]) {
                                    if ($row[$search_field] < $search_value[0] ||
                                        $row[$search_field] > $search_value[1]
                                    ) {
                                        unset($data[$key]);
                                        continue 2;
                                    }
                                }
                            }
                            break;

                        case 'radio':
                        case 'select':
                        case 'text_strict':
                            if ($row[$search_field] != $search_value) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;

                        case 'checkbox':
                        case 'multiselect':
                            if ( ! in_array('', $search_value) && ! in_array($row[$search_field], $search_value)) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;

                        case 'text':
                            if (mb_stripos($row[$search_field], $search_value, null, 'utf8') === false) {
                                unset($data[$key]);
                                continue 2;
                            }
                            break;
                    }
                }
            }
        }

        return $data;
    }


    /**
     * Сортировка
     * @param array  $data
     * @param string $order_field
     * @param string $order_type
     * @return array
     */
    private function orderData(array $data, string $order_field, string $order_type = 'ASC'): array {
        switch (strtoupper($order_type)) {
            case 'ASC':
                usort($data, function($a, $b) use ($order_field) {
                    if (is_numeric($a[$order_field]) && is_numeric($b[$order_field])) {
                        return $a[$order_field] <=> $b[$order_field];
                    }
                    return strnatcasecmp($a[$order_field], $b[$order_field]);});
                break;

            case 'DESC':
                usort($data, function($a, $b) use ($order_field) {
                    if (is_numeric($a[$order_field]) && is_numeric($b[$order_field])) {
                        return  $b[$order_field] <=> $a[$order_field];
                    }
                    return strnatcasecmp($b[$order_field], $a[$order_field]);
                });
                break;
        }

        return $data;
    }


    /**
     * Страница
     * @param array $data
     * @param int   $records_per_page
     * @param int   $current_page
     * @return array
     */
    private function pageData(array $data, int $records_per_page, int $current_page = 1): array {

        $new_data = [];
        $offset   = ($current_page - 1) * $records_per_page;
        $i        = 1;

        foreach ($data as $key => $row) {
            if ($current_page == 1) {
                if ($i <= $records_per_page) {
                    $new_data[$key] = $row;
                } else {
                    break;
                }

            } elseif ($current_page > 1) {
                if ($offset < $i && $i <= $offset + $records_per_page) {
                    $new_data[$key] = $row;

                } elseif ($offset < $i && $i > $offset + $records_per_page) {
                    break;
                }

            } else {
                break;
            }

            $i++;
        }

        return $new_data;
    }
}