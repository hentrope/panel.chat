<?php
/*
	Panels (v)1.0
*/
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use PDO;

/*
	Chat is used by MyApp/bin/chat-server.php to build an instance
	of the Websocket listener. Essentially acts as a wrapper class for
	SkyNet, handling all the work/communication implicit to Websockets
	while SkyNet handles the heavy lifting
*/
class Chat implements MessageComponentInterface {
	//holds on to ConnectionInterface objects for message sending
	protected $clients;
	//tracks connected users
	protected $skyNet;

	public function __construct() {
		$this->clients = array();
		/*
		Takes optional args worldX, worldY, msgLimit
		Skynet constructor sets default values
		Dimensions represent [-x..+x][-y..+y]
		MsgLimit represents max number of messages panel will display at one time
		*/
		$this->skyNet = new SkyNet(); 
	}//end _construct

	/*
		Called upon initial connection of new client
	*/
	public function onOpen(ConnectionInterface $conn) {
		//stores ConnectionInterface object
		$this->clients[$conn->resourceId] = $conn;
		//initializes SkyNet entry for new rscId
		$this->skyNet->connect($conn->resourceId);
		echo ("New Connection: rscId -> {$conn->resourceId}\n");
	}//end onOpen
	/*
		Called when a connected client pushes a message back to the server
	*/
	public function onMessage(ConnectionInterface $from, $msg) {
		$request = json_decode($msg, true);
		$rscId = $from->resourceId; 
		$killflag = 0;
		$notifications = [];
		switch (strtolower($request['type'])) {
			case "login":
				//login fails upon failed token match
				$notifications = $this->skyNet->login($rscId, $request);
				if (!$notifications){
					$this->clients[$rscId]->send(json_encode(
						["type" => "invalidtoken"]));
				}
				break;

			case "message":
				$notifications = $this->skyNet->message($rscId, $request);
				break;

			case "move":
				$notifications = $this->skyNet->move($rscId, $request);
				break;
			
			case "command":
			case "deletemessage":
				$notifications = $this->skyNet->moderate($rscId, $request);
				break;

			case "refreshpanel":
				$notifications = $this->skyNet->refreshPanel($rscId);
				break;

		}//end switch
		//check for a kill order
		if (isset($notifications['killorder'])){
			$killflag = $notifications['killorder'];
			unset($notifications['killorder']);
		}
		//in any case, if the message returned notifications, push them
		if ($notifications !== true && !empty($notifications)){
			foreach ($notifications as $job){
				$job_e = json_encode($job['msg']);
				foreach ($job['list'] as $rId){
					if(isset($rId)){
						$this->clients[$rId]->send($job_e);
		}}}}
		//if a kill order was sent, gracefully terminate that rscId
		if ($killflag){$this->clients[$killflag]->close();}

	}//end onMessage

	/*
		Called when either client or server initiates disconnect (server calls
		via ->close())
	*/
	public function onClose(ConnectionInterface $conn) {
		$notifications = $this->skyNet->disconnect($conn->resourceId);
		foreach ($notifications as $job){
			$job_e = json_encode($job['msg']);
			foreach($job['list'] as $rId){
				$this->clients[$rId]->send($job_e);}}
		echo ("Connection closed: rscId -> {$conn->resourceId}\n");
	}//end onClose

	/*
		Websocket errors are pushed all the way up the stack trace via this callback
	*/
	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";
		$this->onClose($conn);
	}//end onError
}//end class Chat


/*
-------------------------------------------------------------------
SkyNet
Handles all user tracking and transactional operations as well as
notification generation as needed
Functions are broken into three categories:
1) Operator- 'public' facing operations that initiate action
(i.e. movement, message posting, etc)
2) Generator- private functions that return values but make no 
modifications in their work (i.e. fetching specially formatted
values, etc.). 
Used to generate values for notifications and Moderator functions
3) Moderator- private functions that perform the various transactions
required for approved moderator tasks

Primary data structures:

$connUser[$rscId] => (array of attributes) //primary tracking of connected users
$backmap[$name] => $rscId 		//implementation of mapping connUsers bi-directionally

$visByPanel[$panel][$user] (=> $user) 	//tracks all $user with visibility of $panel
$visByUser[$user][$panel] (=> $panel)		//implementation of mapping visByPanel bi-directionally

$banByUser[$rscId][$panel] => 0/1			//tracking for banned status within user's *visibility*

$userList[$panel][$name] (=> $name)		//tracks all $user within $panel
-------------------------------------------------------------------
*/

