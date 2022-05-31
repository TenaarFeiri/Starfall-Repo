<?php
header('Content-type: text/plain');
/*
    Inventory Handler by Tenaar Feiri

    TODO
        - Fuck me there's so much to do. There's so fucking much this handler needs to handle omg!!!
        - Create an easy comms API for SL.
        - Character storage may get big over time. Need an easy way to paginate.
        - Autoloader should keep things efficient & load only the modules we need at any one time. Needs testing.
        - 
*/

$debug = false;
$deb = false;
if($deb) { // Error handling only if we're debugging or developing.
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
define('noigneano93789hg2nopg', true);

// Auto load classes as I need them.
spl_autoload_register(function ($name) {
    include 'classes/inventory.' . $name . '.php';
});
///////////////////////////////////////////////////////////////////////////////////
require_once('classes/database.php');
// Request header data to verify client is Second Life.
$headers = apache_request_headers();
if(array_key_exists("X-SecondLife-Owner-Name", $headers) and array_key_exists("X-SecondLife-Owner-Key", $headers))
{
    $headerData = array(
        'username' => $headers["X-SecondLife-Owner-Name"],
        'uuid' => $headers["X-SecondLife-Owner-Key"]
    );
}
else
{
    if(!$debug)
    {
        die('You are not a Second Life client.');
    }
}
//die($headerData['username']);
function charName($char)
{
    $charName = connectToRptool();
    $charName->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = "
        SELECT titles FROM rp_tool_character_repository WHERE character_id = ?;
    ";
    $do = $charName->prepare($stmt);
    if(!$do->execute([$char]))
    {
        die("err:Could not get character details for log.");
    }
    $out = $do->fetch(\PDO::FETCH_ASSOC);
    return $out['titles'];
}
function writeLog($usr, $uid, $char, $module, $log) {
    // Simple function to create a log.
    $pdo = connectToInventory();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $charName = getCharName(charName($char)); // Get the name of the character.
    $stmt = "
        INSERT INTO logs
        VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
    ";
    $do = $pdo->prepare($stmt);
    $do->bindParam(":usr", $usr);
    $do->bindParam(":uid", $uid);
    $do->bindParam("charid", $char);
    $do->bindParam(":charname", $charName);
    $do->bindParam(":module", $module);
    $do->bindParam(":log", $log);
    if(!$do->execute())
    {
        die("err:Could not log action.");
    }
}
function getCharName($data)
{
    $out;
    $arr = explode("=>", $data);
    $out = $arr[0];
    return $out;
}
// Various object code here depending on the needs of the client POST.

// Various test code for messing with the database. None of this makes it to the final build.
/*
    API Documentation
        All interfacing with this script HAS to happen via POST. This is also a limitation of Second Life.
        All calls to this script MUST include ?func=<function>, or the script will not accept it. For instance: ?func=currency.
        ?func= tells the handler which class it need to use.
        ?input= tells the handler what you're trying to accomplish/which function you need to use.
        ?charId= tells the handler whose inventory we are dealing with.
        ?data= is the catch-all for anything that doesn't need its own array index.
*/

// Verify that the connecting agent and character ID exists. Can probably send that in a custom header...
if(!$debug)
{
    $arr = $_POST;
}
else
{
    $arr = $_GET; // Make it a local array so I don't have to overwork myself when we switch to POST.
}
if(!$debug)
{
    if(!isset($arr['uuid']))
    {
        // If no uuid is defined, use the header.
        $uuid = $headerData['uuid'];
    }
    else
    {
        // Otherwise, there's probably a reason we're accessing the script this way.
        // Potentially to add or remove an item.
        $uuid = $arr['uuid'];
    }
}
else
{
    $uuid = $arr['uuid'];
}

if(empty($arr))
{
    die('No arr data detected.');
}
else if(!isset($arr['func']))
{
    // func HAS to be set for the handler to know what to do. Die without it.
    die('function param is not set');
}
else if(!isset($arr['charId']))
{
    $qObj = connectToRptool();
    $stmt = "SELECT lastchar FROM users WHERE uuid = ?";
    $q = $qObj->prepare($stmt);
    if(!$q->execute([$uuid]))
    {
        die("err:Could not execute inventory handler uuid2charid function.");
    }
    $q = $q->fetch();
    $charId = $q[0];
}
else
{
    $charId = $arr['charId'];
}
    // Verify that the UUID exists in our database, and that the character ID exists.
    // Also verify that they're both associated with one another.
    // If this succeeds, we have verified that agent is the true owner of the character.
$verifyUsr;
$verified = false;
$verifyUsr = new verify();
$verified = $verifyUsr->verifyOwner($charId, $uuid);
if(!$verified)
{
    if($arr['func'] == "getlastid")
    {
        $verifyUsr = null;
        $verifyUsr = new verify();
        $verifyUsr->getLastId($uuid);
    }
    else
    {
        die('err:Verification failed: You are not the owner of this character.');
    }
}

// If we survive both of these checks, we are gucci.
// Time for the meat!

// DEV TEST \\
if($arr['func'] == "test")
{
    $testCur = new currency();
    $testInv = new personal();
    $curOut = $testCur->getInventoryMoney($charId);
    $invOut = $testInv->getFullInventory($charId);
    echo "$curOut \n";
    echo "$invOut \n";
}
    // CURRENCY HANDLING \\
if($arr['func'] == "currency")
{
    // We are tackling currency.
    // Instantiate the currency class.
    try {
        $moneyObj = new currency();
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
        die();
    }
    // Get player's personal money.
    if($arr['input'] == "getInvMoney")
    {
        // Get inventory money. Check if charId is set, otherwise die.
        if(!isset($charId))
        {
            die('charId parameter is not set');
        }
        // Let's get the money of charId's inventory!
        $money = $moneyObj->getInventoryMoney($charId);
        echo "money:".$money;
    }
    if($arr['input'] == "givemoney") // Give money!
    {
        $val = $moneyObj->giveRemoveInventoryMoney($arr['input'], $charId, $arr['amount']);
        if($val == 1)
        {
            $log = "Received " . $arr['amount'] . " of money";
            if(isset($arr['src']))
            {
                $log = $log . " from source: " . $arr['src'];
            }
            writeLog($headerData['username'], $headerData['uuid'], $charId, $arr['func'], $log);
        }
    }
    if($arr['input'] == "takemoney") // Remove money.
    {
        $val = $moneyObj->giveRemoveInventoryMoney($arr['input'], $charId, $arr['amount']);
        if($val == true)
        {
            $out;
            $out = "Character $charId has: " . $moneyObj->getInventoryMoney($charId) . " " . $moneyObj->getSettings("money_name");
            echo $out;
        }
    }
    if($arr['input'] == "tradeMoney") // Handle trades.
    {
        die("err:Trading disabled until further notice.");
        if(!isset($arr['targetChar']))
        {
            die('Cannot trade; targetChar is not set.');
        }
        //$curName = $moneyObj->getSettings("money_name");
        if(($moneyObj->getInventoryMoney($charId) - $arr['amount']) < 0)
        {
            die('You don\'t have enough ' . $curName . ' to trade.');
        }
        if($moneyObj->giveRemoveInventoryMoney("takemoney", $charId, $arr['amount']) and $moneyObj->giveRemoveInventoryMoney("givemoney", $arr['targetChar'], $arr['amount']))
        {
            /*echo "Character $charId has: " . $moneyObj->getInventoryMoney($charId) . " $curName.\n
            Character " . $arr['targetChar'] . " has: " . $moneyObj->getInventoryMoney($arr['targetChar']) . " $curName";*/
            $log = "Traded " . $arr['amount'] . " of money to character (" . $arr['targetChar'] . ") ".getCharName(charName($arr['targetChar']));
            writeLog($headerData['username'], $headerData['uuid'], $charId, $arr['func'], $log);
            echo "trade:success";
        }
    }
    
}

// PERSONAL INVENTORY HANDLING \\
if($arr['func'] == "personal")
{
    try {
        $personalObj = new personal($charId);
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
        die();
    }
    // Retrieve the full inventory plus money!
    if($arr['input'] == "updateInventory")
    {
        if(!isset($charId))
        {
            die('err:charId parameter is not set');
        }

        echo $personalObj->getFullInventory();
    }
    if($arr['input'] == "loginUpdateInventory")
    {
        $out = $personalObj->getFullInventory() . "[[§]]" . getCharName(charName($charId)) . "[[@&@]]" . $charId;
        echo $out;
    }
    if($arr['input'] == "delete")
    {
        if($personalObj->setDeleted())
        {
            die("deletion:success");
        }
        else
        {
            die("deletion:failed");
        }
    }
    if($arr['input'] == "addItem")
    {
        if(!isset($charId) || !isset($arr['data']))
        {
            die('charId and/or data parameter is not set');
        }
        // addItem($itemId, $amount)
        $tmpArr = explode(":", $arr['data']);
        if($personalObj->addItem($tmpArr[0], $tmpArr[1]))
        {
            echo "success:additem";
        }
    }
    if($arr['input'] == "removeItem")
    {
        // Remove item from player's inventory.
        if(!isset($charId) || !isset($arr['data']))
        {
            die('charId and/or data parameter is not set');
        }
        // removeItem($charId, $itemId, $amount)
        $tmpArr = explode(":", $arr['data']);
        if($personalObj->removeItem($tmpArr[0], $tmpArr[1]))
        {
            echo "destroyed:" . $personalObj->getFullInventory($charId);
            //$log = "Destroyed " . $tmpArr[1] . "x (ID: ".$tmpArr[0].") " . $personalObj->getItemDetails($tmpArr[0])['name'];
            $log = 'Destroyed '.$tmpArr[1].'x '.$personalObj->getItemDetails($tmpArr[0])['name'].' (ID: '.$tmpArr[0].')';
            writeLog($headerData['username'], $headerData['uuid'], $charId, $arr['func'], $log);
        }
    }
    if($arr['input'] == "tradeItem")
    {
        // Give amount of item to targetChar.
        // Remove amount of item from charId.
        die("err:Trading disabled until further notice.");
        if(!isset($arr['targetChar']))
        {
            die('err:Cannot trade; targetChar is not set.');
        }
        if(!isset($arr['data']))
        {
            die('err:Cannot trade; no trade data.');
        }
        if($arr['targetChar'] === $charId or $arr['targetChar'] === $uuid)
        {
            die("err:Cannot trade to yourself.");
        }
        $tmpArr = explode(":", $arr['data']); // Explode into array. Item ID on 0, amount on 1.
        //$remove = $personalObj->removeItem($charId, $tmpArr[0], $tmpArr[1]);
        $add = $personalObj->addItem($tmpArr[0], $tmpArr[1], $arr['targetChar']);
        // If this does not die, it has succeeded.
        $remove = $personalObj->removeItem($tmpArr[0], $tmpArr[1]);
        if($add and $remove)
        {
            $log = 'Traded ' . $tmpArr[1] . 'x ' . $personalObj->getItemDetails($tmpArr[0])['name'] . ' (ID: ' . $tmpArr[0] . ') to ' . getCharName(charName($arr['targetChar'])) . ' ('.$arr['targetChar'].')';
            writeLog($headerData['username'], $headerData['uuid'], $charId, $arr['func'], $log);
            echo "trade:success";
        }
        else
        {
            echo "err:Failed to give item to character. Please contact staff.";
            $log = 'Failed to trade ' . $tmpArr[1] . 'x ' . $personalObj->getItemDetails($tmpArr[0])['name'] . ' (ID: ' . $tmpArr[0] . ') to ' . getCharName(charName($arr['targetChar'])) . ' ('.$arr['targetChar'].')';
            writeLog($headerData['username'], $headerData['uuid'], $charId, $arr['func'], $log);
        }
    }
    if($arr['input'] == "getGatherCooldown")
    {
        $out = $personalObj->getGatherCooldown();
        if(!$out)
        {
            die("err:Couldn't get gathering cooldown.");
        }
        echo $out;
    }
}

?>