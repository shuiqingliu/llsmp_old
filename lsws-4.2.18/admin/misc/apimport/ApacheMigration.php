
<?php
require_once('PathTool.php');	
require_once('LSWSConf.php');

//Globals

$IgnoredTags = 
array(
	'ServerType', 'LockFile', 'PidFile', 'ScoreBoardFile',
	'KeepAlive', 'MaxKeepAliveRequests', 'KeepAliveTimeout',
	'MinSpareServers', 'MaxSpareServers', 'StartServers',
	'MaxClients', 'MaxRequestsPerChild', 'BindAddress',
	'LoadModule', 'User', 'Group', '</IfDefine>' 
	); 

$NotSupportedTags =
array(
	'ServerPath', 'CookieLog', 'LogFormat'
	);

$SupportedModules =
array(
	'mod_expires.c', 'mod_alias.c', 'mod_rewrite.c', 'mod_access.c', 'mod_auth.c', 'mod_ssl.c'
	);

$AllowDupBlock = array('VirtualHost');

class MigrUtil
{
	function checkTag($tag, $line)
	{
		if ( strncasecmp( $line, $tag, strlen($tag) ) == 0 )
		{
			return trim( substr( $line, strlen($tag) ), " \"\'" );
		}
		else
			return NULL;

	}

	function getBool($val)
	{
		return ( (strncasecmp( $val, 'On', 2 ) == 0) ? 1 : 0 );
	}

	function getCommaList($val)
	{
		return implode(',', preg_split("/[\s]+/", $val) );
	}

	function splitToList($val)
	{
		return preg_split("/[\s\'\"]+/", $val);
	}

	function getAbsPath( $root, $path )
	{
		if ( $path[0] != '/' )
		{
			if ( !isset($root) )
				return NULL;
			$absPath = $root . '/' . $path;
			$absPath = str_replace('//', '/', $absPath);
			return $absPath;
		}
		else
			return $path;
	}

	function &getIncludeFiles( $root, $includePath )
	{
		if ( strlen($includePath) == 0 )
			return NULL;

		$includePath = MigrUtil::getAbsPath( $root, $includePath);
		if ( $includePath == NULL )
			return NULL;

		$files = array();
		if ( is_file($includePath) )
		{
			$files[] = $includePath;
		}
		else 
		{
			$pattern = '';
			$pos = strpos($includePath, '*');
			if ( $pos === FALSE )
				$pos = strpos($includePath, '?');
			if ( $pos !== FALSE )
			{
				$pos1 = strrpos($includePath, '/');
				if ( $pos1 === FALSE || $pos1 > $pos)
					return NULL;
				$pattern = substr($includePath, $pos1+1);
				$pattern = str_replace('?','.',$pattern);
				$pattern = str_replace('*', '(.*)', $pattern);
				$pattern = '/^'.$pattern.'$/';
				$includePath = substr($includePath, 0, $pos1);
			}

			if ( is_dir($includePath) )
			{
				if ($handle = opendir($includePath)) 
				{
					while (false !== ($file = readdir($handle)))
					{
						if ($file != "." && $file != ".." )
						{
							if ( $pattern != NULL && ! preg_match($pattern, $file) )
								continue;

							$file = $includePath . '/' . $file;
							echo " include file = $file \n";
							if ( is_file($file) )
								$files[] = $file;
						}
					}
					closedir($handle);
				}
			}
		}

		if ( count($files) == 0 )
			return NULL;

		$lists = array();
		foreach ( $files as $f )
		{
			$lines = file( $f );
			if ( $lines != false )
				$lists[$f] = $lines;

		}

		return $lists;
	}

}

class Entry
{
	var $_directive;
	var $_val;

	var $_status; // Unknown=0, Processed=1, Ignored=2, Error=3, Not Support=4
	var $_statusMsg;

	var $_line;
	var $_lineNum;
	var $_fileName;

	function Entry( $line, $lineIndex, &$fileName )
		{
			$this->_line = $line;
			$this->_lineNum = $lineIndex;
			$this->_fileName = &$fileName;

			$pos = strpos($line, ' ');
			$this->_directive = substr($line, 0, $pos);
			$this->_val = trim( substr($line, $pos), " \"\'" );

			global $IgnoredTags, $NotSupportedTags;
			if ( in_array($this->_directive, $IgnoredTags) )
				$this->_status = 2;
			else if ( in_array($this->_directive, $NotSupportedTags) )
				$this->_status = 4;
			else
				$this->_status = 0;
		}

	function processTag( $tag )
		{
			if ( strcasecmp($this->_directive, $tag) == 0 )
			{
				$this->_status = 1;
				return true;
			}
			else
				return false;
		}

	function setStatus( $status, $statusMsg )
		{
			// Unknown=0, Processed=1, Ignored=2, Error=3, Not Support=4
			$this->_status = $status;
			$this->_statusMsg = $statusMsg;
		}
}

