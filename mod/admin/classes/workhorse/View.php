<?php
namespace Core2\Mod\Admin\Workhorse;

require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/class.tab.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';


/**
 * @property
 */
class View extends \Common {

    private $app = "index.php?module=admin&action=workhorse";


    /**
     * таблица с юзерами
     * @return false|string
     * @throws \Exception
     */
    public function getList($app) {

        $list = new \listTable('user');

        $search_roles = $this->db->fetchPairs("
            SELECT id,
                   name
            FROM core_roles
        ");

        $list->addSearch($this->_("Логин"), "u.u_login", "TEXT");
        $list->addSearch($this->_("ФИО"),   "CONCAT_WS(' ', up.lastname, up.firstname, up.middlename)", "TEXT");
        $list->addSearch("Email",           "u.email", "TEXT");
        $list->addSearch($this->_("Роль"),  "r.id", "LIST"); $list->sqlSearch[] = $search_roles;

        $list->SQL = "
            SELECT u_id,
                   u_login,
                   CONCAT_WS(' ', up.lastname, up.firstname, up.middlename),
                   u.email,
                   r.name,
                   (SELECT DATE_FORMAT(login_time, '%Y-%m-%d %H:%i')
                    FROM core_session
                    WHERE u.u_id = user_id
                    ORDER BY login_time DESC
                    LIMIT 1) AS last_login,
                   u.date_added,
                   CASE u.`is_pass_changed` WHEN 'N' THEN 'Да' END AS is_pass_changed,
                   CASE u.`is_email_wrong` WHEN 'Y' THEN 'Да' END AS is_email_wrong,
                   CASE u.`is_admin_sw` WHEN 'Y' THEN 'Да' END AS is_admin_sw,
                   null AS login_btn,
                   u.visible
            FROM core_users AS u
                 LEFT JOIN core_users_profile AS up ON up.user_id = u.u_id
                 LEFT JOIN core_roles AS r ON r.id = u.role_id
            WHERE u_id > 0 /*ADD_SEARCH*/
            ORDER BY u.date_added DESC
        ";

        $list->addColumn($this->_("Логин"),                 "100", "TEXT");
        $list->addColumn($this->_("ФИО"),                   "",    "TEXT");
        $list->addColumn("Email",                           "155", "TEXT");
        $list->addColumn($this->_("Роль"),                  "130", "TEXT");
        $list->addColumn($this->_("Дата последнего входа"), "120", "DATETIME");
        $list->addColumn($this->_("Дата регистрации"),      "135", "DATE");
        $list->addColumn($this->_("Нужно сменить пароль"),  "120", "TEXT");
        $list->addColumn($this->_("Неверный email"),        "125", "TEXT");
        $list->addColumn($this->_("Админ"),                 "1",   "TEXT");
        $list->addColumn("",                                "1",   "BLOCK");
        $list->addColumn("",                                "1",   "STATUS_INLINE", "core_users.visible");

        $list->paintCondition = "'TCOL_11' == 'N'";
        $list->paintColor     = "fafafa";
        $list->fontColor      = "silver";

        $list->addURL    = $app . "&edit=0";
        $list->editURL   = $app . "&edit=TCOL_00";
        $list->deleteKey = "core_users.u_id";

        $list->getData();
        foreach ($list->data as $key => $row) {
            $list->data[$key][10] = "<button class=\"button btn btn-sm btn-default\" type=\"button\" onclick=\"AdminUsers.loginUser('{$row[0]}')\">Войти</button>";
        }

        ob_start();
        $this->printCssModule('admin', '/assets/css/admin.users.css');
        $this->printJsModule('admin', '/assets/js/admin.users.js');
        $list->showTable();
        return ob_get_clean();
    }


    /**
     * @param string    $app
     * @param User|null $user
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function getEdit(string $app, User $user = null) {

        $edit = new \editTable('user');

        $fields = [
            'u_id',
            'u_login',
            'email',
            'role_id',
            'lastname',
            'firstname',
            'middlename',
            'u_pass',
            'certificate',
            'is_email_wrong',
            'is_pass_changed',
            'is_admin_sw',
            'NULL AS send_info_sw'
        ];


        if ( ! $user) {
            $about_email = $this->_("Отправить информацию о пользователе на email");
        } else {
            unset($fields[1]);
            $about_email = $this->_("Отправить информацию об изменении на email");
        }

        $is_auth_certificate_on = $this->core_config->auth && $this->core_config->auth->x509 && $this->core_config->auth->x509->on;


        if ($this->core_config->auth && $this->core_config->auth->pass) {
            $is_auth_pass_on = $this->core_config->auth->pass->on;
        } else {
            $is_auth_pass_on = true;
        }



        if ($this->auth->LDAP) {
            unset($fields[7]);
            unset($fields[8]);
            unset($fields[10]);

        } else {
            if ( ! $is_auth_pass_on) {
                unset($fields[7]);
                unset($fields[10]);
            }
            if ( ! $is_auth_certificate_on) {
                unset($fields[8]);
            }
        }

        $implode_fields = implode(",\n", $fields);

        $edit->SQL = $this->db->quoteInto("
            SELECT {$implode_fields}
            FROM core_users
               LEFT JOIN core_users_profile AS p ON p.user_id = u_id
            WHERE u_id = ?
        ", $user ? $user->u_id : 0);

        $role_list = $this->db->fetchPairs("
            SELECT id, 
                   name 
            FROM core_roles 
            WHERE is_active_sw = 'Y'
            ORDER BY position ASC
        ");



        $certificate = $user
            ? htmlspecialchars($user->certificate)
            : '';

        $description_admin = "<br><small class=\"text-muted\">полный доступ</small>";

        if ( ! $user) {
            $edit->addControl("Логин", "TEXT", "maxlength=\"60\" style=\"width:385px\"", "", "", true);
        }

        $edit->addControl("Email",              "TEXT", "maxlength=\"60\" style=\"width:385px\"", "", "");
        $edit->addControl($this->_("Роль"),     "LIST", "style=\"width:385px\"", "", "", true); $edit->selectSQL[] = ['' => '--'] + $role_list;
        $edit->addControl($this->_("Фамилия"),  "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "");
        $edit->addControl($this->_("Имя"),      "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "", true);
        $edit->addControl($this->_("Отчество"), "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "");

        if ( ! $this->auth->LDAP) {
            if ( ! $this->auth->LDAP && $is_auth_pass_on) {
                $edit->addControl($this->_("Пароль"), "PASSWORD", "", "", "", true);
            }

            if ($is_auth_certificate_on) {
                $cert_desc = '<br><small class="text-muted">x509</small>';
                $edit->addControl($this->_("Сертификат") . $cert_desc, "XFILE_AUTO", "", $this->editCert($certificate), "");
            }
        }

        $edit->addControl($this->_("Неверный email"), "RADIO", "", "", "N", true); $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];

        if ( ! $this->auth->LDAP && $is_auth_pass_on) {
            $edit->addControl($this->_("Предупреждение о смене пароля"), "RADIO", "", "", "N", true); $edit->selectSQL[] = ['N' => 'да', 'Y' => 'нет'];
        }

        $edit->addControl($this->_("Администратор безопасности{$description_admin}"), "RADIO", "", "", "N", true); $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];
        $edit->addControl($about_email,                                               "CHECKBOX", "", "", "0"); $edit->selectSQL[] = ['Y' => ''];

        $is_active_sw = $user
            ? $this->dataUsers->exists("visible = 'Y' AND u_id = ?", $user->u_id)
            : '';

        $edit->addButtonSwitch('visible', $is_active_sw);

        $edit->back = $app;
        $edit->firstColWidth = '200px';
        $edit->addButton($this->_("Вернуться к списку пользователей"), "load('$app')");
        $edit->save("xajax_saveUser(xajax.getFormValues(this.id))");

        return $edit->render();
    }

}