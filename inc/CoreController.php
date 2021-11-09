<?php

require_once 'classes/Common.php';
require_once 'classes/class.list.php';
require_once 'classes/class.edit.php';
require_once 'classes/class.tab.php';
require_once 'classes/Alert.php';
require_once 'Interfaces/File.php';

require_once DOC_ROOT . "core2/mod/admin/classes/modules/InstallModule.php";
require_once DOC_ROOT . "core2/mod/admin/classes/modules/Gitlab.php";
require_once DOC_ROOT . "core2/mod/admin/classes/settings/Settings.php";
require_once DOC_ROOT . "core2/mod/admin/classes/modules/Modules.php";
require_once DOC_ROOT . "core2/mod/admin/classes/roles/Roles.php";
require_once DOC_ROOT . "core2/mod/admin/classes/enum/Enum.php";
require_once DOC_ROOT . 'core2/inc/classes/Panel.php';

use Laminas\Session\Container as SessionContainer;
use Core2\Mod\Admin;

use Core2\Settings as Settings;
use Core2\Modules as Modules;
use Core2\Roles as Roles;
use Core2\Enum as Enum;
use Core2\InstallModule as Install;


/**
 * Class CoreController
 * @property Users         $dataUsers
 * @property Enum          $dataEnum
 * @property Modules       $dataModules
 * @property Roles         $dataRoles
 * @property SubModules    $dataSubModules
 * @property UsersProfile  $dataUsersProfile
 * @property ModProfileApi $apiProfile
 */
class CoreController extends Common implements File {

    const RP = '187777f095b3006d4dbdf3b3548ac407';
    protected $tpl   = '';
    protected $theme = 'default';


    /**
     * CoreController constructor.
     */
	public function __construct() {
		parent::__construct();

        $this->module = 'admin';
        $this->path   = 'core2/mod/';
        $this->path  .= ! empty($this->module) ? $this->module . "/" : '';

        if ( ! empty($this->config->theme)) {
            $this->theme = $this->config->theme;
        }
	}


    /**
     * @param string $k
     * @param array  $arg
     */
    public function __call ($k, $arg) {
		if ( ! method_exists($this, $k)) {
            return;
        }
	}


	/**
	 * @param string $var
	 * @param mixed  $value
	 */
	public function setVars($var, $value) {
		$this->$var = $value;
	}


    /**
     * @throws Exception
     * @return string
     */
	public function action_index() {

        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            parse_str(file_get_contents("php://input"), $put_vars);
            if ( ! empty($put_vars['exit'])) {
                $this->closeSession();
                return;
            }
        }
       
        if ( ! $this->auth->ADMIN) {
            throw new Exception(911);
        }


        if (isset($_GET['data'])) {
            try {
                switch ($_GET['data']) {
                    case 'clear_cache':
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                            throw new Exception('Некорректный запрос');
                        }

                        $this->cache->clearByNamespace($this->cache->getOptions()->getNamespace());

                        return json_encode(['status' => 'success']);
                }

            } catch (Exception $e) {
                return json_encode([
                    'status'        => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }
        }


        $tab = new tabs('mod');
        $tab->beginContainer($this->_("События аудита"));

        $this->printJsModule('admin', '/assets/js/admin.index.js');

        try {
            $changedMods = $this->checkModulesChanges();
            if (empty($changedMods)) {
                Alert::memory()->info($this->_("Система работает в штатном режиме."));
            } else {
				Alert::memory()->danger(implode(", ", $changedMods), $this->_("Обнаружены изменения в файлах модулей:"));
            }
            if ( ! $this->moduleConfig->database ||
                 ! $this->moduleConfig->database->admin ||
                 ! $this->moduleConfig->database->admin->username
            ) {
				Alert::memory()->warning("Задайте параметр 'database.admin.username' в conf.ini модуля 'admin'", $this->_("Не задан администратор базы данных"));
            }

        } catch (Exception $e) {
			Alert::memory()->danger($e->getMessage(), $this->_("Ошибка"));
        }

        echo Alert::get();


        // Кнопка очистки кэша
        $btn_title = $this->_('Очистить кэш');
        echo "<input class=\"button\" type=\"button\" value=\"{$btn_title}\" onclick=\"AdminIndex.clearCache()\"/>";

        $tab->endContainer();
	}


