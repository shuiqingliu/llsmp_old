<?php
require_once('DTblDef.php');
require_once('DPageDef.php');
require_once('PathTool.php');
require_once('ConfData.php');

class ConfValidation
{
	var $_info;

	function &singleton() {
		static $instance;

		if (!isset($instance)) {
			$c = __CLASS__;
			$instance = new $c();
			$instance->init(); //there is php4 bug where constructor is not called..so workaround..call init()
		}
		return $instance;
	}

	function init()
	{
		$this->_info = NULL;
	}

	function validateConf(&$confData, $info)
	{
		$this->_info = $info;
		$type = $confData->_type;

		$pageDef = &DPageDef::getInstance();
		$pages = &$pageDef->getFileDef($type);

		for ( $c = 0 ; $c < count($pages) ; ++$c ) {
			$page = &$pages[$c];
			if ( $page->_dataLoc == NULL ) {
				$this->validateElement($page->_tids, $confData->_data);
			} else {
				$data = &DUtil::locateData( $confData->_data, $page->_dataLoc );
				if ( $data == NULL || count($data) == 0 )
				continue;
				$keys = array_keys($data);
				foreach( $keys as $key ) {
					$this->validateElement($page->_tids, $data[$key] );
				}
			}
		}

		if ( $type == 'serv' || $type == 'admin' ) {
			$this->checkListeners($confData);
		}


		$this->_info = NULL;
	}

	function extractPost(&$tbl, &$d, $disp)
	{
		$this->_info = $disp->_info;
		$goFlag = 1 ;
		$index = array_keys($tbl->_dattrs);
		foreach ( $index as $i ) {
			$attr = &$tbl->_dattrs[$i];

			if ( $attr == NULL ) continue;

			if ( $attr->_FDE[2] == 'N' || $attr->blockedVersion())	continue;

			$d[$attr->_key] = $attr->extractPost();

			$needCheck = true;
			if ( $attr->_type == 'sel1' || $attr->_type == 'sel2' )	{
				if ( $disp->_act == 'c' ) {
					$needCheck = false;
				}
				else {
					$tbl->get_sel1_options($this->_info, $d, $attr);
				}
			}

			if ( $needCheck ) {
				$res = $this->validateAttr($attr, $d[$attr->_key]);
				$this->setValid($goFlag, $res);
			}
		}

		$res = $this->validatePostTbl($tbl, $d);

		$this->setValid($goFlag, $res);

		$this->_info = NULL;

		// if 0 , make it always point to curr page
		return $goFlag;
	}

	//private:
	function checkListeners(&$confData)
	{
		if ( isset($confData->_data['listeners']) )	{
			$keys = array_keys($confData->_data['listeners']);
			foreach ( $keys as $key ) {
				$this->checkListener( $confData->_data['listeners'][$key] );
			}
		}
	}

	function checkListener(&$listener)
	{
		if ( $listener['secure']->_v == '0' ) {
			if ( !isset($listener['certFile']->_v) || $listener['certFile']->_v == NULL ) {
				$listener['certFile']->_e = NULL;
			}
			if ( !isset($listener['keyFile']->_v) || $listener['keyFile']->_v == NULL ) {
				$listener['keyFile']->_e = NULL;
			}
		} else {
			$tids = array('L_SSL_CERT');
			$this->validateElement($tids, $listener);
		}
	}

	function validateElement($tids, &$data)
	{
		$tblDef = &DTblDef::getInstance();
		$valid = 1;
		foreach ( $tids as $tid ) {
			$tbl = &$tblDef->getTblDef($tid);
			$d = &DUtil::locateData( $data, $tbl->_dataLoc );

			if ( $d == NULL ) continue;

			if ( $tbl->_holderIndex != NULL ) {
				$keys = array_keys( $d );
				foreach( $keys as $key ) {
					$res = $this->validateTblAttr($tblDef, $tbl, $d[$key]);
					$this->setValid($valid, $res);
				}
			} else {
				$res = $this->validateTblAttr($tblDef, $tbl, $d);
				$this->setValid($valid, $res);
			}
		}
		return $valid;
	}

	function setValid(&$valid, $res)
	{
		if ( $valid != -1 )	{
			if ( $res == -1 ) {
				$valid = -1;
			} elseif ( $res == 0 && $valid == 1 ) {
				$valid = 0;
			}
		}
		if ( $res == 2 ) {
			$valid = 2;
		}
	}

