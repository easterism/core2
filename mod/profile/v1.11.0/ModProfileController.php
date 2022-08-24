<?php

require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';

require_once 'classes/Profile.php';
require_once 'classes/Messages.php';
require_once 'classes/Event.php';

use Sse\SSE;

/**
 * Class ModProfileController
 */
class ModProfileController extends Common {

	public function __construct () {
		parent::__construct();
		$this->checkRequest(array(
			'edit',
			'editprofile',
			'editother',
			'editdec',
			'tab_' . $this->resId
		));
	}

	/**
	 * Скрипты для загрузки в head
	 * @return array|null массив с src
	 */
	public function topJs() {
        if ($this->checkAcl('profile_messages')) {
            return array("{$this->getModuleSrc('profile')}/html/js/top_message.js");
        }
        return null;
	}


    /**
     * Профиль пользователя
     * @return string
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public function action_index () {

        if (isset($_GET['unread']) && $_GET['unread'] == 1) {
            return json_encode(count($this->api->getUnreadMsg()));
        }
        if (isset($_GET['sse']) && $_GET['sse'] == 'open') {
            $sse = new SSE();
            $sse->exec_limit = 360;
            $sse->sleep_time = 1;
            $sse->addEventListener('', new Core2\Profile\Event($this));
            session_write_close();
            $sse->start();
            return;
        }

        if (isset($_GET['term'])) {
            $_GET['term'] = trim($_GET['term']);
            $result = '';
            if ($_GET['term']) {
                $users = $this->getSearchUsers($_GET['term']);
                $result = json_encode($users);
            }
            return $result;
        }


        ob_start();
		$app = "index.php?module=profile";
        $isset_settings = $this->db->fetchOne("
            SELECT 1
            FROM core_settings
            WHERE visible = 'Y'
              AND is_personal_sw = 'Y'
            LIMIT 1
        ");


        $tab = new tabs($this->resId);
        $tab->addTab("Личные данные", $app, 130);
        if ($isset_settings) {
            $tab->addTab("Персональные настройки", $app, '', $this->auth->ID > 0 ? 'enabled' : 'disabled');
        }

        $profile = new Profile($this->auth->ID);
        $tab->beginContainer("Профиль");

        if ($tab->activeTab == 1) {
            $is_readonly = empty($_REQUEST['editprofile']);
            echo $profile->getEdit($app, $is_readonly);

        } elseif ($tab->activeTab == 2 && $isset_settings && $this->auth->ID > 0) {
            $app .= '&tab_profile=2';
            echo $profile->getEditSettings($app, empty($_GET['edit']));
        }

        $tab->endContainer();
        return ob_get_clean();
	}


    /**
     * Сообщения пользователя
     */
    public function action_messages () {

        $app = "index.php?module=profile&action=messages";
        $messages = new Messages($this->auth->ID);

        if ( ! empty($_GET['settings'])) {
            ob_start();
            $app .= '&settings=1';
            $tab = new Tabs($this->resId);
            $tab->beginContainer('Настройки');
            echo $messages->getSettings($app);
            $tab->endContainer();
            return ob_get_clean();

        }
        if ( ! empty($_GET['write'])) {
            ob_start();
            $tab = new Tabs($this->resId);
            $tab->beginContainer('Новое сообщение');
            echo $messages->getEdit($app);
            $tab->endContainer();
            return ob_get_clean();
        }


        ob_start();
        $src = $this->getModuleSrc('profile');
        $this->printCss($src . '/html/css/messages.css');

        $tab = new Tabs($this->resId);
        $tab->addTab("Входящие",     $app, 130);
        $tab->addTab("Отправленные", $app, 130);
        $tab->beginContainer('Сообщения');
        $tpl = new Templater3(__DIR__ . "/html/mailbox.html");

        try {
            switch($tab->activeTab) {
                case 1:
                    $list = $messages->getListInbox($app . '&tab_profile_messages=1');
                    $tpl->assign('[LIST]', $list);
                    break;

                case 2:
                    $list = $messages->getListOutbox($app . '&tab_profile_messages=2');
                    $tpl->assign('[LIST]', $list);
                    break;
            }
        } catch (Exception $e) {
            $tpl->assign('[LIST]', $e->getMessage());
        }



        if ( ! empty($_GET['read'])) {
            try {
                $message_content = $messages->getMessageContent($_GET['read']);

                $where = $this->db->quoteInto('id = ?', $_GET['read']);
                $this->db->update('mod_profile_messages', array(
                    'is_read' => 'Y'
                ), $where);

                $message = $this->db->fetchRow("
                    SELECT `from`,
                           `to`
                    FROM mod_profile_messages
                    WHERE id = ?
                      AND user_id = ?
                ", array(
                    $_GET['read'],
                    $this->auth->ID
                ));

                $tpl->message_toolbar->assign('[FROM]', htmlspecialchars($message['from']));
                $tpl->message_toolbar->assign('[TO]',   htmlspecialchars($message['to']));
            } catch (Exception $e) {
                $message_content = $e->getMessage();
            }
        } else {
            $message_content = '';
        }

