<?php
namespace Core2\Classes\Table;

use Core2\Classes\Table;

require_once __DIR__ . '/../Table.php';
require_once 'Data/Filter.php';



/**
 *
 */
class Data extends Table {

    /**
     * @return array
     */
    public function fetchData(): array {

        if ( ! $this->is_fetched && ! empty($this->data) && is_array($this->data)) {
            $this->is_fetched = true;

            $filter = new Data\Filter();

            $this->records_total = count($this->data);

            if ( ! empty($this->search_controls) && ! empty($this->session->table->search)) {
                $this->data = $filter->searchData($this->data, $this->search_controls, $this->session->table->search);
            }
            if ( ! empty($this->session->table->order)) {
                $this->data = $filter->orderData($this->data, $this->session->table->order, $this->session->table->order_type);
            }


            $this->records_total = count($this->data);

            if ($this->records_total > (($this->current_page - 1) * $this->records_per_page) - $this->records_per_page) {
                $data_result = $filter->pageData($this->data, $this->records_per_page, $this->current_page);

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
}