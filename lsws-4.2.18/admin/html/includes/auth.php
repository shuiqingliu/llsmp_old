<?

require_once('CLIENT.php');
require_once('DUtil.php');

$client = CLIENT::singleton();

if ( !$client->isValid() 
	|| (isset($_SERVER['HTTP_REFERER']) 
		&& strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === FALSE)) {
	$client->clear();
	header('location:/login.php');
	die();
}

?>