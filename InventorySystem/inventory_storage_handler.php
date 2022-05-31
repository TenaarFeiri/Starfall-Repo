<?php
header('Content-type: text/plain');
define('noigneano93789hg2nopg', true);
// Request header data to verify client is Second Life.
$headers = apache_request_headers();
$debug = false;
$dev = false;
if($debug ^ $dev) { // Error handling only if we're debugging or developing.
    // ----------------------------------------------------------------------------------------------------
    // - Display Errors
    // ----------------------------------------------------------------------------------------------------
    ini_set('display_errors', 'On');
    ini_set('html_errors', 0);
    // ----------------------------------------------------------------------------------------------------
    // - Error Reporting
    // ----------------------------------------------------------------------------------------------------
    error_reporting(-1);

    // ----------------------------------------------------------------------------------------------------
    // - Shutdown Handler
    // ----------------------------------------------------------------------------------------------------
    function ShutdownHandler()
    {
        if(@is_array($error = @error_get_last()))
        {
            return(@call_user_func_array('ErrorHandler', $error));
        };

        return(TRUE);
    };

    register_shutdown_function('ShutdownHandler');

    // ----------------------------------------------------------------------------------------------------
    // - Error Handler
    // ----------------------------------------------------------------------------------------------------
    function ErrorHandler($type, $message, $file, $line)
    {
        $_ERRORS = Array(
            0x0001 => 'E_ERROR',
            0x0002 => 'E_WARNING',
            0x0004 => 'E_PARSE',
            0x0008 => 'E_NOTICE',
            0x0010 => 'E_CORE_ERROR',
            0x0020 => 'E_CORE_WARNING',
            0x0040 => 'E_COMPILE_ERROR',
            0x0080 => 'E_COMPILE_WARNING',
            0x0100 => 'E_USER_ERROR',
            0x0200 => 'E_USER_WARNING',
            0x0400 => 'E_USER_NOTICE',
            0x0800 => 'E_STRICT',
            0x1000 => 'E_RECOVERABLE_ERROR',
            0x2000 => 'E_DEPRECATED',
            0x4000 => 'E_USER_DEPRECATED'
        );

        if(!@is_string($name = @array_search($type, @array_flip($_ERRORS))))
        {
            $name = 'E_UNKNOWN';
        };

        return(print(@sprintf("%s Error in file \xBB%s\xAB at line %d: %s\n", $name, @basename($file), $line, $message)));
    };

    $old_error_handler = set_error_handler("ErrorHandler");
}
if(array_key_exists("X-SecondLife-Owner-Name", $headers) and array_key_exists("X-SecondLife-Owner-Key", $headers))
{
    
}
else
{
    if(!$debug)
    {
        die('err:You are not a Second Life client.');
    }
}



if(!$debug)
{
    $arr = $_POST;
}
else
{
    $arr = $_GET; // Make it a local array so I don't have to overwork myself when we switch to POST.
}

if((!isset($arr['uuid']) or empty($arr['uuid'])))
{
    // If vital information is not included, just die. We need a char id, and we need a UUID to confirm that the character belongs to the client.
    die("err:No charId and/or uuid provided provided.");
}
else if(!isset($arr['func']) or empty($arr['func']))
{
    // Can't handle storage requests if the handler isn't told what to do!
    die("err:No function provided, or function empty.");
}

// Passing all that, auto load classes as I need them.
spl_autoload_register(function ($name) {
    include 'classes/inventory.' . $name . '.php';
});
require_once('classes/database.php');
/*

    Function list

    # withdraw - &itemId=x&amount=x
    # deposit - &itemId=x&amount=x
    # overview - &page=x
    # search - &srch=X

*/

$func = $arr['func'];
$pdo = connectToRptool();
$stmt = "SELECT lastchar,username FROM users WHERE uuid = ?";
$q = $pdo->prepare($stmt);
if(!$q->execute([$arr['uuid']]))
{
    die("Could not get current character ID.");
}
else
{
    $q = $q->fetch();
}
$stmt = "SELECT titles FROM rp_tool_character_repository WHERE character_id = ?";
$q2 = $pdo->prepare($stmt);
if(!$q2->execute([$q[0]]))
{
    die("Could not get current character name.");
}
else
{
    $q2 = $q2->fetch();
}
$name = explode("=>", $q2[0])[0];
function writeLog($usr, $uid, $char, $module, $log, $name) {
    // Simple function to create a log.
    $pdo = connectToInventory();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = "
        INSERT INTO logs
        VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
    ";
    $do = $pdo->prepare($statement);
    $do->bindParam(":usr", $usr);
    $do->bindParam(":uid", $uid);
    $do->bindParam("charid", $char);
    $do->bindParam(":charname", $name);
    $do->bindParam(":module", $module);
    $do->bindParam(":log", $log);
    if(!$do->execute())
    {
        die("err:Could not log action.");
    }
}
$storage = new storage($q[0], $arr['uuid']);
if($arr['func'] === "deposit") // Deposit items!
{
    // Desposits.
    if(!isset($arr['itemId']) or !isset($arr['amount']))
    {
        die("err:itemId and/or amount not defined for storage deposit.");
    }
    else if(empty($arr['itemId']) or empty($arr['amount']))
    {
        die("err:itemId and/or amount for storage deposit containing no value.");
    }
    echo $storage->storeItem($arr['itemId'], $arr['amount']);
    $deets = $storage->getItemDetails($arr['itemId']);
    $log = "Deposited ". $arr['amount'] . "x " . $deets['name'] . " (ID: " . $deets['id'] . ") to their storage.";
    writeLog($q[1], $arr['uuid'], $q[0], $arr['func'], $log, $name);
}
else if($func === "withdraw")
{
    // Withdrawals.
    if(!isset($arr['itemId']) or !isset($arr['amount']))
    {
        die("err:itemId and/or amount not defined for storage deposit.");
    }
    else if(empty($arr['itemId']) or empty($arr['amount']))
    {
        die("err:itemId and/or amount for storage deposit containing no value.");
    }
    if($storage->withdrawItem($arr['itemId'], $arr['amount']))
    {
        // Perform the withdrawal & return a string.
        $deets = $storage->getItemDetails($arr['itemId']);
        $log = "Withdrew ". $arr['amount'] . "x " . $deets['name'] . " (ID: " . $deets['id'] . ") from their storage.";
        writeLog($q[1], $arr['uuid'], $q[0], $func, $log, $name);
        echo "::refreshinventory::";
        
    }
}
else if($func === "overview")
{
    if(!isset($arr['page']))
    {
        die("err:No page provided to storage list.");
    }
    else if($arr['page'] === "0")
    {
        die("err:Page number cannot be 0.");
    }
    $out = $storage->getFullStorage($arr['page']);
    if(!$out)
    {
        die("overview:empty");
    }
    echo $out;
}
else if($func === "search")
{
    $out = $storage->searchForItem($arr['srch']);
    echo $out;
}



?>