var idleTimer;
var socket = {
	conn: null,
	userclass: 0,

	receive: function(obj) {
		console.log("Received:", obj);
		clearTimeout(idleTimer);
		switch(obj.type) {

		case "message":
			var px = obj.panelX;
			var py = obj.panelY;

			if ( Math.abs(view.x - px) < 8
					&& Math.abs(view.y - py) < 8) {
				panels.getPanel(px,py,true).add(obj);
			}
			viewport.redraw();
			break;

		case "paneldelivery":
			socket.userclass = obj.class;
			obj.panels.forEach(function(newPanel){
				var px = newPanel.x;
				var py = newPanel.y;
				if ( Math.abs(view.x - px) < 8
						&& Math.abs(view.y - py) < 8) {
					var panel = panels.getPanel(px,py,true); //if we don't have the panel in cache yet, create it
					if (newPanel.type == "banned") {
						panel.add({
							type: "message",
							b_i_u: 4,
							text: "You have been banned from this panel",
							color: "#000000",
							id: -1,
							username: "System",
							x: 350,
							y: 300,
							time: "null"
						});
					} else {
						//add all of the new messages to the cached panel
						var length = newPanel.messages.length;
						for (var i =0; i < length; i++)
							panel.add(newPanel.messages[i]);
					}
				}
			});
			viewport.redraw();
			break;
		
		case "userlist":
			userlist.set(obj.users);
			break;

		case "userlistedit":
			userlist.remove(obj.user);
			if(obj.action)
			  userlist.add(obj.user);
			break;

		case "loginresponse":
			socket.userclass = obj.class;
			document.getElementById("user-display").innerHTML = obj.username;
			view.xmax = obj.world_x;
			view.ymax = obj.world_y;
			userForm.hide();
			view.sendMove(); // Send panel requests for our current position
			break;
		
		case "invalidtoken":
			userForm.token = null;
			eraseCookie("token");
			socket.disconnect();
			userForm.setSocketError("invalid_token");
			userForm.show();
			break;
		case "banned":
			socket.userclass = 3;
			var panel = panels.getPanel(view.x, view.y, true);
			panel.clear();
			panel.add({
				type: "message",
				b_i_u: 4,
				text: "You have been banned from this panel",
				color: "#000000",
				id: -1,
				username: "System",
				x: 350,
				y: 300,
				time: "null"
			});
			viewport.redraw();
			break;
		case "deletemessage":
			var panel = panels.getPanel(obj.x, obj.y, false);

			if (panel != null)
				for (var i = 0; i < panel.messages.length; i++)
					if (panel.messages[i].id == obj.m_id) {
						panel.messages.splice(i, 1);
						viewport.redraw();
						console.log("Message deleted");
						break;
					}
			break;
		case "notify":
			alert(obj.text);
			break;
		default:
			console.log("No action defined.");
			break;
		}
		idleTimer = setTimeout(socket.refresh, 300000);
	},

	refresh: function(){
		socket.send({
			type: "refreshpanel"
		});
		idleTimer = setTimeout(socket.refresh, 300000);
	},

	send: function(obj) {
		if (socket.conn){
			var message = JSON.stringify(obj);
			socket.conn.send(message);
			console.log("Sent:", obj);
		} else {
			userForm.setError("socket_close", 0);
			userForm.show();
		}
	},

	connect: function(token) {
		socket.disconnect(); // Ensure there is no pre-existing connection
		socket.conn = new WebSocket('ws://panel.chat:8080');

		socket.conn.onopen = function(e) {
			console.log("Attempting to authenticate WebSocket... (" + token + ")");
			socket.send({
				type: "login",
				token: token
			});
		};

		socket.conn.onmessage = function(e) {
			socket.receive(JSON.parse(e.data));
		};

		socket.conn.onclose = function(e) {
			socket.disconnect();
			userForm.setSocketError("socket_close");
			userForm.show();
		};
		
		socket.conn.onerror = function(e) {
			socket.disconnect();
			userForm.setSocketError("socket_error");
			userForm.show();
		};
	},
	
	disconnect: function() {
		if (socket.conn != null)
			socket.conn.close();
		socket.conn = null;
		
		view.reset();
		panels.reset();
	}
}
