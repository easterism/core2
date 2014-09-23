<?
require_once 'Tool.php';

class Error {

	/**
	 * Global error exception
	 *
	 * @param exception $exception
	 */
	public static function catchException(Exception $exception) {
		$isXajax = false;
		if (!empty($_POST['xjxr'])) {
			$isXajax = true;
		}
		$message = $exception->getMessage();
		$code = $exception->getCode();
		if ($code == 99) {
			die($message);
		}
		if (!Tool::file_exists_ip("/Zend/Registry.php")) {
			throw new Exception("SYSTEM:Требуется ZF компонент \"Registry\"");
		}
		require_once("Zend/Registry.php");
		try {
			$cnf = Zend_Registry::get('config');
		} catch (Zend_Exception $e) {
			die($e->getMessage());
		}
		
		if ($cnf->log && $cnf->log->on && $cnf->log->path) {
			$trace = $exception->getTraceAsString();
			$str = date('d-m-Y H:i:s') . ' ERROR: ' . $message . "\n" . $trace . "\n\n\n";
			$f = fopen($cnf->log->path, 'a');
			fwrite($f, $str . chr(10) . chr(13));
			fclose($f);
		}
		if ($message == '911') {
			$text = 'Доступ закрыт! Если вы уверены, что вам сюда можно, обратитесь к администратору.';
			if ($isXajax) {
				header('Content-type:	text/xml; charset="utf-8"');
				echo '<?xml version="1.0" encoding="utf-8" ?><xjx><cmd n="js">alert(\'' . $text . '\');top.document.location=\'index.php\';</cmd></xjx>';
			} else {
				echo $text;
			}
			die();
		} elseif ($message == '404') {
			echo 'Нет такой страницы';
			die();
		} elseif ($code == '13') { //ошибки для js объекта с наличием error
			echo json_encode(array("error" => $message));
			die();
		} elseif ($message == 'expired') {
			header('HTTP/1.1 203 Non-Authoritative Information');
			die();
		}
		if (substr($message, 0, 7) == 'SYSTEM:') {
			header('Content-type:	text/xml; charset="utf-8"');
			echo substr($message, 7);
			die();
		}

		//Zend_Registry::get('logger')->log(__METHOD__ . " " . $str, Zend_Log::ERR);
		if ($cnf->debug->on) {
			$trace = $exception->getTraceAsString();
			$str = date('d-m-Y H:i:s') . ' ERROR: ' . $message . "\n" . $trace . "\n\n\n";
			echo "<PRE>";print_r($str);echo"</PRE>";
		} else {
			die($message);
		}
	}

	public static function catchDbException($exception) {
		$message = $exception->getMessage();
		$code = $exception->getCode();
		if ($code == 1044) {
			die('Нет доступа к базе данных.');
		} elseif ($code == 2002) {
			die('Не верный адрес базы данных.');
		} elseif ($code == 1049) {
			die('Нет соединения с базой данных.');
		} else {
			Error::catchException($exception);
		}

	}

	public static function catchLoginException($exception)
	{
		$message = $exception->getMessage();
		$code = $exception->getCode();
		if ($code == 1044) {
			return 'Нет доступа к базе данных.';
		} elseif ($code == 2002) {
			return 'Не верный адрес базы данных.';
		} elseif ($code == 1049) {
			return 'Нет соединения с базой данных.';
		} else {
			return $message;
		}
	}

	public static function catchZendException($exception) {
		$message = $exception->getMessage();
		$code = $exception->getCode();
		Error::catchException($exception);
	}

	public static function catchZendConfigException($exception) {
		$message = $exception->getMessage();
		$code = $exception->getCode();
		die($message);
	}

	public static function catchXajax(Exception $e, xajaxResponse $res) {
		$res->alert($e->getMessage());
		return $res;
	}
	
}