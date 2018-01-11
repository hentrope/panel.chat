#!/usr/bin/php
<?php

if ($argc < 5){
	echo "\nCreates tables in panel_messages from (-x,-y) to (x,y)\n";
	echo "Usage: ./panelcreator.php <dbuser> <dbpw> <x_dim> <y_dim> \n";
	echo "Example: ./panelcreator.php root rootpw 10 10\n";
  echo "Message tables are created in likeness of templates.messages; existing tables are left untouched\n";
	echo "Ensure that <dbuser> has proper Grants to CREATE in panel_messages.*\n";
	echo "WARNING: This can take considerable time for large numbers of tables (>1000)\n\n";
	exit();
}

$user = $argv[1];
$pw = $argv[2];
$x = $argv[3];
$y = $argv[4];

$pdo; $dsn = "mysql:host=localhost;dbname=panel_messages";
try {$pdo = new PDO($dsn, $user, $pw);}
catch(PDOException $e) {echo $e->getMessage();}
$count = 0;
echo "Started: " . date("m/d/y H:i:s e") . "\n";

for ($i = -$x; $i<=$x; $i++){
  for ($j = -$y; $j<=$y; $j++){
    $stmt = $pdo->query("CREATE TABLE `{$i}/{$j}` LIKE templates.messages");
    if (!$stmt) echo "Table {$i}/{$j} already exists; skipping...\n";
    else {
    	echo "Creating {$i}/{$j}...\n";
    	$count++;
    }
  }//end inner for
}//end outer for

echo "Creation finished\n";
echo "Finished: " . date("m/d/y H:i:s e") . "\n";
echo "{$count} tables created\n";
?>