class Block
{
	var $_type;
	var $_name;
	var $_depth;
	var $_allowDup;
	var $_g = array(); //general
	var $_c = array(); //children

	var $_status; // Unknown=0, Processed=1, Ignored=2, Error=3, Not Support=4
	var $_statusMsg;

	function Block($type, $name, $depth, $dupKey)
		{
			$this->_type = $type;
			$this->_name = $name;
			$this->_depth = $depth;
			$this->_allowDup = $dupKey;
			if ( $depth > 100 )
				exit("Failed to parse the file: reached maximum depth $depth!");
		}

	function addAttribute( &$entry )
		{
			$this->_g[] = $entry;
		}

	function setStatus( $status, $statusMsg )
		{
			// Unknown=0, Processed=1, Ignored=2, Error=3, Not Support=4
			$this->_status = $status;
			$this->_statusMsg = $statusMsg;
		}

	function addChildBlock(&$block)
	{
		if ( strcasecmp($block->_type, 'IfModule') == 0 )
		{	// move one level up
			if ( count($block->_g) > 0 )
			{
				foreach( $block->_g as $entry )
					$this->_g[] = $entry;
			}
			if ( count($block->_c) > 0 )
			{
				$types = array_keys($block->_c);
				foreach( $types as $type )
				{
					$names = array_keys( $block->_c[$type] );
					foreach ( $names as $name ) 
					{
						if ( is_array($block->_c[$type][$name]) )
						{
							foreach( $block->_c[$type][$name] as $child )
								$this->_c[$type][$name][] = $child;
						}
						else
						{
							$this->_c[$type][$name] = $block->_c[$type][$name];
						}
					}
				}
			}
		}
		else
		{
			if ( $block->_allowDup )
				$this->_c[$block->_type][$block->_name][] = $block;
			else
				$this->_c[$block->_type][$block->_name] = $block;

		}
	}

}

class FileLoader
{
	var $_apacheServerRoot;
	var $_debug = 0;

	function &load($apache_conf_path)
	{
		$lines = file($apache_conf_path);
		if ( $lines == false ) 
		{
			echo "Failed to read from Apache config file $apache_conf_path \n";
			return false;
		}

		$rawData = new Block('serv','serv', 0, false);
		$startLine = 0;
		$this->parseLines($lines, $startLine, $rawData, $apache_conf_path);

		if ( $this->_debug )
			var_dump($rawData);

		return $rawData;
	}

	function parseLines(&$lines, &$lineIndex, &$block, $fileName)
	{
		$c = count($lines);
		if ( $this->_debug )
			echo " call parseLines $lineIndex c=$c $fileName\n";

		while ( $lineIndex < $c )
		{
			$line = trim($lines[$lineIndex]);
			$lineIndex ++;

			if ( strlen($line) == 0 || $line[0] == '#' )
				continue;  // bypass comments

			if ( $this->_debug )			
				echo " curLine $lineIndex\n";

			if ( preg_match("/^<([A-z]+)\s+(.*)>$/", $line, $matches) )
			{	
				
				$blockType = $matches[1];
				$blockName = $matches[2];
				$supported = true;
				if ( strcasecmp($blockType, 'IfDefine' ) == 0 )
					continue;
				if ( strcasecmp($blockType, 'IfModule') == 0 )
				{
					global $SupportedModules;
					if ( !in_array($blockName, $SupportedModules) ) 
						$supported = false;
				}
				if ( $this->_debug )
					echo " start new block (supported= $supported ): $line\n";
				$allowDup = false;
				global $AllowDupBlock;
				if ( in_array($blockType, $AllowDupBlock) )
					$allowDup = true;
				$child = new Block( $blockType, $blockName, $block->_depth+1, $allowDup );
				$this->parseLines( $lines, $lineIndex, $child, $fileName );
				if ( $supported )
					$block->addChildBlock( $child );
			}
			else
			{
				$end_tag = "</$block->_type>";
				if ( strncmp( $end_tag, $line, strlen($end_tag) ) == 0 )
				{
					if ( $this->_debug )
						echo " end $end_tag new block: $line\n";
					return;
				}

				$path = MigrUtil::checkTag( 'Include ', $line);
				if ( $path != NULL )
				{
					if ( $this->_debug )
						echo " found Include directive = $path \n";

					$lists = &MigrUtil::getIncludeFiles( $this->_apacheServerRoot, $path );
					if ( $lists != NULL )
					{
						foreach ( $lists as $includeFile => $includeLines )
						{
							$includeStart = 0;
							$this->parseLines( $includeLines, $includeStart, $block, $includeFile );
						}
					}
					else
						echo " failed to include $path\n";
				}
				else
				{
					$entry = new Entry( $line, $lineIndex, $fileName ) ;

					if ( !isset($this->_apacheServerRoot ) )
					{
						$this->_apacheServerRoot = MigrUtil::checkTag('ServerRoot ', $line);
						if ( $this->_apacheServerRoot != NULL )
							$entry->_status = 1;
					}
					$block->addAttribute($entry);

					if ( $this->_debug )
						echo "  add genAttr $line\n";
				}
			}

		}
	}

}

