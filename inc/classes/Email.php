<?

require_once 'Zend/Mail.php';
require_once 'Zend/Mail/Transport/Smtp.php';

require_once 'Db.php';


/**
 * Class Email
 */
class Email {

    public $is_immediately = false;
    protected $mail_data = array(
        'from'       => '',
        'to'         => '',
        'subject'    => '',
        'body'       => '',
        'cc'         => '',
        'bcc'        => '',
        'importance' => 'NORMAL',
        'files'      => array()
    );


    /**
     * Добавление файла к письму
     *
     * @param string $content
     * @param string $name
     * @param string $mimetype
     * @param int $size
     *
     * @return $this
     */
    public function attacheFile($content, $name, $mimetype, $size) {

        $this->mail_data['files'][] = array(
            'content'   => $content,
            'name'      => $name,
            'mimetype'  => $mimetype,
            'size'      => $size
        );

        return $this;
    }


    /**
     * Вставка\получение адреса отправителя
     * @param string|array $to
     * @return $this|string|array
     */
    public function to($to = null) {

        if ($to === null) {
            return @unserialize($this->mail_data['to'])
                ? unserialize($this->mail_data['to'])
                : $this->mail_data['to'];
        } else {
            $to = is_array($to)
                ? serialize($to)
                : $to;

            $this->mail_data['to'] = $to;
            return $this;
        }
    }


    /**
     * Вставка\получение адреса адресата
     * @param string|array $from
     * @return $this|string|array
     */
    public function from($from = null) {

        if ($from === null) {
            return @unserialize($this->mail_data['from'])
                ? unserialize($this->mail_data['from'])
                : $this->mail_data['from'];
        } else {
            $from = is_array($from)
                ? serialize($from)
                : $from;

            $this->mail_data['from'] = $from;
            return $this;
        }
    }


    /**
     * Вставка\получение темы письма
     * @param string $subject
     * @return $this|string
     */
    public function subject($subject = null) {

        if ($subject === null) {
            return $this->mail_data['subject'];

        } else {
            $this->mail_data['subject'] = $subject;
            return $this;
        }
    }


    /**
     * Вставка\получение текста письма
     * @param string $body
     * @return $this|string
     */
    public function body($body = null) {

        if ($body === null) {
            return $this->mail_data['body'];

        } else {
            $this->mail_data['body'] = $body;
            return $this;
        }
    }


    /**
     * Вставка\получение вторичных получателей письма
     * @param string $cc
     * @return $this|string
     */
    public function cc($cc = null) {

        if ($cc === null) {
            return $this->mail_data['cc'];

        } else {
            $this->mail_data['cc'] = $cc;
            return $this;
        }
    }


    /**
     * Вставка\получение адресов получателей чьи адреса не нужно показывать другим получателям.
     * Каждый из получателей не будет видеть в этом поле других получателей из поля bcc
     * @param string $bcc
     * @return $this|string
     */
    public function bcc($bcc = null) {

        if ($bcc === null) {
            return $this->mail_data['bcc'];

        } else {
            $this->mail_data['bcc'] = $bcc;
            return $this;
        }
    }


    /**
     * Вставка\получение важности письма
     * @param string $importance  HIGH, NORMAL, LOW
     * @return $this|string
     */
    public function importance($importance = null) {

        if ($importance === null) {
            return $this->mail_data['importance'];

        } else {
            $this->mail_data['importance'] = $importance;
            return $this;
        }
    }


