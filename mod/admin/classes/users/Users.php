<?php
namespace Core2\Mod\Admin\Users;
use Laminas\Session\Container as SessionContainer;

require_once DOC_ROOT . 'core2/inc/classes/Common.php';


/**
 *
 */
class Users extends \Common {


    /**
     * @param $user_id
     * @return bool
     * @throws \Exception
     */
    public function loginUser($user_id): bool {

        $user = $this->db->fetchRow("
            SELECT u.u_id,
                   u.u_login,
                   u.email,
                   u.role_id,
                   u.is_admin_sw,
                   u.visible,
                   up.firstname,
                   up.lastname,
                   up.middlename,
                   r.name AS role
            FROM core_users AS u
                LEFT JOIN core_users_profile AS up ON u.u_id = up.user_id 
                LEFT JOIN core_roles AS r ON r.id = u.role_id  
            WHERE u.u_id = ?
        ", $user_id);

        if (empty($user)) {
            throw new \Exception($this->_('Указанный пользователь не найден'));
        }

        if ($user['visible'] == 'N') {
            throw new \Exception($this->_('Указанный пользователь не активен'));
        }

        $authNamespace = new SessionContainer('Auth');
        $authNamespace->accept_answer = true;

        $session_life = $this->db->fetchOne("
            SELECT value 
            FROM core_settings 
            WHERE visible = 'Y' 
              AND code = 'session_lifetime' 
            LIMIT 1
        ");

        if ($session_life) {
            $authNamespace->setExpirationSeconds($session_life, "accept_answer");
        }

        if (session_id() == 'deleted') {
            throw new \Exception($this->_("Ошибка сохранения сессии. Проверьте настройки системного времени."));
        }

        $authNamespace->ID     = (int)$user['u_id'];
        $authNamespace->NAME   = $user['u_login'];
        $authNamespace->EMAIL  = $user['email'];
        $authNamespace->LN     = $user['lastname'];
        $authNamespace->FN     = $user['firstname'];
        $authNamespace->MN     = $user['middlename'];
        $authNamespace->ADMIN  = $user['is_admin_sw'] == 'Y';
        $authNamespace->ROLE   = $user['role'] ? $user['role'] : -1;
        $authNamespace->ROLEID = $user['role_id'] ? $user['role_id'] : 0;
        $authNamespace->LDAP   = false;

        return true;
    }
}