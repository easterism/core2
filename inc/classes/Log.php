<?php
namespace Core2;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Processor\WebProcessor;
use Monolog\Formatter\NormalizerFormatter;


/**
 * Обеспечение журналирования запросов пользователей
 * и других событий
 * Class Logger
 */
class Log {
    private $log;
    private $config;
    private $writer;


    /**
     * Log constructor.
     * @param string $name
     * @param string $logfile
     */
    public function __construct($name = 'core2', $logfile = '') {
        if ($name != 'access') {
            $this->log    = new Logger($_SERVER['SERVER_NAME'] . "." . $name);
            $this->config = \Zend_Registry::getInstance()->get('core_config');

            if (isset($this->config->log) &&
                isset($this->config->log->system) &&
                ! empty($this->config->log->system->file) &&
                is_string($this->config->log->system->file)
            ) {
                $stream = new StreamHandler($this->config->log->system->file);
                //$stream->setFormatter(new NormalizerFormatter());
                $this->log->pushHandler($stream);
            }

        } elseif ($logfile) {
            $this->log    = new Logger($name);
            $this->config = \Zend_Registry::getInstance()->get('config');

        } else {
            $this->log = new Logger($name);
        }
    }


    /**
     * @param string $name
     */
    public function access($name) {
        $this->setWriter();
        $this->log->pushProcessor(new WebProcessor());
        $this->log->addInfo($name, array('sid' => \Zend_Session::getId()));
    }


    /**
     * @param array|string $msg
     * @param array        $context
     */
    public function info($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->info($msg, $context);
    }


    /**
     * @param array|string $msg
     * @param array        $context
     */
    public function warning($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->warning($msg, $context);
    }


    /**
     * @param array|string $msg
     * @param array        $context
     */
    public function debug($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->debug($msg, $context);
    }


    /**
     *
     */
    private function setWriter() {
        if ( ! $this->writer) {
            if (isset($this->config->log) &&
                isset($this->config->log->system) &&
                ! empty($this->config->log->system->file) &&
                is_string($this->config->log->system->file)
            ) {
                $this->log->pushHandler(new StreamHandler($this->config->log->system->file, Logger::INFO));
                $this->writer = 'file';
            } else {
                $this->log->pushHandler(new SyslogHandler($_SERVER['SERVER_NAME'] . ".core2"));
                $this->writer = 'syslog';
            }
        }
    }
}