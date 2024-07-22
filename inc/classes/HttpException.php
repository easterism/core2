<?php
namespace Core2;


/**
 * Class HttpException
 * @package Core2
 */
class HttpException extends \Exception {

    protected $error_code;


    /**
     * HttpException constructor.
     * @param string          $message
     * @param string          $error_code
     * @param int|string|null $http_code
     */
    public function __construct(string $message, string $error_code, int $http_code = 400) {

        parent::__construct($message, $http_code);
        $this->error_code = $error_code;
    }

    /**
     *
     * @return int|string|null
     */
    public function getErrorCode() {
        return $this->error_code;
    }
}