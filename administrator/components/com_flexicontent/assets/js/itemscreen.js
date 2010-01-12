var itemscreen = new Class(  
{  
	options:  {
		id: "",
		script_url: "index.php?option=com_flexicontent&controller=items&format=raw",
		task: ""
},

initialize: function( name, options )  
{  
	this.setOptions( options );
	this.name = name;
},  

fetchscreen: function( name, options )  
{
	$(this.name).setHTML('<p class="qf_centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');

  	var ajax = new Ajax(this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id, {
    	method: 'get',
    	update: this.name,
    	evalScripts: false
  	});
  	ajax.request.delay(300, ajax);
},

addtag: function( tagname )
{
	var url = 'index.php?option=com_flexicontent&controller=tags&task=addtag&format=raw';

	var tagajax = new Ajax(url, {
		method: 'get',
		data : Object.toQueryString({'name' : tagname }),
		evalScripts: false
		});
	tagajax.request();
},

reseter: function( task, id, div )
{
	var url = 'index.php?option=com_flexicontent&controller=items&task=' + task + '&id=' + id + '&format=raw';

	var resetajax = new Ajax(url, {
		method: 'get',
		update: div,
		evalScripts: false
		});
	resetajax.request();
}

});

itemscreen.implement( new Options, new Events );