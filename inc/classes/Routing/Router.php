<?php
namespace Core2\Routing;

require_once 'Route.php';


/**
 *
 */
class Router {

    /**
     * @var Route[]
     */
    private array $routes = [];


    /**
     * @param string $base_url
     * @param array  $map
     * @throws \Exception
     */
    public function __construct(string $base_url = '', array $map = []) {

        foreach ($map as $path => $methods) {

            if (is_array($methods) && ! empty($methods)) {
                $route = $this->route("{$base_url}{$path}");

                foreach ($methods as $method => $action) {
                    $route->method($method, $action);
                }
            }
        }
    }


    /**
     * @param string $path
     * @return Route
     */
    public function route(string $path): Route {

        $route = new Route($path);

        $this->routes[$path] = $route;

        return $route;
    }


    /**
     * @return Method|null
     * @throws \Exception
     */
    public function getRoute():? Method {

        $uri = mb_substr($_SERVER['REQUEST_URI'], mb_strlen(rtrim(DOC_PATH, '/')));
        return $this->getRouteMethod($_SERVER['REQUEST_METHOD'], $uri);
    }


    /**
     * @param string $method
     * @param string $path
     * @return Route|null
     * @throws \Exception
     */
    public function getRouteMethod(string $method, string $path):? Method {

        $method = strtolower($method);
        $path   = preg_replace('~\?.*~', '', $path);

        foreach ($this->routes as $route) {

            if (($params = $this->getRouteParams($route, $method, $path)) !== null) {
                $route_method = $route->getMethod($method);
                $route_method->setParams($params);

                return $route_method;
            }
        }

        return null;
    }


    /**
     * @param Route  $route
     * @param string $method
     * @param string $path
     * @return array|null
     */
    private function getRouteParams(Route $route, string $method, string $path):? array {

        $methods = array_keys($route->getMethods());

        if (in_array($method, $methods) || in_array('*', $methods)) {
            $path_regexp = $route->getPathRegexp();

            if (preg_match($path_regexp, $path, $matches)) {

                foreach ($matches as $key => $match) {
                    if (is_numeric($key)) {
                        unset($matches[$key]);
                    }
                }

                return $matches;
            }
        }

        return null;
    }
}