<?php

class CVal
{
	var $_v = NULL; //value
	var $_e = NULL; //err
	function CVal($v, $e=NULL)
	{
		$this->_v = $v;
		$this->_e = $e;
	}
}

class ConfData
{
	var $_data = array();
	var $_path;
	var $_type; //{'serv','admin','vh','tp'}
	var $_id;

	function ConfData($type, $path, $id=NULL)
	{
		$this->_type = $type;
		$this->_path = $path;
		$this->_id = $id;
	}

	function setId($id)
	{
		$this->_id = $id;
	}

}

?>
