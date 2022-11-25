<?php
namespace Core2\Mod\Admin\Users;

require_once DOC_ROOT . "core2/inc/classes/Common.php";


/**
 * @property string $u_id
 * @property string $u_login
 * @property string $u_pass
 * @property string $visible
 * @property string $lastupdate
 * @property string $email
 * @property string $lastuser
 * @property string $is_admin_sw
 * @property string $certificate
 * @property string $role_id
 * @property string $reg_key
 * @property string $date_added
 * @property string $date_expired
 * @property string $is_email_wrong
 * @property string $is_pass_changed
 */
class User extends \Common {

    /**
     * @var array
     */
    private $_data = [];


    /**
     * @param int $user_id
     * @throws \Exception
     */
    public function __construct(int $user_id) {
        parent::__construct();
        $this->setData($user_id);
    }


    /**
     * @param string $v
     * @return \Common|\CommonApi|\CoreController|mixed|\stdObject|\Zend_Config_Ini|\Zend_Db_Adapter_Abstract|null
     * @throws \Exception
     */
    public function __get($v) {
        return $this->_data[$v] ?? parent::__get($v);
    }


    /**
     * Удаление сотрудника
     */
    public function delete() {

        $where = $this->db->quoteInto('u_id ?', $this->u_id);
        $this->db->delete('core_users', $where);
    }


    /**
     * Отключение пользователя
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    public function disable(): bool {

        $where = $this->db->quoteInto('u_id = ?', $this->user_id);
        $this->db->update('core_users', [
            'visible' => 'N',
        ], $where);

        return true;
    }


    /**
     * Включение пользователя
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     */
    public function enable(): bool {

        $where = $this->db->quoteInto('u_id = ?', $this->user_id);
        $this->db->update('core_users', [
            'visible' => 'Y',
        ], $where);

        return true;
    }


    /**
     * Получение данных
     * @param int $user_id
     * @return void
     * @throws \Exception
     */
    private function setData(int $user_id) {

        if ($this->_data) {
            return;
        }

        $row = $user_id ? $this->modAdmin->dataUsers->find($user_id)->current() : null;

        if ($row) {
            $this->_data = $row;
        } else {
            throw new \Exception($this->_('Указанный пользователь не найден'));
        }
    }
}