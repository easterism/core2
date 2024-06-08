<?php

/**
 * Class Templater2
 *
 */
class Templater2 {
	
	private $blocks = array();
	private $vars = array();
	private $html = "";
	private $owner = "";
	private $loopHTML = array();
	private $embrace = array('', '');
	private $_p = array();
    private $plugins = array();
    private $in_assign = false;

	function __construct($tpl = '') { 
		if ($tpl) $this->loadTemplate($tpl);
        //добавляем плагин по умолчанию
        $this->addPlugin("tr", \Core2\Registry::get('translate'));
	}

    public function addPlugin($title, $obj)
    {
        $this->plugins[strtolower($title)] = $obj;
    }

	public function __isset($k)
	{
		return isset($this->_p[$k]);
	}

	/**
	 * nested blocks will be stored inside $_p
	 * @param $k
	 * @return Common|null|self
	 */
	public function __get($k)
	{
		$v = NULL;
		if (array_key_exists($k, $this->_p)) {
			$v = $this->_p[$k];
		} else {
			$temp = new Templater2();
			$temp->owner = $k;
			$temp->setTemplate($this->getBlock($k));
			$temp->setEmbrace(implode('', $this->embrace));
			$v = $this->{$k} = $temp;
		}
		return $v;

	}

	public function __set($k, $v)
	{
		$this->_p[$k] = $v;
		return $this;
	}

	public function setEmbrace($em) {
		$arr = array();
		if ((strlen($em) % 2) == 0) {
			$i = strlen($em) / 2;
			$arr[] = substr($em, 0, $i);
			$arr[] = substr($em, $i);
		}
		$this->embrace = $arr;
	}

	/**
	 * @param $block
	 * @param $text
	 */
	public function prependToBlock($block, $text) {
		$this->newBlock($block);
		$this->blocks[$block]['PREPEND'] = $text;
	}

	/**
	 * @param $block
	 * @param $text
	 */
	public function appendToBlock($block, $text) {
		$this->newBlock($block);
		$this->blocks[$block]['APPEND'] = $text;
	}

	/**
	 * @param $block
	 */
	private function newBlock($block) {
		if (!isset($this->blocks[$block])) {
			$this->blocks[$block] = array('PREPEND' => '', 
										  'APPEND' => '',
										  'GET' => false,
										  'REASSIGN' => false,
										  'REPLACE' => false,
										  'TOUCHED' => false);
		}
	}

	/**
	 * @param $block
	 */
	public function touchBlock($block) {
		$this->newBlock($block);
		$this->blocks[$block]['TOUCHED'] = true;
	}

	/**
	 * @param $path
	 * @param bool $strip
	 */
	public function loadTemplate($path, $strip = true) {
		$this->html = $this->getTemplate($path, $strip);
	}

	/**
	 * @param $path
	 * @param bool $strip
	 * @return bool|mixed|string
	 */
	public function getTemplate($path, $strip = true) {
		if (!is_file($path)) {
			return false;
		}
		$temp = file_get_contents($path);
		if ($strip) {
			$temp = str_replace("\r", "", $temp);
			$temp = str_replace("\t", "", $temp);
		}
		return $temp;
	}

	/**
	 * set the HTML to parse
	 * @param $html
	 */
	public function setTemplate($html) {
		$this->html = $html;
		$this->blocks = array();
		$this->vars = array();
	}

	/**
	 * the final render
	 * @return string
	 */
	public function parse() {
		$html = $this->html;
		$this->autoSearch($html);

		foreach ($this->blocks as $block => $data) {
			if (array_key_exists($block, $this->_p)) {
				$data['REPLACE'] = $this->_p[$block]->parse();
			} else {
				//$data['TOUCHED'] = false;
			}
			$temp = array();
			preg_match("/(.*)<!--\s*BEGIN\s$block\s*-->(.+)<!--\s*END\s$block\s*-->(.*)/sm", $html, $temp);
			if (isset($temp[1])) {
				if (!empty($data['REPLACE'])) {
					$data['GET'] = true;
				}
				if ($data['TOUCHED']) {
					$html = $temp[1] . $data['PREPEND'] . $temp[2] . $data['APPEND'] . $temp[3];
				} else if ($data['GET']) {
					$html = $temp[1] . "<!--$block-->" . $temp[3];
				} else {
					$html = $temp[1] . $temp[3];
				}
				if (!empty($data['REPLACE'])) {
					$html = str_replace("<!--$block-->", $data['REPLACE'], $html);
				}
			}
		}
		if ($this->vars) {
			$html = str_replace(array_keys($this->vars), $this->vars, $html);
		} else {
		    if ($this->in_assign) $html = ''; //Очищаем html, если для него не задано ниодной переменной
        }
		if ($this->loopHTML) {
			$html = implode('', $this->loopHTML) . $html;
		}
		$this->loopHTML = array();

        //apply plugins
        foreach ($this->plugins as $plugin => $process) {
            preg_match_all("/_{$plugin}\(([^\)]+)\)/sm", $html, $temp);
            //$reflector = new ReflectionClass($process);
            //$parameters = $reflector->getMethod($plugin)->getParameters();
            foreach ($temp[1] as $k => $value) {
                $tmp         = explode(',', $value);
                array_walk($tmp, function (&$val) {
                    $val = trim($val, "\"'");
                    return $val;
                });
                $temp[1][$k] = call_user_func_array(array($process, $plugin), $tmp);
            }
            $html = str_replace($temp[0], $temp[1], $html);
        }

		return $html;
	}

