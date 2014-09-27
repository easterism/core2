<?
header('Content-Type: text/html; charset=utf-8');
require_once 'Tool.php';

class Error {

	/**
	 * Global error exception
	 *
	 * @param exception $exception
	 */
	public static function Exception($msg, $code = '') {
		$isXajax = self::isXajax();
		if ($isXajax) {
			header('Content-type: text/xml; charset="utf-8"');
			echo '<?xml version="1.0" encoding="utf-8" ?><xjx><cmd n="js">alert(\'' . $msg . '\');top.document.location=\'index.php\';</cmd></xjx>';
		} else {
			if ($code == 13) {//ошибки для js объекта с наличием error
				echo json_encode(array("error" => $msg));
			} else {
				echo $msg;
			}
		}
		die;
	}

	protected static function isXajax() {
		$isXajax = false;
		if (!empty($_POST['xjxr'])) {
			$isXajax = true;
		}
		return $isXajax;
	}

	public static function catchException(Exception $exception) {
		$message = $exception->getMessage();
		$code = $exception->getCode();

		// Zend_Registry MUST present
		try {
			$cnf = Zend_Registry::get('config');
		} catch (Zend_Exception $e) {
			self::Exception($e->getMessage(), $code);
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
			self::Exception($text, $code);
		} elseif ($message == '404') {
			self::Exception('Нет такой страницы', $code);
		} elseif ($message == 'expired') {
			header('HTTP/1.1 203 Non-Authoritative Information');
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
		$code = $exception->getCode();
		if ($code == 1044) {
			$message = 'Нет доступа к базе данных.';
		} elseif ($code == 2002) {
			$message = 'Не верный адрес базы данных.';
		} elseif ($code == 1049) {
			$message = 'Нет соединения с базой данных.';
		} else {
			$message = $exception->getMessage();
		}
		self::Exception($message, $code);

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
		self::Exception($message, $code);
	}

	public static function catchXajax(Exception $e, xajaxResponse $res) {
		$res->alert($e->getMessage());
		return $res;
	}
	
}