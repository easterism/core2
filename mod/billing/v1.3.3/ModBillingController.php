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

        // Создание операции
        if ( ! empty($_POST['system_name']) && ! empty($_POST['type_operation'])) {
            try {
                $operation_name = ! empty($_POST['operation_name']) ? $_POST['operation_name'] : '';
                $price          = ! empty($_POST['price'])          ? $_POST['price']          : '';
                $result         = array();

                switch ($_POST['type_operation']) {
                    case 'coming':
                        $result['data'] = $this->createComing($_POST['system_name'], $price, $operation_name);
                        break;
                    case 'expense':
                        $result_expense = $this->createExpense($operation_name);
                        if ($result_expense !== true) {
                            throw new Exception('При оплате произошел сбой, попробуйте повторить операцию позже');
                        }
                        break;
                    default:
                        throw new Exception('Некорректный тип операции');
                }
                $result['status'] = 'success';

            } catch(Exception $e) {
                $result = array(
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                );
            }
            return json_encode($result);
        }



        $app             = "index.php?module=billing";
        $date_disable    = $this->getDateDisable();
        $currency        = $this->moduleConfig->currency;
        $balance_commafy = Tool::commafy($this->getBalance());

        try {
            $this->printDependencies();
        } catch(Exception $e) {
            return Alert::getDanger($e->getMessage());
        }

        $src = $this->getModuleSrc('billing');
        if (THEME == 'default') {
            $this->printCss($src . '/html/css/bootstrap.default-theme.css');
        }
        $this->printCss($src . '/html/css/styles.css');

        $title  = $this->moduleConfig->is_active_date_disable ? 'Лицензия оплачена до ' . date('d.m.Y', strtotime($date_disable)) . '<br>' : '';
        $title .= $this->moduleConfig->is_add_balance         ? 'Баланс: ' . $balance_commafy . ' ' . $currency                            : '';

        if (empty($title)) {
            $title = 'Оплаты';
        }

        $tab = new tabs($this->resId);
        $tab->addTab('Платежи', $app, '135');
        $tab->addTab('История изменений', $app, '135');
        $tab->beginContainer($title);

        switch ($tab->activeTab) {
            case 1 :
                $billing_index = new Billing_Index();
                if ( ! empty($_GET['operation'])) {
                    echo $billing_index->getEditBilling($_GET['operation']);
                } else {
                    echo $billing_index->getEditOperation();
                }
                break;

            case 2 :
                $billing_index = new Billing_Index();
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

        $this->module = 'billing';
        $is_active_date_disable = $this->moduleConfig->is_active_date_disable;

        if ( ! $is_active_date_disable) {
            return false;
        }


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
                $location          = $this->getModuleLocation('billing');
                $billing_mail_from = $this->getSetting('billing_mail_from');
                $protocol          = $this->getSetting('https') == 'Y' ? 'https' : 'http';
                $server_urn        = isset($this->config->system) && isset($this->config->system->host)
                    ? $this->config->system->host
                    : '';
                $service_name  = isset($this->moduleConfig->service_name)
                    ? $this->moduleConfig->service_name
                    : $server_urn;
                $mail_tpl_file = ! empty($this->moduleConfig->mail_tpl_file)
                    ? (substr($this->moduleConfig->mail_tpl_file, 0, 1) == '/'
                        ? $this->moduleConfig->mail_tpl_file
                        : DOC_PATH . $this->moduleConfig->mail_tpl_file)
                    : $location . '/html/email.html';


                $operations     = $this->moduleConfig->operations->toArray();
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

                            <a href=\"{$protocol}://{$server_urn}/index.php#module=billing\">Пополнить баланс</a>
                        ";


                        $tpl_email = file_get_contents($mail_tpl_file);
                        $tpl_email = str_replace('[CONTENT]',      $content_email, $tpl_email);
                        $tpl_email = str_replace('[SERVICE_NAME]', $service_name,  $tpl_email);


                        foreach ($emails as $email) {
                            if ( ! empty($email)) {
                                $mail = $this->modAdmin->createEmail();

                                if ($billing_mail_from) {
                                    $mail->from($billing_mail_from);
                                }

                                $mail->to($email)
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

        $this->module = 'billing';
        $is_active_date_disable = $this->moduleConfig->is_active_date_disable;

        if ( ! $is_active_date_disable) {
            return false;
        }


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
                $operations = $this->moduleConfig->operations->toArray();

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
     * Действия над платежными операциями
     */
    public function restOperations() {

        if ( ! empty($this->moduleConfig->gateway_url) &&
             ! empty($this->moduleConfig->gateway_action_md5) &&
            (empty($_SERVER['HTTP_CORE_BILLING_HASH']) ||
             $_SERVER['HTTP_CORE_BILLING_HASH'] != $this->moduleConfig->gateway_action_md5)
        ) {
            throw new WebServiceException("hash sum not equal", 802, 400);
        }

        if (empty($_GET['system'])) {
            throw new WebServiceException("parameter 'system' missing", 800, 400);
        }

        $systems = $this->getSystems();

        if ( ! isset($systems[$_GET['system']])) {
            throw new WebServiceException('the specified payment system not found', 803, 400);
        }


        try {
            $system = new $systems[$_GET['system']]['class']();
            return $system->actionOperations();

        } catch(Exception $e) {
            throw new WebServiceException($e->getMessage(), 804, $e->getCode());
        }
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
        $operations = $this->moduleConfig->operations->toArray();
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
            }

            if ( ! empty($bad_operations)) {
                throw new Exception("В конфигурации модуля некоторые операции имеют некорректные значения: " . implode(", ", $bad_operations));
            }
        }
    }


    /**
     * @param string $system_name
     * @param float  $price
     * @param string $operation_name
     * @return array
     * @throws Exception
     */
    private function createComing($system_name, $price, $operation_name) {

        $systems = $this->getSystems();

        if ( ! isset($systems[$system_name])) {
            throw new Exception('Указанная платежная система не найдена');
        }

        $system = new $systems[$system_name]['class']();
        return $system->createComing($price, $operation_name);
    }


    /**
     * @param string $operation_name
     * @return array
     * @throws Exception
     */
    private function createExpense($operation_name) {

        if (empty($operation_name)) {
            throw new Exception('Не указано название операции');
        }
        $billing_operations = new Billing_Operations();
        return $billing_operations->createExpense($operation_name);
    }
}