	function validatePostTbl($tbl, &$data)
	{
		$isValid = 1;
		if ( $tbl->_holderIndex != NULL && isset($data[$tbl->_holderIndex])) {
			$newref = $data[$tbl->_holderIndex]->_v;
			$oldref = NULL;

			if(isset($this->_info['holderIndex_cur'])) {
				$oldref = $this->_info['holderIndex_cur'];
			}
			//echo "oldref = $oldref newref = $newref \n";
			if ( $oldref == NULL || $newref != $oldref ) {
				if (isset($this->_info['holderIndex']) && $this->_info['holderIndex'] != NULL
				&& in_array($newref, $this->_info['holderIndex']) ) {
					$data[$tbl->_holderIndex]->_e = 'This value has been used! Please choose a unique one.';
					$isValid = -1;
				}
			}
		}

		$checkedTids = array('VH_TOP_D','VH_BASE','VH_UDB', 'VH_SECHL',
		'ADMIN_USR', 'ADMIN_USR_NEW',
		'L_GENERAL', 'L_GENERAL1', 'ADMIN_L_GENERAL', 'ADMIN_L_GENERAL1',
		'TP', 'TP1');

		if ( in_array($tbl->_id, $checkedTids) ) {
			$funcname = 'chkPostTbl_' . $tbl->_id;
			$res = $this->$funcname($data);
			$this->setValid($isValid, $res);
		}
		return $isValid;
	}


	function chkPostTbl_TP(&$d)
	{
		$isValid = 1;

		$confCenter = &ConfCenter::singleton();

		$oldName = trim($confCenter->_disp->_name);
		$newName = trim($d['name']->_v);

		if($oldName != $newName && array_key_exists($newName, $confCenter->_serv->_data['tpTop'])) {
			$d['name']->_e = "Template: \"{$newName}\" already exists. Please use a different name.";
			$isValid = -1;

		}

		return $isValid;
	}

	function chkPostTbl_TP1(&$d)
	{
		return $this->chkPostTbl_TP($d);
	}


	function chkPostTbl_VH_TOP_D(&$d)
	{
		return $this->chkPostTbl_VH_BASE($d);
	}

	function chkPostTbl_VH_BASE(&$d)
	{
		$isValid = 1;

		$confCenter = &ConfCenter::singleton();

		$oldName = trim($confCenter->_disp->_name);
		$newName = trim($d['name']->_v);

		if($oldName != $newName && array_key_exists($newName, $confCenter->_serv->_data['vhTop'])) {
			$d['name']->_e = "Virtual Hostname: \"{$newName}\" already exists. Please use a different name.";
			$isValid = -1;

		}

		return $isValid;
	}

	function chkPostTbl_VH_UDB(&$d)
	{
		$isValid = 1;
		if ( $d['pass']->_v != $d['pass1']->_v ) {
			$d['pass']->_e = 'Passwords do not match!';
			$isValid = -1;
		}

		if ( !isset($d['passwd']) && ($d['pass']->_v == '') ) { //new user
			$d['pass']->_e = 'Missing password!';
			$isValid = -1;
		}

		if ( $isValid == -1 ) {
			return -1;
		}

		if ( strlen($d['pass']->_v) > 0 ) {
			$newpass = $this->encryptPass($d['pass']->_v);
			$d['passwd'] = new CVal($newpass);
		}
		return 1;
	}

	function chkPostTbl_VH_SECHL(&$d)
	{
		$isValid = 1;
		if ( $d['enableHotlinkCtrl']->_v == '0' ) {
			if ( $d['suffixes']->_v == NULL ) {
				$d['suffixes']->_e = NULL;
			}
			if ( $d['allowDirectAccess']->_v == NULL ) {
				$d['allowDirectAccess']->_e = NULL;
			}
			if ( $d['onlySelf']->_v == NULL ) {
				$d['onlySelf']->_e = NULL;
			}
			$isValid = 2;
		} else {
			if ( $d['onlySelf']->_v == '0'
			&& $d['allowedHosts']->_v == NULL ) {
				$d['allowedHosts']->_e = 'Must be specified if "Only Self Reference" is set to No';
				$isValid = -1;
			}
		}
		return $isValid;
	}

