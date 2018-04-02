<?php

namespace Tests;
use PHPUnit\Framework\TestCase;


require_once DOC_ROOT . 'core2/inc/classes/Alert.php';


/**
 * Class AlertTest
 * @package Tests
 */
class AlertTest extends TestCase {

    /**
     * @var \Alert
     */
    protected $alert;


    public function setUp() {

        $this->alert = new \Alert();
    }


    public function tearDown() {

        $this->alert = null;
    }


    /**
     * тест возвращения сообщения об успешном выполнении
     * @param string $str
     * @dataProvider providerShow
     */
    public function testgetSuccess($str) {

        $string = $this->alert->getSuccess($str);
        $this->assertInternalType('string', $string, "{$string} is not correct");
    }


    /** массив тестовых случаев сообщений
     * @return array
     */
    public function providerShow() {

        return [
            ['выполните команду!'],
            ['124124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver'],
        ];
    }


    /**
     * тест распечатки сообщения об успешном выполнении
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerPrint
     * @param string $str
     */
    public function testprintSuccess($str) {

        \Alert::printSuccess($str);
        if ( ! "<div class=\"alert alert-success\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /** массив тестовых случаев сообщений
     * @return array
     */
    public function providerPrint() {

        return [
            ['!'],
            ['расчет выполнен!данные занесены'],
            ['124124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver24124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver24124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver'],
        ];
    }


    /**
     * тест возврата сообщения с информацией
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testgetInfo($str) {

        \Alert::getInfo($str);
        if ( ! "<div class=\"alert alert-info\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /** массив тестовых случаев сообщений
     * @return array
     */
    public function providerGet() {

        return [
            ['!'],
            ['расчет выполнен!данные занесены'],
            ['124124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver24124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver24124545745875dfghjdhjkfghjs@dsdgsdgjrtt7i76iogbhmcvb.ver'],
        ];
    }


    /**
     * тест распечатки сообщения с информацией
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testprintInfo($str) {

        \Alert::printInfo($str);
        if ( ! "<div class=\"alert alert-info\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /**
     * тест возврата сообщения с предупреждением
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testgetWarning($str) {

        \Alert::getWarning($str);
        if ( ! "<div class=\"alert alert-warning\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /**
     * тест распечатки сообщения с предупреждением
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testprintWarning($str) {

        \Alert::printWarning($str);
        if ( ! "<div class=\"alert alert-warning\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /**
     * тест возврата сообщения об ошибке или опасности
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testgetDanger($str) {

        \Alert::getDanger($str);
        if ( ! "<div class=\"alert alert-danger\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }


    /**
     * тест распечатки сообщения об ошибке или опасности
     * @group        ModStorage
     * @group        ModStorageMain
     * @dataProvider providerGet
     * @param string $str
     */
    public function testprintDanger($str) {

        \Alert::printDanger($str);
        if ( ! "<div class=\"alert alert-danger\">{$str}</div>") {
            $this->fail("{$str} is not correct");
        }
    }
} 