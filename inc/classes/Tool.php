<?


/**
 * Class Tool
 */
class Tool {


	/**
	 * Проверка на существование файла
	 * @param  string $filename
	 * @return bool
	 */
	public static function file_exists_ip($filename) {
        if (function_exists("get_include_path")) {
            $include_path = get_include_path();
        } elseif (false !== ($ip = ini_get("include_path"))) {
            $include_path = $ip;
        } else {return false;}
        if (false !== strpos($include_path, PATH_SEPARATOR)) {
            if (false !== ($temp = explode(PATH_SEPARATOR, $include_path)) && count($temp) > 0) {
                for ($n = 0; $n < count($temp); $n++) {
                    if (false !== @file_exists($temp[$n] . $filename)) {
                        return true;
                    }
                }
                return false;
            } else {return false;}
        } elseif (!empty($include_path)) {
            if (false !== @file_exists($include_path)) {
                return true;
            } else {return false;}
        } else {return false;}
    }


	/**
	 * HTTP аутентификация
     *
	 * @param string $realm
	 * @param array  $users
     *
	 * @return bool|int
	 */
	public static function httpAuth($realm, array $users) {
		
		if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
			$auth_data = $_SERVER['PHP_AUTH_DIGEST'];
			$isapi = false;
		} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$auth_data = $_SERVER['HTTP_AUTHORIZATION'];
			$isapi = true;
		}
		if (!isset($auth_data)) {
		    header('HTTP/1.1 401 Unauthorized');
		    header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid('') . '",opaque="' . md5($realm).'"');
			//header('WWW-Authenticate: Basic realm="' . $realm . '"');
		    die('Authorization required!');
		}
		
		$needed_parts = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
		$data = array();
		$matches = array();
	
		preg_match_all('@(\w+)=(?:(?:\'([^\']+)\'|"([^"]+)")|([^\s,]+))@', $auth_data, $matches, PREG_SET_ORDER);
	
		foreach ($matches as $m) {
			$data[$m[1]] = $m[2] ? $m[2] : ($m[3] ? $m[3] : $m[4]);
			unset($needed_parts[$m[1]]);
		}
		$digest = $needed_parts ? false : $data;
		
		if (!isset($users[$digest['username']])) {
			return 1;
		} else {    
			$A1 = md5($digest['username'] . ':' . $digest['realm'] . ':' . $users[$digest['username']]);
			$A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $digest['uri']);
			$valid_response = md5($A1 . ':' . $digest['nonce'] . ':' . $digest['nc'] . ':'. $digest['cnonce'] . ':'.$digest['qop'] . ':' . $A2);
		            
			if ($digest['response'] != $valid_response) {
				return 2;
			} else {
		    	return false;
			}
		}
    }


	/**
	 * Запись логов в файл
	 * Строки маркируются датой и временем
     *
	 * @param string $filename
	 * @param string $text
	 */
	public static function logToFile($filename, $text) {

    	$f = fopen($filename, 'a');
    	if (!$f) return;
    	ob_start();
    	echo date('Y-m-d H:i:s') . ' ';
		if (is_array($text) || is_object($text)) {
			echo "<PRE>";print_r($text);echo"</PRE>";//die();
		} else {
			echo $text;
		}
		$text = ob_get_clean();
		fwrite($f, $text . chr(10) . chr(13));
		fclose($f);
    }

    
    /**
     * Write any data to file
     *
     * @param string $filename
     * @param mixed  $data
     *
     * @return void
     */
	public static function dataToFile($filename, $data) {
    	$f = fopen($filename, 'a');
    	if (!$f) return;
    	ob_start();
		if (is_array($data) || is_object($data)) {
			echo "<PRE>";print_r($data);echo"</PRE>";//die();
		} else {
			echo $data;
		}
		$data = ob_get_clean();
		fwrite($f, $data);
		fclose($f);
    }


    /**
     * Добавление в лог текста
     * @param  mixed $text
     * @return void
     */
    public static function log($text) {

    	$cnf = Zend_Registry::get('config');
    	if ($cnf->log->on && $cnf->log->path) {
			$f = fopen($cnf->log->path, 'a');
			if (is_array($text) || is_object($text)) {
				ob_start();
				echo "<PRE>";print_r($text);echo"</PRE>";//die();
				$text = ob_get_clean();
			}
			fwrite($f, $text . chr(10) . chr(13));
			fclose($f);
		}
    }


    /**
     * Вывод текста в FireBug
     * @param $text
     */
    public static function fb($text) {
		require_once(DOC_ROOT . 'core2/ext/FirePHPCore-0.3.2/lib/FirePHPCore/FirePHP.class.php');
		$firephp = FirePHP::getInstance(true);
		try {
			$firephp->fb($text);
		} catch (Exception $e) {
			\Core2\Error::Exception($e->getMessage());
    	}
    }


    /**
     * Добавляет разделитель через каждые 3 символа в указанном числе
     *
     * @param string $_
     * @param string $del
     *
     * @return string
     */
    public static function commafy($_, $del = ';psbn&') {
	    return strrev( (string)preg_replace( '/(\d{3})(?=\d)(?!\d*\.)/', '$1' . $del , strrev( $_ ) ) );
	}


    /**
     * Salt password
     * @param  string $pass - password
     * @return string
     */
    public static function pass_salt($pass) {

		$salt = "sdv235!#&%asg@&fHTA";
		$spec = array('~','!','@','#','$','%','^','&','*','?');
		$c_text = md5($pass);
		$crypted = md5(md5($salt) . $c_text);
		$temp = '';
		for ($i = 0; $i < mb_strlen($crypted); $i++) {
			if (ord($c_text[$i]) >= 48 && ord($c_text[$i]) <= 57) {
				$temp .= $spec[$c_text[$i]];
			} elseif (ord($c_text[$i]) >= 97 && ord($c_text[$i]) <= 100) {
				$temp .= mb_strtoupper($crypted[$i]);
			} else {
				$temp .= $crypted[$i];
			}
		}
		return md5($temp);
	}


    /**
     * Format date with russian pattern
     *
     * @param string $formatum - date pattern
     * @param int    $timestamp - timestamp to format, curretn time by default
     *
     * @return string
     */
    public static function date_ru($formatum, $timestamp=0) {

        if (($timestamp <= -1) || !is_numeric($timestamp)) return '';
        mb_internal_encoding("UTF-8");

        $q['д'] = array(-1 => 'w', 'воскресенье','понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота');
        $q['в'] = array(-1 => 'w', 'воскресенье','понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу');
        $q['Д'] = array(-1 => 'w', 'Воскресенье','Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота');
        $q['В'] = array(-1 => 'w', 'Воскресенье','Понедельник', 'Вторник', 'Среду', 'Четверг', 'Пятницу', 'Субботу');
        $q['к'] = array(-1 => 'w', 'вс','пн', 'вт', 'ср', 'чт', 'пт', 'сб');
        $q['К'] = array(-1 => 'w', 'Вс','Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');
        $q['м'] = array(-1 => 'n', '', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
        $q['М'] = array(-1 => 'n', '', 'Января', 'Февраля', 'Март', 'Апреля', 'Май', 'Июня', 'Июля', 'Август', 'Сентября', 'Октября', 'Ноября', 'Декабря');
        $q['И'] = array(-1 => 'n', '', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь');
        $q['л'] = array(-1 => 'n', '', 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек');
        $q['Л'] = array(-1 => 'n', '',  'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек');

        if ($timestamp == 0)
        $timestamp = time();
        $temp = '';
        $i = 0;
        while ( (mb_strpos($formatum, 'д', $i) !== FALSE) || (mb_strpos($formatum, 'Д', $i) !== FALSE) ||
              (mb_strpos($formatum, 'в', $i) !== FALSE) || (mb_strpos($formatum, 'В', $i) !== FALSE) ||
              (mb_strpos($formatum, 'к', $i) !== FALSE) || (mb_strpos($formatum, 'К', $i) !== FALSE) ||
              (mb_strpos($formatum, 'м', $i) !== FALSE) || (mb_strpos($formatum, 'М', $i) !== FALSE) ||
              (mb_strpos($formatum, 'и', $i) !== FALSE) || (mb_strpos($formatum, 'И', $i) !== FALSE) ||
              (mb_strpos($formatum, 'л', $i) !== FALSE) || (mb_strpos($formatum, 'Л', $i) !== FALSE)) {
        $ch['д']=mb_strpos($formatum, 'д', $i);
        $ch['Д']=mb_strpos($formatum, 'Д', $i);
        $ch['в']=mb_strpos($formatum, 'в', $i);
        $ch['В']=mb_strpos($formatum, 'В', $i);
        $ch['к']=mb_strpos($formatum, 'к', $i);
        $ch['К']=mb_strpos($formatum, 'К', $i);
        $ch['м']=mb_strpos($formatum, 'м', $i);
        $ch['М']=mb_strpos($formatum, 'М', $i);
        $ch['И']=mb_strpos($formatum, 'И', $i);
        $ch['л']=mb_strpos($formatum, 'л', $i);
        $ch['Л']=mb_strpos($formatum, 'Л', $i);
        foreach ($ch as $k => $v)
          if ($v === FALSE)
            unset($ch[$k]);
        $a = min($ch);
        $index = mb_substr($formatum, $a, 1);
        $temp .= date(mb_substr($formatum, $i, $a - $i), $timestamp) . $q[$index][date($q[$index][-1], $timestamp)];
        $i = $a + 1;
        }
        $temp .= date(mb_substr($formatum, $i), $timestamp);
        return $temp;
	}


	/**
	 * Функция склонения числительных в русском языке
	 *
	 * @param int   $number Число которое нужно просклонять
	 * @param array $titles Массив слов для склонения
     *
	 * @return string
	 */
	public static function declNum($number, $titles) {

		$cases = array(2, 0, 1, 1, 1, 2);
		$num = abs($number);
		return $number . " " . $titles[($num % 100 > 4 && $num % 100 < 20) ? 2 : $cases[min($num % 10, 5)]];
	}


	/**
	 * Определение кодировки
     *
	 * @param string $string
	 * @param int    $pattern_size
     *
	 * @return string
	 */
	public static function detect_encoding($string, $pattern_size = 50) {

		$list = array('cp1251', 'utf-8', 'ascii', '855', 'KOI8R', 'ISO-IR-111', 'CP866', 'KOI8U');
		$c = strlen($string);
		if ($c > $pattern_size) {
			$string = substr($string, floor(($c - $pattern_size) / 2), $pattern_size);
			$c = $pattern_size;
		}

		$reg1 = '/(\xE0|\xE5|\xE8|\xEE|\xF3|\xFB|\xFD|\xFE|\xFF)/i';
		$reg2 = '/(\xE1|\xE2|\xE3|\xE4|\xE6|\xE7|\xE9|\xEA|\xEB|\xEC|\xED|\xEF|\xF0|\xF1|\xF2|\xF4|\xF5|\xF6|\xF7|\xF8|\xF9|\xFA|\xFC)/i';

		$mk = 10000;
		$enc = 'ascii';
		foreach ($list as $item) {
			$sample1 = @iconv($item, 'cp1251', $string);
            $gl = @preg_match_all($reg1, $sample1, $arr);
			$sl = @preg_match_all($reg2, $sample1, $arr);
			if (!$gl || !$sl) continue;
			$k = abs(3 - ($sl / $gl));
			$k += $c - $gl - $sl;
			if ($k < $mk) {
				$enc = $item;
				$mk = $k;
			}
		}
		return $enc;
	}


	/**
	 * Get request headers
	 * @return array
	 */
	public static function getRequestHeaders() {

		$headers = array();
		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) <> 'HTTP_') {
				continue;
			}
			$header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
			$headers[$header] = $value;
		}
		return $headers;
	}


	/**
	 * will execute $cmd in the background (no cmd window) without PHP waiting for it to finish, on both Windows and Unix
	 *
	 * @param $cmd - command to execute
	 */
	public static function execInBackground($cmd) {
		if (substr(php_uname(), 0, 7) == "Windows") {
			pclose(popen("start /B " . $cmd, "r"));
		} else {
			exec($cmd . " > /dev/null &");
		}
	}


    /**
     * @param  string $data
     * @return string
     */
	public static function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}


    /**
     * @param  string $data
     * @return string
     */
	public static function base64url_decode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}


    /**
     * Print link to CSS file
     *
     * @param string $href - CSS filename
     */
    public static function printCss($href) {
        if (strpos($href, '?')) {
            $explode_href = explode('?', $href, 2);
            $href .= file_exists(DOC_ROOT . $explode_href[0])
                    ? '&_=' . crc32(md5_file(DOC_ROOT . $explode_href[0]))
                    : '';
        } else {
            $href .= file_exists(DOC_ROOT . $href)
                    ? '?_=' . crc32(md5_file(DOC_ROOT . $href))
                    : '';
        }
        echo '<link href="' . $href . '" type="text/css" rel="stylesheet" />';
    }


    /**
     * Print link to JS file
     *
     * @param string $src - JS filename
     * @param bool   $chachable
     */
    public static function printJs($src, $chachable = false) {
        if ($chachable) {
            //помещаем скрипт в head
            echo "<script type=\"text/javascript\">jsToHead('$src')</script>";
        } else {
            echo '<script type="text/javascript" language="JavaScript" src="' . $src . '"></script>';
        }
    }


    /**
     * Cортировка массивов по элементу
     *
     * @param array  $array Массив
     * @param string $on Ключ элемента
     * @param int    $order Тип сортировки
     *
     * @return array
     */
    public static function arrayMultisort($array, $on, $order = SORT_ASC) {

        switch ($order) {
            case SORT_ASC:
                usort($array, create_function('$a, $b', "return strnatcasecmp(\$a['$on'], \$b['$on']);"));
                break;
            case SORT_DESC:
                usort($array, create_function('$a, $b', "return strnatcasecmp(\$b['$on'], \$a['$on']);"));
                break;
        }

        return $array;
    }


	/**
	 * Проверка на то, является ли клиентское устройство мобильным
	 * @return bool
	 */
	public static function isMobileBrowser() {

		$useragent = $_SERVER['HTTP_USER_AGENT'];
		if (preg_match('/android.+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|meego.+mobile|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4)))
			return true;
		else return false;
	}


    /**
     * Возвращает сумму прописью
     * @param  float  $num
     * @return string
     */
    public static function num2str($num) {
        $nul = 'ноль';
        $ten = array(
            array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
            array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
        );
        $a20     = array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
        $tens    = array(2 => 'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
        $hundred = array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
        $unit    = array( // Units
             array('копейка' ,'копейки' ,'копеек',	 1),
             array('рубль'   ,'рубля'   ,'рублей'    ,0),
             array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
             array('миллион' ,'миллиона','миллионов' ,0),
             array('миллиард','милиарда','миллиардов',0),
        );
        //
        list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
        $out = array();
        if (intval($rub) > 0) {
            foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
                if ( ! intval($v)) continue;
                $uk = sizeof($unit) - $uk - 1; // unit key
                $gender = $unit[$uk][3];
                list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
                // mega-logic
                $out[] = $hundred[$i1]; # 1xx-9xx
                if ($i2 > 1) $out[]= $tens[$i2] . ' ' . $ten[$gender][$i3]; # 20-99
                else $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
                // units without rub & kop
                if ($uk > 1) $out[]= self::morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
            }
        }
        else $out[] = $nul;
        // $out[] = self::morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
        // $out[] = $kop . ' ' . self::morph($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
        return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
    }


    /**
     * Делаем запрос через CURL и отдаем ответ
     *
     * @param   string $url URL
     * @param   array $data данные которые нужно запостить
     * @param   array $headers заголовки запроса
     *
     * @return  array       ответ запроса + http-код ответа
     */
    public static function doCurlRequest($url, $data = array(), $headers = array())
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 7000);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		if ($data) {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}

        $curl_out = curl_exec($curl);
        //если возникла ошибка
        if (curl_errno($curl) > 0) {
            return array(
                'error' => curl_errno($curl) . ": " . curl_error($curl)
            );
        }
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array(
            'answer'    => $curl_out,
            'http_code' => $http_code
        );
    }


    /**
     * Форматирование размера из байт в человеко-понятный вид
     * @param  int    $bytes
     * @return string
     */
    public static function formatSizeHuman($bytes) {

        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }


    /**
     * Получение максимально возможного размера файла,
     * который можно загрузить на сервер. Размер в байтах.
     * @return int
     */
    public static function getUploadMaxFileSize() {
        $ini = self::convertIniSizeToBytes(trim(ini_get('post_max_size')));
        $max = self::convertIniSizeToBytes(trim(ini_get('upload_max_filesize')));
        $min = max($ini, $max);
        if ($ini > 0) {
            $min = min($min, $ini);
        }

        if ($max > 0) {
            $min = min($min, $max);
        }

        return $min >= 0 ? $min : 0;
    }



    /**
     * Конвертирует размер из ini формата в байты
     * @param  string $size
     * @return int
     */
    private static function convertIniSizeToBytes($size) {
        if ( ! is_numeric($size)) {
            $type = strtoupper(substr($size, -1));
            $size = (int)substr($size, 0, -1);

            switch ($type) {
                case 'K' : $size *= 1024; break;
                case 'M' : $size *= 1024 * 1024; break;
                case 'G' : $size *= 1024 * 1024 * 1024; break;
                default : break;
            }
        }

        return (int)$size;
    }


    /**
     * Склоняем словоформу
     * @ author runcore
     */
    private static function morph($n, $f1, $f2, $f5) {
        $n = abs(intval($n)) % 100;
        if ($n > 10 && $n < 20) return $f5;
        $n = $n % 10;
        if ($n > 1 && $n < 5) return $f2;
        if ($n == 1) return $f1;
        return $f5;
    }

}