<?php

namespace Tests;
use PHPUnit\Framework\TestCase;


require_once __DIR__ . '/../../inc/classes/Db.php';
require_once __DIR__ . '/../../inc/classes/Acl.php';



/**
 * Class AclTest
 * @package Tests
 */
class AclTest extends TestCase {

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
    private $db;

    /**
     * @var \Zend_Acl_Role
     */

    protected $roleGuest;

    /**
     * @var \Acl
     */
    protected $acl_class;


    /**
     * @throws \Zend_Exception
     */
    public function setUp() {

        $config   = \Zend_Registry::get('config');
        $database = new \Db($config);

        $this->acl_class   = new \Acl();
        $this->acl         = new \Zend_Acl();
        $this->db          = $database->db;
        $this->auth        = \Zend_Registry::get('auth');
        $this->auth->ID    = 99;
        $this->auth->NAME  = 'test999';
        $this->auth->ROLE  = '99999';
        $this->auth->EMAIL = 'test@mail.com';
        $this->auth->ROOT  = false;
        $this->auth->SID   = \Zend_Session::getOptions('name');
        $this->auth->setExpirationHops(99, 'ACTION');
        $this->auth->ROLEID = '99999';

        require_once 'Zend/Acl/Role.php';

        //$roleguest = new \Zend_Acl_Role('guest');
        //$this->acl->addRole($roleguest);
        //$this->acl->addRole(new \Zend_Acl_Role('staff'), $roleguest);
        //$this->acl->allow('staff', null, array('edit', 'submit', 'revise'));
        //$this->acl->allow($roleguest, null, 'view');
        //$this->acl->addRole(new \Zend_Acl_Role('99999'), 'staff');
        //$this->acl->allow('99999', null, array('edit', 'submit', 'revise'));
    }


    public function tearDown() {

        $this->acl_class = null;
        $this->acl       = null;
        $this->auth      = null;
        $this->db        = null;

    }

    // тест функции инициализации уровней доступа
    // Получает уровни доступа из БД и свойства $addRes
    // и записывает в Zend_Registry

