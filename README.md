core2
=====
PHP Business application framework.

NOTE: Currently it's russian framework. So you'll get no way to translate any inner locutions. In the future the translator will be available as a system module.

Usage
=====
1. Put the *core2* folder anywhere on your server. 
2. Create index.php file. Make sure that *core2* folder is available from its place.
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
