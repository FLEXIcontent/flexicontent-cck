

/**************************************************************

	Script		: Background Slider
	Version		: 1.1
	Authors		: Samuel Birch
	Desc		: Slides a layer to a given elements position and dimensions.
	Licence		: Open Source MIT Licence

**************************************************************/

var BackgroundSlider = new Class({

	getOptions: function(){
		return {
			duration: 300,
			wait: 500,
			transition: Fx.Transitions.sineInOut,
			className: false,
			fixHeight: false,
			fixWidth: false,
			id: false,
			padding: {top:0,right:0,bottom:0,left:0},
			onClick: this.setStart,
			mouseOver: true
		};
	},

	initialize: function(elements, options){
		this.setOptions(this.getOptions(), options);
		
		this.elements = $$(elements);
		this.timer = 0;
		
		if(this.options.id){
			this.bg = $(this.options.id);
		}else{
			this.bg = new Element('div').setProperty('id','BgSlider_'+new Date().getTime()).injectInside(document.body);
			if(this.options.className){
				this.bg.addClass(this.options.className);	
			}
		}
		
		this.effects = new Fx.Styles(this.bg, {duration: this.options.duration, transition: this.options.transition});
		
		this.elements.each(function(el,i){
			if(this.options.mouseOver){
				el.addEvent('mouseover', this.move.bind(this,el));
				el.addEvent('mouseout', this.reset.bind(this));
			}
			el.addEvent('click', this.options.onClick.bind(this, el))
			if(el.hasClass('bgStart')){
				this.set(el);
			}
		},this);
		
		window.addEvent('resize',function(){
			this.move(this.startElement);
		}.bind(this));
		
	},
	
	setStart: function(el){
		this.startElement = el;
	},
	
	set: function(el){
		this.setStart(el);
		var pos = el.getCoordinates();
		
		if(this.options.id){
			this.options.padding.top = this.bg.getStyle('paddingTop').toInt();
			this.options.padding.right = this.bg.getStyle('paddingRight').toInt();
			this.options.padding.bottom = this.bg.getStyle('paddingBottom').toInt();
			this.options.padding.left = this.bg.getStyle('paddingLeft').toInt();
			this.bg.setStyle('padding','0px');
		}
		
		var obj = {};
		obj.top = (pos.top-this.options.padding.top)+'px';
		obj.left = (pos.left-this.options.padding.left)+'px';
		if(!this.options.fixHeight){obj.height = (pos.height+this.options.padding.top+this.options.padding.bottom)+'px'};
		if(!this.options.fixWidth){obj.width = (pos.width+this.options.padding.left+this.options.padding.right)+'px'};
		
		this.bg.setStyles(obj);
	},
	
	reset: function(){
		if(this.options.wait){
			this.timer = this.move.delay(this.options.wait, this, this.startElement);
		}
	},
	
	move: function(el){
		$clear(this.timer);
		var pos = el.getCoordinates();
		
		this.effects.stop();
		
		var obj = {};
		obj.top = pos.top-this.options.padding.top;
		obj.left = pos.left-this.options.padding.left;
		if(!this.options.fixHeight){obj.height = pos.height+this.options.padding.top+this.options.padding.bottom};
		if(!this.options.fixWidth){obj.width = pos.width+this.options.padding.left+this.options.padding.right};
		
		this.effects.start(obj);
		
	}

});
BackgroundSlider.implement(new Options);
BackgroundSlider.implement(new Events);

/*************************************************************/