    public function test_setupAcl() {

        try {
            $ug_id    = 'test999';
            $old_auth = $this->auth;
            \Zend_Registry::set('auth', $this->auth);
            $ca_type = 'test999';
            $this->db->insert('core_roles', [
                'id'           => '99999',
                'name'         => 'test999',
                'is_active_sw' => 'Y',
                'lastuser'     => 99,
                'access'       => 'a:11:{s:7:"default";a:1:{s:6:"charts";s:2:"on";}s:12:"list_default";a:1:{s:6:"charts";s:2:"on";}s:12:"read_default";a:1:{s:6:"charts";s:2:"on";}s:12:"edit_default";a:1:{s:6:"charts";s:2:"on";}s:14:"delete_default";a:1:{s:6:"charts";s:2:"on";}s:6:"access";a:28:{s:5:"store";s:2:"on";s:14:"store-products";s:2:"on";s:10:"store-base";s:2:"on";s:15:"store-providers";s:2:"on";s:9:"store-out";s:2:"on";s:15:"store-remainder";s:2:"on";s:13:"store-journal";s:2:"on";s:7:"reports";s:2:"on";s:13:"reports-prods";s:2:"on";s:17:"reports-remainder";s:2:"on";s:15:"reports-analize";s:2:"on";s:13:"reports-money";s:2:"on";s:13:"reports-stats";s:2:"on";s:6:"config";s:2:"on";s:8:"calendar";s:2:"on";s:4:"kids";s:2:"on";s:6:"recipe";s:2:"on";s:2:"hr";s:2:"on";s:4:"enum";s:2:"on";s:9:"kids-diet";s:2:"on";s:11:"kids-groups";s:2:"on";s:10:"kids-tabel";s:2:"on";s:7:"kitchen";s:2:"on";s:11:"kitchen-ten";s:2:"on";s:17:"kitchen-printmenu";s:2:"on";s:12:"kitchen-menu";s:2:"on";s:13:"kitchen-norma";s:2:"on";s:13:"kitchen-print";s:2:"on";}s:8:"list_all";a:27:{s:5:"store";s:2:"on";s:14:"store-products";s:2:"on";s:10:"store-base";s:2:"on";s:15:"store-providers";s:2:"on";s:9:"store-out";s:2:"on";s:15:"store-remainder";s:2:"on";s:13:"store-journal";s:2:"on";s:7:"reports";s:2:"on";s:13:"reports-prods";s:2:"on";s:17:"reports-remainder";s:2:"on";s:15:"reports-analize";s:2:"on";s:13:"reports-money";s:2:"on";s:13:"reports-stats";s:2:"on";s:6:"config";s:2:"on";s:8:"calendar";s:2:"on";s:4:"kids";s:2:"on";s:6:"recipe";s:2:"on";s:4:"enum";s:2:"on";s:9:"kids-diet";s:2:"on";s:11:"kids-groups";s:2:"on";s:10:"kids-tabel";s:2:"on";s:7:"kitchen";s:2:"on";s:11:"kitchen-ten";s:2:"on";s:17:"kitchen-printmenu";s:2:"on";s:12:"kitchen-menu";s:2:"on";s:13:"kitchen-norma";s:2:"on";s:13:"kitchen-print";s:2:"on";}s:8:"read_all";a:27:{s:5:"store";s:2:"on";s:14:"store-products";s:2:"on";s:10:"store-base";s:2:"on";s:15:"store-providers";s:2:"on";s:9:"store-out";s:2:"on";s:15:"store-remainder";s:2:"on";s:13:"store-journal";s:2:"on";s:7:"reports";s:2:"on";s:13:"reports-prods";s:2:"on";s:17:"reports-remainder";s:2:"on";s:15:"reports-analize";s:2:"on";s:13:"reports-money";s:2:"on";s:13:"reports-stats";s:2:"on";s:6:"config";s:2:"on";s:8:"calendar";s:2:"on";s:4:"kids";s:2:"on";s:6:"recipe";s:2:"on";s:4:"enum";s:2:"on";s:9:"kids-diet";s:2:"on";s:11:"kids-groups";s:2:"on";s:10:"kids-tabel";s:2:"on";s:7:"kitchen";s:2:"on";s:11:"kitchen-ten";s:2:"on";s:17:"kitchen-printmenu";s:2:"on";s:12:"kitchen-menu";s:2:"on";s:13:"kitchen-norma";s:2:"on";s:13:"kitchen-print";s:2:"on";}s:8:"edit_all";a:27:{s:5:"store";s:2:"on";s:14:"store-products";s:2:"on";s:10:"store-base";s:2:"on";s:15:"store-providers";s:2:"on";s:9:"store-out";s:2:"on";s:15:"store-remainder";s:2:"on";s:13:"store-journal";s:2:"on";s:7:"reports";s:2:"on";s:13:"reports-prods";s:2:"on";s:17:"reports-remainder";s:2:"on";s:15:"reports-analize";s:2:"on";s:13:"reports-money";s:2:"on";s:13:"reports-stats";s:2:"on";s:6:"config";s:2:"on";s:8:"calendar";s:2:"on";s:4:"kids";s:2:"on";s:6:"recipe";s:2:"on";s:4:"enum";s:2:"on";s:9:"kids-diet";s:2:"on";s:11:"kids-groups";s:2:"on";s:10:"kids-tabel";s:2:"on";s:7:"kitchen";s:2:"on";s:11:"kitchen-ten";s:2:"on";s:17:"kitchen-printmenu";s:2:"on";s:12:"kitchen-menu";s:2:"on";s:13:"kitchen-norma";s:2:"on";s:13:"kitchen-print";s:2:"on";}s:10:"delete_all";a:27:{s:5:"store";s:2:"on";s:14:"store-products";s:2:"on";s:10:"store-base";s:2:"on";s:15:"store-providers";s:2:"on";s:9:"store-out";s:2:"on";s:15:"store-remainder";s:2:"on";s:13:"store-journal";s:2:"on";s:7:"reports";s:2:"on";s:13:"reports-prods";s:2:"on";s:17:"reports-remainder";s:2:"on";s:15:"reports-analize";s:2:"on";s:13:"reports-money";s:2:"on";s:13:"reports-stats";s:2:"on";s:6:"config";s:2:"on";s:8:"calendar";s:2:"on";s:4:"kids";s:2:"on";s:6:"recipe";s:2:"on";s:4:"enum";s:2:"on";s:9:"kids-diet";s:2:"on";s:11:"kids-groups";s:2:"on";s:10:"kids-tabel";s:2:"on";s:7:"kitchen";s:2:"on";s:11:"kitchen-ten";s:2:"on";s:17:"kitchen-printmenu";s:2:"on";s:12:"kitchen-menu";s:2:"on";s:13:"kitchen-norma";s:2:"on";s:13:"kitchen-print";s:2:"on";}s:7:"api_all";a:1:{s:2:"hr";s:2:"on";}}',
            ]);
            $ca_id = $this->db->lastInsertId();
            $this->db->insert('core_modules', [
                'm_id'      => '99999',
                'module_id' => 'test',
                'm_name'    => 'test999',
                'visible'   => 'Y',
                'lastuser'  => 99,
            ]);
            $ca_id1 = $this->db->lastInsertId();
            $this->db->insert('core_submodules', [
                'm_id'     => '99999',
                'sm_id'    => 999,
                'sm_name'  => 'test9999',
                'visible'  => 'Y',
                'lastuser' => 99,
            ]);
            $ca_id2 = $this->db->lastInsertId();

            $this->acl_class->setupAcl();

            $result_acl = \Zend_Registry::get('acl');
            $isAllow1   = $result_acl->isAllowed($ug_id, 'test', 'test999', 'Y', 99);

            //var_dump($ca_id);
            //var_dump($ca_id1);
            //var_dump($ca_id2);
            //var_dump($isAllow1);

            if ($isAllow1 == false) {
                //$this->fail(" is not correct");
            }

            $where = $this->db->quoteInto('m_id IN(?)', [$ca_id1]);
            $this->db->delete('core_modules', $where);

            $where = $this->db->quoteInto('m_id IN(?)', [$ca_id2]);
            $this->db->delete('core_submodules', $where);

            $where = $this->db->quoteInto('name = ?', $ug_id);
            $this->db->delete('core_roles', $where);


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
     */

    public function test_allow() {

        try {
            $allcl  = $this->acl_class->setupAcl();
            $allow1 = $this->acl_class->allow('Технолог', 'cron', 'Null');
            $allow2 = $this->acl_class->allow('fhdfghdfjh', 'cron', 'Null');
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if (isset($error) == "Role 'fhdfghdfjh' not found") {
            //$this->fail($error);
        }

    }


    /**
     * тест запрета доступа на использование ресурса
     * @see  Zend_Registry
     */

    public function test_deny() {

        try {
            $ug_id    = 'test999';
            $old_auth = $this->auth;
            \Zend_Registry::set('auth', $this->auth);
            $ca_type = 'test999';
            $this->db->insert('core_roles', [
                'id'           => '99999',
                'name'         => 'test999',
                'is_active_sw' => 'Y',
                'lastuser'     => 99,
            ]);
            $ca_id = $this->db->lastInsertId();

            $this->db->insert('core_modules', [
                'm_id'      => '99999',
                'module_id' => 'test',
                'm_name'    => 'test999',
                'visible'   => 'Y',
                'lastuser'  => 99,
            ]);
            $ca_id1 = $this->db->lastInsertId();
            $this->db->insert('core_submodules', [
                'm_id'     => '99999',
                'sm_id'    => 999,
                'sm_name'  => 'test9999',
                'visible'  => 'Y',
                'lastuser' => 99,
            ]);
            $ca_id2 = $this->db->lastInsertId();

            $this->acl_class->setupAcl();
            //$result_acl = \Zend_Registry::get('acl');
            //var_dump($result_acl->getRoles());
            //var_dump($result_acl->getResources());

            $deny = $this->acl_class->deny('test999', 'test', 'Null');
            //var_dump($deny);
            $result_acl = \Zend_Registry::get('acl');
            $isAllow1   = $result_acl->isAllowed($ug_id, 'test', 'test999', 'Y', 99);

            if ($isAllow1 == false) {
                //$this->fail(" is not correct");
            }
            $where = $this->db->quoteInto('m_id IN(?)', [$ca_id1]);
            $this->db->delete('core_modules', $where);

            $where = $this->db->quoteInto('m_id IN(?)', [$ca_id2]);
            $this->db->delete('core_submodules', $where);

            $where = $this->db->quoteInto('name = ?', $ug_id);
            $this->db->delete('core_roles', $where);


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
     */

    public function test_allowAll() {

        try {
            $allcl  = $this->acl_class->setupAcl();
            $allow1 = $this->acl_class->allowAll('Технолог', 'cron', 'Null');
            $allow2 = $this->acl_class->allowAll('fhdfghdfjh', 'cron', 'Null');
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if (isset($error) == "Role 'fhdfghdfjh' not found") {
            //$this->fail($error);
        }

    }


    /**
     * тест проверки доступа к ресурсу $source для текущей роли
     */
    public function test_checkAcl() {

        $old_auth          = $this->auth;
        $this->auth        = \Zend_Registry::get('auth');
        $this->auth->ID    = 1;
        $this->auth->NAME  = 'root';
        $this->auth->ROLE  = 'ALL';
        $this->auth->EMAIL = 'test@mail.com';
        $this->auth->ROOT  = true;
        $this->auth->SID   = \Zend_Session::getOptions('name');
        $this->auth->setExpirationHops(1, 'ACTION');
        \Zend_Registry::set('auth', $this->auth);
        $result_acl = \Zend_Registry::get('acl');

        $res = $this->acl_class->checkAcl('ALL', '');

        if ($res) {
            echo("Разрешено \n");
        }
        \Zend_Registry::set('auth', $old_auth);
        $old_auth          = $this->auth;
        $this->auth        = \Zend_Registry::get('auth');
        $this->auth->ID    = 1;
        $this->auth->NAME  = 'zzz';
        $this->auth->ROLE  = 'test';
        $this->auth->EMAIL = 'test@mail.com';
        $this->auth->ROOT  = false;
        $this->auth->SID   = \Zend_Session::getOptions('name');
        $this->auth->setExpirationHops(1, 'ACTION');
        \Zend_Registry::set('auth', $this->auth);
        $result_acl = \Zend_Registry::get('acl');
        $res        = $this->acl_class->checkAcl('ADFGADFGDAF', '');
        if ($res) {
            echo('Разрешено');
        } else {
            echo('Нет доступа к ресурсу');
        }
        \Zend_Registry::set('auth', $old_auth);

    }


}
