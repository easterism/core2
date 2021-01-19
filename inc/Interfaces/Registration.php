<?php


/**
 * Дает возможность регистрировать пользователей, используя форму регистрации ядра
 * Interface Registration
 */
interface Registration {

    /**
     * Регистрация пользователей
     * @param array $data Поля отправленные формой регистрации
     * @return string Нужно вернуть короткое текстовое описание успеха регистрации
     * @throws \Exception Необходимо бросить исключение с описанием проблемы, если что-то пошло не так
     */
    public function coreRegistration(array $data);


    /**
     * Завершение регистрации пользователей
     * @param string $key      Ключ идентификации пользователя
     * @param string $password Пароль пользователя
     * @return string Нужно вернуть короткое текстовое описание успеха регистрации
     * @throws \Exception Необходимо бросить исключение с описанием проблемы, если что-то пошло не так
     */
    public function coreRegistrationComplete($key, $password);
}