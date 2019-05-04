<?php

require_once __DIR__ .'/classes/Common.php';
require_once __DIR__ .'/classes/Image.php';

use Zend\Session\Container as SessionContainer;
use Zend\Validator\EmailAddress as ValidateEmailAddress;
use Zend\Validator\Hostname as ValidateHostname;
use Zend\I18n\Validator\IsFloat;
use Zend\I18n\Validator\IsInt;



/**
 * Class ajaxFunc
 */
class ajaxFunc extends Common {

    /**
     * A prefix for all ajax functions to aid automatic xajax registration
     */
	protected $error = array();

    /**
     * @var xajaxResponse
     */
	protected $response;
	protected $script;
	protected $userId;
	private $orderFields;


    /**
     * ajaxFunc constructor.
     * @param xajaxResponse $res
     */
    public function __construct (xajaxResponse $res) {
	    $this->response = $res;
    	parent::__construct();
    }


    /**
     * Сохранение данных формы
     * @param array $data
     * @param array $fields
     * @return object
     */
    public function axSave($data, $fields = array()) {
        $this->error = array();

        if ($this->ajaxValidate($data, $fields)) {
            return $this->response;
        }
        if ( ! $this->saveData($data)) {
            return $this->response;
        }
        $this->done($data);
        return $this->response;
    }

    
    /**
     * Validate function
     * @param array $data
     * @param array $fields
     * @return bool
     */
	protected function ajaxValidate($data, $fields) {

		$order_fields = $this->getSessForm($data['class_id']);

		$control  = $data['control']; //данные полей формы
		$script   = "for(var i = 0; i < document.getElementById('{$order_fields['mainTableId']}_mainform').elements.length; i++){document.getElementById('{$order_fields['mainTableId']}_mainform').elements[i].classList.remove('reqField')};";
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
					$script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "2').className='reqField';";
					$this->error[] = "- {$this->translate->tr('Пароль не совпадает.')}<br/>";
				}
				else if (isset($control[$field . "%tru"]) && $val > $control[$field . "%tru"]) {
					$script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "%tru_day').className='reqField';";
					$script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "%tru_month').className='reqField';";
					$script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "%tru_year').className='reqField';";
					$this->error[] = "- {$this->translate->tr('Дата начала больше даты окончания.')}<br/>";
				}
				else if (substr($field, 0, 6) == 'files|' && $val) {
					$files = explode("|", trim($val, "|"));
					if (count($files)) {
						try {
						    $table_files = $this->db->quoteIdentifier(trim($order_fields['table']) . "_files");
							$this->db->fetchOne("SELECT 1 FROM {$table_files}");
						} catch (Zend_Db_Exception $e) {
							$this->error[] = $e->getMessage() . "<br/>";
						}
					}
				}
			}
		}

		foreach ($fields as $field => $val) {
            $params = explode(",", $val);

            if (in_array("req", $params) && ! array_key_exists($field, $control)) {
                $this->error[] = "- {$this->translate->tr('Ошибка сохранения. Обратитесь к администратору.')}<br/>";
                break;
            }

            if ( ! isset($control[$field])) {
			    continue;
            }

			if (in_array("req", $params) &&
                (is_null($control[$field]) ||
                $control[$field] === false ||
                $control[$field] === '' ||
                (is_array($control[$field]) && ! $control[$field]))
            ) {
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
					$script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').value = '" . $control[$field] . "';";
				}
			}
		}
		if (count($req)) {
			foreach ($req as $val) {
				$script .= "document.getElementById('" . $order_fields['mainTableId'] . $val . "').classList.add('reqField');";
			}
			$this->error[] = "- {$this->translate->tr('Пожалуйста, заполните обязательные поля.')}<br/>";
		}
		
		if (count($email)) {
			$validator = new ValidateEmailAddress();
			foreach ($email as $field) {
                if ( ! $validator->isValid($control[$field]) &&
                     ! preg_match('/^((([0-9A-Za-z]{1}[-0-9A-z\.]{1,}[0-9A-Za-z]{1})|([0-9А-Яа-я]{1}[-0-9А-я\.]{1,}[0-9А-Яа-я]{1}))@([-0-9A-Za-zА-Яа-я]{1,}\.){1,2}[-A-Za-zрфбел]{2,})$/u', $control[$field])
                ) {
				    // email is invalid; print the reasons
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').classList.add('reqField');";
				}
			}
		}
		
		if (count($float)) {
			$validator = new IsFloat();
			foreach ($float as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').classList.add('reqField');";
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
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').className='reqField';";
				}
			}
		}

		if (count($datetime)) {
			foreach ($datetime as $field) {
				if ( ! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}(|:{1}\d{2}))$/', $control[$field])) {
                    $this->error[] = "- Неверный формат даты";
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').className='reqField';";
				} else {
                    list($year, $month, $day) = sscanf($control[$field], '%d-%d-%d');
                    if ( ! checkdate($month, $day, $year)) {
                        $this->error[] = "- Некорректная дата";
                        $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').className='reqField';";
                    }
                }
			}
		}*/
		
		if (count($int)) {
			$validator = new IsInt();
			foreach ($int as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').classList.add('reqField');";
				}
			}
		}
		
		// if (count($phone)) {
		//    $pattern = '/((?!:\A|\s)(?!(\d{1,6}\s+\D)|((\d{1,2}\s+){2,2}))(((\+\d{1,3})|(\(\+\d{1,3}\)))\s*)?((\d{1,6})|(\(\d{1,6}\)))\/?(([ -.]?)\d{1,5}){1,5}((\s*(#|x|(ext))\.?\s*)\d{1,5})?(?!:(\Z|\w|\b\s)))/';
		//    foreach ($phone as $field) {
		//        //TODO do this
		//    }
		// }
		
		if (count($host)) {
			$validator = new ValidateHostname();
			$validator->setMessage('Недопустимый тип данных, значение должно быть строкой', ValidateHostname::INVALID);
			$validator->setMessage("Значение '%value%' выглядит как IP-адрес, но IP-адреса не разрешены", ValidateHostname::IP_ADDRESS_NOT_ALLOWED);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но оно не дожно быть из списка доменов верхнего уровня", ValidateHostname::UNKNOWN_TLD);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но знак '-' находится в недопустимом месте", ValidateHostname::INVALID_DASH);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но оно не соответствует шаблону для доменных имен верхнего уровня '%tld%'", ValidateHostname::INVALID_HOSTNAME_SCHEMA);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но не удаётся извлечь домен верхнего уровня", ValidateHostname::UNDECIPHERABLE_TLD);
			$validator->setMessage("Значение '%value%' не соответствует ожидаемой структуре для DNS имени хоста", ValidateHostname::INVALID_HOSTNAME);
			$validator->setMessage("Значение '%value%' является недопустимым локальным сетевым адресом", ValidateHostname::INVALID_LOCAL_NAME);
			$validator->setMessage("Значение '%value%' выглядит как локальный сетевой адрес, но локальные сетевые адреса не разрешены", ValidateHostname::LOCAL_NAME_NOT_ALLOWED);
			$validator->setMessage("Значение '%value%' выглядит как DNS имя хоста, но указанное значение не может быть преобразованно в допустимый для DNS набор символов", ValidateHostname::CANNOT_DECODE_PUNYCODE);
			
			foreach ($host as $field) {
				if (!$validator->isValid($control[$field])) {
				    foreach ($validator->getMessages() as $message) {
				        $this->error[] = "- $message";
				    }
				    $script .= "document.getElementById('" . $order_fields['mainTableId'] . $field . "').className='reqField';";
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
	 * @param array $data
	 */
	protected function displayError($data) {
        $order_fields = $this->getSessForm($data['class_id']);
    	$this->response->assign($order_fields['mainTableId'] . "_error", "innerHTML", '<a name="' . $order_fields['mainTableId'] . '_error"> </a>' . implode("<br/>", $this->error));
		$this->response->assign($order_fields['mainTableId'] . "_error", "style.display", 'block');
		$this->response->script("toAnchor('{$order_fields['mainTableId']}_error')");
    }


	/**
	 * Принудительная установка значения служебного поля формы
	 * @param string $form_id - id формы
	 * @param string $id - имя поля
	 * @param mixed  $value
	 */
	protected function setSessFormField($form_id, $id, $value) {
		$this->getSessForm($form_id);
		$this->orderFields[$id] = $value;
	}


	/**
	 * Получает значение служебного поля формы
	 * @param string $form_id
	 * @param string $id
	 * @return mixed
	 */
	protected function getSessFormField($form_id, $id) {
		$this->getSessForm($form_id);
		return $this->orderFields[$id];
	}


	/**
	 * Сохранение данных формы
	 * @param array     $data
	 * @param bool|true $inTrans
	 * @return int|string
	 * @throws Exception
	 */
	protected function saveData($data, $inTrans = true) {
		if ( ! $inTrans) {
		    $this->db->beginTransaction();
        }

		try {
			if (empty($data['class_id'])) {
			    throw new Exception("Form error", 500);
            }

			$order_fields = $this->getSessForm($data['class_id']);

			if (count($this->error)) {
				throw new Exception('');
			}

			$authNamespace = Zend_Registry::get('auth');
			$control       = array();
			$fileFlag      = array();
			$fileFlagDel   = array();
			$table         = isset($order_fields['table']) ? trim($order_fields['table']) : '';

			if ( ! $table) {
			    throw new Exception("Ошибка обработки таблицы", 500);
            }

            $table_quoted = $this->db->quoteIdentifier($order_fields['table']);
			$explain      = $this->db->fetchAll("EXPLAIN {$table_quoted}");

			if ( ! $explain || ! is_array($explain)) {
			    throw new Exception("Ошибка обработки таблицы", 500);
            }

			foreach ($data['control'] as $key => $value) {
				if ( ! is_array($value)) $value = trim($value);
				if (substr($key, -3) == '%re') continue;
				if (substr($key, -4) == '%tru') continue;
				if (substr($key, 0, 9) == 'filesdel|') {
					$fileFlagDel[substr($key, 9)] = $value;
					continue;
				}
				if (strpos($key, 'files|') === 0) {
                    $field_id = substr($key, 6);
					$fileFlag[$field_id] = array(
                        'data' => $value
                    );

                    if (isset($order_fields[$field_id.'|maxWidth'])) {
                        $fileFlag[$field_id]['max_width'] = $order_fields[$field_id.'|maxWidth'];
                    }
                    if (isset($order_fields[$field_id.'|maxHeight'])) {
                        $fileFlag[$field_id]['max_height'] = $order_fields[$field_id.'|maxHeight'];
                    }
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

                $last_insert_id = $order_fields['refid'];
				//Проверка доступа
				if ($this->checkAcl($order_fields['resId'], 'edit_owner') && !$this->checkAcl($order_fields['resId'], 'edit_all')) {
				    $key_name = $this->db->quoteIdentifier($order_fields['keyField']);
					$res = $this->db->fetchRow("
                        SELECT * 
                        FROM {$table_quoted} 
                        WHERE {$key_name} = ? 
                        LIMIT 1
                    ", $last_insert_id);
					if (isset($res['author']) && $authNamespace->NAME != $res['author']) {
						throw new Exception($this->translate->tr('Вам разрешено редактировать только собственные данные.'));
					}
				}

                if ( ! empty($control)) {
                    $where = $this->db->quoteInto($order_fields['keyField'] . " = ?", $last_insert_id);
                    $this->db->update($table, $control, $where);
                }

				if ($fileFlag) {
					if ($fileFlagDel) {
						foreach ($fileFlagDel as $value) {
							$value = explode(",", $value);
							$ids = array();
							foreach ($value as $k => $inid) {
								$inid = (int)$inid;
								if ($inid) $ids[] = $inid;
							}
							if ( ! empty($ids)) {
                                $table_name = $this->db->quoteIdentifier("{$table}_files");
                                $where = $this->db->quoteInto('id IN(?)', $ids);
                                $this->db->query("
                                    DELETE FROM {$table_name} 
                                    WHERE refid = ? 
                                      AND {$where}
                                ", $last_insert_id);
                            }
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
	 * @param string $table - название таблицы
	 * @param array  $data
	 * @throws Exception
	 */
	protected function checkTheSame($table, $data) {
		$order_fields = $this->getSessForm($data['class_id']);
		$check = $this->db->fetchOne("SELECT 1 FROM core_controls WHERE tbl=? AND keyfield=? AND val=?",
			array($table, $order_fields['keyField'], $order_fields['refid'])
		);
		if ( ! $check) {
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
        $order_fields = $this->getSessForm($data['class_id']);
        if ( ! empty($data['class_id'])) {
            $this->response->assign($order_fields['mainTableId'] . "_error", "style.display", 'none');
        }
        if ( ! empty($order_fields['back'])) {
			$this->response->script($this->script . "setTimeout(function () {load('{$order_fields['back']}')}, 0);");
		}
	}


    /**
     * Сохранение файлов их XFILES
     * @param string $table - таблица для сохранения
     * @param int    $last_insert_id
     * @param array  $file_controls
     * @return void
     * @throws Exception
     */
    private function saveFile($table, $last_insert_id, $file_controls) {

        $sid        = SessionContainer::getDefaultManager()->getId();
        $upload_dir = $this->config->temp . '/' . $sid;
        $thumb_dir  = $upload_dir . '/thumbnail';

        foreach ($file_controls as $field => $file_control) {

            $files = explode("|", $file_control['data']);

            foreach ($files as $file) {
                if ( ! $file) {
                    continue;
                }

                $file      = explode("###", $file);
                $file_path = $upload_dir . '/' . $file[0];

                if ( ! file_exists($file_path)) {
                    throw new Exception(sprintf($this->translate->tr("Файл %s не найден"), $file[0]));
                }

                $size = filesize($file_path);
                if ($size !== (int)$file[1]) {
                    throw new Exception(sprintf($this->translate->tr("Что-то пошло не так. Размер файла %s не совпадает"), $file[0]));
                }
                $content = file_get_contents($file_path);
                $hash    = md5_file($file_path);

                $file_path_thumb = $thumb_dir . '/' . $file[0];
                $thumb_content   = new Zend_Db_Expr('NULL');

                if (file_exists($file_path_thumb)) {
                    $thumb_content = file_get_contents($file_path_thumb);
                }


                if (( ! empty($file_control['max_width']) || ! empty($file_control['max_height'])) &&
                    strpos($file[2], 'image/') === 0
                ) {
                    $image_info = getimagesize($file_path);

                    if ( ! empty($image_info)) {
                        $type       = explode("/", $file[2]);
                        $max_width  = null;
                        $max_height = null;

                        if ( ! empty($file_control['max_width']) &&
                            is_numeric($file_control['max_width']) &&
                            $file_control['max_width'] > 0
                        ) {
                            $max_width = $file_control['max_width'];
                        }

                        if ( ! empty($file_control['max_height']) &&
                            is_numeric($file_control['max_height']) &&
                            $file_control['max_height'] > 0
                        ) {
                            $max_height = $file_control['max_height'];
                        }


                        if ($max_width && $image_info[0] > $max_width ||
                            $max_height && $image_info[1] > $max_height
                        ) {
                            $image   = \WideImage\WideImage::loadFromString($content);
                            $content = $image->resize($max_width, $max_height)
                                ->asString($type[1]);

                            $hash = md5($content);
                            $size = strlen($content);
                        }
                    }
                }

                $this->db->insert($table . '_files', array(
                    'refid'    => $last_insert_id,
                    'filename' => $file[0],
                    'filesize' => $size,
                    'hash'     => $hash,
                    'type'     => $file[2],
                    'content'  => $content,
                    'fieldid'  => $field,
                    'thumb'    => $thumb_content
                ));
            }
        }
    }


    /**
     * Получение служебных данных формы из сессии
     * @param string $id
     * @return array
     */
    private function getSessForm($id) {
        if ( ! $this->orderFields) {
            $sess_form = new SessionContainer('Form');
            if (!$sess_form || !$id || empty($sess_form->$id)) {
                return array();
            }
            $this->orderFields = $sess_form->$id;
        }
        return $this->orderFields;
    }
}
