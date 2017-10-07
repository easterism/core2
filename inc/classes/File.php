<?php

/**
 * Created by PhpStorm.
 * User: StepovichPE
 * Date: 04.03.2016
 * Time: 0:58
 */
namespace Store;

require_once(DOC_ROOT . "core2/inc/classes/Image.php");

class File extends \Common {
    private $that;
    private $content;
    private $resource;
    private $imgWidth = 80;
    private $imgHeight = 80;

    /**
     * File constructor.
     */
    public function __construct($res) {
        parent::__construct();
        $this->resource = $res;
    }

    /**
     *
     */
    public function dispatch() {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        header("Content-Transfer-Encoding: binary");
        echo $this->content;
    }


    /**
     * Обработка запроса на получение содержимого файла из хранилища
     * @param string $table
     * @param int    $id - id файла
     * @throws \Exception
     */
    public function handleFile($table, $id) {
        $quote_table       = $this->db->quoteIdentifier($table);
        $quote_table_files = $this->db->quoteIdentifier($table.'_files');
        $res2 = $this->db->fetchRow("SELECT `content`,
                                            `refid`,
                                            `filename`,
                                            `filesize`,
                                            `hash`,
                                            `type`,
                                            `fieldid`
                                       FROM {$quote_table_files}
                                      WHERE id = ?", $id);
        if (!$res2) {
            throw new \Exception(404);
        }
        if (!$this->checkAcl($this->resource, 'read_all')) {
            if (!$this->checkAcl($this->resource, 'read_owner')) {
                throw new \Exception(911);
            } else {
                $res = $this->db->fetchRow("SELECT * FROM {$quote_table} WHERE `id` = ? LIMIT 1", $res2['refid']);
                if (!$res || !isset($res['author']) || $this->auth->NAME !== $res['author']) {
                    throw new \Exception(911);
                }
            }
        }

        $image = new \Image();
        if ($image->isImage($res2['type'])) {
            if (!$image->checkGD()) {
                throw new \Exception("GD not installed", 500);
            }
        } else {
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
        }
        header("Content-Disposition: filename=\"{$res2['filename']}\"");
        header("Content-Type: " . $res2['type']);
        header('Content-Length: ' . $res2['filesize']);
        $this->content = $res2['content'];
    }

    /**
     * тамбнэйлы файлов
     * @param string $table
     * @param int    $id
     * @throws \Exception
     */
    public function handleThumb($table, $id) {
        $quote_table       = $this->db->quoteIdentifier($table);
        $quote_table_files = $this->db->quoteIdentifier($table.'_files');
        $res2 = $this->db->fetchRow("SELECT * FROM {$quote_table_files} WHERE id = ?", $id);
        if (!$res2) {
            throw new \Exception(404);
        }
        if (!$this->checkAcl($this->resource, 'read_all')) {
            if (!$this->checkAcl($this->resource, 'read_owner')) {
                throw new \Exception(911);
            } else {
                $res = $this->db->fetchRow("SELECT * FROM {$quote_table} WHERE `id` = ? LIMIT 1", $res2['refid']);
                if (!$res || !isset($res['author']) || $this->auth->NAME !== $res['author']) {
                    throw new \Exception(911);
                }
            }
        }
        header("Content-type: {$res2['type']}");
        header("Content-Disposition: filename=\"{$res2['filename']}\"");
        if (isset($res2['thumb'])) {
            $this->content = $res2['thumb'];
        } else {
            $image = new \Image();
            $image->outStringResized($res2['content'], $res2['type'], $this->imgWidth, $this->imgHeight);
        }
    }


    /**
     * @param string $thumbName
     * @throws \Exception
     * @throws \Zend_Exception
     */
    public function handleFileTemp($thumbName) {
        $config     = \Zend_Registry::get('config');
        $sid        = \Zend_Registry::get('session')->getId();
        $upload_dir = $config->temp . '/' . $sid;
        $fname      = $upload_dir . "/thumbnail/" . $thumbName;
        if (!is_file($fname)) {
            throw new \Exception(404);
        }
        if (phpversion('tidy') < 5.3) {
            $temp = explode('.', $_GET['tfile']);
            if (empty($temp[1]) || $temp[1] == 'jpg') {
                $temp[1] = 'jpeg';
            }
            $mime = 'image/' . $temp[1];
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fname);
        }
        header("Content-Type: $mime");
        header('Content-Length: ' . filesize($fname));
        ob_clean();
        flush();
        readfile($fname);
    }

    /**
     * Получаем список файлов, связанных с оъектом
     *
     * @param $table - название таблицы объекта
     * @param $id
     * @param $filed
     * @return array
     * @throws \Exception
     */
    public function handleFileList($table, $id, $filed) {

        $quote_table_files = $this->db->quoteIdentifier($table . '_files');
        $SQL = "SELECT * FROM {$quote_table_files} WHERE refid = ?";
        $arr = array($id);
        if ($filed) {
            $SQL .= ' AND fieldid = ?';
            $arr[] = $filed;
        }
        $res = $this->db->fetchAll($SQL, $arr);

        list($module, $action) = explode("_", $this->resource);

        $image = new \Image();

        $base_urn = $action == 'index'
            ? "index.php?module=$module&filehandler=$table"
            : "index.php?module=$module&action=$action&filehandler=$table";

        foreach ($res as $key => $value) {
            $type2 = explode("/", $value['type']);
            $type2 = $type2[1];

            $file = new \stdClass();
            $file->name 		= $value['filename'];
            $file->size 		= (int)$value['filesize'];
            if ($image->isImage($value['type'])) {
                if (!$image->checkGD()) {
                    throw new \Exception("GD not installed", 500);
                }
                $file->thumbnail_url = "{$base_urn}&thumbid=" . $value['id'];
            }
            else {
                //$file->thumbnail_url = THEME . "/filetypes/pdf.gif";
            }
            $file->url 			= "{$base_urn}&filehandler=$table&fileid=" . $value['id'];
            $file->delete_url 	= "{$base_urn}&filehandler=$table&fileid=" . rawurlencode($value['filename']);
            $file->delete_type 	= 'DELETE';
            $file->delete_id 	= $value['id'];
            $file->type 		= $value['type'];
            $file->hash 		= $value['hash'];
            $file->id_hash 		= $value['id'] . $value['hash'] . '.' . ($type2 == 'jpeg' ? 'jpg' : $type2);
            $res[$key] 			= $file;
        }
        return $res;
    }

    /**
     * @param $size
     */
    public function setThumbSize($size) {
        $size = explode("x", $size);
        $width = (int)$size[0];
        $height = (int)$size[1];
        if ($width) $this->imgWidth = $width;
        if ($height) $this->imgHeight = $height;
    }
}