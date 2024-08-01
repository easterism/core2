<?php
namespace Core2;

require_once 'Templater3.php';
require_once 'Tool.php';

use Laminas\Session\Container as SessionContainer;

/**
 * @property Model\Users        $dataUsers
 * @property \ModAuthController $modAuth
 */
class Login extends \Common {

    private $system_name = '';
    private $favicon     = [];


    /**
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     * @throws \Zend_Exception
     * @throws \Exception
     */
    public function dispatch(Array $request) {

        //-------------регистрация, аутентификация через форму------------------

        if (isset($request['core'])) {
            if ($this->config->mail && $this->config->mail->server) {

                if ($this->isModuleInstalled('auth')) {
                    $auth_config = $this->modAuth->moduleConfig->auth;
                    $reg_config = $this->modAuth->moduleConfig->registration;
                    $restore_config = $this->modAuth->moduleConfig->restore;

                    if ($reg_config &&
                        $reg_config->on &&
                        $reg_config->role_id
                    ) {

                        if ($request['core'] == 'registration') {
                            $fields = $reg_config->fields ? $reg_config->fields->toArray() : [];

                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                return $this->getPageRegistration($fields);

                            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                return $this->registration($fields, $_POST, $reg_config->role_id);

                            } else {
                                http_response_code(404);

                                return '';
                            }
                        }

                        if ($request['core'] == 'registration_complete') {
                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                if (empty($request['key'])) {
                                    http_response_code(404);
                                    return '';
                                }

                                return $this->getPageRegistrationComplete($request['key']);

                            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                if (empty($_POST['key'])) {
                                    http_response_code(404);
                                    return '';
                                }
                                if (empty($_POST['password'])) {
                                    return json_encode([
                                        'status' => 'error',
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

                    if ($restore_config && $restore_config->on) {
                        if ($request['core'] == 'restore') {
                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                return $this->getPageRestore();

                            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                if (empty($_POST['email'])) {
                                    return json_encode([
                                        'status' => 'error',
                                        'error_message' => $this->_('Заполните email')
                                    ]);
                                }

                                return $this->restore($_POST["email"]);

                            } else {
                                http_response_code(404);
                                return '';
                            }
                        }


                        if ($request['core'] == 'restore_complete') {
                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                if (empty($request['key'])) {
                                    http_response_code(404);
                                    return '';
                                }

                                return $this->getPageRestoreComplete($request['key']);

                            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                if (empty($_POST['key'])) {
                                    http_response_code(404);
                                    return '';
                                }

                                if (empty($_POST['password'])) {
                                    return json_encode([
                                        'status' => 'error',
                                        'error_message' => $this->_('Заполните пароль')
                                    ]);
                                }

                                return $this->restoreComplete($_POST['key'], $_POST['password']);
                            }
                        }
                    }
                }
            }


            if ($this->core_config->auth &&
                $this->core_config->auth->module &&
                $this->core_config->auth->social &&
                $_SERVER['REQUEST_METHOD'] === 'POST' &&
                in_array($request['core'], ['auth_vk', 'auth_ok', 'auth_fb'])
            ) {
                //---------авторизуемся с помощью соцсетей------
                //TODO перенести в модуль Auth
                try {
                    $code = $_POST['code'] ?? '';

                    if ( ! is_string($code)) {
                        throw new \Exception($this->_('Некорректный запрос'));
                    }

                    switch ($request['core']) {
                        case 'auth_vk':
                            if ($this->core_config->auth->social->vk &&
                                $this->core_config->auth->social->vk->on
                            ) {
                                $this->authVk($code);
                            }
                            break;

                        case 'auth_ok':
                            if ($this->core_config->auth->social->ok &&
                                $this->core_config->auth->social->ok->on
                            ) {
                                $this->authOk($code);
                            }
                            break;

                        case 'auth_fb':
                            if ($this->core_config->auth->social->fb &&
                                $this->core_config->auth->social->fb->on
                            ) {
                                $this->authFb($code);
                            }
                            break;
                    }

                    return json_encode([
                        'status' => 'success'
                    ]);

                } catch (\Exception $e) {
                    return json_encode([
                        'error_message' => $e->getMessage()
                    ]);
                }
            }


            if ($request['core'] == 'login') {
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

                    try {
                        $this->authLoginPassword($_POST["login"], $_POST['password']);

                        return json_encode([
                            'status' => 'success'
                        ]);

                    } catch (\Exception $e) {
                        return json_encode([
                            'status'        => 'error',
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // GET LOGIN PAGE
        if ( ! empty($_POST['xjxr'])) {
            throw new \Exception('expired');
        }
        if (array_key_exists('X-Requested-With', Tool::getRequestHeaders())) {

            if ( ! empty($request['module'])) {
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
    public function setFavicon(Array $favicon) {

        if ( ! is_array($favicon)) {
            throw new \Exception('Incorrect favicon');
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

        $tpl  = new \Templater2(Theme::get("login"));
        $logo = $this->getSystemLogo();

        if ($logo) {
            $tpl->logo->assign('{logo}', $logo);
        }

        if ($this->isModuleInstalled('auth')) {
            $auth_config = $this->modAuth->moduleConfig->auth;
            $reg_config = $this->modAuth->moduleConfig->registration;
            $restore_config = $this->modAuth->moduleConfig->restore;

            if ($auth_config->ldap &&
                $auth_config->ldap->on
            ) {
                $tpl->assign("id=\"gfhjkm", "id=\"gfhjkm\" data-ldap=\"1");
            }

            if ($auth_config->social) {
                if ($auth_config->social->fb &&
                    $auth_config->social->fb->on &&
                    $auth_config->social->fb->app_id &&
                    $auth_config->social->fb->api_secret &&
                    $auth_config->social->fb->redirect_url
                ) {

                    $tpl->social->fb->assign('[APP_ID]', $auth_config->social->fb->app_id);
                    $tpl->social->fb->assign('[REDIRECT_URL]', $auth_config->social->fb->redirect_url);
                }

                if ($auth_config->social->ok &&
                    $auth_config->social->ok->on &&
                    $auth_config->social->ok->app_id &&
                    $auth_config->social->ok->public_key &&
                    $auth_config->social->ok->secret_key &&
                    $auth_config->social->ok->redirect_url
                ) {

                    $tpl->social->ok->assign('[APP_ID]', $auth_config->social->ok->app_id);
                    $tpl->social->ok->assign('[REDIRECT_URL]', $auth_config->social->ok->redirect_url);
                }

                if ($auth_config->social->vk &&
                    $auth_config->social->vk->on &&
                    $auth_config->social->vk->app_id &&
                    $auth_config->social->vk->api_secret &&
                    $auth_config->social->vk->redirect_url
                ) {

                    $tpl->social->vk->assign('[APP_ID]', $auth_config->social->vk->app_id);
                    $tpl->social->vk->assign('[REDIRECT_URL]', $auth_config->social->vk->redirect_url);
                }

                if ($auth_config->social->google &&
                    $auth_config->social->google->on
                ) {
                    $tpl->social->google->assign('[OAUTH2]', $this->apiAuth->getAuthUrl('google'));
                }
            }

            if ($this->config->mail && $this->config->mail->server) {
                if ($reg_config &&
                    $reg_config->on &&
                    $reg_config->role_id
                ) {
                    $tpl->ext_actions->touchBlock('registration');
                }

                if ($restore_config && $restore_config->on) {
                    $tpl->ext_actions->touchBlock('restore');
                }
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
    private function getPageRegistration($fields = []) {

        $tpl  = new \Templater3(Theme::get("login-registration"));
        $logo = $this->getSystemLogo();

        if ($logo) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $tpl->touchBlock('restore');

        $isset_phone = false;

        if ($fields) {

            if ( ! empty($fields)) {
                foreach ($fields as $name => $field) {

                    $type = ! empty($field['type']) ? htmlspecialchars($field['type']) : 'text';

                    if ($type == 'phone') {
                        $isset_phone = true;
                    }

                    if ($type == 'select') {
                        $tpl->field->select->assign('[NAME]',     $name);
                        $tpl->field->select->assign('[TITLE]',    ! empty($field['title']) ? htmlspecialchars($field['title']) : '');
                        $tpl->field->select->assign('[REQUIRED]', ! empty($field['required']) ? 'required' : '');

                        if ( ! empty($field['options']) && is_array($field['options'])) {
                            foreach ($field['options'] as $option_val => $option_title) {
                                if (is_string($option_title)) {
                                    $tpl->field->select->select_option->assign('[VALUE]', $option_val);
                                    $tpl->field->select->select_option->assign('[TITLE]', $option_title);
                                    $tpl->field->select->select_option->reassign();
                                }
                            }
                        }

                        $tpl->field->select->reassign();

                    } else {
                        $tpl->field->input->assign('[NAME]',     $name);
                        $tpl->field->input->assign('[TYPE]',     $type);
                        $tpl->field->input->assign('[TITLE]',    ! empty($field['title']) ? htmlspecialchars($field['title']) : '');
                        $tpl->field->input->assign('[REQUIRED]', ! empty($field['required']) ? 'required' : '');
                        $tpl->field->input->reassign();
                    }

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

        $tpl  = new \Templater3(Theme::get("login-registration-complete"));
        $logo = $this->getSystemLogo();

        if ($logo) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $error_message = '';

        $isset_key = $this->db->fetchOne("
                SELECT 1
                FROM core_users
                WHERE reg_key = ?
                  AND date_expired > NOW()
                  AND visible = 'N'
            ", $key);

        if ($isset_key) {
            $tpl->pass->assign('[KEY]', $key);
        } else {
            $error_message = $this->_('Ссылка устарела');
        }

        $tpl->assign('[ERROR_MSG]', $error_message);

        //FIXME сделать ненадежно
        if ($this->modAuth->moduleConfig->restore && $this->modAuth->moduleConfig->restore->on) {
            $tpl->touchBlock('restore');
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

        $tpl  = new \Templater3(Theme::get("login-restore"));
        $logo = $this->getSystemLogo();

        if ($logo) {
            $tpl->logo->assign('{logo}', $logo);
        }

        $tpl->touchBlock('registration');

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

        $tpl  = new \Templater3(Theme::get("login-restore-complete"));
        $logo = $this->getSystemLogo();

        if ($logo) {
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

        $tpl->touchBlock('registration');


        $html = $this->getIndex();
        $html = str_replace('<!--index -->', $tpl->render(), $html);

        return $html;
    }


    /**
     * @param array $user
     * @return bool
     * @throws \Exception
     */
    private function auth(array $user): bool {

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

        $authNamespace->ID    = (int)$user['u_id'];
        $authNamespace->NAME  = $user['u_login'];
        $authNamespace->EMAIL = $user['email'];

        if ($user['u_login'] == 'root') {
            $authNamespace->ADMIN  = true;
            $authNamespace->ROLEID = 0;
        } else {
            $authNamespace->LN     = $user['lastname'];
            $authNamespace->FN     = $user['firstname'];
            $authNamespace->MN     = $user['middlename'];
            $authNamespace->ADMIN  = $user['is_admin_sw'] == 'Y';
            $authNamespace->ROLE   = $user['role'] ?: -1;
            $authNamespace->ROLEID = $user['role_id'] ?: 0;
            $authNamespace->LIVEID = $this->storeSession($authNamespace);
        }

        $authNamespace->LDAP = $user['LDAP'] ?? false;


        //регенерация сессии для предотвращения угона
        if ( ! ($authNamespace->init)) {
            $authNamespace->getManager()->regenerateId();
            $authNamespace->init = true;
        }

        return true;
    }


    /**
     * Авторизация пользователя через форму
     * @param string $login
     * @param string $password
     * @return bool
     * @throws \Zend_Db_Exception
     */
    private function authLoginPassword(string $login, string $password): bool {

        $blockNamespace = new SessionContainer('Block');

        try {
            if ( ! empty($blockNamespace->blocked)) {
                throw new \Exception($this->translate->tr("Ваш доступ временно заблокирован!"));
            }

            $login = trim($login);

            $this->getConnection($this->config->database);

            if ($login === 'root') {
                $user = $this->getUserRoot();
            } else {
                if ($this->core_config->auth &&
                    $this->core_config->auth->ldap &&
                    $this->core_config->auth->ldap->on
                ) {
                    if ((function_exists('ctype_print') ? ! ctype_print($password) : true) ||
                        strlen($password) < 1
                    ) {
                        throw new \Exception($this->_("Ошибка пароля!"));
                    }

                    $user           = $this->getUserLdap($login, $password);
                    $user['LDAP']   = true;
                    $user['u_pass'] = Tool::pass_salt($password);

                } else {
                    $user = $this->dataUsers->getUserByLogin($login);
                }
            }

            if ( ! $user) {
                throw new \Exception($this->translate->tr("Нет такого пользователя"));
            }


            if ($user['u_pass'] !== Tool::pass_salt($password)) {
                throw new \Exception($this->translate->tr("Неверный пароль"));
            }

            $this->auth($user);

            return true;

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

            throw $e;
        }
    }


    /**
     * Вход через вконтакт
     * @param string $code
     * @return bool
     * @throws \Exception
     */
    private function authVk(string $code): bool {

        if ($this->core_config->auth &&
            $this->core_config->auth->module
        ) {
            if (empty($code)) {
                throw new \Exception($this->_('Не указан код авторизации'));
            }


            $mod_controller = $this->getAuthModule();
            $user_id = $mod_controller->authVk($code);
            $user    = $this->dataUsers->getUserById($user_id);

            if (empty($user) || ! is_array($user)) {
                throw new \Exception($this->_('Ошибка входа через соц сеть'));
            }

            $this->auth($user);

            return true;

        } else {
            throw new \Exception($this->_('Вход через эту соц сеть недоступен'));
        }
    }


    /**
     * Вход через Однокласскини
     * @param string $code
     * @return bool
     * @throws \Exception
     */
    private function authOk(string $code): bool {

        if ($this->core_config->auth &&
            $this->core_config->auth->module
        ) {
            if (empty($code)) {
                throw new \Exception($this->_('Не указан код авторизации'));
            }

            $mod_controller = $this->getAuthModule();

            $user_id = $mod_controller->authOk($code);
            $user    = $this->dataUsers->getUserById($user_id);

            if (empty($user) || ! is_array($user)) {
                throw new \Exception($this->_('Ошибка входа через соц сеть'));
            }

            $this->auth($user);

            return true;

        } else {
            throw new \Exception($this->_('Вход через эту соц сеть недоступен'));
        }
    }


    /**
     * Вход через Facebook
     * @param string $code
     * @return bool
     * @throws \Exception
     */
    private function authFb(string $code): bool {

        if ($this->core_config->auth &&
            $this->core_config->auth->module
        ) {
            if (empty($code)) {
                throw new \Exception($this->_('Не указан код авторизации'));
            }

            $mod_controller = $this->getAuthModule();

            $user_id = $mod_controller->authFb($code);
            $user    = $this->dataUsers->getUserById($user_id);

            if (empty($user) || ! is_array($user)) {
                throw new \Exception($this->_('Ошибка входа через соц сеть'));
            }

            $this->auth($user);

            return true;

        } else {
            throw new \Exception($this->_('Вход через эту соц сеть недоступен'));
        }
    }


    /**
     * @param array $fields
     * @param array $data
     * @param       $role_id
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     */
    private function registration(array $fields, array $data, $role_id) {

        try {
            $this->emit('reg_data', $data);
        } catch (\Exception $e) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }

        if (!$fields || !$role_id) return;

        foreach ($fields as $key => $field) {
            if (!empty($field['required']) && empty($data[$key])) {
                return json_encode([
                    'status'        => 'error',
                    'error_message' => sprintf($this->_('Поле %s не заполнено'), $field['title']),
                ]);
            }
        }

        $data['email'] = isset($data['email']) ? trim($data['email']) : null;
        $data['login'] = isset($data['login']) ? trim($data['login']) : $data['email'];
        if (!$data['email']) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Не удалось получить email для регистрации')
            ]);
        }
        if (!$data['login']) {
            return json_encode([
                'status'        => 'error',
                'error_message' => $this->_('Не удалось задать логин пользователя')
            ]);
        }

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
                'role_id'        => $role_id,
                'visible'        => 'N',
                'is_email_wrong' => 'N',
                'reg_key'        => $reg_key,
                'date_expired'   => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
                'date_added'     => new \Zend_Db_Expr('NOW()'),
            ]);

            $user_id = $this->db->lastInsertId();

            if ( ! empty($data['name'])) {
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

            $this->emit('new_user', [
                'user_id' => $user_id,
                'form'    => $data,
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
     * @throws \Exception
     */
    private function registrationComplete($key, $password) {

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
            'u_pass'          => Tool::pass_salt($password),
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
     * @throws \Exception
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
                'error_message' => $this->_('В системе нет пользователя с таким Email')
            ]);
        }


        $reg_key = md5($email . microtime());
        $where   = $this->db->quoteInto('u_id = ?', $user_id);
        $this->db->update('core_users', [
            'reg_key'      => $reg_key,
            'date_expired' => new \Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL 1 DAY)')
        ], $where);

        $this->sendEmailRestore($email, $reg_key);

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
     * @throws \Exception
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
            'u_pass'       => Tool::pass_salt($password),
            'reg_key'      => new \Zend_Db_Expr('NULL'),
            'date_expired' => new \Zend_Db_Expr('NULL'),
        ], $where);


        return json_encode([
            "status"  => "success",
            "message" => "<h4>Пароль изменен!</h4><p>Вернитесь на форму входа и войдите в систему с новым паролем</p>"
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

        Registry::set('context', ['queue', 'index']);

        require_once 'Email.php';
        $email = new \Core2\Email();
        $email->to($mail_address)
            ->subject("{$name}: Регистрация на сервисе")
            ->body($content_email)
            ->send(true);
    }


    /**
     * @param $mail_address
     * @param $reg_key
     */
    private function sendEmailRestore($mail_address, $reg_key) {

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

        Registry::set('context', ['queue', 'index']);

        require_once 'Email.php';
        $core_email = new \Core2\Email();
        $core_email->to($mail_address)
            ->subject("{$name}: Восстановление пароля")
            ->body($content_email)
            ->send(true);
    }


    /**
     * Установка контекста выполнения скрипта
     * @param string $module
     * @param string $action
     */
    private function setContext($module, $action = 'index') {
        Registry::set('context', [$module, $action]);
    }


    /**
     * Получение логотипа системы из conf.ini
     * или установка логотипа по умолчанию
     * @return string|null
     */
    private function getSystemLogo():? string {

        $res = $this->config->system->logo;

        if ( ! empty($res) && is_file($res)) {
            return $res;

        } else {
            $logo       = Theme::getModel()["logo"] ?? '';
            $theme_path = 'core2/html/' . THEME;
            $theme_dir  = DOC_ROOT . $theme_path;

            return $logo && is_file("{$theme_dir}/{$logo}")
                ? "{$theme_path}/{$logo}"
                : null;
        }
    }


    /**
     * Получение данных дя пользователя root
     * @return array
     */
    private function getUserRoot() {

        require_once __DIR__ . '/../CoreController.php';

        $auth            = [];
        $auth['u_pass']  = \CoreController::RP;
        $auth['u_id']    = -1;
        $auth['u_login'] = 'root';
        $auth['email']   = 'easter.by@gmail.com';

        return $auth;
    }


    /**
     * @param string $login
     * @param string $password
     * @return array|bool
     * @throws \Exception
     */
    private function getUserLdap(string $login, string $password): array {

        if ($this->core_config->auth &&
            $this->core_config->auth->module
        ) {
            $module_name = strtolower($this->core_config->auth->module);
            $location    = $this->getModuleLocation($module_name);

            $mod_controller_name = "Mod" . ucfirst($module_name) . "Controller";
            $vendor_autoload     = "{$location}/vendor/autoload.php";

            if ( ! file_exists("{$location}/{$mod_controller_name}.php")) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не найден'), $module_name));
            }

            require_once "{$location}/{$mod_controller_name}.php";

            if (file_exists($vendor_autoload)) {
                require_once $vendor_autoload;
            }

            $this->setContext($module_name);
            $mod_controller = new $mod_controller_name();
            if ( ! ($mod_controller instanceof Auth)) {
                throw new \Exception(sprintf($this->_('Контроллер модуля %s не поддерживает дополнительную авторизацию'), $module_name));
            }

            $user_id = $mod_controller->authLdap($login, $password);
            $user    = $this->dataUsers->getUserById($user_id);

            if (empty($user) || ! is_array($user)) {
                throw new \Exception($this->_('Ошибка входа через LDAP'));
            }

            return $user;

        } else {
            throw new \Exception($this->_('Вход через LDAP недоступен'));
        }
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

        if (Tool::isMobileBrowser()) {
            $tpl->loadTemplate(Theme::get("login-indexMobile"));
        } else {
            $tpl->loadTemplate(Theme::get("login-index"));
        }

        $tpl->assign('{system_name}', $this->system_name);

        $tpl->assign('favicon.png', isset($this->favicon['png']) && is_file($this->favicon['png']) ? $this->favicon['png'] : '');
        $tpl->assign('favicon.ico', isset($this->favicon['ico']) && is_file($this->favicon['ico']) ? $this->favicon['ico'] : '');


        if ( ! empty($this->config->system) &&
             ! empty($this->config->system->theme) &&
             ! empty($this->config->system->theme->login_bg) &&
            $tpl->issetBlock('theme_style')
        ) {
            $path_parts = pathinfo($this->config->system->theme->login_bg);
            if ($path_parts['extension'] == 'mp4') {
                $tpl->theme_style->assign("[LOGIN_BG]", "");
                $tpl->assign("<!--index -->", "<video autoplay muted loop style=\"position: fixed;
                        right: 0;
                        bottom: 0;
                        min-width: 100%;
                        min-height: 100%;
                        z-index: -1000;\">
                    <source src=\"{$this->config->system->theme->login_bg}\" type=\"video/mp4\">
                </video><!--index -->");
            } else {
                $tpl->theme_style->assign("[LOGIN_BG]", $this->config->system->theme->login_bg);
            }
        }

        return $tpl->render();
    }

    /**
     *
     * @return \ModAuthController
     * @throws \Exception
     */
    private function getAuthModule()
    {
        $module_name = 'auth';
        $location    = $this->getModuleLocation($module_name);

        require_once "{$location}/ModAuthController.php";
        $vendor_autoload     = "{$location}/vendor/autoload.php";

        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
        }

        $this->setContext($module_name);
        return new \ModAuthController();
    }
}
