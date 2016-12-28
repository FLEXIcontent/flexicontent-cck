var fc_statehandler = function( options )
{
	this.options = {
		id: '',
		script_url: 'index.php?option=com_flexicontent&format=raw',
		task: '',
		state: ''
	};
	
	if( typeof options !== 'undefined') for (var key in options)
	{
		//console.log(key, options[key]);
		this.options[key] = options[key];
	};

	this.setstate = function( state, id )
	{
		var row = jQuery('#row' + id);
		row.next().hide();
		row.empty().addClass('ajax-loader');
		row.closest('.statetoggler').removeClass('active');

		jQuery.ajax({
			url: this.options.script_url + '&task=' + this.options.task + '&id=' + id + '&state=' + state,
			dataType: 'html',
			data: {
				lang: (typeof _FC_GET !='undefined' && 'lang' in _FC_GET ? _FC_GET['lang']: '')
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

var fc_state_imgs = {
	'1': 'accept.png',
	'-5': 'publish_g.png',
	'0': 'publish_x.png',
	'-3': 'publish_r.png',
	'-4': 'publish_y.png',
	'2': 'archive.png',
	'-2': 'trash.png',
	'u': 'unknown.png'
};
var fc_state_descrs;

jQuery(document).ready(function() {
	fc_state_descrs = {
		'1': Joomla.JText._('FLEXI_PUBLISH_THIS_ITEM'),
		'-5': Joomla.JText._('FLEXI_SET_STATE_AS_IN_PROGRESS'),
		'0': Joomla.JText._('FLEXI_UNPUBLISH_THIS_ITEM'),
		'-3': Joomla.JText._('FLEXI_SET_STATE_AS_PENDING'),
		'-4': Joomla.JText._('FLEXI_SET_STATE_AS_TO_WRITE'),
		'2': Joomla.JText._('FLEXI_ARCHIVE_THIS_ITEM'),
		'-2': Joomla.JText._('FLEXI_TRASH_THIS_ITEM'),
		'u': Joomla.JText._('FLEXI_UNKNOWN')
	};
});

var fc_stateSelector_box;

function fc_toggleStateSelector(el)
{
	var ops = jQuery(el).parent().find('.options');
	fc_stateSelector_box = jQuery(el).closest('.statetoggler');

	if ( ops.is(':hidden') )
	{
		fc_stateSelector_box.addClass('active');

		if (ops.children().length == 0)
		{
			var html = '<div>' + Joomla.JText._('FLEXI_ACTION') + '</div>';
			var iid = ops.data('id');
			var states = ops.data('st');
			jQuery(states).each(function(index, item){
				html += '<span onclick="fc_setitemstate(\'' + item.i + '\', \'' + iid + '\')"><img src="' + fc_statehandler_img_path + fc_state_imgs[item.i] + '">' + fc_state_descrs[item.i] + '</span>';
			});
			jQuery(html).appendTo(ops);
		}
	}

	else
	{
		fc_stateSelector_box.removeClass('active');
		fc_stateSelector_box = null;
	}
	ops.slideToggle(200);
}


function fc_closeStateSelector (e)
{
	if (!fc_stateSelector_box) return;  // no open container
	if (fc_stateSelector_box.is(e.target)) return;  // if target of the click is the container
	if (fc_stateSelector_box.has(e.target).length !== 0) return; // if target of click is a descendant of the container

	fc_stateSelector_box.find('.stateopener').click();
	fc_stateSelector_box = null;
}

jQuery(document).mouseup(function (e)
{
	fc_closeStateSelector(e);
});
