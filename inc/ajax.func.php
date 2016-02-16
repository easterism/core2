<?php

require_once 'classes/Common.php';
require_once 'classes/Image.php';


/**
 * Class ajaxFunc
 */
class ajaxFunc extends Common {

    /**
     * A prefix for all ajax functions to aid automatic xajax registration
     */
	protected $error = array();
	protected $response;
	protected $script;
	protected $userId;
	private $image;
	private $orderFields;

    /**
     * Do things
     */
    public function __construct (xajaxResponse $res) {
	    $this->response = $res;
    	parent::__construct();
    }

    
    /**
     * Validate function
     *
     * @param array $data
     * @param string $fields
     * @return bool
     */
	protected function ajaxValidate($data, $fields) {

		$class_id = $data['class_id'];
		$order_fields = $this->getSessForm($class_id);

		$control  = $data['control']; //данные полей формы
		$script   = "for (var i = 0; i < document.getElementById('{$class_id}_mainform').elements.length; i++) {if(document.getElementById('{$class_id}_mainform').elements[i].className=='reqField')document.getElementById('{$class_id}_mainform').elements[i].className='input'};";
		$req      = array();
		$email    = array();
		$date     = array();
        $datetime = array();
		$float    = array();
		$int      = array();
		$host     = array();
		$phone    = array();
		
		foreach ($control as $field => $val) {
			if (!is_array($val)) {
				$control[$field] = trim($val);
				if (isset($control[$field . "%re"]) && $val !== $control[$field . "%re"]) {
					$script .= "document.getElementById('" . $class_id . $field . "2').className='reqField';";
					$this->error[] = "- {$this->translate->tr('Пароль не совпадает.')}<br/>";
				}
				else if (isset($control[$field . "%tru"]) && $val > $control[$field . "%tru"]) {
					$script .= "document.getElementById('" . $class_id . $field . "%tru_day').className='reqField';";
					$script .= "document.getElementById('" . $class_id . $field . "%tru_month').className='reqField';";
					$script .= "document.getElementById('" . $class_id . $field . "%tru_year').className='reqField';";
					$this->error[] = "- {$this->translate->tr('Дата начала больше даты окончания.')}<br/>";
				}
				else if (substr($field, 0, 6) == 'files|' && $val) {
					$files = explode("|", trim($val, "|"));
					if (count($files)) {
						try {
							$this->db->fetchOne("SELECT 1 FROM `" . trim($order_fields['table']) . "_files`");
						} catch (Zend_Db_Exception $e) {
							$this->error[] = $e->getMessage() . "<br/>";
						}
					}
				}
			}
		}

		foreach ($fields as $field => $val) {
			if (!isset($control[$field])) continue;
			$params = explode(",", $val);			
			if (in_array("req", $params) && (is_null($control[$field]) || $control[$field] === false || $control[$field] === '' || (is_array($control[$field]) && !$control[$field]) )) {
				$req[] = $field;
			}
			if ($control[$field]) {
				if (in_array("email", $params)) {
					$email[] = $field;
				} elseif (in_array("date", $params)) {
					$date[] = $field;
				} elseif (in_array("datetime", $params)) {
                    $datetime[] = $field;
				} elseif (in_array("float", $params)) {
					$float[] = $field;
				} elseif (in_array("int", $params)) {
					$int[] = $field;
				} elseif (in_array("host", $params)) {
					$host[] = $field;
				} elseif (in_array("phone", $params)) {
					$phone[] = $field;
				}
				
				if (in_array("md5", $params)) {
					$script .= "document.getElementById('" . $class_id . $field . "').value = '" . $control[$field] . "';";
				}
			}
		}
		if (count($req)) {
			foreach ($req as $val) {
				$script .= "document.getElementById('" . $class_id . $val . "').className='reqField';";
			}
			$this->error[] = "- {$this->translate->tr('Пожалуйста, заполните обязательные поля.')}<br/>";
		}
		
		if (count($email)) {
			require_once("Zend/Validate/EmailAddress.php");
			$validator = new Zend_Validate_EmailAddress();
			foreach ($email as $field) {
				if (!$validator->isValid($control[$field])) {
				    // email is invalid; print the reasons
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				}
			}
		}
		
		if (count($float)) {
			require_once("Zend/Validate/Float.php");
			$validator = new Zend_Validate_Float();
			foreach ($float as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				}
			}
		}

