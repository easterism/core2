<?php
namespace Core2\Routing;

require_once 'Method.php';

/**
 *
 */
class Route {

    private string $path;
    private array  $methods = [];
    private array  $params  = [];


    /**
     * @param string $path
     */
    public function __construct(string $path) {
        $this->path = $path;
    }


    /**
     * @param array|\Closure $action
     * @return $this
     * @throws \Exception
     */
    public function get(array|\Closure $action): self {

        return $this->method('get', $action);
    }


    /**
     * @param array|\Closure $action
     * @return $this
     * @throws \Exception
     */
    public function post(array|\Closure $action): self {

        return $this->method('post', $action);
    }


    /**
     * @param array|\Closure $action
     * @return $this
     * @throws \Exception
     */
    public function put(array|\Closure $action): self {

        return $this->method('put', $action);
    }


    /**
     * @param array|\Closure $action
     * @return $this
     * @throws \Exception
     */
    public function delete(array|\Closure $action): self {

        return $this->method('delete', $action);
    }


    /**
     * @param array|\Closure $action
     * @return $this
     * @throws \Exception
     */
    public function patch(array|\Closure $action): self {

        return $this->method('patch', $action);
    }


    /**
     * @param array|\Closure $action
     * @return self
     * @throws \Exception
     */
    public function options(array|\Closure $action): self {

        return $this->method('options', $action);
    }


    /**
     * @param array|\Closure $action
     * @return void
     * @throws \Exception
     */
    public function any(array|\Closure $action): void {

        $this->method('*', $action);
    }


    /**
     * @param string         $method
     * @param array|\Closure $action
     * @return self
     * @throws \Exception
     */
    public function method(string $method, array|\Closure $action): self {

        if (empty($method)) {
            throw new \Exception('Empty method name');
        }

        $this->validateAction($action);

        $this->methods[strtolower($method)] = new Method($action);

        return $this;
    }


    /**
     * @param array|\Closure $callback
     * @return void
     * @throws \Exception
     */
    private function validateAction(array|\Closure $callback): void {

        if (is_array($callback)) {
            $correct = ! empty($callback[0]) &&
                       ! empty($callback[1]) &&
                       is_string($callback[1]);

            if ($correct) {
                if (is_string($callback[0])) {
                    if ( ! class_exists($callback[0])) {
                        $correct = false;
                    }

                } elseif ( ! is_object($callback[0])) {
                    $correct = false;
                }
            }

            if ( ! $correct) {
                throw new \Exception('Error callback param');
            }
        }
    }


    /**
     * @return array
     */
    public function getMethods(): array {

        return $this->methods;
    }


    /**
     * @param string $method
     * @return Method|null
     */
    public function getMethod(string $method):? Method {

        $method = strtolower($method);

        if (isset($this->methods[$method])) {
            return $this->methods[$method];

        } elseif (isset($this->methods['*'])) {
            return $this->methods['*'];
        }

        return null;
    }


    /**
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }


    /**
     * @return string
     */
    public function getPathRegexp(): string {

        $path = $this->path;
        $path = str_replace('\~', '~', $path);
        $path = str_replace('~', '\~', $path);
        $path = "~^{$path}$~u";

        if (preg_match_all('~\{(?<name>[a-zA-Z0-9_]+)(?:|:(?<rule>[^}]+))\}~u', $path, $matches)) {

            if ( ! empty($matches[0])) {
                foreach ($matches[0] as $key => $match) {
                    $count = 1;
                    $name  = $matches['name'][$key];
                    $rule  = $matches['rule'][$key] ?: '[\d]+';
                    $path  = str_replace($match, "(?<{$name}>{$rule})", $path, $count);
                }
            }
        }

        return $path;
    }


    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): self {

        $this->params = $params;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getParams():? array {
        return $this->params;
    }
}