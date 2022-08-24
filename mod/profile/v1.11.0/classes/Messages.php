<?php

require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';
require_once DOC_ROOT . 'core2/inc/classes/Templater3.php';


/**
 * Class Messages
 */
class Messages extends Common {

    protected $user_id;


    /**
     * @param int $user_id
     */
    public function __construct($user_id) {
        parent::__construct();
        $this->user_id = $user_id;
    }


    /**
     * Список входящих сообщенний
     * @param string $app
     * @return string
     * @throws Exception
     */
    public function getListInbox($app) {

        ob_start();

        $list = new listTable($this->resId);
        $list->addSearch("Дата",      'm.date_add',  'DATE');
        $list->addSearch("От",        'm.from',      'TEXT');
        $list->addSearch("Вложения",  "IF(f.id IS NOT NULL, 'Y', 'N')", 'LIST'); $list->sqlSearch[] = array('Y' => 'С вложениями', 'N' => 'Без вложений');
        $list->addSearch("Прочитано", "m.is_read", 'LIST'); $list->sqlSearch[] = array('Y' => 'Да', 'N' => 'Нет');


        $list->SQL = $this->db->quoteInto("
            SELECT m.id,
                   m.date_add,
                   m.from,
                   f.id AS attachments,
                   m.is_read
            FROM   mod_profile_messages AS m
                LEFT JOIN mod_profile_messages_files AS f ON f.refid = m.id
            WHERE  m.location = 'inbox'
              AND  m.user_id = ?
                   ADD_SEARCH
            GROUP BY m.id
            ORDER BY m.date_add DESC
        ", $this->user_id);

        $list->addColumn("Дата", "145", "DATETIME");
        $list->addColumn("От",   "",    "HTML");
        $list->addColumn('',     "1%",  "BLOCK", 'align="center"', '', false);


        $list->editURL 	 = $app . "&read=TCOL_00";
        $list->deleteKey = "mod_profile_messages.id";
        $list->getData();


        foreach ($list->data as $k=>$value) {

            // Подсветка непрочитаннных сообщений
            if ($value[4] == 'N') {
                $list->metadata[$k]['fontWeight'] = 'bold';
            }

            // Подсветка выбранного сообщения
            if (isset($_GET['read']) && $value[0] == (int)$_GET['read']) {
                $list->metadata[$k]['paintColor'] = '#BFCFEA';
            }

            // Добавление признака присутствия вложений
            if ($value[3]) {
                $list->data[$k][3] = "<img src=\"{$this->getModuleLocation($this->module)}/html/img/attach.png\" alt=\"attach\">";
            } else {
                $list->data[$k][3] = '';
            }
        }

        $list->showTable();

        return ob_get_clean();
    }


    /**
     * Список отправленных сообщений
     * @param string $app
     * @return string
     */
    public function getListOutbox($app) {

        ob_start();

        $list = new listTable($this->resId);
        $list->addSearch("Дата",     'm.date_add',  'DATE');
        $list->addSearch("Адресат",  'm.to',        'TEXT');
        $list->addSearch("Вложения", "IF(f.id IS NOT NULL, 'Y', 'N')", 'LIST'); $list->sqlSearch[] = array('Y' => 'С вложениями', 'N' => 'Без вложений');


        $list->SQL = $this->db->quoteInto("
            SELECT m.id,
                   m.date_add,
                   m.to,
                   f.id AS attachments
            FROM   mod_profile_messages AS m
                LEFT JOIN mod_profile_messages_files AS f ON f.refid = m.id
            WHERE  m.location = 'outbox'
              AND  m.user_id = ?
                   ADD_SEARCH
            GROUP BY m.id
            ORDER BY m.date_add DESC
        ", $this->user_id);

        $list->addColumn("Дата",    "130px", "DATETIME");
        $list->addColumn("Адресат", "",      "TEXT");
        $list->addColumn('',        "1%",    "BLOCK", 'align="center"', '', false);


        $list->editURL 	 = $app . "&read=TCOL_00";
        $list->deleteKey = "mod_profile_messages.id";
        $list->getData();

        $src = $this->getModuleSrc($this->module);
        foreach ($list->data as $k=>$value) {

            // Подсветка выбранного сообщения
            if (isset($_GET['read']) && $value[0] == (int)$_GET['read']) {
                $list->metadata[$k]['paintColor'] = '#BFCFEA';
            }

            // Добавление признака присутствия вложений
            if ($value[3]) {
                $list->data[$k][3] = "<img src=\"{$src}/html/img/attach.png\" alt=\"attach\">";
            } else {
                $list->data[$k][3] = '';
            }
        }

        $list->showTable();

        return ob_get_clean();
    }


    /**
     * Получение содержимого текста письма
     * @param  int $message_id
     * @return string
     * @throws Exception
     */
    public function getMessageContent($message_id) {

        $message = $this->db->fetchRow("
            SELECT m.from,
                   m.to,
                   m.method_of_getting,
                   m.content_type,
                   m.is_read,
                   m.email_id,
                   m.message
            FROM mod_profile_messages AS m
            WHERE m.id      = ?
              AND m.user_id = ?
        ", array(
            $message_id,
            $this->user_id
        ));

        if (empty($message)) {
            throw new Exception('Сообщение удалено или у вас нет прав для его просмотра');
        }


        if ($message['method_of_getting'] == 'email' && $message['is_read'] == 'N' && $message['email_id'] != '') {
            try {
                require_once("Zend/Mail/Storage/Imap.php");
                require_once("Zend/Mime/Decode.php");

                $settings = $this->db->fetchPairs("
                    SELECT `name`,
                           `value`
                    FROM mod_profile_messages_settings
                    WHERE user_id = ?
                ", $this->user_id);

                $mail_server = isset($settings['mail_server']) ? $settings['mail_server'] : '';
                $login       = isset($settings['login'])       ? $settings['login']       : '';
                $password    = isset($settings['password'])    ? $settings['password']    : '';
                $port        = isset($settings['port'])        ? $settings['port']        : '';
                $ssl         = isset($settings['encryption'])  ? $settings['encryption']  : '';

                $decode       = new Zend_Mime_Decode();
                $storage_mail = new Zend_Mail_Storage_Imap(array(
                    'host'     => $mail_server,
                    'user'     => $login,
                    'password' => $password,
                    'port'     => $port,
                    'ssl'      => $ssl
                ));


                foreach ($storage_mail as $idx => $storage_message) {
                    if ($storage_message instanceof Zend_Mail_Message &&
                        $message['email_id'] == $storage_message->messageId
                    ) {
                        try {
                            // получение тела письма
                            $parts = array();
                            if ($storage_message->isMultipart()) {
                                foreach ($storage_message as $part) {
                                    if ($part instanceof Zend_Mail_Part) {
                                        try {
                                            $parts[] = $decode->decodeQuotedPrintable($part->getContent());

                                        } catch (Zend_Mail_Exception $e) {
                                            // ignore
                                        }
                                    }
                                }
                            } else {
                                $parts[] = $decode->decodeQuotedPrintable($storage_message->getContent());
                            }

                            // сохрание
                            if ( ! empty($parts)) {
                                $where   = $this->db->quoteInto('id = ?', $message_id);
                                $content = $message['message'] = implode('', $parts);
                                $this->db->update('mod_profile_messages', array('message' => $content), $where);
                            }

                            // пометка письма как прочтенное
                            $storage_mail->setFlags($idx, array(Zend_Mail_Storage::FLAG_SEEN));
                            break;

                        } catch (Zend_Mail_Exception $e) {
                            // ignore
                        }
                    }
                }

            } catch (Exception $e) {
                // ignore
            }
        }


        $tpl = new Templater3(__DIR__ . '/../html/message.html');

        switch ($message['content_type']) {
            case 'text/html' :
                $content = strip_tags($message['message'], '<div><p><a><br><table><caption><td><tr><thead><tbody><tfooter><blockquote><cite><q><em><i><b><span><strike><strong><addr><acronym><ul><li><ol><dd><dt><img><h3><h4><h5><h6>');
                $tpl->assign('[CONTENT]', $content);
                break;
            case 'text/plain' :
            default :
                $content = '<pre>' . htmlspecialchars($message['message']) . '</pre>';
                $tpl->assign('[CONTENT]', $content);
                break;
        }

        $message_files = $this->db->fetchAll("
            SELECT f.id,
                   f.filename,
                   f.type
            FROM mod_profile_messages_files AS f
            WHERE f.refid   = ?
        ", $message_id);

        if ( ! empty($message_files)) {
            foreach ($message_files as $file) {

                if (in_array($file['type'], array('image/jpeg', 'image/jpg', 'image/png', 'image/gif'))) {
                    $tpl->files->file->img->assign('[FILENAME]', $file['filename']);
                    $tpl->files->file->img->assign('[FILE_ID]',  $file['id']);
                } else {
                    $tpl->files->file->other->assign('[FILENAME]', $file['filename']);
                    $tpl->files->file->other->assign('[FILE_ID]',  $file['id']);
                }


                $tpl->files->file->reassign();
            }
        }

        return $tpl->render();
    }


    /**
     * Настройки пользователя
     * @param string $app
     * @return string
     */
    public function getSettings($app) {

        ob_start();

        $edit       = new editTable($this->resId);
        $edit->SQL  = "
            SELECT NULL AS id,
                   NULL AS mail_server,
                   NULL AS login,
                   NULL AS password,
                   NULL AS port,
                   NULL AS encryption
        ";

        $settings = $this->db->fetchPairs("
            SELECT `name`,
                   `value`
            FROM mod_profile_messages_settings
            WHERE user_id = ?
        ", $this->user_id);

        $mail_server = isset($settings['mail_server']) ? $settings['mail_server'] : '';
        $login       = isset($settings['login'])       ? $settings['login']       : '';
        $password    = isset($settings['password'])    ? $settings['password']    : '';
        $port        = isset($settings['port'])        ? $settings['port']        : '';
        $ssl         = isset($settings['encryption'])  ? $settings['encryption']  : '';

        $encryption = array(
            ''    => 'Не исползовать',
            'TSL' => 'TSL',
            'SSL' => 'SSL'
        );

        $edit->addControl("Адрес сервера", 'TEXT',     'style="width:189px"', '', $mail_server);
        $edit->addControl("Логин",         'TEXT',     'style="width:189px"', '', $login);
        $edit->addControl("Пароль",        'PASSWORD', '',                    '', $password);
        $edit->addControl("Порт",          'NUMBER',   'style="width:189px"', '', $port);
        $edit->addControl("Шифрование",    'LIST',     'style="width:189px"', '', $ssl); $edit->selectSQL[] = $encryption;


        if ((int)@$_GET['edit']) {
            $edit->addButton("Отменить", "load('$app')");
            $edit->save("xajax_saveMessagesSettings(xajax.getFormValues(this.id))");

        } else {
            $edit->readOnly = true;
            $edit->addButtonCustom("<input type=\"button\" class=\"button btn btn-info\" value=\"Редактировать\" onclick=\"load('{$app}&edit=1')\">");
            $edit->addButton("Назад", "load('index.php?module=profile&action=messages')");
        }


        $edit->back = $app;
        $edit->showTable();

        return ob_get_clean();
    }


    /**
     * Форма создания сообщения
     * @param string $app
     * @return string
     * @throws Exception
     */
    public function getEdit($app) {

        ob_start();

        $src = $this->getModuleSrc('profile');
        $this->printJs("{$src}/html/js/message_write.js");

        $edit      = new editTable($this->resId);
        $edit->SQL = "
            SELECT id,
                   `to`,
                   message
            FROM mod_profile_messages
            WHERE 1=0
        ";

        $editor_options = array(
            'attrs'   => 'style="width:525px;height:100px"',
            'options' => array(
                'width' => '525px',
                'menubar' => false,
                'toolbar' => 'bold italic removeformat | bullist numlist | alignleft aligncenter alignright alignjustify outdent indent | table | link '
            )
        );


        $edit->addControl("Кому",       'TEXT',       'style="width:315px" placeholder="Имя или email"', '', '', true);
        $edit->addControl("",           'FCK',        $editor_options);
        $edit->addControl("Прикрепить", 'XFILES_AUTO');

        $edit->setSessFormField('from',         $this->auth->NAME);
        $edit->setSessFormField('user_id',      $this->auth->ID);
        $edit->setSessFormField('location',     'outbox');
        $edit->setSessFormField('content_type', 'text/html');

        $edit->addButton("Назад", "load('{$app}')");
        $edit->save("xajax_saveSendMessage(xajax.getFormValues(this.id))");
        $edit->classText['SAVE'] = 'Отправить';

        $edit->back = $app .'&tab_profile_messages=2';
        $edit->showTable();

        return ob_get_clean();
    }
}