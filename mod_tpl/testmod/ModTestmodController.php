<?
require_once("core/inc/classes/class.list.php");
require_once("core/inc/classes/class.edit.php");
require_once("core/inc/classes/class.tab.php");
require_once 'mod/reports/ModAjax.php';
require_once("general/Events.php");

class ModReportsController extends Common {
	
	private $amount = array();
	private $added = array();
	private $dates = array();
	
	public function __construct() {
		parent::__construct(new ModAjax());
		
		$this->checkRequest(array(
			'edit',
			'modal',
			'tab_' . $this->resId 
		));
	}
	
	public function action_index() {
		$config = Zend_Registry::getInstance()->get('config');
		$sid = session_id();
		if (isset($_GET['chartsCallback']) && $_GET['chartsCallback'] = 1) {
			//define ("SAVE_PATH",  $config->temp);
			if (!is_dir("mod/reports/image/" . $sid)) mkdir("mod/reports/image/" . $sid);
			define ("SAVE_PATH", "mod/reports/image/" . $sid);
			require_once 'mod/reports/FCExporter.php';
			die;
		}
		
		$img1 = "<img src=\"mod/reports/image/$sid/balance22.png\"/>";
		$img2 = "<img src=\"mod/reports/image/$sid/balance_22.png\"/>";
		$img3 = "<img src=\"mod/reports/image/$sid/dynamic22.png\"/>";
		$img4 = "<img src=\"mod/reports/image/$sid/balance33.png\"/>";				

		if (isset($_GET['pdf']) && $_GET['pdf'] = 1) {
			$res = $this->prepareData($_GET['begin'], $_GET['end']);
			$tpl = new Templater('mod/reports/pdf.tpl');
			$tpl->assign('{DT_BEG}', date('d-m-Y', strtotime($_GET['begin'])));
			$tpl->assign('{DT_END}', date('d-m-Y', strtotime($_GET['end'])));
			include("core/ext/MPDF54/mpdf.php");

			$mpdf = new mPDF('UTF-8-s', '', 0, '', 25,15,16,40);
			//$mpdf->mirrorMargins = 1;	// Use different Odd/Even headers and footers and mirror margins
			$mpdf->SetDisplayMode('fullpage');
			$mpdf->defaultheaderfontsize = 12;	/* in pts */
			$mpdf->defaultheaderfontstyle = 'B';	/* blank, B, I, or BI */
			$mpdf->defaultheaderline = 1; 	/* 1 to include line below header/above footer */
			
			$mpdf->defaultfooterfontsize = 6;	/* in pts */
			$mpdf->defaultfooterfontstyle = 'blank';	/* blank, B, I, or BI */
			$mpdf->defaultfooterline = 1; 	/* 1 to include line below header/above footer */
	
			//$mpdf->SetHeader($header);
			
			//$mpdf->setHTMLFooter($tpl->parse());
			
			
			$mpdf->SetAutoFont(AUTOFONT_ALL);
			$mpdf->WriteHTML(file_get_contents('core/html/default/style2.css'), 1);
			 
			
			if (count($res)) {
				$tpl->touchBlock('row');
				foreach ($res as $k => $value) {
					$tpl->assign('{NUM}', $k + 1);
					foreach ($value as $i => $val) {
						if ($i == 0) continue;
						$tpl->assign("{C$i}", $val);
					}
				}
				$tpl->reassignBlock('row');
				foreach ($this->amount as $key => $value) {
					$tpl->assign("{" . $key . "}", $value);
				}
				$tpl->touchBlock('amount');
				$approve2 = $this->amount['ca_approve2'] + $this->amount['si_approve2'] + $this->amount['db_approve2'] + $this->amount['na_approve2'] + $this->amount['em_approve2'];
				$reject2 = $this->amount['ca_reject2'] + $this->amount['si_reject2'] + $this->amount['db_reject2'] + $this->amount['na_reject2'] + $this->amount['em_reject2'];
				$tpl->assign("{all2}", ($approve2 + $reject2));
				$tpl->assign("{approve2}", $approve2); 
				$tpl->assign("{reject2}", $reject2);
				$tpl->assign("{res2}", ($this->amount['ca_res2'] + $this->amount['si_res2'] + $this->amount['db_res2'] + $this->amount['na_res2'] + $this->amount['em_res2']));
				
				$tpl->assign("{img1}",$img1);
				$tpl->assign("{img2}",$img2);
				$tpl->assign("{img3}",$img3);
				$tpl->assign("{img4}",$img4);		
			} else {
				$tpl->touchBlock('empty');
				$tpl->assign("{img1}", "");
				$tpl->assign("{img2}", "");
				$tpl->assign("{img3}", "");
				$tpl->assign("{img4}", "");
			}
			
			
			$mpdf->WriteHTML($tpl->parse());
			$event = new Events();
			$event->addEventReportDownload($_GET['begin'] . '-' . $_GET['end']);
			$mpdf->Output('report_' . $_GET['begin'] . '-' . $_GET['end'] . '.pdf', 'D');
			unlink($_SERVER['DOCUMENT_ROOT'] . "/mod/reports/image/" . session_id() . "/balance_22.png");
			unlink($_SERVER['DOCUMENT_ROOT'] . "/mod/reports/image/" . session_id() . "/balance22.png");
			unlink($_SERVER['DOCUMENT_ROOT'] . "/mod/reports/image/" . session_id() . "/balance33.png");
			unlink($_SERVER['DOCUMENT_ROOT'] . "/mod/reports/image/" . session_id() . "/dynamic22.png");
			rmdir($_SERVER['DOCUMENT_ROOT'] . "/mod/reports/image/" . session_id());
			die;
			//echo "<PRE>";print_r($res);echo"</PRE>";die();
		}
	}
	
