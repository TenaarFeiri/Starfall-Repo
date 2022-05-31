<?php
    require_once('../classes/database.php');
    class create
    {
        /*
            Creation class for the crafting system.
            Notes here.
        */
        private $debug = false;
        private $invPdo;
        private $rpPdo;
        private $usrDetails;
        private $characterDetails;
        private $characterJobDetails;
        private $gItemDetails;
        private $levelExp = array("10", "20", "48", "70");
        function __construct($chrArr, $jobArr)
        {
            $this->characterDetails = $chrArr;
            $this->characterJobDetails = $jobArr;
            $this->invPdo = connectToInventory();
            $this->usrDetails = $this->getUsrDetails();
        }
        function writeLog($usr, $uid, $char, $module, $log, $name) 
        {
            // Simple function to create a log.
            $statement = "
                INSERT INTO logs
                VALUES (default,default,:usr,:uid,:charid,:charname,:module,:log)
            ";
            $do = $this->invPdo->prepare($statement);
            $do->bindParam(":usr", $usr);
            $do->bindParam(":uid", $uid);
            $do->bindParam("charid", $char);
            $do->bindParam(":charname", $name);
            $do->bindParam(":module", $module);
            $do->bindParam(":log", $log);
            if(!$do->execute())
            {
                $this->pdo->rollBack();
                die("err:Could not log action.");
            }
        }
        function getUsrDetails()
        {
            $stmt = "SELECT username,uuid FROM users WHERE lastchar = ?";
            $this->rpPdo = connectToRptool();
            $do = $this->rpPdo->prepare($stmt);
            try
            {
                $do->execute([$this->characterDetails['character_id']]);
            }
            catch (Exception $e)
            {
                exit("err:" . $e->getMessage());
            }
            return $do->fetch(PDO::FETCH_ASSOC);
        }
        function getItemDetails($id)
        {
            $stmt = "
                SELECT id,name,type,description,type,max_stack,texture_name
                FROM items
                WHERE id = ?
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$id]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
            }
            catch (Exception $e)
            {
                die("err:".$e->getMessage());
            }
            return $do;
        }
        function getCharInventory()
        {
            // Get character inventory from the database, for use in creating things!
            $stmt = "
            SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9
            FROM character_inventory
            WHERE char_id = ?
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                // Try to execute...
                $do->execute([$this->characterDetails['character_id']]);
            }
            catch (Exception $e)
            {
                // Or gracefully error out.
                die("err:".$e->getMessage());
            }
            return $do->fetch(PDO::FETCH_ASSOC);
        }
        function getRecipeDetails($recipe)
        {
            // Get recipe details for a specific recipe.
            // Select the recipe that matches given recipe ID, job ID and matches level requirement.
            $stmt = "
                SELECT * FROM crafting_recipes WHERE id = ? AND job_id = ? AND job_level_requirement <= ?;
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                // Execute query.
                $do->execute([$recipe, $this->characterJobDetails['job'], $this->characterJobDetails['level']]);
            }
            catch (Exception $e)
            {
                // Or die trying.
                die("err:".$e->getMessage());
            }
            $do = $do->fetch(PDO::FETCH_ASSOC); // Turn the result into a readable array.
            if(!$do)
            {
                // If empty, then recipe is invalid or job level inadequate.
                die("err:You don't know this recipe.");
            }
            return $do;
        }
        function matchRecipeMaterials($recipe, $inventory)
        {
            // Return an array of keys for inventory slots containing our ingredients.
            $mats = array($recipe['material_one'], $recipe['material_two'], $recipe['material_three'], $recipe['material_four']);
            $out;
            foreach($mats as $m)
            { // Nest the loops to find matches for the inventory slots.
                if(explode(":", $m)[0] !== "0")
                {
                    foreach($inventory as $inv)
                    {
                        if(explode(":", $inv)[0] === explode(":", $m)[0])
                        {
                            $out[] = array_keys($inventory, $inv)[0];
                        }
                    }
                }
            }
            $mats2 = array();
            foreach($mats as $m)
            {
                if($m !== "0")
                {
                    $mats2[] = $m;
                }
            }
            $mats = $mats2;
            $mats2 = array();
            if(!empty($out) and (count($out) === count($mats)))
            {
                $x = 0;
                foreach($out as $o)
                {
                    if(explode(":", $inventory[$o])[1] < explode(":", $mats[$x++])[1])
                    {
                        return false;
                    }
                }
                return $out;
            }
            return false; // Just return false by default. If nothing returns anything above, then we've been unable to match!
        }
        function hasRequiredItems($recipe, $inventory)
        {
            // Loop through the inventory array and return true if required item is found.
            if($recipe === "0")
            {
                // If it's zero, just return true; no item required.
                return true;
            }
            foreach($inventory as $var)
            {
                $var = explode(":", $var)[0];
                if($var === $recipe)
                {
                    return true;
                }
            }
            return false;
        }
        function create($recipe)
        {
            // Catch-all function to create a recipe.
            $inventory = $this->getCharInventory();
            $recipe = $this->getRecipeDetails($recipe);
            // Then verify that we have the item required to craft.
            // All crafters need a toolkit.
            if(!$this->hasRequiredItems($recipe['required_item'],$inventory))
            {
                die("err:You need a " . $this->getItemDetails($recipe['required_item'])['name'] . " to craft this item.");
            }
            // Barring that, let's first make sure we have inventory space.
            // First we obtain the necessary details from the items database.
            $this->gItemDetails = $this->getItemDetails($recipe['result']);
            // First we generate an array matching crafting ingredients to keys.
            // If we don't have the materials, just fail!
            $crafting = $this->matchRecipeMaterials($recipe, $inventory);
            if(!$crafting)
            {
                die("err:You don't have enough materials to craft this item.");
            }
            // With that saved into an array, let's loop through the inventory to find the first available empty spot
            // OR break upon finding an inventory slot containing our crafted item.
            $slot = "none";
            try
            {
                foreach($inventory as $var)
                {
                    if($var === "0" and $slot === "none")
                    {
                        // Get the array key for the first available empty slot...
                        $slot = array_keys($inventory, $var)[0];
                    }
                    else if(explode(":", $var)[0] === $this->gItemDetails['id'])
                    {
                        // OR if we find the item in the inventory, add that key to $slot
                        // and break the loop as we found what we looked for.
                        $slot = array_keys($inventory, $var)[0];
                        break;
                    }
                }
            }
            catch (Exception $e)
            {
                die("err:".$e->getMessage());
            }
            // At this point we should either have "none" in the $slot, indicating no available room...
            // Or we have a slot to put the item in.
            if($slot === "none")
            {
                die("err:You don't have room in your inventory to craft this item.");
            }
            // Then place the specific item string into its own array.
            if($inventory[$slot] === "0")
            {
                $newData = array(); // Define an empty array if slot is empty.
            }
            else
            {
                $newData = explode(":", $inventory[$slot]); // Otherwise explode string into array: id, amount, texture
            }
            // Then calculate how much we would successfully craft.
            $craftedAmount = random_int($recipe['result_min_amount'], $recipe['result_max_amount']);
            if(empty($newData))
            {
                // If we're adding to an empty slot, construct the new array.
                $newData[] = $this->gItemDetails['id'];
                $newData[] = $craftedAmount;
                $newData[] = $this->gItemDetails['texture_name'];
                $newData = implode(":", $newData);
            }
            else
            {
                // If we're adding to existing entry, check that the addition will not exceed max_stack.
                if(($newData[1] + $recipe['result_max_amount']) > $this->gItemDetails['max_stack'])
                {
                    die("err:You need room for at least " . $recipe['result_max_amount'] . "x " . $this->gItemDetails['name'] . " to craft this item. You currently have " . $newData[1] . ".");
                }
                else
                {
                    $newData[1] = ($newData[1] + $craftedAmount);
                    $newData[2] = $this->gItemDetails['texture_name'];
                    $newData = implode(":", $newData);
                }
            }
            if($this->debug)
            {
                print_r($newData . "\n");
            }
            // Then finally, we're at this point ready to start the actual craft.
            // This will also have to include leveling.
            $this->invPdo->beginTransaction();
            try
            {
                $x = 0;
                $mats = array($recipe['material_one'], $recipe['material_two'], $recipe['material_three'], $recipe['material_four']);
                foreach($crafting as $sl)
                {
                    $tmp = explode(":", $inventory[$sl]);
                    $tmp[2] = $this->getItemDetails($tmp[0])['texture_name'];
                    $tmp[1] = ($tmp[1] - (int)explode(":", $mats[$x++])[1]);
                    if($tmp[1] === 0)
                    {
                        unset($tmp);
                        $tmp = "0";
                    }
                    $stmt = "UPDATE character_inventory
                    SET $sl = ?
                    WHERE char_id = ?
                    ";
                    $do = $this->invPdo->prepare($stmt);
                    if(!is_array($tmp))
                    {
                        $rString = $tmp;
                    }
                    else
                    {
                        $rString = implode(":", $tmp);
                    }
                    if(!$do->execute([$rString, $this->characterDetails['character_id']]))
                    {
                        $this->invPdo->rollBack();
                        exit("err:Rolled back.");
                    }
                }
                $stmt = "UPDATE character_inventory
                SET $slot = ?
                WHERE char_id = ?";
                $do = $this->invPdo->prepare($stmt);
                if(!$do->execute([$newData, $this->characterDetails['character_id']]))
                {
                    $this->invPdo->rollBack();
                    exit("err:Rolled back; could not execute craft.");
                }
                // And then we calculate experience gains!
                if($this->characterJobDetails['level'] !== $this->characterJobDetails['max_level'])
                {
                    $exp = random_int($recipe['experience_min'], $recipe['experience_max']);
                    $expResult = ($this->characterJobDetails['experience'] + $exp);
                }
                else
                {
                    $exp = 0;
                    $expResult = ($this->characterJobDetails['experience'] + $exp);
                }
                if((int)$this->characterJobDetails['level'] === (int)$this->characterJobDetails['max_level'])
                {
                    $nextExp = $this->levelExp[3];
                }
                else
                {
                    $nextExp = $this->levelExp[(int)($this->characterJobDetails['level'] - 1)];
                }
                if($expResult >= $this->characterJobDetails['exp_to_level'] and $this->characterJobDetails['level'] !== $this->characterJobDetails['max_level'])
                {
                    $this->characterJobDetails['level'] = ($this->characterJobDetails['level'] + 1);
                    if((int)$this->characterJobDetails['level'] === (int)$this->characterJobDetails['max_level'])
                    {
                        $nextExp = $this->levelExp[3];
                    }
                    else
                    {
                        $nextExp = $this->levelExp[(int)($this->characterJobDetails['level'] - 1)];
                    }
                }
                $stmt = "
                    UPDATE crafters
                    SET experience = ?, exp_to_level = ?, level = ?
                    WHERE char_id = ?
                ";
                $do = $this->invPdo->prepare($stmt);
                if(!$do->execute([$expResult, $nextExp, $this->characterJobDetails['level'], $this->characterDetails['character_id']]))
                {
                    $this->invPdo->rollBack();
                    exit("err:Could not update crafter table. Rolling back.");
                }
                $log = "Crafted {$craftedAmount}x " . $this->gItemDetails['name'] . " (ID: " . $this->gItemDetails['id'] . ").";
                $this->writeLog($this->usrDetails['username'], $this->usrDetails['uuid'], $this->characterDetails['character_id'], "crafting", $log, explode("=>", $this->characterDetails['titles'])[0]);
                $this->invPdo->commit();
            }
            catch (Exception $e)
            {
                $this->invPdo->rollBack();
                exit("err:Rolled back. Exception: " . $e->getMessage());
            }
            // And when we've made it here, it means everything is a-OK so return data for the handler.
            return $this->gItemDetails['name'] . "=>" . $craftedAmount . "=>" . $exp . "=>" . $this->characterJobDetails['level'];
        }
        function recipes($page)
        {
            // Get the recipes available to the character.
            // First prepare our procedural pagination.
            // I seriously fucking love this thing;
            // I am a genius for working this out!
            // Paginate by up to 9 entries per page, to allow for arrows and a Cancel option in in-game dialogs.
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
            $stmt = "
                SELECT id,recipe_name,material_one,material_two,material_three,material_four
                FROM crafting_recipes
                WHERE job_id = ? AND job_level_requirement <= ?;
                ORDER BY job_level_requirement ASC LIMIT $firstNum, $lastNum
            ";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                // Execute query.
                $do->execute([$this->characterJobDetails['job'], $this->characterJobDetails['level']]);
            }
            catch(Exception $e)
            {
                die("err:".$e->getMessage());
            }
            $out = "";
            $do = $do->fetchAll(PDO::FETCH_ASSOC);
            if(!$do)
            {
                die("err:This crafting job has no recipes yet.");
            }
            if($this->debug)
            {
                print_r($do);
            }
            foreach($do as $var)
            {
                if(empty($out))
                {
                    $out = implode("=>", $var);
                }
                else
                {
                    $out = $out . "><" . implode("=>", $var);
                }
            }
            $out = explode("><", $out);
            foreach($out as $var)
            {
                $k = array_keys($out, $var);
                $t = explode("=>", $var);
                $i = 2;
                do 
                {
                    $tmp = explode(":", $t[$i]);
                    if($tmp[0] !== "0")
                    {
                        $tmp[0] = $this->getItemDetails($tmp[0])['name'];
                    }
                    $t[$i] = implode(":", $tmp);
                    ++$i;
                } while ($i <= 5);
                $out[$k[0]] = implode("=>", $t);
            }
            $out = implode("><", $out);
            return $out;
        }
    }
?>