<?php

namespace Core2;

require_once 'Db.php';

use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

/**
 * Class Acl
 */
class Acl extends Db {


	const INHER_ROLES = 'N';
	protected $addRes = array();
	protected $types = array(
			'default',
			'access',
			'list_all',
			'read_all',
			'edit_all',
			'delete_all',
			'list_owner',
			'read_owner',
			'edit_owner',
			'delete_owner',
			'list_default',
			'read_default',
			'edit_default',
			'delete_default'
		);
    private $_acl;
    private $access_default = [];

    /**
     * @throws \Exception
     */
	public function setupAcl() {

		$registry 	= Registry::getInstance();
		$registry->set('addRes', $this->addRes);
		$auth 		= $registry->get('auth');

        if (empty($auth->ROLEID)) {
            return;
        }

		$key 		= 'acl_' . $auth->ROLEID . self::INHER_ROLES;
        //$this->cache->clean($key); //исползуй это, если кеш сломался

        if ($this->cache->hasItem($key)) {
            $this->_acl = $this->cache->getItem($key);
        }

		if (empty($this->_acl)) {
            $this->_acl = new LaminasAcl();
			$res = $this->db->fetchAll("
                SELECT *
                FROM (
                    (SELECT module_id, 
                            m.seq, 
                            m.access_default, 
                            m.access_add
                     FROM core_modules AS m
                     WHERE visible = 'Y'
                     ORDER BY seq)
                    
                    UNION ALL
                    
                    (SELECT CONCAT(m.module_id, '_', s.sm_key) AS module_id, 
                            m.seq, 
                            s.access_default, 
                            s.access_add
                     FROM core_submodules AS s
                         INNER JOIN core_modules AS m ON m.m_id = s.m_id AND m.visible = 'Y'
                     WHERE sm_id > 0 
                       AND s.visible = 'Y'
                     ORDER BY m.seq, s.seq)
                ) AS a 
                ORDER BY 2
            ");

            // ADD ALL AVAILABLE RESOURCES
            $resources      = [];
            $resources2     = [];

            // Если не назначена роль, добавляем виртуальную роль в ACL
			if ($auth->ROLEID < 0) {
                $this->_acl->addRole(new Role($auth->ROLE));
			}

			// обрабатываем только модули
			foreach ($res as $data) {
				$this->access_default[$data['module_id']] = array();
				if ($data['access_default']) {
					$temp = @unserialize(base64_decode($data['access_default']));
					if ($temp && is_array($temp)) $this->access_default[$data['module_id']] = $temp;
				}
				if ($data['access_add']) {
					$temp = @unserialize(base64_decode($data['access_add']));
					if ($temp && is_array($temp)) $this->access_default[$data['module_id']] += $temp;
				}
				$mod2 = explode('_', $data['module_id']);
				if (!in_array($mod2[0], $resources)) {
					$resources[] = $mod2[0];
                    $this->_acl->addResource(new Resource($mod2[0]));
				}
			}

			// обрабатываем только субмодули
			foreach ($res as $data) {
				$mod2 = explode('_', $data['module_id']);
				if (!empty($mod2[1])) {
					if (!in_array($data['module_id'], $resources2)) {
						$resources2[] = $data['module_id'];
                        $this->_acl->addResource(new Resource($data['module_id']), $mod2[0]);
					}
				}
			}


			if ($auth->ROLEID > 0) {
				$role = $this->db->fetchRow("
                    SELECT name, 
                           access
					FROM core_roles
					WHERE id=? AND is_active_sw = 'Y'
					ORDER BY position DESC
                ", $auth->ROLEID);

				$i = 1;
				if ($role) {
					$this->addRoleAccess($role);
				}
			}
			else {
                //$acl->addRole(new Role('dummy'));
				foreach ($this->access_default as $res => $types) {
					if ($types) {
						foreach ($types as $type => $on) {
							if ($on === 'on') {
								if ($type === 'access') $this->_acl->allow($auth->ROLE, $res, $type);
								if ($type === 'list_all') $this->_acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'list_owner') $this->_acl->allow($auth->ROLE, $res, $type);
								if ($type === 'read_all') $this->_acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'read_owner') $this->_acl->allow($auth->ROLE, $res, $type);
								if ($type === 'edit_all') $this->_acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'edit_owner') $this->_acl->allow($auth->ROLE, $res, $type);
								if ($type === 'delete_all') $this->_acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'delete_owner') $this->_acl->allow($auth->ROLE, $res, $type);
							} else if ($on === 'all') {
								if ($type === 'access') {
                                    $this->_acl->allow($auth->ROLE, $res, $type . "_all");
                                }
							} else if ($on === 'owner') {
								if ($type === 'access') {
                                    $this->_acl->allow($auth->ROLE, $res, $type . "_owner");
                                }
							}
						}
					}
				}
				if ( ! empty($data) && $data['access_default']) {
					$access = unserialize(base64_decode($data['access_default']));
					foreach ($access as $type => $f) {
						$this->_acl->allow($auth->ROLE, $data['module_id'], $type);
					}
				}
			}

