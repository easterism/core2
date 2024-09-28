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
    public function run() {

        $options = getopt('m:a:p:s:h', array(
            'module:',
            'action:',
            'param:',
            'section:',
            'help',
        ));


        if (empty($options) || isset($options['h']) || isset($options['help'])) {
            return implode(PHP_EOL, array(
                'Core 2',
                'Usage: php index.php [OPTIONS]',
                'Optional arguments:',
                "   -m    --module    Module name",
                "   -a    --action    Cli method name",
                "   -p    --param     Parameter in method",
                "   -s    --section   Section name in config file",
                "   -h    --help      Help info",
                "Examples of usage:",
                "php index.php --module cron --action run",
                "php index.php --module cron --action run --section site.com",
                "php index.php --module cron --action runJob --param 123\n",
            ));
        }

        if ((isset($options['m']) || isset($options['module'])) &&
            (isset($options['a']) || isset($options['action']))
        ) {
            $module = isset($options['module']) ? $options['module'] : $options['m'];
            $action = isset($options['action']) ? $options['action'] : $options['a'];
            Registry::set('context', array($module, $action));

            $params = isset($options['param'])
                ? $options['param']
                : (isset($options['p']) ? $options['p'] : false);
            $params = $params === false
                ? array()
                : (is_array($params) ? $params : array($params));

            try {
                $this->db; // FIXME хак

                if ( ! $this->isModuleInstalled($module)) {
                    throw new \Exception("Module '$module' not found");
                }

                if ( ! $this->isModuleActive($module)) {
                    throw new \Exception("Module '$module' does not active");
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

            } catch (\Exception $e) {
                $message = $e->getMessage();
                return $message . PHP_EOL;
            }

        }

        return PHP_EOL;
    }
}