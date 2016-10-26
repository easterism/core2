<?
namespace Core2;

require_once 'Tool.php';
require_once "Zend/Registry.php";


/**
 * Class Error
 */
class Error {

    /**
     * Global error exception
     * @param string $msg
     * @param int $code
     * @return string|void
     */
	public static function Exception($msg, $code = 0) {
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

	/**
	 * Основной обработчик исключений
	 *
	 * @param \Exception $exception
	 */
	public static function catchException(\Exception $exception) {
        $cnf     = self::getConfig();
        $message = $exception->getMessage();
        $code    = $exception->getCode();

		if ($cnf->log && $cnf->log->on && $cnf->log->path) {
            if ((file_exists($cnf->log->path) && is_writable($cnf->log->path)) ||
                ( ! file_exists($cnf->log->path) && is_dir(basename($cnf->log->path)) && is_writable(basename($cnf->log->path)))
            ) {
                $trace = $exception->getTraceAsString();
                $str   = date('d-m-Y H:i:s') . ' ERROR: ' . $message . "\n" . $trace . "\n\n\n";

                $f = fopen($cnf->log->path, 'a');
                fwrite($f, $str . chr(10) . chr(13));
                fclose($f);
            } else {
                $text = sprintf('Нет доступа на запись в файл %s.', $cnf->log->path);
                self::Exception($text, $code);
            }
        }
        if ($code == 503) {
            self::Exception($message, $code);
        }
		if ($message == '911') {
			$text = 'Доступ закрыт! Если вы уверены, что вам сюда можно, обратитесь к администратору.';
			self::Exception($text, $code);
		} elseif ($message == '404') {
            header("HTTP/1.1 404 Page not found");
			self::Exception('Нет такой страницы', $code);
		} elseif ($message == 'expired') {
			header("HTTP/1.1 403 Forbidden");
			die();
		}
		//Zend_Registry::get('logger')->log(__METHOD__ . " " . $str, Zend_Log::ERR);
		if ($cnf->debug && $cnf->debug->on) {
			$trace = $exception->getTraceAsString();
			$str = date('d-m-Y H:i:s') . ' ERROR: ' . $message . "\n" . $trace . "\n\n\n";
			if ($cnf->debug->firephp) {
				Tool::fb($str);
			} else {
                self::Exception("<PRE>{$str}</PRE>", $code);
			}
		} else {
			if (substr($message, 0, 8) == 'SQLSTATE') {
			    $message = 'Ошибка базы данных';
                //TODO вести журнал
            }
            self::Exception($message, $code);
		}
	}

	/**
     * Обработчик исключений адаптера базы данных
     *
	 * @param $exception
	 */
	public static function catchDbException($exception) {
		$code = $exception->getCode();
		if ($code == 1044) {
			$message = 'Нет доступа к базе данных.';
		} elseif ($code == 2002) {
			$message = 'Не верный адрес базы данных.';
		} elseif ($code == 1049) {
			$message = 'Нет соединения с базой данных.';
		} else {
			$cnf = self::getConfig();
			if ($cnf->debug->on) {
				$message = $exception->getMessage(); //TODO вести журнал
			} else {
				$message = "Ошибка базы данных";
			}
		}
		self::Exception($message, $code);

	}

	/**
	 * Получаем экземпляр конфига
	 * @return mixed
	 */
	private static function getConfig() {
		// Zend_Registry MUST present
		try {
			$cnf = \Zend_Registry::get('config');
		} catch (Zend_Exception $e) {
			self::Exception($e->getMessage(), 500);
		}
		return $cnf;
	}

	public static function catchZendException($exception) {
		$message = $exception->getMessage();
		$code = $exception->getCode();
		self::Exception($message, $code);
	}

    /**
     * @param array $out
     * @param int $code
     * @return string|void
     */
	public static function catchJsonException($out, $code = 0) {
		if ($code == 400) {
			header("HTTP/1.1 400 Bad Request");

		} elseif ($code == 403) {
			header("HTTP/1.1 403 Forbidden");

		} elseif ($code == 500) {
			header("HTTP/1.1 500 Internal Server Error");

		} elseif ($code == 503) {
			header("HTTP/1.1 503 Service Unavailable");

		}
		header('Content-type: application/json; charset="utf-8"');
		return self::Exception(json_encode($out), $code);
	}

	public static function catchXajax(Exception $e, xajaxResponse $res) {
		$res->alert($e->getMessage());
		return $res;
	}
	
}