core2
=====
PHP framework for business application.

NOTE: Currently it's russian framework. So you'll get no way to translate any inner locutions. In the future the translator will be available as a system module.

Usage
=====
1. Put the sources inside *core2* folder anywhere on your server.
2. Create MySQL schema with *db.sql*
3. Create index.php file. Make sure that *core2* folder is available from its place.
 ```php
  try {
  	require_once("core2/inc/classes/Error.php");
  	require_once("core2/inc/classes/Init.php");
 
  	$init = new Init();
  	$init->checkAuth();
 
  	echo $init->dispatch();
  } catch (Exception $e) {
  	Error::catchException($e);
  }
 ```
4. Create *conf.ini* file near the index.php
 ```ini
  [production]
  database.params.host      = localhost
  database.params.port      = 3306
  database.params.dbname    = <database name>
  database.params.username  = <username>
  database.params.password  = <password>
 ```
5. Create *admin* user with the same password.
 ```sql
  INSERT INTO `core_users` (`u_login`, `u_pass`, `visible`, `is_admin_sw`) VALUES ('admin', 'ad7123ebca969de21e49c12a7d69ce25', 'Y', 'Y');
  ```