	public function action_sources() {	
		
		$this->printXajax();
		$this->printJs("core/mod/charts/fusioncharts.com/Charts/ExportCharts.js");
		$this->printJs("core/js/jquery/lib/jquery.js");		
		$tab = new tabs($this->resId); 

		$title = "Формирование внутреннего отчета";
		//Tool::fb($_SERVER);
		$tab->beginContainer($title);
		$edit = new editTable($this->resId);
		$edit->error = '';
		if (!empty($_POST['control'])) {
			if (!empty($_POST['control']['begin_date'])) {
				$begin_date = $_POST['control']['begin_date'];
			} else {
				$edit->error .= 'Заполните начальную дату';
			}
			if (!empty($_POST['control']['end_date']) && !empty($_POST['control']['begin_date']) && $_POST['control']['begin_date'] > $_POST['control']['end_date']) {
				$edit->error .= 'Начальная дата не может быть больше конечной';
			}
			$end_date = $_POST['control']['end_date'];
		} else {
			$begin_date = date('Y-m-d');
			$end_date = date('Y-m-d');
		}
		
		$edit->SQL = array(array('id' => 1, 'begin_date' => $begin_date, 'end_date' => $end_date));
		$edit->addControl("Начальная дата:", "DATE", '', '', '', true);
		$edit->addControl("Конечная дата:", "DATE", '');
		$edit->classText['SAVE'] = "Сформировать отчет";
		$edit->showTable();
		if (!$edit->error && !empty($_POST['control'])) {
			$this->buildReport($_POST['control']);
			$event = new Events();
			$event->addEventReport($_POST['control']['begin_date'] . '-' . $_POST['control']['end_date']);
		}
		$tab->endContainer();
	}
	
