<?php
namespace Core2;

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\ServerRequest;

/**
 *
 */
class Request {

    /**
     * @var string
     */
    private string $method = '';

    /**
     * @var string
     */
    private string $query = '';

    /**
     * @var string
     */
    private string $uri = '';

    /**
     * @var array
     */
    private array $props = [];


    const FORMAT_RAW  = 'raw';
    const FORMAT_JSON = 'json';
    const FORMAT_FORM = 'form';

    private ServerRequestInterface $request;
    private array $cache = [];

    public function __construct(?ServerRequestInterface $request = null)
    {
        if (!$request) {
            $request = ServerRequest::fromGlobals();
        }
        $this->request = $request;

        $this->query  = $_SERVER['QUERY_STRING'];
        $this->uri    = $_SERVER['REQUEST_URI'];
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);

        $this->props['GET']    = $_GET;
        $this->props['POST']   = $_POST;
        $this->props['FILES']  = $_FILES;
        $this->props['COOKIE'] = $_COOKIE;
    }

    public function __call($method, $args)
    {
        return $this->request->$method(...$args);
    }

    public function __get($property)
    {
        if (method_exists($this->request, $property)) {
            return $this->request->$property();
        }

        if (method_exists($this, $property)) {
            return $this->$property();
        }

        return null;
    }

    /**
     * @return string
     */
    public function getMethod(): string {

        return $this->method ?? '';
    }


    /**
     * @return string
     */
    public function getUri(): string {

        return mb_substr($this->uri, mb_strlen(rtrim(DOC_PATH, '/')));
    }


    /**
     * @return string
     */
    public function getUriOriginal(): string {

        return $this->uri ?? '';
    }



    /**
     * Проверка адреса
     * @param string $path
     * @return bool
     */
    public function isPath(string $path): bool {

        $preparePath = $this->preparePath($path);
        $uri         = preg_replace('~\?.*$~u', '', $this->getUri());

        return (bool)preg_match("~{$preparePath}~u", $uri);
    }


    /**
     * Поиск и получение данные из адреса
     * @param string $path
     * @return array|null
     */
    public function getPathParams(string $path):? array {

        $result      = null;
        $preparePath = $this->preparePath($path);
        $uri         = preg_replace('~\?.*$~u', '', $this->getUri());

        if (preg_match("~{$preparePath}~u", $uri, $matches)) {
            foreach ($matches as $key => $match) {
                if (is_numeric($key)) {
                    unset($matches[$key]);
                }
            }
            $result = $matches;
        }

        return $result;
    }


    /**
     * @return string
     */
    public function getQueryString(): string {
        return $this->query;
    }



    /**
     * @param string $name
     * @return mixed
     */
    public function getQuery(string $name): mixed {


        $queries = $this->props['GET'];

        return $queries[$name] ?? null;
    }


    /**
     * @return array
     */
    public function getPost(): array {

        return $this->props['POST'] ?? [];
    }


    /**
     * @return array
     */
    public function getFiles(): array {

        return $this->props['FILES'] ?? [];
    }


    /**
     * @return array
     */
    public function getFilesNormalize(): array {

        $files = $this->props['FILES'] ?? [];

        $files_normalized = [];

        foreach ($files as $index => $file) {

            if ( ! is_array($file['name'])) {
                $files_normalized[$index][] = $file;
                continue;
            }

            foreach ($file['name'] as $idx => $name) {
                $files_normalized[$index][$idx] = [
                    'name'     => $name,
                    'type'     => $file['type'][$idx],
                    'tmp_name' => $file['tmp_name'][$idx],
                    'error'    => $file['error'][$idx],
                    'size'     => $file['size'][$idx],
                ];
            }
        }

        return $files_normalized;
    }


    /**
     * @return array
     */
    public function getCookie(): array {

        return $this->props['COOKIE'] ?? [];
    }


    /**
     * @param string|null $format
     * @return string|array
     * @throws \Exception
     */
    public function getBody(string $format = null): string|array {

        $request_raw = file_get_contents('php://input', 'r');

        switch ($format) {
            case self::FORMAT_RAW:
            default:
                $return = &$request_raw;
                break;

            case self::FORMAT_FORM:
                $return = $this->getFormData($request_raw)['fields'];
                break;

            case self::FORMAT_JSON:
                $json_data = @json_decode($request_raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Incorrect json data');
                }
                $return = $json_data;
                break;
        }

        return $return;
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function getFormContent(): array {

        return $_SERVER['REQUEST_METHOD'] == 'POST'
            ? $this->getPost()
            : $this->getBody($this::FORMAT_FORM);
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function getJsonContent(): array {

        return $this->getBody($this::FORMAT_JSON);
    }


    /**
     * @param string $content
     * @return array
     */
    private function getFormData(string $content): array {

        // Fetch content and determine boundary
        $boundary = substr($content, 0, strpos($content, "\r\n"));
        $files    = [];
        $data     = [];

        if (empty($boundary)) {
            parse_str($content, $data);

        } else {
            // Fetch each part
            $parts = array_slice(explode($boundary, $content), 1);


            foreach ($parts as $part) {
                // If this is the last part, break
                if ($part == "--\r\n") {
                    break;
                }

                // Separate content from headers
                $part = ltrim($part, "\r\n");
                [$raw_headers, $body] = explode("\r\n\r\n", $part, 2);

                // Parse the headers list
                $raw_headers = explode("\r\n", $raw_headers);
                $headers     = [];

                foreach ($raw_headers as $header) {
                    [$name, $value] = explode(':', $header);
                    $headers[strtolower($name)] = ltrim($value, ' ');
                }

                // Parse the Content-Disposition to get the field name, etc.
                if (isset($headers['content-disposition'])) {
                    preg_match(
                        '/^(?<type>.+); *name="(?<name>[^"]*)"(; *filename="(?<filename>[^"]*)")?/',
                        $headers['content-disposition'],
                        $matches
                    );

                    $is_file = isset($matches['filename']);

                    //Parse File
                    if ($is_file) {
                        //get tmp name
                        $filename_parts = pathinfo($matches['filename']);
                        $tmp_name       = tempnam(ini_get('upload_tmp_dir'), $filename_parts['filename']);

                        $value = [
                            'error'    => 0,
                            'name'     => $matches['filename'],
                            'tmp_name' => $tmp_name,
                            'size'     => strlen($body),
                            'type'     => $matches['type'],
                        ];

                        //place in temporary directory
                        file_put_contents($tmp_name, $body);
                    } else {
                        $value = substr($body, 0, strlen($body) - 2);
                    }

                    parse_str($matches['name'], $name_structure);
                    $path      = preg_split('~(\[|\])~', $matches['name']);
                    $name_part = &$name_structure;

                    foreach ($path as $key) {
                        if ($key !== '') {
                            if ( ! is_array($name_part)) {
                                $name_part = array();
                            }
                            $name_part = &$name_part[$key];
                        }
                    }
                    $name_part = $value;

                    if ($is_file) {
                        $files = $this->array_merge_recursive_distinct($files, $name_structure);
                    } else {
                        $data = $this->array_merge_recursive_distinct($data, $name_structure);
                    }
                }
            }
        }

        return [
            'fields' => $data,
            'files'  => $files ?: null,
        ];
    }


    /**
     * Замена названия на поисковые данные
     * @param string $path
     * @return string
     */
    private function preparePath(string $path): string {

        if (preg_match_all('~\{(?<name>[a-zA-Z0-9_]+)(?:|:(?<rule>[^}]+))\}~u', $path, $matches)) {

            if ( ! empty($matches[0])) {
                foreach ($matches[0] as $key => $match) {
                    $count = 1;
                    $name  = $matches['name'][$key];
                    $rule  = $matches['rule'][$key] ?: '[\d\w_\-]+';
                    $path  = str_replace($match, "(?<{$name}>{$rule})", $path, $count);
                }
            }
        }

        return $path;
    }


    /**
     * Объединение массивов без дублирования
     * @param array $array1
     * @param array $array2
     * @return array
     */
    private function array_merge_recursive_distinct(array &$array1, array &$array2): array {

        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset ($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}