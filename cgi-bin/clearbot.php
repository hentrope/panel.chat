#!/usr/bin/php
<?php
	
	$dbuser = "paneld";
	$dbpass = "<pass>";
	$dbdsn_m = "mysql:host=localhost;dbname=panel_messages";
	$pdo;
	try {$pdo = new PDO($dbdsn_m, $dbuser, $dbpass);}
	catch(PDOException $e) {echo $e->getMessage();}
	$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = \"panel_messages\" and table_rows > 0");
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row){
		$pdo->query("DELETE FROM `{$row['table_name']}` WHERE current_timestamp > m_expire");
	}
	
?>