        $tpl->assign('[MESSAGE_CONTENT]', $message_content);
        $tpl->assign('[SRC_MOD]',         $src);
        echo $tpl->render();
        $tab->endContainer();

        return ob_get_clean();
    }


    /**
     * Получение писем из ящиков пользователей
     */
    public function fetchEmails () {

        $this->module    = 'profile';
        $profile_settings = $this->dataProfileMessagesSettings;

        $core_users = $this->modAdmin->dataUsers;
        $users      = $core_users->fetchAll()->toArray();

        foreach ($users as $user) {
            $settings = $profile_settings->get($user['u_id']);

            if (isset($settings['mail_server']) && trim($settings['mail_server']) != '') {
                try {
                    require_once("Zend/Mail/Storage/Imap.php");
                    require_once("Zend/Mime/Decode.php");


                    $decode = new Zend_Mime_Decode();
                    $mail   = new Zend_Mail_Storage_Imap(array(
                        'host'     => $settings['mail_server'],
                        'user'     => $settings['email'],
                        'password' => $settings['password'],
                        'port'     => $settings['port'],
                        'ssl'      => $settings['encryption']
                    ));



                    foreach ($mail as $message) {
                        try {
                            if (
                                ! $message->hasFlag(Zend_Mail_Storage::FLAG_SEEN) &&
                                preg_match('~^core - ~', $decode->decodeQuotedPrintable($message->subject))
                            ) {
                                $date_add = date('Y-m-d H:i', strtotime($message->date));
                                $message->from;
                                $content_type = explode(';', $message->contentType);
                                $content_type = is_array($content_type)
                                    ? $content_type[0]
                                    : $content_type;

                                $this->dataProfileMessages->add(array(
                                    'to'                => $message->to,
                                    'from'              => $message->from,
                                    'content_type'      => $content_type,
                                    'date_add'          => $date_add,
                                    'location'          => 'inbox',
                                    'email_id'          => $message->messageId,
                                    'method_of_getting' => 'email',
                                    'user_id'           => $this->auth->ID
                                ));
                            }

                        } catch (Zend_Mail_Exception $e) {
                            // ignore
                        }
                    }

                } catch (Exception $e) {
                    // ignore
                }
            }
        }
    }


    /**
     * Получение идентификатора
     * пользователя по его логину
     * @param string $name
     * @return int
     */
    public function getUserId($name) {

        $name = mb_strtolower($name, 'UTF-8');
        $user_id = $this->db->fetchOne("
            SELECT up.user_id
            FROM core_users_profile AS up
            WHERE LOWER(CONCAT_WS('', up.lastname, ' ', up.firstname)) LIKE ?
            LIMIT 1
        ", $name);

        return (int)$user_id;
    }


    /** получаем настройку пользователя
     * @param string $code
     * @return bool|string
     */
    public function getPersonalSetting($code) {

        //проверяем активна ли настройка в системе
        $is_visible = $this->db->fetchOne("SELECT visible FROM core_settings WHERE visible='Y' AND is_personal_sw='Y' AND code = ?", $code);

        //если активна, отдаём то,что назополнял пользователь
        if ($is_visible == 'Y') {
            return $this->db->fetchOne("SELECT value FROM mod_profile_user_settings WHERE user_id = ? AND code = ?", array($this->auth->ID, $code));
        } else {
            return false;
        }
    }


    /**
     * Процесс по уведомлению пользователей о непрочитанных
     * сообщениях в их профиле
     * @return void
     */
    public function noticeUnreadMessages() {

        $users = $this->db->fetchAll("
            SELECT m.user_id,
                   u.email,
                   u.u_login,
                   COUNT(m.id) AS unread_messages
            FROM mod_profile_messages AS m
                JOIN core_users AS u ON u.u_id = m.user_id
            WHERE m.location = 'inbox'
              AND m.is_read  = 'N'
              AND u.visible  = 'Y'
            GROUP BY m.user_id
        ");


        foreach ($users as $user) {

            if ($user['email']) {
                $body = <<<HTML
                    <p>Здравствуйте, {$user['u_login']}!</p>
                    <p>У вас есть непрочитанные сообщения ({$user['unread_messages']}).</p>
                    <a href="http://{$this->config->system->host}?module=profile&action=messages">Прочитать</a>
HTML;

                $this->modAdmin->createEmail()
                    ->from('noreply@' . $this->config->system->host)
                    ->to($user['email'])
                    ->subject('У вас есть непрочитанные сообщения')
                    ->body($body)
                    ->importance('LOW')
                    ->send();
            }
        }
    }


    /**
     * DEPRECATED
     * Сохраняем данные пользователя
     *
     * @param $key
     * @param $data
     *
     * @return void
     */
    public function putUserData($key, $data) {

        if ( ! $this->isUserDataExist($key)) {
            $this->db->insert(
                'mod_profile_users_data',
                array(
                    'code'      => $key,
                    'user_id'   => $this->auth->ID,
                    'udata'     => serialize($data),
                )
            );

        } else {
            $this->db->update(
                'mod_profile_users_data',
                array(
                    'udata'     => serialize($data),
                ),
                $this->db->quoteInto("code = ? AND ", $key) . $this->db->quoteInto(" user_id = ? ", $this->auth->ID)
            );
        }
    }


    /**
     * Получаем данные пользователя
     * @param $key
     * @return bool
     */
    public function getUserData($key) {

        $udata = $this->db->fetchOne("
            SELECT udata
            FROM mod_profile_users_data
            WHERE code = ?
              AND user_id = ?
        ", array(
            $key,
            $this->auth->ID
        ));

        if (!empty($udata)) $udata = unserialize($udata);

        return $udata;
    }


    /**
     * Названия методов которые могут использоваться в модуле "Планировщик"
     * @return array
     */
    public function getCronMethods() {

        return array(
            'noticeUnreadMessages'
        );
    }


    /**
     * Проверяем существует ли такой ключ в таблицке
     * @param $key
     * @return string id существующих данных
     */
    public function isUserDataExist($key) {

        return $this->db->fetchOne("
            SELECT 1
            FROM mod_profile_users_data
            WHERE code = ?
              AND user_id = ?
        ", array(
            $key,
            $this->auth->ID
        ));
    }


    /**
     * Формирование списка пользователей
     * подпадающих под условие поиска
     * @param string $name
     * @return array
     */
    private function getSearchUsers($name) {

        $users = $this->db->fetchAll("
            SELECT CONCAT_WS('', up.lastname, ' ', up.firstname) AS name
            FROM core_users AS u
                LEFT JOIN core_users_profile AS up ON up.user_id = u.u_id
            WHERE LOWER(CONCAT_WS('', up.lastname, ' ', up.firstname)) LIKE ?
              AND u.u_id <> ?
            LIMIT 15
        ", array(
            '%' . mb_strtolower($name, 'utf8') . '%',
            $this->auth->ID
        ));

        $result = array();
        foreach ($users as $user) {
            $strip_name = strip_tags($user['name']);
            if ($strip_name) {
                $result[] = $strip_name;
            }
        }

        return $result;
    }
}