<?php

// Verify that user ID and char ID owners match up.
// Add new inventory if necessary.
include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});
class verify 
{
    private $pdo;
    private $invPdo;

    function __construct()
    {
        // Connect to the rp_tool database.
        $this->pdo = connectToRptool();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if(!$this->pdo)
        {
            //die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
        }
        $this->invPdo = connectToInventory();
        if(!$this->invPdo)
        {
            //die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
        }
    }

    function getLastId($uuid)
    {
        $stmt = "SELECT lastchar FROM users WHERE uuid = :id";
        $q = $this->pdo->prepare($stmt);
        $q->bindParam(":id", $uuid);
        if(!$q->execute())
        {
            die('Failed to get last char id');
        }
        else
        {
            $q = $q->fetch(\PDO::FETCH_ASSOC);
            if(!$this->checkDeletion($q['lastchar']))
            {
                die($q['lastchar']);
            }
            else
            {
                die('nochar');
            }
        }
    }

    function checkDeletion($charId)
    {
        $stmt = "SELECT deleted FROM rp_tool_character_repository WHERE character_id = :id";
        $c = $this->pdo->prepare($stmt);
        $c->bindParam(":id", $charId);
        if(!$c->execute())
        {
            die("Couldn't check deletion.");
        }
        else
        {
            $c = $c->fetch(\PDO::FETCH_ASSOC);
            if($c['deleted'] == 1)
            {
                return true;
            }
            else
            {
                return false;
            }
        }
    }

    function verifyOwner($charId, $uuid)
    {
        // First verify that user exists & obtain their user ID.
        $stmt = "
            SELECT id,uuid FROM users WHERE uuid = :userid
        ";
        $getUserId = $this->pdo->prepare($stmt); // Prepare the statement for sanitisation.
        $getUserId->bindParam(":userid", $uuid); // Bind parameter to sanitize. Always do this, even if player should be unable to mod data.
        if(!$getUserId->execute()) // And execute & die on failure.
        {
            die('err:Failed to obtain character id & user id from user table. Statement failed.');
        }
        // If we've gotten here, that means the above statement succeeded.
        $getUserId = $getUserId->fetch(\PDO::FETCH_ASSOC); // Create associative array.
        if(!isset($getUserId['id']))
        {
            //die('User with UUID \'' . $uuid . '\' does not exist in database.');
            return false;
        }
        // If we've reached THIS point, user exists. Now verify that the character belongs to the user.
        $confirmCharacter;
        $stmt = "
            SELECT character_id,user_id,deleted 
            FROM rp_tool_character_repository 
            WHERE character_id = :charid AND user_id = :userid
        ";
        $confirmCharacter = $this->pdo->prepare($stmt); // Prepare statement for sanitisation.
        $confirmCharacter->bindParam(":charid", $charId); // Bind parameter for sanitisation.
        $confirmCharacter->bindParam(":userid", $getUserId['id']); // Ditto.
        if(!$confirmCharacter->execute()) // And execute, die on failure.
        {
            die('err:Failed to execute confirmCharacter function.');
        }
        // Succeeding that, let's dump it into an associative array & verify.
        $confirmCharacter = $confirmCharacter->fetch(\PDO::FETCH_ASSOC);
        if(!isset($confirmCharacter['character_id']) or !isset($confirmCharacter['user_id']))
        {
            // Fail if we can't find the character or user id we want to reference on the row.
            return false;
        }
        else if(($getUserId['id'] != $confirmCharacter['user_id']) and ($confirmCharacter['character_id'] != $charId))
        {
            // Fail die if character ID & user ID are mismatched.
            return false;
        }
        // But passing all of the above, we can just return true.
        // Also perform a check to make sure that the character has an inventory. Add if necessary.
        $this->addInventory($charId);
        return true;
    }

    private function addInventory($charId)
    {
        // Add inventory if it doesn't already exists for charId.
        // Also add storage.
        $stmt = "
            SELECT char_id FROM character_inventory WHERE char_id = :charid
        ";
        $addInv = $this->invPdo->prepare($stmt);
        $addInv->bindParam(":charid", $charId);
        if(!$addInv->execute())
        {
            die('err:Failed to execute addInventory SELECT statement.\n');
        }
        // If everything's OK, let's do the checks.
        $checked = $addInv->fetch(\PDO::FETCH_ASSOC);
        if(!isset($checked['char_id']))
        {
            // If inventory row does not exist, add one.
            $stmt = "
                INSERT INTO character_inventory (char_id)
                VALUES (:charid)
            ";
            $addInv = $this->invPdo->prepare($stmt);
            $addInv->bindParam(":charid", $charId);
            if(!$addInv->execute())
            {
                die('err:Failed to execute addInventory INSERT statement.\n');
            }
        }
        // No need for a return.
    }
}

?>