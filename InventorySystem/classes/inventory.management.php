<?php
    require_once('database.php');
    class management
    {
        private $charId;
        private $pdo;
        private $inventory;
        private $money;
        private $itemDetails;
        function __construct($charId)
        {
            $this->charId = $charId;
            $this->pdo = connectToInventory();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->getInventory();
        }
        function getInventory()
        {
            $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,money FROM character_inventory WHERE char_id = ?";
            $do = $this->pdo->prepare($stmt);
            try
            {
                $do->execute([$this->charId]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
                $this->inventory = array();
                foreach($do as $key => $var)
                {
                    if($var != 0 and $key != "char_id" and $key != "money")
                    {
                        $var = explode(":", $var);
                        $var[] = $key; // Add the key to the array so I don't have to loop around and fucking find it. Hmph.
                        $this->inventory[$var[0]] = array_slice($var, 1);
                    }
                    else if($key == "money")
                    {
                        $this->money = $var;
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
                    $this->itemDetails[$var['id']] = array_slice($var, 1);
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
            return implode(":::", $out);
        }
    }
?>
