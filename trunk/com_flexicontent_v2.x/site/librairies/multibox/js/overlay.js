
/**************************************************************

	Script		: Overlay
	Version		: 1.2
	Authors		: Samuel birch
	Desc		: Covers the window with a semi-transparent layer.
	Licence		: Open Source MIT Licence

**************************************************************/

var Overlay = new Class({
	
	getOptions: function(){
		return {
			colour: '#000',
			opacity: 0.7,
			zIndex: 999991,
			container: document.body,
			onClick: Class.empty
		};
	},

	initialize: function(options){
		this.setOptions(this.getOptions(), options);
		
		this.options.container = $(this.options.container);
		
		this.container = new Element('div').setProperty('id', 'OverlayContainer').setStyles({
			position: 'absolute',
			left: '0px',
			top: '0px',
			width: '99.6%',
			zIndex: this.options.zIndex
		}).injectInside(this.options.container);
		
		this.iframe = new Element('iframe').setProperties({
			'id': 'OverlayIframe',
			'name': 'OverlayIframe',
			'src': 'javascript:void(0);',
			'frameborder': 1,
			'scrolling': 'no'
		}).setStyles({
			'position': 'absolute',
			'top': 0,
			'left': 0,
			'width': '100%',
			'height': '100%',
			'filter': 'progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=0)',
			'opacity': 0,
			'zIndex': 999991
		}).injectInside(this.container);
		
		this.overlay = new Element('div').setProperty('id', 'Overlay').setStyles({
			position: 'absolute',
			left: '0px',
			top: '0px',
			width: '100%',
			height: '100%',
			zIndex: 999992,
			backgroundColor: this.options.colour
		}).injectInside(this.container);
		
		this.container.addEvent('click', function(){
			this.options.onClick();
		}.bind(this));
		
		this.fade = new Fx.Style(this.container, 'opacity').set(0);
		this.position();
		
		window.addEvent('resize', this.position.bind(this));
	},
	
	position: function(){ 
		if(this.options.container == document.body){ 
			var h = window.getScrollHeight()+'px'; 
			this.container.setStyles({top: '0px', height: h}); 
		}else{ 
			var myCoords = this.options.container.getCoordinates(); 
			this.container.setStyles({
				top: myCoords.top+'px', 
				height: myCoords.height+'px', 
				left: myCoords.left+'px', 
				width: myCoords.width+'px'
			}); 
		} 
	},
	
	show: function(){
		this.fade.start(0,this.options.opacity);
	},
	
	hide: function(){
		this.fade.start(this.options.opacity,0);
	}
	
});
Overlay.implement(new Options);

/*************************************************************/
