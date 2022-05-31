<?php
if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}

// Turn Arrays into CSV.
function array_2_csv($array) {
    $csv = array();
    foreach ($array as $item) {
        if (is_array($item)) {
            $csv[] = array_2_csv($item);
        } else {
            $csv[] = $item;
        }
    }
    return implode(',', $csv);
}

function array_2_psv($array) {
    $csv = array();
    foreach ($array as $item) {
        if (is_array($item)) {
            $csv[] = array_2_csv($item);
        } else {
            $csv[] = $item;
        }
    }
    return implode('§', $csv);
}

include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});
class personal {

    private $pdo;
    private $debug = false;
    private $rptool;
    private $charid;
    function __construct($character) 
    {
        // Open the database connection when object is created.
        $this->pdo = connectToInventory();
        if($this->debug) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        if(!$this->pdo) {
            //die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
            die("err:Could not construct personal inventory.");
        }
        $this->charid = $character;
    }

    function getFullInventory()
    {
        if($this->isDeleted($this->charid))
        {
            die("err:Could not load inventory due to character deletion. If you are seeing this, your deleted character may only be partially restored. Contact an admin.");
        }
        $out;
        // Get the full personal inventory (NOT STORAGE) of the character in question.
        $stmt = "
            SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,money FROM character_inventory WHERE char_id = :charid
        ";
        $getInventory = $this->pdo->prepare($stmt);
        $getInventory->bindParam(':charid', $this->charid);
        if(!$getInventory->execute()) {
            die("err:Query failed, get full inventory.");
        } else {
            $output = $getInventory->fetch(\PDO::FETCH_ASSOC);
            $a = array();
            $c = 0;
            foreach($output as $item)
            {
                // Let's try to get the textures right.
                ++$c;
                if($c < 10)
                {
                    $tmp = explode(":", $item);
                    if($tmp[0] != "0")
                    {
                        $tex = $this->getItemDetails($tmp[0]);
                        $tmp[2] = $tex['texture_name'];
                    }
                    $tmp = implode(":", $tmp);
                    array_push($a, $tmp);
                }
            }
            array_push($a, $output['money']);
            $out = array_2_csv($a);
        }
        $invNames = $this->getInventoryNames($this->getInventoryArray($this->charid));
        $out = $out . ",". $this->getSettings('money_name');
        if($invNames != "")
        {
            $out = $out . "|" . $invNames;
        }
        $out = "fullinv:" . $out;
        return $out;
    }

    function isDeleted()
    {
        $stmt = "SELECT deleted FROM character_inventory WHERE char_id = :charid";
        $q = $this->pdo->prepare($stmt);
        $q->bindParam("charid", $this->charid);
        if($q->execute())
        {
            $o = $q->fetch(\PDO::FETCH_ASSOC);
            if($o['deleted'] == 1)
            {
                return true;
            }
            return false;
        }
        else
        {
            return true;
        }
    }

    function getSettings($setting)
    {
        $out;
        $stmt = "
            SELECT * FROM settings
        ";
        $getSet = $this->pdo->prepare($stmt);
        if(!$getSet->execute())
        {
            die('err:Could not get data from the settings table.');
        }
        else
        {
            $out = $getSet->fetch(\PDO::FETCH_ASSOC);
            $out = $out[$setting];
        }
        return $out;
    }

    function getInventoryArray()
    {
        // Get the full personal inventory (NOT STORAGE) of the character in question, except money.
        // Return as array.
        $stmt = "
            SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9 FROM character_inventory WHERE char_id = :charid
        ";
        $getInventory = $this->pdo->prepare($stmt);
        $getInventory->bindParam(':charid', $this->charid);
        if(!$getInventory->execute()) {
            die("err:Query failed, get inventory array.");
        } else {
            $output = $getInventory->fetch(\PDO::FETCH_ASSOC);
        }
        return $output;
    }

    function setDeleted()
    {
        $this->pdo->beginTransaction();
        try
        {
            $stmt = "
                        UPDATE character_inventory
                        SET deleted = 1 
                        WHERE char_id = ?
                ";
            $q = $this->pdo->prepare($stmt);
            if(!$q->execute([$this->charid]))
            {
                $this->pdo->rollBack();
                die("err:Could not delete inventory");
            }
            $stmt = "UPDATE character_storage SET deleted = 1 WHERE char_id = ?";
            $q = $this->pdo->prepare($stmt);
            if(!$q->execute([$this->charid]))
            {
                $this->pdo->rollBack();
                die("err:Could not delete inventory");
            }
            $stmt = "UPDATE crafters SET deleted = 1 WHERE char_id = ?";
            $q = $this->pdo->prepare($stmt);
            if(!$q->execute([$this->charid]))
            {
                $this->pdo->rollBack();
                die("err:Could not delete inventory");
            }
            $this->pdo->commit(); // Commit the deletion.
            return true;
        }
        catch(Exception $e)
        {
            $this->pdo->rollBack();
            die("err:Could not delete inventory.\n".$e->getMessage());
        }
        return false;
    }

