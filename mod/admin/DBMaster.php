<?
class DBMaster {
	protected $db;
	protected $current_db_name;
	
	function __construct() {
		global $config;
		
		$db_name = $config->database->params->dbname;
		$db_user = $config->database->params->username;
		$db_pass = $config->database->params->password;
		$db_host = $config->database->params->host;
		
		$this->db = mysql_connect($db_host, $db_user, $db_pass);
		mysql_select_db ($db_name);
		
		$this->current_db_name = $db_name;	
	}
	
	/**
	 * Function for executing all SQL
	 *
	 * @param unknown_type $inSQL
	 * @param unknown_type $withDebug
	 * @param unknown_type $iniDb
	 * @return unknown
	 */
	public function exeSQL($inSQL,$withDebug = true) {
		
		$result = mysql_query($inSQL, $this->db);
		
	    $error = mysql_error($this->db);
	    
	    if ($withDebug && $error) {
	    	echo $error;
			print "<pre>";
			print ($inSQL);
	    	die();
	    }
	    
	    // -- CHECK IF IT WAS INSERTION ---
	    
	    // -- TRY TO RETURN DATASET
	     $arr = @mysql_fetch_array($result);
	    // -- NO RESULT AT ALL
	    if (is_array($arr)) {
	    
		    // -- RETURN AS VARCHAR ----
		    if (count($arr) <= 2) return $arr[0];
		    
		    // FORM RESULT ARRAY TO RETURN TO USER
		    $res = array();
		    while ($arr) {
		    	$res[] = $arr;
		    	$arr = mysql_fetch_array($result);
		    }
		           
		    return $res;
	    } else {
	    	$id = mysql_insert_id($this->db);
			if ($id > 0) {
			  	return $id;
			}    	
	    }
	    
	    return '';
	}
	
