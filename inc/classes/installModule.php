<?
require_once(DOC_ROOT . "core2/inc/classes/class.list.php");

/**
 * Class InstallModule
 */
class InstallModule extends Db {

    /**
     * путь для установки модуля
     *
     * @var string
     */
    public $installPath;


    /**
     * Флаг вкл/выкл модуля после установки
     *
     * @var string
     */
    public $is_visible;


    /**
     * массив с эталонным архивом модуля и хэшем файлов
     *
     * @var array
     */
    public $mData = array();


    /**
     * ID юзера, ставящего модуль
     *
     * @var int
     */
    public $lastUser;


    /**
     * Путь к архиву модуля
     *
     * @var string
     */
    private $zipFile;


    /**
     * путь к файлам во временной попке
     *
     * @var string
     */
    public $tempDir;


    /**
     * Информация из install.xml устанавливаемого модуля
     *
     * @var array
     */
    private $mInfo = array();


    /**
     * Версия установленного модуля
     *
     * @var string
     */
    public $curVer;


    /**
     * Список модулей, которые надо включить для корректной работы
     * перед включением модуля
     *
     * @var array
     */
    private $module_is_off;


    /**
     * содержимое conf.ini
     *
     * @var object
     */
    protected $config;


    /**
     * массив с сообщениями
     *
     * @var array
     */
    private $noticeMsg = array();


    /**
     * массив с именами скопированых и не скопированных файлов
     *
     * @var array
     */
    private $copyFilesInfo = array();


    /**
     * массив с именами удаленных и не удаленных файлов
     *
     * @var array
     */
    private $deleteFilesInfo = array();


    /**
     *
     */
    function __construct() {
        parent::__construct();
    }


    /**
     * Проверка существования файла install.xml во временной папке с модулем
     *
     * @throws Exception
     *
     * @return void
     */
    private function checkXml() {
        if (!is_file($this->tempDir . "/install/install.xml")) {
            throw new Exception("install.xml не найден. Установка прервана.");
        }
    }


    /**
     * проверяем чтоб запрос не удалял таблицы не пренадлежащие модулю
     *
     * @param   string  $sql SQL запрос
     *
     * @return  bool
     */
    public function checkSQL($sql){
        $pattern = "/drop table(.*)/im";

        $matches = array();
        preg_match_all($pattern, $sql, $matches);

        foreach ($matches[0] as $match) {
            if (substr_count($match,'mod_' . $this->mInfo['install']['module_id']) < 1) {
                return false;
            }
        }

        return true;
    }


