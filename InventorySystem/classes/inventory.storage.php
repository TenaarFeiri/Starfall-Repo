<?php
if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}
include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});

class storage {
    private $pdo;
    private $debug = false;
    private $charId;
    private $uuid;
    function __construct($character, $uuid) 
    {
        // Open the database connection when object is created.
        $this->pdo = connectToInventory();
        if($this->debug) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        if(!$this->pdo) {
            die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
        } else {
            if($this->debug)
            {
                echo "Connected to DB.\n\n";
            }
        }
        $this->charId = $character;
        $this->uuid = $uuid;
        if(!$this->verifyUsr())
        {
            die("err:This is not your character.");
        }
    }

    function returnCharId()
    {
        return $this->charId;
    }

    function verifyUsr()
    {
        $stmt = "
            SELECT rp_tool.users.id FROM rp_tool.users WHERE rp_tool.users.uuid = ?
            UNION ALL
            SELECT rp_tool.rp_tool_character_repository.user_id FROM rp_tool.rp_tool_character_repository WHERE rp_tool.rp_tool_character_repository.character_id = ?
        ";

        $vrf = $this->pdo->prepare($stmt);
        if(!$vrf->execute([$this->uuid, $this->charId]))
        {
            die("err:Could note execute storage verify query.");
        }
        $vrf = $vrf->fetchAll();
        if(count($vrf) !== 2)
        {
            die("err:This character does not exist.");
        }
        else if($vrf[0][0] === $vrf[1][0])
        {
            // If the ID is the same, return true!
            return true;
        }
        return false;
    }

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

    function getFullStorage($page)
    {
        $firstNum;
        $lastNum = 9;
        if($page === 1) {
            $firstNum = 0;
        } else {
            $firstNum = 9 * $page - 9;
        }
        $stmt = "
            SELECT *
            FROM character_storage
            WHERE char_id = ?
            ORDER BY num ASC LIMIT $firstNum, $lastNum
        ";
        $chk = $this->pdo->prepare($stmt);
        if(!$chk->execute([$this->charId]))
        {
            // If we fail to execute, die.
            die("err:Could not verify existence of storage space.");
        }
        // Pass that, fetch result.
        $chk = $chk->fetchAll(); // Grab all results & parse to array.
        if(!$chk)
        {
            // If empty, just return false! Handler will explain to the end user.
            return FALSE;
        }
        // Otherwise, format, parse, get item data and return to handler.
        $out = "overview:";
        foreach($chk as $var)
        {
            // Parse each column of the row into a variable.
            // Include item_texture for future-proofing with HUD elements.
            $out = $out . $var['item_id'] . ":" . $var['item_name'] . ":" . $var['amount'] . ":" . $var['item_texture'] . "=>";
        }
        // No point in deleting the last => at the end of the string, the parsing script will just ignore it.
        // Return this to the handler, which will output it back to the client.
        return $out;
    }

    function getItemDetailsArray($arr)
    {
        if(!is_array($arr))
        {
            // This fails immediately if variable is not an array, as we need to reserve the ability to call multiple rows at once.
            die("err:NOT AN ARRAY!!!". '\n' . "$arr");
        }
        // Overcomplicated way of parsing an array into a prepared SQL statement but whatever.
        $stmt = "
            SELECT id,name,description,type,max_stack,texture_name,texture_color,craftable,crafting_mats
            FROM items
            WHERE id IN (?);
        ";
        $arr = $this->array_2_csv($arr); // Become a comma-separated value string! That's right; we're cheating!
        $out = $this->pdo->prepare($stmt);
        if(!$out->execute([$arr]))
        {
            die("err:Storage could not get item details for item ids: $arr");
        }
        // From here on out, we're assuming the query was a success.
        
        // Future code for parse and return here, as needed.
    }

