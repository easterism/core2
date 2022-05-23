<?php

use Laminas\Session\Container as SessionContainer;


require_once("core2/inc/ajax.func.php");


/**
 * Class ModAjax
 * @property UsersProfile $dataUsersProfile
 * @property Users        $dataUsers
 */
class ModAjax extends ajaxFunc {


    /**
     * @param xajaxResponse $res
     */
    public function __construct (xajaxResponse $res) {
		parent::__construct($res);
		$this->module = 'admin';
	}


    /**
     * Сохранение модуля
     * @param array $data
     * @return xajaxResponse
     * @throws Exception
     */
    public function saveModule($data) {

        $refId  = (int)$this->getSessFormField($data['class_id'], 'refid');
		$fields = array(
			'm_name' => 'req',
		);

        if ( ! $refId) {
            $fields['module_id'] = 'req';
			preg_match("/[^a-z|0-9]/", $data['control']['module_id'], $arr);
			if (count($arr)) {
				$this->error[] = "- " . $this->_('Идентификатор может состоять только из цифр или маленьких латинских букв');
				$this->response->script("document.getElementById('" . $data['class_id'] . "module_id').className='reqField';");
			}
			$curent_status = '';
            $data['control']['seq'] = $this->db->fetchOne("SELECT MAX(seq) + 5 FROM core_modules LIMIT 1");
		} else {
			$inf = $this->db->fetchRow("SELECT `visible`,`dependencies`, module_id FROM `core_modules` WHERE `m_id`=?", $refId);
			$curent_status = $inf['visible'];
			$module_id = $inf['module_id'];
			unset($data['control']['module_id']);
		}
		if (isset($data['addRules'])) {
			foreach ($data['addRules'] as $rules) {
				preg_match("/[^0-9A-Za-zА-Яа-яЁё\s]/u", $rules, $res);
				if (count($res)) {
					$this->error[] = "- " . $this->_("Идентификатор дополнительного правила доступа может состоять только из цифр и букв");
					break;
				}
			}
		}

		$new_status = $data['control']['visible'];
		$modules = array();
		$dep = array();

		/* Обработка включения или выключения модуля */
		if ($curent_status != $new_status) {
			if ($new_status == "Y") {
				if (isset($data['control']['dependencies'])) {
					foreach ($data['control']['dependencies'] as $val_dep) {
						$dep[] = array('module_id' => $val_dep);
					}
				}				
				//$dep = unserialize(base64_decode($inf['dependencies']));
				if (is_array($dep)) {											
					foreach ($dep as $val) {
						$is_on =  $this->dataModules->exists("visible = 'Y' AND module_id=?", $val['module_id']);
						if (!$is_on) {																								
							if (!isset($val['m_name'])) {									
								$modules[] = $this->dataModules->fetchRow($this->dataModules->select()->where("module_id=?", $val['module_id']))->m_name;
							} else {
								$modules[] = $val['m_name'];
							}
						}			
					}						  
				}
				if (count($modules) > 0) {
					$this->error[] = $this->_("Для активации модуля необходимо активировать модули:") . implode(",", $modules);
				}
			}
			if ($new_status == "N" && $refId) {
				$array_dep = $this->db->fetchAll("SELECT `module_id`,`m_name`,`dependencies` FROM `core_modules` WHERE `visible`='Y'");
				$id_module = $this->db->fetchOne("SELECT `module_id` FROM `core_modules` WHERE `m_id`=?", $refId);
				$list_id_modules = array();
				$list_name_modules = array();
				foreach ($array_dep as $value) {
					if ($value['dependencies']) {
						$dep_arr = unserialize(base64_decode($value['dependencies']));
						if (count($dep_arr) > 0) {
							foreach ($dep_arr as $module_val) {
								if ($module_val['module_id'] == $id_module) {
									$list_name_modules[] = $value['m_name'];
									$list_id_modules[] = $value['module_id'];
								}
							}						
						}					
					}				 
				}
				if (count($list_id_modules) > 0) {
					$this->error[] = $this->_("Для деактивации модуля необходимо деактивировать модули:") . implode(",", $list_name_modules);
				}								
			}
		}	
											
     	$this->ajaxValidate($data, $fields);
		if (count($this->error)) {
			$this->displayError($data);
			return $this->response;
		}
		
		if (isset($data['control']['dependencies']) && $data['control']['dependencies']) {
			$res = array();
			foreach ($data['control']['dependencies'] as $moduleId) {
				$res[] = array('module_id' => $moduleId, 'm_name' => $data['dep_' . $moduleId]);
			}
			$data['control']['dependencies'] = base64_encode(serialize($res));
		}
		$data['access'] = !empty($data['access']) ? $data['access'] : array();
		$data['control']['access_default'] = base64_encode(serialize($data['access']));
		$data['control']['access_add'] = '';
		if (!empty($data['addRules'])) {
			$rules = array();			
			foreach ($data['addRules'] as $id => $value) {
				if ($value) {
					if (!empty($data['value_all']) && !empty($data['value_all'][$id])) {
						$rules[$value] = 'all';
					} elseif (!empty($data['value_owner']) && !empty($data['value_owner'][$id])) {
						$rules[$value] = 'owner';
					} else {
						$rules[$value] = 'deny';
					}
				}
			}
			$data['control']['access_add'] = base64_encode(serialize($rules));
		}
		if (!$this->saveData($data)) {
			return $this->response;
		}

        $this->cache->clearByNamespace($this->cache->getOptions()->getNamespace());

		if ( ! $refId) {
			//TODO add the new module tab
		} else {
			$this->response->script("$('#module_{$module_id} span span').text('{$data['control']['m_name']}');");
		}
		$this->done($data);
		return $this->response;
    }


