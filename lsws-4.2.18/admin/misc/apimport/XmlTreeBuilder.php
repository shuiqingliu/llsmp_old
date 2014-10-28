<?php

class XmlTreeBuilder
{ 
	var $parser; 
	var $node_stack;

	function &parseFile($filename)
	{
		if ( !is_file($filename) )
			return NULL;
		$fd = fopen($filename, 'r');
		if ( !$fd )
			return NULL;
		
		$contents = fread($fd, filesize($filename)); 
		fclose($fd); 
	
		$root = &$this->parseString($contents); 
		return $root;
	}

	function &parseString(&$xmlstring)
	{ 
		$this->parser = xml_parser_create(); 
		xml_set_object($this->parser, $this); 
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false); 
		xml_set_element_handler($this->parser, 'startElement', 'endElement'); 
		xml_set_character_data_handler($this->parser, 'characterData'); 
		
		$this->node_stack = array(); 
		$this->startElement(null, 'root', array()); 
		
		xml_parse($this->parser, $xmlstring); 
		xml_parser_free($this->parser); 
		
		$rnode = array_pop($this->node_stack); 
		
		return($rnode['ELEMENTS'][0]); 
	} 

	function startElement($parser, $name, $attrs) 
	{ 
		$node = array(); 
		$node['TAG'] = $name;
		
		foreach ($attrs as $key => $value) 
		{ 
			$node[$key] = $value; 
		} 
		
		$node['VALUE'] = ''; 
		$node['ELEMENTS'] = array(); 
		
		array_push($this->node_stack, $node); 
	} 
	
	function endElement($parser, $name) 
	{ 
		$node = array_pop($this->node_stack); 
		$node['VALUE'] = trim($node['VALUE']); 
		
		$lastnode = count($this->node_stack); 
		array_push($this->node_stack[$lastnode-1]['ELEMENTS'], $node); 
	} 
	
	function characterData($parser, $data) 
	{ 
		$lastnode = count($this->node_stack); 
		$this->node_stack[$lastnode-1]['VALUE'] .= $data; 
	}
 

} 

?>