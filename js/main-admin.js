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
	
	var sections = [];
	sec = $.parseJSON(main);
	$.each( sec, function(key, value) {
		sections[value] = key;
	});

	
	// foreach($this->sections as $section_slug => $section) {
	//	echo "sections['$section'] = '$section_slug';";
	//}
	var wrapped = $(".wrap h3").wrap("<div class=\"ui-tabs-panel\">");
		wrapped.each(function() {
			$(this).parent().append($(this).parent().nextUntil("div.ui-tabs-panel"));
		});
		$(".ui-tabs-panel").each(function(index) {
			$(this).attr("id", sections[$(this).children("h3").text()]);
			if (index > 0)
				$(this).addClass("ui-tabs-hide");
		});
		$(".ui-tabs").tabs({
			fx: { opacity: "toggle", duration: "fast" }
		});

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

		$(".wrap h3, .wrap table").show();

		// This will make the "warning" checkbox class really stand out when checked.
		// I use it here for the Reset checkbox.
		$(".warning").change(function() {
			if ($(this).is(":checked"))
				$(this).parent().css("background", "#c00").css("color", "#fff").css("fontWeight", "bold");
			else
				$(this).parent().css("background", "none").css("color", "inherit").css("fontWeight", "normal");
		});

		// Browser compatibility
		if ($.browser.mozilla) 
		         $("form").attr("autocomplete", "off");
});