	function encryptPass($val)
	{
		$valid_chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789/.";
		if (CRYPT_MD5 == 1)
		{
		    $salt = '$1$';
		    for($i = 0; $i < 8; $i++)
		    {
			$salt .= $valid_chars[rand(0,strlen($valid_chars)-1)];
		    }
		    $salt .= '$';
		}
		else
		{
		    $salt = $valid_chars[rand(0,strlen($valid_chars)-1)];
		    $salt .= $valid_chars[rand(0,strlen($valid_chars)-1)];
		}
		$pass = crypt($val, $salt);
		return $pass;
	}

	function chkPostTbl_ADMIN_USR(&$d)
	{
		$isValid = 1;
		if ( $d['oldpass']->_v == '' ) {
			$d['oldpass']->_e = 'Missing Old password!';
			$isValid = -1;
		} else {
			$file = $_SERVER['LS_SERVER_ROOT'] . 'admin/conf/htpasswd';
			$udb = ConfigFileEx::loadUserDB($file);
			$olduser = $this->_info['holderIndex_cur'];
			$passwd = $udb[$olduser]['passwd']->_v;

			$oldpass = $d['oldpass']->_v;
			$encypt = crypt($oldpass, $passwd);

			if ( $encypt != $passwd ) {
				$d['oldpass']->_e = 'Invalid old password! ';
				$isValid = -1;
			}
		}

		if ( $d['pass']->_v == '' )	{
			$d['pass']->_e = 'Missing new password!';
			$isValid = -1;
		} elseif ( $d['pass']->_v != $d['pass1']->_v ) {
			$d['pass']->_e = 'New passwords do not match!';
			$isValid = -1;
		}

		if ( $isValid == -1 ) {
			return -1;
		}

		$newpass = $this->encryptPass($d['pass']->_v);
		$d['passwd'] = new CVal($newpass);

		return 1;
	}


	function chkPostTbl_ADMIN_USR_NEW(&$d)
	{
		$isValid = 1;
		if ( $d['pass']->_v == '' )	{
			$d['pass']->_e = 'Missing new password!';
			$isValid = -1;
		} elseif ( $d['pass']->_v != $d['pass1']->_v ) {
			$d['pass']->_e = 'New passwords do not match!';
			$isValid = -1;
		}

		if ( $isValid == -1 ) {
			return -1;
		}

		$newpass = $this->encryptPass($d['pass']->_v);
		$d['passwd'] = new CVal($newpass);

		return 1;
	}


	function chkPostTbl_L_GENERAL(&$d)
	{
		$isValid = 1;

		$ip = $d['ip']->_v;
		if ( $ip == 'ANY' ) {
			$ip = '*';
		}
		$port = $d['port']->_v;
		$d['address'] = new CVal("$ip:$port");

		$confCenter = &ConfCenter::singleton();

		$oldName = trim($confCenter->_disp->_name);
		$newName = trim($d['name']->_v);

		if($oldName != $newName && array_key_exists($newName, $confCenter->_serv->_data['listeners'])) {
			$d['name']->_e = "Listener \"{$newName}\" already exists. Please use a different name.";
			$isValid = -1;

		}

		return $isValid;
	}

	function chkPostTbl_L_GENERAL1(&$d)
	{
		return $this->chkPostTbl_L_GENERAL($d);
	}

	function chkPostTbl_ADMIN_L_GENERAL(&$d)
	{
		return $this->chkPostTbl_L_GENERAL($d);
	}

	function chkPostTbl_ADMIN_L_GENERAL1(&$d)
	{
		return $this->chkPostTbl_L_GENERAL($d);
	}

	function validateTblAttr(&$tblDef, $tbl, &$data)
	{
		$valid = 1;
		if ( $tbl->_subTbls != NULL ) {
			$tid = DUtil::getSubTid($tbl->_subTbls, $data);
			if ( $tid == NULL ) {
				return;
			}
			$tbl1 = &$tblDef->getTblDef($tid);
		} else {
			$tbl1 = &$tbl;
		}

		$index = array_keys($tbl1->_dattrs);
		foreach ( $index as $i ) {
			$attr = $tbl1->_dattrs[$i];

			if ( $attr->_type == 'sel1' || $attr->_type == 'sel2' ) {
				$tbl->get_sel1_options($this->_info, $data, $attr);
			}

			$res = $this->validateAttr($attr, $data[$attr->_key]);
			$this->setValid($valid, $res);
		}
		return $valid;
	}

