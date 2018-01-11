<?php

error_reporting(E_ALL);															// Uncomment for error reporting.
ini_set("display_errors", 1);

function getPDO() {
	$dsn = "mysql:host=localhost;dbname=panel_users";
	$dbuser = "paneld"; $dbpw = "<pass>";
	$pdo = new PDO($dsn, $dbuser, $dbpw);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);				// Uncomment for error reporting.
	return $pdo;
}

function validateName($str) {
	$length = strlen($str);
	
	if ($length < 1)
		return "username_too_short";

	if ($length > 20)
		return "username_too_long";

	if (preg_match('/[^A-Za-z0-9_]/', $str))
		return "illegal_character";

	return false;
}

function getHash($username, $userpass) {
	return base64_encode(hash('sha512', strtoupper($username) . $userpass, true));
}

function validateToken($str) {
	if (preg_match('/[^a-zA-Z0-9+\/]/', $str))
		return "invalid_token";
	
	return false;
}

function checkUser($pdo, $username, $userpass) {
	// Write a query to find the user matching the given name
	$query = "SELECT u_name FROM `registeredUsers`"
		. "WHERE UPPER(u_name)='" . strtoupper($username) . "'";
	
	// If a password was provided, add it to the query
	if ($userpass !== null)
		$query .= " AND u_pHash='" . getHash($username, $userpass) . "'";

	// Get the u_name from the query. If no u_name was returned, then the user details are incorrect.
	return $pdo->query($query)->fetchColumn();
}

function checkKilled($pdo, $username) {
	// Write a query to find the user matching the given name
	$query = "SELECT u_killed FROM `registeredUsers`"
		. "WHERE UPPER(u_name)='" . strtoupper($username) . "'";

	// Return whether their u_killed field is 1
	return $pdo->query($query)->fetchColumn() == 1;
}

function register($username, $userpass) {
	// Ensure that the username contains only valid characters/is proper length
	if ($msg = validateName($username))
		return array(
			"code" => 400,
			"msg" => $msg
		);
	
	// Create the PDO instance
	$pdo = getPDO();
	
	// Check whether a user with that name already exists
	if (checkUser($pdo, $username, null))
		return array(
			"code" => 400,
			"msg" => "user_exists"
		);

	// If all checks pass, create the user
	$query = "INSERT INTO `registeredUsers`"
		. " (u_name,u_pHash) VALUES"
		. " ('{$username}','" . getHash($username, $userpass) . "')";
	if (!$pdo->prepare($query)->execute())
		return array(
			"code" => 500,
			"msg" => "cannot_create_user"
		);

	return array(
		"code" => 200,
		"msg" => "success"
	);
}

function login($username, $userpass, $remember) {
	// Ensure that the username contains only valid characters/is proper length
	if ($msg = validateName($username))
		return array(
			"code" => 400,
			"msg" => $msg
		);
	
	// Create the PDO instance
	$pdo = getPDO();

	// Run a query to see if there are any users with matching credentials
	$username = checkUser($pdo, $username, $userpass);
	
	// If no u_name was returned, then the user details are incorrect.
	if (!$username)
		return array(
			"code" => 400,
			"msg" => "invalid_credentials"
		);
	
	// Don't log the user in if they are banned
	if (checkKilled($pdo, $username))
		return array(
			"code" => 400,
			"msg" => "user_killed"
		);

	// Generate a random base64 string to use as a token
	// Token length as recommended in: https://security.stackexchange.com/questions/94630/
	$token = base64_encode(random_bytes(16));
	$token = preg_replace('/=+/i', '', $token);
	
	// Set the expiration time (30 days if remember, 5 minutes otherwise)
	$expiration = time() + ($remember ? 86400 * 30 : 300);

	// Run a query to store the resulting token back in the database
	$query = "INSERT INTO `assignedTokens` (u_authToken, u_name)"
		. " VALUES ('{$token}','{$username}')";
	if (!$pdo->prepare($query)->execute())
		return array(
			"code" => 500,
			"msg" => "cannot_create_token"
		);

	// If the user has checked the "remember me" box, save their token in
	//if ($remember)
	//	setrawcookie("token", $token, $expiration, "/", "panel.chat", false); // Once we move back to HTTPS, set the last parameter to TRUE

	// Send the token directly so that it may be used even if cookies are disabled
	return array(
		"code" => 200,
		"msg" => $token,
		"token" => $remember ? $token : null,
		"expiration" => $expiration
	);
}

function logout($token) {
	// Ensure that the token contains only valid characters
	if ($msg = validateToken($token))
		return array(
			"code" => 400,
			"msg" => $msg
		);

	// Create the PDO instance
	$pdo = getPDO();

	// Run a query to store the resulting token back in the database
	$query = "DELETE FROM `assignedTokens`"
		. " WHERE u_authToken='{$token}'";
	if (!$pdo->prepare($query)->execute())
		return array(
			"code" => 500,
			"msg" => "cannot_delete_token"
		);
	
	return array(
		"code" => 200,
		"msg" => "success"
	);
}

switch ($_POST["action"]) {
	case "register":
		$response = register($_POST["username"], $_POST["userpass"]);
		break;
	
	case "login":
		$response = login($_POST["username"], $_POST["userpass"], $_POST["remember"] == "true");
		break;
	
	case "logout":
		$response = logout($_POST["token"]);
		break;
	
	default:
		$response = array(
			"code" => 400,
			"msg" => "invalid_action"
		);
		break;
}

http_response_code($response["code"]);
echo $response["msg"];
if (isset($response["token"]) && $response["token"] != null)
	setrawcookie("token", $response["token"], $response["expiration"], "/", "panel.chat", false); // Once we move back to HTTPS, set the last parameter to TRUE