class Extractor
{
	var $_apacheServerRoot;
	var $_serv = array();
	var $_vhosts = array();
	var $_vhnames = array();
	var $_debug = 0;

	function Extractor($apacheServerRoot, $lswsServerRoot)
	{
		$this->_apacheServerRoot = $apacheServerRoot;
		//load default
		$lswsConf = new LSWSConf();
		$this->_serv = &$lswsConf->loadServConf($lswsServerRoot);
	}

	function extract( &$rawData )
	{
		$this->extractServGeneral( $rawData->_g );
		$this->extractServSecurity( $rawData->_g, $_serv['security'] );
		$this->extractTuning( $rawData->_g );
		$this->extractListener( $rawData->_g );
		$this->extractVhosts( $rawData );
		//var_dump( $this->_serv['listeners'] );
	}

	function extractVhosts( &$block )
	{
		$catchAll = array();
		$this->extractVH( $block, 'ApacheMain', $catchAll);
		//var_dump( $block->_c['VirtualHost'] );
		if ( isset($block->_c['VirtualHost']) )
		{
			$vhnames = array_keys($block->_c['VirtualHost']);
			foreach( $vhnames as $name )
			{
				for ( $i = 0; $i < count($block->_c['VirtualHost'][$name]); ++$i )
					$this->extractVH( $block->_c['VirtualHost'][$name][$i], $name, $catchAll );
			}
		}
		//analyse and set all other values,

		//add catchAll domain
		foreach( $catchAll as $addr => $vhname )
		{
			$this->_serv['listeners'][$addr]['vhmap'][$vhname]['domain'] .= ', *';
		}

		$vhnames = array_keys( $this->_vhosts );
		foreach ( $vhnames as $name )
		{
			$this->_serv['vhTop'][$name] = $this->_vhosts[$name]['vhTop'];
		}

	}

	function extractVH( &$block, $name, &$catchAll )
	{
		$vh = array();
		$vh['vhTop']['name'] = $name;
		$domains = array();
		$vhname = '';

		$list = &$block->_g;
		for ( $i = 0 ; $i < count( $list ) ; ++$i )
		{
			$entry = &$list[$i];
			if ( $entry->_status > 0 )
				continue;
			if ( $entry->processTag('ServerName') )
			{
				if ( $vhname == '' )
				{
					$vhname = $entry->_val;
					$i = 0;
					while ( isset( $this->_vhnames[$vhname] ) )
					{
						++$i;
						$vhname = $entry->_val . $i; 
					}
					$this->_vhnames[$vhname] = 1;
					$vh['vhTop']['name'] = $vhname;
					$domains[] = $entry->_val;
				}
			}
			else if ( $entry->processTag('ServerAlias') )
			{
				$alias = preg_split("/\s+/", $entry->_val);
				$domains = array_merge($domain, $alias);
			}
			else if ( $entry->processTag('DocumentRoot') )
			{
				$vh['general']['docRoot'] = $entry->_val;
				$vh['vhTop']['vhRoot'] = $entry->_val;
			}
			else if ( $entry->processTag('DirectoryIndex') )
			{
				$vh['general']['index']['indexFiles'] = MigrUtil::getCommaList($entry->_val);
			}
			else if ( $entry->processTag('Options') )
			{
				$res = $this->containOption($entry->_val, 'Indexes');
				if ( $res != -1 )
					$vh['general']['index']['autoIndex'] = $res;
				$res = $this->containOption($entry->_val, 'FollowSymLinks');
				if ( $res != -1 )
					$vh['vhTop']['allowSymbolLink'] = $res;
				$res = $this->containOption($entry->_val, 'SymLinksIfOwnerMatch');
				if ( $res == 1 )
					$vh['vhTop']['allowSymbolLink'] = 2;

			}
			else if ( $entry->processTag('Order') )
			{
				$order = $entry->_val;
			}
			else if ( $entry->processTag('Allow') )
			{
				$allow = $entry->_val;
			}
			else if ( $entry->processTag('Deny') )
			{
				$deny = $entry->_val;
			}
			else if (( $entry->processTag( 'SSLCertificateFile' ) )||
					 ( $entry->processTag( 'SSLCertificateKeyFile' ) )||
					 ( $entry->processTag( 'SSLCipherSuite' ) )||
					 ( $entry->processTag( 'SSLEngine' ) ))
			{
				$vh['ssl'][ strtolower($entry->_directive) ] = $entry->_val; 
			}
		

		}

		$domains = array_unique($domains);
		$this->addVhMap($vh, $vhname, $name, $domains, $catchAll);

		$this->extractLogging( $list, $vh['general'] );
		$vh['general']['log_useServer'] = (isset($vh['general']['log_fileName']))?0:1;
		$vh['general']['accessLog_useServer'] = (isset($vh['general']['accessLog_fileName']))?0:1;

		$this->extractExpires( $list, $vh['general'] );

		if ( isset( $order ) )
			$this->extractAccessControl( $order, $allow, $deny, $vh['security']['accessControl'] );

		$this->extractVhRewrite( $list, $vh );
		$this->extractContext( $block, $vh );
		$vh['vhTop']['configFile'] = '$SERVER_ROOT/conf/' . $vhname . '.xml';

		$this->_vhosts[$vhname] = $vh;

	}

