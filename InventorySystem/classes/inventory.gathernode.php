<?php
if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}
include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});

class gathernode {
    private $pdo;
    private $rpT;
    private $debug = false;
    private $personalObj;
    private $user;
    private $character;
    private $characterJob;
    private $inventory;
    private $item;
    private $settings;
    function __construct($uuid, $itemId)
    {
        // Connect to the database and set up the object.
        try
        {
            $this->pdo = connectToInventory();
            if($this->debug) {
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        }
        catch(Exception $e)
        {
            die("err:" . $e->getMessage());
        }
        $this->settings = $this->getSettings();
        $this->item = $this->getItemDetails($itemId);
        $this->user = $this->getUserDetails($uuid);
        $this->character = $this->getCharDetails($this->user['lastchar']);
        $this->inventory = $this->getCurrentInventory($this->user['lastchar']);
        $this->characterJob = $this->chkPlayerJobs();
    }

    function arrayParse($array) { // Easy way to turn an array into a comma-separated string.
        $csv = array();
        foreach ($array as $item) {
            if (is_array($item)) {
                $csv[] = $this->arrayParse($item);
            } else {
                $csv[] = $item;
            }
        }
        return implode(':@:', $csv);
    }

    function dumpItemDetails()
    {
        return $this->arrayParse($this->item);
    }

    function getCharDetails($charId)
    {
        $this->rpT = connectToRptool();
        $stmt = "SELECT * FROM rp_tool_character_repository WHERE character_id = ?";
        $this->rpT->beginTransaction();
        try
        {
            $d = $this->rpT->prepare($stmt);
            $d->execute([$charId]);
            $d = $d->fetch(PDO::FETCH_ASSOC);
            $this->rpT->commit();
        } catch (Exception $e)
        {
            $this->rpT->rollBack();
            die("err:".$e->getMessage());
        }
        return $d;
    }

    function chkPlayerJobs()
    {
        $id = $this->character['character_id'];
        $stmt = "SELECT * FROM crafters
        WHERE char_id = ?;
        ";
        $do = $this->pdo->prepare($stmt);
        try
        {
            $do->execute([$id]);
        }
        catch (Exception $e)
        {
            exit("err:" . $e->getMessage());
        }
        return $do->fetch(PDO::FETCH_ASSOC);
    }
    function getCharName()
    {
        return explode("=>", $this->character['titles'])[0];
    }

    function writeLog($usr, $uid, $char, $module, $log, $name) 
    {
        // Simple function to create a log.
        $statement = "
            INSERT INTO logs
            VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
        ";
        $do = $this->pdo->prepare($statement);
        $do->bindParam(":usr", $usr);
        $do->bindParam(":uid", $uid);
        $do->bindParam("charid", $char);
        $do->bindParam(":charname", $name);
        $do->bindParam(":module", $module);
        $do->bindParam(":log", $log);
        if(!$do->execute())
        {
            $this->pdo->rollBack();
            die("err:Could not log action.");
        }
    }

    function getUserDetails($uuid)
    {
        $usrDB;
        try
        {
            $usrDB = connectToRptool();
            if($this->debug)
            {
                $usrDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        }
        catch (Exception $e)
        {
            die("err:".$e->getMessage());
        }
        $stmt = "SELECT * FROM users WHERE uuid = ?";
        try
        {
            $out = $usrDB->prepare($stmt);
            $out->execute([$uuid]);
            $out = $out->fetch(PDO::FETCH_ASSOC);
            if($this->debug)
            {
                // Print out the array while in debug mode, so we can observe what the fuck is happening.
                print_r($out);
            }
        }
        catch (Exception $e)
        {
            die("err:".$e->getMessage());
        }
        return $out;
    }

    function getCurrentInventory($charId)
    {
        $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,deleted,gather_attempts,next_gather FROM character_inventory WHERE char_id = ?";
        try
        {
            $out = $this->pdo->prepare($stmt);
            $out->execute([$charId]);
            $out = $out->fetch(PDO::FETCH_ASSOC);
            if($this->debug)
            {
                // Print out the array while in debug mode, so we can observe what the fuck is happening.
                print_r($out);
            }
        }
        catch(Exception $e)
        {
            die("err:".$e->getMessage());
        }
        return $out;
    }

    function getItemDetails($itemId)
    {
        $stmt = "SELECT * FROM items WHERE id = ?";
        try
        {
            $out = $this->pdo->prepare($stmt);
            $out->execute([$itemId]);
            $out = $out->fetch(PDO::FETCH_ASSOC);
            if($this->debug)
            {
                // Print out the array while in debug mode, so we can observe what the fuck is happening.
                print_r($out);
            }
        }
        catch (Exception $e)
        {
            die("err:".$e->getMessage());
        }
        return $out;
    }

    function getSettings()
    {
        // Gets all the settings from the database so we don't have to ping it constantly.
        $out;
        $stmt = "
            SELECT * FROM settings
        ";
        try
        {
            $getSet = $this->pdo->prepare($stmt);
            $getSet->execute();
            $out = $getSet->fetch(PDO::FETCH_ASSOC);
            if($this->debug)
            {
                // Print out the array while in debug mode, so we can observe what the fuck is happening.
                print_r($out);
            }
        } catch (Exception $e)
        {
            die("err:" . $e->getMessage());
        }
        return $out;
    }

    function randomFloat($successRate)
    {
        $max = 100.0;
        $result = mt_rand(0, $max);
        $calc = ($successRate - $result / $max);
        return $calc;
    }

    function howMuch()
    {
        $success = $this->randomFloat($this->item['gather_success_chance']);
        $leeway = -0.10;
        if($this->item['assoc_job'] === $this->characterJob['job'])
        {
            $exp = $this->characterJob['experience'];
            if($exp > $this->characterJob['exp_to_level'])
            {
                $exp = $this->characterJob['exp_to_level'];
            }
            $r = ($exp / $this->characterJob['max_level']);
            $r = "0." . $r;
            $leeway = $leeway - $r;
        }
        if($success > $leeway)
        {
            $min = 1;
            $max = $this->item['max_gather'];
            return random_int($min, $max);
        }
        else
        {
            return "0";
        }
    }

    function attemptGather()
    {
        if($this->inventory['gather_attempts'] <= 0 or $this->inventory['next_gather'] < time())
        {
            if($this->inventory['next_gather'] < time())
            {
                $newTime = time() + $this->settings['gathercooldown'];
                $this->pdo->beginTransaction();
                try
                {
                    $g = $this->settings['max_gather_attempts'];
                    $this->inventory['gather_attempts'] = $g;
                    $sth = $this->pdo->prepare("UPDATE character_inventory SET next_gather = $newTime, gather_attempts = $g WHERE char_id = {$this->user['lastchar']}");
                    if($sth->execute())
                    {
                        $this->pdo->commit();
                    }
                }
                catch (Exception $e)
                {
                    $this->pdo->rollBack();
                    die("err:".$e->getMessage());
                }
            }
            else
            {
                die("err:You don't have more gathering attempts remaining.");
            }
        }
        else
        {
            if($this->inventory['next_gather'] < time())
            {
                $newTime = time() + $this->settings['gathercooldown'];
            }
            else
            {
                $newTime = $this->inventory['next_gather'];
            }
        }
        $gthAm = ($this->inventory['gather_attempts'] - 1);
        if($gthAm < 0)
        {
            $gthAm = 0;
        }
        $out = array();
        $inv = array_slice($this->inventory,0,9); // Get the first nine entries of the inventory.
        $slot;
        foreach($inv as $arr)
        {
            if($arr === "0" and empty($slot))
            {
                $slot = array_keys($inv, $arr)[0];
            }
            else if(explode(":", $arr)[0] === $this->item['id'])
            {
                $slot = array_keys($inv, $arr)[0];
                break;
            }
        }
        if(empty($slot))
        {
            // If we're still empty, inventory is full.
            die("err:Inventory is full; cannot gather.");
        }
        $out[] = $slot;
        $out[] = $inv[$slot];
        $amount = $this->howMuch();
        // writeLog($usr, $uid, $char, $module, $log, $name) 
        if($out[1] === "0")
        {
            if($amount > 0)
            {
                $out[1] = $this->item['id'] . ":" . $amount . ":" . $this->item['texture_name'];
                // Add item!
                $this->pdo->beginTransaction();
                try
                {
                    $stmt = "UPDATE character_inventory
                    SET $out[0] = ?
                    WHERE char_id = ?;
                    ";
                    $do = $this->pdo->prepare($stmt);
                    $do->execute([$out[1],$this->user['lastchar']]);
                    $stmt = "UPDATE character_inventory
                    SET next_gather = $newTime, gather_attempts = $gthAm
                    WHERE char_id = {$this->user['lastchar']} 
                    ";
                    $doTwo = $this->pdo->prepare($stmt);
                    $doTwo->execute();
                    if($doTwo and $do)
                    {
                        $log = "Gathered {$amount}x {$this->item['name']} (ID: {$this->item['id']}).";
                        $this->writeLog($this->user['username'], $this->user['uuid'], $this->character['character_id'], "gather", $log, $this->getCharName());
                        $this->pdo->commit();
                    }
                    else
                    {
                        $this->pdo->rollBack();
                    }
                }
                catch(Exception $e)
                {
                    $this->pdo->rollBack();
                    die("err:".$e->getMessage());
                }
            }
            else
            {
                $this->pdo->beginTransaction();
                try
                {
                    $stmt = "UPDATE character_inventory
                    SET next_gather = $newTime, gather_attempts = $gthAm
                    WHERE char_id = {$this->user['lastchar']} 
                    ";
                    $do = $this->pdo->prepare($stmt);
                    if($do->execute())
                    {
                        $log = "Failed to gather {$this->item['name']} (ID: {$this->item['id']}).";
                        $this->writeLog($this->user['username'], $this->user['uuid'], $this->character['character_id'], "gather", $log, $this->getCharName());
                        $this->pdo->commit();
                    }
                    else
                    {
                        $this->pdo->rollBack();
                    }
                } catch (Exception $e)
                {
                    $this->pdo->rollBack();
                    die("err:".$e->getMessage());
                }
                die("err:You failed to gather " . $this->item['name'] . ". You have $gthAm attempts remaining.");
            }
        }
        else
        {
            if($amount < 1)
            {
                $this->pdo->beginTransaction();
                try
                {
                    $stmt = "UPDATE character_inventory
                    SET next_gather = $newTime, gather_attempts = $gthAm
                    WHERE char_id = {$this->user['lastchar']} 
                    ";
                    $do = $this->pdo->prepare($stmt);
                    if($do->execute())
                    {
                        $log = "Failed to gather {$this->item['name']} (ID: {$this->item['id']}).";
                        $this->writeLog($this->user['username'], $this->user['uuid'], $this->character['character_id'], "gather", $log, $this->getCharName());
                        $this->pdo->commit();
                    }
                    else
                    {
                        $this->pdo->rollBack();
                    }
                } catch (Exception $e)
                {
                    $this->pdo->rollBack();
                    die("err:".$e->getMessage());
                }
                die("err:You failed to gather " . $this->item['name'] . ". You have $gthAm attempts remaining.");
            }
            else
            {
                $tmp = explode(":", $out[1]);
                $tmpAm = $tmp[1] + $amount;
                if($tmpAm > $this->item['max_stack'])
                {
                    die("err:Failed to gather {$amount}x {$this->item['name']}; would exceed your maximum stack of {$this->item['max_stack']}.");
                }
                $tmp[1] = $tmpAm; // Update our array entry for the amount.
                $tmp[2] = $this->item['texture_name']; // Also update the texture.
                $out[1] = implode(":", $tmp);
                $this->pdo->beginTransaction(); // Start the transaction.
                try
                {
                    $stmt = "
                        UPDATE character_inventory
                        SET {$out[0]} = ?, next_gather = $newTime, gather_attempts = $gthAm
                        WHERE char_id = {$this->user['lastchar']}
                    ";
                    $do = $this->pdo->prepare($stmt);
                    if($do->execute([$out[1]]))
                    {
                        $log = "Gathered {$amount}x {$this->item['name']} (ID: {$this->item['id']}).";
                        $this->writeLog($this->user['username'], $this->user['uuid'], $this->character['character_id'], "gather", $log, $this->getCharName());
                        $this->pdo->commit();
                    }
                    else
                    {
                        $this->pdo->rollBack();
                    }
                } catch (Exception $e)
                {
                    $this->pdo->rollBack();
                    die("err:".$e->getMessage());
                }
            }
        }
        $out = array();
        $out[] = "success";
        $out[] = $amount;
        $out[] = $this->item['name'];
        $out[] = ($this->inventory['gather_attempts'] - 1);
        $out[] = ($this->inventory['next_gather']);
        return implode("::", $out);
    }
}
?>