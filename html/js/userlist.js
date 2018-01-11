var userlist = {
	list: [],
	div: document.getElementById("userlist"),
	
	update: function() {
		html = "";

		userlist.list.forEach( function(item, i, arr) {
			//append user class label
			html += "<div class='user-options' id='" + item.username + "-options'>";
			switch(item.class){
				case 3:
					html += "<span class='label label-danger'>BANNED</span> ";
					break;
				case 2:
					html += "<span class='label label-primary'>MOD</span> ";
					break;
				case 1:
					html += "<span class='label label-warning'>ADMIN</span> ";
					break;
				default:
					break;
			}
			html += item.username + "</div>";
			//if (i < arr.length - 1)
			//	html += "<br>";
		});
		userlist.div.innerHTML = html;
		//Must add popup and event listeners AFTER setting HTML, otherwise jquery can't access the divs
		userlist.list.forEach( function(item, i, arr) {
		  var field = $("#" + item.username + "-options");
		  $('html').click(function(e){
			field.popover('hide');
		  });

		field.popover({
			title: "",
			content: "",
			html: true,
			trigger: 'manual',
			placement: "auto bottom"
		}).click(function(e){
			$(this).popover('toggle');
					if(socket.userclass == 1 || socket.userclass == 2){
							document.getElementById("ban-click").addEventListener("click", function(){
								var time = prompt("Enter ban duration in hours:");
					socket.send({
										type: "command",
										text: "/ban " + item.username + " " + time
									});
							});

				document.getElementById("mute-click").addEventListener("click", function(){
									var time = prompt("Enter mute duration in hours:");
									socket.send({
					type: "command",
					text: "/mute " + item.username + " " + time
					});
								});

				document.getElementById("mod-click").addEventListener("click", function(){
									socket.send({
					type: "command",
					text: "/mod "+ item.username
					})
								});
					}
						e.stopPropagation();
					});
			//field.data('bs.popover').options.title = item.username;
			if(socket.userclass == 1 || socket.userclass == 2){
			field.data('bs.popover').options.title = item.username;
					field.data('bs.popover').options.content= "<div class='user-options' id='ban-click'>Ban User</div>"+
																"<div class='user-options' id='mute-click'>Mute User</div>"+
									"<div class='user-options' id='mod-click'>Mod User</div>";
			}
			/*else{
			field.data('bs.popover').options.content
			}*/

		});
	},
	
	set: function(list) {
		userlist.list = list;
		userlist.update();
	},
	
	add: function(item) {
		userlist.list.push(item);
		userlist.update();
	},
	
	remove: function(item) {
		userlist.list.forEach( function(item2, i, arr) {
			if (item.username == item2.username) {
				arr.splice(i, 1);
				userlist.update();
				return;
			}
		});
	}
};
