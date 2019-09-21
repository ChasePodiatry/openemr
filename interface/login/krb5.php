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

function loginFailed()
{
    header("Location: /interface/login/login.php?krb5=false");
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

switch ($_SERVER['AUTH_TYPE']) {
    case "Negotiate":
    case "negotiate":
        $username = $_SERVER['REMOTE_USER'];
        if ($username == null) {
            loginFailed();
        }

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
            loginFailed();
        }

        if ($authGroup = privQuery("select * from groups where user=? and name=?", array($username, $provider))) {
            loginSuccess($userInfo, $authGroup, $provider);
        } else {
            newEvent('login', $username, $provider, 0, "failure: $ip. user not in group: $provider");
            loginFailed();
        }

        break;

    default:
        loginFailed();
        break;
}

require_once("$srcdir/auth.inc");

echo "<h2>Kerberos Auth</h2>";
echo "Auth type: " . $_SERVER['AUTH_TYPE'] . "<br />";
echo "Remote user: " . $_SERVER['REMOTE_USER'] . "<br />";
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