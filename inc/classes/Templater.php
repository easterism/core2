<?
class Templater {
	
	private $blocks = array();
	private $vars = array();
	private $html = "";
	private $selects = array();
		
	function __construct($tpl = '') { 
		if ($tpl) $this->loadTemplate($tpl);
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
	 * @param $html
	 */
	public function setTemplate($html) {
		$this->html = $html;
		$this->blocks = array();
		$this->vars = array();
	}

	/**
	 * @param string $html
	 * @return mixed|string
	 */
	public function parse($html = '') {
		if (!$html) {
			$html = $this->html;
		}
		$this->autoSearch($html);
		//echo "<PRE>";print_r($this->blocks);echo"</PRE>";//die();
		foreach ($this->blocks as $block => $data) {
			$temp = array();
			preg_match("/(.*)<!--\s*BEGIN\s$block\s*-->(.+)<!--\s*END\s$block\s*-->(.*)/sm", $html, $temp);
			if (isset($temp[1])) {
				if (!empty($data['REPLACE'])) {
					$data['GET'] = true;
				}
				if ($data['REASSIGN'] || $data['TOUCHED']) {
					if ($data['REASSIGN']) {
						//$this->blocks[$block]['TOUCHED'] = false;
						//$loop = $this->parse($temp[2]);
						$loop = $temp[2];
						$keysInLoop = array();
						foreach ($this->vars as $key => $variable) {
							if (strpos($loop, $key) !== false) {
								foreach ($variable as $k => $val) {
									$keysInLoop[$k][$key] = $val;
								}
							}
						}
						foreach ($keysInLoop as $k => $vals) {
							$temp[2] = str_replace(array_keys($vals), $vals, $temp[2]);
							if (isset($keysInLoop[$k + 1])) {
								$temp[2] .= $loop;
							}
						}
						
					}
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
		foreach ($this->vars as $key => $variable) {
			$this->vars[$key] = $variable[0];
		}
		$html = str_replace(array_keys($this->vars), 
						 $this->vars, 
						 $html);
		$this->setTemplate('');
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
	 * @param $html
	 * @return array
	 */
	public function getSelectTags($html) {
		$arrayOfSelect = array();
		preg_match_all("/<select\s([^>]+)>(.*?)<\/select>/msi", $html, $arrayOfSelect);
		return $arrayOfSelect;
	}

	/**
	 * @param $html
	 * @param $selectID
	 * @param $inOptions
	 * @param string $inVal
	 * @return mixed
	 */
	public function updateSelectById($html, $selectID, $inOptions, $inVal = '') {
		if (count($this->selects) == 0) {
			$this->selects = $this->getSelectTags($this->html);
		}
		
		if (is_array($inOptions)) {
			$tmp = "";
			foreach ($inOptions as $key => $val) {
				$sel = '';
				if ($key == $inVal) $sel = "selected=\"selected\"";
				$tmp .= "<option $sel value=\"$key\">$val</option>";			
			}
			$inOptions = $tmp;
		}
			
		$selPos = '';
		// -- FIND SELECT --
		if ($this->selects[1]) {
			reset($this->selects[1]);
			
			while (list($key, $val) = each($this->selects[1])) {
				if (stripos(' ' . $val, ' id="' . $selectID . '"') !== false or stripos(' ' . $val, ' name="' . $selectID . '"') !== false) {
					$selPos = $key;
					break;
				}
			}
			// -- RETURN IF DID NOT FIND ITEM
			if (!is_numeric($selPos)) return $html;
			
			// -- REPLACE HTML
			return  str_replace($this->selects[0][$selPos], '<select ' . $this->selects[1][$selPos] . '>' . $inOptions . '</select>', $html);
		}
		return $html;
	}	
	
	/**
	 * Fill SELECT items on page
	 *
	 * @param string $inID
	 * @param array/varhar $inOptions
	 * @param string $inVal
	 */
	public function fillDropDown($inID, $inOptions, $inVal = '') {
		if (is_array(current($inOptions))) {
			$opt = array();
			foreach ($inOptions as $val) {
				$opt[current($val)] = next($val);
			}
		} else {
			$opt = $inOptions;
		}
		$this->html = $this->updateSelectById($this->html, $inID, $opt, $inVal);
	}

	/**
	 * @param $var
	 * @param string $value
	 * @return mixed
	 */
	public function assign($var, $value = '') {
		if (is_array($var)) {
			foreach ($var as $key => $val) {
				$this->assign($key, $val);		
			}
			return ;
		}
		$this->vars[$var][] = $value;
	}

	/**
	 * @param $block
	 */
	public function reassignBlock($block) {
		$this->newBlock($block);
		$this->blocks[$block]['REASSIGN'] = true;
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
	 * @param $block
	 * @param $value
	 * @param string $html
	 * @return mixed
	 */
	public function replaceBlock($block, $value, $html = '') {
		if (!$html) {
			$html = $this->html;
		}
		$this->blocks[$block]['REPLACE'] = $value;
		return str_replace("<!--$block-->", $value, $html);
	}
}
