var sidebar = {
	visible: false,
	container: document.getElementById("app-container"),
	
	toggle: function() {
		sidebar.visible = !sidebar.visible;
		
		if (sidebar.visible)
			sidebar.container.className = "expanded";
		else
			sidebar.container.className = "";
	},
	
	onLogout: function() {
		var http = new XMLHttpRequest();
		http.open("POST", "user.php");
		http.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		
		http.onreadystatechange = function() {
			if (http.readyState == 4) {
				console.log(http.responseText);
				if (http.status != 200)
					console.error(http.responseText);
				
				userForm.token = null;
				eraseCookie("token");
				socket.disconnect();
				userForm.show();
			}
		}
		http.send( "action=logout"
				+ "&token=" + encodeURIComponent(userForm.token));
		
		console.log("Logout Submitted");
	},
	
	xpos: document.getElementById("jump-xpos"),
	ypos: document.getElementById("jump-ypos"),
	jump: function() {
		// Get the new position from the fields
		var x = parseInt(sidebar.xpos.value),
			y = parseInt(sidebar.ypos.value);
		
		// Make sure that the new positions are valid integers
		if (!isNaN(x) && !isNaN(y)) {
			// Make sure the new position is within the bounds of the map
			x = x < -view.xmax ? -view.xmax : x;
			x = x > view.xmax ? view.xmax : x;
			y = y < -view.ymax ? -view.ymax : y;
			y = y > view.ymax ? view.ymax : y;

			// Move the view to the new position
			view.move(x, -y, view.zoom);
		}
	},
	setPos: function(x, y) {
		sidebar.xpos.value = x;
		sidebar.ypos.value = -y;
	},
	makeFilter: function(input) {
		var prev = input.value;
		
		return function() {
			var val = input.value;
			if (val == "" || val == "-")
				prev = val;
			else {
				var ival = parseInt(val);
				if (!isNaN(ival))
					prev = ival;
				input.value = prev;
			}
		}
	}
};

document.getElementById('app-toggle-button').addEventListener('click', sidebar.toggle);
document.getElementById('logout').addEventListener('click', sidebar.onLogout);
document.getElementById("jump-button").addEventListener('click', sidebar.jump);

sidebar.setPos(view.x, view.y);
sidebar.xpos.addEventListener("input", sidebar.makeFilter(sidebar.xpos));
sidebar.ypos.addEventListener("input", sidebar.makeFilter(sidebar.ypos));
