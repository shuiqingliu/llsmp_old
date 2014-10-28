<?php
require_once('XmlTreeBuilder.php'); 
require_once('PathTool.php');	

class LSWSConf
{
	function loadServConf($serverRoot)
	{
		$file = $serverRoot . "/conf/httpd_config.xml" ;

		$xmltree = new XmlTreeBuilder(); 
		$rootNode = &$xmltree->parseFile($file);

		if ( $rootNode == NULL )
			return NULL;

		return $this->extractServConf($rootNode);
	}


	function &extractServConf(&$rootNode)
	{
		$serv = array();
		$el = &$rootNode['ELEMENTS'];
		$generalTags = array( 'serverName', 'adminEmails', 'mime', 'autoRestart',
							  'chrootPath', 'enableChroot', 'inMemBufSize',
							  'swappingDir', 'user', 'group', 'adminRoot',
							  'indexFiles', 'showVersionNumber', 'priority');
		for( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			$el1 = &$el[$i]['ELEMENTS'];

			if ( in_array($name, $generalTags) )
			{
				$serv['general'][$name] = $el[$i]['VALUE'];
			}
			elseif ( $name == 'logging' )
			{
				$this->extractLogging($el1, $serv['general']);
			}
			elseif ( $name == 'htAccess' )
			{
				$this->extractHtAccess($el1, $serv['general']);
			}
			elseif ( $name == 'expires' )
			{
				$this->extractExpires($el1, $serv['general']);
			}
			elseif ( $name == 'tuning' )
			{
				$serv['tuning'] = &$this->extractServTuning($el1);
			}
			elseif ( $name == 'security' )
			{
				$serv['security'] = &$this->extractServSecurity($el1);
			}
			elseif ( $name == 'virtualHostList' )
			{
				$this->extractVHosts($el1, $serv);
			}
			elseif ( $name == 'listenerList' )
			{
				$this->extractListeners($el1, $serv);
			}
			elseif ( $name == 'extProcessorList' )
			{
				$serv['ext'] = &$this->extractExt($el[$i]['ELEMENTS']);
			}
			elseif ( $name == 'scriptHandlerList' )
			{
				$serv['scriptHandler'] = &$this->extractScriptHandler( $el[$i]['ELEMENTS']);
			}
		}
		return $serv;
	}


