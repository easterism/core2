<?php

require_once DOC_ROOT . 'core2/inc/classes/class.list.php';
require_once DOC_ROOT . 'core2/inc/classes/class.edit.php';
require_once DOC_ROOT . 'core2/inc/classes/Common.php';


/**
 * Class Profile
 */
class Profile extends Common {

    protected $user_id;


    /**
     * @param int $user_id
     */
    public function __construct($user_id) {
        parent::__construct();
        $this->user_id = $user_id;
    }


    /**
     * Редактирование
     * @param string $app
     * @param bool   $is_readonly
     * @return string
     */
    public function getEdit($app, $is_readonly = true) {

        ob_start();

        $edit = new editTable($this->resId);
        $edit->SQL = $this->db->quoteInto("
            SELECT id,
                   firstname,
                   lastname,
                   middlename,
                   email,
                   u_pass
            FROM core_users_profile AS up
                INNER JOIN core_users AS u ON u.u_id=up.user_id
            WHERE up.user_id = ?
        ", $this->user_id);

        if ( ! $is_readonly) {
            $edit->addControl("Имя",      "TEXT", 'maxlength="255"', "", "", true);
            $edit->addControl("Фамилия",  "TEXT", 'maxlength="255"', "", "", true);
            $edit->addControl("Отчество", "TEXT", 'maxlength="255"', "", "");
            $edit->addControl("Email",    "TEXT", '', "", "", true);

            if ( ! $this->auth->LDAP) {
                $passHash = "
                    <script>
                        $(document).ready(function(){
                            var ofFunc = $('#main_profile_mainform').attr('onSubmit');
                            var hash = \"if ($('#main_profileu_pass').val() != '') \{$('#main_profileu_pass').val(hex_md5($('#main_profileu_pass').val()));\";
                            hash += \"$('#main_profileu_pass2').val(hex_md5($('#main_profileu_pass2').val()));}\";
                            $('#main_profile_mainform').attr('onSubmit', hash + ofFunc);
                        })
                    </script>
                ";
                $edit->addControl("Пароль", "PASSWORD", "", $passHash, "", true);
            }
            $edit->addButton('Отмена', "load('{$app}')");

        } else {
            $edit->addControl("Имя",      "PROTECTED");
            $edit->addControl("Фамилия",  "PROTECTED");
            $edit->addControl("Отчество", "PROTECTED");
            $edit->addControl("Email",    "PROTECTED");
            if ( ! $this->auth->LDAP) {
                $edit->addControl("Пароль", "CUSTOM", "******");
            }
            $edit->readOnly = 'Y';
            $edit->addButtonCustom("<input type=\"button\" class=\"button btn btn-info\"
                                           value=\"Редактировать\" onclick=\"load('{$app}&editprofile=1')\">");
        }

        $edit->back = $app;
        $edit->save("xajax_saveProfile(xajax.getFormValues(this.id))");

        $edit->showTable();

        return ob_get_clean();
    }



    /**
     * @param string $app
     * @param bool   $is_readonly
     *
     * @return string
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public function getEditSettings($app, $is_readonly = true) {

        $settings = $this->getSettings();

        $edit = new editTable($this->resId);
        $data = array();

        foreach ($settings as $setting) {
            $data[$setting['name']] = $setting['value'];

            switch ($setting['type']) {
                case 'checkbox':
                    $edit->addControl($setting['label'], "CHECKBOX2");
                    $edit->selectSQL[] = $setting['options'];
                    break;

                case 'list':
                    $edit->addControl($setting['label'], "LIST");
                    $edit->selectSQL[] = $setting['options'];
                    break;

                default: $edit->addControl($setting['label'], "TEXT", 'size="60"');
            }
        }

        $edit->SQL = array(array('id' => 1) + $data);

        if ($is_readonly) {
            $edit->addButton("Отменить", "load('$app')");
        } else {
            $edit->readOnly = true;
            $edit->addButtonCustom("<input type=\"button\" class=\"button btn btn-info\"
                                           value=\"Редактировать\" onclick=\"load('{$app}&edit=1')\">");
        }

        $edit->firstColWidth = "300px";
        $edit->back = $app . "&tab_{$this->resId}=1";
        $edit->save("xajax_saveSettings(xajax.getFormValues(this.id))");


        ob_start();
        $edit->showTable();
        return ob_get_clean();
    }




    /**
     * Получение всех настроек
     * @return array
     */
    private function getSettings() {

        $this->syncSettings();

        $core_settings = $this->db->fetchAll("
            SELECT code,
                   `value`,
                   system_name
            FROM core_settings
            WHERE visible = 'Y'
              AND is_personal_sw = 'Y'
            ORDER BY seq
        ");


        $user_settings = $this->db->fetchPairs("
            SELECT code,
                   `value`
            FROM mod_profile_user_settings
            WHERE user_id = ?
        ", $this->user_id);

        $settings = array();
        foreach ($core_settings as $core_setting) {

            $setting          = array();
            $setting['label'] = $core_setting['system_name'] ? $core_setting['system_name'] : $core_setting['code'];
            $setting['name']  = $core_setting['code'];
            $setting['value'] = $user_settings[$core_setting['code']];

            if (strpos($core_setting['value'], '|') !== false) {
                if (strpos($core_setting['value'], '*') === 0) {
                    $setting['type'] = 'checkbox';
                    $explode_value   = explode('|', mb_substr($core_setting['value'], 1, 10000, 'utf8'));
                } else {
                    $setting['type']  = 'list';
                    //$setting['value'] = $user_settings[$core_setting['code']];
                    $explode_value    = explode('|', $core_setting['value']);
                }

                $options       = array();
                foreach ($explode_value as $val) {
                    if (strpos($val, ':') !== false) {
                        $temp = explode(':', $val);
                        $options[$temp[0]] = $temp[1];
                    } else {
                        $options[$val] = $val;
                    }
                }

                $setting['options'] = $options;
            } else {
                $setting['type'] = 'text';
            }

            $settings[] = $setting;
        }

        return $settings;
    }


    /**
     * Синхронизация данных между таблицами core_settings и mod_settings
     */
    private function syncSettings() {

        $core_settings = $this->db->fetchAll("
            SELECT code,
                   `value`,
                   system_name
            FROM core_settings
            WHERE visible = 'Y'
              AND is_personal_sw = 'Y'
        ");

        $user_settings = $this->db->fetchCol("
            SELECT code
            FROM mod_profile_user_settings
            WHERE user_id = ?
        ", $this->user_id);

        foreach ($core_settings as $core_setting) {
            if (array_search($core_setting['code'], $user_settings) === false) {
                if (strpos($core_setting['value'], '|') !== false) {
                    if (strpos($core_setting['value'], '*') === 0) {
                        $core_setting['value'] = mb_substr($core_setting['value'], 1, null, 'utf8');
                    }
                    $explode_value = explode('|', $core_setting['value']);
                    $val           = current($explode_value);
                    if (strpos($val, ':') !== false) {
                        $temp = explode(':', $val);
                        $value = $temp[0];
                    } else {
                        $value = $val;
                    }

                } else {
                    $value = $core_setting['value'];
                }

                $this->db->insert("mod_profile_user_settings",
                    array(
                        'code'    => $core_setting['code'],
                        'value'   => $value,
                        'user_id' => $this->user_id
                    )
                );
            }
        }

        foreach ($user_settings as $code) {
            $isset_setting = false;
            foreach ($core_settings as $core_setting) {
                if ($code == $core_setting['code']) {
                    $isset_setting  = true;
                    break;
                }
            }

            if ( ! $isset_setting) {
                $where = array();
                $where[] = $this->db->quoteInto('code = ?',    $code);
                $where[] = $this->db->quoteInto('user_id = ?', $this->user_id);
                $this->db->delete('mod_profile_user_settings', $where);
            }
        }
    }
}