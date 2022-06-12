<?php
    header('Content-type: text/plain');


    define('_debug', true);
    if(_debug) { // Error handling only if we're debugging or developing.
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
    define('noigneano93789hg2nopg', true);
    // Auto load classes as I need them.
    spl_autoload_register(function ($name) {
        include 'classes/inventory.' . $name . '.php';
    });
    ///////////////////////////////////////////////////////////////////////////////////
    require_once('classes/database.php');
    // Request header data to verify client is Second Life.
    $headers = apache_request_headers();
    if(array_key_exists("X-SecondLife-Owner-Name", $headers) and array_key_exists("X-SecondLife-Owner-Key", $headers))
    {
        $headerData = array(
            'username' => $headers["X-SecondLife-Owner-Name"],
            'uuid' => $headers["X-SecondLife-Owner-Key"]
        );
    }
    else
    {
        if(!_debug)
        {
            die('You are not a Second Life client.');
        }
    }
    if(!_debug)
    {
        $arr = $_POST;
    }
    else
    {
        $arr = $_GET;
    }
    if(!_debug)
    {
        if(!isset($arr['usr']))
        {
            $usr = $headerData['usr'];
        }
        else
        {
            $usr = $arr['usr'];
        }
    }
    else
    {
        $usr = $arr['usr'];
    }

    if(empty($arr))
    {
        die('No arr data detected.');
    }
    else if(!isset($arr['func']))
    {
        die('function param is not set');
    }
    else if(!isset($arr['charId']))
    {
        $qObj = connectToRptool();
        $stmt = "SELECT lastchar FROM users WHERE uuid = :usr OR username = :usr";
        $q = $qObj->prepare($stmt);
        $q->bindParam(":usr", $arr['usr']);
        if(!$q->execute())
        {
            die("err:Could not execute inventory handler uuid2charid function.");
        }
        $q = $q->fetch();
        $charId = $q[0];
    }
    else
    {
        $charId = $arr['charId'];
    }
    $verifyUsr;
    $verified = false;
    $verifyUsr = new verify();
    $verified = $verifyUsr->verifyOwner($charId, $usr);
    if(!$verified)
    {
        if($arr['func'] == "getlastid")
        {
            $verifyUsr = null;
            $verifyUsr = new verify();
            $verifyUsr->getLastId($usr);
        }
        else
        {
            if(!_debug)
            {
                die('err:Verification failed: You are not the owner of this character.');
            }
        }
    }

    // If we survive both of these checks, we are gucci.
    // Time for the meat!
    $mngr = new management($charId);
    if($arr['func'] == "test")
    {
        echo $mngr->showData("9");
    }
    if(isset($arr['show']) and isset($arr['itemId']) and !empty($arr['itemId']))
    {
        if($arr['func'] == "getDetails")
        {
            echo $mngr->showData("9");
        }
    }
?>
