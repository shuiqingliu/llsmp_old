<?php

/****
 * purge_cache_byurl
 *
 * Example: /usr/local/lsws/admin/fcgi-bin/admin_php /usr/local/lsws/admin/misc/purge_cache_byurl.php -r mywebsite.com /index.php
 */

if ($argc < 4 || $argc > 6) {
    echo "Invalid arguments!\n";
    echo  "Usage: php $argv[0] -(r|p) domain url [server_ip] [port]
    -r method option: Refresh cache (use stale cache while updating cache)
    -p method option: Purge cache (delete cache entry)
	domain: required parameter for domain name 
	url: required parameter for url
	server_ip: optional parameter, default is 127.0.0.1
	server_port: optional parameter, default is 80
";
    exit;
}
if ( $argv[1] == '-p' )
    $method = "PURGE";
else if ($argv[1] == '-r' )
    $method = "REFRESH";
else
{
    echo "ERROR: unknown or missing method option";
    exit;
}
$domain = $argv[2];
$url = $argv[3];
$server_ip = ($argc >= 5) ? $argv[4] : '127.0.0.1';
$port = ($argc == 6) ? $argv[5] : 80;


$fp = fsockopen($server_ip, $port, $errno, $errstr, 2);
if (!$fp) {
    echo "$errstr ($errno)\n";
} else {
    $out = "$method $url HTTP/1.0\r\n"
	. "Host: $domain\r\n"
	. "Connection: Close\r\n\r\n";
    fwrite($fp, $out);
    while (!feof($fp)) {
        echo fgets($fp, 128);
    }
    fclose($fp);
}

?>
