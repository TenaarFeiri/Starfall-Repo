<?php
    header('Content-type: text/plain');
    $headers = apache_request_headers();
    $debug = false;
    $dev = false;
    if($debug ^ $dev) { // Error handling only if we're debugging or developing.
        // ----------------------------------------------------------------------------------------------------
        // - Display Errors
        // ----------------------------------------------------------------------------------------------------
        ini_set('display_errors', 'On');
        ini_set('html_errors', 0);
        // ----------------------------------------------------------------------------------------------------
        // - Error Reporting
        // ----------------------------------------------------------------------------------------------------
        error_reporting(-1);

        // ----------------------------------------------------------------------------------------------------
        // - Shutdown Handler
        // ----------------------------------------------------------------------------------------------------
        function ShutdownHandler()
        {
            if(@is_array($error = @error_get_last()))
            {
                return(@call_user_func_array('ErrorHandler', $error));
            };

            return(TRUE);
        };

        register_shutdown_function('ShutdownHandler');

        // ----------------------------------------------------------------------------------------------------
        // - Error Handler
        // ----------------------------------------------------------------------------------------------------
        function ErrorHandler($type, $message, $file, $line)
        {
            $_ERRORS = Array(
                0x0001 => 'E_ERROR',
                0x0002 => 'E_WARNING',
                0x0004 => 'E_PARSE',
                0x0008 => 'E_NOTICE',
                0x0010 => 'E_CORE_ERROR',
                0x0020 => 'E_CORE_WARNING',
                0x0040 => 'E_COMPILE_ERROR',
                0x0080 => 'E_COMPILE_WARNING',
                0x0100 => 'E_USER_ERROR',
                0x0200 => 'E_USER_WARNING',
                0x0400 => 'E_USER_NOTICE',
                0x0800 => 'E_STRICT',
                0x1000 => 'E_RECOVERABLE_ERROR',
                0x2000 => 'E_DEPRECATED',
                0x4000 => 'E_USER_DEPRECATED'
            );

            if(!@is_string($name = @array_search($type, @array_flip($_ERRORS))))
            {
                $name = 'E_UNKNOWN';
            };

            return(print(@sprintf("%s Error in file \xBB%s\xAB at line %d: %s\n", $name, @basename($file), $line, $message)));
        };

        $old_error_handler = set_error_handler("ErrorHandler");
    }
    if(array_key_exists("X-SecondLife-Owner-Name", $headers) and array_key_exists("X-SecondLife-Owner-Key", $headers))
    {
        
    }
    else
    {
        if(!$debug)
        {
            die('err:You are not a Second Life client.');
        }
    }
    if(!$debug)
    {
        $arr = $_POST;
    }
    else
    {
        $arr = $_GET; // Make it a local array so I don't have to overwork myself when we switch to POST.
    }

    if((!isset($arr['usr']) or empty($arr['usr'])))
    {
        // If vital information is not included, just die. We need a char id, and we need a UUID to confirm that the character belongs to the client.
        die("err:No usr provided provided.");
    }
    else if(!isset($arr['func']) or empty($arr['func']))
    {
        // Can't handle storage requests if the handler isn't told what to do!
        die("err:No function provided, or function empty.");
    }
    // Passing all that, auto load classes as I need them.
    spl_autoload_register(function ($name) {
        include 'classes/faction.' . $name . '.php';
    });
    if(isset($arr['status']))
    {
        if($arr['func'] == "whoAmI" and isset($arr['usr']) and !empty($arr['usr']))
        {
            $factionStatus = new status($arr['usr']); // Make a new object!
            echo $factionStatus->whoAmI();
        }
    }
    else if(isset($arr['npc']))
    {
        if(isset($arr['func']) and $arr['func'] == "npcChkFaction" and isset($arr['usr']) and !empty($arr['usr']) and isset($arr['npcId']) and !empty($arr['npcId']))
        {
            // NPC Faction check!
            $factionNpcAction = new npc($arr['usr'], $arr['npcId']);
            $factionNpcAction->verifyUsrFaction();
        }
        else if(isset($arr['func']) and $arr['func'] == "npcVendor")
        {
            if(empty($arr['npcId']) or !isset($arr['npcId']))
            {
                exit("err:This NPC function was called without a valid npcId value.");
            }
            else if(empty($arr['usr']) or !isset($arr['usr']))
            {
                exit("err:No valid usr provided.");
            }
            else if(empty($arr['action']) or !isset($arr['action']))
            {
                exit("err:No valid action provided.");
            }
            $npcVendorAction = new npc($arr['usr'], $arr['npcId']);
            echo $npcVendorAction->executeAction($arr['action']);
        }
    }
    else if(isset($arr['membership']))
    {
        if($arr['func'] == "invite")
        {
            if(!isset($arr['target']) or empty($arr['target']))
            {
                exit("err:No target selected, or target is empty.");
            }
            else if(!isset($arr['faction']) or empty($arr['faction']))
            {
                exit("err:No faction specified, or empty faction.");
            }
            $factionMemberAction = new membership($arr['usr']);
            echo $factionMemberAction->addMemberToFaction($arr['target'], $arr['faction']);
        }
        else if($arr['func'] == "kick")
        {
            if(!isset($arr['target']) or empty($arr['target']))
            {
                exit("err:No target selected, or target is empty.");
            }
            else if(!isset($arr['faction']) or empty($arr['faction']))
            {
                exit("err:No faction specified, or empty faction.");
            }
            $factionMemberAction = new membership($arr['usr']);
            echo $factionMemberAction->removeMemberFromFaction($arr['target'], $arr['faction']);
        }
    }
?>