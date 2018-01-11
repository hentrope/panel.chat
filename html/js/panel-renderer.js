var viewport = {
	rendering: false,
	prevtime: 0,

	// The canvas renderer handles all of the transformations.
	// Simply write the panel renderer as if it has the entire canvas to work with.
	renderPanel: function(x, y) {
		if (x <= view.xmax && x >= -view.xmax && y <= view.ymax && y >= -view.ymax) {
			if ((x + y)&1 != 0)
				ctx.fillStyle = "#EEEEEE";
			else
				ctx.fillStyle = "#FFFFFF";

			//Render Panel identifier
			ctx.fillRect(0, 0, canvas.width, canvas.height);

			ctx.fillStyle = "rgba(0, 0, 0, 0.1)";
			ctx.font = "100px Arial";
			if (x == 0 && y == 0) {
				var txt = "Welcome!";
				var width = Math.min(ctx.measureText(txt).width);
				ctx.fillText(txt, 500 - width / 2 , 250);

				ctx.font = "40px Arial";
				
				txt = "Use the arrow keys or click to move panels.";
				width = Math.min(ctx.measureText(txt).width);
				ctx.fillText(txt, 500 - width / 2 , 350);
				
				txt = "Page up/page down or the mouse scroll wheel";
				width = Math.min(ctx.measureText(txt).width);
				ctx.fillText(txt, 500 - width / 2 , 400);
				
				txt = "can be used to zoom in and out.";
				width = Math.min(ctx.measureText(txt).width);
				ctx.fillText(txt, 500 - width / 2 , 450);
			} else {
				var txt = "(" + x + ", " + -y + ")";
				var width = Math.min(ctx.measureText(txt).width);
				ctx.fillText(txt, 500 - width / 2 , 325);
			}

			//Get panel from cache
			//NOTE: If for some reason we don't have the specified panel in cache, panel will just be empty
			var messages = panels.getMessages(x, y);
			
			// Determine the targeted message which should be highlighted
			var target = popover.message;
			if (target == null && mouse.inside)
				target = mouse.message;
			
			// Render all messages in currently stored panel object
			messages.forEach( function(msg) {
				// If the message is either being moused over, or has a popover active, highlight it
				if (msg === popover.message || (mouse.inside && msg == mouse.message)) {
					ctx.fillStyle = "rgba(0, 0, 0, 0.1)";
					ctx.roundRect(msg.x - 2, msg.y - msg.height + msg.baseline - 2, msg.width + 4, msg.height + 4, 5).fill();
				}

				ctx.fillStyle = msg.color;
				ctx.font = msg.font;
				ctx.fillText(msg.text, msg.x, msg.y);
				
				if (msg.stroke) {
					ctx.strokeStyle = "rgba(0, 0, 0, 0.3)";
					ctx.strokeText(msg.text, msg.x, msg.y);
				}

				// Code for manual underlining
				if (msg.b_u_i & 1) {
					ctx.strokeStyle = msg.color;
					ctx.beginPath();
					ctx.moveTo(msg.x, msg.y);
					ctx.lineTo(msg.x + msg.width, msg.y);
					ctx.stroke();
				}
			});
			
			// If the view is zoomed out, shade the panel that has the mouse over it
			if (mouse.inside
					&& view.zoom + view.zoff > 0.95
					&& Math.floor(mouse.worldX) == x
					&& Math.floor(mouse.worldY) == y) {
				ctx.fillStyle = "rgba(51, 112, 183, 0.1)";
				ctx.fillRect(0, 0, canvas.width, canvas.height);
			}
		}
	},
	
	render: function(time) {
		// Calculate how much the offsets must be reduced with based on time since last frame
		var factor = 1 / Math.exp((time - viewport.prevtime) / 75);
		view.xoff = factor * view.xoff;
		view.yoff = factor * view.yoff;
		view.zoff = factor * view.zoff;
		
		// Recalculate the mouse position based on the new offsets
		mouse.updateWorld();
		
		// If all offsets are too small to make a difference, finish rendering
		if (Math.abs(view.xoff) < 0.002
				&& Math.abs(view.yoff) < 0.002
				&& Math.abs(view.zoff) < 0.002) {
			view.xoff = view.yoff = view.zoff = 0;
			viewport.rendering = false;
		}

		// Clear the canvas
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		ctx.save();
		// Calculate the effective zoom level
		var effZoom = view.zoom + view.zoff;

		// Using the level of zoom, determine how to scale the canvas
		var scale = 1/(2*effZoom + 1);
		ctx.scale(scale, scale);

		// Translate the entire canvas by the x and y offsets
		ctx.translate(-view.xoff * canvas.width, -view.yoff * canvas.height);

		// Calculate the range of panels that would be in the view at the current zoom level
		var base = Math.ceil(effZoom);
		var xlower = -base + Math.floor(view.xoff);
		var xupper = base + Math.ceil(view.xoff);
		var ylower = -base + Math.floor(view.yoff);
		var yupper = base + Math.ceil(view.yoff);

		// Render all of the panels that fall within these ranges
		for (var i = xlower; i <= xupper; i++) {
			for (var j = ylower; j <= yupper; j++) {
				if (Math.abs(i) <= view.xmax && Math.abs(j) <= view.ymax) {
					ctx.save();
					ctx.translate((effZoom + i) * canvas.width, (effZoom + j) * canvas.height);
					viewport.renderPanel(view.x + i, view.y + j);
					ctx.restore();
				}
			}
		}
		ctx.restore();
		
		// Draw the coordinates of the mouse on-screen
		/*ctx.fillStyle = "black";
		ctx.font = "8px arial";
		ctx.fillText("(" + mouse.worldX + ", " + mouse.worldY + ")", 8, 8);
		ctx.fillText("(" + Math.floor(mouse.worldX) + ", " + Math.floor(mouse.worldY) + ")", 8, 18);*/

		// If all offsets are too small to make a difference, finish rendering
		if (Math.abs(view.xoff) < 0.001     // 1/1000 pixels wide
				&& Math.abs(view.yoff) < 0.00166 // 1/600 pixels tall
				&& Math.abs(view.zoff) < 0.002) {
			view.xoff = view.yoff = view.zoff = 0;
			viewport.rendering = false;
		}

		// If the animation is not complete, request another frame
		if (viewport.rendering) {
			viewport.prevtime = time;
			requestAnimationFrame(viewport.render);
		}
	},
	
	redraw: function() {
		if (!viewport.rendering) {
			viewport.rendering = true;
			viewport.prevtime = performance.now();
			requestAnimationFrame(viewport.render);
		}
	}
};

viewport.redraw();
