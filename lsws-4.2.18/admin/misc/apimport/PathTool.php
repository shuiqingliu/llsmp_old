<?php

class PathTool
{
	function getAbsolutePath($root, $path)
	{
		if ( $path{-1} != '/' )
			$path .= '/';

		$newPath = $this->getAbsoluteFile($root, $path);
		return $newPath;
	}

	function getAbsoluteFile($root, $path)
	{
		if ( $path{0} != '/' )
			$path = $root . '/' . $path;

		$newPath = $this->clean($path);
		return $newPath;
	}

	function hasSymbolLink($path)
	{
		if ( $path != realpath($path) )
			return true;
		else
			return false;
	}

	function clean($path)
	{
		do {
			$newS1 = $path;
			$newS = str_replace('//', '/',  $path);
			$path = $newS;
		} while ( $newS != $newS1 );
		do {
			$newS1 = $path;
			$newS = str_replace('/./', '/',  $path);
			$path = $newS;
		} while ( $newS != $newS1 );
		do {
			$newS1 = $path;
			$newS = ereg_replace('/[^/]+/\.\./', '/',  $path);
			$path = $newS;
		} while ( $newS != $newS1 );

		return $path;
	}

	function createFile($path, &$err)
	{
		if ( file_exists($path) )
		{
			if ( is_file($path) )
			{
				$err = 'Already exists';
				return false;
			}
			else
			{
				$err = 'name conflicting with an existing dirtory';
				return false;
			}
		}
		$dir = substr($path, 0, (strrpos($path, '/')));
		if ( PathTool::createDir($dir, 0700, $err) )
		{
			if ( touch($path) )
			{
				chmod($path, 0600);
				return true;
			}
			else
				$err = 'failed to create file '. $path;
		}

		return false;
	}

	function createDir($path, $mode, &$err)
	{
		if ( file_exists($path) )
		{
			if ( is_dir($path) )
				return true;
			else
			{
				$err = "$path is not a directory";
				return false;
			}
		}
		$parent = substr($path, 0, (strrpos($path, '/')));
		if ( strlen($parent) <= 1 )
		{
			$err = "invalid path: $path";
			return false;
		}
		if ( !file_exists($parent) 
			 && !PathTool::createDir($parent, $mode, $err) )
			return false;

		if ( mkdir($path, $mode) )
			return true;
		else
		{
			$err = "fail to create directory $path";
			return false;
		}
		
	}


}

?>