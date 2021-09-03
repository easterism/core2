<?php
namespace Core2;

interface Auth {

    /**
     * @param string $login
     * @param string $password
     * @return int
     */
    public function authLdap(string $login, string $password): int;


    /**
     * @param string $code
     * @return int
     */
    public function authVk(string $code): int;


    /**
     * @param string $code
     * @return int
     */
    public function authFb(string $code): int;


    /**
     * @param string $code
     * @return int
     */
    public function authOk(string $code): int;
}