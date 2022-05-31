<?php
    /*
        Catch-all class for handling blurbs and quests.
    */
    require_once('database.php');
    class quests
    {
        private $debug = true;
        private $invPdo; // Interacting with the inventory system.
        private $rptPdo; // Interacting with the RP tool system.
        private $uuid; // uuid of the user.
        private $charData; // Character id of the user.
        private $charInventory; // Array containing the current inventory of the user, including money.
        private $charJobId; // ID of the character's job, if any.
        private $charJobData; // Data array for the job.
        private $npcId; // ID of the NPC.
        private $npcData; // Including names, quest chains, etc.
        private $blurbArr; // Array containing blurb options from client.
        private $dbBlurb; // Array for blurb data from database.
        private $questLog; // Array containing quest log data.
        private $options;
        function __construct($uuid, $npcId) {
            // When operating inside a class, we use the $this-> prefix in lieu of simple $ to access variables.
            // This is a restriction imposed by PHP to allow for the compartmentalisation of variables,
            // preventing them from being accessed outside  the class where inappropriate.
            $this->invPdo = connectToInventory(); // Instantiate new PDO object for the inventory database.
            $this->rptPdo = connectToRptool(); // Instantiate new PDO object for the RP tool datababe.
            $this->uuid = $uuid; // Then bind the UUID provided to the constructor to a variable.
            $this->npcId = $npcId; // And also bind the NPC's ID, as this makes it easier to access data relevant to him.
            $this->npcData = $this->getNpcData(); // Get the NPC data!
            $this->getCharacterData(); // Use the now instantiated variables to obtain the character data we need for the object class.
            if($this->debug)
            {
                print_r($this->charInventory);
                print_r($this->charData);
                if($this->charJobId)
                {
                    print_r($this->charJobData);
                }
                print_r($this->npcData);
            }
        }
        function getCharacterData() // Get relevant character data to store in the object class.
        {
            // Declare a statement to get the last used character from the RP Tool DB.
            // 'lastchar' will always be the currently loaded character.
            $stmt = "SELECT lastchar FROM users WHERE uuid = ?";
            $do = $this->rptPdo->prepare($stmt); // Prepare the statement, i.e sanitise it. Always sanitise, even data users shouldn't be able to control.
            // No transaction is needed here, so let's execute.
            try // Try-Catch function attempts to perform the bracketed tasks, and then catches errors for graceful error handling if it fails.
            {
                $do->execute([$this->uuid]); // As our sanitisation tag for this statement is ?, we create an array between [], and execute will replace each ? in the order that they appear, moving down the list.
                $do = $do->fetch(PDO::FETCH_ASSOC); // If the earlier statement was correct, fetch an associative array of the results.
                // An associative array functions as a Key-Value pair, where each variable has a key associated with it to make it easier to directly grab.
                if($do)
                {
                    // If there is something in the array, then we update the variable.
                    // Specifically we want to get all character data from the repo.
                    $this->charData = $do['lastchar'];
                    $stmt = "SELECT * FROM rp_tool_character_repository WHERE character_id = ?";
                    $do = $this->rptPdo->prepare($stmt);
                    $do->execute([$this->charData]); // Execute again.
                    $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array of the results.
                    $this->charData = $do; // Then dump the thing into the private variable.
                    // This all happens within the try-catch statement so error handling is already being taken care of.
                }
                else
                {
                    // Otherwise we kill the program!
                    exit("err:Invalid UUID or character id.");
                }
            } catch(Exception $e) {
                // And if an error has happened, kill the program & output the specific error that we caught.
                exit("err:" . $e->getMessage());
            }
            // Now that we're through that, we reuse the statement and do variables to query the inventory data from the inventory DB.
            $stmt = "SELECT item_1,item_2,item_3,item_4,item_5,item_6,item_7,item_8,item_9,money FROM character_inventory WHERE char_id = ?";
            // Same deal as before. Prepare, sanitise, execute.
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->charData['character_id']]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
                if($do)
                {
                    $this->charInventory = $do;
                }
                else
                {
                    die("err:Could not get inventory for character ID: " . $this->charData['character_id']);
                }
            } catch(Exception $e) {
                exit("err:" . $e->getMessage());
            }
            // Next we collect the character's job ID, if they have one.
            $stmt = "SELECT job FROM crafters WHERE char_id = ?";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->charData['character_id']]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
                if($do)
                {
                    $this->charJobId = $do['job'];
                    // Then get the job data like names and such from the job ID.
                    $stmt = "SELECT * FROM crafting_jobs WHERE id = ?";
                    $do = $this->invPdo->prepare($stmt);
                    $do->execute([$this->charJobId]);
                    if($do)
                    {
                        // And then if we find it, store it in a variable.
                        $do = $do->fetch(PDO::FETCH_ASSOC);
                        $this->charJobData = $do;
                    }
                    else
                    {
                        // If we can't, then the job is invalid!
                        exit("err:Job does not exist.");
                    }
                }
                else
                {
                    $this->charJobId = false; // False is no job.
                }
            } catch(Exception $e)
            {
                exit("err:" . $e->getMessage());
            }
            // If nothing has happened by now, then the above queries have completed successfully, and the function ends.
            // No need to return anything.
        }
        function getNpcData()
        {
            // When a player touches the NPC & begins a session, get the default blurb.
            // This will vary if the player has a job.
            $stmt = "SELECT * FROM npc_database WHERE id = ?";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->npcId]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
            } catch(Exception $e)
            {
                exit("err:" . $e->getMessage());
            }
            return $do;
        }
        function parseReplaceStrings($data)
        {
            // This function looks for wildcards in the string to replace things with!
            $wildcards = array(
                "%name%", // Character's name.
                "%job%", // Character's job!
                "%job_pronoun%", // Character's job pronoun
            );
            $replace = array(
                explode("=>", $this->charData['titles'])[0],
                $this->charJobData ? $this->charJobData['name'] : "", // This is a built-in conditional array. If we have a job, it will replace with relevant details.
                $this->charJobData ? $this->charJobData['pronoun'] : "" // Otherwise, it'll just delete the wildcard.
            );
            return str_replace($wildcards, $replace, $data); // And finally return the fully parsed string.
        }
        function openBlurb($data)
        {
            // Open specific blurb data.
            $out = ""; // Initialise the out veriable.
            if($data === "0")
            {   // If data is exactly 0, then we're opening the NPC's default blurb!
                $arr = $data;
                if($this->npcData['job_blurb'] === "0" or !$this->charJobId) // If NPC does not do any job specific dialogue, get and return the default blurb.
                {
                    $stmt = "SELECT * FROM blurbs WHERE id = ?"; // Prepare a sanitised statement, as always.
                    $do = $this->invPdo->prepare($stmt);
                    try
                    {
                        $do->execute([$this->npcData['default_blurb']]); // Query blurbs for the default blurb!
                        $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array. We could also get a numeric once, but assoc for consistency.
                        if(!$do)
                        {
                            // If we fetched nothing, exit gracefully!
                            exit("err:No blurb with ID " . $this->npcData['default_blurb'] . " exists.");
                        }
                        else $this->dbBlurb = $do;
                    }catch(Exception $e)
                    {
                        exit("err:" . $e->getMessage()); // Exit and report the error to the client if one occurs.
                    }
                    // Then we parse it!
                    $out = $this->parseReplaceStrings($do['blurb_text']); // Return a parsed blurb string.
                }
                else
                {
                    $jobArr = array(); // Instantiate an array.
                    foreach(explode(",", $this->npcData['job_blurb']) as $item)
                    { // Then look through each of the job blurb options to construct a new array.
                        $jobArr[explode(":", $item)[0]] = explode(":", $item)[1]; // This one has the job ID number as the array key, and the value as the blurb ID.
                    }
                    if(!array_key_exists($this->charJobId, $jobArr)) // Check to see if an option exists for the user's current job.
                    {
                        $blurb = $this->npcData['default_blurb']; // If it doesn't, use the default blurb.
                    }
                    else
                    {
                        $blurb = $jobArr[$this->charJobId]; // Otherwise, get the blurb ID.
                    }
                    $stmt = "SELECT * FROM blurbs WHERE id = ?"; // Prepare a sanitised statement, as always.
                    $do = $this->invPdo->prepare($stmt);
                    try
                    {
                        $do->execute([$blurb]); // Query blurbs for the default blurb!
                        $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array. We could also get a numeric once, but assoc for consistency.
                        if(!$do)
                        {
                            // If we fetched nothing, exit gracefully!
                            exit("err:No blurb with ID " . $blurb . " exists.");
                        }
                        else $this->dbBlurb = $do;
                    }catch(Exception $e)
                    {
                        exit("err:" . $e->getMessage()); // Exit and report the error to the client if one occurs.
                    }
                    // Then we parse it!
                    $out = $this->parseReplaceStrings($do['blurb_text']); // Return a parsed blurb string.
                }
            }
            else
            {
                // Otherwise, we create an array of the data string.
                // It's a nested array, which we'll loop through.
                $arr = explode(":", $data);
                $options = array();
                foreach($arr as $o)
                {
                    // For each option variable, split them into their own array entries.
                    // We're doing it like this because then it's easier to look by array key for the big check 
                    // coming later.
                    $o = explode(";", $o);
                    $c = false;
                    $str = "";
                    foreach($o as $ob)
                    {
                        // This is such a retarded solution.
                        // Manually construct an associative array
                        // from a stupid string.
                        // We don't even have to do it this way, it's just lazy.
                        // And it's easier to implement in the database.
                        if($c)
                        {
                            $options += [$str => $ob];
                            $c = false;
                        }
                        else
                        {
                            $str = $ob;
                            $c = true;
                        }
                    }
                }
                $this->options = array_map('trim', $options);
                print_r($this->options);
                $out = $this->parseOptions(); // This will return a digestible string based on the options provided.
            }
            return $out;
        }
        function findInventoryItem($data)
        {
            // Currently unfinished function. Find the correct item in the user's inventory.
            // Return an array containing item's position and parsed item string.
            // In other words, here is where we calculate stack reduction.
            return false; // Return false by default.
        }
        function hasOption($option)
        {
            return array_key_exists($option, $this->options);
        }
        function parseOptions()
        {
            /*
                This function is the meat of the whole quests class. This will parse any options provided to the script in strict order.
                This function will also override any attempt to access a specific blurb if the conditions are right for it.
                The writer is responsible for making sure that there are no collisions.

                CRITICAL options indexes can abort the script sequence, preventing other options from being parsed. CRITICAL options will always be parsed first, in order from most to least critical.
                Most other options are in arbitrary order.

                SYNTAX INDEX
                (()) -- Separator. Used for strided listing in SL. Function(())Data(())Function(())Data, etc.
                blurbText(())<text_here> -- Replace <text_here> with blurb text. blurbText function tells the SL script to display the blurb in a dialog.
                blurbOptions(())<options_here> -- Replace <options_here> with button prompts.
                blurbOptionsCmd(())<commands> -- String containing data SL script will send back to the server based on its corresponding option.

                OPTIONS INDEX
                isCorrupted -- Bigger than 0 if corrupted. This number is the blurb we want the NPC to say if a character is corrupted. CRITICAL
                hasItem -- Must be bigger than 0. Will be ignored otherwise. Requires one particular item to be in the character's inventory, and can specify an amount of them that need to be present. CRITICAL
            */
            $out = "";
            $override = false;
            if($this->hasOption("isCorrupted"))
            {
                if($this->options['isCorrupted'] > 0)
                {
                    // If the NPC will say something different to a corrupted individual, pull that blurb.
                    // This will override blurb ID options, as well as any other option.
                    $override = "isCorrupted"; // This will indicate WHY we are overridden, i.e why $out cannot be changed.
                    // Anything that doesn't change the output string will continue to run.
                    $stmt = "SELECT * FROM blurbs WHERE id = ?"; // Prepare a sanitised statement, as always.
                    $do = $this->invPdo->prepare($stmt);
                    try
                    {
                        $do->execute([$this->options['isCorrupted']]); // Query blurbs for the default blurb!
                        $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array. We could also get a numeric once, but assoc for consistency.
                        if(!$do)
                        {
                            // If we fetched nothing, exit gracefully!
                            exit("err:No blurb with ID " . $this->options['isCorrupted'] . " exists.");
                        }
                    }catch(Exception $e)
                    {
                        exit("err:" . $e->getMessage()); // Exit and report the error to the client if one occurs.
                    }
                    // Then we parse it!
                    return "blurbText(())" . $this->parseReplaceStrings($do['blurb_text']) . "(())blurbOptions(())" . $do['choices'] . "(())blurbOptionsCmd(())" . $do['choice_data']; // Return a parsed blurb string + options.
                }
            }
            if($this->hasOption("hasItem"))
            {
                // Check to see if user has a specific item.
                // If user does not have an item, exit this function with a blurb from
                // noItem key.
                $inv = $this->findInventoryItem($this->options['hasItem']);
                if(!$inv) // If inventory does not have the required item, the check fails and the script returns a blurb & exits. No further options are processed.
                {
                    if(!$this->hasOption("noItem"))
                    {
                        // If no noItem variable is produced, just give the default NPC blurb.
                        $stmt = "SELECT * FROM blurbs WHERE id = ?"; // Prepare a sanitised statement, as always.
                        $do = $this->invPdo->prepare($stmt);
                        try
                        {
                            $do->execute([$this->npcData['default_blurb']]); // Query blurbs for the default blurb!
                            $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array. We could also get a numeric once, but assoc for consistency.
                            if(!$do)
                            {
                                // If we fetched nothing, exit gracefully!
                                exit("err:No blurb with ID " . $this->npcData['default_blurb'] . " exists.");
                            }
                        }catch(Exception $e)
                        {
                            exit("err:" . $e->getMessage()); // Exit and report the error to the client if one occurs.
                        }
                        // Then we parse it!
                        return "blurbText(())" . $this->parseReplaceStrings($do['blurb_text']) . "(())blurbOptions(())" . $do['choices'] . "(())blurbOptionsCmd(())" . $do['choice_data']; // Return a parsed blurb string + options.
                    }
                    else
                    {
                        // Otherwise return the blurb for the lack of item.
                        $stmt = "SELECT * FROM blurbs WHERE id = ?"; // Prepare a sanitised statement, as always.
                        $do = $this->invPdo->prepare($stmt);
                        try
                        {
                            $do->execute([$this->options['noItem']]); // Query blurbs for the provided noItem parameter.
                            $do = $do->fetch(PDO::FETCH_ASSOC); // Fetch an associative array. We could also get a numeric once, but assoc for consistency.
                            if(!$do)
                            {
                                // If we fetched nothing, exit gracefully!
                                exit("err:No blurb with ID " . $this->options['noItem'] . " exists.");
                            }
                        }catch(Exception $e)
                        {
                            exit("err:" . $e->getMessage()); // Exit and report the error to the client if one occurs.
                        }
                        // Then we parse it!
                        return "blurbText(())" . $this->parseReplaceStrings($do['blurb_text']) . "(())blurbOptions(())" . $do['choices'] . "(())blurbOptionsCmd(())" . $do['choice_data']; // Return a parsed blurb string + options.
                    }
                }
                else
                {
                    /*
                        But if we have found the item, then we need to do a bunch of things!
                    */
                    // THIS IS WHERE I STOPPED LAST TIME
                }
            }
            return $out;
        }
    }
?>