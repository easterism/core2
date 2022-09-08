<?php

/**
 * Class ModProfileApi
 */
class ModProfileApi extends CommonApi {

	public function __construct() {
		parent::__construct('profile');

	}

    /**
     * Получение сообщения о неверном email
     *
     * @param object $dataUser
     */
    private function getWrongEmail($dataUser)
    {
        //Проверка сообщений пользователя
        if (isset($dataUser->is_email_wrong) && $dataUser->is_email_wrong == 'Y') {
            $label = $this->getModuleName('profile');
            return '<div style="color:#ff4500;">На ваш email не отправляются сообщения.
				Установите правильный email в модуле <a href="#module=profile"><b>' . $label[0] . '</b></a>.
				После проверки нового email это сообщение пропадет.</div>';
        }
    }

    /**
     * Получение сообщения о смене пароля
     *
     * @param object $dataUser
     */
    private function getChangePass($dataUser)
    {
        // Проверка для сообщения о смене пароля
        if (!$this->auth->LDAP && isset($dataUser->is_pass_changed) && $dataUser->is_pass_changed == 'N') {
            $label = $this->getModuleName('profile');
            return '<div style="color:red;">Убедительная просьба: измените пароль для входа в систему.
				Это можно сделать в модуле <a href="#module=profile"><b>' . $label[0] . '</b></a></div>';
        }
    }


    /**
     * Получение списка не прочитанных сообщений
     *
     * @param int $user_id
     */
    public function getUnreadMsg($user_id = 0)
    {
        if (!$user_id) $user_id = $this->auth->ID;
        return $this->db->fetchAll("
                SELECT id
                FROM mod_profile_messages
                WHERE location = 'inbox'
                  AND user_id = ?
                  AND is_read = 'N'
            ", $user_id);
    }

    /**
     * Получение сообщений для профиля. Отображается на главной
     *
     * @return string
     */
    public function getProfileMsg() {
        $dataUser = $this->modAdmin->dataUsers->find($this->auth->ID)->current();
        $out      = $this->getWrongEmail($dataUser) . $this->getChangePass($dataUser);
        $mails    = $this->getUnreadMsg();
        if (!empty($mails)) {

            $n = sizeof($mails);

            $src = $this->getModuleSrc('profile');
            if ($n == 1) {
                $msg = "непрочитанное сообщение";
            } elseif ($n > 1 && $n < 5) {
                $msg = "непрочитанных сообщения";
            } else {
                $msg = "непрочитанных сообщений";
            }
            $out .= "<div style=\"color:green;\"><img src=\"{$src}/html/img/email-green.gif\"> У вас {$n} <a href=\"#module=profile&action=messages\">$msg</a></div>";
        }
        return $out;
    }
}