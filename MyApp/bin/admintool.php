#!/usr/bin/php
<?php

if ($argc < 4 ){
	echo "\nGrants/revokes 'world-wide' Administrator status to/from the specified account\n";
	echo "Usage: ./admintool.php [dbuser] [dbpw] [targetuser] <-r>\n";
	echo "Default functionality is GRANT ONLY; optional -r flag (<-r>) enables revocation\n";
	echo "Example (grant): ./admintool.php dbuser dbpw targetuser\n";
	echo "Example (revoke): ./admintool.php dbuser dbpw targetuser -r\n";
	echo "Ensure that dbuser has sufficient Grants to SELECT on panel_users.*\nand UPDATE panel_users.registeredUsers\n\n";
	exit();
}

$user = $argv[1];
$pw = $argv[2];
$target = $argv[3];
$revoke = 0;
if (isset($argv[4]) && strtolower($argv[4]) == "-r")
	{$revoke =  1;}

$pdo; $dsn = "mysql:host=localhost;dbname=panel_users";
try {$pdo = new PDO($dsn, $user, $pw);}
catch(PDOException $e) {echo $e->getMessage();}

//query existing admin status
$stmt = $pdo->query("SELECT u_admin from registeredUsers where u_name = \"{$target}\"");
if (!$stmt){
	echo "User lookup failed; ensure existence of user \"{$target}\" inside panel_users.registeredUsers\n";
	exit();
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);
//user does not exist in registeredUsers table
//if (!$row) 
	//{echo "User lookup failed; ensure existence of user \"{$target}\" inside panel_users.registeredUsers";}
//desired status already exists
if ($row['u_admin'] != $revoke){
	echo "User \"{$target}\" is already of the status you are attempting to assign\n";
	exit();
}
//otherwise update status
$pdo->query(
	"UPDATE registeredUsers SET u_admin = "
. ($revoke ? 0 : 1)
. " WHERE u_name = \"{$target}\""
);

echo "Administrator rights " . ($revoke? "revoked from " : "granted to ") . "user \"{$target}\"\n";


?>