    function getCharName()
    {
        $stmt = "SELECT titles FROM rp_tool_character_repository WHERE character_id = :id";
        $rptool = connectToRPTool();
        $run = $this->rptool->prepare($stmt);
        $run->bindParam(":id", $this->charid);
        if($run->execute())
        {
            $run = $run->fetch(\PDO::FETCH_ASSOC);
            $out;
			$arr = explode("=>", $data[1]);
			$out = $arr[0];
			return $out;
        }
        else
        {
            die("err:Could not get char_name.");
        }
    }

    function getInventoryNames($arr)
    {
        $out = "";
        $i = 1;
        $iids = "";
        $o = "";
        foreach($arr as $item)
        {
            $tmp = explode(":", $item);
            if($tmp[0] != "0")
            {
                $iids = $iids . $tmp[0];
                $o = $o . "?";
            }
            if($i > 9)
            {
                break;
            }
            else if($tmp[0] != "0")
            {
                $iids = $iids . ",";
                $o = $o . ",";
            }
            ++$i;
        }
        if($o == "")
        {
            return;
        }
        $iids = substr($iids, 0, -1) . "";
        $o = substr($o, 0, -1);
        $stmt = "
            SELECT id,name,texture_color FROM items
            WHERE id IN ($o)
        ";
        $getNames = $this->pdo->prepare($stmt);
        if(!$getNames->execute(explode(",", $iids)))
        {
            die('err:Could not get inventory names.');
        }
        $getNames = $getNames->fetchAll(\PDO::FETCH_ASSOC);
        //die(print_r($getNames));
        return array_2_psv($getNames);
    }

    function getItemDetails($itemId)
    {
        $stmt = "
            SELECT * FROM items WHERE id = :itemId
        ";
        $itemDetails = $this->pdo->prepare($stmt);
        $itemDetails->bindParam(":itemId", $itemId);
        if(!$itemDetails->execute())
        {
            // If query fails, tell me.
            die('err:itemDetails failed, personal inv.');
        }
        else
        {
            $itemDetails = $itemDetails->fetch(\PDO::FETCH_ASSOC);
        }
        if(!isset($itemDetails['id']))
        {
            die("err:Item with id {$itemId} does not exist.");
        }
        if($this->debug)
        {
            // Output what we're working with just for clarity's sake while debugging/developing.
            echo array_2_csv($itemDetails);
        }
        return $itemDetails;
    }

    function addItem()//addItem($itemId, $amount)
    {
        $args = func_get_args();
        $char;
        if(count($args) === 3)
        {
            $char = $args[2];
        }
        else
        {
            $char = $this->charid;
        }
        $itemId = $args[0];
        $amount = $args[1];
        //print_r($args);
        
        // Add item to the inventory, then get full inventory to return to the HUD.
        // Only add if there is an empty slot. If itemId already exists, add amount unless item max is exceeded.
        // First get all the details of the item id.
        $itemDetails = $this->getItemDetails($itemId);
        $getInventory = $this->getInventoryArray($char);
        //$charname = $this->getCharName($this->charid);
        // Now that we have the player's whole inventory...
        $addItem;
        $i = 1; // Track which item slot we're adding to.
        foreach($getInventory as $item)
        {
            $tmp = explode(":", $item); // Split result into an array...
            if($tmp[0] != "0") // If inventory slot is not empty...
            {
                if($tmp[0] == $itemId) // Compare itemId with the item ID of the inventory slot (should be $tmp[0])...
                {
                    $calcAmount = ($tmp[1] + $amount);
                    if($calcAmount > $itemDetails['max_stack'])
                    {
                        die("err:Could not give " . $amount . " " . $itemDetails['name'] . ". Will exceed recipient's maximum stack of " . $itemDetails['max_stack'] . ".");
                    }
                    $pos = "item_" . $i;
                    $stmt = "
                        UPDATE character_inventory
                        SET {$pos} = :data 
                        WHERE char_id = :charId
                    ";
                    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $idata = $itemId.":". $calcAmount . ":" . $itemDetails['texture_name'];
                    $addItem = $this->pdo->prepare($stmt);
                    $addItem->bindParam(":charId", $char);
                    $addItem->bindParam(":data", $idata);
                    if(!$addItem->execute())
                    {
                        die("err:Could not add item; please contact staff. Error: add-item-failure.");
                    }
                    break; // Exit the loop when done.
                }
            }
            else if($tmp[0] == "0")
            {
                if($amount > $itemDetails['max_stack'])
                {
                    $death = "err:Could not give " . $amount . " " . $itemDetails['name'] . ". Will exceed recipient's maximum stack of " . $itemDetails['max_stack'] . ".";
                    die($death);
                }
                $pos = "item_" . $i;
                $stmt = "
                        UPDATE character_inventory
                        SET {$pos} = :data 
                        WHERE char_id = :charId
                ";
                $idata = $itemId.":". $amount . ":" . $itemDetails['texture_name'];
                $addItem = $this->pdo->prepare($stmt);
                $addItem->bindParam(":charId", $this->charid);
                $addItem->bindParam(":data", $idata);
                if(!$addItem->execute())
                {
                    die("err:Could not add item; please contact staff. Error: add-new-item-failure.");
                }
                break;
            }

            ++$i;
            if($i>9)
            {
                die("err:You don't have any free item slots to add {$amount} of {$itemDetails['name']}");
            }
        }

        //$out = $this->getFullInventory($this->charid);
        return true;

    }

