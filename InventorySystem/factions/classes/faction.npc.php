<?php
spl_autoload_register(function ($name) {
    include 'faction.' . $name . '.php';
});
class npc extends status
{
    private $npcData;
    private $npcPlayerDataArray;
    private $npcActionArr;
    private $module = "npc action";
    function __construct($usr, $npcId)
    {
        $this->npcData = $this->getNpcData($npcId); // Get data on the NPC.
        parent::__construct($usr); // Provide parent constructor with username or uuid so it can instantiate properly.
        if(_debug)
        {
            print_r($this->character);
            print_r($this->npcData);
        }
    }

    function getNpcData($npcId)
    {
        parent::connect("inventory");
        $stmt = "SELECT * FROM npc_table WHERE npc_id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$npcId]);
            return $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:Cannot execute getNpcData.\n\n" . $e->getMessage());
        }
    }

    function getBlurb($blurbId)
    {
        parent::connect("inventory");
        $stmt = "SELECT * FROM blurbs WHERE id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$blurbId]);
            return $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:Cannot execute getBlurb.\n\n" . $e->getMessage());
        }
    }

    function executeAction($actionList)
    {
        $this->npcActionArr = explode(",", $actionList); // Store actions in $npcActionArr
        $action = $this->npcActionArr[0]; // First entry will always be action.
        parent::connect("inventory"); // Connect to inventory, just in case it's not already done.
        /*
            Actions list:
            VENDORS
                - viewGoods (page, optional faction req)
                - viewItem (itemId)
                - sellItem (itemId, amount)
                - buyItem (itemId, amount)
                - showBlurb (blurbId)
            
            FACTION DEPOSIT
                - viewDemand (page, faction)
                - donateGoods (itemId, amount)
                - showBlurb (blurbId)
        */
        if($action === "showBlurb") // Catch showBlurb first!
        {   
            // Code here to get the blurb ID and associated options.
            if($this->npcActionArr[1] == 0)
            {
                // If zero, get the default opening blurb from the npc_table.
                $blurb = $this->getBlurb($this->npcData['opening_blurb']);
                if(_debug)
                {
                    print_r($blurb);
                }
                $blurb['blurb_text'] = $this->wildCardReplace($blurb['blurb_text']);
                return $this->npcData['npc_name'] . "&&" . $blurb['blurb_text'] . "&&" . $blurb['choices'] . "&&" . $blurb['choice_data'] . "&&" . $this->wildCardReplace($blurb['emote']);
            }
            $blurb = $this->getBlurb($this->npcActionArr[1]);
            if(_debug)
            {
                print_r($blurb);
            }
            $blurb['blurb_text'] = $this->wildCardReplace($blurb['blurb_text']);
            return $this->npcData['npc_name'] . "&&" . $blurb['blurb_text'] . "&&" . $blurb['choices'] . "&&" . $blurb['choice_data'] . "&&" . $this->wildCardReplace($blurb['emote']);
        }
        else if($action === "viewGoods") // VIEW GOODS
        {
            // Viewing goods available to that NPC!
            if(count($this->npcActionArr) < 2)
            {
                exit("err:Not enough parameters for viewGoods.");
            }
            else if($this->npcData['vendor'] != 1)
            {
                exit("err:NPC is not an active vendor.");
            }
            else if(count($this->npcActionArr) > 2)
            {
                // Faction check!
                $faction = $this->npcActionArr[2];
                if(!is_numeric($faction))
                {
                    exit("err:Faction variable is not numeric.");
                }
                $factionInfo = parent::getFactionInfo($faction);
                if(_debug)
                {
                    print_r($factionInfo);
                }
                if(!$this->character['charFaction'] or $this->character['charFaction']['char_faction'] != $faction)
                {
                    exit("err:Sorry, you're not a " . $factionInfo['factionData']['pronoun'] . " and can't use my services.");
                }
            }
            $stmt = "SELECT items_list FROM vendor_tables WHERE id = ?";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->npcData['vendor_table_row']]);
                $shopArr = $do->fetch(PDO::FETCH_ASSOC);
                $shopArr = explode(",", $shopArr['items_list']);
            }
            catch(PDOException $e)
            {
                exit("err:Could not get items list from vendor table.\n\n" . $e->getMessage());
            }
            if(_debug)
            {
                echo "shopArr\n\n";
                print_r($shopArr);
            }
            $page = $this->npcActionArr[1];
            if($page < 1)
            {
                $page = 1;
            }
            $firstNum;
            $lastNum = 9;
            if($page === 1) {
                $firstNum = 0;
            } else {
                $firstNum = 9 * $page - 9;
            }
            $shopArr = array_slice($shopArr, $firstNum, $lastNum);
            $wilds = parent::createPdoWildcards($shopArr);
            $stmt = "SELECT id,name FROM items WHERE id IN ($wilds)";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute($shopArr);
                $do = $do->fetchAll(PDO::FETCH_ASSOC);
                if(_debug)
                {
                    print_r($do);
                }
            }
            catch(PDOException $e)
            {
                exit("err:Could not get names from items table.\n\n" . $e->getMessage());
            }
            $nameArr = array();
            $idArr = array();
            foreach($do as $var)
            {
                $idArr[] = $var['id'];
                $nameArr[] = $var['name'];
            }
            return implode(",", $idArr) . "&&" . implode(",", $nameArr);
        }
        else if($action == "viewItem")
        {
            if(count($this->npcActionArr) < 2)
            {
                exit("err:Not enough parameters for viewItem.");
            }
            else if(count($this->npcActionArr) > 2)
            {
                // Faction check!
                $faction = $this->npcActionArr[2];
                if(!is_numeric($faction))
                {
                    exit("err:Faction variable is not numeric.");
                }
                $factionInfo = parent::getFactionInfo($faction);
                if(_debug)
                {
                    print_r($factionInfo);
                }
                if(!$this->character['charFaction'] or $this->character['charFaction']['char_faction'] != $faction)
                {
                    exit("err:Sorry, you're not a " . $factionInfo['factionData']['pronoun'] . " and can't use my services.");
                }
            }
            else if(!is_numeric($this->npcActionArr[1]))
            {
                exit("err:viewItem id parameter is not a valid numeric.");
            }
            $item = $this->getItemDetails($this->npcActionArr[1]);
            $out = "" . $item['name'] . "\n\n" .
            "" . $item['description'] . "\n\n" .
            "Buy price: " . $item['vendor_value'] . "\n" .
            "Sell price: " . $item['sell_price'] . "\n" . 
            "Maximum stack: " . number_format($item['max_stack']);
            return $out;
        }
        else if($action == "buyItem")
        {
            if(count($this->npcActionArr) < 3)
            {
                exit("err:Not enough parameters for buyItem.");
            }
            else if(count($this->npcActionArr) > 3)
            {
                // Faction check!
                $faction = $this->npcActionArr[3];
                if(!is_numeric($faction))
                {
                    exit("err:Faction variable is not numeric.");
                }
                $factionInfo = parent::getFactionInfo($faction);
                if(_debug)
                {
                    print_r($factionInfo);
                }
                if(!$this->character['charFaction'] or $this->character['charFaction']['char_faction'] != $faction)
                {
                    exit("err:Sorry, you're not a " . $factionInfo['factionData']['pronoun'] . " and can't use my services.");
                }
            }
            $item = $this->getItemDetails($this->npcActionArr[1]);
            if($item['vendor_value'] === "Not for sale.")
            {
                exit("err:Sorry; " . $item['name'] . " is currently not for sale.");
            }
            $this->module = "npc buyItem";
            $wilds = parent::createPdoWildcards([$this->character['charData']['character_id']]);
            $inventory = parent::getMultipleUsrInventories([$this->character['charData']['character_id']], $wilds, false)[$this->character['charData']['character_id']];
            $money = parent::getMultipleUsrInventories([$this->character['charData']['character_id']], $wilds, true)[$this->character['charData']['character_id']]['money'];
            $slot = $this->findInInventory($inventory, $item, $this->npcActionArr[2], false);
            $slotArr = explode(":", $inventory[$slot]);
            $stmt = "UPDATE character_inventory SET $slot = ?, money = ? WHERE char_id = ?";
            $buyTotal = ($item['vendor_value'] * $this->npcActionArr[2]);
            if($buyTotal > $money)
            {
                exit("err:You don't have enough money to buy this! Your total is " . number_format($buyTotal) . " Crowns, but you only have " . number_format($money) .".");
            }
            if($slotArr[0] == "0")
            {
                $slotArr[0] = $item['id'];
                $slotArr[1] = $this->npcActionArr[2];
                $slotArr[2] = $item['texture_name'];
            }
            else
            {
                if($slotArr[1] + $this->npcActionArr[2] <= $item['max_stack'])
                {
                    $slotArr[1] = ($slotArr[1] + $this->npcActionArr[2]);
                }
            }
            if(_debug)
            {
                print_r($item);
                print_r($inventory);
                print_r($slotArr);
                echo $money;
            }
            $slotArr = implode(":", $slotArr);
            $do = $this->invPdo->prepare($stmt);
            $this->invPdo->beginTransaction();
            try
            {
                $money = ($money - $buyTotal);
                if($money < 0)
                {
                    exit("err:(Negative money value. Please report problem to admins as this shouldn't happen.)");
                }
                $do->execute([$slotArr, $money, $this->character['charData']['character_id']]);
                $log = "Purchased " . $this->npcActionArr[2] . "x " . $item['name'] . " (ID: " . $item['id'] . ") for " . $buyTotal . " money.";
                parent::writeLog($this->module, $log);
                $this->invPdo->commit();
            }
            catch(PDOException $e)
            {
                $this->invPdo->rollBack();
                exit("err:Could not complete purchase.");
            }
            return "purchase&&success&&" . $this->npcActionArr[2] . "&&" . $item['name'];
        }
        else if($action == "sellItem")
        {
            if(count($this->npcActionArr) < 3)
            {
                exit("err:Not enough parameters for sellItem.");
            }
            else if(count($this->npcActionArr) > 3)
            {
                // Faction check!
                $faction = $this->npcActionArr[3];
                if(!is_numeric($faction))
                {
                    exit("err:Faction variable is not numeric.");
                }
                $factionInfo = parent::getFactionInfo($faction);
                if(_debug)
                {
                    print_r($factionInfo);
                }
                if(!$this->character['charFaction'] or $this->character['charFaction']['char_faction'] != $faction)
                {
                    exit("err:Sorry, you're not a " . $factionInfo['factionData']['pronoun'] . " and can't use my services.");
                }
            }
            $item = $this->getItemDetails($this->npcActionArr[1]);
            if($item['sell_price'] == "Won't buy.")
            {
                exit("err:Sorry; I'm not currently buying this item.");
            }
            $this->module = "npc sellItem";
            $wilds = parent::createPdoWildcards([$this->character['charData']['character_id']]);
            $inventory = parent::getMultipleUsrInventories([$this->character['charData']['character_id']], $wilds, false)[$this->character['charData']['character_id']];
            $money = parent::getMultipleUsrInventories([$this->character['charData']['character_id']], $wilds, true)[$this->character['charData']['character_id']]['money'];
            $slot = $this->findInInventory($inventory, $item, $this->npcActionArr[2], true); // True bc we're selling.
            if(!$slot)
            {
                exit("err:Sorry; you don't seem to have any of this item to sell.");
            }
            else
            {
                $arr = explode(":", $inventory[$slot]);
                if($arr[1] - $this->npcActionArr[2] < 0)
                {
                    exit("err:Sorry; you don't seem to have enough of this item to sell. You're trying to sell " . $this->npcActionArr[2] . " but you only have " . $arr[1] .".");
                }
                else
                {
                    $arr[1] = ($arr[1] - $this->npcActionArr[2]);
                    if($arr[1] == 0)
                    {
                        $arr = "0";
                    }
                    else
                    {
                        $arr = implode(":", $arr);
                    }
                }
            }
            $total = ($item['sell_price'] * $this->npcActionArr[2]);
            $money = ($money + $total);
            if(_debug)
            {
                print_r($inventory);
                echo PHP_EOL . $slot . PHP_EOL;
                print_r($arr);
                echo PHP_EOL;
                echo $money;
                echo PHP_EOL;
            }
            $stmt = "UPDATE character_inventory SET $slot = ?, money = ? WHERE char_id = ?";
            $do = $this->invPdo->prepare($stmt);
            $this->invPdo->beginTransaction();
            try
            {
                $do->execute([$arr, $money, $this->character['charData']['character_id']]);
                $log = "Sold " . $this->npcActionArr[2] . "x " . $item['name'] . " (ID: " . $item['id'] . ") for " . $total . " money.";
                parent::writeLog($this->module, $log);
                $this->invPdo->commit();
            }
            catch(PDOException $e)
            {
                $this->invPdo->rollBack();
                exit("err:Could not complete purchase.");
            }
            return "sale&&success&&" . $this->npcActionArr[2] . "&&" . $item['name'] . "&&" . $total;
        }
    }

    function findInInventory($inventory, $item, $amount, $selling)
    {
        $out = "";
        foreach($inventory as $key => $val)
        {
            if($out == "" and $val == "0")
            {
                $out = $key; // Make this the position in the inventory
            }
            $list = explode(":", $val);
            if($list[0] == $item['id'])
            {
                $total = ($list[1] + $amount);
                if($total > $item['max_stack'] and !$selling)
                {
                    exit("err:You don't have enough space for this purchase. You can carry a maximum of " . $item['max_stack'] . " and this purchase would bring you to $total.");
                }
                return $key;
            }
        }
        if($amount > $item['max_stack'] and !$selling)
        {
            exit("err:You don't have enough space for this purchase. You can carry a maximum of " . $item['max_stack'] . " and this purchase would bring you to $amount.");
        }
        if($selling)
        {
            $out = false;
        }
        return $out;
    }

    function getItemDetails($itemId)
    {
        $stmt = "SELECT id,name,description,vendor_value,sell_price,max_stack,texture_name FROM items WHERE id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$this->npcActionArr[1]]);
            $do = $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:" . $e->getMessage());
        }
        if($do['vendor_value'] == "0")
        {
            $do['vendor_value'] = "Not for sale.";
        }
        else
        {
            $do['vendor_value'] = number_format($do['vendor_value']);
        }
        if($do['sell_price'] == "0")
        {
            $do['sell_price'] = "Won't buy.";
        }
        else
        {
            $do['sell_price'] = number_format($do['sell_price']);
        }
        return $do;
    }

    function wildCardReplace($data)
    {
        // This function looks for wildcards in the string to replace things with!
        $wildcards = array(
            "%name%", // Character's name.
            "%npcName%",
            "%facRank%",
            "%facName%",
            "%facPronoun%"
        );
        $replace = array(
            explode("=>", $this->character['charData']['titles'])[0],
            $this->npcData ? $this->npcData['npc_name'] : "",
            $this->character['charFaction'] ? $this->character['factionData']['factionRankData']['rank_pronoun'] : "",
            $this->character['charFaction'] ? $this->character['factionData']['name'] : "",
            $this->character['charFaction'] ? $this->character['factionData']['pronoun'] : ""
        );
        return str_replace($wildcards, $replace, $data); // And finally return the fully parsed string.
    }
    function verifyUsrFaction()
    {
        // Bunch of stuff to verify NPC & User faction match, and what they will do say if faction doesn't match.
        $this->npcPlayerDataArray = 
        array(
            "character" => 
                array(
                    "data" => $this->character['charFaction'], 
                    "rank" => $this->character['factionData']['factionRankData']
                ), 
            "faction" => $this->character['factionData']
        );
        if(_debug)
        {
            print_r($this->npcPlayerDataArray);
        }
        $this->connect("inventory");
        $stmt = "SELECT * FROM blurbs WHERE id = ?"; // NPC tables not implemented yet. This is just to tell object inheritance.
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$this->npcId]);
            print_r($do->fetch(PDO::FETCH_ASSOC));
        }
        catch(PDOException $e)
        {
            exit("err:" . $e->getMessage());
        }
    }
}

?>