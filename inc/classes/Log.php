<?php
namespace Core2;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\MissingExtensionException;
use Monolog\Handler\TelegramBotHandler;
use Core2\Log\DiscordHandler;
use \Laminas\Config\Config;

/**
 * Журналирование событий
 */
class Log {

    private Logger $logger;
    private ?Config $config;
    private ?Config $core_config;
    private string  $logs_dir    = '';
    private array   $files       = [];
    private string $date_format  = "Y-m-d H:i:s.u";
    private array  $handlers     = [];


    /**
     * @param string $name
     */
    public function __construct(string $name = 'core2') {

        $this->logger      = new Logger($_SERVER['SERVER_NAME'] . "." . $name);
        $this->config      = Registry::isRegistered('config') ? Registry::get('config') : null;
        $this->core_config = Registry::isRegistered('core_config') ? Registry::get('core_config') : null;

        $this->logs_dir = $this?->config?->log?->dir && is_string($this->config->log->dir)
            ? $this->config->log->dir
            : ($this?->config?->log?->system?->file ?: dirname($this->config->log->system->file));


        if ($name == 'access') {
            if ($this?->config?->log &&
                $this?->config?->log?->access &&
                ! empty($this->config->log->access->file) &&
                is_string($this->config->log->access->file)
            ) {
                $this->files[] = $this->config->log->access->file;
            }

        } else {
            if ($this?->config?->log &&
                $this?->config?->log?->system &&
                ! empty($this->config->log->system->file) &&
                is_string($this->config->log->system->file)
            ) {
                $this->files[] = $this->config->log->system->file;
            }

            if ($this->core_config?->log &&
                ($this->core_config->log->system) &&
                ! empty($this->core_config->log->system->file) &&
                is_string($this->core_config->log->system->file)
            ) {
                if ( ! in_array($this->core_config->log->system->file, $this->files)) {
                    $this->files[] = $this->core_config->log->system->file;
                }
            }
        }
    }


    /**
     * Журнал запросов
     * @param string $name
     * @param string $sid
     */
    public function access(string $name, string $sid): void {
        $this->logger->pushProcessor(new WebProcessor());
        $this->logger->info($name, ['sid' => $sid]);
    }


