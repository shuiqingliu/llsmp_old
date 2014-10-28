<?
require_once('../includes/global.php');
include_once('auth.php');

require_once('Service.php');
require_once('ConfCenter.php');

require_once("PRODUCT.php");
require_once("XUI.php");
require_once("GUI.php");
require_once("STATS.php");

$stats = STATS::singleton();
$stats->parse_all();
$blocked_count = count($stats->blocked_ip);

echo GUI::header();

?>
<table class="xtbl" width="100%" border="0" cellpadding="4" cellspacing="1">
<tr height="15"><td class="xtbl_title">Current Blocked IP List (Total <? echo $blocked_count; ?>)</tr>

<tr class="xtbl_value"><td>

<?
	echo join(', ', $stats->blocked_ip);
?>			

</td></tr>
</table>

<?

echo GUI::footer();

?>