	function validateAttr($attr, &$cvals)
	{
		if ( $attr->_type == 'cust' ) {
			return 1;
		}

		$valid = 1;
		if ( is_array($cvals) )	{
			for ( $i = 0 ; $i < count($cvals) ; ++$i ) {
				$res = $this->isValidAttr($attr, $cvals[$i]);
				$this->setValid($valid, $res);
			}
		} else {
			$valid = $this->isValidAttr($attr, $cvals);
		}
		return $valid;
	}

	function isValidAttr(&$attr, &$cval)
	{
		$cval->_e = NULL;

		if ( !isset($cval->_v) || $cval->_v === NULL || $cval->_v === '' ) {
			if ( $attr->_allowNull ) {
				return 1;
			}
			$cval->_e = 'value must be set';
			return -1;
		}

		$chktype = array('uint', 'name', 'vhname', 'sel','sel1','sel2',
		'bool','file','filep','file0','file1', 'filetp', 'path',
		'uri','expuri','url', 'email', 'dir', 'addr', 'wsaddr', 'parse');
		// not checked type ('domain', 'subnet'
		if ( in_array($attr->_type, $chktype) )	{
			$type3 = substr($attr->_type, 0, 3);
			if ( $type3 == 'sel' ) {
				$funcname = 'chkAttr_sel';
			}
			elseif ( $type3 == 'fil' || $type3 == 'pat' ) {
				$funcname = 'chkAttr_file';
			}
			else {
				$funcname = 'chkAttr_' . $attr->_type;
			}

			if ( $attr->_multiInd == 1 ) {
				$valid = 1;
				$vals = DUtil::splitMultiple($cval->_v);
				$err = array();
				foreach( $vals as $i=>$v ) {
					$res = $this->$funcname($attr, $v, $err[$i]);
					$this->setValid($valid, $res);
				}
				$cval->_e = trim(implode(' ', $err));
				return $valid;
			}else {
				return $this->$funcname($attr, $cval->_v, $cval->_e);
			}
		} else {
			return 1;
		}
	}

	function chkAttr_sel($attr, $val, &$err)
	{
		if ( isset( $attr->_maxVal ) ) {
			if ( !array_key_exists($val, $attr->_maxVal) ) {
				$err = 'invalid value: ' . $val;
				return -1;
			}
		}
		return 1;
	}

	function chkAttr_name($attr, &$val, &$err)
	{
		$val = preg_replace("/\s+/", ' ', $val);
		if ( preg_match( "/[<>&%]/", $val ) ) {
			$err = 'invalid characters in name';
			return -1;
		}
		if ( strlen($val) > 100 ) {
			$err = 'name can not be longer than 100 characters';
			return -1;
		}
		return 1;
	}

	function chkAttr_vhname($attr, &$val, &$err)
	{
		$val = preg_replace("/\s+/", ' ', $val);
		if ( preg_match( "/[,;<>&%]/", $val ) ) {
			$err = 'Invalid characters found in name';
			return -1;
		}
		if ( strpos($val, ' ') !== FALSE ) {
			$err = 'No space allowed in the name';
			return -1;
		}
		if ( strlen($val) > 100 ) {
			$err = 'name can not be longer than 100 characters';
			return -1;
		}
		$this->_info['VH_NAME'] = $val;
		return 1;
	}

	function allow_create($attr, $absname)
	{
		if ( strpos($attr->_maxVal, 'c') === false ) {
			return false;
		}
		if ( $attr->_minVal >= 2
		&& ( strpos($absname, $_SERVER['LS_SERVER_ROOT'])  === 0 )) {
			return true;
		}

		if (isset($this->_info['VH_ROOT'])) {
			$VH_ROOT = $this->_info['VH_ROOT'];
		} else {
			$VH_ROOT = NULL;
		}

		if (isset($this->_info['DOC_ROOT'])) {
			$DOC_ROOT = $this->_info['DOC_ROOT'];
		}

		if ( $attr->_minVal >= 3 && ( strpos($absname, $VH_ROOT) === 0 ) ) {
			return true;
		}

		if ( $attr->_minVal == 4 && ( strpos($absname, $DOC_ROOT) === 0 ) ) {
			return true;
		}

		return false;
	}

