<?php

require_once 'Billing.php';


/**
 * Class Billing_Operations
 */
class Billing_Operations extends Billing {


    /**
     * Создание списания по названию операции
     * @param string $paid_operation
     * @return bool
     * @throws Exception
     */
    public function createExpense($paid_operation) {

        $this->module = 'billing';

        if ( ! isset($this->moduleConfig->operation->{$paid_operation})) {
            throw new Exception('Указанная операция не существует');
        }

        $date_disable = $this->getDateDisable();
        $balance      = $this->getBalance();
        $operation    = $this->moduleConfig->operation->{$paid_operation}->toArray();

        if ($balance < $operation['price']) {
            throw new Exception('На балансе недостаточное количество средств для оплаты указанной операции');
        }


        $date_start = strtotime($date_disable) > time() ? strtotime($date_disable) : time();
        $date_from  = date('d.m.Y', $date_start);
        $date_to    = date('d.m.Y', strtotime(" +{$operation['days']} days", $date_start));
        $note       = str_replace('[DATE_FROM]', $date_from, $operation['expense_title']);
        $note       = str_replace('[DATE_TO]',   $date_to,   $note);


        $this->db->beginTransaction();
        try {
            $operation_id = $this->createExpenseRaw(-$operation['price'], $note, 'completed');

            $date_disable = date('Y-m-d H:i:s', strtotime(" +{$operation['days']} days", $date_start));

            if ($this->issetModuleControl()) {
                $this->callModuleControl('billingExpense', array($operation_id, $date_disable));
            } else {
                $where = "code = 'billing_date_disable'";
                $this->db->update('core_settings', array(
                    'value' => $date_disable
                ), $where);
                $this->cache->remove("all_settings_" . $this->config->database->params->dbname);
            }
            $this->db->commit();

        } catch(Exception $e) {
            $this->db->rollBack();
            return false;
        }

        return true;
    }


    /**
     * Создание пополнения
     * @param float  $price
     * @param string $note
     * @param string $system_name
     * @param string $status
     * @param string $paid_operation
     * @return int
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public function createComing($price, $note, $system_name, $status = 'pending', $paid_operation = '') {

        if ($price <= 0) {
            throw new Exception('Сумма для пополнения должна быть положительной');
        }

        $this->db->beginTransaction();
        try {
            $balance = $this->getBalance();
            $this->db->insert('mod_billing_operations', array(
                'operation_num'      => '',
                'price'              => $price,
                'note'               => $note,
                'type_operation'     => 'coming',
                'status_transaction' => $status,
                'system_name'        => $system_name,
                'paid_operation'     => $paid_operation,
                'balance_before'     => $balance,
                'date_created'       => new Zend_Db_Expr('NOW()'),
                'lastuser'           => $this->auth->ID,
            ));

            $this->module = 'billing';
            $operation_num_prefix = ! empty($this->moduleConfig->operation_num_prefix)
                ? $this->moduleConfig->operation_num_prefix
                : '';

            $operation_id  = $this->db->lastInsertId();
            $operation_num = $operation_num_prefix . 'C' . str_pad($operation_id, 6, "0", STR_PAD_LEFT);

            $where = $this->db->quoteInto('id = ?', $operation_id);
            $this->db->update('mod_billing_operations', array(
                'operation_num' => $operation_num
            ), $where);

            if ($status == 'completed') {
                $this->notifySuccessComing($operation_num);
            }

            if ($this->issetModuleControl()) {
                $this->callModuleControl('billingComing', array($operation_id));
            }


            $this->db->commit();

        } catch(Exception $e) {
            $this->db->rollBack();
            throw $e;
        }


        return $operation_id;
    }


    /**
     * Создание списания
     * @param float  $price
     * @param string $note
     * @param string $status
     * @return int
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    private function createExpenseRaw($price, $note, $status = 'pending') {

        if ($price >= 0) {
            throw new Exception('Сумма для списания должна быть отрицательной');
        }


        $this->db->beginTransaction();
        try {
            $balance = $this->getBalance();
            $this->db->insert('mod_billing_operations', array(
                'operation_num'      => '',
                'price'              => $price,
                'note'               => $note,
                'type_operation'     => 'expense',
                'status_transaction' => $status,
                'balance_before'     => $balance,
                'date_created'       => new Zend_Db_Expr('NOW()'),
                'lastuser'           => $this->auth->ID,
            ));

            $this->module = 'billing';
            $operation_num_prefix = ! empty($this->moduleConfig->operation_num_prefix)
                ? $this->moduleConfig->operation_num_prefix
                : '';

            $operation_id  = $this->db->lastInsertId();
            $operation_num = $operation_num_prefix . 'E' . str_pad($operation_id, 6, "0", STR_PAD_LEFT);

            $where = $this->db->quoteInto('id = ?', $operation_id);
            $this->db->update('mod_billing_operations', array(
                'operation_num' => $operation_num
            ), $where);

            $this->db->commit();

        } catch(Exception $e) {
            $this->db->rollBack();
            throw $e;
        }


        return $operation_id;
    }
}