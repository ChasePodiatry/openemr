<?php

$provider = "Chase Podiatry and Chiropody";

$ignoreAuth = true;
include_once("../globals.php");
//$srcdir = "../../../library";
include_once("$srcdir/sql.inc");

require_once("$srcdir/authentication/common_operations.php");
require_once("$srcdir/authentication/privDB.php");

$ip = $_SERVER['REMOTE_ADDR'];
$successUrl = "/interface/main/main_screen.php?&site=" . attr($_SESSION['site_id']);

function loginFallback()
{
    header("Location: /interface/login/login.php?cfzt=false");
    exit();
}

function loginFailed($error = null, $status = 403)
{
    ?>
    <center>
        Access denied <?php echo $error ? ": " . $error : "" ?>
        <a href="/cdn-cgi/access/logout" target="_parent">Retry</a>
    </center>
    <?php
    exit($status);
}

function loginSuccess($userInfo, $authGroup, $provider)
{
    $_SESSION['authUser'] = $userInfo['username'];
    $_SESSION['authPass'] = $userInfo['password'];
    $_SESSION['authGroup'] = $authGroup['name'];
    $_SESSION['authUserID'] = $userInfo['id'];
    $_SESSION['authProvider'] = $provider;
    $_SESSION['authId'] = $userInfo{'id'};
    $_SESSION['cal_ui'] = $userInfo['cal_ui'];
    $_SESSION['userauthorized'] = $userInfo['authorized'];
    // Some users may be able to authorize without being providers:
    if ($userInfo['see_auth'] > '2') $_SESSION['userauthorized'] = '1';
    newEvent('login', $userInfo['username'], $provider, 1, "success: " . $_SERVER['REMOTE_ADDR']);

    /**
     * Perform some of the housekeeping actions from auth.inc
     */
// set the language
    if (!empty($_POST['languageChoice'])) {
        $_SESSION['language_choice'] = $_POST['languageChoice'];
    } else {
        $_SESSION['language_choice'] = 1;
    }

    $_SESSION['loginfailure'] = null;
    unset($_SESSION['loginfailure']);
    //store the very first initial timestamp for timeout errors
    $_SESSION["last_update"] = time();
}


$accessToken = $_COOKIE['CF_Authorization'];
if (!$accessToken) loginFallback();
$JWK = file_get_contents("https://chasepodiatry.cloudflareaccess.com/cdn-cgi/access/certs");
if (!$JWK) loginFallback();
$JWK = json_decode($JWK);

$JWT = explode(".", $accessToken, 3);
$head = json_decode(base64_decode(strtr($JWT[0], '-_', '+/')));
$claims = json_decode(base64_decode($JWT[1]));
$sig = base64_decode(strtr($JWT[2], '-_', '+/'));

if ($head->{'alg'} != "RS256") loginFailed("alg is not of approved type");
$sigCert = false;

foreach ($JWK as $key => $value) {
    if ($value->{'kid'} == $head->{'kid'}) {
        $sigCert = $value->{'cert'};
    }
}

if (!$sigCert) loginFailed("kid not found in certs", 500);
$publicKey = openssl_pkey_get_public($sigCert);
if (!$publicKey) loginFailed("cannot decode public key", 500);

$valid = openssl_verify(join(".", [$JWT[0], $JWT[1]]), $sig, $publicKey, "RSA-SHA256");
if (!$valid) loginFailed("token signature not valid");

// token is known to be good, start parsing the result
$username = $claims->{'custom'}->{'krb_principal_name'};

$getUserSQL = "select id, username, authorized, see_auth, password" .
    ", cal_ui, active " .
    " from users where krb5_principle = ?";
$userInfo = privQuery($getUserSQL, array($username));
$username = $userInfo['username'];

$getUserSecureSQL = " SELECT " . implode(",", array(COL_ID, COL_PWD, COL_SALT))
    . " FROM " . TBL_USERS_SECURE
    . " WHERE BINARY " . COL_UNM . "=?";
// Use binary keyword to require case sensitive username match
$userSecure = privQuery($getUserSecureSQL, array($username));
$userInfo['password'] = $userSecure['password'];

if ($userInfo['active'] != 1) {
    newEvent('login', $username, $provider, 0, "failure: $ip. user not active or not found in users table");
    loginFailed("user not active");
}

if ($authGroup = privQuery("select * from groups where user=? and name=?", array($username, $provider))) {
    loginSuccess($userInfo, $authGroup, $provider);
} else {
    newEvent('login', $username, $provider, 0, "failure: $ip. user not in group: $provider");
    loginFailed("user not in group $provider");
}

require_once("$srcdir/auth.inc");

echo "<h2>Cloudflare Auth</h2>";
?>

<script type="text/javascript">
    window.top.location = "<?php echo $successUrl; ?>"
</script>


<center>
    <h1>Welcome back <?php echo $_SESSION['authUser']; ?></h1>
    <p>
        Please wait to be redirected. If you are not automatically redirected, <a href="<?php echo $successUrl ?>"
                                                                                  target="_top">click here</a>
    </p>
</center>