<?php
spl_autoload_register(function ($name) {
    include 'faction.' . $name . '.php';
});
class membership extends status
{
    private $module = "faction membership";
    function __construct($usr)
    {
        parent::__construct($usr); // Provide parent constructor with username or uuid so it can instantiate properly.
    }

    function addMemberToFaction($target, $faction)
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["factionInvite"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
        if(array_search($target, $this->usr))
        {
            exit("err:You can't add yourself to a faction; you need to be invited.");
        }
        $targetData = parent::getUserInfo($target);
        $targetCharacterData = parent::getCharacterData($targetData['lastchar']);
        if(_debug)
        {
            print_r($targetData);
            print_r($targetCharacterData);
        }
        $charName = explode("=>", $targetCharacterData['charData']['titles'])[0];
        if(!empty($targetCharacterData['charFaction']))
        {
            exit("err:Character " . $charName . " is already in a faction. They must leave their faction before they can be added to a new one.");
        }
        $startingRank = $this->character['factionData']['starter_rank']; // Add the starting rank.
        if($startingRank == "0")
        {
            exit("err:Database misconfiguration; no starting rank set for faction: " . $this->character['factionData']['name']);
        }
        parent::connect("inventory"); // Connect just in case.
        // Let's perform the insert.
        $stmt = 
        "   INSERT INTO faction_members (char_id,char_name,char_faction,char_faction_rank) 
            VALUES (?,?,?,?)
        ";
        $do = $this->invPdo->prepare($stmt); // Prepare it.
        // Then build the array.
        $arr = array(
            $targetCharacterData['charData']['character_id'], // Character id.
            $charName, // Character name
            $faction, // Faction
            $startingRank // Starting rank
        );
        // Then execute as part of a transaction.
        $this->invPdo->beginTransaction();
        try
        {
            $do->execute($arr);
            $log = "Added " . $charName . " (ID: " . $targetCharacterData['charData']['character_id'] . ") to " . $this->character['factionData']['name'] . ".";
            parent::writeLog($this->module, $log);
            $this->invPdo->commit();
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
            exit("err:" . $e->getMessage());
        }
        // Then retrieve target character data again. This time using character_id from $targetCharacterData since we don't need the lastchar column.
        $targetCharacterData = parent::getCharacterData($targetCharacterData['charData']['character_id']);
        if(empty($targetCharacterData['charFaction']))
        {
            // If we're still empty after all of this, error out.
            exit("err:Could not add " . $charName . " to " . $this->character['factionData']['name'] . ".");
        }
        else
        {
            return "You have added $charName to " . $this->character['factionData']['name'] . ".";
        }
    }

