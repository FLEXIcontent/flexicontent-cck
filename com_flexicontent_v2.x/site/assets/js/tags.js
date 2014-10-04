var tagajax = new Class(  
{  
	options:
	{
		id: "",
		script_url: "index.php?option=com_flexicontent&format=raw",
		task: ""
	},

	initialize: function( name, options ) {  
		this.setOptions( options );
		this.name = name;
	},  

	fetchscreen: function( name, options ) {
		$(this.name).setHTML('<p class="qf_centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		
  		var ajax = new Ajax("index.php?option=com_flexicontent&format=raw&task=getajaxtags&id=" + this.options.id, {
    		method: 'get',
			update: this.name,
    		evalScripts: false
  		});
  		ajax.request.delay(3000, ajax);
	},

	addtag: function( tagname ) {
		var url = 'index.php?option=com_flexicontent&controller=items&task=addtag&format=raw';

		var settag = new Ajax(url, {
			method: 'get',
			data : Object.toQueryString({'name' : tagname }),
			evalScripts: false
		});
		settag.request();
	}
});

tagajax.implement( new Options, new Events );