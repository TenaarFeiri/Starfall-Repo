<?php
    // Handler for all staff-related inventory functions.
    header('Content-type: text/plain');
    require_once('classes/database.php');
    $staffList = array( // Complete array of authorised users/staff.
        "Tenaar Feiri",
        "Anippe Resident",
        "Ashtyn Ninetails",
        "Ash Hammerthall",
        "Kaeldan Monk",
        "Elerlissa Ashbourne",
        "Symphicat Resident",
        "F3NN3CF0X Resident",
        "Cylliano Dreamscape",
        "Athian Hoggard",
        "Felix Stourmead",
        "Ani Aunerfal",
        "Jaecar Ulrik",
        "Xaedrian Resident",
        "DruonGrawal Resident",
        "Sesti Arentire"
    );
    // Auto load classes as I need them.
    spl_autoload_register(function ($name) {
        include 'classes/inventory.' . $name . '.php';
    });
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
    if($debug) // Use $_GET when we're debugging, so I don't have to fuck with $_POST. In this way I can develop outside of SL.
    {
        $arr = $_GET;
    }
    else
    {
        $arr = $_POST;
    }
    if(array_key_exists("X-SecondLife-Owner-Name", $headers))
    {
        $owner = $headers['X-SecondLife-Owner-Name']; // Not necessary, just easier. Put owner name in its own var.
        if(!in_array($owner, $staffList))
        {
            die("err:You are not an authorised user.");
        }
        if(isset($arr['hudPing']))
        {
            echo "admin_usr"; // Echo admin_usr so the HUD can know the user is an admin.
        }
    }
    else
    {
        if(!$debug)
        {
            die('err:You are not a Second Life client.');
        }
        if(!isset($arr['username']) and $debug)
        {
            die("err:Debug mode. Username not set.");
        }
        else
        {
            $owner = $arr['username'];
        }
        if(!in_array($owner, $staffList))
        {
            die("err:You are not an authorised user.");
        }
    }
    if(!isset($arr['func']) or empty($arr['func']))
    {
        die("err:Func parameter empty or not set."); // Kill script if we have no functions!
    }
    if(!$debug)
    {
        $staffAction = new staff($owner, $headers['X-SecondLife-Owner-Key']); // Create a new object! Store the key and UUID of the staff member initiating it, for log purposes. 
    }
    else
    {
        $staffAction = new staff($owner, "Test-Uuid"); // Create a new object! Store the key and UUID of the staff member initiating it, for log purposes. 
    }
    // HUD CHECK IF CURRENT USER IS STAFF
    if($arr['func'] === "chkStaff")
    {
        // Check if owner can access staff menu.
        if(in_array($owner, $staffList))
        {
            echo "::isStaffMember::";
        }
        // Otherwise do nothing. Default is no access.
    }
    // CHECK INVENTORY
    else if($arr['func'] === "chkChar" and isset($arr['target']) and !empty($arr['target']) and isset($arr['page']) and !empty($arr['page']))
    {
        echo $staffAction->getCharList($arr['target'], $arr['page']); // Open character list to check inventory.
    }
    // GET LOG DETAILS
    else if($arr['func'] === "chkLogs" and isset($arr['page']) and !empty($arr['page']))
    {
        echo $staffAction->getLogs($arr['page']);
    }
    // SEARCH LOGS
    else if($arr['func'] === "srchLogs" and isset($arr['page']) and !empty($arr['page']) and isset($arr['search']) and !empty($arr['search']))
    {
        echo $staffAction->searchLogs($arr['search'], $arr['page']);
    }
    // GIVE MONEY TO ACTIVE CHARACTER
    else if($arr['func'] === "giveMoney" and (isset($arr['targets']) and !empty($arr['targets'])) and (isset($arr['amount'])))
    {
        // Perform the staff action addMoneyToCurrentUserCharacter, which will add money to the player's currently loaded character.
        echo $staffAction->addMoneyToMultiple($arr['targets'], $arr['amount']);
    }
    // GET CHARACTER'S INVENTORY
    else if($arr['func'] === "getCharInventory" and (isset($arr['target']) and !empty($arr['target'])))
    {
        echo $staffAction->getCharacterInventory($arr['target']);
    }
    // GET SPECIFIC CHARACTER INVENTORY
    else if($arr['func'] === "getSpecificCharInventory" and (isset($arr['charId']) and !empty($arr['charId'])))
    {
        echo $staffAction->getSpecificCharacterInventory($arr['charId']);
    }
    // ADD ITEMS TO ONE OR MORE ACTIVE CHARACTER
    else if($arr['func'] === "addItemToMultiple" and (isset($arr['targets']) and !empty($arr['targets']) and isset($arr['itemId']) and !empty($arr['itemId']) and isset($arr['amount']) and !empty($arr['amount'])))
    {
        echo $staffAction->addItemToMultiple($arr['targets'], $arr['itemId'], $arr['amount']);
    }
    else if($arr['func'] === "test")
    {
        //print_r($staffAction->addItemToMultiple($arr['targets'], "1", "99"));
    }
?>