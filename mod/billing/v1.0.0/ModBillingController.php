<?php

require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Alert.php';
require_once DOC_ROOT . 'core2/inc/classes/Tool.php';
require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';

require_once 'classes/Billing.php';
require_once 'classes/Billing_Index.php';
require_once 'classes/Billing_Operations.php';



/**
 * Class ModBillingController
 */
class ModBillingController extends Billing {

    /**
     * @return string
     */
    public function action_index() {

        if ( ! empty($_POST['system_name']) && ! empty($_POST['type_operation'])) {
            try {
                $result = $this->paymentOperations($_POST['system_name'], $_POST['type_operation']);
            } catch(Exception $e) {
                $result = array(
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                );
            }
            return json_encode($result);
        }


        $app             = "index.php?module=billing";
        $billing_index   = new Billing_Index();
        $date_disable    = $billing_index->getDateDisable();
        $currency        = $this->moduleConfig->currency;
        $balance_commafy = Tool::commafy($billing_index->getBalance());

        $this->printDependencies();


        $src = $this->getModuleSrc('billing');
        if (THEME == 'default') {
            $this->printCss($src . '/html/css/bootstrap.default-theme.css');
        }
        $this->printCss($src . '/html/css/styles.css');


        $tab = new tabs($this->resId);
        $tab->addTab('Платежи', $app, '135');
        $tab->addTab('История', $app, '135');
        $tab->beginContainer('Лицензия оплачена до ' . date('d.m.Y', strtotime($date_disable)) .
                             '<br>Баланс: ' . $balance_commafy . ' ' . $currency);

        switch ($tab->activeTab) {
            case 1 :
                if ( ! empty($_GET['operation'])) {
                    echo $billing_index->getEditBilling($_GET['operation']);
                } else {
                    echo $billing_index->getEditOperation();
                }
                break;

            case 2 :
                echo $billing_index->getListHistory();
                break;
        }


        $tab->endContainer();
        return ob_get_clean();
    }


    /**
     * @return array
     */
    public function getCronMethods() {

        return array(
            'cronReminderPay'     => 'Напоминание об оплате',
            'cronCheckOperations' => 'Проверка оплат пополнений',
            'cronLicenseRenewals' => 'Автоматическое продление лицензий',
        );
    }


