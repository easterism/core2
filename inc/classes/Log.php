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
        private $writer;

        public function __construct($name) {
            $this->log = new Logger($name);
        }

        public function access($name) {
            $this->setWriter();
            $this->log->pushProcessor(new WebProcessor());
            $this->log->addInfo($name, array('sid' => \Zend_Session::getId()));
        }

        private function setWriter() {
            if (!$this->writer) {
                $this->config = \Zend_Registry::getInstance()->get('config');
                if ($this->config->log->system->file) {
                    $this->log->pushHandler(new StreamHandler($this->config->log->system->file, Logger::INFO));
                    $this->writer = 'file';
                } else {
                    $this->log->pushHandler(new SyslogHandler());
                    $this->writer = 'syslog';
                }
            }
        }

        public function info($msg, $context = array()) {
            $this->log->addInfo($msg, $context);
        }

    }