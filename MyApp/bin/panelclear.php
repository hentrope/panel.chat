#!/usr/bin/php
<?php

if ($argc < 3){
	echo "\nRemoves ALL tables present in panel_messages database\n";
	echo "Usage: ./panelclear.php <dbuser> <dbpw>\n";
	echo "Example: ./panelcreator.php root rootpw\n";
	echo "Ensure that <dbuser> has proper Grants to DROP from panel_messages.*\n";
	echo "WARNING: THIS IS A DESTRUCTIVE AND UNREVERSABLE ACTION; BACK UP AS NECESSARY PRIOR TO EXECUTION\n";
	echo "WARNING: This can take considerable time for large numbers of tables (>1000)\n\n";
	exit();
}

$user = $argv[1];
$pw = $argv[2];

$pdo; $dsn = "mysql:host=localhost;dbname=panel_messages";
try {$pdo = new PDO($dsn, $user, $pw);}
catch(PDOException $e) {echo $e->getMessage();}

$stmt = $pdo->query(
	"SELECT concat('DROP TABLE IF EXISTS ', '`', table_name, '`', ';') as cmd "
	. "FROM information_schema.tables " 
	. "WHERE table_schema = \"panel_messages\""
);
$cmds = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$cmds){
	echo "\nNo tables to delete... exiting\n";
	exit();
}
$count = 0;
echo "Started: " . date("m/d/y H:i:s e") . "\n";
foreach ($cmds as $cmd){
	$stmt = $pdo->query($cmd['cmd']);
	if ($stmt) {
		echo "Executed: {$cmd['cmd']}\n";
		$count++;
	}
}
echo "Deletion finished\n";
echo "Finished: " . date("m/d/y H:i:s e") . "\n";
echo "{$count} tables removed\n"
?>
