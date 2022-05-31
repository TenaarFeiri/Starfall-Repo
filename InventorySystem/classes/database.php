<?php
    function connectToInventory()
    {
        return new PDO("mysql:host=localhost;dbname=inventory_system", "user", "psswd");
    }
    function connectToRptool()
    {
        return new PDO("mysql:host=localhost;dbname=rp_tool", "user", "psswd");
    }
?>