class SkyNet{
/*
MOD_OPS defines a map of possible slash commands that can be accepted
for their accoording actions. The commands map to an integer that
represents the need for admin powers to execute (1 => yes, 0 => no)
Ergo operations that map to a 1 require admin status, etc.
*/
	const MOD_OPS = array(
		"ban" => 0,
		"mute" => 0,
		"unmute" => 0,
		"mod" => 1,
		"unmod" => 1,
		"unban" => 0,
		"kill" => 1,
		"unkill" => 1,
		"deletemessage" => 0
	);

	protected $connUsers;
	protected $visByPanel;
	protected $visByUser;
	protected $banByUser;
	protected $userList;

	protected $world_x;
	protected $world_y;
	protected $msg_limit;
	protected $msg_exp_time;

	private $dbuser;
	private $dbpass;
	private $dbdsn_u;
	private $dbdsn_m;

	private $backmap; //username -> rscId for connUsers

	public function __construct(
		$world_x = 25, 
		$world_y = 25, 
		$msg_limit = 50, 
		$msg_exp_time = 30) {
			$this->dbuser = "paneld";
			$this->dbpass = "<pass>";
			$this->dbdsn_m = "mysql:host=localhost;dbname=panel_messages";
			$this->dbdsn_u = "mysql:host=localhost;dbname=panel_users";
			$this->world_x = $world_x;
			$this->world_y = $world_y;
			$this->msg_limit = $msg_limit;
			$this->msg_exp_time = $msg_exp_time; //minutes
			$this->connUsers =
			$this->backmap =  
			$this->visByUser = 
			$this->visByPanel = 
			$this->banByUser =
			$this->userList = array();
			for ($i = -$world_x; $i<=$world_x; $i++){
				for ($j = -$world_y; $j<=$world_y; $j++){
					$this->visByPanel["{$i}/{$j}"] = array();
					$this->userList["{$i}/{$j}"] = array();
				}//end inner for
			}//end outer for
	}//end __construct

/*
--------------------------------------
Operator Functions are public facing operations accessed by the Chat class 
to initiate SkyNet actions
--------------------------------------
*/
	/*
	Handles request from Chat::OnOpen (initialized connection)
	*/
	public function connect($rscId){
		$connUsers[$rscId] = null;
	}//end connect

	/*
	Handles request from Chat::OnClose (terminated connection)
	*/
	public function disconnect($rscId){
		//Build ULE for delivery
		if(!isset($rscId)){return;}
		$currentLoc = "{$this->connUsers[$rscId]['locX']}/{$this->connUsers[$rscId]['locY']}";
		$notifications = [$this->g_response(
			$this->generateULrscids($currentLoc),
			$this->generateULE(
				$this->connUsers[$rscId]['name'], 
				$this->generateClass($rscId), 
				false
			))];
		//scrub user from visibility system
		foreach($this->visByUser[$rscId] as $panel){
			unset($this->visByPanel[$panel][$rscId]);
		}
		unset($this->visByUser[$rscId]);
		unset($this->banByUser[$rscId]);
		unset($this->backmap[$this->connUsers[$rscId]['name']]);
		unset($this->userList[$currentLoc][$this->connUsers[$rscId]['name']]);
		unset($this->connUsers[$rscId]);
		return $notifications;
	}//end disconnect
	
