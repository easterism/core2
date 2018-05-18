<?php
/**
 * TREE CLASS HERE
 * Created by Easter
 *
 */
require_once("class.ini.php");
class Tree extends initTree {
	var $tree_id				= "";
	var $SQL					= "";
	var $HTML					= "";
	var $template				= "";
	var $currentTrunc			= "";
	var $lastTrunc				= "";
	var $treeArray				= array();
	var $errors					= array();
	var $nodesToInsert			= array();
	
	public function __construct($name) {
		parent::__construct();
		$this->tree_id 	= $name;
	}
	/**
	 * Create SQL based array
	 *
	 * @param int $parent - default parent id
	 */
	function createFromSQL($parent = 0) {
		return $this->createArray($parent);
	}

	/**
	 * @param int $parent
	 * @param array $temp
	 * @return array
	 */
	function createArray($parent = 0, $temp = array()) {
		if (strpos($this->SQL, "[PARENT]") === false) {
			$noparent = 1;
		}
		$SQL = str_replace("[PARENT]", $parent, $this->SQL);
		
		$res = $this->db->query($SQL);
		if (!$res) {
			$this->errors[] = $this->db->errorInfo();
			return $temp;
		}
		$key = 0;
		$data = $res->fetchAll();
		foreach ($data as $fields) {
		
			if ((!isset($fields['img']) or $fields['img'] == "") && $this->baseImage != "") {
				$fields['img'] = $this->baseImage;
			}
			if (!isset($fields['template'])) $fields['template'] = '';
			$temp[$key]['DATA'] = array('NAME'		=> $fields['title'], 
										'IMG'		=> $fields['img'], 
										'ACTION'	=> $fields['action'], 
										'ID'		=> $fields['id'], 
										'TEMPLATE'	=> $fields['template']);
			if (!isset($noparent)) $noparent = '';
			if ($noparent != 1) {
				$temp[$key] = $this->createArray($fields['id'], $temp[$key]);
			} else {
				//$temp[$key] = array();
			}
			$key++;
		}
		return $temp;
	}
	/**
	 * @param array $add
	 * @param array $new
	 */
	function updateArray($add, $new = array()) {
		foreach ($add as $value) {
			if (count($value) > 1) {
				$temp = array();
				$j = 1;
				for ($i = 0; $i < $j; $i++) {
					if ($value[$i]) {
						$temp[] = $value[$i];
						$j++;
					}
				}
				$new[$value['DATA']['NAME']] = $this->updateArray($temp, $new[$value['DATA']['NAME']]);
			} else {
				$new[$value['DATA']['NAME']] = $value['DATA']['NAME'];
			}
		}
		$this->treeArray = $new;
		return $new;
	}
	
	/**
	 * build tree HTML
	 *
	 * @param array $arr - normaly is 0
	 * @return string
	 */
	function treeFromArray($arr = 0) {
		if ($arr == 0) $arr = $this->treeArray;
	   	if (count($arr)) {
	    	$count2 = 0;
	        foreach ($arr as $value) {
	        	$count2++;
	        	if ($count2 == count($arr)) {
        			$img = "L"; 
        			$image = $this->L;
        		} else {
        			$img = "T";
        			$image = $this->T;
        		}
        		$template = str_replace("\n", "", $this->template);
        		
	        	if (array_key_exists($this->tree_id . "_" . $value['DATA']['ID'], $this->nodesToInsert)) {
	        		foreach ($this->nodesToInsert[$this->tree_id . "_" . $value['DATA']['ID']] as $insNode) {
		        		if (!count($value[0])) {
			        		$value[0] = $insNode;
			        	} else {
							$value[] = $insNode;
			        	}
	        		}
	        		
				}
	        	if ($value['DATA']['TEMPLATE'] != "") {
        			$template = str_replace("\n", "", $value['DATA']['TEMPLATE']);
        		} else {
        			$template = str_replace("\n", "", $this->template);
        		}
				$template = str_replace("\r", "", $template);
				$template = str_replace("\t", "", $template);
        		$temp = str_replace("[ID]", $this->tree_id . "_" . $value['DATA']['ID'], $template);
        		$temp = str_replace("[REAL_ID]", $value['DATA']['ID'], $temp);
				$temp = str_replace("[IN1]", "", $temp);
        		$temp = str_replace("[ACTION_1]", $value['DATA']['ACTION'], $temp);
            	$temp = str_replace("[IMG_1]", $value['DATA']['IMG'], $temp);
		        if (!isset($value[0])) $value[0] = '';
	        	if ($value[0] && is_array($value[0])) {
        			
            		$type = "";
            		$onclick = "";
            		if (count($value[0])) {
            			if (substr($value['DATA']['NAME'], 0, 1) == "*") $type = "minus";
            			else $type = "plus";
            			if (($img . $type) == "Lminus") $image = $this->Lminus;
	            		if (($img . $type) == "Tminus") $image = $this->Tminus;
	            		if (($img . $type) == "Lplus") $image = $this->Lplus;
	            		if (($img . $type) == "Tplus") $image = $this->Tplus;
            			$onclick = " onclick='tree.doExpand(this, \"" . $img . "\", \"$this->Lminus\",\"$this->Tminus\",\"$this->Lplus\",\"$this->Tplus\")'";
            		}
            		
            			$temp = str_replace("[IN1_1]", ' style="cursor:pointer; width:16px;height:16px; background:url(' . $image . ') no-repeat;" ' . $onclick, $temp);
            			$temp = str_replace("[VAL1_1]", "", $temp);
            			$temp = str_replace("[IN1_2]", "", $temp);
            			$temp = str_replace("[VAL1_2]", ltrim($value['DATA']['NAME'], "*"), $temp);
            		$temp = str_replace("[IN2]", substr($value['DATA']['NAME'], 0, 1) == "*" ? '' : 'style="display:none"', $temp);
            			$temp = str_replace("[IN2_1]", " style='width:16px;height:16px;" . ($count2 != count($arr) ? "background-image: url({$this->I});" : "") . "'", $temp);
            			$temp = str_replace("[VAL2_1]", "", $temp);
            			$temp = str_replace("[IN2_2]", "", $temp);
            			unset($value['DATA']);
            			$temp = str_replace("[VAL2_2]", $this->treeFromArray($value), $temp);
            	} else {
	            		$temp = str_replace("[IN1_1]", ' style="width:16px;height:16px;background:url(' . $image . ') no-repeat;"', $temp);
	            		$temp = str_replace("[VAL1_1]", "", $temp);
	            		$temp = str_replace("[IN1_2]", "", $temp);
	            		$temp = str_replace("[VAL1_2]", ltrim($value['DATA']['NAME'], "*"), $temp);
            		$temp = str_replace("[IN2]", ' style="display:none"', $temp);
	            		$temp = str_replace("[IN2_1]", "", $temp);
	            		$temp = str_replace("[VAL2_1]", "", $temp);
	            		$temp = str_replace("[IN2_2]", "", $temp);
	            		$temp = str_replace("[VAL2_2]", "", $temp);
	            		
            	}
            	if (!isset($HTML)) $HTML = '';
            	$HTML .= $temp;
			}
			return $HTML;
		}
		return "";
	}
	