	/**
	 * @throws Exception
     * @return void|string
	 */
	public function action_modules() {

        if ( ! $this->auth->ADMIN) {
            throw new Exception(911);
        }

        //проверка наличия обновлений для модулей
        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            header('Content-type: application/json; charset="utf-8"');
            parse_str(file_get_contents("php://input"), $put_vars);
            $mods = array();
            if (!empty($put_vars['checkModsUpdates'])) {
                try {
                    $install = new Install();
                    $ups = $install->checkInstalledModsUpdates();
                    foreach ($put_vars['checkModsUpdates'] as $module_id => $m_id) {
                        if (!empty($ups[$module_id])) {
                            $ups[$module_id]['m_id'] = $m_id;
                            $mods[] = $ups[$module_id];
                        }
                    }
                } catch (Exception $e) {
                    $mods[] = $e->getMessage();
                }
            }
            return json_encode($mods);
        }

        //список модулей из репозитория
        if (isset($_GET['getModsListFromRepo'])) {
            $install = new Install();
            $install->getHTMLModsListFromRepo((int) $_GET['getModsListFromRepo']);
            return;
        }
        //скачивание архива модуля
        if (!empty($_GET['download_mod'])) {
            $install = new Install();
            $install->downloadAvailMod($_GET['download_mod']);
            return;
        }
        if (!empty($_GET['__gitlab'])) {
            $gl = new \Core2\Gitlab();
            $gl->getTags();
            return;
        }

        $mods = new Modules();
        if (empty($_POST)) {
            $this->printJs("core2/mod/admin/assets/js/mod.js");
        }

        $panel = new \Panel('tab');
        $panel->addTab($this->_("Установленные модули"), 'install',   "index.php?module=admin&action=modules");
        $panel->addTab($this->_("Доступные модули"),	     'available', "index.php?module=admin&action=modules");
        $panel->setTitle($this->_("Модули"));
        ob_start();
        switch ($panel->getActiveTab()) {
            case 'install':
                if (!empty($_POST)) {
                    /* Обновление файлов модуля */
                    if (!empty($_POST['refreshFilesModule'])) {
                        $install = new Install();
                        return $install->mRefreshFiles($_POST['refreshFilesModule']);
                    }

                    /* Обновление модуля */
                    if (!empty($_POST['updateModule'])) {
                        $install = new Install();
                        return $install->checkModUpdates($_POST['updateModule']);
                    }

                    //Деинсталяция модуля
                    if (isset($_POST['uninstall'])) {
                        $install = new Install();
                        return $install->mUninstall($_POST['uninstall']);
                    }
                }
                if (isset($_GET['edit']) && $_GET['edit'] != '') {
                    $mods->getInstalledEdit((int) $_GET['edit']);
                } else {
                    $mods->getInstalled();
                }
                break;

            case 'available':
                // Инсталяция модуля
                if (!empty($_POST['install'])) {
                    $install = new Install();
                    return $install->mInstall($_POST['install']);
                }
                // Инсталяция модуля из репозитория
                if (!empty($_POST['install_from_repo'])) {
                    $install = new Install();
                    return $install->mInstallFromRepo($_POST['repo'], $_POST['install_from_repo']);
                }
                if (isset($_GET['add_mod'])) {
                    $mods->getAvailableEdit((int) $_GET['add_mod']);
                }
                $mods->getAvailable();

                break;
        }