	/**
	 * @param $html
	 */
	private function autoSearch($html) {
		$temp = array();
		preg_match_all("/<!--\s*BEGIN\s(.+?)\s*-->/sm", $html, $temp);
		if (isset($temp[1]) && count($temp[1])) {
			foreach ($temp[1] as $block) {
				$this->newBlock($block);
			}
		}
	}

	/**
	 * Fill SELECT items on page
	 *
	 * @param string $inID
	 * @param array $inOptions
	 * @param string $inVal
	 */
	public function fillDropDown($inID, Array $inOptions, $inVal = '') {
		if (is_array(current($inOptions))) {
			$opt = array();
			foreach ($inOptions as $val) {
				$opt[current($val)] = next($val);
			}
		} else {
			$opt = $inOptions;
		}

		$tmp = "";
		if ($inVal) {
			$inVal = explode(',', $inVal);
		} else {
			$inVal = array();
		}
		foreach ($inOptions as $key => $val) {
			$sel = '';
			if (in_array($key, $inVal)) $sel = "selected=\"selected\"";
			$tmp .= "<option $sel value=\"$key\">$val</option>";
		}
		$inOptions = $tmp;
		$arrayOfSelect = array();
		$reg = "/(<select\s*.*id\s*=\s*[\"|']{$inID}[\"|'][^>]*>).*?(<\/select>)/msi";
		$this->html = preg_replace($reg, "$1[[$inID]]$2", $this->html);
		$this->assign("[[$inID]]", $inOptions, true);
	}

	/**
	 * @param $var
	 * @param string $value
	 * @return mixed
	 */
	public function assign($var, $value = '', $avoidEmbrace = false) {
		if (is_array($var)) {
			foreach ($var as $key => $val) {
				$this->assign($key, $val, $avoidEmbrace);
			}
		} else {
			if ($avoidEmbrace) {
				$this->vars[$var] = $value;
			} else {
				$this->vars[$this->embrace[0] . $var . $this->embrace[1]] = $value;
			}
		}
		$this->in_assign = true;
	}

	private function clear() {
		$this->blocks = array();
		$this->vars = array();
		foreach ($this->_p as $obj => $data) {
			$this->_p[$obj]->clear();
		}
	}

	/**
	 * Reset the current instance's variables and make them able to assign again
	 */
	public function reassign()
	{
		$this->loopHTML[] = $this->parse();
		$this->clear();
	}

	/**
	 * @param $block
	 * @param string $html
	 * @return string
	 */
	public function getBlock($block, $html = '') {
		if (!$html) {
			$html = $this->html;
		}
		$temp = array();
		preg_match("/(.*)<!--\s*BEGIN\s$block\s*-->(.+)<!--\s*END\s$block\s*-->(.*)/sm", $html, $temp);
		if (isset($temp[2]) && $temp[2]) {
			$html = $temp[2];
		}
		$this->newBlock($block);
		$this->blocks[$block]['GET'] = true;
		return $html;
	}

    /**
     * synonym parse()
     */
	public function render() {
        return $this->parse();
    }

    /**
     * заменяет блок $block значением $value
     * @param $block
     * @param $value
     * @param $html
     * @return array|mixed|string|string[]
     */
    public function replaceBlock($block, $value, $html = '') {
        if (!$html) {
            $html = $this->html;
        }
        $this->blocks[$block]['REPLACE'] = $value;
        return str_replace("<!--$block-->", $value, $html);
    }

}
