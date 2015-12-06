var fc_statehandler = function( options )
{
	this.options = {
		id: "",
		script_url: "index.php?option=com_flexicontent&format=raw",
		task: "",
		state: ""
	};
	
	if( typeof options !== 'undefined') for (var key in options)
	{
		//console.log(key, options[key]);
		this.options[key] = options[key];
	};

	this.setstate = function( state, id ) {
		var stateurl = this.options.script_url + "&task=" + this.options.task + "&id=" + id + "&state=" + state;
		jQuery('#row' + id).empty().addClass('ajax-loader');
		
		jQuery.ajax({
			url: stateurl,
			dataType: "html",
			data: {
				lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: '')
			},
			success: function( data )
			{
				jQuery('#row' + id).removeClass('ajax-loader').html(data);
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	}
};

function fc_toggleStateSelector(el){
	if ( jQuery(el).parent().find("ul").is(":hidden") ) {
		jQuery(el).closest("ul.statetoggler").find(".stateopener").addClass("active");
	} else {
		jQuery(el).closest("ul.statetoggler").find(".stateopener").removeClass("active");
	}
	jQuery(el).parent().find("ul").slideToggle(200);
}
