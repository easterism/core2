<?php

/**
 * Created by PhpStorm.
 * User: StepovichPE
 * Date: 04.03.2016
 * Time: 0:58
 */
namespace Core2\Store;

use Laminas\Session\Container as SessionContainer;
use Aws\S3\S3Client;

require_once(__DIR__ . "/Common.php");
require_once(__DIR__ . "/Image.php");

class File extends \Common {
    private $content;
    private $resource;
    private $imgWidth = 80;
    private $imgHeight = 80;
    private $data = [];

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

    private function getFileData($table, $id) {
        $quote_table       = $this->db->quoteIdentifier($table);
        $quote_table_files = $this->db->quoteIdentifier($table.'_files');
        $res2 = $this->db->fetchRow("SELECT `refid`,
                                            `filename`,
                                            `filesize`,
                                            `hash`,
                                            `type`,
                                            `fieldid`,
                                            `storage`
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
        $this->data = $res2;
    }

    public function getData() {
        return $this->data;
    }


    /**
     * Обработка запроса на получение содержимого файла из хранилища
     * @param string $table
     * @param int    $id - id файла
     * @throws \Exception
     */
    public function handleFile($table, $id) {
        $this->getFileData($table, $id);
        $res2 = $this->data;

        $image = new Image();
        if ($image->isImage($res2['type'])) {
            if (!$image->checkGD()) {
                throw new \Exception("GD not installed", 500);
            }
        } else {
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
        }

        $filename_encode = rawurlencode($res2['filename']);

        header("Content-Disposition: filename=\"{$res2['filename']}\"; filename*=utf-8''{$filename_encode}");
        header("Content-Type: " . $res2['type']);
        header('Content-Length: ' . $res2['filesize']);

        $content = $this->getContent($table, $id);
        $this->content = $content;
    }

    public function getContent($table, $id)
    {
        $quote_table_files = $this->db->quoteIdentifier($table . '_files');
        $content = $this->db->fetchRow("SELECT `content`, `storage`, `hash` FROM {$quote_table_files} WHERE id = ?", $id);
        //$cacheed = $this->config->temp . '/' . $content['hash'];
        if ($content['storage']) {
            $s      = explode("|", $content['storage']);
            if ($s[0] === 'S3') {
                // Check S3 Storage
                if ($s3 = $this->config->s3) {
                    try {
                        $client = new S3Client([
                            'region' => 'us-west-2',
                            'version' => 'latest',
                            'endpoint' => $s3->host,
                            'credentials' => [
                                'key' => $s3->access_key,
                                'secret' => $s3->secret_key
                            ],
                            // Set the S3 class to use objects.dreamhost.com/bucket
                            // instead of bucket.objects.dreamhost.com
                            'use_path_style_endpoint' => true
                        ]);
                        //$listResponse = $client->listBuckets();
                        $object  = $client->getObject(['Bucket' => $s[1], 'Key' => "{$s[2]}|{$s[3]}|{$s[4]}"]);
                        $content = $object['Body']->getContents();

                        return $content;

                    } catch (\Exception $e) {
                        throw new \Exception($e->getMessage());
                        //TODO Log me!
                    }
                } else {
                    throw new \Exception("S3 settings not found", 404);
                }
            }
        }
        return $content['content'];
    }

    /**
     * тамбнэйлы файлов
     * @param string $table
     * @param int    $id
     * @throws \Exception
     */
    public function handleThumb($table, $id) {
        $this->getFileData($table, $id);
        $res2 = $this->data;

        header("Content-type: {$res2['type']}");
        header("Content-Disposition: filename=\"{$res2['filename']}\"");


        if ( ! empty($res2['hash'])) {
            $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

            header("Etag: {$res2['hash']}");
            header('Cache-Control: public');

            //check if page has changed. If not, send 304 and exit
            if ($etagHeader == $res2['hash']) {
                header("HTTP/1.1 304 Not Modified");
                return '';
            }
        }

        $quote_table_files = $this->db->quoteIdentifier($table . '_files');
        $thumb = $this->db->fetchOne("SELECT `thumb` FROM {$quote_table_files} WHERE id = ?", $id);
        //Если задан размер тамбнейла или если тамбнейла нет в базе
        if (!empty($_GET['size']) || !$thumb) {
            $content = $this->getContent($table, $id);
            ob_start();
            $image = new Image();
            $image->outStringResized($content, $res2['type'], $this->imgWidth, $this->imgHeight);
            $this->content = ob_get_clean();

        } else {
            $this->content = $thumb;
        }
    }


    /**
     * @param string $thumbName
     * @throws \Exception
     * @throws \Zend_Exception
     */
    public function handleFileTemp($thumbName) {
        $config     = \Zend_Registry::get('config');
        $sid        = SessionContainer::getDefaultManager()->getId();
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

        $this->content = file_get_contents($fname);
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
        $SQL = "SELECT `id`, 
                        `refid`,
                        `filename`,
                        `filesize`,
                        `hash`,
                        `type`,
                        `fieldid`,
                        `storage` 
            FROM {$quote_table_files} 
            WHERE refid = ?";
        $arr = array($id);
        if ($filed) {
            $SQL .= ' AND fieldid = ?';
            $arr[] = $filed;
        }
        $res = $this->db->fetchAll($SQL, $arr);

        list($module, $action) = explode("_", $this->resource);

        $image = new Image();

        $base_urn = $action == 'index'
            ? "index.php?module=$module"
            : "index.php?module=$module&action=$action";

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
                $file->thumbnail_url = "{$base_urn}&filehandler=$table&thumbid=" . $value['id'];
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