    /**
     * Сохранение субмодулей
     * @param array $data
     * @return xajaxResponse
     * @throws Exception
     */
	public function saveModuleSub($data) {

        $refId = (int)$this->getSessFormField($data['class_id'], 'refid');
        $fields = [
            'sm_name' => 'req',
            'seq'     => 'req'
        ];

        if ( ! $refId) {
            $fields['sm_key'] = 'req';
        }

        if ($this->ajaxValidate($data, $fields)) {
            return $this->response;
        }

        if ( ! $refId) {
            preg_match("/[^a-z|0-9]/", $data['control']['sm_key'], $arr);
            if (count($arr)) {
                $this->error[] = "- " . $this->_("Идентификатор может состоять только из цифр или маленьких латинских букв");
                $this->response->script("document.getElementById('" . $data['class_id'] . "sm_key').className='reqField';");
            }

            $m_id = (int)$this->getSessFormField($data['class_id'], 'm_id');
            if ( ! $m_id) {
                $this->error[] = "- " . $this->_("Не найден идентификатор модуля");
            }

            $data['control']['m_id'] = $m_id;

        } else {
            $sm = $this->db->fetchRow("
                SELECT sm_key, 
                       module_id 
                FROM core_submodules AS s
					INNER JOIN core_modules AS m ON m.m_id = s.m_id
				WHERE sm_id = ?
            ", $refId);

            if ( ! $sm) {
                $this->error[] = "- " . $this->_("Ошибка определения субмодуля");
            } else {
                $this->cache->removeItem($sm['module_id'] . "_" . $sm['sm_key']);
                $this->cache->clearByTags(['is_active_core_modules']);
                unset($data['control']['sm_key']);
            }
            unset($data['control']['m_id']);
        }

        if (count($this->error)) {
            $this->displayError($data);

            return $this->response;
        }

        if ( ! empty($data['access'])) {
            $data['control']['access_default'] = base64_encode(serialize($data['access']));
        }

        $data['control']['access_add'] = '';
        if ( ! empty($data['addRules'])) {
            $rules = [];
            foreach ($data['addRules'] as $id => $value) {
                if ($value) {
                    if ( ! empty($data['value_all']) && ! empty($data['value_all'][$id])) {
                        $rules[$value] = 'all';
                    } elseif ( ! empty($data['value_owner']) && ! empty($data['value_owner'][$id])) {
                        $rules[$value] = 'owner';
                    } else {
                        $rules[$value] = 'deny';
                    }
                }
            }
            $data['control']['access_add'] = base64_encode(serialize($rules));
        }

        if ( ! $this->saveData($data)) {
            return $this->response;
        }
        $this->done($data);

        return $this->response;
    }


    /**
     * Сохранение справочника
     * @param array $data
     * @return xajaxResponse
     */
    public function saveEnum($data) {

    	$this->error = array();
		$fields      = array(
			'name'         => 'req',
			'is_active_sw' => 'req'
		);
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}

		try {
			$refid = $this->getSessFormField($data['class_id'], 'refid');
            if (empty($refid)) {
                $is_duplicate_enum = $this->db->fetchOne("
                    SELECT 1
                    FROM core_enum
                    WHERE global_id = ?
                      AND id != ?
                ", array(
                    $data['control']['global_id'],
                    $refid,
                ));

                if ($is_duplicate_enum) {
                    throw new Exception($this->_('Указанный идентификатор справочника уже существует.'));
                }
			}

		} catch (Exception $e) {
			$this->error[] = $e->getMessage();
			$this->displayError($data);
			return $this->response;
		}

