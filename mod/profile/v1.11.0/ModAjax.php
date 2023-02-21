<?php

require_once DOC_ROOT . "core2/inc/ajax.func.php";


/**
 * Class ModAjax
 * @property ModProfileController $modProfile
 */
class ModAjax extends ajaxFunc {


    /**
     * @param $data
     * @return xajaxResponse
     * @throws Zend_Db_Adapter_Exception
     */
    public function axSaveSettings($data) {

		foreach ($data['control'] as $code => $value) {
            $isset_code = $this->db->fetchOne("
                SELECT 1
                FROM mod_profile_user_settings
                WHERE code = ?
                  AND user_id = ?
            ", array(
                $code,
                $this->auth->ID
            ));

            if ($isset_code) {
                $where = $this->db->quoteInto('user_id = ?', $this->auth->ID);
                $this->db->update('mod_profile_user_settings', array(
                    'value' => $value,
                    'code'  => $code
                ), $where);

            } else {
                $this->db->insert('mod_profile_user_settings', array(
                    'value'   => $value,
                    'code'    => $code,
                    'user_id' => $this->auth->ID
                ));
            }
		}
				
		$this->done($data);
		return $this->response;
    }


	/**
	 * Обновление профиля пользователя
	 * @param array $data
	 * @return mixed
	 */
	public function axSaveProfile($data) {
		$fields = array(
			'firstname' 	=> 'req',
     		'lastname' 		=> 'req',
     		'email' 		=> 'req,email',
		);
		if (isset($data['control']['u_pass']) && !$this->auth->LDAP) {
			$fields['u_pass'] = 'req';
		}
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$this->db->beginTransaction();
		try {
			//if email exists
			$checkEmail = $this->db->fetchOne("SELECT 1 FROM core_users WHERE u_id != ? AND email = ? LIMIT 1", array($this->auth->ID, $data['control']['email']));
			if ($checkEmail) {
				$this->error[] = 'Email "' . $data['control']['email'] . '" принадлежит другому пользователю системы.';
				$this->displayError($data);
				return $this->response;
			}
			$currentUser = $this->db->fetchRow("SELECT email, u_pass FROM core_users WHERE u_id = ? LIMIT 1", $this->auth->ID);
			if ($currentUser['email'] != $data['control']['email']) {
				$this->sendUserInformation(array(
					'login' => $this->auth->NAME,
					'old' => array(
						'email' => $currentUser['email']
					),
					'new' => array(
						'email' => $data['control']['email']
					))
				);
			}


			$where = $this->db->quoteInto('user_id = ?', $this->auth->ID);
			$this->db->update('core_users_profile',
				array(
					'firstname' => $data['control']['firstname'],
					'lastname' => $data['control']['lastname'],
					'middlename' => $data['control']['middlename']
				), $where);
			$where = $this->db->quoteInto('u_id = ?', $this->auth->ID);
			$this->db->update('core_users', array('email' => $data['control']['email']), $where);

			unset($data['control']['email']);
			if (!empty($data['control']['u_pass']) && !$this->auth->LDAP) {
				$newPass = Tool::pass_salt($data['control']['u_pass']);
				$u = array('u_pass' => $newPass);
				if ($currentUser['u_pass'] != $newPass) {
					$u['is_pass_changed'] = 'Y';
				}
				$this->db->update('core_users', $u, $where);
			}
			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollback();
			$this->error[] = $e->getMessage();
		}

		$this->done($data);
		return $this->response;
    }


    /**
     * Сохраниение настроек
     * @param array $data
     * @return xajaxResponse
     */
    public function axSaveMessagesSettings($data) {

        if ($this->ajaxValidate($data, array())) {
            return $this->response;
        }

        $mc = $this->modProfile->dataProfileMessagesSettings;

        // Сервер
        if ($mc->exists($this->auth->ID, 'mail_server')) {
            $row = $mc->fetchRow($mc->select()->where("name = 'mail_server' AND user_id = ?", $this->auth->ID));
            $row->user_id = $this->auth->ID;
            $row->value   = $data['control']['mail_server'];
            $row->save();

        } else {
            $mc->insert(array(
                'name'    => 'mail_server',
                'value'   => $data['control']['mail_server'],
                'user_id' => $this->auth->ID
            ));
        }

        // Логин
        if ($mc->exists($this->auth->ID, 'login')) {
            $row = $mc->fetchRow($mc->select()->where("name = 'login' AND user_id = ?", $this->auth->ID));
            $row->user_id = $this->auth->ID;
            $row->value   = $data['control']['login'];
            $row->save();

        } else {
            $mc->insert(array(
                'name'    => 'login',
                'value'   => $data['control']['login'],
                'user_id' => $this->auth->ID
            ));
        }

        // Пароль
        if (isset($data['control']['password']) && $data['control']['password'] != '') {
            if ($mc->exists($this->auth->ID, 'password')) {
                $row = $mc->fetchRow($mc->select()->where("name = 'password' AND user_id = ?", $this->auth->ID));
                $row->user_id = $this->auth->ID;
                $row->value   = $data['control']['password'];
                $row->save();

            } else {
                $mc->insert(array(
                    'name'    => 'password',
                    'value'   => $data['control']['password'],
                    'user_id' => $this->auth->ID
                ));
            }
        }

        // Порт
        if (isset($data['control']['port']) && $data['control']['port'] != '') {
            if ($mc->exists($this->auth->ID, 'port')) {
                $row = $mc->fetchRow($mc->select()->where("name = 'port' AND user_id = ?", $this->auth->ID));
                $row->user_id = $this->auth->ID;
                $row->value   = $data['control']['port'];
                $row->save();

            } else {
                $mc->insert(array(
                    'name'    => 'port',
                    'value'   => $data['control']['port'],
                    'user_id' => $this->auth->ID
                ));
            }
        }

        // SSL
        if (isset($data['control']['encryption']) && $data['control']['encryption'] != '') {
            if ($mc->exists($this->auth->ID, 'encryption')) {
                $row = $mc->fetchRow($mc->select()->where("name = 'encryption' AND user_id = ?", $this->auth->ID));
                $row->user_id = $this->auth->ID;
                $row->value   = $data['control']['encryption'];
                $row->save();

            } else {
                $mc->insert(array(
                    'name'    => 'encryption',
                    'value'   => $data['control']['encryption'],
                    'user_id' => $this->auth->ID
                ));
            }
        }

        $this->done($data);

        return $this->response;
    }


    /**
     * Отправки нового сообщения
     * @param array $data
     * @return xajaxResponse
     */
    public function axSaveSendMessage($data) {

        try {
            $fields = array(
                'to' => 'req'
            );
            if ($this->ajaxValidate($data, $fields)) {
                return $this->response;
            }

            $data['control']['from']         = $this->auth->NAME;
            $data['control']['user_id']      = $this->auth->ID;
            $data['control']['location']     = 'outbox';
            $data['control']['content_type'] = 'text/html';


            if (strpos($data['control']['to'], '@') >= 1) {
                $message_id = $this->saveData($data, false);
                //$this->modWebservice;

                if ($message_id) {
                    // отправка сообщения потльзователю через сервис
                    if (0) {

                        // отправка сообщения на email
                    } else {
                        preg_match('~([a-z0-9\._\-]+@[a-z0-9\._\-]+)~', $data['control']['to'], $match);
                        $user_email_from = $this->db->fetchOne("
                            SELECt email
                            FROM core_users
                            WHERE u_id = ?
                        ", $this->auth->ID);

                        $email = $this->modAdmin->createEmail()->from($user_email_from)->to($match[1])->subject('core - Сообщение от ' . $this->auth->NAME)->body($data['control']['message']);

                        $message_files = $this->db->fetchAll("
                            SELECT content,
                                   filename,
                                   filesize,
                                   `type`
                            FROM mod_profile_messages_files
                            WHERE refid = ?
                        ", $message_id);

                        if ( ! empty($message_files)) {
                            foreach ($message_files as $file) {
                                $email->attacheFile($file['content'], $file['filename'], $file['type'], $file['filesize']);
                            }
                        }

                        if ( ! $email->send()) throw new Exception("Ошибка отправки сообщения");
                    }
                }

            // Отправка сообщения группе
            } elseif (strpos($data['control']['to'], '@') === 0) {

                if (in_array(mb_strtolower($data['control']['to']), ['@все', '@all'])) {
                    $users = $this->db->fetchCol("
                        SELECT u_id
                        FROM core_users 
                        WHERE visible = 'Y'
                          AND u_id != ?
                    ", $this->auth->ID);

                    if (empty($users)) {
                        throw new Exception("В системе нет активных пользователей для отправки им сообщения.");
                    }

                } else {
                    $role_name = substr($data['control']['to'], 1);
                    $role_id   = $this->db->fetchOne("
                        SELECT id
                        FROM core_roles 
                        WHERE is_active_sw = 'Y'
                          AND name = ?
                    ", $role_name);

                    if (empty($role_id)) {
                        throw new Exception("Указанная роль не найдена, либо она не активна");
                    }

                    $users = $this->db->fetchCol("
                        SELECT u_id
                        FROM core_users 
                        WHERE role_id = ?
                          AND u_id != ?
                    ", [
                        $role_id,
                        $this->auth->ID
                    ]);

                    if (empty($users)) {
                        throw new Exception("В указанной роли нет пользователей для отправки им сообщений");
                    }
                }


                $message_id = $this->saveData($data, false);

                if ($message_id) {
                    foreach ($users as $user_id) {

                        try {
                            $this->db->beginTransaction();

                            $is_copy_message = $this->db->query("
                                INSERT INTO mod_profile_messages (
                                  `from`,
                                  `to`,
                                  message,
                                  location,
                                  user_id,
                                  is_read,
                                  content_type
                                ) SELECT m.`from`,
                                         m.`to`,
                                         m.message,
                                         'inbox' AS location,
                                         ?       AS user_id,
                                         'N'     AS is_read,
                                         m.content_type
                                  FROM mod_profile_messages AS m
                                  WHERE m.id = ?
                            ", array(
                                $user_id,
                                $message_id
                            ));


                            $message_id_to = $this->db->LastInsertId();

                            if ( ! $is_copy_message || $message_id_to <= 0) {
                                throw new Exception('Не удалось сохранить сообщение для адресата');

                            } elseif ($data['control']['files|'] != '') {
                                $is_copy_files = $this->db->query("
                                    INSERT INTO mod_profile_messages_files (
                                      content,
                                      refid,
                                      filename,
                                      filesize,
                                      hash,
                                      `type`,
                                      fieldid,
                                      thumb
                                    ) SELECT f.content,
                                             ? AS refid,
                                             f.filename,
                                             f.filesize,
                                             f.hash,
                                             f.`type`,
                                             f.fieldid,
                                             f.thumb
                                      FROM mod_profile_messages_files AS f
                                      WHERE f.refid = ?
                                ", array(
                                    $message_id_to,
                                    $message_id
                                ));

                                if ( ! $is_copy_files) {
                                    throw new Exception('Не удалось сохранить сообщение для адресата. Ошибка сохранения файлов');
                                }
                            }

                            $this->db->commit();

                        } catch (Exception $e) {
                            $this->db->rollback();
                            throw new Exception($e->getMessage());
                        }
                    }

                } else {
                    throw new Exception('Не удалось сохранить сообщение');
                }


            // отправка сообщения пользователю внутри системы
            } else {
                $user_id_to = $this->modProfile->getUserId($data['control']['to']);

                if ($user_id_to) {
                    $message_id = $this->saveData($data, false);

                    if ($message_id) {
                        try {
                            $this->db->beginTransaction();

                            $is_copy_message = $this->db->query("
                                INSERT INTO mod_profile_messages (
                                  `from`,
                                  `to`,
                                  message,
                                  location,
                                  user_id,
                                  is_read,
                                  content_type
                                ) SELECT m.`from`,
                                         m.`to`,
                                         m.message,
                                         'inbox' AS location,
                                         ?       AS user_id,
                                         'N'     AS is_read,
                                         m.content_type
                                  FROM mod_profile_messages AS m
                                  WHERE m.id = ?
                            ", array(
                                $user_id_to,
                                $message_id
                            ));


                            $message_id_to = $this->db->LastInsertId();

                            if ( ! $is_copy_message || $message_id_to <= 0) {
                                throw new Exception('Не удалось сохранить сообщение для адресата');

                            } elseif ($data['control']['files|'] != '') {
                                $is_copy_files = $this->db->query("
                                    INSERT INTO mod_profile_messages_files (
                                      content,
                                      refid,
                                      filename,
                                      filesize,
                                      hash,
                                      `type`,
                                      fieldid,
                                      thumb
                                    ) SELECT f.content,
                                             ? AS refid,
                                             f.filename,
                                             f.filesize,
                                             f.hash,
                                             f.`type`,
                                             f.fieldid,
                                             f.thumb
                                      FROM mod_profile_messages_files AS f
                                      WHERE f.refid = ?
                                ", array(
                                    $message_id_to,
                                    $message_id
                                ));

                                if ( ! $is_copy_files) {
                                    throw new Exception('Не удалось сохранить сообщение для адресата');
                                }
                            }

                            $this->db->commit();

                        } catch (Exception $e) {
                            $this->db->rollback();
                            throw new Exception($e->getMessage());
                        }

                    } else {
                        throw new Exception('Не удалось сохранить сообщение');
                    }

                } else {
                    throw new Exception('Нет такого пользователя');
                }
            }


        } catch (Exception $e) {
            $this->error[] =  $e->getMessage();
            $this->displayError($data);
        }

        $this->done($data);
        return $this->response;
    }


    /**
     * Отправка уведомления о изменении профиля
     * @param array $data
     * @throws Exception
     */
    private function sendUserInformation($data) {

        $body  = "Пользователь <b>{$data['login']}</b> изменил email на ресурсе {$_SERVER["SERVER_NAME"]}<br/>";
        $body .= "Старый email: {$data['old']['email']}<br/>";
        $body .= "Новый email: {$data['new']['email']}<br/>";

        $this->modAdmin->createEmail()
            ->to('easter.by@gmail.com')
            ->from('informer@' . $_SERVER["SERVER_NAME"])
            ->subject('Профиль изменен на ресурсе ' . $_SERVER["SERVER_NAME"])
            ->body($body)
            ->send();
    }
}