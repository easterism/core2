<?php


/**
 * Class ProfileMessages
 */
class ProfileMessages extends Zend_Db_Table_Abstract {

    /**
     * Название таблицы
     * @var string
     */
    protected $_name = 'mod_profile_messages';


    /**
     * Добавление сообщения в базу
     *
     * @param array $mail
     *      Данные письма
     * @return bool|int
     *      Идентификатор добавленного письма,
     *      либо false в случае ошибки
     */
    public function add(array $mail) {

        $mail_id = $this->insert($mail);

        return $mail_id
            ? $mail_id
            : false;
    }
}