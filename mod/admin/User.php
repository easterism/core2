<?php
/**
 * Created by PhpStorm.
 * User: easter
 * Date: 06.10.17
 * Time: 11:27
 */

namespace Core2;


/**
 * Class User
 * @property \Users $dataUsers
 * @package Core2
 */
class User extends \Common {

    private $app   = "index.php?module=admin&action=users";
    private $_user;
    private $_info = ['cols' => []]; //перечень всех полей


    /**
     * User constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->_info = $this->dataUsers->info();
    }


    /**
     * @param string $name
     * @return \Common|\CoreController|mixed|\Zend_Config_Ini|\Zend_Db_Adapter_Abstract|null
     * @throws \Exception
     */
    public function __get($name) {

        if ( ! in_array($name, $this->_info['cols']))  {
            return parent::__get($name);
        } else {
            return $this->_user->$name;
        }
    }


    /**
     * получаем данные по id
     * @param $id - id юзера
     * @throws \Exception
     */
    public function get($id) {

        $this->_user = $this->dataUsers->find($id)->current();

        if ( ! $this->_user) {
            throw new \Exception(404);
        }
    }


    /**
     * таблица с юзерами
     * @return false|string
     */
    public function table() {

        $list = new \listTable('user');

        $list->addSearch($this->_("Логин"),   "u.u_login", "TEXT");
        $list->addSearch($this->_("Фамилия"), "up.lastname", "TEXT");
        $list->addSearch($this->_("Имя"),     "up.firstname", "TEXT");

        $list->SQL = "
            SELECT u_id,
                   u_login,
                   CONCAT_WS(' ' ,up.lastname, up.firstname, up.middlename),
                   email,
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
                   u.visible
            FROM core_users AS u
                 LEFT JOIN core_users_profile AS up ON up.user_id = u.u_id
                 LEFT JOIN core_roles AS r ON r.id = u.role_id
            WHERE u_id > 0 /*ADD_SEARCH*/
            ORDER BY u.date_added DESC
        ";

        $list->addColumn($this->_("Логин"),                 "100", "TEXT");
        $list->addColumn($this->_("Имя"),                   "",    "TEXT");
        $list->addColumn("Email",                           "155", "TEXT");
        $list->addColumn($this->_("Роль"),                  "130", "TEXT");
        $list->addColumn($this->_("Дата последнего входа"), "120", "DATETIME");
        $list->addColumn($this->_("Дата регистрации"),      "135", "DATE");
        $list->addColumn($this->_("Нужно сменить пароль"),  "120", "TEXT");
        $list->addColumn($this->_("Неверный email"),        "125", "TEXT");
        $list->addColumn($this->_("Админ"),                 "1",   "TEXT");
        $list->addColumn("",                                "1",   "STATUS_INLINE", "core_users.visible");

        $list->paintCondition = "'TCOL_10' == 'N'";
        $list->paintColor     = "fafafa";
        $list->fontColor      = "silver";

        $list->addURL    = $this->app . "&edit=0";
        $list->editURL   = $this->app . "&edit=TCOL_00";
        $list->deleteKey = "core_users.u_id";


        ob_start();
        $list->showTable();
        return ob_get_clean();
    }


    /**
     * форма редактирования
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function edit() {

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


        if ( ! $this->_user->u_id) {
            $about_email = $this->_("Отправить информацию о пользователе на email");
        } else {
            unset($fields[1]);
            $about_email = $this->_("Отправить информацию об изменении на email");
        }

        if ($this->auth->LDAP) {
            unset($fields[7]);
        }

        $edit->SQL = $this->db->quoteInto("
                SELECT " . implode("," . chr(10), $fields) . "
                FROM core_users
                   LEFT JOIN core_users_profile AS p ON p.user_id = u_id
                WHERE `u_id` = ?
            ", $this->_user->u_id);

        $role_list = $this->db->fetchPairs("
            SELECT id, 
                   name 
            FROM core_roles 
            WHERE is_active_sw = 'Y'
            ORDER BY position ASC
        ");

        $certificate = '';

        if ($this->_user->u_id) {
            $certificate = htmlspecialchars($this->_user->certificate);
        }


        if ( ! $this->_user->u_id) {
            $edit->addControl("Логин:", "TEXT", "maxlength=\"60\" style=\"width:385px\"", "", "", true);
        }

        $edit->addControl("Email:",              "TEXT", "maxlength=\"60\" style=\"width:385px\"", "", "");
        $edit->addControl($this->_("Роль:"),     "LIST", "style=\"width:385px\"", "", "", true); $edit->selectSQL[] = ['' => '--'] + $role_list;
        $edit->addControl($this->_("Фамилия:"),  "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "");
        $edit->addControl($this->_("Имя:"),      "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "", true);
        $edit->addControl($this->_("Отчество:"), "TEXT", "maxlength=\"20\" style=\"width:385px\"", "", "");

        if ( ! $this->auth->LDAP) {
            $edit->addControl($this->_("Пароль:"), "PASSWORD", "", "", "", true);
        }

        $edit->addControl($this->_("Сертификат:"),                                 "XFILE_AUTO",  "", $this->editCert($certificate), "");
        $edit->addControl($this->_("Неверный email:"),                             "RADIO", "", "", "N", true); $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];
        $edit->addControl($this->_("Предупреждение о смене пароля:"),              "RADIO", "", "", "N", true); $edit->selectSQL[] = ['N' => 'да', 'Y' => 'нет'];
        $edit->addControl($this->_("Администратор безопасности (полный доступ):"), "RADIO", "", "", "N", true); $edit->selectSQL[] = ['Y' => 'да', 'N' => 'нет'];
        $edit->addControl($about_email,                                            "CHECKBOX", "", "", "0"); $edit->selectSQL[] = ['Y' => ''];

        $is_active_sw = $this->_user->u_id
            ? $this->dataUsers->exists("visible = 'Y' AND u_id = ?", $this->_user->u_id)
            : '';

        $edit->addButtonSwitch('visible', $is_active_sw);

        $edit->back = $this->app;
        $edit->addButton($this->_("Вернуться к списку пользователей"), "load('$this->app')");
        $edit->save("xajax_saveUser(xajax.getFormValues(this.id))");

        ob_start();
        $edit->showTable();
        return ob_get_clean();
    }


    /**
     * Создание нового юзера
     */
    public function create() {
        $this->_user = $this->dataUsers->createRow();
    }


    /**
     * вывод
     * @return false|string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function dispatch() {

        if ($this->_user) {
            return $this->edit();
        } else {
            return $this->table();
        }
    }


    /**
     * @param $cert
     * @return string
     */
    private function editCert($cert) {

        $html = "
            <br/>
            <textarea style=\"min-width:385px;max-width:385px;min-height: 150px\" name=\"control[certificate_ta]\">{$cert}</textarea>
            <br>
            <label class=\"text-muted\">
                <input type=\"checkbox\" name=\"certificate_parse\" value=\"Y\"> Использовать ФИО и email из сертификата
            </label>
        ";

        return $html;
    }
}