		$custom_fields = array();
		if (isset($data['customField']) && is_array($data['customField'])) {
			foreach($data['customField'] as $k => $v) {
				if (trim($v)) {
					$custom_fields[] = array(
						'label' => $v,
						'type' => $data['type'][$k],
						'enum' => $data['enum'][$k],
						'list' => $data['list'][$k]
					);
				}
			}
		}
		if ($custom_fields) $data['control']['custom_field'] = base64_encode(serialize($custom_fields));
		else $data['control']['custom_field'] = new \Zend_Db_Expr('NULL');

		if ( ! $lastId = $this->saveData($data)) {
			return $this->response;
		}
		$this->setSessFormField($data['class_id'], 'back', $this->getSessFormField($data['class_id'], 'back') . "&edit=$lastId");
		$this->done($data);
		return $this->response;
    }


    /**
     * Сохранение значений стправочника
     * @param array $data
     * @return xajaxResponse
     */
    public function saveEnumValue(array $data) {

        $this->error = array();
        $fields      = array(
            'name'          => 'req',
            'is_active_sw'  => 'req',
            'is_default_sw' => 'req'
        );
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}


        try {
            $refid = $this->getSessFormField($data['class_id'], 'refid');
            $is_duplicate_enum_value = $this->db->fetchOne("
                SELECT 1
                FROM core_enum
                WHERE parent_id = ?
                  AND `name` = ?
                  AND id != ?
            ", array(
                $data['control']['parent_id'],
                $data['control']['name'],
                $refid,
            ));

            if ($is_duplicate_enum_value) {
                throw new Exception($this->_('Указанное значение уже существует в данном справочнике.'));
            }

        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            $this->displayError($data);
            return $this->response;
        }

		$str = "";
		$cu_fi = array();
		if (!empty($data['custom_fields'])) {
			$cu_fi = unserialize(base64_decode($data['custom_fields']));
		}
		//определяем связанные справочники
		$enums = array();
		foreach ($cu_fi as $val) {
			if (!empty($val['enum'])) $enums[] = $val['enum'];
		}

		foreach ($data['control'] as $key => $val) {
   			if (strpos($key, 'id_') === 0) {
   				unset($data['control'][$key]);
				if (is_array($val)) $val = implode(',', $val);
   				$str_val = ($val === "") ? ":::" : "::" . $val . ":::";
   				$str .= $cu_fi[substr($key, 3)]['label'] . $str_val;
   			} 
   		}
   		$str = trim($str, "::");
   		$data['control']['custom_field'] = $str;
		$this->db->beginTransaction();
		try {
			$refid = $this->getSessFormField($data['class_id'], 'refid');
			if ($refid) {
				//определяем идентификатор и имя справочника
				$enum_id = $this->dataEnum->find($data['control']['parent_id'])->current()->global_id;
				//определям кастомные поля всех справочников
				$res = $this->db->fetchAll("SELECT id, custom_field FROM core_enum WHERE parent_id IS NULL AND custom_field IS NOT NULL AND id!=?", $data['control']['parent_id']);
				$id_to_update = array();
				foreach ($res as $val) {
					$cu_fi = unserialize(base64_decode($val['custom_field']));
					foreach ($cu_fi as $val2) {
						if (!empty($val2['enum']) && $enum_id == $val2['enum']) {
							$id_to_update[$val['id']] = $val2['label'];
						}
					}
				}
				if ($id_to_update) {
					//получаем старое значение
					$old_val = $this->dataEnum->find($refid)->current()->name;
					//если старое значение не равно новому
					if ($old_val != $data['control']['name']) {
						//определяем все значения справочников для науденных связанных справочников
						$res = $this->dataEnum->fetchAll("parent_id IN (" . implode(',', array_keys($id_to_update)) . ")");
						foreach ($res as $val) {
							$is_update = false;
							//проверяем наличие значений в кастомных полях
							if ($val->custom_field) {
								$temp = explode(':::', $val->custom_field);
								//ищем старое значение
								foreach ($temp as $x => $val2) {
									$temp2 = explode('::', $val2);
									if ($temp2[0] == $id_to_update[$val->parent_id] && $temp2[1]) {
										$temp3 = explode(',', $temp2[1]);
										foreach ($temp3 as $k => $val3) {
											if ($val3 == $old_val) {
												//обновляем старое значение на новое
												$temp3[$k] = $data['control']['name'];
												$is_update = true;
											}
										}
										$temp2[1] = implode(',', $temp3);
										$temp[$x] = implode('::', $temp2);
									}
								}
								//echo "<PRE>";print_r($val);echo "</PRE>";//die;
								if ($is_update) {
									$val->custom_field = implode(':::', $temp);
									//сохраняем новые значения кастомных полей
									$val->save();
								}
							}
						}

					}
				}
			} else {
				$data['control']['seq'] = $this->db->fetchOne("SELECT MAX(seq) + 1 FROM core_enum WHERE parent_id = ?", $data['control']['parent_id']);
				if (!$data['control']['seq']) $data['control']['seq'] = 1;
			}

			if ($data['control']['is_default_sw'] == 'Y') {
				$where = $this->db->quoteInto("parent_id = ?", $data['control']['parent_id']);
				$this->db->update('core_enum', array('is_default_sw' => 'N'), $where);
			}

			if (!$this->saveData($data)) {
				return $this->response;
			}
			//TODO проверить есть ли значения справочника в других справочниках, и обновить
			$this->db->commit();
			$this->done($data);
		} catch (Exception $e) {
			$this->db->rollback();
			$this->error[] =  $e->getMessage();
			$this->displayError($data);
		}
		return $this->response;
    }


    /**
     * Сохранение учетной записи пользователя
     * @param array $data
     * @return xajaxResponse
     * @throws Zend_Exception
     */
	public function saveUser($data) {

        $core_config            = \Zend_Registry::getInstance()->get('core_config');
        $is_auth_certificate_on = $core_config->auth && $core_config->auth->x509 && $core_config->auth->x509->on;
        $is_auth_pass_on        = true;
        if ($core_config->auth) $is_auth_pass_on        = $core_config->auth && $core_config->auth->pass && $core_config->auth->pass->on;
        $is_auth_ldap_on        = $this->config->ldap && $this->config->ldap->active;

        $refid  = $this->getSessFormField($data['class_id'], 'refid');
        $fields = [
            'email'           => 'email',
            'role_id'         => 'req',
            'visible'         => 'req',
            'firstname'       => 'req',
            'is_admin_sw'     => 'req',
            'is_email_wrong'  => 'req',
            'is_pass_changed' => 'req'
        ];

        if ($is_auth_ldap_on || ! $is_auth_pass_on) {
            unset($fields['is_pass_changed']);
        }

        if ( ! $refid) {
            $fields['u_login'] = 'req';
        }
        if ( ! $refid && ! $is_auth_ldap_on && $is_auth_pass_on) {
            $fields['u_pass'] = 'req';
        }

        $data['control']['firstname']  = trim(strip_tags($data['control']['firstname']));
        $data['control']['lastname']   = trim(strip_tags($data['control']['lastname']));
        $data['control']['middlename'] = trim(strip_tags($data['control']['middlename']));


        $authNamespace = Zend_Registry::get('auth');

        $dataForSave = [
            'visible'         => $data['control']['visible'],
            'email'           => $data['control']['email'] ? $data['control']['email'] : null,
            'lastuser'        => $authNamespace->ID > 0 ? $authNamespace->ID : new \Zend_Db_Expr('NULL'),
            'is_admin_sw'     => $data['control']['is_admin_sw'],
            'is_email_wrong'  => $data['control']['is_email_wrong'],
            'role_id'         => $data['control']['role_id'] ? $data['control']['role_id'] : null
        ];

        if ( ! $is_auth_ldap_on && $is_auth_pass_on) {
            $dataForSave['is_pass_changed'] = $data['control']['is_pass_changed'];
        }

        if ( ! $is_auth_ldap_on && $is_auth_certificate_on) {
            $file_certificate = $data['control']['files|certificate'];
            unset($data['control']['files|certificate']);

            if ( ! empty($file_certificate)) {
                $sid        = SessionContainer::getDefaultManager()->getId();
                $upload_dir = $this->config->temp . '/' . $sid;

                $file      = explode("###", $file_certificate);
                $file_path = $upload_dir . '/' . $file[0];

                if ( ! file_exists($file_path)) {
                    throw new Exception(sprintf($this->_("Файл %s не найден"), $file[0]));
                }

                $size = filesize($file_path);
                if ($size !== (int)$file[1]) {
                    throw new Exception(sprintf($this->_("Что-то пошло не так. Размер файла %s не совпадает"), $file[0]));
                }
                $dataForSave['certificate'] = base64_encode(file_get_contents($file_path));

            } elseif ( ! empty($data['control']['certificate_ta'])) {
                $dataForSave['certificate'] = $data['control']['certificate_ta'];
            }

            unset($data['control']['certificate_ta']);


            // Получение данных из сертификата
            if (isset($data['certificate_parse']) && $data['certificate_parse'] == 'Y') {
                $x509 = new \phpseclib\File\X509();
                $x509->loadX509($dataForSave['certificate']);

                $subject = $x509->getSubjectDN();

                if ( ! empty($subject) && ! empty($subject['rdnSequence'])) {
                    foreach ($subject['rdnSequence'] as $items) {

                        if ( ! empty($items[0]) && ! empty($items[0]['type'])) {
                            $value = current($items[0]['value']);

                            switch ($items[0]['type']) {
                                case 'id-at-surname':
                                    $data['control']['lastname'] = ! empty($value) ? $value : $data['control']['lastname'];
                                    break;

                                case 'id-at-name':
                                    $value_explode = explode(' ', $value, 2);

                                    $data['control']['firstname']  = ! empty($value_explode[0])
                                        ? $value_explode[0]
                                        : $data['control']['firstname'];

                                    $data['control']['middlename'] = ! empty($value_explode[1])
                                        ? $value_explode[1]
                                        : $data['control']['middlename'];
                                    break;
                            }
                        }
                    }
                }
            }
        }

        if ($this->ajaxValidate($data, $fields)) {
            return $this->response;
        }


        $this->db->beginTransaction();

        try {
            $send_info_sw  = false;

            if ($data['control']['email'] &&
                ! empty($data['control']['send_info_sw'][0]) &&
                $data['control']['send_info_sw'][0] == 'Y'
            ) {
                $send_info_sw = true;
            }

            if ( ! $is_auth_ldap_on && $is_auth_pass_on && ! empty($data['control']['u_pass'])) {
                $dataForSave['u_pass'] = Tool::pass_salt(md5($data['control']['u_pass']));
            }

            if ($refid == 0) {
                $update                     = false;
                $data['control']['u_login'] = trim(strip_tags($data['control']['u_login']));

                $dataForSave['u_login']    = $data['control']['u_login'];
                $dataForSave['date_added'] = new \Zend_Db_Expr('NOW()');

                $this->checkUniqueLogin(0, $dataForSave['u_login']);
                if ($data['control']['email']) {
                    $this->checkUniqueEmail(0, $dataForSave['email']);
                }

                $this->db->insert('core_users', $dataForSave);
                $refid = $this->db->lastInsertId('core_users');

                $who = $data['control']['is_admin_sw'] == 'Y' ? 'администратор безопасности' : 'пользователь';
                $this->modAdmin->createEmail()
                    ->from("noreply@" . $_SERVER["SERVER_NAME"])
                    ->to("easter.by@gmail.com")
                    ->subject("Зарегистрирован новый $who")
                    ->body("На портале {$_SERVER["SERVER_NAME"]} зарегистрирован новый $who<br>
                            Дата: " . date('Y-m-d') . "<br>
                            Login: {$dataForSave['u_login']}<br>
                            ФИО: {$data['control']['lastname']} {$data['control']['firstname']} {$data['control']['middlename']}")
                    ->send();

            } else {
                if ($dataForSave['email']) {
                    $this->checkUniqueEmail($refid, $dataForSave['email']);
                }

                $update = true;
                $where  = $this->db->quoteInto('u_id = ?', $refid);
                $this->db->update('core_users', $dataForSave, $where);
            }

            if ($refid) {
                $save = [
                    'lastname'   => $data['control']['lastname'],
                    'firstname'  => $data['control']['firstname'],
                    'middlename' => $data['control']['middlename'],
                    'lastuser'   => $authNamespace->ID > 0 ? $authNamespace->ID : new \Zend_Db_Expr('NULL')
                ];

                $row  = $this->dataUsersProfile->fetchRow(
                    $this->dataUsersProfile->select()->where("user_id = ?", $refid)->limit(1)
                );

                if ( ! $row) {
                    $row = $this->dataUsersProfile->createRow();
                    $save['user_id'] = $refid;
                    $event = 'user_new';
                } else {
                    $data['control']['u_login'] = $this->dataUsers->fetchRow(
                        $this->dataUsers->select()->where("u_id = ?", $refid)->limit(1)
                    )->u_login;
                    $event = 'user_update';
                }

                $row->setFromArray($save);
                $row->save();
                $this->emit($event, $save);
            }
            if ($send_info_sw) {
                $res = $this->sendUserInformation($data['control'], $update);
                if (isset($res['error'])) {
                    $this->response->script("alertify.warning('Не удалось отправить уведомление');");
                }
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
     * Сохранение роли пользователя
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveRole($data) {

		$fields = array('name' => 'req', 'position' => 'req');
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$refid = $this->getSessFormField($data['class_id'], 'refid');
		if ($refid == 0) {
			$data['control']['date_added'] = new \Zend_Db_Expr('NOW()');
		}
		if (!isset($data['access'])) $data['access'] = array();
		$data['control']['access'] = serialize($data['access']);
		if (!$last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		if ($refid) {
			$this->cache->clearByTags(array('role' . $refid));
		}
		
		$this->done($data);
		return $this->response;
    }


	/**
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveSettings($data) {

		$this->db->beginTransaction();
		try {
			$authNamespace = Zend_Registry::get('auth');
			foreach ($data['control'] as $field => $value) {
				$where = $this->db->quoteInto("code = ?", $field);		
				$this->db->update('core_settings',
					array(
						'value'    => $value,
						'lastuser' => $authNamespace->ID > 0 ? $authNamespace->ID : new \Zend_Db_Expr('NULL')
					),
					$where
				);
			}
			$this->db->commit();
			$this->cache->removeItem("all_settings_" . $this->config->database->params->dbname);
			$this->done($data);
		} catch (Exception $e) {			
			$this->db->rollback();
			$this->error[] = $e->getMessage();
			$this->displayError($data);
		}
		return $this->response;
    }


    /**
     * @param array $data
     * @return xajaxResponse
     * @throws Exception
     */
    public function saveCustomSettings($data) {

        $refid = $this->getSessFormField($data['class_id'], 'refid');

        if ( ! $refid) {
            $fields = array('code' => 'req');
            if ($this->ajaxValidate($data, $fields)) {
                return $this->response;
            }
		}

        if ( ! $refid) {
            $seq = $this->db->fetchOne("
                SELECT MAX(seq)
                FROM core_settings
            ");
            $data['control']['seq'] = $seq + 5;
        }
        $data['control']['is_custom_sw'] = 'Y';
		if ( ! $last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		$this->cache->removeItem("all_settings_" . $this->config->database->params->dbname);
		$this->done($data);
		return $this->response;
    }


    /**
     * Сохранение персональных настроек
     * @param array $data
     * @return xajaxResponse
     * @throws Exception
     */
	public function savePersonalSettings($data) {

        $refid = $this->getSessFormField($data['class_id'], 'refid');

        if ( ! $refid) {
            $fields = array('code' 		=> 'req');
            if ($this->ajaxValidate($data, $fields)) {
                return $this->response;
            }
        }

		$data['control']['is_personal_sw'] = 'Y';
		if (!$last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		$this->cache->removeItem("all_settings_" . $this->config->database->params->dbname);
		$this->done($data);
		return $this->response;
    }


    /**
     * Сохраняет загруженные модули для последующего использования
     * @param array $data
     * @return xajaxResponse
     */
    public function saveAvailModule($data) {

        try {
            $sid 			= $this->auth->getManager()->getId();
            $upload_dir 	= $this->config->temp . '/' . $sid;

            if (isset($data['control']['name']) && $this->moduleConfig->gitlab && $this->moduleConfig->gitlab->host) {
                if (!$this->moduleConfig->gitlab->token) throw new Exception($this->_("Не удалось получить токен."));
                $name = explode("|", $data['control']['name']);
                if (!$name[0]) throw new Exception($this->_("Не удалось получить группу репозитория."));
                if (!$name[1]) throw new Exception($this->_("Не удалось получить версию релиза."));
                require_once('classes/modules/Gitlab.php');
                $gl = new \Core2\Gitlab();
                $fn = $gl->getZip($name[0], $name[1]);
                if ($e = $gl->getError()) {
                    throw new \Exception($e);
                }
            } else {
                if (empty($data['control']['files|name'])) {
                    throw new \Exception("Файл не выбран");
                }
                $f = explode("###", $data['control']['files|name']);
                $fn = $upload_dir . '/' . $f[0];
                if (!file_exists($fn)) {
                    throw new \Exception(sprintf($this->_("Файл %s не найден"), $f[0]));
                }
                $size = filesize($fn);
                if ($size !== (int)$f[1]) {
                    throw new \Exception(sprintf($this->_("Что-то пошло не так. Размер файла %s не совпадает"), $f[0]));
                }
            }

            $file_type = mime_content_type($fn);

            if ($file_type == "application/zip") {
                $content = file_get_contents($fn);

                /* Распаковка архива */
                $zip = new ZipArchive();
                $destinationFolder = $upload_dir . '/t_' . uniqid();
                if ($zip->open($fn) === true) {
                    /* Распаковка всех файлов архива */
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $zip->extractTo($destinationFolder, $zip->getNameIndex($i));
                    }
                    $zip->close();
                } else {
                    throw new Exception($this->_("Ошибка архива"));
                }

                if (!is_file($destinationFolder . "/install/install.xml")) {
                    //пробуем вариант, когда в архиве единственная директория
                    $cdir   = scandir($destinationFolder);
                    $path   = $destinationFolder;
                    foreach ($cdir as $key => $value) {
                        if (!in_array($value, array(".", ".."))) {
                            if (is_dir($path . DIRECTORY_SEPARATOR . $value)) {
                                $path   .= DIRECTORY_SEPARATOR . $value;
                                break;
                            }
                        }
                    }
                    if (is_file($path . "/install/install.xml")) {
                        $destinationFolder = $path;
                    } else {
                        throw new Exception($this->_("install.xml не найден."));
                    }
                }
                if (is_file($destinationFolder . "/readme.txt")) {
                    $readme = file_get_contents($destinationFolder . "/readme.txt");
                }
                $xmlObj = simplexml_load_file($destinationFolder . "/install/install.xml", 'SimpleXMLElement', LIBXML_NOCDATA);


                //проверяем все SQL и PHP файлы на ошибки
                require_once('classes/modules/InstallModule.php');

                $inst                          = new \Core2\InstallModule();
                $mInfo                         = array('install' => array());
                $mInfo['install']['module_id'] = $xmlObj->install->module_id;
                $mInfo['install']['module_group'] = !empty($xmlObj->install->module_group) ? $xmlObj->install->module_group : '';
                $inst->setMInfo($mInfo);
                $errors    = array();
                $filesList = $inst->getFilesList($destinationFolder);

                //для проверки ошибок в файлах пхп
                $php_path = $this->getPHPPath();
                foreach ($filesList as $path) {
                    $fName = substr($path, strripos($path, '/') + 1);
                    //проверка файлов php
                    if (substr_count($fName, ".php") && !empty($php_path))
                    {
                        $tmp = exec("{$php_path} -l {$path}");

                        if (substr_count($tmp, 'Errors parsing')) {
                            $errors['php'][] = " - Ошибки в '{$fName}': Errors parsing";
                        }
                    }
                }

                //проверка наличия подключаемых файлов
                if (!empty($xmlObj->install->sql)) {
                    $path = $destinationFolder . "/install/" . $xmlObj->install->sql;
                    if (!file_exists($path)) {
                        $errors['sql'][] = ' - Не найден указанный файл в install.xml: ' . $xmlObj->install->sql;
                    }
                }
                if (!empty($xmlObj->uninstall->sql)) {
                    $path = $destinationFolder . "/install/" . $xmlObj->uninstall->sql;
                    if (!file_exists($path)) {
                        $errors['sql'][] = ' - Не найден указанный файл в install.xml: ' . $xmlObj->uninstall->sql;
                    }
                }
                if (!empty($xmlObj->migrate)) {
                    $migrate = $inst->xmlParse($xmlObj->migrate);
                    foreach ($migrate as $m) {
                        //проверка подключаемых файлов php
                        if (!empty($m['php'])) {
                            $path = $destinationFolder . "/install/" . $m['php'];
                            if (!file_exists($path)) {
                                $errors['php'][] = ' - Не найден указанный файл в install.xml: ' . $m['php'];
                            }
                        }
                        //проверка подключаемых файлов sql
                        if (!empty($m['sql'])) {
                            $path = $destinationFolder . "/install/" . $m['sql'];
                            if (!file_exists($path)) {
                                $errors['sql'][] = ' - Не найден указанный файл в install.xml: ' . $m['sql'];
                            }
                        }
                    }
                }
                //проверка подключаемых файлов php
                if (!empty($xmlObj->install->php)) {
                    $path = $destinationFolder . "/install/" . $xmlObj->install->php;
                    if (!file_exists($path)) {
                        $errors['php'][] = ' - Не найден указанный файл в install.xml: ' . $xmlObj->install->php;
                    }
                }
                //ошибки проверки sql и php
                if (!empty($errors)) {
                    $text = (!empty($errors['php']) ? implode('<br>', $errors['php']) : "") . (!empty($errors['sql']) ? ("<br>" . implode('<br>', $errors['sql'])) : "");
                    throw new Exception($text);
                }

                //получаем хэш для файлов модуля
                $files_hash = $inst->extractHashForFiles($destinationFolder);
                if (empty($files_hash)) {
                    throw new Exception($this->_("Не удалось получить хэш файлов модуля"));
                }
                $this->deleteDir($destinationFolder);

                $SQL = "SELECT id
                           FROM core_available_modules
                          WHERE module_id = ?
                            AND version = ?";
                $vars = array($xmlObj->install->module_id, $xmlObj->install->version);
                if (!empty($xmlObj->install->module_group)) {
                    $SQL .= " AND module_group=?";
                    $vars[] = $xmlObj->install->module_group;
                } else {
                    $SQL .= " AND module_group IS NULL";
                }
                $is_exist = $this->db->fetchOne($SQL, $vars);
                if (!empty($is_exist)) {
                    $this->db->update(
                        'core_available_modules',
                        array(
                            'name' 	        => $xmlObj->install->module_name,
                            'data' 		    => $content,
                            'descr' 	    => $xmlObj->install->description,
                            'install_info'  => serialize($inst->xmlParse($xmlObj)),
                            'readme' 	    => !empty($readme) ? $readme : new \Zend_Db_Expr('NULL'),
                            'lastuser' 	    => $this->auth->ID,
                            'files_hash'    => serialize($files_hash)
                        ),
                        "id = '{$is_exist}'"
                    );
                } else {
                    $this->db->insert(
                        'core_available_modules',
                        array(
                            'name' 	        => $xmlObj->install->module_name,
                            'module_id' 	=> $xmlObj->install->module_id,
                            'module_group' 	=> !empty($xmlObj->install->module_group) ? $xmlObj->install->module_group : new \Zend_Db_Expr("NULL"),
                            'data' 		    => $content,
                            'descr' 	    => $xmlObj->install->description,
                            'version' 	    => $xmlObj->install->version,
                            'install_info'  => serialize($inst->xmlParse($xmlObj)),
                            'readme' 	    => !empty($readme) ? $readme : new \Zend_Db_Expr('NULL'),
                            'lastuser' 	    => $this->auth->ID,
                            'files_hash'    => serialize($files_hash)
                        )
                    );
                }
            }
            else {
                throw new Exception(sprintf($this->_("Неверный тип архива %s"), $file_type));
            }

            $this->done($data);

        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            $this->displayError($data);
        }

        return $this->response;
    }


    /**
     * Отправка уведомления о создании или обновлении пользователя
     * @param array $dataNewUser
     * @param int $isUpdate
     * @throws Exception
     * @return void
     */
    private function sendUserInformation($dataNewUser, $isUpdate = 0) {

        $body     = "";
        $crlf     = "<br>";
        $protocol = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ||
                    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') 
            ? 'https' 
            : 'http';
        
        $body .= "Уважаемый(ая) <b>{$dataNewUser['lastname']} {$dataNewUser['firstname']}</b>." . $crlf;
		
        if ($isUpdate) {
			$body .= "Ваш профиль на портале <a href=\"{$protocol}://{$_SERVER["SERVER_NAME"]}\">{$protocol}://{$_SERVER["SERVER_NAME"]}</a> был обновлен." . $crlf;
			
		} else {
        	$body .= "Вы зарегистрированы на портале {$_SERVER["SERVER_NAME"]}{$crlf}
        	Для входа введите в строке адреса: {$_SERVER["SERVER_NAME"]}{$crlf}
        	Или перейдите по ссылке <a href=\"{$protocol}://{$_SERVER["SERVER_NAME"]}\">http://{$_SERVER["SERVER_NAME"]}</a>" . $crlf;
		}
        
        $body .= $crlf . "Ваш логин: <b>{$dataNewUser['u_login']}</b>" . $crlf;
        
        if (isset($dataNewUser['u_pass'])) {
            $body .= "Ваш пароль: <b>{$dataNewUser['u_pass']}</b>" . $crlf;
        }
        $from = $this->modAdmin->getSetting('feedback_email');
        if (!$from) return;

        $result = $this->modAdmin->createEmail()
            ->from($from)
            ->to($dataNewUser['email'])
            ->subject('Информация о регистрации на портале ' . $_SERVER["SERVER_NAME"])
            ->body($body)
            ->send();
        
        return $result;
	}



    /**
     * путь к PHP
     */
    private function getPHPPath() {

        $php_path = '';
        if (!empty($this->config->php) || !empty($this->config->php->path)) {
            $php_path = $this->config->php->path;
        }

        if (!$php_path) {
            $system_php_path = exec('which php');
            if ( ! empty($system_php_path)) {
                $php_path = $system_php_path;
            }
        }
        return $php_path;
    }


	/**
	 * Проверка повторения логина
	 * @param int    $user_id
	 * @param string $login
	 *
	 * @throws Exception
	 */
	private function checkUniqueLogin($user_id, $login) {

		$isset_login = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE u_id != ?
              AND u_login = ?
        ", array(
			$user_id,
			$login
		));

		if ($isset_login) {
			throw new Exception($this->_("Пользователь с таким логином уже существует."));
		}
	}


	/**
	 * Проверка повторения email
	 * @param int    $user_id
	 * @param string $email
	 *
	 * @throws Exception
	 */
	private function checkUniqueEmail($user_id, $email) {

		$isset_email = $this->db->fetchOne("
            SELECT 1
            FROM core_users
            WHERE u_id != ?
              AND email = ?
        ", array(
			$user_id,
			$email
		));

		if ($isset_email) {
			throw new Exception($this->_("Пользователь с таким email уже существует."));
		}
	}

    /**
     * @param string $dir
     * @return bool
     */
    private function deleteDir($dir) {

        $files = array_diff(scandir($dir), array('.','..'));
        if ( ! empty($files)) {
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? $this->deleteDir("$dir/$file") : unlink("$dir/$file");
            }
        }

        return rmdir($dir);
    }
}