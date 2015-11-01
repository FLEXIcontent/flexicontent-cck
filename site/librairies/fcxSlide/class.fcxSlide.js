/*
Author:	ggppdk

Requires: jQuery 1.7 or later

License: GPL3 (for the slider), the easing functions have BSD License, maybe move to separate file

Description:
	a jQuery slider written almost from scratch (easing functions by George McGinley, see License below)
	with advanced responsive design and complex walk calculation for items, item handles and page handles
	
		- jQuery based, smooth scroll/drag and performance wise
		
		- Fixed ITEM size (fixed width OR height) of Dynamic ITEM size (items per_page)
		
		- Semi-responsive / fully-responsive, detects vieport width change
			and resizes the 1 item dimension (the non-fixed dimension) or both dimensions for full-responsive mode with 'items per page'
		
		- Touch-drag sliding
		- Mouse-drag sliding
		
		- Supports
			a. item handles
			b. page handles (Pagination support !)
			c. action handles
		
		- Dynamice page buttons recalulation
		
		- Walk either items OR pages or both (!)
		
		- Walking methods based on jQuery transition effects:
			For both items and pages: scroll, fade, slide, fade-slide clip, scale, drop
			For pages only: blind, bounce, fold, pulsate, shake
			
		- jQuery EASING support for all (?) WALK methods
		
		- Handle transition of already visible items intuitively (move them instead of sliding or fading them)
		
		- Walk the slider ONLY IF needed (if target item or page not already within view-port), otherwise just "activate" next/previous item
			
		- Auto-scroll for item handles container
			a. auto-scroll at edges
			b. auto-scroll on page change
			c. support for mCSB jQuery scroller
			
		- TODO: improve above description


Class Name:
	FCXscroller

Parameters:
	
	mode: string | 'horizontal', 'vertical' | default: 'horizontal'
	transition: string | 'scroll', 'fade', 'slide', 'fade-slide' | default: 'scroll'
	fxOptions: object | jQuery.Animate options | default: { duration: 400, easing: 'linear' }
	transition_visible_duration: int | default: 100
	
	items: dom collection | required
	items_inner: dom collection | optional
	items_box: dom element | required
	items_mask: dom element | required
	
	responsive: int | default 0
	items_per_page: int | default: 1
	item_size: int | item size (px) | default: 240
	
	touch_walk: boolean | default: true
	mouse_walk: boolean | default: false
	dragstart_margin: int | default: 20
	dragwalk_margin: int | default: 100
	
	page_handles: dom collection | default: null
	page_handle_event: string | event type| default: 'click'
	
	item_handles: dom collection | default: null
	item_handles_box: dom element | optional
	item_handles_dir: string | 'horizontal', 'vertical
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


Properties (parameter initializable):
	
	mode: string
	transition: string
	fxOptions: object
	transition_visible_duration: int     // ... duration of moving already visible items
	
	items: dom collection
	items_inner: dom collection
	items_box: dom element
	items_mask: dom element
	
	responsive: int        // ... 0:fixed ITEM size using 'item_size' (px), 1:automatic ITEM size to match 'items_per_page'
	items_per_page: int
	item_size: int
	
	touch_walk: boolean
	mouse_walk: boolean
	dragstart_margin: int
	dragwalk_margin: int
	
	page_handles: dom collection
	page_handle_event: string
	
	item_handles: dom collection
	item_handles_box: dom element
	item_handles_dir: string
	item_handle_event: string
	item_handle_duration: int
	
	action_handles: object
	action_handle_event: string
	
	edgeWrap: boolean
	autoPlay: boolean
	playInterval: int
	playMethod: string
	
	onWalk: function
	startItem: int


Properties (non-parameter initializable):

	pageCount: int       // currently active pages
	currentPage: int     // currently focused page
	currentIndex: int    // currently focused item
	lastIndex: int       // previously focused item
	
	resizeTimeout     // timeout TIMER object to fire ONCE the resize handling function
	autoPlayInterval  // interval TIMER object to fire REGULARLY the autoplay handling function
	
	isDragging: boolean  // touch/mouse movement is over drag start threshold


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

	play(interval,direction,wait): start auto walk items
		interval: int | required
		direction: string | "previous" or "next" (!do not append '_page', since this is automatically inside the function) | required
		wait: boolean | required
	stop(halt): stop scheduled auto walk
		halt: bolean | default:false

	walk(item,manual,noFx,force): walk to item (or to page if negative)
		item: int | required
		manual: bolean | default:false
		noFx: boolean | default:false
	
	resize(event): update carousel display on window resize
	addTouchDrag(): add touch/drag events to the items container
*/

