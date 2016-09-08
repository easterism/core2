<?php

require_once 'Billing.php';


/**
 * Class Billing_System
 */
abstract class Billing_System extends Billing {

    /**
     * Форма пополнения
     * @return mixed
     */
    abstract public function getFormComing();


    /**
     * Форма списания
     * @param string $operation_name
     * @return mixed
     */
    abstract public function getFormExpense($operation_name);


    /**
     * Проверка зависимостей
     * Выполняется при открытии модуля
     */
    public function printDependence() {

    }


    /**
     * Метод пополнения
     * @param string $paid_operation
     */
    public function createComing($paid_operation = '') {

    }


    /**
     * Проверка оплаты
     * @param string $operation_num
     * @param string $transaction_id
     */
    public function checkComing($operation_num, $transaction_id) {

    }
}