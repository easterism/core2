<?
$tab = new tabs('audit'); 

$title = "Аудит";
$pathToArray = "core2/mod/admin/audit/db_array.php";

$tab->beginContainer($title);

	if ($tab->activeTab == 1) {
		//$o_master = new DBMaster(); print_r($o_master->getSystemInstallDBArray());
		if (!file_exists($pathToArray)) {
			echo "Cannot find file";
			die;
		} else {
				require_once $pathToArray;
				$o_master = new DBMaster();
				$a_result = $o_master->checkCurrentDB($DB_ARRAY);			
				$AuditNamespace = new Zend_Session_Namespace('Audit');
				//echo "<pre>";print_r($AuditNamespace->RES);die; 
				//echo "<pre>";print_r($a_result); 
			
			if (isset($_GET['db_update_one']) && $_GET['db_update_one'] == 1)
				if ($a_result['COM'] > 0 && is_array($AuditNamespace->RES)) {					
						$a_tmp = explode('<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->', $AuditNamespace->RES['SQL'][$_GET['number']]);						
						if ($a_tmp != ''){
							$o_master->exeSQL($a_tmp[0]);
						
						}
											
					$a_result = $o_master->checkCurrentDB($DB_ARRAY);											 		
			}	
			
			$AuditNamespace->RES = $a_result;
			 
			if (isset($_GET['db_update']) && $_GET['db_update'] == 1) {
				if ($a_result['COM'] > 0) {
					while (list($key, $val) = each($a_result['COM'])) {
						$a_tmp = explode('<!--NEW_LINE_FOR_DB_CORRECT_SCRIPT-->', $a_result['SQL'][$key]);
						while (list($k, $v) = each($a_tmp)) {
							if ($v != '') {
								$o_master->exeSQL($v);
							}
						}
					}
				$a_result = $o_master->checkCurrentDB($DB_ARRAY);					
				} 
			}
			
			
			if (count($a_result['COM']) > 0) {
				reset($a_result['COM']);
				while (list($key, $val) = each($a_result['COM'])) {
					echo $val . '<span class="auditSql"><i>(' . $a_result['SQL'][$key] . ')</i></span>' . "&nbsp&nbsp<a href=\"javascript:load('?module=admin&action=audit&loc=core&db_update_one=1&number=".$key."')\"><b><span class=\"auditLineCorrect\">Исправить</span></b></a><br />";
				}
				echo "<input class=\"auditButton\" type=\"button\" value=\"Исправить все\" onclick=\"load('?module=admin&action=audit&loc=core&db_update=1')\"/>";
				echo "<h3>Предупреждения:</h3>";
				foreach ($a_result['WARNING'] as $val) {
					echo "<span class=auditWarningText>".$val."</span></br>";						
				}
				die;
			}
		
			echo "Все ОК";
		}  
		
	}
$tab->endContainer();