	function configssl( &$vh, &$l )
	{

		if ( !isset( $vh['ssl']['sslengine'] ) )
			return;
		if ( strcasecmp( $vh['ssl']['sslengine'], 'on') )
			return;  
		if ( !isset( $vh['ssl']['sslcertificatefile'] )||
			 !isset( $vh['ssl']['sslcertificatekeyfile']))
			return;
		$l['secure'] = 1;
		$l['certFile'] = $vh['ssl']['sslcertificatefile'];
		$l['keyFile'] = $vh['ssl']['sslcertificatekeyfile'];
		if ( isset( $vh['ssl']['sslciphersuite'] ) )
		{
			$l['ciphers'] = $vh['ssl']['sslciphersuite'];
		}
	 }


	function addVhMap(&$vh, &$vhname, $name, &$domains, &$catchAll)
	{
		$laddrs = array_keys($this->_serv['listeners']);
		if ( $vhname == '' )
			$vhname = $name;

		if ( $name == 'ApacheMain' )
		{	//default server
			return;
		}

		$tmpvn = preg_split("/\s+/", $name);
		foreach( $tmpvn as $vn )
		{
			$pos = strpos($vn, ':');
			if ( $pos === false )
			{
				$ip = $vn;
				$port = '*';
			}
			else
			{
				$ip = substr($vn, 0, $pos);
				$port = substr($vn, $pos+1);
			}

			foreach ( $laddrs as $addr )
			{
				$l = &$this->_serv['listeners'][$addr];
				if (( isset( $l['namevh']))&&
					(!isset( $catchAll[$addr] )))
					$catchAll[$addr] = "ApacheMain";
				if ( $this->checkVhListenerMap($ip, $port, $l['ip'], $l['port']) )
				{
					$l['vhmap'][$vhname]['vh'] = $vhname;
					$l['vhmap'][$vhname]['domain'] = implode(',',$domains);
					if (( $ip == '_default_' )||( $l['namevh'] == 0 ))
					{
						$catchAll[$addr] = $vhname;
						if ( isset( $vh['ssl'] ) )
						{
							$this->configssl( $vh, $l );
						}
							 
					}
					else if ( $ip != '*' )
						$l['vhmap'][$vhname]['domain'] .= ','.$ip;
				}

			}
		}

	}


	function checkVhListenerMap($vh_ip, $vh_port, $l_ip, $l_port)
	{
		if ( $vh_port != '*' )
		{
			if ( $vh_port != $l_port )
				return false;
		}
		if ( ($l_ip == '*') || ($vh_ip == '*') 
			 || ($vh_ip == '_default_') || ($l_ip == $vh_ip) )
		{
			if ( $this->_debug )
				echo "checkVhListenerMap=true VH $vh_ip:$vh_port -- Listener $l_ip:$l_port \n";
			return true;
		}
		else
			return false;
	}

	function extractTuning( &$entryList )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;

			if ( $entry->processTag('Timeout') )
			{
				$this->_serv['tuning']['connTimeout'] = $entry->_val;
			}
		}
	}

	function extractLogging( &$entryList, &$holder )
	{
		$hasCustomLog = false;
		$holder['log_logLevel'] = 'INFO';
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;

			if ( $entry->processTag('ErrorLog') )
			{
				if ( $entry->_val[0] == '|' )
				{
					$entry->setStatus(4, 'Does not allow pipe, use default value instead.');
				}
				else if ( strncmp($entry->_val, 'syslog', 6) == 0 )
				{
					$entry->setStatus(4, 'Does not support syslog, use default value instead.');			  }
				else
				{
					$log_fileName = $entry->_val;
					if ( $log_fileName{0} != '/' )
						$holder['log_fileName'] = $this->_apacheServerRoot.'/'.$log_fileName;
					else 
						$holder['log_fileName'] = $log_fileName;
				}
			}
			else if ( $entry->processTag('LogLevel') )
			{
				$levels = array('error','warn','notice','info','debug');
				if ( in_array($entry->_val, $levels) )
					$holder['log_logLevel'] = strtoupper($entry->_val);
			}
			else if ( $entry->processTag('TransferLog') )
			{
				if ( $entry->_val[0] == '|' )
				{
					$entry->setStatus(4, 'Does not allow pipe, use default value instead.');
				}
				else
				{
					$accessLog_fileName = $entry->_val;
					if ( $accessLog_fileName{0} != '/' )
						$holder['accessLog_fileName'] = $this->_apacheServerRoot.'/'.$accessLog_fileName;
					else 
						$holder['accessLog_fileName'] = $accessLog_fileName;
				}
				
			}
			else if ( $entry->processTag('CustomLog') )
			{
				$entry->setStatus(4, 'Does not support customized log.');
				$hasCustomLog = true;
			}

		}
		//if ( $hasCustomLog && !isset($holder['accessLog_fileName']) )
		//	$holder['accessLog_fileName'] = 'logs/access_log';
	}

	function extractExpires( &$entryList, &$holder )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
