var messageInput = document.getElementById("message-input");

var view = {
	x: 0,			// x coordinate of the panel in which the view is focused on
	y: 0,			// y coordinate of the panel in which the view is focused on
	zoom: 0,		// Level of zoom. 0 is fully zoomed in, 1 is first level zoomed out.

	xoff: 0,		// Number of panels to offset the view horizontally
	yoff: 0,		// Number of panels to offset the view vertically
	zoff: 0,		// Amount to offset the level of zoom

	oldx: this.x,			// Previous x coordinate, used for sendMove()
	oldy: this.y,			// Previous y coordinate, used for sendMove()
	oldzoom: this.zoom,		// Previous zoom level, used for sendMove()

	xmax: 100,      // Worldsize X
	ymax: 100,		// Worldsize Y
	zmax: 2,		// Zoom limit

	/*
	 * Function called by a control changing the current view.
	 * This function initially only updates the canvas visually, while the
	 * movement and panel requests are sent later in sendMove().
	 */
	move: function(newX, newY, newZoom) {
		view.xoff -= newX - view.x;
		view.yoff -= newY - view.y;
		view.zoff -= newZoom - view.zoom;

		view.x = newX;
		view.y = newY;
		view.zoom = newZoom;

		view.sendMove();
		sidebar.setPos(newX, newY);
		viewport.redraw();

		popover.hide();
		messageInput.disabled = (view.zoom != 0 || (view.x == 0 && view.y == 0 && socket.userclass != 1));
	},

	/*
	 * This is a debounced function called by move().
	 * This function calculates all panels coming into the view, and sends a request
	 * for those panels in a move message.
	 */
	sendMove: debounce( function() {
		var	newPanels = [],
			xlower = Math.max(view.x-view.zoom, -view.xmax),
			xupper = Math.min(view.x+view.zoom, view.xmax),
			ylower = Math.max(view.y-view.zoom, -view.ymax),
			yupper = Math.min(view.y+view.zoom, view.ymax);

		for (var j = ylower; j <= yupper; j++)
			for (var i = xlower; i <= xupper; i++)
				if (Math.abs(i-view.oldx) > view.oldzoom || Math.abs(j-view.oldy) > view.oldzoom) {
					var	list = panels.getMessages(i, j);

					//add to list of PartialRequest objects to be returned
					newPanels.push({
						type: "partialrequest",
						panel: i+"/"+j,
						lastID: list.length > 0 ? list[list.length-1].id : -1
					});
				}

		socket.send({
			type: "move",
			newPX: view.x,
			newPY: view.y,
			newZL: view.zoom,
			newPanels: newPanels
		});

		view.oldx = view.x;
		view.oldy = view.y;
		view.oldzoom = view.zoom;
	}, 300),
	
	/*
	 * Function called when the client disconnects.
	 * This allows for the correct panels to be requested when move() is called
	 * after the client reconnects.
	 */
	reset: function() {
		view.oldx = 0;
		view.oldy = 0;
		view.oldzoom = 0;
	}
};

window.addEventListener('keydown', function(e) {
	if (document.activeElement.tagName === "BODY") {
		switch (e.keyCode) {
			case 37:
				if (view.x > -view.xmax)
					view.move(view.x - 1, view.y, view.zoom);
				break;
			case 39:
				if( view.x < view.xmax)
					view.move(view.x + 1, view.y, view.zoom);
				break;
			case 38:
				if (view.y > -view.ymax)
					view.move(view.x, view.y - 1, view.zoom);
				break;
			case 40:
				if (view.y < view.ymax) 
					view.move(view.x, view.y + 1, view.zoom);
				break;
			case 33: //Pageup
				if (view.zoom > 0)
					view.move(view.x, view.y, view.zoom - 1);
				break;
			case 34: //Pagedown
				if (view.zoom < view.zmax)
					view.move(view.x, view.y, view.zoom + 1);
				break;

			default:
				return;
		}
		e.preventDefault();
	}
});

window.addEventListener('mousewheel', function(e) {
	if (document.activeElement.tagName === "BODY") {
		if (e.deltaY < 0) {
			if (view.zoom > 0)
				view.move(view.x, view.y, view.zoom - 1);
			e.preventDefault();
		}
		
		else if (e.deltaY > 0) {
			if (view.zoom < view.zmax)
					view.move(view.x, view.y, view.zoom + 1);
			e.preventDefault();
		}
	}
});

window.addEventListener('DOMMouseScroll', function(e) {
	if (document.activeElement.tagName === "BODY" && e.axis == 2) {
		if (e.detail < 0) {
			if (view.zoom > 0)
				view.move(view.x, view.y, view.zoom - 1);
			e.preventDefault();
		}
		
		else if (e.detail > 0) {
			if (view.zoom < view.zmax)
					view.move(view.x, view.y, view.zoom + 1);
			e.preventDefault();
		}
	}
});
