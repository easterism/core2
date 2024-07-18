<?php
namespace Core2\Traits;

require_once __DIR__ . '/../classes/Templater3.php';


/**
 *
 */
trait Import {


    /**
     * @param array $options
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function getFieldImport(array $options, array $data): string {

        $options['field']      ??= 'import';
        $options['fields']     ??= [];
        $options['max_cols']   ??= 20;
        $options['max_rows']   ??= 20;
        $options['max_width']  ??= 900;
        $options['max_height'] ??= 500;

        $styles = [];
        $styles[] = "max-width:" . (is_numeric($options['max_width']) ? "{$options['max_width']}px" : $options['max_width']);
        $styles[] = "max-height:" . (is_numeric($options['max_height']) ? "{$options['max_height']}px" : $options['max_height']);


        $count_col = 0;
        foreach ($data as $row) {
            $count_cols_real = count(array_filter($row));

            if ($count_cols_real > $count_col) {
                $count_col = min($count_cols_real, $options['max_cols']);
            }

            if ($count_col == $options['max_rows']) {
                break;
            }
        }


        $tpl = new \Templater3(__DIR__ . '/../../html/' . THEME . '/html/edit/import.html');

        for ($i = 0; $i < $count_col; $i++) {
            $tpl->column->fillDropDown("select_fields-[COL_NUMBER]", ['' => '--'] + $options['fields']);
            $tpl->column->assign("[COL_NUMBER]", $i);
            $tpl->column->reassign();
        }

        $num = 0;
        foreach ($data as $row) {
            if ($num >= $options['max_rows']) {
                break;
            }


            $col = 0;
            foreach ($row as $cell) {
                if ($col >= $count_col) {
                    break;
                }

                $tpl->row->cell->assign("[VALUE]", htmlspecialchars($cell));
                $tpl->row->cell->reassign();

                $col++;
            }


            $tpl->row->assign("[ROW_NUMBER]", $num);
            $tpl->row->reassign();

            $num++;
        }

        $tpl->assign("[STYLES]",     implode(';', $styles));
        $tpl->assign("[FIELD]",      $options['field']);
        $tpl->assign("[TOTAL_ROWS]", count($data));

        return $tpl->render();
    }
}