			$this->cache->setItem($key, $this->_acl);
			$this->cache->setTags($key, array("role" . $auth->ROLEID));
		}

        $res        = $this->_acl->getResources();
        $resources  = [];
        $resources2 = [];

        foreach ($res as $re) {
            if (strpos($re, '_')) {
                $resources2[] = $re;
            } else {
                $resources[] = $re;
            }
        }

		$registry->set('acl',         $this->_acl);
		$registry->set('availRes',    $resources);
		$registry->set('availSubRes', $resources2);
	}

    /**
     * @param array $role
     * @return void
     * @throws \Exception
     */
    private function addRoleAccess(array $role)
    {
        if (empty($this->_acl)) {
            throw new \Exception("Need to setup ACL");
        }
        $roleName = $role['name'];
        if (self::INHER_ROLES == 'Y') {
            if (!$this->_acl->hasRole(new Role($role['name']))) {
                $this->_acl->addRole(new Role($role['name']));
            } else {
                $this->_acl->addRole(new Role($role['name']), $roleName);
            }
        } else {
            $this->_acl->addRole(new Role($roleName));
        }

        $access = $role['access'] ? unserialize($role['access']) : null;

        if (!$access) {
            return;
        }
        $resources        = $this->_acl->getResources();
        foreach ($access as $type => $data) {
            if (strpos($type, 'default') === false && is_array($resources)) {

                foreach ($resources as $availRes) {
                    if (!empty($data[str_replace('_', '-', $availRes)])) {
                        $this->_acl->allow($roleName, $availRes, $type);
                    } else {
                        $this->_acl->deny($roleName, $availRes, $type);
                    }
                }

                foreach ($resources as $availRes) {
                    $res = explode("_", $availRes);
                    if (empty($res[1])) {
                        continue;
                    }
                    $res = $res[0];
                    if ($type == 'access' && empty($data[$res])) {
                        //закрываем доступ, если основной ресурс не досупен
                        $this->_acl->deny($roleName, $availRes, $type);
                    }
                }

            }
        }
        foreach ($access as $type => $data) {
            if (strpos($type, 'default') !== false) {

                $type = explode('_', $type);
                $type = !empty($type[1]) ? $type[0] : 'access';

                foreach ($data as $res => $on) {
                    $res = str_replace('-', '_', $res);
                    if (!empty($this->access_default[$res])) {
                        if (isset($this->access_default[$res][$type]) && $this->access_default[$res][$type] === 'on') {
                            $this->_acl->allow($roleName, $res, $type);
                        } // если в настройках модуля установлен access

                        if (isset($this->access_default[$res][$type . "_all"]) && $this->access_default[$res][$type . "_all"] === 'on') {
                            $this->_acl->allow($roleName, $res, $type . "_all");
                        } // если в настройках модуля установлен all
                        elseif (isset($this->access_default[$res][$type . "_owner"]) && $this->access_default[$res][$type . "_owner"] === 'on') {
                            $this->_acl->allow($roleName, $res, $type . "_owner");
                        } // если в настройках модуля установлен owner

                        if (isset($this->access_default[$res][$type])) {
                            if ($this->access_default[$res][$type] === 'all') {
                                $this->_acl->allow($roleName, $res, $type . "_all");
                            } // если в настройках модуля для кастомного правила установлен all
                            elseif ($this->access_default[$res][$type] === 'owner') {
                                $this->_acl->allow($roleName, $res, $type . "_owner");
                            } // если в настройках модуля для кастомного правила установлен owner
                        }
                    }
                }
            }
        }
    }


    /**
     * Проверка существования и установка ресурса в ACL
     * @param Registry $registry
     * @param               $resource
     *
     * @throws \Exception
     */
    private function setResource($resource) {
        $registry    = Registry::getInstance();
        $acl         = $registry->get('acl');
        $addRes      = $registry->get('addRes');
        $availRes    = $registry->get('availRes');
        $availSubRes = $registry->get('availSubRes');
        if (!in_array($resource, $availRes) && !in_array($resource, $addRes) && !in_array($resource, $availSubRes)) {
            $acl->addResource(new Resource($resource));
            $addRes[] = $resource;
        }
        if ($addRes) {
            $registry->set('addRes', $addRes);
        }
    }


	/**
     * Разрешить использование ресурса $resource для роли $role с привилегиями $type
	 * @param $role
	 * @param $resource
	 * @param $type
	 */
	public function allow($role, $resource, $type = 'access') {
        $registry    = Registry::getInstance();
        $acl  = $registry->isRegistered('acl') ? $registry->get('acl') : null;
        if (!$acl) {
            throw new \Exception("Need to setup ACL");
        }
        $this->setResource($resource);
        $acl->allow($role, $resource, $type);
		$registry->set('acl', $acl);

	}

	/**
	 * Доступ роли к ресурсу по всем параметрам, за исключением тех, что указаны в $except
	 *
	 * @param       $role
	 * @param       $resource
	 * @param array $except
	 */
	public function allowAll($role, $resource, Array $except = array())
	{
		$types = array(
				'access',
				'list_all',
				'read_all',
				'edit_all',
				'delete_all'
		);
		foreach ($types as $type) {
			if ($except && in_array($type, $except)) {
                continue;
            }
			$this->allow($role, $resource, $type);
		}
	}

    /**
     * Запретить использование ресурса $resource для роли $role с привилегиями $type
     * @param $role
     * @param $resource
     * @param $type
     *
     * @throws \Exception
     */
    public function deny($role, $resource, $type = 'access')
    {
        $registry    = Registry::getInstance();
        $acl         = $registry->get('acl');
        $this->setResource($resource);
        $acl->deny($role, $resource, $type);
        $registry->set('acl', $acl);
    }

	/**
     * Проверка доступа к ресурсу $source для текущей роли
	 * @param $source
	 * @param $type
	 * @return bool
	 */
	public function checkAcl($source, $type = 'access'): bool {

        $registry = Registry::getInstance();

        $auth = $registry->get('auth');
        if ($auth->NAME == 'root' || $auth->ADMIN) {
            return true;
        }

        $acl  = $registry->isRegistered('acl') ? $registry->get('acl') : null;
        if (!$acl) {
            return false;
        }

        if (($xxx = strrpos($source, 'xxx')) > 0) {
            $source = substr($source, 0, $xxx); //TODO SHOULD BE FIX
        }

        if (($index = strrpos($source, '_index')) > 0) {
            $source = substr($source, 0, $index); //TODO SHOULD BE FIX
        }

        if (in_array($source, $registry->get('availRes'))) {
            return $acl->isAllowed($auth->ROLE, $source, $type);

		} elseif (in_array($source, $registry->get('availSubRes'))) {
			return $acl->isAllowed($auth->ROLE, $source, $type);

		} elseif (in_array($source, $registry->get('addRes'))) {
			return $acl->isAllowed($auth->ROLE, $source, $type);

		} else {
			return false;
		}
	}
}
