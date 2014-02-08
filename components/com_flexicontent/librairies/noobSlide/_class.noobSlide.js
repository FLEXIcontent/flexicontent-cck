/*
Author:
	luistar15, <leo020588 [at] gmail.com>
	ggppdk:
		- Horizontal responsive behavior,
		- Horizontal/Vertical slider improvement (only slide if new current item is not already visible)
	
License:
	MIT License
 
Class
	noobSlide (rev.03-11-10)

Arguments:
	Options - see Options below

Options:
	marginR: int | item right margin | default: 0
	mask: dom element | optional
	box: dom element | required
	items: dom collection | required
	size: int | item size (px) | default: 240
	mode: string | 'horizontal', 'vertical' | default: 'horizontal'
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
	interval: int | for periodical | default: 5000
	autoPlay: boolean | default: false
	onWalk: event | pass arguments: currentItem, currentHandle | default: null
	startItem: int | default: 0
	minVisible: int | default: 1

Properties:
	marginR: int
	mask: dom element
	box: dom element
	items: dom collection
	size: int
	mode: string
	buttons: object
	button_event: string
	handles: dom collection
	handle_event: string
	previousIndex: int
	nextIndex: int
	fx: Fx.style instance
	interval: int
	autoPlay: boolean
	onWalk: function
	minVisible: int
	
Methods:
	previous(manual): walk to previous item
		manual: bolean | default:false
	next(manual): walk to next item
		manual: bolean | default:false
	play (delay,direction,wait): auto walk items
		delay: int | required
		direction: string | "previous" or "next" | required
		wait: boolean | required
	stop(): stop auto walk
	walk(item,manual): walk to item
		item: int | required
		manual: bolean | default:false
	addHandleButtons(handles):
		handles: dom collection | required
	addActionButtons(action,buttons):
		action: string | "previous", "next", "play", "playback", "stop" | required
		buttons: dom collection | required

Requires:
	mootools 1.1 core
*/
var noobSlide = new Class({

	initialize: function(params){
		this.minVisible = params.minVisible||1;
		this.marginR = params.marginR || 0;
		this.items = params.items;
		this.mode = params.mode || 'horizontal';
		this.modes = {horizontal:['left','width'], vertical:['top','height']};
		this.size = params.size || 240;
		this.mask = params.mask || null;
		this.box = params.box.setStyle(this.modes[this.mode][1],(this.size*this.items.length)+'px');
		this.button_event = params.button_event || 'click';
		this.handle_event = params.handle_event || 'click';
		this.onWalk = params.onWalk || null;
		this.currentIndex = null;
		this.lastIndex = null;
		this.previousIndex = null;
		this.nextIndex = null;
		this.interval = params.interval || 5000;
		this.autoPlay = params.autoPlay || false;
		this._auto = null;
		this.handles = params.handles || null;
		if(this.handles){
			this.addHandleButtons(this.handles);
		}
		this.buttons = {
			previous: [],
			next: [],
			play: [],
			playback: [],
			stop: []
		};
		if(params.buttons){
			for(var action in params.buttons){
				this.addActionButtons(action, $type(params.buttons[action])=='array' ? params.buttons[action] : [params.buttons[action]]);
			}
		}
		this.fx = new Fx.Style(this.box,this.modes[this.mode][0],params.fxOptions||{duration:500,wait:false});
		this.box.setStyle(this.modes[this.mode][0],(this.size*-(params.startItem||0))+'px');
		if(params.autoPlay) this.play(this.interval,'next',true);
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
	},

	previous: function(manual){
			this.walk((this.currentIndex>0 ? this.currentIndex-1 : this.items.length-1),manual);
	},

	next: function(manual){
			this.walk((this.currentIndex<this.items.length-1 ? this.currentIndex+1 : 0),manual);
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
		if (1) //item!=this.currentIndex) // dirty workaround to allow re-execution (this function fails when previous walk has not finished) (mootools v1.1)
		{
			if (this.mask && this.mode=='horizontal') {
				minVisible = (this.mask.clientWidth+this.marginR) / this.size;
				this.minVisible = parseInt(minVisible);
			}
			
			this.currentIndex=item;
			this.previousIndex = this.currentIndex + (this.currentIndex>0 ? -1 : this.items.length-1);
			this.nextIndex = this.currentIndex + (this.currentIndex<this.items.length-1 ? 1 : 1-this.items.length);
			
			if (this.currentIndex >= this.lastIndex && this.currentIndex <= this.lastIndex + this.minVisible - 1) {
				var offSetIndex = this.lastIndex;  // Already viewable
			} else if (this.currentIndex + this.minVisible >= this.items.length) {
				var offSetIndex = this.lastIndex < this.items.length - this.minVisible ?
					this.currentIndex - (this.minVisible-1) : // Position at rightmost of viewport
					this.items.length - this.minVisible;     // Do not position leftmost of viewport, instead position to view all last rightmost items
			} else {
				var offSetIndex = this.lastIndex < this.currentIndex ?
					this.currentIndex - (this.minVisible-1) :  // Position at rightmost of viewport
					this.currentIndex;    // Position at leftmost of viewport
			}
			this.lastIndex = offSetIndex;
			
			var noFx = false; // not supported ...in v1.1
			if(manual){
				this.stop();
			}
			if(noFx){
				this.fx.cancel().set((this.size*-offSetIndex)+'px');
			}else{
				this.fx.start(this.size*-offSetIndex);
			}
			if(manual && this.autoPlay){
				this.play(this.interval,'next',true);
			}
			if(this.onWalk){
				this.onWalk((this.items[this.currentIndex] || null), (this.handles && this.handles[this.currentIndex] ? this.handles[this.currentIndex] : null));
			}
		}
	}
	
});