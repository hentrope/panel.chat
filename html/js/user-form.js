var userForm = {
	prompt: $('#user-modal'),
	visible: true,
	
	login: document.forms["login-form"],
	onLogin: function() {
		var name = userForm.login["username"].value,
			pass = userForm.login["userpass"].value,
			rem = userForm.login["remember"].checked;

		var http = new XMLHttpRequest();
		http.open("POST", "user.php");
		http.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		
		http.onreadystatechange = function() {
			if (http.readyState == 4) {
				if (http.status == 200)
					socket.connect(http.responseText);
				else
					userForm.setError(http.responseText, 1);
			}
		}
		http.send( "action=login"
				+ "&username=" + encodeURIComponent(name)
				+ "&userpass=" + encodeURIComponent(pass)
				+ "&remember=" + encodeURIComponent(rem));
		
		console.log("Login Submitted");
	},
	
	register: document.forms["register-form"],
	onRegister: function() {
		var name = userForm.register["username"].value,
			pass = userForm.register["userpass"].value,
			conf = userForm.register["confirm-pass"].value;
		
		if (pass != conf) {
			userForm.setError("unequal_passwords", 2);
			return;
		}

		var http = new XMLHttpRequest();
		http.open("POST", "user.php");
		http.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

		http.onreadystatechange = function() {
			if (http.readyState == 4) {
				if (http.status == 200)
					userForm.setSuccess(http.responseText, 2);
				else
					userForm.setError(http.responseText, 2);
			}
		}
		http.send( "action=register"
				+ "&username=" + encodeURIComponent(name)
				+ "&userpass=" + encodeURIComponent(pass));

		console.log("Register submitted");
	},
	
	registerSuccess: document.getElementById('register-success-msg'),
	setSuccess: function(msg, target) {
		switch (msg) {
			case "success":
				msg = "Account successfully created!"; break;
		}
		
		if (!target || target == 2) {
			userForm.registerSuccess.innerHTML = msg;
			userForm.registerError.innerHTML = "";
		}
	},

	loginError: document.getElementById('login-error-msg'),
	registerError: document.getElementById('register-error-msg'),
	setError: function(err, target) {
		switch (err) {
			case "socket_close":
				err = "Disconnected from server."; break;
			case "socket_error":
				err = "Error connecting to server."; break;
			case "invalid_token":
				err = "Invalid token, log in again."; break;
			case "username_too_short":
				err = "Username is too short."; break;
			case "username_too_long":
				err = "Username is too short."; break;
			case "illegal_character":
				err = "Illegal username character(s). A-Z a-z 0-9 _ allowed."; break;
			case "unequal_passwords":
				err = "Passwords do not match."; break;
			case "user_exists":
				err = "Username unavailable."; break;
			case "cannot_create_user":
				err = "Unable to create user."; break;
			case "invalid_credentials":
				err = "User credentials do not match."; break;
			case "user_killed":
				err = "This account has been banned."; break;
			case "cannot_create_token":
				err = "Unable to create token."; break;
		}
		
		if (!target || target == 1) {
			userForm.loginError.innerHTML = err;
		}
		
		if (!target || target == 2) {
			userForm.registerSuccess.innerHTML = "";
			userForm.registerError.innerHTML = err;
		}
	},
	
	socketError: false,
	setSocketError: function(err) {
		// Ignore the message if there is already a socket error
		if (!userForm.socketError) {
			userForm.setError(err, 0);		// Show the error message
			userForm.socketError = true;	// Set the socket error flag
		}
	},
	
	show: function() {
		if (!userForm.visible) {
			userForm.visible = true;
			userForm.prompt.modal("show");
		}
	},
	
	hide: function() {
		if (userForm.visible) {
			userForm.visible = false;
			userForm.prompt.modal("hide");
			userForm.setError("", 0);
			userForm.socketError = false;		// Clear the socket error flag
		}
	},
	
	token: readCookie("token")
}

document.getElementById('login-button').addEventListener('click', userForm.onLogin);
document.getElementById('register-button').addEventListener('click', userForm.onRegister);

if (userForm.token)
	socket.connect(userForm.token);
