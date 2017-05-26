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
    private $writer_custom;


    /**
     * Log constructor.
     * @param string $name
     */
    public function __construct($name = 'core2') {
        if ($name !== 'access') {
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

        } else {
            $this->config = \Zend_Registry::getInstance()->get('config');
            $this->log = new Logger($name);
        }
    }


    /**
     * дополнительный лог в заданный файл
     * @param $filename
     * @return $this
     */
    public function file($filename) {
        if ( ! $this->writer_custom) {
            $this->log->pushHandler(new StreamHandler($filename));
            $this->writer_custom = $filename;
        }
        return $this;
    }


    /**
     * Журнал запросов
     * @param string $name
     */
    public function access($name) {
        $this->setWriter();
        $this->log->pushProcessor(new WebProcessor());
        $this->log->info($name, array('sid' => \Zend_Registry::get('session')->getId()));
    }


    /**
     * Информационная запись в лог
     * @param array|string $msg
     * @param array        $context
     */
    public function info($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->info($msg, $context);
        $this->removeCustomWriter();
    }


    /**
     * Предупреждение в лог
     * @param array|string $msg
     * @param array        $context
     */
    public function warning($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->warning($msg, $context);
        $this->removeCustomWriter();
    }


    /**
     * Отладочная информация в лог
     * @param array|string $msg
     * @param array        $context
     */
    public function debug($msg, $context = array()) {
        if (is_array($msg)) {
            $context = $msg;
            $msg = '-';
        }
        $this->log->debug($msg, $context);
        $this->removeCustomWriter();
    }


    /**
     * прекращение записи в заданный дополнительный лог
     */
    private function removeCustomWriter() {
        if ($this->writer_custom) {
            $this->log->popHandler();
            $this->writer_custom = false;
        }
    }


    /**
     * Куда писать журнал запросов
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