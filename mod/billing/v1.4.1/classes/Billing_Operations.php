<?php

require_once 'Billing.php';


/**
 * Class Billing_Operations
 */
class Billing_Operations extends Billing {


    /**
     * Создание списания по названию операции
     * @param string $operation_name
     * @param string $currency
     * @return bool
     * @throws Exception
     */
    public function createExpense($operation_name, $currency = null) {

        $this->module = 'billing';

        if ( ! isset($this->moduleConfig->operations->{$operation_name})) {
            throw new Exception('Указанная операция не существует');
        }

        $date_disable = $this->getDateDisable();
        $balance      = $this->getBalance();
        $operation    = $this->moduleConfig->operations->{$operation_name}->toArray();

        if ($balance < $operation['price']) {
            throw new Exception('На балансе недостаточное количество средств для оплаты указанной операции');
        }

        if (empty($operation['days'])) {
            $note         = $operation['expense_title'];
            $date_disable = null;

        } else {
            $operation['days']++;
            $date_start   = strtotime($date_disable) > time() ? strtotime($date_disable) : time();
            $date_from    = date('d.m.Y', $date_start);
            $date_to      = date('d.m.Y', strtotime(" +{$operation['days']} days", $date_start));
            $note         = str_replace('[DATE_FROM]', $date_from, $operation['expense_title']);
            $note         = str_replace('[DATE_TO]',   $date_to,   $note);
            $date_disable = date('Y-m-d', strtotime(" +{$operation['days']} days", $date_start));
        }



        $this->db->beginTransaction();
        try {
            $operation_id = $this->createExpenseRaw(-$operation['price'], $note, $currency, 'completed');

            if ($this->issetModuleControl()) {
                $this->callModuleControl('billingExpense', array($operation_id, $date_disable));

            } elseif ($date_disable) {
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
     * @param array $coming
     * @return int
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public function createComing($coming) {

        if (empty($coming['price']) || $coming['price'] < 0) {
            throw new Exception('Сумма для пополнения должна быть положительной');
        }

        if (empty($coming['system_name'])) {
            throw new Exception('Не указана платежная система');
        }

        $this->db->beginTransaction();
        try {

            $balance = $this->getBalance();
            $this->db->insert('mod_billing_operations', array(
                'operation_num'      => '',
                'price'              => $coming['price'],
                'note'               => ! empty($coming['note']) ? $coming['note'] : new Zend_Db_Expr('NULL'),
                'type_operation'     => 'coming',
                'status_transaction' => ! empty($coming['status']) ? $coming['status'] : 'pending',
                'system_name'        => $coming['system_name'],
                'paid_operation'     => ! empty($coming['paid_operation']) ? $coming['paid_operation'] : new Zend_Db_Expr('NULL'),
                'balance_before'     => $balance,
                'discount_price'     => ! empty($coming['discount_price']) ? $coming['discount_price'] : new Zend_Db_Expr('NULL'),
                'discount_name'      => ! empty($coming['discount_name'])  ? $coming['discount_name']  : new Zend_Db_Expr('NULL'),
                'shipping_price'     => ! empty($coming['shipping_price']) ? $coming['shipping_price'] : new Zend_Db_Expr('NULL'),
                'shipping_name'      => ! empty($coming['shipping_name'])  ? $coming['shipping_name']  : new Zend_Db_Expr('NULL'),
                'tax'                => ! empty($coming['tax'])            ? $coming['tax']            : new Zend_Db_Expr('NULL'),
                'currency'           => ! empty($coming['currency'])       ? $coming['currency']       : new Zend_Db_Expr('NULL'),
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

            if ($coming['status'] == 'completed') {
                $this->notifySuccessComing($operation_num);
            }

            if ($this->issetModuleControl()) {
                $this->callModuleControl('billingCreateComing', array($operation_id));
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
     * @param string $currency
     * @param string $status
     * @return int
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    private function createExpenseRaw($price, $note, $currency = null, $status = 'pending') {

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
                'currency'           => ! empty($currency) ? $currency : new Zend_Db_Expr('NULL'),
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