	/**
	 * This function compare arrays and return array of SQL and array of Comments
	 *
	 * @param unknown_type $inArr
	 */
	public function checkCurrentDB ($inArr) {
		
		@set_time_limit(6000);
		
		$a_result = array();
		$a_result['COM'] = array();
		$a_result['SQL'] = array();
		
		$curArr = $this->getTableList('core_%');
	
		$a_ini_tables = $inArr['TABLES'];
		$a_cur_tables = $curArr;
		//echo "<pre>";print_r($curArr); die;
		
		reset($a_ini_tables);
		$a_cur_tables = $curArr;
		//echo "<pre>";print_r($a_cur_tables); die;

		
		// ----- COMPARING ARRAYS -------------------------------
		$op_pos = 0;
		reset($inArr);
		//echo "<PRE>";print_r($a_cur_tables);echo"</PRE>";die();
		while (list($key, $val) = each($a_ini_tables)) {
			// -- CHECK IF TABLE DOES NOT EXISTS
			$op_pos++;
			if (!isset($a_cur_tables[$key])) {
				$a_result['COM'][$op_pos] = "Table <b>$key</b> does not exist.";
				$a_result['SQL'][$op_pos] = $this->createTable($key, $a_ini_tables[$key]);
				// TODO: -- SQL for table creation
				
				continue;
			}
			
			$a_columns = $val['COLUMNS'];
			reset($a_columns);
			// -- CHECK COLUMNS 
			while (list($k, $v) = each($a_columns)) {
				//echo "<pre>";print_r($a_cur_tables); die;
				if(!isset($a_cur_tables[$key]['COLS'][$k])) {
					$a_result['COM'][$op_pos] = "Column <b>$k</b> for table <b>$key</b> does not exist.";
					$a_result['SQL'][$op_pos] = $this->alterTableAddColumn($key, $k, $v);
					// TODO: -- SQL for table creation
					$op_pos++;
					continue;
				}
				
				//-- CHECK IF WE NEED TO CORRECT TABLE
				reset($v);
				$need_to_alter_column = false;
				
				while (list($k1, $v1) = each($v)) {
					if ($v1 != $a_cur_tables[$key]['COLS'][$k][$k1]) {
						//echo $k1."--".$v1."--".$a_cur_tables[$key]['COLS'][$k][$k1];
						//echo "<PRE>";print_r($a_cur_tables[$key]['COLS'][$k]);echo"</PRE>";//die();
						$need_to_alter_column = true;
						break;
					}
				}
				
				if ($need_to_alter_column) {
					$a_result['COM'][$op_pos] = "Column <b>$k</b> for table <b>$key</b> differs from original column.";
					$a_result['SQL'][$op_pos] = $this->alterTableAlterColumn($key, $k, $v, $a_cur_tables[$key]['COLS'][$k]);
					$op_pos++;
					continue;
				}	
			}
			
			if (isset($val['FK']) and is_array($val['FK'])) {
				foreach ($val['FK'] as $k => $v) {
					if (!isset($a_cur_tables[$key]['FK'][$k])) {
						
						$tbl_engine = $a_cur_tables[$key]['ENGINE'];
						
						if (strtoupper($tbl_engine) != 'INNODB') {
							$a_result['COM'][$op_pos] = "Storage Engine for table <b>$key</b> should be <b>innodb</b>.";
							$a_result['SQL'][$op_pos] = "ALTER TABLE `$key` ENGINE = innodb;";
							$op_pos++;
						}
												
						$a_result['COM'][$op_pos] = "Foreing Key <b>$k</b> for table <b>$key</b> does not exist.";
						$a_result['SQL'][$op_pos] = $this->createFK($key, $k, $v);
						$op_pos++;
					} else {
						$tmp_arr = $a_cur_tables[$key]['FK'][$k];
						if ($tmp_arr['COL_NAME'] != $v['COL_NAME']
						    or $tmp_arr['REF_TABLE'] != $v['REF_TABLE']
						    or $tmp_arr['REF_COLUMN'] != $v['REF_COLUMN']
						    or $tmp_arr['ON_DELETE'] != $v['ON_DELETE']
						    or $tmp_arr['ON_UPDATE'] != $v['ON_UPDATE']
						) {
							$a_result['COM'][$op_pos] = "Foreing Key <b>$k</b> for table <b>$key</b> does not fit original constraint.";
							$a_result['SQL'][$op_pos] = $this->dropFK($key, $k) . '<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->' . $this->createFK($key, $k, $v);
							$op_pos++;
						}
					}
				}
			}
			
			// -- CHECK INDEXES --------
			if (isset($val['KEY']) and is_array($val['KEY'])) {
				foreach ($val['KEY'] as $k => $v) {
					if (!isset($a_cur_tables[$key]['KEY'][$k])) {
						$a_result['COM'][$op_pos] = "Index <b>$k</b> for table <b>$key</b> does not exist.";
						$a_result['SQL'][$op_pos] = $this->createIndex($key, $k, $v);
						$op_pos++;
					} else {
						$type_cur = '';
						if (isset($a_cur_tables[$key]['KEY'][$k]['TYPE'])) {
							if ($a_cur_tables[$key]['KEY'][$k]['TYPE'] == 'UNIQ') {
								$type_cur = 'UNIQ';	
							}
						}
						
						ksort($a_cur_tables[$key]['KEY'][$k]['COLS']);
						$cols_cur = '';
						foreach ($a_cur_tables[$key]['KEY'][$k]['COLS'] as $k1 => $v1) {
							$cols_cur .= $k1.'#';			
						}
						
						$type = '';
						if (isset($v['TYPE'])) {
							$type = $v['TYPE'];
						}
												
						ksort($v['COLUMNS']);
						$cols = '';
						foreach ($v['COLUMNS'] as $k1 => $v1) {
							$cols .= $k1.'#';			
						}

						if ($type_cur != $type or $cols != $cols_cur) {
							$a_result['COM'][$op_pos] = "Index <b>$k</b> for table <b>$key</b> differs from original index.";
							$a_result['SQL'][$op_pos] = $this->dropIndex($key, $k) . '<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->' . $this->createIndex($key, $k, $v);
							$op_pos++;
						}
						
					}
					

				}
			}
			
			
		}
		
		while (list($key, $val) = each($a_cur_tables)) {
			// -- CHECK IF TABLE DOES NOT EXISTS
			$op_pos++;
			
			
			$a_cur_columns = $val['COLS'];
			
			//echo "<pre>";print_r($a_cur_tables);echo "<br>";
			reset($a_columns);
			// -- CHECK COLUMNS 
			while (list($k, $v) = each($a_cur_columns)) {
				if(!isset($a_ini_tables[$key]['COLUMNS'][$k])) {
					
					$a_result['WARNING'][$op_pos] = "Таблица <b>$key</b> имеет столбец <b>$k</b>, которого нету в эталонной таблице.";
					//$a_result['SQL'][$op_pos] = $this->alterTableAddColumn($key, $k, $v);
					// TODO: -- SQL for table creation
					$op_pos++;
					continue;
				}
			}
		}
		//print_r($a_result['WARNING']);
		
		return $a_result;
	}
	
	private function dropIndex($inTable, $inInd) {
		return "ALTER TABLE `$inTable` DROP INDEX `$inInd`;";
	}
	
	private function createIndex($inTable, $inInd, $inArr) {

		$type = '';
		if (isset($inArr['TYPE']) and $inArr['TYPE'] == 'UNIQ') $type = ' UNIQUE';		
		$str = "CREATE$type INDEX `$inInd` ON `$inTable` (";
		foreach ($inArr['COLUMNS'] as $key => $val) {
			$str .= "`$key`, ";		
		}
		
		$str = trim($str, ', ');
		
		return $str . ');';		
	}
	
