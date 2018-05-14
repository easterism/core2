<?php
namespace Core2;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Processor\WebProcessor;
use Monolog\Formatter\NormalizerFormatter;
use Zend\Session\Container as SessionContainer;


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
    private $handlers;


    /**
     * Log constructor.
     * @param string $name
     */
    public function __construct($name = 'core2') {
        if ($name !== 'access') {
            //эта секция предназначена для работы ядра
            $this->log = new Logger($_SERVER['SERVER_NAME'] . "." . $name);
            $this->config = \Zend_Registry::getInstance()->get('core_config');

            if ($name === 'webhook') {
                if (isset($this->config->log) &&
                    isset($this->config->log->webhook))
                {

                    //TODO add more webhooks
                } else {
                    return new \stdClass();
                }
            } else {
                if (isset($this->config->log) &&
                    isset($this->config->log->system) &&
                    !empty($this->config->log->system->file) &&
                    is_string($this->config->log->system->file)
                ) {
                    $stream = new StreamHandler($this->config->log->system->file);
                    //$stream->setFormatter(new NormalizerFormatter());
                    $this->log->pushHandler($stream);
                }
            }
        } else {
            $this->config = \Zend_Registry::getInstance()->get('config');
            $this->log = new Logger($name);
        }
    }
    /**
     * Обработчик метода не доступного через экземпляр
     * @param string    $name       Имя метода
     * @param array     $arguments  Параметры метода
     */
    public function __call($name, $arguments)
    {
        if ($name == 'slack') {
            if (!$this->config->log || !$this->config->log->webhook->slack) return new \stdObject();
            $channel = null;
            $username = null;
            $useAttachment = true;
            $iconEmoji = null;
            $useShortAttachment = false;
            $includeContextAndExtra = false;
            $level = Logger::CRITICAL;
            $bubble = true;
            $excludeFields = array();

            if (isset($arguments[0])) $channel = $arguments[0];
            if (isset($arguments[1])) $username = $arguments[1];

            //TODO add other params
            $this->handlers[$name] = array($this->config->log->webhook->slack->url, $channel, $username, $useAttachment, $iconEmoji, $useShortAttachment, $includeContextAndExtra, $level, $bubble, $excludeFields);

            return $this;
        }
        return null;
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
        $this->log->info($name, array('sid' => SessionContainer::getDefaultManager()->getId()));
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
        if ($this->handlers) {
            $this->setHandler(Logger::INFO);
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
        if ($this->handlers) {
            $this->setHandler(Logger::WARNING);
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
        if ($this->handlers) {
            $this->setHandler(Logger::DEBUG);
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

    /**
     * Установка обработчика
     * @param int $level уровень журналирования
     */
    private function setHandler($level) {
        while ($this->log->getHandlers()) {
            $this->log->popHandler();
        }
        foreach ($this->handlers as $name => $params) {
            if ($name == 'slack') {
                $handler = new SlackWebhookHandler($params[0], $params[1], $params[2], $params[3], $params[4], $params[5], $params[6], $level);
                $this->log->pushHandler($handler);
            }
        }

    }
}