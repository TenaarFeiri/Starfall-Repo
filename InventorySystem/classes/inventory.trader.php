<?php
if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}
/*$dir = (__DIR__);
$dir = explode('\\', $dir);
include_once($dir[0].'\\'.$dir[1].'\\private\\database.php');*/
include_once('database.php');
/* spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
}); */

/*
    Testing URL: http://localhost/InventorySystem/inventory_trade_handler.php?target=59ee7fce-5203-4d8c-b4db-12cb50ad2c10&me=5675c8a0-430b-4281-af36-60734935fad3&id=5&amount=1
*/
class trader {
    private $pdoInv;
    private $pdoRp;
    private $debug = false;
    private $charGiving;
    private $charGivingUuid;
    private $charGivingName;
    private $givingUsr;
    private $charReceiving;
    private $charReceivingName;
    private $charReceivingUuid;
    private $receivingUsr;
    private $iId;
    private $settings;
    function __construct($uuid1, $uuid2) 
    {
        // Open the database connection when object is created.
        try
        {
            $this->pdoInv = connectToInventory();
            $this->pdoRp = connectToRptool();
            $this->settings = $this->getSettings();
            if($this->debug) {
                $this->pdoInv->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdoRp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "Started\n";
            }
            if(!$this->pdoInv or !$this->pdoRp) {
                die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
            } else {
                if($this->debug) {
                    echo "Connected to both databases.\n\n";
                }
            }
        }
        catch (Exception $e)
        {
            throw $e;
        }
        $this->getCharIds($uuid1, $uuid2); // If all's good, get character IDs.
        $this->getCharNames(); // And if all's good there, get names and parse them.
        if($this->mempty($this->charGiving, $this->charGivingName, $this->charReceiving, $this->charReceivingName, $this->charReceivingUuid, $this->givingUsr, $this->receivingUsr, $this->charGivingUuid))
        {
            // If these crucial variables aren't set or are empty, kill the script.
            die("err:Not all variables are set.");
        }
        // And now we are instantiated and ready to do the heavy lifting.
    }

    function mempty() // Check multiple empty statements all at once.
    {
        foreach(func_get_args() as $arg)
        {
            if(empty($arg))
            {
                return true; // Return true if any arguments are empty. We do not want them empty.
            }
        }
        return false;
    }

    function getSettings()
    {
        $stmt = "SELECT * FROM settings";
        try
        {
            $settings = $this->pdoInv->prepare($stmt);
            $settings->execute();
            $settings = $settings->fetch(PDO::FETCH_ASSOC);
        }
        catch (Exception $e)
        {
            die($e->getMessage());
        }
        return $settings;
    }

    function writeLog($log, $module)
    {
        // Format reference
        // "tradesuccess::" . $this->charReceivingName . "::" . $this->charReceivingUuid . "::" . $item['name'] . "::" . $amount . "::" . $this->charGivingName;
        // "moneysuccess::{$this->charReceivingName}::{$this->settings['money_name']}::{$amount}::{$this->charGivingName}"
        $stmt = "
        INSERT INTO logs
        VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
        ";
        $log = explode("::", $log); // Turn log into a usable array.
        $write = $this->pdoInv->prepare($stmt);
        $write->bindParam(":usr", $this->givingUsr);
        $write->bindParam(":uid", $this->charGivingUuid);
        $write->bindParam(":charid", $this->charGiving);
        $write->bindParam(":charname", $this->charGivingName);
        $write->bindParam(":module", $module);
        $out;
        if($module === "personal")
        {
            $out = "Gave " . $log[4] . "x of " . $log[3] . " (ID: " . $this->iId . ") to character " . $this->charReceivingName . " (ID: " . $this->charReceiving . ").";
        }
        if($module === "money")
        {
            $out = "Gave " . $log[1] . " (ID: " . $this->charReceiving . ") " . $log[3] . " " . $log[2] . ".";
        }
        $write->bindParam(":log", $out);
        try
        {
            $write->execute();
        } catch (Exception $e)
        {
            echo $e->getMessage();
        }
    }

