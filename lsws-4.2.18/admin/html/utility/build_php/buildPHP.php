<?

require_once('../../includes/global.php');
include_once('auth.php');

require_once('XUI.php');

require_once('Service.php');
require_once('ConfCenter.php');
require_once('GUI.php');
require_once('PRODUCT.php');
require_once('STATS.php');

include_once( 'buildconf.inc.php' );

$client = CLIENT::singleton();

if ($client->timeout == 0) {
	$confCenter = ConfCenter::singleton();//will set timeout
}


echo GUI::header();
echo GUI::top_menu();

$check = new BuildCheck();

switch($check->next_step) {
	
	case "1":
		include("buildStep1.php"); 
		break;
	case "2":
		include("buildStep2.php");
		break;
	case "3":
		include("buildStep3.php");
		break;
	case "4":
		include("buildStep4.php");
		break;
		
	case "0":
	default: // illegal
		echo "ERROR";
}

echo GUI::footer();

?>
