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
 * Time: 9:25s
 */
class Gitlab extends \Common {

    private $error;
    private $client;
    private $api_version = 'v4';
    private $per_page    = '80';
    private $projects    = [];


    /**
     *
     */
    public function __construct() {

        parent::__construct();

        $host         = $this->moduleConfig->gitlab->host;
        $token        = $this->moduleConfig->gitlab->token;
        $this->client = new Client([
            'base_uri' => "https://{$host}/api/{$this->api_version}/",
            'headers'  => [
                'Accept'        => 'application/json',
                'PRIVATE-TOKEN' => $token,
            ],
        ]);
    }


    /**
     * Получаем список всех релизов из Gitlab
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTags() {

        $arch         = [];
        $filter_group = [];
        if ($this->moduleConfig->gitlab->filter && $this->moduleConfig->gitlab->filter->group) {
            $filter_group = explode(",", $this->moduleConfig->gitlab->filter->group);
            $filter_group = array_map('trim', $filter_group);
        }

        $this->getProjects();
        $data = $this->projects;


        foreach ($data as $repo) {
            if ($filter_group) {
                if ( ! in_array($repo->namespace->full_path, $filter_group)) continue;
            }

            try {
                $response = $this->client->request('GET', "projects/{$repo->id}/repository/tags");
                $code     = $response->getStatusCode();
                $body     = $response->getBody()->getContents();
                $tags     = json_decode($body);

                if ( ! isset($arch[$repo->id])) $arch[$repo->id] = [
                    'name' => $repo->path_with_namespace,
                    'tags' => [],
                ];

                if ($tags) {
                    foreach ($tags as $tag) {
                        $arch[$repo->id]['tags'][] = [
                            'name'          => $tag->name,
                            'message'       => $tag->message,
                            'author_name'   => $tag->commit->author_name,
                            'author_email'  => $tag->commit->author_email,
                            'authored_date' => $tag->commit->authored_date,
                        ];
                    }
                }

            } catch (RequestException $e) {
                //$msg = Psr7\str($e->getRequest());
                if ($e->hasResponse()) {
                    //$msg = Psr7\str($e->getResponse());
                    $msg         = json_decode($e->getResponse()->getBody());
                    $this->error = $msg->error;
                }
            } catch (\Exception $e) {
                $this->error = $e->getMessage();
            }
        }

        return $arch;
    }


    /**
     * Получаем адрес архива
     * @param $group
     * @param $tag
     * @return string $zip
     * @throws \GuzzleHttp\Exception\GuzzleException
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
            $opened = $zip->open($fn);
            if ($opened === true){
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
                    $fn     = tempnam($this->config->temp, "gitlabzip_") . ".zip";
                    $res    = $zip->open($fn, \ZipArchive::CREATE);
                    if ($res === true) {
                        $this->zipDir($zip, $dirToZip);
                        $zip->close();

                        $zip = $fn;

                    } else {
                        $this->error = $this->translate->tr("Не удалось подготовить файл для установки! {$this->getZipError($res)}");
                        return;
                    }
                } else {
                    $this->error = $this->translate->tr("Не удалось подготовить директорию для установки");
                    return;
                }
            }
            else {
                $this->error = $this->translate->tr("Не удалось создать подготовить файл для установки. {$this->getZipError($opened)}");
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
     * @param $code
     * @return string
     */
    private function getZipError($code) {
        switch ($code)
        {
            case 1:
                return 'Multi-disk zip archives not supported';

            case 2:
                return 'Renaming temporary file failed';

            case 3:
                return 'Closing zip archive failed';

            case 4:
                return 'Seek error';

            case 5:
                return 'Read error';

            case 6:
                return 'Write error';

            case 7:
                return 'CRC error';

            case 8:
                return 'Containing zip archive was closed';

            case 9:
                return 'No such file';

            case 10:
                return 'File already exists';

            case 11:
                return 'Can\'t open file';

            case 12:
                return 'Failure to create temporary file';

            case 13:
                return 'Zlib error';

            case 14:
                return 'Malloc failure';

            case 15:
                return 'Entry has been changed';

            case 16:
                return 'Compression method not supported';

            case 17:
                return 'Premature EOF';

            case 18:
                return 'Invalid argument';

            case 19:
                return 'Not a zip archive';

            case 20:
                return 'Internal error';

            case 21:
                return 'Zip archive inconsistent';

            case 22:
                return 'Can\'t remove file';

            case 23:
                return 'Entry has been deleted';

            default:
                return 'An unknown error has occurred('.intval($code).')';
        }
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