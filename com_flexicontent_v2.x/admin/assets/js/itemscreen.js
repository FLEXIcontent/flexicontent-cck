var itemscreen = new Class(
{
	options:  {
		id: "",
		script_url: "index.php?option=com_flexicontent&controller=items&tmpl=component",
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
		if(this.options.id>0) {
			new Request.HTML({
				url: this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id,
				method: 'get',
				update: $(this.name),
				evalScripts: false
			}).send();
		}else{
			$(this.name).set('html', '0');
		}
	},

	addtag: function( cid, tagname, url ) {
		new Request.HTML({
			url: url+'&cid='+cid,
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
	},

	reseter: function( task, id, div, url ) {
		new Request.HTML({
			url: url+'&format=raw&task='+task+'&id='+id,
			method: 'get',
			update: $(div),
			evalScripts: false
		}).send();
	}
});

itemscreen.implement( new Options, new Events );
