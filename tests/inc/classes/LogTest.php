<?php
/**
 * Created by PhpStorm.
 * User: GrinkevichVM
 * Date: 03.04.2018
 * Time: 11:43
 */

namespace Tests;

use Core2\Log;
use PHPUnit\Framework\TestCase;

require_once DOC_ROOT . 'core2/inc/classes/Log.php';

class LogTest extends TestCase
{

    /**
     *
     */
    public function test__construct()
    {
        $this->assertTrue(true,true);
    }

    public function test__call()
    {
        $this->assertTrue(true,true);
    }

    public function testFile()
    {
        $this->assertTrue(true,true);
    }

    public function testAccess()
    {
        $this->assertTrue(true,true);
    }

    public function testInfo()
    {
        $this->assertTrue(true,true);
    }

    public function testWarning()
    {
        $this->assertTrue(true,true);
    }

    public function testDebug()
    {
        $this->assertTrue(true,true);
    }
}
