var code_box_cnt = 0;

function toggle_code_inputbox(btn) {
	var _btn = jQuery(btn);
	var el = jQuery(btn).next().next();

	var becomes_visible = !el.is(':visible');
	el.toggle();
	becomes_visible ? code_box_cnt++ : code_box_cnt--;
	if (code_box_cnt < 0) code_box_cnt = 0;

	if (becomes_visible) {
		_btn.addClass('btn-info').find('span').removeClass('icon-eye').addClass('icon-eye-close');
		el.get(0).select();
		el.prev().show(400);
	} else {
		_btn.removeClass('btn-info').find('span').removeClass('icon-eye-close').addClass('icon-eye');
		el.prev().hide(400);
	}
}

function set_editor_contents(txtarea, theData, extension) {
	var editorId = txtarea.attr('id');

	// Check if Joomla editor instance exists (Joomla 4/5 standard)
	if (Joomla.editors.instances && Joomla.editors.instances[editorId]) {
		var editor = Joomla.editors.instances[editorId];
		editor.setValue(theData.content);

		// Try to set mode if underlying instance is CodeMirror
		// Note: Joomla's wrapper might expose the CM instance differently or not at all depending on version
		// But setting value is the critical part to fix the double editor issue.
		// In J4/J5, we might access the CM instance via .codemirror or .cm if available on the wrapper context
		// For now, let's assume standard behavior is enough to fix the breakage.
	}
	else {
		// Fallback for when no editor is loaded or old Joomla
		var CM = txtarea.next();//.get(0).CodeMirror;
		if (CM.hasClass('CodeMirror')) {
			CM.get(0).CodeMirror.toTextArea();
			txtarea.val(theData.content);
			txtarea.attr('form', 'layout_file_editor_form');
			txtarea.show();
			CM = CodeMirror.fromTextArea(txtarea.get(0),
				{
					mode: extension,
					indentUnit: 2,
					lineNumbers: true,
					matchBrackets: true,
					lineWrapping: true,
					onCursorActivity: function () {
						CM.setLineClass(hlLine, null);
						hlLine = CM.setLineClass(CM.getCursor().line, "activeline");
					}
				});
			CM.getWrapperElement().style['font-size'] = 14 + 'px';
			CM.getWrapperElement().style['font-weight'] = 'bold';
			CM.getWrapperElement().style['font-family'] = 'Courier New';
			CM.refresh();
		}
		else {
			txtarea.val(theData.content);
			txtarea.show();
		}
	}
}

function save_layout_file(formid) {
	var form = jQuery('#' + formid);
	var layout_name = jQuery('#editor__layout_name').val();
	var file_subpath = jQuery('#editor__file_subpath').val();
	if (file_subpath == '') {
		alert(Joomla.JText._('FLEXI_TMPLS_LOAD_FILE_BEFORE_SAVING'));
		return;
	}

	if (isCoreLayout && !confirm(Joomla.JText._('FLEXI_TMPLS_SAVE_BUILT_IN_TEMPLATE_FILE_WARNING'))) {
		return false;
	}

	txtarea = jQuery('#editor__file_contents');
	txtarea.before('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... ' + Joomla.JText._("FLEXI_SAVING") + '<br/></span>');

	// Set the current codemirror data into the textarea before serializing and submit the form via AJAX
	var editorId = txtarea.attr('id');
	if (Joomla.editors.instances && Joomla.editors.instances[editorId]) {
		// standard Joomla API
		txtarea.val(Joomla.editors.instances[editorId].getValue());
	} else {
		// fallback
		var CM = txtarea.next();//.get(0).CodeMirror;
		if (CM.hasClass('CodeMirror')) {
			var file_contents = CM.get(0).CodeMirror.getValue();
			txtarea.val(file_contents);
		}
	}

	jQuery.ajax({
		type: "POST",
		url: "index.php?option=com_flexicontent&task=templates.savelayoutfile&format=raw",
		data: form.serialize(),
		success: function (data) {
			jQuery('#fc_doajax_loading').remove();
			var theData = jQuery.parseJSON(data);
			jQuery('#ajax-system-message-container').html(theData.sysmssg);
			if (!theData.content) return;  // Saving task may return modified data, if so set them into the editor

			var extension = jQuery('#editor__file_subpath').val().split('.').pop().toLowerCase();
			set_editor_contents(txtarea, theData, extension);
		}
	});
}

function load_layout_file(layout_name, file_subpath, load_mode, btn_classes) {
	var layout_name = (typeof layout_name != "undefined" && layout_name != '') ? layout_name : jQuery('#editor__layout_name').val();
	var file_subpath = (typeof file_subpath != "undefined" && file_subpath != '') ? file_subpath : jQuery('#editor__file_subpath').val();
	var btn_classes = (typeof btn_classes != "undefined") ? btn_classes : '';
	if (btn_classes == '-1') btn_classes = jQuery('#editor__btn_classes').val();

	var load_mode = (typeof load_mode != "undefined") ? load_mode : 0;
	var form = jQuery('#layout_file_editor_form');

	jQuery('#editor__layout_name').val(layout_name);
	jQuery('#editor__file_subpath').val(file_subpath);
	jQuery('#editor__load_mode').val(load_mode);
	jQuery('#editor__btn_classes').val(btn_classes);

	jQuery('.code_box').hide();
	btn_classes = btn_classes != '' ? btn_classes.split(" ") : Array();
	jQuery.each(btn_classes, function (cname, val) {
		jQuery('.' + val).show();
	});
	if (btn_classes.length)
		jQuery('#code_box_header').css('display', '');
	else
		jQuery('#code_box_header').css('display', 'none');

	if (load_mode == '2') {
		form.submit();
		return;
	}

	txtarea = jQuery('#editor__file_contents');
	txtarea.before('<span id="fc_doajax_loading"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center" /> ... ' + Joomla.JText._("FLEXI_LOADING") + '<br/></span>');
	txtarea.hide();
	jQuery('#layout_edit_name_container').html(file_subpath);

	var ajax_data = { layout_name: layout_name, file_subpath: file_subpath, load_mode: load_mode };
	ajax_data[jformToken] = 1;

	jQuery.ajax({
		type: form.attr('method'),
		url: form.attr('action'),
		data: ajax_data,
		success: function (data) {
			jQuery('#fc_doajax_loading').remove();
			var theData = jQuery.parseJSON(data);
			jQuery('#ajax-system-message-container').html(theData.sysmssg);

			var extension = file_subpath.split('.').pop().toLowerCase();
			extension == 'css' ?
				jQuery('#edit-css-files-warning').show() :
				jQuery('#edit-css-files-warning').hide();

			// Loading task always return data, even empty data, set them into the editor
			set_editor_contents(txtarea, theData, extension);

			// Display the buttons
			jQuery('#editor__save_file_btn').css('display', '');
			jQuery('#editor__download_file_btn').css('display', '');
			jQuery('#editor__load_common_file_btn').css('display', parseInt(theData.default_exists) ? '' : 'none');
		}
	});
}