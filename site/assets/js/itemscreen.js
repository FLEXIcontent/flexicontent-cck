var itemscreen = new Class(
{
	options:  {
		id: '',
		script_url: 'index.php?option=com_flexicontent&controller=items&tmpl=component',
		task: ''
	},

	initialize: function( name, options )
	{
		this.setOptions( options );
		this.name = name;
	},  

	fetchscreen: function( name )  
	{
		var doname = typeof name != 'undefined' ? name : this.name;

		if (this.options.id <= 0)
		{
			jQuery('#'+doname).html('0');
			return;
		}

		jQuery('#'+doname).html('<p><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		jQuery.ajax({
			url: this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id,
			type: 'get',
			data: {},
			success: function (data)
			{
				jQuery('#'+doname).html(data);
			},
			error: function (xhr, ajaxOptions, thrownError)
			{
				jQuery('#'+doname).html('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	},

	addtag: function( cid, tagname, url )
	{
		jQuery.ajax({
			url: url+'&cid='+cid,
			type: 'get',
			data : {'name' : tagname },
			success: function (data)
	    {
				myvar = data.split("|");
				if( ((typeof myvar[0])!="undefined") && (typeof myvar[1]!="undefined") )
				{
					if (myvar[0]!='0')
						addToList(myvar[0], myvar[1]);
					else
						alert(myvar[1]);
				}
	    },
	    error: function (xhr, ajaxOptions, thrownError)
	    {
	    	alert('Failed to add tag'); //alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	},

	reseter: function( task, id, name, url )
	{
		var doname = typeof name != 'undefined' ? name : this.name;

		jQuery('#'+doname).html('<p><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		jQuery.ajax({
			url: url+'&format=raw&task='+task+'&id='+id,
			type: 'get',
			success: function (data)
			{
				jQuery('#'+doname).html(data);
			},
			error: function (xhr, ajaxOptions, thrownError)
			{
				jQuery('#'+doname).html('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	}
});

itemscreen.implement( new Options, new Events );
