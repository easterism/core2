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

        if (isset($_GET['core'])) {
            if ($this->config->mail && $this->config->mail->server) {
                if ($this->core_config->registration &&
                    $this->core_config->registration->on &&
                    $this->core_config->registration->role_id
                ) {

                    if ($_GET['core'] == 'registration') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            return $this->getPageRegistration();

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            return $this->registration($_POST);

                        } else {
                            http_response_code(404);

                            return '';
                        }
                    }

                    if ($_GET['core'] == 'registration_complete') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            if (empty($_GET['key'])) {
                                http_response_code(404);
                                return '';
                            }

                            return $this->getPageRegistrationComplete($_GET['key']);

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            if (empty($_POST['key'])) {
                                http_response_code(404);
                                return '';
                            }
                            if (empty($_POST['password'])) {
                                return json_encode([
                                    'status'        => 'error',
                                    'error_message' => $this->_('Заполните пароль')
                                ]);
                            }

                            return $this->registrationComplete($_POST['key'], $_POST['password']);

                        } else {
                            http_response_code(404);
                            return '';
                        }
                    }
                }

                if ($this->core_config->restore && $this->core_config->restore->on) {
                    if ($_GET['core'] == 'restore') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            return $this->getPageRestore();

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            if (empty($_POST['email'])) {
                                return json_encode([
                                    'status'        => 'error',
                                    'error_message' => $this->_('Заполните email')
                                ]);
                            }

                            return $this->restore($_POST["email"]);

                        } else {
                            http_response_code(404);
                            return '';
                        }
                    }


                    if ($_GET['core'] == 'restore_complete') {
                        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                            if (empty($_GET['key'])) {
                                http_response_code(404);
                                return '';
                            }

                            return $this->getPageRestoreComplete($_GET['key']);

                        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            if (empty($_POST['key'])) {
                                http_response_code(404);
                                return '';
                            }

                            if (empty($_POST['password'])) {
                                return json_encode([
                                    'status'        => 'error',
                                    'error_message' => $this->_('Заполните пароль')
                                ]);
                            }

                            return $this->restoreComplete($_POST['key'], $_POST['password']);
                        }
                    }
                }
            }


            if ($_GET['core'] == 'login') {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    if (empty($_POST['login'])) {
                        return json_encode([
                            'status'  => 'error',
                            'error_message' => $this->_('Заполните логин')
                        ]);
                    }

                    if (empty($_POST['password'])) {
                        return json_encode([
                            'status'  => 'error',
                            'error_message' => $this->_('Заполните пароль')
                        ]);
                    }

                    return $this->login($_POST["login"], $_POST['password']);
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

        return $this->getPageLogin();
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
    private function getPageLogin() {

        $tpl = new \Templater2("core2/html/" . THEME . "/login/login.html");

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration &&
                $this->core_config->registration->on &&
                $this->core_config->registration->role_id
            ) {
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
    private function getPageRegistration() {

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

        $isset_phone = false;

        if ($this->core_config->registration->fields) {
            $fields = $this->core_config->registration->fields->toArray();

            if ( ! empty($fields)) {
                foreach ($fields as $name => $field) {

                    $type = ! empty($field['type']) ? htmlspecialchars($field['type']) : 'text';

                    if ($type == 'phone') {
                        $isset_phone = true;
                    }

                    $tpl->field->assign('[NAME]',     $name);
                    $tpl->field->assign('[TYPE]',     $type);
                    $tpl->field->assign('[TITLE]',    ! empty($field['title']) ? htmlspecialchars($field['title']) : '');
                    $tpl->field->assign('[REQUIRED]', ! empty($field['required']) ? 'required' : '');
                    $tpl->field->reassign();
                }
            }
        }


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);


        if ($isset_phone) {
            $scripts = [
                '<script src="core2/js/cleave.min.js"></script>',
                '<script src="core2/js/cleave-phone.i18n.js"></script>',
            ];
            $html = str_replace('<!--system_js-->', implode('', $scripts), $html);
        }

        return $html;
    }


    /**
     * @param $key
     * @return string|string[]
     * @throws \Zend_Db_Exception
     * @throws \Exception
     */
    private function getPageRegistrationComplete($key) {

        $tpl  = new \Templater3("core2/html/" . THEME . "/login/registration-complete.html");
        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $isset_key = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE reg_key = ?
              AND date_expired > NOW()
              AND visible = 'N'
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
     * @return string|string[]
     * @throws \Exception
     */
    private function getPageRestore() {

        $tpl = new \Templater3("core2/html/" . THEME . "/login/restore.html");

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        if ($this->config->mail && $this->config->mail->server) {
            if ($this->core_config->registration &&
                $this->core_config->registration->on &&
                $this->core_config->registration->role_id
            ) {
                $tpl->touchBlock('registration');
            }
        }

        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * @param $key
     * @return string|string[]
     * @throws \Exception
     */
    private function getPageRestoreComplete($key) {

        $tpl = new \Templater3("core2/html/" . THEME . "/login/restore-complete.html");

        $logo = $this->getSystemLogo();

        if (is_file($logo)) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $isset_key = $this->db->fetchOne("
            SELECT 1
            FROM core_users
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
            if ($this->core_config->registration &&
                $this->core_config->registration->on &&
                $this->core_config->registration->role_id
            ) {
                $tpl->touchBlock('registration');
            }
        }


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * Авторизация пользователя через форму
     * @param $login
     * @param $password
     * @return string
     */
    private function login($login, $password) {

        $blockNamespace = new SessionContainer('Block');

        try {

            if ( ! empty($blockNamespace->blocked)) {
                throw new \Exception($this->translate->tr("Ваш доступ временно заблокирован!"));
            }


            $login = trim($login);


            if ($this->config->ldap &&
                $this->config->ldap->active &&
                ((function_exists('ctype_print') ? ! ctype_print($password) : true) || strlen($password) < 30)
            ) {
                throw new \Exception($this->translate->tr("Ошибка пароля!"), 400);
            }


            $this->getConnection($this->config->database);


            $auth         = [];
            $is_ldap_auth = false;

            if ($login === 'root') {
                $auth = $this->getAuthRoot();

            } else {
                // LDAP
                if ($this->config->ldap && $this->config->ldap->active) {
                    $auth = $this->getAuthLdap($login, $password);

                    if ( ! empty($auth)) {
                        $is_ldap_auth = true;

                    } else {
                        $password = md5($password);
                    }
                }

                // LOGIN
                if (empty($auth)) {
                    $auth = $this->getAuthLogin($login);
                }
            }

            if ( ! $auth) {
                throw new \Exception($this->translate->tr("Нет такого пользователя"));
            }


            $auth['LDAP'] = $is_ldap_auth;

            $md5_pass = \Tool::pass_salt($password);


            if ($auth['LDAP']) {
                $auth['u_pass'] = $md5_pass;
            }

            if ($auth['u_pass'] !== $md5_pass) {
                throw new \Exception($this->translate->tr("Неверный пароль"));
            }

            $authNamespace = new SessionContainer('Auth');
            $authNamespace->accept_answer = true;

            $session_life = $this->db->fetchOne("
                SELECT value 
                FROM core_settings 
                WHERE visible = 'Y' 
                  AND code = 'session_lifetime' 
                LIMIT 1
            ");

            if ($session_life) {
                $authNamespace->setExpirationSeconds($session_life, "accept_answer");
            }

            if (session_id() == 'deleted') {
                throw new \Exception($this->translate->tr("Ошибка сохранения сессии. Проверьте настройки системного времени."));
            }

            $authNamespace->ID    = (int)$auth['u_id'];
            $authNamespace->NAME  = $auth['u_login'];
            $authNamespace->EMAIL = $auth['email'];

            if ($auth['u_login'] == 'root') {
                $authNamespace->ADMIN  = true;
                $authNamespace->ROLEID = 0;
            } else {
                $authNamespace->LN     = $auth['lastname'];
                $authNamespace->FN     = $auth['firstname'];
                $authNamespace->MN     = $auth['middlename'];
                $authNamespace->ADMIN  = $auth['is_admin_sw'] == 'Y';
                $authNamespace->ROLE   = $auth['role'] ? $auth['role'] : -1;
                $authNamespace->ROLEID = $auth['role_id'] ? $auth['role_id'] : 0;
                $authNamespace->LIVEID = $this->storeSession($authNamespace);
            }

            $authNamespace->LDAP = $auth['LDAP'];


            //регенерация сессии для предотвращения угона
            if ( ! ($authNamespace->init)) {
                $authNamespace->getManager()->regenerateId();
                $authNamespace->init = true;
            }


            return json_encode([
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            $code = $e->getCode() > 200 && $e->getCode() < 600 ? $e->getCode() : 403;
            http_response_code($code);

            if (isset($blockNamespace->numberOfPageRequests)) {
                $blockNamespace->numberOfPageRequests++;
            } else {
                $blockNamespace->numberOfPageRequests = 1;
            }

            if ($blockNamespace->numberOfPageRequests > 5) {
                $blockNamespace->blocked = time();
                $blockNamespace->setExpirationSeconds(60);
                $blockNamespace->numberOfPageRequests = 1;
            }


            return json_encode([
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }


    /**
     * @param $data
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     * @throws \Exception
     */
    private function registration(array $data) {

        // Кастомная регистрация
        if ($this->core_config->registration->module) {
            $module_name = strtolower($this->core_config->registration->module);
            $location    = $this->getModuleLocation($module_name);

            $mod_controller_name = "Mod" . ucfirst($module_name) . "Controller";

            if ( ! file_exists("{$location}/{$mod_controller_name}.php")) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не найден'), $module_name));
            }

            require_once "{$location}/{$mod_controller_name}.php";


            $this->setContext($module_name);
            $mod_controller = new $mod_controller_name();
            if ( ! ($mod_controller instanceof \Registration)) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не поддерживает регистрацию'), $module_name));
            }

            try {
                $result_text = $mod_controller->coreRegistration($data);

                if ($result_text && is_string($result_text)) {
                    return json_encode([
                        'status'  => 'success',
                        'message' => $result_text,
                    ]);

                } else {
                    throw new \Exception($this->_('Не удалось завершить регистрацию. Попробуйте позже, либо свяжитесь с администратором'));
                }

            } catch (\Exception $e) {
                return json_encode([
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }


        // Стандартная регистрация
        if (empty($data['name'])) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Имя не заполнено'),
            ]);
        }

        if (empty($data['login'])) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Логин не заполнен'),
            ]);
        }

        if (empty($data['email'])) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Email не заполнен'),
            ]);
        }

        $data['name']  = trim($data['name']);
        $data['email'] = trim($data['email']);
        $data['login'] = trim($data['login']);


        $isset_user_login = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE u_login = ?
              AND visible = 'Y'
        ", $data['login']);


        if ($isset_user_login) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Пользователь с таким логином уже есть'),
            ]);
        }

        $isset_user_email = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE email = ?
              AND visible = 'Y'
        ", $data['email']);

        if ($isset_user_email) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Пользователь с таким email уже есть'),
            ]);
        }


        $user = $this->db->fetchRow("
            SELECT u_id AS id,
                   email,
                   visible AS is_active_sw
            FROM core_users
            WHERE (email = ? OR u_login = ?)
              AND visible = 'N'
        ", [
            $data['email'],
            $data['login']
        ]);

        $reg_key = md5($data['email'] . microtime());

        if ( ! empty($user)) {
            $where   = $this->db->quoteInto('u_id = ?', $user['id']);
            $this->db->update('core_users', [
                'reg_key'      => $reg_key,
                'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
            ], $where);

        } else {
            $this->db->insert('core_users', [
                'u_login'        => $data['login'],
                'email'          => $data['email'],
                'role_id'        => $this->core_config->registration->role_id,
                'visible'        => 'N',
                'is_email_wrong' => 'N',
                'reg_key'        => $reg_key,
                'date_expired'   => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
                'date_added'     => new \Zend_Db_Expr('NOW()'),
            ]);

            $user_id = $this->db->lastInsertId();

            $data['name'] = preg_replace('~[ ]{2,}~', ' ', $data['name']);

            $name_explode = explode(' ', $data['name']);
            $middlename   = ! empty($name_explode[2]) ? $name_explode[2] : '';
            $lastname     = ! empty($name_explode[1]) ? $name_explode[0] : '';
            $firstname    = $lastname ? $name_explode[1] : $name_explode[0];

            $this->db->insert('core_users_profile', [
                'user_id'    => $user_id,
                'lastname'   => $lastname,
                'firstname'  => $firstname,
                'middlename' => $middlename,
            ]);
        }


        $this->sendEmailRegistration($data['email'], $reg_key);

        return json_encode([
            'status'  => 'success',
            'message' => $this->_('На указанную вами почту отправлены данные для входа в систему')
        ]);
    }


    /**
     * @param $key
     * @param $password
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     */
    private function registrationComplete($key, $password) {

        // Кастомное завершение регистрации
        if ($this->core_config->registration->module) {
            $module_name = strtolower($this->core_config->registration->module);
            $location    = $this->getModuleLocation($module_name);

            $mod_controller_name = "Mod" . ucfirst($module_name) . "Controller";

            if ( ! file_exists("{$location}/{$mod_controller_name}.php")) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не найден'), $module_name));
            }

            require_once "{$location}/{$mod_controller_name}.php";

            $this->setContext($module_name);
            $mod_controller = new $mod_controller_name();
            if ( ! ($mod_controller instanceof \Registration)) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не поддерживает регистрацию'), $module_name));
            }

            try {
                $result_text = $mod_controller->coreRegistrationComplete($key, $password);

                if ($result_text && is_string($result_text)) {
                    return json_encode([
                        'status'  => 'success',
                        'message' => $result_text,
                    ]);

                } else {
                    throw new \Exception($this->_('Не удалось завершить регистрацию. Попробуйте позже, либо свяжитесь с администратором'));
                }

            } catch (\Exception $e) {
                return json_encode([
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }



        $user_info = $this->db->fetchRow("
            SELECT u_id AS id,
                   u_login AS login,
                   email
            FROM core_users 
            WHERE reg_key = ?
              AND date_expired > NOW()
            LIMIT 1
        ", $key);

        if (empty($user_info)) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Ссылка устарела'),
            ]);
        }

        $where = $this->db->quoteInto('u_id = ?', $user_info['id']);
        $this->db->update('core_users', [
            'visible'         => 'Y',
            'is_pass_changed' => 'Y',
            'u_pass'          => \Tool::pass_salt($password),
            'reg_key'         => new \Zend_Db_Expr('NULL'),
            'date_expired'    => new \Zend_Db_Expr('NULL'),
        ], $where);


        return json_encode([
            'status'  => 'success',
            'message' => '<h4>Готово!</h4><p>Вы можете войти в систему</p>'
        ]);
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
            WHERE u.email = ?
            LIMIT 1
        ", $email);

        if (empty($user_id)) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Пользователя с таким Email нет в системе')
            ]);
        }


        $reg_key = md5($email . microtime());
        $where   = $this->db->quoteInto('u_id = ?', $user_id);
        $this->db->update('core_users', [
            'reg_key'      => $reg_key,
            'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
        ], $where);


        $name     = $this->config->system && $this->config->system->name ? $this->config->system->name : $_SERVER['SERVER_NAME'];
        $protocol = ! empty($this->config->system) && $this->config->system->https ? 'https' : 'http';
        $host     = ! empty($this->config->system) ? $this->config->system->host : '';
        $doc_path = rtrim(DOC_PATH, '/') . '/';

        $content_email = "
            Вы запросили смену пароля на сервисе {$host}<br>
            Для продолжения <b>перейдите по указанной ниже ссылке</b>.<br><br>

            <a href=\"{$protocol}://{$host}{$doc_path}index.php?core=restore_complete&key={$reg_key}\" 
               style=\"font-size: 16px\">{$protocol}://{$host}{$doc_path}index.php?core=restore_complete&key={$reg_key}</a>
        ";

        $this->setContext('queue');

        require_once 'Email.php';
        $core_email = new \Core2\Email();
        $core_email->to($email)
            ->subject("{$name}: Восстановление пароля")
            ->body($content_email)
            ->send(true);

        return json_encode([
            'status'  => 'success',
            'message' => $this->_('На указанную вами почту отправлены данные для смены пароля')
        ]);
    }


    /**
     * @param $key
     * @param $password
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     */
    private function restoreComplete($key, $password) {

        $user_id = $this->db->fetchOne("
            SELECT u_id
            FROM core_users 
            WHERE reg_key = ?
              AND date_expired > NOW()
            LIMIT 1
        ", $key);

        if (empty($user_id)) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Ссылка устарела')
            ]);
        }

        $where = $this->db->quoteInto('u_id = ?', $user_id);
        $this->db->update('core_users', [
            'u_pass'       => \Tool::pass_salt($password),
            'reg_key'      => new \Zend_Db_Expr('NULL'),
            'date_expired' => new \Zend_Db_Expr('NULL'),
        ], $where);


        return json_encode([
            "status"  => "success",
            "message" => "<h4>Пароль изменен!</h4>
                          <p>Вернитесь на форму входа и войдите в систему с новым паролем</p>"
        ]);
    }


    /**
     * @param $mail_address
     * @param $reg_key
     */
    private function sendEmailRegistration($mail_address, $reg_key) {

        $name     = $this->config->system && $this->config->system->name ? $this->config->system->name : $_SERVER['SERVER_NAME'];
        $protocol = $this->config->system && $this->config->system->https ? 'https' : 'http';
        $host     = $this->config->system ? $this->config->system->host : '';
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
            ->subject("{$name}: Регистрация на сервисе")
            ->body($content_email)
            ->send(true);
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
     * Получение данных дя пользователя root
     * @return array
     */
    private final function getAuthRoot() {

        require_once __DIR__ . '/../CoreController.php';

        $auth            = [];
        $auth['u_pass']  = \CoreController::RP;
        $auth['u_id']    = -1;
        $auth['u_login'] = 'root';
        $auth['email']   = 'easter.by@gmail.com';

        return $auth;
    }


    /**
     * @param $login
     * @param $password
     * @return array
     * @throws \Exception
     */
    private function getAuthLdap($login, $password) {

        require_once 'LdapAuth.php';

        $ldapAuth = new \LdapAuth();
        $ldapAuth->auth($login, $password);
        $ldapStatus = $ldapAuth->getStatus();

        switch ($ldapStatus) {
            case \LdapAuth::ST_LDAP_AUTH_SUCCESS :
                $userData = $ldapAuth->getUserData();
                $login    = $userData['login'];

                if (isset($userData['root']) && $userData['root'] === true) {
                    return $this->getAuthRoot();
                }

                $user_id = $this->dataUsers->fetchRow($this->db->quoteInto("u_login = ?", $login))->u_id;
                if ( ! $user_id) {
                    //create new user
                    $this->db->insert('core_users', [
                        'visible'     => 'Y',
                        'is_admin_sw' => $userData['admin'] ? 'Y' : 'N',
                        'u_login'     => $login,
                        'role_id'     => $this->config->ldap->role_id ?: new \Zend_Db_Expr('NULL'),
                        'date_added'  => new \Zend_Db_Expr('NOW()'),
                    ]);

                } elseif ($userData['admin']) {
                    $this->db->update('core_users', ['is_admin_sw' => 'Y'], $this->db->quoteInto('u_id = ?', $user_id));
                }

                return $this->getAuthLogin($login);
                break;

            case \LdapAuth::ST_LDAP_USER_NOT_FOUND :
                //удаляем пользователя если его нету в AD и с префиксом LDAP_%
                //$this->db->query("DELETE FROM core_users WHERE u_login = ?", $login);
                break;

            case \LdapAuth::ST_LDAP_INVALID_PASSWORD :
                throw new \Exception($this->translate->tr("Неверный пароль или пользователь отключён"));
                break;

            case \LdapAuth::ST_ERROR :
                throw new \Exception($this->translate->tr("Ошибка LDAP: ") . $ldapAuth->getMessage());
                break;

            default:
                throw new \Exception($this->translate->tr("Неизвестная ошибка авторизации по LDAP"));
                break;
        }

        return [];
    }


    /**
     * @param $login
     * @return mixed
     */
    private function getAuthLogin($login) {

        return $this->dataUsers->getUserByLogin($login);
    }


    /**
     * Сохранение информации о входе пользователя
     * @param SessionContainer $auth
     * @return mixed
     * @throws \Exception
     */
    private function storeSession(SessionContainer $auth) {

        if ($auth && $auth->ID && $auth->ID > 0) {

            $sid = $auth->getManager()->getId();
            $sess = $this->dataSession;
            $row = $sess->fetchRow($sess->select()
                ->where("logout_time IS NULL AND user_id = ?", $auth->ID)
                ->where("sid = ?", $sid)
                ->where("ip = ?", $_SERVER['REMOTE_ADDR'])
                ->limit(1));

            if ( ! $row) {
                $row             = $sess->createRow();
                $row->sid        = $sid;
                $row->login_time = new \Zend_Db_Expr('NOW()');
                $row->user_id    = $auth->ID;
                $row->ip         = $_SERVER['REMOTE_ADDR'];
                $row->save();
            }

            if ( ! $row->id) {
                throw new \Exception($this->translate->tr("Не удалось сохранить данные сессии"));
            }

            return $row->id;
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