	function test_file(&$absname, &$err, $attr)
	{
		$absname = PathTool::clean($absname);
		if ( isset( $_SERVER['LS_CHROOT'] ) )	{
			$root = $_SERVER['LS_CHROOT'];
			$len = strlen($root);
			if ( strncmp( $absname, $root, $len ) == 0 ) {
				$absname = substr($absname, $len);
			}
		}

		if ( $attr->_type == 'file0' ) {
			if ( !file_exists($absname) ) {
				return 1; //allow non-exist
			}
		}
		if ( $attr->_type == 'path' || $attr->_type == 'filep' || $attr->_type == 'dir' ) {
			$type = 'path';
		} else {
			$type = 'file';
		}

		if ( ($type == 'path' && !is_dir($absname))
		|| ($type == 'file' && !is_file($absname)) ) {
			$err = $type .' '. htmlspecialchars($absname) . ' does not exist.';
			if ( $this->allow_create($attr, $absname) ) {
				$err .= ' <a href="javascript:createFile(\''. $attr->_htmlName . '\')">CLICK TO CREATE</a>';
			} else {
				$err .= ' Please create manually.';
			}

			return -1;
		}
		if ( (strpos($attr->_maxVal, 'r') !== false) && !is_readable($absname) ) {
			$err = $type . ' '. htmlspecialchars($absname) . ' is not readable';
			return -1;
		}
		if ( (strpos($attr->_maxVal, 'w') !== false) && !is_writable($absname) ) {
			$err = $type . ' '. htmlspecialchars($absname) . ' is not writable';
			return -1;
		}
		if ( (strpos($attr->_maxVal, 'x') !== false) && !is_executable($absname) ) {
			$err = $type . ' '. htmlspecialchars($absname) . ' is not executable';
			return -1;
		}
		return 1;
	}

	function chkAttr_file($attr, &$val, &$err)
	{
		clearstatcache();
		$err = NULL;

		if ( $attr->_type == 'filep' ) {
			$path = substr($val, 0, strrpos($val,'/'));
		} else {
			$path = $val;
			if ( $attr->_type == 'file1' ) {
				$pos = strpos($val, ' ');
				if ( $pos > 0 ) {
					$path = substr($val, 0, $pos);
				}
			}
		}

		$res = $this->chk_file1($attr, $path, $err);
		if ($attr->_type == 'filetp') {
			$pathtp = $_SERVER['LS_SERVER_ROOT'] . 'conf/templates/';
			if (strstr($path, $pathtp) === FALSE) {
				$err = ' Template file must locate within $SERVER_ROOT/conf/templates/';
				$res = -1;
			}
			else if (substr($path, -4) != '.xml') {
				$err = ' Template file name needs to be ".xml"';
				$res = -1;
			}
		}
		if ( $res == -1
		&& $_POST['file_create'] == $attr->_htmlName
		&& $this->allow_create($attr, $path) )	{
			if ( PathTool::createFile($path, $err, $attr->_htmlName) ) {
				$err = "$path has been created successfully.";
			}
			$res = 0; // make it always point to curr page
		}
		if ( $attr->_key == 'vhRoot' )	{
			if ( substr($path,-1) != '/' ) {
				$path .= '/';
			}
			if ($res == -1) {
				// do not check path for vhroot, it may be different owner
				$err = '';
				$res = 1;
			}
			$this->_info['VH_ROOT'] = $path;
		}
		elseif ($attr->_key == 'docRoot') {
			if ($res == -1) {
				// do not check path for vhroot, it may be different owner
				$err = '';
				$res = 1;
			}
		}
		return $res;
	}

