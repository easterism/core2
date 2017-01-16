<?

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

	/**
	 *
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 *
	 */
	public function setupAcl() {
		$registry 	= Zend_Registry::getInstance();
		$registry->set('addRes', $this->addRes);
		$auth 		= Zend_Registry::get('auth');

		$key 		= 'acl_' . $auth->ROLEID . self::INHER_ROLES;

		if (!($this->cache->test($key))) {
			$acl = new Zend_Acl();
			$SQL = "SELECT *
					  FROM (
						(SELECT module_id, m.seq, m.access_default, m.access_add
						  FROM core_modules AS m
						  WHERE visible='Y'
						  ORDER BY seq)
						UNION ALL
						(SELECT CONCAT(m.module_id, '_', s.sm_key) AS module_id, m.seq, s.access_default, s.access_add
							FROM core_submodules AS s
								 INNER JOIN core_modules AS m ON m.m_id = s.m_id AND m.visible='Y'
							WHERE sm_id > 0 AND s.visible='Y'
						   ORDER BY m.seq, s.seq)
					   ) AS a ORDER BY 2";
			$res = $this->db->fetchAll($SQL);
			// ADD ALL AVAILABLE RESOURCES

			$resources = array();
			$resources2 = array();
			$access_default = array();

			// Если не назначена роль, добавляем виртуальную роль в ACL
			if ($auth->ROLE === -1) {
				$acl->addRole(new Zend_Acl_Role($auth->ROLE));
			}

			// обрабатываем только модули
			foreach ($res as $data) {
				$access_default[$data['module_id']] = array();
				if ($data['access_default']) {
					$temp = @unserialize(base64_decode($data['access_default']));
					if ($temp && is_array($temp)) $access_default[$data['module_id']] = $temp;
				}
				if ($data['access_add']) {
					$temp = @unserialize(base64_decode($data['access_add']));
					if ($temp && is_array($temp)) $access_default[$data['module_id']] += $temp;
				}
				$mod2 = explode('_', $data['module_id']);
				if (!in_array($mod2[0], $resources)) {
					$resources[] = $mod2[0];
					$acl->addResource(new Zend_Acl_Resource($mod2[0]));
				}
			}

			// обрабатываем только субмодули
			foreach ($res as $data) {
				$mod2 = explode('_', $data['module_id']);
				if (!empty($mod2[1])) {
					if (!in_array($data['module_id'], $resources2)) {
						$resources2[] = $data['module_id'];
						$acl->addResource(new Zend_Acl_Resource($data['module_id']), $mod2[0]);
					}
				}
			}
			//echo "<PRE>";print_r($access_default);echo "</PRE>";die;

			if ($auth->ROLE !== -1) {
				$roles   = $this->db->fetchAll("SELECT id, name, access
											FROM core_roles
											WHERE is_active_sw = 'Y'
											ORDER BY position DESC");
				$i = 1;
				foreach ($roles as $value) {
					$roleName = $value['name'];
					if (self::INHER_ROLES == 'Y') {
						if ($i == 1) {
							$acl->addRole(new Zend_Acl_Role($value['name']));
						} else {
							$acl->addRole(new Zend_Acl_Role($value['name']), $roleName);
						}
					} else {
						$acl->addRole(new Zend_Acl_Role($roleName));
					}

					$access = unserialize($value['access']);
                    if ( ! empty($access)) {
                        foreach ($access as $type => $data) {
                            if (strpos($type, 'default') === false) {

                                foreach ($resources2 as $availSubRes) {
                                    if (!empty($data[str_replace('_', '-', $availSubRes)])) {
                                        $acl->allow($roleName, $availSubRes, $type);
                                    } else {
                                        $acl->deny($roleName, $availSubRes, $type);
                                    }
                                }
                                foreach ($resources as $availRes) {
                                    if (!empty($data[$availRes])) {
                                        $acl->allow($roleName, $availRes, $type);
                                    } else {
                                        $acl->deny($roleName, $availRes, $type);
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
                                    if (!empty($access_default[$res])) {
                                        if (isset($access_default[$res][$type]) && $access_default[$res][$type] === 'on') $acl->allow($roleName, $res, $type); // если в настройках модуля установлен access

                                        if (isset($access_default[$res][$type . "_all"]) && $access_default[$res][$type . "_all"] === 'on') $acl->allow($roleName, $res, $type . "_all"); // если в настройках модуля установлен all
                                        elseif (isset($access_default[$res][$type . "_owner"]) && $access_default[$res][$type . "_owner"] === 'on') $acl->allow($roleName, $res, $type . "_owner"); // если в настройках модуля установлен owner

                                        if (isset($access_default[$res][$type])) {
                                            if ($access_default[$res][$type] === 'all') $acl->allow($roleName, $res, $type . "_all"); // если в настройках модуля для кастомного правила установлен all
                                            elseif ($access_default[$res][$type] === 'owner') $acl->allow($roleName, $res, $type . "_owner"); // если в настройках модуля для кастомного правила установлен owner
                                        }
                                    }
                                }
                            }
                        }
					}
					$i++;
				}
			}
			else {
				foreach ($access_default as $res => $types) {
					if ($types) {
						foreach ($types as $type => $on) {
							if ($on === 'on') {
								if ($type === 'access') $acl->allow($auth->ROLE, $res, $type);
								if ($type === 'list_all') $acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'list_owner') $acl->allow($auth->ROLE, $res, $type);
								if ($type === 'read_all') $acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'read_owner') $acl->allow($auth->ROLE, $res, $type);
								if ($type === 'edit_all') $acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'edit_owner') $acl->allow($auth->ROLE, $res, $type);
								if ($type === 'delete_all') $acl->allow($auth->ROLE, $res, $type);
								elseif ($type === 'delete_owner') $acl->allow($auth->ROLE, $res, $type);
							} else if ($on === 'all') {
								if ($type === 'access') $acl->allow($auth->ROLE, $res, $type . "_all");
							} else if ($on === 'owner') {
								if ($type === 'access') $acl->allow($auth->ROLE, $res, $type . "_owner");
							}
						}
					}
				}
				if ($data['access_default']) {
					$access = unserialize(base64_decode($data['access_default']));
					foreach ($access as $type => $f) {
						$acl->allow($auth->ROLE, $data['module_id'], $type);
					}
				}
			}
			$this->cache->save($acl, $key, array("role" . $auth->ROLEID));
			$this->cache->save($resources, $key . 'availRes', array("role" . $auth->ROLEID));
			$this->cache->save($resources2, $key . 'availSubRes', array("role" . $auth->ROLEID));
		}
		else {
			$acl = $this->cache->load($key);
			$resources = $this->cache->load($key . 'availRes');
			$resources2 = $this->cache->load($key . 'availSubRes');
		}
		//echo "<PRE>";print_r($acl);echo "</PRE>";die;
		$registry->set('acl', $acl);
		$registry->set('availRes', $resources);
		$registry->set('availSubRes', $resources2);

	}

    /**
     * Проверка существования и установка ресурса в ACL
     * @param Zend_Registry $registry
     * @param               $resource
     *
     * @throws Zend_Exception
     */
    private function setResource(Zend_Registry $registry, $resource) {
        $acl         = $registry->get('acl');
        $addRes      = $registry->get('addRes');
        $availRes    = $registry->get('availRes');
        $availSubRes = $registry->get('availSubRes');
        if (!in_array($resource, $availRes) && !in_array($resource, $addRes) && !in_array($resource, $availSubRes)) {
            $acl->addResource(new Zend_Acl_Resource($resource));
            $addRes[] = $resource;
        }
        if ($addRes) $registry->set('addRes', $addRes);
    }


	/**
     * Разрешить использование ресурса $resource для роли $role с привилегиями $type
	 * @param $role
	 * @param $resource
	 * @param $type
	 */
	public function allow($role, $resource, $type = 'access') {
        $registry    = Zend_Registry::getInstance();
        $acl         = $registry->get('acl');
        $this->setResource($registry, $resource);
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
			if ($except && in_array($type, $except)) continue;
			$this->allow($role, $resource, $type);
		}
	}

    /**
     * Запретить использование ресурса $resource для роли $role с привилегиями $type
     * @param $role
     * @param $resource
     * @param $type
     *
     * @throws Zend_Exception
     */
    public function deny($role, $resource, $type = 'access')
    {
        $registry    = Zend_Registry::getInstance();
        $acl         = $registry->get('acl');
        $this->setResource($registry, $resource);
        $acl->deny($role, $resource, $type);
        $registry->set('acl', $acl);
    }

	/**
     * Проверка доступа к ресурсу $source для текущей роли
	 * @param $source
	 * @param $type
	 * @return bool
	 */
	public function checkAcl($source, $type = 'access') {
        $registry = Zend_Registry::getInstance();
		$xxx = strrpos($source, 'xxx');
		if ($xxx > 0) {
			$source   = substr($source, 0, $xxx); //TODO SHOULD BE FIX
		}
        $acl      = $registry->get('acl');
        $auth     = $registry->get('auth');
		if ($auth->NAME == 'root' || $auth->ADMIN) {
			return true;
		} elseif (in_array($source, $registry->get('availRes'))) {
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
