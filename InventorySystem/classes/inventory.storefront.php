<?php
if(!defined('noigneano93789hg2nopg')) 
{ // Kill the whole script if it's accessed directly.
    die('Direct access not permitted'); 
}
include_once 'database.php';
spl_autoload_register(function ($name) {
    include 'inventory.' . $name . '.php';
});
class storefront {
    private $pdo;
    private $debug = true;
    function __construct() 
    {
        // Open the database connection when object is created.
        $this->pdo = connectToInventory();
        if($this->debug) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        echo "Started\n";
        if(!$this->pdo) {
            die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
        } else {
            echo "Connected to DB: " . $inv_database;
        }
    }


    function closeConnection() 
    {
        // Close the connection just to be sure.
        // We shouldn't -have- to do this, but I like to do it nonetheless.
        // This is also useful for when we're expecting no more interactions with the database
        // and maintaining a connection is just wasted.
        $this->pdo->connection = null;
    }
}

?>