	private function buildReport($data) {

		$list = new listTable($this->resId); 
		//$list->roundRecordCount = true;
		
		$list->SQL = "SELECT 1";
		$list->addHeader(array(
			'Дата' => array('row' => 2, 'replace' => true),
			'Каналы связи' => array('col' => 4), 
			'Интернет сайты' => array('col' => 4), 
			'ЦОД' => array('col' => 4), 
			'Сетевое адресное пространство' => array('col' => 4), 
			'Электронная почта' => array('col' => 4), 
			'Всего ресурсов' => array('col' => 4), 
			'ВОЛС' => array('col' => 4)
		));
		$list->addColumn("<small>Дата</small>", "", "DATE", "", "", false);
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Зарег.</small>", "", "TEXT", "", "", false);
		
		$list->addColumn("<small>Рассм.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Подтв.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Откл.</small>", "", "TEXT", "", "", false);
		$list->addColumn("<small>Принято</small>", "", "TEXT", "", "", false);
		
		//$list->classText['ADD'] = "Выгрузить отчет";
		//$list->addURL = "?module=" . $this->module . "&begin=" . $data['begin_date'] . '&end=' . $data['end_date'] . "&pdf=1";
		$list->noCheckboxes = 1;
		
		$list->getData();
		$res = $this->prepareData($data['begin_date'], $data['end_date']);
		
		$list->data = $res;
		$list->setRecordCount(count($res));
		$list->showTable();
		$tpl = new Templater("mod/reports/download_report.tpl");
		$tpl->assign('{begin_date}', $data['begin_date']);
		$tpl->assign('{end_date}', $data['end_date']);
		
		if (count($res)) {
			include("core/mod/charts/fusioncharts.com/FusionCharts.php");
			$strXML  = "<graph  caption='В период с " . $data['begin_date'] . " по " . $data['end_date'] . "'  divLineColor='FFFFFF' bgAlpha='0'  rotateNames='0' rotateNames='0'  numVDivLines='6' formatNumberScale='0' decimalPrecision='0' divlineDecimalPrecision='0' limitsDecimalPrecision='0' exportEnabled='1' exportAtClient='0' exportAction='save' showExportDialog='0' exportFileName='balance22' exportHandler='index.php?module=reports&chartsCallback=1'>";      
			$strXML .= "<categories>";
			            	$strXML .= "<category name='Каналы связи'/>";
			            	$strXML .= "<category name='Интернет сайты'/>";
			            	$strXML .= "<category name='ЦОД'/>";
			            	$strXML .= "<category name='Сетевое адр. пространство'/>";
			            	$strXML .= "<category name='Электронная почта'/>";
			            $strXML .= "</categories>";
			            $strXML .= "<dataset seriesname='Рассмотрено' color='cda07F' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_all2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['si_all2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['db_all2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['na_all2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['em_all2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Подтверждено' color='3300ff' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_approve2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['si_approve2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['db_approve2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['na_approve2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['em_approve2']) . "'/>";
			        	$strXML .= "</dataset>";
			        	$strXML .= "<dataset seriesname='Отклонено' color='00ff33' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_reject2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['si_reject2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['db_reject2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['na_reject2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['em_reject2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Зарегистрировано' color='FF3300' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_res2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['si_res2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['db_res2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['na_res2']) . "'/>";
			                $strXML .= "<set value='" . ($this->amount['em_res2']) . "'/>";
			        	$strXML .= "</dataset>";        
			            	
			
			$strXML  .= "</graph>";
		
			echo '<table><tr valign="top"><td>';
			echo renderChart("mod/reports/fusioncharts.com/MSColumn3D.swf", "", $strXML, "balance22", 900, 300, false, true);
			echo '</td><td>';
			$strXML  = "<graph  caption='В период с " . $data['begin_date'] . " по " . $data['end_date'] . "' legendIconScale='0' legendNumColumns='1' divLineColor='FFFFFF' rotateNames='0' bgAlpha='0' decimalPrecision='0' exportEnabled='1' divlineDecimalPrecision='0' limitsDecimalPrecision='0'  numVDivLines='6' exportEnabled='1' exportAtClient='0' exportAction='save' showExportDialog='0' exportFileName='balance_22' exportHandler='index.php?module=reports&chartsCallback=1'>";   
			            $strXML .= "<categories>";
			            	$strXML .= "<category name='Всего ресурсов'/>";
			            $strXML .= "</categories>";
			            $strXML .= "<dataset seriesname='Рассмотрено' color='cda07F' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_all2'] + $this->amount['si_all2'] + $this->amount['db_all2'] + $this->amount['na_all2'] + $this->amount['em_all2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Подтверждено' color='3300ff' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_approve2'] + $this->amount['si_approve2'] + $this->amount['db_approve2'] + $this->amount['na_approve2'] + $this->amount['em_approve2']) . "'/>";
			        	$strXML .= "</dataset>";
			        	$strXML .= "<dataset seriesname='Отклонено' color='00ff33' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_reject2'] + $this->amount['si_reject2'] + $this->amount['db_reject2'] + $this->amount['na_reject2'] + $this->amount['em_reject2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Зарегистрировано' color='FF3300' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['ca_res2'] + $this->amount['si_res2'] + $this->amount['db_res2'] + $this->amount['na_res2'] + $this->amount['em_res2']) . "'/>";
			        	$strXML .= "</dataset>";        
			            $strXML .= "</graph>";
			echo renderChart("mod/reports/fusioncharts.com/MSColumn3D.swf", "", $strXML, "balance_22", 300, 320, false, true);
			echo '</td></tr><tr><td>';
			$strXML  = "<graph  caption='Динамика в период с " . $data['begin_date'] . " по " . $data['end_date'] . "' bgColor='F5FFF5' divLineColor='FFFFFF' showBorder='0' rotateNames='1' bgAlpha='0' numDivLines='6' numVDivLines='" . count($this->dates[$data['begin_date'] . $data['end_date']]) . "' divlinecolor='D7D8D3' showAlternateHGridColor='1' alternateHGridColor='D7D8D3' alternateHGridAlpha='20' decimalPrecision='0' divlineDecimalPrecision='0' canvasBorderThickness='1' limitsDecimalPrecision='0' exportEnabled='1' exportAtClient='0' exportAction='save' showExportDialog='0' exportFileName='dynamic22' exportHandler='index.php?module=reports&chartsCallback=1' >";
			                $strXML .= "<categories>";
			
			$tmp = array();
			foreach ($this->dates[$data['begin_date'] . $data['end_date']] as $d => $v) {
				$all = 0;
				$view = 0;
				$strXML .= "<category name='" . date('d-m-y', strtotime($d)) . " '/>";
				if (isset($v['approved'])) {
					$view += count($v['approved']);
				}
				if (isset($v['rejected'])) {
					$view += count($v['rejected']);
				}
				if (isset($v['added'])) {
					$all += count($v['added']);
				}
				
				$tmp[$d] = array($all, $view);
			}
			$strXML .= "</categories><dataset seriesName='Всего заявок' color='F6BD0F'>";
			foreach ($tmp as $d => $v) {
				$strXML .= "<set value='" . $v[0] . "'/>";
			}
			$strXML .= "</dataset><dataset seriesName='Рассмотрено заявок' color='A66EDD'>";
			foreach ($tmp as $d => $v) {
				$strXML .= "<set value='" . $v[1] . "'/>";
			}
			$strXML .= "</dataset>";
			$strXML .= "</graph>";
				
			echo renderChart("mod/reports/fusioncharts.com/MSLine.swf", "", $strXML, "dynamic22", 900, 300, false, true);
			echo '</td><td>';
			$strXML  = "<graph  caption='В период с " . $data['begin_date'] . " по " . $data['end_date'] . "'legendIconScale='0' legendNumColumns='1' divLineColor='FFFFFF' rotateNames='0'  numDivLines='6' bgAlpha='0' decimalPrecision='0' divlineDecimalPrecision='0' limitsDecimalPrecision='0' exportEnabled='1' exportAtClient='0' exportAction='save' showExportDialog='0' exportFileName='balance33' exportHandler='index.php?module=reports&chartsCallback=1'>";
			            $strXML .= "<categories>";
			            	$strXML .= "<category name='Всего ВОЛС'/>";
			            $strXML .= "</categories>";
			            $strXML .= "<dataset seriesname='Рассмотрено' color='cda07F' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['op_all2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Подтверждено' color='3300ff' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['op_approve2']) . "'/>";
			        	$strXML .= "</dataset>";
			        	$strXML .= "<dataset seriesname='Отклонено' color='00ff33' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['op_reject2']) . "'/>";
			        	$strXML .= "</dataset>";
					    $strXML .= "<dataset seriesname='Принято' color='FF3300' showValues='1'>";
			                $strXML .= "<set value='" . ($this->amount['op_res2']) . "'/>";
			        	$strXML .= "</dataset>"; 			  	
			            $strXML .= "</graph>";
			echo renderChart("mod/reports/fusioncharts.com/MSColumn3D.swf", "", $strXML, "balance33", 300, 320, false, true);
			echo '</td></tr></table>';
			$tpl->assign('class="button"', 'class="buttonDisabled" disabled="disabled"');
		}
		echo $tpl->parse();			
			
		/*
		echo '<div><div style="float:left;width:100px;">Рассмотрено: </div>';
		echo '<div style="float:left">Каналы связи: ' . $this->amount['ca_all2'];
		echo '<br/>Интернет сайты: ' . $this->amount['si_all2'];
		echo '<br/>ЦОД: ' . $this->amount['db_all2'];
		echo '<br/>Сетевое адресное пространство: ' . $this->amount['na_all2'];
		echo '<br/>Электронная почта: ' . $this->amount['em_all2'];
		echo '<br/>Всего ресурсов: ' . ($this->amount['ca_all2'] + $this->amount['si_all2'] + $this->amount['db_all2'] + $this->amount['na_all2'] + $this->amount['em_all2']);
		echo '</div></div>';
		
		echo '<div style="clear:both"><div style="float:left;width:100px;">Подтверждено: </div>';
		echo '<div style="float:left">Каналы связи: ' . $this->amount['ca_approve2'];
		echo '<br/>Интернет сайты: ' . $this->amount['si_approve2'];
		echo '<br/>ЦОД: ' . $this->amount['db_approve2'];
		echo '<br/>Сетевое адресное пространство: ' . $this->amount['na_approve2'];
		echo '<br/>Электронная почта: ' . $this->amount['em_approve2'];
		echo '<br/>Всего ресурсов: ' . ($this->amount['ca_approve2'] + $this->amount['si_approve2'] + $this->amount['db_approve2'] + $this->amount['na_approve2'] + $this->amount['em_approve2']);
		echo '</div></div>';
		
		echo '<div style="clear:both"><div style="float:left;width:100px;">Отклонено: </div>';
		echo '<div style="float:left">Каналы связи: ' . $this->amount['ca_reject2'];
		echo '<br/>Интернет сайты: ' . $this->amount['si_reject2'];
		echo '<br/>ЦОД: ' . $this->amount['db_reject2'];
		echo '<br/>Сетевое адресное пространство: ' . $this->amount['na_reject2'];
		echo '<br/>Электронная почта: ' . $this->amount['em_reject2'];
		echo '<br/>Всего ресурсов: ' . ($this->amount['ca_reject2'] + $this->amount['si_reject2'] + $this->amount['db_reject2'] + $this->amount['na_reject2'] + $this->amount['em_reject2']);
		echo '</div></div>';
		
		echo '<div style="clear:both"><div style="float:left;width:100px;">Зарегистрировано: </div>';
		echo '<div style="float:left">Каналы связи: ' . $this->amount['ca_res2'];
		echo '<br/>Интернет сайты: ' . $this->amount['si_res2'];
		echo '<br/>ЦОД: ' . $this->amount['db_res2'];
		echo '<br/>Сетевое адресное пространство: ' . $this->amount['na_res2'];
		echo '<br/>Электронная почта: ' . $this->amount['em_res2'];
		echo '<br/>Всего ресурсов: ' . ($this->amount['ca_res2'] + $this->amount['si_res2'] + $this->amount['db_res2'] + $this->amount['na_res2'] + $this->amount['em_res2']);
		echo '</div></div>';*/
	}
	
