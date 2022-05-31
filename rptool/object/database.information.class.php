<?php
	if(!defined('noigneano93789hg2nopg')) {
   die('Direct access not permitted'); }
	//ini_set('display_errors',1);
	//error_reporting(E_ALL);
	// Database connection class for the Neckbeard RP Tool Online
	// Written by Tenaar Feiri
	/* 	Changelog:
				(August 4th, 2018)
			* Began work on creating the class.
			* Succeeded in creating database connection & pulling array from database.
			* Added function to close connection.
			* Added function to search and return specific rows from table 'users'.
			* Began work on a getAllTitles function.
			* Built in IP check functions. Should account for all kinds of IPs, though we only use the $ip, 'low-high' format.
			*
				(August 5th, 2018)
			*	Modified a fuckton of code.
			*	Added function to keep track of when the user interfaces with the server. (Always called when checking if user exists and it returns true.)
			*	Added function to register user (bug: version recording doesn't work yet)
			*
			August 6th, 2018
			*	Fixed version recording; Now records version of RP tool currently in use.
			*
			August 7th, 2018
			*	Removed implementation of HTTP server functionality; This is a free tool, and so we can just use the SL marketplace as a delivery platform.
			*
				(February 11th, 2019)
			* Implemented character deletion.
			* Began implementation of nuclear purge.
			*
	*/
	
	class rptoolDatabaseDetails {
		// Our database details class.
		// Initialize the server variables.
		private $server = "localhost";
		private $username = "user";
		private $password = "password";
		private $database = "rp_tool";
		private $debug = false;
		private $messErr_connectionDatabaseFailed = "Error : connection failed. Please try later.";
		private $pdo;
		
		function __construct() {
			// Open the database connection when object is created.
			$this->pdo = new PDO("mysql:host=$this->server;dbname=$this->database", $this->username, $this->password);
			if($this->debug) {
				$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			if(!$this->pdo) {
				die($messErr_connectionDatabaseFailed . "<br /><br />" . PDO::errorInfo());
			}
		}
		
		function closeConnection() {
			// Close the connection just to be sure.
			// We shouldn't -have- to do this, but I like to do it nonetheless.
			// This is also useful for when we're expecting no more interactions with the database
			// and maintaining a connection is just wasted.
			$this->pdo->connection = null;
		}
		
		function registerUser($username, $uuid, $version) {
			// If user does not exist, we will create and register them.
				// First create the timestamp. This will register the last active date.
				date_default_timezone_set("Europe/Oslo");
				$timestamp = date("Y.m.d");
				
				// Then prepare the statement.
				$insertNewUser;
				$insertStatement = "
					INSERT INTO users (username, uuid, version, registered, lastactive, lastchar)
					VALUES (:username, :uuid, :version, :registered, :lastactive, '0')
				";
				$insertNewUser = $this->pdo->prepare($insertStatement);
				$insertNewUser->bindParam(":username", $username);
				$insertNewUser->bindParam(":uuid", $uuid);
				$insertNewUser->bindParam(":version", $version);
				$insertNewUser->bindParam(":registered", $timestamp);
				$insertNewUser->bindParam(":lastactive", $timestamp);
				
				if($insertNewUser->execute()) {
					// If we've successfully inserted the new user, return "registereduser"
					// This will make rptool-main.php run this function again.
					return true;;
				}
				else {
					// Otherwise return false.
					return false;
				}
		}
		
		function updateTimeActive($userID, $version) {
			// If userExists is called and the user does exist, update last active time.
			date_default_timezone_set("Europe/Oslo");
			$timestamp = date("Y.m.d");
			/*if(!is_numeric($version)) {
				$version = 00000;
			}*/
			$updateStatement = "
					UPDATE users
					SET lastactive = :time, version = :vers
					WHERE id = :userid";
			$update = $this->pdo->prepare($updateStatement);
			$update->bindParam(":time", $timestamp);
			$update->bindParam(":userid", $userID);
			$update->bindParam(":vers", $version);
			if($update->execute()) {
				return true;
			} else {
				return false;
			}
			
		}
		
		function updateLastCharacter($userID, $charID) {
			$stmt = "
				UPDATE users
				SET lastchar = :char
				WHERE id = :user
			";
			$exec = $this->pdo->prepare($stmt);
			$exec->bindParam(":char", $charID);
			$exec->bindParam(":user", $userID);
			if($exec->execute()) {
				return true;
			}
			return false;
		}			
		
		function userExists($username, $uuid, $version) {
			// Verify that the user exists in the database, before all else,
			// then output the array.
			
			$statement = "
				SELECT * FROM users
				WHERE uuid = :uuid AND username = :username
				";
			$output = $this->pdo->prepare($statement);
			$result;
			// Sanitize the input by binding parameters.
			// Then unlike when you put the variable directly into the statement
			// the MySQL engine will always treat them as a string, preventing injection.
			$output->bindParam(":uuid", $uuid);
			$output->bindParam(":username", $username);
			if(!$output->execute()) {
				// Die if we couldn't execute the query.
				closeConnection();
				die('Could not execute!');
			} else {
				// But if we did execute the query, return false if result is empty.
				$result = $output->fetch(\PDO::FETCH_ASSOC);
				if(!$result) { 
						return false;
				} else {
					// If user does exist, return the whole array.
					$userID = $result['id'];
					// Update time and version number.
					if(!$this->updateTimeActive($userID, $version)) {
						exit("Could not update active time.");
					} else {
						// Database reflects the last version of the tool used, always.
						// As we don't want to do another call to SELECT the updated version,
						// we'll just manually configure the value.
						$result['version'] = $version;
					}
					return $result;
				}
			}
			// If we made it all the way down here, something went wrong.
			// We should not reach this point at all.
			return false;
		}
		
		
		function getAllCharTitles($userID, $charID) {
			// Return all character titles for the specified character.
			// Return false if character doesn't exist.
			$statement = "
				SELECT * FROM rp_tool_character_repository
				WHERE character_id = :char_id AND user_id = :user_id
				";
			$output = $this->pdo->prepare($statement);
			$result;
			$returnstring;
			$output->bindParam(":user_id", $userID, PDO::PARAM_INT); // bindParam automatically sanitizes. We don't technically need this for this data, but I like to be safe.
			$output->bindParam(":char_id", $charID, PDO::PARAM_INT);
			if($output->execute()) {
				$result = $output->fetch(\PDO::FETCH_ASSOC);
				$returnstring = "alltitles:charid=" . $result['character_id'] . ":" . $result['constants'] . "@T@" . $result['titles'] . "@T@" . $result['settings'];
				if(!$result) {
					printf("result is empty\n");
				}
				date_default_timezone_set("Europe/Oslo");
				$timestamp = date("Y.m.d");
				$updateTime = $this->pdo->prepare("
				
					UPDATE rp_tool_character_repository
					SET last_loaded = :time
					WHERE user_id = :userid AND character_id = :charid
				
				");
				$updateTime->bindParam(":userid", $userID);
				$updateTime->bindParam(":charid", $charID);
				$updateTime->bindParam(":time", $timestamp);
				if(!$updateTime->execute()) {
					printf("ERROR: Failed to update last loaded.");
				}
				return $returnstring;
			}
			
			return false;
		}
		
		
		function updateAll($charID, $userID, $data) {
			// Update the entry for specified character.
			// First let's see if it actually exists.
			// Easy enough. Just use the above function.
			if($this->getAllCharTitles($userID, $charID)) {
				// If character exists, do the update. Otherwise do nothing.
				$dataArr = explode("@T@", $data);
				$charname = $this->getCharName($dataArr);
				$statement = "
					UPDATE rp_tool_character_repository
					SET constants = :const, titles = :tits, settings = :set, char_name = :charname
					WHERE user_id = :userid AND character_id = :charid
				"; // char_name = :charname
				$output = $this->pdo->prepare($statement);
				$output->bindParam(":const", $dataArr[0]);
				$output->bindParam(":tits", $dataArr[1]);
				$output->bindParam(":set", $dataArr[2]);
				$output->bindParam(":userid", $userID);
				$output->bindParam(":charid", $charID);
				$output->bindParam(":charname", $charname);
				if($output->execute()) {
					return true;
				}
			}
			return false;
		}

		function getCharName($data)
		{
			$out;
			$arr = explode("=>", $data[1]);
			$out = $arr[0];
			return $out;
		}
		
		function createCharacter($userID) {
			// Create a new character associated with the userID.
			// This does not need to be sanitized; user ID is generated automatically in the database
			// and all other variables are procedurally generated as well.
			date_default_timezone_set("Europe/Oslo");
			$timestamp = date("Y.m.d");
			$output = $this->pdo->prepare("
				INSERT INTO rp_tool_character_repository (user_id, date_created, last_loaded) 
				VALUES (:id, :time, :othertime)
			");
			$output->bindParam(":time", $timestamp);
			$output->bindParam(":othertime", $timestamp);
			$output->bindParam(":id", $userID);
			if($output->execute()) {
				$getID = $this->pdo->prepare("SELECT character_id FROM rp_tool_character_repository WHERE user_id = '$userID' ORDER BY character_id DESC LIMIT 0, 1");
				if($getID->execute()) {
					
					$ret = $getID->fetch(\PDO::FETCH_ASSOC);
					//$test = "CharID: " . $ret['character_id'];
					//printf("Charid: '$test'");
					return $ret['character_id'];
				}
				return false;
			}
			return false;
		}

		function updateServer($server) {
			$statement = "UPDATE in_game_server SET server_address = :server";
			$result = $this->pdo->prepare($statement);
			$result->bindParam(":server", $server);
			if($result->execute()) {
				return true;
			}
			return false;
		}
		
		// Let's load characters.
		function getCharList($page, $userID) {
			$out = "nochars:";

				/*$lastNum = 9 * $page;
				$firstNum = "($lastNum - 9) + 1";
				eval("\$firstNum = $firstNum;");*/
			$firstNum;
			$lastNum = 9;
			if($page === 1) {
				$firstNum = 0;
			} else {
				$firstNum = 9 * $page - 9;
			}
			$statement = "
				SELECT * FROM rp_tool_character_repository WHERE (user_id = :id AND deleted = 0) ORDER BY character_id ASC LIMIT $firstNum, $lastNum
			";
			$query = $this->pdo->prepare($statement);
			$query->bindParam(":id", $userID);
			if($query->execute()) {
				$out = "charlist:";
				while($result = $query->fetch(\PDO::FETCH_ASSOC)) {
					$out = $out . $result['character_id'] . ":";
					$temp = $result['titles'];
					$tempArr = explode("=>", $temp);
					$out = $out . $tempArr[0] . "=>"; // Should add the name of the character.
				}
				$out = $out . "EOF"; // End Of File request for the RP tool.
			}
			return $out;
		}
		
		function resetCharacter($charID, $userID) {
			$stmt = "UPDATE rp_tool_character_repository SET constants = DEFAULT(constants), titles = DEFAULT(titles), settings = DEFAULT(settings) WHERE (user_id = :id AND character_id = :charid)";
			$query = $this->pdo->prepare($stmt);
			$query->bindParam(":id", $userID);
			$query->bindParam(":charid", $charID);
			if($query->execute()) {
				echo "Query executed.\n";
				return true;
			}
			return false;
		}
		
		function deleteCharacter($charID, $userID) {
			
			$stmt = "UPDATE rp_tool_character_repository
					SET deleted=1
					WHERE user_id = :id AND character_id = :charid";
			$query = $this->pdo->prepare($stmt);
			$query->bindParam(":id", $userID);
			$query->bindParam(":charid", $charID);
			if($query->execute()) {
				echo "Query executed.\n";
				return true;
			}
			return false;
		}
		
		function nuclearApocalypse($userID) {
			// BOOM! DECIMATION!
			// TODO: Program complete user purge that wipes all saved data, including username, UUID, characters, etc.
			// This is the nuclear option for people who wish completely disassociate from the RP tool.
			
		}
		
		
	}
?>
