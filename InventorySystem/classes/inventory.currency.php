<?php
/* TODO:

    - Create a transaction system.

*/

if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}
include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});
class currency
{
    private $pdo;
    private $debug = false;
    function __construct() 
    {
        //include_once('database.php');
        // Open the database connection when object is created.
        $this->pdo = connectToInventory();
        if($this->debug) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        //echo "Started\n";
        if(!$this->pdo) {
            die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
        }
    }


    function getInventoryMoney($charId)
    {
        // Retrieve current inventory money.
        $output;
        $stmt = "
            SELECT money FROM character_inventory WHERE char_id = :charid
        ";
        $getWealth = $this->pdo->prepare($stmt);
        $getWealth->bindParam(":charid", $charId);
        if(!$getWealth->execute()) {
            die("err:Failed to get inventory money. Error: inv-money-noexec");
        } else {
            $output = $getWealth->fetch(\PDO::FETCH_ASSOC);
            $moneyName = $this->getSettings('money_name');
            $output = $output["money"] . "," . $moneyName;
        }
        return $output;
    }

    function giveRemoveInventoryMoney($plsdo, $charId, $amount)
    {
        // Add money to inventory. Or remove it.
        $currentMoney = $this->getInventoryMoney($charId); // Get current amount of inventory money.
        $newMoney;
        $out;
        $stmt = "
            UPDATE character_inventory 
            SET money = :money
            WHERE char_id = :charid
        ";
        if($plsdo == "givemoney")
        {
            // If we're giving money...
            $newMoney = ($currentMoney + $amount);
            $out = 1;
        }
        else if($plsdo == "takemoney")
        {
            // If we're taking money...
            $newMoney = ($currentMoney - $amount);
            $out = 2;
        }
        else
        {
            die('giveRemoveInventoryMoney plsdo var is not set.');
        }
        if($newMoney < 0)
        {
            // If money becomes less than zero, die.
            die("err:You don't have enough money.");
        }
        $giveRemove = $this->pdo->prepare($stmt);
        $giveRemove->bindParam(":money", $newMoney);
        $giveRemove->bindParam(":charid", $charId);
        if(!$giveRemove->execute())
        {
            //die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
            return false;
        }
        return $out; // Return true if transaction succeeded.
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
}

?>