	private function getData($begin_date, $end_date) {
		$res = $this->db->fetchAll("SELECT order_id, DATE_FORMAT(date_approve, '%Y-%m-%d') AS date_approve, DATE_FORMAT(date_reject, '%Y-%m-%d') AS date_reject 
					  FROM mod_crypto_signed
					 WHERE (date_approve > :b 
					   AND date_approve < :e 
					   AND approve_sign IS NOT NULL
					   AND reject_sign IS NULL
					   AND date_reject IS NULL) 
					   OR
					   (date_reject > :b 
					   AND date_reject < :e 
					   AND reject_sign IS NOT NULL
					   AND approve_sign IS NULL
					   AND date_approve IS NULL)
					   ",
					array('b' => $begin_date, 'e' => $end_date));
		$res_added = $this->db->fetchAll("SELECT DATE_FORMAT(date_added, '%Y-%m-%d') AS date_added, order_id
				FROM mod_crypto_signed
					 WHERE DATE_FORMAT(date_added, '%Y-%m-%d') >= :b 
					   AND DATE_FORMAT(date_added, '%Y-%m-%d') <= :e",
			array('b' => $begin_date, 'e' => $end_date));
				
		$dates = array();
		foreach ($res as $value) {
			if (!empty($value['date_approve'])) {
				$dates[$value['date_approve']]['approved'][] = $value['order_id'];
			} else if (!empty($value['date_reject'])) {
				$dates[$value['date_reject']]['rejected'][] = $value['order_id'];
			} 
		}
		foreach ($res_added as $value) {
			$dates[$value['date_added']]['added'][] = $value['order_id'];
		}
		ksort($dates);
		$this->dates[$begin_date . $end_date] = $dates;
		return $dates;
	}
	
