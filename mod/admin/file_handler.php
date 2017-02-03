<?
	require_once("core2/inc/classes/Image.php");
	$config = Zend_Registry::get('config');
	if (!empty($_GET['fileid'])) {
		$t = trim(strip_tags($_GET['t']));
		$res2 = $this->db->fetchRow("SELECT * FROM `{$t}_files` WHERE id=?", $_GET['fileid']);
		if (!$res2) {
			throw new Exception(404);
		}
		header("Content-Disposition: filename=\"{$res2['filename']}\"");
		$Image = new Image();
		$res = $Image->outString($res2['content'], $res2['type']);
		if (!$res) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header('Content-Disposition: attachment;filename="' . $res2['filename'] . '"');
			header("Content-Transfer-Encoding: binary");
			echo $res2['content'];
			die;
		}
		die;
	}
	elseif (!empty($_GET['thumbid'])) {
		$t = trim(strip_tags($_GET['t']));
		$res2 = $this->db->fetchRow("SELECT * FROM `{$t}_files` WHERE id=?", $_GET['thumbid']);
		if (!$res2) {
			throw new Exception(404);
		}
		header("Content-type: {$res2['type']}");
		header("Content-Disposition: filename=\"{$res2['filename']}\"");
		if ( ! empty($res2['thumb'])) {
			echo $res2['thumb'];
		} else {
			$Image = new Image();
			$Image->outStringResized($res2['content'], $res2['type'], 80, 80);
		}
		die;

	}
	elseif (!empty($_GET['tfile'])) {
		$config     = Zend_Registry::get('config');
		$sid        = Zend_Session::getId();
		$upload_dir = $config->temp . '/' . $sid;
		$fname      = $upload_dir . "/thumbnail/" . $_GET['tfile'];
		if (!is_file($fname)) {
			throw new Exception(404);
		}
    	if (phpversion('tidy') < 5.3) {
    		$temp = explode('.', $_GET['tfile']);
    		if (empty($temp[1]) || $temp[1] == 'jpg') {
    			$temp[1] = 'jpeg';
    		}
    		$mime = 'image/' . $temp[1];
    	} else {
	    	$finfo = finfo_open(FILEINFO_MIME_TYPE); 
	    	$mime = finfo_file($finfo, $fname);
    	} 
    	header("Content-Type: $mime");
    	header('Content-Length: ' . filesize($fname));
    	ob_clean();
	    flush();
	    readfile($fname);
    	die;
	}
	