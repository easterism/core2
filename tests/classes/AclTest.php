<?php
/**
 * Created by PhpStorm.
 * User: BelskayaIG
 * Date: 20.05.15
 * Time: 12:23
 */
namespace Tests;
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../classes/Db.php';
require_once __DIR__ . '/../../classes/Acl.php';

/**
 * Class AclTest
 * @package Tests
 */
class AclTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Zend_Auth
     */
    protected $auth;

    /**
     * @var \Zend_Acl
     */

    protected $acl;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    static private $db;

    /**
     * @var \Zend_Acl_Role
     */

    protected $roleGuest;

    /**
     * @var \Acl
     */
    protected $acl_class;


    public function setUp()
    {
        $this->acl_class = new \Acl();
        $this->acl = new \Zend_Acl();
        $this->db = \Zend_Registry::get('db');

        $this->auth        = \Zend_Registry::get('auth');
        $this->auth->ID    = 1;
        $this->auth->NAME  = 'test';
        $this->auth->ROLE  = '99999';
        $this->auth->EMAIL = 'test@mail.com';
        $this->auth->ROOT  = false;
        $this->auth->SID   = \Zend_Session::getOptions('name');
        $this->auth->setExpirationHops(1, 'ACTION');

        require_once 'Zend/Acl/Role.php';

        //$roleguest = new \Zend_Acl_Role('guest');
        //$this->acl->addRole($roleguest);
        //$this->acl->addRole(new \Zend_Acl_Role('staff'), $roleguest);
        //$this->acl->allow('staff', null, array('edit', 'submit', 'revise'));
        //$this->acl->allow($roleguest, null, 'view');
        //$this->acl->addRole(new \Zend_Acl_Role('99999'), 'staff');
        //$this->acl->allow('99999', null, array('edit', 'submit', 'revise'));
    }

    public function tearDown()
    {
        $this->acl_class = null;
        $this->acl = null;
        $this->auth = null;
        $this->db = null;

    }

    // тест функции инициализации уровней доступа
    // Получает уровни доступа из БД и свойства $addRes
    // и записывает в Zend_Registry

    public function test_setupAcl() {
        try {
            $ug_id = '99999';
            $old_auth = $this->auth;
            \Zend_Registry::set('auth', $this->auth);
            $ca_type = 'deletemodule';
            $this->db->insert('cms_user_groups', array(
                'ug_id' => '99999',
                'ug_name' => 'test',
                'ug_desc' => null,
                'lastuser' => 1,
                'lastupdate' => 'Test',
            ));
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'deletemodule',
                'ca_key' => 'N',
            ));
            $ca_id1 = $this->db->lastInsertId();
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'installmodule',
                'ca_key' => 'N',
            ));
            $ca_id2 = $this->db->lastInsertId();
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'createpage',
                'ca_key' => 'Y',
            ));
            $ca_id3 = $this->db->lastInsertId();
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'cms',
                'ca_key' => 'storage',
            ));
            $ca_id4 = $this->db->lastInsertId();
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'cms',
                'ca_key' => 'modules',
            ));
            $ca_id5 = $this->db->lastInsertId();
            $this->acl_class->setupAcl();

            $result_acl = \Zend_Registry::get('acl');


            $isAllow1 = $result_acl->isAllowed( $ug_id, 'deletemodule');

            $isAllow2 = $result_acl->isAllowed( $ug_id, 'createpage','Y');

            $isAllow3 = $result_acl->isAllowed( $ug_id, 'installmodule','N');

            $isAllow4 = $result_acl->isAllowed( $ug_id, 'cms','storage');

            $isAllow5 = $result_acl->isAllowed( $ug_id, 'cms','modules');

            //var_dump($result_acl->getRoles());
            //var_dump($result_acl->getResources());
            //var_dump($isAllow1);
            //var_dump($isAllow2);
            //var_dump($isAllow3);
            //var_dump($isAllow4);
            //var_dump($isAllow5);

            if ($isAllow1 == false) {
                //$this->fail(" is not correct");
            }
            if ($isAllow2 == false) {
                //$this->fail(" is not correct");
            }
            if ($isAllow3 == false) {
                //$this->fail(" is not correct");
            }
            if ($isAllow4 == false) {
                //$this->fail(" is not correct");
            }
            if ($isAllow5 == false) {
                //$this->fail(" is not correct");
            }
        //} catch (\Exception $e) {
        //    $error = $e->getMessage();
        //}

        $where = $this->db->quoteInto('ca_id IN(?)', array($ca_id1, $ca_id2, $ca_id3, $ca_id4, $ca_id5));
        $this->db->delete('cms_access', $where);


        $where = $this->db->quoteInto('ug_id = ?', $ug_id);
        $this->db->delete('cms_user_groups', $where);


        \Zend_Registry::set('auth', $old_auth);
        } catch (\Exception $e) {
           $error = $e->getMessage();
        }
        if (isset($error)) {
            $this->fail($error);
        }
    }

    /**
     * тест разрешения доступа на использование ресурса
     * @see  Zend_Registry
     * @group ModStorage
     * @group ModStorageController
     * dataProvider provider_get_data_files
     * @param string $role
     * @param string $resource
     * @param string $type
     */

    public function test_allow()
    {

        $allcl = $this->acl_class->setupAcl();
        $allow = $this->acl_class->allow('92345345', 'ASDASD', '--');
// тест не пройден, данные в метод передаются согласно описанию string -  на выходе ошика.
//TODO HPUnit_Framework_Error : Argument 1 passed to Zend_Acl::add() must implement interface Zend_Acl_Resource_Interface, string given, called in Z:\rti-techno\admin\classes\Acl.php on line 160 and defined
        //  C:\libs\Zend\Acl.php:341
        //Z:\rti-techno\admin\classes\Acl.php:160
        //Z:\rti-techno\admin\tests\classes\AclTest.php:202

    }

    /**
     * тест запрета доступа на использование ресурса
     * @see  Zend_Registry
     * @group ModStorage
     * @group ModStorageController
     * @dataProvider provider_get_data_files
     * @param string $role
     * @param string $resource
     * @param string $type
     */

    public function test_deny($role, $resource, $type)
    {
        try {
            $ug_id = '99999';

            $old_auth = $this->auth;

            $this->auth->ROLE  = '99999';

            \Zend_Registry::set('auth', $this->auth);

            $this->db->insert('cms_user_groups', array(
                'ug_id' => '99999',
                'ug_name' => 'test',
                'ug_desc' => null,
                'lastuser' => 1,
                'lastupdate' => 'Test',
            ));
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'deletemodule',
                'ca_key' => 'N',
            ));
            $ca_id1 = $this->db->lastInsertId();
            $this->db->insert('cms_access', array(
                'ug_id' => '99999',
                'ca_type' => 'installmodule',
                'ca_key' => 'N',
            ));
            $ca_id2 = $this->db->lastInsertId();

            $this->acl_class->setupAcl();

            $deny = $this->acl->deny($role, $resource, $type);
            // тест корректно не работает. //TODO Zend_Acl_Role_Registry_Exception : Role '99999' not found

        } catch (\Exception $e)
        {
            $error = $e->getMessage();
        }

        $where = $this->db->quoteInto('ca_id IN(?)', array( $ca_id1, $ca_id2));
        $this->db->delete('cms_access', $where);

        $where = $this->db->quoteInto('ug_id = ?', $ug_id);
        $this->db->delete('cms_user_groups', $where);
        \Zend_Registry::set('auth', $old_auth);

        if (isset($error)) {
            $this->fail($error);
        }
    }

    /**
     * @return array
     */
    public function provider_get_data_files() {
        return array(
            // array('guest', 'createpage', 'Y'),
            // array('99999', 'cms', 'modules'),
            // array('99999', 'cms', 'storage'),
            array('99999', 'deletemodule', 'N')
        );
    }

    /**
     * тест проверки наличия доступа из CMS к странице созданной не средствами CMS
     *
     */
    public function test_isManagementAllowed() {
        // добавляем пользователя 99999 и новую страницу в базе cms_access с ca_type='management' и ca_key='C5' и ug_id='99999'.
        // и потом проверяем метод isManagementAllowed().
        $ug_id = '99999';

        $old_auth = $this->auth;

        $this->auth->ROLE  = '99999';

        \Zend_Registry::set('auth', $this->auth);

        $this->db->insert('cms_user_groups', array(
            'ug_id' => '99999',
            'ug_name' => 'test',
            'ug_desc' => null,
            'lastuser' => 1,
            'lastupdate' => 'Test',
        ));
        $this->db->insert('cms_access', array(
            'ug_id' => '99999',
            'ca_type' => 'management',
            'ca_key' => 'C5',
        ));
        $ca_id1 = $this->db->lastInsertId();

        $this->acl_class->setupAcl();

        $isManagementAllowed = $this->acl_class->isManagementAllowed($ca_id1);
        //$this->assertObjectHasAttribute('C5', $db_new, 'данной страницы не существует');

        if ($isManagementAllowed) {echo("\n".'страница НЕ доступна');}

        else {{echo("\n".'страница доступна');}}

        // после проверки удаляем пользователя 99999 и новую страницю в базе cms_access с ca_type='management' и ca_key='C5' и ug_id='99999'.
        $where = $this->db->quoteInto('ca_id IN(?)', array( $ca_id1));
        $this->db->delete('cms_access', $where);

        $where = $this->db->quoteInto('ug_id = ?', $ug_id);
        $this->db->delete('cms_user_groups', $where);
        \Zend_Registry::set('auth', $old_auth);
    }

    /**
     * тест проверки наличия доступа из CMS к странице созданой средствами CMS
     *
     */
    public function test_isPageAllowed() {
        // добавляем пользователя 99999 и новую страницу в базе cms_access с ca_type='menu' и ca_key='P135' и ug_id='99999'.
        // и потом проверяем метод isPageAllowed().
        $ug_id = '99999';

        $old_auth = $this->auth;

        $this->auth->ROLE  = '99999';

        \Zend_Registry::set('auth', $this->auth);

        $this->db->insert('cms_user_groups', array(
            'ug_id' => '99999',
            'ug_name' => 'test',
            'ug_desc' => null,
            'lastuser' => 1,
            'lastupdate' => 'Test',
        ));
        $this->db->insert('cms_access', array(
            'ug_id' => '99999',
            'ca_type' => 'menu',
            'ca_key' => 'P135',
        ));
        $ca_id1 = $this->db->lastInsertId();

        $this->acl_class->setupAcl();

        $isPageAllowed = $this->acl_class->isPageAllowed($ca_id1);

        if ($isPageAllowed) {echo("\n".'страница НЕ доступна');}

        else {{echo("\n".'страница доступна');}}

        // после проверки удаляем пользователя 99999 и новую страницю в базе cms_access с ca_type='menu' и ca_key='P135' и ug_id='99999'.
        $where = $this->db->quoteInto('ca_id IN(?)', array( $ca_id1));
        $this->db->delete('cms_access', $where);

        $where = $this->db->quoteInto('ug_id = ?', $ug_id);
        $this->db->delete('cms_user_groups', $where);
        \Zend_Registry::set('auth', $old_auth);

    }

    /**
     * тест проверки наличия доступа к компонентам CMS
     */
    public function test_isCoreAllowed() {
        // добавляем пользователя 99999 и новую страницу в базе cms_access с ca_type='logout' и ca_key='Y' и ug_id='99999'.
        // и потом проверяем метод isCoreAllowed().
        //в этом метоже проверяется работа метода checkAcl($source, $type)
        $ug_id = '99999';

        $old_auth = $this->auth;

        $this->auth->ROLE  = '99999';

        \Zend_Registry::set('auth', $this->auth);

        $this->db->insert('cms_user_groups', array(
            'ug_id' => '99999',
            'ug_name' => 'test',
            'ug_desc' => null,
            'lastuser' => 1,
            'lastupdate' => 'Test',
        ));
        $this->db->insert('cms_access', array(
            'ug_id' => '99999',
            'ca_type' => 'menu',
            'ca_key' => 'P135',
        ));
        $ca_id1 = $this->db->lastInsertId();

        $this->acl_class->setupAcl();

        $isCoreAllowed = $this->acl_class->isCoreAllowed($ca_id1);

        if ($isCoreAllowed) {echo("\n".'доступ запрещен');}

        else {echo("\n".'страница доступна');
            echo("\n".$isCoreAllowed);}

        // после проверки удаляем пользователя 99999 и новую страницю в базе cms_access c ca_type='logout' и ca_key='Y' и ug_id='99999'
        $where = $this->db->quoteInto('ca_id IN(?)', array( $ca_id1));
        $this->db->delete('cms_access', $where);

        $where = $this->db->quoteInto('ug_id = ?', $ug_id);
        $this->db->delete('cms_user_groups', $where);
        \Zend_Registry::set('auth', $old_auth);

    }


}
