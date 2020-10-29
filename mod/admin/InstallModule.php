<?
namespace Core2;

require_once DOC_ROOT . "/core2/inc/classes/Common.php";
require_once DOC_ROOT . "/core2/inc/classes/class.list.php";

use Zend\Session\Container as SessionContainer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

/**
 * Class InstallModule
 */
class InstallModule extends \Common {

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

    private $dependedModList = array();


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
     * Адаптер базы данных
     *
     * @var \Zend_Db_Adapter_Abstract
     */
    private $db;

    private $repos = [];


    /**
     *
     */
    function __construct() {
        parent::__construct();
        $this->module = 'admin';
        $this->setDb();
    }

    /**
     * Собственное подключение к базе данных
     *
     * @return void
     */
    private function setDb() {
        //делаем свое подключение к БД и включаем отображение исключений
        if ($this->moduleConfig->database && $this->moduleConfig->database->admin->username) {
            $this->db = $this->newConnector($this->config->database->params->dbname, $this->moduleConfig->database->admin->username, $this->moduleConfig->database->admin->password, $this->config->database->params->host);
        } else {
            $this->db = $this->newConnector($this->config->database->params->dbname, $this->config->database->params->username, $this->config->database->params->password, $this->config->database->params->host);
        }
        if ($this->config->system->timezone) $this->db->query("SET time_zone = '{$this->config->system->timezone}'");

        $this->db->getConnection()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->getConnection()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        //$db->getConnection()->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
    }


    /**
     * Проверка существования файла install.xml во временной папке с модулем
     *
     * @throws \Exception
     *
     * @return void
     */
    private function checkXml() {
        if (!is_file($this->tempDir . "/install/install.xml")) {
            throw new \Exception($this->translate->tr("install.xml не найден. Установка прервана."));
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
     * @param   array  $mod  Модуль для проверки
     *
     * @return  bool
     */
    public function checkModuleDepend($mod) {
        $existingMod = $this->db->fetchRow("SELECT `m_name`, `visible`, version FROM `core_modules` WHERE `module_id`=?", $mod['module_id']);
        if(empty($existingMod)) {
            return false;
        }
        if(!empty($existingMod) && empty($mod['version'])) {
            return true;
        }
        $version = trim(str_replace(array(">", "<", "="), "", $mod['version']));
        $case = trim(str_replace($version, "", $mod['version']));
        $answer = version_compare($existingMod['version'], $version, $case);
        if($answer) {
            if($existingMod['visible'] != 'Y') {
                $this->module_is_off[] = !empty($mod['module_name']) ? $mod['module_name'] : $mod['module_id'];
                $this->is_visible = "N";
            }
        }
        return $answer;
    }


    /**
     * Копируем файлы модуля из временной папки в папку с модулями
     * //TODO сделать отдельный инсталлер
     *
     * @return void
     * @throws \Exception
     */
    private function copyModFiles() {
        //смотрим в какую папку устанавливать
        $is_system  = strtolower($this->mInfo['install']['module_system']);
        $prefix = "";
        if ($is_system == "y") {
            $prefix = "core2/";
        }
        $pathToMod = "{$prefix}mod/{$this->mInfo['install']['module_id']}";
        $pathToVer = $this->installPath;
        //удаляем старые файлы
//        if (file_exists($pathToVer)) {
//            $this->justDeleteFiles($pathToVer, false);
//        }
        //есди папки модуля не существует, то создаем
        $is_writeable = is_writeable("{$prefix}mod") || is_writeable("{$pathToMod}");
        if ($is_writeable && (!file_exists("{$pathToMod}") || !file_exists($pathToVer))) {
            if (!file_exists("{$pathToMod}")) {
                mkdir("{$pathToMod}");
            }
            mkdir($pathToVer);
        }
        if (!$is_writeable || (file_exists($pathToVer) && !is_writeable($pathToVer))) {
            $this->is_visible = "N";
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Копирование файлов:"), $this->translate->tr("Ошибка: нет доступа для записи, скопируйте файлы вручную"), "danger");
        } else {
            $this->copyFiles($this->tempDir, $pathToVer);
        }
    }


    /**
     * Распаковка Zip
     *
     * @param   string      $destinationFolder Папка в которую распаковать архив
     *
     * @return  void
     * @throws  \Exception
     */
    public function extractZip($destinationFolder) {
        $zip = new \ZipArchive();
        $this->autoDestination($destinationFolder);
        if ($zip->open($this->zipFile) === true){
            /* Распаковка всех файлов архива */
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->extractTo($destinationFolder, $zip->getNameIndex($i));
            }
            $zip->close();
        } else {
            throw new \Exception($this->translate->tr("Ошибка архива"));
        }
    }


    /**
     * Проверка и создание директории если необходимо
     *
     * @param   string  $destinationFolder Папка
     *
     * @return  void
     * @throws  \Exception
     */
    private function autoDestination($destinationFolder) {
        if (!is_dir($destinationFolder)) {
            if (!mkdir($destinationFolder)) {
                throw new \Exception($this->translate->tr("Не могу создать директорию для разархивирования") . " ('{$destinationFolder}').");
            }
        }
    }


    /**
     * Обработка прав доступа для модуля
     *
     * @return array
     * @throws \Exception
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
                $this->addNotice($this->translate->tr("Права доступа"), $this->translate->tr("Права по умолчанию"), $this->translate->tr("Добавлены"), "info");
            } else {
                $this->addNotice($this->translate->tr("Права доступа"), $this->translate->tr("Права по умолчанию"), $this->translate->tr("Отсутствуют"), "warning");
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
                $this->addNotice($this->translate->tr("Права доступа"), $this->translate->tr("Дополнительные права"), $this->translate->tr("Добавлены"), "info");
            } elseif (!empty($Inf['install']['access']['additional']) && is_array($Inf['install']['access']['additional'])) {
                throw new \Exception("Ошибки в структуре install.xml (access > additional)");
            }
        $access = base64_encode(serialize($access));
        $access_add = base64_encode(serialize($access_add));
        return array("access_default" => $access, "access_add" => $access_add);
    }


    /**
     * Получаем модули необходимые для работы устанавливаемого модуля
     *
     * @return array|bool|string
     * @throws \Exception
     */
    private function checkNecMods() {

        //проверка зависимости от версии ядра
        if (isset($this->mInfo['install']['required_core'])) {
            if ( ! $required_core = $this->mInfo['install']['required_core']) {
                throw new \Exception($this->_("Не удалось определить требуемую версию ядра"));

            } else {
                $config = \Zend_Registry::getInstance()->get('core_config');

                if ( ! $config->version) {
                    throw new \Exception($this->_("Не задана версия ядра"));

                } else {
                    if (version_compare($config->version, $required_core, '<')) {
                        throw new \Exception(sprintf($this->_("Требуется ядро %s"), "v$required_core"));
                    }
                }
            }
        }

        $Inf = empty($this->mInfo['install']['dependent_modules']) ? array() : $this->mInfo['install']['dependent_modules'];
        if (!empty($Inf['m']['module_name']) || !empty($Inf['m'][0]['module_name'])) {
            $depend = array();
            $is_stop = false;
            if (!empty($Inf['m']['module_name'])) {
                $tmp2 = $Inf['m'];
                $Inf['m'] = array();
                $Inf['m'][] = $tmp2;
            }
            foreach ($Inf['m'] as $dep_value) {
                $depend[] = $dep_value;
                if ($this->checkModuleDepend($dep_value) == false) {
                    $this->addNotice($this->translate->tr("Зависимость от модулей"), $this->translate->tr("Модуль не установлен"), "Требуется модуль \"{$dep_value['module_name']}\" версии {$dep_value['version']}", "danger");
                    $is_stop = true;
                } else {
                    $this->addNotice($this->translate->tr("Зависимость от модулей"), "Зависит от '{$dep_value['module_name']}'", !in_array($dep_value['module_name'], $this->module_is_off) ? "Модуль включен" : "Следует включить этот модуль", !in_array($dep_value['module_name'], $this->module_is_off) ? "info" : "warning");
                }
            }
            if ($is_stop) {
                throw new \Exception($this->translate->tr("Установите все необходимые модули!"));
            }
            $depend = base64_encode(serialize($depend));
            return $depend;
        }
        //для старых версий install.xml
        elseif (!empty($Inf['m'])) {
            $depend = array();
            $is_stop = false;
            if (!is_array($Inf['m'])) {
                $tmp2 = $Inf['m'];
                $Inf['m'] = array();
                $Inf['m'][] = $tmp2;
            }
            foreach ($Inf['m'] as $dep_value) {
                $depend[] = array('module_id' => $dep_value);
                $is_installed = $this->db->fetchOne("SELECT visible FROM core_modules WHERE module_id = ?", $dep_value);
                if (empty($is_installed)) {
                    $this->addNotice($this->translate->tr("Зависимость от модулей"), "Модуль не установлен" , "Требуется модуль \"{$dep_value}\"", "danger");
                    $is_stop = true;
                } else {
                    if ($is_installed == 'N') {
                        $this->module_is_off[] = $dep_value;
                    }
                    $this->addNotice($this->translate->tr("Зависимость от модулей"), "Зависит от '{$dep_value}'", !in_array($dep_value, $this->module_is_off) ? "Модуль включен" : "Следует включить этот модуль", !in_array($dep_value, $this->module_is_off) ? "info" : "warning");
                }
            }
            if ($is_stop) {
                throw new \Exception($this->translate->tr("Установите все необходимые модули!"));
            }
            $depend = base64_encode(serialize($depend));
            return $depend;
        } elseif (!empty($Inf)) {
            throw new \Exception("Ошибки в структуре install.xml (dependent_modules)");
        } else {
            $this->addNotice($this->translate->tr("Зависимость от модулей"), $this->translate->tr("Проверка выполнена"), $this->translate->tr("Не имеет зависимостей"), "info");
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

                    if (!empty($valsub['access']['additional']['rule'])) {
                        if (!empty($valsub['access']['additional']['rule']['name'])) {
                            $val = $valsub['access']['additional']['rule'];
                            $valsub['access']['additional']['rule'] = array();
                            $valsub['access']['additional']['rule'][] = $val;
                        }
                        foreach ($valsub['access']['additional']['rule'] as $value) {
                            $access_add[$value["name"]] = ($value["all"] == "on") ? "all" : ($value["owner"] == "on" ? "owner" : "");
                        }
                    } elseif (!empty($valsub['access']['additional']) && is_array($valsub['access']['additional'])) {
                        throw new \Exception("Ошибки в структуре install.xml (submodules > access > additional)");
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
            throw new \Exception("Ошибки в структуре install.xml (submodules)");
        } else {
            $this->addNotice($this->translate->tr("Субмодули"), $this->translate->tr("Проверка выполнена"), $this->translate->tr("Модуль не имеет субмодулей"), "info");
            return false;
        }

    }


    /**
     * Установка модуля
     *
     * @return  void
     * @throws  \Exception
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
        //удовлетворяем зависимости модуля
        $this->resolveDependencies(true);
        //инфа о модуле
        $arrForInsert['module_id']       = $this->mInfo['install']['module_id'];
        $arrForInsert['m_name']          = $this->mInfo['install']['module_name'];
        $arrForInsert['lastuser']        = $this->lastUser;
        $arrForInsert['is_system']       = $this->mInfo['install']['module_system'];
        $arrForInsert['is_public']       = $this->mInfo['install']['module_public'];
        $arrForInsert['isset_home_page'] = isset($this->mInfo['install']['isset_home_page']) ? $this->mInfo['install']['isset_home_page'] : 'Y';
        $arrForInsert['seq']             = (int)$this->db->fetchOne("SELECT MAX(`seq`) FROM `core_modules`") + 5;
        $arrForInsert['uninstall']       = $this->getUninstallSQL();
        $arrForInsert['version']         = $this->mInfo['install']['version'];
        $arrForInsert['files_hash']      = $this->mData['files_hash'];
        $arrForInsert['visible']         = $this->is_visible == "N" ? "N" : "Y";

        //обработка прав доступа
        if ($access = $this->getAccess()) {
            $arrForInsert['access_default'] = $access['access_default'];
            $arrForInsert['access_add']     = $access['access_add'];
        }
        //регистрация модуля

        $this->db->insert('core_modules', $arrForInsert);
        //$lastId = $this->db->lastInsertId('core_modules'); //FIXME Не работает, не знаю почему :(
        $lastId = $this->db->fetchOne("SELECT m_id FROM core_modules WHERE module_id=?", $arrForInsert['module_id']);
        $this->addNotice($this->translate->tr("Регистрация модуля"), $this->translate->tr("Операция выполнена"), $this->translate->tr("Успешно"), "info");
        //регистрация субмодулей модуля
        $subModules = $this->getSubModules($lastId);
        if (!empty($subModules)) {
            foreach ($subModules as $subval)
            {
                $this->db->insert('core_submodules', $subval);
            }
            $this->addNotice($this->translate->tr("Субмодули"), $this->translate->tr("Субмодули добавлены"), $this->translate->tr("Успешно"), "info");
        }
        //перезаписываем путь к файлам модуля
        $this->cache->clearByTags(['is_active_core_modules']);
        $this->cache->remove($this->mInfo['install']['module_id']);
        //подключаем *.php если задан
        $this->installFile();
        //выводим сообщения
        if ($this->is_visible == "N") {
            $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "' а потом этот модуль") : " включите модуль";
            $this->addNotice($this->translate->tr("Установка"), $this->translate->tr("Установка завершена"), "Для работы{$msg}", "warning");
        } else {
            $this->addNotice($this->translate->tr("Установка"), $this->translate->tr("Установка завершена"), $this->translate->tr("Успешно"), "info");
        }
    }


    /**
     * Установка таблиц модуля
     *
     * @return  void
     * @throws  \Exception
     */
    private function installSql() {
        if (empty($this->mInfo['install']) || empty($this->mInfo['install']['sql'])) {
            return;
        }

        $file = $this->mInfo['install']['sql'];//достаём имя файла со структурой
        $sql = file_get_contents($this->tempDir . "/install/" . $file);//достаём из файла структуру
        if (!empty($sql)) {
            $sql = $this->SQLPrepareToExecute($sql);//готовим
            if ($this->checkSQL($sql)) {
                //разбиваем запросы на отдельные
                $sql = $this->SQLToQueriesArray($sql);
                foreach ($sql as $qu) {
                    $this->db->query($qu);//TODO исправить работу с last_insert_id()
                }
                $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("Таблицы добавлены"), $this->translate->tr("Успешно"), "info");
            } else {
                throw new \Exception($this->translate->tr("Попытка удаления таблиц не относящихся к модулю!"));
            }
        }
    }

    /**
     * выполнение скриптов инсталляции
     */
    private function installFile() {
        if (!empty($this->mInfo['install']['php']) && file_exists($this->tempDir . "/install/" . $this->mInfo['install']['php'])) {
            require_once($this->tempDir . "/install/" . $this->mInfo['install']['php']);
        }
    }

    /**
     * Обновление таблиц модуля
     *
     * @return bool
     * @throws \Exception
     */
    private function migrateSql() {
        $curVer = "v" . trim($this->curVer);
        $file_name = !empty($this->mInfo['migrate'][$curVer]['sql']) ? $this->mInfo['migrate'][$curVer]['sql'] : "";
        $sql = '';
        if (!empty($file_name)) {
            $file_loc = $this->tempDir . "/install/" . $file_name;
            if (!empty($file_name) && is_file($file_loc)) {
                $sql = file_get_contents($file_loc);
            } else {
                throw new \Exception(sprintf($this->translate->tr("Не найден файл %s для обновления модуля!"), $file_name));
            }
        }
        if (!$sql) {
            return false;
        } else {
            $sql = $this->SQLPrepareToExecute($sql);//готовим
            if (!$this->checkSQL($sql)) {
                throw new \Exception($this->translate->tr("Попытка удаления таблиц не относящихся к модулю!"));
            }
            //разбиваем запросы на отдельные
            $sql = $this->SQLToQueriesArray($sql);
            foreach ($sql as $qu) {
                $this->db->query($qu);//выполняем
            }
            $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("Таблицы добавлены"), "Успешно", "info");
        }
        return true;
    }

