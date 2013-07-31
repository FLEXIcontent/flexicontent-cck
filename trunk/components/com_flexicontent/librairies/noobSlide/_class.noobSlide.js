/*
Author:
	luistar15, <leo020588 [at] gmail.com>
License:
	MIT-style license.
 
Class
	noobSlide (rev.03-11-10)

Arguments:
	options - see Options below

Options:
	box: dom element | required
	items: dom collection | required
	size: int | item size (px) | default: 240
	mode: string | 'horizontal', 'vertical' | default: 'horizontal'
	interval: int | for peridical | default: 5000
	buttons:{
		previous: single dom element OR dom collection| default: null
		next:  single dom element OR dom collection | default: null
		play:  single dom element OR dom collection | default: null
		playback:  single dom element OR dom collection | default: null
		stop:  single dom element OR dom collection | default: null
	}
	button_event: string | event type | default: 'click'
	handles: dom collection | default: null
	handle_event: string | event type| default: 'click'
	fxOptions: object | Fx.Style options | default: {duration:500,wait:false}
	autoPlay: boolean | default: false
	onWalk: event | pass arguments: currentItem, currentHandle | default: null
	startItem: int

Properties:
	box: dom element
	items: dom collection
	size: int
	mode: string
	interval: int
	buttons: object
	button_event: string
	handles: dom collection
	handle_event: string
	previousIndex: int
	nextIndex: int
	fx: Fx.style instance
	autoPlay: boolean
	onWalk: function
	
Methods:
	previous(manual): walk to previous item
		manual: bolean | default:false
	next(manual): walk to next item
		manual: bolean | default:false
	play (delay,direction,wait): auto walk items
		delay: int | required
		direction: string | "previous" or "next" | required
		wait: boolean | required
	stop(): sopt auto walk
	walk(item,manual): walk to item
		item: int | required
		manual: bolean | default:false
	addHandleButtons(handles):
		handles: dom collection | required
	addActionButtons(action,buttons):
		action: string | "previous", "next", "play", "playback", "stop" | required
		buttons: dom collection | required

*/
var noobSlide = new Class({

	initialize: function(params){
		this.items = params.items;
		this.mode = params.mode || 'horizontal';
		this.modes = {horizontal:['left','width'], vertical:['top','height']};
		this.size = params.size || 240;
		this.box = params.box.setStyle(this.modes[this.mode][1],(this.size*this.items.length)+'px');
		this.button_event = params.button_event || 'click';
		this.handle_event = params.handle_event || 'click';
		this.interval = params.interval || 5000;
		this.buttons = {previous: [], next: [], play: [], playback: [], stop: []};
		if(params.buttons){
			for(var action in params.buttons){
				this.addActionButtons(action, $type(params.buttons[action])=='array' ? params.buttons[action] : [params.buttons[action]]);
			}
		}
		this.handles = params.handles || null;
		if(this.handles){
			this.addHandleButtons(this.handles);
		}
		this.fx = new Fx.Style(this.box,this.modes[this.mode][0],params.fxOptions||{duration:500,wait:false});
		this.onWalk = params.onWalk || null;
		this.currentIndex = params.startItem || 0;
		this.previousIndex = null;
		this.nextIndex = null;
		this.autoPlay = params.autoPlay || false;
		this._auto = null;
		this.box.setStyle(this.modes[this.mode][0],(-this.currentIndex*this.size)+'px');
		if(params.autoPlay) this.play(this.interval,'next',true);
	},

	previous: function(manual){
		this.currentIndex += this.currentIndex>0 ? -1 : this.items.length-1;
		this.walk(null,manual);
	},

	next: function(manual){
		this.currentIndex += this.currentIndex<this.items.length-1 ? 1 : 1-this.items.length;
		this.walk(null,manual);
	},

	play: function(delay,direction,wait){
		this.stop();
		if(!wait){
			this[direction](false);
		}
		this._auto = this[direction].periodical(delay,this,false);
	},

	stop: function(){
		$clear(this._auto);
	},

	walk: function(item,manual){
		if($defined(item)){
			if(item==this.currentIndex) return;
			this.currentIndex=item;
		}
		this.previousIndex = this.currentIndex + (this.currentIndex>0 ? -1 : this.items.length-1);
		this.nextIndex = this.currentIndex + (this.currentIndex<this.items.length-1 ? 1 : 1-this.items.length);
		if(manual){ this.stop(); }
		this.fx.start(-this.currentIndex*this.size);
		if(this.onWalk){ this.onWalk(this.items[this.currentIndex],(this.handles?this.handles[this.currentIndex]:null)); }
		if(manual && this.autoPlay){ this.play(this.interval,'next',true); }
	},
	
	addHandleButtons: function(handles){
		for(var i=0;i<handles.length;i++){
			handles[i].addEvent(this.handle_event,this.walk.pass([i,true],this));
		}
	},

	addActionButtons: function(action,buttons){
		for(var i=0; i<buttons.length; i++){
			switch(action){
				case 'previous': buttons[i].addEvent(this.button_event,this.previous.pass([true],this)); break;
				case 'next': buttons[i].addEvent(this.button_event,this.next.pass([true],this)); break;
				case 'play': buttons[i].addEvent(this.button_event,this.play.pass([this.interval,'next',false],this)); break;
				case 'playback': buttons[i].addEvent(this.button_event,this.play.pass([this.interval,'previous',false],this)); break;
				case 'stop': buttons[i].addEvent(this.button_event,this.stop.create({bind:this})); break;
			}
			this.buttons[action].push(buttons[i]);
		}
	}
	
});