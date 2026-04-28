<?php
namespace Core2;

require_once 'Acl.php';
require_once 'Navigation.php';
require_once 'Templater3.php';

use Exception;
use Templater3;
use xajax;
use TopCss;
use TopJs;

/**
 * @property \Core2\Model\Modules      $dataModules
 * @property \Core2\Model\UsersProfile $dataUsersProfile
 */
class Menu extends Acl {

    private $auth;


    /**
     *
     */
    public function __construct() {
        $this->auth = Registry::get('auth');
        parent::__construct();
    }


    /**
     * Get side menu
     * @return string
     * @throws Exception
     */
    public function getMenu(): string {

        $xajax = new xajax();
        $xajax->configure('javascript URI', 'core2/vendor/belhard/xajax');
        $xajax->register(XAJAX_FUNCTION, 'post'); //регистрация xajax функции post()
//            $xajax->configure('errorHandler', true);

        if (Tool::isMobileBrowser()) {
            $tpl_file      = Theme::get("indexMobile");
            $tpl_file_menu = Theme::get("menuMobile");
        } else {
            $tpl_file      = Theme::get("index");
            $tpl_file_menu = Theme::get("menu");
        }

        $tpl      = new \Templater3($tpl_file);
        $tpl_menu = new \Templater3($tpl_file_menu);

        $tpl->assign('{system_name}', $this->getSystemName());

        $favicons = $this->getSystemFavicon();

        $tpl->assign('favicon.png', $favicons['png']);
        $tpl->assign('favicon.ico', $favicons['ico']);

        $tpl_menu->assign('<!--SYSTEM_NAME-->',        $this->getSystemName());
        $tpl_menu->assign('<!--CURRENT_USER_LOGIN-->', htmlspecialchars($this->auth->NAME));
        $tpl_menu->assign('<!--CURRENT_USER_FN-->',    $this->auth->FN ? htmlspecialchars($this->auth->FN) : "");
        $tpl_menu->assign('<!--CURRENT_USER_LN-->',    $this->auth->LN ? htmlspecialchars($this->auth->LN) : "");
        $img = "https://www.gravatar.com/avatar/" . md5(strtolower(trim($this->auth?->EMAIL ?? ''))) . "?s=28&d=identicon";

        $this->module = 'admin';
        $row = $this->dataUsersProfile->getRowByUserId($this->auth->ID);
        if ($row && isset($row->avatar) && $row->avatar) {
            $img = "data:image/png;base64, {$row->avatar}";
        }
        $tpl_menu->assign('[GRAVATAR_URL]', $img);


        $modules_js     = [];
        $modules_css    = [];
        $navigate_items = [];
        $modules        = $this->getModuleList();

        foreach ($modules as $module) {
            if ( isset($module['sm_key'])) {
                //пропускаем субмодули
                continue;
            }

            $module_id = $module['module_id'];

            if ($module['is_public'] == 'Y') {
                if ($module['isset_home_page'] == 'N') {
                    $first_action = 'index';

                    foreach ($modules as $mod) {
                        if ( ! empty($mod['sm_id']) && $module['m_id'] == $mod['m_id']) {
                            $first_action = $mod['sm_key'];
                            break;
                        }
                    }

                    $url           = "index.php?module={$module_id}&action={$first_action}";
                    $module_action = "&action={$first_action}";

                } else {
                    $url           = "index.php?module=" . $module_id;
                    $module_action = '';
                }

                $tpl_menu->modules->assign('[MODULE_ID]',     $module_id);
                $tpl_menu->modules->assign('[MODULE_NAME]',   $module['m_name']);
                $tpl_menu->modules->assign('[MODULE_ACTION]', $module_action);
                $tpl_menu->modules->assign('[MODULE_URL]',    $url);
                $tpl_menu->modules->reassign();
            }


            if ($module_id == 'admin') {
                continue;
            }

            try {
                $location = $this->getModuleLocation($module_id); //получение расположения модуля
                $modController = "Mod" . ucfirst($module_id) . "Controller";
                $file_path = $location . "/" . $modController . ".php";

                if (file_exists($file_path)) {
                    ob_start();
                    $autoload = $location . "/vendor/autoload.php";

                    if (file_exists($autoload)) {
                        require_once $autoload;
                    }

                    require_once $file_path;

                    // подключаем класс модуля
                    if (class_exists($modController)) {
                        $this->setContext($module_id);
                        $modController = new $modController();

                        if (($modController instanceof TopJs || method_exists($modController, 'topJs'))) {
                            $module_js_list = $modController->topJs();
                            if (is_array($module_js_list)) {
                                foreach ($module_js_list as $val) {
                                    $module_js = Tool::addSrcHash($val);
                                    if (!in_array($module_js, $modules_js)) $modules_js[] = $module_js;
                                }
                            }
                        }

                        if ($modController instanceof TopCss &&
                            $module_css_list = $modController->topCss()
                        ) {
                            foreach ($module_css_list as $val) {
                                $module_css = Tool::addSrcHash($val);
                                if (!in_array($module_css, $modules_css)) $modules_css[] = $module_css;
                            }
                        }

                        if (THEME !== 'default') {
                            $nav = new Navigation(); //TODO переделать для обработки всех модулей сразу
                            $nav->setModuleNavigation($module['module_id']);

                            if ($modController instanceof \Navigation) {
                                $modController->navigationItems($nav);
                            }

                            foreach ($nav->toArray() as $item) {

                                $item['position'] = ! empty($item['position']) ? $item['position'] : 'main';

                                if (empty($navigate_items[$item['position']])) {
                                    $navigate_items[$item['position']] = [];
                                }

                                $item['module_name'] = $module_id;
                                $navigate_items[$item['position']][] = $item;
                            }
                        }
                    }
                    ob_clean();
                }
            } catch (\Exception $e) {
                //проблемы с загрузкой модуля
                //TODO добавить в log
            }
        }

        foreach ($modules as $module) {
            if ( ! empty($module['sm_key']) && $module['is_public'] === 'Y') {
                $url = "index.php?module=" . $module['module_id'] . "&action=" . $module['sm_key'];

                $tpl_menu->submodules->assign('[MODULE_ID]',      $module['module_id']);
                $tpl_menu->submodules->assign('[SUBMODULE_ID]',   $module['sm_key']);
                $tpl_menu->submodules->assign('[SUBMODULE_NAME]', $module['sm_name']);
                $tpl_menu->submodules->assign('[SUBMODULE_URL]',  $url);
                $tpl_menu->submodules->reassign();
            }
        }

        if ( ! empty($navigate_items)) {
            $nav = new Navigation();
            foreach ($navigate_items as $position => $items) {
                $navigate_items[$position] = \Core2\Tool::multisort($items, [
                    'seq'    => SORT_DESC,
                    'serial' => SORT_ASC
                ]);
            }

            foreach ($navigate_items as $position => $items) {
                if ( ! empty($items)) {
                    foreach ($items as $item) {

                        switch ($position) {
                            case 'profile':
                                if ($tpl_menu->issetBlock('navigate_item_profile')) {
                                    $tpl_menu->navigate_item_profile->assign('[MODULE_NAME]', $item['module_name']);
                                    $tpl_menu->navigate_item_profile->assign('[HTML]',        $nav->renderNavigateItem($item, 'profile'));
                                    $tpl_menu->navigate_item_profile->reassign();
                                }
                                break;

                            case 'main':
                            default:
                                if ($tpl_menu->issetBlock('navigate_item')) {
                                    $tpl_menu->navigate_item->assign('[MODULE_NAME]', $item['module_name']);
                                    $tpl_menu->navigate_item->assign('[HTML]',        $nav->renderNavigateItem($item));
                                    $tpl_menu->navigate_item->reassign();
                                }
                                break;
                        }
                    }
                }
            }
        }


        if ( ! empty($this->config->system) &&
             ! empty($this->config->system->theme) &&
             ! empty($this->config->system->theme->bg_color) &&
             ! empty($this->config->system->theme->text_color) &&
             ! empty($this->config->system->theme->border_color) &&
            $tpl_menu->issetBlock('theme_style')
        ) {
            $tpl_menu->theme_style->assign("[BG_COLOR]",     $this->config->system->theme->bg_color);
            $tpl_menu->theme_style->assign("[TEXT_COLOR]",   $this->config->system->theme->text_color);
            $tpl_menu->theme_style->assign("[BORDER_COLOR]", $this->config->system->theme->border_color);
        }

        $tpl->assign('<!--index-->', $tpl_menu->render());
        $out = '';

        if ( ! empty($modules_css)) {
            foreach ($modules_css as $src) {
                if ($src) $out .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
            }
        }

        if ( ! empty($modules_js)) {
            foreach ($modules_js as $src) {
                if ($src) $out .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
            }
        }

        $tpl->assign('<!--xajax-->', "<script type=\"text/javascript\">var coreTheme  ='" . THEME . "'</script>" . $xajax->getJavascript() . $out);


        $system_js = "";
        if (isset($this->config->system->js) && is_object($this->config->system->js)) {
            foreach ($this->config->system->js as $src) {
                $system_js .= "<script type=\"text/javascript\" src=\"{$src}\"></script>";
            }
        }
        $tpl->assign("<!--system_js-->", $system_js);

        $system_css = "";
        if (isset($this->config->system->css) && is_object($this->config->system->css)) {
            foreach ($this->config->system->css as $src) {
                $system_css .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$src}\"/>";
            }
        }
        $tpl->assign("<!--system_css-->", $system_css);

