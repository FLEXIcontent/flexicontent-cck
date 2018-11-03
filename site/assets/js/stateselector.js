
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
var fc_state_icons = {
	'1': 'publish',
	'-5': 'checkmark-2',
	'0': 'unpublish',
	'-3': 'question',
	'-4': 'pencil-2',
	'2': 'archive',
	'-2': 'trash',
	'u': 'question-2'
};

var fc_statehandler = function(options)
{
	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fc_base_uri;

	this.options = {
		id: '',
		script_url: live_site + '/index.php?option=com_flexicontent&format=raw',
		task: '',
		state: '',
		font_icons: true,
		refresh_on_success: false
	};
	
	if( typeof options != 'undefined') for (var key in options)
	{
		this.options[key] = options[key];  //window.console.log(key, options[key]);
	};


	// *** AJAX request to set new item state
	this.setState = function(state, id)
	{
		var row = jQuery('#row' + id);
		var toggler = row.closest('.statetoggler');
		var options = this.options;

		row.empty().addClass('ajax-loader');

		jQuery.ajax({
			url: this.options.script_url + '&task=' + this.options.task + '&id=' + id + '&state=' + state,
			dataType: 'json',
			data: {
				lang: (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '')
			},
			success: function( data )
			{
				row.removeClass('ajax-loader').html(data.html);
				toggler.attr('data-original-title', data.title);

				if (typeof data.error != 'undefined')
				{
					jQuery('#system-message-container').html(data.error);
				}
				else
				{
					if (options.refresh_on_success)
					{
						window.document.body.innerHTML = '<span class="fc_loading_msg">Reloading ... please wait</span>';
						window.location.reload(false);  //window.location.replace(window.location.href);
					}
				}
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	};


	// *** Toggle drop down selector display, and populate available options if this is not already done
	this.toggleSelector = function(el)
	{
		if (!fc_statehandler_singleton) return;

		var toggler = jQuery(el);
		var ops = toggler.find('.options');

		var fc_state_titles = typeof _fc_state_titles != 'undefined' ? _fc_state_titles : {
			 '1': 'FLEXI_PUBLISH_THIS_ITEM',
			'-5': 'FLEXI_SET_STATE_AS_IN_PROGRESS',
			 '0': 'FLEXI_UNPUBLISH_THIS_ITEM',
			'-3': 'FLEXI_SET_STATE_AS_PENDING',
			 '-4': 'FLEXI_SET_STATE_AS_TO_WRITE',
				'2': 'FLEXI_ARCHIVE_THIS_ITEM',
			'-2': 'FLEXI_TRASH_THIS_ITEM',
			 'u': 'FLEXI_UNKNOWN'
		};

		if ( ops.is(':hidden') )
		{
			this.active_selector_box = null;

			toggler.addClass('active');
			toggler.css('z-index', 100); // should 3 or more

			if (ops.children().length == 0)
			{
				var html = ''; //'<div>' + Joomla.JText._('FLEXI_ACTION') + '</div>';

				var iid    = ops.data('id');
				var states = ops.data('st');
				var titles = ops.data('tt');

				jQuery(states).each(function(index, item)
				{
					var action_text = (titles && !! titles[item.i]) ? (Joomla.JText._('FLEXI_SET_STATE_TO') + ' ' + titles[item.i]) : Joomla.JText._(fc_state_titles[item.i]);

					html += fc_statehandler_singleton.options.font_icons
						? '<span onclick="fc_statehandler_singleton.setState(\'' + item.i + '\', \'' + iid + '\')"><span class="icon-' + fc_state_icons[item.i] + '"></span>' + action_text + '</span>'
						: '<span onclick="fc_statehandler_singleton.setState(\'' + item.i + '\', \'' + iid + '\')"><img src="' + fc_statehandler_singleton.options.img_path + fc_state_imgs[item.i] + '"/>' + action_text + '</span>';
				});
				jQuery(html).appendTo(ops);
			}
			this.active_selector_box = toggler;
		}
		else
		{
			this.active_selector_box = null;

			setTimeout(function(){
				toggler.removeClass('active');
				toggler.css('z-index', '');
			}, 200);
		}
		ops.slideToggle(200);
	};


	// *** Detect click outside container and close any open selector
	this.closeSelector = function(event)
	{
		if (!this.active_selector_box) return;  // no open container
		if (this.active_selector_box.is(event.target)) return;  // if target of the click is the container
		if (this.active_selector_box.has(event.target).length !== 0) return; // if target of click is a descendant of the container

		this.active_selector_box.click();
		this.active_selector_box = null;
	};
};


var fc_statehandler_singleton = false;

jQuery(document).mouseup(function(event)
{
	if (fc_statehandler_singleton) fc_statehandler_singleton.closeSelector(event);
});