    function getCharIds()
    {
        $args = func_get_args(); // Get arguments passed to getCharIds.
        // Count the arguments. This function is not dynamically called, but let's guard ourselves anyway.
        // Just in case.
        if(count($args) < 2)
        {
            die("err:Did not provide 2 arguments to getCharIds.");
        }
        else if(count($args) > 2)
        {
            die("err:Too many arguments provided to getCharIds");
        }
        $stmt = "SELECT username,lastchar,uuid FROM users WHERE uuid = ?
                UNION
                SELECT username,lastchar,uuid FROM users WHERE uuid = ?;
        ";
        $getIds = $this->pdoRp->prepare($stmt);
        try
        {
            $getIds->execute($args); // $args should always have 2 values, no more or less. Execute statement.
            $getIds = $getIds->fetchAll(PDO::FETCH_ASSOC); // Fetch the records of active character from the table.
        }
        catch(Exception $e)
        {
            throw $e;
        }
        if($this->debug)
        {
            print_r($getIds);
        }
        // At this stage, all is well.
        $this->charGiving = $getIds[0]['lastchar'];
        $this->charGivingUuid = $getIds[0]['uuid'];
        $this->charReceiving = $getIds[1]['lastchar'];
        $this->charReceivingUuid = $getIds[1]['uuid'];
        $this->givingUsr = $getIds[0]['username'];
        $this->receivingUsr = $getIds[1]['username'];
    }

    function getCharNames()
    {
        $stmt = "SELECT titles FROM rp_tool_character_repository WHERE character_id = ?
                UNION
                SELECT titles FROM rp_tool_character_repository WHERE character_id = ?;
        ";
        $pdoObj = $this->pdoRp->prepare($stmt);
        try
        {
            $pdoObj->execute([$this->charGiving,$this->charReceiving]);
            $pdoObj = $pdoObj->fetchAll(PDO::FETCH_NUM);
            if($this->debug)
            {
                print_r($pdoObj);
            }
        }
        catch(Exception $e)
        {
            throw $e;
        }
        $names = array();
        foreach($pdoObj as $arg)
        {
            $names[] = explode("=>", $arg[0])[0]; // This line is so fucking dumb but it works. Extract the character's name from the title information.
        }
        $this->charGivingName = $names[0];
        $this->charReceivingName = $names[1];
    }

    function giveItem($id, $amount) // Perform item transaction & all the checks involved with it.
    {
        $item = $this->getItemDetails($id); // Start by querying item details. This will kill the script if it fails so we don't need to catch errors here.
        $this->iId = $id;
        $slotArr = $this->checkInventorySpace($item, $amount); // If the item exists, check recipient's inventory for it. If none exists & there is space available, return that slot number. Otherwise die.
        $slot = "item_" . $slotArr[0];
        $slotDetails = explode(":", $slotArr[1]);
        $slotDetails[2] = $item['texture_name']; // Update the texture name in the string just to be safe.
        $giverArr = $this->checkGiverInventorySpace($item, $amount); // Check the giver's inventory space, returns -1 if transaction would fail. Otherwise returns fully parsed statement.
        if($giverArr === -1)
        {
            die("err:trade_fail_notenough");
        }
        else
        {
            $giverArr = explode("=>", $giverArr);
            $giverSlot = $giverArr[0];
            $giverSlot = $this->sanitise($giverSlot);
        }
        $slot = $this->sanitise($slot);
        if($this->debug)
        {
            print_r($slotArr);
            print_r($slot);
            print_r($slotDetails);
            print_r($giverArr);
        }
        // At this point, we're probably ready to complete the transaction.
        // So!
        $slotDetails = implode(":", $slotDetails);
        try
        {
            $stmt ="
                UPDATE character_inventory 
                SET {$slot} = ? 
                WHERE char_id = ?
            ";
            $this->pdoInv->beginTransaction(); // Start transaction.
            $transaction = $this->pdoInv->prepare($stmt);
            $transaction->execute([$slotDetails, $this->charReceiving]);
            $stmt = "UPDATE character_inventory 
            SET {$giverSlot} = ? 
            WHERE char_id = ?";
            $transaction = $this->pdoInv->prepare($stmt);
            $transaction->execute([$giverArr[1], $this->charGiving]);
            if(!$this->pdoInv->commit())
            {
                $this->pdoInv->rollBack();
            }
            $out = "tradesuccess::" . $this->charReceivingName . "::" . $this->charReceivingUuid . "::" . $item['name'] . "::" . $amount . "::" . $this->charGivingName;
            $this->writeLog($out, "personal");
        } catch (Exception $e)
        {
            $this->pdoInv->rollBack();
            die("err:syntax_error");
        }
        // If we're here, success!
        echo $out; // Echo the feedback.
    }

