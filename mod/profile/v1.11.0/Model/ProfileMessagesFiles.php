<?php



/**
 * Class ProfileMessagesFiles
 */
class ProfileMessagesFiles extends Zend_Db_Table_Abstract {

    /**
     * Список файлов для отправки с сообщением
     * @var array
     */
    protected $attach_files = array();

    /**
     * Название таблицы
     * @var string
     */
    protected $_name = 'mod_profile_messages_files';


    /**
     * Добавление к сообщению файлов
     *
     * @param string $content
     * @param string $name
     * @param string $mimetype
     * @param int $size
     *
     * @return $this
     */
    public function add ($content, $name, $mimetype, $size) {

        $this->attach_files[] = array(
            'content'  => $content,
            'name'     => $name,
            'mimetype' => $mimetype,
            'size'     => $size
        );

        return $this;
    }


    /**
     * Очистка добавленных файлов
     *
     * @return void
     */
    public function clear () {

        $this->attach_files = array();
    }


    /**
     * Добавление прикрепляемых к письму файлов
     *
     * @param int $message_id
     *      Идентификатор письма
     *
     * @return bool
     *      Добавлены прикрепляемые файлы к письму или нет
     */
    public function attached ($message_id) {

        if (empty($this->attach_files)) return true;

        foreach ($this->attach_files as $file) {
            $insert_file = array(
                'refid'    => $message_id,
                'filename' => $file['name'],
                'size'     => $file['size'],
                'type'     => $file['mimetype'],
                'content'  => $file['content'],
                'hash'     => md5($file['content']),
                'fieldid'  => $file['fieldid'] ? $file['fieldid'] : 0,
            );

            $is_add = $this->insert($insert_file);

            if ( ! $is_add) {
                return false;
            }
        }

        $this->clear();

        return true;
    }
} 