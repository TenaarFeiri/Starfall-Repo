<?php

// Dice rolling feature!
// Written by Tenaar Feiri
// Changelogs:
//              - It's far more valuable to know the total sum of the roll than it is to know the average.
//              - Added additional filtering to ensure numbers are being supplied.
//
//
//
/*
    Some notes to keep in mind:
    - Entering a dice roll value that is somehow incorrect still gets processed and results in a 500 error. Investigate.
    - Look into adding additional randomization. Maybe implement function that runs through several randomized passes before selecting a number.
    - Look into performance improvements. (Maybe see if it's possible to make more efficient IP checking.)
*/

define('noigneano93789hg2nopg', true);
require_once('object/database.information.class.php');
$ip = $_SERVER['REMOTE_ADDR'];
/*if((!rptoolDatabaseDetails::ip_in_range($ip, '8.2.32.0-8.2.35.255') && !rptoolDatabaseDetails::ip_in_range($ip, '8.4.128.0-8.4.131.255') && !rptoolDatabaseDetails::ip_in_range($ip, '8.10.144.0-8.10.151.255') &&
		!rptoolDatabaseDetails::ip_in_range($ip, '63.210.156.0-63.210.159.255') && !rptoolDatabaseDetails::ip_in_range($ip, '64.154.220.0-64.154.223.255') && !rptoolDatabaseDetails::ip_in_range($ip, '216.82.0.0-216.82.63.255')
	)){
		die('Not in range');
	}*/

function isInteger($input){
    return(ctype_digit(strval($input)));
}
if(isset($_POST['min']) && isset($_POST['max'])) { // min and max should both always be set. If not, do nothing.

	$max = $_POST['max'];
	$min = $_POST['min'];
	// SPECULATIVE FIX HERE
	$intMin = (int) filter_var($min, FILTER_SANITIZE_NUMBER_INT);
	$intMax = (int) filter_var($max, FILTER_SANITIZE_NUMBER_INT);
	if(!isInteger($intMin) && !isInteger($intMax))
	{
        // If this is triggered, exit quietly.
        exit;
	}
	// END SPECULATIVE FIX
	$num = 1;
	if(isset($_POST['num'])) {
		if($_POST['num'] < 10) {
			$num = $_POST['num'];
		} else {
			$num = 10;
		}
	}
	if(!isInteger($max) || !isInteger($min) || !isInteger($num)) {
		printf("Not all variables are eligible integers.\n", E_USER_WARNING);
		exit();
	}
	$dice;
	if($num > 1) {
		$dice = array();
		while($num >= 1) { // Loop through the numbers to generate random numbers into the array.
			array_push($dice, random_int($min, $max));
			--$num;
		}
		$total = array_sum($dice);
		
		$out = "Multiple dice roll (D".$max.", minimum roll: ".$min."): " . implode(', ', $dice) . "\nTotal sum: " . $total;
		printf($out);
	} else {
		$dice = "(D".$max.", minimum roll: ".$min.") ". random_int($min, $max);
		printf($dice);
	}
}
