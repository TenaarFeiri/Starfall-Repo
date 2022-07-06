<?php
    require_once('database.php');
    class management
    {
        private $charId;
        private $charName;
        private $user;
        private $pdo;
        private $inventory;
        private $rawInventory;
        private $corruption;
        private $dreamRot;
        private $money;
        private $itemDetails;
        private $selectedItem;
        private $module = "inventory";


        function __construct($charId, $usr)
        {
            $this->charId = $charId;
            $this->pdo = connectToInventory();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->charName = $this->getName();
            $this->user = $this->getUsrData($usr);
            $this->getInventory();
            if(_debug)
            {
                print_r($this->user);
            }
        }


        function writeLog($log)
        {
            $charName = $this->charName;
            // Write data to log database.
            $stmt = "
            INSERT INTO logs
            VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
            ";
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->bindParam(":usr", $this->user['username']);
                $do->bindParam(":uid", $this->user['uuid']);
                $do->bindParam(":charid", $this->charId);
                $do->bindParam(":charname", $charName);
                $do->bindParam(":module", $this->module);
                $do->bindParam(":log", $log);
                if(!$do->execute())
                {
                    exit("err:Could not log the following action: " . $log ."\nError array:\n". print_r($do->errorInfo()));
                }
                // Do nothing if successful.
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
        }


        function getUsrData($usr)
        {
            $stmt = "SELECT * FROM users WHERE username = :usr OR uuid = :usr";
            $rpt = connectToRptool();
            $do = $rpt->prepare($stmt);
            $do->bindParam(":usr", $usr);
            try
            {
                $do->execute();
                return $do->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
        }


        function getName()
        {
            $rpt = connectToRptool();
            $stmt = "SELECT titles FROM rp_tool_character_repository WHERE character_id = ?";
            try
            {
                $do = $rpt->prepare($stmt);
                $do->execute([$this->charId]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
            return explode("=>", $do['titles'])[0];
        }


        function getCorruption()
        {
            return "corruption:::" . implode(":::", $this->corruption);
        }


        function updateCorruption()
        {
            $stmt = "UPDATE character_inventory SET fog_corruption= ?, demon_corruption = ?, mana_corruption = ?";
            $this->pdo->beginTransaction();
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->execute([$this->corruption['fog_corruption'], $this->corruption['demon_corruption'], $this->corruption['mana_corruption']]);
                $this->pdo->commit();
            }
            catch(PDOException $e)
            {
                $this->pdo->rollBack();
                exit("err:" . $e->getMessage());
            }
        }


        function raiseCorruption($type, $value)
        {
            $key = "";
            if($type == "demon")
            {
                $key = "demon_corruption";
            }
            else if($type == "fog")
            {
                $key = "fog_corruption";
            }
            else if($type == "mana")
            {
                $key = "mana_corruption";
            }
            else
            {
                exit("Error: No corruption type or wrong corruption type defined. Type: " . $type);
            }
            $this->corruption[$key] = ($this->corruption[$key] + ($value + random_int(0, random_int(1, 10))));
            if($this->corruption[$key] > 100)
            {
                $this->corruption[$key] = 100;
            }
        }


        function lowerCorruption($type, $value)
        {
            $key = "";
            if($type == "demon")
            {
                $key = "demon_corruption";
            }
            else if($type == "fog")
            {
                $key = "fog_corruption";
            }
            else if($type == "mana")
            {
                $key = "mana_corruption";
            }
            else
            {
                exit("Error: No corruption type or wrong corruption type defined. Type: " . $type);
            }
            $this->corruption[$key] = ($this->corruption[$key] - ($value + random_int(0, random_int(1, 10))));
            if($this->corruption[$key] < 0)
            {
                $this->corruption[$key] = 0;
            }
        }


        function checkCondition()
        {
            $arr = array();
            $fog;
            if($this->corruption["fog_corruption"] < 10)
            {
                $fog = "You feel yourself to be in good condition.";
            }
            else if($this->corruption["fog_corruption"] >= 10 and $this->corruption["fog_corruption"] < 20)
            {
                $fog = "You feel a chill in the air, or maybe it's just you. Probably just a mild cold or something.";
            }
            else if($this->corruption["fog_corruption"] >= 20 and $this->corruption["fog_corruption"] < 30)
            {
                $fog = "You don't feel too hot. You have the sniffles, movement is painful and probably also the beginnings of a fever. Perhaps it's a trick of the light, but you would swear that your skin has turned somewhat paler.";
            }
            else if($this->corruption["fog_corruption"] >= 30 and $this->corruption["fog_corruption"] < 40)
            {
                $fog = "You feel terrible. Like a bad flu. You have definitely paled compared to before. Probably not the worst idea in the world to seek a healer.";
            }
            else if($this->corruption["fog_corruption"] >= 40 and $this->corruption["fog_corruption"] < 50)
            {
                $fog = "You feel better, but you can't help but notice that your claws and teeth have darkened somewhat. Your eyes have changed colour. You're not sure if your eye colour is fading or darkening, but there is a sickly green tinge to their colour all the same. Maybe someone else could confirm.";
            }
            else if($this->corruption["fog_corruption"] >= 50 and $this->corruption["fog_corruption"] < 60)
            {
                $fog = "You're feeling pretty great! But you're definitely changing. Your teeth and claws have visibly blackened, and your eyes are barely recognizable. You feel a growing pull towards the sea, as though something calls to you...";
            }
            else if($this->corruption["fog_corruption"] >= 60 and $this->corruption["fog_corruption"] < 70)
            {
                $fog = "Really, everything is great! You feel stronger than before, even, and if you are able to regenerate, it's certainly been kicked into overdrive. You've noticed your teeth and claws growing longer and terrible mood swings. There is also this sense of animalistic wildness within, now, and an apprehension towards people that may not have been there before...";
            }
            else if($this->corruption["fog_corruption"] >= 70 and $this->corruption["fog_corruption"] < 80)
            {
                $fog = "Besides feeling better and stronger than you ever have before, and even besides the awesome spikes or thorns that have begun growing out of your skin of late, some conscious part of must surely recognise the danger that you're in. You have many more feral impulses and often feel as though you lack control of yourself. Sometimes it's like your mind is stuck in a cage, watching the body act on its own. If you don't get help NOW or as soon as possible, you feel as though you'll lose yourself...";
            }
            else if($this->corruption["fog_corruption"] >= 80 and $this->corruption["fog_corruption"] < 90)
            {
                $fog = "You don't feel like you've grown stronger since last time, but most of the time, you don't really care. Your body has begun developing aquatic or pelagic features as mutation has set in, but you don't mind, most of the time. Largely because you're no longer really able to. You struggle to be sentient for long enough to ask for help.";
            }
            else if($this->corruption["fog_corruption"] >= 90 and $this->corruption["fog_corruption"] < 100)
            {
                $fog = "The mutations are out of control. Whatever consciousness remains within, struggles to recognise the body it inhabits. The Fog whispers to you, demands you serve it, demands you hunt the uncorrupted and bring them into the fold. You can resist, if you want, but the urge to obey may be overwhelming. You are suddenly much stronger and faster than before, and you feel magic welling within. More importantly, you feel your grip on yourself slipping. If you don't get help now, you know you will never be right again.";
            }
            else if($this->corruption["fog_corruption"] >= 100)
            {
                $fog = "You are become the Fog. You serve the Fog. Your body is no longer yours, but if you had any sense of yourself remaining, you'd probably not recognise it anyway. You are fully and irreversibly mutated, and you feel you have but a few purposes left in whatever you would call this life: HUNT. CONVERT. SACRIFICE. You are beyond redemption.";
            }
            $demon;
            $mana;
            if($this->corruption["mana_corruption"] < 10)
            {
                $mana = "";
            }
            $arr[] = $fog;
            $arr[] = $demon;
            $arr[] = $mana;
            return "condition::" . implode("::", $arr);
        }


        function getInventory()
        {
            $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,money,fog_corruption,demon_corruption,mana_corruption,dream_rot FROM character_inventory WHERE char_id = ?";
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->execute([$this->charId]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
                $this->corruption = array_slice($do, 10, 12);
                $this->money = $do['money'];
                $this->dreamRot = $do['dream_rot'];
                array_splice($do, 9);
                $this->rawInventory = $do;
                if(_debug)
                {
                    print_r($this->corruption);
                    echo PHP_EOL . $this->money . PHP_EOL;
                    print_r($do);
                }
                $this->inventory = array();
                foreach($do as $key => $var)
                {
                    if($var != 0 and $key != "char_id")
                    {
                        $var = explode(":", $var);
                        $var[] = $key; // Add the key to the array so I don't have to loop around and fucking find it. Hmph.
                        $this->inventory[$var[0]] = array_slice($var, 1);
                    }
                }
                $wildcards = $this->wildcards($this->inventory);
                $stmt = "SELECT * FROM items WHERE id IN ($wildcards)";
                $do = $this->pdo->prepare($stmt);
                $ids = array();
                foreach($this->inventory as $key => $var)
                {
                    $ids[] = $key;
                }
                $do->execute($ids);
                $do = $do->fetchAll(PDO::FETCH_ASSOC);
                foreach($do as $var)
                {
                    $this->itemDetails[$var['id']] = $var;//array_slice($var, 1);
                }
                if(_debug)
                {
                    print_r($this->itemDetails);
                }
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
        }


        function wildcards($arr)
        {
            $out = array();
            foreach($arr as $key => $val)
            {
                if($val != "0")
                {
                    $out[] = "?";
                }
            }
            return implode(",", $out);
        }


        function showData($itemId)
        {
            $out = array("echo");
            $item = $this->itemDetails[$itemId];
            $out[] = $item['name'];            
            $out[] = $item['description'];
            $out[] = $item['sell_price'];
            $out[] = $item['max_stack'];
            $out[] = $item['usable'];
            $out[] = $this->charName;
            return implode(":::", $out);
        }


        function strReplace($data, $itemId)
        {
            $search = array(
                "%name%",
                "%itemName%",
                "%effectLowToMid%",
                "%effectLowToHigh%",
                "%effectMidToHigh%",
                "%effectLow%",
                "%effectMid%",
                "%effectHigh%"
            );
            $replace = array(
                $this->charName,
                $this->itemDetails[$itemId]['name'],
                "",
                "",
                "",
                "",
                "",
                ""
            );
            return str_replace($search, $replace, $data);
        }


        function findInventoryDetails($itemId)
        {
            foreach($this->rawInventory as $key => $val)
            {
                $var = explode(":", $val);
                if($var[0] == $itemId)
                {
                    return [$key => $val];
                }
            }
            return false;
        }


        function useItem($itemId)
        {
            $item = $this->itemDetails[$itemId]; // Get all item details.
            if($item['usable'] == "0")
            {
                exit("err:Item is not usable.");
            }
            $inventory = $this->findInventoryDetails($itemId);
            $key;
            foreach($inventory as $k => $val)
            {
                $key = $k;
            }
            if(!$inventory)
            {
                exit("err:Item does not exist in your inventory.");
            }
            if(_debug)
            {
                echo $key . PHP_EOL;
                echo PHP_EOL . "Use item:" . PHP_EOL;
                print_r($inventory);
            }
            $useFunc = explode(":", $item['use_effect']);
            $inventory = explode(":", $inventory[$key]); // Create a new array. ID, Amount, Texture
            $out = "";
            $this->pdo->beginTransaction();
            foreach($useFunc as $var)
            {
                $var = explode(",", $var);
                if($var[0] == "destroy")
                {
                    if($inventory[1] < $var[1])
                    {
                        exit("err:You need " . $var[1] . " or more to use this.");
                    }
                    $inventory[1] = ($inventory[1] - $var[1]);
                    if($inventory[1] <= 0)
                    {
                        $inventory = array("0");
                    }
                    $inventory = implode(":", $inventory);
                    $stmt = "UPDATE character_inventory SET $key = ? WHERE char_id = $this->charId";
                    $do = $this->pdo->prepare($stmt);
                    try
                    {
                        $do->execute([$inventory]);
                        $log = "Used and consumed " . $var[1] . "x " . $item['name'] . "(ID: " . $item['id'] . ").";
                        $this->writeLog($log);
                        $out = $out . "useditem::";
                    }
                    catch(PDOException $e)
                    {
                        $this->pdo->rollBack();
                        exit("err:A database error has occurred. Could not use item ID $itemId. Fail point: destroy");
                    }
                }
                else if($var[0] == "effect")
                {
                    $stmt = "SELECT * FROM items_effects WHERE id = ?";
                    $do = $this->pdo->prepare($stmt);
                    try
                    {
                        $do->execute([$var[1]]);
                        $do = $do->fetch(PDO::FETCH_ASSOC);
                        $out = $out . "tattle::" . $this->strReplace($do['effect_out'], $itemId) . "::";
                    }
                    catch(PDOException $e)
                    {
                        $this->pdo->rollBack();
                        exit("err:A database error has occurred. Could not get effect id " . $var[1]);
                    }
                }
                else if($var[0] == "raiseCorruption")
                {
                    if($var[1] != "demon" and $var[1] != "mana" and $var[1] != "fog")
                    {
                        exit("err:Corruption value defined, but type is neither fog, demon or mana.");
                    }
                    $amount = $var[2];
                    $this->raiseCorruption($corruption, $amount);
                    $this->updateCorruption();
                    $out = $out . "corruption::You feel something change within.::";
                }
            }
            $this->pdo->commit(); // Commit at the end of the loop!
            return $out . "::charname::" . $this->charName;
        }


        function destroyItem($itemId, $amount)
        {
            $item = $this->itemDetails[$itemId]; // Get all item details.
            $inventory = $this->findInventoryDetails($itemId);
            if(!$inventory)
            {
                exit("err:Item does not exist in your inventory.");
            }
            $key;
            foreach($inventory as $k => $val)
            {
                $key = $k;
            }
            $originalAmount = $invData[1];
            $invData = explode(":", $inventory[$key]); // Inventory data for the thing!
            $invData[1] = ($invData[1] - $amount);
            if($invData[1] < 0)
            {
                exit("err:You cannot destroy this many. You only have " . $originalAmount . ".");
            }
            else if($invData[1] == 0)
            {
                $invData = array("0");
            }
            $invData = implode(":", $invData);
            if(_debug)
            {
                print_r($invData);
            }
            $stmt = "UPDATE character_inventory SET $key = ? WHERE char_id = $this->charId;";
            $this->pdo->beginTransaction();
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->execute([$invData]);
                $log = "Destroyed " . $amount . "x " . $item['name'] . " (ID: " . $item['id'] . ").";
                $this->writeLog($log);
                $this->pdo->commit();
                return $this->charName . "::" . $item['name'] . "::" . $amount;
            }
            catch(PDOException $e)
            {
                $this->pdo->rollBack();
                exit("err:Could not successfully destroy the item.");
            }
        }


        function tradeItem($itemId, $amount, $target)
        {

        }
    }
?>