    function kickMember($charId, $faction) // Kick from member list selection!
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["factionKick"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
        else if($charId == $this->character['charData']['character_id'])
        {
            exit("err:You cannot kick yourself from your own faction.");
        }
        $targetCharacterData = parent::getCharacterData($charId);
        if(!$targetCharacterData['charFaction']['char_faction'])
        {
            exit("err:Target is not in a faction.");
        }
        if($targetCharacterData['charFaction']['char_faction'] != $faction)
        {
            exit("err:You are not allowed to kick someone not in your faction.");
        }
        $stmt = "DELETE FROM faction_members WHERE char_id = ?";
        parent::connect("inventory");
        $this->invPdo->beginTransaction();
        try
        {
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$charId]);
            $log = "Removed " . $targetCharacterData['charData']['char_name'] . " (ID: " . $targetCharacterData['charData']['character_id'] . ") from " . $targetCharacterData['factionData']['name'] . ".";
            parent::writeLog($this->module, $log);
            $this->invPdo->commit();
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
            exit("err:" . $e->getMessage());
        }
        return "You have kicked " . $targetCharacterData['charData']['char_name'] . " (ID: " . $targetCharacterData['charData']['character_id'] . ") from " . $targetCharacterData['factionData']['name'] . ".";
    }

    function removeMemberFromFaction($target, $faction)
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["factionInvite", "factionKick"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
        if(array_search($target, $this->usr))
        {
            exit("err:You can't kick yourself from the faction.");
        }
        $targetData = parent::getUserInfo($target);
        $targetCharacterData = parent::getCharacterData($targetData['lastchar']);
        if(_debug)
        {
            print_r($targetData);
            print_r($targetCharacterData);
        }
        $charName = explode("=>", $targetCharacterData['charData']['titles'])[0];
        if(empty($targetCharacterData['charFaction']))
        {
            exit("err:" . $charName . " is not in a faction.");
        }
        else if($targetCharacterData['charFaction']['char_faction'] != $faction)
        {
            exit("err:" . $charName . " is not a member of your faction.");
        }
        parent::connect("inventory"); // Connect if necessary. Shouldn't be.
        $stmt = "DELETE FROM faction_members WHERE char_id = ?";
        $do = $this->invPdo->prepare($stmt);
        $this->invPdo->beginTransaction();
        try
        {
            $do->execute([$targetCharacterData['charData']['character_id']]);
            $log = "Removed " . $charName . " (ID: " . $targetCharacterData['charData']['character_id'] . ") from " . $this->character['factionData']['name'] . ".";
            parent::writeLog($this->module, $log);
            $this->invPdo->commit();
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
            exit("err:" . $e->getMessage());
        }
        return $charName . " has been removed from " . $targetCharacterData['factionData']['name'];
    }

    function getRanks($page, $faction)
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["factionPromote", "factionDemote"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
        $officer = parent::getRankPermissions($this->character, ["officer"]); // Make our lives easy, reduce calls.
        $leader = parent::getRankPermissions($this->character, ["leader"]); // Always true is leader.
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
        parent::connect("inventory");
        $stmt = "SELECT id,rank_name,rank_permissions FROM faction_ranks WHERE rank_faction = ? ORDER BY rank_sort DESC LIMIT $firstNum, $lastNum";
        // factionData factionRankData
        try
        {
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$faction]);
            $do = $do->fetchAll(PDO::FETCH_ASSOC);
            if(!$do)
            {
                exit("err:No ranks on this page. You're probably too far ahead; go back a page.");
            }
            $result = array();
            foreach($do as $arr)
            {
                if(parent::getRankPermissions($this->character, ["officer"]) or parent::getRankPermissions($this->character, ["leader"]))
                {
                    $result[] = $arr['id'] . ":" . $arr['rank_name'];
                }
            }
            return implode("::", $result);
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
    }

    function getSpecificRankPerms($rankId)
    {
        $stmt = "SELECT rank_permissions FROM faction_ranks WHERE id = ?";
        parent::connect("inventory");
        try
        {
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$rankId]);
            return $do->fetch(PDO::FETCH_ASSOC);
        }
        catch(PDOException $e)
        {
            exit("err:Could not get rank permissions for faction $rankId.");
        }
    }

    function localSearchRankPerms(array $arr, array $perm) 
    {
        $x = count($perm);
        $i = 0;
        $arr = explode(",", $arr['rank_permissions']);
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

    function changeToRank($rankId, $target, $faction)
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["officer"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
        $targetData = parent::getUserInfo($target);
        $targetCharacterData = parent::getCharacterData($targetData['lastchar']);
        $rankPerms = $this->getSpecificRankPerms($rankId);
        if($targetCharacterData['charFaction']['char_faction'] != $this->character['charFaction']['char_faction'])
        {
            exit("err:Target is not in your faction.");
        }
        else if($targetCharacterData['charFaction']['char_faction_rank'] == $rankId)
        {
            exit("err:Member is already this rank.");
        }
        if(($this->localSearchRankPerms($rankPerms, ["officer"]) or $this->localSearchRankPerms($rankPerms, ["leader"])))
        {
            if($this->localSearchRankPerms(["rank_permissions" => $this->character['factionData']['factionRankData']['rank_permissions']], ["officer"]))
            {
                exit("err:Officers cannot promote people to officer ranks, or to leader ranks.");
            }
        }
        if(_debug) { print_r($targetCharacterData); print_r($rankPerms); }
        // And now FINALLY we can get to the meat.
        // We're not going to bother with ensuring hierarchy. Anyone with officer permissions can change non-officers and non-leaders' ranks.
        $stmt = "UPDATE faction_members SET char_faction_rank = ? WHERE char_id = ? AND char_faction = ?";
        parent::connect("inventory");
        $this->invPdo->beginTransaction();
        $do = $this->invPdo->prepare($stmt);
        try
        {
            $do->execute([$rankId, $targetData['lastchar'], $faction]);
            $log = "Changed rank of " . explode("=>", $targetCharacterData['charData']['titles'])[0] . " (ID: " . $targetCharacterData['charData']['character_id'] . ") to rank ID " . $rankId . ".";
            parent::writeLog($this->module, $log);
            $this->invPdo->commit();
            return "success::" . explode("=>", $targetCharacterData['charData']['titles'])[0];
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
            exit("err:Could not change ranks.");
        }
    }

    function leaveFaction() // Leave your current faction.
    {
        if(!$this->character['charFaction'])
        {
            exit("err:You are not currently in a faction.");
        }
        $charId = $this->character['charData']['character_id'];
        parent::connect("inventory");
        $this->invPdo->beginTransaction();
        $stmt = "DELETE FROM faction_members WHERE char_id = ?";
        try
        {
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$charId]);
            $log = "Left " . $this->character['factionData']['name'] . ".";
            parent::writeLog($this->module, $log);
            $this->invPdo->commit();
        }
        catch(PDOException $e)
        {
            $this->invPdo->rollBack();
            exit("err:" . $e->getMessage());
        }
        return "leaving::You have left " . $this->character['factionData']['name'] . ".";
    }

    function getMemberList($faction, $page)
    {
        if($this->character['charFaction']['char_faction'] != $faction or !parent::getRankPermissions($this->character, ["seeMembers", "factionKick", "factionInvite"]))
        {
            exit("err:You don't have the correct permissions to perform this function.");
        }
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
        parent::connect("inventory");
        $ranks = parent::getAllRankNames($faction);
        $stmt = "SELECT char_id,char_name,char_faction,char_faction_rank FROM faction_members WHERE char_faction = ? ORDER BY num ASC LIMIT $firstNum, $lastNum";
        $results = array();
        try
        {
            $do = $this->invPdo->prepare($stmt);
            $do->execute([$faction]);
            $results = $do->fetchAll(PDO::FETCH_ASSOC);
            if(!$results)
            {
                exit("err:No members found on page $page. Go back a page.");
            }
            if(_debug)
            {
                print_r($ranks);
                print_r($results);
            }
        }
        catch(PDOException $e)
        {
            exit($e->getMessage());
        }
        $chars = array();
        foreach($results as $char)
        {
            $char['char_faction_rank'] = $ranks[$char['char_faction_rank']];
            $chars[] = implode("&&", $char);
        }
        return implode("&&&", $chars);
    }
}

?>
