var messageForm = document.forms["message"];
var lastTime = [];
var timer = 0;

messageForm.submit = function() {
	if(socket.userclass == 4){
		alert("You are muted in this panel.");
		return;
	}

	if(socket.userclass == 3){
		alert("You are banned from this panel.");
		return;
	}

	var message = messageForm.text.value.trim();

	if (message != ""){
		if(coolDown()){
			if(message.indexOf("/") == 0){
				socket.send({
				type: "command",
				text: message
			});
		}
		else {
			socket.send({
				type: "message",
				text: message,
				color: messageForm.color.value,
				b_u_i: (messageForm.bold.checked ? 4 : 0)
						+ (messageForm.italic.checked ? 2 : 0)
						+ (messageForm.underline.checked ? 1 : 0),
				panelX: view.x,
				panelY: view.y
			});
		  }
		}
		else{
		  alert("Let someone else talk will ya? Chill for 10 seconds before posting again");
		}
	}
	messageForm.text.value = "";
}

function coolDown(){
	lastTime.push(Date.now());

	lastTime.forEach(function(time, index, lastTime){
		//check if message was posted within last 10 seconds
		if(Date.now()-time >= 10000){
		  //if old, delete
		  lastTime.splice(index, 1);
		}
	});

	//check frequency here
	if(lastTime.length >= 8){
		document.getElementById("message-input").disabled = true;
		setTimeout(function(){document.getElementById("message-input").disabled = false;}, 10000);
		return false;
	}
	else{
		return true;
	}
}

messageForm.onkeypress = function(e) {
	if (e.keyCode == 13) {
		e.preventDefault();
		messageForm.submit();
	}
};

document.getElementById('message-button').addEventListener("click", messageForm.submit);

// This just fixes a graphical error when reloading the page
$("#message-bar-buttons > label").each(function(index) {
	var	label = $(this);
	if (label.find("input").prop("checked")) {
		label.addClass("active");
	}
});
