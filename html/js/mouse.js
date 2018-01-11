var mouse = {
	inside: false,
	canvasX: 0,
	canvasY: 0,
	worldX: 0,
	worldY: 0,
	message: null,
	
	updateWorld: function() {
		var oldX = mouse.worldX,
			oldY = mouse.worldY,
			oldMsg = mouse.message;

		// Calculate the effective zoom level
		var effZoom = view.zoom + view.zoff;

		// Using the level of zoom, determine how to scale the canvas
		var scale = 2 * effZoom + 1;
		
		// Use the scale and effective zoom levels to calculate the mouse position
		mouse.worldX = mouse.canvasX * scale / 1000 - effZoom + view.x + view.xoff;
		mouse.worldY = mouse.canvasY * scale / 600 - effZoom + view.y + view.yoff;
		
		// Clear the old selected message
		mouse.message = null;
		
		// If the client is fully zoomed in, find the new selected message at this position		
		if (effZoom < 0.1) {
			// Calculate the panel x/y, and the position of the mouse within the panel
			var panelX = Math.floor(mouse.worldX),
				panelY = Math.floor(mouse.worldY),
				panelSubX = (mouse.worldX - panelX) * 1000,
				panelSubY = (mouse.worldY - panelY) * 600;
			
			// Retrieve all messages for whichever panel the mouse is inside of
			var messages = panels.getMessages(panelX, panelY);
			
			// Loop through all messages in the panel, checking for collision
			for (var i = messages.length - 1; i >= 0; i--) {
				var msg = messages[i];
				if (panelSubX >= msg.x
						&& panelSubX < msg.x + msg.width
						&& panelSubY >= msg.y - msg.height + msg.baseline
						&& panelSubY < msg.y + msg.baseline) {
					mouse.message = msg;
					break;
				}
			}
			
			if (mouse.message !== oldMsg)
				viewport.redraw();
		} else {
			// Only redraw the canvas if the mouse changed panels
			if (Math.floor(mouse.worldX) != Math.floor(oldX)
					|| Math.floor(mouse.worldY) != Math.floor(oldY))
				viewport.redraw();
		}
	},
	
	click: function() {
		if (view.zoom + view.zoff < 0.1) {
			if (mouse.message == null)
				popover.hide();
			else
				popover.show(mouse.message);
		} else {
			var x = Math.floor(mouse.worldX),
				y = Math.floor(mouse.worldY);
			if (x == view.x && y == view.y)
				view.move(view.x, view.y, 0);
			else if (Math.abs(x) <= view.xmax && Math.abs(y) <= view.ymax)
				view.move(x, y, view.zoom);
		}
	}
};

canvas.addEventListener('click', mouse.click);

canvas.addEventListener('mouseenter', function(e) {
	mouse.inside = true;
	if (view.zoom + view.zoff > 0.1)
		viewport.redraw();
});

canvas.addEventListener('mouseleave', function(e) {
	mouse.inside = false;
	if (view.zoom + view.zoff > 0.1)
		viewport.redraw();
});

canvas.addEventListener('mousemove', function(evt) {
	var rect = canvas.getBoundingClientRect();
	mouse.canvasX = evt.clientX - rect.left;
	mouse.canvasY = evt.clientY - rect.top;
	mouse.updateWorld();
});