	private function dropFK($inTable, $inKey) {
		return "ALTER TABLE $inTable DROP FOREIGN KEY `$inKey`;";
	}
	
	private function createFK($inTable, $inKey, $inArr) {
		$col_name  = $inArr['COL_NAME'];
		$ref_table = $inArr['REF_TABLE'];
		$ref_col   = $inArr['REF_COLUMN'];
		
		$act_on_del = $inArr['ON_DELETE']; if ($act_on_del == '') $act_on_del = 'RESTRICT';
		$act_on_upd = $inArr['ON_UPDATE']; if ($act_on_upd == '') $act_on_upd = 'RESTRICT';		
		
		$str = "ALTER TABLE `$inTable` 
                  ADD CONSTRAINT `$inKey` FOREIGN KEY (`$col_name`)
				    REFERENCES `$ref_table` (`$ref_col`)
				    ON DELETE $act_on_del
				    ON UPDATE $act_on_upd;";
		
		return $str;
	}
	
	private function createTable($inTable, $inArr) {
		$str = "CREATE TABLE `$inTable` (";
		reset($inArr['COLUMNS']);
		
		/*
		            [ca_id] => Array
                (
                    [TYPE] => int(11)
                    [NULL] => NO
                    [DEFAULT] => 
                    [EXTRA] => auto_increment
                )
		*/
                
		
		while (list($key, $val) = each($inArr['COLUMNS'])) {
			$str .= "`$key` " . $val['TYPE'];
			if ($val['NULL'] == 'NO') {
				$str .= " NOT NULL";
			}
			if ($val['EXTRA'] != '') {
				$str .= " " . $val['EXTRA'];
			}
			if ($val['DEFAULT'] != '') {
				$str .= " DEFAULT " . $this->prepareDefault($val);
			}
			
			$str .= ', ';
		}
		
		if ($inArr['PRIMARY_KEY'] != '') {
			 $str .= " PRIMARY KEY (`" . $inArr['PRIMARY_KEY'] . "`), ";
		}
		
		if (isset($inArr['KEY']) and count($inArr['KEY']) > 0) {
			foreach ($inArr['KEY'] as $key => $val) {
				if (isset($val['TYPE']) && $val['TYPE'] == 'UNIQ') {
					$str .= " UNIQUE KEY (";
				} else {
					$str .= " KEY (";
				}
				foreach ($val['COLUMNS'] as $k => $v) {
					$str .= "`$k`, ";
				}
				$str = trim($str, ', ');
				$str .= '), ';
			}			
		}
		/*
		    [FK] => Array
        (
            [cms_aaa1_fk] => Array
                (
                    [COL_NAME] => s1
                    [REF_TABLE] => cms_zzz
                    [REF_COLUMN] => s1
                    [ON_DELETE] => NO ACTION
                    [ON_UPDATE] => CASCADE
                )

        )

        */
		
		if (isset($inArr['FK'])) {
			foreach ($inArr['FK'] as $key => $val) {
				$col_name  = $val['COL_NAME'];
				$ref_table = $val['REF_TABLE'];
				$ref_col   = $val['REF_COLUMN'];
				
				$act_on_del = $val['ON_DELETE']; if ($act_on_del == '') $act_on_del = 'RESTRICT';
				$act_on_upd = $val['ON_UPDATE']; if ($act_on_upd == '') $act_on_upd = 'RESTRICT';
				
				$str .= " CONSTRAINT `$key` FOREIGN KEY (`$col_name`) REFERENCES `$ref_table` (`$ref_col`) ON DELETE $act_on_del ON UPDATE $act_on_upd, ";	
			}
			
		}
		
		$str = trim($str, ', ');
		$str .= ') ';
		if (!isset($inArr['ENGINE'])) {
			$str .= ' ENGINE=MyISAM';
		} else {
			$str .= ' ENGINE='.$inArr['ENGINE'];
		}
		
		$str .= ' DEFAULT CHARSET=utf8;';
		
		return $str;
	}
	
	private function alterTableAlterColumn ($inTable, $inColumn, $inArr, $inCurArr) {
		
		$type = $inArr['TYPE'];
		$str  = "ALTER TABLE $inTable MODIFY $inColumn $type";
		
		if ($inArr['NULL'] == 'NO') {
			$str .= " NOT NULL";
		}
		$add_drop_default = "";
		if (!empty($inArr["EXTRA"])) {
			$str .= " " . $inArr["EXTRA"];
		}
		$default = $this->prepareDefault($inArr);
		if ($default !== 0 && ($default == '' || $default == "''") && strpos($type, 'int') !== false) {
			
		} else {
			if (strpos($type, 'text') === false && strpos($type, 'blob') === false && strpos($type, 'datetime') === false && strpos($type, 'timestamp') === false) {
				$str .= " DEFAULT $default";
			}
		}
		if (empty($inArr["DEFAULT"]) && $inArr['DEFAULT'] != $inCurArr['DEFAULT']) {
			$add_drop_default = "<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT--> ALTER TABLE $inTable  ALTER $inColumn DROP DEFAULT;";
		}
		
		return $str . ';' . $add_drop_default;
	}
	
