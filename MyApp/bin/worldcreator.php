#!/usr/bin/php
<?php

if ($argc < 3){
	echo "\nCreates the necessary database/table structures for proper application execution\n";
	echo "Database/table structure is defined within templates.databaseTargets and templates.tableTargets\n";
	echo "databaseTargets defines database names, tableTargets defines dbname.tablename mapping\n";
	echo "Usage: ./worldcreator.php <dbuser> <dbpw>\n";
	echo "Example: ./worldcreator.php root rootpw\n";
  echo "World creation requires extensive Grants for dbuser; use of the root user is recommended\n";
	exit();
}

$user = $argv[1];
$pw = $argv[2];
$pdo; $dsn = "mysql:host=localhost;dbname=templates";
try {$pdo = new PDO($dsn, $user, $pw);}
catch(PDOException $e) {echo $e->getMessage();}

//build necessary databases
echo "Started: " . date("m/d/y H:i:s e") . "\n";
echo "\nBegin Database creation\n";
$stmt = $pdo->query(
	"SELECT db_name as name from templates.databaseTargets"
);
if (!$stmt){
	echo "Failed to query templates.databaseTargets; ensure login details and Grant powers";
	exit();
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows){
	echo "Templates.databaseTargets returned no rows\n";
	exit();
}

foreach($rows as $row){
	$stmt = $pdo->query("CREATE DATABASE {$row['name']}");
	//$result = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$stmt) {echo "Make DB: Database {$row['name']} already exists\n";}
	else {echo "Make DB: Database {$row['name']} created\n";}
}
echo "Finished Database creation\n";

echo "\nBegin Table creation\n";
$stmt = $pdo->query(
	"SELECT template_name as name, template_dest as dest, generic from templateTargets"
);
if (!$stmt){
	echo "Failed to query templates.templateTargets for template -> database mapping\n";
	exit();
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
	echo "templates.templateTargets returned no rows\n";
	exit();
}

foreach($rows as $row){
	//generic queries do not have identical namesakes in the destination
	//i.e. templates.messages used to create multiple, unique instances in panel_messages
	if (!$row['generic']){
		$stmt = $pdo->query(
			"CREATE TABLE {$row['dest']}.{$row['name']} " 
			. "LIKE templates.{$row['name']}");
		if (!$stmt){
			echo "Make Table: Table {$row['dest']}.{$row['name']} already exist\n";
			continue;
		}	
		else {echo "Make Table: Table {$row['dest']}.{$row['name']} created\n";}
	}
}
echo "Finished Table creation\n";

echo "\nWorld creation finished\n";
echo "Finished: " . date("m/d/y H:i:s e") . "\n";
?>