    function removeItem()//removeItem($itemId, $amount)
    {
        $args = func_get_args();
        $char;
        if(count($args) === 3)
        {
            $char = $args[2];
        }
        else
        {
            $char = $this->charid;
        }
        $itemId = $args[0];
        $amount = $args[1];
        //die($itemId . " and " . $amount);
        $itemDetails = $this->getItemDetails($itemId);
        $inventoryArray = $this->getInventoryArray($char);
        $i = 1; // Track which item slot we're adding to.
        foreach($inventoryArray as $item)
        {
            $tmp = explode(":", $item); // Split result into an array...
            if($tmp[0] != 0) // If inventory slot is not empty, find item ID.
            {
                if($tmp[0] == $itemId)
                {
                    // If item ID exists, remove items.
                    $calcAmount = ($tmp[1] - $amount);
                    if($calcAmount < 0)
                    {
                        // Don't nuke the inventory!
                        die("err:Cannot remove $amount of " . $itemDetails['name'] . "; you don't have enough.");
                    }
                    $pos = "item_" . $i;
                    $stmt = "
                        UPDATE character_inventory
                        SET $pos = :data
                        WHERE char_id = :charid
                    ";
                    $idata;
                    if($calcAmount == 0)
                    {
                        $idata = "0";
                    }
                    else
                    {
                        $idata = $itemId.":". $calcAmount . ":" . $itemDetails['texture_name'];
                    }
                    $addItem = $this->pdo->prepare($stmt);
                    $addItem->bindParam(":charid", $char);
                    $addItem->bindParam(":data", $idata);
                    if(!$addItem->execute())
                    {
                        die("err:Could not remove item; please contact staff. Error: remove-item-failure.");
                    }
                    break;
                }
            }
            ++$i;
        }
        return true;
    }

    function replaceItem($itemId, $amount, $slot)
    {
        // Replace an item slot in the inventory.

    }

    function getGatherCooldown()
    {
        $stmt = "
            SELECT next_gather,gather_attempts FROM character_inventory
            WHERE char_id = ?
        ";
        $getGather = $this->pdo->prepare($stmt);
        if($getGather->execute([$this->charid]))
        {
            $getGather = $getGather->fetch(\PDO::FETCH_ASSOC);
            $howLong = round((($getGather['next_gather'] - time()) / 3600), 1, PHP_ROUND_HALF_EVEN);
            if($getGather['gather_attempts'] == "0" and $howLong > 0)
            {
                return "cooldown:" . $howLong;
            }
            else
            {
                $max = $this->getSettings("max_gather_attempts");
                if($getGather['gather_attempts'] < $max and $howLong <= 0)
                {
                    $getGather['gather_attempts'] = $max;
                }
                return "gathers:" . $getGather['gather_attempts'];
            }
        }
        return false;
    }

    function calculateTradeTargetInventorySpace($targetChar, $itemId, $amount)
    {
        // Calculate inventory space to see if target character has enough space.
        $itemDetails = $this->getItemDetails($itemId);
        $inventoryArray = $this->getInventoryArray($targetChar);
        $i = 1; // Track which item slot we're adding to.
        $empty = 0;
        foreach($inventoryArray as $item)
        {
            $tmp = explode(":", $item); // Split result into an array...
            if($tmp[0] != 0) // If inventory slot is not empty, find item ID.
            {
                if($tmp[0] == $itemId)
                {
                    // If item ID exists, count items.
                    $calcAmount = ($tmp[1] + $amount);
                    if($calcAmount > $itemDetails['max_stack'])
                    {
                        die("err:Cannot give $amount of " . $itemDetails['name'] . "; they don't have enough room.");
                    }
                }
                else if($tmp[0] == 0)
                {
                    ++$empty;
                }
            }
            ++$i;
        }
        // If we got this far, we are gucci.
        // Make sure that there are empty slots to add to.
        if($empty < 0)
        {
            die("err:Cannot give $amount of " . $itemDetails['name'] . "; they have no inventory slots.");
        }
        // If we got here, we are good to return true!
        // Not that it REALLY matters as we're using die() to kill the whole program over the slightest error.
        return true;
    }

    function getTradeTarget($target)
    {
        $stmt = "SELECT lastchar FROM users WHERE uuid = ?";
        $qObj = connectToRptool();
        $q = $qObj->prepare($stmt);
        if(!$q->execute([$target]))
        {
            die("err:Could not get trade target.");
        }
        $q = $q->fetch();
        return $q[0];
    }
    function tradeItem($data, $target)
    {

    }
}
?>