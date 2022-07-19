<?php
require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';
require_once DOC_ROOT . 'core2/inc/classes/class.list.php';

require_once 'Billing.php';


/**
 * Class Billing_Index
 */
class Billing_Index extends Billing {

    /**
     * @return string
     */
    public function getEditOperation() {

        $tpl           = new Templater3(__DIR__ . '/../html/operations_form.html');
        $operations    = $this->moduleConfig->operations->toArray();
        $currency      = $this->moduleConfig->currency;
        $first_element = true;


        if ( ! empty($operations)) {
            foreach ($operations as $name => $operation) {
                $price = Tool::commafy($operation['price']);

                $tpl->operation->assign('[NAME]',    $name);
                $tpl->operation->assign('[TITLE]',   $operation['title']);
                $tpl->operation->assign('[CHECKED]', $first_element ? 'checked="checked"' : '');

                $tpl->operation->price->assign('[PRICE]',    $price);
                $tpl->operation->price->assign('[CURRENCY]', $currency);
                $tpl->operation->reassign();

                $first_element = false;
            }
        }

        if ($this->moduleConfig->is_add_balance) {
            $tpl->operation->assign('[NAME]',   'add_balance');
            $tpl->operation->assign('[TITLE]',  'Пополнение баланса');
            $tpl->operation->assign('[CHECKED]', $first_element ? 'checked="checked"' : '');
            $tpl->operation->reassign();
        }

        return $tpl->render();
    }


    /**
     * @param string $operation_name
     * @return string
     */
    public function getEditBilling($operation_name) {

        $src = $this->getModuleSrc('billing');
        $this->printJs($src . '/html/js/billing.js');

        if ($operation_name == 'add_balance') {
            return $this->getEditBillingComing();
        } else {
            return $this->getEditBillingExpense($operation_name);
        }
    }


