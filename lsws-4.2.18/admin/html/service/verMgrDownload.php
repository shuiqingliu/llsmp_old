<h2>Version Management - Downloading New Release</h2>
<p class="xtbl_value"><a target=_new href="http://www.litespeedtech.com/products/webserver/changelog/">Release Notes</a>
	 </p>
<?
if ($act != 'download')
	return; //illegal entrance
?>

<div style='height:80px;'></div>

<center>
Downloading In-Progress, this may take a few minutes ...<br><br>
... Please wait ...
<img src='/static/images/working.gif' onload="vermgr_upgrade('upgrade','<?=$actId?>')">
</center>
<div style='height:30px;'></div>
