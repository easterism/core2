core2
=====
PHP framework for business applications.

Minimum Server Requirements
---------------------------

* PHP 7.4 or greater
* PDO PHP extension
* MySQL or PostgreSQL Database
* composer for dependencies


Installation
------------
1. Put the source code into *core2* folder anywhere on your server.
2. Create MySQL schema with [db.sql](db.sql)
3. Create *admin* user with the same password.
 ```sql
  INSERT INTO `core_users` (`u_login`, `u_pass`, `visible`, `is_admin_sw`, `date_added`) VALUES ('admin', 'ad7123ebca969de21e49c12a7d69ce25', 'Y', 'Y', NOW());
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

Support
-------
<img src="phpStorm.png"/>
<a href="https://www.jetbrains.com/phpstorm/" target="_blank">PhpStorm</a>
