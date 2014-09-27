core2
=====
PHP Business application framework.

NOTE: Currently it's russian framework. So you'll get no way to translate any inner locutions. In the future the translator will be available as a system module.

Usage
=====
```php
 header('Content-Type: text/html; charset=utf-8');
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
