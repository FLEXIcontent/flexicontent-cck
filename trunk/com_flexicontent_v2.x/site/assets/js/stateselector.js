var stateselector = {
	makeSlider: function(){
		this.sliders = $$('ul.statetoggler ul').map(function(ul){
			return new Fx.Slide(ul, {
				mode: 'vertical'
			}).hide();
		});

		$$('ul.statetoggler a.opener').each(function(lnk, index){
			lnk.addEvent('click', function(){
				this.sliders.each(function(slider, sliderIndex){
					if (sliderIndex == index) this.sliders[sliderIndex].toggle();
					else this.sliders[sliderIndex].slideOut();
				}, this);
			}.bind(this));
		}, this);
		
		$$('ul.statetoggler a.closer').each(function(lnk, index){
			lnk.addEvent('click', function(){
				this.sliders.each(function(slider, sliderIndex){
					this.sliders[sliderIndex].slideOut();
				}, this);
			}.bind(this));
		}, this);
	},

	init: function(){
		this.makeSlider();
	}
};

var processstate = new Class(  
{  
	options:  {
		id: "",
		script_url: "index.php?option=com_flexicontent&format=raw",
		task: "setitemstate",
		state: ""
	},

	initialize: function( name, options ) {  
		this.setOptions( options );
		this.name = name;
	},

	dostate: function( state, id ) {
		var url = this.options.script_url + "&task=" + this.options.task + "&id=" + id + "&state=" + state;
		if (MooTools.version>='1.2.4') {
			new Request.HTML({
				url: url,
				method: 'get',
				evalScripts: false,
				update: $('row' + id)
				//,onComplete: hider
			}).send();
		} else {
			var setstate = new Ajax(url, {
				method: 'get',
				evalScripts: false,
				update: 'row' + id
				//,onComplete: hider
			});
			setstate.request();
		}
		
		//function hider(response) {
		//	alert(response);
		//}
	}
});

processstate.implement( new Options, new Events );