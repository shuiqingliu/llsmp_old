<?

require_once('DUtil.php');
require_once('DTblDef.php');
require_once('ConfigFile.php');
require_once('ConfigFileEx.php');
require_once('PathTool.php');
require_once('ConfData.php');
require_once('ConfValidation.php');
require_once('DispInfo.php');

class ConfCenter
{
	var $_confType; //'serv','admin'
	var $_serv;
	var $_curOne; //vh or tp
	
	var $_info;
	var $_disp;

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
		$mid = DUtil::grab_input("request",'m');
		$this->_confType = ( $mid != NULL && $mid[0] == 'a' ) ? 'admin' : 'serv';

		$this->_disp = new DispInfo('confMgr.php?');

		$this->loadConfig();

		$client = CLIENT::singleton();

		if ($client->timeout == 0) {
			$this->setSessionTimeout();
		}
	}

	function &getDispInfo()
	{
		return $this->_disp;
	}

	function &exportConf()
	{
		$data = array();
		$listeners = array();
		if (isset($this->_serv->_data['listeners']))
		{
			$lnames = array_keys($this->_serv->_data['listeners']);
			foreach( $lnames as $ln )
			{
				$l = &$this->_serv->_data['listeners'][$ln];
				$listeners[$ln] = $l['address']->_v;
			}
		}

		$data['listener'] = $listeners;
		$vhnames = array();
		if (isset($this->_serv->_data['vhTop']))
			$vhnames = array_keys($this->_serv->_data['vhTop']);
		if (isset($this->_serv->_data['tpTop']))
		{
			foreach( $this->_serv->_data['tpTop'] as $tp)
			{
				if (isset($tp['member'])) {
					$vhnames = array_merge($vhnames, array_keys($tp['member']));
				}
				
			}
		}
		
		$data['vhost'] = $vhnames;
		$data['serv'] = $this->_serv->_id;
		$data['servLog'] = $this->getAbsFile($this->_serv->_data['general']['log']['fileName']->_v, 'SR');

		return $data;
	}

	function getServerLogFileName()
	{
		return $this->getAbsFile($this->_serv->_data['general']['log']['fileName']->_v, 'SR');
	}

	function getAdminEmails()
	{
		return $this->_serv->_data['general']['adminEmails']->_v;
	}
	
	function setSessionTimeout()
	{
		$timeout = 600;
		if ( $this->_confType == 'admin' )
		{
			$t = $this->_serv->_data['general']['sessionTimeout']->_v;
		}
		else
		{
			$adminFile = $this->getConfFilePath('admin');
			$t = ConfigFileEx::grepTagValue($adminFile, 'sessionTimeout');
		}
		if ( ($t != null) && is_numeric($t) && ($t >= 60))
		{
			$timeout = $t;
		}

		$client = CLIENT::singleton();

		$client->setTimeout($timeout);

	}

	function getConfFilePath($type, $name='')
	{
		$path = NULL;
		if ( $type == 'serv') {
			$path = $_SERVER['LS_SERVER_ROOT'] . "conf/httpd_config.xml" ; //fixed location
		} 
		elseif ( $type == 'admin') {
			$servconf = $this->getConfFilePath('serv');
			$adminRoot = ConfigFileEx::grepTagValue($servconf, 'adminRoot');
			if ( $adminRoot != null ) {
				$adminRoot = $this->getAbsFile($adminRoot,'SR');
			} else {
				$adminRoot = $this->getAbsFile('$SERVER_ROOT/admin/','SR'); //default loc
			}

			if ($name == '') {
				$path = $adminRoot . 'conf/admin_config.xml' ;
			} elseif ($name == 'key') {
				$path = $adminRoot . 'conf/jcryption_keypair' ;
			}
		}
		elseif ( $type == 'vh' ) {
			$path = $this->getAbsFile($this->_serv->_data['vhTop'][$name]['configFile']->_v, 'VR', $name);
		} 
		else {
			$path = $this->getAbsFile($this->_serv->_data['tpTop'][$name]['templateFile']->_v, 'SR', $name);
		}
		return $path;
	}

	function loadConfig()
	{
		$confpath = $this->getConfFilePath($this->_confType);
		$this->_serv = new ConfData($this->_confType, $confpath);

		$config = new ConfigFile();

		$err = '';
		$res = $config->load( $this->_serv, $err );
		if ( !$res )
		{
			$this->_info['CONF_FILE_ERR'] = $err;
			return false;
		}

		if ( $this->_confType == 'serv' )
		{
			$serverName = $this->_serv->_data['general']['serverName']->_v;
			if ($serverName == '$HOSTNAME' || $serverName == '') {
				$serverName = php_uname('n');
			}
			$this->_serv->setId($serverName);
			$this->_disp->init($this->_serv->_id);
		}
		$this->genMenuInfo();
		
		if ( $this->_disp->_type == 'vh' || $this->_disp->_type == 'tp')
		{
			$vhpath = $this->getConfFilePath($this->_disp->_type, $this->_disp->_name);
			$this->_curOne = new ConfData($this->_disp->_type, $vhpath, $this->_disp->_name);
			$res = $config->load($this->_curOne, $err);
	
			if ( !$res )
			{
				$this->_info['CONF_FILE_ERR'] = $err;
				return false;
			}
	
			if ( $this->_disp->_type == 'vh' )
			{
				$this->_info['VH_NAME'] = $this->_disp->_name;
				$this->getAbsFile('$VH_ROOT/', 'VR', $this->_disp->_name); //set $this->_info['VH_ROOT'] 
				$this->getAbsFile('$DOC_ROOT/', 'DR', $this->_disp->_name); // set $this->_info['DOC_ROOT']
			}
				
		}
		
		$this->loadSpecial();
		$viewActions = array('C');
		if ( !in_array($this->_disp->_act,  $viewActions)) {
			$this->refreshInfo();
		}

		return true;
	}

	function genMenuInfo()
	{
		if ( $this->_confType == 'serv' )
		{
			$this->_info['LNS'] = array();
			if (isset($this->_serv->_data['listeners']))
			{
				$keys = DUtil::array_string_keys($this->_serv->_data['listeners']);
				sort($keys);
				foreach( $keys as $name )
				{
					$this->_info['LNS'][$name] = $name;
				}
			}

			$this->_info['VHS'] = array();

			if ( isset($this->_serv->_data['vhTop']) )
			{
				$keys = DUtil::array_string_keys($this->_serv->_data['vhTop']);
				sort($keys);
				foreach( $keys as $name)
				{
					$this->_info['VHS'][$name] = $name;
				}
			}
		}

	}

	function getAbsFile($filename, $type, $vn=NULL)
	{
		// type = 'SR', 'VR', 'DR'
		if ( $vn != NULL && strpos($filename, '$VH_NAME')!== false )
		{
			$filename = str_replace('$VH_NAME', $vn, $filename);
		}
		$s = $filename{0};
		if ( $s == '$' )
		{
			if ( strncasecmp('$SERVER_ROOT', $filename, 12) == 0 )
			{
				$filename = $_SERVER['LS_SERVER_ROOT'] . substr($filename, 13);
			}
			elseif ( ($type == 'VR' || $type == 'DR')
					  && (strncasecmp('$VH_ROOT', $filename, 8) == 0) )
			{
				if ( !isset($this->_info['VH_ROOT']) && $vn)
				{
					$vhroot = $this->getAbsFile($this->_serv->_data['vhTop'][$vn]['vhRoot']->_v,'SR', $vn);
					if ( substr($vhroot, -1, 1) !== '/' )
						$vhroot .= '/';
					$this->_info['VH_ROOT'] = $vhroot;
				}
				else
					$vhroot = $this->_info['VH_ROOT'];

				$filename = $vhroot . substr($filename, 9);
			}
			elseif ( $type == 'DR' 
					 && (strncasecmp('$DOC_ROOT', $filename, 9) == 0) )
			{
				if ( !isset($this->_info['DOC_ROOT']) )
				{
					$docroot = $this->getAbsFile($this->_curOne->_data['general']['docRoot']->_v, 'VR', $vn);
					if ( substr($docroot, -1, 1) !== '/' )
						$docroot .= '/';
					$this->_info['DOC_ROOT'] = $docroot;
				}
				else
					$docroot = $this->_info['DOC_ROOT'];
				$filename = $docroot . substr($filename, 10);
			}

		}
		elseif ( $s == '/' )
		{
			if ( isset( $_SERVER['LS_CHROOT'] ) )
			{
				$root = $_SERVER['LS_CHROOT'];
				$len = strlen($root);
				if ( strncmp( $filename, $root, $len ) == 0 )
					$filename = substr($filename, $len);
			}
		}
		return $filename;
	}

	function savePost( $extractData, &$disp, $tbl, &$cur_ref )
	{
		$oldConf = &$this->getConfData($disp);
		$data = &DUtil::locateData( $oldConf, $tbl->_dataLoc, $disp->_ref);
		$data0 = &$data;

		$oldRef = $cur_ref;
		$newRef = NULL;

		if ( $tbl->_holderIndex != NULL )
		{
			$newRef = $extractData[$tbl->_holderIndex]->_v;
			if ( $cur_ref != NULL && $cur_ref != $newRef && isset($data[$cur_ref]))
			{
				$data[$newRef] = $data[$cur_ref];
				unset($data[$cur_ref]);
			}
			$cur_ref = $newRef;
			$data = &$data[$cur_ref];
		}

		if ( isset($tbl->_defaultExtract) )
		{
			foreach( $tbl->_defaultExtract as $k => $v )
			{
				if ( $v == '$$' )
					$data[$k] = $extractData[$k];
				else
					$data[$k] = new CVal($v);
			}
		}

		$index = array_keys($tbl->_dattrs);
		foreach ( $index as $i )
		{
			$attr = &$tbl->_dattrs[$i];
			if ( !is_a($attr,"DAttr") || $attr->_FDE[2] == 'N' || $attr->blockedVersion())
				continue;
			$data[$attr->_key] = $extractData[$attr->_key];
		}

		if ( strncmp('VH_CTX', $tbl->_id, 6) == 0 && $oldRef == NULL )
		{
			$data['order'] = new CVal(count($data0));
		}
		elseif ( strpos($tbl->_id, 'L_GENERAL') !== FALSE )
		{
			$validator = &ConfValidation::singleton();
			$validator->checkListener($data);
		}
		elseif ( $tbl->_id == 'VH_BASE')
		{
			$oldRef = $disp->_name;
			$newRef = $data['name']->_v;
		}

		$this->checkRefresh($tbl->_id, 's', $oldRef, $newRef, $disp);
	}

	function saveFile($type, $name, $tid=NULL, $ref=NULL)
	{
		$config = new ConfigFile();
		$res = false;
		$restart_required = TRUE;

		if ( $tid == 'SERV_MIME' )
		{
			$res = ConfigFileEx::saveMime($this->_info['MIME_FILE'],
									 $this->_serv->_data['MIME']);
		}
		elseif ( $tid == 'VH_UDB' )
		{
			DUtil::trimLastId($ref);
			$ref = DUtil::getLastId($ref);
			$res = ConfigFileEx::saveUserDB($this->_info['UDB_FILE'],
									   $this->_curOne->_data['UDB'][$ref]);
			$restart_required = FALSE;								
		}
		elseif ( $tid == 'VH_GDB' )
		{
			DUtil::trimLastId($ref);
			$ref = DUtil::getLastId($ref);
			$res = ConfigFileEx::saveGroupDB($this->_info['GDB_FILE'],
									   $this->_curOne->_data['GDB'][$ref]);
			$restart_required = FALSE;
		}
		elseif ( $tid == 'ADMIN_USR' || $tid == 'ADMIN_USR_NEW' )
		{
			$file = $_SERVER['LS_SERVER_ROOT'] . 'admin/conf/htpasswd';
			$res = ConfigFileEx::saveUserDB($file, $this->_serv->_data['ADM']);
			$restart_required = FALSE;								
		}
		elseif ( $type == 'serv' || $type == 'sltop' || $type == 'sl'
				  || $type == 'vhtop' || $type == 'tptop'
				  || ($type == 'tp' && ($tid == 'TP_MEMBER' || $tid == 'TP')) 
				  || ($type == 'vh' && stristr($tid,'VH_BASE')))
		{
			$res = $config->save($this->_serv);
		}
		elseif ( $type == 'admin' || $type == 'altop' || $type == 'al' )
		{
			$res = $config->save($this->_serv);
			$this->setSessionTimeout();
		}
		elseif ( $type == 'vh' || $type == 'tp')
		{
			if ( !isset($this->_curOne) )
			{
				$curpath = $this->getConfFilePath($type, $name);
				$this->_curOne = new ConfData($type, $curpath, $name);
			}
			$res = $config->save($this->_curOne);
		}

		if ( $restart_required && $res ) {
			$client = CLIENT::singleton();
			$client->changed = TRUE;
		}
		return $res;
	}

	function &loadKeys( $list )
	{
		if ( isset($list) && count($list) > 0 ) {
			$temp = array_keys($list);
			return $temp;
		}
		else {
			$temp = NULL;
			return $temp;
		}
	}

	function refreshInfo()
	{
		$handler = array();

		if ( isset($this->_serv->_data['ext']) ) {
			foreach( $this->_serv->_data['ext'] as $name=>$e ) {
				$handler[$e['type']->_v][$name] = '[Server Level]: ' . $name;
			}
		}
		$handler['cgi']['cgi'] = 'CGI Daemon';
		
		if ( isset($this->_curOne->_data['ext'])){
			foreach( $this->_curOne->_data['ext'] as $name=>$e ) {
				$handler[$e['type']->_v][$name] = '[VHost Level]: ' . $name;
			}
		}
		$this->_info['ext'] = $handler;

		if ( isset($this->_curOne) && isset($this->_curOne->_data['sec']['realm']))	{
			$realms = array();
			$rn = $this->loadKeys($this->_curOne->_data['sec']['realm']);
			if ( $rn != NULL ) {
				foreach ( $rn as $name ) {
					$realms[$name] = $name;
				}
			}
			$this->_info['realms'] = $realms;
		}
	}

	function changeName_VH($oldName, $newName)
	{
	
		//$vhconf = $this->getConfFilePath('vh', $newName);
		//if ( !file_exists($vhconf) ) {
			//$this->saveFile('vh', $newName);
		//}
		
		$l = &$this->_serv->_data['listeners'];
		$lnames = array_keys($l);
		foreach ( $lnames as $ln )
		{
			if ( isset($l[$ln]['vhMap'][$oldName]) )
			{
				$map = $l[$ln]['vhMap'][$oldName];
				$map['vhost']->_v = $newName;
				unset($l[$ln]['vhMap'][$oldName]);
				$l[$ln]['vhMap'][$newName] = $map;
			}
		}
		$this->_serv->_data['vhTop'][$newName] = $this->_serv->_data['vhTop'][$oldName];
		$this->_disp->_mid = "vh_$newName";
		$this->_disp->_name = $newName;
		$this->_curOne->_id = $newName;
		$this->_disp->init();
		
	}

	function changeName_L($oldName, $newName)
	{
		if ( $this->_serv->_data['tpTop'] != NULL
			 && count($this->_serv->_data['tpTop']) > 0 )
		{
			$tns = array_keys($this->_serv->_data['tpTop']);
			foreach ( $tns as $tn )
			{
				$tl = &$this->_serv->_data['tpTop'][$tn]['listeners'];
				$vals = DUtil::splitMultiple($tl->_v);
				foreach( $vals as $i=>$v )
				{
					if ( $v == $oldName )
						$vals[$i] = $newName;
				}
				$tl->_v = implode(', ', $vals);
			}

		}
	}

	function changeName_EXT($oldName, $newName)
	{
		if ( isset($this->_curOne) ) {
			$data = &$this->_curOne->_data;
		} else {
			$data = &$this->_serv->_data;
		}

		if ( $data['scriptHandler'] != NULL )
		{
			$keys = array_keys($data['scriptHandler']);
			foreach( $keys as $key )
			{
				$sh = &$data['scriptHandler'][$key];
				if ( $sh['handler']->_v == $oldName )
					$sh['handler']->_v = $newName;
			}
		}

		if ( isset($data['context']) )
		{
			$keys = array_keys($data['context']);
			foreach( $keys as $key )
			{
				$ctx = &$data['context'][$key];
				if ( isset($ctx['authorizer']) && $ctx['authorizer']->_v == $oldName )
					$ctx['authorizer']->_v = $newName;
				if ( isset($ctx['handler']) && $ctx['handler']->_v == $oldName )
					$ctx['handler']->_v = $newName;
			}
		}
	}

	function changeName_REALM($oldName, $newName)
	{
		$data = &$this->_curOne->_data;
		if ( $data['context'] != NULL )
		{
			$keys = array_keys($data['context']);
			foreach( $keys as $key )
			{
				$ctx = &$data['context'][$key];
				if ( isset($ctx['realm']) && $ctx['realm']->_v == $oldName )
					$ctx['realm']->_v = $newName;
			}
		}
	}

	function &getConfData($disp)
	{
		$data = NULL;
		if ( $disp->_type == 'vh' )	{
			if($disp->_pid == 'base') {
				$data = &$this->_serv->_data['vhTop'][$disp->_name];
			}
			
			else {
				$data = &$this->_curOne->_data;
			}
		}
		elseif ( $disp->_type == 'tp' )	{
			if ( $disp->_pid == 'member' ) {
				$data = &$this->_serv->_data['tpTop'][$disp->_name];
			} else {
				$data = &$this->_curOne->_data;
			}
		}
		elseif ( $disp->_type[0] == 'a' ) {
			if ( $disp->_type == 'al' ) {
				$data = &$this->_serv->_data['listeners'][$disp->_name];
			}
			else {
				$data = &$this->_serv->_data;
			}
		}
		else {
			if ( $disp->_type == 'sl' ) {
				$data = &$this->_serv->_data['listeners'][$disp->_name];
			}
			elseif ( $disp->_type == 'tptop' && $disp->_pid == 'tpTop1' ) {
				$data = &$this->_serv->_data['tpTop'][$disp->_ref];
			}
			else {
				$data = &$this->_serv->_data;
			}
		}
		return $data;
	}

	function loadSpecial()
	{
		$tid = DUtil::getLastId($this->_disp->_tid);
		
		if ( $this->_disp->_type == 'serv') {

			if ( $tid == 'SERV_MIME_TOP' ||  $tid == 'SERV_MIME'){
				$file = $this->getAbsFile($this->_serv->_data['general']['mime']->_v, 'SR');
				$this->_serv->_data['MIME'] = &ConfigFileEx::loadMime($file);
				$this->_info['MIME_FILE'] = $file;
			}
		
		}
		elseif ( $this->_disp->_type == 'admin') {

			if ( $this->_disp->_pid == 'security' ){
				$file = $_SERVER['LS_SERVER_ROOT'] . 'admin/conf/htpasswd';
				$this->_serv->_data['ADM'] = ConfigFileEx::loadUserDB($file);
			}

		}
		elseif ($this->_disp->_type == 'vh') {
			
			if ($tid == 'VH_UDB' || $tid == 'VH_GDB') {
				$temp = explode('`',$this->_disp->_ref);
				$ref = $temp[0];
			}
			else {
				$ref = DUtil::getLastId($this->_disp->_ref);
			}

			if ( $tid == 'VH_UDB_TOP' || $tid == 'VH_UDB')
			{
				$loc = $this->_curOne->_data['sec']['realm'][$ref]['userDB:location']->_v;
				$file = $this->getAbsFile($loc, 'VR', $this->_info['VH_NAME']);
				$this->_info['UDB_FILE'] = $file;
				$this->_curOne->_data['UDB'][$ref] = ConfigFileEx::loadUserDB($file);
	
			}
			elseif ( $tid == 'VH_GDB_TOP' || $tid == 'VH_GDB')
			{
				$loc = $this->_curOne->_data['sec']['realm'][$ref]['groupDB:location']->_v;
				$file = $this->getAbsFile($loc, 'VR', $this->_info['VH_NAME']);
				$this->_info['GDB_FILE'] = $file;
				$this->_curOne->_data['GDB'][$ref] = &ConfigFileEx::loadGroupDB($file);
			}
		}
	}

	function checkRefresh($tid, $act, $oldRef, $newRef,&$disp)
	{
		if ( $tid == 'SERV_PROCESS' )
		{
			$serverName = $this->_serv->_data['general']['serverName']->_v;
			if ( $serverName != $this->_serv->_id) {
				$this->_serv->setId($serverName);
				$this->_disp->init($serverName);
			}
			return;
		}

		$menuTids = array('L_GENERAL1', 'L_GENERAL', 'ADMIN_L_GENERAL1', 'ADMIN_L_GENERAL', 'VH_TOP_D', 'VH_BASE', 'TP1');
		$isExt = (strncmp($tid, 'A_EXT', 5) == 0) || (strncmp($tid, 'TP_EXT', 6) == 0);
		$isRealm = (strncmp($tid, 'VH_REALM', 8)==0) || (strncmp($tid, 'TP_REALM', 8)==0);

		if ( in_array($tid, $menuTids) )
		{
			$this->genMenuInfo();
		}
		elseif ( $isExt || $isRealm ) {
			$this->refreshInfo();
		}
		else
			return;

		if ( $act == 's' && $oldRef != NULL && $oldRef != $newRef )
		{
			if ( $tid == 'VH_BASE' )
			{
				$this->changeName_VH($oldRef, $newRef);
			}
			elseif ( $tid == 'L_GENERAL1' || $tid == 'ADMIN_L_GENERAL1' ) {
				$this->changeName_L($oldRef, $newRef);
			}
			elseif ( $isExt ) {
				$this->changeName_EXT($oldRef, $newRef);
			}
			elseif ( $isRealm ) {
				$this->changeName_REALM($oldRef, $newRef);
			}
		}
	}

	function &doAction(&$disp)
	{
		if ($disp->_act != 'v' ) {
		    //if (strpos($_SERVER['HTTP_REFERER'], "{$_SERVER['HTTP_HOST']}/config/confMgr.php") === FALSE) {
		    //	$disp->_err = 'illegal entry!';
		    //	return NULL;
		    //}
			if ($disp->_token != $disp->_tokenInput) {
				$disp->_err = "illegal entry point!"; 
				return NULL;
			}
		}
		$disp->_info = &$this->_info;

		//init here
		$data = &$this->getConfData($disp);

		$tid = DUtil::getLastId($disp->_tid);
		$ref = DUtil::getLastId($disp->_ref);
		$needTrim = false;
		$tblDef = &DTblDef::getInstance();

		if ( $disp->_act == 's' )
		{
			$tbl = &$tblDef->getTblDef($tid);
			$extractedData = NULL;
			if ($tbl->_holderIndex != NULL) 
			{
				$curdata = DUtil::locateData( $data, $tbl->_dataLoc);
				$disp->_info['holderIndex'] = DUtil::array_string_keys($curdata);
				$disp->_info['holderIndex_cur'] = $ref;
			}
			else
				$disp->_info['holderIndex'] = NULL;
			
			$validator = &ConfValidation::singleton();
			$go = $validator->extractPost( $tbl, $extractedData, $disp );
			if ( $go > 0 )
			{
				$this->savePost( $extractedData, $disp, $tbl, $ref);
				$this->saveFile($disp->_type, $disp->_name, $tbl->_id, $disp->_ref);
				$needTrim = true;
			}
			else
			{
				$disp->_act = 'S';
				$disp->_err = 'Input error detected. Please resolve the error(s). ';
				return $extractedData;
			}
		}
		else if ( $disp->_act == 'c' || $disp->_act == 'n')
		{
			$tbl = &$tblDef->getTblDef($tid);
			$extractedData = array();
			$validator = &ConfValidation::singleton();
			$validator->extractPost( $tbl, $extractedData, $disp );
			if ($disp->_act == 'n')
			{
				$tid = DUtil::getSubTid($tbl->_subTbls, $extractedData);
				if ( $tid != NULL )
				{
					DUtil::switchLastId($disp->_tid, $tid);
					$tbl = &$tblDef->getTblDef($tid);
				}
			}
			return $extractedData;
		}
		else if ( $disp->_act == 'D' )
		{
			$tbl = &$tblDef->getTblDef($tid);
			$oldConf = &$this->getConfData($disp);
			$data = &DUtil::locateData( $oldConf, $tbl->_dataLoc, $disp->_ref);
			if (($disp->_tid == 'ADMIN_L_GENERAL1')
			|| ($disp->_pid == 'security' && $disp->_tid == 'ADMIN_USR')) {
				// for admin listener, do not allow to delete all
				// for admin user, do not allow to delete all
				if (count($data) == 1) {
					DUtil::trimLastId($disp->_tid);
					DUtil::trimLastId($disp->_ref);
					return $oldConf;
				}
			}
			unset($data[$ref]);

			if ( strncmp('VH_CTX', $tbl->_id, 6) == 0 )
				$this->resetCtxSeq($data);

			$this->saveFile($disp->_type, $disp->_name, $tid, $disp->_ref);
			$data = &$this->getConfData($disp); //reload everything
			$this->checkRefresh($tid, 'D', $ref, NULL, $disp);
			$needTrim = true;
		}
		else if ( $disp->_act == 'I' )
		{
			if ( $this->instantiateTempl($disp) )
			{
				$tbl = &$tblDef->getTblDef($tid);
				$oldConf = &$this->getConfData($disp);
				$data = &DUtil::locateData( $oldConf, $tbl->_dataLoc);
				unset($data[$ref]);
				$this->saveFile($disp->_type, $disp->_name, $tid);

				$this->genMenuInfo('serv');
				$data = &$this->getConfData($disp); //reload everything
				$needTrim = true;
			}
		}
		else if ( $disp->_act == 'B' )
		{
			$needTrim = true;
		}

		if ( isset($_GET['ctxseq']) )
		{
			if ( $this->changeCtxSeq($disp, DUtil::getGoodVal1($_GET['ctxseq'])) )
				$this->saveFile($disp->_type, $disp->_name, $tid);

		}

		if ( $needTrim )
		{
			DUtil::trimLastId($disp->_tid);
			DUtil::trimLastId($disp->_ref);
		}


		return $data;
	}

	function resetCtxSeq(&$data)
	{
		$keys = array_keys($data);
		$i = 1;
		foreach($keys as $k )
		{
			$data[$k]['order']->_v = $i;
			++$i;
		}
	}

	function changeCtxSeq($disp, $seq)
	{
		$plus = true;
		if ( $seq < 0 )
		{
			$plus = false;
			$seq = - $seq;
		}

		$ctxs = &$this->_curOne->_data['context'];

		$c = count($ctxs);
		if ( (!$plus && $seq == 1) || ($plus && $seq == $c) )
			 return false;

		$newCtxs = array();
		$i = 1;
		foreach( $ctxs as $uri => $ctx )
		{
			if ( $plus )
			{
				if ( $i == $seq )
				{
					$savedUri = $uri;
					$saved = $ctx;
				}
				else if ( $i == $seq + 1 )
				{
					$ctx['order']->_v = $seq;
					$newCtxs[$uri] = $ctx;
					$saved['order']->_v = $seq + 1;
					$newCtxs[$savedUri] = $saved;
				}
				else
					$newCtxs[$uri] = $ctx;
			}
			else
			{
				if ( $i == $seq - 1 )
				{
					$savedUri = $uri;
					$saved = $ctx;
				}
				else if ( $i == $seq )
				{
					$ctx['order']->_v = $seq - 1;
					$newCtxs[$uri] = $ctx;
					$saved['order']->_v = $seq;
					$newCtxs[$savedUri] = $saved;
				}
				else
					$newCtxs[$uri] = $ctx;
			}
			++$i;
		}

		$this->_curOne->_data['context'] = &$newCtxs;
		return true;
	}

	function instantiateTempl(&$disp)
	{
		$tn = $disp->_name;
		$tp = &$this->_serv->_data['tpTop'][$tn];
		if ( $tp['listeners']->_e != NULL )
		{
			$disp->_err = 'Please fix error in Mapped Listeners first.';
			return false;
		}

		$d = $this->_curOne->_data;
		$configFile = $d['general']['configFile'];
		if ( $configFile->_e != NULL )
		{
			$disp->_err = 'Please fix error in General->Config File first.';
			return false;
		}

		$vhRoot = $tp['member'][$disp->_ref]['vhRoot'];
		if ( $vhRoot->_v != NULL )
		{
			if ( $vhRoot->_e != NULL )
			{
				$disp->_err = 'Please fix VHRoot error in Member Virtual Host first.';
				return false;
			}
		}
		else
		{
			$vhRoot = $d['general']['vhRoot'];
			if ( $vhRoot->_e != NULL )
			{
				$disp->_err = 'Please fix error in General->VHost Root first.';
				return false;
			}
		}
		$vn = $disp->_ref;
		$vr = $this->getAbsFile($vhRoot->_v, 'SR', $vn);
		if ( !file_exists($vr) )
		{
			$disp->_err = 'Please fix error: Virtual Host Root ' . $vr . ' does not exist.';
			return false;
		}


		$path = $configFile->_v;
		if ( strncasecmp('$VH_ROOT', $path, 8) == 0 )
			$path = $vr . substr($path, 8);
		$path = $this->getAbsFile($path, 'SR', $vn);
		if ( !file_exists($path) && ! PathTool::createFile($path, $disp->_err) )
		{
			return false;
		}
		$confData = new ConfData('vh', $path, $vn);
		$confData->_data = &$this->_curOne->_data;

		$config = new ConfigFile();
		$res = $config->save($confData);
		if ( !$res )
		{
			$disp->_err = 'failed to save to file ' . $path;
			return false;
		}

		$v = array();
		$v['name'] = new CVal($vn);
		$v['vhRoot'] = $vhRoot;
		$v['configFile'] = $configFile;
		$v['maxKeepAliveReq'] = $d['general']['maxKeepAliveReq'];
		$v['smartKeepAlive'] = $d['general']['smartKeepAlive'];

		$v = array_merge($v, $d['sec']['file'], $d['sec']['cgi'], $d['sec']['conn']);
		$this->_serv->_data['vhTop'][$vn] = $v;

		$lns = preg_split("/, /", $tp['listeners']->_v, -1, PREG_SPLIT_NO_EMPTY);
		$vhmap['vhost'] = new CVal($vn);
		$domains = $tp['member'][$vn]['vhDomain']->_v . ', ' . $tp['member'][$vn]['vhAliases']->_v ;
		$vhmap['domain'] = new CVal($domains);
		foreach( $lns as $ln )
		{
			$l = &$this->_serv->_data['listeners'][$ln];
			$l['vhMap'][$vn] = $vhmap;
		}

		return true;
	}

	function enableDisableVh($act, $actId)
	{
		$haschanged = FALSE;
		$cur_disabled = array();
		if (isset($this->_serv->_data['service']['suspendedVhosts'])) {
			$cur_disabled = preg_split("/[,;]+/", $this->_serv->_data['service']['suspendedVhosts']->_v, -1, PREG_SPLIT_NO_EMPTY); 
		}
		$found = in_array($actId, $cur_disabled);
		if ($act == 'disable') {
			if (!$found) {
				$cur_disabled[] = $actId;
				$haschanged = TRUE;
			}
		}
		else if ($act == 'enable') {
			if ($found) {
				$key = array_search($actId, $cur_disabled);
				unset($cur_disabled[$key]);
				$haschanged = TRUE;
			}
		}
		if ($haschanged) {
			$vals = implode(',', $cur_disabled);
			$this->_serv->_data['service']['suspendedVhosts'] = new CVal($vals);
			$this->saveFile('serv', '');
		}
	}
	
	function &getPageData(&$disp, &$confErr)
	{
		$disp->_err = NULL;

		$data = &$this->doAction($disp);

		if(isset($this->_info['CONF_FILE_ERR'])) {
			$confErr = $this->_info['CONF_FILE_ERR'];
		}
		return $data;
	}

}

?>