	private function prepareDefault($inArr) {
		$def = $inArr['DEFAULT'];
		
		if (strtoupper($def) == 'CURRENT_TIMESTAMP' and strtoupper($inArr['TYPE']) == 'DATETIME') {return $def;}
		if (strtoupper($def) == 'CURRENT_TIMESTAMP' and strtoupper($inArr['TYPE']) == 'TIMESTAMP') {return $def;}
		
		return "'" . str_replace("'", "''", $def) . "'";
	}
	
	private function alterTableAddColumn($inTable, $inColName,  $inArr) {
		$type = $inArr['TYPE'];
		$nn   = '';
		if ($inArr['NULL'] == 'NO') {
			$nn = 'NOT NULL';
		}
		if ($inArr["DEFAULT"] == '') {
			$default = '';//"DEFAULT NULL";
		} else {
			$def = $this->prepareDefault($inArr);
			$default = "DEFAULT $def";			
		}
		$str = "ALTER TABLE $inTable ADD COLUMN $inColName $type $nn $default";
		
		return trim($str) . ';';
	}
	
	
	/**
	 * Return array of tables for Selected Schema and according to Table Template condition - correct 
	 * order taking into consideration foreigns for tables.
	 *
	 * @param unknown_type $inTableTemplate
	 * @param unknown_type $inSchema
	 */
	public function getTableList($inTableTemplate) {

		$strSQL = "SELECT table_name, 
		                  1 as col_1 
		             FROM INFORMATION_SCHEMA.TABLES
                    WHERE table_schema = '$this->current_db_name'
                          AND table_name LIKE '$inTableTemplate'
                    ORDER BY table_name";
		
		$rows = $this->exeSQL($strSQL);
				
		if (!is_array($rows)) $rows = array();
		
		$a_tables = array();
		while (list($key, $val) = each($rows)) {
			$a_tables[$val['table_name']] = 0; 	
		}
		
		// --- TAKE INFORMATION ABOUT 
		
		$strSQL = "SELECT usg.table_name,
                          usg.referenced_table_name,
                          usg.column_name,
                          usg.referenced_column_name,
                          usg.constraint_name,
                          tbl.table_comment
                     FROM information_schema.key_column_usage AS usg
                          JOIN information_schema.tables AS tbl
                               ON usg.table_name = tbl.table_name 
                                  AND tbl.table_schema = '$this->current_db_name'
                    WHERE constraint_schema = '$this->current_db_name'
                          AND referenced_table_name IS NOT NULL
                    ORDER BY table_name";
		
		$a_reffers  = array();
		$a_ref_cols = array();
		$a_comments = array();
		$rows = $this->exeSQL($strSQL);
		if (is_array($rows)) {
			while (list($key, $val) = each($rows)) {
				$a_reffers[$val['table_name']][$val['referenced_table_name']] = 1;
				$a_ref_cols[$val['table_name']][$val['constraint_name']] = $val;
				$a_comments[$val['table_name']] = $val['table_comment'];
			}
		}
		
		// --- SORTING TABLE
		$tbl_count = count($a_tables);
		
		$a_result_tables = array();
		
		reset($a_tables);
		$tmp_pos = 0;
		while ($tbl_count > 0) {	
			reset($a_tables);
			while (list($key, $val) = each($a_tables)) {
				if ($val == 1) continue;
				if (!$this->canTableBeAddedToFinalArray($key, $a_reffers, $a_tables)) continue;
				
				$a_tables[$key] = 1;
				$a_result_tables[$key] = array();	
				$tbl_count--;
			}		
			$tmp_pos++;
			if ($tmp_pos > 1000) break;
		}
		
		reset($a_result_tables);
		while (list($key, $val) = each($a_result_tables)) {
			
			
			$rows = $this->exeSQL("explain $key");
			$a_tmp = array();
			
			while (list($k, $v) = each($rows)) {
				while (list($k1, $v1) = each($v)) {
					if ($k1 == 'Field') continue;
					$a_tmp[$v['Field']][strtoupper($k1)] = $v1;	
				}
					
			}
			
			$strSQL = "SHOW CREATE TABLE " . $key;
			$row    = $this->exeSQL($strSQL);
						
			$script = $row[0]['Create Table'];				
			
			$a_columns = $a_tmp;
			if (!isset($a_result_tables[$key])) $a_result_tables[$key] = array();
			
			$a_result_tables[$key]['ENGINE'] = $this->getTableEngineInfo($script);
			
			$a_result_tables[$key]['COLS'] = $a_tmp;	
			if (isset($a_ref_cols[$key]) and is_array($a_ref_cols[$key])) {
				reset($a_ref_cols[$key]);
						
				while (list($k, $v) = each($a_ref_cols[$key])) {
					$a_oprs = $this->defineFkArr($v['table_name'], $v['column_name'], $script);
					
					$a_result_tables[$key]['FK'][$k]['COL_NAME']   = $v['column_name'];		
					$a_result_tables[$key]['FK'][$k]['REF_TABLE']  = $v['referenced_table_name'];		
					$a_result_tables[$key]['FK'][$k]['REF_COLUMN'] = $v['referenced_column_name'];		
					$a_result_tables[$key]['FK'][$k]['ON_DELETE']  = $a_oprs['ON_DEL'];		
					$a_result_tables[$key]['FK'][$k]['ON_UPDATE']  = $a_oprs['ON_UPD'];		
				}
			}
			
			reset($a_columns);
			while (list($k, $v) = each($a_columns)) {
				if ($v['KEY'] == 'PRI') {
					$a_result_tables[$key]['PRIMARY_KEY'] = $k;
					break;
				}	
			}
			
			$a_keys = $this->getTableKeyInfo($script);
			if (count($a_keys) > 0) {
				$a_result_tables[$key]['KEY'] = $a_keys;
			}
				
			
			
			$a_result_tables[$key]['SCRIPT'] = $script;	
			if (isset($a_comments[$key])) {
				$a_result_tables[$key]['COMM'] = $a_comments[$key];	
			} else {
				$a_result_tables[$key]['COMM'] = '';
			}
			
		}
		reset($a_result_tables);
		return $a_result_tables;
	}
	
