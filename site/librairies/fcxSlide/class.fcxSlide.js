/*
Author:	ggppdk

Requires: jQuery 1.7 or later

License: GPL3 (for the scroller), the easing functions have BSD License, maybe move to separate file

Description:
	a jQuery scroller written almost from scratch (easing functions by George McGinley, see License below)
	with advanced responsive design and complex walk calculation for items, item handles and page handles
	
		- jQuery based
		
		- Dynamic size (width) calculation for both items and pages
		
		- Horizontal responsive behaviour (that detects container width change)
		
		- Supports
			a. item handles
			b. page handles (Pagination support !)
			c. action handles
		
		- Walk either items OR pages or both (!)
		
		- Walking methods (for both items and pages) based on jQuery effects:
			a. scroll
			b. fade
			c. slide
			d. fade-slide
			
		- jQuery EASING support for all (?) WALK methods
		
		- Handle transition of already visible items intuitively (move them instead of sliding or fading them)
		
		- Walk carousel ONLY IF needed (if target item or page not already within view-port), otherwise just "activate" next/previous item
			
		- Auto-scroll for item handles container
			a. auto-scroll at edges
			b. auto-scroll on page change
			c. support for mSCB jQuery scroller
			
		- TODO: improve above description
		- TODO: write more


Class Name:
	FCXscroller

Parameters:
	
	mode: string | 'horizontal', 'vertical' | default: 'horizontal'
	transition: string | 'scroll', 'fade', 'slide', 'fade-slide' | default: 'scroll'
	fxOptions: object | jQuery.Animate options | default: { duration: 400, easing: 'linear' }
	transition_visible_duration: int | default: 100
	
	items: dom collection | required
	items_box: dom element | required
	items_mask: dom element | required
	items_per_page: int | default: 1
	item_size: int | item size (px) | default: 240
	item_hdir_marginr: int | item right margin for horizontal scroll | default: 0
	
	page_handles: dom collection | default: null
	page_handle_event: string | event type| default: 'click'
	
	item_handles: dom collection | default: null
	item_handles_box: dom element | optional
	item_handle_event: string | event type| default: 'click'
	item_handle_duration: int | default: 400
	
	action_handles:{
		previous: single dom element OR dom collection| default: null
		next:  single dom element OR dom collection | default: null
		previous_page: single dom element OR dom collection| default: null
		next_page:  single dom element OR dom collection | default: null
		play:  single dom element OR dom collection | default: null
		playback:  single dom element OR dom collection | default: null
		stop:  single dom element OR dom collection | default: null
	}
	action_handle_event: string | event type | default: 'click'
	
	edgeWrap: boolean | default: true
	autoPlay: boolean | default: false
	playInterval: int | for periodical | default: 5000
	playMethod: string | 'page' or 'item' | default: 'page'
	
	onWalk: event | pass arguments: currentItem, currentHandle | default: null
	startItem: int | default: 0


Properties:
	
	mode: string
	transition: string
	fxOptions: object
	transition_visible_duration: int   // for moving already visible items
	
	items: dom collection
	items_box: dom element
	items_mask: dom element
	items_per_page: int
	item_size: int
	item_hdir_marginr: int
	
	page_handles: dom collection
	page_handle_event: string
	
	item_handles: dom collection
	item_handles_box: dom element
	item_handle_event: string
	item_handle_duration: int
	
	action_handles: object
	action_handle_event: string
	
	lastPageCount: int
	currentIndex: int    // currently focused item
	lastIndex: int       // previously focused item
	currentPage: int
	
	edgeWrap: boolean
	autoPlay: boolean
	playInterval: int
	playMethod: string
	onWalk: function
	

Methods:

	bindItemHandles(item_handles):
		item_handles: dom collection | required
	bindPageHandles(page_handles):
		page_handles: dom collection | null
	bindActionHandles(action_handles):
		action_handles: dom collection | required

	previous_page(manual): walk to previous page
		manual: bolean | default:false
	next_page(manual): walk to next page
		manual: bolean | default:false

	previous(manual): walk to previous item
		manual: bolean | default:false
	next(manual): walk to next item
		manual: bolean | default:false

	play (interval,direction,wait): start auto walk items
		interval: int | required
		direction: string | "previous" or "next" (!do not append '_page', since this is automatically inside the function) | required
		wait: boolean | required
	stop(): stop auto walk

	walk(item,manual,noFx, force): walk to item (or to page if negative)
		item: int | required
		manual: bolean | default:false
		noFx: boolean | default:false

*/
var fcxSlide = new Class({

	initialize: function(params)
	{
		this.mode = params.mode || 'horizontal';
		this.transition = params.transition || 'scroll';
		this.fxOptions = params.fxOptions || { duration: 400, easing: 'linear' };
		this.transition_visible_duration = params.transition_visible_duration || 100;
		
		this.items = params.items;
		this.items_box = params.items_box;
		this.items_mask = params.items_mask;
		this.items_per_page = params.items_per_page || 1;
		this.item_size = params.item_size || 240;
		this.item_hdir_marginr = params.item_hdir_marginr || 0;
		
		this.page_handles = params.page_handles || null;
		this.page_handle_event = params.page_handle_event || 'click';
		
		this.item_handles = params.item_handles || null;
		this.item_handles_box = params.item_handles_box || false;
		this.item_handle_event = params.item_handle_event || 'click';
		this.item_handle_duration = params.item_handle_duration || 400;
		
		this.action_handle_event = params.action_handle_event || 'click';
		//params.action_handles
		
		this.onWalk = params.onWalk || null;
		
		this.lastPageCount = 0;
		this.currentIndex = 0;
		this.lastIndex = null;
		this.currentPage = 0;
		
		this.edgeWrap     = params.edgeWrap || false;
		this.autoPlay     = params.autoPlay || false;
		this.playInterval = params.playInterval || 5000;
		this.playMethod   = params.playMethod || 'page';
		
		this.autoPlay_timer = null;
		
		// Set appropriate size for the items box (although this should not be needed if box has appropriate CSS)
		this.mode_to_css = {horizontal:['left','width'], vertical:['top','height']};
		jQuery(this.items_box).css(this.mode_to_css[this.mode][1],(this.item_size*this.items.length)+'px');
		
		if (this.item_handles)  this.bindItemHandles(this.item_handles);
		if (this.page_handles)  this.bindPageHandles(this.page_handles);
		if (params.action_handles) {
			this.action_handles = { previous: [], next: [], previous_page: [], next_page: [], play: [], playback: [], stop: [] };
			this.bindActionHandles(params.action_handles);
		}
		
		this.walk((params.startItem||0),true,true,true);
	},


	bindItemHandles: function(item_handles){
		var slider = this;
		for(var i=0;i<item_handles.length;i++){
			jQuery(item_handles[i]).on(
				this.item_handle_event,
				{ key : i },
				function(param) {
					slider.walk(param.data.key,true,false);
				}
			);
		}
	},

	bindPageHandles: function(page_handles){
		var slider = this;
		for(var i=0;i<page_handles.length;i++){
			jQuery(page_handles[i]).on(
				this.page_handle_event,
				{ key : -i },
				function(param) {
					slider.walk(param.data.key,true,false);
				}
			);
		}
	},

	bindActionHandles: function(_action_handles){
		var slider = this;
		for (var action in _action_handles)
		{
			// Detect if current handle element is not a array of elements to be assigned the same action
			var action_set = (typeof(_action_handles[action])=='array')  ?  _action_handles[action]  :  [_action_handles[action]];
			
			// This handles an array but usually this action will be assigned to a single item in array, but some action maybe assigned to more than one control handle, e.g. 'next_page' or 'next'
			for(var i=0; i<action_set.length; i++){
				switch(action)
				{
					case 'stop':     action_set[i].on(this.action_handle_event, { }, function(param) { slider.stop(); } ); break;
					case 'playback': action_set[i].on(this.action_handle_event, { }, function(param) { slider.play(slider.playInterval,'previous',false); } ); break;
					case 'play':     action_set[i].on(this.action_handle_event, { }, function(param) { slider.play(slider.playInterval,'next',false); } ); break;
					
					case 'previous_page': action_set[i].on(this.action_handle_event, { }, function(param) { slider.previous_page(true); } ); break;
					case 'next_page':     action_set[i].on(this.action_handle_event, { }, function(param) { slider.next_page(true); } ); break;
					
					case 'previous': action_set[i].on(this.action_handle_event, { }, function(param) { slider.previous(true); } ); break;
					case 'next':     action_set[i].on(this.action_handle_event, { }, function(param) { slider.next(true); } ); break;
				}
				this.action_handles[action].push(action_set[i]);
			}
		}
	},
	
	
	previous_page: function(manual){
		this.walk('previous_page', manual, false);
	},
	
	next_page: function(manual){
		this.walk('next_page', manual, false);
	},
	
	
	previous: function(manual){
		this.walk((this.currentIndex>0 ? this.currentIndex-1 : (!this.edgeWrap ? 0 : this.items.length-1)), manual, false);
	},
	
	next: function(manual){
		this.walk((this.currentIndex<this.items.length-1 ? this.currentIndex+1 : (!this.edgeWrap ? this.items.length-1 : 0)), manual, false);
	},
	
	
	play: function(interval,direction,wait){
		this.stop();
		direction_func = direction + (this.playMethod=='page' ? '_page' : '' );
		if(!wait){
			this[direction_func](false);
		}
		var obj = this;
		this.autoPlay_timer = setInterval(
			function() {
				obj[direction_func].call(obj,[false])
			},
			interval
		);
	},
	
	stop: function(){
		clearTimeout(this.autoPlay_timer);
	},
	
	
	walk: function(item,manual,noFx, force)
	{
		/* Detect container width */
		var _items_per_page;
		if (this.items_mask && this.mode=='horizontal') {
			_items_per_page = (this.items_mask[0].clientWidth+this.item_hdir_marginr) / this.item_size;
			this.items_per_page = parseInt(_items_per_page);
		}
		if (!this.items_per_page) this.items_per_page = 1;  // if width detection fails
		
		
		/* Detect number of pages, current page and position of 1st item at last page */
		var page_count = Math.floor(this.items.length / this.items_per_page) + ((this.items.length % this.items_per_page) ? 1 : 0);
		var curr_page  = Math.floor(this.currentIndex / this.items_per_page);
		var last_page_item = page_count * this.items_per_page;
		
		/* Update page handles according to new page detection */
		if (this.page_handles && this.lastPageCount != page_count) {
			for(var i=0; i<page_count; i++){
				jQuery(this.page_handles[i]).css('display', 'inline-block');
			}
			for(var i=page_count; i<this.page_handles.length; i++){
				jQuery(this.page_handles[i]).css('display', 'none');
			}
		}
		this.lastPageCount = page_count;
		
		/* WALK item or full page */
		if (item=='next_page' || item=='previous_page') {
			var scrollPage = true;
			new_page = (item=='next_page') ? curr_page + 1  :  curr_page - 1;
		} else {
			var scrollPage = item < 0;
			if (scrollPage) new_page = -item;
		}
		
		if (scrollPage) {
			// WRAP around if page is out of limits
			new_page = new_page < page_count ? new_page : (!this.edgeWrap ? page_count - 1 : 0);
			new_page = new_page >= 0         ? new_page : (!this.edgeWrap ? 0 : page_count - 1);
			item = -new_page;
			
			// Decide item to show, checking item is within item limits, (we do this step, for both cases of WALKING item OR page)
			item = this.items_per_page * (-item);
		}
		// Update currentpage index
		this.currentPage = Math.floor(item / this.items_per_page);
		
		// Force item within limits
		item = item < this.items.length ? item : this.items.length-1;
		
		if (item!=this.currentIndex || force)
		{
			this.lastIndex = this.lastIndex || 0;
			this.currentIndex = item;
			
			if (!scrollPage) {
				if (this.currentIndex >= this.lastIndex && this.currentIndex <= this.lastIndex + this.items_per_page - 1) {
					var offSetIndex = this.lastIndex;  // Already viewable
				} else if (this.currentIndex + this.items_per_page >= this.items.length) {
					var offSetIndex = this.lastIndex < this.items.length - this.items_per_page ?
						this.currentIndex - (this.items_per_page-1) : // Position at rightmost of viewport
						this.items.length - this.items_per_page;      // Do not position leftmost of viewport, instead position to view all last rightmost items
				} else {
					var offSetIndex = this.lastIndex < this.currentIndex ?
						this.currentIndex - (this.items_per_page-1) :  // Position at rightmost of viewport
						this.currentIndex;    // Position at leftmost of viewport
				}
			} else {
				var offSetIndex = this.currentIndex > last_page_item ?
					last_page_item :
					this.currentIndex;
			}
			offSetIndex = offSetIndex || 0;  // not needed ?
			this.lastIndex = offSetIndex;
			
			// Scroll the item handles box, to include current item
			if (this.item_handles_box) {
				
				var use_mCSB = jQuery(this.item_handles_box).hasClass('mCustomScrollbar');
				
				var handlebox = jQuery(this.item_handles[0]).parent(); // works with mCSB too //jQuery(this.item_handles_box);
				var scrollWidth = handlebox[0].scrollWidth;
				
				var item_width = scrollWidth / this.items.length;
				var item_pos   = item_width * item;
				
				var left_most  = 0;
				var right_most = this.items.length * item_width;
				
				var prev_item_left  = item_pos - item_width;
				var next_item_right = item_pos + 2*item_width;
				
				var viewport_width = handlebox.parent().width();
				var viewport_left  = ( use_mCSB ? -parseInt(handlebox.css('left')) : handlebox.scrollLeft() ) - 1;
				var viewport_right = viewport_width + viewport_left + 1;
				
				//alert('' + viewport_right + ' < ' + next_item_right + ' && ' + viewport_right + ' < ' + right_most);
				//alert('' + viewport_left  + ' > ' + prev_item_left  + ' && ' + viewport_left  + ' > ' + left_most );
				
				// Viewport (a) does not includes next item fully  AND  (b) not at right most
				if ( (viewport_right < next_item_right)  &&  (viewport_right < right_most) ) {
					! use_mCSB ?
						handlebox.animate({ scrollLeft: next_item_right - viewport_width }, this.item_handle_duration)  :
						jQuery(this.item_handles_box).mCustomScrollbar("scrollTo", next_item_right - viewport_width, { scrollInertia: this.item_handle_duration });
				}
				
				// Viewport (a) does not include prev item fully  AND  (b) not at left most
				else if ( (viewport_left > prev_item_left)  &&  (viewport_left > left_most) ) {
					! use_mCSB ?
					handlebox.animate({ scrollLeft: prev_item_left }, this.item_handle_duration) :
					jQuery(this.item_handles_box).mCustomScrollbar("scrollTo", prev_item_left, { scrollInertia: this.item_handle_duration } );
				}
			}
			
			if (manual) jQuery(this.items_box).stop();
			
			// Start the item transistion
			if (this.transition=='0') {
				jQuery(this.items_box).css('left', '' + (this.item_size*-offSetIndex) + 'px');
			}
			
			else if (this.transition=='scroll') {
				jQuery(this.items_box).animate({ left: this.item_size*-offSetIndex }, this.fxOptions);
			}
			
			else {
				
				if (this.items_mask && this.mode=='horizontal') {
					jQuery(this.items_mask).css('min-height', '' + this.items_mask[0].clientHeight + 'px');
				}
				
				// Calculate items shown per page including any partial item visible more than 20%,
				// make total duration according to item show, is using pages
				limit = noFx ? this.items.length - offSetIndex : this.items_per_page + (_items_per_page - this.items_per_page > 0.2 ? 1 : 0);
				
				//alert('offSetIndex: ' + offSetIndex + ' , limit: ' + limit);
				for(i=0; i<offSetIndex; i++) {
					if (i >= this.items.length) break;
					jQuery(this.items[i]).hide();
				}
				for(i=offSetIndex+limit; i<this.items.length; i++) {
					if (i >= this.items.length) break;
					jQuery(this.items[i]).hide();
				}
				
				for(i=0; i<limit; i++) {
					if (offSetIndex+i >= this.items.length) break;
					
					//alert(offSetIndex+i);
					jQuery(this.items[offSetIndex+i]).css('position', 'absolute');
					if (scrollPage) {
						this.mode=='horizontal' ?
							jQuery(this.items[offSetIndex+i]).css('left', '' + (i*this.item_size) + 'px') :
							jQuery(this.items[offSetIndex+i]).css('top', '' + (i*this.item_size) + 'px');
					} else {
						this.mode=='horizontal' ?
							jQuery(this.items[offSetIndex+i]).animate( {left: (i*this.item_size)}, this.transition_visible_duration ) :
							jQuery(this.items[offSetIndex+i]).animate( {top : (i*this.item_size)}, this.transition_visible_duration );
					}
					
					//alert('' + (offSetIndex+i) + ' ' + jQuery(this.items[offSetIndex+i]).css('display'));
					if ( jQuery(this.items[offSetIndex+i]).css('display') != 'none' ) {
						jQuery(this.items[offSetIndex+i]).stop(false, true);  // Stop current animation forcing it to complete, but do not remove pending animations
						continue;
					}
					if (noFx) {
						jQuery(this.items[offSetIndex+i]).show();
					} else if (this.transition=='fade') {
						jQuery(this.items[offSetIndex+i]).fadeIn(this.fxOptions);
					} else if (this.transition=='slide') {
						jQuery(this.items[offSetIndex+i]).slideDown(this.fxOptions);
					} else if (this.transition=='fade-slide') {
						jQuery(this.items[offSetIndex+i]).show(this.fxOptions);
					} else {  // unknown just show them instantly
						jQuery(this.items[offSetIndex+i]).show();
					}
				}
			}
			
			// Schedule autoplay if enabled
			if(manual && this.autoPlay){
				this.play(this.playInterval,'next',true);
			}
			// Update current item/page data
			if(this.onWalk){
				var currentItem = this.items[this.currentIndex] || null;
				var currentPageHandle = (this.page_handles && this.page_handles[this.currentPage]) ? this.page_handles[this.currentPage] : null;
				var currentItemHandle = (this.item_handles && this.item_handles[this.currentIndex]) ? this.item_handles[this.currentIndex] : null;
				this.onWalk(currentItem, currentPageHandle, currentItemHandle);
			}
		}
	}
});



