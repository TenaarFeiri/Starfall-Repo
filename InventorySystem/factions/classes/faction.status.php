<?php
require_once('../classes/database.php');
define("_debug", false);
class status
{
    public $rptPdo;
    public $invPdo;
    public $usr;
    public $character;    
    function __construct($usr)
    {
        $this->usr = $this->getUserInfo($usr); // Replace with an array containing user information.
        $this->character = $this->getCharacterData($this->usr['lastchar']);
        if(!empty($this->character['charFaction']))
        {
            // Update character name if target is in a faction.
            // This will be called every time someone loads a character.
            if(_debug)
            {
                print_r($this->character);
            }
            $this->updateCharacterNames();
        }
    }

    function connect($to)
    {
        if($to == "rptool")
        {
            if(is_null($this->rptPdo) or !$this->rptPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->rptPdo = connectToRptool();
                $this->rptPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        }
        else if($to == "inventory")
        {
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        }
    }

    function writeLog($module, $log)
    {
        $charName = explode("=>", $this->character['charData']['titles'])[0];
        // Write data to log database.
        $stmt = "
        INSERT INTO logs
        VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
        ";
        $this->connect("inventory");
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $chr = 0;
            $do->bindParam(":usr", $this->usr['username']);
            $do->bindParam(":uid", $this->usr['uuid']);
            $do->bindParam(":charid", $this->character['charData']['character_id']);
            $do->bindParam(":charname", $charName);
            $do->bindParam(":module", $module);
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

    function getRankPermissions(array $targetArr, array $perm) 
    {
        $arr = explode(",", $targetArr['factionData']['factionRankData']['rank_permissions']);
        $key = array_search("leader", $arr);
        if($key !== false)
        {
            return true; // Always return true for leader.
        }
        $x = count($perm);
        $i = 0;
        foreach($perm as $v)
        {
            if($key = array_search($v, $arr) !== false)
            {
                $i++; // Add to count after finding correct permissions.
            }
        }
        if($i < $x)
        {
            return false;
        }
        return true;
    }

    function getAllRankNames($faction)
    {
        $stmt = "SELECT id,rank_name FROM faction_ranks WHERE rank_faction = ?";
        try
        {
            $this->connect("inventory");
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$faction]);
            $do = $do->fetchAll(PDO::FETCH_ASSOC);
            $out = array();
            foreach($do as $var)
            {
                $out[$var['id']] = $var['rank_name'];
            }
            return $out;
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
    }

    function createPdoWildcards($arr)
    {
        // Return a string containing number of ?-s needed for programmatically generated PDO queries.
        $out = array();
        foreach($arr as $v)
        {
            $out[] = "?";
        }
        return implode(",", $out); // Return the string containing all ?-s.
    }
    function getMultipleUsrInventories($targets, $wildCards, $getMoney)
    {
        $this->connect("inventory");
        if(!$getMoney)
        {
            $stmt = "SELECT char_id,item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9 FROM character_inventory WHERE char_id IN ($wildCards) ORDER BY char_id ASC";
        }
        else
        {
            $stmt = "SELECT char_id,money FROM character_inventory WHERE char_id IN ($wildCards) ORDER BY char_id ASC";
        }
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute($targets);
            $do = $do->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
            $out = array();
            foreach($do as $key => $var)
            {
                $out[$key] = $var[0];
            }
        }
        catch(PDOException $e)
        {
            exit("err:" . $e->getMessage());
        }
        return $out;
    }

    function updateCharacterNames() // Update character name in member list if present.
    {
        $charName = $charName = explode("=>", $this->character['charData']['titles'])[0];
        if($charName === $this->character['charFaction']['char_name'])
        {
            return; // Do nothing if name is an exact match.
        }
        $stmt = "UPDATE faction_members SET char_name = ? WHERE char_id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$charName, $this->character['charData']['character_id']]);
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
        }
    }    

    function getUserInfo($usr)
    {
        $this->connect("rptool");
        $stmt = "SELECT * FROM users WHERE username = :usr OR uuid = :usr";
        $do = $this->rptPdo->prepare($stmt);
        try
        {
            $do->bindParam(":usr", $usr);
            $do->execute();
            $do = $do->fetch(PDO::FETCH_ASSOC);
            if(!$do)
            {
                exit("err:No user ID found for usr: " . $usr . ". If you searched by username, they may have changed it. You can also search by UUID.");
            }
            else
            {
                return $do;
            }
        }
        catch(PDOException $e)
        {
            die("err:".$e->getMessage());
        }
    }

    function getCharacterData($charId)
    {
        $this->connect("rptool"); // Open a new connection if necessary.
        $this->connect("inventory");
        $stmt = "SELECT * FROM rp_tool_character_repository WHERE character_id = ?";
        $do = $this->rptPdo->prepare($stmt);
        $data = array();
        try
        {
            $do->execute([$charId]);
            $data["charData"] = $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:".$e->getMessage());
        }
        $stmt = "SELECT * FROM faction_members WHERE char_id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$charId]);
            $data["charFaction"] = $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:".$e->getMessage());
        }
        if($data['charFaction'])
        {
            $stmt = "SELECT * FROM faction WHERE id = :f;";
            $stmt2 = "SELECT * FROM faction_ranks WHERE id = :r GROUP BY id,rank_faction DESC;";
            $do = $this->invPdo->prepare($stmt);
            $do2 = $this->invPdo->prepare($stmt2);
            $do->bindParam(":f", $data['charFaction']['char_faction']);
            $do2->bindParam(":r", $data['charFaction']['char_faction_rank']);
            try
            {
                $do->execute();
                $do2->execute();
                $data["factionData"] = $do->fetch(PDO::FETCH_ASSOC);
                $data["factionData"]["factionRankData"] = $do2->fetch(PDO::FETCH_ASSOC);
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
        }
        return $data;
    }

    function whoAmI()
    {
        if(!$this->character['charFaction'])
        {
            return "null";
        }
        else if($this->character['factionData']['id'] != $this->character['factionData']['factionRankData']['rank_faction'])
        {
            return "null";
        }
        return $this->character['factionData']['name'] . "&&" . $this->character['factionData']['pronoun'] . "&&" . $this->character['factionData']['factionRankData']['rank_name'];
    }

    function getFactionInfo($faction)
    {
        $stmt = "SELECT * FROM faction WHERE id = ?";
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$faction]);
            $arr['factionData'] = $do->fetch(PDO::FETCH_ASSOC);
            $stmt = "SELECT * FROM faction_ranks WHERE rank_faction = ?";
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$faction]);
            $arr['factionRanks'] = $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:Could not obtain faction info. Please contact staff!");
        }
        return $arr;
    }
}

?>