	private function getTableEngineInfo($inScript) {
		$a_res = array();
		$a_tmp = explode(chr(10), $inScript);
		
		foreach ($a_tmp as $key => $val) {
			if (strpos($val, 'ENGINE=InnoDB')) return 'InnoDB';		
		}
		
		return 'MyISAM';
	}
	
	private function getTableKeyInfo($inScript) {
		$a_res = array();
		$a_tmp = explode(chr(10), $inScript);
		while (list($key, $val) = each($a_tmp)) {
			$val = trim($val);
			$cnt_name = '';
			if (strpos($val, 'KEY') === 0) {
				$a_val = explode('`', $val);
				while (list($k, $v) = each($a_val)) {
					if ($k % 2 == 0) continue;
					if ($k == 1) {
						$cnt_name = $v;
						if (!isset($a_res[$cnt_name])) $a_res[$cnt_name] = array();
						$a_res[$cnt_name]['TYPE'] = 'MUL';
						continue;	
					}
					if (!isset($a_res[$cnt_name]['COLS'])) $a_res[$cnt_name]['COLS'] = array();
					$a_res[$cnt_name]['COLS'][$v] = count($a_res[$cnt_name]['COLS']); 
				}
			}
			
			if (strpos($val, 'UNIQUE KEY') === 0) {
				$a_val = explode('`', $val);
				while (list($k, $v) = each($a_val)) {
					if ($k % 2 == 0) continue;
					if ($k == 1) {
						$cnt_name = $v;
						$a_res[$cnt_name]['TYPE'] = 'UNIQ';
						continue;	
					}
					$a_res[$cnt_name]['COLS'][$v] = @count($a_res[$cnt_name]['COLS']); 
				}
			}			
		}

		return $a_res;	
	}
	
	
	private function parseStr($val, $inType) {
		$arr = explode($inType, $val);

		$str = $arr[1];
		$arr = explode('ON UPDATE', $str);
		$str = $arr[0];
		
		
		if (strpos($str, 'SET NULL') !== false) return 'SET NULL';
		if (strpos($str, 'RESTRICT') !== false) return 'RESTRICT';
		if (strpos($str, 'CASCADE') !== false) return 'CASCADE';
		
		return 'NO ACTION';
	}
	
	private function defineFkArr($inTable, $inColumn, $script = '') {
		
		if ($script == '') {
			$strSQL = "SHOW CREATE TABLE $inTable";
			$row = $this->exeSQL($strSQL);
			$script = $row[0]['Create Table'];
		}
		$a_tmp = explode(chr(10), $script);
		
		$a_res['ON_DEL'] = 'NO ACTION';
		$a_res['ON_UPD'] = 'NO ACTION';
		
		while (list($key, $val) = each($a_tmp)) {
			if (strpos($val, "FOREIGN KEY (`$inColumn`) REFERENCES")) {

				$a_res['ON_DEL'] = $this->parseStr($val, 'ON DELETE');
				$a_res['ON_UPD'] = $this->parseStr($val, 'ON UPDATE');
				
			}
		}
		
		return $a_res;
			
	}
	
