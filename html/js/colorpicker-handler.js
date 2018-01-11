var	val = $("#message-color")
	btn = $("#message-color-button"),
	prev = btn.find(".color-preview");

btn.ColorPickerSliders({
	connectedinput: val,
	color: val.val(),
	previewontriggerelement: false,
	title: 'Select Color',
	hsvpanel: true,
	sliders: false,
	swatches: [
		"black",
		"gray",
		"silver",
		"white",
		"maroon",
		"red",
		"olive",
		"yellow",
		"green",
		"lime",
		"teal",
		"aqua",
		"navy",
		"blue",
		"purple",
		"fuchsia"
	],
	customswatches: false,
	onchange: function(container, color) {
		prev.css("background-color", color.tiny.setAlpha(1).toHexString());
	}
});

prev.css("background-color", val.val());