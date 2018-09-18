<?php


/**
 * Class Users
 */
class Users extends Zend_Db_Table_Abstract {

	protected $_name = 'core_users';


    /**
     * @param string $expr
     * @param array  $var
     * @return null|Zend_Db_Table_Row_Abstract
     */
	public function exists($expr, $var = array()) {
		$sel = $this->select()->where($expr, $var);

		return $this->fetchRow($sel->limit(1));
	}

    /**
     * Получаем значение одного поля
     *
     * @param $field
     * @param $expr
     * @param array $var
     * @return string
     */
    public function fetchOne($field, $expr, $var = array())
    {
        $sel = $this->select();
        if ($var) {
            $sel->where($expr, $var);
        } else {
            $sel->where($expr);
        }
        return $this->fetchRow($sel)->$field;
    }


    /**
     * @param string $id
     * @return mixed
     */
	public function getUserById($id) {
        $res   = $this->_db->fetchRow("
            SELECT `u_id`, 
                   `u_pass`, 
                   u.email, 
                   `u_login`, 
                   p.lastname, 
                   p.firstname, 
                   p.middlename, 
                   u.is_admin_sw, 
                   r.name AS role, 
                   u.role_id
            FROM `core_users` AS u
                LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
                LEFT JOIN core_roles AS r ON r.id = u.role_id
            WHERE u.`visible` = 'Y' 
              AND u.u_id = ? 
            LIMIT 1
        ", $id);

        return $res;
    }


	/**
	 * Получаем информацию о пользователе по его логину
	 * @param $login
	 *
	 * @return mixed
	 */
	public function getUserByLogin($login) {
        $res   = $this->_db->fetchRow("
            SELECT `u_id`, 
                   `u_pass`, 
                   u.email, 
                   `u_login`, 
                   p.lastname, 
                   p.firstname, 
                   p.middlename, 
                   u.is_admin_sw, 
                   r.name AS role, 
                   u.role_id
            FROM `core_users` AS u
                LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
                LEFT JOIN core_roles AS r ON r.id = u.role_id
            WHERE u.`visible` = 'Y' 
              AND (u.u_login = :login OR u.email = :login) 
            LIMIT 1
        ", [
            'login' => $login
        ]);

        return $res;
	}
}