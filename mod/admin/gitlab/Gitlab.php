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

    /**
     * Получаем список всех релизов из Gitlab
     *
     * @param $host
     * @param $token
     */
    public function getTags() {
        $host = $this->moduleConfig->gitlab->host;
        $token = $this->moduleConfig->gitlab->token;
        $data = \Tool::doCurlRequest("https://$host/api/v3/projects/owned?statistics=1&per_page=100", array(), array("PRIVATE-TOKEN:$token"));
        if ($data['http_code'] == 200) {
            $data = json_decode($data['answer']);
            $arch = array();
            $filter_group = array();
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
    }

    /**
     * Получаем адрес архива
     * @param $group
     * @param $tag
     * @return array
     */
    public function getZip($group, $tag) {
        $host = $this->moduleConfig->gitlab->host;
        return \Tool::doCurlRequest("https://{$host}/{$group}/repository/archive.zip?ref={$tag}", array(), array("PRIVATE-TOKEN:{$this->moduleConfig->gitlab->token}"));
    }

}