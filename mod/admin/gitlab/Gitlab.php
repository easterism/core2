<?php
namespace Core2;
/**
 * Created by PhpStorm.
 * User: StepovichPE
 * Date: 17.03.2017
 * Time: 9:25
 */
class Gitlab extends \Common
{
    private $error;

    /**
     * Получаем список всех релизов из Gitlab
     *
     * @param $host
     * @param $token
     */
    public function getTags() {
        $host   = $this->moduleConfig->gitlab->host;
        $token  = $this->moduleConfig->gitlab->token;
        $data   = \Tool::doCurlRequest("https://$host/api/v3/projects/owned?statistics=1&per_page=100", array(), array("PRIVATE-TOKEN:$token"));
        if ($data['http_code'] == 200) {
            $data           = json_decode($data['answer']);
            $arch           = array();
            $filter_group   = array();
            if ($this->moduleConfig->gitlab->filter && $this->moduleConfig->gitlab->filter->group) {
                $filter_group = explode(",", $this->moduleConfig->gitlab->filter->group);
            }
            foreach ($data as $repo) {
                if ($filter_group) {
                    if (!in_array($repo->namespace->name, $filter_group)) continue;
                }
                $tags = \Tool::doCurlRequest("https://$host/api/v3/projects/{$repo->id}/repository/tags", array(), array("PRIVATE-TOKEN:$token"));
                if ($tags && $tags['answer']) {
                    $tags = json_decode($tags['answer']);
                    if ($tags) {
                        if (!isset($arch[$repo->id])) $arch[$repo->id] = array('name' => $repo->path_with_namespace, 'tags' => array());
                        foreach ($tags as $tag) {
                            //echo "<pre>";print_r($tag);echo "</pre>";//die;
                            $arch[$repo->id]['tags'][] = array('name' => $tag->name,
                                'message' => $tag->message,
                                'author_name' => $tag->commit->author_name,
                                'author_email' => $tag->commit->author_email,
                                'authored_date' => $tag->commit->authored_date
                            );
                        }
                    }
                }
            }
            //echo "<pre>";print_r($arch);echo "</pre>";die;
            foreach ($arch as $item) {
                echo $item['name'];
                echo "<ul>";
                foreach ($item['tags'] as $tag) {
                    echo "<li><a href=\"javascript:gl.selectTag('{$item['name']}','{$tag['name']}');$.modal.close();\">{$tag['name']}</a>
                    {$tag['author_name']} ({$tag['author_email']})
                    </li>";
                }
                echo "</ul>";
            }
        }
        else {
            if (!empty($data['answer'])) {
                $msg = json_decode($data['answer']);
                echo "<h3>{$msg->message}</h3>";
            }
            else if (!empty($data['error'])) {
                echo "<h3>{$data['error']}</h3>";
            }
        }
    }

    /**
     * Получаем адрес архива
     * @param $group
     * @param $tag
     * @return string $zip
     */
    public function getZip($group, $tag) {
        $host = $this->moduleConfig->gitlab->host;
        $answer = \Tool::doCurlRequest("https://{$host}/{$group}/repository/archive.zip?ref={$tag}", array(), array("PRIVATE-TOKEN:{$this->moduleConfig->gitlab->token}"));
        $zip = '';
        if ($answer['http_code'] == 200) {
            $zip = new \ZipArchive();
            $upload_dir 	    = $this->config->temp . '/' . \Zend_Session::getId();
            $destinationFolder  = $upload_dir . '/gitlab_' . uniqid() . '/';
            $fn                 = tempnam($upload_dir, "gitlabzip");
            file_put_contents($fn, $answer['answer']);

            if ($zip->open($fn) === true){
                $zip->extractTo($destinationFolder);
                $zip->close();
                unlink($fn);
                $iterator = new \DirectoryIterator($destinationFolder);
                $dirToZip = '';
                foreach ($iterator as $info) {
                    if (!$info->isDot() && $info->isDir()) {
                        $dirToZip = $iterator->getPathname();
                    }
                }
                if ($dirToZip) {
                    $fn     = tempnam($upload_dir, "gitlabzip_");
                    $res    = $zip->open($fn, \ZipArchive::CREATE);
                    if ($res === true) {
                        $this->zipDir($zip, $dirToZip);
                        $zip->close();

                        $zip = $fn;

                    } else {
                        $this->error = $this->translate->tr("Не удалось создать подготовить файл для установки");
                        return;
                    }
                } else {
                    $this->error = $this->translate->tr("Не удалось создать подготовить файл для установки");
                    return;
                }
            } else {
                $this->error = $this->translate->tr("Не удалось создать подготовить файл для установки");
                return;
            }
            $answer['answer'];
        } else {
            $this->error = $answer['error'];
        }
        return $zip;
    }

    /**
     * получаем ошибки, накопленные при работе
     * @return mixed
     */
    public function getError() {
        return $this->error;
    }

    private function zipDir(\ZipArchive $zip, $path, $pos = 0) {
        if (!$pos) $pos = strlen($path);
        $nodes = glob($path . '/*');
        foreach ($nodes as $node) {
            if (is_dir($node)) {
                $zip->addEmptyDir(substr($node, $pos));
                $this->zipDir($zip, $node, $pos);
            } else if (is_file($node))  {
                $zip->addFile($node, substr($node, $pos));
            }
        }
    }

}