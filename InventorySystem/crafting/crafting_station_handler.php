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

    if((!isset($arr['uuid']) or empty($arr['uuid'])))
    {
        // If vital information is not included, just die. We need a char id, and we need a UUID to confirm that the character belongs to the client.
        die("err:No uuid provided provided.");
    }
    else if(!isset($arr['func']) or empty($arr['func']))
    {
        // Can't handle storage requests if the handler isn't told what to do!
        die("err:No function provided, or function empty.");
    }
    else if(!isset($arr['class']) or empty($arr['class']))
    {
        // Die if no crafting class is defined.
        die("err:Station has not provided a crafting job ID.");
    }
    // Passing all that, auto load classes as I need them.
    spl_autoload_register(function ($name) {
        include 'classes/crafting.' . $name . '.php';
    });
    $craft = new station($arr['uuid'], $arr['class']);
    if($arr['func'] === "chkChar")
    {
        // Called when user touches the crafting station.
        if($craft->chkChar())
        {
            // If true, character has the crafting job so inform the station.
            if(!$craft->jobChk())
            {
                echo "fail:check:diffjob:" . $craft->characterName(); // Fail it if the user's job does not match the station.
            }
            else
            {
                echo "success:check:" . $craft->characterName();
            }
        }
        else
        {
            // Inform the table that the user has no job, if this failed.
            echo "fail:check:nojob:". $craft->characterName();
        }
    }
    else if($arr['func'] === "getPage" and isset($arr['page']) and !empty($arr['page']))
    {
        if($craft->jobChk())
        {
            $create = new create($craft->getCharDetails(), $craft->getCharClassDetails());
            echo $create->recipes($arr['page']);
        }
        else
        {
            exit("err:You are not the right job to view this menu.");
        }
    }
    else if($arr['func'] === "create" and isset($arr['recipeId']) and !empty($arr['recipeId']))
    {
        if($craft->jobChk())
        {
            $create = new create($craft->getCharDetails(), $craft->getCharClassDetails());
            $out = $create->create($arr['recipeId']);
            echo $out;
        }
        else
        {
            exit("err:You are not the correct job for this crafting station.");
        }
    }
    else if($arr['func'] === "register")
    {
        echo $craft->addCrafter();
    }
    else if($arr['func'] === "remove")
    {
        echo $craft->removeCrafter();
    }
    else if($arr['func'] === "chkStats")
    {
        echo $craft->chkStats();
    }
?>