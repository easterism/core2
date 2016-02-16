<?php
    namespace Core2;
    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;
    use Monolog\Handler\SyslogHandler;
    use Monolog\Processor\WebProcessor;

    /**
     * Обеспечение журналирования запросов пользователей
     * и других событий
     * Class Logger
     */
    class Log {
        private $log;
        private $config;

        public function __construct($name) {
            $this->config = \Zend_Registry::getInstance()->get('config');
            $this->log = new Logger($name);
            if ($this->config->log->system->file) {
                $this->log->pushHandler(new StreamHandler($this->config->log->system->file, Logger::INFO));
            } else {
                $this->log->pushHandler(new SyslogHandler());
            }
        }

        public function info($log) {
            $this->log->addInfo($log);
        }

        public function access($name) {
            $this->log->pushProcessor(new WebProcessor());
            $this->log->addInfo($name, array('sid' => \Zend_Session::getId()));
        }

    }