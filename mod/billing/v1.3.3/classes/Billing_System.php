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
     * Метод пополнения
     * @param float  $price
     * @param string $operation_name
     */
    abstract public function createComing($price, $operation_name = '');


    /**
     * Действия над платежными операциями
     */
    abstract public function actionOperations();


    /**
     * Проверка зависимостей
     * Выполняется при открытии модуля
     */
    public function printDependence() {

    }
}