    /**
     * Проверяем, не зависят ли другие модули от данного
     *
     * @param   string  $module_id  Модуль для проверки
     *
     * @return  bool
     */
    private function checkModuleDepend($module_id) {
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
                $this->addNotice("Зависимость от модулей", "Зависит от '{$val['m_name']}'", $status ? "Модуль включен" : "Следует включить этот модуль", $status ? "info" : "warning");
            }
            return true;
        } else {
            $this->addNotice("Зависимость от модулей", "Модуль не установлен" , "Установите модуль \"{$module_id}\"", "danger");
            return false;
        }
    }


    /**
     * Копируем файлы модуля из временной папки в папку с модулями
     *
     * @return void
     * @throws Exception
     */
    private function copyModFiles() {
        //смотрим в какую папку устанавливать
        $dir = (strtolower($this->mInfo['install']['module_system']) == "y" ? "core2/" : "") . "mod/";
        //если открыта для записи
        if (is_writeable($dir)) {
            //удаляем старые файлы
            $this->justDeleteFiles($this->installPath);
            //сперва надо создать папку с модулем
            if (!is_dir("{$dir}/{$this->mInfo['install']['module_id']}")) {
                $this->autoDestination("{$dir}{$this->mInfo['install']['module_id']}");
                //$this->addNotice("Файлы модуля", "Директория \"{$dir}{$this->mInfo['install']['module_id']}\"", "Создана", "info");
            } else {
                //$this->addNotice("Файлы модуля", "Директория \"{$dir}{$this->mInfo['install']['module_id']}\"", "Существует", "info");
            }
            //создаем папку с версией
            if (!is_dir($this->installPath)) {
                $this->autoDestination($this->installPath);
//                $this->addNotice("Файлы модуля", "Директория \"{$this->installPath}\"", "Создана", "info");
            } else {
//                $this->addNotice("Файлы модуля", "Директория \"{$this->installPath}\"", "Существует", "info");
            }

            //копируем файлы в директорию модуля
            $this->copyFiles($this->tempDir, $this->installPath);
        } else {// если закрыта
            $this->is_visible = "N";
            $this->addNotice("Файлы модуля", "Копирование файлов:", "Ошибка: директория с модулями закрыта для записи, скопируйте файлы вручную", "danger");
        }
    }


    /**
     * Распаковка Zip
     *
     * @param   string      $destinationFolder Папка в которую распаковать архив
     *
     * @return  void
     * @throws  Exception
     */
    public function extractZip($destinationFolder) {
        $zip = new ZipArchive();
        $this->autoDestination($destinationFolder);
        if ($zip->open($this->zipFile) === true){
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
     * Проверка и создание директории если необходимо
     *
     * @param   string  $destinationFolder Папка
     *
     * @return  void
     * @throws  Exception
     */
    private function autoDestination($destinationFolder) {
        if (!is_dir($destinationFolder)) {
            if (!mkdir($destinationFolder)) {
                throw new Exception("Не могу создать директорию для разархивирования.");
            }
        }
    }


    /**
     * Обработка прав доступа для модуля
     *
     * @return array
     */
    private function getAccess() {
        $Inf = $this->mInfo;
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
                $this->addNotice("Права доступа", "Права по умолчанию", "Добавлены", "info");
            } else {
                $this->addNotice("Права доступа", "Права по умолчанию", "Отсутствуют", "warning");
            }
            if (!empty($Inf['install']['access']['additional']['rule'])) {
                if (!empty($Inf['install']['access']['additional']['rule']['name'])) {
                    $val = $Inf['install']['access']['additional']['rule'];
                    $Inf['install']['access']['additional']['rule'] = array();
                    $Inf['install']['access']['additional']['rule'][] = $val;
                }
                foreach ($Inf['install']['access']['additional']['rule'] as $value) {
                    $access_add[$value["name"]] = ($value["all"] == "on") ? "all" : ($value["owner"] == "on" ? "owner" : "");
                }
                $this->addNotice("Права доступа", "Дополнительные права", "Добавлены", "info");
            } elseif (!empty($Inf['install']['access']['additional']) && is_array($Inf['install']['access']['additional'])) {
                throw new Exception("Ошибки в install.xml (access > additional)");
            }
        $access = base64_encode(serialize($access));
        $access_add = base64_encode(serialize($access_add));
        return array("access_default" => $access, "access_add" => $access_add);
    }


    /**
     * Получаем модули необходимые для работы устанавливаемого модуля
     *
     * @return array|bool|string
     * @throws Exception
     */
    private function checkNecMods() {
        $Inf = $this->mInfo;
        if (!empty($Inf['install']['dependent_modules']['m'])) {
            $depend = array();
            $is_stop = false;
            if (!is_array($Inf['install']['dependent_modules']['m'])) {
                $val = $Inf['install']['dependent_modules']['m'];
                $Inf['install']['dependent_modules']['m'] = array();
                $Inf['install']['dependent_modules']['m'][] = $val;
            }
            foreach ($Inf['install']['dependent_modules']['m'] as $dep_value) {
                $depend[] = array('module_id' => $dep_value);
                if ($this->checkModuleDepend($dep_value) == false) {
                    $is_stop = true;
                }
            }
            if ($is_stop) {
                throw new Exception("Установите все необходимые модули!");
            }
            $depend = base64_encode(serialize($depend));
            return $depend;
        } elseif (!empty($Inf['install']['dependent_modules']) && is_array($Inf['install']['dependent_modules'])) {
            throw new Exception("Ошибки в install.xml (dependent_modules)");
        } else {
            $this->addNotice("Зависимость от модулей", "Проверка выполнена", "Не имеет зависимостей", "info");
            return false;
        }
    }


    /**
     * Обрабатываем субмодули устанавливаемого модуля
     *
     * @param   int         $m_id   ID устанавливаемого модуля
     *
     * @return  array|bool
     */
    private function getSubModules($m_id) {
        echo "";
        $Inf = $this->mInfo;
        $arrSubModules = array();
        if (!empty($Inf['install']['submodules']['sm'])) {
            if (!empty($Inf['install']['submodules']['sm']['sm_id'])) {
                $val = $Inf['install']['submodules']['sm'];
                $Inf['install']['submodules']['sm'] = array();
                $Inf['install']['submodules']['sm'][] = $val;
            }
            $seq=1;
            foreach ($Inf['install']['submodules']['sm'] as $valsub) {
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
                    }
                    $access_add = base64_encode(serialize($access_add));

                    $arrSubModules[] = array(
                            'sm_name' 		 	=> $valsub['sm_name'],
                            'sm_key' 		 	=> $valsub['sm_id'],
                            'sm_path' 		 	=> empty($valsub['sm_path']) ? "" : $valsub['sm_path'],
                            'visible' 		 	=> 'Y',
                            'm_id' 			 	=> $m_id,
                            'lastuser'  	 	=> $this->lastUser,
                            'seq' 	    	 	=> $seq,
                            'access_default' 	=> $access,
                            'access_add'		=> $access_add
                    );
                    $seq = $seq + 5;
                }
            }
            return $arrSubModules;
        } elseif (!empty($Inf['install']['submodules']) && is_array($Inf['install']['submodules'])) {
            throw new Exception("Ошибки в install.xml (submodules)");
        } else {
            $this->addNotice("Субмодули", "Проверка выполнена", "Модуль не имеет субмодулей", "info");
            return false;
        }

    }


    /**
     * Установка модуля
     *
     * @return  void
     * @throws  Exception
     */
    public function Install() {
        $arrForInsert = array();
        //проверка на зависимость от других модулей
        if ($depend = $this->checkNecMods()) {
            $arrForInsert['dependencies'] = $depend;
        }
        //установка таблиц модуля
        $this->installSql();
        //копирование файлов
        $this->copyModFiles();
        //инфа о модуле
        $arrForInsert['module_id']  = $this->mInfo['install']['module_id'];
        $arrForInsert['global_id']  = uniqid();
        $arrForInsert['m_name']     = $this->mInfo['install']['module_name'];
        $arrForInsert['lastuser']   = $this->lastUser;
        $arrForInsert['is_system']  = $this->mInfo['install']['module_system'];
        $arrForInsert['is_public']  = $this->mInfo['install']['module_public'];
        $arrForInsert['seq']        = $this->db->fetchOne("SELECT MAX(`seq`) FROM `core_modules`") + 5;
        $arrForInsert['uninstall']  = $this->getUninstallSQL();
        $arrForInsert['version']    = $this->mInfo['install']['version'];
        $arrForInsert['files_hash'] = $this->mData['files_hash'];
        $arrForInsert['visible']    = $this->is_visible == "N" ? "N" : "Y";

        //обработка прав доступа
        if ($access = $this->getAccess()) {
            $arrForInsert['access_default'] = $access['access_default'];
            $arrForInsert['access_add']     = $access['access_add'];
        }

        //регистрация модуля
        $this->db->insert('core_modules', $arrForInsert);
        $lastId = $this->db->lastInsertId();
        $this->addNotice("Регистрация модуля", "Операция выполнена", "Успешно", "info");
        //регистрация субмодулей модуля
        $subModules = $this->getSubModules($lastId);
        if (!empty($subModules)) {
            foreach ($subModules as $subval)
            {
                $this->db->insert('core_submodules', $subval);
            }
            $this->addNotice("Субмодули", "Субмодули добавлены", "Успешно", "info");
        }
        //перезаписываем путь к файлам модуля
        $this->cache->remove("is_active_" . $this->mInfo['install']['module_id']);
        $this->cache->remove("is_installed_" . $this->mInfo['install']['module_id']);
        $this->cache->remove($this->mInfo['install']['module_id']);
        //выводим сообщения
        if ($this->is_visible == "N") {
            $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "' а потом этот модуль") : " включите модуль";
            $this->addNotice("Установка", "Установка завершена", "Для работы{$msg}", "warning");
        } else {
            $this->addNotice("Установка", "Установка завершена", "Успешно", "info");
        }
    }


    /**
     * Установка таблиц модуля
     *
     * @return  void
     * @throws  Exception
     */
    public function installSql() {
        $file = $this->mInfo['install']['sql'];//достаём имя файла со структурой
        $sql = file_get_contents($this->tempDir . "/install/" . $file);//достаём из файла структуру
        if (!empty($sql)) {
            $sql = str_replace("#__", "mod_" . $this->mInfo['install']['module_id'], $sql);//готовим
            if ($this->checkSQL($sql)) {
                $this->db->query($sql);//ставим
                $this->addNotice("Таблицы модуля", "Таблицы добавлены", "Успешно", "info");
            } else {
                throw new Exception("Попытка удаления таблиц не относящихся к модулю!");
            }
        }
    }


    /**
     * Обновление таблиц модуля
     *
     * @return bool
     * @throws Exception
     */
    public function migrateSql() {
        $curVer = "v" . trim($this->curVer);
        $file_name = !empty($this->mInfo['migrate'][$curVer]['sql']) ? $this->mInfo['migrate'][$curVer]['sql'] : "";
        $sql = '';
        if (!empty($file_name)) {
            $file_loc = $this->tempDir . "/install/" . $file_name;
            if (!empty($file_name) && is_file($file_loc)) {
                $sql = file_get_contents($file_loc);
            }
        }
        if (empty($sql)) {
            return false;
        } else {
            $sql = str_replace("#__", "mod_" . $this->mInfo['install']['module_id'], $sql);//готовим
            if (!$this->checkSQL($sql)) {
                throw new Exception("Попытка удаления таблиц не относящихся к модулю!");
            }
            $this->db->query($sql);
            $this->addNotice("Таблицы модуля", "Таблицы добавлены", "Успешно", "info");
        }
        return true;
    }


    /**
     * XML object to array
     *
     * @param       $arrObjData
     * @param array $arrSkipIndices
     *
     * @return array
     */
    static function xmlParse($arrObjData, $arrSkipIndices = array()) {
        $arrData = array();

        // if input is object, convert into array
        if (is_object($arrObjData)) {
            $arrObjData = get_object_vars($arrObjData);
        }

        if (is_array($arrObjData)) {
            foreach ($arrObjData as $index => $value) {
                if ((string)$index != "comment") {
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
     * Обновление модуля
     *
     * @return  void
     * @throws  Exception
     */
    private function Upgrate() {
        $arrForUpgrate = array();
        //проверка обновляемой версии версии
        $this->curVer = $this->db->fetchOne("SELECT `version` FROM `core_modules` WHERE `module_id`=?", $this->mInfo['install']['module_id']);
        $this->checkVer();
        //проверяем зависимости от модулей
        if ($depend = $this->checkNecMods()) {
            $arrForUpgrate['dependencies'] = $depend;
        }
        //обновляем таблицы модуля
        $this->migrateSql();
        //копируем файлы из архива
        $this->copyModFiles();
        //инфа о модуле
        $arrForUpgrate['m_name']        = $this->mInfo['install']['module_name'];
        $arrForUpgrate['lastuser']      = $this->lastUser;
        $arrForUpgrate['is_system']     = $this->mInfo['install']['module_system'];
        $arrForUpgrate['is_public']     = $this->mInfo['install']['module_public'];
        $arrForUpgrate['uninstall']     = $this->getUninstallSQL();
        $arrForUpgrate['version']       = $this->mInfo['install']['version'];
        $arrForUpgrate['files_hash']    = $this->mData['files_hash'];
        $arrForUpgrate['visible']       = $this->is_visible == "N" ? "N" : "Y";
        //обрабатываем доступ
        if ($access = $this->getAccess()) {
            $arrForUpgrate['access_default'] = $access['access_default'];
            $arrForUpgrate['access_add']     = $access['access_add'];
        }
        //обновляем инфу о модуле
        $where = $this->db->quoteInto('module_id = ?', $this->mInfo['install']['module_id']);
        $this->db->update('core_modules', $arrForUpgrate, $where);
        //обновляем субмодули модуля
        $m_id = $this->db->fetchOne("SELECT `m_id` FROM `core_modules` WHERE `module_id`='".$this->mInfo['install']['module_id']."'");
        $this->db->query("DELETE FROM `core_submodules` WHERE m_id = {$m_id}");
        if ($subModules = $this->getSubModules($m_id)) {
            foreach ($subModules as $subval) {
                $this->db->insert('core_submodules', $subval);
            }
            $this->addNotice("Субмодули", "Субмодули обновлены", "Успешно", "info");
        }
        //перезаписываем путь к файлам модуля
        $this->cache->remove("is_active_" . $this->mInfo['install']['module_id']);
        $this->cache->remove("is_installed_" . $this->mInfo['install']['module_id']);
        $this->cache->remove($this->mInfo['install']['module_id']);
        //выводим сообщения
        if ($this->is_visible == "N") {
            $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "', а потом этот модуль") : " включите модуль";
            $this->addNotice("Обновление", "Обновление завершено", "Для работы{$msg}", "warning");
        } else {
            $this->addNotice("Обновление", "Обновление завершено", "Успешно", "info");
        }
    }


    /**
     * Удаление директории с файлами и объединение уведомлений
     *
     * @param   $dir директория с файлами
     *
     * @return  void
     */
    public function deleteFolder ($dir){
        $this->deleteFilesInfo = array();
        $this->justDeleteFiles($dir);
        if (!empty($this->deleteFilesInfo['is_not_writeable'])) {
//            asort($this->deleteFilesInfo['is_not_writeable']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['is_not_writeable']), "Папка закрыта для записи, удалите её самостоятельно", "danger");
            $this->addNotice("Файлы модуля", "Удаление", "Папка закрыта для записи, удалите её самостоятельно", "danger");
        }
        if (!empty($this->deleteFilesInfo['success'])) {
//            asort($this->deleteFilesInfo['success']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['success']), "Файлы удалены", "info");
            $this->addNotice("Файлы модуля", "Удаление", "Успешно", "info");
        }
        if (!empty($this->deleteFilesInfo['not_exists'])) {
//            asort($this->deleteFilesInfo['not_exists']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['not_exists']), "Не существует", "info");
            $this->addNotice("Файлы модуля", "Удаление", "Файлы не найдены", "info");
        }
    }


    /**
     * Проверяем используется ли другими модулями наш модуль
     *
     * @param   string      $module_id
     *
     * @return bool|string
     */
    public function isUsedByOtherMods($module_id) {
        $dependencies = $this->db->fetchAll("
            SELECT *
              FROM `core_modules`
             WHERE `dependencies` IS NOT NULL
        ");
        $is_used_by_other_modules = array();
        if (!empty($dependencies)){
            foreach($dependencies as $val){
                $modules = unserialize(base64_decode($val['dependencies']));

                if (!empty($modules)){
                    foreach($modules as $module){
                        if ($module['module_id'] == $module_id) $is_used_by_other_modules[] = $val['m_name'];
                    }
                }
            }
        }

        if (!empty($is_used_by_other_modules)){
            return implode(", ", $is_used_by_other_modules);
        }

        return false;
    }


    /**
     * Создаем архив с модулем во временной попке
     *
     * @param   string  $data Сожержимое архива
     *
     * @return void
     */
    public function make_zip($data) {
        $this->zipFile = $this->config->temp . "/" . session_id() . ".zip";
        file_put_contents($this->zipFile, $data);
    }


    /**
     * Отдаём архив с доступным модулем на скачку
     *
     * @param int   $id Ид модуля
     *
     * @return void
     */
    public function downloadZip($id) {
        try {
            $info = $this->db->fetchRow("
                SELECT `data`,
                       module_id
                FROM `core_available_modules`
                WHERE id = ?
            ", $id);

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . $info['module_id'] . ".zip");
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            echo $info['data'];
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }


    /**
     * Отдаём архив с шаблоном модуля на скачку
     *
     * @param int   $template Название шаблона
     *
     * @return void
     */
    public function downloadModTemplate($template) {
        try {
            $zip_file = $this->config->temp.'/' . $template . '_tmp_'. uniqid() . ".zip";
            $template_path = "core2/mod_tpl/" . $template;

            $zip = new ZipArchive;
            $res = $zip->open($zip_file, ZipArchive::CREATE);
            if ($res === TRUE) {
                $dir = opendir($template_path) or die("Не могу открыть");
                while ($file = readdir($dir)){
                    if ($file != "." && $file != "..") {
                        if (is_dir($template_path."/".$file)) {//если есть вложеные папки

                            $dir2 = opendir($template_path."/".$file) or die("Не могу открыть");
                            while ($file2 = readdir($dir2)){
                                if ($file2 != "." && $file2 != "..") {
                                    if (is_file($template_path . "/" . $file . "/" . $file2))  {
                                        $zip->addFile($template_path . "/" . $file . "/" . $file2, $file . "/" . $file2);
                                    }
                                }
                            }

                        } else if (is_file($template_path."/".$file))  {
                            $zip->addFile($template_path."/".$file, $file);
                        }
                    }
                }
                $zip->close();

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename=' . $template . ".zip");
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                readfile($zip_file);
            } else {
                throw new Exception("Ошибка создания архива");
            }
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }


    /**
     * получаем sql с деинсталяцией для модуля
     *
     * @return bool|string
     */
    public function getUninstallSQL() {
        $sql = file_get_contents($this->tempDir . "/install/" . $this->mInfo['uninstall']['sql']);
        if (!empty($sql)) {
            $sql = str_replace("#__", "mod_" . $this->mInfo['install']['module_id'], $sql);
            if ($this->checkSQL($sql)) {
                return $sql;
            } else {
                $this->addNotice("Таблицы модуля", "В SQL для удаления модуля обнаружена попытка удаления таблиц не относящихся к модулю", "SQL проигнорирован", "warning");
                return null;
            }
        }
        return null;
    }


    /**
     * добавляем сообщение
     *
     * @param   $group
     * @param   $head
     * @param   string  $explanation
     * @param   string  $type           info|info2|warning|danger
     *
     * @return  void
     */
    public function addNotice($group, $head, $explanation = '', $type = ''){
        If (empty($type)) {
            $this->noticeMsg[$group][] = "<h3>$head</h3>";
        } elseif ($type == 'info') {
            $this->noticeMsg[$group][] = "<div class=\"im-msg-green\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'info2') {
            $this->noticeMsg[$group][] = "<div class=\"im-msg-blue\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'warning') {
            $this->noticeMsg[$group][] = "<div class=\"im-msg-yellow\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'danger') {
            $this->noticeMsg[$group][] = "<div class=\"im-msg-red\">{$head}<br><span>{$explanation}</span></div>";
        }
    }


    /**
     * собираем сообщения в строку
     *
     * @return string HTML сообщения
     */
    public function printNotices($tab = false){
        $html = "";
        foreach ($this->noticeMsg as $group=>$msges) {
            if (!empty($group)) $html .= "<h3>{$group}</h3>";
            foreach ($msges as $msg) {
                $html .= $msg;
            }
        }
        $this->noticeMsg = array();
        if ($tab) {
            $html .= "<br><input type=\"button\" class=\"button\" value=\"Вернуться к списку модулей\" onclick=\"load('index.php?module=admin&action=modules&loc=core&tab_mod={$tab}');\">";
        }
        return $html;
    }


    /**
     * Проверка обновляемой версии модуля
     *
     * @return  void
     * @throws  Exception
     */
    public function checkVer()
    {
        if ($this->curVer == $this->mInfo['install']['version']) {
            throw new Exception("У вас уже установлена эта версия!");
        } elseif ($this->curVer > $this->mInfo['install']['version']) {
            throw new Exception("У вас стоит более актуальная версия!");
        }
        $curVer = "v" . trim($this->curVer);
        if (!isset($this->mInfo['migrate'][$curVer])) {
            throw new Exception("обновление для {$curVer} не предусмотрено!");
        }
    }


    /**
     * Копируем файлы модуля из временной папки в директорию сайта
     *
     * @param   string  $dir    Директория откуда копируем
     * @param   string  $dirTo  Директория куда копируем
     *
     * @return  void
     */
    private function justCopyFiles($dir, $dirTo) {
        $cdir = scandir($dir);
        $error   = array();
        $success = array();
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", "..", "install"))) {
                $path   = $dir . DIRECTORY_SEPARATOR . $value;
                $pathTo = $dirTo . DIRECTORY_SEPARATOR . $value;
                if (is_dir($path)) {
                    if (!is_dir($pathTo)) {
                        if (is_writable($dirTo)) {
                            mkdir($pathTo);
                            $this->justCopyFiles($path, $pathTo);
                        } else {
                            $error[] = $dirTo;
                        }
                    }
//                    $this->justCopyFiles($path, $pathTo);

                } else {
                    $result = false;
                    if (is_writable($dirTo)) {
                        $result = copy($path, $pathTo);
                    }
                    if ($result) {
                        $success[]   = $value;
                    } else {
                        $error[] = $value;
                    }
                }
            }
        }
        if (!empty($error)) {
            $this->is_visible = "N";
            foreach ($error as $e) {
                $this->copyFilesInfo['error'][] = "{$dirTo}/{$e}";
            }
        }
        if (!empty($success)) {
            foreach ($success as $s) {
                $this->copyFilesInfo['success'][] = "{$dirTo}/{$s}";
            }
        }
    }


    /**
     * Получаем хэш для файлов из директории
     *
     * @param   string  $dir    Папка с файлами
     *
     * @return  array           Хэш файлов
     */
    public function extractHashForFiles($dir) {
        $info   = array();
        if (is_dir($dir)) {
            $cdir = scandir($dir);
            foreach ($cdir as $key => $value) {
                if (!in_array($value, array(".", ".."))) {
                    $path   = $dir . DIRECTORY_SEPARATOR . $value;
                    if (is_dir($path)) {
                        $info[$value] = array(
                            'type' => 'dir',
                            'cont' => $this->extractHashForFiles($path)
                        );
                    } else {
                        $info[$value] = array(
                            'type' => 'file',
                            'cont' => md5_file($path)
                        );
                    }
                }
            }
        }
        return $info;
    }


    /**
     * Получаем хэш файлов из таблицы core_modules
     *
     * @param   string  $mod_id ID модуля
     *
     * @return  array           Хэш файлов
     */
    public function getFilesHashFromDb($mod_id) {
        $files_hash = $this->db->fetchOne("SELECT files_hash FROM core_modules WHERE module_id = ?", $mod_id);
        return empty($files_hash) ? array() : unserialize($files_hash);
    }


    /**
     * Сравнение хэша файлов
     *
     * @param array $dirhash         Кэш директории с файлами
     * @param array $dbhash          Кэш директории с файлами из БД
     * @param bool  $is_skip_install исключаем папку инстал из проверки или нет
     *
     * @return array                Найденые изменения
     */
    public function compareFilesHash($dirhash, $dbhash, $is_skip_install = true) {

        if ($is_skip_install) {
            foreach ($dbhash as $name=>$cont) {
                if ($name == 'install' && $cont['type'] == 'dir') {
                    unset($dbhash[$name]);
                }
            }
        }

        $compare = array();

        foreach ($dirhash as $name=>$cont) {
            if ($cont['type'] == 'file') {
                if (isset($dbhash[$name]) && $dirhash[$name]['type'] == $dbhash[$name]['type']) {
                    if ($dirhash[$name]['cont'] != $dbhash[$name]['cont']) {
                        $compare[$name] = array(
                            'event' => 'changed',
                            'type'  => $cont['type']
                        );
                    }
                    unset($dbhash[$name]);
                } else {
                    $compare[$name] = array(
                        'event' => 'added',
                        'type'  => $cont['type']
                    );
                }
            } else {
                if (isset($dbhash[$name]) && $dirhash[$name]['type'] == $dbhash[$name]['type']) {
                    $tmp = $this->compareFilesHash($dirhash[$name]['cont'], $dbhash[$name]['cont']);
                    if (!empty($tmp)) {
                        $compare[$name] = array(
                            'event' => 'changed',
                            'type'  => $cont['type'],
                            'cont'  => $tmp
                        );
                    }
                    unset($dbhash[$name]);
                } else {
                    $compare[$name] = array(
                        'event' => 'added',
                        'type'  => $cont['type'],
                        'cont'  => $dirhash[$name]['cont']
                    );
                }
            }
        }

        foreach ($dbhash as $name=>$cont) {
            $compare[$name] = array('event' => 'lost') + $cont;
        }

        return $compare;
    }


    /**
     * Массив с разлициями хэшей файлов пересобираем
     * по категориям удалено/добавлено/изменено
     *
     *@param array $arr    Массив различий хэшей файлов
     *
     *@return array        Массив по категориям удалено/добавлено/изменено
     */
    public function branchesCompareFilesHash($arr) {
        $tmp = array();
        foreach ($arr as $name=>$i) {
            if ($i['type'] == 'file') {
                $tmp[isset($i['event']) ? $i['event'] : 0][] = $name;
            } else {
                $cont = $this->branchesCompareFilesHash($i['cont']);
                foreach ($cont as $ev=>$fls) {
                    foreach ($fls as $fname) {
                        $tmp[!empty($ev) ? $ev : (isset($i['event']) ? $i['event'] : 0)][] = "{$name}/{$fname}";
                    }
                }
            }
        }
        return $tmp;
    }


    /**
     * Копируем файлы модуля из временной папки в директорию сайта
     * с объединением уведомлений
     *
     * @param   string  $dir    Директория откуда копируем
     * @param   string  $dirTo  Директория куда копируем
     *
     * @return  void
     */
    public function copyFiles($dir, $dirTo) {
        $this->copyFilesInfo = array();
        $this->justCopyFiles($dir, $dirTo);
        if (!empty($this->copyFilesInfo['error'])) {
//            asort($this->copyFilesInfo['error']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->copyFilesInfo['error']), "Файлы не скопированы, скопируйте их вручную", "danger");
            $this->addNotice("Файлы модуля", "Копирование", "Файлы не скопированы, скопируйте их вручную", "danger");
        }
        if (!empty($this->copyFilesInfo['success'])) {
//            asort($this->copyFilesInfo['success']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->copyFilesInfo['success']), "Файлы скопированы успешно", "info");
            $this->addNotice("Файлы модуля", "Копирование", "Файлы скопированы успешно", "info");
        }
    }


    /**
     * Удаляем файлы из директории
     *
     * @param   string  $dir    Директория с файлами
     *
     * @return  void
     */
    public function justDeleteFiles ($dir, $is_delete_root = true){
        if (file_exists($dir)) {
            if (is_writeable($dir)) {
                $d = opendir($dir);
                while ($f=readdir($d)){

                    if($f != "." && $f != ".."){
                        if (is_dir($dir . "/" . $f)) {
                            $this->justDeleteFiles($dir."/".$f);
                        } else {
                            unlink($dir . "/" . $f);
                            $this->deleteFilesInfo['success'][] = $dir . "/" . $f;
                        }

                    }
                }
                if ($is_delete_root) {
                    rmdir($dir);
                }
                $this->deleteFilesInfo['success'][] = $dir;
            }else{
                $this->deleteFilesInfo['is_not_writeable'][] = $dir;
            }
        } else {
            $this->deleteFilesInfo['not_exists'][] = $dir;
        }
    }


    /**
     * Подготовка к установке модуля
     *
     * @return  void
     * @throws  Exception
     */
    private function prepareToInstall() {

        //распаковываем архив
        $this->config 	= Zend_Registry::getInstance()->get('config');
        $this->tempDir  = $this->config->temp . "/tmp_" . uniqid();
        $this->make_zip($this->mData['data']);
        $this->extractZip($this->tempDir);

        //проверяем не изменились ли файлы
        $compare = $this->compareFilesHash($this->extractHashForFiles($this->tempDir), unserialize($this->mData['files_hash']), false);
        if (!empty($compare)) {
            throw new Exception("Хэши файлов модуля не совпадают с эталоном! Установка прервана.");
        }

        //проверяем есть ли install.xml и забераем оттуда инфу
        $this->checkXml();
        $xmlObj         = simplexml_load_file($this->tempDir . "/install/install.xml");
        $mInfo 		    = $this->xmlParse($xmlObj);
        $this->mInfo	= $mInfo;

        //путь установки модуля
        $this->installPath 	= ((strtolower($mInfo['install']['module_system']) == "y" ? "core2/" : "") . "mod/{$mInfo['install']['module_id']}/v{$mInfo['install']['version']}");

        //ID юзера, ставящего модуль
        $authNamespace 		= Zend_Registry::get('auth');
        $this->lastUser 	= $authNamespace->ID < 0 ? NULL : $authNamespace->ID;

        $this->module_is_off = array();
    }


    /**
     * Установка модуля из доступных модулей
     *
     * @param   string  $mod_id Название-идентификатор модуля
     *
     * @return  string          HTML процесса установки
     */
    public function mInstall($mod_id){
        try {
            //подготовка к установке модуля
            $temp = $this->db->fetchRow(
                "SELECT `data`,
                        `install_info`,
                        `version`,
                        files_hash
                   FROM `core_available_modules`
                  WHERE `id` = ?",
                $mod_id
            );
            if (empty($temp)) {
                throw new Exception('Модуль не найден в доступных модулях');
            }
            $this->mData['data']          = $temp['data'];
            $this->mData['files_hash']    = $temp['files_hash'];
            $this->prepareToInstall();

            if ($this->isModuleInstalled($this->mInfo['install']['module_id'])) {
                $st = "<h3>Обновляем модуль</h3>";
                $this->Upgrate();
            } else {
                $st = "<h3>Устанавливаем модуль</h3>";
                $this->Install();
            }

            $this->db->commit();
            return $st . $this->printNotices(2);

        } catch (Exception $e) {
            $this->db->rollback();
            $this->addNotice("Установщик", "Установка прервана, произведен откат транзакции", "Ошибка: {$e->getMessage()}", "danger");
            return $st . $this->printNotices(2);
        }
    }


    /**
     * Установка модуля из репозитория
     *
     * @param   string  $repo_url   Подготовленный URL для запроса к репозиторию
     * @param   int     $m_id       ID модуля из репозитория
     *
     * @return  string              HTML процесса установки
     */
    public function mInstallFromRepo($repo_url, $m_id){
        $this->db->beginTransaction();
        try {
            //запрашиваем модуль из репозитория
            $out = $this->doCurlRequestToRepo($repo_url, $m_id);

            //подготовка к установке модуля
            $out = json_decode($out['answer']);
            $data       = base64_decode($out->data);
            $files_hash = base64_decode($out->files_hash);
            if (!empty($data) && empty($out->massage)){//если есть данные и пустые сообщения устанавливаем модуль
                $this->mData['data']        = $data;
                $this->mData['files_hash']  = $files_hash;
            }else{//если есть сообщение значит что-то не так
                throw new Exception($out->massage);
            }
            $this->prepareToInstall();

            if ($this->isModuleInstalled($this->mInfo['install']['module_id'])) {
                $st = "<h3>Обновляем модуль</h3>";
                $this->Upgrate();
            } else {
                $st = "<h3>Устанавливаем модуль</h3>";
                $this->Install();
            }

            $this->db->commit();
            return $st . $this->printNotices(2);

        } catch (Exception $e) {
            $this->db->rollback();
            $this->addNotice("Установщик", "Установка прервана, произведен откат транзакции", "Ошибка: {$e->getMessage()}", "danger");
            return $st . $this->printNotices(2);
        }
    }


    /**
     * Делаем запрос через CURL и отдаем ответ
     *
     * @param   string $url URL
     * @return  array       ответ запроса + http-код ответа
     */
    private function doCurlRequest($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $curl_out = curl_exec($curl);
        //если возникла ошибка
        if (curl_errno($curl) > 0) {
            return array(
                'error'    => curl_errno($curl) . ": ". curl_error($curl)
            );
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array(
            'answer'    => $curl_out,
            'http_code' => $http_code
        );
    }


    /**
     * Делаем запрос к репозиторию и отдаем ответ
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     * @param   string      $request    Ид из репозитория или repo_list
     *
     * @return  array                   Ответ запроса + http-код ответа
     * @throws  Exception
     */
    private function doCurlRequestToRepo($repo_url, $request) {
        //готовим ссылку для запроса модуля из репозитория
        $key = base64_encode(serialize(array(
            "server"    => strtolower(str_replace(array('http://','index.php'), array('',''), $_SERVER['HTTP_REFERER'])),
            "request"   => $request
        )));

        $repo_url = trim($repo_url);
        $url = "{$repo_url}&key={$key}";
        $curl = $this->doCurlRequest($url);
        //если чет пошло не так
        if (!empty($curl['error']) || $curl['http_code'] != 200) {
            if (!empty($curl['error'])) {
                throw new Exception("CURL - {$curl['error']}");
            } else {
                $out = json_decode($curl['answer']);
                throw new Exception("Ответ репозитория - {$out->message}");
            }
        }

        return $curl;
    }


    /**
     * Запрос списка модулей из репозитория
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     *
     * @return  mixed                   Массив с информацией о доступных для установки модулей
     * @throws  Exception
     */
    public function getModsListFromRepo($repo_url) {
        //проверяем есть ли ключь к репозиторию, если нет то получаем
        $repo_url = trim($repo_url);
        if (substr_count($repo_url, "repo?apikey=") == 0) {
            $api_key = $this->getRepoKey($repo_url);
            $repo_url = explode("webservice?reg_apikey=", $repo_url);
            $repo_url = $repo_url[0] . "repo?apikey={$api_key}";
        }
        $this->repo_url = $repo_url;
        //запрашиваем список модулей из репозитория
        $out = $this->doCurlRequestToRepo($repo_url, 'repo_list');
        //достаём список модулей
        $out = json_decode($out['answer']);
        return unserialize(base64_decode($out->data));
    }


    /**
     * Меняем у вебсервиса регистрационный ключь на пользовательский чтобы получить доступ к репозиторию
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     *
     * @return  string                  Apikey для доступа к репозиторию
     * @throws  Exception
     */
    public function getRepoKey($repo_url) {
        //формируем url
        $server = trim($repo_url);
        if (substr_count($server, "webservice?reg_apikey=") == 0) {
            throw new Exception("Не верно задан адрес \"{$server}\"(пример http://REPOSITORY/api/webservice?reg_apikey=YOUR_KEY) для репозитория!");
        } else {
            $tmp = explode("webservice?reg_apikey=", $server);
            $key = $tmp[1];
            $server = $tmp[0];
        }
        $url =  "{$server}webservice?reg_apikey=" . $key . "&name=repo%20{$_SERVER['HTTP_HOST']}";
        //получаем apikey
        $curl = $this->doCurlRequest($url);
        //если чет пошло не так
        if (!empty($curl['error']) || $curl['http_code'] != 200)
        {
            if (!empty($curl['error'])) {
                throw new Exception("CURL - {$curl['error']}");
            } else {
                $out = json_decode($curl['answer']);
                throw new Exception("Ответ вебсервиса репозитория - {$out->message}");
            }
        }
        //если всё гуд
        else
        {
            $out = json_decode($curl['answer']);
            $repos = $this->getCustomSetting("repo");
            $repos = explode(";", $repos);
            foreach($repos as $k=>$r){
                //если находим нашь репозиторий
                if (substr_count($r, $server) > 0) {
                    $repos[$k] = "{$server}repo?apikey={$out->apikey}";
                }
            }
            $repos = implode(";", $repos);
            $this->db->update("core_settings", array("value" => $repos), "code = 'repo'");
        }

        return $out->apikey;
    }


    /**
     * Таблица-список доступных модулей из репозитория
     *
     * @param   string  $repo_url   Подготовленный URL для запроса к репозиторию
     *
     * @return  string              HTML
     */
    public function getHTMLModsListFromRepo($repo_url) {
        $_GET['repo_id'] = !empty($_GET['repo_id']) ? $_GET['repo_id'] : "";
        try {
            //достаём список модулей
            $repo_list = $this->getModsListFromRepo($repo_url);

            //готовим данные для таблицы
            $arr = array();
            foreach ($repo_list as $key=>$val) {
                $arr[$key]['id']            = $key;
                $arr[$key]['name']          = $repo_list[$key]['install']['module_name'];
                $arr[$key]['module_id']     = $repo_list[$key]['install']['module_id'];
                $arr[$key]['descr']         = $repo_list[$key]['install']['description'];
                $arr[$key]['version']       = $repo_list[$key]['install']['version'];
                $arr[$key]['author']        = $repo_list[$key]['install']['author'];
                $arr[$key]['module_system'] = $repo_list[$key]['install']['module_system'] == 'Y' ? "Да" : "Нет";
                $arr[$key]['install_info']  = $repo_list[$key];
            }

            $list_id = "repo_table_" . uniqid();
            $list = new listTable($list_id);
            $list->SQL = "SELECT 1";
            $list->addColumn("Имя модуля", "200px", "TEXT");
            $list->addColumn("Идентификатор", "200px", "TEXT");
            $list->addColumn("Описание", "", "TEXT");
            $list->addColumn("Версия", "150px", "TEXT");
            $list->addColumn("Автор", "150px", "TEXT");
            $list->addColumn("Системный", "50px", "TEXT");
            $list->addColumn("Действие", "96", "BLOCK", 'align=center');
            $list->noCheckboxes = "yes";

            $list->getData();
            $copy_list = $arr;
            if (!empty($copy_list)) {
                $our_available_modules = $this->db->fetchAll("
                    SELECT id,
                         `name`,
                         module_id,
                         descr,
                         version,
                         install_info
                    FROM core_available_modules
                ");
                $listAllModules = $this->db->fetchAll("SELECT module_id, version FROM core_modules");
            }
            $tmp = array();
            foreach ($copy_list as $key=>$val) {
                $mVersion = $val['version'];
                $mId = $val['install_info']['install']['module_id'];
                $mName = $val['name'];
                $copy_list[$key]['install_info'] = "<div onclick=\"installModuleFromRepo('$mName', 'v$mVersion', '{$copy_list[$key]['id']}', '{$this->repo_url}')\"><img src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/></div>";
                foreach ($listAllModules as $allval) {
                    if ($mId == $allval['module_id']) {

                        if ($mVersion == $allval['version']) {
                            $copy_list[$key]['install_info'] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Уже установлен\" border=\"0\"/></a>";
                        }
                    }
                }
                foreach ($our_available_modules as $allval) {
                    $allval['install_info'] = unserialize($allval['install_info']);
                    if ($mId == $allval['install_info']['install']['module_id']) {
                        if ($mVersion == $allval['version']) {
                            $copy_list[$key]['install_info'] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Уже есть\" border=\"0\"/></a>";
                        }
                    }
                }
                $tmp[$mId][$mVersion] = $copy_list[$key];
            }
            //смотрим есть-ли разные версии одного мода
            //если есть, показываем последнюю, осатльные в спойлер
            $copy_list = array();
            foreach ($tmp as $module_id=>$val) {
                ksort($val);
                $max_ver = (max(array_keys($val)));
                $copy_list[$module_id] = $val[$max_ver];
                unset($val[$max_ver]);
                if (!empty($val)) {
                    $copy_list[$module_id]['version'] .= " <a href=\"\" onclick=\"$('.repo_table_{$_GET['repo_id']}_{$module_id}').toggle(); return false;\">Предыдущие версии</a><br>";
                    $copy_list[$module_id]['version'] .= "<table width=\"100%\" class=\"repo_table_{$_GET['repo_id']}_{$module_id}\" style=\"display: none;\"><tbody>";
                    foreach ($val as $version=>$val) {
                        $copy_list[$module_id]['version'] .= "<tr><td style=\"border: 0px; padding: 0px;\">{$version}</td><td style=\"border: 0px; text-align: right; padding: 0px;\">{$val['install_info']}</td></tr>";
                    }
                    $copy_list[$module_id]['version'] .= "</tbody></table>";
                }
            }
            //пагинация
            $per_page = count($copy_list);
            $list->recordsPerPage = $per_page;
            $list->setRecordCount($per_page);

            $list->data = $copy_list;
            $list->showTable();

        } catch (Exception $e) {
            $this->addNotice("", $e->getMessage(), "При подключении к репозиторию произошла ошибка", "danger");
            return $this->printNotices();
        }
    }


    /**
     * Деинсталяция модуля
     *
     * @param   int     $m_id   ID модуля
     *
     * @return  string          HTML процесса удаления
     */
    public function mUninstall($m_id) {
        $m_id = (int)$m_id;
        try {
            $mInfo = $this->db->fetchRow("SELECT is_system, module_id, version, uninstall FROM `core_modules` WHERE `m_id`=?", $m_id);
            $this->mInfo['install']['module_id'] = $mInfo['module_id'];//надо для checkSQL
            //если модуль существует
            if (!empty($mInfo)) {
                $is_used_by_other_modules = $this->isUsedByOtherMods($mInfo['module_id']);
                //если не используется другими модулями
                if ($is_used_by_other_modules === false) {
                    //Удаляем таблицы модуля
                    if (!empty($mInfo['uninstall'])) {
                        $sql = str_replace('#__', 'mod_' . $mInfo['module_id'], $mInfo['uninstall']);
                        if ($this->checkSQL($sql)) {
                            $this->db->query($sql);
                            $this->addNotice("Таблицы модуля", "Удаление таблиц", "Выполнено", "info");
                        } else {
                            $this->addNotice("Таблицы модуля", "Таблицы не удалены", "Попытка удаления таблиц не относящихся к модулю!", "warning");
                        }
                    } else {
                        $this->addNotice("Таблицы модуля", "Таблицы не удалены", "Инструкции по удалению не найдены, удалите их самостоятельно!", "warning");
                    }
                    //удаляем субмодули
                    $this->db->delete("core_submodules", $this->db->quoteInto("m_id =?", $m_id));
                    $this->addNotice("Субмодули", "Удаление субмодулей", "Выполнено", "info");
                    //удаляем регистрацию модуля
                    $this->db->delete('core_modules', $this->db->quoteInto("module_id=?", $mInfo['module_id']));
                    $this->addNotice("Регистрация модуля", "Удаление сведений о модуле", "Выполнено", "info");

                    if ($mInfo['is_system'] == 'N') {
                        $modulePath = (strtolower($mInfo['is_system']) == "y" ? "core2/" : "") .  "mod/{$mInfo['module_id']}/v{$mInfo['version']}";
                        $this->deleteFolder($modulePath);
                    } else {
                        $this->addNotice("Файлы модуля", "Файлы не удалены", "Файлы системных модулей удаляются вручную!", "warning");
                    }

                } else {//если используется другими модулями
                    throw new Exception("Модуль используется модулями {$is_used_by_other_modules}");
                }

                $this->addNotice("Деинсталяция", "Статус", "Завершена", "info");
                return "<h3>Деинсталяция модуля</h3>" . $this->printNotices(1);

            } else{//если модуль не существует
                throw new Exception("Модуль уже удален или не существует!");
            }

        } catch (Exception $e) {
            $this->addNotice("Деинсталяция", "Ошибка: {$e->getMessage()}", "Деинсталяция прервана", "danger");
            return "<h3>Деинсталяция модуля</h3>" . $this->printNotices(1);
        }
    }


    /**
     * Перезапись файлов модуля
     *
     * @param   string  $mod_id Название-идентификатор модуля
     * @param   string  $m_v    Версия модуля
     *
     * @return  string          HTML процесса перезаписи
     */
    public function mRefreshFiles($mod_id, $m_v) {
        try {
            $data       = '';
            $files_hash = '';

            //ищем архив в доступных модулях
            $mod       = $this->db->fetchRow("SELECT `data`, files_hash FROM core_available_modules WHERE module_id = ? AND version = ?", array($mod_id, $m_v));
            if (!empty($mod['data']) && !empty($mod['files_hash'])) {
                $data       = $mod['data'];
                $files_hash = $mod['files_hash'];
                //$this->addNotice("Поиск эталона", "Поиск в доступных модулях", "Архив найден", "info");
            }

            //ищем архив в репозитории
            if (empty($data) || empty($files_hash)) {
                //проверяем заданы ли ссылки на репозитории
                $mod_repos = $this->getSetting('repo');
                if (empty($mod_repos)){
                    $this->addNotice("Поиск эталона", "Поиск в репозиториях", "Репозитории не заданы", "warning");
                } else {
                    $mod_repos = explode(";", $mod_repos);
                    //готовим аякс запросы к репозиториям
                    foreach($mod_repos as $i => $repo_url){
                        $repo_url = trim($repo_url);
                        if (!empty($repo_url) && substr_count($repo_url, "repo?apikey=") != 0) {
                            //запрашиваем список модулей из репозитория
                            $out = $this->doCurlRequestToRepo($repo_url, 'repo_list');
                            $out = json_decode($out['answer']);
                            //достаём список модулей и ищем нужный
                            $repo_list = unserialize(base64_decode($out->data));
                            foreach ($repo_list as $m_id=>$val) {
                                if ($val['install']['module_id'] == $mod_id && $val['install']['version'] == $m_v) {
                                    //запрашиваем нужный модуль
                                    $out = $this->doCurlRequestToRepo($repo_url, $m_id);
                                    $out        = json_decode($out['answer']);
                                    $data       = base64_decode($out->data);
                                    $files_hash = base64_decode($out->files_hash);
                                    if (!empty($data) && !empty($files_hash)) {
                                        //$this->addNotice("Поиск эталона", "Репозиторий {$repo_url}", "Найден нужный модуль", "info");
                                        break(2);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            //заменяем файлы
            if (!empty($data) && !empty($files_hash)) {
                //путь к файлам модуля
                $is_system  = $this->db->fetchOne("SELECT is_system FROM core_modules WHERE module_id = ?", $mod_id);
                $prefix = "";
                if ($is_system == "Y") {
                    $prefix = "core2/";
                }
                $path = "{$prefix}mod/{$mod_id}/v{$m_v}";
                //есди папки модуля не существует, то создаем
                $is_writeable = is_writeable("{$prefix}/mod") || is_writeable($path);
                if ($is_writeable && (!file_exists("{$prefix}mod/{$mod_id}") || !file_exists($path))) {
                    if (!file_exists("{$prefix}/mod/{$mod_id}")) {
                        mkdir("{$prefix}/mod/{$mod_id}");
                    }
                    mkdir($path);
                }
                if (!$is_writeable || (file_exists($path) && !is_writeable($path))) {
                    $this->addNotice("Обновление файлов", "Перезапись файлов прервана", "Папка закрыта для записи", "danger");
                } else {
                    //удаляем старые файлы
                    if (file_exists($path)) {
                        $this->justDeleteFiles($path, false);
                    }
                    //записываем новые файлы
                    $config                 = Zend_Registry::getInstance()->get('config');
                    $tempDir                = $config->temp . "/tmp_" . uniqid();
                    $this->make_zip($data);
                    $this->extractZip($tempDir);
                    $this->justCopyFiles($tempDir, $path);
                    //обновляем инфу о хэшах
                    $this->db->update("core_modules", array('files_hash' => $files_hash), $this->db->quoteInto("module_id = ?", $mod_id));
                    $this->addNotice("Обновление файлов", "Обновление завершено", "Успешно", "info");
                }
            } else {
                $t = array();
                if (empty($files_hash)) {
                    $t[] = "хэши файлов";
                }
                if (empty($data)) {
                    $t[] = "архив";
                }
                $this->addNotice("Обновление файлов", "Не найдены " . implode(" и ", $t), "Перезапись файлов прервана", "danger");
            }
        } catch (Exception $e) {
            $this->addNotice("Обновление файлов", $e->getMessage(), "Ошибка", "danger");
        }

        return $this->printNotices(1);
    }



    public function isModuleInstalled($module_id) {
        return $this->db->fetchOne("SELECT 1 FROM core_modules WHERE module_id = ?", $module_id);
    }
}