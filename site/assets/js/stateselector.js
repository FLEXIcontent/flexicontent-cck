var fc_statehandler = new Class(  
{  
	options:  {
		id: "",
		script_url: "index.php?option=com_flexicontent&format=raw",
		task: "",
		state: ""
	},

	initialize: function( options ) {  
		for (var key in options) {
			//console.log(key, options[key]);
			this.options[key] = options[key];
		}
	},

	setstate: function( state, id ) {
		var stateurl = this.options.script_url + "&task=" + this.options.task + "&id=" + id + "&state=" + state;
		jQuery('#row' + id).empty().addClass('ajax-loader');
		
		jQuery.ajax({
			url: stateurl,
			dataType: "html",
			success: function( data )
			{
				jQuery('#row' + id).removeClass('ajax-loader').html(data);
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	}
});

function fc_toggleStateSelector(el){
	if ( jQuery(el).parent().find("ul").is(":hidden") ) {
		jQuery(el).closest("ul.statetoggler").find(".stateopener").addClass("btn-warning");
	} else {
		jQuery(el).closest("ul.statetoggler").find(".stateopener").removeClass("btn-warning");
	}
	jQuery(el).parent().find("ul").slideToggle();
}