    function sanitise($data)
    {
        return "`".str_replace("`","``",$data)."`";
    }

    function chkStorageForUnique($id)
    {
        $stmt = "SELECT * FROM character_storage WHERE char_id = ? AND item_id = ?";
        $do = $this->pdoInv->prepare($stmt);
        try
        {
            $do->execute([$this->charId, $itemId]);
            $do = $do->fetch(PDO::FETCH_ASSOC);
            if($do)
            {
                return true;
            }
        }
        catch(PDOException $e)
        {
            exit("err:" . $e->getMessage());
        }
        return false;
    }

    function checkInventorySpace($item, $amount) // Check inventory space of recipient, return slot number to put $id in.
    {
        $id = $item['id'];
        if($item['type'] === "unique")
        {
            if($this->chkStorageForUnique($id))
            {
                exit("err:Target already possesses one of this unique item in their storage.");
            }
        }
        $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9 FROM character_inventory WHERE char_id = ?"; // Select all the inventory slots.
        $pdoObj = $this->pdoInv->prepare($stmt);
        try
        { // Execute statement, catch errors.
            $pdoObj->execute([$this->charReceiving]);
            $pdoObj = $pdoObj->fetch(PDO::FETCH_NUM);
        } catch (Exception $e)
        {
            throw $e;
        }
        if($this->debug)
        { // If in debug mode, print what we get so we can see the data.
            print_r($pdoObj);
        }
        $exists = false; // True if we've found the existing slot. If both this and $i are false, inventory is full.
        $i; // This will have the number of the first available slot.
        $iCount = 0;
        $out;
        foreach($pdoObj as $arg)
        {
            $tmp = explode(":", $arg);
            if($tmp[0] === "0" and !isset($i))
            {
                $i = $iCount; // Gives us the first slot number that is available, in case inventory does not contain an item.
            }
            else
            {
                if($tmp[0] === $id) // Set the $exists parameter & note down which slot we're doing.
                {
                    $exists = true;
                    if($exists and $item['type'] === "unique")
                    {
                        exit("err:Target already possesses one of this unique item in their inventory.");
                    }
                    $i = $iCount; // However, overwrite $i and confirm $exists if we find it in the inventory.
                    if(($tmp[1] + $amount) > $item['max_stack'])
                    {
                        die("err:max_stack_error");
                    }
                    else
                    {
                        $tmp[1] = ($tmp[1] + $amount);
                    }
                    $out = implode(":", $tmp);
                    break;
                }
            }
            ++$iCount;
        }
        if(!$exists and !isset($i))
        {
           die("tradefail::" . $this->charReceivingName . "::" . $this->charReceivingUuid . "::" . $item['name'] . "::" . $amount . "::" . $this->charGivingName); // Die if inventory is full & no available slot was found.
        }
        else if(!$exists and isset($i))
        {
            $out = $item['id'] . ":" . $amount . ":" . $item['texture_name'];
        }
        return array(($i + 1), $out);
    }

