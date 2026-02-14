<?php
namespace Core2;

require_once 'Templater3.php';
require_once 'Tool.php';

use Laminas\Session\Container as SessionContainer;
use Exception;
use Templater3;

/**
 * @property Model\Users              $dataUsers
 * @property \ModWebserviceController $modWebservice
 */
class Login extends \Common {

    private $system_name = '';
    private $favicon     = [];


    /**
     * @return string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Exception
     * @throws \Zend_Exception
     * @throws Exception
     */
    public function dispatch(array $route) {

        //-------------регистрация, аутентификация через форму------------------
        $uri = $route['module'];
        parse_str($route['query'], $query);
        if (isset($query['core'])) {
            $uri = $query['core']; //FIXME DEPRECATED
        }
        if ($uri == 'registration') {
            $auth = $this->isModuleInstalled('auth');
            if (!$auth) {
                throw new Exception($this->_('Модуль регистрации не найден'), 404);
            }
            if (isset($auth['submodules']['registration']) && $auth['submodules']['registration']['visible'] !== 'Y') {
                //субмдуль регистрациивыключен
                throw new Exception($this->_('Регистрация недоступна'), 403);
            }
            $form_html = $this->modAuth->getPageRegistration();
            $tpl    = new Templater3();
            $tpl->setTemplate($form_html);
            $html = str_replace('<!--index -->', $tpl->render(), $this->getIndex());
            return $html;
        }
        elseif ($uri == 'registration_complete') {
            $auth = $this->isModuleInstalled('auth');
            if (!$auth) {
                throw new Exception($this->_('Модуль регистрации не найден'), 404);
            }
            if (isset($auth['submodules']['restore']) && $auth['submodules']['restore']['visible'] !== 'Y') {
                //субмдуль регистрациивыключен
                throw new Exception($this->_('Регистрация недоступна'), 403);
            }
            if (!isset($query['key'])) {
                //субмдуль регистрациивыключен
                throw new Exception($this->_('Ключ не передан'), 400);
            }
            $form_html = $this->modAuth->getPageRegistrationComplete($query['key']);
            $tpl  = new Templater3();
            $tpl->setTemplate($form_html);
            $html = str_replace('<!--index -->', $tpl->render(), $this->getIndex());
            return $html;
        }
        elseif ($uri == 'restore') {
            $auth = $this->isModuleInstalled('auth');
            if (!$auth) {
                throw new Exception($this->_('Модуль регистрации не найден'), 404);
            }
            if (isset($auth['submodules']['restore']) && $auth['submodules']['restore']['visible'] !== 'Y') {
                //субмдуль регистрациивыключен
                throw new Exception($this->_('Восстановление пароля недоступно'), 403);
            }
            $form_html = $this->modAuth->getPageRestore();
            $tpl  = new Templater3();
            $tpl->setTemplate($form_html);
            $html = str_replace('<!--index -->', $tpl->render(), $this->getIndex());
            return $html;
        }
        elseif ($uri == 'restore_complete') {
            $auth = $this->isModuleInstalled('auth');
            if (!$auth) {
                throw new Exception($this->_('Модуль регистрации не найден'), 404);
            }
            if (isset($auth['submodules']['restore']) && $auth['submodules']['restore']['visible'] !== 'Y') {
                //субмдуль регистрациивыключен
                throw new Exception($this->_('Восстановление пароля недоступно'), 403);
            }
            $form_html = $this->modAuth->getPageRestoreComplete($query['key']);
            $tpl  = new Templater3();
            $tpl->setTemplate($form_html);
            $html = str_replace('<!--index -->', $tpl->render(), $this->getIndex());
            return $html;
        }
        else {
            return $this->getPageLogin();
        }

    }


    /**
     * Попытка входа в систему
     * @param string      $login
     * @param string      $password
     * @param string|null $return_url
     * @return array|string[]
     */
    public function enter(string $login, string $password, string $return_url = null):array
    {
        if (empty($login)) {
            return [
                'status'  => 'error',
                'error_message' => $this->_('Заполните логин')
            ];
        }

        if (empty($password)) {
            return [
                'status'  => 'error',
                'error_message' => $this->_('Заполните пароль')
            ];
        }

        try {
            if ( ! empty($return_url) && filter_var($return_url, FILTER_VALIDATE_URL) !== false) {
                $return_url_parse = parse_url($return_url);

                if ( ! empty($return_url_parse['host']) &&
                     $this->config?->auth?->return_url?->domains &&
                     is_array($this->config->auth->return_url->domains->toArray()) &&
                     in_array($return_url_parse['host'], $this->config->auth->return_url->domains->toArray())
                ) {
                    $this->checkLogin($login, $password);

                    $name        =  $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $name        =  $name ?: "{$login}-" . crc32(uniqid());
                    $token       = $this->modWebservice->webtokens()->createWebtoken($login, $name);
                    $return_url .= parse_url($return_url, PHP_URL_QUERY)
                        ? "&access_token={$token}"
                        : "?access_token={$token}";

                    return [
                        'status'     => 'success',
                        'return_url' => $return_url,
                    ];
                }
            }

            $this->authLoginPassword($login, $password);

            return [
                'status' => 'success',
            ];

        } catch (\Exception $e) {

            return [
                'status'        => 'error',
                'error_message' => $e->getMessage(),
            ];
        }
    }


