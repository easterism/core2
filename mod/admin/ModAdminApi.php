<?php

require_once __DIR__ . '/../../inc/classes/CommonApi.php';

use Core2\Error;
use Laminas\Session\Container as SessionContainer;
use OpenApi\Attributes as OAT;
use Core2\Switches;

/**
 * @property \Core2\Model\Users $dataUsers
 */
class ModAdminApi extends CommonApi
{
    public function action_index()
    {
        $params = $this->route['params'];
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'DELETE':
                if (isset($params['_resource']) && isset($params['_field']) && isset($params['_value'])) {
                    //это удаление из UI
                    if (empty($params['_resource'])) throw new \Exception("Не удалось определить местоположение данных для удаления.");
                    if (empty($params['_field'])) throw new \Exception("Не удалось определить источник для удаления.");
                    if (empty($params['_value'])) throw new \Exception("Не удалось определить объекты для удаления.");
                    return $this->indexDelete($params['_resource'], $params['_field'], explode(",", $params['_value']));
                }
                //здесь друдие виды удаления
                break;
            case 'POST':
                if (!empty($params['switch'])) {
                    return $this->indexSwitch($params['switch'], $this->getInputBody());
                }
                break;
            default:
                throw new \Exception('Error: method not handled', 405);
        }
    }

    public function action_users()
    {
        $params = $this->route['params'];
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'DELETE':
                $ids = $this->getParamsDelete($params);
                foreach ($ids as $id) {

                    $user = $this->dataUsers->find($id)->current();
                    if ($user) {
                        $user->delete();
                        $this->emit("delete_user", ['id' => $id]);
                    }
                }
                return ['loc' => "index.php?module=admin&action=users"];
                //здесь друдие виды удаления
                break;
            default:
                throw new Exception('Error: method not handled', 405);
        }
    }

    public function action_enum()
    {
        $params = $this->route['params'];
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'DELETE':
                $ids = $this->getParamsDelete($params);
                $parent_id = 0;
                foreach ($ids as $id) {

                    $enum = $this->dataEnum->find($id)->current();
                    if ($enum) {
                        $parent_id = $enum->parent_id;
                        $enum->delete();
                        $this->emit("delete_enum", ['id' => $id]);
                    }
                }
                if ($parent_id) return ['loc' => "index.php?module=admin&action=enum&edit=$parent_id"];
                return ['loc' => "index.php?module=admin&action=enum"];
                //здесь друдие виды удаления
                break;
            default:
                throw new Exception('Error: method not handled', 405);
        }
    }

    /**
     * проверка параметров удаления
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function getParamsDelete(array $params): array
    {
        if (!$this->auth->ADMIN) throw new Exception("Доступ запрещен", 911);
        if (isset($params['_resource']) && isset($params['_field']) && isset($params['_value'])) {
            //это удаление из UI
            if (empty($params['_resource'])) throw new Exception("Не удалось определить местоположение данных для удаления.");
            if (empty($params['_field'])) throw new Exception("Не удалось определить источник для удаления.");
            if (empty($params['_value'])) throw new Exception("Не удалось определить объекты для удаления.");
            return explode(",", $params['_value']);
        }
        throw new Exception('Params error', 400);

    }


    public function action_acl()
    {
        $params = $this->route['params'];
        if (!$params || !key($params)) throw new Exception('Error: empty params', 400);
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $resource = strtolower(key($params));
                $submod = strtolower(current($params));
                if ($submod) $resource .= "-$submod";

                return $this->getAccessInfo($resource);
                break;
            case 'POST':
                if (!empty($params['switch'])) {
                    return $this->indexSwitch($params['switch'], $this->getInputBody());
                }
                break;
            default:
                throw new Exception('Error: method not handled', 405);
        }
    }

    /**
     * @param $data
     * @return array|bool|string|void|null
     * @throws Exception
     */
    private function indexDelete($resource, $field, array $values)
    {
        try {

            [$table, $refid] = explode(".", $field);

            if ( ! $table || ! $refid) {
                throw new RuntimeException("Не удалось определить параметры удаления!");
            }
            $admin      = false;
            if (strpos($table, 'core_') === 0) {
                //удаление в таблицах ядра
                if (!$this->auth->ADMIN) throw new RuntimeException("Доступ запрещен");
                $admin = true;
            }

            if (!$admin) {
//                $resource = explode('xxx', $resource);
                //кастомное удаление само должно проверять права на удаление
                $custom = $this->customDelete($resource, $values);
                if ($custom) return $custom;
            }

            $delete_all   = $this->checkAcl($resource, 'delete_all');
            $delete_owner = $this->checkAcl($resource, 'delete_owner');
            if (!$delete_all && !$delete_owner) throw new RuntimeException("Удаление запрещено");
            $authorOnly   = false;
            if ($delete_owner && !$delete_all) {
                $authorOnly = true;
            }
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
                    throw new RuntimeException("Данные не содержат признака автора!");
                } else {
                    $auth = new SessionContainer('Auth');
                }
            }


        } catch (RuntimeException $e) {
            throw new Exception($this->translate->tr($e->getMessage()), 400);
        } catch (Exception $e) {
            return Error::catchJsonException([
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }

        $this->db->beginTransaction();
        try {
            foreach ($values as $key) {
                $where = array($this->db->quoteInto("`$refid` = ?", $key));
                if ($authorOnly) {
                    $where[] = $this->db->quoteInto("author = ?", $auth->NAME);
                }
                if ($nodelete) $this->db->update($table, array('is_deleted_sw' => 'Y'), $where);
                else $this->db->delete($table, $where);
            }
            $this->db->commit();
            $this->emit('delete', [$table . "." . $refid => $values]); //генерируем событие удаления для слушателей
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return Error::catchJsonException([
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Проверка модуля на реализацию собственного удаления
     * @param $resource
     * @param array $ids
     * @return array|bool
     * @throws Exception
     */
    private function customDelete($resource, array $ids)
    {
        $mod = explode('xxx', $resource);
        $mod = explode("_", $mod[0]);
        $location      = $this->getModuleLocation($mod[0]); //определяем местоположение модуля
        $modController = "Mod" . ucfirst(strtolower($mod[0])) . "Controller";
        $this->requireController($location, $modController);
        $modController = new $modController();

        $res = false;
        if ($modController instanceof Delete) {
            ob_start();
            $res = $modController->action_delete($resource, implode(",", $ids));
            ob_clean();
        }
        return $res;
    }

    /**
     * @param $location
     * @param $modController
     * @return void
     * @throws Exception
     */
    private function requireController($location, $modController) {
        $controller_path = $location . "/" . $modController . ".php";
        if (!file_exists($controller_path)) {
            throw new Exception(sprintf($this->translate->tr("Модуль не найден: %s"), $modController), 400);
        }
        $autoload = $location . "/vendor/autoload.php";
        if (file_exists($autoload)) { //подключаем автозагрузку если есть
            require_once $autoload;
        }
        require_once $controller_path; // подлючаем контроллер
        if (!class_exists($modController)) {
            throw new RuntimeException(sprintf($this->translate->tr("Модуль сломан: %s"), $location));
        }
    }

    #[OAT\Post(
        path: '/admin/index/switch/{resource}',
        operationId: 'switchRecord',
        description: 'Переключает признак активности записи',
        tags: ['Админ'],
        parameters: [
            new OAT\Parameter(
                name: 'resource',
                description: 'ижентификатор ресурса, в котором происходит переключение',
                in: 'path',
                required: true,
                schema: new OAT\Schema(type: 'string')
            )],
        requestBody: new OAT\RequestBody(
            required: true,
            description: 'данные для переключения',
            content: new OAT\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OAT\Schema(
                    type: 'object',
                    required: ['data', 'is_active', 'value'],
                    properties: [
                        new OAT\Property(property: 'data', type: 'string', title: 'поле базы данных с признаком для переключения'),
                        new OAT\Property(property: 'is_active', type: 'string', title: 'хначение переключателя'),
                        new OAT\Property(property: 'value', type: 'string', title: 'id записи, для которой происходит переключение')
                    ]
                )
            )
        ),
        responses: [
            new OAT\Response(
                response: 200,
                description: 'OK',
            ),
            new OAT\Response(
                response: 400,
                description: 'Ошибка переключения',
            )
        ]
    )]
    private function indexSwitch($resource, $data)
    {

        try {
            if (empty($data['data']) || empty($data['value']) || empty($data['is_active'])) {
                throw new RuntimeException("Не хватает данных для переключения");
            }
            $table = explode(".", $data['data']);
            $refid = isset($table[1]) ? $table[1] : '';
            $id    = isset($table[2]) ? $table[2] : '';
            $table = $table[0];
            $admin      = false;
            if (strpos($table, 'core_') === 0) {
                //таблица ядра
                if (!$this->auth->ADMIN) throw new RuntimeException("Доступ запрещен");
                $admin = true;
            }


            if (!$admin) {
                $custom = $this->customSwitch($resource, $data['data'], $data['value'], $data['is_active']);
                if ($custom) {
                    if ($custom === true) return ['status' => "ok"];
                    return $custom;
                }
            }



            preg_match('/[a-z|A-Z|0-9|_|-]+/', trim($table), $arr);
            $table_name = $arr[0];
            $is_active = $refid;
            if (!$id && !empty($data['value'])) {
                $id = (int) $data['value'];
            }
            $keys_list = $this->db->fetchRow("SELECT * FROM `{$table_name}` LIMIT 1");
            $keys = array_keys($keys_list);
            $key = $keys[0];
            $where = $this->db->quoteInto($key . "= ?", $id);
            $this->db->update($table_name, array($is_active => $data['is_active']), $where);
            //очистка кеша активности по всем записям таблицы
            // используется для core_modules
            $this->cache->clearByTags(["is_active_" . $table_name]);

            return ['status' => "ok"];
        } catch (RuntimeException $e) {
            throw new Exception($this->translate->tr($e->getMessage()), 400);
        } catch (Exception $e) {
            return ['status' => $e->getMessage()];
        }
    }

    /**
     *
     * @param $resource
     * @param $table_field
     * @param $refid
     * @param $value
     * @return array|bool
     * @throws Exception
     */
    private function customSwitch($resource, $table_field, $refid, $value)
    {

        $resource_clean = str_contains($resource, 'xxx') ? explode("xxx", $resource)[0] : $resource;
        $mod            = explode("_", $resource_clean);
        $location      = $this->getModuleLocation($mod[0]);
        $modController = "Mod" . ucfirst(strtolower($mod[0])) . "Controller";

        $this->requireController($location, $modController);
        $controller = new $modController();

        if ($controller instanceof Switches) {
            try {
                ob_start();
                $result = $controller->action_switch($resource, $table_field, $refid, $value);
                ob_clean();
            } catch (\Exception $e) {
                $result = [ 'status' => $e->getMessage() ];
            }
            return $result;
        }
        return false;
    }

    #[OAT\Get(
        path: '/admin/acl/{res}/{subres}',
        operationId: 'getAccessInfo',
        summary: 'Возвращает информацию о правах доступа к модулю {res} и к субмодулю {subres}',
        tags: ['Админ'],
        parameters: [
            new OAT\Parameter(
                name: 'res',
                in: 'path',
                required: true,
                schema: new OAT\Schema(type: 'string')
            ),
            new OAT\Parameter(
                name: 'subres',
                in: 'path',
                required: false,
                schema: new OAT\Schema(type: 'string')
            )],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'массив с правилами доступа',
                content: new OAT\JsonContent(
                    type: 'array',
                    items: new OAT\Items()
                ),
            ),
        ]
    )]
    private function getAccessInfo(string $resource)
    {
        $res = explode('-', $resource);
        $mod = $this->getModule($res[0]);
        if (!$mod) throw new \Exception('Error: resource not found', 404);
        if (!empty($res[1]) && !isset($mod['submodules'][$res[1]])) throw new \Exception('Error: resource not found', 404);

        if ($this->auth->ADMIN) {
            $default = [
                'access' => 'on',
                'list_all' => 'on',
                'read_all' => 'on',
                'edit_all' => 'on',
                'delete_all' => 'on'
            ];

            $access_add = $mod['access_add'] ? unserialize(base64_decode($mod['access_add'])) : [];
            foreach ($access_add as $key => $item) {
                $default[$key . "_all"] = 'on';
            }
            if (isset($res[1])) {
                $access_add = $mod['submodules'][$res[1]]['access_add'] ? unserialize(base64_decode($mod['submodules'][$res[1]]['access_add'])) : [];
                foreach ($access_add as $key => $item) {
                    $default[$key . "_all"] = 'on';
                }
            }
            return $default;
        }

        $role = $this->db->fetchRow("
                    SELECT name, 
                           access
					FROM core_roles
					WHERE id=? AND is_active_sw = 'Y'
					ORDER BY position DESC
                ", $this->auth->ROLEID);

        if (!$role) throw new \Exception('Error: role not found', 404);
        $access = $role['access'] ? unserialize($role['access']) : [];
        $res = [];
        foreach ($access as $rule => $resources) {

            if (!isset($resources[$resource])) {
                continue;
            }
            $res[$rule] = $resources[$resource];
        }
        return $res;
    }

}