	/*
	Handles a LOGIN object from the client
	*/
	public function login($rscId, $request){
		//match tokens to gather username and userid
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$stmt = $pdo->query(
		"SELECT a.u_name, r.u_id, r.u_admin, r.u_killed FROM "
		. "assignedTokens a, registeredUsers r WHERE "
		. "a.u_authToken = \"{$request['token']}\" AND "
		. "r.u_name = a.u_name");
		//return false if the query fails
		if (!$stmt) {
			unset($this->connUsers[$rscId]);
			return false;
		}
		$rslt = $stmt->fetch(PDO::FETCH_ASSOC);
		if(!$rslt){
			unset($this->connUsers[$rscId]);
			return false;
		}
		//disconnect if killed account
		if ($rslt['u_killed'] == 1){
			unset($this->connUsers[$rscId]);
			return false;
		}
		//set entry in $connUsers
		$this->connUsers[$rscId] = array(
			'name' => $rslt['u_name'],									
			'id' => $rslt['u_id'],
			'admin' => (int)$rslt['u_admin'],
			'locX' => 0,
			'locY' => 0,
			'locZ' => 0,
			'mod' => 0,
			'muted' => ($rslt['u_admin'] ? 0 : 1),
			'banned' => 0,
			'lastMsgTime' => null
		);
		//set the backmap
		$this->backmap[$rslt['u_name']] = $rscId;
		//set both $vis panels
		$this->visByPanel['0/0'][$rscId] = $rscId;
		$this->visByUser[$rscId]['0/0'] = "0/0";
		//set entry into $userList[table] 
		$this->userList['0/0'][$rslt['u_name']] = $rslt['u_name'];
		//initialize rscId entry for banByUser
		$this->banByUser[$rscId]["0/0"] = $this->connUsers[$rscId]['banned'];

		$notifications = array();
		//login response, userlist and 0/0 panel to newly connected user
		array_push($notifications, $this->g_response(
			[$rscId], $this->generateLR($rscId)
		));
		array_push($notifications, $this->g_response(
			[$rscId], $this->generatePD($rscId, $this->g_pr("0/0", 0))
		));
		array_push($notifications, $this->g_response(
			[$rscId], $this->generateUL("0/0")
		));
		//userlistedit for everyone else in that room
		$list = $this->generateULrscids("0/0");
		unset($list[$rscId]);
		array_push($notifications, $this->g_response(
			$list, $this->generateULE(
				$this->connUsers[$rscId]['name'],
				$this->generateClass($rscId)
			)));
		return $notifications;
	}//end login
	