	private function addToInstallArray(&$inStr, $inVal) {
		
		$a_params = func_get_args();
		
		$html =  '<span style="color:#0000bb">$DB_ARRAY</span>';
		
		for ($x = 2; $x < count($a_params); $x++) {
			$html .= '<span style="color:#007700">[</span><span style="color:#dd0000">\''. $a_params[$x] . '\'</span><span style="color:#007700">]</span>';
		}
		
		$word_ln = strlen($a_params[count($a_params) - 1]);
		
		if ($inVal != 'array()') {
			$pad_cnt = 7;
			if ($a_params[4] == 'FK') {
				$pad_cnt = 10;
			}
			
			for ($x = 0; $x < $pad_cnt - $word_ln; $x++) {
				$html .= '&nbsp;';
			}
		}
		
		if (strpos($inVal, '"') === 0) {
			$html .=  '<span style="color:#007700"> = </span><span style="color:#dd0000">' . $inVal . '</span><span style="color:#007700">;</span>';
		} else {
			$html .=  '<span style="color:#007700"> = ' . $inVal . ';</span>';
		}
		
		$inStr .= $html;
	}
	
	private function addCommentToInstallArray(&$inStr, $inVal) {
		$inStr .="<span style=\"color:#888\">//$inVal</span></br>";
	}
	
	public function getSystemInstallDBArray() {
		$a_tables = $this->getTableList('core_%');
		$str = "";
		$this->addCommentToInstallArray($str, 'DB Array Initilizing');
		$this->addToInstallArray($str, 'array()');
		$this->addEmptyLines($str);
		$this->addCommentToInstallArray($str, 'TABLES contains information about tables');
		$this->addToInstallArray($str, 'array()', 'TABLES');
		$this->addEmptyLines($str, 1);

		while (list ($key, $val) = each($a_tables)) {
			$this->addCommentToInstallArray($str, 'TABLE: ' . $key);
			$this->addToInstallArray($str, 'array()', 'TABLES', $key);
			$this->addEmptyLines($str, 0);
			$this->addCommentToInstallArray($str, 'Table Enginge Definition');
			$this->addToInstallArray($str, '"' . $val['ENGINE'] . '"', 'TABLES', $key, 'ENGINE');
			
			$this->addEmptyLines($str, 0);
			if (isset($val['PRIMARY_KEY'])) {
				$this->addCommentToInstallArray($str, "Primary Key for $key");
				$this->addToInstallArray($str, '"' . $val['PRIMARY_KEY'] . '"', 'TABLES', $key, 'PRIMARY_KEY');
				$this->addEmptyLines($str, 0);				
			} else {
				$this->addCommentToInstallArray($str, "Primary Key is not defined for $key");
			}
			
			
			$this->addCommentToInstallArray($str, "Define array for columns");
			$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'COLUMNS');
			$this->addEmptyLines($str, 1);
			
			reset($val['COLS']);
			while (list ($k, $v) = each($val["COLS"])) {
				$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'COLUMNS', $k);
				$this->addEmptyLines($str, 0);
				$this->addToInstallArray($str, '"' . $v['TYPE'] . '"', 'TABLES', $key, 'COLUMNS', $k, 'TYPE');
				$this->addEmptyLines($str, 0);	
				$this->addToInstallArray($str, '"' . $v['NULL'] . '"', 'TABLES', $key, 'COLUMNS', $k, 'NULL');
				$this->addEmptyLines($str, 0);		
				$this->addToInstallArray($str, '"' . $v['DEFAULT'] . '"', 'TABLES', $key, 'COLUMNS', $k, 'DEFAULT');
				$this->addEmptyLines($str, 0);		
				$this->addToInstallArray($str, '"' . $v['EXTRA'] . '"', 'TABLES', $key, 'COLUMNS', $k, 'EXTRA');
				$this->addEmptyLines($str, 0);										
				$this->addEmptyLines($str, 0);
			
			}
			
