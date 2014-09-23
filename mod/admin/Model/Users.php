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


}