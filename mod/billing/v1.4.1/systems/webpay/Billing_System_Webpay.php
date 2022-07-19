<?php

require_once __DIR__ . '/../../classes/Billing.php';
require_once __DIR__ . '/../../classes/Billing_System.php';
require_once __DIR__ . '/../../classes/Billing_Operations.php';


/**
 * Class Billing_System_Webpay
 */
class Billing_System_Webpay extends Billing_System {


    /**
     * @return string
     */
    public function getFormComing() {

        $tpl         = new Templater3(__DIR__ . '/html/billing_coming.html');
        $webpay      = $this->moduleConfig->system->webpay->toArray();
        $gateway_url = $this->moduleConfig->gateway_url;
        $currency    = $this->moduleConfig->currency;

        if ( ! empty($webpay['options'])) {
            $apikey   = $this->getApikey();
            $protocol = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ||
                        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

            if ($_SERVER['SERVER_PORT']) {
                $port = $protocol == 'https'
                    ? ($_SERVER['SERVER_PORT'] != 443 ? ':' . $_SERVER['SERVER_PORT'] : '')
                    : ($_SERVER['SERVER_PORT'] != 80  ? ':' . $_SERVER['SERVER_PORT'] : '');
            } else {
                $port = '';
            }

            $host_encoded = urlencode("{$protocol}://{$_SERVER['SERVER_NAME']}{$port}");

            if (empty($webpay['options']['wsb_return_url'])) {
                $webpay['options']['wsb_return_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=success"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=success&apikey={$apikey}";
            }
            if (empty($webpay['options']['wsb_cancel_return_url'])) {
                $webpay['options']['wsb_cancel_return_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=cancel"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=cancel&apikey={$apikey}";
            }
            if (empty($webpay['options']['wsb_notify_url'])) {
                $webpay['options']['wsb_notify_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=notify"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=success&apikey={$apikey}";
            }

            foreach ($webpay['options'] as $name => $value) {
                $tpl->form_field->assign('[NAME]',  $name);
                $tpl->form_field->assign('[VALUE]', htmlspecialchars($value));
                $tpl->form_field->reassign();
            }
        }


        $user_email = $this->db->fetchOne("
            SELECT u.email
            FROM core_users AS u
            WHERE u.u_id = ?
        ", $this->auth->ID);

        $action            = ! empty($webpay['action'])           ? $webpay['action']           : '';
        $max_coming_price  = ! empty($webpay['max_coming_price']) ? $webpay['max_coming_price'] : '';

        if ($gateway_url) {
            $tpl->assign('[ACTION]', $gateway_url);
            $tpl->form_field->assign('[NAME]',  'billing_vars[action]');
            $tpl->form_field->assign('[VALUE]', $action);
        } else {
            $tpl->assign('[ACTION]', $action);
        }


        $tpl->assign('[EMAIL]',            $user_email);
        $tpl->assign('[MAX_COMING_PRICE]', $max_coming_price);
        $tpl->assign('[CURRENCY]',         $currency);
        $tpl->assign('[OPERATION_NAME]',   '');

        ob_start();
        $src = $this->getModuleSrc('billing');
        $this->printJs($src . '/systems/webpay/html/js/billing_webpay.js');
        $billing_webpay = ob_get_clean();

        return $billing_webpay . $tpl->render();
    }


    /**
     * @param string $operation_name
     * @return string
     * @throws Exception
     */
    public function getFormExpense($operation_name) {

        $this->module = 'billing';

        if ( ! isset($this->moduleConfig->operations->{$operation_name})) {
            throw new Exception("Operation \"{$operation_name}\" not found");
        }

        $tpl         = new Templater3(__DIR__ . '/html/billing_expense.html');
        $operation   = $this->moduleConfig->operations->{$operation_name}->toArray();
        $webpay      = $this->moduleConfig->system->webpay->toArray();
        $gateway_url = $this->moduleConfig->gateway_url;
        $currency    = $this->moduleConfig->currency;

        if ( ! empty($webpay['options'])) {
            $apikey   = $this->getApikey();
            $protocol = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ||
                        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

            if ($_SERVER['SERVER_PORT']) {
                $port = $protocol == 'https'
                    ? ($_SERVER['SERVER_PORT'] != 443 ? ':' . $_SERVER['SERVER_PORT'] : '')
                    : ($_SERVER['SERVER_PORT'] != 80  ? ':' . $_SERVER['SERVER_PORT'] : '');
            } else {
                $port = '';
            }

            $host_encoded = urlencode("{$protocol}://{$_SERVER['SERVER_NAME']}{$port}");

            if (empty($webpay['options']['wsb_return_url'])) {
                $webpay['options']['wsb_return_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=success"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=success&apikey={$apikey}";
            }
            if (empty($webpay['options']['wsb_cancel_return_url'])) {
                $webpay['options']['wsb_cancel_return_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=cancel"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=cancel&apikey={$apikey}";
            }
            if (empty($webpay['options']['wsb_notify_url'])) {
                $webpay['options']['wsb_notify_url'] = ! empty($gateway_url)
                    ? "{$gateway_url}?system=webpay&host={$host_encoded}&apikey={$apikey}&action=notify"
                    : "$protocol://{$_SERVER['SERVER_NAME']}{$port}/api/billing/operations?system=webpay&action=notify&apikey={$apikey}";
            }

            foreach ($webpay['options'] as $name => $value) {
                $tpl->form_field->assign('[NAME]',  $name);
                $tpl->form_field->assign('[VALUE]', htmlspecialchars($value));
                $tpl->form_field->reassign();
            }
        }

        $billing         = new Billing();
        $balance         = $billing->getBalance();
        $date_disable    = $billing->getDateDisable();
        $operation_price = is_integer($operation['price'] - $balance)
            ? $operation['price'] - $balance
            : number_format($operation['price'] - $balance, 2, '.', '');


        $user_email = $this->db->fetchOne("
            SELECT u.email
            FROM core_users AS u
            WHERE u.u_id = ?
        ", $this->auth->ID);

        $action = ! empty($webpay['action'])  ? $webpay['action']  : '';

        if ($gateway_url) {
            $tpl->assign('[ACTION]', $gateway_url);
            $tpl->form_field->assign('[NAME]',  'billing_vars[action]');
            $tpl->form_field->assign('[VALUE]', $action);
        } else {
            $tpl->assign('[ACTION]', $action);
        }

        if (empty($operation['days'])) {
            $operation['days'] = 0;
        }

        $date_start = strtotime($date_disable) > time() ? strtotime($date_disable) : time();
        $date_from  = date('d.m.Y', $date_start);
        $date_to    = date('d.m.Y', strtotime(" +{$operation['days']} days", $date_start));
        $operation_title = str_replace('[DATE_FROM]', $date_from, $operation['coming_title']);
        $operation_title = str_replace('[DATE_TO]',   $date_to,   $operation_title);

        $tpl->assign('[OPERATION_TITLE]', $operation_title);
        $tpl->assign('[OPERATION_PRICE]', $operation_price);
        $tpl->assign('[OPERATION_NAME]',  $operation_name);
        $tpl->assign('[EMAIL]',           $user_email);
        $tpl->assign('[CURRENCY]',        $currency);


        if ($balance > 0) {
            $tpl->combo_price->assign('[PLUGIN_TITLE]',  $webpay['title']);
            $tpl->combo_price->assign('[BALANCE_PRICE]', Tool::commafy($balance));
            $tpl->combo_price->assign('[PLUGIN_PRICE]',  Tool::commafy($operation_price));
            $tpl->combo_price->assign('[TOTAL_COMMAFY]', Tool::commafy($operation['price']));
            $tpl->combo_price->assign('[CURRENCY]',      $currency);

        } else {
            $tpl->one_price->assign('[TOTAL_COMMAFY]', Tool::commafy($operation['price']));
            $tpl->one_price->assign('[CURRENCY]',      $currency);
        }


        ob_start();
        $src = $this->getModuleSrc('billing');
        $this->printJs($src . '/systems/webpay/html/js/billing_webpay.js');
        $billing_webpay = ob_get_clean();

        return $billing_webpay . $tpl->render();
    }


    /**
     * Создание операции на пополнение баланса и возврат подписанных данных на форму
     * @param float  $price          стоимость операции
     * @param string $operation_name название операции которую хотят оплатить
     * @return array
     * @throws Exception
     */
    public function createComing($price, $operation_name = '') {

        if ( ! is_numeric($price)) {
            throw new Exception('Стоимость указана некорректно');
        }

        $coming = array(
            'price'          => $price,
            'note'           => 'Пополнение баланса',
            'status'         => 'pending',
            'system_name'    => 'webpay',
            'paid_operation' => $operation_name,
        );
        $coming['discount_price'] = ! empty($this->moduleConfig->system->webpay->options->wsb_discount_price)
            ? $this->moduleConfig->system->webpay->options->wsb_discount_price
            : null;
        $coming['discount_name'] = ! empty($this->moduleConfig->system->webpay->options->wsb_discount_name)
            ? $this->moduleConfig->system->webpay->options->wsb_discount_name
            : null;
        $coming['shipping_price'] = ! empty($this->moduleConfig->system->webpay->options->wsb_shipping_price)
            ? $this->moduleConfig->system->webpay->options->wsb_shipping_price
            : null;
        $coming['shipping_name'] = ! empty($this->moduleConfig->system->webpay->options->wsb_shipping_name)
            ? $this->moduleConfig->system->webpay->options->wsb_shipping_name
            : null;
        $coming['tax'] = ! empty($this->moduleConfig->system->webpay->options->wsb_tax)
            ? $this->moduleConfig->system->webpay->options->wsb_tax
            : null;
        $coming['currency'] = ! empty($this->moduleConfig->system->webpay->options->wsb_currency_id)
            ? $this->moduleConfig->system->webpay->options->wsb_currency_id
            : null;


        if (round($coming['price'] + $coming['tax'] + $coming['shipping_price'] - $coming['discount_price'], 2) < 0.1) {
            $currency  = ! empty($coming['currency']) ? $coming['currency'] : '';
            $min_price = $coming['discount_price']
                ? ($coming['discount_price'] - ($coming['shipping_price'] + $coming['tax'] - 0.1))
                : '0.10';
            throw new Exception('Минимальная сумма для платежа: ' . $min_price . " {$currency}");
        }


        $billing_operations = new Billing_Operations();
        $operation_id = $billing_operations->createComing($coming);
        $operation    = $this->db->fetchRow("
            SELECT operation_num,
                   price
            FROM mod_billing_operations
            WHERE id = ?
        ", $operation_id);

        $signed_data = $this->sign($operation['operation_num'], $operation['price']);

        return $signed_data;
    }


    /**
     * @return bool
     */
    public function printDependence() {

        $exist_dependence = false;
        $this->module = 'billing';
        $webpay = $this->moduleConfig->system->webpay->toArray();
        $req_options  = array(
            'wsb_storeid',
            'wsb_store',
            'wsb_currency_id',
        );


        if ( ! empty($webpay['options'])) {
            foreach ($req_options as $key => $req_option) {
                if ( ! empty($webpay['options'][$req_option])) {
                    unset($req_options[$key]);
                }
            }
        }


        if ( ! array_key_exists('*scart', $webpay['options'])) {
            $req_options[] = '*scart';

        } elseif ($webpay['options']['*scart'] != '') {
            $exist_dependence = true;
            echo Alert::getDanger('В конфигурации этого модуля обязательный параметр <b>system.webpay.options.*scart</b> должен быть пуст');
        }

        $req_params = array(
            'title',
            'action',
            'secret_key',
            'payment_check_url',
            'username',
            'md5_password',
        );

        foreach ($req_params as $req_param) {
            if (empty($webpay[$req_param])) {
                $req_options[] = 'system.webpay.' . $req_param;
            }
        }

        if ( ! array_key_exists('max_coming_price', $webpay)) {
            $req_options[] = 'system.webpay.max_coming_price';
        }

        if ( ! empty($req_options)) {
            foreach ($req_options as $key => $req_option) {
                if (strpos($req_option, '.') === false) {
                    $req_options[$key] = "system.webpay.options.{$req_option}";
                }
            }
            $exist_dependence = true;
            echo Alert::getDanger('В конфигурации этого модуля отсутствуют обязательные параметры: ' . implode(', ', $req_options));
        }



        $negative_vars = array();

        if ( ! empty($webpay['max_coming_price']) && $webpay['max_coming_price'] < 0) {
            $negative_vars[] = 'system.webpay.max_coming_price';
        }
        if ( ! empty($webpay['options']['wsb_tax']) && $webpay['options']['wsb_tax'] < 0) {
            $negative_vars[] = 'system.webpay.options.wsb_tax';
        }
        if ( ! empty($webpay['options']['wsb_shipping_price']) && $webpay['options']['wsb_shipping_price'] < 0) {
            $negative_vars[] = 'system.webpay.options.wsb_shipping_price';
        }
        if ( ! empty($webpay['options']['wsb_discount_price']) && $webpay['options']['wsb_discount_price'] < 0) {
            $negative_vars[] = 'system.webpay.options.wsb_discount_price';
        }

        if ( !  empty($negative_vars)) {
            $exist_dependence = true;
            echo Alert::getDanger('В конфигурации этого модуля следующие параметры не должны быть отрицательными: ' . implode(', ', $negative_vars));
        }

        return $exist_dependence;
    }


    /**
     * Проверка оплаты
     * @param string $operation_num
     * @param string $transaction_id
     * @return bool
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public function checkComing($operation_num, $transaction_id) {

        $operation = $this->db->fetchRow("
            SELECT id,
                   transaction_id,
                   status_transaction,
                   paid_operation,
                   currency
            FROM mod_billing_operations
            WHERE operation_num = ?
        ", $operation_num);

        if (empty($operation)) {
            throw new Exception("Номер операции \"{$operation_num}\" не найден");
        }

        if (empty($operation['transaction_id'])) {
            $where = $this->db->quoteInto('id = ?', $operation['id']);
            $this->db->update('mod_billing_operations', array(
                'transaction_id' => $transaction_id
            ), $where);

            $operation['transaction_id'] = $transaction_id;
        }

        if ($operation['status_transaction'] == 'pending') {
            $data = $this->getTransactionData($operation['transaction_id']);

            if ( ! empty($data['payment_type'])) {
                if (in_array($data['payment_type'], array('1', '4'))) {
                    $where = $this->db->quoteInto('id = ?', $operation['id']);
                    $this->db->update('mod_billing_operations', array(
                        'status_transaction' => 'completed'
                    ), $where);

                    $this->notifySuccessComing($operation_num);

                    if ($operation['paid_operation']) {
                        if ($this->issetModuleControl()) {
                            $this->callModuleControl('billingCreateExpense', array(
                                $operation['paid_operation'],
                                $operation_num,
                                $operation['currency']
                            ));

                        } else {
                            $operations = $this->moduleConfig->operations->toArray();
                            if (isset($operations[$operation['paid_operation']])) {
                                $operation_config = $operations[$operation['paid_operation']];
                                $balance          = $this->getBalance();

                                if ($balance >= $operation_config['price']) {
                                    try {
                                        $billing_operations = new Billing_Operations();
                                        $billing_operations->createExpense($operation['paid_operation'], $operation['currency']);
                                    } catch (Exception $e) {
                                        // ignore
                                    }
                                }
                            }
                        }
                    }

                } elseif (in_array($data['payment_type'], array('5', '7'))) {
                    $where = $this->db->quoteInto('id = ?', $operation['id']);
                    $this->db->update('mod_billing_operations', array(
                        'status_transaction' => 'canceled'
                    ), $where);
                }
            }
        }

        return true;
    }


    /**
     * Действия над платежными операциями
     * @return string
     * @throws WebServiceException
     */
    public function actionOperations() {


        if (empty($_GET['action'])) {
            throw new WebServiceException("parameter 'action' missing", 820, 400);
        }

        if ( ! is_string($_GET['action'])) {
            throw new WebServiceException("parameter 'action' must be a string", 821, 400);
        }

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            switch ($_GET['action']) {
                case 'success': return $this->actionSuccess(); break;
                case 'cancel':  return $this->actionCancel();  break;
                default: throw new WebServiceException("parameter 'action' incorrect", 822, 400);
            }

        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            switch ($_GET['action']) {
                case 'notify':  return $this->actionNotify();  break;
                default: throw new WebServiceException("parameter 'action' incorrect", 822, 400);
            }


        } else {
            throw new WebServiceException('incorrect request method', 200, 400);
        }
    }


    /**
     * Проверка платежа
     * @return string
     * @throws WebServiceException
     */
    private function actionSuccess() {

        // если это не просто нажатие "вернуться на сайт"
        if ( ! empty($_GET['wsb_order_num']) && ! empty($_GET['wsb_tid'])) {
            $isset_operation = $this->db->fetchRow("
                SELECT 1
                FROM mod_billing_operations
                WHERE operation_num = ?
            ", $_GET['wsb_order_num']);

            if (empty($isset_operation)) {
                throw new WebServiceException("operation number \"{$_GET['wsb_order_num']}\" not found", 825, 400);
            }


            try {
                $this->checkComing($_GET['wsb_order_num'], $_GET['wsb_tid']);

            } catch(Exception $e) {
                throw new WebServiceException($e->getMessage(), 826, $e->getCode());
            }
        }


        $this->module = 'billing';

        header("HTTP/1.1 200 OK");
        if (empty($this->moduleConfig->gateway_url)) {
            header("Content-Type: text/html; charset=UTF-8");
            return "<script>window.location.href = '/index.php#module=billing';</script>" .
                   "Перенаправление на <a href=\"/index.php#module=billing\">index.php#module=billing</a>";
        } else {
            header("Content-Type: application/json");
            return json_encode(array(
                'status' => 'success'
            ));
        }
    }


    /**
     * Проверка платежа
     * @return string
     * @throws WebServiceException
     */
    private function actionNotify() {

        if (empty($_POST['site_order_id'])) {
            throw new WebServiceException("parameter 'site_order_id' missing", 828, 400);
        }

        if (empty($_POST['transaction_id'])) {
            throw new WebServiceException("parameter 'transaction_id' missing", 829, 400);
        }

        if (empty($_POST['batch_timestamp'])) {
            throw new WebServiceException("parameter 'batch_timestamp' missing", 830, 400);
        }

        if (empty($_POST['currency_id'])) {
            throw new WebServiceException("parameter 'currency_id' missing", 831, 400);
        }

        if (empty($_POST['amount'])) {
            throw new WebServiceException("parameter 'amount' missing", 832, 400);
        }

        if (empty($_POST['payment_method'])) {
            throw new WebServiceException("parameter 'payment_method' missing", 833, 400);
        }

        if (empty($_POST['order_id'])) {
            throw new WebServiceException("parameter 'order_id' missing", 834, 400);
        }

        if (empty($_POST['payment_type'])) {
            throw new WebServiceException("parameter 'payment_type' missing", 835, 400);
        }

        if (empty($_POST['rrn']) && empty($_POST['rc'])) {
            throw new WebServiceException("parameter 'rrn' or 'rc' is missing", 836, 400);
        }

        try {
            $operation = $this->db->fetchRow("
                SELECT id,
                       transaction_id,
                       status_transaction,
                       paid_operation,
                       currency
                FROM mod_billing_operations
                WHERE operation_num = ?
            ", $_POST['site_order_id']);

            if (empty($operation)) {
                throw new Exception("Номер операции \"{$_POST['site_order_id']}\" не найден");
            }

            if (empty($operation['transaction_id'])) {
                $where = $this->db->quoteInto('id = ?', $operation['id']);
                $this->db->update('mod_billing_operations', array(
                    'transaction_id' => $_POST['transaction_id']
                ), $where);

                $operation['transaction_id'] = $_POST['transaction_id'];
            }

            if ($operation['status_transaction'] == 'pending') {

                $this->checkTransactionDataNotify($_POST);

                if ( ! empty($_POST['payment_type'])) {
                    if (in_array($_POST['payment_type'], array('1', '4'))) {
                        $where = $this->db->quoteInto('id = ?', $operation['id']);
                        $this->db->update('mod_billing_operations', array(
                            'status_transaction' => 'completed'
                        ), $where);

                        $this->notifySuccessComing($_POST['site_order_id']);

                        if ($operation['paid_operation']) {
                            if ($this->issetModuleControl()) {
                                $this->callModuleControl('billingCreateExpense', array(
                                    $operation['paid_operation'],
                                    $_POST['site_order_id'],
                                    $operation['currency']
                                ));

                            } else {
                                $operations = $this->moduleConfig->operations->toArray();
                                if (isset($operations[$operation['paid_operation']])) {
                                    $operation_config = $operations[$operation['paid_operation']];
                                    $balance          = $this->getBalance();

                                    if ($balance >= $operation_config['price']) {
                                        try {
                                            $billing_operations = new Billing_Operations();
                                            $billing_operations->createExpense($operation['paid_operation'], $operation['currency']);
                                        } catch (Exception $e) {
                                            // ignore
                                        }
                                    }
                                }
                            }
                        }

                    } elseif (in_array($_POST['payment_type'], array('5', '7'))) {
                        $where = $this->db->quoteInto('id = ?', $operation['id']);
                        $this->db->update('mod_billing_operations', array(
                            'status_transaction' => 'canceled'
                        ), $where);
                    }
                }
            }

        } catch(Exception $e) {
            throw new WebServiceException($e->getMessage(), 826, $e->getCode());
        }

        header("HTTP/1.1 200 OK");
        header("Content-Type: application/json");
        return json_encode(array(
            'status' => 'success'
        ));
    }


    /**
     * Отмена платежа
     * @throws WebServiceException
     * @throws Zend_Db_Adapter_Exception
     */
    private function actionCancel() {

        if (empty($_GET['wsb_order_num'])) {
            throw new WebServiceException("parameter 'wsb_order_num' missing", 823, 400);
        }

        $operation = $this->db->fetchRow("
            SELECT id,
                   status_transaction
            FROM mod_billing_operations
            WHERE operation_num = ?
        ", $_GET['wsb_order_num']);

        if (empty($operation)) {
            throw new WebServiceException("operation number \"{$_GET['wsb_order_num']}\" not found", 825, 400);
        }

        if ($operation['status_transaction'] == 'pending') {
            $where = $this->db->quoteInto('id = ?', $operation['id']);
            $this->db->update('mod_billing_operations', array(
                'status_transaction' => 'canceled'
            ), $where);
        } else {
            throw new WebServiceException('the operation has already been performed', 827, 400);
        }


        $this->module = 'billing';

        header("HTTP/1.1 200 OK");
        if (empty($this->moduleConfig->gateway_url)) {
            header("Content-Type: text/html; charset=UTF-8");
            return "<script>window.location.href = '/index.php#module=billing';</script>" .
                   "Перенаправление на <a href=\"/index.php#module=billing\">index.php#module=billing</a>";
        } else {
            header("Content-Type: application/json");
            return json_encode(array(
                'status' => 'success'
            ));
        }
    }


    /**
     * @param int|string $transaction_id
     * @return array
     * @throws Exception
     */
    private function getTransactionData($transaction_id) {

        $this->module = 'billing';

        $username          = $this->moduleConfig->system->webpay->username;
        $md5_password      = $this->moduleConfig->system->webpay->md5_password;
        $payment_check_url = $this->moduleConfig->system->webpay->payment_check_url;
        $secret_key        = $this->moduleConfig->system->webpay->secret_key;

        $post_data = '*API=&API_XML_REQUEST='.urlencode("<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>
            <wsb_api_request>
                <command>get_transaction</command>
                <authorization>
                    <username>{$username}</username>
                    <password>{$md5_password}</password>
                </authorization>
                <fields>
                    <transaction_id>{$transaction_id}</transaction_id>
                </fields>
            </wsb_api_request>
        ");
        $curl = curl_init($payment_check_url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $response = curl_exec($curl);
        curl_close($curl);

        $transaction = new SimpleXMLElement($response);

        if ($transaction === false) {
            throw new Exception('error load transaction data');
        }

        $data                    = array();
        $data['transaction_id']  = (string)$transaction->fields->transaction_id;
        $data['batch_timestamp'] = (string)$transaction->fields->batch_timestamp;
        $data['currency_id']     = (string)$transaction->fields->currency_id;
        $data['amount']          = (string)$transaction->fields->amount;
        $data['payment_method']  = (string)$transaction->fields->payment_method;
        $data['payment_type']    = (string)$transaction->fields->payment_type;
        $data['order_id']        = (string)$transaction->fields->order_id;
        $data['rrn']             = (string)$transaction->fields->rrn;

        $signature = md5(implode('', $data) . $secret_key);

        $data['order_num']       = (string)$transaction->fields->order_num;
        $data['wsb_signature']   = (string)$transaction->fields->wsb_signature;

        if ($signature != $data['wsb_signature']) {
            throw new Exception('transaction data is not valid: signature not equal');
        }

        return $data;
    }


    /**
     * @param array $data
     * @throws Exception
     */
    private function checkTransactionDataNotify($data) {
	$this->module = 'billing';
        $secret_key = $this->moduleConfig->system->webpay->secret_key;

        $signature_data = array();
        $signature_data['batch_timestamp'] = $data['batch_timestamp'];
        $signature_data['currency_id']     = $data['currency_id'];
        $signature_data['amount']          = $data['amount'];
        $signature_data['payment_method']  = $data['payment_method'];
        $signature_data['order_id']        = $data['order_id'];
        $signature_data['site_order_id']   = $data['site_order_id'];
        $signature_data['transaction_id']  = $data['transaction_id'];
        $signature_data['payment_type']    = $data['payment_type'];
        $signature_data['rrn']             = $data['rrn'];
//        $signature_data['rc']             = $data['rc'];

        $signature = md5(implode('', $signature_data) . $secret_key);

        if ($signature != $data['wsb_signature']) {
            throw new Exception('transaction data is not valid: signature not equal');
        }
    }


    /**
     * @param string    $order_num
     * @param float|int $price
     * @return array
     * @throws Exception
     */
    private function sign($order_num, $price) {

        $this->module = 'billing';
        $webpay = $this->moduleConfig->system->webpay->toArray();

        $seed           = time();
        $tax            = isset($webpay['options']['wsb_tax'])            ? $webpay['options']['wsb_tax']            : 0;
        $shipping_price = isset($webpay['options']['wsb_shipping_price']) ? $webpay['options']['wsb_shipping_price'] : 0;
        $discount_price = isset($webpay['options']['wsb_discount_price']) ? $webpay['options']['wsb_discount_price'] : 0;
        $test           = isset($webpay['options']['wsb_test'])           ? $webpay['options']['wsb_test']           : '';
        $storeid        = isset($webpay['options']['wsb_storeid'])        ? $webpay['options']['wsb_storeid']        : '';
        $currency_id    = isset($webpay['options']['wsb_currency_id'])    ? $webpay['options']['wsb_currency_id']    : '';
        $secret_key     = isset($webpay['secret_key'])                    ? $webpay['secret_key']                    : '';

        $total     = round(($price + $tax + $shipping_price) - $discount_price, 2);
        $signature = sha1($seed . $storeid . $order_num . $test . $currency_id . $total . $secret_key);

        return array(
            'total'     => $total,
            'seed'      => $seed,
            'order_num' => $order_num,
            'signature' => $signature,
        );
    }


    /**
     * @return string
     */
    private function getApikey() {

        return $this->db->fetchOne("
            SELECT apikey
            FROM mod_webservice_apikeys
            WHERE name = 'billing_key'
              AND is_active_sw = 'Y'
            LIMIT 1
        ");
    }
}