    function storeItem($itemId, $amount)
    {
        $details = $this->getItemDetails($itemId);
        if(!$details)
        {
            die("err:Item with ID $itemId does not exist.");
        }
        $slot = $this->findInventoryItem($itemId, true);
        if(!$slot)
        {
            die("err:You do not have this item in your inventory.");
        }
        $whichSlot = "item_".$slot;
        $inventorySlot = $this->getInventoryItemDetails($slot);
        $var = explode(":", $inventorySlot[0]);
        if($var[0] === "0")
        {
            $var = "0";
        }
        else
        {
            $var[1] = ($var[1] - $amount);
            if($var[1] < 0)
            {
                die("err:You are trying to deposit more than you have.");
            }
            else if($var[1] === 0)
            {
                $var = "0";
            }
            else
            {
                $var = implode(":", $var);
            }
        }
        if($this->checkItem($itemId))
        {
            // If item is already stored, perform an UPDATE operation.
            // Also make sure we are increasing the amount stored.
            $storedDetails = $this->getStoredItemDetails($itemId);
            if($this->debug)
            {
                print_r($storedDetails);
            }
            $stmt = "
                UPDATE character_storage
                SET amount = ?, item_name = ?
                WHERE num = ?;
                UPDATE character_inventory
                SET $whichSlot = ?
                WHERE char_id = ?;
            ";
            $num = ($storedDetails['amount'] + $amount);
            $store = $this->pdo->prepare($stmt);
            if(!$store->execute([
                $num,
                $details['name'],
                $storedDetails['num'],
                $var,
                $this->charId
                ]))
            {
                die("err:Could not add amount to database for character $this->charId, entry ". $storedDetails['num']);
            }
            else
            {
                return "success:".$details['name'].":".$amount;
            }
        }
        else
        {
            // Otherwise we are doing an INSERT operation.
            $stmt = "
                INSERT INTO character_storage
                (char_id,item_id,item_name,item_texture,amount) VALUES (?,?,?,?,?);
                UPDATE character_inventory
                SET $whichSlot = ?
                WHERE char_id = ?;
            ";
            $store = $this->pdo->prepare($stmt);
            if(!$store->execute([
                $this->charId, 
                $details['id'], 
                $details['name'], 
                $details['texture_name'], 
                $amount,
                $var,
                $this->charId
                ]))
            {
                die("err:Could not execute storeItem statement.");
            }
            else
            {
                return "success:".$details['name'].":".$amount;
            }
        }
        return "failed:".$details['name'].":".$amount;
    }

    function withdrawItem($itemId, $amount)
    {
        $details = $this->getItemDetails($itemId);
        if(!$details)
        {
            die("err:Item with ID $itemId does not exist.");
        }
        else if(!$this->checkItem($itemId))
        {
            die("err:You have not stored this item yet.");
        }
        // Passing these checks, we've verified that the item exists.
        $slot = $this->findInventoryItem($itemId, false);
        $whichSlot = "item_".$slot;
        $inventorySlot = $this->getInventoryItemDetails($slot);
        $item = $this->getStoredItemDetails($itemId);
        $newStoredAmount = ($item['amount'] - $amount);
        if($newStoredAmount < 0)
        {
            die("err:Cannot withdraw more than you have.");
        }
        if($this->debug)
        {
            echo "\n";
            print_r($item);
            echo "\n\n";
            print_r($inventorySlot);
        }
        $var = explode(":", $inventorySlot[0]);
        if($var[0] === "0")
        {
            if($amount > $details['max_stack'])
            {
                die("err:Withdrawing this amount would cause your inventory stack to exceed the maximum size of ".$details['max_stack']);
            }
            $var = $itemId.":0:".$details['texture_name'];
            $var = explode(":", $var);
        }
        else if(($var[1] + $amount) > $details['max_stack'])
        {
            die("err:Withdrawing this amount would cause your inventory stack to exceed the maximum size of ".$details['max_stack']);
        }
        $var[1] = ($var[1] + $amount);
        $var = implode(":", $var);
        if($slot === 0)
        {
            die("err:Attempted to access inventory slot 0. This should not happen; please contact admin.");
        }
        else
        {
            if($newStoredAmount === 0)
            {
                $stmt = "
                    DELETE FROM character_storage WHERE num = ?;
                    UPDATE character_inventory SET $whichSlot = ? WHERE char_id = ?;
                ";
                $upd = $this->pdo->prepare($stmt);
                if(!$upd->execute([$item['num'], $var, $this->charId]))
                {
                    die("err:Could not execute withdrawal statement nSAm is 0.");
                }
                else
                {
                    return true;
                }
            }
            else
            {
                $stmt = "
                    UPDATE character_storage SET amount = ?, item_name = ? WHERE num = ?;
                    UPDATE character_inventory SET $whichSlot = ? WHERE char_id = ?;
                ";
                $upd = $this->pdo->prepare($stmt);
                if(!$upd->execute([$newStoredAmount, $details['name'], $item['num'], $var, $this->charId]))
                {
                    die("err:Could not execute withdrawal statement nSAm not 0.");
                }
                else
                {
                    return true;
                }
            }
        }
        
        return false;
    }

