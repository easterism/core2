<?php
namespace Core2\Routing;
use Core2\Request;

require_once __DIR__ . "/../Request.php";


/**
 *
 */
class Method {

    private array          $params = [];
    private array|\Closure $action = [];


    /**
     * @param array|\Closure $action
     */
    public function __construct(array|\Closure $action) {

        $this->action = $action;
    }


    /**
     * @return array
     */
    public function getParams(): array {

        return $this->params;
    }


    /**
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): self {

        $this->params = array_values($params);
        return $this;
    }


    /**
     * @param mixed $param
     * @return $this
     */
    public function appendParam(mixed $param): self {

        $this->params[] = $param;
        return $this;
    }


    /**
     * @param mixed $param
     * @return $this
     */
    public function prependParam(mixed $param): self {

        array_unshift($this->params, $param);
        return $this;
    }


    /**
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function run(Request $request): mixed {

        if (is_array($this->action)) {
            if (is_object($this->action[0])) {
                if ( ! is_callable([$this->action[0], $this->action[1]])) {
                    throw new \Exception('Method not found: ' . $this->action[1]);
                }

                return call_user_func_array([$this->action[0], $this->action[1]], $this->params);

            } else {
                $class = new $this->action[0]($request);
                if ( ! is_callable([$class, $this->action[1]])) {
                    throw new \Exception('Method not found: ' . $this->action[1]);
                }

                return call_user_func_array([$class, $this->action[1]], $this->params);
            }

        } else {
            return call_user_func_array($this->action, $this->params);
        }
    }
}