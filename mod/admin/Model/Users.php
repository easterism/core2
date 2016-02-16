<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */

class Users extends Zend_Db_Table_Abstract {

	protected $_name = 'core_users';
	//protected $_dependentTables = array('UsersProfile');

	public function exists($expr, $var = array())
	{
		$sel = $this->select()->where($expr, $var);

		return $this->fetchRow($sel->limit(1));
	}

	public function getUserById($id) {
        $res   = $this->_db->fetchRow("SELECT `u_id`, `u_pass`, u.email, `u_login`, p.lastname, p.firstname, p.middlename, u.is_admin_sw, r.name AS role, u.role_id
								 FROM `core_users` AS u
								 	  LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
								 	  LEFT JOIN core_roles AS r ON r.id = u.role_id
								WHERE u.`visible`='Y' AND u.u_login=? LIMIT 1", $id);
        return $res;
    }

	/**
	 * Получаем информацию о пользователе по его логину
	 * @param $login
	 *
	 * @return mixed
	 */
	public function getUserByLogin($login) {
        $res   = $this->_db->fetchRow("SELECT `u_id`, `u_pass`, u.email, `u_login`, p.lastname, p.firstname, p.middlename, u.is_admin_sw, r.name AS role, u.role_id
								 FROM `core_users` AS u
								 	  LEFT JOIN core_users_profile AS p ON u.u_id = p.user_id
								 	  LEFT JOIN core_roles AS r ON r.id = u.role_id
								WHERE u.`visible`='Y' AND u.u_login=? LIMIT 1", $login);
        return $res;
	}

}