    /**
     * Напоминание об оплате
     * Уведомление за 5, 2 и 1 день до отключения
     */
    public function cronReminderPay() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingCronReminderPay');

        } else {

            $emails      = $this->moduleConfig->mail_reminder_pay;
            $is_reminder = $this->db->fetchOne("
                SELECT 1
                FROM core_settings AS s
                WHERE s.code = 'billing_date_disable'
                  AND NOW() BETWEEN CAST(s.value AS DATE) - INTERVAL 6 DAY AND CAST(s.value AS DATE)
            ");

            if ($is_reminder && ! empty($emails)) {
                $location     = $this->getModuleLocation('billing');
                $mail_from    = $this->moduleConfig->mail_from;
                $service_name = $this->moduleConfig->service_name;
                $protocol     = $_SERVER['SERVER_PORT'] == '443' ||  $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
                $server_urn   = isset($this->moduleConfig->system) && isset($this->moduleConfig->system->host)
                    ? $this->moduleConfig->system->host
                    : $_SERVER['SERVER_NAME'];
                $mail_tpl_file =  ! empty($this->moduleConfig->mail_tpl_file)
                    ? (substr($this->moduleConfig->mail_tpl_file, 0, 1) == '/'
                        ? $this->moduleConfig->mail_tpl_file
                        : DOC_PATH . $this->moduleConfig->mail_tpl_file)
                    : '';
                $mail_tpl_file = str_replace('[BILLING_LOCATION]', $location, $mail_tpl_file);


                $operations     = $this->moduleConfig->operation->toArray();
                $balance        = $this->getBalance();
                $date_disable   = $this->getDateDisable();
                $enough_balance = true;

                foreach ($operations as $name => $operation_conf) {
                    if ($balance >= $operation_conf['price']) {
                        $enough_balance = false;
                        break;
                    }
                }

                if ($enough_balance) {
                    $d1 = new DateTime($date_disable);
                    $d2 = new DateTime();

                    $interval = $d1->diff($d2);
                    $day_diff = $interval->format('%a');

                    if ($d1 >= $d2 && ($day_diff <= 2 || $day_diff == 5)) {
                        $content_email = "
                            <p>
                                Здравствуйте!<br/><br/>
                                Период использования сервиса {$service_name} подходит к концу.<br/>
                                Пожалуйста пополните свой баланс для продолжения использования.
                            </p>

                            <a href=\"{$protocol}://{$server_urn}?module=billing\">Пополнить баланс</a>
                        ";


                        $tpl_email = file_get_contents($mail_tpl_file);
                        $tpl_email = str_replace('[CONTENT]',      $content_email, $tpl_email);
                        $tpl_email = str_replace('[SERVICE_NAME]', $service_name,  $tpl_email);


                        foreach ($emails as $email) {
                            if ( ! empty($email)) {
                                $this->modAdmin->createEmail()
                                    ->from($mail_from)
                                    ->to($email)
                                    ->subject("Период использования сервиса {$service_name} подходит к концу!")
                                    ->body($tpl_email)
                                    ->send();
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Автоматическое продление лицензий
     * По максимальной цене
     */
    public function cronLicenseRenewals() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingCronLicenseRenewals');

        } else {
            $is_disabled = $this->db->fetchOne("
                SELECT 1
                FROM core_settings AS s
                WHERE s.code = 'billing_date_disable'
                  AND NOW() > CAST(s.value AS DATE)
            ");

            if ($is_disabled) {
                $operations = $this->moduleConfig->operation->toArray();

                if ( ! empty($operations)) {
                    $balance        = $this->getBalance();
                    $operation      = array();
                    $operation_name = '';

                    foreach ($operations as $name => $operation_conf) {
                        if ($balance >= $operation_conf['price'] &&
                            (empty($operation) || $operation['price'] < $operation_conf['price'])
                        ) {
                            $operation      = $operation_conf;
                            $operation_name = $name;
                        }
                    }

                    if ( ! empty($operation_name)) {
                        $billing_operations = new Billing_Operations();
                        $billing_operations->createExpense($operation_name);
                    }
                }
            }
        }
    }


    /**
     * Проверка оплат платежей
     */
    public function cronCheckOperations() {

        $operations = $this->db->fetchAll("
            SELECT operation_num,
                   transaction_id,
                   system_name
            FROM mod_billing_operations
            WHERE transaction_id IS NOT NULL
              AND transaction_id != ''
              AND system_name IS NOT NULL
              AND system_name != ''
              AND type_operation = 'coming'
              AND status_transaction = 'pending'
        ");

        if ( ! $operations) {
            $systems = $this->getSystems();

            foreach ($operations as $operation) {

                if ( ! isset($systems[$operation['system_name']])) {
                    continue;
                }

                $system = new $systems[$operation['system_name']]['class']();
                $system->checkComing($operation['operation_num'], $operation['transaction_id']);
            }
        }
    }


    /**
     * Подтверждение выполненной оплаты
     * @return string
     * @throws WebServiceException
     * @throws Zend_Db_Adapter_Exception
     */
    public function restSuccess() {

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new WebServiceException('incorrect request method', 200, 400);
        }

        if (empty($_POST['system_name'])) {
            throw new WebServiceException("parameter 'system_name' missing", 800, 400);
        }

        $systems = $this->getSystems();

        if ( ! isset($systems[$_POST['system_name']])) {
            throw new WebServiceException('the specified payment system not found', 802, 400);
        }


        try {
            if (empty($_POST['operation_num'])) {
                throw new WebServiceException("parameter 'operation_num' missing", 801, 400);
            }

            $transaction_id = ! empty($_POST['transaction_id']) ? $_POST['transaction_id'] : '';

            $system = new $systems[$_POST['system_name']]['class']();
            $system->checkComing($_POST['operation_num'], $transaction_id);

        } catch(Exception $e) {
            throw new WebServiceException($e->getMessage(), 802, $e->getCode());
        }



        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        return json_encode(array(
            'status' => 'success'
        ));
    }


    /**
     * Отмена оплаты
     * Ответ платежной системы
     * @return string
     * @throws WebServiceException
     * @throws Zend_Db_Adapter_Exception
     */
    public function restCancel() {

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new WebServiceException('incorrect request method', 200, 400);
        }

        if (empty($_POST['system_name'])) {
            throw new WebServiceException("parameter 'system_name' missing", 800, 400);
        }

        $systems = $this->getSystems();

        if ( ! isset($systems[$_POST['system_name']])) {
            throw new WebServiceException('the specified payment system not found', 805, 400);
        }


        if (empty($_POST['operation_num'])) {
            throw new WebServiceException("parameter 'operation_num' missing", 801, 400);
        }

        $operation = $this->db->fetchRow("
            SELECT id,
                   status_transaction
            FROM mod_billing_operations
            WHERE operation_num = ?
        ", $_POST['operation_num']);

        if (empty($operation)) {
            throw new WebServiceException("operation number \"{$_POST['operation_num']}\" not found", 804, 400);
        }

        if ($operation['status_transaction'] == 'pending') {
            $where = $this->db->quoteInto('id = ?', $operation['id']);
            $this->db->update('mod_billing_operations', array(
                'status_transaction' => 'canceled'
            ), $where);
        } else {
            throw new WebServiceException('the operation has already been performed', 803, 400);
        }


        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        return json_encode(array(
            'status' => 'success'
        ));
    }


    /**
     * @throws Exception
     */
    private function printDependencies() {

        $systems = $this->getSystems();

        // Проверка настроек платежных систем
        if ( ! empty($systems)) {
            foreach ($systems as $name => $system) {
                $plugin_controller = new $system['class']();

                if (empty($this->moduleConfig->currency)) {
                    throw new Exception("В конфигурации модуля не заполнен обязательный параметр \"currency\"");
                }

                if (empty($this->moduleConfig->system->{$name}->title)) {
                    throw new Exception("В конфигурации модуля не заполнен обязательный параметр \"system.{$name}.title\"");
                }

                if ( ! method_exists($plugin_controller, 'printDependence')) {
                    throw new Exception("Не найден метод \"printDependence\" в классе платежной системы \"{$system['class']}\"");
                }

                $plugin_controller->printDependence();
            }

        } else {
            throw new Exception("В модуле отсутствуют плагины платежных систем");
        }


        // Проверка операций
        $operations = $this->moduleConfig->operation->toArray();
        if ( ! empty($operations)) {
            $bad_operations = array();

            foreach ($operations as $name => $operation) {
                if (empty($operation['title'])) {
                    $bad_operations[] = "operation.{$name}.title";
                }
                if (empty($operation['coming_title'])) {
                    $bad_operations[] = "operation.{$name}.coming_title";
                }
                if (empty($operation['expense_title'])) {
                    $bad_operations[] = "operation.{$name}.expense_title";
                }
                if (empty($operation['price']) || $operation['price'] <= 0) {
                    $bad_operations[] = "operation.{$name}.price";
                }
                if (empty($operation['days']) || $operation['days'] <= 0) {
                    $bad_operations[] = "operation.{$name}.days";
                }
            }

            if ( ! empty($bad_operations)) {
                throw new Exception("В конфигурации модуля некоторые операции имеют некорректные значения: " . implode(", ", $bad_operations));
            }
        }
    }


    /**
     * @param string $system_name
     * @param string $type_operation
     * @return array
     * @throws Exception
     */
    private function paymentOperations($system_name, $type_operation) {

        $result = array();

        if ($system_name == 'balance') {
            switch ($type_operation) {
                case 'expense':
                    if (empty($_POST['paid_operation'])) {
                        throw new Exception('Не указано название операции');
                    }
                    $billing_operations = new Billing_Operations();
                    $result_expense = $billing_operations->createExpense($_POST['paid_operation']);
                    if ($result_expense !== true) {
                        throw new Exception('При оплате произошел сбой, попробуйте повторить операцию позже');
                    }
                    break;

                default:
                    throw new Exception('Указанный тип операции является некорректным');
            }


        } else {
            $systems = $this->getSystems();

            if ( ! isset($systems[$system_name])) {
                throw new Exception('Указанная платежная система не найдена');
            }

            $system = new $systems[$system_name]['class']();

            switch ($type_operation) {
                case 'coming':
                    $paid_operation = ! empty($_POST['paid_operation']) ? $_POST['paid_operation'] : '';
                    $result_coming  = $system->createComing($paid_operation);
                    if (is_array($result_coming)) {
                        $result = $result_coming;
                    } else {
                        $result['data'] = $result_coming;
                    }
                    break;

                default:
                    throw new Exception('Указанный тип операции является некорректным');
            }
        }


        $result['status'] = 'success';
        return $result;
    }
}