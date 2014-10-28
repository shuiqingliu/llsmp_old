<?

ob_start(8196); // just in case

header("Cache-Control: no-store, no-cache, private"); //HTTP/1.1
header("Expires: -1"); //ie busting
header("Pragma: no-cache");


//set auto include path...get rid of all path headaches
ini_set('include_path',
$_SERVER['LS_SERVER_ROOT'] . 'admin/html/classes/:' . 
$_SERVER['LS_SERVER_ROOT'] . 'admin/html/classes/' .$_SERVER['LS_PRODUCT'] . '/:' . 
$_SERVER['LS_SERVER_ROOT'] . 'admin/html/includes/:.');

function __autoload($class)
{
    include($class . '.php');

    // Check to see if the include declared the class
    if (!class_exists($class, false)) {
        trigger_error("Unable to load class: $class", E_USER_WARNING);
    }
}


?>
