<?php
/**
 * Управляет файлами модуля
 * User: StepovichPE
 * Date: 04.03.2016
 * Time: 00:26
 */
interface File {

    /**
     * Перехват запросов на отображение файла
     * @param $context - контекст отображения (fileid, thumbid, tfile)
     * @param $table - имя таблицы, с которой связан файл
     * @param $id - id файла
     *
     * @return bool
     */
    public function action_filehandler($context, $table, $id);
}