//('enableExpires', 'expiresByType', 'expiresDefault');
			if ( $entry->processTag('ExpiresActive') )
			{
				$holder['enableExpires'] = MigrUtil::getBool($entry->_val);
			}
			else if ( $entry->processTag('ExpiresDefault') )
			{
				$holder['expiresDefault'] = $entry->_val;
			}
			else if ( $entry->processTag('ExpiresByType') )
			{
				$byType[] = $entry->_val;
			}
		}
		if ( isset($byType) )
			$holder['expiresByType'] = implode(',', $byType);
	}

	function newAliasContext(&$entry, &$holder)
	{
		$isRegEx = (stristr( $entry->_directive, 'Match') !== false);
		$type = (stristr( $entry->_directive, 'Script') === false ) ? 'NULL':'cgi';

		$l = MigrUtil::splitToList($entry->_val);
		if ( count($l) == 2 )
		{
			$ctx = array();
			$ctx['type'] = $type;
			if ( $isRegEx )
				$ctx['uri'] = 'exp:' . $l[0];
			else
				$ctx['uri'] = $l[0];
			$ctx['location'] = $l[1];
			$holder[$ctx['uri']] = $ctx;
		}
		else
			$entry->setStatus( 3, 'Wrong parameters');
	}

	function newRedirectContext(&$entry, &$holder)
	{
		$l = MigrUtil::splitToList($entry->_val);
		$isRegEx = (stristr( $entry->_directive, 'Match') !== false);


		$codes = array('permanent'=>301, 'temp'=>302, 'seeother'=>303, 'gone'=>410);
		$ctx = array();
		$ctx['type'] = 'redirect';

		if ( count($l) == 2 )
		{
			$uri = $l[0];
			$loc = $l[1];
		}
		else if ( count($l) == 3 )
		{
			$status = $l[0];
			if ( !is_numeric($status) )
			{
				if ( isset($codes[$status]) )
					$status = $codes[$status];
				else
					$status = 302;
			}
			$ctx['statusCode'] = $status;
			$uri = $l[1];
			$loc = $l[2];
		}
		else
		{
			$entry->setStatus( 3, 'Wrong parameters');
			return;
		}

		if ( strcasecmp($entry->_directive, 'RedirectTemp') == 0 )
			$status = 302;
		else if ( strcasecmp($entry->_directive, 'RedirectPermanent') == 0 )
			$status = 301;

		$ctx['externalRedirect'] = ( $uri[0] == '/' && !isset($status) )? 0:1;

		if ( $isRegEx )
			$ctx['uri'] = 'exp:' . $uri;
		else
			$ctx['uri'] = $uri;
		$ctx['location'] = $loc;
		$holder[$ctx['uri']] = $ctx;
		
	}

	function extractSpecialContext( &$entryList, &$holder )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			if ( $entry->processTag('Alias') 
				 || $entry->processTag('AliasMatch') 
				 || $entry->processTag('ScriptAlias')
				 || $entry->processTag('ScriptAliasMatch') )
			{
				$this->newAliasContext( $entry, $holder);
			}
			else if ( $entry->processTag('Redirect') 
					  || $entry->processTag('RedirectMatch')
					  || $entry->processTag('RedirectTemp')
					  || $entry->processTag('RedirectPermanent') )
			{
				$this->newRedirectContext( $entry, $holder );
			}
		}

	}

	function checkURIfromPath( $path, $basePath, $baseUri )
	{
		if ( strncmp( $path, $basePath, strlen($basePath) ) !== 0 )
			return false;

		if ( strlen($basePath) == strlen($path) )
			return $baseUri;

		$uri = $baseUri . '/' . substr($path, strlen($basePath));
		$uri = str_replace( array('///','//'), '/', $uri );
		return $uri; 
	}

	function locateContext($path, &$contextLists, $docRoot)
	{
		$u = array_keys($contextLists);
		//find matched one first
		foreach( $u as $uri )
		{
			if ( $path === $contextLists[$uri]['location'] )
				return $uri;
		}

		$found = false;
		foreach( $u as $uri )
		{
			$ctx = &$contextLists[$uri];
			$newUri = $this->checkURIfromPath( $path, $ctx['location'], $uri );
			if ( $newUri !== false )
			{
				$found = true;
				break;
			}
		}

		if ( !$found )
		{
			$newUri = $this->checkURIfromPath( $path, $docRoot, '/' );
			if ( $newUri !== false )
				$found = true;
		}

		if ( $found )
		{
			if ( !isset($contextLists[$newUri]) )
			{
				$ctx['uri'] = $newUri;
				$ctx['location'] = $path;
				$contextLists[$newUri] = $ctx;
			}
			return $newUri;
		}

		return false;
	}

	function containOption( $options, $checkTag )
	{
		$pos = strpos( $val, $checkTag );
		if ( $pos !== false )
		{
			if ( $pos > 0 && $val[$pos-1] === '-' )
				return 0;
			else
				return 1;
		}
		return -1;
	}

	function extractCtxAllowOverride( $val, &$ctx )
	{
		$res = $this->containOption($val, 'All');
		if ( $res == 1 )
			$allow = 31;
		else
			$allow = 0;
		$res = $this->containOption($val, 'Limit');
		if ( $res == 1 )
			$allow |= 1;
		else if ( $res == 0 )
			$allow &= (~1);

		$res = $this->containOption($val, 'AuthConfig');
		if ( $res == 1 )
			$allow |= 2;
		else if ( $res == 0 )
			$allow &= (~2);
		$res = $this->containOption($val, 'FileInfo');
		if ( $res == 1 )
			$allow |= 4;
		else if ( $res == 0 )
			$allow &= (~4);
		$res = $this->containOption($val, 'Indexes' );
		if ( $res == 1 )
			$allow |= 8;
		else if ( $res == 0 )
			$allow &= (~8);
		$res = $this->containOption($val, 'Options' );
		if ( $res == 1 )
			$allow |= 16;
		else if ( $res == 0 )
			$allow &=(~16);
		$ctx['allowOverride'] = $allow;
	}

	function extractAccessControl( $order, $allow, $deny, &$holder )
	{
		if ( !isset($order) )
			return;
		if ( isset($allow) )
		{
			$allow = trim(substr($allow, 5)); //remove from
			$allow = str_replace(array('all','All','ALL'), '*', $allow);
			$holder['accessControl_allow'] = $allow;
		}
		if ( isset($deny) )
		{
			$deny = trim(substr($deny, 5));
			$deny = str_replace(array('all','All','ALL'), '*', $deny);
			$holder['accessControl_deny'] = $deny;
		}
	}

	function extractCtxAuth( &$entryList, &$ctx, &$realms )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			if ( $entry->processTag('AuthName') )
			{
				$ctx['authName'] = $entry->_val;
			}
			else if ( $entry->processTag('Require') )
			{
				$ctx['required'] = $entry->_val;
			}
			else if ( $entry->processTag('AuthUserFile') )
			{
				$au = MigrUtil::getAbsPath( $this->_apacheServerRoot, $entry->_val );
			}
			else if ( $entry->processTag('AuthGroupFile') )
			{
				$ag = MigrUtil::getAbsPath( $this->_apacheServerRoot, $entry->_val );
			}
		}
		if ( isset($au) )
		{ //check realm
		    $key = $au . $ag;
			if ( !isset($realms[$key]) )
			{
				$realm = array();
				$realm['userDB_location'] = $au;
				if ( isset($ag) )
					$realm['groupDB_location'] = $ag;
				if ( isset($ctx['authName']) )
				{
					$realm_name = $ctx['authName'];
					if ( isset( $realms['names'][$realm_name] ) )
						$realm_name .= '_' . count($realms['names']);
				}
				else
					$realm_name = 'Realm_' . count($realms['names']);
				$realm['name'] = $realm_name;
				$realm['type'] = 'file';
				$realms['names'][$realm_name] = $realm_name;

				$realms[$key] = $realm;
			}
		}

	}

	function extractDirectory( &$block, &$ctx, &$realms )
	{
		$entryList = &$block->_g; 
		$needAuth = false;
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			if ( $entry->processTag('Options') )
			{
				$res = $this->containOption($entry->_val, 'Indexes');
				if ( $res != -1 )
					$ctx['autoIndex'] = $res;
			}
			else if ( $entry->processTag('AllowOverride') )
			{
				$this->extractCtxAllowOverride( $entry->_val, $ctx );
			}
			else if ( $entry->processTag('AuthType') )
			{
				if ( $entry->_val == 'Basic' )
					$needAuth = true;
				else
					$entry->setStatus( 4, 'Not support Digest' );
			}
			else if ( $entry->processTag('Order') )
			{
				$order = $entry->_val;
			}
			else if ( $entry->processTag('Allow') )
			{
				$allow = $entry->_val;
			}
			else if ( $entry->processTag('Deny') )
			{
				$deny = $entry->_val;
			}
		}
		if ( $needAuth )
			$this->extractCtxAuth( $entryList, $ctx, $realms );
		if ( isset($order) )
			$this->extractAccessControl( $order, $allow, $deny, $ctx );
		$this->extractCtxRewrite( $entryList, $ctx );
	}

	function extractContext( &$block, &$vh )
	{
		$contextLists = array();
		$realms = array();
		$this->extractSpecialContext( $block->_g, $contextLists);

		$dirBlocks = &$block->_c['Directory'];

		if ( isset($dirBlocks) && count($dirBlocks) > 0 )
		{
			$dirLoc = array_keys($dirBlocks);
			foreach ( $dirLoc as $loc )
			{
				if ( $loc[0] === '~' )
					$dirBlocks[$loc]->setStatus( 4, 'Does not support regular expression Directory');
				else
				{
					$dirBlocks[$loc]->setStatus( 1, 'OK' );
					$path = trim($block->_name, '"*\'');
					$uri = $this->locateContext( $path, $contextLists, $vh['general']['docRoot']);
					if ( $uri === false )
					{
						$dirBlocks[$loc]->setStatus( 2, 'Ingores directory, not accessible!');
					}
					else
					{
						$ctx = &$contextLists[$uri];
						$this->extractDirectory( $dirBlocks[$loc], $ctx, $realms );
					}
				}
			}
		}

		foreach ( $contextLists as $ctx )
		{
			if ( $ctx['type'] == 'NULL' )
				$ctx['allowBrowse'] = 1;
			$vh['context'][$ctx['type']][$ctx['uri']] = $ctx;
		}
		if ( count($realms) > 0 )
		{
			foreach ( $realms as $realm )
			{
				$vh['security']['realm'][$realm['name']] = $realm;
			}
		}

	}

	function extractVhRewrite( &$entryList, &$holder )
	{
		$rewrite = array();
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			else if ( $entry->processTag('RewriteEngine') )
			{
				$rewrite['ctrl']['enable'] = MigrUtil::getBool($entry->_val);
			}
			else if ( $entry->processTag('RewriteOptions') )
			{
				$entry->setStatus(4, 'Not support at virtual host level');
			}
			else if ( $entry->processTag('RewriteLog') )
			{
				$entry->setStatus(4, 'Not support, will write to error log.');
			}
			else if ( $entry->processTag('RewriteLogLevel') )
			{
				$rewrite['ctrl']['logLevel'] = $entry->_val;
			}
			else if ( $entry->processTag('RewriteLock') )
			{
				$entry->setStatus(2, '');
			}
			else if ( $entry->processTag('RewriteMap') )
			{
				$t = MigrUtil::splitToList($entry->_val);
				$map['name'] = $t[0];
				$map['location'] = $t[1];
				$rewrite['map'][$map['name']] = $map;
			}
			else if ( $entry->processTag('RewriteCond') || $entry->processTag('RewriteRule') )
			{
				$rewrite['rules'] .= $entry->_line . "\n";
			}

		}
		if ( count($rewrite) > 0 )
			$holder['rewrite'] = $rewrite;
	}

	function extractCtxRewrite( &$entryList, &$ctx )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			else if ( $entry->processTag('RewriteEngine') )
			{
				$ctx['rewrite_enable'] = MigrUtil::getBool($entry->_val);
			}
			else if ( $entry->processTag('RewriteOptions') )
			{
				if ( strpos($entry->_val, 'inherit') !== false )
					$ctx['rewrite_inherit'] = 1;
			}
			else if ( $entry->processTag('RewriteBase') )
			{
				$ctx['rewrite_base'] = $entry->_val;
			}
			else if ( $entry->processTag('RewriteCond') || $entry->processTag('RewriteRule') )
			{
				$ctx['rewrite_rules'] = $entry->_line;
			}

		}
	}


	function extractServSecurity( &$entryList, &$s )
	{
		$g = array();
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;
			else if ( $entry->processTag('Options') )
			{
				$res = $this->containOption($entry->_val, 'FollowSymLinks');
				if ( $res != -1 )
					$s['fileAccessControl_followSymbolLink'] = $res;
				$res = $this->containOption($entry->_val, 'SymLinksIfOwnerMatch');
				if ( $res == 1 )
					$s['fileAccessControl_followSymbolLink'] = 2;

			}
		}
	}

	function extractServGeneral( &$entryList )
	{
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;

			if ( $entry->processTag('ServerName') )
			{
				$this->_serv['general']['serverName'] = $entry->_val;
			}
			else if ( $entry->processTag('ServerAdmin') )
			{
				$this->_serv['general']['adminEmails'] = $entry->_val;
			}
			else if ( $entry->processTag('DirectoryIndex') )
			{
				$this->_serv['general']['indexFiles'] = MigrUtil::getCommaList($entry->_val);
			}
		}

		//$this->extractLogging( $entryList, $this->_serv['general'] );
		$this->extractExpires( $entryList, $this->_serv['general'] );
		
	}

	function extractListener( &$entryList )
	{
		$port = NULL;
		$listeners = array();
		$hasListen = false;
		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;

			if ( $entry->processTag('Listen') )
			{
				$l = array();
				if ( preg_match("/^(\d+)$/", $entry->_val, $m) )
				{
					$l['port'] = $m[1];
					$l['ip'] = '*';
					$l['namevh'] = 0;
					$listeners[] = $l;
					$hasListen = true;
				}
				else if ( preg_match("/^([\d\.]+):(\d+)$/", $entry->_val, $m) )
				{
					$l['ip'] = $m[1];
					$l['port'] = $m[2];
					$l['namevh'] = 0;
					$listeners[] = $l;
					$hasListen = true;
				}
				else
				{
					$entry->setStatus(3, 'Only accept IP Address:Port');
				}
			}
			else if ( $entry->processTag('Port') )
			{
				$port = &$entry;
			}
		}
		if ( isset($port) )
		{
			if ( $hasListen )
			{
				$port->setStatus( 2, 'Ignore due to Listen directive');
			}
			else
			{
				if ( preg_match("/^(\d+)$/", $port->_val, $m) )
				{
					$l['port'] = $m[1];
					$l['ip'] = '*';
					$l['namevh'] = 0;
					$listeners[] = $l;
				}
				else
					$port->setStatus( 3, 'Parse Error' );
			}
		}

		for ( $i = 0 ; $i < count( $entryList ) ; ++$i )
		{
			$entry = &$entryList[$i];
			if ( $entry->_status > 0 )
				continue;

			if ( $entry->processTag('NameVirtualHost') )
			{
				$l = array();
				if ( preg_match("/^(\d+)$/", $entry->_val, $m) )
				{
					$l['port'] = $m[1];
					$l['ip'] = '*';
				}
				else if ( preg_match("/^([\d\.]+):(\d+)$/", $entry->_val, $m) )
				{
					$l['ip'] = $m[1];
					$l['port'] = $m[2];
				}
				foreach ( $listeners as $n )
				{
					if ((($l['ip'] == '*')||($l['ip'] == $n['ip']))
					  &&(($l['port'] == '*')||($l['port'] == $n['port'])))
						$n['namevh'] = 1;
				}
			}
		}
		
		foreach ( $listeners as $l )
		{
			$addr = $l['ip'] . ':' . $l['port'];
			$l['name'] = $addr;
			$l['address'] = $addr;
			$this->_serv['listeners'][$addr] = $l;
		}
	}
}

