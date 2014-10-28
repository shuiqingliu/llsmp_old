<?
require_once('DUtil.php');

class PRODUCT
{

	var $product = NULL;
	var $version = NULL;
	var $edition = '';
	var $type = NULL; //LSWS or LSLB, get from client
	
	var $tmp_path = NULL;
	var $processes = 1;
	
	var $new_release = NULL;
	var $new_version = NULL;
	
	var $installed_releases = array();	
	
	function &singleton() {
		static $instance;

		if (!isset($instance)) {
			$c = __CLASS__;
			$instance = new $c();
			$instance->init(); //there is php4 bug where constructor is not called..so workaround..call init()

		}

		return $instance;
	}


	function init() {

		$client = CLIENT::singleton();
		$this->type = $client->type;
		
		$this->processes = DUtil::grab_input("server",$this->type . '_CHILDREN','int');
		
		$matches = array();
		if($this->type == 'LSWS') {
			$this->product = 'LITESPEED WEB SERVER';
			$str = DUtil::grab_input("server",'LSWS_EDITION');
			if ( preg_match('/^(.*)\/(.*)\/(.*)$/i', $str, $matches ) ) {
				$this->edition = strtoupper(trim($matches[2]));
				$this->version = trim($matches[3]);
			}
			$this->tmp_path = "/tmp/lshttpd/";
		}
		elseif ($this->type == 'LSLB') {
			$this->product = 'LITESPEED LOAD BALANCER';
			$str = DUtil::grab_input("server",'LSLB_RELEASE');
			if ( preg_match('/^(.*)\/(.*)$/i', $str, $matches ) ) {
				$this->version = trim($matches[2]);
			}
			$this->tmp_path = "/tmp/lslbd/";
		}
	}
	
	function getInstalled() {

		$dir = DUtil::grab_input("server",'LS_SERVER_ROOT'). 'bin';
		$dh = @opendir($dir);
		if ($dh) {
			while (($fname = readdir($dh)) !== false) {
				$matches = array();
				if ($this->type == 'LSWS') {
					if (preg_match('/^lswsctrl\.(.*)$/', $fname, $matches)) {
						$this->installed_releases[] = $matches[1];
					}
				}
				elseif ($this->type == 'LSLB') {
					if (preg_match('/^lslbctrl\.(.*)$/', $fname, $matches)) {
						$this->installed_releases[] = $matches[1];
					}
					
				}
			}
			closedir($dh);
		}
	}
	
	function isInstalled($version) {
		$state = FALSE;
		
		foreach($this->installed_releases as $value) {
			if($version == $value) {
				return TRUE;
			}
		}
		
		return $state;
	}
	
	function refreshVersion() {
		$versionfile = DUtil::grab_input("server",'LS_SERVER_ROOT'). 'VERSION';
		$this->version = trim( file_get_contents($versionfile) );		
	}
	
	function getNewVersion() {

		$dir = DUtil::grab_input("server",'LS_SERVER_ROOT'). 'autoupdate';
		$releasefile = $dir.'/release';
		if ( file_exists($releasefile) ) {
			$this->new_release = trim( file_get_contents($releasefile) );
			$matches = array();
			if (strpos($this->new_release, '-')) {
				if ( preg_match( '/^(.*)-/', $this->new_release, $matches ) ) {
					$this->new_version = $matches[1];
				}
			}
			else {
				$this->new_version = $this->new_release;
			}
		}
	}

}

?>