	/**
	 * Add single node to the array
	 *
	 * @param string $id - node id, should be unique in all tree (replasement for [ID] in template)
	 * @param string $type - 'branch' or 'trunc' or 'sql'(the SQL variable should be setup before)
	 * @param string $name - node title
	 * @param string $is_last - is node will be last in the trunk
	 * @param string $action - node action (replasement for [ACTION_1] in template)
	 * @param string $image - path to image (replasement for [IMG_1] in template)
	 */
	public function addNode($id, $type = "branch", $name = "&nbsp;", $is_last = "", $action = "", $image = "", $template = "") {
		$type = strtolower($type);
		if ($image == "" && $this->baseImage != "") {
			$image = $this->baseImage;
		}
		$data = array("NAME" 	=> $name,
                      "IMG" 	=> $image, 
                      "ACTION" 	=> $action,
                      "ID" 		=> $id,
					  "TEMPLATE" => $template);
		if (!$this->currentTrunc) {
			@$this->currentTrunc = "\$this->treeArray[" . (end(array_keys($this->treeArray)) + 1) . "]";
		}
		if ($type == "trunc") {
			if ($this->lastTrunc) {
				$this->removeLastIndex();
				$this->increaseLastIndex();
				$this->lastTrunc = "";
			}
			
			eval($this->currentTrunc . "['DATA'] = \$data;");
			eval($this->currentTrunc . "[0] = array();");
			$this->currentTrunc .= "[0]";
			if ($is_last != "") {
				$this->lastTrunc = $this->currentTrunc;
			}
		} elseif ($type == 'branch') {
			eval($this->currentTrunc . "['DATA'] = \$data;");
			if ($is_last != "") {
				$this->removeLastIndex();
			}
			$this->increaseLastIndex();
		} elseif ($type == 'sql') {
			eval($this->currentTrunc . "['DATA'] = \$data;");
			eval($this->currentTrunc . "[0] = array();");
			$this->currentTrunc .= "[0]"; 
			$temp = $this->createFromSQL();
			/*if (!count($temp)) {
				$temp[] = array("DATA" => array('NAME'=>'', 'IMG'=>'', 'ACTION'=>'', 'ID'=>''));
			}*/
			foreach ($temp as $node) {
				eval($this->currentTrunc . " = \$node;");
				$this->increaseLastIndex();
			}
			if ($is_last != "") {
				$this->removeLastIndex();
				$this->increaseLastIndex();
			}
		}
	}
	
	private function increaseLastIndex() {
		// get last index and remove it from currentTrunc
		$index = substr($this->currentTrunc, strrpos($this->currentTrunc, "[") + 1, -1);
		$this->removeLastIndex();
		
		//increase and append last index
		$index++;
		$this->currentTrunc .= "[$index]";
	}
	
	private function removeLastIndex() {
		$this->currentTrunc = substr($this->currentTrunc, 0, strrpos($this->currentTrunc, "["));
	}
	
	/**
	 * Insert node into array
	 *
	 */
	function insertNode($destination_id, $source_id, $name = "&nbsp;", $action = "", $image = "", $template = "") {
		if (!$image) {
			$image = $this->baseImage;
		}
		$this->nodesToInsert[$destination_id][] = array("DATA" => array("NAME" => $name,
																   "IMG" => $image,
																   "ACTION" => $action,
																   "ID" => $source_id,
																   "TEMPLATE" => $template
																	),
												   0 => array()
													);
	}
	
	/**
	 * print tree
	 *
	 */
	function printTree() {
		$this->HTML = $this->treeFromArray();
		echo $this->HTML;
	}
}
?>