	private function prepareData($begin_date, $end_date) {
		if (empty($this->dates[$begin_date . $end_date])) {
			$dates = $this->getData($begin_date, $end_date);
		} else {
			$dates = $this->dates[$begin_date . $end_date];
		}
		//echo "<PRE>";print_r($dates);echo"</PRE>";die();
		$res = array();
		$this->amount = array(
			"si_all2" => 0,
			"si_approve2" => 0,
			"si_reject2" => 0,
			"si_res2" => 0,
			"db_all2" => 0,
			"db_approve2" => 0,
			"db_reject2" => 0,
			"db_res2" => 0,
			"em_all2" => 0,
			"em_approve2" => 0,
			"em_reject2" => 0,
			"em_res2" => 0,
			"ca_all2" => 0,
			"ca_approve2" => 0,
			"ca_reject2" => 0,
			"ca_res2" => 0,
			"na_all2" => 0,
			"na_approve2" => 0,
			"na_reject2" => 0,
			"na_res2" => 0,
			"op_all2" => 0,
			"op_approve2" => 0,
			"op_reject2" => 0,
			"op_res2" => 0
		);
		$this->added = array(
			"si_all" => 0,
			"db_all" => 0,
			"em_all" => 0,
			"ca_all" => 0,
			"na_all" => 0,
			"op_all" => 0
		);
		foreach ($dates as $dt => $value) {
			$si_all = 0;
			$si_approve = 0;
			$si_reject = 0;
			$si_res = 0;
			$db_all = 0;
			$db_approve = 0;
			$db_reject = 0;
			$db_res = 0;
			$em_all = 0;
			$em_approve = 0;
			$em_reject = 0;
			$em_res = 0;
			$ca_all = 0;
			$ca_approve = 0;
			$ca_reject = 0;
			$ca_res = 0;
			$na_all = 0;
			$na_approve = 0;
			$na_reject = 0;
			$na_res = 0;
			$op_all = 0;
			$op_approve = 0;
			$op_reject = 0;
			$op_res = 0;
			if (isset($value['added']) && is_array($value['added'])) {
				foreach ($value['added'] as $order_id) {
					$prefix = substr($order_id, 0, 2);
					if ($prefix == 'SI') {
						$si_all++;
					} elseif ($prefix == 'DB') {
						$db_all++;
					} elseif ($prefix == 'EM') {
						$em_all++;
					} elseif ($prefix == 'CA') {
						$ca_all++;
					} elseif ($prefix == 'NA') {
						$na_all++;
					} elseif ($prefix == 'OP') {
						$op_all++;
					}
				}
			}
			if (isset($value['approved']) && is_array($value['approved'])) {
				foreach ($value['approved'] as $order_id) {
				
					$prefix = substr($order_id, 0, 2);
					$add 	= substr($order_id, 2, 1);
				
					if ($add != 'C') $c = 1;
					else $c = 0;
				
					if ($prefix == 'SI') {
						$si_approve++;
						$si_res += $c;
					} elseif ($prefix == 'DB') {
						$db_approve++;
						$db_res += $c;
					} elseif ($prefix == 'EM') {
						$em_approve++;
						$em_res += $c;
					} elseif ($prefix == 'CA') {
						$ca_approve++;
						$ca_res += $c;
					} elseif ($prefix == 'NA') {
						$na_approve++;
						$na_res += $c;
					} elseif ($prefix == 'OP') {
						$op_approve++;
						$op_res += $c;
					}
				}
			}
			if (isset($value['rejected']) && is_array($value['rejected'])) {
				foreach ($value['rejected'] as $order_id) {
				
					$prefix = substr($order_id, 0, 2);
					$add 	= substr($order_id, 2, 1);
				
					if ($add != 'C') $c = 1;
					else $c = 0;
				
					if ($prefix == 'SI') {
						$si_reject++;
						//$si_res += $c;
					} elseif ($prefix == 'DB') {
						$db_reject++;
						//$db_res += $c;
					} elseif ($prefix == 'EM') {
						$em_reject++;
						//$em_res += $c;
					} elseif ($prefix == 'CA') {
						$ca_reject++;
						//$ca_res += $c;
					} elseif ($prefix == 'NA') {
						$na_reject++;
						//$na_res += $c;
					} elseif ($prefix == 'OP') {
						$op_reject++;
						//$op_res += $c;
					}
				}
			}
			
			$ca = $ca_approve + $ca_reject;
			$si = $si_approve + $si_reject;
			$db = $db_approve + $db_reject;
			$na = $na_approve + $na_reject;
			$em = $em_approve + $em_reject;
			$res[] = array($dt, $dt,
				$ca, $ca_approve, $ca_reject, $ca_res,
				$si, $si_approve, $si_reject, $si_res,
				$db, $db_approve, $db_reject, $db_res,
				$na, $na_approve, $na_reject, $na_res,
				$em, $em_approve, $em_reject, $em_res,
				($ca + $si + $db + $na + $em), 
				($ca_approve + $si_approve + $db_approve + $na_approve + $em_approve), 
				($ca_reject + $si_reject + $db_reject + $na_reject + $em_reject), 
				($ca_res + $si_res + $db_res + $na_res + $em_res),
				($op_approve + $op_reject), $op_approve, $op_reject, $op_res,
			); 
			
			$this->amount['ca_all2'] += ($ca_approve + $ca_reject);
			$this->amount['ca_approve2'] += $ca_approve;
			$this->amount['ca_reject2'] += $ca_reject;
			$this->amount['ca_res2'] += $ca_res;
			$this->amount['si_all2'] += ($si_approve + $si_reject);
			$this->amount['si_approve2'] += $si_approve;
			$this->amount['si_reject2'] += $si_reject;
			$this->amount['si_res2'] += $si_res;
			$this->amount['db_all2'] += ($db_approve + $db_reject);
			$this->amount['db_approve2'] += $db_approve;
			$this->amount['db_reject2'] += $db_reject;
			$this->amount['db_res2'] += $db_res;
			$this->amount['em_all2'] += ($em_approve + $em_reject);
			$this->amount['em_approve2'] += $em_approve;
			$this->amount['em_reject2'] += $em_reject;
			$this->amount['em_res2'] += $em_res;
			$this->amount['na_all2'] += ($na_approve + $na_reject);
			$this->amount['na_approve2'] += $na_approve;
			$this->amount['na_reject2'] += $na_reject;
			$this->amount['na_res2'] += $na_res;
			$this->amount['op_all2'] += ($op_approve + $op_reject);
			$this->amount['op_approve2'] += $op_approve;
			$this->amount['op_reject2'] += $op_reject;
			$this->amount['op_res2'] += $op_res;
			
			$this->added['ca_all'] += $ca_all;
			$this->added['si_all'] += $si_all;
			$this->added['db_all'] += $db_all;
			$this->added['em_all'] += $em_all;
			$this->added['na_all'] += $na_all;
			$this->added['op_all'] += $op_all;
		}
		return $res;
	}
	
}