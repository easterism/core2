<?php
namespace Core2\Classes\Table\Data;

use Core2\Classes\Table\Search;



/**
 * Class Filter
 * @package Core2\Classes\Table
 */
class Filter {


    /**
     * Фильтрация
     * @param array $data
     * @param array $filter_rules
     * @param array $filter_data
     * @return array
     */
    public function filterData(array $data, array $filter_rules, array $filter_data): array {

        foreach ($data as $key => $row) {

            foreach ($filter_data as $key2 => $filter_value) {
                $filter_column = $filter_rules[$key2];

                if ($filter_column instanceof \Core2\Classes\Table\Filter) {
                    if ($filter_value == '') {
                        continue;
                    }

                    $filter_field = $filter_column->getField();

                    if ( ! array_key_exists($filter_field, $row)) {
                        continue;
                    }

                    switch ($filter_column->getType()) {
                        case 'date':
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

                        case 'text_strict':
                            if ($row[$filter_field] !== $filter_value) {
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
    public function searchData(array $data, array $search_rules, array $search_data): array {

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

                        case 'text_strict':
                            if ($row[$search_field] !== $search_value) {
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
    public function orderData(array $data, string $order_field, string $order_type = 'ASC'): array {

        switch (strtoupper($order_type)) {
            case 'ASC':
                usort($data, function($a, $b) use ($order_field) {return strnatcasecmp($a[$order_field], $b[$order_field]);});
                break;

            case 'DESC':
                usort($data, function($a, $b) use ($order_field) {return strnatcasecmp($b[$order_field], $a[$order_field]);});
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
    public function pageData(array $data, int $records_per_page, int $current_page = 1): array {

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