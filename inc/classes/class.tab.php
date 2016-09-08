<?
require_once "class.ini.php";
require_once "Templater.php";


/**
 * Class tabs
 */
class tabs extends initTabs {

	public $activeTab = 1;

	protected $tabs_id	            = '';
	protected $tabs		            = array();
	protected $help 	            = array();
	protected $class_tabActiveDIV   = "tabActive";
	protected $class_tabInactiveDIV = "tabInactive";
	protected $class_tabActiveTD    = "tabActiveTD";
	protected $class_tabInactiveTD  = "tabInactiveTD";
	protected $class_tabsTABLE	    = "";


	/**
	 * tabs constructor.
	 * @param string $name
	 */
	public function tabs($name = '') {
		if (empty($name)) {
			$name = time();
		}
		$this->tabs_id   = "tab_" . $name;
		$this->activeTab = ! empty($_GET[$this->tabs_id]) ? $_GET[$this->tabs_id] : 1;
	}


	/**
	 * @param string   $caption
	 * @param string   $location
	 * @param int|null $width
	 * @param string   $status
	 */
	public function addTab($caption, $location, $width = null, $status = 'enabled') {
		$this->tabs[$this->tabs_id][] = array(
            'caption'  => $caption,
            'location' => $location,
            'width'    => $width,
            'status'   => $status
        );
	}


	/**
	 * @return string
	 */
	public function getId() {
		return $this->tabs_id;
	}


	/**
	 * Add a help note to the container
	 * @param string $str
	 * @param int    $tab
	 */
	public function addHelp($str, $tab = 1) {
		$this->help[$tab] = $str;
	}


	/**
	 * @param string $caption
	 */
	public function beginContainer($caption) {
		$HTML = "<table class=\"containerTable\">
			<tr>
				<td class=\"containerHeaderTD\">";
        $tpl = new Templater("core2/html/" . THEME . "/tab/caption.tpl");
		$tpl->assign('[caption]', $caption);
		if ( ! empty($this->help[$this->tabs_id])) {
			$tpl->touchBlock('help');
			$tpl->assign('[TAB_ID]', $this->tabs_id);
			$tpl->assign('[HELP]', $this->help[$this->tabs_id]);
			unset($this->help[$this->tabs_id]);
		}
		$HTML .= $tpl->parse() . "</td></tr>";
		if (isset($this->tabs[$this->tabs_id]) && count($this->tabs[$this->tabs_id])) {
			$HTML .= "<tr valign=\"top\">".
						"<td width=\"100%\" class=\"containerTabsTD\">".
							"<table width=\"100%\" class=\"tabsTable\">".
								"<tr>".
									"<td class=\"tabsLeftSideTD\">&nbsp;</td>";
			$tabid = 1;
            foreach ($this->tabs[$this->tabs_id] as $value) {
				if ($tabid == $this->activeTab) {
					$style = $this->class_tabActiveDIV;
					$styletd = $this->class_tabActiveTD;
				} else {
					$style = $this->class_tabInactiveDIV;
					$styletd = "tabInactiveTD";
				}
				$action = "load('" . (strpos($value['location'], "?") !== false ? $value['location'] . "&" : $value['location'] . "?") . $this->tabs_id . "=$tabid')";
				if (substr($value['location'], 0, 11) == 'javascript:') {
					$action = substr($value['location'], 11);
				}
				if ($value['status'] == 'disabled') {
					$action = "alert('{$this->classText['DISABLED']}');";
				}
				$HTML .= 			"<td class=\"$styletd\">".
										'<div style="margin-top:-1px;' . ($value['width'] ? "width:" . (int)$value['width'] . "px;" : "") . ($value['status'] == 'disabled' ? "color:silver;" : "") . '" id="' . $this->tabs_id . '_' . $tabid . '" class="' . $style . '" onclick="' . $action . '">' . $value['caption'] . '</div>'.
									"</td>";
				$tabid++;
			}
			$HTML .= 				"<td class=\"tabsRightSideTD\">&nbsp;</td>".
								"</tr>".
							"</table>".
						"</td>".
					"</tr>";
		} else {
			$HTML .= "<tr valign=\"top\"><td class=\"tabInactiveTD\"></td></tr>";
		}
		$HTML .=	"<tr><td class=\"containerTD\">";
		echo $HTML;
	}


	/**
	 *
	 */
	function endContainer() {
		echo "</td></tr></table>";
	}
}