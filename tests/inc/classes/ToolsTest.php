<?php

namespace Tests;
use PHPUnit\Framework\TestCase;


require_once DOC_ROOT . 'core2/inc/classes/Tool.php';
require_once DOC_ROOT . 'core2/inc/classes/Error.php';


/**
 * Class ToolsTest
 * @package Tests
 */
class ToolsTest extends TestCase {

    /**
     * @var \Tool
     */
    protected $tools;


    public function setUp() {

        $this->tools = new \Tool();
    }


    public function tearDown() {

        $this->tools = null;
    }


    /**
     * тест проверки на существование файла
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerFile
     * @param string $filen
     */
    public function test_file_exists_ip($filen) {

        $filename = DOC_ROOT . $filen;
        if (\Tool::file_exists_ip($filename)) {
            $this->fail(" Отсутствует файл " . $filename);
        }
    }


    /** массив тестовых случаев корректных email
     * @return array
     */
    public function providerFile() {

        return [
            ['core2/tests/classes/AlertTest.php'],
            ['core2\\tests\\Init.php'],
        ];
    }


    /**
     * тест HTTP аутентификации  *
     */
    public function test_httpAuth() {

        $_SERVER['PHP_AUTH_DIGEST'] = "Basic QWxhZGRpbjpvcGVuIHNlc2FtZQ==";
        //$_SERVER['HTTP_AUTHORIZATION']='Basic QWxhZGRpbjpvcGVuIHNlc2FtZQ==';
        //$_SERVER['PHP_AUTH_DIGEST']="Basic RGlnZXN0IHVzZXJuYW1lPSdiZWxza2F5YWlnJyxyZWFsbT0ndGVzdHJlYWxtQGJlbGhhcmQuY29tJywNCiAgICAgICAgICAgICAgICAgbm9uY2U9J2RjZDk4YjcxMDJkZDJmMGU4YjExZDBmNjAwYmZiMGMwOTMnLA0KICAgICAgICAgICAgICAgICB1cmk9J0RPQ19ST09UIC4gJ1xodG1sXGJvb3RzdHJhcFxlZGl0XG1vZGFsMi5odG1sJywNCiAgICAgICAgICAgICAgICAgcW9wPWF1dGgsDQogICAgICAgICAgICAgICAgIG5jPTAwMDAwMDAxLA0KICAgICAgICAgICAgICAgICBjbm9uY2U9JzBhNGYxMTNiJywNCiAgICAgICAgICAgICAgICAgcmVzcG9uc2U9JzY2MjlmYWU0OTM5M2EwNTM5NzQ1MDk3ODUwN2M0ZWYxJywNCiAgICAgICAgICAgICAgICAgb3BhcXVlPSc1Y2NjMDY5YzQwM2ViYWY5ZjAxNzFlOTUxN2Y0MGU0MSc";
        //$_SERVER['HTTP_AUTHORIZATION']="Digest username='belskayaig',
        //         realm='testrealm@belhard.com',
        //         nonce='dcd98b7102dd2f0e8b11d0f600bfb0c093',
        //         uri='DOC_ROOT . '\html\bootstrap\edit\modal2.html',
        //         qop=auth,
        //         nc=00000001,
        //         cnonce='0a4f113b',
        //         response='6629fae49393a05397450978507c4ef1',
        //         opaque='5ccc069c403ebaf9f0171e9517f40e41'";

        $users = [
            ['username' => 'Aladdin', 'password' => 'open sesame']
        ];
        //$realm = array('username' => 'bbb', 'password' => 'bbb');
        $realm = ['username' => 'Aladdin', 'password' => 'open'];
        if ($code = \Tool::httpAuth($realm, $users)) {
            if ($code == 1) \Core2\Error::Exception("Неверный пользователь.");
            if ($code == 2) \Core2\Error::Exception("Неверный пароль.");
        }
    }


    /** проверка функции logToFile($text) - записываем в тестовый лог сообщение (идет создание файла)
     *  потом удаляем файл
     */
    public function test_logToFile() {

        $dir      = __DIR__ . "\\fdir";
        $filename = $dir . '\\test123.txt';
        $text     = 'сообщение пользователю 1';
        //echo("\n $filename");
        $fh = $this->tools->logToFile($filename, $text);
        unlink($filename);
    }


