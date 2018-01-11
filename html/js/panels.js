// This basically turns panel into a class
function Panel(x, y) {
	this.x = x;
	this.y = y;
	this.messages = [];
	
	this.add = function (msg) {
		msg.height = 24;
		msg.baseline = Math.floor(msg.height / 4);
		
		ctx.font =
		msg.font = (msg.b_u_i & 4 ? "bold " : "")
				+ (msg.b_u_i & 2 ? "italic " : "")
				+ msg.height + "px Arial";
		
		msg.width = Math.min(ctx.measureText(msg.text).width);
		msg.x = Math.round(Math.max(0, (1000 - msg.width) * msg.x / 1000)); // Minimum of 0 prevents text from going off panel
		msg.y = Math.round((600 - msg.height) * msg.y / 600 + msg.height);
		
		var r = parseInt(msg.color.substring(1, 3), 16);
		var g = parseInt(msg.color.substring(3, 5), 16);
		var b = parseInt(msg.color.substring(5, 7), 16);
		var brightness = 0.299*r + 0.587*g + 0.114*b;
		msg.stroke = brightness > 200;

		if (this.messages.push(msg) > 50)
			this.messages.shift();
	}
};

var panels = {
	cache: [],

	// Forewarning: Using panels.get with create=true for a panel >= 14 panels away from your view
	// may cause one of the panels in your view to be cleared. Check whether the target panel is
	// near the user's view before placing any messages in it.
	getPanel: function(px, py, create) {
		// Calculate and fetch the panel at the corresponding address in cache.
		var	addr = ((py & 15) << 4) + (px & 15),
			panel = panels.cache[addr];

		// If this panel is not the one we need, void the result.
		if (panel != null && (panel.x != px || panel.y != py))
			panel = null;

		// If create==true and we did not get a result, create a new panel in cache.
		if (create && panel == null)
			panel = panels.cache[addr] = new Panel(px, py);

		return panel;
	},

	getMessages: function(px, py) {
		var panel = panels.getPanel(px, py, false);
		return panel != null ? panel.messages : [];
	},
	
	/*
	 * Function called when the client disconnects.
	 * The cache will no longer be valid after a disconnect, since message removal
	 * messages could not have been sent.
	 */
	reset: function() {
		for (var i = 0; i < 16 * 16; i++)
			panels.cache[i] = null;
	}
};
