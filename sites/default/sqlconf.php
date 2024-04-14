<?php
//  OpenEMR
//  MySQL Config
//  Referenced from /library/sqlconf.php.

global $disable_utf8_flag;
$disable_utf8_flag = false;

$host	= isset($_ENV['MYSQL_HOST']) ? $_ENV['MYSQL_HOST'] : 'localhost';
$port	= isset($_ENV['MYSQL_PORT']) ? $_ENV['MYSQL_PORT'] : '3306';
$login	= isset($_ENV['MYSQL_LOGIN']) ? $_ENV['MYSQL_LOGIN'] : 'openemr';
$pass	= isset($_ENV['MYSQL_PASS']) ? $_ENV['MYSQL_PASS'] : 'openemr';
$dbase	= isset($_ENV['MYSQL_DBASE']) ? $_ENV['MYSQL_DBASE'] : 'openemr';

$sqlconf = array();
global $sqlconf;
$sqlconf["host"]= $host;
$sqlconf["port"] = $port;
$sqlconf["login"] = $login;
$sqlconf["pass"] = $pass;
$sqlconf["dbase"] = $dbase;

//////////////////////////
//////////////////////////
//////////////////////////
//////DO NOT TOUCH THIS///
$config = 1; /////////////
//////////////////////////
//////////////////////////
//////////////////////////
?>
