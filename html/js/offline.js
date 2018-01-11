/*
 * offline.js is used to allow the client to function without an active server.
 * This was added in order to demonstrate the client's features after the project's completion.
 */

userForm.token = null;
eraseCookie("token");
document.getElementById('logout').removeEventListener('click', sidebar.onLogout);

socket.refresh = function() {};
socket.connect = function() {};
socket.disconnect = function() {};

socket.send = function(obj) {
	switch (obj.type) {
		case "message":
			obj.x = 1000 * Math.random();
			obj.y = 600 * Math.random();
			obj.username = "OFFLINE";
			obj.time = new Date().toISOString().slice(0, 19).replace('T', ' ');
			socket.receive(obj);
			break;
	}
};

socket.receive({
	type: "loginresponse",
	class: 0,
	username: "OFFLINE",
	world_x: 20,
	world_y: 20
});

socket.receive({
	type: "userlist",
	users: [{
		username: "OFFLINE",
		class: 0
	}]
});
