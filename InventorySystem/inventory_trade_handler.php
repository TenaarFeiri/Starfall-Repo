<?php

header('Content-type: text/plain');
define('noigneano93789hg2nopg', true);
// Request header data to verify client is Second Life.
$headers = apache_request_headers();
// Auto load classes as I need them.
spl_autoload_register(function ($name) {
    include 'classes/inventory.' . $name . '.php';
});
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
if(array_key_exists("X-SecondLife-Owner-Name", $headers) and array_key_exists("X-SecondLife-Owner-Key", $headers) and !$debug)
{
    if($debug)
    {
        $args = $_GET;
    }
    else
    {
        $args = $_POST;
    }
}
else
{
    if(!$debug)
    {
        die('err:You are not a Second Life client.');
    }
    $args = $_GET;
}
if(!isset($args['target']))
{
    die("err:No target UUID specified.");
}
else if(!isset($args['me']) and $debug)
{
    die("err:Debug mode. Please specify me variable.");
}
if($debug)
{
    $me = $args['me'];
    $trade = new trader($me, $args['target']);
}
else
{
    $me = $headers["X-SecondLife-Owner-Key"];
    $trade = new trader($me, $args['target']);
}

if(isset($args['id']) and isset($args['amount']) and !isset($args['money']))
{
    // This really should just be this simple.
    $trade->giveItem($args['id'], $args['amount']);
}
else if(isset($args['money']) and isset($args['amount']))
{
    // And this is for trading money. Just use one trade script for both functions!
    $trade->giveMoney($args['amount']);
}

?>