<?php

require_once DOC_ROOT . "/core2/inc/classes/Templater3.php";
require_once DOC_ROOT . "/core2/inc/classes/Alert.php";

require_once 'Billing.php';


/**
 * Class Billing_Disable
 */
class Billing_Disable extends Billing {


    /**
     * Проверка, активна ли система
     * @return bool
     */
    public function isDisable() {

        $module = $this->module;
        $this->module = 'billing';
        $is_active_date_disable = $this->moduleConfig->is_active_date_disable;
        $this->module = $module;

        if ( ! $is_active_date_disable) {
            return false;
        }


        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingIsDisable');

        } else {
            if (empty($this->auth->ID)) {
                return false;
            }

            if ($this->auth->ADMIN) {
                return false;
            }

            $date_disable = $this->getSetting('billing_date_disable');
            return $date_disable ? strtotime($date_disable) < time() : false;
        }
    }


    /**
     * Получение страницы блокировки
     * @return string
     */
    public function getDisablePage() {

        if ($this->issetModuleControl()) {
            return $this->callModuleControl('billingGetDisablePage');

        } else {
            Zend_Registry::set('context', array('billing', 'index'));

            $balance         = $this->getBalance();
            $balance_commafy = Tool::commafy($balance);

            $content = "<h2>Для продолжения работы пополните баланс.</h2>
                        <a href=\"/\" onclick=\"document.cookie.split(';').forEach(function(c){document.cookie=c.replace(/^ +/, '').replace(/=.*/, '=;expires='+new Date().toUTCString()+';path=/');});\"
                           >Сменить пользователя</a>
                        <br><br><br>
                        <h4>Ваш текущий баланс: {$balance_commafy} BYN.</h4>
                        <br>";

            if ( ! empty($_GET['operation'])) {
                $content .= $this->getBillingExpense($_GET['operation']);
            } else {
                $content .= $this->getBillingOperations();
            }

            $src = $this->getModuleSrc('billing');
            $tpl = new Templater3(__DIR__ . '/../html/disable_page.html');
            $tpl->assign('[SYSTEM_NAME]', $this->config->system->name);
            $tpl->assign('[MOD_SRC]',     $src);
            $tpl->assign('[CONTENT]',     $content);
            return $tpl->render();
        }
    }


    /**
     * @return string
     */
    private function getBillingOperations() {

        $this->module = 'billing';
        $config = $this->moduleConfig;

        $tpl        = new Templater3(__DIR__ . '/../html/operations_form.html');
        $operations = $config->operations->toArray();
        $currency   = $config->currency;

        if ( ! empty($operations)) {
            $first_element = true;
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

        return $tpl->render();
    }


    /**
     * @param string $operation_name
     * @return string
     * @throws Exception
     */
    private function getBillingExpense($operation_name) {

        $this->module = 'billing';
        $config = $this->moduleConfig;

        if ( ! isset($config->operations->{$operation_name})) {
            $message = Alert::getDanger('<h4>Указанная операция не существует.</h4>Вернитесь к списку операций и повторите выбор.');
            return $message . '<a href="/">Назад</a>';
        }


        $tpl = new Templater3(__DIR__ . '/../html/billing_expense.html');

        $billing      = new Billing();
        $currency     = $config->currency;
        $operation    = $config->operations->{$operation_name}->toArray();
        $systems      = $billing->getSystems();
        $date_disable = $billing->getDateDisable();
        $balance      = $billing->getBalance();


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

                    $plugin_title = $config->system->{$name}->title;

                    $tpl->pay_methods->pay_method->assign('[NAME]',  $name);
                    $tpl->pay_methods->pay_method->assign('[TITLE]', $plugin_title);
                    $tpl->pay_methods->pay_method->reassign();
                }

            } else {
                $system       = current($systems);
                $plugin_name  = key($systems);
                $plugin_title = $config->system->{$plugin_name}->title;

                $tpl->one_method->assign('[NAME]', $plugin_name);

                if ($balance > 0) {
                    $tpl->one_method->assign('[TITLE]', 'Мой баланс и ' . $plugin_title);
                } else {
                    $tpl->one_method->assign('[TITLE]', $plugin_title);
                }

                $plugin_controller = new $system['class']();

                $tpl->systems->assign('[NAME]',          $plugin_name);
                $tpl->systems->assign('[FORM_CONTENT]',  $plugin_controller->getFormExpense($operation_name));
            }

        } else {
            $message = Alert::getDanger('<h4>Ошибка</h4>В модуле отсутствуют плагины платежных систем.');
            return $message . '<a href="/">Назад</a>';
        }

        return $tpl->render();
    }
}