var fcxSlide = function(params)
{
	this.initialize = function(params) {
		this.mode = params.mode || 'horizontal';
		this.transition = params.transition || 'scroll';
		this.fxOptions = params.fxOptions || { duration: 400, easing: 'linear' };
		this.transition_visible_duration = params.transition_visible_duration || 100;
		
		this.items = params.items;
		this.items_inner = params.items_inner || null;
		this.items_box = params.items_box;
		this.items_mask = params.items_mask;
		
		this.responsive = params.responsive || 0;
		this.items_per_page = params.items_per_page || 1;
		this.item_size = params.item_size || 240;
		
		this.touch_walk = params.touch_walk || 1;
		this.mouse_walk = params.mouse_walk || 0;
		this.dragstart_margin = params.dragstart_margin || 20;
		this.dragwalk_margin = params.dragwalk_margin || 100;
		
		this.page_handles = params.page_handles || null;
		this.page_handle_event = params.page_handle_event || 'click';
		
		this.item_handles = params.item_handles || null;
		this.item_handles_box = params.item_handles_box || false;
		this.item_handles_dir = params.item_handles_dir || 'horizontal';
		this.item_handle_event = params.item_handle_event || 'click';
		this.item_handle_duration = params.item_handle_duration || 400;
		
		//params.action_handles  // NOTE: the member variable is different than the parameter
		this.action_handle_event = params.action_handle_event || 'click';
		
		this.edgeWrap     = params.edgeWrap || false;
		this.autoPlay     = params.autoPlay || false;
		this.playInterval = params.playInterval || 5000;
		this.playMethod   = params.playMethod || 'page';
		this.startItem    = params.startItem || 0;
		
		this.onWalk = params.onWalk || null;
		
		// member variables, not initialized via parameters
		this.pageCount = 0;
		this.currentPage = 0;
		this.currentIndex = 0;
		this.lastIndex = null;
		
		this.resizeTimeout = null;
		this.autoPlayInterval = null;
		
		this.isDragging = false;
		
		// Add Item/Page/Action Handles
		if (this.item_handles)  this.bindItemHandles(this.item_handles);
		if (this.page_handles)  this.bindPageHandles(this.page_handles);
		if (params.action_handles) {
			this.action_handles = { previous: [], next: [], previous_page: [], next_page: [], play: [], playback: [], stop: [] };
			this.bindActionHandles(params.action_handles);
		}
		
		// Walk to initial item
		this.walk(this.startItem,true,false,true);
		
		// Add listener ON window resize event, to update the slider and rescroll to current item
		jQuery(window).resize({slider: this}, this.resize);
		
		// Add touch event support (mobile devices, etc), and optionally mouse drag support too
		this.addTouchDrag();
	};
	
	this.bindItemHandles = function(item_handles){
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
	};

	this.bindPageHandles = function(page_handles){
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
	};

	this.bindActionHandles = function(_action_handles){
		var slider = this;
		for (var action in _action_handles)
		{
			// Detect if current handle element is not a array of elements to be assigned the same action
			var action_set = (typeof(_action_handles[action])=='array')  ?  _action_handles[action]  :  [_action_handles[action]];
			
			// This handles an array but usually this action will be assigned to a single item in array, but some action maybe assigned to more than one control handle, e.g. 'next_page' or 'next'
			for(var i=0; i<action_set.length; i++){
				switch(action)
				{
					case 'stop':     action_set[i].on(this.action_handle_event, { }, function(ev) { slider.stop(true); } ); break;
					case 'playback': action_set[i].on(this.action_handle_event, { }, function(ev) { slider.play(slider.playInterval,'previous',false); } ); break;
					case 'play':     action_set[i].on(this.action_handle_event, { }, function(ev) { slider.play(slider.playInterval,'next',false); } ); break;
					
					case 'previous_page': action_set[i].on(this.action_handle_event, { }, function(ev) { slider.previous_page(true); } ); break;
					case 'next_page':     action_set[i].on(this.action_handle_event, { }, function(ev) { slider.next_page(true); } ); break;
					
					case 'previous': action_set[i].on(this.action_handle_event, { }, function(ev) {  slider.previous(true); } ); break;
					case 'next':     action_set[i].on(this.action_handle_event, { }, function(ev) { slider.next(true); } ); break;
				}
				// Prevent actions on mousedonwn of the action handles, e.g. text selection
				action_set[i].on('mousedown', { }, function(ev) { ev.preventDefault(); } ); 
				this.action_handles[action].push(action_set[i]);
			}
		}
	};
	
	
	this.previous_page = function(manual){
		this.walk('previous_page', manual, false);
	};
	
	this.next_page = function(manual){
		this.walk('next_page', manual, false);
	};
	
	this.previous = function(manual){
		this.walk((this.currentIndex>0 ? this.currentIndex-1 : (!this.edgeWrap ? 0 : this.items.length-1)), manual, false);
	};
	
	this.next = function(manual){
		this.walk((this.currentIndex<this.items.length-1 ? this.currentIndex+1 : (!this.edgeWrap ? this.items.length-1 : 0)), manual, false);
	};
	
	this.play = function(interval,direction,wait){
		// Clear the current pending autoplay timer, because we want to reschedule a new one
		this.stop();
		
		// Decide if doing PAGE or ITEM walk (configuration)
		direction_func = direction + (this.playMethod=='page' ? '_page' : '' );
		if(!wait){
			this[direction_func](false);
		}
		var slider = this;
		this.autoPlayInterval = setInterval(
			function() {
				slider[direction_func].call(slider,[false])
			},
			interval
		);
		this.autoPlay = true;
	};
	
	this.stop = function(halt){
		clearTimeout(this.autoPlayInterval);
		if (halt) {
			this.autoPlay = false;
		}
	};
	
	this.debounce = function(func, wait, immediate) {
		var timeout;
		return function() {
			var context = this, args = arguments;
			var later = function() {
				timeout = null;
				if (!immediate) func.apply(context, args);
			};
			var callNow = immediate && !timeout;
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
			if (callNow) func.apply(context, args);
		};
	};
	
	this.walk_now = function(item,manual,noFx,force)
	{
		/* Detect ITEMs per page for horizontal */
		var items_per_page_float = this.items_per_page;
		if (this.items_mask && this.mode=='horizontal' && this.responsive==0) {
			items_per_page_float = (this.items_mask[0].clientWidth) / this.item_size;
			this.items_per_page = parseInt(items_per_page_float);
		}
		if (!this.items_per_page) { // if width detection fails
			items_per_page_float = this.items_per_page = 1;
		}
		
		var width_changed = !this.items_mask || !this.mask_width || this.mask_width != this.items_mask[0].clientWidth;
		if (this.items_mask) this.mask_width = this.items_mask[0].clientWidth;
		
		if (this.mode=='horizontal') {
			if (this.responsive==1) {
				var forcedWidth = 0;
				if (this.items_mask) forcedWidth = this.items_mask[0].clientWidth / this.items_per_page;
				forcedWidth = forcedWidth ? forcedWidth : 240;  // if detection fails, use default
				// Round to closest lower integer (aka 'floor') to avoid problems while scrolling, also since is this less than items container, it should not be a problem
				this.item_size = forcedWidth;
			}
			if (width_changed) {
				for(i=0; i<this.items.length; i++) {
					jQuery(this.items[i]).css('width', this.item_size);
				}
			}
		}
		
		// Set height of elements to max ITEM height for both HORIZONTAL and VERTICAL modes (* vertical configuration may force fixed height)
		if (width_changed && (this.responsive==1 || !this.item_height_px_OLD)) {
			this.item_height_px_OLD = 0; // Force updating height
			var maxHeight = 0;
			jQuery(this.items).css('height', 'auto');
			jQuery(this.items_inner).css('height', 'auto');
			this.items.each(function() { maxHeight = Math.max(maxHeight, this.clientHeight); });
			//alert('Setting item height to:' + maxHeight);
			
			// Set item size for vertical
			if (this.mode=='vertical') this.item_size = maxHeight;
			this.item_height_px = maxHeight;
		} else if (this.mode=='vertical') {
			this.item_height_px = this.item_size;
		}
		
		// Force height
		if (!this.item_height_px_OLD || this.item_height_px_OLD != this.item_height_px) {
			//var start = new Date().getTime();  // execution time of setting/forcing the -height- of item containers
			// Set height of item containers
			jQuery(this.items).css('height', this.item_height_px);
			
			// Set height of item inner containers (used for padding/margin)
			if (this.items_inner) {
				var innerEl = jQuery(this.items_inner[0]);
				var bordT = innerEl.outerHeight() - innerEl.innerHeight();
				var paddT = innerEl.innerHeight() - innerEl.height();
				var margT = innerEl.outerHeight(true) - innerEl.outerHeight();
				jQuery(this.items_inner).css('height', this.item_height_px - bordT - paddT - margT);
			}
			//var end = new Date().getTime();
			//var time = end - start;
			//if ( window.console && window.console.log ) window.console.log ('Execution time of setting height of item containers: ' + time);
		}
		this.item_height_px_OLD = this.item_height_px;
		
		// Set appropriate size for the items box
		this.mode_to_css = {horizontal:['left','width'], vertical:['top','height']};
		jQuery(this.items_box).css(this.mode_to_css[this.mode][1],(this.item_size*this.items.length)+'px');
		jQuery(this.items_mask).css(this.mode_to_css[this.mode][1],(this.item_size*this.items_per_page)+'px');
		
		
		/* Detect number of pages, current page and position of 1st item at last page */
		var page_count = Math.floor(this.items.length / this.items_per_page) + ((this.items.length % this.items_per_page) ? 1 : 0);
		var curr_page  = Math.floor(this.currentIndex / this.items_per_page);
		var last_page_item = page_count * this.items_per_page;
		
		/* Update page handles according to new page detection */
		if (this.page_handles && this.pageCount != page_count) {
			for(var i=0; i<page_count; i++){
				jQuery(this.page_handles[i]).css('display', 'inline-block');
			}
			for(var i=page_count; i<this.page_handles.length; i++){
				jQuery(this.page_handles[i]).css('display', 'none');
			}
		}
		this.pageCount = page_count;
		
		
		/* WALK item or full page */
		if (item=='next_page' || item=='previous_page') {
			var scrollPage = true;
			new_page = (item=='next_page') ? curr_page + 1  :  curr_page - 1;
		} else {
			var scrollPage = item < 0;
			if (scrollPage) new_page = -item;
		}
		
		// If doing page slide, then decide item to show, checking item is within item limits,
		var smoothWrap = 0;
		if (scrollPage) {
			// WRAP around if page is out of limits
			if (new_page >= page_count && this.edgeWrap) smoothWrap = this.items_per_page;
			new_page = new_page < page_count ? new_page : (!this.edgeWrap ? page_count - 1 : 0);
			
			if (new_page < 0 && this.edgeWrap) smoothWrap = -this.items_per_page;
			new_page = new_page >= 0         ? new_page : (!this.edgeWrap ? 0 : page_count - 1);
			item = -new_page;
			
			item = this.items_per_page * (-item);
		} else {
			if (this.currentIndex==this.items.length-1 && item==0)
				smoothWrap = this.items_per_page;
			else if (this.currentIndex==this.items.length-1 && item==0)
				smoothWrap = -this.items_per_page;
		}
		
		// Update currentpage index
		this.currentPage = Math.floor(item / this.items_per_page);
		
		// Make sure item within limits
		item = item < this.items.length ? item : this.items.length-1;
		
		
		if (item!=this.currentIndex || force)
		{
			// Stop current jQuery animation on the items box, forcing it to complete
			// NOTE: this -NOT- the stop() method of the slider (that stops the scheduled autoplay)
			// Cancel all animations without completing them, to allow continuing from current position, thus avoiding position jump to previous touch position
			jQuery(this.items_box).stop(true, false);
			
			
			// **********
			// WALK START
			// **********
			
			// 1. Update position indexes of the slider (lastIndex, currentIndex),
			//    and offSetIndex (= how many items to offset the items box), this depends if doing a page walk
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
			
			
			// 2. Scroll the item handles box, to include current item
			if (this.item_handles_box) {
				
				var use_mCSB = jQuery(this.item_handles_box).hasClass('mCustomScrollbar');
				var handlebox = jQuery(this.item_handles[0]).parent(); // works with mCSB too //jQuery(this.item_handles_box);
				
				// Enforce some CSS
				this.item_handles_dir=='horizontal' ?
					jQuery(handlebox).css('overflowY', 'hidden').css('overflowX', 'auto') :
					jQuery(handlebox).css('overflowX', 'hidden').css('overflowY', 'auto') ;
				if (!use_mCSB) {
					this.item_handles_dir=='horizontal' ?
						jQuery(handlebox).css('width', '100%').css('min-height', (jQuery(this.item_handles[0]).outerHeight(true)+16) + 'px') :
						jQuery(handlebox).css('width', '' + '' + (jQuery(this.item_handles[0]).outerWidth(true)+24) + 'px') ;
				}
				if (this.item_handles_dir!='horizontal') jQuery(handlebox).css('max-height', '' + this.items_mask[0].clientHeight + 'px') ;
				
				var scrollSize  = this.item_handles_dir=='horizontal' ? handlebox[0].scrollWidth : handlebox[0].scrollHeight;
				var handle_size = scrollSize / this.items.length;
				var item_pos    = handle_size * item;
				
				var prev_most = 0;
				var next_most = this.items.length * handle_size;
				
				var prev_item_startpos = item_pos - handle_size;
				var prev_item_endpos   = item_pos + 2*handle_size;
				
				var viewport_size  = this.item_handles_dir=='horizontal' ? handlebox.parent().width() : handlebox.parent().height() ;
				var viewport_start = this.item_handles_dir=='horizontal' ?
					(( use_mCSB ? -parseInt(handlebox.css('left')) : handlebox.scrollLeft() ) - 1) :
					(( use_mCSB ? -parseInt(handlebox.css('top'))  : handlebox.scrollTop()  ) - 1) ;
				var viewport_end   = viewport_size + viewport_start + 1;
				
				//alert('' + viewport_end + ' < ' + prev_item_endpos + ' && ' + viewport_end + ' < ' + next_most);
				//alert('' + viewport_start  + ' > ' + prev_item_startpos  + ' && ' + viewport_start  + ' > ' + prev_most );
				
				// Viewport (a) does not includes next item fully  AND  (b) not at right most
				if ( (viewport_end < prev_item_endpos)  &&  (viewport_end < next_most) ) {
					! use_mCSB ?
						(this.item_handles_dir=='horizontal' ?
							handlebox.animate({ scrollLeft: prev_item_endpos - viewport_size }, this.item_handle_duration) :
							handlebox.animate({ scrollTop:  prev_item_endpos - viewport_size }, this.item_handle_duration)
						) :
						jQuery(this.item_handles_box).mCustomScrollbar("scrollTo", prev_item_endpos - viewport_size, { scrollInertia: this.item_handle_duration });
				}
				
				// Viewport (a) does not include prev item fully  AND  (b) not at left most
				else if ( (viewport_start > prev_item_startpos)  &&  (viewport_start > prev_most) ) {
					! use_mCSB ?
						(this.item_handles_dir=='horizontal' ?
							handlebox.animate({ scrollLeft: prev_item_startpos }, this.item_handle_duration) :
							handlebox.animate({ scrollTop:  prev_item_startpos }, this.item_handle_duration)
						) :
					jQuery(this.item_handles_box).mCustomScrollbar("scrollTo", prev_item_startpos, { scrollInertia: this.item_handle_duration } );
				}
			}
			
			
			// 3. Start transition in items box (aka -WALK- the slider)
			if (this.transition=='0') {
				this.mode=='horizontal' ?
					jQuery(this.items_box).css('left', '' + (this.item_size*-offSetIndex) + 'px') :
					jQuery(this.items_box).css('top', '' + (this.item_size*-offSetIndex) + 'px');
			}
			
			else if (this.transition=='scroll') {
				if (smoothWrap) {
					this.mode=='horizontal' ?
						jQuery(this.items_box).css('left', '' + (this.item_size*(-offSetIndex+smoothWrap)) + 'px') :
						jQuery(this.items_box).css('top', '' + (this.item_size*(-offSetIndex+smoothWrap)) + 'px');
				}
				this.mode=='horizontal' ?
					jQuery(this.items_box).animate({ left: this.item_size*-offSetIndex }, this.fxOptions) :
					jQuery(this.items_box).animate({ top: this.item_size*-offSetIndex }, this.fxOptions);
			}
			
			else {
				//var start = new Date().getTime();  // execution time of -scheduling- the transition effect
				
				if (this.items_mask && this.mode=='horizontal') {
					jQuery(this.items_mask).css('min-height', '' + this.items_mask[0].clientHeight + 'px');
				}
				this.mode=='horizontal' ?
					jQuery(this.items_box).css('left', '0px') :
					jQuery(this.items_box).css('top', '0px');
				
				// Calculate items shown per page including any partial item visible more than 20%,
				// make total duration according to item show, is using pages
				limit = noFx ? this.items.length - offSetIndex : this.items_per_page + (items_per_page_float - this.items_per_page > 0.2 ? 1 : 0);
				
				//window.console.log('offSetIndex: ' + offSetIndex + ' , limit: ' + limit + ' , items_per_page: ' + items_per_page_float);
				// Cancel all animations without completing them, but force current to complete, to allow new one to start from proper point
				for(i=0; i<this.items.length; i++) {
					jQuery(this.items[i]).stop(false, true);
				}
				
				// Hide items not inside current page
				for(i=0; i<offSetIndex; i++) {
					if (i >= this.items.length) break;
					jQuery(this.items[i]).hide();
				}
				for(i=offSetIndex+limit; i<this.items.length; i++) {
					if (i >= this.items.length) break;
					jQuery(this.items[i]).hide();
				}
				
				jQuery(this.items).css('position', 'absolute');
				for(i=0; i<limit; i++) {
					if (offSetIndex+i >= this.items.length) break;
					
					//window.console.log((this.mode=='horizontal' ? 'left' : 'top') +': ' + (offSetIndex+i) + ' - '+(i*this.item_size) + 'px');
					if (scrollPage) {
						this.mode=='horizontal' ?
							jQuery(this.items[offSetIndex+i]).css('left', '' + (i*this.item_size) + 'px') :
							jQuery(this.items[offSetIndex+i]).css('top', '' + (i*this.item_size) + 'px');
					} else {
						this.mode=='horizontal' ?
							jQuery(this.items[offSetIndex+i]).animate( {left: (i*this.item_size)}, this.transition_visible_duration ) :
							jQuery(this.items[offSetIndex+i]).animate( {top : (i*this.item_size)}, this.transition_visible_duration );
					}
					
					if (noFx) {
						jQuery(this.items[offSetIndex+i]).show();
					} else if (this.transition=='fade') {
						jQuery(this.items[offSetIndex+i]).fadeIn(this.fxOptions);
					} else if (this.transition=='fade-slide') {
						jQuery(this.items[offSetIndex+i]).show(this.fxOptions);
					} else {  // Other transition: clip, drag, explode, etc
						jQuery(this.items[offSetIndex+i]).show(this.transition, this.fxOptions);
					}
				}
				
				//var end = new Date().getTime();
				//var time = end - start;
				//if ( window.console && window.console.log ) window.console.log ('Execution time of transition effect: ' + time);
			}
			
			// ********
			// WALK END
			// ********
			
			
			// Restart (aka clear and reschedule) the autoplay timer, (if this was a MANUAL walk)
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
	};
	
	this.resize = function(event){
		clearTimeout(this.resizeTimeout); // Clear any other pending resizing within the timeout limit e.g. 100 ms
		var slider = event.data.slider;  // Set this in case it is destroyed by the time timeout function is executed
		this.resizeTimeout = setTimeout(function(){
			slider.walk(slider.currentIndex,true,false,true);
		}, 100);
	};
	
	this.addTouchDrag = function(){
		if (!this.touch_walk && !this.mouse_walk) return;  // nothing to do
		
		var slider = this;
		var sliderBox = document.getElementById(this.items_mask.attr('id'));
		
		var boxPos = 0;
		var startPos = 0;
		var travelDist = 0;
		
		// Prevent click if a drag was started (minor drags are not considered, see dragstart_margin parameter)
		jQuery(sliderBox).on('click', function(ev){
			// Click event is scheduled/queued after mouseup event, isDragging FLAG is cleared via setTimeout, thus we can use it here to prevent clicks
			if (slider.isDragging) {
				var e = ev.originalEvent;
				e.preventDefault();
				e.stopPropagation();
			}
		});
		
		var startEvents = (this.mouse_walk ? 'mousedown' : '') + (this.touch_walk ? ' touchstart' : '');
		jQuery(sliderBox).on(startEvents, function(ev){
			var e = ev.originalEvent;  // Get original event
			if (ev.type=='mousedown') {
				// In the case of using mouse events, we need to prevent events for 'mousedown'
				e.preventDefault(); e.stopPropagation();  // this may create undesired effects in some cases
			}
			
			var obj = ev.type!='touchstart' ? e : e.changedTouches[0]; // reference first touch point for this event
			
			// Indicate draging by changing mouse cursor
			//jQuery(slider.items_box).css('cursor', (slider.mode=='horizontal' ? (travelDist<0 ? 'w-resize' : 'e-resize') : (travelDist<0 ? 'n-resize' : 's-resize')) );
			jQuery(slider.items_box).css('cursor', "pointer" );
			
			// Stop all jQuery animations and force them to complete, to get proper current position of the slider
			jQuery(slider.items_box).finish();
			boxPos = parseInt(jQuery(slider.items_box).css(slider.mode=='horizontal' ? 'left' : 'top'));
			startPos = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY);
			//if ( window.console && window.console.log ) window.console.log ('Status: mousedown<br /> Start coordinate: ' + startPos + 'px');
		});
		
		
		var moveEvents = (this.mouse_walk ? 'mousemove' : '') + (this.touch_walk ? ' touchmove' : '');
		jQuery(sliderBox).on(moveEvents, function(ev){
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault();	//e.stopPropagation();
			
			var obj = ev.type!='touchmove' ? e : e.changedTouches[0]; // reference first touch point for this event
			var moveStarted = (startPos!=0);
			if (!moveStarted) return;
			
			var travelDist = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY) - startPos;
			//if ( window.console && window.console.log ) ; //window.console.log ('Status: mousemove<br /> Distance traveled: ' + travelDist + 'px');
			
			// Check if drap distance is over the drag start threshold
			if (!slider.isDragging && Math.abs(travelDist) < slider.dragstart_margin) return;
			slider.isDragging = true;
			
			// Touch/Mouse Drag is at new point, retarget to new point,
			// Cancel all animations without completing them, to allow continuing from current position, thus avoiding position jump to previous touch position
			jQuery(slider.items_box).stop(true, false);
			(slider.mode=='horizontal') ?
				jQuery(slider.items_box).animate({ left: boxPos+travelDist }, "fast") :
				jQuery(slider.items_box).animate({ top: boxPos+travelDist }, "fast") ;
		});
		
		
		var endEvents = (this.mouse_walk ? 'mouseleave mouseup' : '') + (this.touch_walk ? ' touchend' : '');
		jQuery(sliderBox).on(endEvents, function(ev){
			var e = ev.originalEvent;  // Get original event
			//e.preventDefault(); //e.stopPropagation();
			
			var obj = ev.type!='touchend' ? e : e.changedTouches[0]; // reference first touch point for this event
			
			var moveStarted = (startPos!=0);
			if (!moveStarted) return;  // check if initial click was not inside the slider
			var travelDist = parseInt(slider.mode=='horizontal' ? obj.clientX : obj.clientY) - startPos;
			//if ( window.console && window.console.log ) window.console.log ('Status: mouseup<br /> End coordinate: ' + (slider.mode=='horizontal' ? obj.clientX : obj.clientY) + 'px');
			
			jQuery(slider.items_box).css('cursor', 'auto');  // restore mouse pointer
			
			// Check if drap distance is over the drag walk threshold
			if (Math.abs(travelDist) > slider.dragwalk_margin)
			{
				// Drag is under walk threshold, walk the slider to proper direction,
				// Cancel all animations without completing them, to allow walking from current position, thus avoiding position jump to last touch position
				jQuery(slider.items_box).stop(true, false);
				slider.stop(true);  // Cancel autoplay, to avoid confusion to the user
			  (travelDist < 0) ?        // Walk the slider
			  	slider.next_page(true) :
			  	slider.previous_page(true) ;
			}
			
			else {
				// Drag is under threshold, return slider to original position before dragging was stated,
				// cancel all animations without completing, to allow returning from current position, thus avoiding position jump to last touch position
				jQuery(slider.items_box).stop(true, false);
				(slider.mode=='horizontal') ?
					jQuery(slider.items_box).animate({ left: boxPos }, "fast") :
					jQuery(slider.items_box).animate({ top: boxPos }, "fast") ;
			}
			
			startPos = 0;
			setTimeout(function(){ slider.isDragging = false; }, 100);
		});
	};
	
	this.walk = this.debounce(this.walk_now, 50, false);
	
	this.initialize(params);
};
