<?php
/**
 * Created by JetBrains PhpStorm.
 * User: StepovichPE
 * Date: 14.09.13
 * Time: 17:04
 * To change this template use File | Settings | File Templates.
 */

class UsersProfile extends Zend_Db_Table_Abstract {

	protected $_name = 'core_users_profile';

	protected $_referenceMap = array(
		'User' => array(
			'columns'       => 'user_id',
			'refTableClass' => 'Users'
		)
	);
}