    /** проверка функции дозаписи в файл сообщений
     *  потом удаляем файл
     */
    public function test_dataToFile() {

        $dir      = __DIR__ . "\\fdir";
        $filename = $dir . '\\test123.txt';
        $text     = 'сообщение пользователю 1';
        //echo("\n $filename");
        $fh    = $this->tools->logToFile($filename, $text);
        $text1 = 'сообщение пользователю 222';
        $fh    = $this->tools->dataToFile($filename, $text1);
        $text2 = 'сообщение пользователю 333';
        $fh    = $this->tools->dataToFile($filename, $text2);
        $text3 = 'сообщение пользователю 444';
        $fh    = $this->tools->dataToFile($filename, $text3);
        unlink($filename);
    }


    /**
     * проверка функции log($text) - записываем в тестовый лог сообщение (идет создание файла)
     *  потом читаем его и сравниваем строки между собой и удаляем файл
     * реализовано с помощью методов PHPUnit
     */
    public function test_Log() {

        $cnf          = \Zend_Registry::get('config');
        $cnf->log->on = true;
        $text         = 'сообщение пользователю 2';
        $fn           = $this->tools->log($text);
        //echo("\n $fn");
        $filename = __DIR__ . "\\fdir\\test_log.txt";
        $this->assertStringNotEqualsFile($filename, $text, "{$text}, и {$fn} не сравнилось");
        //unlink($filename);
    }


    /**
     * тест добавления пробела в число через каждые 3 символа
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerNumber
     * @param string $Numb
     */
    public function test_commafy($Numb) {

        $fn = $this->tools->commafy($Numb);
        if ( ! \Tool::commafy($Numb)) {
            $this->fail(" тест не пройден " . $Numb . " для такой строки не работает");
        }
    }


    /** массив тестовых случаев чисел
     * @return array
     */
    public function providerNumber() {

        return [
            ['123456789012345678901234567890'],
            ['111'],
            ['ффф111'],
            ['ццццццццц'],
            ['0'],
        ];
    }


    /**
     * тест пропущен из-за сложности:
     * firebag формирует заголовок, который не пропускает phpunit - выпадает ошибка
     */
    public function test_fb() {

        $this->markTestSkipped('firebag формирует заголовок, который не пропускает phpunit - выпадает ошибка');

    }


    /**
     * проверка хеширования пароля
     */
    public function test_pass_salt() {

        $text = 'пароль';
        $fn   = $this->tools->pass_salt($text);
    }