    /**
     * выполнение скриптов миграции
     *
     * @return bool
     * @throws \Exception
     */
    private function migrateFile() {
        $curVer = "v" . trim($this->curVer);
        $file_php = !empty($this->mInfo['migrate'][$curVer]['php']) ? $this->mInfo['migrate'][$curVer]['php'] : "";
        //подключаем *.php если задан
        $sql = '';
        if (!empty($file_php)) {
            $file_loc = $this->tempDir . "/install/" . $file_php;
            if (!empty($file_php) && is_file($file_loc)) {
                try {
                    require_once($this->tempDir . "/install/" . $this->mInfo['migrate']["v" . trim($this->curVer)]['php']);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            } else {
                throw new \Exception(sprintf($this->translate->tr("Не найден файл %s для обновления модуля!"), $file_php));
            }
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
                        if (is_array($value) && isset($value[0]) && !$value[0]) {
                            $value = array();
                        }
                    }
                    if (in_array($index, $arrSkipIndices)) {
                        continue;
                    }
                    if (is_scalar($value)) $value = trim($value);
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
     * @throws  \Exception
     */
    private function Upgrade() {
        $arrForUpgrade = array();
        //проверка обновляемой версии версии
        $this->curVer = $this->db->fetchOne("SELECT `version` FROM `core_modules` WHERE `module_id`=?", $this->mInfo['install']['module_id']);
        $this->checkVer();
        //проверяем зависимости от модулей
        if ($depend = $this->checkNecMods()) {
            $arrForUpgrade['dependencies'] = $depend;
        }
        //обновляем таблицы модуля
        $this->migrateSql();
        //копируем файлы из архива
        $this->copyModFiles();
        //удовлетворяем зависимости модуля
        $this->resolveDependencies();
        //инфа о модуле
        //$arrForUpgrade['m_name']        = $this->mInfo['install']['module_name'];
        $arrForUpgrade['lastuser']        = $this->lastUser;
        $arrForUpgrade['is_system']       = $this->mInfo['install']['module_system'];
        $arrForUpgrade['is_public']       = $this->mInfo['install']['module_public'];
        $arrForUpgrade['isset_home_page'] = isset($this->mInfo['install']['isset_home_page']) ? $this->mInfo['install']['isset_home_page'] : 'Y';
        $arrForUpgrade['uninstall']       = $this->getUninstallSQL();
        $arrForUpgrade['version']         = $this->mInfo['install']['version'];
        $arrForUpgrade['files_hash']      = $this->mData['files_hash'];
        $arrForUpgrade['visible']         = $this->is_visible == "N" ? "N" : "Y";
        //обрабатываем доступ
        if ($access = $this->getAccess()) {
            $arrForUpgrade['access_default'] = $access['access_default'];
            $arrForUpgrade['access_add']     = $access['access_add'];
        }
        //обновляем инфу о модуле
        $where = $this->db->quoteInto('module_id = ?', $this->mInfo['install']['module_id']);
        $this->db->update('core_modules', $arrForUpgrade, $where);
        //обновляем субмодули модуля
        $m_id = $this->db->fetchOne("SELECT `m_id` FROM `core_modules` WHERE `module_id`=?", $this->mInfo['install']['module_id']);
        $submodules_exists = $this->dataSubModules->fetchFields(['sm_id', 'sm_name', 'sm_key'], "m_id=?", [$m_id]);
        //
        if ($subModules = $this->getSubModules($m_id)) {
            foreach ($subModules as $subval) {
                $ex = 0;
                foreach ($submodules_exists as $val) {
                    if ($subval['sm_key'] == $val->sm_key) {
                        $ex = 1;
                        continue;
                    }
                }
                if (!$ex) $this->db->insert('core_submodules', $subval);
            }
            foreach ($submodules_exists as $val) {
                $notex = $val->sm_id;
                foreach ($subModules as $subval) {
                    if ($subval['sm_key'] == $val->sm_key) {
                        $notex = 0;
                        continue;
                    }
                }
                if ($notex) $this->db->query("DELETE FROM `core_submodules` WHERE sm_id = ?", $notex);
            }
            $this->addNotice($this->translate->tr("Субмодули"), $this->translate->tr("Субмодули обновлены"), $this->translate->tr("Успешно"), "info");
        }
        //перезаписываем путь к файлам модуля
        $this->cache->clearByTags(['is_active_core_modules']);
        $this->cache->remove($this->mInfo['install']['module_id']);
        //подключаем *.php если задан
        $this->migrateFile();
        //выводим сообщения
        if ($this->is_visible == "N") {
            $msg = !empty($this->module_is_off) ? (" вклчючите '" . implode("','", $this->module_is_off) . "', а потом этот модуль") : " включите модуль";
            $this->addNotice($this->translate->tr("Обновление"), $this->translate->tr("Обновление завершено"), "Для работы{$msg}", "warning");
        } else {
            $this->addNotice($this->translate->tr("Обновление"), $this->translate->tr("Обновление завершено"), $this->translate->tr("Успешно"), "info");
        }
    }


    /**
     * Удаление директории с файлами и объединение уведомлений
     *
     * @param   $folder - путь к папке с файлами
     *
     * @return  void
     */
    public function deleteFolder ($folder){
        $this->deleteFilesInfo = array();
        $this->checkAndDeleteFiles($folder);
        if (!empty($this->deleteFilesInfo['is_not_writeable'])) {
//            asort($this->deleteFilesInfo['is_not_writeable']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['is_not_writeable']), "Папка закрыта для записи, удалите её самостоятельно", "danger");
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Удаление"), $this->translate->tr("Папка закрыта для записи, удалите её самостоятельно"), "danger");
        } elseif (!empty($this->deleteFilesInfo['not_exists'])) {
//            asort($this->deleteFilesInfo['not_exists']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['not_exists']), "Не существует", "info");
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Удаление"), $this->translate->tr("Файлы не найдены"), "info");
        } elseif (!empty($this->deleteFilesInfo['success'])) {
//            asort($this->deleteFilesInfo['success']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->deleteFilesInfo['success']), "Файлы удалены", "info");
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Удаление"), $this->translate->tr("Успешно"), "info");
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
    public function downloadAvailMod($id) {
        try {
            $mod = $this->db->fetchRow("
                SELECT `data`,
                       module_id,
                       version
                FROM `core_available_modules`
                WHERE id = ?
            ", $id);

            $this->returnZipToDownload($mod['data'], $mod['module_id'] . $mod['version']);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }


    /**
     * получаем sql с деинсталяцией для модуля
     *
     * @return bool|string
     */
    public function getUninstallSQL() {
        if (empty($this->mInfo['uninstall']) || empty($this->mInfo['uninstall']['sql'])) {
            return null;
        }
        $sql = file_get_contents($this->tempDir . "/install/" . $this->mInfo['uninstall']['sql']);
        if (!empty($sql)) {
            $sql = $this->SQLPrepareToExecute($sql);
            if ($this->checkSQL($sql)) {
                return $sql;
            } else {
                $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("В SQL для удаления модуля обнаружена попытка удаления таблиц не относящихся к модулю"), "SQL проигнорирован", "warning");
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
            $this->noticeMsg[$group][] = "<div class=\"text-success\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'info2') {
            $this->noticeMsg[$group][] = "<div class=\"text-primary\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'warning') {
            $this->noticeMsg[$group][] = "<div class=\"text-warning\">{$head}<br><span>{$explanation}</span></div>";
        } elseif ($type == 'danger') {
            $this->noticeMsg[$group][] = "<div class=\"text-danger\">{$head}<br><span>{$explanation}</span></div>";
        }
    }


    /**
     * собираем сообщения в строку
     * @param bool $tab
     * @return string HTML сообщения
     */
    public function printNotices($tab = false){
        $html = "";
        foreach ($this->noticeMsg as $group => $msges) {
            if (!empty($group)) $html .= "<h3>{$group}</h3>";
            foreach ($msges as $msg) {
                $html .= $msg;
            }
        }
        $this->noticeMsg = array();
        if ($tab) {
            $html .= "<br><input type=\"button\" class=\"button\" value=\"" . $this->translate->tr('Вернуться к списку модулей') . "\" onclick=\"load('index.php?module=admin&action=modules&tab_mod={$tab}');\">";
        }
        return $html;
    }


    /**
     * Проверка обновляемой версии модуля
     *
     * @return  void
     * @throws  \Exception
     */
    private function checkVer()
    {
        $v = array($this->curVer, $this->mInfo['install']['version']);
        natsort($v);
        //проверка актуальности версии
        if ($v[0] === $v[1]) {
            throw new \Exception($this->translate->tr("У вас уже установлена эта версия!"));
        } elseif (current($v) === $this->mInfo['install']['version']) {
            throw new \Exception($this->translate->tr("У вас установлена более актуальная версия!"));
        }
        //проверка предусмотрено ли обновление
        $curVer = "v" . trim($this->curVer);
        if (!isset($this->mInfo['migrate'][$curVer])) {
            throw new \Exception("обновление для {$curVer} не предусмотрено!");
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
        $compare = array();
        if (file_exists($dirTo)) {
            $dirhash    = $this->extractHashForFiles($dirTo);
            $etalonHash = unserialize($this->mData['files_hash']);
            $dirToArr = explode("/", str_replace('\\', '/', $dirTo));
            if (strtolower($this->mInfo['install']['module_system']) == 'n') {
                $w = 3;
            } else {
                $w = 4;
            }
            if (!empty($dirToArr[$w])) {
                for ($i = $w; $i < count($dirToArr); $i++) {
                    $etalonHash = $etalonHash[$dirToArr[$i]]['cont'];
                }
            }
            $compare    = $this->compareFilesHash($dirhash, $etalonHash);
        }
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", "..", "install"))) {
                $path   = $dir . DIRECTORY_SEPARATOR . $value;
                $pathTo = $dirTo . DIRECTORY_SEPARATOR . $value;
                if (is_dir($path)) {
                    if (!is_dir($pathTo)) {
                        mkdir($pathTo);
                    }
                    $this->justCopyFiles($path, $pathTo);
                } else {
                    if (!empty($compare[$value]) && ($compare[$value]['event'] == 'changed' || $compare[$value]['event'] == 'lost')) {
                        copy($path, $pathTo);
                        $this->copyFilesInfo['success'][] = $pathTo;
                    }
                }
            }
        }
        //удаляем лишние файлы
        foreach ($compare as $fname => $f) {
            if ($f['event'] == 'added' ) {
                $pathTo = $dirTo . DIRECTORY_SEPARATOR . $fname;
                $this->justDeleteFiles($pathTo);
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

        foreach ($dirhash as $name => $cont) {
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
     * @param array $arr Массив различий хэшей файлов
     * @return array     Массив по категориям удалено/добавлено/изменено
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
        $this->checkAndCopyFiles($dir, $dirTo);
        if (!empty($this->copyFilesInfo['error'])) {
//            asort($this->copyFilesInfo['error']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->copyFilesInfo['error']), "Файлы не скопированы, скопируйте их вручную", "danger");
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Копирование"), $this->translate->tr("Файлы не скопированы, скопируйте их вручную"), "danger");
        }
        elseif (!empty($this->copyFilesInfo['success'])) {
//            asort($this->copyFilesInfo['success']);
//            $this->addNotice("Файлы модуля", implode("<br>", $this->copyFilesInfo['success']), "Файлы скопированы успешно", "info");
            $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Копирование"), $this->translate->tr("Файлы скопированы успешно"), "info");
        }
    }


    /**
     * Удаляем файлы или директорию с файлами
     *
     * @param string $loc            Директория или файл
     * @param bool   $is_delete_root
     * @return  void
     */
    public function justDeleteFiles ($loc, $is_delete_root = true){
        if (file_exists($loc)) {
            if (is_writeable($loc)) {
                if (is_dir($loc)) { //если папка
                    $d = opendir($loc);
                    while ($f=readdir($d)){

                        if($f != "." && $f != ".."){
                            if (is_dir($loc . "/" . $f)) {
                                $this->justDeleteFiles($loc."/".$f);
                            } else {
                                unlink($loc . "/" . $f);
                                $this->deleteFilesInfo['success'][] = $loc . "/" . $f;
                            }

                        }
                    }
                    if ($is_delete_root) {
                        rmdir($loc);
                    }
                } else { //если файл
                    unlink($loc);
                }
                $this->deleteFilesInfo['success'][] = $loc;
            }
        }
    }


    /**
     * Подготовка к установке модуля
     *
     * @return  void
     * @throws  \Exception
     */
    private function prepareToInstall() {

        //распаковываем архив
        $this->tempDir  = $this->config->temp . "/tmp_" . uniqid();
        $this->make_zip($this->mData['data']);
        $this->extractZip($this->tempDir);

        //проверяем не изменились ли файлы
        $tmpDirContent = $this->extractHashForFiles($this->tempDir); //содержимое директории с файлами модуля
        if (count($tmpDirContent) == 1) { //скорее всего в директории еще директория с файлами модуля
            $this->tempDir .= "/" . key($tmpDirContent);
            $tmpDirContent = current($tmpDirContent);
            $tmpDirContent = $tmpDirContent['cont'];
        }

        $compare = $this->compareFilesHash($tmpDirContent, unserialize($this->mData['files_hash']), false);
        if (!empty($compare)) {
            throw new \Exception($this->translate->tr("Хэши файлов модуля не совпадают с эталоном! Установка прервана."));
        }

        //проверяем есть ли install.xml и забераем оттуда инфу
        $this->checkXml();
        $xmlObj         = simplexml_load_file($this->tempDir . "/install/install.xml", 'SimpleXMLElement', LIBXML_NOCDATA);
        $mInfo 		    = $this->xmlParse($xmlObj);
        $this->mInfo	= $mInfo;

        //путь установки модуля
        $this->installPath 	= ((strtolower($mInfo['install']['module_system']) == "y" ? "core2/" : "") . "mod/{$mInfo['install']['module_id']}/v{$mInfo['install']['version']}");

        //ID юзера, ставящего модуль
        $authNamespace 		= \Zend_Registry::get('auth');
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
        $this->db->beginTransaction();
        $st = '';
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
                throw new \Exception($this->translate->tr('Модуль не найден в доступных модулях'));
            }
            $this->mData['data']          = $temp['data'];
            $this->mData['files_hash']    = $temp['files_hash'];
            $this->prepareToInstall();

            if ($this->isModuleInstalled($this->mInfo['install']['module_id'])) {
                $st = "<h3>Обновляем модуль '{$this->mInfo['install']['module_name']}' до v{$this->mInfo['install']['version']}</h3>";
                $this->Upgrade();
            } else {
                $st = "<h3>Устанавливаем модуль '{$this->mInfo['install']['module_name']}' v{$this->mInfo['install']['version']}</h3>";
                $this->Install();
            }

            $this->db->commit();
            return $st . $this->printNotices(2);

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->addNotice($this->translate->tr("Установщик"), $this->translate->tr("Установка прервана, произведен откат транзакции"), "Ошибка: {$e->getMessage()}", "danger");
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
        //echo "<pre>";print_r($this->db);echo "</pre>";die;
        $this->db->beginTransaction();
        $st = '';
        try {
            //запрашиваем модуль из репозитория
            $out = $this->doCurlRequestToRepo($repo_url, $m_id);

            //подготовка к установке модуля
            $data       = base64_decode($out->data);
            $files_hash = base64_decode($out->files_hash);
            if (!empty($data) && empty($out->massage)){//если есть данные и пустые сообщения устанавливаем модуль
                $this->mData['data']        = $data;
                $this->mData['files_hash']  = $files_hash;
            } else{//если есть сообщение значит что-то не так
                throw new \Exception($out->massage);
            }
            $this->prepareToInstall();

            if ($this->isModuleInstalled($this->mInfo['install']['module_id'])) {
                $st = "<h3>Обновляем модуль '{$this->mInfo['install']['module_name']}' до v{$this->mInfo['install']['version']}</h3>";
                $this->Upgrade();
            } else {
                $st = "<h3>Устанавливаем модуль '{$this->mInfo['install']['module_name']}' v{$this->mInfo['install']['version']}</h3>";
                $this->Install();
            }

            $this->db->commit();

        } catch (\Exception $e) {
            $this->db->rollBack();
            $msg = $e->getMessage();
            if ($this->config->debug->on) $msg .= $e->getTraceAsString();
            //TODO вести лог
            $this->addNotice($this->translate->tr("Установщик"), $this->translate->tr("Установка прервана, произведен откат транзакции"), "Ошибка: {$msg}", "danger");
        }
        return $st . $this->printNotices(2);
    }


    /**
     * Делаем запрос к репозиторию и отдаем ответ
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     * @param   string      $request    Ид из репозитория или repo_list
     *
     * @return  array                   Ответ запроса + http-код ответа
     * @throws  \Exception
     */
    private function doCurlRequestToRepo($repo_url, $request) {
        //echo "<PRE>";print_r($request);echo "</PRE>";//die;
        $repo_url       = explode("/", $repo_url);
        $request_uri    = array_pop($repo_url);
        $repo_url       = implode("/", $repo_url) . "/";
        if (!isset($this->repos[$repo_url])) {
            $this->repos[$repo_url] = new Client(['base_uri' => $repo_url]);
        }
        $client     = $this->repos[$repo_url];
        //готовим ссылку для запроса модуля из репозитория
        $key = base64_encode(serialize(array(
            "server"    => strtolower(str_replace(array('http://','index.php'), array('',''), $_SERVER['HTTP_REFERER'])),
            "request"   => $request
        )));

        $query = "{$request_uri}&key={$key}";
        try {
            $response = $client->get($query);
            $code = $response->getStatusCode();
            if ($code === 200) {
                $body = $response->getBody()->getContents();
                if ($response->getHeader('Content-Type')[0] !== 'application/json') {
                    throw new \Exception("Не верный формат ответа");
                }
                return json_decode($body);
            }
        } catch (RequestException $e) {
            $msg = '';
            if ($e->hasResponse()) {
                $msg = Psr7\str($e->getResponse());
            }
            if (!$msg) $msg = $e->getMessage();
            throw new \Exception($msg);
        }

    }


    /**
     * Запрос списка модулей из репозитория
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     *
     * @return  mixed                   Массив с информацией о доступных для установки модулей
     * @throws  \Exception
     */
    private function getModsListFromRepo($repo_url) {
        //проверяем есть ли ключ к репозиторию, если нет то получаем
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
        return ! empty($out->data) ? unserialize(base64_decode($out->data)) : [];
    }


    /**
     * Меняем у вебсервиса регистрационный ключ на пользовательский чтобы получить доступ к репозиторию
     *
     * @param   string      $repo_url   Подготовленный URL для запроса к репозиторию
     *
     * @return  string                  Apikey для доступа к репозиторию
     * @throws  \Exception
     */
    private function getRepoKey($repo_url) {
        //формируем url
        $server = trim($repo_url);
        if (substr_count($server, "webservice?reg_apikey=") == 0) {
            throw new \Exception("Не верно задан адрес \"{$server}\"(пример http://REPOSITORY/api/webservice?reg_apikey=YOUR_KEY) для репозитория!");
        } else {
            $tmp = explode("webservice?reg_apikey=", $server);
            $key = $tmp[1];
            $server = $tmp[0];
        }
        $url =  "{$server}webservice?reg_apikey=" . $key . "&name=repo%20{$_SERVER['HTTP_HOST']}";
        //получаем apikey
        $curl = \Tool::doCurlRequest($url);
        //если чет пошло не так
        if (empty($curl['http_code']) || $curl['http_code'] != 200)
        {
            if (!empty($curl['error'])) {
                throw new \Exception("CURL - {$curl['error']}");
            } else {
                $out = json_decode($curl['answer']);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $message = isset($out->message) ? $out->message : '';
                } else {
                    $message = strip_tags($curl['answer'], '<p><b><i><span><small><a><div><ul><ol><li><br><hr><input><select><button><table><caption><tr><th><td>');
                }

                throw new \Exception("Ответ вебсервиса репозитория - {$message}");
            }
        }
        //если всё гуд
        else
        {
            $out = json_decode($curl['answer']);
            $repos = $this->getSetting("repo");
            if ($repos === false) throw new \Exception("Не задан адрес репозитория модулей");
            $repos = explode(";", $repos);
            foreach ($repos as $k => $r) {
                //если находим нашь репозиторий
                if (substr_count($r, $server) > 0) {
                    $repos[$k] = "{$server}repo?apikey={$out->apikey}";
                }
            }
            $repos = implode(";", $repos);
            $this->db->update("core_settings", array("value" => $repos), "code = 'repo'");
            $this->cache->remove("all_settings_" . $this->config->database->params->dbname);
        }

        return $out->apikey;
    }


    /**
     * Таблица-список доступных модулей из репозитория
     *
     * @param   string  $repo_id   порядковый номер репозитория
     *
     */
    public function getHTMLModsListFromRepo($repo_id) {
        try {
            $mod_repos = $this->getSetting('repo');
            $mod_repos = explode(";", $mod_repos);
            $repo_url = $mod_repos[$repo_id];
            //достаём список модулей
            $repo_list = $this->getModsListFromRepo($repo_url);
            if (!$repo_list) throw new \RuntimeException(404);

            $api_key = explode("?apikey=", $repo_url);
            $api_key = !empty($api_key[1]) ? $api_key[1] : uniqid();

            $list_id = "repo_table_" . $api_key;

            $list = new \listTable($list_id);
            $list->ajax = true;
            $list->extOrder = true;
            $list->addSearch("Имя модуля",      '`name`',  	'TEXT');
            $list->addSearch("Идентификатор",	'module_id','TEXT');

            $list->SQL = "SELECT 1";
            $list->addColumn("Имя модуля", "200px", "TEXT");
            $list->addColumn("Идентификатор", "200px", "TEXT");
            $list->addColumn("Описание", "", "TEXT");
            $list->addColumn("Зависимости", "200px", "BLOCK");
            $list->addColumn("Версия", "150px", "TEXT");
            $list->addColumn("Автор", "150px", "TEXT");
            $list->addColumn("Системный", "50px", "TEXT");
            $list->addColumn("Действие", "96", "BLOCK", 'align=center');
            $list->noCheckboxes = "yes";

            $list->getData();

            //ПОИСК
            $ss = new SessionContainer('Search');
            $ssi = 'main_' . $list_id;
            $ss = $ss->$ssi;
            $search = array();
            if (!empty($ss['search'])) {
                $search[0] = mb_strtolower($ss['search'][0], 'utf-8');
                $search[1] = mb_strtolower($ss['search'][1], 'utf-8');
            }

            //формируем список зависимостей доступных модулей
            $this->prepareSearchDependedMods();
            //готовим данные для таблицы
            $arr = array();
            if ( ! empty($repo_list)) {
                foreach ($repo_list as $key => $val) {
                    $ins = $val['install'];
                    //перевариваем зависимости
                    $ins['dependent_modules']['m'] = !empty($ins['dependent_modules']['m']) ? $ins['dependent_modules']['m'] : array();
                    $Inf = $ins['dependent_modules'];
                    if (!empty($Inf['m']['module_name']) || !empty($Inf['m'][0]['module_name']) //новая версия
                        || !empty($Inf['m']) //старая версия
                    ) {
                        if (
                            !empty($Inf['m']['module_name'])  //новая версия
                            || !is_array($Inf['m']) //старая версия
                        ) {
                            $tmp2 = $Inf['m'];
                            $Inf['m'] = array();
                            $Inf['m'][] = $tmp2;
                        }
                        //старая версия
                        foreach ($Inf['m'] as $k => $dep_value) {
                            if (is_string($dep_value)) {
                                $Inf['m'][$k] = array('module_id' => $dep_value);
                            }
                        }
                        $ins['dependent_modules'] = $Inf;
                    }

                    if (
                        (!empty($search[0]) && !mb_substr_count(mb_strtolower($ins['module_name'], 'utf-8'), $search[0], 'utf-8'))
                        || (!empty($search[1]) && !mb_substr_count(mb_strtolower($ins['module_id'], 'utf-8'), $search[1], 'utf-8'))
                    ) {
                        continue;
                    }
                    $arr[$key]['id']            = $key;
                    $arr[$key]['name']          = $ins['module_name'];
                    $arr[$key]['module_id']     = $ins['module_id'];
                    $arr[$key]['descr']         = $ins['description'];
                    $arr[$key]['depends']       = $ins['dependent_modules']['m'];
                    $arr[$key]['version']       = $ins['version'];
                    $arr[$key]['author']        = $ins['author'];
                    $arr[$key]['module_system'] = $ins['module_system'] == 'Y' ? "Да" : "Нет";
                    $arr[$key]['install_info']  = $val;
                    //добавляем к списку доступных зовисимостей зависимости из репозитория
                    if (!isset($this->dependedModList[$ins['module_id']])) $this->dependedModList[$ins['module_id']] = array();
                    $this->dependedModList[$ins['module_id']][$ins['version']] = $ins['dependent_modules']['m'];
                }
            }


            $copy_list = $arr;
            if (!empty($copy_list)) {
                $allMods = array();
                $tmp = $this->db->fetchAll("SELECT module_id, version FROM core_modules");
                foreach ($tmp as $t) {
                    $allMods[$t['module_id']] = $t['version'];
                }
            }
            $tmp = array();
            foreach ($copy_list as $key => $val) {
                $mVersion = $val['version'];
                $mId = $val['install_info']['install']['module_id'];
                $mName = $val['name'];

                //зависимости модуля
                $Inf = !empty($val['install_info']['install']['dependent_modules']) ? $val['install_info']['install']['dependent_modules'] : array();
                if (isset($Inf[0])) unset($Inf[0]);
                $deps = array();
                if (
                    !empty($Inf['m']['module_name']) || !empty($Inf['m'][0]['module_name']) //новая версия
                    || !empty($Inf['m']) //старая версия
                ) {
                    if (
                        !empty($Inf['m']['module_name'])  //новая версия
                        || !is_array($Inf['m']) //старая версия
                    ) {
                        $tmp2 = $Inf['m'];
                        $Inf['m'] = array();
                        $Inf['m'][] = $tmp2;
                    }
                    //старая версия
                    foreach ($Inf['m'] as $k => $dep_value) {
                        if (is_string($dep_value)) {
                            $Inf['m'][$k] = array('module_id' => $dep_value);
                        }
                    }
                    //проверяем в соответствии с условиямив се ли нужные модули установлены
                    $deps = $this->getNeedToInstallDependedModList($Inf['m']);
                } elseif (!empty($Inf)) {
                    $deps[] = "<span style=\"color: red;\">Неверный install.xml</span>";
                }
                $copy_list[$key]['depends'] = implode("<br>", $deps);

                //кнопка установки
                $copy_list[$key]['install_info'] = "";
                if (!empty($allMods[$mId]) && $mVersion <= $allMods[$mId]) {
//                    $copy_list[$key]['install_info'] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Уже установлен\" border=\"0\"/></a>";
                } elseif (!empty($deps)) {
//                    $copy_list[$key]['install_info'] = "<img onclick=\"alert('Сначала установите модули: " . implode(", ", $needToInstall) . "')\" src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/>";
                    $copy_list[$key]['install_info'] = "<img src=\"core2/html/".THEME."/img/box_out_disable.png\" title=\"Требуется установка дополнительных модулей\" border=\"0\"/>";
                } else {
                    $copy_list[$key]['install_info'] = "<img onclick=\"modules.requestToRepo('$mName', 'v$mVersion', '{$copy_list[$key]['id']}', '{$this->repo_url}', 'install');\" src=\"core2/html/".THEME."/img/box_out.png\" border=\"0\" title=\"Установить\"/>";
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
                    $copy_list[$module_id]['version'] .= " <a href=\"\" onclick=\"$('.repo_table_{$repo_id}_{$module_id}').toggle(); return false;\">Предыдущие версии</a><br>";
                    $copy_list[$module_id]['version'] .= "<table width=\"100%\" class=\"repo_table_{$repo_id}_{$module_id}\" style=\"display: none;\"><tbody>";
                    foreach ($val as $version => $val2) {
                        $copy_list[$module_id]['version'] .= "<tr><td style=\"border: 0px; padding: 0px;\">{$version}</td><td style=\"border: 0px; text-align: right; padding: 0px;\">{$val2['install_info']}</td></tr>";
                    }
                    $copy_list[$module_id]['version'] .= "</tbody></table>";
                }
            }
//            //пагинация
//            $per_page = count($copy_list);
//            $list->recordsPerPage = $per_page;
//            $list->setRecordCount($per_page);

            //пагинация
            $ss         = new SessionContainer('Search');
            $ssi        = 'main_' . $list_id;
            $ss         = $ss->$ssi;
            $per_page   = empty($ss["count_{$list_id}"]) ? 1 : (int)$ss["count_{$list_id}"];
            $list->recordsPerPage = $per_page;

            $page       = empty($_GET["_page_{$list_id}"]) ? 1 : (int)$_GET["_page_{$list_id}"];
            $from       = ($page - 1) * $per_page;
            $to         = $page * $per_page;
            $i          = 0;
            $tmp        = array();
            $list->setRecordCount(count($copy_list));
            foreach ($copy_list as $val) {
                $i++;
                if ($i > $from && $i <= $to) {
                    $tmp[] = $val;
                }
            }

            $list->data = $copy_list;
            $list->showTable();

        }
        catch (\RuntimeException $e) {
            $this->addNotice("", $this->translate->tr("При подключении к репозиторию произошла ошибка"), $e->getMessage(), "danger");
            echo $this->printNotices();
        }
        catch (\Exception $e) {
            $this->addNotice("", $this->translate->tr("При подключении к репозиторию произошла ошибка"), $e->getMessage(), "danger");
            echo $this->printNotices();
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
            $mInfo = $this->db->fetchRow("SELECT is_system, module_id, m_name, version, uninstall FROM `core_modules` WHERE `m_id`=?", $m_id);
            $this->mInfo['install']['module_id'] = $mInfo['module_id'];//надо для checkSQL
            //если модуль существует
            if (!empty($mInfo)) {
                $is_used_by_other_modules = $this->isUsedByOtherMods($mInfo['module_id']);
                //если не используется другими модулями
                if ($is_used_by_other_modules === false) {
                    //Удаляем таблицы модуля
                    if (!empty($mInfo['uninstall'])) {
                        $sql = $this->SQLPrepareToExecute($mInfo['uninstall']);
                        if ($this->checkSQL($sql)) {
                            //разбиваем запросы на отдельные
                            $sql = $this->SQLToQueriesArray($sql);
                            foreach ($sql as $qu) {
                                //даже если ошибки в скрипте, удаление продолжается
                                try {
                                    $this->db->query($qu);//выполняем
                                } catch (\Exception $e) {
                                    $this->addNotice($this->translate->tr("Таблицы модуля"), "Ошибка при удалении таблиц (удаление продолжается)", $e->getMessage(), "warning");
                                }
                            }
                            $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("Удаление таблиц"), $this->translate->tr("Выполнение SQL завершено"), "info");
                        } else {
                            $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("Таблицы не удалены"), "Попытка удаления таблиц не относящихся к модулю, удалите их самостоятельно!", "warning");
                        }
                    } else {
                        $this->addNotice($this->translate->tr("Таблицы модуля"), $this->translate->tr("Таблицы не удалены"), "Инструкции по удалению не найдены, удалите их самостоятельно!", "warning");
                    }
                    //удаляем субмодули
                    $this->db->delete("core_submodules", $this->db->quoteInto("m_id =?", $m_id));
                    $this->addNotice($this->translate->tr("Субмодули"), $this->translate->tr("Удаление субмодулей"), $this->translate->tr("Выполнено"), "info");
                    //удаляем регистрацию модуля
                    $this->db->delete('core_modules', $this->db->quoteInto("module_id=?", $mInfo['module_id']));
                    $this->addNotice($this->translate->tr("Регистрация модуля"), $this->translate->tr("Удаление сведений о модуле"), $this->translate->tr("Выполнено"), "info");

                    //чистим кэш
                    $this->cache->clearByTags(['is_active_core_modules']);
                    $this->cache->remove($this->mInfo['install']['module_id']);

                    //удаляем файлы
                    $modulePath = (strtolower($mInfo['is_system']) == "y" ? "core2/" : "") .  "mod/{$mInfo['module_id']}/v{$mInfo['version']}";
                    if (strtolower($mInfo['is_system']) == 'n') {
                        $this->deleteFolder($modulePath);
                    } else {
//                        $this->deleteFolder($modulePath);
                        $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Файлы не удалены"), $this->translate->tr("Файлы системных модулей удаляются вручную!"), "warning");
                    }

                } else {//если используется другими модулями
                    throw new \Exception("Модуль используется модулями {$is_used_by_other_modules}");
                }

                $this->addNotice($this->translate->tr("Деинсталяция"), "Статус", "Завершена", "info");
                return "<h3>Деинсталяция модуля " . (!empty($mInfo['m_name']) ? "'{$mInfo['m_name']}'" : "") . "</h3>" . $this->printNotices(1);

            } else{//если модуль не существует
                throw new \Exception($this->translate->tr("Модуль уже удален или не существует!"));
            }

        } catch (\Exception $e) {
            $this->addNotice($this->translate->tr("Деинсталяция"), "Ошибка: {$e->getMessage()}", $this->translate->tr("Деинсталяция прервана"), "danger");
            return "<h3>Деинсталяция модуля " . (!empty($mInfo['m_name']) ? "'{$mInfo['m_name']}'" : "") . "</h3>" . $this->printNotices(1);
        }
    }


    /**
     * Перезапись файлов модуля
     *
     * @param   string  $mod_id Название-идентификатор модуля
     *
     * @return  string          HTML процесса перезаписи
     */
    public function mRefreshFiles($mod_id) {
        $mod    = $this->db->fetchRow("SELECT m_name, version FROM core_modules WHERE module_id = ?", $mod_id);
        $m_v    = $mod['version'];
        $st = "<h3>Обновляем файлы модуля '{$mod['m_name']}' v{$mod['version']}</h3>";
        try {
            $data       = '';
            $files_hash = '';

            //получаем список всех доступных модулей
            $availMods = $this->getInfoAllAvailMods();

            //ищем нужный модуль
            if (!empty($availMods[$mod_id][$m_v])) {
                $m = $availMods[$mod_id][$m_v];
                if ($m['location'] == 'avail') {
                    $mod       = $this->db->fetchRow("SELECT `data`, files_hash FROM core_available_modules WHERE id = ?", $m['location_id']);
                    if (!empty($mod['data']) && !empty($mod['files_hash'])) {
                        $data       = $mod['data'];
                        $files_hash = $mod['files_hash'];
                    }
                } elseif ($m['location'] == 'repo') {
                    //запрашиваем нужный модуль
                    $out = $this->doCurlRequestToRepo($m['location_url'], $m['location_id']);
                    $data       = base64_decode($out->data);
                    $files_hash = base64_decode($out->files_hash);
                }
            } else {
                $this->addNotice($this->translate->tr("Поиск эталона"), $this->translate->tr("Поиск в доступных модулях и репозиториях"), $this->translate->tr("Модуль не найден"), "warning");
            }

            //заменяем файлы
            if (!empty($data) && !empty($files_hash)) {
                //путь к файлам модуля
                $is_system  = $this->db->fetchOne("SELECT is_system FROM core_modules WHERE module_id = ?", $mod_id);
                $prefix = "";
                if ($is_system == "Y") {
                    $prefix = "core2/";
                }
                $pathToMod = "{$prefix}mod/{$mod_id}";
                $pathToVer = "{$pathToMod}/v{$m_v}";

                //удаляем старые файлы
//                if (file_exists($pathToVer)) {
//                    $this->justDeleteFiles($pathToVer, false);
//                }

                //есди папки модуля не существует, то создаем
                $is_writeable = is_writeable("{$prefix}mod") || is_writeable($pathToMod);
                if ($is_writeable && (!file_exists($pathToMod) || !file_exists($pathToVer))) {
                    if (!file_exists($pathToMod)) {
                        mkdir($pathToMod);
                    }
                    mkdir($pathToVer);
                }
                if (!$is_writeable || (file_exists($pathToVer) && !is_writeable($pathToVer))) {
                    $this->addNotice($this->translate->tr("Обновление файлов"), $this->translate->tr("Перезапись файлов прервана"), $this->translate->tr("Папка закрыта для записи"), "danger");
                } else {
                    //записываем новые файлы
                    $config                 = \Zend_Registry::getInstance()->get('config');
                    $tempDir                = $config->temp . "/tmp_" . uniqid();
                    $this->make_zip($data);
                    $this->extractZip($tempDir);
                    $this->mData['files_hash'] = $files_hash;
                    $this->checkAndCopyFiles($tempDir, $pathToVer);
                    if (!empty($this->copyFilesInfo['error'])) {
                        $this->addNotice($this->translate->tr("Обновление файлов"), $this->translate->tr("Ошибка"), $this->translate->tr("Убедитесь, что есть доступ на запись для всех файлов"), "danger");
                    } else {
                        //обновляем инфу о хэшах
                        $this->db->update("core_modules", array('files_hash' => $files_hash), $this->db->quoteInto("module_id = ?", $mod_id));
                        $this->addNotice($this->translate->tr("Обновление файлов"), $this->translate->tr("Обновление завершено"), $this->translate->tr("Успешно"), "info");
                    }
                }
            } else {
                $t = array();
                if (empty($files_hash)) {
                    $t[] = "хэши файлов";
                }
                if (empty($data)) {
                    $t[] = "архив";
                }
                $this->addNotice($this->translate->tr("Обновление файлов"), "Не найдены " . implode(" и ", $t), $this->translate->tr("Перезапись файлов прервана"), "danger");
            }
        } catch (\Exception $e) {
            $this->addNotice($this->translate->tr("Обновление файлов"), $e->getMessage(), $this->translate->tr("Ошибка"), "danger");
        }

        return $st. $this->printNotices(1);
    }



    /**
     * Отдаём архив на скачку
     *
     * @param $data
     * @param $filename
     */
    public function returnZipToDownload($data, $filename) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename={$filename}.zip");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $data;
        exit;
    }


    /**
     * получаем инфу о всех модулях, которые доступны для установки
     *
     * @return array
     * @throws \Exception
     */
    private function getInfoAllAvailMods () {
        $list = array();
        //берем из доступных модулей
        $mods       = $this->db->fetchAll("SELECT id, install_info FROM core_available_modules");
        foreach ($mods as $m) {
            $i = unserialize($m['install_info']);
            if (isset($i['install']) && isset($i['install']['module_id']) && isset($i['install']['version'])) {
                $i['location'] = 'avail';
                $i['location_id'] = $m['id'];
                $list[$i['install']['module_id']][$i['install']['version']] = $i;
            }
        }

        //проверяем заданы ли ссылки на репозитории
        $mod_repos = $this->getSetting('repo');
        if (!empty($mod_repos)){
            $mod_repos = explode(";", $mod_repos);
            //готовим аякс запросы к репозиториям
            try {
                //готовим аякс запросы к репозиториям
                foreach($mod_repos as $i => $repo_url){
                    $repo_url = trim($repo_url);
                    if (!empty($repo_url) && substr_count($repo_url, "repo?apikey=") != 0) {
                        //запрашиваем список модулей из репозитория
                        $out = $this->doCurlRequestToRepo($repo_url, 'repo_list');
                        //достаём список модулей и ищем нужный
                        $repo_list = ! empty($out->data) ? unserialize(base64_decode($out->data)) : [];
                        foreach ($repo_list as $m_id => $i) {
                            if (empty($list[$i['install']['module_id']][$i['install']['version']])) {
                                $i['location']      = 'repo';
                                $i['location_id']   = $m_id;
                                $i['location_url']  = $repo_url;
                                $list[$i['install']['module_id']][$i['install']['version']] = $i;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {

            }
        }

        return $list;
    }


    /**
     * поиск обновления и обновление конкретного модуля
     *
     * @param $mod_id
     *
     * @return string
     */
    public function checkModUpdates($mod_id) {
        $this->db->beginTransaction();
        try {
            $mod    = $this->db->fetchRow("SELECT m_name, version FROM core_modules WHERE module_id = ?", $mod_id);
            $m_name = $mod['m_name'];
            $m_v    = $mod['version'];
            $st         = "<h3>Обновляем модуль '{$mod['m_name']}'</h3>";
            $data       = '';
            $files_hash = '';
            $ver        = '';

            $update = array($m_v => array());
            $update[$m_v]['install']['module_id'] = $mod_id;

            //получаем список всех доступных модулей
            $availMods = $this->getInfoAllAvailMods();

            //ищем нужный модуль
            if (!empty($availMods[$mod_id])) {
                foreach ($availMods[$mod_id] as $mod_v => $i) {
                    if (array_key_exists($mod_v, $update)) continue;
                    $update[$mod_v] = $i;
                }
            }
            unset($availMods);
            krsort($update, SORT_NATURAL);
            if (count($update) > 1) {
                $update_exists = array();
                foreach ($update as $mod_v => $i) {
                    if (isset($i['migrate']["v{$m_v}"])) {
                        $update_exists = $i;
                        break;
                    }
                }
                $update = $update_exists;
            }


            if (!empty($update)) {
                if ($update['location'] == 'avail') {
                    $mod       = $this->db->fetchRow("SELECT `data`, files_hash FROM core_available_modules WHERE id = ?", $update['location_id']);
                    if (!empty($mod['data']) && !empty($mod['files_hash'])) {
                        $data       = $mod['data'];
                        $files_hash = $mod['files_hash'];
                    }
                } elseif ($update['location'] == 'repo') {
                    //запрашиваем нужный модуль
                    $out = $this->doCurlRequestToRepo($update['location_url'], $update['location_id']);
                    $data       = base64_decode($out->data);
                    $files_hash = base64_decode($out->files_hash);
                }
                $ver = $update['install']['version'];
            }


            //заменяем файлы
            if (!empty($data) && !empty($files_hash)) {
                $st = "<h3>Обновляем модуль '{$m_name}' до v{$ver}</h3>";
                $this->mData['data']          = $data;
                $this->mData['files_hash']    = $files_hash;
                $this->prepareToInstall();
                $this->Upgrade();
            } else {
                $this->addNotice($this->translate->tr("Поиск обновлений"), $this->translate->tr("Поиск в доступных модулях и репозиториях"), $this->translate->tr("Обновления не найдены"), "warning");
            }

            $this->db->commit();

        } catch (\Exception $e) {
            $this->db->rollBack();
            $msg = $e->getMessage();
            if ($this->config->debug->on) $msg .= "<pre>" . $e->getTraceAsString() . "</div>";
            //TODO вести лог
            $this->addNotice($this->translate->tr("Обновление"), $this->translate->tr("Обновление прервано, произведен откат транзакции"), "Ошибка: {$msg}", "danger");
        }

        return $st . $this->printNotices(1);
    }


    /**
     * поиск наличия обновления для модулей
     *
     * @return array
     */
    public function checkInstalledModsUpdates() {
        $updates = array();
        $availMods = $this->getInfoAllAvailMods();
        $allMods = $this->db->fetchAll("SELECT module_id, version FROM core_modules");
        foreach ($allMods as $t) {
            if (!empty($availMods[$t['module_id']])) {
                foreach ($availMods[$t['module_id']] as $mod_v => $i) {
                    if ($mod_v === $t['version']) continue;
                    $comparer = [$mod_v, $t['version']];
                    natsort($comparer);
                    if (current($comparer) === $t['version'] && isset($i['migrate']["v{$t['version']}"])) {
                        $updates[$t['module_id']] = array(
                            'module_id' => $t['module_id'],
                            'version'   => $mod_v,
                            'm_name'    => $i['install']['module_name']
                        );
                    }
                }
            }
        }

        return $updates;
    }


    /**
     * получаем полный список зависимых модулей(включая вложенные) в нужном порядке установки
     */
    private function getDependedModList($mods, $level = 0) {
        //формируем список зависимых модулей

        $list = array();
        foreach ($mods as $m) {
            $deps = $this->searchDependedMods($m);
            //если этот модуль имеет свои зависимости
            if ($level > 7) $deps = []; //избегаем бесконечной рекурсии при перекрестных зависимостях
            if (!empty($deps)) {
                $tmp = $this->getDependedModList($deps, $level + 1);
                foreach ($tmp as $m_id => $m_info) {
                    if (empty($list[$m_id])) {
                        $list[$m_id] = $m_info;
                    } else {
                        if ($list[$m_id]['level'] <= $m_info['level']) {
                            $list[$m_id]['level'] = $m_info['level'];
                            //условия по версиям складываем в массив
                            if (!empty($m_info['version'])) {
                                if (!is_array($m_info['version'])) {
                                    $tmp2 = $m_info['version'];
                                    $m_info['version'] = array();
                                    $m_info['version'][] = $tmp2;
                                }
                                foreach ($m_info['version'] as $v) {
                                    if (empty($list[$m_info['module_id']]['version']) || !in_array($v, $list[$m_info['module_id']]['version'])) {
                                        $list[$m_info['module_id']]['version'][] = $v;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            //добавляем модуль в зависимости
            if (empty($list[$m['module_id']])) {
                $list[$m['module_id']] = array(
                    'module_id'     => $m['module_id'],
                    'module_name'   => !empty($m['module_name']) ? $m['module_name'] : (!empty($this->modListNames[$m['module_id']]) ? $this->modListNames[$m['module_id']] : $m['module_id']),
                    'level'         => $level
                );
                //условия по версиям складываем в массив
                if (!empty($m['version'])) {
                    if (!is_array($m['version'])) {
                        $tmp2 = $m['version'];
                        $m['version'] = array();
                        $m['version'][] = $tmp2;
                    }
                    foreach ($m['version'] as $v) {
                        if (empty($list[$m['module_id']]['version']) || !in_array($v, $list[$m['module_id']]['version'])) {
                            $list[$m['module_id']]['version'][] = $v;
                        }
                    }
                }
            } else {
                if ($list[$m['module_id']]['level'] <= $level) {
                    $list[$m['module_id']]['level'] = $level;
                    //условия по версиям складываем в массив
                    if (!empty($m['version'])) {
                        if (!is_array($m['version'])) {
                            $tmp2 = $m['version'];
                            $m['version'] = array();
                            $m['version'][] = $tmp2;
                        }
                        foreach ($m['version'] as $v) {
                            if (empty($list[$m['module_id']]['version']) || !in_array($v, $list[$m['module_id']]['version'])) {
                                $list[$m['module_id']]['version'][] = $v;
                            }
                        }
                    }
                }
            }
        }

        //сортируем и выводим в нужном порядке
        if ($level == 0) {
            $tmp = array();
            foreach ($list as $l) {
                $tmp[$l['level']][] = $l;
            }
            krsort($tmp);
            $list = array();
            foreach ($tmp as $lvl) {
                foreach ($lvl as $l) {
                    $list[] = $l;
                }
            }
        }

        return $list;
    }

    /**
     * ищем зависимые модули для данного с учетом версионностей
     */
    private function searchDependedMods($mod) {
        //получаем зависимости для всех модулей
        if (empty($this->dependedModList)) {
            $this->prepareSearchDependedMods();
        }

        //с учетом заданной версионности вытаскиваем зависимости для самой актуальной версии
        $ver = '';
        if (empty($mod['version'])) {//в старых установщиках может отсутствовать версия, поэтому берем из доступных самую актуальную
            if (!empty($this->dependedModList[$mod['module_id']])) {
                $ver = max(array_keys($this->dependedModList[$mod['module_id']]));
            }
        } else {
            if (!is_array($mod['version'])) {
                $tmp = array();
                $tmp[] = $mod['version'];
                $mod['version'] = $tmp;
            }
            //все доступные для установки модули
            $ver = !empty($this->dependedModList[$mod['module_id']]) ? $this->dependedModList[$mod['module_id']] : array();
            //листаем массив с версиями и условиями
            foreach ($mod['version'] as $vrsn) {
                $version = trim(str_replace(array(">", "<", "="), "", $vrsn));
                $case = trim(str_replace($version, "", $vrsn));
                foreach ($ver as $v => $m) {
                    if (
                        ($case == ">=" && $v < $version)
                        || ($case == ">" && $v <= $version)
                        || ($case == "<=" && $v > $version)
                        || ($case == "<" && $v >= $version)
                        || ($case == "=" && $v != $version)
                    ) {
                        unset($ver[$v]);
                    }
                }
            }
            if (!empty($ver)) {
                $ver = max(array_keys($ver));
            }
        }
        if (empty($ver)) {
            return array();
        } else {
            return $this->dependedModList[$mod['module_id']][$ver];
        }
    }

    /**
     * формируем массив с зависимостями модулей для каждой версии модуля из доступных модулей
     */
    public function prepareSearchDependedMods() {
        //получаем зависимости для всех модулей
        if (empty($this->dependedModList)) {
            $answer = $this->db->fetchAll(
                "SELECT  module_id,
                        install_info
                  FROM `core_available_modules`"
            );
            $list = array();
            $names = array();
            foreach ($answer as $val) {
                $Inf = unserialize($val['install_info']);
                if (isset($Inf['install']) &&
                    isset($Inf['install']['module_id']) &&
                    isset($Inf['install']['module_name']) &&
                    isset($Inf['install']['version'])
                ) {
                    $names[$Inf['install']['module_id']] = $Inf['install']['module_name'];
                    $version = $Inf['install']['version'];
                    $Inf = !empty($Inf['install']['dependent_modules']) ? $Inf['install']['dependent_modules'] : array();
                    $tmp = array();
                    //достаем зависимости
                    if (!empty($Inf['m']['module_name']) || !empty($Inf['m'][0]['module_name'])) {
                        if (!empty($Inf['m']['module_name'])) {
                            $tmp2 = $Inf['m'];
                            $Inf['m'] = array();
                            $Inf['m'][] = $tmp2;
                        }
                        $tmp = $Inf['m'];
                    }
                    //для старых версий install.xml
                    elseif (!empty($Inf['m'])) {
                        if (!is_array($Inf['m'])) {
                            $tmp2 = $Inf['m'];
                            $Inf['m'] = array();
                            $Inf['m'][] = $tmp2;
                        }
                        foreach ($Inf['m'] as $dep_value) {
                            $tmp[] = array('module_id' => $dep_value);
                        }
                    }
                    $list[$val['module_id']][$version] = $tmp;
                }
            }
            $this->modListNames     = $names;
            $this->dependedModList  = $list;
        }
    }


    /**
     * Проверяем какие зависимые модули трубется установить с учетом версионности
     *
     * @param array $mods
     *
     * @return array массив с HTML модулей требующих установки
     */
    public function getNeedToInstallDependedModList(array $mods) {
        //получаем развернутый список зависимостей включая вложенные, и все в нужном порядке установки
        $mods = $this->getDependedModList($mods);
        //echo "<PRE>";print_r($mods);echo "</PRE>";//die;
        //ищем модули которые необходимо установить и отдаем HTML список
        $deps = array();
        foreach ($mods as $dep_value) {
            //проверяем что бы все условия версионности выполнялись
            $flag = false;
            if (!empty($dep_value['version'])) {
                foreach ($dep_value['version'] as $version) {
                    $mod = array(
                        'module_id' => $dep_value['module_id'],
                        'version' => $version
                    );
                    if ($this->checkModuleDepend($mod) == false) {
                        $flag = true;
                        break;
                    }
                }
                $dep_value['version'] = implode(";", $dep_value['version']);
            } else {
                if ($this->checkModuleDepend($dep_value) == false) {
                    $flag = true;
                }
            }
            //если условия версионности не выполняются
            if ($flag) {
                //старая версия
                if (empty($dep_value['version'])) {
                    $dep_value['version'] = "";
                }
                //старая версия
                if (empty($dep_value['module_name'])) {
                    $dep_value['module_name'] = $dep_value['module_id'];
                }
                $deps[] = "<b style=\"color: red;\">{$dep_value['module_name']} {$dep_value['version']}</b>";
            }
        }
        return $deps;
    }


    /**
     * выполнение проверок перед копированием файлов модуля
     *
     * @param   string  $dir    Директория откуда копируем
     * @param   string  $dirTo  Директория куда копируем
     *
     * @return  void
     */
    private function checksBeforeCopyFiles($dir, $dirTo) {
        //сварниваем эталонные кэши файлов с имеющимися
        $compare = array();
        if (file_exists($dirTo)) {
            $dirhash    = $this->extractHashForFiles($dirTo);
            $etalonHash = unserialize($this->mData['files_hash']);
            $dirToArr = explode("/", str_replace('\\', '/', $dirTo));
            if (strtolower($this->mInfo['install']['module_system']) == 'n') {
                $w = 3;
            } else {
                $w = 4;
            }
            if (!empty($dirToArr[$w])) {
                for ($i = $w; $i < count($dirToArr); $i++) {
                    $etalonHash = $etalonHash[$dirToArr[$i]]['cont'];
                }
            }
            $compare    = $this->compareFilesHash($dirhash, $etalonHash);
        }
        //првоеряем возможность записи и замены имеющихся файлов
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", "..", "install"))) {
                $path   = $dir . DIRECTORY_SEPARATOR . $value;
                $pathTo = $dirTo . DIRECTORY_SEPARATOR . $value;
                if (is_dir($path)) {//папка
                    if (is_writable($dirTo)) {
                        if (is_dir($pathTo)) {
                            $this->checksBeforeCopyFiles($path, $pathTo);
                        }
                    } else {
                        $this->copyFilesInfo['error'][] = $pathTo;
                    }
                } else {//файл
                    if (is_writable($dirTo)) {
                        //если файл изменен или отсутствует, то его надо перезаписать
                        if (!empty($compare[$value]) && ($compare[$value]['event'] == 'changed' || $compare[$value]['event'] == 'lost')) {
                            if (file_exists($pathTo) && !is_writable($pathTo)) {
                                $this->copyFilesInfo['error'][] = $pathTo;
                            }
                        }
                    } else {
                        $this->copyFilesInfo['error'][] = $pathTo;
                    }
                }
            }
        }
        //сможем ли удалить лишние файлы
        foreach ($compare as $fname => $f) {
            if ($f['event'] == 'added') {
                $pathTo = $dirTo . DIRECTORY_SEPARATOR . $fname;
                $this->checksBeforeDeleteFiles($pathTo);
            }
        }
    }


    /**
     * Копируем файлы, исли в проверке небыло ошибок
     *
     * @param   string  $dir    Директория откуда копируем
     * @param   string  $dirTo  Директория куда копируем
     *
     * @return  void
     */
    private function checkAndCopyFiles($dir, $dirTo) {
        //сперва прогоняем проверки, потом выполняем копирование
        $this->checksBeforeCopyFiles($dir, $dirTo);

        if (empty($this->copyFilesInfo) && empty($this->deleteFilesInfo)) {
            //копируем файлы
            $this->justCopyFiles($dir, $dirTo);
        }
    }


    /**
     * Проверка перед удалением файла или директории
     *
     * @param   string  $loc    Директория или файл
     * @param   bool  $is_delete_root
     *
     * @return  void
     */
    public function checksBeforeDeleteFiles ($loc, $is_delete_root = true){
        if (file_exists($loc)) {
            if (is_writeable($loc)) {
                if (is_dir($loc)) { //если папка
                    $d = opendir($loc);
                    while ($f=readdir($d)){
                        if($f != "." && $f != ".."){
                            if (is_dir($loc . "/" . $f)) {
                                $this->checksBeforeDeleteFiles($loc."/".$f);
                            }
                        }
                    }
                }
            } else {
                $this->deleteFilesInfo['is_not_writeable'][] = $loc;
            }
        } else {
            $this->deleteFilesInfo['not_exists'][] = $loc;
        }
    }


    /**
     * Проверяем, и если все ок, то удаляем файлы
     *
     * @param      $loc - Директория или файл
     * @param bool $is_delete_root
     */
    private function checkAndDeleteFiles($loc, $is_delete_root = true){
        //сперва прогоняем проверки, потом выполняем копирование
        $this->checksBeforeDeleteFiles($loc, $is_delete_root);

        if (empty($this->deleteFilesInfo)) {
            //копируем файлы
            $this->justDeleteFiles($loc, $is_delete_root);
        }
    }


    /**
     * подготавливаем к выполнению SQL
     *
     * @param $sql
     *
     * @return mixed
     */
    private function SQLPrepareToExecute($sql){
        $sql = str_replace("#__", "mod_" . $this->mInfo['install']['module_id'], $sql);
        $sql = str_replace("%MODULE_ID%", $this->mInfo['install']['module_id'], $sql);
        return $sql;
    }


    /**
     * разбиваем SQL на запросы и отдаем в массиве
     *
     * @param $sql
     *
     * @return mixed
     */
    private function SQLToQueriesArray($sql){
        $sql = preg_split("~;\s*\n~", $sql);
        $queries = array();
        foreach ($sql as $qu) {
            $qu = trim($qu);
            if ($qu) {
                $queries[] = $qu;
            }
        }
        return $queries;
    }


    /**
     * проверяем SQL на синтаксические ошибки
     *
     * @param $sql
     *
     * @throws \Exception
     */
    public function SQLСheckingSyntax($sql) {
        //заменяем "служебные слова"
        $sql = $this->SQLPrepareToExecute($sql);
        //разбираем на запросы
        $sql = $this->SQLToQueriesArray($sql);
        //проверяем каждый запрос отдельно
        $errors = array();
        foreach ($sql as $qu) {
            try {
                $this->db->prepare($qu);
            }
            catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        if (!empty($errors)) {
            throw new \Exception(implode("<br>", $errors));
        }
    }


    /**
     * задаем свой $mInfo
     *
     * @param $mInfo
     */
    public function setMInfo($mInfo) {
        $this->mInfo = $mInfo;
    }


    /**
     * получаем список файлов заданной директории
     * @param $dir
     * @return array
     */
    public function getFilesList($dir) {
        $cdir = scandir($dir);
        $files = array();
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                $path   = $dir . DIRECTORY_SEPARATOR . $value;
                if (is_dir($path)) {
                    $files = array_merge($files, $this->getFilesList($path));
                } else {
                    $files[] = $path;
                }
            }
        }
        return $files;
    }

    /**
     * Установка зависимостей composer
     * @param bool $install
     * @throws \Exception
     */
    private function resolveDependencies($install = false) {
        //return;
        //is composer.json exists
        if (file_exists($this->installPath . DIRECTORY_SEPARATOR . "composer.json")) {
            chdir($this->installPath);
            $output = "";
            $return_var = "";
            $msg = "";
            exec("composer update 2>&1", $output, $return_var);
            foreach ($output as $k => $item) {
                $item = trim($item);
                if ($item == '[RuntimeException]') {
                    //$msg = $output[$k + 1];
                    $notice = $this->translate->tr("Не удалось провести установку. Попробуйте установить зависимости вручную.");
                    $this->addNotice($this->translate->tr("Файлы модуля"), $this->translate->tr("Проверка зависимостей: "), $notice, "danger");
                }
            }
            if ($msg) {
                throw new \Exception($msg);
            }
        }
    }

}