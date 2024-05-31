<?php
namespace Core2\Store;
/**
 * Class Image
 *
 * @author Easter
 */



/**
 * Class Image
 */
class Image {

    /**
     * Path to file .ttf
     *
     * @var string
     */
    public $font = 'plugins/font/Monotype.ttf';
    const FORMAT_PICTURE = '/^(gif|png|jpe?g)$/i';
    const MIME = '/^image\/(gif|png|jpe?g)$/i';

    public function __construct() { }

    /**
     * Check directory for file
     *
     * @param string $path path to file
     * @return bool
     */
    public function checkDir($path) {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        if (!is_dir($dirname)) {
            $dir = mkdir($dirname, 0777);
//			chmod($dirname, 0777);
            if (!$dir) {
                trigger_error(__CLASS__ . "::" . __FUNCTION__ . " Error of creation Dir: " . $dirname);
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * Validate format of file
     *
     * @param string $filename path to file
     * @return mixed boll or array data
     */
    public function checkTypeImage($filename) {
        $type = getimagesize($filename);
        $file = array();
        if ($type) {
            $file['width'] = $type[0];
            $file['height'] = $type[1];
            $tmp = explode("/", $type['mime']);
            $file['type'] = $tmp[1];
            if (!preg_match(Image::FORMAT_PICTURE, $file['type'])) {
                return false;
            } else {
                return $file;
            }
        } else {
            return false;
        }
    }


    /**
     * @param $source
     * @param $target
     *
     * @return bool
     */
    private function imageChecks($source, $target) {
        if (Image::checkDir($target)) {
            if ($source && Image::checkTypeImage($source)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Upload file image
     *
     * @param string $source path to upload file
     * @param string $target path to new file
     * @return bool
     */
    public function uploadImage($source, $target) {
        if ($this->imageChecks($source, $target)) {
            return move_uploaded_file($source, $target);
        }
        return false;
    }

    /**
     * Check GD or GD2 library
     *
     * @return bool
     */
    public function checkGD() {
        $checkGD = (extension_loaded("gd") || extension_loaded("gd2"));
        return $checkGD;
    }

    private function getImageMime($filename) {
        $data = getimagesize($filename);
        return $data['mime'];
    }

    private function getImageType($filename) {
        $data = getimagesize($filename);
        return $this->mimeToType($data['mime']);
    }

    private function mimeToType($mime) {
        $tmp = explode("/", $mime);
        if (!empty($tmp[1])) $mime = $tmp[1];
        return strtolower($mime);
    }

    public function imageCreateFromFile($filename, $type = '') {
        if (!Image::checkGD()) {
            return false;
        }

        if (!$type) {
            $type = $this->getImageType($filename);
        }

        $img = '';
        switch ($type) {
            case 'jpeg':
            case 'jpg':
                $img = imagecreatefromjpeg($filename);
                break;

            case 'png':
                $img = imagecreatefrompng($filename);
                break;

            case 'gif':
                $img = imagecreatefromgif($filename);
                break;
        }
        if (!$img) {
            $img = imagecreatefromstring(file_get_contents($filename));
        }
        return $img;
    }

    /**
     * Save image resource to $savepath
     * @param $img - resource
     * @param String $savepath
     * @param string $type - image type
     * @param int $quality - quality for jpeg
     * @return bool
     */
    private function saveImage($img, $savepath, $type, $quality = 100) {
        $type = $this->mimeToType($type);

        switch ($type) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($img, $savepath, $quality);
                break;

            case 'png':
                $bg_png = imagecolorallocate($img, 0, 0, 0);
                imagecolortransparent($img, $bg_png);
                imagepng($img, $savepath);
                break;

            case 'gif':
                imagegif($img, $savepath);
                break;
        }
        imagedestroy($img);
        return true;
    }

    /**
     * Resize image
     *
     * @param string  $filename 	path to image file
     * @param string  $savepath 	path to new image file
     * @param string  $type 		type (format) image file
     * @param mixed $max_width 	max width of image file
     * @param mixed $max_height 	min width of image file
     * @param integer $quality 		quality of image (jpg)
     * @param bool $crop 		is crop
     */
    public function changeSizeImage($filename, $savepath, $type = '', $max_width = '', $max_height = '', $quality = 100, $crop = false) {
        if (!Image::checkGD()) {
            return false;
        }
        $filename = str_replace("\\", "/", $filename);
        $savepath = str_replace("\\", "/", $savepath);
        /*Tools::logToFile('xxx', 'filename='.$filename);
        Tools::logToFile('xxx', 'savepath='.$savepath);
        Tools::logToFile('xxx', 'type='.$type);
        Tools::logToFile('xxx', 'max_width='.$max_width);
        Tools::logToFile('xxx', 'max_height='.$max_height);*/

        if (!$max_width && !$max_height) {
            throw new \Exception("Нулевой размер картинки!");
        }
        if (file_exists($savepath) && is_file($savepath) && $savepath !== '.') {
            unlink($savepath);
        }
        $data = getimagesize($filename);
        if (!$type) {
            $type = $data['mime'];
        }
        $type = $this->mimeToType($type);

        $w = $data[0];
        $h = $data[1];
        $img = $this->imageCreateFromFile($filename, $type);
        if (!$img) throw new \Exception("Не могу создать картинку!");

        // resize
        if ($crop) {
            if (!$max_width || !$max_height) throw new \Exception("Размер картинки задан не верно!");
            if ($w < $max_width or $h < $max_height) throw new \Exception("Картинка слишком маленькая!");
            $ratio = max($max_width / $w, $max_height / $h);
            $h = $max_height / $ratio;
            $x = ($w - $max_width / $ratio) / 2;
            $w = $max_width / $ratio;
        } else {
            $ratio = min($max_width / $w, $max_height / $h);
            if (!$max_width || !$max_height) {
                $ratio = max($max_width / $w, $max_height / $h);
            }
            $max_width = $w * $ratio;
            $max_height = $h * $ratio;
            if ($w < $max_width && $h < $max_height) throw new \Exception("Картинка слишком маленькая!");
            $x = 0;
        }
        if ($ratio == 1) {
            $this->saveImage($img, $savepath, $type, $quality);
            return true;
        }

        $new_img = imagecreatetruecolor($max_width, $max_height);

        // preserve transparency
        if ($type == "gif" or $type == "png"){
            imagecolortransparent($new_img, imagecolorallocatealpha($new_img, 0, 0, 0, 127));
            imagealphablending($new_img, false);
            imagesavealpha($new_img, true);
        }

        imagecopyresampled($new_img, $img, 0, 0, $x, 0, $max_width, $max_height, $w, $h);
        $this->saveImage($new_img, $savepath, $type, $quality);
        imagedestroy($img);
        return true;

    }

    /**
     * Just dislay image
     *
     * @param string $image_file path to image file
     */
    public function outImage($image_file) {
        if ($type = $this->checkTypeImage($image_file)) {
            header("Content-type: image/" . $type['type']);
            switch ($type['type']) {
                case 'jpeg':
                case 'jpg':
                    $image = @imagecreatefromjpeg($image_file);
                    imagejpeg($image);
                    break;

                case 'png':
                    $image = @imagecreatefrompng($image_file);
                    $bg_png = imagecolorallocate($image, 0, 0, 0);
                    imagecolortransparent($image, $bg_png);
                    imagepng($image);
                    break;

                case 'gif':
                    $image = @imagecreatefromgif($image_file);
                    imagegif($image);
                    break;
            }

            imagedestroy($image);
        }
    }

    public function outString($image_string, $type) {
        $image = imagecreatefromstring($image_string);
        if ($image !== false) {
            $type2 = explode("/", $type);
            $type2 = $type2[1];
            if (preg_match(Image::FORMAT_PICTURE, $type2)) {
                header("Content-type: image/$type2");
                switch ($type2) {
                    case 'jpeg':
                    case 'jpg':
                        imagejpeg($image);
                        break;

                    case 'png':
                        $bg_png = imagecolorallocate($image, 0, 0, 0);
                        imagecolortransparent($image, $bg_png);
                        imagepng($image);
                        break;

                    case 'gif':
                        imagegif($image);
                        break;
                }
                imagedestroy($image);
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * @param $image_string
     * @param $type
     * @param int $def_width
     * @param int $def_height
     * @param bool $enlarge
     */
    public function outStringResized($image_string, $type, $def_width = 150, $def_height = 150, $enlarge = false) {
        $image = imagecreatefromstring($image_string);
        if ($image !== false) {
            $type = explode("/", $type);
            $type = $type[1];
            if (preg_match(Image::FORMAT_PICTURE, $type)) {
                header("Content-type: image/$type");
                $x = imagesx($image);
                $y = imagesy($image);

                if ($x > $y && $x > $def_width) {
                    $delta = $def_width / $x;
                    $newx = $def_width;
                    $newy = $y * $delta;
                } elseif ($x < $y && $y > $def_height) {
                    $delta = $def_height / $y;
                    $newy = $def_height;
                    $newx = $x * $delta;
                } elseif ($x == $y) {
                    if ($x > $def_width) {
                        $delta = $def_width / $x;
                        $newx = $x * $delta;
                    } else {
                        $newx = $x;
                    }
                    if ($y > $def_height) {
                        $delta = $def_height / $y;
                        $newy = $y * $delta;
                    } else {
                        $newy = $y;
                    }
                } else {
                    $newx = $x;
                    $newy = $y;
                }

                $new_img = imagecreatetruecolor((int)$newx, (int)$newy);
                imagecopyresampled($new_img, $image, 0, 0, 0, 0, (int)$newx, (int)$newy, $x, $y);

                switch ($type) {
                    case 'jpeg':
                    case 'jpg':
                        imagejpeg($new_img);
                        break;

                    case 'png':
                        $bg_png = imagecolorallocate($new_img, 0, 0, 0);
                        imagecolortransparent($new_img, $bg_png);
                        imagepng($new_img);
                        break;

                    case 'gif':
                        imagegif($new_img);
                        break;
                }
                imagedestroy($new_img);
            }
        }
    }

    /**
     * Resampled and out image
     *
     * @param string $image_file	path to image file
     * @param integer $def_width 	requisite width image, default 150px
     * @param integer $def_height	requisite height image, default 150px
     */
    public function outImageResized($image_file, $def_width = 150, $def_height = 150, $enlarge = false) {
        if (file_exists($image_file)) {
            if ($type = $this->checkTypeImage($image_file)) {
                header("Content-type: image/" . $type['type']);
                switch ($type['type']) {
                    case 'jpeg':
                    case 'jpg':
                        $image = @imagecreatefromjpeg($image_file);
                        break;

                    case 'png':
                        $image = @imagecreatefrompng($image_file);
                        break;

                    case 'gif':
                        $image = @imagecreatefromgif($image_file);
                        break;
                }

                $x = imagesx($image);
                $y = imagesy($image);

                if ($x > $y && $x > $def_width) {
                    $delta = $def_width / $x;
                    $newx = $def_width;
                    $newy = $y * $delta;
                } elseif ($x < $y && $y > $def_height) {
                    $delta = $def_height / $y;
                    $newy = $def_height;
                    $newx = $x * $delta;
                } elseif ($x == $y) {
                    if ($x > $def_width) {
                        $delta = $def_width / $x;
                        $newx = $x * $delta;
                    } else {
                        $newx = $x;
                    }
                    if ($y > $def_height) {
                        $delta = $def_height / $y;
                        $newy = $y * $delta;
                    } else {
                        $newy = $y;
                    }
                } else {
                    $newx = $x;
                    $newy = $y;
                }

                $new_img = imagecreatetruecolor($newx, $newy);
                imagecopyresampled($new_img, $image, 0, 0, 0, 0, $newx, $newy, $x, $y);

                switch ($type['type']) {
                    case 'jpeg':
                    case 'jpg':
                        imagejpeg($new_img);
                        break;

                    case 'png':
                        $bg_png = imagecolorallocate($new_img, 0, 0, 0);
                        imagecolortransparent($new_img, $bg_png);
                        imagepng($new_img);
                        break;

                    case 'gif':
                        imagegif($new_img);
                        break;
                }
                imagedestroy($new_img);
            }
        }
    }

    /**
     * Create image
     *
     * @param integer $width	width of image, default 100px
     * @param integer $height	height of image, default 50px
     * @param string  $text		text on image
     * @param array   $param 	array parameters of image [background color, text color, show border, border color, size, angle, position X, position Y, font]
     * @param integer $quality	quality of image, default 100%
     */
    public function createImage($text = '', $width = 100, $height = 50, $param = array(), $quality = 100) {
        if (imagetypes() & IMG_JPEG) {
            header("Content-type: image/jpeg");
            $img = imagecreate($width, $height);
            if ($param['bg_color']) {
                $bgColor[0] = $param['bg_color'][0];
                $bgColor[1] = $param['bg_color'][1];
                $bgColor[2] = $param['bg_color'][2];
            } else {
                $bgColor[0] = 255;
                $bgColor[1] = 255;
                $bgColor[2] = 255;
            }
            $bg_color = imagecolorallocate($img, $bgColor[0], $bgColor[1], $bgColor[2]);
            if ($param['text_color']) {
                $textColor[0] = $param['text_color'][0];
                $textColor[1] = $param['text_color'][1];
                $textColor[2] = $param['text_color'][2];
            } else {
                $textColor[0] = 150;
                $textColor[1] = 150;
                $textColor[2] = 150;
            }
            $text_color = imagecolorallocate($img, $textColor[0], $textColor[1], $textColor[2]);

            if (!$param['border']) $param['border'] = 'yes';
            if ($param['border'] == 'yes') {
                if ($param['border_color']) {
                    $borderColor[0] = $param['border_color'][0];
                    $borderColor[1] = $param['border_color'][1];
                    $borderColor[2] = $param['border_color'][2];
                } else {
                    $borderColor[0] = 200;
                    $borderColor[1] = 200;
                    $borderColor[2] = 200;
                }
                $border_color = imagecolorallocate($img, $borderColor[0], $borderColor[1], $borderColor[2]);
                imagerectangle($img, 0, 0, ($width - 1), ($height - 1), $border_color);
            }

            if (!$param['size'])  $param['size'] = 16;
            if (!$param['angle']) $param['angle'] = 0;
            if (!$param['x'])     $param['x'] = 15;
            if (!$param['y'])     $param['y'] = 15;
            if (!$param['font'])  $param['font'] = $this->font;

            imagettftext($img, $param['size'], $param['angle'], $param['x'], $param['y'], $text_color, $param['font'], $text);
            imagejpeg($img, null, $quality);
            imagedestroy($img);
        }
    }

    public function getContents($files = array()) {
        if (empty($files)) {
            $files = $_FILES;
        }
        if (empty($files)) {
            return false;
        }
        $data = current($files);
        if (empty($data['type'])) {
            return false;
        }
        if (!preg_match(Image::MIME, current($data['type']))) {
            return false;
        }
        return file_get_contents(current($data['tmp_name']));

    }

    /**
     * Проверяет, относится ли указанный mimetype к картинкам
     * @param $type - mimetype
     *
     * @return bool
     */
    public function isImage($type) {
        if (!preg_match(self::MIME, $type)) {
            return false;
        }
        return true;
    }
}
