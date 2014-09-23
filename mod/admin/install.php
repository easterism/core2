<?

	class InstallModule extends Db {
		
		public $moduleData;								
		public $installPath;
        public $uninstall;
        public $is_visible;
//        private $is_system;
//        private $is_public;
//		public $moduleId;
		public $mod;
//		public $moduleName;
//		public $subModule;
		public $lastUser;
		private $tempFile;
		private $tempDir;
		public $listTablesBegin;
		public $installInfo;
//		public $versionInstall;
		public $versionCurrent; 
		public $depend;
		private $isUpgrate;
		private $module_is_off;
		protected $config;
        public $notice_msg = array();

    function __construct() {
			parent::__construct();
		}

        /** подготовка к установки с сервера
         * @param $mod_id
         */
        private function init($mod_id) {

            $this->module_is_off = array();
            $this->moduleData = $this->db->fetchRow("SELECT `data`,
															`install_info`,
															`version`
													   FROM `core_available_modules`
													  WHERE `id` = ?", $mod_id);
            $installInfo 		= unserialize($this->moduleData['install_info']);
            $this->installInfo 	= $installInfo;
            $this->mod 	        = $installInfo['install'];
            $this->installPath 	= (strtolower($installInfo['install']['module_system']) == "y" ? "core2/mod/{$installInfo['install']['module_id']}/v{$installInfo['install']['version']}" : "mod/{$installInfo['install']['module_id']}");
//            $this->moduleId 	= $installInfo['install']['module_id'];
//            $this->moduleName 	= $installInfo['install']['module_name'];
//            $this->is_system 	= $installInfo['install']['module_system'];
//            $this->is_public 	= $installInfo['install']['module_public'];
//            $this->subModule 	=  (isset($installInfo['install']['sub_module']) ? $installInfo['install']['sub_module'] : "");
            $this->listTablesBegin = $this->db->listTables();
            $authNamespace 		= Zend_Registry::get('auth');
            $this->config 		= Zend_Registry::getInstance()->get('config');
            $this->lastUser 	= $authNamespace->ID < 0 ? NULL : $authNamespace->ID;
//            $this->versionInstall = $this->moduleData['version'];
            //echo "<pre>";  print_r($this->versionInstall); die;
            $this->checkXml();
		}

		private function checkXml() {
			$this->tempDir = $this->config->temp . "/tmp_" . uniqid();
            $this->unZip($this->tempDir);
			if (!is_file($this->tempDir . "/install/install.xml")) {
				throw new Exception("install.xml не найден. Установка прервана.");
			}
		}





    /** проверяем чтоб запрос не удалял таблицы не пренадлежащие модулю
     * @param $sql
     * @return bool
     */
    public function checkSQL($sql){
        /* провераем на чистоту sql-файла */
        /* $listTables - массив таблиц ядра */

        $pattern = "/drop table(.*)/im";

        $matches = array();
        preg_match_all($pattern, $sql, $matches);

        foreach ($matches[0] as $match) {
            if (substr_count($match,'mod_' . $this->mod['module_id']) < 1) {
                return false;
            }
        }

        return true;
    }

		/**
		 * Проверяем установленные версии модуля
		 * @return bool
		 */
		public function checkInstall($mod_id) {
            $this->init($mod_id);
			$res = $this->db->fetchAll("SELECT `version` FROM `core_modules` WHERE `module_id`=?", $this->mod['module_id']);
			if (count($res)) {
				$this->versionCurrent = $res[0]['version'];
				return true;
			} else return false;

		}

		private function checkModule($module_id) {
			$res = $this->db->fetchAll("SELECT `m_name`, `visible` FROM `core_modules` WHERE `module_id`=?", $module_id);
			if($res) {
				foreach ($res as $val) {
					if($val['visible'] == 'Y') {
                        $status = true;
					} else {
						$this->module_is_off[] = $val['m_name'];
                        $this->is_visible = "N";
                        $status = false;
					}
                    $this->add_notice("Зависимость от модулей", $status ? "Модуль включен" : "Следует включить этот модуль", "Зависит от '{$val['m_name']}'", $status ? "mod_info" : "mod_warning");
				}
                return true;
			} else {
				return false;
			}				 
		}
			
		public function deleteTables() {
			$diff = array_diff($this->db->listTables(), $this->listTablesBegin);					
			/* Удаляем ранее созданные таблицы */					
			if (count($diff)) {
				foreach ($diff as $val_diff) {
					$query = "DROP TABLE IF EXISTS `".$val_diff."`";
					$this->db->query($query);							 
				}				
			}  
		}

        /** распоковка зип
         * @throws Exception
         */
        private function extractZip() {

            //смотрим в какую папку устанавливать
            $dir = (strtolower($this->installInfo['install']['module_system']) == "y" ? "core2/" : "") . "mod/";

            if (is_writeable($dir)) {//если открыта для записи
                $msg = "";
                if (strtolower($this->installInfo['install']['module_system']) == "y") {
                    if (!is_dir("{$dir}/{$this->mod['module_id']}")) {
                        $this->autoDestination("{$dir}{$this->mod['module_id']}");
                        $this->add_notice("Файлы", "Директория \"{$dir}{$this->mod['module_id']}\"", "Создана", "mod_info");
                    } else {
                        $this->add_notice("Файлы", "Директория \"{$dir}{$this->mod['module_id']}\"", "Существует", "mod_info");
                    }
                }

                if (!is_dir($this->installPath)) {
                    $this->autoDestination($this->installPath);
                    $this->add_notice("Файлы", "Директория \"{$this->installPath}\"", "Создана", "mod_info");
                } else {
                    $this->add_notice("Файлы", "Директория \"{$this->installPath}\"", "Существует", "mod_info");
                }

                $zip = new ZipArchive();
                if ($zip->open($this->tempFile) === true){
                    echo "<div class=\"block\"></div>";
                    /* Распаковка всех файлов архива */
                    for($i = 0; $i < $zip->numFiles; $i++) {
                        if (substr_count($zip->getNameIndex($i), "install/") == 0) { // папку инстал и содержимое пропускаем
                            $zip->extractTo($this->installPath, $zip->getNameIndex($i));
                            $msg .= "\"{$zip->getNameIndex($i)}\"<br>";
                        }
                    }
                    $zip->close();
                    $this->add_notice("Файлы", "Копирование файлов:<br> {$msg}", "Успешно", "mod_info");
                } else {
                    $this->is_visible = "N";
                    $this->add_notice("Файлы", "Копирование файлов:", "Ошибка: не могу открыть архив с файлами, скопируйте файлы вручную", "mod_danger");
                }

            } else {// если закрыта
                $this->is_visible = "N";
                $this->add_notice("Файлы", "Копирование файлов:", "Ошибка: директория с модулями закрыта для записи, скопируйте файлы вручную", "mod_danger");
            }
		}

		/**
		 * Распаковка Zip
		 * @param $destinationFolder
		 * @throws Exception
		 */
		public function unZip($destinationFolder) {
            $this->make_zip($this->moduleData['data']);
			$zip = new ZipArchive();
			$this->autoDestination($destinationFolder);
			if ($zip->open($this->tempFile) === true){
				/* Распаковка всех файлов архива */
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$zip->extractTo($destinationFolder, $zip->getNameIndex($i));
				}
				$zip->close();
			} else {
				throw new Exception("Ошибка архива");
			}
		}

		/**
		 * Проверка и создание директории
		 * @param $destinationFolder
		 * @throws Exception
		 */
		private function autoDestination($destinationFolder) {
			if (!is_dir($destinationFolder)) {
				if (!mkdir($destinationFolder)) {
					throw new Exception("Не могу создать директорию для разархивирования.");
				}
			}
		}


        /**
         * @return array
         */
        private function getAccess() {

            $Inf = $this->installInfo;
			$access = array();
			$access_add = array();
				if (!empty($Inf['install']['access']['default'])) {
					foreach ($Inf['install']['access']['default'] as $key => $value ) {
                        if ($key == "access"){
                            if ($value == "on") {
                                $access[$key] = $value;
                            }
                        } else {
                            foreach ($value as $who=>$ac) {
                                if ($ac == "on") {
                                    $access[$key . "_" . $who] = $ac;
                                }
                            }
                        }
					}
                    $this->add_notice("Права доступа", "Права по умолчанию", "Добавлены", "mod_info");
				} else {
                    $this->add_notice("Права доступа", "Права по умолчанию", "Отсутствуют", "mod_warning");
                }
				if (!empty($Inf['install']['access']['additional'])) {
					foreach ($Inf['install']['access']['additional'] as $value) {
                        if ($value["all"] == "on" || $value["owner"] == "on"){
                            $access_add[$value["name"]] = ($value["all"] == "on" ? "all" : "owner");
                        }
					}
                    $this->add_notice("Права доступа", "Дополнительные права", "Добавлены", "mod_info");
				} else {
//                    $this->add_notice("Права доступа", "Дополнительные права", "Отсутствуют", "mod_info");
                }
            $access = base64_encode(serialize($access));
            $access_add = base64_encode(serialize($access_add));
            return array("access_default" => $access, "access_add" => $access_add);
		}

		private function getDepend() {			
            $Inf = $this->installInfo;
			$depend = array();
			if (!empty($Inf['install']['dependent_modules'])) {
				foreach ($Inf['install']['dependent_modules'] as $dep_value) {

					$depend[] = array('module_id' => $dep_value);

					if ($this->checkModule($dep_value) == false) {
                        $this->add_notice("Зависимость от модулей", "Зависит от '{$dep_value}'", "Ошибка: модуль не установлен!", "mod_danger");
                        throw new Exception("Установите нужный модуль");
                    }
				}
				$depend = base64_encode(serialize($depend));
				return $depend;							
			} else {
                $this->add_notice("Зависимость от модулей", "Проверка выполнена", "Не имеет зависимостей", "mod_info");
				return false;
			}						
		}

		private function getSubModules($lastId) {
            echo "";
            $Inf = $this->installInfo;
			$arrSubModules = array();						
			if (!empty($Inf['install']['submodules'])) {
				$seq=1;				
				foreach ($Inf['install']['submodules'] as $valsub) {
					$access = array();	
					$access_add = array();					 
					if (!empty($valsub['sm_id']) && !empty($valsub['sm_name'])) {
                        if (!empty($valsub['access']['default'])) {
                            foreach ($valsub['access']['default'] as $key => $value ) {
                                if ($key == "access"){
                                    $access[$key] = $value;
                                } else {
                                    foreach ($value as $who=>$ac) {
                                        if ($ac == "on") {
                                            $access[$key . "_" . $who] = $ac;
                                        }
                                    }
                                }
                            }
                            $access = base64_encode(serialize($access));
                        }
                        if (!empty($valsub['access']['additional'])) {
                            foreach ($valsub['access']['additional'] as $value) {
                                if ($value["all"] == "on" || $value["owner"] == "on"){
                                    $access_add[$value["name"]] = ($value["all"] == "on" ? "all" : "owner");
                                }
                            }
                            $access_add = base64_encode(serialize($access_add));
                        }

						$arrSubModules[] = array(
								'sm_name' 		 	=> $valsub['sm_name'],
								'sm_key' 		 	=> $valsub['sm_id'],
								'sm_path' 		 	=> empty($valsub['sm_path']) ? "" : $valsub['sm_path'],
								'visible' 		 	=> 'Y',
								'm_id' 			 	=> $lastId, 
								'lastuser'  	 	=> $this->lastUser,
								'seq' 	    	 	=> $seq,
								'access_default' 	=> $access,
								'access_add'		=> $access_add
						);						
						$seq = $seq + 5;
					}							
				}															
				return $arrSubModules;
			} else {
                $this->add_notice("Субмодули", "Проверка выполнена", "Модуль не имеет субмодулей", "mod_info");
                return false;
            }

		}

        /**
         * регистрация модуля и всех его настроек и зависимостей
         */
        public function Install() {
			
			/* $maxSeq- позиция модуля */
			$globalId = uniqid();
			$maxSeq = $this->db->fetchOne("SELECT MAX(`seq`) FROM `core_modules`") + 5;
			$arrForInsert = array(
					'module_id' 	=> $this->mod['module_id'],
				 	'm_name' 		=> $this->mod['module_name'],
				 	'lastuser' 		=> $this->lastUser,
				 	'visible' 		=> 'Y',
				 	'is_system' 	=> $this->mod['module_system'],
				 	'is_public' 	=> $this->mod['module_public'],
			 		'version' 		=> $this->mod['version'],
			 		'global_id' 	=> $globalId,
			 		'uninstall' 	=> $this->getUninstallSQL(),
			 		'seq' 			=> $maxSeq
			);

            //TODO вывести сообщение о выключеных модулях
            //проверка на зависимость от других модулей
			if ($depend = $this->getDepend()) {								
				$arrForInsert['dependencies'] = $depend;				
			}

            //обработка прав доступа
			if ($access = $this->getAccess()) {
                $arrForInsert['access_default'] = $access['access_default'];
                $arrForInsert['access_add']     = $access['access_add'];
			}
								
            //регистрация модуля
			$this->db->insert('core_modules', $arrForInsert);
            $this->add_notice("Регистрация модуля", "Операция выполнена", "Успешно", "mod_info");
			$lastId = $this->db->lastInsertId();

            //регистрация субмодулей модуля
            $subModules = $this->getSubModules($lastId);
			if (!empty($subModules)) {
				foreach ($subModules as $subval)
				{					
					$this->db->insert('core_submodules', $subval);					
				}
                $this->add_notice("Субмодули", "Субмодули добавлены", "Успешно", "mod_info");
			}
            
            //установка таблиц модуля
            $this->setInstallSql();
			
			/* Распаковка и копирование файлов*/
			$this->extractZip();

            if ($this->is_visible == "N") {
                $this->db->update('core_modules', array('visible' => 'N'), $this->db->quoteInto('module_id = ?', $this->mod['module_id']));
                $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "' а потом этот модуль") : " включите модуль";
                $this->add_notice("Установка", "Установка завершена", "Для работы{$msg}", "mod_warning");
            } else {
                $this->add_notice("Установка", "Установка завершена", "Успешно", "mod_info");
            }
		}

		/**
		 * задаем sql для таблиц модуля
		 * @throws Exception
		 */
		public function setInstallSql() {
			$xmlObj = simplexml_load_file($this->tempDir . "/install/install.xml");
			$file = $xmlObj->install->sql;//достаём имя файла со структурой
//            $this->uninstall = file_get_contents($this->tempDir . "/install/" . $xmlObj->uninstall->sql);//готовим удалятор
            $sql = file_get_contents($this->tempDir . "/install/" . $file);//достаём из файла структуру
			if (!empty($sql)) {
                $sql = str_replace("#__", "mod_" . $this->mod['module_id'], $sql);//готовим
				if ($this->checkSQL($sql)) {
					$this->db->query($sql);//ставим
                    $this->add_notice("Таблицы модуля", "Таблицы добавлены", "Успешно", "mod_info");
				} else {
                    throw new Exception("Попытка удаления таблиц не относящихся к модулю!");
                }
			}
		}
		
		public function setMigrateSql() {
			$xmlObj = simplexml_load_file($this->tempDir . "/install/install.xml");

			$versionCurrent = "v" . trim($this->versionCurrent);
            $file = $xmlObj->migrate->$versionCurrent->sql;


			if (empty($file)) {
                throw new Exception("Обновление с версии {$this->versionCurrent} до {$this->mod['version']} не предусмотрено!");
            }

            $sql = file_get_contents($this->tempDir . "/install/" . $file);

			if (empty($sql)) {
                return false;
            }else{
                $sql = str_replace("#__", "mod_" . $this->mod['module_id'], $sql);//готовим
                if (!$this->checkSQL($sql)) {
                    throw new Exception("Попытка удаления таблиц не относящихся к модулю!");
                }
            }

			$this->db->query($sql);
            $this->add_notice("Таблицы модуля", "Таблицы добавлены", "Успешно", "mod_info");
			return true;
		}
		
		static function xmlParse($arrObjData, $arrSkipIndices = array()) {
			$arrData = array();
    
    		// if input is object, convert into array
    		if (is_object($arrObjData)) {
        		$arrObjData = get_object_vars($arrObjData);
    		}

    		if (is_array($arrObjData)) {
        		foreach ($arrObjData as $index => $value) {
                    if ($index != "comment") {
                        if (is_object($value) || is_array($value)) {
                            $value = self::xmlParse($value, $arrSkipIndices); // recursive call
                        }
                        if (in_array($index, $arrSkipIndices)) {
                            continue;
                        }
                        $arrData[$index] = $value;
                    }
        		}
    		}
    		return $arrData;
		}


        /**
         * обновление модуля
         */
        public function Upgrate() {

			$this->isUpgrate = true;
			$arrForUpgrate = array();		

			$arrForUpgrate = array('m_name' =>$this->mod['module_name'],
				'lastuser'      => $this->lastUser,
                'is_system' 	=> $this->mod['module_system'],
                'is_public' 	=> $this->mod['module_public'],
                'uninstall'     => $this->getUninstallSQL(),
				'version'       => $this->mod['version']
			);

            //проверяем зависимости от модулей
			if ($depend = $this->getDepend()) {								 
				$arrForUpgrate['dependencies'] = $depend;				
			}

            //обрабатываем доступ
			if ($access = $this->getAccess()) {
                $arrForInsert['access_default'] = $access['access_default'];
                $arrForInsert['access_add']     = $access['access_add'];
			}

            //обмнавляем субмодули модуля
            $m_id = $this->db->fetchOne("SELECT `m_id` FROM `core_modules` WHERE `module_id`='".$this->mod['module_id']."'");
            $this->db->query("DELETE FROM `core_submodules` WHERE m_id = {$m_id}");
            if ($subModules = $this->getSubModules($m_id)) {
				foreach ($subModules as $subval) {
					$this->db->insert('core_submodules', $subval);	
				}
                $this->add_notice("Субмодули", "Проверка выполнена", "Субмодули обновлены", "mod_info");
			}

            //обновляем таблицы модуля
            $this->setMigrateSql();

            //копируем файлы из архива
			$this->extractZip();

            //обновляем инфу модуля
            $arrForInsert['visible'] = $this->is_visible == "N" ? "N" : "Y";
            $where = $this->db->quoteInto('module_id = ?', $this->mod['module_id']);
            $this->db->update('core_modules', $arrForUpgrate, $where);


            if ($this->is_visible == "N") {
                $this->db->update('core_modules', array('visible' => 'N'), $this->db->quoteInto('module_id = ?', $this->mod['module_id']));
                $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "' а потом этот модуль") : " включите модуль";
                $this->add_notice("Обновление", "Обновление завершено", "Для работы{$msg}", "mod_warning");
            } else {
                $this->add_notice("Обновление", "Обновление завершено", "Успешно", "mod_info");
            }
        }
		
		public function Uninstall ($modulePath){
            if (is_writeable($modulePath)) {//если открыта для записи

                $d = opendir($modulePath);
                $msq = "";
                while ($f=readdir($d)){

                    if($f != "." && $f != ".."){
                        if (is_dir($modulePath . "/" . $f)) {
                            $this->Uninstall($modulePath."/".$f);
                        } else {
                            $msq .= "{$f}<br>";
                            unlink($modulePath . "/" . $f);
                        }

                    }
                }
                $this->add_notice("Файлы модуля", $msq, "Файлы удалены!", "mod_info");
                rmdir($modulePath);
            }else{
                $this->add_notice("Файлы модуля", "Файлы не удалены", "Папка закрыта на запись, удалите файлы самостоятельно!", "mod_warning");
            }
		}

        /**
         * проверяем используется ли другими модулями наш модуль
         * @param $module_id
         * @return bool
         */
        public function is_used_by_other_modules($module_id) {
            $dependencies = $this->db->fetchAll("
                SELECT *
                FROM `core_modules`
                WHERE `dependencies` IS NOT NULL
            ");

            if (!empty($dependencies)){
                foreach($dependencies as $val){
                    $modules = unserialize(base64_decode($val['dependencies']));

                    if (!empty($modules)){
                        foreach($modules as $module){
                            if ($module['module_id'] == $module_id) return $val['m_name'];
                        }
                    }
                }
            }

            return false;
        }

        /** ищем install_info для нашего модуля
         * @param $module_id
         * @return array
         */
        public function get_install_info($module_id) {
            $installs = $this->db->fetchAll("
                SELECT `install_info`,
                       `data`
                FROM `core_available_modules`
                WHERE `install_info` IS NOT NULL
            ");
            $module_version = $this->db->fetchOne("
                SELECT `version`
                FROM `core_modules`
                WHERE `module_id` = ?
            ", $module_id);

            if (!empty($installs)){
                foreach($installs as $install){
                    $install_info = unserialize($install['install_info']);
                    if ($install_info['install']['module_id'] == $module_id && $install_info['install']['version'] == $module_version){
                        $this->moduleData['data']           = $install['data'];
                        $this->moduleData['install_info']   = $install['install_info'];
                        return $install_info;
                    }
                }
            }

            return array();
        }

        /** создаем зип файл
         * @param $data
         */
        public function make_zip($data) {
            $this->tempFile = $this->config->temp . "/" . session_id() . ".zip";
            file_put_contents($this->tempFile, $data);
        }


        /** отдаём зип файл
         * @param $id
         */
        public function download_zip($id) {

            $info = $this->db->fetchRow("
                SELECT `data`,
                       `install_info`
                FROM `core_available_modules`
                WHERE id = ?
            ", $id);
            $install_info = unserialize($info['install_info']);

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . $install_info['install']['module_id'] . ".zip");
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            echo $info['data'];
            exit;
        }


        /**
         * получение параметров для установки из репозитория
         */
        private function init_install_from_repo() {

            $this->checkXml();

            $this->module_is_off = array();
            $xmlObj = simplexml_load_file($this->tempDir . "/install/install.xml");
            $installInfo 		= $this->xmlParse($xmlObj);

            $this->installInfo 	= $installInfo;
            $this->mod 	        = $installInfo['install'];
            $this->installPath 	= (strtolower($installInfo['install']['module_system']) == "y" ? "core2/mod/{$installInfo['install']['module_id']}/v{$installInfo['install']['version']}" : "mod/{$installInfo['install']['module_id']}");
            $this->listTablesBegin = $this->db->listTables();

            $authNamespace 		= Zend_Registry::get('auth');
            $this->config 		= Zend_Registry::getInstance()->get('config');
            $this->lastUser 	= $authNamespace->ID < 0 ? NULL : $authNamespace->ID;
        }


    /**
     * Проверяем установленные версии модуля
     * @return bool
     * @throws Exception
     */
    public function check_install_for_repo() {
        $this->init_install_from_repo();
        if ($this->versionCurrent == $this->mod['version']) throw new Exception("Установка прервана, эта версия модуля уже установлена!");
        $res = $this->db->fetchAll("SELECT `version` FROM `core_modules` WHERE `module_id`=?", $this->mod['module_id']);
        if (count($res)) {
            $this->versionCurrent = $res[0]['version'];
            return true;
        } else return false;

    }



        /** получаем sql  с деинсталяцией для модуля
         *
         * @return bool
         */
        public function getUninstallSQL() {
            $sql = file_get_contents($this->tempDir . "/install/" . $this->installInfo['uninstall']['sql']);
            if (!empty($sql)) {
                $sql = str_replace("#__", "mod_" . $this->mod['module_id'], $sql);
                if ($this->checkSQL($sql)) {
                    return $sql;
                } else {
                    return null;
                }
            }
        }


        /**
         * добавляем событие для вывода
         * @param $group
         * @param $head
         * @param string $explanation
         * @param string $type
         */
        public function add_notice($group, $head, $explanation = '', $type = ''){
            If (empty($type)) {
                $this->notice_msg[$group][] = "<h3>$head</h3>";
            } elseif ($type == 'mod_info') {
                $this->notice_msg[$group][] = "<div class=\"mod_info\">{$head}<br><font>{$explanation}</font></div>";
            } elseif ($type == 'mod_warning') {
                $this->notice_msg[$group][] = "<div class=\"mod_warning\">{$head}<br><font>{$explanation}</font></div>";
            } elseif ($type == 'mod_danger') {
                $this->notice_msg[$group][] = "<div class=\"mod_danger\">{$head}<br><font>{$explanation}</font></div>";
            }
        }


        /**
         * собираем сообщения в строку
         * @return string
         */
        public function print_notice($tab = false){
            $html = "";
            foreach ($this->notice_msg as $group=>$msges) {
                if (!empty($group)) $html .= "<h3>{$group}</h3>";
                foreach ($msges as $msg) {
                    $html .= $msg;
                }
            }
            if ($tab) {
                $html .= "<br><input type=\"button\" class=\"button\" value=\"Вернуться к списку модулей\" onclick=\"load('index.php?module=admin&action=modules&loc=core&tab_mod={$tab}');\">";
            }
            return $html;
        }
}

?>