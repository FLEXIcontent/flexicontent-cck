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
		$(doname).set('html', '<p class="qf_centerimg"><img src="components/com_flexicontent/assets/images/ajax-loader.gif" align="center"></p>');
		var ajax = new Ajax(this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id, dooptions);
		ajax.request.delay(300, ajax);
	},

	addtag: function( cid, tagname, url )
	{
		var url = url+'&cid='+cid;
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
	},

	reseter: function( task, id, div, url ) {
	  var url = url+'&format=raw&task='+task+'&id='+id;
		//var url = 'index.php?option=com_flexicontent&controller=items&task=' + task + '&id=' + id + '&format=raw';

		var resetajax = new Ajax(url, {
			method: 'get',
			update: div,
			evalScripts: false
			});
		resetajax.request();
	}
});

itemscreen.implement( new Options, new Events );
