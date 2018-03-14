<?php
namespace Core2\Store;

use Zend\Session\Container as SessionContainer;

require_once DOC_ROOT . "core2/inc/classes/Db.php";
require_once DOC_ROOT . "core2/inc/classes/Image.php";


/**
 * Class FileUploader
 * @package Store
 */
class FileUploader extends \Core2\Db {

    private $options;

    /**
     * FileUploader constructor.
     * @param array $options
     */
    function __construct($options = null) {

        parent::__construct();

        $config     = \Zend_Registry::get('config');
        $sid        = SessionContainer::getDefaultManager()->getId();
        $upload_dir = $config->temp . '/' . $sid;

        if ( ! is_dir($upload_dir . "/thumbnail")) {
            $old = umask(0);
            if ( ! is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            mkdir($upload_dir . "/thumbnail", 0777);
            umask($old);
        }

        $upload_dir   .= "/";
        $this->options = [
            'script_url'              => $_SERVER['PHP_SELF'],
            'upload_dir'              => $upload_dir,
            'upload_dir_thumb'        => $upload_dir . "thumbnail",
            'upload_url'              => 'index.php?module=admin&action=handler&tfile=',
            'thumb_url'               => 'index.php?module=admin&action=handler&thumbid=',
            'upload_id'               => 'index.php?module=admin&action=handler&fileid=',
            'thumb_id'                => 'index.php?module=admin&action=handler&thumb=1&fileid=',
            'param_name'              => 'files',
            // The php.ini settings upload_max_filesize and post_max_size
            // take precedence over the following max_file_size setting:
            'max_file_size'           => null,
            'min_file_size'           => 1,
            'accept_file_types'       => '/.+$/i',
            'max_number_of_files'     => null,
            'discard_aborted_uploads' => true,
            'image_versions'          => [
                // Uncomment the following version to restrict the size of
                // uploaded images. You can also add additional versions with
                // their own upload directories:
                /*
                'large' => array(
                    'upload_dir' => dirname(__FILE__).'/files/',
                    'upload_url' => dirname($_SERVER['PHP_SELF']).'/files/',
                    'max_width' => 1920,
                    'max_height' => 1200
                ),
                */
                'thumbnail' => [
                    'upload_dir' => $upload_dir . "thumbnail/",
                    'upload_url' => 'index.php?module=admin&action=handler&tfile=',
                    'max_width'  => 80,
                    'max_height' => 80
                ]
            ]
        ];


        if ($options) {
            $this->options = array_replace_recursive($this->options, $options);
        }
    }


    private function get_file_object($file_name) {
        $file_path = $this->options['upload_dir'].$file_name;
        if (is_file($file_path) && $file_name[0] !== '.') {
            $file = new \stdClass();
            $file->name = $file_name;
            $file->size = filesize($file_path);
            $file->url = $this->options['upload_url'].rawurlencode($file->name);
            foreach($this->options['image_versions'] as $version => $options) {
                if (is_file($options['upload_dir'].$file_name)) {
                    $file->{$version.'_url'} = $options['upload_url']
                        .rawurlencode($file->name);
                }
            }
            $file->delete_url = $this->options['upload_id'] . rawurlencode($file->name);
            $file->delete_type = 'DELETE';
            return $file;
        }
        return null;
    }

    private function get_file_objects() {
        return array_values(array_filter(array_map(
            array($this, 'get_file_object'),
            scandir($this->options['upload_dir'])
        )));
    }


    private function get_db_objects($tbl, $refid, $fieldid = '') {

        //echo "<PRE>";print_r($this->options);echo "</PRE>";die;
        $SQL = "SELECT * FROM `{$tbl}_files` WHERE refid=?";
        $arr = array($refid);
        if ($fieldid) {
            $SQL .= ' AND fieldid=?';
            $arr[] = $fieldid;
        }
        $res = $this->db->fetchAll($SQL, $arr);

        $Image = new Image();
        foreach ($res as $key => $value) {
            $type2 = explode("/", $value['type']);
            $type2 = $type2[1];

            $file = new \stdClass();
            $file->name 		= $value['filename'];
            $file->size 		= (int)$value['filesize'];
            if (preg_match(\Image::FORMAT_PICTURE, $type2)) {
                $file->thumbnail_url = $this->options['thumb_url'] . $value['id'] . '&t=' . $tbl;
            } else {
                //$file->thumbnail_url = THEME . "/filetypes/pdf.gif";
            }
            $file->url 			= $this->options['upload_id'] . $value['id'] . '&t=' . $tbl;
            $file->delete_url 	= $this->options['upload_id'] . rawurlencode($value['filename']);
            $file->delete_type 	= 'DELETE';
            $file->delete_id 	= $value['id'];
            $file->type 		= $value['type'];
            $file->hash 		= $value['hash'];
            $file->id_hash 		= $value['id'] . $value['hash'] . '.' . ($type2 == 'jpeg' ? 'jpg' : $type2);
            $res[$key] 			= $file;
        }
        return $res;
    }


    private function create_scaled_image($file_name, $options) {
        $file_path = $this->options['upload_dir'].$file_name;
        $new_file_path = $options['upload_dir'].$file_name;
        list($img_width, $img_height) = @getimagesize($file_path);
        if (!$img_width || !$img_height) {
            return false;
        }
        $scale = min(
            $options['max_width'] / $img_width,
            $options['max_height'] / $img_height
        );
        if ($scale > 1) {
            $scale = 1;
        }
        $new_width = $img_width * $scale;
        $new_height = $img_height * $scale;
        $new_img = @imagecreatetruecolor($new_width, $new_height);
        switch (strtolower(substr(strrchr($file_name, '.'), 1))) {
            case 'jpg':
            case 'jpeg':
                $src_img = @imagecreatefromjpeg($file_path);
                $write_image = 'imagejpeg';
                break;
            case 'gif':
                $src_img = @imagecreatefromgif($file_path);
                $write_image = 'imagegif';
                break;
            case 'png':
                $src_img = @imagecreatefrompng($file_path);
                $write_image = 'imagepng';
                break;
            default:
                $src_img = $image_method = null;
        }
        $success = $src_img && @imagecopyresampled(
                $new_img,
                $src_img,
                0, 0, 0, 0,
                $new_width,
                $new_height,
                $img_width,
                $img_height
            ) && $write_image($new_img, $new_file_path);
        // Free up memory (imagedestroy does not delete files):
        @imagedestroy($src_img);
        @imagedestroy($new_img);
        return $success;
    }

    private function has_error($uploaded_file, $file, $error) {
        if ($error) {
            return $error;
        }
        if (!preg_match($this->options['accept_file_types'], $file->name)) {
            return 'acceptFileTypes';
        }
        if ($uploaded_file && is_uploaded_file($uploaded_file)) {
            $file_size = filesize($uploaded_file);
        } else {
            $file_size = $_SERVER['CONTENT_LENGTH'];
        }
        if ($this->options['max_file_size'] && (
                $file_size > $this->options['max_file_size'] ||
                $file->size > $this->options['max_file_size'])
        ) {
            return 'maxFileSize';
        }
        if ($this->options['min_file_size'] &&
            $file_size < $this->options['min_file_size']) {
            return 'minFileSize';
        }
        if (is_int($this->options['max_number_of_files']) && (
                count($this->get_file_objects()) >= $this->options['max_number_of_files'])
        ) {
            return 'maxNumberOfFiles';
        }
        return $error;
    }

    private function handle_file_upload($uploaded_file, $name, $size, $type, $error) {
        $file = new \stdClass();
        // Remove path information and dots around the filename, to prevent uploading
        // into different directories or replacing hidden system files.
        // Also remove control characters and spaces (\x00..\x20) around the filename:
        $explode_name = explode('/',stripslashes($name));
        $file->name = trim(end($explode_name), ".\x00..\x20");
        $file->size = intval($size);
        $file->type = $type;
        $error = $this->has_error($uploaded_file, $file, $error);
        if (!$error && $file->name) {
            $file_path = $this->options['upload_dir'] . $file->name;
            $append_file = is_file($file_path) && $file->size > filesize($file_path);
            clearstatcache();
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path,
                        fopen($uploaded_file, 'r'),
                        FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path,
                    fopen('php://input', 'r'),
                    $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = filesize($file_path);
            if ($file_size === $file->size) {
                $file->url = "index.php?module=admin&filehandler=temp&tfile=" . rawurlencode($file->name);
                foreach($this->options['image_versions'] as $version => $options) {
                    if ($this->create_scaled_image($file->name, $options)) {
                        $file->{$version.'_url'} = "index.php?module=admin&filehandler=temp&tfile="
                            .rawurlencode($file->name);
                    }
                }
            } else if ($this->options['discard_aborted_uploads']) {
                unlink($file_path);
                $file->error = 'abort';
            }
            $file->size = $file_size;
            $file->delete_url = 'index.php?module=admin&action=upload&file=' . rawurlencode($file->name);
            $file->delete_type = 'DELETE';
            $file->delete_service = $file->name . '###' . $file->size . '###' . $file->type;
        } else {
            $file->error = $error;
        }
        return $file;
    }

    public function get() {
        $info = array();
        if (!empty($_GET['refid']) && !empty($_GET['tbl'])) {
            $tbl = trim(strip_tags($_GET['tbl']));
            $info = $this->get_db_objects($tbl, $_GET['refid'], $_GET['f']);
        } else {
            $file_name = isset($_GET['file']) ? basename(stripslashes($_REQUEST['file'])) : null;
            if ($file_name) {
                $info = $this->get_file_object($file_name);
            } else {
                //$info = $this->get_file_objects();
            }
        }
        header('Content-type: application/json');
        echo json_encode(array('files' => $info));
    }

    public function post() {
        $upload = isset($_FILES[$this->options['param_name']]) ?
            $_FILES[$this->options['param_name']] : array(
                'tmp_name' => null,
                'name' => null,
                'size' => null,
                'type' => null,
                'error' => null
            );
        $info = array('files' => array());
        if (is_array($upload['tmp_name'])) {
            foreach ($upload['tmp_name'] as $index => $value) {
                $info['files'][] = $this->handle_file_upload(
                    $upload['tmp_name'][$index],
                    isset($_SERVER['HTTP_X_FILE_NAME']) ?
                        $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'][$index],
                    isset($_SERVER['HTTP_X_FILE_SIZE']) ?
                        $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'][$index],
                    isset($_SERVER['HTTP_X_FILE_TYPE']) ?
                        $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'][$index],
                    $upload['error'][$index]
                );
            }
        } else {
            $info['files'][] = $this->handle_file_upload(
                $upload['tmp_name'],
                isset($_SERVER['HTTP_X_FILE_NAME']) ?
                    $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'],
                isset($_SERVER['HTTP_X_FILE_SIZE']) ?
                    $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'],
                isset($_SERVER['HTTP_X_FILE_TYPE']) ?
                    $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'],
                $upload['error']
            );
        }
        header('Vary: Accept');
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-type: application/json');
        } else {
            header('Content-type: text/plain');
        }
        echo json_encode($info);
    }

    public function delete() {
        $file_name = isset($_REQUEST['file']) ?
            basename(stripslashes($_REQUEST['file'])) : null;
        $file_path = $this->options['upload_dir'].$file_name;
        $success = is_file($file_path) && $file_name[0] !== '.' && unlink($file_path);
        if ($success) {
            foreach($this->options['image_versions'] as $version => $options) {
                $file = $options['upload_dir'].$file_name;
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        header('Content-type: application/json');
        echo json_encode($success);
    }
}