/*
Author:
	luistar15, <leo020588 [at] gmail.com>
	Rainer Ilgen <raineri@gmx.net> Extention for Fading
	ggppdk:
		- Horizontal responsive behavior,
		- Horizontal/Vertical slider improvement (only slide if new current item is not already visible)
		- Horizontal/Vertical fader improvement (only fade if new current item is not already visible)
	
License:
	MIT License
 
Class
	noobSlide (rev.03-11-10)
	noobSlide Extention for Fading (rev. 23-10-08)

Arguments:
	Parameters - see Parameters below

Parameters:
	marginR: int | item right margin | default: 0
	mask: dom element | optional
	box: dom element | required
	items: dom collection | required
	size: int | item size (px) | default: 240
	mode: string | 'horizontal', 'vertical' | default: 'horizontal'
	fade: boolean | default: false
	addButtons:{
		previous: single dom element OR dom collection| default: null
		next:  single dom element OR dom collection | default: null
		play:  single dom element OR dom collection | default: null
		playback:  single dom element OR dom collection | default: null
		stop:  single dom element OR dom collection | default: null
	}
	button_event: string | event type | default: 'click'
	handles: dom collection | default: null
	page_handles: dom collection | default: null
	handle_event: string | event type| default: 'click'
	fxOptions: object | Fx.Tween options | default: {duration:500,wait:false}
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
	fade: boolean
	buttons: object
	button_event: string
	handles: dom collection
	page_handles: dom collection
	handle_event: string
	previousIndex: int
	nextIndex: int
	fx: Fx.Tween instance
	interval: int
	autoPlay: boolean
	onWalk: function
	minVisible: int
	
Methods:
	previous(manual): walk to previous item
		manual: bolean | default:false
	next(manual): walk to next item
		manual: bolean | default:false
	play (interval,direction,wait): auto walk items
		interval: int | required
		direction: string | "previous" or "next" | required
		wait: boolean | required
	stop(): stop auto walk
	walk(item,manual,noFx): walk to item
		item: int | required
		manual: bolean | default:false
		noFx: boolean | default:false
	addHandleButtons(handles):
		handles: dom collection | required
	addPageHandleButtons(page_handles):
		page_handles: dom collection | null
	addActionButtons(action,buttons):
		action: string | "previous", "next", "play", "playback", "stop" | required
		buttons: dom collection | required

Requires:
	mootools 1.2 core
*/
var noobSlide = new Class({

	initialize: function(params){
		this.minVisible = params.minVisible||1;
		this.marginR = params.marginR || 0;
		this.items = params.items;
		this.mode = params.mode || 'horizontal';
		this.fade = params.fade || false;
		this.fxOptions = params.fxOptions;
		this.modes = {horizontal:['left','width'], vertical:['top','height']};
		this.size = params.size || 240;
		this.mask = params.mask || null;
		this.box = params.box.setStyle(this.modes[this.mode][1],(this.size*this.items.length)+'px');
		this.button_event = params.button_event || 'click';
		this.handle_event = params.handle_event || 'click';
		this.onWalk = params.onWalk || null;
		this.currentIndex = 0;
		this.lastIndex = null;
		this.previousIndex = null;
		this.nextIndex = null;
		this.interval = params.interval || 5000;
		this.autoPlay = params.autoPlay || false;
		this._play = null;
		this.handles = params.handles || null;
		this.page_handles = params.page_handles || null;
		if(this.handles){
			this.addHandleButtons(this.handles);
		}
		if(this.page_handles){
			this.addPageHandleButtons(this.page_handles);
		}
		this.buttons = {
			previous: [],
			next: [],
			play: [],
			playback: [],
			stop: []
		};
		if(params.addButtons){
			for(var action in params.addButtons){
				this.addActionButtons(action, typeOf(params.addButtons[action])=='array' ? params.addButtons[action] : [params.addButtons[action]]);
			}
		}
		if(this.fade)
		{
			//Prepare Fading
			this.orderItems();
			this.fading((params.startItem||0),true,true);
		}
		else
		{
			//original Sliding
			this.fx = new Fx.Tween(this.box,Object.append((this.fxOptions||{duration:500,wait:false}),{property:this.modes[this.mode][0]}));
			this.walk((params.startItem||0),true,true);
		}
	},

	//new function for Ordering the Items for Fading
	orderItems: function() {
		for(i=0;i<this.items.length;i++)
		{
			//this.items[i].setStyle('position', 'absolute');
			//this.items[i].setStyle('left', '0px');
			//this.items[i].setStyle('z-index', i+1);
			//this.items[i].fade('out');
		}
	},

	addHandleButtons: function(handles){
		for(var i=0;i<handles.length;i++){
			if(this.fade)
			{
				handles[i].addEvent(this.handle_event,this.fading.pass([i,true],this));
			}
			else
			{
				handles[i].addEvent(this.handle_event,this.walk.pass([i,true],this));
			}
		}
	},

	addPageHandleButtons: function(page_handles){
		/*for(var i=0;i<page_handles.length;i++){
			if(this.fade)
			{
				page_handles[i].addEvent(this.handle_event,this.fading.pass([i,true],this));
			}
			else
			{
				page_handles[i].addEvent(this.handle_event,this.walk.pass([i,true],this));
			}
		}*/
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
		if(this.fade)
		{
			this.fading((this.currentIndex>0 ? this.currentIndex-1 : this.items.length-1),manual);
		}
		else
		{
			this.walk((this.currentIndex>0 ? this.currentIndex-1 : this.items.length-1),manual);
		}
	},

	next: function(manual){
		if(this.fade)
		{
			this.fading((this.currentIndex<this.items.length-1 ? this.currentIndex+1 : 0),manual);
		}
		else
		{
			this.walk((this.currentIndex<this.items.length-1 ? this.currentIndex+1 : 0),manual);
		}
	},

	play: function(interval,direction,wait){
		this.stop();
		if(!wait){
			this[direction](false);
		}
		this._play = this[direction].periodical(interval,this,[false]);
	},

	stop: function(){
		clearTimeout(this._play);
	},

	walk: function(item,manual,noFx){
		item = item < this.items.length ? item : this.items.length-1;
		if (item!=this.currentIndex)
		{
			if (this.mask && this.mode=='horizontal') {
				minVisible = (this.mask.clientWidth+this.marginR) / this.size;
				this.minVisible = parseInt(minVisible);
			}
			if (!this.minVisible) this.minVisible = 1;  // if detection fails
			
			this.lastIndex = this.lastIndex || 0;
			this.currentIndex = item;
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
	},
	
	//Fading
	fading: function(item,manual,noFx){
		item = item < this.items.length ? item : this.items.length-1;
		if (item!=this.currentIndex)
		{
			if (this.mask && this.mode=='horizontal') {
				minVisible = (this.mask.clientWidth+this.marginR) / this.size;
				this.minVisible = parseInt(minVisible);
			}
			if (!this.minVisible) this.minVisible = 1;  // if detection fails
			
			this.lastIndex = this.lastIndex || 0;
			this.currentIndex = item;
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
			offSetIndex = offSetIndex || 0;
			this.lastIndex = offSetIndex;
			
			if(manual){
				this.stop();
			}
			
			if (this.mask && this.mode=='horizontal') {
				this.mask.setStyle('min-height', '' + this.mask.clientHeight + 'px');
			}
			for(i=0;i<this.items.length;i++) {
				this.items[i].set('tween', this.fxOptions).fade(noFx ? 'hide' : 'out');
			}
			limit = noFx ? this.items.length - offSetIndex : this.minVisible;
			for(i=0;i<limit;i++) {
				if (offSetIndex+i >= this.items.length) break;
				this.items[offSetIndex+i].setStyle('position', 'absolute');
				this.mode=='horizontal' ?
					this.items[offSetIndex+i].setStyle('left', '' + (i*this.size) + 'px') :
					this.items[offSetIndex+i].setStyle('top', '' + (i*this.size) + 'px');
				this.items[offSetIndex+i].set('tween', this.fxOptions).fade(noFx ? 'show' : 'in');
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