/* 
 * FOLLOWING part are jQuery easing functions authored by George McGinley Smith
 *
 * jQuery Easing v1.3 - http://gsgd.co.uk/sandbox/jquery/easing/
 *
 * Uses the built in easing capabilities added In jQuery 1.1 to offer multiple easing options
 *
 * TERMS OF USE - jQuery Easing
 * 
 * Open source under the BSD License. 
 * 
 * Copyright © 2008 George McGinley Smith
 * All rights reserved.
 */

// t: current time, b: begInnIng value, c: change In value, d: duration
jQuery.easing['jswing'] = jQuery.easing['swing'];

jQuery.extend( jQuery.easing,
{
	def: 'easeOutQuad',
	swing: function (x, t, b, c, d) {
		//alert(jQuery.easing.default);
		return jQuery.easing[jQuery.easing.def](x, t, b, c, d);
	},
	
	
	easeInQuad: function (x, t, b, c, d) {
		return c*(t/=d)*t + b;
	},
	easeOutQuad: function (x, t, b, c, d) {
		return -c *(t/=d)*(t-2) + b;
	},
	easeInOutQuad: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t + b;
		return -c/2 * ((--t)*(t-2) - 1) + b;
	},
	
	
	easeInCubic: function (x, t, b, c, d) {
		return c*(t/=d)*t*t + b;
	},
	easeOutCubic: function (x, t, b, c, d) {
		return c*((t=t/d-1)*t*t + 1) + b;
	},
	easeInOutCubic: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t + b;
		return c/2*((t-=2)*t*t + 2) + b;
	},
	
	
	easeInQuart: function (x, t, b, c, d) {
		return c*(t/=d)*t*t*t + b;
	},
	easeOutQuart: function (x, t, b, c, d) {
		return -c * ((t=t/d-1)*t*t*t - 1) + b;
	},
	easeInOutQuart: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
		return -c/2 * ((t-=2)*t*t*t - 2) + b;
	},
	
	
	easeInQuint: function (x, t, b, c, d) {
		return c*(t/=d)*t*t*t*t + b;
	},
	easeOutQuint: function (x, t, b, c, d) {
		return c*((t=t/d-1)*t*t*t*t + 1) + b;
	},
	easeInOutQuint: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return c/2*t*t*t*t*t + b;
		return c/2*((t-=2)*t*t*t*t + 2) + b;
	},
	
	
	easeInSine: function (x, t, b, c, d) {
		return -c * Math.cos(t/d * (Math.PI/2)) + c + b;
	},
	easeOutSine: function (x, t, b, c, d) {
		return c * Math.sin(t/d * (Math.PI/2)) + b;
	},
	easeInOutSine: function (x, t, b, c, d) {
		return -c/2 * (Math.cos(Math.PI*t/d) - 1) + b;
	},
	
	
	easeInExpo: function (x, t, b, c, d) {
		return (t==0) ? b : c * Math.pow(2, 10 * (t/d - 1)) + b;
	},
	easeOutExpo: function (x, t, b, c, d) {
		return (t==d) ? b+c : c * (-Math.pow(2, -10 * t/d) + 1) + b;
	},
	easeInOutExpo: function (x, t, b, c, d) {
		if (t==0) return b;
		if (t==d) return b+c;
		if ((t/=d/2) < 1) return c/2 * Math.pow(2, 10 * (t - 1)) + b;
		return c/2 * (-Math.pow(2, -10 * --t) + 2) + b;
	},
	
	
	easeInCirc: function (x, t, b, c, d) {
		return -c * (Math.sqrt(1 - (t/=d)*t) - 1) + b;
	},
	easeOutCirc: function (x, t, b, c, d) {
		return c * Math.sqrt(1 - (t=t/d-1)*t) + b;
	},
	easeInOutCirc: function (x, t, b, c, d) {
		if ((t/=d/2) < 1) return -c/2 * (Math.sqrt(1 - t*t) - 1) + b;
		return c/2 * (Math.sqrt(1 - (t-=2)*t) + 1) + b;
	},
	
	
	easeInElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		return -(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
	},
	easeOutElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d)==1) return b+c;  if (!p) p=d*.3;
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		return a*Math.pow(2,-10*t) * Math.sin( (t*d-s)*(2*Math.PI)/p ) + c + b;
	},
	easeInOutElastic: function (x, t, b, c, d) {
		var s=1.70158;var p=0;var a=c;
		if (t==0) return b;  if ((t/=d/2)==2) return b+c;  if (!p) p=d*(.3*1.5);
		if (a < Math.abs(c)) { a=c; var s=p/4; }
		else var s = p/(2*Math.PI) * Math.asin (c/a);
		if (t < 1) return -.5*(a*Math.pow(2,10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )) + b;
		return a*Math.pow(2,-10*(t-=1)) * Math.sin( (t*d-s)*(2*Math.PI)/p )*.5 + c + b;
	},
	
	
	easeInBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158;
		return c*(t/=d)*t*((s+1)*t - s) + b;
	},
	easeOutBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158;
		return c*((t=t/d-1)*t*((s+1)*t + s) + 1) + b;
	},
	easeInOutBack: function (x, t, b, c, d, s) {
		if (s == undefined) s = 1.70158; 
		if ((t/=d/2) < 1) return c/2*(t*t*(((s*=(1.525))+1)*t - s)) + b;
		return c/2*((t-=2)*t*(((s*=(1.525))+1)*t + s) + 2) + b;
	},
	
	
	easeInBounce: function (x, t, b, c, d) {
		return c - jQuery.easing.easeOutBounce (x, d-t, 0, c, d) + b;
	},
	easeOutBounce: function (x, t, b, c, d) {
		if ((t/=d) < (1/2.75)) {
			return c*(7.5625*t*t) + b;
		} else if (t < (2/2.75)) {
			return c*(7.5625*(t-=(1.5/2.75))*t + .75) + b;
		} else if (t < (2.5/2.75)) {
			return c*(7.5625*(t-=(2.25/2.75))*t + .9375) + b;
		} else {
			return c*(7.5625*(t-=(2.625/2.75))*t + .984375) + b;
		}
	},
	easeInOutBounce: function (x, t, b, c, d) {
		if (t < d/2) return jQuery.easing.easeInBounce (x, t*2, 0, c, d) * .5 + b;
		return jQuery.easing.easeOutBounce (x, t*2-d, 0, c, d) * .5 + c*.5 + b;
	}
});