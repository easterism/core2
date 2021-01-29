<?php
namespace Core2;

use Laminas\Session\Container as SessionContainer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

/**
 * Created by PhpStorm.
 * User: StepovichPE
 * Date: 17.03.2017
 * Time: 9:25
 */
class Gitlab extends \Common
{
    private $error;
    private $client;
    private $api_version = 'v4';
    private $per_page = '80';
    private $projects = array();

    public function __construct()
    {
        parent::__construct();

        $host   = $this->moduleConfig->gitlab->host;
        $token  = $this->moduleConfig->gitlab->token;
        $this->client = new Client(['base_uri' => "https://$host/api/{$this->api_version}/",
                                    'headers' => [
                                        'Accept'     => 'application/json',
                                        'PRIVATE-TOKEN' => $token
                                    ]
                                ]);
    }

    /**
     * Получаем список всех релизов из Gitlab
     *
     * @param $host
     * @param $token
     */
    public function getTags() {
        $host   = $this->moduleConfig->gitlab->host;
        $token  = $this->moduleConfig->gitlab->token;

        $arch           = array();
        $filter_group   = array();
        if ($this->moduleConfig->gitlab->filter && $this->moduleConfig->gitlab->filter->group) {
            $filter_group = explode(",", $this->moduleConfig->gitlab->filter->group);
        }

        $this->getProjects();
        $data           = $this->projects;
        foreach ($data as $repo) {
            if ($filter_group) {
                if (!in_array($repo->namespace->full_path, $filter_group)) continue;
            }
            //$tags = \Tool::doCurlRequest("https://$host/api/{$this->api_version}/projects/{$repo->id}/repository/tags", array(), array("PRIVATE-TOKEN:$token"));
            try {
                $response = $this->client->request('GET', "projects/{$repo->id}/repository/tags");
                $code = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $tags = json_decode($body);

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
            catch (RequestException $e) {
                //$msg = Psr7\str($e->getRequest());
                if ($e->hasResponse()) {
                    //$msg = Psr7\str($e->getResponse());
                    $msg = json_decode($e->getResponse()->getBody());
                    $this->error =  $msg->error;
                }
            } catch (\Exception $e) {
                $this->error =  $e->getMessage();
            }
        }
        //echo "<pre>";print_r($arch);echo "</pre>";die;
        foreach ($arch as $item) {
            echo $item['name'];
            echo "<ul>";
            foreach ($item['tags'] as $tag) {
                echo "<li><a href=\"javascript:void(0);\" onclick=\"gl.selectTag('{$item['name']}','{$tag['name']}');$.modal.close();\">{$tag['name']}</a>
                {$tag['author_name']} ({$tag['author_email']})
                </li>";
            }
            echo "</ul>";
        }

    }

    /**
     * Получаем адрес архива
     * @param $group
     * @param $tag
     * @return string $zip
     */
    public function getZip($group, $tag) {
        $group = urlencode($group);
        //$answer = \Tool::doCurlRequest("https://{$host}/api/v4/projects/{$group}/repository/archive.zip?ref={$tag}", array(), array("PRIVATE-TOKEN:{$this->moduleConfig->gitlab->token}"));

        $zip = '';
        try {
            $response = $this->client->request('GET', "projects/{$group}/repository/archive.zip?sha={$tag}");
            $code = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            $zip = new \ZipArchive();
            $upload_dir 	    = $this->config->temp . '/' . SessionContainer::getDefaultManager()->getId();
            $destinationFolder  = $upload_dir . '/gitlab_' . uniqid() . '/';
            $fn                 = tempnam($this->config->temp, "gitlabzip");
            if (!$fn) throw new \Exception("Не удалось создать файл для установки");
            file_put_contents($fn, $body);

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
                    $fn     = tempnam($this->config->temp, "gitlabzip_");
                    $res    = $zip->open($fn, \ZipArchive::CREATE);
                    if ($res === true) {
                        $this->zipDir($zip, $dirToZip);
                        $zip->close();

                        $zip = $fn;

                    } else {
                        $this->error = $this->translate->tr("Не удалось подготовить файл для установки! $res");
                        return;
                    }
                } else {
                    $this->error = $this->translate->tr("Не удалось подготовить директорию для установки");
                    return;
                }
            } else {
                $this->error = $this->translate->tr("Не удалось создать подготовить файл для установки");
                return;
            }
        } catch (RequestException $e) {
            //$msg = Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                //$msg = Psr7\str($e->getResponse());
                $msg = json_decode($e->getResponse()->getBody());
                $this->error = $msg->error;
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
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

    /**
     * запаковывает директорию
     * @param \ZipArchive $zip
     * @param $path
     * @param int $pos
     */
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

    /**
     * получаем список всех доступных проектов
     * @param int $page
     */
    private function getProjects($page = 1) {
        try {
            $response = $this->client->request('GET', "projects?page=$page&per_page={$this->per_page}");
            $code = $response->getStatusCode();
            $data = json_decode($response->getBody()->getContents());
            if ($data) {
                $this->projects = array_merge($this->projects, $data); //сохраняем результат
                $this->getProjects($page + 1);
            }
        } catch (RequestException $e) {
            //$msg = Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                //$msg = Psr7\str($e->getResponse());
                $msg = json_decode($e->getResponse()->getBody());
                $this->error =  $msg->error;
            }
        } catch (\Exception $e) {
            $this->error =  $e->getMessage();
        }
    }

    /**
     * вывод сообщения об ошибке curl запроса
     * @param $data
     */
    private function printError($data) {
        if (!empty($data['answer'])) {
            $msg = json_decode($data['answer']);
            echo "<h3>{$msg->message}</h3>";
        }
        else if (!empty($data['error'])) {
            echo "<h3>{$data['error']}</h3>";
        }
    }

}