	function &extractServTuning(&$el)
	{
		$tuning = array();
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			$tunning[$name] = $el[$i]['VALUE'];
		}
		return $tunning;
	}

	function &extractScriptHandler(&$el)
	{
		$scriptHandler = array();
		$tags = array('suffix', 'type', 'handler');
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$el1 = &$el[$i]['ELEMENTS'];
			$sc = array();
			for ( $j = 0 ; $j < count($el1) ; ++ $j )
			{
				$name = $el1[$j]['TAG'];
				if ( in_array($name, $tags) )
					$sc[$name] = $el1[$j]['VALUE'];
			}
			if ( isset($sc['type']) && isset($sc['suffix']) )
			{
				$scriptHandler[$sc['suffix']] = $sc;
			}
		}
		return $scriptHandler;
	}

	function &extractExt(&$el)
	{
		$ext = array();
		$tags = array('address', 'name', 'type', 'maxConns', 'initTimeout',
					  'retryTimeout', 'respBuffer', 'autoStart', 
				  	  'backlog', 'path', 'instances', 'priority');
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$f = array();
			$el1 = &$el[$i]['ELEMENTS'];
			for ( $j = 0 ; $j < count($el1) ; ++$j )
			{
				$name = $el1[$j]['TAG'];
				if ( in_array($name, $tags) )
				{
					$f[$name] = $el1[$j]['VALUE'];
				}
				elseif ( $name == 'env' )
				{
					$f['env'][] = $el1[$j]['VALUE'];
				}
			}
			if ( isset($f['name']) )
				$ext[$f['name']] = &$f;
			unset($f);
		}
		return $ext;
	}

	function &extractServSecurity(&$el)
	{
		$sec = array();
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			if ( $name == 'fileAccessControl' )
			{
				$tags1 = array('followSymbolLink', 'checkSymbolLink', 
							  'requiredPermissionMask', 'restrictedPermissionMask' );
				$el1 = &$el[$i]['ELEMENTS'];
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					$name1 = $el1[$j]['TAG'];
					if ( in_array($name1, $tags1) )
					{
						$sec[$name.'_'.$name1] = $el1[$j]['VALUE'];
					}
				}
			}
			elseif ( $name == 'CGIRLimit' )
			{
				$el1 = &$el[$i]['ELEMENTS'];
				$tags1 = array('maxCGIInstances','minUID', 'minGID', 'priority', 
							   'CPUSoftLimit','CPUHardLimit','memSoftLimit',
							   'memHardLimit','procSoftLimit','procHardLimit');
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					$name1 = $el1[$j]['TAG'];
					if ( in_array($name1, $tags1) )
					{
						$sec[$name.'_'.$name1] = $el1[$j]['VALUE'];
					}
				}
			}
			elseif ( $name == 'perClientConnLimit' )
			{
				$el1 = &$el[$i]['ELEMENTS'];
				$tags1 = array('staticReqPerSec','dynReqPerSec', 'outBandwidth', 'inBandwidth', 'softLimit','hardLimit','gracePeriod','banPeriod');
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					$name1 = $el1[$j]['TAG'];
					if ( $name1 == 'throttleLimit' )
					{
						$sec['perClientConnLimit_outBandwidth'] = $el1[$j]['VALUE'];
						$sec['perClientConnLimit_inBandwidth'] = $el1[$j]['VALUE'];

					}
					else if ( in_array($name1, $tags1) )
						$sec[$name.'_'.$name1] = $el1[$j]['VALUE'];
				}
			}
			elseif ( $name == 'accessDenyDir' )
			{
				$el1 = &$el[$i]['ELEMENTS'];
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					if ( $el1[$j]['TAG'] == 'dir' )
						$sec[$name][] = $el1[$j]['VALUE'];
				}
			}
			elseif ( $name == 'accessControl' )
			{
				$this->extractAccessControl($el[$i]['ELEMENTS'], $sec);
			}
		}
		return $sec;
	}

	function extractAccessControl(&$el, &$sec)
	{
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			if ( $name == 'allow' )
				$sec['accessControl_allow'] = $el[$i]['VALUE'];
			elseif ( $name == 'deny' )
				$sec['accessControl_deny'] = $el[$i]['VALUE'];
		}
	}


	function &extractHtAccess(&$el, &$holder)
	{
		$tags = array('allowOverride', 'accessFileName');
		$this->extractData($el, $holder, $tags);
	}

	function &extractData(&$el, &$holder, &$tags)
	{
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			$value = $el[$i]['VALUE'];
			if ( in_array($name, $tags) )
				$holder[$name] = $value;
		}
	}
	
	function &extractExpires(&$el, &$holder)
	{
		$tags = array('enableExpires', 'expiresByType', 'expiresDefault');
		$this->extractData($el, $holder, $tags);
	}

	function extractLogging(&$el, &$holder)
	{
		$logTags = array('fileName', 'useServer', 'logLevel',
						 'debugLevel', 'rollingSize', 'enableStderrLog');
		$aclogTags = array('fileName', 'useServer', 'keepDays',
						   'rollingSize', 'logReferer', 'logUserAgent',
							'compressArchive');
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$name = $el[$i]['TAG'];
			if ( $name == 'log' )
			{
				$el1 = &$el[$i]['ELEMENTS'];
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					$name = $el1[$j]['TAG'];
					if ( in_array($name, $logTags) )
						$holder["log_$name"] = $el1[$j]['VALUE'];
				}
			}
			elseif ( $name == 'accessLog' )
			{
				$el1 = &$el[$i]['ELEMENTS'];
				for ( $j = 0 ; $j < count($el1) ; ++$j )
				{
					$name = $el1[$j]['TAG'];
					if ( in_array($name, $aclogTags) )
						$holder["accessLog_$name"] = $el1[$j]['VALUE'];
				}
			}
		}
	}

	function extractListeners(&$el, &$holder)
	{
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$l = array();

			$el1 = &$el[$i]['ELEMENTS'];
			for ( $j = 0 ; $j < count($el1) ; ++$j )
			{
				$name = $el1[$j]['TAG'];
				if ( $name == 'vhostMapList' )
				{
					$el2 = &$el1[$j]['ELEMENTS'];
					for ( $k = 0 ; $k < count($el2) ; ++$k )
					{
						$el3 = $el2[$k]['ELEMENTS'];
						for ( $a = 0 ; $a < count($el3) ; ++$a )
						{
							$name2 = $el3[$a]['TAG'];
							if ( $name2 == 'vhost' )
							{
								$vh = $el3[$a]['VALUE'];
							}
							elseif ( $name2 == 'domain' )
								$domain = $el3[$a]['VALUE'];
						}
						if ( isset($vh) && isset($domain) )
						{
							$l['vhmap'][$vh]['vh'] = $vh;
							$l['vhmap'][$vh]['domain'] = $domain;
						}
					}
				}
				else
					$l[$name] = $el1[$j]['VALUE'];

				if ( $name == 'address' )
				{
					$addr = $el1[$j]['VALUE'];
					$pos = strpos($addr,':');
					if ( $pos )
					{
						$l['ip'] = substr($addr, 0, $pos);
						$l['port'] = substr($addr, $pos+1);
						if ( $l['ip'] == '*' )
							$l['ip'] = 'ANY';
					}
				}
			}
			if ( isset($l['name']) )
			{
				$holder['listeners'][$l['name']] = &$l ;
			}

			unset($l);
		}
	}

	function extractVHosts(&$el, &$holder)
	{
		for ( $i = 0 ; $i < count($el) ; ++$i )
		{
			$vh = array();

			$el1 = &$el[$i]['ELEMENTS'];
			for ( $j = 0 ; $j < count($el1) ; ++$j )
			{
				$name = $el1[$j]['TAG'];
				if ( $name == 'name' )
					$vname = $el1[$j]['VALUE'];
				$vh[$name] = $el1[$j]['VALUE'];
			}
			if ( isset($vname) )
				$holder['vhTop'][$vname] = &$vh ;
			unset($vh);
		}
	}

	function saveVhFile(&$vh, $confFile)
	{
		$fd = fopen($confFile, 'w');
		if ( !$fd )
			return false;

		fputs( $fd, '<?xml version="1.0" encoding="UTF-8"?>'."\n");
		$level = 0;
		fputs( $fd, $this->xmlTagS( $level, 'virtualHostConfig' ));

		//general
		$g = &$vh['general'];
		////docRoot
		fputs( $fd, $this->xmlTag( $level, 'docRoot', $g['docRoot']));
		fputs( $fd, $this->xmlTag( $level, 'enableGzip', $g['enableGzip']));
		fputs( $fd, $this->xmlTag( $level, 'adminEmails', $g['adminEmails']));

		////logging
		fputs( $fd, $this->xmlTagS( $level, 'logging' ));
		if ( isset( $g['log_fileName'] ))
		{
			fputs( $fd, $this->xmlTagS($level, 'log' ));
			fputs( $fd, $this->xmlTag( $level, 'useServer', $g['log_useServer'] ));
			fputs( $fd, $this->xmlTag( $level, 'fileName', $g['log_fileName'] ));
			fputs( $fd, $this->xmlTag( $level, 'logLevel', $g['log_logLevel'] ));
			fputs( $fd, $this->xmlTag( $level, 'rollingSize', $g['log_rollingSize']));
			fputs( $fd, $this->xmlTagE( $level, 'log' ));
		}
		if ( isset( $g['accessLog_fileName'] ))
		{
			fputs($fd, $this->xmlTagS($level, 'accessLog' ));
			fputs( $fd, $this->xmlTag($level, 'useServer', $g['accessLog_useServer']));
			fputs( $fd, $this->xmlTag($level, 'fileName', $g['accessLog_fileName']));
			fputs( $fd, $this->xmlTag($level, 'logReferer', $g['accessLog_logReferer']));
			fputs( $fd, $this->xmlTag($level, 'logUserAgent', $g['accessLog_logUserAgent']));
			fputs( $fd, $this->xmlTag($level, 'rollingSize', $g['accessLog_rollingSize']));
			fputs( $fd, $this->xmlTag($level, 'keepDays', $g['accessLog_keepDays'] ));
			fputs( $fd, $this->xmlTag($level, 'compressArchive', $g['accessLog_compressArchive'] ));
			fputs($fd, $this->xmlTagE($level, 'accessLog' ));
		}
		fputs( $fd, $this->xmlTagE( $level, 'logging' ));

		////index files
		$this->writeSection( $fd, $level, $g['index'], 'index' );

		//htAccess
		if ( isset($g['allowOverride']) || isset($g['accessFileName']) )
		{ 
			fputs( $fd, $this->xmlTagS( $level, 'htAccess' ));
			fputs( $fd, $this->xmlTag( $level, 'allowOverride', $g['allowOverride'] ));
			fputs( $fd, $this->xmlTag( $level, 'accessFileName', $g['accessFileName'] ));
			fputs( $fd, $this->xmlTagE( $level, 'htAccess' ));
		}

		//expires
		fputs( $fd, $this->xmlTagS( $level, 'expires' ));
		fputs( $fd, $this->xmlTag(  $level, 'enableExpires', $g['enableExpires'] ));
		fputs( $fd, $this->xmlTag(  $level, 'expiresDefault', $g['expiresDefault'] ));
		fputs( $fd, $this->xmlTagE( $level, 'expires' ));

		//security
		if ( isset( $vh['security'] ))
		{
			$s = &$vh['security'];
			fputs( $fd, $this->xmlTagS( $level, 'security' ));
			fputs( $fd, $this->xmlTagS( $level, 'general' ));
			fputs($fd, $this->xmlTag( $level, 'enableContextAC', $s['general']['enableContextAC'] ));
			fputs( $fd, $this->xmlTagE( $level, 'general' ));
			//// hotlink
			$this->writeSection( $fd, $level, $s['hotlink'], 'hotlinkCtrl' );
			
			//// realmList
			$keys = $this->loadKeys($s['realm']);
			if ( $keys != NULL )
			{
				fputs( $fd, $this->xmlTagS( $level, 'realmList' ));
				sort( $keys );
				foreach ( $keys as $key )
				{
					$name = $key;
					$realm = $s['realm'][$key];
					fputs($fd, $this->xmlTagS($level, 'realm'));
					fputs($fd, $this->xmlTag( $level, 'name', $name ));
					fputs($fd, $this->xmlTag( $level, 'type', $realm['type']));
					fputs($fd, $this->xmlTagS($level, 'userDB'));
					fputs($fd, $this->xmlTag( $level, 'location', $realm['userDB_location']));
					if ( $realm['type'] == 'LDAP' )
					{
						fputs($fd, $this->xmlTag( $level, 'attrPasswd', $realm['userDB_attrPasswd']));
						fputs($fd, $this->xmlTag( $level, 'attrMemberOf', $realm['userDB_attrMemberOf']));
					}
					fputs($fd, $this->xmlTag( $level, 'maxCacheSize', $realm['userDB_maxCacheSize']));
					fputs($fd, $this->xmlTag( $level, 'cacheTimeout', $realm['userDB_cacheTimeout']));
					fputs($fd, $this->xmlTagE($level, 'userDB'));
					fputs($fd, $this->xmlTagS($level, 'groupDB'));
					fputs($fd, $this->xmlTag( $level, 'location', $realm['groupDB_location']));
					if ( $realm['type'] == 'LDAP' )
					{
						fputs($fd, $this->xmlTag( $level, 'attrGroupMember', $realm['groupDB_attrGroupMember']));
					}
					fputs($fd, $this->xmlTag( $level, 'maxCacheSize', $realm['groupDB_maxCacheSize']));
					fputs($fd, $this->xmlTag( $level, 'cacheTimeout', $realm['groupDB_cacheTimeout']));
					fputs($fd, $this->xmlTagE($level, 'groupDB'));
					if ( $realm['type'] == 'LDAP' )
					{
						fputs($fd, $this->xmlTag( $level, 'LDAPBindDN', $realm['LDAPBindDN'] ));
						fputs($fd, $this->xmlTag( $level, 'LDAPBindPasswd', $realm['LDAPBindPasswd'] ));
					}
					fputs($fd, $this->xmlTagE($level, 'realm'));
				}
				fputs( $fd, $this->xmlTagE( $level, 'realmList' ));
			}
		
		//// access control
			if ( $s['accessControl']['accessControl_allow'] != NULL 
				|| $s['accessControl']['accessControl_deny'] != NULL  )
			{
				fputs( $fd, $this->xmlTagS($level, 'accessControl' ));
				fputs( $fd, $this->xmlTag( $level, 'allow', $s['accessControl']['accessControl_allow']));
				fputs( $fd, $this->xmlTag( $level, 'deny', $s['accessControl']['accessControl_deny']));
				fputs( $fd, $this->xmlTagE($level, 'accessControl' ));
			}
			fputs( $fd, $this->xmlTagE( $level, 'security' ));
		}

		//ext
		if ( isset($vh['ext']) && count($vh['ext']) > 0 )
		{
			fputs( $fd, $this->xmlTagS( $level, 'extProcessorList' ));
			$ext0 = &$vh['ext'];
			foreach( $ext0 as $a )
			{
				$id = $a['type'] . $a['name'];
				$ext[$id] = $a;
			}
			ksort($ext);
			reset($ext);

			foreach( $ext as $f )
			{
				fputs( $fd, $this->xmlTagS( $level, 'extProcessor' ));
				fputs( $fd, $this->xmlTag(  $level, 'name', $f['name'] ));
				fputs( $fd, $this->xmlTag(  $level, 'address', $f['address'] ));
				fputs( $fd, $this->xmlTag(  $level, 'type', $f['type'] ));
				fputs( $fd, $this->xmlTag(  $level, 'maxConns', $f['maxConns'] ));
				fputs( $fd, $this->xmlTag(  $level, 'initTimeout', $f['initTimeout'] ));
				fputs( $fd, $this->xmlTag(  $level, 'retryTimeout', $f['retryTimeout'] ));
				fputs( $fd, $this->xmlTag(  $level, 'respBuffer', $f['respBuffer'] ));
				if ( isset( $f['env'] ) )
				{
					foreach( $f['env'] as $value )
					{
						fputs( $fd, $this->xmlTag(  $level, 'env', $value ));
					}
				}
				if ( $f['type'] == 'fcgi' || $f['type'] == 'fcgiauth' )
				{
					fputs( $fd, $this->xmlTag(  $level, 'autoStart', $f['autoStart'] ));
					fputs( $fd, $this->xmlTag(  $level, 'path', $f['path'] ));
					fputs( $fd, $this->xmlTag(  $level, 'backlog', $f['backlog'] ));
					fputs( $fd, $this->xmlTag(  $level, 'instances', $f['instances'] ));
					fputs( $fd, $this->xmlTag(  $level, 'priority', $f['priority'] ));
				}
				fputs( $fd, $this->xmlTagE( $level, 'extProcessor' ));
			}
			fputs( $fd, $this->xmlTagE( $level, 'extProcessorList' ));
		}

		//contexts
		$htypes = $this->loadKeys($vh['context']);
		if ( $htypes != NULL )
		{
			fputs( $fd, $this->xmlTagS( $level, 'contextList' ));
			foreach( $htypes as $htype )
			{
				$ct = &$vh['context'][$htype];
				foreach ( $ct as $ctx )
				{
					fputs( $fd, $this->xmlTagS($level, 'context' ));

					foreach ($ctx as $key=>$val)
					{
						if ( $val !== '' 
							 && strncmp($key, 'accessControl', 13) != 0
							 && strncmp( $key, 'rewrite', 7) != 0
							 && ($key != 'authorizer') )
							fputs( $fd, $this->xmlTag( $level, $key, $val ));		
					}
					//// access control
					if ( $ctx['accessControl_allow'] != NULL || $ctx['accessControl_deny'] != NULL  )
					{
						fputs( $fd, $this->xmlTagS($level, 'accessControl' ));
						fputs( $fd, $this->xmlTag( $level, 'allow', $ctx['accessControl_allow']));
						fputs( $fd, $this->xmlTag( $level, 'deny', $ctx['accessControl_deny']));
						fputs( $fd, $this->xmlTagE($level, 'accessControl' ));
					}
					if ( $ctx['authorizer'] != NULL )
					{
						fputs( $fd, $this->xmlTagS($level, 'extAuthorizer'));
						fputs( $fd, $this->xmlTag( $level, 'type', 'fcgiauth'));
						fputs( $fd, $this->xmlTag( $level, 'handler', $ctx['authorizer']));
						fputs( $fd, $this->xmlTagE($level, 'extAuthorizer'));
					}
					if ( $ctx['rewrite_enable'] != NULL || $ctx['rewrite_inherit'] != NULL
						 || $ctx['rewrite_base'] != NULL || $ctx['rewrite_rules'] != NULL )
					{
						fputs( $fd, $this->xmlTagS($level, 'rewrite' ));
						fputs( $fd, $this->xmlTag( $level, 'enable', $ctx['rewrite_enable']));
						fputs( $fd, $this->xmlTag( $level, 'inherit', $ctx['rewrite_inherit']));
						fputs( $fd, $this->xmlTag( $level, 'base', $ctx['rewrite_base']));
						fputs( $fd, $this->xmlTag( $level, 'rules', $ctx['rewrite_rules']));
						fputs( $fd, $this->xmlTagE($level, 'rewrite' ));
					}

					fputs( $fd, $this->xmlTagE( $level, 'context' ));
				}
			}
			fputs( $fd, $this->xmlTagE( $level, 'contextList' ));
		}

		//script handler
		fputs( $fd, $this->xmlTagS( $level, 'scriptHandlerList' ));
		$keys = $this->loadKeys($vh['scriptHandler']);
		if ( $keys != NULL )
		{
			$sc = &$vh['scriptHandler'];
			sort( $keys );
			foreach( $keys as $key )
			{
				fputs( $fd, $this->xmlTagS( $level, 'scriptHandler' ));
				fputs( $fd, $this->xmlTag(  $level, 'suffix', $sc[$key]['suffix'] ));
				fputs( $fd, $this->xmlTag(  $level, 'type', $sc[$key]['type'] ));
				if ( isset( $sc[$key]['handler'] ))
					fputs( $fd, $this->xmlTag(  $level, 'handler', $sc[$key]['handler'] ));
				fputs( $fd, $this->xmlTagE( $level, 'scriptHandler' ));
			}
		}
		fputs( $fd, $this->xmlTagE( $level, 'scriptHandlerList' ));

		//error pages
		$keys = $this->loadKeys($vh['errUrl']);
		if ( $keys != NULL )
		{
			fputs( $fd, $this->xmlTagS( $level, 'customErrorPages' ));
			sort( $keys );
			foreach( $keys as $key )
			{
				fputs( $fd, $this->xmlTagS( $level, 'errorPage' ));
				fputs( $fd, $this->xmlTag(  $level, 'errCode', $key ));
				fputs( $fd, $this->xmlTag(  $level, 'url', $vh['errUrl'][$key]['url'] ));
				fputs( $fd, $this->xmlTagE( $level, 'errorPage' ));
			}
			fputs( $fd, $this->xmlTagE( $level, 'customErrorPages' ));
		}

		//rewrite
		fputs( $fd, $this->xmlTagS( $level, 'rewrite' ));
		if ( isset($vh['rewrite']['ctrl']) )
		{
			fputs( $fd, $this->xmlTag( $level, 'enable', $vh['rewrite']['ctrl']['enable'] ));
			fputs( $fd, $this->xmlTag( $level, 'logLevel', $vh['rewrite']['ctrl']['logLevel'] ));
		}
		$keys = $this->loadKeys($vh['rewrite']['map']);
		if ( $keys != NULL )
		{
			sort( $keys );
			foreach( $keys as $key )
			{
				fputs( $fd, $this->xmlTagS( $level, 'map' ));
				fputs( $fd, $this->xmlTag(  $level, 'name', $key ));
				fputs( $fd, $this->xmlTag(  $level, 'location', $vh['rewrite']['map'][$key]['location'] ));
				fputs( $fd, $this->xmlTagE( $level, 'map' ));
			}
		}
		fputs( $fd, $this->xmlTag( $level, 'rules', $vh['rewrite']['rules'] ));
		fputs( $fd, $this->xmlTagE( $level, 'rewrite' ));

		//frontPage
		$this->writeSection( $fd, $level, $vh['addons']['frontPage'], 'frontPage' );

		//AWStats
		$this->writeSection( $fd, $level, $vh['addons']['awstats'], 'awstats' );

		fputs( $fd, $this->xmlTagE( $level, 'virtualHostConfig' ));
		fclose($fd);
		$this->_isChanged = true;
		clearstatcache();
		$this->_modTime['vh'][$vhname]['file'] = $confFile;
		$this->_modTime['vh'][$vhname]['time'] = filemtime($confFile);

		return true;
	}

	function saveServerFile(&$serv, $filename)
	{
		$fd = fopen($filename, 'w');
		if ( !$fd ) 
			return false;
		fputs( $fd, '<?xml version="1.0" encoding="UTF-8"?>'."\n");
		$level = 0;
		fputs( $fd, $this->xmlTagS( $level, 'httpServerConfig' ));

		$g = &$serv['general'];
		fputs( $fd, $this->xmlTag( $level, 'serverName', $g['serverName']));
		fputs( $fd, $this->xmlTag( $level, 'user', $g['user']));
		fputs( $fd, $this->xmlTag( $level, 'group', $g['group']));
		fputs( $fd, $this->xmlTag( $level, 'priority', $g['priority']));
		fputs( $fd, $this->xmlTag( $level, 'chrootPath', $g['chrootPath']));
		fputs( $fd, $this->xmlTag( $level, 'enableChroot', $g['enableChroot']));
		fputs( $fd, $this->xmlTag( $level, 'inMemBufSize', $g['inMemBufSize']));
		fputs( $fd, $this->xmlTag( $level, 'swappingDir', $g['swappingDir']));
		fputs( $fd, $this->xmlTag( $level, 'autoRestart', $g['autoRestart']));
		fputs( $fd, $this->xmlTag( $level, 'adminRoot', $g['adminRoot']));

		fputs( $fd, $this->xmlTag( $level, 'mime', $g['mime']));
		if ( isset($g['indexFiles']) )
			fputs( $fd, $this->xmlTag( $level, 'indexFiles', $g['indexFiles'] ));
		fputs( $fd, $this->xmlTag( $level, 'showVersionNumber', $g['showVersionNumber']));
		fputs( $fd, $this->xmlTag( $level, 'adminEmails', $g['adminEmails']));

		// logging
		fputs( $fd, $this->xmlTagS( $level, 'logging' ));
		fputs( $fd, $this->xmlTagS( $level, 'log' ));
		fputs( $fd, $this->xmlTag(  $level, 'fileName', $g['log_fileName'] ));
		fputs( $fd, $this->xmlTag(  $level, 'logLevel', $g['log_logLevel'] ));
		fputs( $fd, $this->xmlTag(  $level, 'debugLevel', $g['log_debugLevel'] ));
		fputs( $fd, $this->xmlTag(  $level, 'rollingSize', $g['log_rollingSize']));
		fputs( $fd, $this->xmlTag(  $level, 'enableStderrLog', $g['log_enableStderrLog']));
		fputs( $fd, $this->xmlTagE( $level, 'log' ));
		fputs( $fd, $this->xmlTagS( $level, 'accessLog' ));
		fputs( $fd, $this->xmlTag(  $level, 'fileName', $g['accessLog_fileName']));
		fputs( $fd, $this->xmlTag(  $level, 'logReferer', $g['accessLog_logReferer']));
		fputs( $fd, $this->xmlTag(  $level, 'logUserAgent', $g['accessLog_logUserAgent']));
		fputs( $fd, $this->xmlTag(  $level, 'rollingSize', $g['accessLog_rollingSize']));
		fputs( $fd, $this->xmlTag(  $level, 'keepDays', $g['accessLog_keepDays'] ));
		fputs( $fd, $this->xmlTag(  $level, 'compressArchive', $g['accessLog_compressArchive'] ));
		fputs( $fd, $this->xmlTagE( $level, 'accessLog' ));
		fputs( $fd, $this->xmlTagE( $level, 'logging' ));


		//htAccess
		if ( isset($g['allowOverride']) || isset($g['accessFileName']) )
		{ 
			fputs( $fd, $this->xmlTagS( $level, 'htAccess' ));
			fputs( $fd, $this->xmlTag( $level, 'allowOverride', $g['allowOverride'] ));
			fputs( $fd, $this->xmlTag( $level, 'accessFileName', $g['accessFileName'] ));
			fputs( $fd, $this->xmlTagE( $level, 'htAccess' ));
		}

		//expires
		fputs( $fd, $this->xmlTagS( $level, 'expires' ));
		fputs( $fd, $this->xmlTag(  $level, 'enableExpires', $g['enableExpires'] ));
		fputs( $fd, $this->xmlTag(  $level, 'expiresDefault', $g['expiresDefault'] ));
		fputs( $fd, $this->xmlTag(  $level, 'expiresByType', $g['expiresByType'] ));
		fputs( $fd, $this->xmlTagE( $level, 'expires' ));

		//tuning
		$this->writeSection( $fd, $level, $serv['tuning'], 'tuning' );

		//security
		$s = &$serv['security'];
		fputs( $fd, $this->xmlTagS( $level, 'security' ));

		fputs( $fd, $this->xmlTagS( $level, 'fileAccessControl' ));
		fputs( $fd, $this->xmlTag(  $level, 'followSymbolLink', $s['fileAccessControl_followSymbolLink'] ));
		fputs( $fd, $this->xmlTag(  $level, 'checkSymbolLink', $s['fileAccessControl_checkSymbolLink'] ));
		fputs( $fd, $this->xmlTag(  $level, 'requiredPermissionMask', $s['fileAccessControl_requiredPermissionMask'] ));
		fputs( $fd, $this->xmlTag(  $level, 'restrictedPermissionMask', $s['fileAccessControl_restrictedPermissionMask'] ));
		fputs( $fd, $this->xmlTagE( $level, 'fileAccessControl' ));

		fputs( $fd, $this->xmlTagS( $level, 'CGIRLimit' ));
		fputs( $fd, $this->xmlTag(  $level, 'maxCGIInstances', $s['CGIRLimit_maxCGIInstances'] ));
		fputs( $fd, $this->xmlTag(  $level, 'minUID', $s['CGIRLimit_minUID'] ));
		fputs( $fd, $this->xmlTag(  $level, 'minGID', $s['CGIRLimit_minGID'] ));
		fputs( $fd, $this->xmlTag(  $level, 'priority', $s['CGIRLimit_priority'] ));
		fputs( $fd, $this->xmlTag(  $level, 'CPUSoftLimit', $s['CGIRLimit_CPUSoftLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'CPUHardLimit', $s['CGIRLimit_CPUHardLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'memSoftLimit', $s['CGIRLimit_memSoftLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'memHardLimit', $s['CGIRLimit_memHardLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'procSoftLimit', $s['CGIRLimit_procSoftLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'procHardLimit', $s['CGIRLimit_procHardLimit'] ));
		fputs( $fd, $this->xmlTagE( $level, 'CGIRLimit' ));

		fputs( $fd, $this->xmlTagS( $level, 'perClientConnLimit' ));
		fputs( $fd, $this->xmlTag(  $level, 'staticReqPerSec', $s['perClientConnLimit_staticReqPerSec'] ));
		fputs( $fd, $this->xmlTag(  $level, 'dynReqPerSec', $s['perClientConnLimit_dynReqPerSec'] ));
		fputs( $fd, $this->xmlTag(  $level, 'outBandwidth', $s['perClientConnLimit_outBandwidth'] ));
		fputs( $fd, $this->xmlTag(  $level, 'inBandwidth', $s['perClientConnLimit_inBandwidth'] ));
		fputs( $fd, $this->xmlTag(  $level, 'softLimit', $s['perClientConnLimit_softLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'hardLimit', $s['perClientConnLimit_hardLimit'] ));
		fputs( $fd, $this->xmlTag(  $level, 'gracePeriod', $s['perClientConnLimit_gracePeriod'] ));
		fputs( $fd, $this->xmlTag(  $level, 'banPeriod', $s['perClientConnLimit_banPeriod'] ));
		fputs( $fd, $this->xmlTagE( $level, 'perClientConnLimit' ));

		fputs( $fd, $this->xmlTagS( $level, 'accessDenyDir' ));
		if ( isset($s['accessDenyDir']) )
		{
			foreach( $s['accessDenyDir'] as $value )
			{
				fputs( $fd, $this->xmlTag( $level, 'dir', $value ));
			}
		}
		fputs( $fd, $this->xmlTagE($level, 'accessDenyDir' ));
		fputs( $fd, $this->xmlTagS($level, 'accessControl' ));
		if ( isset($s['accessControl_allow']) )
			fputs( $fd, $this->xmlTag( $level, 'allow', $s['accessControl_allow']));
		if ( isset($s['accessControl_deny']))
			fputs( $fd, $this->xmlTag( $level, 'deny', $s['accessControl_deny']));
		fputs( $fd, $this->xmlTagE($level, 'accessControl' ));
		fputs( $fd, $this->xmlTagE($level, 'security' ));

		//ext
		fputs( $fd, $this->xmlTagS( $level, 'extProcessorList' ));
		if ( isset($serv['ext']) && count($serv['ext']) > 0)
		{
			$ext0 = &$serv['ext'];
			$ext = array();
			foreach( $ext0 as $a )
			{
				$id = $a['type'] . $a['name'];
				$ext[$id] = $a;
			}
			ksort($ext);
			reset($ext);

			foreach( $ext as $f )
			{
				fputs( $fd, $this->xmlTagS( $level, 'extProcessor' ));
				fputs( $fd, $this->xmlTag(  $level, 'name', $f['name'] ));
				fputs( $fd, $this->xmlTag(  $level, 'address', $f['address'] ));
				fputs( $fd, $this->xmlTag(  $level, 'type', $f['type'] ));
				fputs( $fd, $this->xmlTag(  $level, 'maxConns', $f['maxConns'] ));
				fputs( $fd, $this->xmlTag(  $level, 'initTimeout', $f['initTimeout'] ));
				fputs( $fd, $this->xmlTag(  $level, 'retryTimeout', $f['retryTimeout'] ));
				fputs( $fd, $this->xmlTag(  $level, 'respBuffer', $f['respBuffer'] ));
				if ( isset( $f['env'] ) )
				{
					foreach( $f['env'] as $value )
					{
						fputs( $fd, $this->xmlTag(  $level, 'env', $value ));
					}
				}
				if ( $f['type'] == 'fcgi' || $f['type'] == 'fcgiauth' || $f['type'] == 'lsapi' || $f['type'] == 'logger' )
				{
					fputs( $fd, $this->xmlTag(  $level, 'autoStart', $f['autoStart'] ));
					fputs( $fd, $this->xmlTag(  $level, 'path', $f['path'] ));
					fputs( $fd, $this->xmlTag(  $level, 'backlog', $f['backlog'] ));
					fputs( $fd, $this->xmlTag(  $level, 'instances', $f['instances'] ));
					fputs( $fd, $this->xmlTag(  $level, 'priority', $f['priority'] ));
				}
				fputs( $fd, $this->xmlTagE( $level, 'extProcessor' ));
			}
		}
		fputs( $fd, $this->xmlTagE( $level, 'extProcessorList' ));

		//script handler
		fputs( $fd, $this->xmlTagS( $level, 'scriptHandlerList' ));
		$keys = $this->loadKeys($serv['scriptHandler']);
		if ( $keys != NULL )
		{
			$sc = &$serv['scriptHandler'];
			sort( $keys );
			foreach( $keys as $key )
			{
				fputs( $fd, $this->xmlTagS( $level, 'scriptHandler' ));
				fputs( $fd, $this->xmlTag(  $level, 'suffix', $sc[$key]['suffix'] ));
				fputs( $fd, $this->xmlTag(  $level, 'type', $sc[$key]['type'] ));
				if ( isset( $sc[$key]['handler'] ))
					fputs( $fd, $this->xmlTag(  $level, 'handler', $sc[$key]['handler'] ));
				fputs( $fd, $this->xmlTagE( $level, 'scriptHandler' ));
			}
		}
		fputs( $fd, $this->xmlTagE( $level, 'scriptHandlerList' ));

		//listner
		fputs( $fd, $this->xmlTagS( $level, 'listenerList' ));
		$lnames = array_keys($serv['listeners']);
		sort($lnames);
		reset($lnames);
		foreach( $lnames as $ln )
		{
			$l = &$serv['listeners'][$ln];
			fputs( $fd, $this->xmlTagS( $level, 'listener' ));
			fputs( $fd, $this->xmlTag(  $level, 'name', $ln ));
			$ip = $l['ip'];
			if ( $ip == 'ANY' )
				$ip = '*';
			$addr = $ip . ':' . $l['port'];
			fputs( $fd, $this->xmlTag(  $level, 'address', $addr ));
			fputs( $fd, $this->xmlTag(  $level, 'binding', $l['binding'] ));
			fputs( $fd, $this->xmlTag(  $level, 'secure', $l['secure'] ));
			fputs( $fd, $this->xmlTag( $level, 'certFile', $l['certFile'] ));
			fputs( $fd, $this->xmlTag( $level, 'keyFile', $l['keyFile'] ));
			fputs( $fd, $this->xmlTag( $level, 'ciphers', $l['ciphers'] ));
			fputs( $fd, $this->xmlTagS( $level, 'vhostMapList' ));
			$keys = $this->loadKeys($l['vhmap']);
			if ( $keys != NULL )
			{
				sort( $keys );
				foreach ( $keys as $key )
				{
					fputs( $fd, $this->xmlTagS($level, 'vhostMap' ));
					fputs( $fd, $this->xmlTag( $level, 'vhost', $key ));
					fputs( $fd, $this->xmlTag( $level, 'domain', $l['vhmap'][$key]['domain'] ));
					fputs( $fd, $this->xmlTagE($level, 'vhostMap' ));
				}
			}
			fputs( $fd, $this->xmlTagE( $level, 'vhostMapList' ));
			fputs( $fd, $this->xmlTagE( $level, 'listener' ));
		}
		fputs( $fd, $this->xmlTagE( $level, 'listenerList' ));

		//vh
		fputs( $fd, $this->xmlTagS( $level, 'virtualHostList' ));
		$hnames = array_keys($serv['vhTop']);
		sort( $hnames);
		foreach( $hnames as $hn )
		{
			if ( $hn )
			{
				$vh = &$serv['vhTop'][$hn];
				if ( substr($vh['vhRoot'],-1) != '/' )
					$vh['vhRoot'] .= '/';
				$this->writeSection( $fd, $level, $vh, 'virtualHost' );
			}
		}
		fputs( $fd, $this->xmlTagE( $level, 'virtualHostList' ));

		fputs( $fd, $this->xmlTagE( $level, 'httpServerConfig' ));
		fclose($fd);

		return true;
	}

	function &loadKeys( &$list )
	{
		if ( isset($list) && count($list) > 0 )
			return array_keys($list);
		else
			return NULL;
	}

	function writeSection( &$fd, &$level, &$holder, $sectionName )
	{
		if ( !isset( $holder ) )
			return;
		fputs( $fd, $this->xmlTagS( $level,  $sectionName ));
		foreach( $holder as $key=>$val )
		{
			fputs( $fd, $this->xmlTag(  $level, $key, $val ));
		}
		fputs( $fd, $this->xmlTagE( $level, $sectionName ));
	}

	function xmlTag($level, $tag, $value)
	{
		if ( is_array($value) )
		{		echo "tag=$tag\n";
		var_dump($value);

		}
		$val = htmlspecialchars($value);
		return str_repeat('  ', $level) . "<$tag>$val</$tag>\n";
	}

	function xmlTagS(&$level, $tag)
	{
		return str_repeat('  ', $level++) . "<$tag>\n";
	}

	function xmlTagE(&$level, $tag)
	{
		return str_repeat('  ', --$level) . "</$tag>\n";
	}


	function dbg_out($tag, &$obj)
	{
		echo "<!-- $tag --\n";
		var_dump($obj);
		echo "-->\n";
	}
	function dbg_tag($tag)
	{
		echo "<!-- $tag -->\n";
	}


}
	
?>