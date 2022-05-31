<?php
	$latest = '1.1.1';
	$store = 'https://marketplace.secondlife.com/p/rptool-online/16624404';
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
	require_once('object/database.information.class.php');
	
	/*
		* Written by Tenaar Feiri
		* Main script for interfacing with the database through the titler.
		* Changelogs:
			August 4th, 2018
			*	Added IP check. Yet to confirm if it allows access from Second Life objects.
			*	Tested rptoolDatabaseDetails object, confirmed to work.
			*	Added debug value to disable IP check for testing purposes.
			*	
			August 6th, 2018
			*	Updated entire script with new error handling for debugging. (o)
			*	Changed the method by which the script accepts information from SL objects. (x)
			*	Implemented function for updating specific characters. (x)
			*	Started implementation on selecting and loading characters. ()
			*
			August 7th, 2018
			*	Implemented functioning version check that badgers users to update whenever they attach the RP tool and it's out of date.
			*	Removed implementation of HTTP server functionality; This is a free tool, and so we can just use the SL marketplace as a delivery platform.
			*
	*/
	// Time for one mother of a check!
	// This is to make sure that the page is being accessed by only Second Life objects.
	$ip = $_SERVER['REMOTE_ADDR'];
	$headers = apache_request_headers();
	$headerData = array(
		'username' => $headers["X-SecondLife-Owner-Name"],
		'uuid' => $headers["X-SecondLife-Owner-Key"]
	);
	$database;
	if(!empty($_POST)) {
		// If we're receiving post data.
		// All data should be url-decoded automatically from the client.
		/*
			Function list:
			1 = update all character information
			2 = update only constants
			3 = update only titles
			4 = update only options
			5 = get all data for character
			6 = get all registered characters
			7 = load character by ID
			8 = create new character
		*/
		
		$version;
		$userID;
		if(!isset($_POST['version'])) {
				// If, for some reason, no client version is supplied, set it to zero.
				// That way, client version will always be updated in the future.
				$version = '0.0';
			} else {
				$version = $_POST['version'];
			}
		// Let's check if user exists.
		// First, open the PDO connection.
		$database = new rptoolDatabaseDetails();
		// Then let's query for user data.
		$userData = $database->userExists($headerData['username'], $headerData['uuid'], $version);
		if(!$userData) {
			// If data doesn't exist, let's register the user.
			if(!$database->registerUser($headerData['username'], $headerData['uuid'], $version)) {
				// If we can't register the user for some reason, die if we're debugging.
				if($debug) {
					die();
				}
				else {
					exit("ERROR\nCould not register new user!");
				}
			} else {
				// But if we did successfully register them, awesome!
				// Let's do the query again to make sure we're good.
				$userData = $database->userExists($headerData['username'], $headerData['uuid'], $version);
				if(!$userData) {
					// If this fails, print error and stop the entire script.
					exit("ERROR\nUser attempted registered, but failed to save.");
				} else {
					printf("newuser:Hello secondlife:///app/agent/" . $headerData['uuid'] . "/about" . "! You are now a registered user of the RP tool, and can now create a character. 
					Please hit \"NEW\" on the HUD or type \"/1 newchar\" (without the quotes, and on the channel you are currently using) to make your first character.\nYour character information will save automatically whenever you update your titler.\n\n");
				}
				
			}
		}
	}
		if(isset($_POST['updall'])) { // If we're updating constants and titles at once.
			// Double conditional here so that we are certain that we're updating the titler.
			if($_POST['updall'] != 1) {
				exit();
			}
			if(isset($_POST['data']) && isset($_POST['charid'])) {
				if($_POST['charid'] === 0)
				{
					printf("Character ID is 0; Cannot update invalid character. Please contact secondlife:///app/agent/5675c8a0-430b-4281-af36-60734935fad3/about for assistance.");
					exit();
				}
				// We only want to update this if there is a charid and data param. Otherwise do nothing.
				if(!$database->updateAll($_POST['charid'], $userData['id'], $_POST['data'])) {
					printf("ERROR: Couldn't update all titles for character id " . $_POST['charid']);
				} else {
					printf("Update successful!");
				}
			} else {
				printf("ERROR: Expected vars &data and &charid. One or the other is missing.");
				exit();
			}
		} else if(isset($_POST['checkversion'])) {
			// Do a version check!
			if($userData['version'] < $latest) {
				// If this is true, query the server I will set up later.
				// For now this is blank.
				$badgering = "plzupdate:NOTICE: Hi secondlife:///app/agent/" . $headerData['uuid'] . "/about"."! Your RP tool version is OUT OF DATE.\nThere is a new update available. [$store Please visit the marketplace and download the latest version at your earliest convenience!]";
				printf($badgering);
			}
	} else if(isset($_POST['loadchar']) && $_POST['loadchar'] > 0) {
			$chardata = $database->getAllCharTitles($userData['id'], $_POST['loadchar']);
			if($chardata) {
				if(!$database->updateLastCharacter($userData['id'], $_POST['loadchar']))
				{
					// Update lastchar. This should never fail but you never know!
					printf("Failed to update lastchar.");
					exit();
				}
				printf($chardata);
			} else {
				printf("Failed to load.");
			}
		} else if(isset($_POST['charlist'])) {
		$page = $_POST['charlist'];
		echo $database->getCharList($page, $userData['id']);		
	} else if(isset($_POST['create'])) {
		// If we're creating a character, create the char and then send the loading signal!
		$returnedCharID = $database->createCharacter($userData['id']);
		if($returnedCharID) {
			$chardata = $database->getAllCharTitles($userData['id'], $returnedCharID);
			if($chardata) {
				if($database->updateLastCharacter($userData['id'], $returnedCharID))
				{
					// Just doing this so the bool won't error. It shouldn't, but you never know.
				}
				printf($chardata);
			} else {
				printf("ERROR: New character created, but could not load.");
			}
		} else {
			printf("ERROR: Could not create character.");
		}
	} else if(isset($_POST['test'])) {
		// Test function.
		// Used for debugging HTTP Response parsing.
		printf("newuser:This is a test string.");
	} else if(isset($_POST['reset']) && isset($_POST['charID'])) {
		// Reset a character.
		if($database->resetCharacter($_POST['charID'], $userData['id'])) {
			$chardata = $database->getAllCharTitles($userData['id'], $_POST['charID']);
			if($chardata) {
				if(!$database->updateLastCharacter($userData['id'], $_POST['charID']))
				{
					// Update lastchar. This should never fail but you never know!
					printf("Failed to update lastchar.");
					exit();
				}
				printf($chardata);
			} else {
				printf("Failed to load.");
			}
		} else {
			printf("Failed to reset character: " . $_POST['charID']);
		}
		
	} else if(isset($_POST['delete']) && $_POST['delete'] > 0) {
			//echo "Hello!";
			if(!$database->deleteCharacter($_POST['delete'], $userData['id']))
			{
				die('Couldn\'t delete!');
			}
		} else {
		// If $_POST is empty, do nothing.
		exit();
	}
	
?>