	function chk_file1($attr, &$path, &$err)
	{
		if(!strlen($path)) {
			$err = "Invalid Path.";
			return -1;
		}

		$s = $path{0};

		if ( strpos($path, '$VH_NAME') !== false )	{
			$path = str_replace('$VH_NAME', $this->_info['VH_NAME'], $path);
		}

		if ( $s == '/' ) {
			return $this->test_file($path, $err, $attr);
		}

		if ( $attr->_minVal == 1 ) {
			$err = 'only accept absolute path';
			return -1;
		}
		elseif ( $attr->_minVal == 2 ) {
			if ( strncasecmp('$SERVER_ROOT', $path, 12) != 0 )	{
				$err = 'only accept absolute path or path relative to $SERVER_ROOT' . $path;
				return -1;
			} else {
				$path = $_SERVER['LS_SERVER_ROOT'] . substr($path, 13);
			}
		}
		elseif ( $attr->_minVal == 3 ) {
			if ( strncasecmp('$SERVER_ROOT', $path, 12) == 0 ) {
				$path = $_SERVER['LS_SERVER_ROOT'] . substr($path, 13);
			} elseif ( strncasecmp('$VH_ROOT', $path, 8) == 0 )	{
				if (isset($this->_info['VH_ROOT'])) {
					$path = $this->_info['VH_ROOT'] . substr($path, 9);
				}
			} else {
				$err = 'only accept absolute path or path relative to $SERVER_ROOT or $VH_ROOT';
				return -1;
			}
		}
		elseif ( $attr->_minVal == 4 ) {
			if ( strncasecmp('$SERVER_ROOT', $path, 12) == 0 ) {
				$path = $_SERVER['LS_SERVER_ROOT'] . substr($path, 13);
			} elseif ( strncasecmp('$VH_ROOT', $path, 8) == 0 )	{
				$path = $this->_info['VH_ROOT'] . substr($path, 9);
			} elseif ( strncasecmp('$DOC_ROOT', $path, 9) == 0 ) {
				$path = $this->_info['DOC_ROOT'] . substr($path, 10);
			} else {
				$path = $this->_info['DOC_ROOT'] . $path;
			}
		}

		return $this->test_file($path, $err, $attr);
	}

	function chkAttr_uri($attr, $val, &$err)
	{
		if ( $val{0} != '/' ) {
			$err = 'URI must start with "/"';
			return -1;
		}
		return 1;
	}

	function chkAttr_expuri($attr, $val, &$err)
	{
		if ( $val{0} == '/' || strncmp( $val, 'exp:', 4 ) == 0 ) {
			return 1;
		} else {
			$err = 'URI must start with "/" or "exp:"';
			return -1;
		}
	}

	function chkAttr_url($attr, $val, &$err)
	{
		if (( $val{0} != '/' )
		&&( strncmp( $val, 'http://', 7 ) != 0 )
		&&( strncmp( $val, 'https://', 8 ) != 0 )) {
			$err = 'URL must start with "/" or "http(s)://"';
			return -1;
		}
		return 1;
	}

	function chkAttr_email($attr, $val, &$err)
	{
		if ( preg_match("/^[[:alnum:]._-]+@.+/", $val ) ) {
			return 1;
		} else {
			$err = 'invalid email format: '.$val;
			return -1;
		}
	}

	function chkAttr_dir($attr, &$val, &$err)
	{
		if ( substr($val,-1) == '*' ) {
			return $this->chkAttr_file($attr, substr($val,0,-1), $err);
		} else {
			return $this->chkAttr_file($attr, $val, $err);
		}
	}

	function chkAttr_addr($attr, $val, &$err)
	{
		if ( preg_match("/^[[:alnum:]._-]+:(\d)+$/", $val) ) {
			return 1;
		} elseif ( preg_match("/^UDS:\/\/.+/i", $val) ) {
			return 1;
		} else {
			$err = 'invalid address: correct syntax is "IPV4|IPV6_address:port" or UDS://path';
			return -1;
		}
	}

	function chkAttr_wsaddr($attr, $val, &$err)
	{
		if ( preg_match("/^((http|https):\/\/)?[[:alnum:]._-]+(:\d+)?$/", $val) ) {
			return 1;
		} else {
			$err = 'invalid address: correct syntax is "[http|https://]IPV4|IPV6_address[:port]". ';
			return -1;
		}
	}

	function chkAttr_bool($attr, $val, &$err)
	{
		if ( $val === '1' || $val === '0' ) {
			return 1;
		}
		$err = 'invalid value';
		return -1;
	}