    /**
     * История платежей
     * @return string
     * @throws Exception
     */
    public function getListHistory() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingGetListHistory');
        } else {

            $list = new listTable('billingxxxhistory');
            $list->noCheckboxes = 'yes';

            $list->addSearch("Номер",             'bo.operation_num',      'TEXT');
            $list->addSearch("Дата операции",     'bo.date_created',       'DATE');
            $list->addSearch("Описание операции", 'bo.note',               'TEXT');
            $list->addSearch("Статус транзакции", 'bo.status_transaction', 'LIST'); $list->sqlSearch[] = array('pending' => 'В ожидании', 'canceled' => 'Отменено', 'completed' => 'Завершено');
            $list->addSearch("Операция",          'bo.type_operation',     'LIST'); $list->sqlSearch[] = array('coming' => 'Пополнение', 'expense' => 'Списание');
            $list->addSearch("Сумма",             'bo.price',              'NUMBER');

            $shipping_field = $this->moduleConfig->is_active_shipping ? 'bo.shipping_price,' : '';
            $tax_field      = $this->moduleConfig->is_active_tax      ? 'bo.tax,'            : '';
            $discount_field = $this->moduleConfig->is_active_discount ? 'bo.discount_price,' : '';

            $list->SQL = "
                SELECT bo.id,
                       bo.operation_num,
                       bo.date_created,
                       bo.note,
                       bo.status_transaction,
                       bo.type_operation,
                       {$shipping_field}
                       {$tax_field}
                       {$discount_field}
                       bo.price
                FROM mod_billing_operations AS bo
                WHERE 1=1 ADD_SEARCH
                ORDER BY bo.date_created DESC
            ";

            $list->addColumn("Номер",             "100", "TEXT");
            $list->addColumn("Дата операции",     "125", "TEXT");
            $list->addColumn("Описание операции", "",    "TEXT");
            $list->addColumn("Статус транзакции", "140", "TEXT");
            $list->addColumn("Операция",          "140", "TEXT");
            if ($this->moduleConfig->is_active_shipping) {
                $list->addColumn("Доставка", "90", "NUMBER", 'style="text-align:right;border-right:1px solid #ddd"');
            }
            if ($this->moduleConfig->is_active_tax) {
                $list->addColumn("Налог", "90", "NUMBER", 'style="text-align:right;border-right:1px solid #ddd"');
            }
            if ($this->moduleConfig->is_active_discount) {
                $list->addColumn("Скидка", "90", "NUMBER", 'style="text-align:right;border-right:1px solid #ddd"');
            }
            $list->addColumn("Сумма", "120", "NUMBER", 'style="text-align:right"');

            $currency = $this->moduleConfig->currency;

            $list->getData();
            foreach ($list->data as $k => $row) {

                // Дата операции
                if ($row[2]) {
                    $list->data[$k][2] = date('d.m.Y H:i', strtotime($row[2]));
                }

                // Операция
                if ($row[5]) {
                    $list->data[$k][5] = $row[5] == 'coming'
                        ? '<span class="text-success">Пополнение</span>'
                        : ($row[5] == 'expense' ? '<span class="text-danger">Списание</span>' : '');
                }

                // Статус транзакции
                switch ($row[4]) {
                    case 'pending':   $list->data[$k][4] = '<span class="text-primary">В ожидании</span>'; break;
                    case 'canceled':  $list->data[$k][4] = '<span class="text-danger">Отменено</span>'; break;
                    case 'completed': $list->data[$k][4] = '<span class="text-success">Завершено</span>'; break;
                }

                $col = 6;

                // Доставка
                if ($this->moduleConfig->is_active_shipping) {
                    $list->data[$k][$col] = $row[$col] != '' ? "{$row[$col]} {$currency}" : '';
                    $col++;
                }

                // Налог
                if ($this->moduleConfig->is_active_tax) {
                    $list->data[$k][$col] = $row[$col] != '' ? "{$row[$col]} {$currency}" : '';
                    $col++;
                }

                // Скидка
                if ($this->moduleConfig->is_active_discount) {
                    $list->data[$k][$col] = $row[$col] != '' ? "{$row[$col]} {$currency}" : '';
                    $col++;
                }

                // Сумма
                $list->data[$k][$col] = $row[$col] != '' ? "{$row[$col]} {$currency}" : '';
            }

            ob_start();
            $list->showTable();
            return ob_get_clean();
        }
    }


    /**
     * @param string $operation_name
     * @return string
     * @throws Exception
     */
    private function getEditBillingExpense($operation_name) {

        if ( ! isset($this->moduleConfig->operations->{$operation_name})) {
            return Alert::getDanger('<h4>Указанная операция не существует.</h4>Вернитесь к списку операций и повторите выбор');
        }


        $tpl = new Templater3(__DIR__ . '/../html/billing_expense.html');

        $this->module = 'billing';
        $currency     = $this->moduleConfig->currency;
        $operation    = $this->moduleConfig->operations->{$operation_name}->toArray();
        $systems      = $this->getSystems();
        $date_disable = $this->getDateDisable();
        $balance      = $this->getBalance();

        if (empty($operation['days'])) {
            $operation['days'] = 0;
        }

        $date_start = strtotime($date_disable) > time() ? strtotime($date_disable) : time();
        $date_from  = date('d.m.Y', $date_start);
        $date_to    = date('d.m.Y', strtotime(" +{$operation['days']} days", $date_start));
        $operation_title = str_replace('[DATE_FROM]', $date_from, $operation['coming_title']);
        $operation_title = str_replace('[DATE_TO]',   $date_to,   $operation_title);

        $tpl->assign('[OPERATION_TITLE]', $operation_title);
        $tpl->assign('[OPERATION_NAME]',  $operation_name);
        $tpl->assign('[CURRENCY]',        $currency);



        if ($balance >= $operation['price']) {
            $tpl->balance_only->assign('[TOTAL_COMMAFY]', Tool::commafy($operation['price']));

            $tpl->one_method->assign('[TITLE]', 'Мой баланс');
            $tpl->one_method->assign('[NAME]',  'balance');


        } elseif ( ! empty($systems)) {
            if (count($systems) >= 2) {
                $plugin_number = 0;
                foreach ($systems as $name => $system) {
                    $plugin_controller = new $system['class']();

                    $tpl->systems->assign('[NAME]',          $name);
                    $tpl->systems->assign('[FORM_CONTENT]',  $plugin_controller->getFormExpense($operation_name));
                    $tpl->systems->assign('[STYLE_DISPLAY]', $plugin_number++ == 0 ? 'display:block' : 'display:none');
                    $tpl->systems->reassign();

                    $plugin_title = $this->moduleConfig->system->{$name}->title;

                    $tpl->pay_methods->pay_method->assign('[NAME]',  $name);
                    $tpl->pay_methods->pay_method->assign('[TITLE]', $plugin_title);
                    $tpl->pay_methods->pay_method->reassign();
                }

            } else {
                $system       = current($systems);
                $plugin_name  = key($systems);
                $plugin_title = $this->moduleConfig->system->{$plugin_name}->title;

                $tpl->one_method->assign('[NAME]', $plugin_name);

                if ($balance > 0) {
                    $tpl->one_method->assign('[TITLE]', 'Мой баланс и ' . $plugin_title);
                } else {
                    $tpl->one_method->assign('[TITLE]', $plugin_title);
                }

                $plugin_controller = new $system['class']();

                $tpl->systems->assign('[NAME]',          $plugin_name);
                $tpl->systems->assign('[FORM_CONTENT]',  $plugin_controller->getFormExpense($operation_name));
                $tpl->systems->assign('[STYLE_DISPLAY]', 'display:block');
            }

        } else {
            throw new Exception("В модуле отсутствуют плагины платежных систем");
        }

        return $tpl->render();
    }



    /**
     * @return string
     * @throws Exception
     */
    private function getEditBillingComing() {

        $tpl = new Templater3(__DIR__ . '/../html/billing_coming.html');

        $systems = $this->getSystems();

        if ( ! empty($systems)) {
            $plugin_number = 0;
            foreach ($systems as $name => $system) {
                $plugin_controller = new $system['class']();

                $tpl->systems->assign('[NAME]',          $name);
                $tpl->systems->assign('[FORM_CONTENT]',  $plugin_controller->getFormComing());
                $tpl->systems->assign('[STYLE_DISPLAY]', $plugin_number++ == 0 ? 'display:block' : 'display:none');
                $tpl->systems->reassign();
            }

            if (count($systems) >= 2) {
                foreach ($systems as $name => $system) {
                    $system_title = $this->moduleConfig->system->{$name}->title;

                    $tpl->pay_methods->pay_method->assign('[NAME]',  $name);
                    $tpl->pay_methods->pay_method->assign('[TITLE]', $system_title);
                    $tpl->pay_methods->pay_method->reassign();
                    $tpl->pay_methods->pay_method->reassign();
                }

            } else {
                reset($systems);
                $system_name  = key($systems);
                $system_title = $this->moduleConfig->system->{$system_name}->title;

                $tpl->one_method->assign('[TITLE]', $system_title);
            }
        } else {
            throw new Exception("В модуле отсутствуют плагины платежных систем");
        }

        return $tpl->render();
    }
}