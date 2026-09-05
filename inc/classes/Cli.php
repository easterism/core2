<?php

namespace Core2;

require_once 'Db.php';

class Cli extends Db
{

    /**
     * Cli
     * @return string
     * @throws \Exception
     */
    public function run($module, $action, $params) {

        register_shutdown_function(array($this, 'fatal_handler'));

        Registry::set('context', array($module, $action));
        Registry::set('auth', new \StdClass());

        $params = $params === false
            ? array()
            : (is_array($params) ? $params : array($params));

        //$this->db; // FIXME хак

        if ( ! $this->isModuleInstalled($module)) {
            throw new \Exception(sprintf($this->_("Module '%s' not found"), $module));
        }

        if ( ! $this->isModuleActive($module)) {
            throw new \Exception(sprintf($this->_("Module '%s' does not active"), $module));
        }

        $location     = $this->getModuleLocation($module);
        $mod_cli      = 'Mod' . ucfirst(strtolower($module)) . 'Cli';
        $mod_cli_path = "{$location}/{$mod_cli}.php";

        if ( ! file_exists($mod_cli_path)) {
            throw new \Exception(sprintf($this->_("File '%s' does not exists"), $mod_cli_path));
        }

        require_once $mod_cli_path;

        if ( ! class_exists($mod_cli)) {
            throw new \Exception(sprintf($this->_("Class '%s' not found"), $mod_cli));
        }


        $all_class_methods = get_class_methods($mod_cli);
        if ($parent_class = get_parent_class($mod_cli)) {
            $parent_class_methods = get_class_methods($parent_class);
            $self_methods = array_diff($all_class_methods, $parent_class_methods);
        } else {
            $self_methods = $all_class_methods;
        }

        if (array_search($action, $self_methods) === false) {
            throw new \Exception(sprintf($this->_("Cli method '%s' not found in class '%s'"), $action, $mod_cli));
        }

        $autoload_file = $location . "/vendor/autoload.php";
        if (file_exists($autoload_file)) {
            require_once($autoload_file);
        }

        $mod_instance = new $mod_cli();
        $result = call_user_func_array(array($mod_instance, $action), $params);

        if (is_scalar($result)) {
            return (string)$result . PHP_EOL;
        }

        return PHP_EOL;
    }

    public function fatal_handler()
    {
        $errfile = "unknown file";
        $errstr  = "shutdown";
        $errno   = E_CORE_ERROR;
        $errline = 0;

        $error = error_get_last();

        if ($error !== NULL) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr  = $error["message"];
            $trace   = print_r(debug_backtrace(), true);
            $this->log->error($errstr . chr(10) . $errfile . "($errline)");
        }
    }
}