<?php
namespace Core2;

use Zend\Session\Container as SessionContainer;


require_once 'Templater3.php';
require_once 'Tool.php';


/**
 * Class Login
 * @package Core2
 * @property \Zend_Config_Ini $core_config
 * @property \Users           $dataUsers
 */
class Login extends Db {

    private $system_name = '';
    private $favicon     = [];


    /**
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     * @throws \Zend_Exception
     * @throws \Exception
     */
    public function dispatch() {

        if (isset($_GET['core']) && $this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration && $this->core_config->registration->on) {

                if ($_GET['core'] == 'registration') {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        return $this->getRegistration();

                    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $data = [
                            'company_name' => isset($_POST['company_name']) ? $_POST['company_name'] : '',
                            'email'        => isset($_POST['email']) ? $_POST['email'] : '',
                            'unp'          => isset($_POST['unp']) ? $_POST['unp'] : '',
                            'tel'          => isset($_POST['tel']) ? $_POST['tel'] : '',
                        ];

                        return $this->registration($data);

                    } else {
                        http_response_code(404);
                        return '';
                    }
                }

                if ($_GET['core'] == 'registration_complete') {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        if (empty($_GET['key'])){
                            http_response_code(404);
                            return '';
                        }
                        return $this->getRegistrationComplete($_GET['key']);

                    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (empty($_POST['key'])){
                            http_response_code(404);
                            return '';
                        }
                        if (empty($_POST['password'])) {
                            return json_encode([
                                'status'  => 'error',
                                'message' => $this->_('Заполните пароль')
                            ]);
                        }

                        return $this->setUserPass($_POST['key'], $_POST['password']);

                    } else {
                        http_response_code(404);
                        return '';
                    }
                }
            }

            if ($this->core_config->restore && $this->core_config->restore->on) {
                if ($_GET['core'] == 'restore') {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

                        if ( ! empty($_GET['key'])) {
                            return $this->restoreComplete($_GET['key']);

                        } else {
                            return $this->getRestore();
                        }

                    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (empty($_POST['email'])) {
                            return json_encode([
                                'status'  => 'error',
                                'message' => $this->_('Заполните email')
                            ]);
                        }

                        return $this->restore($_POST["email"]);

                    } else {
                        http_response_code(404);
                        return '';
                    }
                }

                if ($_GET['core'] == 'restore_complete') {
                    if (empty($_POST['key'])){
                        http_response_code(404);
                        return '';
                    }
                    if (empty($_POST['password'])) {
                        return json_encode([
                            'status'  => 'error',
                            'message' => $this->_('Заполните пароль')
                        ]);
                    }
                    return $this->setUserRestorePass($_POST['key'], $_POST['password']);
                }
            }
        }

        // GET LOGIN PAGE
        if (array_key_exists('X-Requested-With', \Tool::getRequestHeaders())) {
            if ( ! empty($_POST['xjxr'])) {
                throw new \Exception('expired');
            }
            if ( ! empty($_GET['module'])) {
                http_response_code(403);
                return '';
            }
        }

        return $this->getLogin();
    }


    /**
     * @param $system_name
     * @throws \Exception
     */
    public function setSystemName($system_name) {

        if ( ! is_scalar($system_name)) {
            throw new \Exception('Incorrect system name');
        }

        $this->system_name = $system_name;
    }


    /**
     * @param $favicon
     * @throws \Exception
     */
    public function setFavicon($favicon) {

        if ( ! is_array($favicon)) {
            throw new \Exception('Incorrect favicon data');
        }

        $this->favicon = $favicon;
    }


    /**
     * Форма входа в систему
     * @return string
     * @throws \Zend_Exception
     * @throws \Exception
     */
    private function getLogin() {

        if (isset($_POST['action'])) {
            require_once 'core2/inc/CoreController.php';
            $this->setContext('admin');
            $core = new \CoreController();

            $url  = "index.php";
            if ($core->action_login($_POST) && ! empty($_SERVER['QUERY_STRING'])) {
                $url .= "#" . $_SERVER['QUERY_STRING'];
            }

            header("Location: $url");
            return '';
        }

        $tpl = new \Templater3("core2/html/" . THEME . "/login/login.html");

        $errorNamespace = new SessionContainer('Error');
        $blockNamespace = new SessionContainer('Block');

        if ( ! empty($blockNamespace->blocked)) {
            $tpl->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
            $tpl->assign('[ERROR_LOGIN]', '');
        } elseif ( ! empty($errorNamespace->ERROR)) {
            $tpl->error->assign('[ERROR_MSG]', $errorNamespace->ERROR);
            $tpl->assign('[ERROR_LOGIN]', $errorNamespace->TMPLOGIN);
            $errorNamespace->ERROR = '';
        } else {
            $tpl->error->assign('[ERROR_MSG]', '');
            $tpl->assign('[ERROR_LOGIN]', '');
        }

        if (empty($this->config->ldap->active) || ! $this->config->ldap->active) {
            $tpl->assign('<form', "<form onsubmit=\"document.getElementById('gfhjkm').value=hex_md5(document.getElementById('gfhjkm').value)\"");
        }

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $reg  = \Zend_Registry::getInstance();
        $auth = $reg->get('auth');

        if ( ! empty($auth->TOKEN)) {
            $tpl->assign('name="action"', 'name="action" value="' . $auth->TOKEN . '"');
        }

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration && $this->core_config->registration->on) {
                $tpl->ext_actions->touchBlock('registration');
            }

            if ($this->core_config->restore && $this->core_config->restore->on) {
                $tpl->ext_actions->touchBlock('restore');
            }
        }


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * Форма регистрации
     * @return string
     * @throws \Exception
     */
    private function getRegistration() {

        $tpl  = new \Templater3("core2/html/" . THEME . "/login/registration.html");
        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->restore && $this->core_config->restore->on) {
                $tpl->touchBlock('restore');
            }
        }


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * @param $data
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     */
    private function registration(array $data) {

        if (empty($data['email'])) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Email не заполнен')
            ]);
        }

        if (empty($data['unp'])) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('УНП не заполнено')
            ]);
        }

        if (empty($data['company_name'])) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Название организации не заполнено')
            ]);
        }

        if (empty($data['tel'])) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Телефон не заполнен')
            ]);
        }

        $data['email'] = trim($data['email']);

        $isset_user_login = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE u_login = ?
        ", $data['email']);


        if ($isset_user_login) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Такой пользователь уже есть')
            ]);
        }

        $isset_user_email = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE email = ?
        ", $data['email']);

        if ($isset_user_email) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Пользователь с таким Email уже есть')
            ]);
        }

        $isset_unp = $this->db->fetchOne("
            SELECT 1
            FROM mod_ordering_contractors
            WHERE unp = ?
              AND is_deleted_sw = 'N' 
        ", $data['unp']);

        if ($isset_unp) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Организация с таким УНП уже есть')
            ]);
        }


        $contractor = $this->db->fetchRow("
            SELECT id,
                   email,
                   active_sw
            FROM mod_ordering_contractors
            WHERE email = ?
        ", $data['email']);

        if ( ! empty($contractor)) {
            if ($contractor['active_sw'] == 'N') {
                $reg_key = \Tool::pass_salt(md5($data['email'] . microtime()));
                $where   = $this->db->quoteInto('id = ?', $contractor['id']);
                $this->db->update('mod_ordering_contractors', [
                    'reg_key'      => $reg_key,
                    'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
                ], $where);

                $this->sendEmailRegistration($data['email'], $reg_key);

                return json_encode([
                    'status'  => 'success',
                    'message' => $this->_('На указанную вами почту отправлены данные для входа в систему')
                ]);

            } else {
                return json_encode([
                    'status'  => 'error',
                    'message' => $this->_('Организация с таким Email уже есть')
                ]);
            }
        }

        $reg_key = \Tool::pass_salt(md5($data['email'] . microtime()));
        $this->db->insert('mod_ordering_contractors', [
            'title'        => $data['company_name'],
            'email'        => $data['email'],
            'unp'          => $data['unp'],
            'phone'        => $data['tel'],
            'reg_key'      => $reg_key,
            'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
        ]);

        $this->sendEmailRegistration($data['email'], $reg_key);

        return json_encode([
            'status'  => 'success',
            'message' => $this->_('На указанную вами почту отправлены данные для входа в систему')
        ]);
    }


    /**
     * @param $mail_address
     * @param $reg_key
     */
    private function sendEmailRegistration($mail_address, $reg_key) {

        $protocol = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';
        $host     = ! empty($this->config->system) ? $this->config->system->host : '';
        $doc_path = rtrim(DOC_PATH, '/') . '/';

        $content_email = "
            Вы зарегистрированы на сервисе {$host}<br>
            Для продолжения регистрации <b>перейдите по указанной ниже ссылке</b>.<br><br>
            <a href=\"{$protocol}://{$host}{$doc_path}index.php?core=registration_complete&key={$reg_key}\" 
               style=\"font-size: 16px\">{$protocol}://{$host}{$doc_path}index.php?core=registration_complete&key={$reg_key}</a>
        ";

        $reg = \Zend_Registry::getInstance();
        $reg->set('context', ['queue', 'index']);

        require_once 'Email.php';
        $email = new \Core2\Email();
        $email->to($mail_address)
            ->subject("Автопромсервис: Регистрация на сервисе")
            ->body($content_email)
            ->send(true);
    }


    /**
     * @param $key
     * @return string|string[]
     * @throws \Zend_Db_Exception
     * @throws \Exception
     */
    private function getRegistrationComplete($key) {

        $tpl  = new \Templater3("core2/html/" . THEME . "/login/registration-complete.html");
        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $isset_key = $this->db->fetchOne("
            SELECT 1
            FROM mod_ordering_contractors
            WHERE reg_key = ?
              AND date_expired > NOW()
        ", $key);

        $error_message = '';

        if ($isset_key) {
            $tpl->pass->assign('[KEY]', $key);
        } else {
            $error_message = $this->_('Ссылка устарела');
        }

        $tpl->assign('[ERROR_MSG]', $error_message);

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->restore && $this->core_config->restore->on) {
                $tpl->touchBlock('restore');
            }
        }

        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * @param $key
     * @param $password
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     */
    private function setUserPass($key, $password) {

        $user_info = $this->db->fetchRow("
            SELECT id,
                   user_id,
                   email,
                   title
            FROM mod_ordering_contractors 
            WHERE reg_key = ?
              AND date_expired > NOW()
            LIMIT 1
        ", $key);

        if (empty($user_info)) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Ссылка устарела')
            ]);
        }

        $this->db->insert('core_users', [
            'visible'     => 'N',
            'is_admin_sw' => 'N',
            'u_login'     => trim($user_info['email']),
            'u_pass'      => \Tool::pass_salt($password),
            'email'       => trim($user_info['email']),
            'date_added'  => new \Zend_Db_Expr('NOW()'),
            'role_id'     => $this->config->registry->role
        ]);
        $user_id = $this->db->lastInsertId();

        $this->db->insert('core_users_profile', [
            'user_id'   => $user_id,
            'firstname' => $user_info['title'],
        ]);

        $where = $this->db->quoteInto('id = ?', $user_info['id']);
        $this->db->update('mod_ordering_contractors', [
            'reg_key'      => new \Zend_Db_Expr('NULL'),
            'date_expired' => new \Zend_Db_Expr('NULL'),
            'user_id'      => $user_id,
        ], $where);

        return json_encode([
            'status'  => 'success',
            'message' => '<h4>Готово!</h4>
                          <p>Вы сможете зайти в систему, после прохождения модерации</p>'
        ]);
    }


    /**
     * @param $key
     * @param $password
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     */
    private function setUserRestorePass($key, $password) {

        $user_info = $this->db->fetchRow("
            SELECT id,
                   user_id
            FROM mod_ordering_contractors 
            WHERE reg_key = ?
              AND date_expired > NOW()
            LIMIT 1
        ", $key);

        if (empty($user_info)) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Ссылка устарела')
            ]);
        }

        $where = $this->db->quoteInto('id = ?', $user_info['id']);
        $this->db->update('mod_ordering_contractors', [
            'u_pass'       => \Tool::pass_salt($password),
            'reg_key'      => new \Zend_Db_Expr('NULL'),
            'date_expired' => new \Zend_Db_Expr('NULL'),
        ], $where);

        if ( ! empty($user_info['user_id'])) {
            $where = $this->db->quoteInto('u_id = ?', $user_info['user_id']);
            $this->db->update('core_users', [
                'u_pass' => \Tool::pass_salt($password),
            ], $where);
        }

        return json_encode([
            "status"  => "success",
            "message" => "<h4>Пароль изменен!</h4>
                          <p>Вернитесь на форму входа и войдите в систему с новым паролем</p>"
        ]);
    }


    /**
     * @return string|string[]
     * @throws \Exception
     */
    private function getRestore() {

        $tpl = new \Templater3("core2/html/" . THEME . "/login/restore.html");

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration && $this->core_config->registration->on) {
                $tpl->touchBlock('registration');
            }
        }

        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * @param $email
     * @return false|string|string[]
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     */
    private function restore($email) {

        $user_id = $this->db->fetchOne("
            SELECT u.u_id
            FROM core_users AS u
                JOIN mod_ordering_contractors AS oc ON u.u_id = oc.user_id
            WHERE u.email = ?
            LIMIT 1
        ", $email);

        if (empty($user_id)) {
            return json_encode([
                'status'  => 'error',
                'message' => $this->_('Пользователя с таким Email нет в системе')
            ]);
        }


        $reg_key = \Tool::pass_salt(md5($email . microtime()));
        $where   = $this->db->quoteInto('user_id = ?', $user_id);
        $this->db->update('mod_ordering_contractors', [
            'reg_key'      => $reg_key,
            'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
        ], $where);


        $protocol = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';
        $host     = ! empty($this->config->system) ? $this->config->system->host : '';
        $doc_path = rtrim(DOC_PATH, '/') . '/';

        $content_email = "
            Вы запросили смену пароля на сервисе {$host}<br>
            Для продолжения <b>перейдите по указанной ниже ссылке</b>.<br><br>

            <a href=\"{$protocol}://{$host}{$doc_path}index.php?core=restore&key={$reg_key}\" 
               style=\"font-size: 16px\">{$protocol}://{$host}{$doc_path}index.php?core=restore&key={$reg_key}</a>
        ";

        $this->setContext('queue');

        require_once 'Email.php';
        $core_email = new \Core2\Email();
        $core_email->to($email)
            ->subject("Автопромсервис: Восстановление пароля")
            ->body($content_email)
            ->send(true);

        return json_encode([
            'status'  => 'success',
            'message' => $this->_('На указанную вами почту отправлены данные для смены пароля')
        ]);
    }


    /**
     * @param $key
     * @return string|string[]
     * @throws \Exception
     */
    private function restoreComplete($key) {

        $tpl = new \Templater3("core2/html/" . THEME . "/login/restore-complete.html");

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $isset_key = $this->db->fetchOne("
            SELECT 1
            FROM mod_ordering_contractors
            WHERE reg_key = ?
              AND date_expired > NOW()
        ", $key);

        $error_message = '';

        if ($isset_key) {
            $tpl->pass->assign('[KEY]', $key);
        } else {
            $error_message = $this->_('Ссылка устарела');
        }

        $tpl->assign('[ERROR_MSG]', $error_message);

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration && $this->core_config->registration->on) {
                $tpl->touchBlock('registration');
            }
        }


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * Установка контекста выполнения скрипта
     * @param string $module
     * @param string $action
     */
    private function setContext($module, $action = 'index') {
        \Zend_Registry::set('context', [$module, $action]);
    }


    /**
     * Получение логотипа системы из conf.ini
     * или установка логотипа по умолчанию
     * @return string
     */
    private function getSystemLogo() {

        $res = $this->config->system->logo;

        if ( ! empty($res) && is_file($res)) {
            return $res;
        } else {
            return 'core2/html/' . THEME . '/img/logo.gif';
        }
    }


    /**
     * @return string
     * @throws \Exception
     */
    private function getIndex() {

        $tpl = new \Templater3();

        if (\Tool::isMobileBrowser()) {
            $tpl->loadTemplate("core2/html/" . THEME . "/login/indexMobile.html");
        } else {
            $tpl->loadTemplate("core2/html/" . THEME . "/login/index.html");
        }

        $tpl->assign('{system_name}', $this->system_name);

        $tpl->assign('favicon.png', isset($this->favicon['png']) && is_file($this->favicon['png']) ? $this->favicon['png'] : '');
        $tpl->assign('favicon.ico', isset($this->favicon['ico']) && is_file($this->favicon['ico']) ? $this->favicon['ico'] : '');

        return $tpl->render();
    }
}