<?php


/**
 * Class ProfileMessagesSettings
 */
class ProfileMessagesSettings extends Zend_Db_Table_Abstract {

    /**
     * Массив настроек
     * @var array
     */
    private $settings = array();

    /**
     * Название таблицы
     * @var string
     */
    protected $_name = 'mod_profile_messages_settings';

    /**
     * Получение настроек
     *
     * @param string $name
     * @return array|string
     */
    public function get($user_id, $name = '') {

        if ( ! isset($this->settings[$user_id]) || empty($this->settings[$user_id])) {
            $tmp_settings = $this->fetchAll($this->select()->where('user_id = ?', $user_id))->toArray();
            $this->settings[$user_id] = array();
            foreach ($tmp_settings as $item) {
                $this->settings[$user_id][$item['name']] = $item['value'];
            }
        }

        if (isset($this->settings[$user_id][$name])) {
            return $this->settings[$user_id][$name];
        }

        return $name != ''
            ? $this->settings[$user_id][$name]
            : $this->settings[$user_id];
    }


    /**
     * Получение одного значения из таблицы
     *
     * @param string $field
     * @param string $expr
     * @param array $var
     * @return mixed
     */
    public function fetchOne($field, $expr, $var = array())  {

        $sel = $this->select()->where($expr, $var);
        return @$this->fetchRow($sel)->$field;
    }


    /**
     * Проверка на существование значения
     *
     * @param string $name
     * @return mixed
     */
    public function exists($user_id, $name) {

        $sel = $this->select()->where('name = ?', $name)->where('user_id = ?', $user_id)->limit(1);
        return $this->fetchRow($sel);
    }
} 