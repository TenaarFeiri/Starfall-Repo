<?php

    require_once('database.php');

    class staff
    {
        private $invPdo;
        private $rptPdo;
        private $stafferName;
        private $stafferUuid;

        // Inventory details
        private $targetInventory; 

        //
        function __construct($name, $uuid) 
        {
            // Whatever nonsense we'll end up needing to do goes here.
            // In this case, it's binding the staffer's name to a variable so we can log their bullshit!
            $this->stafferName = $name;
            $this->stafferUuid = $uuid;
        }

        // GET TARGET'S USER ID
        function getUserId($usr)
        {
            // Get the ID of the target user from the users database.
            // Can use either username or UUID.
            $stmt = "SELECT id FROM users WHERE username = :usr OR uuid = :usr";
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
                    return $do['id'];
                }
            }
            catch(PDOException $e)
            {
                die("err:".$e->getMessage());
            }
        }

        function writeLog($log)
        {
            // Write data to log database.
            $stmt = "
                INSERT INTO logs
                VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
            ";
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $action = "Staff Action";
                $chr = 0;
                $module = "staff";
                $do->bindParam(":usr", $this->stafferName);
                $do->bindParam(":uid", $this->stafferUuid);
                $do->bindParam(":charid", $chr);
                $do->bindParam(":charname", $action);
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

        function getLogs($page)
        {
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            if($page < 1)
            {
                // Always ensure we never go below 1 no matter what the client tells us.
                $page = 1;
            }
            $firstNum;
            $lastNum = 4;
            if($page === 1) {
                $firstNum = 0;
            } else {
                $firstNum = 4 * $page - 4;
            }
            // Then we get the character ID and the titles from the RP tool repository.
            // We'll format these and return them to the staff tool.
            $stmt = "
                SELECT entry,time,username,uuid,char_id,char_name,module,log
                FROM logs
                ORDER BY entry DESC LIMIT $firstNum, $lastNum
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                if(!$do->execute())
                {
                    exit(print_r($do->errorInfo()));
                }
                $do = $do->fetchAll(PDO::FETCH_ASSOC);
                $out = "PAGE $page\n\n";
                foreach($do as $arr)
                {
                    $out = $out . "# " . $arr['entry'] . "\nTime: " . $arr['time'] . "\nUsername: " . $arr['username'] . "\nUUID: " . $arr['uuid'] . "\nCharacter name: " . $arr['char_name'] . "\nCharacter ID: " . $arr['char_id'] . "\nModule: " . $arr['module'] . "\nLog: " . $arr['log'] . "\n---------\n";
                }
                return $out;
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
        }

        function searchLogs($search, $page)
        {
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            if($page < 1)
            {
                // Always ensure we never go below 1 no matter what the client tells us.
                $page = 1;
            }
            $firstNum;
            $lastNum = 4;
            if($page === 1) {
                $firstNum = 0;
            } else {
                $firstNum = 4 * $page - 4;
            }
            // Then we get the log files from the database.
            // We'll format these and return them to the staff tool, and it'll just print the logs in chat.
            $stmt = "
                SELECT entry,time,username,uuid,char_id,char_name,module,log
                FROM logs
                WHERE (
                    username like (:search) or
                    uuid like (:search) or
                    char_id like (:search) or
                    char_name like (:search) or
                    module like (:search)
                )
                ORDER BY entry DESC LIMIT $firstNum, $lastNum
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $search = $search . "%";
                $do->bindParam(":search", $search);
                if(!$do->execute())
                {
                    exit(print_r($do->errorInfo()));
                }
                $do = $do->fetchAll(PDO::FETCH_ASSOC);
                if(empty($do))
                {
                    return "No logs available.";
                }
                $out = "PAGE $page\n\n";
                foreach($do as $arr)
                {
                    $out = $out . "# " . $arr['entry'] . "\nTime: " . $arr['time'] . "\nUsername: " . $arr['username'] . "\nUUID: " . $arr['uuid'] . "\nCharacter name: " . $arr['char_name'] . "\nCharacter ID: " . $arr['char_id'] . "\nModule: " . $arr['module'] . "\nLog: " . $arr['log'] . "\n---------\n";
                }
                return $out;
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
        }

        function getUserDetails($userId)
        {
            // Get details about the user associated with a character ID!
            $stmt = "SELECT username,uuid FROM users WHERE id = ?";
            $this->rptPdo = connectToRptool(); // Reinitialise the rptPdo object just to be sure that we have it.
            $this->rptPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $do = $this->rptPdo->prepare($stmt);
            try
            {
                $do->execute([$userId]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
                if(!$do)
                {
                    exit("err:For some reason, this user ID is not associated with any entries in the users table. This probably means they requested a deletion. Notify Tenaar to purge all remaining data associated with user id $userId.");
                }
                return $do; // Return the fetched array to the calling variable.
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
        }

        // LIST THE TARGET'S CHARACTERS
        function getCharList($usr, $page)
        {
            // Function to return the character list of a user.
            // Also paginate.
            $this->rptPdo = connectToRptool(); // Initiate a connection to the RP tool database that contains character data, not inventory.
            $this->rptPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $usrId = $this->getUserId($usr); // Get the target user's character ID.
            if($page < 1)
            {
                // Always ensure we never go below 1 no matter what the client tells us.
                $page = 1;
            }
            $firstNum;
            $lastNum = 9;
            if($page === 1) {
                $firstNum = 0;
            } else {
                $firstNum = 9 * $page - 9;
            }
            // Then we get the character ID and the titles from the RP tool repository.
            // We'll format these and return them to the staff tool.
            $stmt = "
                SELECT character_id,titles
                FROM rp_tool_character_repository
                WHERE (user_id = ? AND deleted = 0)
                ORDER BY character_id ASC LIMIT $firstNum, $lastNum
            ";
            $do = $this->rptPdo->prepare($stmt);
            try
            {
                $do->execute([$usrId]); // Execute the query to get the user's active characters.
                $do = $do->fetchAll(\PDO::FETCH_ASSOC); // And then fetch all rows returned!
                if(!empty($do))
                {
                    // If we get a result, return it!
                    $out = array(); // Initialise a new array. This will have our LSL-digestible output.
                    foreach($do as $arr)
                    {
                        // Then place each array entry into a new array containing character ID and name.
                        $out[] = $arr['character_id'] . "&&&" . explode("=>", $arr['titles'])[0];
                    }
                    $usrDetails = $this->getUserDetails($usrId);
                    $this->writeLog("Retrieved character list for user " . $usrDetails['username'] . "(ID: " . $usrId . "), page " . $page);
                    return "page::$page::".implode("&%&", $out); // Then return the output.
                }
                else
                {
                    // If we get an empty result, just return the last valid query.
                    $page = ($page - 1);
                    if($page < 1)
                    {
                        $page = 1;
                    }
                    if($page === 1) {
                        $firstNum = 0;
                    } else {
                        $firstNum = 9 * $page - 9;
                    }
                    $stmt = "
                    SELECT character_id,titles
                    FROM rp_tool_character_repository
                    WHERE (user_id = ? AND deleted = 0)
                    ORDER BY character_id ASC LIMIT $firstNum, $lastNum
                    ";
                    $do = $this->rptPdo->prepare($stmt);
                    $do->execute([$usrId]); // Execute the query to get the user's active characters.
                    $do = $do->fetchAll(PDO::FETCH_ASSOC); // And then fetch all rows returned!
                    if($do)
                    {
                        // If we get a result, return it!
                        $out = array(); // Initialise a new array. This will have our LSL-digestible output.
                        foreach($do as $arr)
                        {
                            // Then place each array entry into a new array containing character ID and name.
                            $out[] = $arr['character_id'] . "&&&" . explode("=>", $arr['titles'])[0];
                        }
                        $usrDetails = $this->getUserDetails($usrId);
                        //$this->writeLog("Retrieved character list for user " . $usrDetails['username'] . " (ID: " . $usrId . "), page " . $page);
                        return "page::$page::".implode("&%&", $out); // Then return the output.
                    }
                }
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
        }
        function getMultiCharacterDetails($charId, $wildCards)
        {
            $this->rptPdo = connectToRptool();
            $this->rptPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = "SELECT * FROM rp_tool_character_repository WHERE character_id IN ($wildCards) GROUP BY character_id ASC";
            $do = $this->rptPdo->prepare($stmt);
            try
            {
                $do->execute(explode(",", $charId));
                $do = $do->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
                if(!$do)
                {
                    exit("err:Could not get character details from the repository for id $charId.");
                }
                $out = array();
                foreach($do as $key => $var)
                {
                    $out[$key] = $var[0];
                }
                return $out;
            }
            catch(PDOException $e)
            {
                exit("err:".$e->getMessage());
            }
        }
        // GET ITEM DETAILS
        function getItemDetails($ids)
        {
            $ids = explode(",", $ids); // Create an array.
            $sqlWildcards = array();
            foreach($ids as $v)
            {
                $sqlWildcards[] = "?";
            }
            $sqlWildcards = implode(",", $sqlWildcards);
            $stmt = "SELECT id,name,description,type,max_stack,texture_name,texture_color,max_gather,gather_success_chance FROM items WHERE id IN ($sqlWildcards) ORDER BY id DESC";
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute($ids);
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
            return $do->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
        }
        function pdoWildcards($arr)
        {
            // Return a string containing number of ?-s needed for programmatically generated PDO queries.
            $out = array();
            foreach($arr as $v)
            {
                $out[] = "?";
            }
            return implode(",", $out); // Return the string containing all ?-s.
        }
        function getMultipleUserData($targets, $wildCards)
        {
            $stmt = "SELECT id,username,uuid,lastchar FROM users WHERE uuid IN ($wildCards) GROUP BY id";
            if(is_null($this->rptPdo) or !$this->rptPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->rptPdo = connectToRptool();
                $this->rptPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            $do = $this->rptPdo->prepare($stmt);
            try
            {
                $do->execute($targets);
                
            }
            catch(PDOException $e)
            {
                exit("err:" . $e->getMessage());
            }
            return $do->fetchAll(PDO::FETCH_ASSOC);
        }
        function getMultipleUsrInventories($targets, $wildCards, $getMoney)
        {
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
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
        // ADD MONEY TO MULTIPLE
        function addMoneyToMultiple($t, $amount)
        {
            if($amount < 1)
            {
                exit("err:You have to give at least 1 money. You tried to give $amount.");
            }
            $targets = explode(",", $t);
            $targets = array_map('trim', $targets);
            $wildCards = $this->pdoWildcards($targets);
            $usrData = $this->getMultipleUserData($targets, $wildCards);
            $usrTmp = array();
            foreach($usrData as $key => $var)
            {
                $usrTmp[$var['lastchar']] = $var;
            }
            $usrData = $usrTmp;
            unset($usrTmp);
            $characters = array();
            foreach($usrData as $var)
            {
                $characters[] = $var['lastchar'];
            }
            $inventories = $this->getMultipleUsrInventories($characters, $wildCards, true);
            $charData = $this->getMultiCharacterDetails(implode(",", $characters), $wildCards);
            $money = array();
            foreach($inventories as $key => $var)
            {
                if(!empty($var))
                {
                    $var['money'] = ($var['money'] + $amount);
                    $money[$key] = $var;
                }
            }
            unset($inventories);
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            $this->invPdo->beginTransaction();
            $out = array();
            try
            {
                $stmt = "UPDATE character_inventory SET money = ? WHERE char_id = ?";
                foreach($money as $id => $money)
                {
                    if(!empty($money))
                    {
                        $do = $this->invPdo->prepare($stmt);
                        $do->execute([$money['money'], $id]);
                        $this->writeLog("Gave " . explode("=>", $charData[$id]['titles'])[0] . " (ID: $id) " . $amount . " money." );
                        $out[$id] = explode("=>", $charData[$id]['titles'])[0];
                    }
                }
                $this->invPdo->commit();
                $n = "moneysuccess::";
                return $n . implode("&&", $out);
            }
            catch(PDOException $e)
            {
                $this->invPdo->rollBack();
                exit("err:" . $e->getMessage());
            }
        }
        // REMOVE MONEY FROM MULTIPLE
        function removeMoneyFromMultiple($t, $amount)
        {
            if($amount < 1)
            {
                exit("err:You have to take at least 1 money. You tried to take $amount.");
            }
            $targets = explode(",", $t);
            $targets = array_map('trim', $targets);
            $wildCards = $this->pdoWildcards($targets);
            $usrData = $this->getMultipleUserData($targets, $wildCards);
            $usrTmp = array();
            foreach($usrData as $key => $var)
            {
                $usrTmp[$var['lastchar']] = $var;
            }
            $usrData = $usrTmp;
            unset($usrTmp);
            $characters = array();
            foreach($usrData as $var)
            {
                $characters[] = $var['lastchar'];
            }
            $inventories = $this->getMultipleUsrInventories($characters, $wildCards, true);
            $charData = $this->getMultiCharacterDetails(implode(",", $characters), $wildCards);
            $money = array();
            foreach($money as $id => $var)
            {
                if(($var['money'] - $amount) < 0) // Kill if even one person cannot lose that much money.
                {
                    exit("err:" . $charData[$id]['titles'])[0] . "doesn't have enough money. They have " . $var['money'] . " & you tried to remove $amount.");
                }
                
            }
        }
        // ADD ITEM TO MULTIPLE
        function addItemToMultiple($t, $itemId, $amount)
        {
            $targets = explode(",", $t); // Turn $targets into an array containing char UUIDs.
            $targets = array_map('trim', $targets);
            $wildCards = $this->pdoWildcards($targets); // Generate ?-s for the PDO statement.
            $itemDetails = $this->getItemDetails($itemId);
            if(count($itemDetails) > 1) { exit("err:Can only give one item at a time to multiple people."); }
            if(!$itemDetails) { exit("err:No item exists with this ID."); }
            $itemDetails = $itemDetails[$itemId][0];
            $usrData = $this->getMultipleUserData($targets, $wildCards);
            $usrTmp = array();
            foreach($usrData as $key => $var)
            {
                $usrTmp[$var['lastchar']] = $var;
            }
            $usrData = $usrTmp;
            unset($usrTmp);
            $characters = array();
            foreach($usrData as $var)
            {
                $characters[] = $var['lastchar'];
            }
            $inventories = $this->getMultipleUsrInventories($characters, $wildCards, false);
            $charData = $this->getMultiCharacterDetails(implode(",", $characters), $wildCards);
            $sendToStorage = array(); // Who gets their stuff sent to storage!
            $sendToInventory = array(); // Who gets their shit sent to the inventory.
            foreach($inventories as $id => $inv)
            {
                $arr = $inv;
                $slot = array_keys($arr, "0", true);
                foreach($arr as $key => $var)
                {
                    $str = explode(":", $var);
                    if($str[0] === $itemId)
                    {
                        $slot = $key;
                        $str[1] = ($str[1] + $amount);
                        if($str[1] > $itemDetails['max_stack'])
                        {
                            // Send to storage if it will exceed the maximum stack.
                            $sendToStorage[$id] = implode(":", $str);
                        }
                        else
                        {
                            $sendToInventory[$id][$slot] = implode(":", $str);
                        }
                        break;
                    }
                }
                // If we're here, it doesn't exist in inventory. Check for first available item slot.
                if($slot and (!array_key_exists($id, $sendToStorage) and !array_key_exists($id, $sendToInventory)) and $amount <= $itemDetails['max_stack'])
                {
                    $sendToInventory[$id][$slot[0]] = $itemId . ":" . $amount . ":" . $itemDetails['texture_name'];
                }
                else if((!array_key_exists($id, $sendToStorage) and !array_key_exists($id, $sendToInventory)))
                {
                    // If there are no open slots whatsoever, just add it to storage!
                    $sendToStorage[$id] = $itemId . ":" . $amount . ":" . $itemDetails['texture_name'];
                }
            }
            if(count($sendToStorage) < 1 and count($sendToInventory) < 1)
            {
                exit("err:Something went wrong; no transactions in send arrays.");
            }
            if(is_null($this->invPdo) or !$this->invPdo) // If connection is closed or doesn't exist, start a new one.
            {
                $this->invPdo = connectToInventory();
                $this->invPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            // Now get to the meat of the matter!
            // We'll do the storage people first!
            $out = array(); // Who got what!
            if(count($sendToStorage) > 0)
            {
                $this->invPdo->beginTransaction();
                try
                {
                    foreach($sendToStorage as $key => $var)
                    {
                        $stmt = "SELECT num,amount FROM character_storage WHERE char_id = :chr AND item_id = :item";
                        $do = $this->invPdo->prepare($stmt);
                        $do->bindParam(":chr", $key);
                        $do->bindParam(":item", $itemId);
                        $do->execute();
                        $result = $do->fetch(PDO::FETCH_ASSOC);
                        if(!$result)
                        {
                            $stmt = "INSERT INTO character_storage (char_id,item_id,item_name,item_texture,amount) VALUES (?,?,?,?,?)";
                            $do = $this->invPdo->prepare($stmt);
                            $do->execute([$key, $itemId, $itemDetails['name'], $itemDetails['texture_name'], $amount]);
                            $out[$key] = explode("=>", $charData[$key]['titles'])[0];
                            $this->writeLog("Gave " . explode("=>", $charData[$key]['titles'])[0] . " (ID: $key) " . $amount . "x " . $itemDetails['name'] . " (ID: " . $itemId . ")." );
                        }
                        else
                        {
                            $stmt = "UPDATE character_storage SET amount = ? WHERE char_id = ? AND item_id = ?";
                            $do = $this->invPdo->prepare($stmt);
                            $am = ($result['amount'] + $amount);
                            $do->execute([$am, $key, $itemId]);
                            $out[$key] = explode("=>", $charData[$key]['titles'])[0];
                            $this->writeLog("Gave " . explode("=>", $charData[$key]['titles'])[0] . " (ID: $key) " . $amount . "x " . $itemDetails['name'] . " (ID: " . $itemId . ")." );
                        }
                    }
                    unset($result);
                    $this->invPdo->commit();
                }
                catch(PDOException $e)
                {
                    $this->invPdo->rollBack();
                    exit("err:" . $e->getMessage());
                }
            }
            if(count($sendToInventory) > 0)
            {
                $this->invPdo->beginTransaction();
                try
                {
                    foreach($sendToInventory as $key => $var)
                    {
                        $stmt = "UPDATE character_inventory SET " . array_keys($var)[0] . " = ? WHERE char_id = ?";
                        $do = $this->invPdo->prepare($stmt);
                        $do->execute([$var[array_keys($var)[0]], $key]);
                        $out[$key] = explode("=>", $charData[$key]['titles'])[0];
                        $this->writeLog("Gave " . explode("=>", $charData[$key]['titles'])[0] . " (ID: $key) " . $amount . "x " . $itemDetails['name'] . " (ID: " . $itemId . ")." );
                    }
                    $this->invPdo->commit();
                }
                catch(PDOException $e)
                {
                    $this->invPdo->rollBack();
                    exit("err:" . $e->getMessage());
                }
            }
            $n = "itemsuccess::" . $itemDetails['name'] . "::";
            return $n . implode("&&", $out);
        }
    }

?>