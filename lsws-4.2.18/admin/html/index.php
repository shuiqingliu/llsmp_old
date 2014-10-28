<?

require_once('includes/global.php');
include_once('includes/auth.php');

require_once('ConfCenter.php');
require_once('Service.php');
require_once('XUI.php');
require_once('GUI.php');
require_once('PRODUCT.php');
require_once('STATS.php');

$service = new Service();

$product = PRODUCT::singleton();
$product->getNewVersion();
$product->getInstalled();

echo GUI::header();
echo GUI::top_menu();

?>


<h2>Home</h2>

<?
if ( ($product->new_version != NULL) && $product->isInstalled($product->new_version) == FALSE )
{
	echo '<table class="xtbl" width="100%" border="0" cellpadding="5" cellspacing="1">';
	echo '<tr class=xtbl_header><td class=xtbl_title colspan=2>New Release Available</td></tr>';
	echo '<tr class=xtbl_value><td class=icon><img style="vertical-align:absmiddle;" src="/static/images/icons/up.gif"></td>';
	echo '<td>New release: ' . $product->new_release . ' is now <a href="/service/serviceMgr.php?vl=3">available</a>.</td>'."\n";
	echo '</td></tr>';
	echo '</table>'. "\n";
}

?>

<table width="100%" class=xtbl border="0" cellpadding="5" cellspacing="1">
<tr class=xtbl_header><td class=xtbl_title colspan=2>Main Areas</td></tr>
<tr>
	<td width=120 class="xtbl_label" valign=middle align=center>Service Manager<br><a href="/service/serviceMgr.php"><img src="/static/images/icons/controlpanel.gif"></a></td>
	<td valign=middle class="xtbl_value">Perform server restart, manage upgrades, check server status, view
	real-time statistics, and more.</td>
</tr>
<tr>
	<td width=120 class="xtbl_label" valign=middle align=center>Configuration<br><a href="/config/confMgr.php?m=serv"><img src="/static/images/icons/serverconfig.gif"></a></td>
	<td valign=middle class="xtbl_value">Configure LiteSpeed Web Server's settings.</td>
</tr>
<tr>
	<td width=120 class="xtbl_label" align=center>WebAdmin Console<br><a href="/config/confMgr.php?m=admin"><img src="/static/images/icons/adminconfig.gif"></a></td>
	<td valign=middle class="xtbl_value">Manage WebAdmin console settings.</td>
</tr>
</table>

<!-- <a href="javascript:showReport()"> -->



<?
$buf = array();
$res = $service->showErrLog($buf);



if ( $res !== 0  )
{
	echo "<table width=100% class=xtbl border=0 cellpadding=5 cellspacing=1>
<tr class=xtbl_header>
	<td class=xtbl_title colspan=2 nowrap>Found {$res} warning/error messages in the log:</td>
	<td class=xtbl_edit><a href='/service/serviceMgr.php?vl=1'>More</a></td>
</tr>
<tr class=xtbl_label>
	<td width='170'>Time</td>
	<td width='50'>Level</td>
	<td >Message</td>
</tr>
";

foreach($buf as $key => $entry) {
	echo $entry;
}

echo "</table>";

}



echo GUI::footer();

?>