	function chkAttr_parse($attr, $val, &$err)
	{
		if ( preg_match($attr->_minVal, $val) ) {
			return 1;
		} else {
			$err = 'invalid format - ' . $val . ', syntax is '.$attr->_maxVal;
			return -1;
		}
	}

	function getKNum($strNum)
	{
		$tag = substr($strNum, -1);
		switch( $tag ) {
			case 'K':
			case 'k': $multi = 1024; break;
			case 'M':
			case 'm': $multi = 1048576; break;
			case 'G':
			case 'g': $multi = 1073741824; break;
			default: return intval($strNum);
		}

		return (intval(substr($strNum, 0, -1)) * $multi);
	}

	function chkAttr_uint($attr, $val, &$err)
	{
		if ( preg_match("/^(-?\d+)([KkMmGg]?)$/", $val, $m) ) {
			$val1 = $this->getKNum($val);
			if (isset($attr->_minVal)) {
				$min = $this->getKNum($attr->_minVal);
				if ($val1 < $min) {
					$err = 'number is less than the minumum required';
					return -1;
				}

			}
			if (isset($attr->_maxVal)) {
				$max = $this->getKNum($attr->_maxVal);
				if ( $val1 > $max )	{
					$err = 'number exceeds maximum allowed';
					return -1;
				}
			}
			return 1;
		} else {
			$err = 'invalid number format';
			return -1;
		}
	}

	//	function validateIntegrity($tids, &$data)
	//	{
	//	}

	function validate($tids, &$data, &$attr)
	{
		if ( is_array( $tids ) ) {
			$isValid = true;
			foreach ( $tids as $tid ) {
				$isValid1 = $this->check($tid, $data, $attr);
				$isValid = $isValid && $isValid1;
			}
		} else {
			$isValid = $this->check($tids, $data, $attr);
		}
		return $isValid;
	}

	function check($tid, &$data, &$attr)
	{
		switch( $tid ) {
			case 'A_EXT': return $this->chkExtApp($data, $attr);
			case 'A_SCRIPT': return $this->chkScriptHandler($data, $attr);

			case 'L_GENERAL':
			case 'L_GENERAL1':
			case 'ADMIN_L_GENERAL':
			case 'ADMIN_L_GENERAL1':
			case 'VH_TOP':
			case 'VH_REALM': return $this->chkDups( $data, $attr['names'], 'name');
			case 'VH_ERRPG': return $this->chkDups( $data, $attr['names'], 'errCode');
			case 'VH_CTXG':
			case 'VH_CTXJ':
			case 'VH_CTXS':
			case 'VH_CTXF':
			case 'VH_CTXC':
			case 'VH_CTXR':
			case 'VH_CTXRL': return $this->chkDups( $data, $attr['names'], 'uri');
		}
		return true;
	}

	function chkDups(&$data, &$checkList, $key)
	{
		if ( in_array( $data[$key]->_v, $checkList ) ) {
			$data[$key]->_e = 'This ' . $key . ' "' . $data[$key]->_v . '" already exists. Please enter a different one.';
			return false;
		}
		return true;
	}

	function chkExtApp(&$data, &$attr)
	{
		$isValid = true;
		if ( $data['autoStart']->_v ) {
			if ( $data['path']->_v == NULL ) {
				$data['path']->_e = 'must provide path when autoStart is enabled';
				$isValid = false;
			}
			if ( $data['backlog']->_v == NULL )	{
				$data['backlog']->_e = 'must enter backlog value when autoStart is enabled';
				$isValid = false;
			}
			if ( $data['instances']->_v == NULL ) {
				$data['instances']->_e = 'must give number of instances when autoStart is enabled';
				$isValid = false;
			}
		}

		if ( isset($attr['names']) && (!$this->chkDups($to, $attr['names'], 'name')) ) {
			$isValid = false;
		}
		return $isValid;
	}

	function chkScriptHandler(&$to, &$attr)
	{
		$vals = DUtil::splitMultiple( $to['suffix']->_v );
		$isValid = true;
		foreach( $vals as $suffix )	{
			if ( in_array( $suffix, $attr['names'] ) ) {
				$t[] = $suffix;
				$isValid = false;
			}
		}
		if ( !$isValid ) {
			$to['suffix']->_e .= ' Suffix ' . implode(', ', $t) . ' already exists. Please use a different suffix.';
		}
		return $isValid;
	}

}

?>