    /**
     * тест изменения формата даты в ссоответствии с шаблоном     *
     */
    public function test_date_ru() {

        $formatum  = 'д';
        $timestamp = mktime(0, 0, 0, 4, -31, 2000);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'вторник') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'в';
        $timestamp = mktime(0, 0, 0, 1, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'четверг') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'Д';
        $timestamp = mktime(0, 0, 0, 1, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'Четверг') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'В';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'Воскресенье') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'к';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'вс') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'К';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'Вс') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'м';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'февраля') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'М';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'Февраля') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'И';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'Февраль') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'л';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn");
        if ($fn != 'фев') {
            $this->fail(" тест не пройден " . $fn);
        }
        $formatum  = 'Л';
        $timestamp = mktime(0, 0, 0, 2, 1, 2015);
        $fn        = $this->tools->date_ru($formatum, $timestamp);
        //echo("\n $fn \n");
        if ($fn != 'Фев') {
            $this->fail(" тест не пройден " . $fn);
        }
    }


    /**
     * тест функции склонения числительных в русском языке
     */
    public function test_declNum() {

        $number = 360;
        $titles = [
            'триста шестьдесят',
            'трехсот шестидесяти',
            'тремстам шестидесяти',
            'триста шестьдесят',
            'тремястами шестьюдесятью',
            '(о) трехстах шестидесяти'
        ];
        $fn     = $this->tools->declNum($number, $titles);
        //echo("\n $fn");
        if (trim($fn) != trim('360 тремстам шестидесяти')) {
            $this->fail("1 тест не пройден ");
        }
        $number = 40;
        $titles = ['сорок', 'сорока', 'сорока', 'сорок', 'сорока', '(о) сорока'];
        $fn     = $this->tools->declNum($number, $titles);
        //echo("\n $fn");
        if ($fn != '40 сорока') {
            $this->fail("2 тест не пройден ");
        }
        $number = 5;
        $titles = ['пять', 'пяти', 'пяти', 'пять', 'пятью', '(о) пяти'];
        $fn     = $this->tools->declNum($number, $titles);
        //echo("\n $fn");
        if ($fn != '5 пяти') {
            $this->fail("3 тест не пройден ");
        }

    }


    /**
     * тест определения кодировки
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerString
     * @param string $str
     */
    public function test_detect_encoding($str) {

        $pattern_size = 50;
        $fn           = $this->tools->detect_encoding($str, $pattern_size);
        //  echo("\n $fn \n");
    }


    /** массив тестовых случаев чисел
     * @return array
     */
    public function providerString() {

        return [
            ['Stroka'],
            ["абвгдеёжзийклмнопрстуфхцчшщъыьэюя"], //Русский алфавит в кодировке windows-1251
            ["ЮАБЦДЕ╦ФГХИЙКЛМНОПЯРСТУЖВЬЫЗШЭЩЧЪ"],//Русский алфавит в кодировке koi8-r, koi8-u, iso-ir-111
            ['рстуфхИцчшщъыьэюя№ёђѓєѕіїјљњћќ§ўџ'],//Русский алфавит в кодировке iso-8859-5
            ["рстуфх╕цчшщъыьэюяЁёЄєЇїЎў°∙·√№¤■"],//Русский алфавит в кодировке x-cp866
            ["ЯрРсСтИТуУжЖвВьЬ№­ыЫзЗшШэЭщЩчЧ§■"],//Русский алфавит в кодировке ibm855
            ['абвгдеЄжзийклмнопрстуфхцчшщъыьэю€'],//Русский алфавит в кодировке x-mac-cyrillic, x-mac-ukrainian
        ];
    }


    public function test_getRequestHeaders() {

        $head = [];
        $fn   = $this->tools->getRequestHeaders();
        if ($fn != $head) {
            $this->fail(" тест не пройден " . $fn);
        }
    }


    public function test_execInBackground() {

        {
            $this->markTestSkipped('пропущен на данное время-данный метод нужен для работы под linux');

        }
    }


    /**
     * тест метода кодирования строки с использованием MIME base64
     */
    public function test_base64url_encode() {

        //$Data = "Digest username='belskayaig',realm='testrealm@belhard.com',
        //        nonce='dcd98b7102dd2f0e8b11d0f600bfb0c093',
        //         uri='DOC_ROOT . '\html\bootstrap\edit\modal2.html',
        //         qop=auth,
        //         nc=00000001,
        //         cnonce='0a4f113b',
        //         response='6629fae49393a05397450978507c4ef1',
        //         opaque='5ccc069c403ebaf9f0171e9517f40e41'";

        $Data = 'Строка для кодирования /123№+456_-_';
        $fn   = $this->tools->base64url_encode($Data);
        echo("\n $fn \n");
        if ($fn != "0KHRgtGA0L7QutCwINC00LvRjyDQutC-0LTQuNGA0L7QstCw0L3QuNGPIC8xMjPihJYrNDU2Xy1f") {
            $this->fail(" тест не пройден - кодирование для " . $Data . " не работает");
        }
    }


    /**
     * тест метода декодирования строки с использованием MIME base64
     */
    public function test_base64url_decode() {

        //$Data = 'QWxhZGRpbjpvcGVuIHNlc2FtZQ==';
        $Data = '0KHRgtGA0L7QutCwINC00LvRjyDQutC-0LTQuNGA0L7QstCw0L3QuNGPIC8xMjPihJYrNDU2Xy1f';
        $fn   = $this->tools->base64url_decode($Data);
        //echo("\n $fn \n");
        if ($fn != "Строка для кодирования /123№+456_-_") {
            $this->fail(" тест не пройден - декодирование для " . $Data . " не работает");
        }
    }


    /* тест печати ссылки на css файл
     */
    public function test_printCss() {

        $filename = DOC_ROOT . "core2\\html\\bootstrap\\css\\bootstrap.modal.min.css";
        $fn       = $this->tools->printCss($filename);
    }


    /* тест печати- ссылки на js файл
     */
    public function test_printJs() {

        $filename = DOC_ROOT . "core2\\html\\bootstrap\\js\\js.js";
        $fn       = $this->tools->printJs($filename);
    }


    /** тест функции сортировки
     */
    public function test_arrayMultisort() {

        $type = SORT_ASC;
        $a    = [
            ['service' => 1, 'number' => 5, 'name' => 'five'],
            ['service' => 2, 'number' => 4, 'name' => 'four'],
            ['service' => 1, 'number' => 1, 'name' => 'one'],
            ['service' => 7, 'number' => 3, 'name' => 'three'],
            ['service' => 1, 'number' => 6, 'name' => 'six'],
            ['service' => 10, 'number' => 2, 'name' => 'two'],
            ['service' => 11, 'number' => 15, 'name' => 'fifteen'],
            ['service' => 13, 'number' => 12, 'name' => 'twelve'],
            ['service' => 14, 'number' => 11, 'name' => 'eleven'],
            ['service' => 27, 'number' => 13, 'name' => 'thirteen'],
            ['service' => 21, 'number' => 16, 'name' => 'sixteen'],
        ];
        $art  = $this->tools->arrayMultisort($a, 'number', $type);
        foreach ($art as $key => $value) {
            //echo $key.": ".$value['service'].": ".$value['number'].": ".$value['name']."\n";
        }
        $type = SORT_DESC;
        $art  = $this->tools->arrayMultisort($a, 'number', $type);

        foreach ($art as $key => $value) {
            //echo $key.": ".$value['service'].": ".$value['number'].": ".$value['name']."\n";
        }
    }


    /* тест проверки является ли клиентское устройство мобильным
     */
    public function test_isMobileBrowser() {

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/4.5 [en] (X11; U; Linux 2.2.9 i586).';
        $fn                         = $this->tools->isMobileBrowser();
        //var_dump($fn);
        if ($fn) {
            $this->fail(" тест не пройден -  не мобильный браузер");
        }
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; U; Android 2.3.5; en-gb; HTC Desire HD A9191 Build/GRJ90) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1';
        $fn                         = $this->tools->isMobileBrowser();
        //var_dump($fn);
        if ( ! $fn) {
            $this->fail(" тест не пройден -  мобильный браузер");
        }
        $_SERVER['HTTP_USER_AGENT'] = 'KDDI-KC31 UP.Browser/6.2.0.5 (GUI) MMP/2.0';
        $fn                         = $this->tools->isMobileBrowser();
        //var_dump($fn);
        if ( ! $fn) {
            $this->fail(" тест не пройден -   мобильный браузер");
        }
        $_SERVER['HTTP_USER_AGENT'] = 'BenQ-CF61/1.00/WAP2.0/MIDP2.0/CLDC1.0 UP.Browser/6.3.0.4.c.1.102 (GUI) MMP/2.0';
        $fn                         = $this->tools->isMobileBrowser();
        //var_dump($fn);
        if ( ! $fn) {
            $this->fail(" тест не пройден -   мобильный браузер");
        }
    }


    /* тест суммы прописью
     *
     */
    public function test_num2str() {

        $num = 9987234007;
        $art = $this->tools->num2str($num);
        if (trim($art) != trim("девять миллиардов девятьсот восемьдесят семь миллионов двести тридцать четыре тысячи семь")) {
            $this->fail(" тест не пройден ");
        };
    }


    /** тест функции проверки некорректности введенного запроса
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerdoCurlRequest
     * @param string $what , $where
     */
    public function test_doCurlRequest($where, $what) {

        $is_curlCheck = $this->tools->doCurlRequest($where, $what, $casesensitive = false);
        //var_dump( $is_curlCheck);
        if ( ! $is_curlCheck) {
            $this->fail("{$what}, на {$where} is not correct");
        }
    }


    /** массив тестовых случаев
     * @return array
     */
    public function providerdoCurlRequest() {

        return [
            ['http://google.ru', 'пример']
        ];
    }
}

