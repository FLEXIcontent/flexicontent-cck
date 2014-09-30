var itemscreen = new Class(
{
	options:  {
		id: "",
		script_url: "index.php?option=com_flexicontent&controller=items&format=raw",
		task: ""
	},

	initialize: function( name, options ) {
		this.setOptions( options );
		this.name = name;
	},  

	fetchscreen: function( name, options )  
	{
		var doname = this.name;
		if(typeof name!="undefined") {
			doname = name;
		}
		var dooptions = {
			method: 'get',
			update: doname,
			evalScripts: false
		};
		if(typeof options!="undefined") {
			dooptions = options;
		}
		
		var loader_html = '<p class="qf_centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>';
		var url_to_load = this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id;
		
		if (MooTools.version>='1.2.4') {
			$(doname).set('html', loader_html);
			if(this.options.id>0) {
				new Request.HTML({
					url: url_to_load,
					method: 'get',
					update: $(this.name),
					evalScripts: false
				}).send();
			} else {
				$(this.name).set('html', '0');
			}
		} else {
			$(doname).setHTML(loader_html);
			var ajax = new Ajax(url_to_load, dooptions);
			ajax.request.delay(300, ajax);
		}
		
	},

	addtag: function( cid, tagname, url )
	{
		var url = url+'&cid='+cid;
		if (MooTools.version>='1.2.4') {
			new Request.HTML({
				url: url,
				method: 'get',
				data : {'name' : tagname },
				evalScripts: false,
		    onSuccess: function(responseText){
					myvar = responseText[0].wholeText.split("|");
					if( ((typeof myvar[0])!="undefined") && (typeof myvar[1]!="undefined") ) {
						addToList(myvar[0], myvar[1]);
					}
		    },
		    onFailure: function(){
		    	alert('Failed to add tag');
				}
			}).send();
		} else {
			var tagajax = new Ajax(url, {
				method: 'get',
				data : Object.toQueryString({'name' : tagname }),
				evalScripts: false,
				onComplete:function (html) {
					myvar = html.split("|");
					if( ((typeof myvar[0])!="undefined") && (typeof myvar[1]!="undefined") ) {
						addToList(myvar[0], myvar[1]);
					}
				}
			});
			tagajax.request();
		}
	},

	reseter: function( task, id, div, url )
	{
		var doname = div;
		var loader_html = '<p class="qf_centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>';
	  var url = url+'&format=raw&task='+task+'&id='+id;
		if (MooTools.version>='1.2.4') {
			$(doname).set('html', loader_html);
			new Request.HTML({
				url: url,
				method: 'get',
				update: $(div),
				evalScripts: false
			}).send();
		} else {
			$(doname).setHTML(loader_html);
			var resetajax = new Ajax(url, {
				method: 'get',
				update: $(div),
				evalScripts: false
			});
			resetajax.request();
		}
	}
});

itemscreen.implement( new Options, new Events );