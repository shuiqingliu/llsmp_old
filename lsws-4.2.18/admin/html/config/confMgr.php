<?

require_once('../includes/global.php');
include_once('auth.php');

require_once('GUI.php');
require_once('PRODUCT.php');
require_once('XUI.php');
require_once('ConfCenter.php');
require_once('DATTR_HELP.php');


$confCenter = &ConfCenter::singleton();

$disp = &$confCenter->getDispInfo();
$pageDef = &DPageDef::getInstance();
$page = $pageDef->getPageDef($disp->_type, $disp->_pid);
$pageData = &$confCenter->getPageData($disp, $confErr = NULL);

require_once('confHeader.php');

if ( $confErr == NULL ) {
	$page->printHtml($pageData, $disp);
}
else {
	echo '<div class="message_error">' . $confErr . '</div>';
}

require_once('confFooter.php');

?>