    function checkItem($itemId)
    {
        // Verify that item exists.
        $stmt = "SELECT num FROM character_storage WHERE (item_id,char_id) = (?,?)";
        $chk = $this->pdo->prepare($stmt);
        if(!$chk->execute([$itemId,$this->charId]))
        {
            // Return false if query doesn't execute.
            return false;
        }
        else
        {
            // If we've succeeded, check if item exists.
            $chk = $chk->fetch(PDO::FETCH_NUM);
            if(!$chk)
            {
                // If empty, return false!
                return false;
            }
        }
        // Passing all of the above, return true. We found it!
        return true;
    }

    function findInventoryItem($itemId, $storing)
    {
        // Function to find the amount of itemId in player's current inventory, if any.
        $stmt = "SELECT item_1, item_2, item_3, item_4, item_5, item_6, item_7, item_8, item_9 FROM character_inventory WHERE char_id = ?";
        $chk = $this->pdo->prepare($stmt);
        if(!$chk->execute([$this->charId]))
        {
            // If this fails, kill program.
            die("err:Could not execute findInventoryItem query.");
        }
        $rows = $chk->columnCount();
        $chk = $chk->fetch(PDO::FETCH_ASSOC);
        $num = 1;
        $naught = 0;
        foreach($chk as $var)
        {
            $arr = explode(":", $var);
            if($arr[0] === $itemId)
            {
                // If it exists, save us some trouble by returning the item's slot.
                return $num;
            }
            if($naught === 0)
            {
                if($arr[0] === "0")
                {
                    // Record the first available slot number that's empty.
                    $naught = $num;
                }
            }
            ++$num;
        }
        // If we're here, we couldn't find it in the inventory.
        if($naught > $rows)
        {
            // If naught is bigger than rows, go zero.
            // If this is zero, we know something went fucky as this should never happen.
            $naught = 0;
        }
        if($storing)
        {
            return false;
        }
        return $naught;
    }

    function getInventoryItemDetails($slot)
    {
        $item = "item_".$slot;
        $stmt = "SELECT $item FROM character_inventory WHERE char_id = ?";
        $chk = $this->pdo->prepare($stmt);
        if(!$chk->execute([$this->charId]))
        {
            die("err:Could not execute getInventoryItemDetails query.");
        }
        return $chk->fetch();
    }

    function getStoredItemDetails($itemId)
    {
        // Get the details of a stored item.
        $stmt = "SELECT * FROM character_storage WHERE (item_id,char_id) = (?,?)";
        $gSiD = $this->pdo->prepare($stmt);
        if(!$gSiD->execute([$itemId, $this->charId]))
        {
            die("err:Could not get stored item details for item id $itemId");
        }
        return $gSiD->fetch(PDO::FETCH_ASSOC);
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
            $itemDetails = $itemDetails->fetch(PDO::FETCH_ASSOC);
        }
        if(!isset($itemDetails['id']))
        {
            die("err:Item with id {$itemId} does not exist.");
        }
        if($this->debug)
        {
            // Output what we're working with just for clarity's sake while debugging/developing.
            print_r($itemDetails);
        }
        return $itemDetails;
    }

    function searchForItem($searchTerm)
    {
        // Searching function.
        $stmt = "
            SELECT num,item_id,item_name,amount FROM character_storage WHERE MATCH(item_name) AGAINST (:searchterm) AND char_id = :charid ORDER BY MATCH(item_name) AGAINST (:searchterm) LIMIT 9;
        ";
        $dbsrch = $this->pdo->prepare($stmt);
        $dbsrch->bindParam(":searchterm", $searchTerm);
        $dbsrch->bindParam(":charid", $this->charId);
        if(!$dbsrch->execute())
        {
            die("err:Could not search!");
        }
        $dbsrch = $dbsrch->fetchAll();
        if(!$dbsrch)
        {
            return "empty";
        }
        $arr = array();
        foreach($dbsrch as $var)
        {
            $arr = array_merge($arr, [$var['num'].":".$var['item_id'].":".$var['item_name'].":".$var['amount']]);
        }
        //print_r($arr);
        $out = "search:" . implode("=>", $arr);
        return $out;
    }
    
}
?>