    /**
     * Информационная запись в лог
     * @param string           $message
     * @param array|\Throwable $context
     * @throws MissingExtensionException
     */
    public function info(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::INFO, $message, $context);
    }


    /**
     * @param string           $message
     * @param array|\Throwable $context
     * @throws \Exception
     */
    public function notice(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::NOTICE, $message, $context);
    }


    /**
     * Предупреждение в лог
     * @param string           $message
     * @param array|\Throwable $context
     * @throws MissingExtensionException
     * @throws \Exception
     */
    public function warning(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::WARNING, $message, $context);
    }


    /**
     * Предупреждение в лог
     * @param string          $message
     * @param array|\Throwable $context
     * @throws MissingExtensionException
     * @throws \Exception
     */
    public function error(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::ERROR, $message, $context);
    }


    /**
     * @param string           $message
     * @param array|\Throwable $context
     * @throws \Exception
     */
    public function critical(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::CRITICAL, $message, $context);
    }


    /**
     * @param string           $message
     * @param array|\Throwable $context
     * @throws \Exception
     */
    public function emergency(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::EMERGENCY, $message, $context);
    }


    /**
     * Отладочная информация в лог
     * @param string           $message
     * @param array|\Throwable $context
     * @throws \Exception
     */
    public function debug(string $message, array|\Throwable $context = []): void {

        $this->callEvent(Logger::DEBUG, $message, $context);
    }


    /**
     * Лог в заданный файл
     * @param string $filename
     * @return $this
     */
    public function file(string $filename): self {

        $this->handlerFile($filename);

        return $this;
    }


    /**
     * Отправка сообщения в Slack
     * @return self|\stdClass
     * @throws MissingExtensionException
     */
    public function slack(): \stdClass|self {

        if ( ! $this->core_config?->log?->webhook?->slack?->url ||
             ! is_string($this->core_config->log->webhook->slack->url)
        ) {
            return new \stdClass();
        }


        $handler = new SlackWebhookHandler($this->core_config->log->webhook->slack->url);
        $handler->setFormatter(new LineFormatter(null, $this->date_format));

        $this->handlers[] = $handler;

        return $this;
    }


    /**
     * Отправка сообщения в Slack
     * @return self|\stdClass
     */
    public function discord(): \stdClass|self {

        if ( ! $this->core_config?->log?->webhook?->discord?->url ||
             ! is_string($this->core_config->log->webhook->discord->url)
        ) {
            return new \stdClass();
        }

        require_once 'Log/DiscordHandler.php';

        $handler = new DiscordHandler($this->core_config->log->webhook->discord->url);
        $handler->setFormatter(new LineFormatter(null, $this->date_format));

        $this->handlers[] = $handler;

        return $this;
    }


    /**
     * Отправка сообщения в Tg
     * @return self|\stdClass
     * @throws MissingExtensionException
     */
    public function telegram(): \stdClass|self {

        if ( ! $this->core_config->log?->webhook?->telegram?->apikey ||
             ! $this->core_config->log?->webhook?->telegram?->channels ||
             ! is_string($this->core_config->log->webhook->telegram->apikey) ||
             ! is_string($this->core_config->log->webhook->telegram->channels)
        ) {
            return new \stdClass();
        }

        $channels = explode(',', $this->core_config->log->webhook->telegram->channels);
        $channels = array_map('trim', $channels);
        $channels = array_filter($channels);

        if (empty($channels)) {
            return new \stdClass();
        }

        foreach ($channels as $channel) {
            $handler = new TelegramBotHandler($this->core_config->log->webhook->telegram->apikey, $channel);
            $handler->setFormatter(new LineFormatter(null, $this->date_format));

            $this->handlers[] = $handler;
        }

        return $this;
    }


    /**
     * @param int              $level
     * @param string           $message
     * @param array|\Throwable $context
     * @return void
     * @throws MissingExtensionException
     * @throws \Exception
     */
    private function callEvent(int $level, string $message, array|\Throwable $context = []): void {

        $name = strtolower(Logger::getLevelName($level));

        if ( ! $this->handlers) {
            $this->setDefaultHandler();
            $this->subscription($level);
        }

        if ($context instanceof \Throwable) {
            $context = [
                'error_message' => $context->getMessage(),
                'file'          => $context->getFile(),
                'file_line'     => $context->getLine(),
                'trace'         => $context->getTraceAsString(),
            ];
        }


        $this->setHandlers($level);

        try {
            $this->logger->{$name}($message, $context);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        $this->clearHandlers();
    }


    /**
     * @return void
     * @throws \Exception
     */
    private function setDefaultHandler(): void {

        if ($this->files) {
            foreach ($this->files as $file) {
                $this->handlerFile($file);
            }
        }
    }


    /**
     * Запись лога в файл
     * @param string $filename
     * @return void
     */
    private function handlerFile(string $filename): void {

        $filename = str_starts_with($filename, '/')
            ? $filename
            : "{$this->logs_dir}/{$filename}";

        if ( ! $filename) {
            return;
        }

        $handler = new StreamHandler($filename);
        $handler->setFormatter(new LineFormatter(null, $this->date_format));

        $this->handlers[] = $handler;
    }


    /**
     * Удаление заданных ранее дополнительных обработчиков
     * @return void
     */
    private function clearHandlers(): void {

        $this->handlers = [];

        $this->logger->setHandlers([]);
    }


    /**
     * Установка обработчиков
     * @param int $level Уровень
     * @return void
     */
    private function setHandlers(int $level): void {

        foreach ($this->handlers as $handler) {
            if ($handler instanceof AbstractProcessingHandler) {
                $handler->setLevel($level);
                $this->logger->pushHandler($handler);
            }
        }
    }


    /**
     * Подписка на события
     * @param int $level
     * @return void
     * @throws MissingExtensionException
     */
    private function subscription(int $level): void {

        if ($this->core_config?->log?->subscribe?->level &&
            $this->core_config?->log?->subscribe?->recipients &&
            is_string($this->core_config->log->subscribe->level) &&
            is_string($this->core_config->log->subscribe->recipients)
        ) {
            $subscribe_levels = explode(',', $this->core_config->log->subscribe->level);
            $subscribe_levels = array_map('trim', $subscribe_levels);

            if ( ! in_array('all', $subscribe_levels)) {
                if ($level === Logger::ERROR && ! in_array('error', $subscribe_levels)) {
                    return;

                } elseif ($level === Logger::WARNING && ! in_array('warning', $subscribe_levels)) {
                    return;

                } elseif ($level === Logger::INFO && ! in_array('info', $subscribe_levels)) {
                    return;

                } elseif ($level === Logger::DEBUG && ! in_array('debug', $subscribe_levels)) {
                    return;
                }
            }


            $recipients = explode(',', $this->core_config->log->subscribe->recipients);
            $recipients = array_map('trim', $recipients);
            $recipients = array_filter($recipients);

            if ( ! empty($recipients)) {
                if (in_array('slack', $recipients)) {
                    $this->slack();
                }

                if (in_array('telegram', $recipients)) {
                    $this->telegram();
                }

                if (in_array('discord', $recipients)) {
                    $this->discord();
                }
            }
        }
    }
}