			if (isset($val['FK']) and count($val['FK']) > 0) {
				$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'FK');
				$this->addEmptyLines($str, 1);
				reset($val['FK']);
				while (list ($k, $v) = each($val["FK"])) {	
					$this->addToInstallArray($str, '"' . $v['COL_NAME'] . '"', 'TABLES', $key, 'FK', $k, 'COL_NAME');
					$this->addEmptyLines($str, 0);	
					$this->addToInstallArray($str, '"' . $v['REF_TABLE'] . '"', 'TABLES', $key, 'FK', $k, 'REF_TABLE');
					$this->addEmptyLines($str, 0);		
					$this->addToInstallArray($str, '"' . $v['REF_COLUMN'] . '"', 'TABLES', $key, 'FK', $k, 'REF_COLUMN');
					$this->addEmptyLines($str, 0);	
					$this->addToInstallArray($str, '"' . $v['ON_DELETE'] . '"', 'TABLES', $key, 'FK', $k, 'ON_DELETE');
					$this->addEmptyLines($str, 0);	
					$this->addToInstallArray($str, '"' . $v['ON_UPDATE'] . '"', 'TABLES', $key, 'FK', $k, 'ON_UPDATE');
					$this->addEmptyLines($str, 0);							
				}			
			}
			
			if (isset($val['KEY'])) {
				$this->addEmptyLines($str, 0);
				$this->addCommentToInstallArray($str, 'Table Key array ');
				$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'KEY');
				$this->addEmptyLines($str, 0);
				reset($val['KEY']);
				
				while (list($k, $v) = each($val['KEY'])) {
					$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'KEY', $k);	
					$this->addEmptyLines($str, 0);
					if ($v['TYPE'] != 'MUL') {
						$this->addToInstallArray($str, '"' . $v['TYPE'] . '"', 'TABLES', $key, 'KEY', $k, 'TYPE');	
						$this->addEmptyLines($str, 0);
					}
					$this->addToInstallArray($str, 'array()', 'TABLES', $key, 'KEY', $k, 'COLUMNS');	
					$this->addEmptyLines($str, 0);
					while (list($kc, $vc) = each($v['COLS'])) {
						$this->addToInstallArray($str, '"' . $vc . '"', 'TABLES', $key, 'KEY', $k, 'COLUMNS', $kc);	
						$this->addEmptyLines($str, 0);	
					}
					
					
					$this->addEmptyLines($str, 0);
				}
				
			}
			
			// -- END LINE
			$this->addEmptyLines($str, 0);
		}
		
		return $str;
	}
	
	private function canTableBeAddedToFinalArray($inTableName, &$a_reffers, &$a_tables) {
		if (!isset($a_reffers[$inTableName])) return true;
		reset($a_reffers[$inTableName]);
		while (list($key, $val) = each($a_reffers[$inTableName])) {
			if (!isset($a_tables[$key])) $a_tables[$key] = -1;
			if ($a_tables[$key] != 1 and $inTableName != $key) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Returns DB Array for selected Module according to it's real DB
	 *
	 * @param unknown_type $inModuleID
	 */
	public function getModuleDBArray($inModuleID) {
		if (!is_numeric($inModuleID)) {
			$table_tmpl = 'cms_%';
		} else {
			$table_tmpl = 'mod_' . $inModuleID . '%';
		}

		$a_tables = $this->getTableList($table_tmpl);
		reset($a_tables);
		return $a_tables;
	}
	
	/**
	 * Return HTML DB Script for selected module
	 *
	 * @param unknown_type $inModuleID
	 * @return unknown
	 */
	public function getModuleDBScript($inModuleID) {
		$a_tables = $this->getModuleDBArray($inModuleID);
		
		$str = '';
		reset($a_tables);
		while (list($key, $val) = each($a_tables)) {
			    $val['SCRIPT'] = $this->prepareScriptToShow($val["SCRIPT"]);
			  	
			    
			    
				$str .= $val['SCRIPT'];
				$str .= ";<br/><br/><br/>";
		}
		
		
		$str .=  $this->prepareScriptToShow("/*INSERTION OF MENU ITEMS*/" . chr(10));
		$str .=  $this->prepareScriptToShow("DELETE FROM `mod_".$inModuleID."_menu`;" . chr(10));
		
		// --- MENUE INSERTION --------------------
		$strSQL = "SELECT * FROM mod_".$inModuleID."_menu ORDER BY mm_order";
		$str .= $this->prepareScriptToShow($this->getInsertScriptFromSQL("mod_".$inModuleID."_menu", $strSQL)) . chr(10);
		
		$str .=  $this->prepareScriptToShow("/*INSERTION INTO MODULE TABLE*/" . chr(10));
		
		$strSQL = "SELECT * FROM cms_modules WHERE m_global_id = $inModuleID";
		$str .= $this->prepareScriptToShow($this->getInsertScriptFromSQL('cms_modules', $strSQL, array('m_id'))) . chr(10);		
		
		
		return $str;
	}
	
	public function getInsertScriptFromSQL($inTable, $inSQL, $notIncludeArr = '') {
		if(!is_array($notIncludeArr)) {
			$notIncludeArr = array();
		}
		
		$a_not = array();
		
		foreach ($notIncludeArr as $key => $val) {
			$a_not[$val] = 1;			
		}
		
		$result = $this->exeSQL($inSQL);
		

		
		$str = "";
		
		foreach ($result as $key => $val) {
			$str.= "INSERT INTO `$inTable` (";

			foreach ($val as $k => $v) {
				if (is_numeric($k) or $a_not[$k] == 1) continue;
				$str .= ' `'.$k . '`, ';					
			}	
			
			$str = trim($str, ', ') . ')' . chr(10) . ' VALUES (';	
			
			foreach ($val as $k => $v) {
				$v = str_replace("'", "''", $v);
				if (is_numeric($k) or $a_not[$k] == 1) continue;
				if ($v == '') {
					$str .= "NULL" . ', ';
					continue;		
				}
				$str .= "'$v'" . ', ';					
			}			
			
			$str = trim($str, ', ') . ') ON DUPLICATE KEY UPDATE ' . chr(10);	
			
			foreach ($val as $k => $v) {
				$v = str_replace("'", "''", $v);
				if (is_numeric($k) or $a_not[$k] == 1) continue;
				if ($v == '') {
					$str .= "`$k` = NULL" . ', ';	
					continue;
				}
				$str .= "`$k` = '$v'" . ', ';		
			}
			
			$str = trim($str, ', ') . ';' . chr(10) . chr(10);
			
		}
		
		
		return trim($str, chr(10));
		
	}
	
	
	/**
	 * Function is used to display array for module installation
	 *
	 * @param integer $inModuleID
	 * @return varchar
	 */
	public function getModuleInstallDBArray($inModuleID) {
		$a_tables = $this->getModuleDBArray($inModuleID);
		
		$str = '<span style="color:#0000bb">$MODULE_INFO</span><span style="color:#007700">[</span><span style="color:#dd0000">\'mod_sql\'</span><span style="color:#007700">]</span><span style="color:#007700"> = array();</span>';
		$this->addEmptyLines($str, 2);
				
		$pos = 1;

		while (list ($key, $val) = each($a_tables)) {
			if ($key == "mod_$inModuleID"."_menu") continue;
			$this->addArrayItemToModuleInstallation($str, $pos, $val['SCRIPT']);
			$this->addEmptyLines($str, 1);
			$pos++;
		}
		
		
		return $str;
	}
	
	private function addArrayItemToModuleInstallation(&$inStr, $inPos, $inScript) {
		
		$inScript = $this->allignTableScript($inScript, 40 + strlen($inPos));
		
		$html = '<span style="color:#0000bb">$MODULE_INFO</span><span style="color:#007700">[</span><span style="color:#dd0000">\'mod_sql\'</span><span style="color:#007700">]</span><span style="color:#007700">[</span><span style="color:#dd0000">\'1.0.0\'</span><span style="color:#007700">]</span>';
		$html  .= '<span style="color:#007700">[</span><span style="color:#0000bb">\''. $inPos .'\'</span><span style="color:#007700">]</span>';
		$html  .= '<span style="color:#007700"> = </span>';
		$html  .= '<span style="color:#a50a0a"> "' . str_replace('"', '\\"', $inScript) . '"</span>';
		$html  .= '<span style="color:#007700">;</span>';
		$inStr .= $html;
	}
	
	private function allignTableScript ($inStr, $inPos) {
		$inStr = trim($inStr);
		$a_tmp = explode(chr(10), $inStr);
		$inStr = '';
		while (list ($key, $val) = each($a_tmp)) {
			if ($key > 0) {
				for ($x =0; $x < $inPos; $x++) {
					$val = '&nbsp;' . $val;
				}
			}
			$inStr .= $val . chr(10);
		}
		
		$inStr = trim($inStr, chr(10));
		
		$inStr = str_replace(chr(10), '<br>', $inStr);

		return $inStr;
	}
	
	private function addEmptyLines(&$inStr, $lineCount = 1) {
		$lineCount++;
		for ($x = 0; $x < $lineCount; $x++) {
			$inStr .= "<br />";
		}
	}
	
	static public function prepareScriptToShow($inStr) {
		 $inStr = htmlspecialchars($inStr);
		 $inStr = str_replace(chr(10), '<br>', $inStr);
		 $inStr = str_replace('/*', '<span style="color:#666">/*', $inStr);
		 $inStr = str_replace('*/', '*/</span>', $inStr);
		 $inStr = str_replace("''", chr(2), $inStr);
		 $a_tmp = explode('`', $inStr);
		 $str = '';
		 while (list($key, $val) = each($a_tmp)) {
		 	if ($key % 2 != 0) {
		 		$str .= "`<span style=\"color:#0933D0\">$val</span>`";
		 	} else {
		 		$str .= $val;
		 	}
		 }
		 
		$a_tmp = explode("'", $str);
		 $str = '';
		 while (list($key, $val) = each($a_tmp)) {
		 	if ($key % 2 != 0) {
		 		$str .= "'<span style=\"color:red\">$val</span>'";
		 	} else {
		 		$str .= $val;
		 	}
		 }		 
		 
		 $str = str_replace(chr(2), "<span style=\"color:red\">''</span>", $str);
		 
		 return $str;
	}
	
}

?>