	/*
	Handles a MESSAGE object from the client
	*/
	public function message($rscId, $request){
		//pull user's details for ease-of-use
		$user = $this->connUsers[$rscId];
		$panel = "{$user['locX']}/{$user['locY']}";
		//user is muted or banned
		if (
			$user['muted'] || 
			$user['banned'] ||
			(!$user['admin'] && $panel == "0/0")
		) {return false;}
	 	//pack request for return
	 	$request['username'] = $user['name'];
		$request['x'] = rand(0,1000);
		$request['y'] = rand(0,600);
		//convert mask binary -> octal for db
		$mask = bindec($request['b_u_i']);
		//post message to DB
		$pdo;
		try {$pdo = new PDO($this->dbdsn_m, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$pdo->query(
			"INSERT INTO `{$panel}` "
			. "(u_id, u_name, m_text, m_mask, m_color, m_expire, m_xoffset, m_yoffset) "
			. "VALUES ({$user['id']}, \"{$user['name']}\", \"{$request['text']}\", "
			. "{$mask}, \"{$request['color']}\", " 
			. "(current_timestamp + INTERVAL {$this->msg_exp_time} MINUTE), "
			. "{$request['x']}, {$request['y']})");
		//set m_id
		$request['id'] = $pdo->lastInsertId();
		//set timestamp
		$row = ($stmt = $pdo->query(
			"SELECT m_time FROM `{$user['locX']}/{$user['locY']}` "
			. "WHERE m_id = {$request['id']}"))->fetch(PDO::FETCH_ASSOC);
		$this->connUsers['lastMsgTime'] = $request['time'] = $row['m_time'];
		unset($pdo);
		//return updated message request as packed notification
		return [$this->g_response($this->visByPanel[$panel], $request)];
	}//end message

	/*
	Handles a MOVE object from the client
	*/
	public function move($rscId, $request){
		//double check bounds
		if (
			abs($request['newPX']) > $this->world_x ||
			abs($request['newPY']) > $this->world_y ||
			!isset($this->connUsers[$rscId])
		) {return false;}

		//Calculate new visibility matrix 
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$x = $request['newPX']; //new x
		$y = $request['newPY']; //new y
		$z = $request['newZL']; //new z
		$newVis = array();
		for (
			$i = (abs($x-$z) <= $this->world_x ? ($x-$z) : -$this->world_x); 
			$i <= ($x + $z) && $i <= $this->world_x; 
			$i++) {
				for (
					$j = (abs($y-$z) <= $this->world_y ? ($y-$z) : -$this->world_y); 
					$j <= ($y + $z) && $j <= $this->world_y; 
					$j++) {
						$newVis["{$i}/{$j}"] = "{$i}/{$j}";
						$stmt = $pdo->query(
							"SELECT u_banned from moderation " 
						. "where (u_id, p_id) = ({$this->connUsers[$rscId]['id']}, \"{$i}/{$j}\")"
						);
						$this->banByUser[$rscId]["{$i}/{$j}"] = (
							($stmt && ($stmt->fetch(PDO::FETCH_ASSOC))['u_banned'] == 1) ? 1 : 0);
						// if ($stmt && ($stmt->fetch(PDO::FETCH_ASSOC))['u_banned'] == 1)
						// 	{$this->banByUser[$rscId]["{$i}/{$j}"] = 1;}
						// else 
						// 	{$this->banByUser[$rscId]["{$i}/{$j}"] = 0;}
				}//end inner for
		}//end outer for

		//remove all panels no longer in user's vis/ban matrices
		foreach(array_diff($this->visByUser[$rscId], $newVis) as $panel) {
			unset($this->visByPanel[$panel][$rscId]);
			unset($this->visByUser[$rscId][$panel]);
			unset($this->banByUser[$rscId][$panel]);
		} 
		//add all panels new to user's vis matrix
		foreach(array_diff($newVis, $this->visByUser[$rscId]) as $panel) {
			$this->visByPanel[$panel][$rscId] = $rscId;
			$this->visByUser[$rscId][$panel] = $panel;
		}
		//stash the old coords
		$ox = $this->connUsers[$rscId]['locX'];
		$oy = $this->connUsers[$rscId]['locY'];

		//update userlist (remove old add new)
		unset($this->userList["{$ox}/{$oy}"]["{$this->connUsers[$rscId]['name']}"]);
		$this->userList["{$x}/{$y}"][$this->connUsers[$rscId]['name']] 
			= $this->connUsers[$rscId]['name'];
		//Update connUser entry
		$this->connUsers[$rscId]['locX'] = $x;
		$this->connUsers[$rscId]['locY'] = $y;
		$this->connUsers[$rscId]['locZ'] = $z;
		$stmt = $pdo->query(
			"SELECT u_mod, u_muted, u_banned FROM moderation " 
		. "WHERE (u_id, p_id) = ({$this->connUsers[$rscId]['id']}, \"{$x}/{$y}\")"
		);
		if ($stmt){
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$this->connUsers[$rscId]['mod'] = $row['u_mod'];
			$this->connUsers[$rscId]['muted'] = $row['u_muted'];
			$this->connUsers[$rscId]['banned'] = $row['u_banned'];
		}
		else {
			$this->connUsers[$rscId]['mod'] = 0;
			$this->connUsers[$rscId]['muted'] = 0;
			$this->connUsers[$rscId]['banned'] = 0;
		}
		//reset time since last message
		$this->connUsers[$rscId]['lastMsgTime'] = null;
		
		$notifications = array();
		//always push panel delivery
		array_push($notifications, $this->g_response(
			[$rscId], $this->generatePD($rscId, $request['newPanels'])
		));
		//push ULEs and new UL if the request involved a move
		if ($x != $ox || $y != $oy) {
			//userlist of new room
			array_push($notifications, $this->g_response(
				$this->generateULrscids("{$x}/{$y}"),
				$this->generateUL("{$x}/{$y}")
			));
			//ULE leave to old room
			array_push($notifications, $this->g_response(
				$this->generateULrscids("{$ox}/{$oy}"),
				$this->generateULE(
					$this->connUsers[$rscId]['name'],
					$this->generateClass($rscId),
					false
			)));
			//ULE join to new room
			array_push($notifications, $this->g_response(
				$this->generateULrscids("{$x}/{$y}"),
				$this->generateULE(
					$this->connUsers[$rscId]['name'],
					$this->generateClass($rscId)
			)));
		}//end if move request
		return $notifications;
	}//end move
	
	/*
	Handles a REFRESHPANEL object from client
	*/
	public function refreshPanel($rscId){
		return [
			$this->g_response(
				$rscId,
				$this->generatePD($rscId, 
					$this->g_pr(
						"{$this->connUsers[$rscId]['locX']}/{$this->connUsers[$rscId]['locY']}", 
						0
		)))];
	}//end refreshPanel

	/*
	Handles a COMMAND object from the client
	*/
	public function moderate($rscId, $request){
		$cmd; $args;
		$target; 
		//(CALLING user's panelID)
		$pid = "{$this->connUsers[$rscId]['locX']}/{$this->connUsers[$rscId]['locY']}";
		$self = $this->mod_fetchTarget($pid, $this->connUsers[$rscId]['name']); 
		if(!$self['admin'] && !$self['mod']) {
			return false;
		}
		if (strtolower($request['type']) != "deletemessage"){
			$args = explode(" ", $request['text']);
			$cmd = strtolower(trim($args['0'], " /"));
			//invalid command
			if (!isset(self::MOD_OPS[$cmd])) {
				return false;
			}
			$target = $this->mod_fetchTarget($pid, trim($args[1]));
			
			if ($target['active'] = isset($this->backmap[$target['name']])){
				$target['rscId'] = $this->backmap[$target['name']];
				$target['loc'] = "{$this->connUsers[$rscId]['locX']}/{$this->connUsers[$rscId]['locY']}";
				$target['local'] = ($target['loc'] == $pid ? true : false);
			}//end if target active
			if (
				!$target ||
				$target['admin'] ||
				$self['name'] == $target['name'] ||
				(!$self['admin'] && (self::MOD_OPS[$cmd] == 1 || $target['mod']))
			){
				return false;
			}
		}//end if not delete message
		else {
			$cmd = "deletemessage";
			$user = $this->mod_fetchUserFromMsg($pid, $request['m_id']);
			$target = $this->mod_fetchTarget($pid, $user);
			if (
				($target['admin'] && ($target['name'] != $self['name'])) ||
				$target['mod'] && !$self['admin']
			){
				return false;
			}
		}//end else (if messagedelete)
		
		//(if code makes it here, self is either a mod or admin)
		//fetchTarget returns false if it fails to match registeredUsers entry
		//if the target is admin, or if self is not admin WHILE either requesting
		//admin operation or attempting to target a mod (as another mod), return false
		//Admins can't be touched by anyone internally, mods can't be touched by other mods
		//denotes if target is actively connected
		//$target['active'] = isset($this->backmap[$target['name']]);
		//Command has been validated by this point; execute it
		switch ($cmd){
			case "ban":
				if (isset($args[2]) && !is_numeric(trim($args[2]))) {return false;} //non-numeric time argument
				$target = $this->mod_banTarget($target, trim($args[2]));
				//only push notifications if target's actively connected
				if ($target['active']) { //if target's active, send then Banned object
					$notifications = array();
					array_push($notifications, $this->g_response(
						[$target['rscId']],
						$this->generateBanned($target['panel'])
					));
					array_push($notifications, $this->g_response(
						$this->generateULrscids($target['panel']),
						$this->generateULE($target['name'], 3)
					));
					return $notifications;
				}//end if target active
				else return false;

			case "unban":
				$target = $this->mod_banTarget($target, 0, false);
				if ($target['active']){
					$notifications = array();
					array_push($notifications, $this->g_response(
						[$target[$rscId]], 
						$this->generatePD(
							$target['rscId'], 
							$this->g_pr($target['panel'], 0))
					));
					array_push($notifications, $this->g_response(
						$this->generateULrscids($target['panel']),
						$this->generateULE(
							$target['name'],
							($target['muted'] ? 4 : 0)
						)));
					return $notifications;
				}//end if target active
				else return false;

			case "mute":
				$target = $this->mod_muteTarget($target);
				if ($target['active']){
					return $this->g_response(
						$this->generateULrscids($target['panel']),
						$this->generateULE($target['name'], 4)
					);
				}//end if target active
				else return false;

			case "unmute":
				$target = $this->mod_muteTarget($target, false);
				if ($target['active']){
					return $this->g_response(
						$this->generateULrscids($target['panel']), 
						$this->generateULE($target['name'], ($target['banned'] ? 3 : 0))
					);
				}//end if target active
				else return false;

			case "mod":
				//prevent shadow rules
				if ($target['admin']) {return false;}	
				$target = $this->mod_elevateTarget($target);
				if ($target['active']){
					$notifications = array();
					array_push($notifications, $this->g_response(
						$this->generateULrscids($target['panel']),
						$this->generateULE($target['name'], 2)
					));
					array_push($notifications, $this->g_response(
						[$target['rscId']], [
							"type" => "notify",
							"text" => "You have been granted Moderator powers within panel {$target['panel']}"
						]));
					return $notifications;
				}//end if target active
				else return false;

			case "unmod":
				$target = $this->mod_elevateTarget($target, false);
				if ($target['active']){
					$notifications = array();
					array_push($notifications, $this->g_response(
						$this->generateULrscids($target['panel']), 
						$this->generateULE($target['name'])
					));
					array_push($notifications, $this->g_response(
						[$target['rscId']], [
							"type" => "notify",
							"text" => "Your Moderator powers within panel {$target['panel']} have been revoked"
						]));
					return $notifications;
				}
				else return false;

			case "kill":
				$target = $this->mod_killTarget($target);
				$notifications = array(); 
				if ($target['active']){
					array_push($notifications, $this->g_response([$target['rscId']], [
							"type" => "notify",
							"text" => "Your account has been permanently banned"
						]));
					array_push(
						$notifications, 
						$this->g_response(
							$this->generateULrscids($target['panel']), 
							$this->generateULE($target['name'], 5, false)
						)
					);
					$notifications["killorder"] = $target['rscId'];
					return $notifications;
				}//end if target active
				else return false;
			
			case "unkill":
				$target = $this->mod_killTarget($target, false);
				return true;

			case "deletemessage":
				$panel = "{$request['x']}/{$request['y']}";
				$m_id = $target['msg'] = $request['m_id'];
				//returns true if a message was deleted
				if ($this->mod_deleteMsg($pid, $m_id)) {				
					return [$this->g_response($this->generateWorldrscids(),$request)];
				}
				else return false;
		}//end switch

	}//end moderate


	/*
	--------------------------------------
	Generator-level functions do not change values but generate formatted 
	'notification' objects for the client handler to send back
	--------------------------------------
	
	/*
	Creates an internally-used data structure that communicates what
	messages need to be sent to whom; $list defines (rscId) recipients 
	of $msg
	*/
	private function g_response($list, $msg){
		return [
			"list" => $list,
			"msg" => $msg
		];
	}//end g_response

/*
	Generates a PARTIALREQUEST for the instances it's needed
	but not generated by a client's request (aka login)
*/
	private function g_pr($panel, $last){
		$array = [];
		$otherarray = array(
			"type" => "partialrequest",
			"panel" => $panel,
			"lastID" => $last
		);
		array_push($array, $otherarray);
		return $array;
	}//end g_pr

	private function generateWorldrscids(){
		$list = [];
		foreach($this->backmap as $rscId){
			array_push($list, $rscId);
		}
		return $list;
	}//end generateWorldrscids

	private function generateULrscids($panel){
		$rtn = [];
		foreach($this->userList[$panel] as $name){
			array_push($rtn, $this->backmap[$name]);
		}
		return $rtn;
	}//end generateULrscids


	/*
	Generates a PANELDELIVERY object; $panels is a list of PARTIALREQUESTs
	*/
	private function generatePD($rscId, $panels){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_m, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$paneldelivery = array(
			'type' => "paneldelivery",
			'class' => $this->generateClass($rscId),
			'panels' => array()
		);
		//for each panel in the partialpanel request, pull all of
		//the messages with m_id > $request->lastID
		foreach($panels as $request){
			if( !isset($this->visByUser[$rscId][$request['panel']])) {continue;}
			$coord = explode("/", $request['panel']);
			$panel = array(
				'type' => ($this->banByUser[$rscId][$request['panel']] == 0 ? "panel" : "banned"),
				'x' => $coord['0'],
				'y' => $coord['1']
			);
			//only build a panel's messages if the user isn't banned
			if (!$this->banByUser[$rscId][$request['panel']]) {		
				$panel['messages'] = array();
				//get requested messages in bulk
				
				$stmt = $pdo->query(
					"SELECT * FROM (SELECT * FROM `{$request['panel']}` "
					. "WHERE m_id > {$request['lastID']} " 
					. "ORDER BY `m_id` DESC LIMIT {$this->msg_limit}) "
					. "as t ORDER BY `m_id`"
				);
				if ($stmt) {
					$msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
					//for each message returned, create an object and 
					//push it into a zero-indexed array ($messages)
					foreach($msgs as $msg){
						$message;
						$message['type'] = "message";
						$message['id'] = $msg['m_id'];
						$message['username'] = $msg['u_name'];
						$message['color'] = $msg['m_color'];
						$message['b_u_i'] = decbin($msg['m_mask']);
						$message['text'] = $msg['m_text'];
						$message['x'] = $msg['m_xoffset'];
						$message['y'] = $msg['m_yoffset'];
						$message['time'] = $msg['m_time'];
						array_push($panel['messages'], $message);
					} //end foreach msg
				} //end if msg exists
			}//end if not banned
			array_push($paneldelivery['panels'], $panel);
		}//end foreach panel
		return $paneldelivery;
	}//end generatePD
/*
	Generates a banned object
*/
	private function generateBanned($panel){
		$exp = explode("/", $panel);
		return [
			"type" => "banned",
			"x" => $exp[0],
			"y" => $exp[1]
		];
	}//end generateBanned

/*
	Generates a Userlist object
*/
	private function generateUL($panel){
		$rtn = [
			"type" => "userlist",
			"users" => []
		];
		foreach($this->userList[$panel] as $username){
			array_push($rtn['users'], [
				"type" => "user",
				"username" => $username,
				"class" => $this->generateClass($this->backmap[$username])
			]);
		}
		return $rtn;
	}//end generateUL

	/*
	Generates a Userlistedit object; $stay indcates value of 
	'action' (join/leave)
	*/
	private function generateULE($name, $newclass, $stay = true){
		return array(
			"type" => "userlistedit",
			"action" => $stay,
			"user" => array(
				"type" => "user",
				"username" => $name,
				"class" => $newclass
			)
		);
	}//end generateULE

	/*
	Generates a loginresponse object
	*/
	private function generateLR($rscId){
		return [
			"type" => "loginresponse",
			"username" => $this->connUsers[$rscId]['name'],
			"world_x" => $this->world_x,
			"world_y" => $this->world_y,
			"msg_limit" => $this->msg_limit
	 	];
		}//end generateLR
	/*
	Generates a class in the format that the client expects:
	0 = normal, 1 = admin, 2 = mod, 3 = banned, 4 = muted, 5 = killed
	*/
	private function generateClass($rscId){
		if ($this->connUsers[$rscId]['admin'] == 1) return 1;
		else if ($this->connUsers[$rscId]['mod'] == 1) return 2;
		else if ($this->connUsers[$rscId]['banned'] == 1) return 3;
		else if ($this->connUsers[$rscId]['muted'] == 1) return 4;
		else return 0;
	}//end generateClass
	/*
		Generates a deletemessage object
	*/
/*	private function generateDM($panel, $mid){
		$exp = explode("/", $panel);
		return array(
			"type" => "deletemessage",
			"x" => $exp[0],
			"y" => $exp[1],
			"m_id" => $mid
		);
	}//end generateDM
*/

	// ----------------------------------------------------------------
	/*
	Moderator-level functions
	Used to perform actual DB/data structure transactions involved 
	with moderation actions
	*/
	// ----------------------------------------------------------------
	
	private function mod_fetchUserFromMsg($panel, $mid){
		try {$pdo = new PDO($this->dbdsn_m, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$stmt = $pdo->query("SELECT u_name FROM `{$panel}` WHERE m_id = {$mid}");
		
		if (!$stmt){return false;}
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row){return false;}
		return $row['u_name'];
	}//end mod_fetchUser
	/*
	Fetch (name, id, admin, mod, panel) status of target $uname in given $panel
	Also ensures $uname exists as a registeredUser (fails otherwise) and an 
	entry matching (id, panel)	exists in moderation; if not, it's inserted. 
	*/
	private function mod_fetchTarget($panel, $uname){
		$target; $pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$stmt = $pdo->query(
			"SELECT u_name as name, u_id as id, u_admin as admin " 
		. "FROM registeredUsers WHERE u_name = \"{$uname}\"");
		//return false if the user does not exist
		if (!$stmt) return false;
		//else start to build target
		$target = $stmt->fetch(PDO::FETCH_ASSOC);
		if($target == false){return false;}
		//check if (target, panel) has entry in moderation
		$pdo->query("INSERT IGNORE INTO moderation (u_id, p_id) VALUES ({$target['id']}, \"{$panel}\")");
		$query = 
			"SELECT u_mod, u_muted, u_banned FROM moderation "
			. "WHERE (u_id, p_id) = ({$target['id']}, \"{$panel}\")";
		$stmt = $pdo->query($query);
		//claim the values it provides
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$target['mod'] = $row['u_mod'];
		$target['muted'] = $row['u_muted'];
		$target['banned'] = $row['u_banned'];
		$target['panel'] = $panel;
		return $target;
	}//end mod_fetchTarget


	private function mod_banTarget($target, $time, $ban = true){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		
		$query = (
			$ban 
			? 
			"UPDATE moderation SET u_banned = 1, u_mod = 0, " 
		. "u_bannedUntil = (current_timestamp + INTERVAL {$time} HOUR) " 
		. "WHERE (u_id, p_id) = ({$target['id']}, \"{$target['panel']}\")"
			:
			"UPDATE moderation SET u_banned = 0, u_bannedUntil = NULL " 
		. "WHERE (u_id, p_id) = ({$target['id']}, \"{$target['panel']}\")"
		);
		$pdo->query($query);
		if ($ban){
			$target['banned'] = 1;
			$target['mod'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['banned'] = 1;
				$this->connUsers[$target['rscId']]['mod'] = 0;
			}
		}
		else {
			$target['banned'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['banned'] = 0;
			}
		}
		return $target;
	}//end mod_banTarget

	private function mod_muteTarget($target, $mute = true){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$query = (
			$mute ?
			"UPDATE moderation SET u_muted = 1, u_mod = 0 " :
			"UPDATE moderation set u_muted = 0 "
		);
		$query .= "WHERE (u_id, p_id) = ({$target['id']}, \"{$target['panel']}\")";
		$pdo->query($query);
		if ($mute){
			$target['muted'] = 1;
			$target['mod'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['muted'] = 1;
				$this->connUsers[$target['rscId']]['mod'] = 0;
			}
		}
		else{
			$target['muted'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['muted'] = 0;
			}
		}
		return $target;
	}//end mod_muteTarget

	private function mod_elevateTarget($target, $mod = true){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$pdo->query(
			"INSERT IGNORE INTO moderation (u_id, p_id) "
			. "VALUES ({$target['id']}, \"{$target['panel']}\")");
		
		$query = (
			$mod ? 
			"UPDATE moderation SET u_mod = 1, u_muted = 0, u_banned = 0 " :
			"UPDATE moderation SET u_mod = 0 "
		);
		$query .= "WHERE (u_id, p_id) = ({$target['id']}, \"{$target['panel']}\")";
		$pdo->query($query);
		if ($mod){
			$target['mod'] = 1;
			$target['muted'] = $target['banned'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['mod'] = 1;
				$this->connUsers[$target['rscId']]['muted'] = 
				$this->connUsers[$target['rscId']]['banned'] = 0;
			}
		}
		else{
			$target['mod'] = 0;
			if ($target['active'] && $target['local']){
				$this->connUsers[$target['rscId']]['mod'] = 0;
			}
		}
		return $target;
	}//end mod_elevateTarget

	private function mod_killTarget($target, $kill = true){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_u, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$query = 
			"UPDATE registeredUsers SET u_killed = "
		. ($kill ? 1 : 0)
		. " WHERE (u_name, u_id) = (\"{$target['name']}\", {$target['id']})";
		$pdo->query($query);
		$target['killed'] = ($kill ? 1 : 0);
		return $target;
	}//mod_killTarget

	private function mod_deleteMsg($panel, $mid){
		$pdo;
		try {$pdo = new PDO($this->dbdsn_m, $this->dbuser, $this->dbpass);}
		catch(PDOException $e) {echo $e->getMessage();}
		$stmt = $pdo->query("DELETE FROM `{$panel}` WHERE m_id = {$mid}");

		return true;
		
	}//end mod_deleteMsg



}//end class SkyNet