        return $tpl->render();
    }


    /**
     * Список доступных модулей для core2m
     * @return string
     * @throws Exception
     */
    public function getMenuMobile() {


        $mods     = $this->getModuleList();
        $modsList = [];

        foreach ($mods as $data) {
            if ($data['is_public'] == 'Y') {
                $modsList[$data['m_id']] = [
                    'module_id'  => $data['module_id'],
                    'm_name'     => strip_tags($data['m_name']),
                    'm_id'       => $data['m_id'],
                    'submodules' => []
                ];
            }
        }
        foreach ($mods as $data) {
            if ( ! empty($data['sm_id']) && $data['is_public'] == 'Y') {
                $modsList[$data['m_id']]['submodules'][] = [
                    'sm_id'   => $data['sm_id'],
                    'sm_key'  => $data['sm_key'],
                    'sm_name' => strip_tags($data['sm_name'])
                ];
            }
        }

        //проверяем наличие контроллера для core2m в модулях
        foreach ($modsList as $k => $data) {
            $location      = $this->getModuleLocation($data['module_id']);
            if (isset($this->auth->MOBILE) && $this->auth->MOBILE) { //признак того, что мы в core2m
                $controller = "Mobile" . ucfirst(strtolower($data['module_id'])) . "Controller";
            } else {
                $controller = "Mod" . ucfirst(strtolower($data['module_id'])) . "Api";
            }
            if ( ! file_exists($location . "/$controller.php")) {
                unset($modsList[$k]); //FIXME если это не выполнится, core2m не будет работать!
            } else {
                require_once $location . "/$controller.php";
                $r = new \ReflectionClass($controller);
                $submodules = []; //должен быть массивом!
                foreach ($data['submodules'] as $s => $submodule) {
                    $method = 'action_' . $submodule['sm_key'];
                    if (!$r->hasMethod($method)) continue;
                    $submodules[] = $submodule;
                }
                $modsList[$k]['submodules'] = $submodules;
                if (!$submodules && !$r->hasMethod('action_index')) { //нет методов, доступных извне
                    unset($modsList[$k]);
                }
            }
        }
        $modsList = array_values($modsList);
        $data = [
            'system_name' => strip_tags($this->getSystemName()),
            'id'          => $this->auth->ID,
            'name'        => $this->auth->LN . ' ' . $this->auth->FN . ' ' . $this->auth->MN,
            'login'       => $this->auth->NAME,
            'avatar'      => "https://www.gravatar.com/avatar/" . ($this->auth->EMAIL ? md5(strtolower(trim($this->auth->EMAIL))) : ''),
            'required_location' => false,
            'modules'     => $modsList
        ];

        if ($this->config->mobile) { //Настройки для Core2m
            if ($this->config->mobile->required && $this->config->mobile->required->location) {
                $data['required_location'] = true; //требовать геолокацию для работы
            }
        }
        return json_encode($data);
//            return json_encode([
//                'status' => 'success',
//                'data'   => $data,
//            ] + $data); // Для совместимости с разными приложениями
    }


    /**
     * Получение названия системы из conf.ini
     * @return mixed
     */
    private function getSystemName() {
        $res = $this->config->system->name;
        return $res;
    }

    private function getSystemFavicon(): array {

        $favicon_png = $this->config->system->favicon_png;
        $favicon_ico = $this->config->system->favicon_ico;

        $favicon_png = $favicon_png && is_file($favicon_png)
            ? $favicon_png
            : (is_file('favicon.png') ? 'favicon.png' : '');

        $favicon_ico = $favicon_ico && is_file($favicon_ico)
            ? $favicon_ico
            : (is_file('favicon.ico') ? 'favicon.ico' : '');

        if (defined('THEME')) {
            if (!$favicon_png) {
                $favicon_png = 'core2/html/' . THEME . '/img/favicon.png';
            }
            if (!$favicon_ico) {
                $favicon_ico = 'core2/html/' . THEME . '/img/favicon.ico';
            }
        }

        return [
            'png' => $favicon_png,
            'ico' => $favicon_ico,
        ];
    }


    /**
     * Получаем список доступных модулей
     * @return array
     */
    private function getModuleList(): array {

        $this->module = 'admin';
        $res  = $this->dataModules->getModuleList();

        $mods = array();
        $tmp  = array();
        foreach ($res as $data) {
            if (isset($tmp[$data['m_id']]) || $this->checkAcl($data['module_id'], 'access')) {
                //чтобы модуль отображался в меню, нужно еще людое из правил просмотри или чтения
                $types = array(
                    'list_all',
                    'read_all',
                    'list_owner',
                    'read_owner',
                );
                $forMenu = false;
                foreach ($types as $type) {
                    if ($this->checkAcl($data['module_id'], $type)) {
                        $forMenu = true;
                        break;
                    }
                }
                if (!$forMenu) continue;
                if ($data['sm_key']) {
                    if ($this->checkAcl($data['module_id'] . '_' . $data['sm_key'], 'access')) {
                        $tmp[$data['m_id']][] = $data;
                    } else {
                        $tmp[$data['m_id']][] = array(
                            'm_id'            => $data['m_id'],
                            'm_name'          => $data['m_name'],
                            'module_id'       => $data['module_id'],
                            'isset_home_page' => empty($data['isset_home_page']) ? 'Y' : $data['isset_home_page'],
                            'is_public'       => $data['is_public']
                        );
                    }
                } else {
                    $tmp[$data['m_id']][] = $data;
                }
            }
        }
        unset($res);
        foreach ($tmp as $m_id => $data) {
            $module = current($data);
            $mods[] = array(
                'm_id'            => $m_id,
                'm_name'          => $module['m_name'],
                'module_id'       => $module['module_id'],
                'isset_home_page' => empty($module['isset_home_page']) ? 'Y' : $module['isset_home_page'],
                'is_public'       => $module['is_public']
            );
            foreach ($data as $submodule) {
                if (empty($submodule['sm_id'])) continue;
                $mods[] = $submodule;
            }
        }
        if ($this->auth->ADMIN || $this->auth->NAME == 'root') {
            $tmp = array(
                'm_id'            => -1,
                'm_name'          => $this->translate->tr('Админ'),
                'module_id'       => 'admin',
                'isset_home_page' => 'Y',
                'is_public'       => 'Y'
            );
            $mods[] = $tmp;
            $mods[] = array_merge($tmp, array('sm_id' => -1, 'sm_name' => $this->translate->tr('Модули'), 		'sm_key' => 'modules',    'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -2, 'sm_name' => $this->translate->tr('Конфигурация'), 'sm_key' => 'settings',   'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -3, 'sm_name' => $this->translate->tr('Справочники'),	'sm_key' => 'enum',       'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -4, 'sm_name' => $this->translate->tr('Пользователи'), 'sm_key' => 'users',      'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -5, 'sm_name' => $this->translate->tr('Роли'), 		'sm_key' => 'roles',      'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -6, 'sm_name' => $this->translate->tr('Мониторинг'), 	'sm_key' => 'monitoring', 'loc' => 'core'));
            $mods[] = array_merge($tmp, array('sm_id' => -7, 'sm_name' => $this->translate->tr('Аудит'), 		'sm_key' => 'audit',      'loc' => 'core'));
        }
        return $mods;
    }

    private function setContext($module, $action = 'index') {
        $registry     = Registry::getInstance();
        //$registry 	= new ServiceManager();
        //$registry->setAllowOverride(true);
        $registry->set('context', array($module, $action));
    }

}