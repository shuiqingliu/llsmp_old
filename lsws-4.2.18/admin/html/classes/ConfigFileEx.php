<?php

require_once('PathTool.php');

class ConfigFileEx
{

	// grep logging.log.fileName, no need from root tag, any distinctive point will do
	function grepTagValue($filename, $tags)
	{
		$contents = file_get_contents($filename);
		if (is_array($tags))
		{
			$values = array();
			foreach ($tags as $tag)
			{
				$values[$tag] = ConfigFileEx::grepSingleTagValue($tag, $contents);
			}
			return $values;
		}
		else
		return ConfigFileEx::grepSingleTagValue($tags, $contents);
	}

	function grepSingleTagValue($tag, &$contents)
	{
		$singleTags = explode('.', $tag);
		$cur_pos = 0;
		$end_tag = '';
		foreach($singleTags as $singletag)
		{
			$findtag = '<'.$singletag.'>';
			$cur_pos = strpos($contents, $findtag, $cur_pos);
			if ($cur_pos === FALSE)
			break;
			$cur_pos += strlen($findtag);
			$end_tag = '</'.$singletag.'>';
		}
		if(!strlen($contents) || !strlen($end_tag)) {
			$last_pos = FALSE;
		}
		else {
			$last_pos = strpos($contents, $end_tag, $cur_pos);
		}
		if ( $last_pos !== FALSE)
		return substr($contents, $cur_pos, $last_pos - $cur_pos);
		else
		return null;
	}

	// other files
	function &loadMime($filename)
	{
		$lines = file($filename);
		if ( $lines == false )
		return false;

		$mime = array();
		foreach( $lines as $line )
		{
			$c = strpos($line, '=');
			if ( $c > 0 )
			{
				$suffix = trim(substr($line, 0, $c));
				$type = trim(substr($line, $c+1 ));
				$entry = array();
				$entry['suffix'] = new CVal($suffix);
				$entry['type'] = new CVal($type);
				$mime[$suffix] = $entry;
			}
		}
		ksort($mime, SORT_STRING);
		reset($mime);


		return $mime;
	}

	function saveMime($filename, &$mime)
	{
		$fd = fopen($filename, 'w');
		if ( !$fd )
		return false;
		ksort($mime, SORT_STRING);
		reset($mime);
		foreach( $mime as $key => $entry )
		{
			if ( strlen($key) < 8 )
			$key = substr($key . '        ', 0, 8);
			$line = "$key = " . $entry['type']->_v . "\n";
			fputs( $fd, $line );
		}
		fclose($fd);

		return true;
	}

	function &loadUserDB($filename)
	{
		if ( PathTool::isDenied($filename) ) {
			return false;
		}

		$lines = file($filename);
		$udb = array();
		if ( $lines == false )
		{
			error_log('failed to read from ' . $filename);
			return $udb;
		}

		foreach( $lines as $line )
		{
			$line = trim($line);

			$parsed = explode(":",$line);

			if(is_array($parsed)) {

				$size = count($parsed);

				if($size != 2 && $size !=3) {
					continue;
				}

				if(!strlen($parsed[0]) || !strlen($parsed[1])) {
					continue;
				}

				$user = array();

				if($size >= 2) {
					$user['name'] = new CVal(trim($parsed[0]));
					$user['passwd'] = new CVal(trim($parsed[1]));
				}

				if($size == 3 && strlen($parsed[2])) {
					$user['group'] = new CVal(trim($parsed[2]));
				}

				$udb[$user['name']->_v] = $user;
			}
		}

		ksort($udb);
		reset($udb);
		return $udb;
	}

	function saveUserDB($filename, &$udb)
	{
		if ( PathTool::isDenied($filename) ) {
			return false;
		}

		$fd = fopen($filename, 'w');
		if ( !$fd ) {
			return false;
		}

		ksort($udb);
		reset($udb);
		foreach( $udb as $name => $user ) {
			$pass = $user['passwd']->_v;

			$line = $name . ':' . $pass;
			if (isset($user['group']) ) {
				$grp = $user['group']->_v;
				$line .= ":$grp";
			}
			fputs( $fd, "$line\n" );
		}
		fclose($fd);
		return true;
	}

	function &loadGroupDB($filename)
	{
		if ( PathTool::isDenied($filename) ) {
			return false;
		}

		$gdb = array();
		$lines = file($filename);
		if ( $lines == false ) {
			return $gdb;
		}

		foreach( $lines as $line ) {
			$line = trim($line);

			$parsed = explode(":",$line);

			if(is_array($parsed) && count($parsed) == 2) {
				$group = array();
				$group['name'] = new CVal(trim($parsed[0]));
				$group['users'] = new CVal(trim($parsed[1]));
				$gdb[$group['name']->_v] = $group;

			}

		}
		ksort($gdb);
		reset($gdb);
		return $gdb;
	}

	function saveGroupDB($filename, &$gdb)
	{
		if ( PathTool::isDenied($filename) )
		return false;
		$fd = fopen($filename, 'w');
		if ( !$fd )
		return false;
		ksort($gdb);
		reset($gdb);
		foreach( $gdb as $name => $entry )
		{
			$line = $name . ':' . $entry['users']->_v . "\n";
			fputs( $fd, $line );
		}
		fclose($fd);
		return true;
	}

}

?>