    /**
     * @param $system_name
     * @throws \Exception
     */
    public function setSystemName($system_name) {

        if ( ! is_scalar($system_name)) {
            throw new Exception('Incorrect system name');
        }

        $this->system_name = $system_name;
    }


    /**
     * @param $favicon
     * @throws \Exception
     */
    public function setFavicon(Array $favicon) {

        if ( ! is_array($favicon)) {
            throw new Exception('Incorrect favicon');
        }

        $this->favicon = $favicon;
    }


    /**
     * Форма входа в систему
     * @return string
     * @throws \Zend_Exception
     * @throws \Exception
     */
    private function getPageLogin() :string {

        $tpl  = new Templater3(Theme::get("login"));
        $logo = $this->getSystemLogo();

        if ($logo) {
            $tpl->logo->assign('{logo}', $logo);
        }
        $danger = '';
        if (!empty($this->config->session->cookie_secure)) {
            //cookie работают только по HTTPS
            $danger = $this->_("Вход возможен только по защищенному соединению.");
        }
        $tpl->assign('{danger}', $danger);
        if ($auth = $this->isModuleInstalled('auth')) {
            if (isset($auth['submodules']['registration']) && $auth['submodules']['registration']['visible'] !== 'Y') {
                //субмдуль регистрациивыключен
            }
            else {
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
                        if (isset($auth['submodules']['restore']) && $auth['submodules']['restore']['visible'] !== 'Y') {
                            //субмодуль восстановления пароля выключен
                        } else {
                            $tpl->ext_actions->touchBlock('restore');
                        }

                    }
                }
            }
        }

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
            throw new Exception($this->_("Ошибка сохранения сессии. Проверьте настройки системного времени."));
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
     * @param string $login
     * @param string $password
     * @return array
     * @throws Exception
     */
    private function checkLogin(string $login, string $password): array {

        $blockNamespace = new SessionContainer('Block');

        try {
            if ( ! empty($blockNamespace->blocked)) {
                throw new Exception($this->_("Ваш доступ временно заблокирован!"));
            }

            $login = trim($login);

//            $this->getConnection($this->config->database);

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
                        throw new Exception($this->_("Ошибка пароля!"));
                    }

                    $user           = $this->getUserLdap($login, $password);
                    $user['LDAP']   = true;
                    $user['u_pass'] = Tool::pass_salt($password);

                } else {
                    $user = $this->dataUsers->getUserByLogin($login);
                }
            }

            if ( ! $user) {
                throw new Exception($this->_("Нет такого пользователя"));
            }


            if ($user['u_pass'] !== Tool::pass_salt($password)) {
                throw new Exception($this->_("Неверный пароль"));
            }

            return $user;

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
     * Авторизация пользователя через форму
     * @param string $login
     * @param string $password
     * @return void
     * @throws \Zend_Db_Exception
     * @throws Exception
     */
    private function authLoginPassword(string $login, string $password): void {

        $user = $this->checkLogin($login, $password);
        $this->auth($user);
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

        if ($res = $this->config->system->logo) {
            if (is_file($res)) return "<img src='{$res}' alt='logo'>";
        }
        $tpl       = new Templater3(Theme::get("logo"));
        return $tpl->render();
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

        $tpl = new Templater3();
        if (!$this->favicon) {
            $this->favicon = $this->getSystemFavicon();
        }

        if (Tool::isMobileBrowser()) {
            $tpl->loadTemplate(Theme::get("login-indexMobile"));
        } else {
            $tpl->loadTemplate(Theme::get("login-index"));
        }

        if ($this->system_name) {
            $this->setSystemName($this->config->system->name);
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
     * get favicons from conf.ini
     * @return array
     */
    private function getSystemFavicon(): array {

        $favicon_png = $this->config->system->favicon_png;
        $favicon_ico = $this->config->system->favicon_ico;

        $favicon_png = $favicon_png && is_file($favicon_png)
            ? $favicon_png
            : (is_file('favicon.png') ? 'favicon.png' : '');

        $favicon_ico = $favicon_ico && is_file($favicon_ico)
            ? $favicon_ico
            : (is_file('favicon.ico') ? 'favicon.ico' : '');

        if (defined('THEME')) {
            if (!$favicon_png) {
                $favicon_png = 'core2/html/' . THEME . '/img/favicon.png';
            }
            if (!$favicon_ico) {
                $favicon_ico = 'core2/html/' . THEME . '/img/favicon.ico';
            }
        }

        return [
            'png' => $favicon_png,
            'ico' => $favicon_ico,
        ];
    }

}