    function checkGiverInventorySpace($item, $amount)
    {
        // This function returns the slot for the item in the giver's inventory + its calculated data including remaining items, if any.
        // Returns false if item doesn't exist in inventory.
        // Returns -1 if transaction would fail.
        $id = $item['id'];
        $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9 FROM character_inventory WHERE char_id = ?";
        $pdoObj = $this->pdoInv->prepare($stmt);
        try
        {
            $pdoObj->execute([$this->charGiving]); // Get the inventory of our man, the boii, the good samaritan and generous soul!
            $pdoObj = $pdoObj->fetch(PDO::FETCH_NUM);
            if($this->debug)
            {
                print_r($pdoObj);
            }
        }
        catch(Exception $e)
        {
            throw $e;
        }
        $found = false;
        $i = 1;
        foreach($pdoObj as $arg)
        {
            $tmp = explode(":", $arg);
            if($tmp[0] === $id)
            {
                $found = true;
                break;
            }
            else
            {
                ++$i;
            }
        }
        if($this->debug)
        {
            print_r($tmp);
            echo "\nitem_" . $i . "\n";
        }
        if(!$found)
        {
            die("err:You do not have this item in your inventory. You should not be seeing this error message. Please contact an admin; your inventory has broken & needs manual repair.");
        }
        // At this point, calculate how much will be removed & return a finished string + slot number.
        // Return slot number & 0 if last of inventory.
        // Failing that, return -1 to indicate transaction cannot take place.
        if(($tmp[1] - $amount) === 0)
        {
            $out = 0;
        }
        else if(($tmp[1] - $amount) < 0)
        {
            return -1;
        }
        else
        {
            // At this point, construct a workable string telling us where to go.
            $tmp[1] = ($tmp[1] - $amount);
            $tmp[2] = $item['texture_name'];
            $out = implode(":", $tmp);
        }
        return "item_" . $i . "=>" . $out;
    }

    function getItemDetails($id) // Get details of the item in question.
    {
        $stmt = "SELECT id,max_stack,type,texture_name,name FROM items WHERE id = ?";
        $pdoObj = $this->pdoInv->prepare($stmt);
        try
        {
            $pdoObj->execute([$id]);
            $pdoObj = $pdoObj->fetch(PDO::FETCH_ASSOC);
        }
        catch (Exception $e)
        {
            throw $e;
        }
        if($this->debug)
        {
            print_r($pdoObj);
        }
        return $pdoObj;
    }

    function giveMoney($amount)
    {
        $giverInventory = $this->getInventoryArray($this->charGiving);
        $receiverInventory = $this->getInventoryArray($this->charReceiving);
        if(($giverInventory['money'] - $amount) < 0)
        {
            // If lower than 0, return error.
            die("err:You don't have that kind of {$this->settings['money_name']} to give!");
        }
        $giverMoneyAmount = ($giverInventory['money'] - $amount); // How much money will remain after transaction.
        $receiverMoneyAmount = ($receiverInventory['money'] + $amount); // Final amount after adding to the receiver's money.
        $stmt ="
                UPDATE character_inventory 
                SET money = ? 
                WHERE char_id = ?
            ";
        $this->pdoInv->beginTransaction();
        try
        {
            $moolah = $this->pdoInv->prepare($stmt);
            $moolah->execute([$giverMoneyAmount, $this->charGiving]);
            $moolah->execute([$receiverMoneyAmount, $this->charReceiving]);
            $this->pdoInv->commit();
            $out = "moneysuccess::{$this->charReceivingName}::{$this->settings['money_name']}::{$amount}::{$this->charGivingName}::{$this->charReceivingUuid}"; // Return this on successful transfer!
            $this->writeLog($out, "money");
        }
        catch(Exception $e)
        {
            $this->pdoInv->rollBack();
            if($this->debug)
            {
                die($e->getMessage());
            }
            else
            {
                die("moneyfail:query_fail_rollback");
            }
        }
        echo $out;
    }

    function getInventoryArray($id)
    {
        // Function to get a character's full inventory array. Select all,
        // in case of future expansion.
        $stmt = "SELECT char_id,item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,money FROM character_inventory WHERE char_id = :id";
        $getInv = $this->pdoInv->prepare($stmt);
        $getInv->bindParam(":id", $id);
        try
        {
            $getInv->execute();
            $getInv = $getInv->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e)
        {
            die($e->getMessage());
        }
        if($this->debug)
        {
            print_r($getInv);
        }
        if($id !== $getInv['char_id'])
        {
            // Kill scripts if there for some reason is an ID mismatch. Shouldn't happen.
            die("err:Char ID mismatch for money transaction. Please report this issue to staff with steps taken to produce the error. Thank you!");
        }
        return $getInv;
    }

}
?>