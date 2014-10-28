<?php
class DATTR_HELP_ITEM
{
	var $name;
	var $desc;
	var $tips; 
	var $syntax;
	
	function DATTR_HELP_ITEM($name, $desc, $tips = NULL, $syntax = NULL) {
		
		$this->name = $name;
		$this->desc = $desc;
		$this->tips = $tips;
		$this->syntax = $syntax;
	}
	

	function render($key, $blocked_version=0) 
	{ 
		$key = str_replace(array(':','_'), '', $key );
		$buf = '<img class="xtip-hover-' . $key . '" src="/static/images/icons/info.gif">' 
			. '<div id="xtip-note-' . $key . '" class="snp-mouseoffset notedefault"><b>' 
			. $this->name
			. '</b>' ;
		switch ($blocked_version) {
			case 0: break; 
			case 1:  // LSWS ENTERPRISE; 
				$buf .= ' <i>This feature is available in Enterprise Edition</i>';
				break;
			case 2: // LSWS 2CPU +
			case 3: // LSLB 2CPU +
				$buf .= ' <i>This feature is available for Multiple-CPU license</i>';
				break;
		}
		$buf .= '<hr size=1 color=black>'
			. $this->desc
			. '<br><br>';
		if ($this->syntax) {
			$buf .= 'Syntax: ' 
				. $this->syntax
				. '<br><br>';
		}
		if ($this->example) {
			$buf .= 'Example: ' 
				. $this->example
				. '<br><br>';
		}
		if ($this->tips) {
			$buf .= 'Tip(s):<ul type=circle>';
			if (strpos($this->tips, '<br>') !== FALSE) {
				$tips = split('<br>', $this->tips);
				foreach($tips as $ti) {
					$ti = trim($ti);
					if ($ti != '')
						$buf .= '<li>' . $ti . '</li>';	
				}
			}
			else {
				$buf .= '<li>' . $this->tips . '</li>';
			}
			$buf .= '</ul>';
		}

		$buf .= '</div>';
		return $buf;
	}
	
}

?>