    /**
     * Сохранение в таблицу рассылки нового письма
     * @param  bool  $immediately Немедленная отправка письма
     * @return array Массив с содержимым (ok => true) или (error => текст ошибки)
     */
    public function send($immediately = false) {

        try {
            $db = new Db();

            if (empty($this->mail_data['from'])) {
                $config = Zend_Registry::get('config');
                $server = isset($config->system) && isset($config->system->host)
                    ? $config->system->host
                    : $_SERVER['SERVER_NAME'];
                $server_name = isset($config->system) && isset($config->system->name)
                    ? $config->system->name
                    : $server;

                $this->mail_data['from'] = array(
                    'noreply@' . (substr_count($server, ".") > 0 ? $server : $server . '.com'),
                    $server_name
                );

            } elseif (is_string($this->mail_data['from'])) {
                $from = explode('<', $this->mail_data['from']);
                if ( ! empty($from[1])) {
                    $this->mail_data['from'] = array(
                        trim($from[1], '<> '),
                        trim($from[0])
                    );
                }
            }

            if (is_string($this->mail_data['to'])) {
                $to = explode('<', $this->mail_data['to']);
                if ( ! empty($to[1])) {
                    $this->mail_data['to'] = array(
                        trim($to[1], '<> '),
                        trim($to[0])
                    );
                }
            }


            if ($db->isModuleActive('queue')) {
                $version = $db->getModuleVersion('queue');

                if (version_compare($version, '1.2.0', '<')) {
                    // DEPRECATED
                    $location = $db->getModuleLocation('queue');
                    require_once $location . '/ModQueueController.php';

                    $queue = new modQueueController();

                    $this->mail_data['date_send'] = $immediately
                        ? new Zend_Db_Expr('NOW()')
                        : null;

                    $queue->createEmail(
                        $this->mail_data['from'],
                        $this->mail_data['to'],
                        $this->mail_data['subject'],
                        $this->mail_data['body'],
                        $this->mail_data['cc'],
                        $this->mail_data['bcc'],
                        $this->mail_data['importance'],
                        $this->mail_data['date_send']
                    );

                    if ( ! empty($this->mail_data['files'])) {
                        foreach ($this->mail_data['files'] as $file) {
                            $queue->attacheFile($file['content'], $file['name'], $file['mimetype'], $file['size']);
                        }
                    }


                    $zend_db = Zend_Registry::get('db');
                    $zend_db->beginTransaction();
                    $mail_id = $queue->save();

                    if ( ! $mail_id || $mail_id <= 0) {
                        $zend_db->rollback();
                        throw new Exception('Ошибка добавления сообщения в очередь');

                    }
                    $zend_db->commit();

                    if ($immediately) {
                        $is_send = $this->zend_send(
                            $this->mail_data['from'],
                            $this->mail_data['to'],
                            $this->mail_data['subject'],
                            $this->mail_data['body'],
                            $this->mail_data['cc'],
                            $this->mail_data['bcc'],
                            $this->mail_data['files']
                        );

                        if ( ! $is_send) {
                            throw new Exception('Не удалось отправить сообщение');
                        }
                    }

                } else {
                    $location = $db->getModuleLocation('queue');
                    require_once $location . '/ModQueueController.php';

                    $queue = new modQueueController();

                    $this->mail_data['date_send'] = $immediately
                        ? new Zend_Db_Expr('NOW()')
                        : null;

                    $queue->createMail($this->mail_data);

                    if ($immediately) {
                        $is_send = $this->zend_send(
                            $this->mail_data['from'],
                            $this->mail_data['to'],
                            $this->mail_data['subject'],
                            $this->mail_data['body'],
                            $this->mail_data['cc'],
                            $this->mail_data['bcc'],
                            $this->mail_data['files']
                        );

                        if ( ! $is_send) {
                            throw new Exception('Не удалось отправить сообщение');
                        }
                    }
                }

            } else {
                $is_send = $this->zend_send(
                    $this->mail_data['from'],
                    $this->mail_data['to'],
                    $this->mail_data['subject'],
                    $this->mail_data['body'],
                    $this->mail_data['cc'],
                    $this->mail_data['bcc'],
                    $this->mail_data['files']
                );

                if ( ! $is_send) {
                    throw new Exception('Не удалось отправить сообщение');
                }
            }

            return array('ok' => true);

        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }


    /**
     * DEPRECATED
     * Отправка мгновенного сообщения
     * @return array Массив с содержимым (ok => true) или (error => текст ошибки)
     */
    public function sendImmediately() {

        try {
            $is_send = $this->zend_send(
                $this->mail_data['from'],
                $this->mail_data['to'],
                $this->mail_data['subject'],
                $this->mail_data['body'],
                $this->mail_data['cc'],
                $this->mail_data['bcc'],
                $this->mail_data['files']
            );

            if ( ! $is_send) {
                throw new Exception('Не удалось отправить сообщение');
            }

            return array('ok' => true);

        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }


    /**
     * Отправка письма
     *
     * @param string $from
     * @param array|string $to
     * @param string $subj
     * @param string $body
     * @param string $cc
     * @param string $bcc
     * @param array $files
     *
     * @return bool Успешна или нет отправка
     */
    private function zend_send($from, $to, $subj, $body, $cc = '', $bcc = '', $files = array()) {

        $cnf = Zend_Registry::get('config');
        $configSmtp = array();
        if (!empty($cnf->mail->port)) {
            $configSmtp['port'] = $cnf->mail->port;
        }
        if (!empty($cnf->mail->auth)) {
            $configSmtp['auth'] = $cnf->mail->auth;
        }
        if (!empty($cnf->mail->username)) {
            $configSmtp['username'] = $cnf->mail->username;
        }
        if (!empty($cnf->mail->password)) {
            $configSmtp['password'] = $cnf->mail->password;
        }
        if (!empty($cnf->mail->ssl)) {
            $configSmtp['ssl'] = $cnf->mail->ssl;
        }

        $mail = new Zend_Mail('utf-8');
        if (is_array($from)) {
            $mail->setFrom($from[0], $from[1]);
        } else {
            $mail->setFrom($from);
        }
        if (is_array($to)) {
            $mail->addTo($to[0], $to[1]);
        } else {
            $mail->addTo($to);
        }

        if (!empty($cc)) $mail->addCc($cc);
        if (!empty($bcc)) $mail->addBcc($bcc);

        $mail->setSubject($subj);
        $mail->setBodyHtml($body);


        if ( ! empty($files)) {
            foreach ($files as $file) {
                $at = $mail->createAttachment($file['content']);
                $at->type     = $file['mimetype'];
                $at->filename = $file['name'];
            }
        }

        $tr = null;
        if (!empty($cnf->mail->server)) {
            $tr = new Zend_Mail_Transport_Smtp($cnf->mail->server, $configSmtp);
        }

        return $mail->send($tr);
    }
} 