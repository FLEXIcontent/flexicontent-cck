/**************************************************************

	Script		: Overlay
	Version		: 2.0.4
	Authors		: Samuel Birch
	Desc		: Covers the window with a semi-transparent layer.
	Licence		: Open Source MIT Licence
	Modified	: Liam Smart (liam_smart@hotmail.com) - MooTools 1.2 upgrade

**************************************************************/

//start overlay class
var Overlay = new Class({
	
	//implements
	Implements: Options,
	
	//options
	options:{
		colour: '#000',//background color of overlay
		opacity: 0.7,//opacity of overlay
		zIndex: 100,//the z-index of the overlay (needs to lower than multiBox pop-up)
		onClick: new Class()//make sure new class is loaded
	},

	//initialization
	initialize: function(options){
		//set options
		this.setOptions(options);
		//start building overlay
		this.container = new Element('div', {
			'id': 'OverlayContainer',
			'styles': {
				position: 'absolute',
				left: 0,
				top: 0,
				width: '100%',
				visibility: 'hidden',
				overflow: 'hidden',
				zIndex: this.options.zIndex,
				opacity: 0
			}
		}).inject(this.options.container,'inside');
		
		this.iframe = new Element('iframe', {
			'id': 'OverlayIframe',
			'name': 'OverlayIframe',
			'src': 'javascript:void(0);',
			'frameborder': 0,
			'scrolling': 'no',
			'styles': {
				position: 'absolute',
				top: 0,
				left: 0,
				width: '100%',
				height: '100%',
				filter: 'progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=0)',
				opacity: 0,
				zIndex: 101
			}
		}).inject(this.container,'inside');
		
		this.overlay = new Element('div', {
			'id': 'Overlay',
			'styles': {
				position: 'absolute',
				left: 0,
				top: 0,
				width: '100%',
				height: '100%',
				zIndex: 102,
				backgroundColor: this.options.colour
			}
		}).inject(this.container,'inside');
		
		this.container.addEvent('click', function(){
			this.options.onClick();
		}.bind(this));
		
		this.fade = new Fx.Morph(this.container);
		this.position();
		//make sure overlay is resized when browser is
		window.addEvent('resize',this.position.bind(this));
	},
	
	position: function(){
		if(this.options.container == document.body){
			this.container.setStyles({
				height: window.getScrollSize().y,
				width:  window.getScrollSize().x
			});
		}else{
			var myCoords = this.options.container.getCoordinates();
			this.container.setStyles({
				top: myCoords.top,
				height: myCoords.height,
				left: myCoords.left,
				width: myCoords.width
			});
		};
	},
	
	show: function(){
		this.fade.start({
			visibility: 'visible',
			opacity: this.options.opacity
		}).chain(function() {
			visibility: 'hidden'
		});
	},
	
	hide: function(){
		this.fade.start({
			opacity: 0
		}).chain(function() {
			visibility: 'hidden'
		});
	}
});