        $panel->setContent(ob_get_clean());
        return $panel->render();
	}


	/**
     * Переключатель признака активности записи
	 * @throws Exception
     * @return void
	 */
	public function action_switch() {

		try {
            if ( ! isset($_POST['data'])) {
                throw new Exception($this->translate->tr('Произошла ошибка! Не удалось получить данные'));
            }

			$res = explode('.', $_POST['data']);

			preg_match('/[a-z|A-Z|0-9|_|-]+/', trim($res[0]), $arr);
			$table_name = $arr[0];
			$is_active = $res[1];
			$id = isset($res[2]) ? $res[2] : 0;
			if (!$id && !empty($_POST['value'])) {
				$id = (int) $_POST['value'];
			}
			$status = $_POST['is_active'];
			$keys_list = $this->db->fetchRow("SELECT * FROM `{$table_name}` LIMIT 1");
			$keys = array_keys($keys_list);
			$key = $keys[0];
			$where = $this->db->quoteInto($key . "= ?", $id);
			$this->db->update($table_name, array($is_active => $status), $where);
			//очистка кеша активности по всем записям таблицы
			// используется для core_modules
			$this->cache->clearByTags(["is_active_" . $table_name]);

			echo json_encode(array('status' => "ok"));
		} catch (Exception $e) {
			echo json_encode(array('status' => $e->getMessage()));
		}	
	}

	/**
	 * Занимается удалением записей в таблицах базы данных
	 * если в талице есть поле is_deleted_sw, то запись не удаляется, а поле is_deleted_sw принимает значение 'Y'
	 *
	 * @param array $params
	 *
	 * @return bool
	 * @throws Exception
	 */
    public function action_delete(Array $params)
    {
        $sess       = new SessionContainer('List');
        $resource   = $params['res'];
        if (!$resource) throw new Exception($this->translate->tr("Не удалось определить идентификатор ресурса"), 13);
        if (!$params['id']) throw new Exception($this->translate->tr("Нет данных для удаления"), 13);
        $ids        = explode(",", $params['id']);
		$sessData   = $sess->$resource;
        $deleteKey  = $sessData['deleteKey'];
        if (!$deleteKey) throw new Exception($this->translate->tr("Не удалось определить параметры удаления"), 13);
        [$table, $refid] = explode(".", $deleteKey);
        if (!$table || !$refid) throw new Exception($this->translate->tr("Не удалось определить параметры удаления"), 13);

        if (($this->checkAcl($resource, 'delete_all') || $this->checkAcl($resource, 'delete_owner'))) {
            $authorOnly = false;
            if ($this->checkAcl($resource, 'delete_owner') && !$this->checkAcl($resource, 'delete_all')) {
                $authorOnly = true;
            }
            $this->db->beginTransaction();
            try {
                $is = $this->db->fetchAll("EXPLAIN `$table`");

                $nodelete = false;
                $noauthor = true;

                foreach ($is as $value) {
                    if ($value['Field'] == 'is_deleted_sw') {
                        $nodelete = true;
                    }
                    if ($authorOnly && $value['Field'] == 'author') {
                        $noauthor = false;
                    }
                }
                if ($authorOnly) {
                    if ($noauthor) {
                        throw new \Exception($this->translate->tr("Данные не содержат признака автора!"));
                    } else {
                        $auth = new SessionContainer('Auth');
                    }
                }
                if ($nodelete) {
                    foreach ($ids as $key) {
                        $where = array($this->db->quoteInto("`$refid` = ?", $key));
                        if ($authorOnly) {
                            $where[] = $this->db->quoteInto("author = ?", $auth->NAME);
                        }
                        $this->db->update($table, array('is_deleted_sw' => 'Y'), $where);
                    }
                } else {
                    foreach ($ids as $key) {
                        $where = array($this->db->quoteInto("`$refid` = ?", $key));
                        if ($authorOnly) {
                            $where[] = $this->db->quoteInto("author = ?", $auth->NAME);
                        }
                        $this->db->delete($table, $where);
                    }
                }
                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollback();
                throw new \Exception($e->getMessage(), 13);
            }
        } else {
            throw new \Exception(911, 13);
        }
        return true;
    }


	/**
	 * Обновление последовательности записей
	 */
	public function action_seq () {
        if (empty($_POST['id'])) return '{}';
		$this->db->beginTransaction();
		try {
            $ss = new SessionContainer('Search');
            $tbl_id = "main_" . $_POST['id'];
            $tmp = $ss->$tbl_id;
            if ($tmp && !empty($tmp['order'])) {
                throw new \Exception($this->translate->tr("Ошибка! Сначала переключитесь на сортировку по умолчанию."));
            }
			preg_match('/[a-z|A-Z|0-9|_|-]+/', trim($_POST['tbl']), $arr);
			$tbl = $arr[0];
            $id = "id";
            // исключение для списка модулей
            if ($tbl == 'core_modules') $id = 'm_id';
            $sql = "SELECT $id AS id, seq FROM `$tbl` WHERE $id IN ('" . implode("','", $_POST['data']) . "') ORDER BY seq ASC";
			$res = $this->db->fetchPairs($sql);
			if ($res) {
				$values = array_values($res);
				foreach ($_POST['data'] as $k => $val) {
					$where = $this->db->quoteInto("$id=?", $val);
					$this->db->update($tbl, array('seq' => $values[$k]), $where);
				}
			}
			$this->db->commit();
            return '{}';
		} catch (Exception $e) {
			$this->db->rollback();
            return json_encode(array('error' => $e->getMessage()));
		}
	}


	/**
     * Пользователи
	 * @throws Exception
     * @return string
	 */
	public function action_users(): string {

        require_once __DIR__ . "/../mod/admin/classes/users/View.php";
        require_once __DIR__ . "/../mod/admin/classes/users/Users.php";
        require_once __DIR__ . "/../mod/admin/classes/users/User.php";

	    if ( ! $this->auth->ADMIN) {
		    throw new Exception(911);
        }


        if (isset($_GET['data'])) {
            try {
                switch ($_GET['data']) {
                    // Войти под пользователем
                    case 'login_user':
                        $users = new Admin\Users\Users();
                        $users->loginUser($_POST['user_id']);

                        return json_encode([
                            'status' => 'success',
                        ]);
                        break;
                }

            } catch (Exception $e) {
                return json_encode([
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $app   = "index.php?module=admin&action=users";
		$view  = new Admin\Users\View();
        $panel = new Panel();

        ob_start();

        try {
            if (isset($_GET['edit'])) {
                if (empty($_GET['edit'])) {
                    $panel->setTitle($this->_("Создание нового пользователя"), '', $app);
                    echo $view->getEdit($app);

                } else {
                    $user = new Admin\Users\User($_GET['edit']);
                    $panel->setTitle($user->u_login, $this->_('Редактирование пользователя'), $app);
                    echo $view->getEdit($app, $user);
                }


            } else {
                $panel->setTitle($this->_("Справочник пользователей системы"));
                echo $view->getList($app);
            }

        } catch (\Exception $e) {
            echo Alert::danger($e->getMessage(), 'Ошибка');
        }

        $panel->setContent(ob_get_clean());
        return $panel->render();
	}


	/**
     * Субмодуль Конфигурация
	 * @throws Exception
     * @return void
	 */
	public function action_settings () {
		if (!$this->auth->ADMIN) throw new Exception(911);
        $app = "index.php?module=admin&action=settings";
        $settings = new Settings();
        $tab = new tabs('settings');
        $tab->addTab($this->translate->tr("Настройки системы"), 			$app, 130);
        $tab->addTab($this->translate->tr("Дополнительные параметры"), 		$app, 180);
        $tab->addTab($this->translate->tr("Персональные параметры"), 		$app, 180);

        $title = $this->translate->tr("Конфигурация");
        $tab->beginContainer($title);

        if ($tab->activeTab == 1) {
            if (!empty($_GET['edit'])) {
                $settings->edit(-1);
            }
            $settings->stateSystem();
        } elseif ($tab->activeTab == 2) {
            if (isset($_GET['edit'])) {
                if ($_GET['edit']) {
                    $settings->edit($_GET['edit']);
                } else {
                    $settings->create();
                }
            }
            $settings->stateAdd();
        } elseif ($tab->activeTab == 3) {
            if (isset($_GET['edit'])) {
                if ($_GET['edit']) {
                    $settings->edit($_GET['edit']);
                } else {
                    $settings->create();
                }
            }
            $settings->statePersonal();
        }
        $tab->endContainer();
	}


	/**
	 *
	 */
	public function action_welcome () {

		if (!empty($_POST['sendSupportForm'])) {
			if (isset($_POST['supportFormModule'])) {
				$supportFormModule = trim(strip_tags(stripslashes($_POST['supportFormModule'])));
			} else {
				$supportFormModule = '';
			}
			if (isset($_POST['supportFormMessage'])) {
				$supportFormMessage = trim(stripslashes($_POST['supportFormMessage']));
			} else {
				$supportFormMessage = '';
			}
			$supportFormMessagePost = $supportFormMessage;

			header('Content-type: application/json; charset="utf-8"');

			try {
				if (empty($supportFormModule)) {
					throw new Exception($this->translate->tr('Выберите модуль.'));
				}
				if (empty($supportFormMessage)) {
					throw new Exception($this->translate->tr('Введите текст сообщения.'));
				}

				$dataUser = $this->dataUsers->getUserById($this->auth->ID);

				if ($dataUser) {
					$to = $this->getSetting('feedback_email');
					$cc = $this->getSetting('feedback_email_cc');

                    if (empty($to)) {
                        $to = $this->getSetting('admin_email');
                    }

					if (empty($to)) {
                        throw new Exception($this->translate->tr('Не удалось отправить сообщение.'));
                    }

					$html = "<pre>{$supportFormMessage}</pre>";
					$html .= '<hr/><small>';
					$html .= '<b>Хост:</b> ' . $_SERVER['HTTP_HOST'];
					$html .= '<br/><b>Модуль:</b> ' . $supportFormModule;
					$html .= '<br/><b>Пользователь:</b> ' . $dataUser['lastname'] . ' ' . $dataUser['firstname'] . ' ' . $dataUser['middlename'] . ' (Логин: ' . $dataUser['u_login'] . ')';
					$html .= '</small>';

                    $email = $this->createEmail();

                    if ( ! empty($dataUser['email'])) {
                        $email->from($dataUser['email']);
                    }
                    if ( ! empty($cc)) {
                        $email->cc($cc);
                    }

                    $result = $email->to($to)
                        ->subject("Запрос обратной связи от {$_SERVER['HTTP_HOST']} (модуль $supportFormModule).")
                        ->body($html)
                        ->send();

                    if (isset($result['error'])) {
                        throw new Exception($this->translate->tr('Не удалось отправить сообщение'));
                    }

                    $this->apiProfile->sendFeedback($supportFormMessage, [
                        'location_module' => $supportFormModule,
                    ]);
				}
				echo '{}';
			} catch (Exception $e) {
                \Core2\Error::catchJsonException([
                    'error_code'    => $e->getCode(),
                    'error_message' => $e->getMessage()
                ], 500);
            }

            return;
		}
		if (file_exists('mod/home/welcome.php')) {
			require_once 'mod/home/welcome.php';
		}
	}


    /**
     * Перехват запросов на отображение файла
     * @param $context - контекст отображения (fileid, thumbid, tfile)
     * @param $table - имя таблицы, с которой связан файл
     * @param $id - id файла
     * @return bool
     */
    public function action_filehandler($context, $table, $id) {

        // Используется для случая когда не нужно получать список уже загруженных файлов
        if ($table == 'core_users') {
            echo json_encode([]);
            return true;
        }
    }


	/**
	 * Форма обратной связи
	 * @return mixed|string
	 */
	public function feedbackForm() {

		$mods = $this->db->fetchAll("
			SELECT m.module_id,
				   m.m_name,
				   sm.sm_key,
				   sm.sm_name
			FROM core_modules AS m
			    LEFT JOIN core_submodules AS sm ON sm.m_id = m.m_id AND sm.visible = 'Y'
			WHERE m.visible = 'Y' AND m.is_public = 'Y'
			ORDER BY sm.seq
        ");

		$selectMods = '';
		if (count($mods)) {
			$currentMod = array();

			foreach ($mods as $key => $value) {
				if (!$value['module_id']) continue;
				if ($value['sm_key'] && !$this->checkAcl($value['module_id'] . '_' . $value['sm_key'], 'access')) {
					continue;
				} elseif (!$this->checkAcl($value['module_id'], 'access')) {
					continue;
				}

                $value['m_name']  = strip_tags($value['m_name']);
                $value['sm_name'] = strip_tags($value['sm_name']);

				if (!isset($currentMod[$value['m_name']])) {
					$currentMod[$value['m_name']] = array();
				}
				if ($value['sm_key']) {
					$currentMod[$value['m_name']][] = $value['sm_name'];
				}
			}

			foreach ($currentMod as $key => $value) {
				$selectMods .= '<option class="feedBackOption" value="' . $key . '">' . $key . '</option>';
				foreach ($value as $sub) {
					$valueSmMod = $key . '/' . $sub;
					$selectMods .= '<option value="' . $valueSmMod . '">&nbsp; &nbsp;' . $sub . '</option>';
				}
			}
		}
		$this->printJs("core2/mod/admin/assets/js/feedback.js", true);
		require_once 'classes/Templater2.php';
		$tpl = new Templater2("core2/mod/admin/assets/html/feedback.html");
		$tpl->assign('</select>', $selectMods . '</select>');
		return $tpl->parse();
	}


	/**
	 * информация о профиле пользователя
	 * @return string
	 */
	public function userProfile() {

		if ($this->auth->NAME !== 'root' && $this->auth->LDAP) {
			require_once 'core2/inc/classes/LdapAuth.php';
			$ldap = new \Core2\LdapAuth();
			$ldap->getLdapInfo($this->auth->NAME);
		}

		$name = $this->auth->FN;
		if (!empty($name)) {
			$name .= ' ' . $this->auth->MN;
		} else {
			$name = $this->auth->NAME;
		}
		if (!empty($name)) $name = '<b>' . $name . '</b>';
		$out = '<div>' . sprintf($this->translate->tr("Здравствуйте, %s"), $name) . '</div>';
		$sLife = (int)$this->getSetting("session_lifetime");
		if (!$sLife) {
			$sLife = ini_get('session.gc_maxlifetime');
		}
		if ($this->config->database->adapter == 'Pdo_Mysql') {
			$res = $this->db->fetchRow("SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i') AS login_time2,
											   ip
										  FROM core_session
										 WHERE user_id = ?
										   AND (NOW() - last_activity > $sLife)=1
										 ORDER BY login_time DESC
										 LIMIT 1", $this->auth->ID);
		} elseif ($this->config->database->adapter == 'pdo_pgsql') {
			$res = $this->db->fetchRow("SELECT DATE_FORMAT(login_time, '%d-%m-%Y %H:%i') AS login_time2,
											   ip
										  FROM core_session
										 WHERE user_id = ?
										   AND EXTRACT(EPOCH FROM (NOW() - last_activity)) > $sLife
										 ORDER BY login_time DESC
										 LIMIT 1", $this->auth->ID);
		}
		if ($res) {
			$out .= '<div>' . sprintf($this->translate->tr("Последний раз Вы заходили %s с IP адреса %s"), '<b>' . $res['login_time2'] . '</b>', '<b>' . $res['ip'] . '</b>') . '</div>';
		}
		//Проверка активных сессий данного пользователя
		if ($this->config->database->adapter == 'Pdo_Mysql') {
			$res = $this->db->fetchAll("SELECT ip, sid
										  FROM core_session
										 WHERE user_id = ?
										   AND logout_time IS NULL
										   AND (NOW() - last_activity > $sLife)=0
										 ", $this->auth->ID);
		} elseif ($this->config->database->adapter == 'pdo_pgsql') {
			$res = $this->db->fetchAll("SELECT ip, sid
										  FROM core_session
										 WHERE user_id = ?
										   AND logout_time IS NULL
										   AND EXTRACT(EPOCH FROM (NOW() - last_activity)) <= $sLife
										 ", $this->auth->ID);
		}
		$co = count($res);
		if ($co > 1) {
			$ip = array();
			foreach ($res as $k => $value) {
				if ($value['sid'] == session_id()) continue;
				$ip[] = $value['ip'];
			}
			$ip = implode(', ', $ip);
			if ($co == 2) {
				$o = '';
				$px = 'ь';
				$px2 = 'й';
			} else {
				$o = 'o';
				$px2 = 'е';
				if (5 >= $co && $co > 2) $px = 'я';
				elseif ($co > 5) $px = 'ей';
			}
			$out .= '<div style="color:red"><b>Внимание! Обнаружен' . $o . ' еще ' . ($co - 1) . ' пользовател' . $px . ', использующи' . $px2 . ' вашу учетную запись.</b><br/>IP: ' . $ip . '</div>';
		}


		// Проверка наличия входящих непрочитаных сообщений
		$out .= $this->apiProfile->getProfileMsg();

		return $out;
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_roles() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$this->printCss($this->path . "assets/css/role.css");
        $roles = new Roles();
        $roles->dispatch();
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_enum()
    {
        if (!$this->auth->ADMIN) throw new Exception(911);
        $enum = new Enum();
        $tab = new tabs('enum');

        $title = $this->_("Справочники");
        if (!empty($_GET['edit'])) {
            $title = $this->_("Редактирование справочника");
        }
        elseif (isset($_GET['new'])) {
            $title = $this->_("Создание нового справочника");
        }
        $tab->beginContainer($title);
        $this->printJs("core2/mod/admin/assets/js/enum.js");
        $this->printJs("core2/mod/admin/assets/js/mod.js");
        if (!empty($_GET['edit'])) {
            echo $enum->editEnum($_GET['edit']);
            $tab->beginContainer(sprintf($this->translate->tr("Перечень значений справочника \"%s\""), $this->dataEnum->find($_GET['edit'])->current()->name));
            if (isset($_GET['newvalue'])) {
                echo $enum->newEnumValue($_GET['edit']);
            } elseif (!empty($_GET['editvalue'])) {
                echo $enum->editEnumValue($_GET['edit'], $_GET['editvalue']);
            }
            echo $enum->listEnumValues($_GET['edit']);
            $tab->endContainer();
        } elseif (isset($_GET['new'])) {
            echo $enum->newEnum();
        } else {
            echo $enum->listEnum();
        }
        $tab->endContainer();
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_monitoring() {
		if (!$this->auth->ADMIN) throw new Exception(911);
        try {
            $app = "index.php?module=admin&action=monitoring";
            require_once $this->path . 'classes/monitoring/monitoring.php';
        } catch (Exception $e) {
            echo Alert::danger($e->getMessage());
        }
	}


	/**
	 * @throws Exception
     * @return void
	 */
	public function action_audit() {
		if (!$this->auth->ADMIN) throw new Exception(911);
		$app = "index.php?module=admin&action=audit";
		require_once $this->path . 'classes/audit/Audit.php';
        $audit = new \Core2\Audit();
        $tab = new tabs('audit');

        $tab->addTab($this->translate->tr("База данных"), 		    $app, 100);
        $tab->addTab($this->translate->tr("Контроль целостности"),	$app, 150);

        $tab->beginContainer($this->_("Аудит"));

        if ($tab->activeTab == 1) {
            $audit->database();
        }
        elseif ($tab->activeTab == 2) {
            $audit->integrity();
        }
        $tab->endContainer();
	}


	/**
	 *
	 */
	public function action_upload() {
        require_once 'classes/FileUploader.php';

        $upload_handler = new \Core2\Store\FileUploader();

        header('Pragma: no-cache');
        header('Cache-Control: private, no-cache');
        header('Content-Disposition: inline; filename="files.json"');
        header('X-Content-Type-Options: nosniff');

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'HEAD':
            case 'GET':
                $upload_handler->get();
                //$upload_handler->getDb();
                break;
            case 'POST':
                $upload_handler->post();
                break;
            case 'DELETE':
                $upload_handler->delete();
                break;
            default:
                header('HTTP/1.0 405 Method Not Allowed');
        }
	}


	/**
	 * обработка запросов на содержимое файлов
	 */
	public function fileHandler($resource, $context, $table, $id) {
		require_once 'classes/File.php';
		$f = new \Core2\Store\File($resource);
		if ($context == 'fileid') {
			$f->handleFile($table, $id);
		}
		elseif ($context == 'thumbid') {
		    if (!empty($_GET['size'])) {
		        $f->setThumbSize($_GET['size']);
            }
			$f->handleThumb($table, $id);
		}
		elseif ($context == 'tfile') {
			$f->handleFileTemp($id);
		}
		elseif (substr($context, 0, 6) == 'field_') {
            header('Content-type: application/json');
            try {
                $res = array('files' => $f->handleFileList($table, $id, substr($context, 6)));
            } catch (Exception $e) {
                $res = array('error' => $e->getMessage());
            }
            echo json_encode($res);
			return true;
		}
		$f->dispatch();
        return true;
	}

    /**
     * Создание письма
     * @return \Core2\Email
     */
    public function createEmail() {

        require_once 'classes/Email.php';
        return new \Core2\Email();
    }

    /**
     * Проверяем файлы модулей на изменения
     *
     * @return array
     */
    private function checkModulesChanges() {
        $server = $this->config->system->host;
        $admin_email = $this->getSetting('admin_email');

        if (!$admin_email) {
            $id = $this->db->fetchOne("SELECT id FROM core_settings WHERE code = 'admin_email'");
            if (empty($id)) {
                $this->db->insert(
                    "core_settings",
                    array(
                        'system_name'   => 'Email для уведомлений от аудита системы',
                        'code'          => 'admin_email',
                        'is_custom_sw'  => 'Y',
                        'visible'       => 'Y'
                    )
                );
                $id = $this->db->lastInsertId("core_settings");
            }
            Alert::memory()->info("Создайте дополнительный параметр <a href=\"\" onclick=\"load('index.php#module=admin&action=settings&edit={$id}&tab_settings=2'); return false;\">'admin_email'</a> с адресом для уведомлений", $this->translate->tr("Отправка уведомлений отключена"));
        }
        if (!$server) {
            Alert::memory()->info($this->translate->tr("Не задан параметр 'host' в conf.ini"), $this->translate->tr("Отправка уведомлений отключена"));
        }

        $data = $this->db->fetchAll("SELECT module_id FROM core_modules WHERE is_system = 'N' AND files_hash IS NOT NULL");
        $mods = array();

        $install    = new Install();

        foreach ($data as $val) {
            $dirhash    = $install->extractHashForFiles($this->getModuleLocation($val['module_id']));
            $dbhash     = $install->getFilesHashFromDb($val['module_id']);
            $compare    = $install->compareFilesHash($dirhash, $dbhash);
            if (!empty($compare)) {
//                $this->db->update("core_modules", array('visible' => 'N'), $this->db->quoteInto("module_id = ? ", $val['module_id']));
                $mods[] = $val['module_id'];
                //отправка уведомления
                if ($admin_email && $server) {
                	if ($this->isModuleActive('queue')) {
						$is_send = $this->db->fetchOne(
							"SELECT 1
                           FROM mod_queue_mails
                          WHERE subject = 'Обнаружены изменения в структуре модуля'
                            AND date_send IS NULL
                            AND DATE_FORMAT(date_add, '%Y-%m-%d') = DATE_FORMAT(NOW(), '%Y-%m-%d')
                            AND body LIKE '%{$val['module_id']}%'"
						);
					} else {
						$is_send = false;
					}
                    if (!$is_send) {
                        $n = 0;
                        $br = $install->branchesCompareFilesHash($compare);
                        if (!empty($br['added'])) {
                            $n += count($br['added']);
                        }
                        if (!empty($br['changed'])) {
                            $n += count($br['changed']);
                        }
                        if (!empty($br['lost'])) {
                            $n += count($br['lost']);
                        }
                        $answer = $this->modAdmin->createEmail()
                            ->to($admin_email)
                            ->subject("{$server}: обнаружены изменения в структуре модуля")
                            ->body("<b>{$server}:</b> обнаружены изменения в структуре модуля {$val['module_id']}. Обнаружено  {$n} несоответствий.")
                            ->send();
                        if (isset($answer['error'])) {
                            Alert::memory()->danger($answer['error'], $this->translate->tr("Уведомление не отправлено"));
                        }
                    }
                }
            }
        }

        return $mods;
    }


	/**
	 * Получаем логи
	 * @param string $type
	 * @param string $search
	 * @param int    $limit_lines
	 * @return array
	 */
	private function getLogsData($type, $search, $limit_lines = null) {

		if ($type == 'file') {
			$handle = fopen($this->config->log->system->file, "r");
			$count_lines = 0;
			while (!feof($handle)) {
				fgets($handle, 4096);
				$count_lines += 1;
			}

			if ($search) {
				$search = preg_quote($search, '/');
			}
			rewind($handle); //перемещаем указатель в начало файла
			$body = array();
			while (!feof($handle)) {
				$tmp = fgets($handle, 4096);
				if ($search) {
					if (preg_match("/$search/", $tmp)) {
						if (!$limit_lines || $limit_lines > count($body)) {
							$body[] = $tmp;
						} else {
							array_shift($body);
							$body[] = $tmp;
						}
					}
				} else {
					if (!$limit_lines || $limit_lines >= count($body)) {
						$body[] = $tmp;
					} else {
						array_shift($body);
						$body[] = $tmp;
					}
				}
			}
			fclose($handle);
			return array('body' => implode('', $body), 'count_lines' => $count_lines);

		} else {
			$where = '';
			if ($search) {
				$where = $this->db->quoteInto('WHERE u.u_login LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.sid LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.action LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.lastupdate LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.query LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.request_method LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.remote_port LIKE ?', "%$search%") .
						$this->db->quoteInto(' OR l.ip LIKE ?', "%$search%");
			}
            $sql = "
                SELECT u.u_login,
                       l.sid,
                       l.action,
                       l.lastupdate,
                       l.query,
                       l.request_method,
                       l.remote_port,
                       l.ip
                FROM core_log AS l
                    LEFT JOIN core_users AS u ON u.u_id = l.user_id
                    $where
            ";

            if ($limit_lines) {
                $count_where = $this->db->fetchOne("
                    SELECT count(*)
                    FROM core_log AS l
                        LEFT JOIN core_users AS u ON u.u_id = l.user_id
                        $where
                ");

                $start = $count_where - $limit_lines;
                if ($start < 0) {
                    $start = 0;
                }
                $sql .= " LIMIT $start, $limit_lines ";
            }


			$data        = $this->db->fetchAll($sql);
            $count_lines = $this->db->fetchOne("SELECT count(*) FROM core_log");


            $data2 = '';
			foreach ($data as $tmp) {
				$data2 .= "user: {$tmp['u_login']}, sid: {$tmp['sid']}, action: {$tmp['action']}, lastupdate: {$tmp['lastupdate']}, query: {$tmp['query']}, query: {$tmp['request_method']}, remote_port: {$tmp['remote_port']}, ip: {$tmp['ip']}\n";
			}

			return array('body' => $data2, 'count_lines' => $count_lines);
		}
	}
}