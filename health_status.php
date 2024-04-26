<?php
ini_set('exit_on_timeout', true);
ini_set('max_execution_time', 60);
ini_set('mysql.connect_timeout', 60);

$ignoreAuth=true;
include_once("interface/globals.php");
include_once("$srcdir/sql.inc");

$sql = "SELECT gl_value FROM globals WHERE gl_name = 'support_phone_number';";
$res = sqlQueryNoLog($sql);
$phone = $res['gl_value'];

$error = false;

$session = session_start();
$_SESSION['phone'] = $phone;
session_abort();

if (!$phone || !$config || !$session) {
    http_response_code(500);
    $error = true;
}

?>
<html>

<body>
<h1><?php echo $GLOBALS["openemr_name"] ?> System Status</h1>
Summary: <?php echo $error ? 'not-ok' : "ok" ?>
<ul>
    <li>Configured: <?php echo $config ? "yes" : "no" ?></li>
    <li>Support phone: <?php echo $phone ? $phone : "error" ?></li>
</ul>
</body>

</html>
