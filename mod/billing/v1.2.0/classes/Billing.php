<?php
require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';

require_once __DIR__ . '/Billing_Operations.php';


/**
 * Class Billing
 */
class Billing extends Common {


    /**
     * Получение даты отключения
     * @return string
     */
    public function getDateDisable() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingGetDateDisable');

        } else {
            $date_disable = $this->getSetting('billing_date_disable');
            return $date_disable
                ? date('Y-m-d h:i:s', strtotime($date_disable))
                : date('Y-m-d h:i:s', strtotime('+ 1 minute'));
        }
    }


    /**
     * Получение баланса
     * @return float
     */
    public function getBalance() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingGetBalance');

        } else {
            return (float)$this->db->fetchOne("
                SELECT SUM(price)
                FROM mod_billing_operations
                WHERE status_transaction = 'completed'
            ");
        }
    }


    /**
     * Получение списка установленных плагинов платежных систем
     * @return array
     * @throws Exception
     */
    public function getSystems() {

        $this->module   = 'billing';
        $systems        = array();
        $systems_dir    = __DIR__ . '/../systems';
        $systems_config = $this->moduleConfig->system->toArray();


        if ( ! empty($systems_config)) {
            foreach ($systems_config as $name => $system) {

                $plugin_controller_name = 'Billing_System_' . ucfirst($name);
                $plugin_controller_file = $systems_dir . '/' .  $name . '/' . $plugin_controller_name . '.php';

                if ( ! file_exists($plugin_controller_file)) {
                    throw new Exception("Не найден файл-контроллер платежной системы \"{$plugin_controller_file}\"");
                }

                require_once $plugin_controller_file;


                if ( ! class_exists($plugin_controller_name)) {
                    throw new Exception("Не найден класс-контроллер платежной системы \"{$plugin_controller_name}\"");
                }

                $systems[$name] = array(
                    'file'  => $plugin_controller_file,
                    'class' => $plugin_controller_name
                );
            }
        }

        return $systems;
    }


    /**
     * Проверка указан и активен ли контролирующий модуль
     * @return bool
     */
    protected function issetModuleControl() {

        $this->module = 'billing';

        return isset($this->moduleConfig->control_module_name) &&
               ! empty($this->moduleConfig->control_module_name) &&
               $this->isModuleActive(strtolower($this->moduleConfig->control_module_name));
    }


    /**
     * Вызов метода из контролирующего модуля
     * @param string $method
     * @param array  $params
     * @throws Exception
     * @return mixed
     */
    protected function callModuleControl($method, $params = array()) {

        $mod_name            = strtolower($this->moduleConfig->control_module_name);
        $mod_location        = $this->getModuleLocation($mod_name);
        $mod_controller_name = 'Mod' . ucfirst($mod_name) . 'Controller';
        $mod_controller_file = $mod_location . '/' . $mod_controller_name . '.php';

        if ( ! file_exists($mod_controller_file)) {
            throw new Exception("Не найден файл-контроллер модуля \"{$mod_name}\"");
        }

        require_once $mod_controller_file;


        if ( ! class_exists($mod_controller_name)) {
            throw new Exception("Не найден класс-контроллер модуля \"{$mod_name}\"");
        }

        if ( ! in_array("Billing_Control", class_implements($mod_controller_name))) {
            throw new Exception("К классу-контроллеру модуля \"{$mod_name}\" не подключен интерфейс Billing_Control");
        }

        $mod_controller = new $mod_controller_name();
        return call_user_func_array(array($mod_controller, $method), $params);
    }


    /**
     * Отправка письма с уведомлением об успешной оплате клиента
     * @param string $operation_num
     */
    protected function notifySuccessComing($operation_num) {

        $billing_mail_success_coming = $this->getSetting('billing_mail_success_coming');
        $billing_mail_from           = $this->getSetting('billing_mail_from');

        if ( ! empty($billing_mail_success_coming)) {
            $operation = $this->db->fetchRow("
                SELECT price,
                       note,
                       transaction_id,
                       system_name,
                       DATE_FORMAT(lastupdate, '%d.%m.%Y %H:%i:%s') AS lastupdate
                FROM mod_billing_operations
                WHERE operation_num = ?
            ", $operation_num);

            if ( ! empty($operation)) {
                $service_name = ! empty($this->moduleConfig->service_name) ? $this->moduleConfig->service_name : '';
                $currency     = ! empty($this->moduleConfig->currency) ? $this->moduleConfig->currency : '';

                $body  = "<b>Платежная система:</b> {$operation['system_name']}<br>";
                $body .= "<b>Время совершения операции:</b> {$operation['lastupdate']}<br>";
                $body .= "<b>№ операции:</b> {$operation_num}<br>";
                $body .= "<b>№ транзакции:</b> {$operation['transaction_id']}<br>";
                $body .= "<b>Сумма:</b> {$operation['price']} {$currency}<br>";

                $mail = $this->modAdmin->createEmail();

                if ( ! empty($billing_mail_from)) {
                    $mail->from($billing_mail_from);
                }

                $mail->to($billing_mail_success_coming)
                    ->subject("Поступила оплата ({$service_name})")
                    ->body($body)
                    ->send();
            }
        }
    }
}