		/*if (count($date)) {
			require_once("Zend/Validate/Date.php");
			$validator = new Zend_Validate_Date();
			foreach ($date as $field) {
				if ( ! $validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				}
			}
		}

		if (count($datetime)) {
			foreach ($datetime as $field) {
				if ( ! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}(|:{1}\d{2}))$/', $control[$field])) {
                    $this->error[] = "- Неверный формат даты";
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				} else {
                    list($year, $month, $day) = sscanf($control[$field], '%d-%d-%d');
                    if ( ! checkdate($month, $day, $year)) {
                        $this->error[] = "- Некорректная дата";
                        $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
                    }
                }
			}
		}*/
		
		if (count($int)) {
			require_once("Zend/Validate/Int.php");
			$validator = new Zend_Validate_Int();
			foreach ($int as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				}
			}
		}
		
		if (count($phone)) {
			$pattern = '/((?!:\A|\s)(?!(\d{1,6}\s+\D)|((\d{1,2}\s+){2,2}))(((\+\d{1,3})|(\(\+\d{1,3}\)))\s*)?((\d{1,6})|(\(\d{1,6}\)))\/?(([ -.]?)\d{1,5}){1,5}((\s*(#|x|(ext))\.?\s*)\d{1,5})?(?!:(\Z|\w|\b\s)))/';
			foreach ($phone as $field) {
				//TODO do this
			}
		}
		
		if (count($host)) {
			require_once("Zend/Validate/Hostname.php");
			$validator = new Zend_Validate_Hostname();
			$validator->setMessage('Недопустимый тип данных, значение должно быть строкой', Zend_Validate_Hostname::INVALID);
			$validator->setMessage("Значение '%value%' выглядит как IP-адрес, но IP-адреса не разрешены", Zend_Validate_Hostname::IP_ADDRESS_NOT_ALLOWED);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но оно не дожно быть из списка доменов верхнего уровня", Zend_Validate_Hostname::UNKNOWN_TLD);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но знак '-' находится в недопустимом месте", Zend_Validate_Hostname::INVALID_DASH);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но оно не соответствует шаблону для доменных имен верхнего уровня '%tld%'", Zend_Validate_Hostname::INVALID_HOSTNAME_SCHEMA);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но не удаётся извлечь домен верхнего уровня", Zend_Validate_Hostname::UNDECIPHERABLE_TLD);
			$validator->setMessage("Значение '%value%' не соответствует ожидаемой структуре для DNS имени хоста", Zend_Validate_Hostname::INVALID_HOSTNAME);
			$validator->setMessage("Значение '%value%' является недопустимым локальным сетевым адресом", Zend_Validate_Hostname::INVALID_LOCAL_NAME);
			$validator->setMessage("Значение '%value%' выглядит как локальный сетевой адрес, но локальные сетевые адреса не разрешены", Zend_Validate_Hostname::LOCAL_NAME_NOT_ALLOWED);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но указанное значение не может быть преобразованно в допустимый для DNS набор символов", Zend_Validate_Hostname::CANNOT_DECODE_PUNYCODE);
			
			foreach ($host as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $class_id . $field . "').className='reqField';";
				}
			}
		}
		
		
		$this->script = $script;
		if (count($this->error)) {
			$this->displayError($data);
			$this->response->script($script);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Отображение ошибок для xajax запросов
	 * @param $data
	 */
	protected function displayError($data) {
		$class_id = $data['class_id'];
    	$this->response->assign($class_id . "_error", "innerHTML", '<a name="' . $class_id . '_error"> </a>' . implode("<br/>", $this->error));
		$this->response->assign($class_id . "_error", "style.display", 'block');
		$this->response->script("toAnchor('{$class_id}_error')");
		$this->response->script("top.preloader.progressbarStop();");
    }

	/**
	 * Получение служебных данных формы из сессии
	 * @param $id
	 *
	 * @return array
	 */
	private function getSessForm($id)
	{
		if (!$this->orderFields) {
			$sess_form = new Zend_Session_Namespace('Form');
			if (!$id || empty($sess_form->$id)) {
				return array();
			}
			$this->orderFields = $sess_form->$id;
		}
		return $this->orderFields;
	}

	/**
	 * Принудительная установка значения служебного поля формы
	 *
	 * @param $form_id - id формы
	 * @param $id - имя поля
	 * @param $value
	 */
	protected function setSessFormField($form_id, $id, $value)
	{
		$this->getSessForm($form_id);
		$this->orderFields[$id] = $value;
	}

	/**
	 * Получает значение служебного поля формы
	 * @param $form_id
	 * @param $id
	 *
	 * @return mixed
	 */
	protected function getSessFormField($form_id, $id)
	{
		$this->getSessForm($form_id);
		return $this->orderFields[$id];
	}

	/**
	 * Сохранение файлов их XFILES
	 * 
	 * @throws Exception
	 * @param $table - таблица для сохранения
	 * @param $last_insert_id
	 * @param $data
	 * @return void
	 */
    private function saveFile($table, $last_insert_id, $data) {
		//echo "<PRE>";print_r($data);echo "</PRE>";die;
    	$sid 			= Zend_Session::getId();
    	$upload_dir 	= $this->config->temp . '/' . $sid;
		$thumb_dir 		= $upload_dir . '/thumbnail';
    	foreach ($data as $field => $value) {
			$temp = explode("|", $value);
			foreach ($temp as $f) {
				if (!$f) continue;
				$f = explode("###", $f);
				$fn = $upload_dir . '/' . $f[0];
				if (!file_exists($fn)) {
					throw new Exception(sprintf($this->translate->tr("Файл %s не найден"), $f[0]));
				}
				$size = filesize($fn);
				if ($size !== (int)$f[1]) {
					throw new Exception(sprintf($this->translate->tr("Что-то пошло не так. Размер файла %s не совпадает"), $f[0]));
				}
				$content = file_get_contents($fn);
				$hash = md5_file($fn);

				$fn = $thumb_dir . '/' . $f[0];
				$thumb = new Zend_Db_Expr('NULL');
				if (file_exists($fn)) {
					$thumb = file_get_contents($fn);
				}
				$this->db->insert($table . '_files',
					array(
						'refid'    => $last_insert_id,
						'filename' => $f[0],
						'filesize' => $size,
						'hash'     => $hash,
						'type'     => $f[2],
						'content'  => $content,
						'fieldid'  => $field,
						'thumb'    => $thumb
					)
				);
			}
		}
    }

	/**
	 * Save data
	 * @param array     $data
	 * @param bool|true $inTrans
	 * @return int|string
	 * @throws Exception
	 */
	protected function saveData($data, $inTrans = true) {
		$last_insert_id = 0;
		if (!$inTrans) $this->db->beginTransaction();
		try {
			if (empty($data['class_id'])) throw new Exception("Form error", 500);
			$order_fields = $this->getSessForm($data['class_id']);
			if (count($this->error)) {
				throw new Exception();
			}
			$authNamespace = Zend_Registry::get('auth');
			$control       = array();
			$fileFlag      = array();
			$fileFlagDel   = array();
			$table         = trim($order_fields['table']);
			if (!$table) throw new Exception("Ошибка обработки таблицы", 500);
			$explain = $this->db->fetchAll("EXPLAIN `$table`");
			if (!$explain || !is_array($explain)) throw new Exception("Ошибка обработки таблицы", 500);
			foreach ($data['control'] as $key => $value) {
				if (!is_array($value)) $value = trim($value);
				if (substr($key, -3) == '%re') continue;
				if (substr($key, -4) == '%tru') continue;
				if (substr($key, 0, 9) == 'filesdel|') {
					$fileFlagDel[substr($key, 9)] = $value;
					continue;
				}
				if (substr($key, 0, 6) == 'files|') {
					$fileFlag[substr($key, 6)] = $value;
					continue;
				}

				if ($value !== '0' && $value !== 0 && $value !== 0.0 && empty($value)) {
					$data['control'][$key] = new Zend_Db_Expr('NULL');
				} elseif (is_array($value)) {
					$data['control'][$key] = implode(",", $value);
				}
				/*else {
					if (!is_object($value) && !is_array(@unserialize((string)$value))) {
						$data['control'][$key] = htmlspecialchars($value);
					}
				}*/
				$control[$key] = $data['control'][$key];
				if (isset($data['control'][$key . '%tru'])) {
					$is_tru = false;
					foreach ($explain as $va) {
						if ($va['Field'] == $key . '_tru') {
							$control[$key . '_tru'] = $data['control'][$key . '%tru'] ? $data['control'][$key . '%tru'] : new Zend_Db_Expr('NULL');
							$is_tru = true;
							break;
						}
					}
					if (!$is_tru) {
						$control[$key] .= ' - ' . $data['control'][$key . '%tru'];
					}
				}
			}
            foreach ($explain as $value) {
                if ($value['Field'] == 'lastuser') {
                    $control['lastuser'] = (int) $authNamespace->ID;
                    if ($control['lastuser'] == -1) $control['lastuser'] = new Zend_Db_Expr('NULL');
                }
				if ($value['Field'] == 'author' && !$order_fields['refid'] && empty($control['author'])) {
					$control['author'] = $authNamespace->NAME;
				}
            }

			if (!$order_fields['refid']) {
				$this->db->insert($table, $control);
				$last_insert_id = $this->db->lastInsertId($table);
				if ($fileFlag) {
					$this->saveFile($table, $last_insert_id, $fileFlag);
				}
			} else {
				// CHECK IF THE RECORD WAS CHANGED
				$this->checkTheSame($table, $data);

				//Проверка доступа
				if ($this->checkAcl($order_fields['resId'], 'edit_owner') && !$this->checkAcl($order_fields['resId'], 'edit_all')) {
					$res = $this->db->fetchRow("SELECT * FROM `$table` WHERE `{$order_fields['keyField']}`=? LIMIT 1", $order_fields['refid']);
					if (isset($res['author']) && $authNamespace->NAME != $res['author']) {
						throw new Exception($this->translate->tr('Вам разрешено редактировать только собственные данные.'));
					}
				}

                if ( ! empty($control)) {
				$where = $this->db->quoteInto($order_fields['keyField'] . " = ?", $order_fields['refid']);
				$this->db->update($table, $control, $where);
                }
				$last_insert_id = $order_fields['refid'];
				if ($fileFlag) {
					if ($fileFlagDel) {
						foreach ($fileFlagDel as $value) {
							$value = explode(",", $value);
							$ids = array();
							foreach ($value as $k => $inid) {
								$inid = (int)$inid;
								if ($inid) $ids[] = $inid;
							}
							$this->db->query("DELETE FROM `{$table}_files` WHERE refid='$last_insert_id' AND id IN('" . implode("','", $ids) . "')");
						}
					}
					$this->saveFile($table, $last_insert_id, $fileFlag);
				}
			}
			if (!$inTrans) $this->db->commit();
		}
		catch (Exception $e) {
			if (!$inTrans) {
				$this->db->rollback();
			} else {
				throw $e;
			}
			$msg = $e->getMessage();
			if ($msg) {
				$this->error[] = $msg;
			}
			$this->displayError($data);
			return 0;
		}
		return $last_insert_id;
	}

	/**
	 * Проверка одновременного редактирования
	 * @param $table - название таблицы
	 * @param $data
	 *
	 * @throws Exception
	 */
	protected function checkTheSame($table, $data) {
		$order_fields = $this->getSessForm($data['class_id']);
		$check = $this->db->fetchOne("SELECT 1 FROM core_controls WHERE tbl=? AND keyfield=? AND val=?",
			array($table, $order_fields['keyField'], $order_fields['refid'])
		);
		if (!$check) {
			throw new Exception($this->translate->tr('Кто-то редактировал эту запись одновременно с вами, но успел сохранить данные раньше вас. Ничего страшного, обновите страницу и проверьте, возможно этот кто-то сделал за вас работу :)'));
		} else {
			$this->db->query("DELETE FROM core_controls WHERE tbl=? AND keyfield=? AND val=?",
				array($table, $order_fields['keyField'], $order_fields['refid'])
			);
		}
	}

	/**
	 * выполняется последним
	 * отображает ошибки, если есть
	 * @param array $data
	 */
	protected function done($data) {
		if ($this->error) {
			$this->displayError($data);
			return;
		}
		if (!empty($data['class_id'])) {
			$this->response->assign($data['class_id'] . "_error", "style.display", 'none');
		}
		$order_fields = $this->getSessForm($data['class_id']);
		if (!empty($order_fields['back'])) {
			$this->response->script($this->script . "load('{$order_fields['back']}');");
		}
	}
	
       
    /**
     * basic save function
     *
     * @param array $data
     * @param string $fields
     * @return object
     */
	public function axSave($data, $fields = array()) {
    	$this->error = array();
		//echo "<pre>"; print_r($data);echo "</pre>"; die();
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		if (!$this->saveData($data)) {
			return $this->response;
		}
		$this->done($data);
		return $this->response;
	}
	
}