class ApacheMigration
{
	var $_rawData;
	var $_apacheServerRoot;

	// input var
	var $_lswsServerRoot;
	var $apache_conf_path;

	function init( $apacheConfPath, $lswsServerRoot)
	{
		$this->_lswsServerRoot = $lswsServerRoot;
		$this->apache_conf_path = $apacheConfPath;
	}

	function renameOldFile( &$filename )
	{
		if ( is_file( $filename ) )
		{
			$i = 0;
			$newname = $filename . '.old';
			while( is_file( $newname ) )
			{
				$newname = $filename . '.old_'. $i;
				++$i;	
			}
			rename( $filename, $newname );
		}
	}

	function migrate()
	{
		$fileLoader = new FileLoader();
		$this->_rawData = &$fileLoader->load( $this->apache_conf_path );
		$this->_apacheServerRoot = $fileLoader->_apacheServerRoot;
		$fileLoader = NULL;

		$extractor = new Extractor($this->_apacheServerRoot, $this->_lswsServerRoot);
		$extractor->extract($this->_rawData);
		//var_dump($extractor);
		//var_dump($this->_rawData);
		$writer = new LSWSConf();
		$filename = $this->_lswsServerRoot."/conf/httpd_config.xml" ;
		$this->renameOldFile( $filename );
		$writer->saveServerFile( $extractor->_serv, $filename);
		$vhnames = array_keys($extractor->_vhosts);
		foreach ( $vhnames as $vhname )
		{
			$filename = $this->_lswsServerRoot."/conf/$vhname.xml";
			$this->renameOldFile( $filename );
			$writer->saveVhFile($extractor->_vhosts[$vhname], $filename );
		}


	}
}

if ( count($_SERVER['argv']) != 3 )
{
	echo "ApacheMigration.php: Importing apache configuration to the current lsws configuration file.\n";
	echo "Usage: php ApacheMigration.php apache_conf_file lsws_server_root \n";
	echo "\t apache_conf_file: absolute path to the main Apache configuration file\n";
	echo "\t lsws_server_root: LiteSpeed web server's Server Root\n\n";
	exit(1);
}

$migrate = new ApacheMigration();
$apachePath = $_SERVER['argv'][1];
$lswsSR = $_SERVER['argv'][2];

$migrate->init($apachePath, $lswsSR);
$migrate->migrate();

