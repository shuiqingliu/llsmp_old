<?
require_once('../../includes/global.php');
include_once('auth.php');

include_once( 'buildconf.inc.php' );

global $_SESSION;
$progress_file = $_SESSION['progress_file'];
$log_file = $_SESSION['log_file'];

$progress = htmlspecialchars(file_get_contents($progress_file));
echo $progress;

echo "\n**LOG_DETAIL** retrieved from $log_file\n";
$log = htmlspecialchars(file_get_contents($log_file));
echo $log;

?>
