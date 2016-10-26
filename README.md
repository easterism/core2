core2
=====
PHP framework for business application.

NOTE: Currently it's russian framework. So you'll get no way to translate any inner locutions. In the future the translator will be available as a system module.

Minimum Server Requirements
---------------------------

* PHP 5.2 or greater (Zend Framework library in include_path)
* PDO PHP extension
* MySQL or PostgreSQL Database


Installation
------------
1. Put the sources inside *core2* folder anywhere on your server.
2. Create MySQL schema with [db.sql](db.sql)
3. Create *admin* user with the same password.
 ```sql
  INSERT INTO `core_users` (`u_login`, `u_pass`, `visible`, `is_admin_sw`) VALUES ('admin', 'ad7123ebca969de21e49c12a7d69ce25', 'Y', 'Y');
  ```

4. Create *index.php* file anywhere inside the document root. Make sure that *core2* folder is available from its place.
 ```php
  try {
  	require_once("core2/inc/classes/Error.php");
  	require_once("core2/inc/classes/Init.php");
 
  	$init = new Init();
  	$init->checkAuth();
 
  	echo $init->dispatch();
  } catch (Exception $e) {
  	\Core2\Error::catchException($e);
  }
 ```
5. Create *conf.ini* file near the index.php
 
 ```ini
  [production]
  database.params.host      = localhost
  database.params.port      = 3306
  database.params.dbname    = <database name>
  database.params.username  = <database user>
  database.params.password  = <user password>
 ```

Usage
-----
Open URL of new index.php file in your browser. Use 'admin' username and 'admin' password.
