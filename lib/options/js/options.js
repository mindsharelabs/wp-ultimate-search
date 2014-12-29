jQuery(document).ready(function($){

  	// This will make the "warning" checkbox class really stand out when checked.
	// I use it here for the Reset checkbox.
	$(".warning").change(function() {
		if ($(this).is(":checked"))
			$(this).parent().css("background", "#c00").css("color", "#fff").css("fontWeight", "bold");
		else
			$(this).parent().css("background", "none").css("color", "inherit").css("fontWeight", "normal");
	});

	/**
	 * Preserves user's currently selected tab after page reload
	 */
	
	var hash = window.location.hash;
	hash && $('ul.nav a[href="' + hash + '"]').tab('show');

	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {

		var scrollmem = $('body').scrollTop();
		window.location.hash = e.target.hash;
		$('html,body').scrollTop(scrollmem);

		$(".code_text").each(function(i) {
			editor[i].refresh();
		});

	});

	/**
	 * Code Editor Field
	 * @since 2.0
	 */
	
	var editor = new Array();

	function codemirror_resize_fix() {
		var evt = document.createEvent('UIEvents');
		evt.initUIEvent('resize', true, false, window, 0);
		window.dispatchEvent(evt);
	}

	$(".code_text").each(function(i) {

		var lang = jQuery(this).attr("data-lang");
		switch(lang) {
			case 'php':
				lang = 'application/x-httpd-php';
				break;
			case 'css':
				lang = 'text/css';
				break;
			case 'html':
				lang = 'text/html';
				break;
			case 'javascript':
				lang = 'text/javascript';
				break;
			default:
				lang = 'application/x-httpd-php';
		}

		var theme = $(this).attr("data-theme");
		switch(theme) {
			case 'default':
				theme = 'default';
		 		break;
			case 'dark':
				theme = 'twilight';
				break;
			default:
		 		theme = 'default';
		}

		editor[i] = CodeMirror.fromTextArea(this, {
			lineNumbers:    true,
			//matchBrackets:  true,
			mode:           lang,
			indentUnit:     4,
			indentWithTabs: true
			//enterMode:      "keep",
			//tabMode:        "shift"
		});

		editor[i].setOption("theme", theme);
	});

	/**
	 * File upload button
	 * @since 2.0
	 */
	$('.fileinput-field .btn-file').bind('click', function(e) {
		tb_show('', 'media-upload.php?post_id=0&type=image&apc=apc&TB_iframe=true');
		var filebutton = $(this);
		//store old send to editor function
		window.restore_send_to_editor = window.send_to_editor;

		//overwrite send to editor function
		window.send_to_editor = function(html) {

			imgurl = $('img', html).attr('src');

			var index = imgurl.lastIndexOf("/") + 1;
			var filename = imgurl.substr(index);

			filebutton.find('.fileinput-input').val(imgurl);
			filebutton.prev().find('.fileinput-filename').text(filename);

			filebutton.parent().parent().removeClass('fileinput-new').addClass('fileinput-exists');

			// Update image preview
			filebutton.parent().parent().find('.fileinput-preview').html("<img src='" + imgurl + "'>");

			//load_images_muploader();
			tb_remove();
			//restore old send to editor function
			window.send_to_editor = window.restore_send_to_editor;

		}

		return false;
	});

	$('.fileinput-field .btn.fileinput-exists').bind('click', function(e) {

		$(this).parent().parent().removeClass('fileinput-exists').addClass('fileinput-new');
		$(this).prev().find('.fileinput-input').val('');
		$(this).prev().prev().find('.fileinput-filename').text('');


	});

});
