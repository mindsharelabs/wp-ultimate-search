jQuery(document).ready(function($) {

	$(function(){
		$(".tooltip").tipTip({defaultPosition: "top"});
	});
	
	$("input[type=\"checkbox\"]").change(function() {
		var title = $(this).attr("id");
	    if (this.checked) {
	        $("#" + title + "-title").addClass("checked");
	    } else {
	        $("#" + title + "-title").removeClass("checked");
	    }
	});
	$(".VS-icon-cancel").click(function() {
		var title = $(this).parent().attr("id");
		title = title.replace("-title", "");
		$("#" + title).attr("checked", false);
		$(this).parent().removeClass("checked");
	})


	$("input[type=text], textarea").each(function() {
		if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "")
			$(this).css("color", "#999");
	});

	$("input[type=text], textarea").focus(function() {
		if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "") {
			$(this).val("");
			$(this).css("color", "#000");
		}
	}).blur(function() {
		if ($(this).val() == "" || $(this).val() == $(this).attr("placeholder")) {
			$(this).val($(this).attr("placeholder"));
			$(this).css("color", "#999");
		}
	});

	// Browser compatibility
	if ($.browser.mozilla) 
	         $("form").attr("autocomplete", "off");
});
