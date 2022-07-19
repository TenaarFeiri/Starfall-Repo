<?php
header('Content-type: text/plain');
/*
    Inventory Gathernode Handler by Tenaar Feiri

    TODO
        - Build a gathering node function.
*/

$debug = false;
if($debug) { // Error handling only if we're debugging or developing.
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

// Request header data to verify client is Second Life.
$headers = apache_request_headers();
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
if(!isset($arr['uuid']) or empty($arr['uuid']))
{
    die("err:No uuid details provided.");
}
if(empty($arr))
{
    die('err:No arr data detected.');
}
else if(!isset($arr['func']))
{
    // func HAS to be set for the handler to know what to do. Die without it.
    die('err:function param is not set');
}
// At this point, we KNOW we will access gathernode class, so instantiate it.
$gather = new gathernode($arr['uuid'], $arr['itemId']);
if($arr['func'] == "gatherItem")
{
    if(!isset($arr['itemId']) or empty($arr['itemId'])) // Moved the death function here as we may need it locally.
    {
        die("err:No itemId provided.");
    }
    echo $gather->attemptGather(); // Attempt to gather!
}
else if($arr['func'] == "getItemDetails")
{
    if(!empty($arr['items']) and isset($arr['items']))
    {
        echo $gather->getItems($arr['items']);
    }
    else
    {
        exit("err:items param not set.");
    }
}
else if($arr['func'] == "viewItem")
{
    if(empty($arr['itemId']) or !isset($arr['itemId']))
    {
        exit("err:No itemId defined.");
    }
    echo $gather->viewItem();
}

?>