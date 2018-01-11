var popover = {
	nativeDiv:document.getElementById("positioner"),
	jqueryDiv: $("#positioner"),
	message: null,
	visible: false,
	
	show: function(message) {
		popover.message = message;

		// Use this mess of this equation (basically the inverse of what's in mouse.js)
		// to calculate the canvas y position of the message
		var effZoom = view.zoom + view.zoff;
		var msgY = (message.y - message.height + message.baseline + 600 * (effZoom - view.yoff)) / (2 * effZoom + 1);

		// Set the x/y coordinates of the positioner
		popover.nativeDiv.style.left = mouse.canvasX + "px";
		popover.nativeDiv.style.top = msgY + "px";
		
		// Set the title and contents of the popover and show it
		popover.jqueryDiv.data('bs.popover').options.title = message.username;
		popover.jqueryDiv.data('bs.popover').options.content = message.time;
		if(socket.userclass == 1 || socket.userclass == 2){
			popover.jqueryDiv.data('bs.popover').options.content+= "<br><div id='delmsg'>Delete Message</div>";
			popover.jqueryDiv.popover("show");
                	document.getElementById("delmsg").addEventListener("click", function(){
                          socket.send({
                                type: "deletemessage",
                                x: view.x,
                                y: view.y,
                                m_id: message.id
                          });
                          popover.hide();
                	});
		}
		else{
			popover.jqueryDiv.popover("show");
		}

		popover.visible = true;
		//console.log("Popover at: " + mouse.canvasX + ", " + mouse.canvasY);
	},
	
	hide: function() {
		popover.message = null;
		
		if (popover.visible == true) {
			popover.jqueryDiv.popover("hide");
			viewport.redraw();
			popover.visible = false;
		}
	}
};

popover.jqueryDiv.popover({
	title: "",
	content: "",
	html: true,
	placement: "auto top"
});
