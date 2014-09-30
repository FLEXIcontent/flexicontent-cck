// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function (window) {
	
	// https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Function/bind
	if (!Function.prototype.bind ) {

		Function.prototype.bind = function( obj ) {
			var slice = [].slice,
					args = slice.call(arguments, 1), 
					self = this, 
					nop = function () {}, 
					bound = function () {
						return self.apply( this instanceof nop ? this : ( obj || {} ), 
																args.concat( slice.call(arguments) ) );    
					};

			nop.prototype = self.prototype;

			bound.prototype = new nop();

			return bound;
		};
	}


	if (typeof Code === "undefined") {
		Code = {};
		Code.PhotoSwipe = {};
	}
	
	
	
	Code.PhotoSwipe.Util = {
		
		browser: {
    	version: (navigator.userAgent.match( /.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/ ) || [])[1],
    	webkit: /webkit/i.test(navigator.userAgent),
    	opera: /opera/i.test(navigator.userAgent), // untested
    	msie: /msie/i.test(navigator.userAgent) && !/opera/.test(navigator.userAgent), 
    	mozilla: /mozilla/i.test(navigator.userAgent) && !/(compatible|webkit)/.test(navigator.userAgent),
			mobileSafari: /mobile.*safari/i.test(navigator.userAgent),
			is3dSupported: false,
			isAndroid: /android/i.test(navigator.userAgent),
			isBlackberry: /blackberry/i.test(navigator.userAgent),
			isiOS: /like Mac OS/i.test(navigator.userAgent),
			touchSupported: false,
			gestureSupported: false,
			
			
			_eventTagNames: {
				'select':'input',
				'change':'input',
				'submit':'form',
				'reset':'form',
				'error':'img',
				'load':'img',
				'abort':'img'
			},
				
				
			/*
			 * Function: isEventSupported
			 * http://perfectionkills.com/detecting-event-support-without-browser-sniffing/
			 */
			isEventSupported: function(eventName) {
				var el = document.createElement(this._eventTagNames[eventName] || 'div');
				eventName = 'on' + eventName;
				var isSupported = (eventName in el);
				if (!isSupported) {
					el.setAttribute(eventName, 'return;');
					isSupported = typeof el[eventName] == 'function';
				}
				el = null;
				return isSupported;
			}
    },
	
	
		/*
		 * Function: setElementData
		 */
		setElementData: function(el, key, value){
			
			if ( this.isNothing(el.UtilData) ){
				el.UtilData = { };
			}
			
			el.UtilData[key] = value;
		},
		
		
		/*
		 * Function: getElementData
		 */
		getElementData: function(el, key, defaultValue){
			
			if (typeof defaultValue === "undefined"){
				defaultValue = null;
			}
			
			if ( this.isNothing(el.UtilData) ){
				return defaultValue;
			}
			
			if ( this.isNothing(el.UtilData[key]) ){
				return defaultValue;
			}
			
			return el.UtilData[key];
			
		},
		
		
		/*
		 * Function: removeElementData
		 */
		removeElementData: function(el, key){
		
			delete el.UtilData[key];
			
		},
		
		
		/*
		 * Function: coalesce
		 * Takes any number of arguments and returns the first non Null / Undefined argument.
			*/
		coalesce: function () {
			var i;
			for (i = 0; i < arguments.length; i++) {
				if (!this.isNothing(arguments[i])) {
					return arguments[i];
				}
			}
			return null;
		},
		
		
		
		/*
		 * Function: registerNamespace
		 */			
		registerNamespace: function () {
			var args = arguments, obj = null, i, j;
			for (i = 0; i < args.length; ++i) {
				var ns = args[i];
				var nsParts = ns.split(".");
				var root = nsParts[0];
				eval('if (typeof ' + root + ' == "undefined"){' + root + ' = {};} obj = ' + root + ';');
				for (j = 1; j < nsParts.length; ++j) {
					obj[nsParts[j]] = obj[nsParts[j]] || {};
					obj = obj[nsParts[j]];
				}
			}
		},
		
		
		
		/*
		 * Function: extend
		 */
		extend: function(destination, source, overwriteProperties){
			if (this.isNothing(overwriteProperties)){
				overwriteProperties = true;
			}
			if (destination && source && this.isObject(source)){
				for(var prop in source){
					if (overwriteProperties){
						destination[prop] = source[prop];
					}
					else{
						if(typeof destination[prop] == "undefined"){ 
							destination[prop] = source[prop]; 
						}
					}
				}
			}
		},
		
		
		/*
		 * Function: swapArrayElements
		 */
		swapArrayElements: function(arr, i, j){
			
			var temp = arr[i]; 
			arr[i] = arr[j];
			arr[j] = temp;
		
		},
		
		
		/*
		 * Function: isObject
		 */
		isObject: function(obj){
			return typeof obj == "object";
		},
		
		
		
		/*
		 * Function: isNothing
		 */
		isNothing: function (obj) {
			if (typeof obj === "undefined" || obj === null) {
				return true;
			}	
			return false;
		},
		
		
		
		/*
		 * Function: isFunction
		 */
		isFunction: function(obj){
			return typeof obj == "function";
		},
		
		
		
		/*
		 * Function: isArray
		 */
		isArray: function(obj){
			return obj && Code.PhotoSwipe.Util.isFunction(obj.pop);
		},
		
		
		
		/*
		 * Function: isNumber
		 */
		isNumber: function(obj){
			return typeof obj == "number";
		},
		
		
		/*
		 * Function: isString
		 */
		isString: function(obj){
			return typeof obj == "string";
		},
		
		
		
		/*
		 * Function: trim
		 */
		trim: function(val) {
			var re = new RegExp(/\s+?/);
			return val.replace(re, '');
    }
		
		
	};
	
	if (Code.PhotoSwipe.Util.browser.webkit){
		var test3DEl = document.createElement('div');
		Code.PhotoSwipe.Util.browser.is3dSupported = !Code.PhotoSwipe.Util.isNothing(test3DEl.style.WebkitPerspective);
	}
	
	Code.PhotoSwipe.Util.browser.touchSupported = Code.PhotoSwipe.Util.browser.isEventSupported('touchstart');
	Code.PhotoSwipe.Util.browser.gestureSupported = Code.PhotoSwipe.Util.browser.isEventSupported('gesturestart');
	
})(window);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function (window, Util) {
	
	Util.extend(Util, {
		
		DOM: {
		
			
			/*
			 * Function: resetTranslate
			 * Required for smoother transition on iOS
			 */
			resetTranslate: function(el){
				
				if (Util.browser.webkit){
					if (Util.browser.is3dSupported){
						Util.DOM.setStyle(el, { webkitTransform: 'translate3d(0px, 0px, 0px)'});
					}
					else{
						Util.DOM.setStyle(el, { webkitTransform: 'translate(0px, 0px)'});
					}
				}
				else{
					Util.DOM.setStyle(el, {
						webkitTransform: 'translate(0px, 0px)',
						MozTransform: 'translate(0px, 0px)',
						transform: 'translate(0px, 0px)'
					});
				}
				
			},
		
		
			/*
			 * Function: createElement
			 */
			createElement: function(type, attributes, content){
				
				var retval = document.createElement(type);
					
				for(var attribute in attributes) {
					if(attributes.hasOwnProperty(attribute)){
						retval.setAttribute(attribute, attributes[attribute]);
					}
				}
    
				retval.innerHTML = content || '';
				
				return retval;
				
			},
			
			
			/*
			 * Function: appendChild
			 */
			appendChild: function(childEl, parentEl){
				
				parentEl.appendChild(childEl);
				
			},
			
			
			/*
			 * Function: appendText
			 */
			appendText: function(text, parentEl){
				
				var textNode = document.createTextNode(text);
				Util.DOM.appendChild(textNode, parentEl);
				
			},
			
			
			/*
			 * Function: appendToBody
			 */
			appendToBody: function(childEl){
				
				this.appendChild(childEl, document.body);
				
			},
			
			
			/*
			 * Function: removeChild
			 */
			removeChild: function(childEl, parentEl){
			
				parentEl.removeChild(childEl);
				
			},
			
			
			
			/*
			 * Function: removeChildren
			 */
			removeChildren: function(parentEl){
				
				if (parentEl.hasChildNodes()){
					
					while (parentEl.childNodes.length >= 1){
						parentEl.removeChild(parentEl.childNodes[parentEl.childNodes.length -1]);
					}
					
				}
			
			},
			
			
			
			/*
			 * Function: hasAttribute
			 */
			hasAttribute: function(el, attributeName){
			
				return el.getAttribute(attributeName);
			
			},
			
			
			/*
			 * Function: getAttribute
			 */
			getAttribute: function(el, attributeName){
				
				if(!this.hasAttribute(el, attributeName)){
					return '';
				}
				
				return el.getAttribute(attributeName);
			
			},
			
			
			/*
			 * Function: el, attributeName
			 */
			setAttribute: function(el, attributeName, value){
				
				el.setAttribute(attributeName, value);
				
			},
			
			
			/*
			 * Function: removeAttribute
			 */
			removeAttribute: function(el, attributeName){
				
				if (this.hasAttribute(el, attributeName)){
				
					el.removeAttribute(attributeName);
					
				}
			
			},
			
			
			/*
			 * Function: addClass
			 */
			addClass: function(el, className){
				
				var currentClassValue = Util.DOM.getAttribute(el, 'class');
				
				var re = new RegExp('(?:^|\\s+)' + className + '(?:\\s+|$)');
				
				if ( ! re.test(currentClassValue) ){
					if (currentClassValue !== ''){
						currentClassValue = currentClassValue + ' ';
					}
					currentClassValue = currentClassValue + className;
					Util.DOM.setAttribute(el, 'class', currentClassValue);
				}
       
			},
			
			
			/*
			 * Function: removeClass
			 */
			removeClass: function(el, className){
			
				var currentClassValue = Util.DOM.getAttribute(el, 'class');
				
				var re = new RegExp('(?:^|\\s+)' + className + '(?:\\s+|$)');
				
				if (re.test(currentClassValue)){
					
					currentClassValue = currentClassValue.replace(re, ' ');
					
					Util.DOM.setAttribute(el, 'class', currentClassValue);
					Util.DOM.removeClass(el, className);
					
				}
				else{
					currentClassValue = Util.trim(currentClassValue);
					if (currentClassValue === ''){
						Util.DOM.removeAttribute(el, 'class');
					}
					else{
						Util.DOM.setAttribute(el, 'class', currentClassValue);
					}
				}
				
			},
			
			
			/*
			 * Function: hasClass
			 */
			hasClass: function(el, className){
				
				var re = new RegExp('(?:^|\\s+)' + className + '(?:\\s+|$)');
        return re.test(Util.DOM.getAttribute(el, 'class'));
				
			},
			
			
			/*
			 * Function: setStyle
			 */
			setStyle: function(el, style, value){
				
				if (Util.isObject(style)) {
					for(var propertyName in style) {
						if(style.hasOwnProperty(propertyName)){
							el.style[propertyName] = style[propertyName];
						}
					}
				}
				else {
					el.style[style] = value;
				}
			},
			
			
			/*
			 * Function: getStyle
			 */
			getStyle: function(el, styleName){
				
				return window.getComputedStyle(el,'').getPropertyValue(styleName);
				
			},
			
			
			/*
			 * Function: hide
			 */
			hide: function(el){
				
				// Store the current display value if we use show
				Util.setElementData(el, 'oldDisplayValue', Util.DOM.getStyle(el, 'display'));
				Util.DOM.setStyle(el, 'display', 'none');
			
			},
			
			
			/*
			 * Function: show
			 */
			show: function(el){
				
				if (Util.DOM.getStyle(el, 'display') == 'none'){
					var oldDisplayValue = Util.getElementData(el, 'oldDisplayValue', 'block');
					if (oldDisplayValue === 'none'){
						oldDisplayValue = 'block';
					}
					Util.DOM.setStyle(el, 'display', oldDisplayValue);
				}
				
			},
			
			
			/*
			 * Function: width 
			 * Content width, excludes padding
			 */
			width: function(el, value){
				
				if (!Util.isNothing(value)){
					if (Util.isNumber(value)){
						value = value + 'px';
					}
					el.style.width = value;
				}
				
				return this._getDimension(el, 'width');
				
			},
			
			
			/*
			 * Function: outerWidth
			 */
			outerWidth: function(el){
				
				var retval = Util.DOM.width(el);
				
				retval += parseInt(Util.DOM.getStyle(el, 'padding-left'), 10) + parseInt(Util.DOM.getStyle(el, 'padding-right'), 10); 
				retval += parseInt(Util.DOM.getStyle(el, 'margin-left'), 10) + parseInt(Util.DOM.getStyle(el, 'margin-right'), 10); 
				retval += parseInt(Util.DOM.getStyle(el, 'border-left-width'), 10) + parseInt(Util.DOM.getStyle(el, 'border-right-width'), 10); 
				return retval;
			
			},
			
			
			/*
			 * Function: height 
			 * Content height, excludes padding
			 */
			height: function(el, value){
				
				if (!Util.isNothing(value)){
					if (Util.isNumber(value)){
						value = value + 'px';
					}
					el.style.height = value;
				}
				
				return this._getDimension(el, 'height');
				
			},
			
			
			/*
			 * Function: _getDimension
			 */
			_getDimension: function(el, dimension){
				
				var retval = window.parseInt(window.getComputedStyle(el,'').getPropertyValue(dimension));
				
				if (isNaN(retval)){
					
					// If this is the case, chances are the element is not displayed and we can't get
					// the width and height. This temporarily shows and hides to get the value
					var styleBackup = { 
						display: el.style.display,
						left: el.style.left
					};
					
					el.style.display = 'block';
					el.style.left = '-1000000px';
					
					retval = window.parseInt(window.getComputedStyle(el,'').getPropertyValue(dimension));
					
					el.style.display = styleBackup.display;
					el.style.left = styleBackup.left;
				}
				return retval;
				
			},
			
			
			
			/*
			 * Function: outerHeight
			 */
			outerHeight: function(el){
				
				var retval = Util.DOM.height(el);
				
				retval += parseInt(Util.DOM.getStyle(el, 'padding-top'), 10) + parseInt(Util.DOM.getStyle(el, 'padding-bottom'), 10); 
				retval += parseInt(Util.DOM.getStyle(el, 'margin-top'), 10) + parseInt(Util.DOM.getStyle(el, 'margin-bottom'), 10); 
				retval += parseInt(Util.DOM.getStyle(el, 'border-top-width'), 10) + parseInt(Util.DOM.getStyle(el, 'border-bottom-width'), 10); 
								
				return retval;
			
			},
			
			
			/*
			 * Function: documentWidth
			 */
			documentWidth: function(){
				
				return Util.DOM.width(document.documentElement);
				
			},

			
			/*
			 * Function: documentHeight
			 */
			documentHeight: function(){
				
				return Math.round(Util.DOM.height(document.documentElement));
				
			},
			
			
			/*
			 * Function: bodyWidth
			 */
			bodyWidth: function(){
				
				return Util.DOM.width(document.body);
			
			},
			
			
			/*
			 * Function: bodyHeight
			 */
			bodyHeight: function(){
				
				return Util.DOM.height(document.body);
			
			},
			
			
			/*
			 * Function: windowWidth
			 */
			windowWidth: function(){
			
				return window.innerWidth;
			
			},
			
			
			/*
			 * Function: windowHeight
			 */
			windowHeight: function(){
			
				return window.innerHeight;
			
			},
			
			
			/*
			 * Function: windowScrollLeft
			 */
			windowScrollLeft: function(){
			
				return window.pageXOffset;
			
			},
			
			
			/*
			 * Function: windowScrollTop
			 */
			windowScrollTop: function(){
			
				return window.pageYOffset;
			
			},
			
			
			/*
			 * Function: addEventListener
			 */
			addEventListener: function(el, type, listener){
				
				el.addEventListener(type, listener, false);
			
			},
			
			
			/*
			 * Function: removeEventListener
			 */
			removeEventListener: function(el, type, listener){
				
				el.removeEventListener(type, listener, false);
			
			},
			
			
			/*
			 * Function: getMousePosition
			 */
			getMousePosition: function(event){
				
				var retval = {
					x: 0,
					y: 0
				};
				
				if (event.pageX) {
					retval.x = event.pageX;
				}
				else if (event.clientX) {
					retval.x = event.clientX + (document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft);
				}
			
				if (event.pageY) {
					retval.y = event.pageY;
				}
				else if (event.clientY) {
					retval.y = event.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
				}
				
				return retval;
			},
			
			
			/*
			 * Function: getTouchEvent
			 */
			getTouchEvent: function(event){
				
				return event;
			
			}
			
		}
	
		
	});
	
	
})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function (window, Util) {
	
	Util.extend(Util, {
		
		
		Animation: {
			
			_applyTransitionDelay: 50,
			
			/*
			 * Function: _setTransition
			 * Sets animation transitions on the element
			 */
			_setTransition: function(el, property, duration, timingFunction, delay, callback){
				
				var 
					transitionPrefix = Util.Animation._getTransitionPrefix(),
					p = Util.coalesce(property, ''),
					d = Util.coalesce(duration, ''),
					t, de, c;
				
				if (Util.isFunction(timingFunction)){
					c = timingFunction;
					t = '';
					de = '';
				}
				else{
					c = callback;
					t = Util.coalesce(timingFunction, '');
					de = Util.coalesce(delay, '');
				}
				
				var transitionValues = {};
				transitionValues[transitionPrefix + 'Property'] = p;
				transitionValues[transitionPrefix + 'Duration'] = d;
				transitionValues[transitionPrefix + 'TimingFunction'] = t;
				transitionValues[transitionPrefix + 'Delay'] = de;
								
				Util.DOM.setStyle(el, transitionValues);
								
				// Wait for the above transitions to get applied
				if (Util.isFunction(c)){
					window.setTimeout(
						function(){
							c(el);
						},
						Util.Animation._applyTransitionDelay
					);
				}
				
			},
			
			
			/*
			 * Function: _setTransitionEndEventListener
			 * Sets an event listener on transition end. This will:
			 * - Remove the transitionEnd event hander
			 * - Fire any animation end callback you specified
			 *
			 * The function stores a pointer to the event handler functions
			 * on the element object itself (using Util.setElementData)
			 *
			 * This gives us a reference when removing the event listener
			 */
			_setTransitionEndEventListener: function(el){
			
				Util.setElementData(el, 'transitionEndEvent', function(e){
					
					var el = e.target;
					
					Util.DOM.removeEventListener(el, Util.Animation._getTransitionEndEventLabel(), Util.getElementData(el, 'transitionEndEvent'));
					Util.removeElementData(el, 'transitionEndEvent');
									
					var callback = Util.getElementData(el, 'transitionEndCallback');
					Util.removeElementData(el, 'transitionEndCallback');
				
					// Remove the tranistion
					Util.Animation._removeTransitions(el);
					
					if (Util.isFunction(callback)){
						
						window.setTimeout(
							function(){
								callback(e);
							},
							Util.Animation._applyTransitionDelay
						);
				
					}
					
				});
				
				
				Util.DOM.addEventListener(el, Util.Animation._getTransitionEndEventLabel(), Util.getElementData(el, 'transitionEndEvent'));
			
			},
			
			
			/*
			 * Function: _removeTransitions
			 */
			_removeTransitions: function(el){
				
				var transitionPrefix = Util.Animation._getTransitionPrefix();
				
				var transitionValues = {};
				transitionValues[transitionPrefix + 'Property'] = '';
				transitionValues[transitionPrefix + 'Duration'] = '';
				transitionValues[transitionPrefix + 'TimingFunction'] = '';
				transitionValues[transitionPrefix + 'Delay'] = '';
								
				Util.DOM.setStyle(el, transitionValues);
								
			},
			
			
			/*
			 * Function: _getTransitionEndEventLabel
			 */
			_getTransitionEndEventLabel: function(){
				
				return (document.documentElement.style.WebkitTransition !== undefined) ? "webkitTransitionEnd" : "transitionend";
				
			},
			
			
			_getTransitionPrefix: function(){
				
				return (document.documentElement.style.WebkitTransition !== undefined) ? "webkitTransition" : (document.documentElement.style.MozTransition !== undefined) ? "MozTransition" : "transition";
				
			},
			
			
			/*
			 * Function: stopFade
			 */
			stopFade: function(el){
				
				var fadeCallback = Util.getElementData(el, 'transitionEndEvent');
				if (Util.isNothing(fadeCallback)){
					return;
				}
				
				Util.DOM.removeEventListener(
					el, 
					Util.Animation._getTransitionEndEventLabel(), 
					Util.getElementData(el, 'transitionEndEvent')
				);
				
				var currentOpacity = window.getComputedStyle(el,'').getPropertyValue('opacity');
							
				Util.Animation._removeTransitions(el);
				
				Util.DOM.setStyle(el, 'opacity', currentOpacity);
				
			},
			
			
			/*
			 * Function: fadeIn
			 * Fades an element in.
			 * Make sure the element is displayed before calling
			 */
			fadeIn: function(el, opacity, duration, callback){
					
				opacity = Util.coalesce(opacity, 1);
				duration = Util.coalesce(duration, 500);
				
				Util.setElementData(el, 'transitionEndCallback', callback);
				
				Util.Animation._setTransition(el, 'opacity', duration + 'ms', function(el){
					
					Util.Animation._setTransitionEndEventListener(el);
					Util.DOM.setStyle(el, 'opacity', opacity);
					
				});
				
			},
			
			
			/*
			 * Function: fadeOut
			 * Fades an element out
			 * Make sure the element is displayed before calling
			 * Does not "hide" the element when animation is over
			 */
			fadeOut: function(el, duration, callback){
				
				if (Util.isNothing(duration)){
					duration = 500;
				}
				
				Util.setElementData(el, 'transitionEndCallback', callback);
				
				Util.Animation._setTransition(el, 'opacity', duration + 'ms', function(el){
					
					Util.Animation._setTransitionEndEventListener(el);
					Util.DOM.setStyle(el, 'opacity', 0);
				
				});
				
			},
			
			
			
			/*
			 * Function: slideTo
			 * Slides an element by an x,y position
			 */
			slideBy: function(el, xPos, yPos, duration, callback){
				
				if (Util.isNothing(duration)){
					duration = 500;
				}
				
				/* Store some values against the element for later use */
				Util.setElementData(el, 'transitionEndCallback', Util.Animation._onSlideByEnd);
				Util.setElementData(el, 'slideByCallback', callback);
				Util.setElementData(el, 'slideByXPos', xPos);
				Util.setElementData(el, 'slideByYPos', yPos);
				
				//ease-in-out
				Util.Animation._setTransition(el, 'all', duration + 'ms', 'ease-in', 0, function(el){
					
					Util.Animation._setTransitionEndEventListener(el);
					
					var 
						xPos = Util.getElementData(el, 'slideByXPos'),
						yPos = Util.getElementData(el, 'slideByYPos');
					
					Util.removeElementData(el, 'slideByXPos');
					Util.removeElementData(el, 'slideByYPos');
					
					
					if (Util.browser.webkit){
						if (Util.browser.is3dSupported){
							Util.DOM.setStyle(el, { webkitTransform: 'translate3d(' + xPos + 'px, ' + yPos + 'px, 0)'});
						}
						else{
							Util.DOM.setStyle(el, { webkitTransform: 'translate(' + xPos + 'px, ' + yPos + 'px)'});
						}
					}
					else {
						Util.DOM.setStyle(el, {
							webkitTransform: 'translate(' + xPos + 'px, ' + yPos + 'px)',
							MozTransform: 'translate(' + xPos + 'px, ' + yPos + 'px)',
							transform: 'translate(' + xPos + 'px, ' + yPos + 'px)'
						});
					}
					
				});
				
			},
			
			
			
			_onSlideByEnd: function(e){
					
				// Reset the real css top and left after the transformation
				var 
					el = e.target,
					
					callback = Util.getElementData(el, 'slideByCallback'),
					
					transform = Util.coalesce(el.style.webkitTransform, el.style.MozTransform, el.style.transform),
					
					transformExploded = transform.match( /\((.*?)\)/ )[1].split(', '),
					
					transformedX = window.parseInt(transformExploded[0]),
					
					transformedY = window.parseInt(transformExploded[1]),
					
					domX = window.parseInt(Util.DOM.getStyle(el, 'left')),
					
					domY = window.parseInt(Util.DOM.getStyle(el, 'top'));
				
				Util.DOM.setStyle(el, {
					webkitTransform: '',
					MozTransform: '',
					transform: '',
					left: (domX + transformedX) + 'px',
					top: (domY + transformedY) + 'px'
				});
				
				
				Util.removeElementData(el, 'slideByCallback');
				Util.removeElementData(el, 'slideByXPos');
				Util.removeElementData(el, 'slideByYPos');
				
				if (Util.isFunction(callback)){
					window.setTimeout(
						function(){
							callback(e);
						},
						Util.Animation._applyTransitionDelay
					);
					//window.setTimeout(callback, Util.Animation._applyTransitionDelay, e);
				}
				
			}
			
			
		}
		
		
	});
	
	
})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function () {
	
	// Based on http://www.javascriptbank.com/how-build-custom-events-javascript.html
	Code.PhotoSwipe.EventClass = SimpleClass.extend({
		
		_listeners: null,
		
		init: function(){
			
			this._listeners = {};
		
		},
		
		
		addEventListener: function(type, listener){
			
			if (typeof this._listeners[type] === 'undefined'){
				this._listeners[type] = [];
 			}
			this._listeners[type].push(listener);
			
		},
		
		
		dispatchEvent: function(event){
			if (typeof event == "string"){
				event = { type: event };
			}
			if (!event.target){
				event.target = this;
			}

			if (!event.type){ 
				throw new Error("Event object missing 'type' property.");
			}

			if (this._listeners[event.type] instanceof Array){
				var listeners = this._listeners[event.type];
				for (var i=0, len=listeners.length; i < len; i++){
					listeners[i].call(this, event);
				}
			}
		},
		

		removeEventListener: function(type, listener){
			if (this._listeners[type] instanceof Array){
				var listeners = this._listeners[type];
				for (var i=0, len=listeners.length; i < len; i++){
					if (listeners[i] === listener){
						listeners.splice(i, 1);
						break;
					}
				}
			}
		}
		
	});
	

})();
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function (window, Util) {
	
	/*
	 * Class: Code.PhotoSwipe.ElementClass
	 * Most PhotoSwipe classes inherit from this class
	 * Provides hooks for fading in and out
	 */
	Code.PhotoSwipe.ElementClass = Code.PhotoSwipe.EventClass.extend({
		
		el: null,
		settings: null,
		isHidden: null,
		
		fadeInHandler: null,
		fadeOutHandler: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this._super();
			
			this.settings = {
				opacity: 1,
				fadeInSpeed: 250,
				fadeOutSpeed: 500
			};
			
			Util.extend(this.settings, options);
			
			this.fadeInHandler = this.postFadeIn.bind(this);
			this.fadeOutHandler = this.postFadeOut.bind(this);
			this.isHidden = true;
			
		},
		
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
		},
		
		
		
		/*
		 * Function: show
		 */
		show: function(){
			
			this.stopFade();
						
			// Show
			Util.DOM.setStyle(this.el, 'opacity', this.settings.opacity);
			Util.DOM.show(this.el);
			
			this.postShow();
			
		},
		
		
		
		/*
		 * Function: postShow
		 * Overide this 
		 */
		postShow: function(){
			
			this.isHidden = false;
			this.addEventListeners();		
			this.dispatchEvent(Code.PhotoSwipe.ElementClass.EventTypes.onShow);
		
		},
		
	
		
		/*
		 * Function: fadeIn
		 */
		fadeIn: function(){
			
			Util.DOM.setStyle(this.el, 'opacity', 0);
			
			this.fadeInFromCurrentOpacity();
			
		},
		
		
		
		/*
		 * Function: fadeInFromCurrentOpacity
		 */
		fadeInFromCurrentOpacity: function(){
			
			this.stopFade();
			
			this.isHidden = false;
			
			// Fade in
			Util.DOM.show(this.el);
			Util.Animation.fadeIn(
				this.el, 
				this.settings.opacity, 
				this.settings.fadeInSpeed, 
				this.fadeInHandler
			);
			
		},
		
		
				
		/*
		 * Function: postFadeIn
		 */
		postFadeIn: function(e){
			
			if (this.isHidden){
				return;
			}
			
			this.addEventListeners();			
			this.dispatchEvent(Code.PhotoSwipe.ElementClass.EventTypes.onFadeIn);
			
		},
		
	
				
		/*
		 * Function: hide
		 */
		hide: function(){
					
			this.stopFade();
			
			Util.DOM.hide(this.el);
			
			this.postHide();
			
		},
		
		
		/*
		 * Function: postHide
		 * Overide this 
		 */
		postHide: function(){
			
			this.isHidden = true;
			this.removeEventListeners();	
			this.dispatchEvent(Code.PhotoSwipe.ElementClass.EventTypes.onHide);
			
		},
		
		
		/*
		 * Fuction: fadeOut
		 */
		fadeOut: function(){
			
			this.stopFade();
				
			this.isHidden = true;
						
			Util.Animation.fadeOut(this.el, this.settings.fadeOutSpeed, this.fadeOutHandler);
			
		},
		
		
		
		
		/*
		 * Function: preFadeOut
		 */
		postFadeOut: function(e){
			
			if (!this.isHidden){
				return;
			}
			
			Util.DOM.hide(this.el);
			this.removeEventListeners();
			
			this.dispatchEvent(Code.PhotoSwipe.ElementClass.EventTypes.onFadeOut);
			
		},
		
		
		
		/*
		 * Function: stopFade
		 */
		stopFade: function(){
			
			Util.Animation.stopFade(this.el);
		
		},
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
					
		},
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
						
		}
		
		
	});
	
	
	
	Code.PhotoSwipe.ElementClass.EventTypes = {
		onShow: 'onShow',
		onHide: 'onHide',
		onClick: 'onClick',
		onFadeIn: 'onFadeIn',
		onFadeOut: 'onFadeOut'
	};
	

})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	
	/*
	 * Class: Code.PhotoSwipe.FullSizeImageClass
	 */
	Code.PhotoSwipe.FullSizeImageClass = Code.PhotoSwipe.EventClass.extend({
		
		el: null,
		index: null,
		
		// The naturalWidth and naturalHeight of the image as loaded from the server
		// This maybe different from the width and height set on the img element
		// We need this to scale the image correctly
		naturalWidth: null,
		naturalHeight: null,
		src: null,
		caption: null,
		metaData: null,
		scaleMethod: null,
		isLandscape: null,
		isLoading: null,
		hasLoaded: null,
		
		loadEventHandler: null,
		
		
		/*
		 * Function: init
		 */
		init: function(index, scaleMethod, src, caption, metaData){
			
			this._super();
			
			this.index = index;
			this.naturalWidth = 0;
			this.naturalHeight = 0;
			this.src = src;
			this.caption = caption;
			this.metaData = Util.coalesce(metaData, {});
			this.isLandscape = false;
			this.isLoading = false;
			this.hasLoaded = false;
			this.scaleMethod = scaleMethod;
			
			this.loadEventHandler = this.onLoad.bind(this);
			
		},
		
		
		/*
		 * Function: load
		 */
		load: function(){
			
			// Load in the image
			this.isLoading = true;
			
			this.el = new Image();
			Util.DOM.addClass(this.el, 'ps-full-size-image');
			this.el.onload = this.loadEventHandler;
			this.el.src = this.src;
			
		},
		
		
		/*
		 * Function: onLoad
		 */
		onLoad: function(){
			
			this.naturalWidth = Util.coalesce(this.el.naturalWidth, this.el.width);
			this.naturalHeight = Util.coalesce(this.el.naturalHeight, this.el.height);
			this.isLandscape = (this.naturalWidth > this.naturalHeight);
			this.isLoading = false;
			this.hasLoaded = true;
			
			this.dispatchEvent(Code.PhotoSwipe.FullSizeImageClass.EventTypes.onLoad);
			
		}
	
	
	});
	
	
	Code.PhotoSwipe.FullSizeImageClass.EventTypes = {
		onLoad: 'onLoad'
	};
	

})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	/*
	 * Class: Code.PhotoSwipe.DocumentOverlayClass
	 */
	Code.PhotoSwipe.DocumentOverlayClass = Code.PhotoSwipe.ElementClass.extend({
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this.settings = {
				zIndex: 1000
			};
			
			Util.extend(this.settings, options);
			
			this._super(options);
			
			// Create element and append to body
			this.el = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.DocumentOverlayClass.CssClasses.documentOverlay }, '');
			Util.DOM.setStyle(this.el, {
				left: 0,
				position: 'absolute',
				zIndex: this.settings.zIndex,
				top: 0
			});
			Util.DOM.hide(this.el);
			Util.DOM.appendToBody(this.el);
			
		},
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			// Set the height and width to fill the document
			Util.DOM.width(this.el, Util.DOM.bodyWidth());
			Util.DOM.height(this.el, Util.DOM.bodyHeight());
			
		}
	
	});
	
	
	Code.PhotoSwipe.DocumentOverlayClass.CssClasses = {
		documentOverlay: 'ps-document-overlay'
	};

})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	/*
	 * Class: Code.PhotoSwipe.ViewportClass
	 */
	Code.PhotoSwipe.ViewportClass = Code.PhotoSwipe.ElementClass.extend({
		
		touchStartPoint: null,
		
		touchStartTime: null,
		touchStartHandler: null,
		touchMoveHandler: null,
		touchEndHandler: null,
		
		gestureStartHandler: null,
		gestureChangeHandler: null,
		gestureEndHandler: null,
		
		isGesture: null,
		
		mouseDownHandler: null,
		mouseUpHandler: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this.settings = {
				swipeThreshold: 500,
				swipeTimeThreshold: 250,
				zIndex: 1000
			};
			
			Util.extend(this.settings, options);
			
			this._super(this.settings);
			
			this.touchStartPoint = { x: 0, y: 0 };
			
			if (Util.browser.touchSupported){
				this.touchStartHandler = this.onTouchStart.bind(this);
				this.touchMoveHandler = this.onTouchMove.bind(this);
				this.touchEndHandler = this.onTouchEnd.bind(this);
			}
			
			if (Util.browser.gestureSupported){
				this.gestureStartHandler = this.onGestureStart.bind(this);
				this.gestureChangeHandler = this.onGestureChange.bind(this);
				this.gestureEndHandler = this.onGestureEnd.bind(this);
			}
			
			this.mouseDownHandler = this.onMouseDown.bind(this);
			this.mouseUpHandler = this.onMouseUp.bind(this);
			
			// Create element and append to body
			this.el = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ViewportClass.CssClasses.viewport, 'data-role': 'dialog' }, '');
			Util.DOM.setStyle(this.el, {
				position: 'absolute',
				left: 0,
				zIndex: this.settings.zIndex,
				overflow: 'hidden'
			});
			Util.DOM.hide(this.el);
			Util.DOM.appendToBody(this.el);

		},
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			// Set the height and width to fill the document
			Util.DOM.setStyle(this.el, {
				top: Util.DOM.windowScrollTop()  + 'px'
			});
			
			Util.DOM.width(this.el, Util.DOM.bodyWidth());
			Util.DOM.height(this.el, Util.DOM.windowHeight());

		},
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
						
			if (Util.browser.touchSupported){
				Util.DOM.addEventListener(this.el, 'touchstart', this.touchStartHandler);
				Util.DOM.addEventListener(this.el, 'touchmove', this.touchMoveHandler);
				Util.DOM.addEventListener(this.el, 'touchend', this.touchEndHandler);
			}
			
			if (Util.browser.gestureSupported){
				Util.DOM.addEventListener(this.el, 'gesturestart', this.gestureStartHandler);
				Util.DOM.addEventListener(this.el, 'gesturechange', this.gestureChangeHandler);
				Util.DOM.addEventListener(this.el, 'gestureend', this.gestureEndHandler);
			}
			
			Util.DOM.addEventListener(this.el, 'mousedown', this.mouseDownHandler);
			Util.DOM.addEventListener(this.el, 'mouseup', this.mouseUpHandler);
			
		},
		
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			if (Util.browser.touchSupported){
				Util.DOM.removeEventListener(this.el, 'touchstart', this.touchStartHandler);
				Util.DOM.removeEventListener(this.el, 'touchmove', this.touchMoveHandler);
				Util.DOM.removeEventListener(this.el, 'touchend', this.touchEndHandler);
			}
			
			if (Util.browser.gestureSupported){
				Util.DOM.removeEventListener(this.el, 'gesturestart', this.gestureStartHandler);
				Util.DOM.removeEventListener(this.el, 'gesturechange', this.gestureChangeHandler);
				Util.DOM.removeEventListener(this.el, 'gestureend', this.gestureEndHandler);
			}
			
			Util.DOM.removeEventListener(this.el, 'mousedown', this.mouseDownHandler);
			Util.DOM.removeEventListener(this.el, 'mouseup', this.mouseUpHandler);
			
		},
		
		
		
		/*
		 * Function: getTouchPoint
		 */
		getTouchPoint: function(touches){
			
			return {
				x: touches[0].pageX,
				y: touches[0].pageY
			};
			
		},
		
		
		/*
		 * Function: onGestureStart
		 */
		onGestureStart: function(e){
		
			e.preventDefault();
			
			var touchEvent = Util.DOM.getTouchEvent(e);
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.gestureStart,
				scale: touchEvent.scale,
				rotation: touchEvent.rotation
			});
			
		},
		
		
		/*
		 * Function: onGestureChange
		 */
		onGestureChange: function(e){
			
			e.preventDefault();
			
			var touchEvent = Util.DOM.getTouchEvent(e);
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.gestureChange,
				scale: touchEvent.scale,
				rotation: touchEvent.rotation
			});
						
		},
		
		
		/*
		 * Function: onGestureEnd
		 */
		onGestureEnd: function(e){
			
			e.preventDefault();
			
			var touchEvent = Util.DOM.getTouchEvent(e);
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.gestureEnd,
				scale: touchEvent.scale,
				rotation: touchEvent.rotation
			});
			
		},
		
		
		/*
		 * Function: onTouch
		 */
		onTouchStart: function(e){
			
			e.preventDefault();
			
			var 
				touchEvent = Util.DOM.getTouchEvent(e),
				touches = touchEvent.touches;
			
			if (touches.length > 1){
				this.isGesture = true;
				return;
			}
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.touchStart,
				point: this.getTouchPoint(touches)
			});
			
			
			this.touchStartTime = new Date();
			this.isGesture = false;
			this.touchStartPoint = this.getTouchPoint(touches);
			
		},
		
		
		
		/*
		 * Function: onTouchMove
		 * For some reason, even though it's not a requirement,
		 * if we don't listen out for the touchmove event,
		 * we are unable to detect the swipe on Blackberry6
		 */
		onTouchMove: function(e){
			
			e.preventDefault();
			
			if (this.isGesture){
				return;
			}
			
			var 
				touchEvent = Util.DOM.getTouchEvent(e),
				touches = touchEvent.touches;
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.touchMove,
				point: this.getTouchPoint(touches)
			});
			
		},
		
		
		
		/*
		 * Function: onTouchEnd
		 */
		onTouchEnd: function(e){
			
			e.preventDefault();
			
			if (this.isGesture){
				return;
			}
			
			// http://backtothecode.blogspot.com/2009/10/javascript-touch-and-gesture-events.html
			// iOS removed the current touch from e.touches on "touchend"
			// Need to look into e.changedTouches
			
			var 
				touchEvent = Util.DOM.getTouchEvent(e),
				touches = (!Util.isNothing(touchEvent.changedTouches)) ? touchEvent.changedTouches : touchEvent.touches,
				touchEndPoint = this.getTouchPoint(touches);
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: Code.PhotoSwipe.ViewportClass.Actions.touchEnd,
				point: touchEndPoint
			});
			
			this.fireTouchEvent(this.touchStartPoint, touchEndPoint);
			
		},
		
		
		
		/*
		 * Function: onMouseDown
		 */
		onMouseDown: function(e){
			
			e.preventDefault();
			
			this.touchStartTime = new Date();
			this.isGesture = false;
			this.touchStartPoint = Util.DOM.getMousePosition(e);
			
		},
		
		
		
		/*
		 * Function: onMouseUp
		 */
		onMouseUp: function(e){
		
			e.preventDefault();
			this.fireTouchEvent(this.touchStartPoint, Util.DOM.getMousePosition(e));
			
		},
		
		
		
		/*
		 * Function: fireTouchEvent
		 */
		fireTouchEvent: function(touchStartPoint, touchEndPoint){
			
			var action;
			
			var 
				endTime = new Date(),
				diffTime = endTime - this.touchStartTime;
			
			if (diffTime > this.settings.swipeTimeThreshold){
				return;
			}
			
			var distance = touchEndPoint.x - touchStartPoint.x;
				
			if (Math.abs(distance) >= this.settings.swipeThreshold){
			
				if (distance < 0){
					
					// Swipe left
					action = Code.PhotoSwipe.ViewportClass.Actions.swipeLeft;
					
				}
				else{
					
					// Swipe right
					action = Code.PhotoSwipe.ViewportClass.Actions.swipeRight;
					
				}
				
			}
			else{
				
				// Click
				action = Code.PhotoSwipe.ViewportClass.Actions.click;
			
			}
			
			if (Util.isNothing(action)){
				return;
			}
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ViewportClass.EventTypes.onTouch, 
				target: this, 
				action: action 
			});
			
		}
		
	});
	
	
	Code.PhotoSwipe.ViewportClass.CssClasses = {
		viewport: 'ps-viewport'
	};
	
	
	Code.PhotoSwipe.ViewportClass.Actions = {
		click: 'click',
		swipeLeft: 'swipeLeft',
		swipeRight: 'swipeRight',
		touchStart: 'touchStart',
		touchMove: 'touchMove',
		touchEnd: 'touchEnd',
		gestureStart: 'gestureStart',
		gestureChange: 'gestureChange',
		gestureEnd: 'gestureEnd'
	};
	
	
	Code.PhotoSwipe.ViewportClass.EventTypes = {
		onTouch: 'onTouch'
	};
	
	
})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util, FullSizeImageClass){

	/*
	 * Class: Code.PhotoSwipe.SliderItemClass
	 */
	Code.PhotoSwipe.SliderItemClass = Code.PhotoSwipe.EventClass.extend({
		
		el: null,
		imageContainerEl: null,
		imageEl: null,
		parentEl: null,
		fullSizeImage: null,
		
		fullSizeImageLoadEventHandler: null,
		
		savedImageWidth: null,
		savedImageHeight: null,
		
		
		/*
		 * Function: init
		 */
		init: function(parentEl){
			
			this._super();
			
			this.parentEl = parentEl;	
						
			this.fullSizeImageLoadEventHandler = this.onFullSizeImageLoad.bind(this);
			
			// Create element and append to parentEl
			this.el = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.SliderItemClass.CssClasses.item + ' ' + Code.PhotoSwipe.SliderItemClass.CssClasses.loading }, '');
			Util.DOM.setStyle(this.el, {
				position: 'absolute',
				overflow: 'hidden',
				top: 0
			});
			Util.DOM.resetTranslate(this.el);
			Util.DOM.appendChild(this.el, this.parentEl);
			
			this.imageContainerEl = Util.DOM.createElement('div');
			Util.DOM.setStyle(this.imageContainerEl, {
				position: 'absolute',
				overflow: 'hidden',
				top: 0,
				left: 0
			});
			Util.DOM.appendChild(this.imageContainerEl, this.el);
			
			// Create image element and append to slider item
			this.imageEl = new Image();
			Util.DOM.setStyle(this.imageEl, {
				display: 'block',
				position: 'absolute',
				margin: 0,
				padding: 0
			});
			Util.DOM.hide(this.imageEl);
			Util.DOM.appendChild(this.imageEl, this.imageContainerEl);
			
		},
		
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(width, height, xPos){
			
			Util.DOM.width(this.el, width);
			Util.DOM.height(this.el, height);
			Util.DOM.setStyle(this.el, 'left', xPos + 'px');
			
			Util.DOM.width(this.imageContainerEl, width);
			Util.DOM.height(this.imageContainerEl, height);
			
			this.resetImagePosition();
			
		},
		
				
		
		/*
		 * Function: resetImagePosition
		 */
		resetImagePosition: function(){
			
			if (Util.isNothing(this.fullSizeImage)){
				return;
			}
			
			var src = Util.DOM.getAttribute(this.imageEl, 'src');
			
			var 
				scale, 
				newWidth, 
				newHeight, 
				newTop, 
				newLeft,
				maxWidth = Util.DOM.width(this.el),
				maxHeight = Util.DOM.height(this.el);
			
			if (this.fullSizeImage.isLandscape) {
				// Ensure the width fits the screen
				scale = maxWidth / this.fullSizeImage.naturalWidth;
			}
			else {
				// Ensure the height fits the screen
				scale = maxHeight / this.fullSizeImage.naturalHeight;
			}
			
			newWidth = Math.round(this.fullSizeImage.naturalWidth * scale);
			newHeight = Math.round(this.fullSizeImage.naturalHeight * scale);
			
			if (this.fullSizeImage.scaleMethod === 'zoom'){
				
				scale = 1;
				if (newHeight < maxHeight){
					scale = maxHeight /newHeight;	
				}
				else if (newWidth < maxWidth){
					scale = maxWidth /newWidth;	
				}
				
				if (scale !== 1) {
					newWidth = Math.round(newWidth * scale);
					newHeight = Math.round(newHeight * scale);
				}
				
			}
			else if (this.fullSizeImage.scaleMethod === 'fit') {
				// Rescale again to ensure full image fits into the viewport
				scale = 1;
				if (newWidth > maxWidth) {
					scale = maxWidth / newWidth;
				}
				else if (newHeight > maxHeight) {
					scale = maxHeight / newHeight;
				}
				if (scale !== 1) {
					newWidth = Math.round(newWidth * scale);
					newHeight = Math.round(newHeight * scale);
				}
			}
			
			newTop = ((maxHeight - newHeight) / 2) + 'px';
			newLeft = ((maxWidth - newWidth) / 2) + 'px';
			
			Util.DOM.width(this.imageEl, newWidth);
			Util.DOM.height(this.imageEl, newHeight);
			Util.DOM.setStyle(this.imageEl, {
				top: newTop,
				left: newLeft
			});
			
			Util.DOM.show(this.imageEl);
			
			this.savedImageWidth = newWidth;
			this.savedImageHeight = newHeight;
		},
		
		
		
		/*
		 * Function: setFullSizeImage
		 */
		setFullSizeImage: function(fullSizeImage){
			
			this.fullSizeImage = fullSizeImage;
			
			Util.DOM.removeClass(this.el, Code.PhotoSwipe.SliderItemClass.CssClasses.loading);
			Util.DOM.removeClass(this.el, Code.PhotoSwipe.SliderItemClass.CssClasses.imageError);
			
			// Something is wrong!
			if (Util.isNothing(this.fullSizeImage)) {
				this.fullSizeImage = null;
				Util.DOM.addClass(this.el, Code.PhotoSwipe.SliderItemClass.CssClasses.imageError);
				this.hideImage();
				return;
			}
						
			// Still loading
			if (!this.fullSizeImage.hasLoaded) {
				
				Util.DOM.addClass(this.el, Code.PhotoSwipe.SliderItemClass.CssClasses.loading);
				this.hideImage();
				
				if (!this.fullSizeImage.isLoading){
				
					// Trigger off the load
					this.fullSizeImage.addEventListener(
						FullSizeImageClass.EventTypes.onLoad, 
						this.fullSizeImageLoadEventHandler
					);
				
				
					this.fullSizeImage.load();
					
				}
				
				return;
			
			}
			
			// Loaded so show the image
			Util.DOM.setAttribute(this.imageEl, 'src', this.fullSizeImage.src);
			
			this.resetImagePosition();
	
			this.dispatchEvent(Code.PhotoSwipe.SliderItemClass.EventTypes.onFullSizeImageDisplay);
			
		},
		
		
		/*
		 * Function: onFullSizeImageLoad
		 */
		onFullSizeImageLoad: function(e){
			
			e.target.removeEventListener(FullSizeImageClass.EventTypes.onLoad, this.fullSizeImageLoadEventHandler);
			
			if (Util.isNothing(this.fullSizeImage) || e.target.index !== this.fullSizeImage.index){
				// Chances are the user has moved the slider
				// and the image to display in the item has now changed
				// from when the item originally called the fullSizeImage
				// to load. If that's the case, rethrow the event, the 
				// slider will be listening for this and can find a
				// relevant slideitem for the loaded image
				this.dispatchEvent({ 
					type: Code.PhotoSwipe.SliderItemClass.EventTypes.onFullSizeImageLoadAnomaly, 
					target: this, 
					fullSizeImage: e.target 
				});
			}
			else{
				this.setFullSizeImage(e.target);
			}
			
		},
		
		
		/*
		 * Function: hideImage
		 */
		hideImage: function(){
			
			Util.DOM.removeAttribute(this.imageEl, 'src');
			Util.DOM.hide(this.imageEl);
			
		}
	
	
	});

	
	Code.PhotoSwipe.SliderItemClass.CssClasses = {
		item: 'ps-slider-item',
		loading: 'ps-slider-item-loading',
		imageError: 'ps-slider-item-image-error'
	};

	
	Code.PhotoSwipe.SliderItemClass.EventTypes = {
		onFullSizeImageDisplay: 'onFullSizeImageDisplay',
		onFullSizeImageLoadAnomaly: 'onFullSizeImageLoadAnomaly'
	};
	
	
})
(
	window,
	Code.PhotoSwipe.Util, 
	Code.PhotoSwipe.FullSizeImageClass
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util, SliderItemClass){

	/*
	 * Class: Code.PhotoSwipe.SliderClass
	 */
	Code.PhotoSwipe.SliderClass = Code.PhotoSwipe.ElementClass.extend({
		
		parentEl: null,
		parentElWidth: null,
		parentElHeight: null,
		items: null,
		scaleEl: null,
		
		lastScaleValue: null,
		
		previousItem: null,
		currentItem: null,
		nextItem: null,
		
		hasBounced: null,
		lastShowAction: null,
		bounceSlideBy: null,
		
		showNextEndEventHandler: null,
		showPreviousEndEventHandler: null,
		bounceStepOneEventHandler: null,
		bounceStepTwoEventHandler: null,
		
		sliderFullSizeImageLoadAnomalyEventHandler: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options, parentEl){
			
			this.settings = {
				slideSpeed: 250
			};
			
			Util.extend(this.settings, options);
			
			this._super(this.settings);
			
			this.parentEl = parentEl;
				
			this.hasBounced = false;
			
			this.showNextEndEventHandler = this.onShowNextEnd.bind(this);
			this.showPreviousEndEventHandler = this.onShowPreviousEnd.bind(this);
			this.bounceStepOneEventHandler = this.onBounceStepOne.bind(this);
			this.bounceStepTwoEventHandler = this.onBounceStepTwo.bind(this);
			
			this.sliderFullSizeImageLoadAnomalyEventHandler = this.onSliderFullSizeImageLoadAnomaly.bind(this);
				
			// Create element and append to body
			this.el = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.SliderClass.CssClasses.slider }, '');
			Util.DOM.setStyle(this.el, {
				position: 'absolute',
				top: 0
			});
			Util.DOM.hide(this.el);
			Util.DOM.appendChild(this.el, parentEl);
			
			// Create previousItem, currentItem, nextItem
			this.items = [];
			this.items.push(new SliderItemClass(this.el));
			this.items.push(new SliderItemClass(this.el));
			this.items.push(new SliderItemClass(this.el));
			
			this.previousItem = this.items[0];
			this.currentItem = this.items[1];
			this.nextItem = this.items[2];
			
		},
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
			
			for (var i = 0; i<this.items.length; i++){
				
				var item = this.items[i];
				
				item.addEventListener(
					SliderItemClass.EventTypes.onFullSizeImageLoadAnomaly,
					this.sliderFullSizeImageLoadAnomalyEventHandler
				);
				
			}
			
		},
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			for (var i = 0; i<this.items.length; i++){
				
				var item = this.items[i];
				
				item.removeEventListener(
					SliderItemClass.EventTypes.onFullSizeImageLoadAnomaly,
					this.sliderFullSizeImageLoadAnomalyEventHandler
				);
			
			}
			
		},
		

		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			Util.DOM.show(this.currentItem.imageContainerEl);
			
			this.parentElWidth = Util.DOM.width(this.parentEl);
			this.parentElHeight = Util.DOM.height(this.parentEl);
			
			// Set the width and height to the parentEl it is bound to
			Util.DOM.width(this.el, this.parentElWidth * 3);
			Util.DOM.height(this.el, this.parentElHeight);
			
			// Re-position items
			this.previousItem.resetPosition(this.parentElWidth, this.parentElHeight, 0);
			this.currentItem.resetPosition(this.parentElWidth, this.parentElHeight, this.parentElWidth);
			this.nextItem.resetPosition(this.parentElWidth, this.parentElHeight, this.parentElWidth * 2);
			
			// Center the slider in the parentEl
			this.center();
			
		},
		
		
		
		/*
		 * Function: center
		 */
		center: function(){
			
			Util.DOM.resetTranslate(this.el);
			
			Util.DOM.setStyle(this.el, {
				left: (this.parentElWidth * -1) + 'px'
			});
			
		},
		
		
		
		/*
		 * Function: setCurrentFullSizeImage
		 */
		setCurrentFullSizeImage: function (currentFullSizeImage) {
			
			this.currentItem.setFullSizeImage(currentFullSizeImage);
			
			this.dispatchDisplayCurrentFullSizeImage();
			
		},
		
		
		/*
		 * Function: setPreviousAndNextFullSizeImages
		 */
		setPreviousAndNextFullSizeImages: function (previousFullSizeImage, nextFullSizeImage) {
			
			this.nextItem.setFullSizeImage(nextFullSizeImage);
			this.previousItem.setFullSizeImage(previousFullSizeImage);
			
		},
		
		
		/*
		 * Function: showNext
		 */
		showNext: function(){
						
			this.lastShowAction = Code.PhotoSwipe.SliderClass.ShowActionTypes.next;
			this.hasBounced = false;
			
			if (Util.isNothing(this.nextItem.fullSizeImage)) {
				// Do a bounce effect
				this.bounce();	
				return;
			}
			
			var slideBy = this.parentElWidth * -1;
			
			Util.Animation.slideBy(this.el, slideBy, 0, this.settings.slideSpeed, this.showNextEndEventHandler);
		
		},
		
		
		/*
		 * Function: 
		 */
		showPrevious: function(){
			
			this.lastShowAction = Code.PhotoSwipe.SliderClass.ShowActionTypes.previous;
			this.hasBounced = false;
			
			if (Util.isNothing(this.previousItem.fullSizeImage)) {
				// Do a bounce effect
				this.bounce();	
				return;
			}
			
			var slideBy = this.parentElWidth;
			
			Util.Animation.slideBy(this.el, slideBy, 0, this.settings.slideSpeed, this.showPreviousEndEventHandler);
		
		},
		
		
		/*
		 * Function: bounce
		 */
		bounce: function () {
			
			Util.DOM.show(this.currentItem.imageContainerEl);
			
			this.hasBounced = true;
			
			this.bounceSlideBy = this.parentElWidth / 2;
			
			Util.Animation.slideBy(
				this.el, 
				(this.lastShowAction === Code.PhotoSwipe.SliderClass.ShowActionTypes.previous) ? this.bounceSlideBy : this.bounceSlideBy * -1, 
				0, 
				this.settings.slideSpeed, 
				this.bounceStepOneEventHandler
			);
			
		},
		
		
		/*
		 * Function: onBounceStepOne
		 */
		onBounceStepOne: function(e){
		
			Util.Animation.slideBy(
				this.el, 
				(this.lastShowAction === Code.PhotoSwipe.SliderClass.ShowActionTypes.previous) ? this.bounceSlideBy * -1 : this.bounceSlideBy, 
				0, 
				this.settings.slideSpeed, 
				this.bounceStepTwoEventHandler
			);
			
		},
		
		
		/*
		 * Function: onBounceStepTwo
		 */
		onBounceStepTwo: function(e){
			
			this.dispatchDisplayCurrentFullSizeImage();
			
		},
		
		
		
		/*
		 * Function: onShowNextEnd
		 */
		onShowNextEnd: function(){
			
			Util.DOM.show(this.currentItem.imageContainerEl);
						
			// Swap the next and current around, then re-center the slider
			Util.swapArrayElements(this.items, 1, 2);
			
			this.currentItem = this.items[1];
			this.nextItem = this.items[2];
			
			var parentElWidth = this.parentElWidth;
			Util.DOM.setStyle(this.currentItem.el, 'left', parentElWidth + 'px');
			Util.DOM.setStyle(this.nextItem.el, 'left', (parentElWidth*2) + 'px');
			
			this.center();
			
			this.dispatchDisplayCurrentFullSizeImage();
			
		},
		
		
		/*
		 * Function: onShowPreviousEnd
		 */
		onShowPreviousEnd: function(){
			
			Util.DOM.show(this.currentItem.imageContainerEl);
			
			// Swap the previous and current around, then re-center the slider
			Util.swapArrayElements(this.items, 1, 0);
			
			this.currentItem = this.items[1];
			this.previousItem = this.items[0];
			
			Util.DOM.setStyle(this.currentItem.el, 'left', this.parentElWidth + 'px');
			Util.DOM.setStyle(this.previousItem.el, 'left', '0px');
			
			this.center();
			
			this.dispatchDisplayCurrentFullSizeImage();
			
		},
		
		
		
		/*
		 * Function: onSliderFullSizeImageLoadAnomaly
		 * This is fired when a slider item has loaded an
		 * image, but the image loaded is not what it currently
		 * should be displaying
		 */
		onSliderFullSizeImageLoadAnomaly: function(e){
			
			var fullSizeImage = e.fullSizeImage;
			
			if (!Util.isNothing(this.currentItem.fullSizeImage)) {
				if (this.currentItem.fullSizeImage.index === fullSizeImage.index) {
					this.currentItem.setFullSizeImage(fullSizeImage);
					this.dispatchDisplayCurrentFullSizeImage();
					return;
				}
			}
			
			if (!Util.isNothing(this.nextItem.fullSizeImage)) {
				if (this.nextItem.fullSizeImage.index === fullSizeImage.index) {
					this.nextItem.setFullSizeImage(fullSizeImage);
					return;
				}
			}
			
			if (!Util.isNothing(this.previousItem.fullSizeImage)) {
				if (this.previousItem.fullSizeImage.index === fullSizeImage.index) {
					this.previousItem.setFullSizeImage(fullSizeImage);
					return;
				}
			}
		
		},
		
		
		/*
		 * Function: dispatchDisplayCurrentFullSizeImage
		 */
		dispatchDisplayCurrentFullSizeImage: function(){
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.SliderClass.EventTypes.onDisplayCurrentFullSizeImage, 
				target: this, 
				fullSizeImage: this.currentItem.fullSizeImage 
			});
			
		}
		
	
	});

	Code.PhotoSwipe.SliderClass.CssClasses = {
		slider: 'ps-slider'
	};
	
	Code.PhotoSwipe.SliderClass.ShowActionTypes = {
		
		next: 'next',
		previous: 'previous'
	
	};
	
	Code.PhotoSwipe.SliderClass.EventTypes = {
		
		onDisplayCurrentFullSizeImage: 'onDisplayCurrentFullSizeImage'
	
	};
	
	
})
(
	window,
	Code.PhotoSwipe.Util, 
	Code.PhotoSwipe.SliderItemClass
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	/*
	 * Class: Code.PhotoSwipe.CaptionClass
	 */
	Code.PhotoSwipe.CaptionClass = Code.PhotoSwipe.ElementClass.extend({
		
		contentEl: null,
		
		touchMoveHandler: null,
		
		captionValue: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this.settings = {
				position: 'top',
				zIndex: 1000
			};
			
			Util.extend(this.settings, options);
			
			this._super(this.settings);
			
			this.captionValue = '';
			
			this.touchMoveHandler = this.onTouchMove.bind(this);
			
			// Create element and append to body
			var cssClass = Code.PhotoSwipe.CaptionClass.CssClasses.caption;
			if (this.settings.position === 'bottom'){
				cssClass = cssClass + ' ' + Code.PhotoSwipe.CaptionClass.CssClasses.bottom;
			}
			
			this.el = Util.DOM.createElement('div', { 'class': cssClass }, '');
			Util.DOM.setStyle(this.el, {
				left: 0,
				position: 'absolute',
				overflow: 'hidden',
				zIndex: this.settings.zIndex,
				opacity: 0
			});
			Util.DOM.hide(this.el);
			Util.DOM.appendToBody(this.el);
			
			this.contentEl = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.CaptionClass.CssClasses.content }, '');
			Util.DOM.appendChild(this.contentEl, this.el);
			
		},
		
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
			
			if (Util.browser.touchSupported){
				Util.DOM.addEventListener(this.el, 'touchmove', this.touchMoveHandler);
			}
			
		},
		
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			if (Util.browser.touchSupported){
				Util.DOM.removeEventListener(this.el, 'touchmove', this.touchMoveHandler);
			}
						
		},
		
		
		
		/*
		 * Function: onTouch
		 */
		onTouchMove: function(e){
			
			e.preventDefault();
			
		},
		
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			var top;
			
			if (this.settings.position === 'bottom') {
				top = Util.DOM.windowHeight() - Util.DOM.outerHeight(this.el) + Util.DOM.windowScrollTop();
			}
			else {
				top = Util.DOM.windowScrollTop();
			}
			
			Util.DOM.setStyle(this.el, 'top', top + 'px');
			Util.DOM.width(this.el, Util.DOM.bodyWidth());
			
		},
		
		
		
		/*
		 * Function: setCaptionValue
		 */
		setCaptionValue: function(captionValue){
		
			Util.DOM.removeChildren(this.contentEl);
			
			captionValue = Util.coalesce(captionValue, '\u00A0');
			
			if (Util.isObject(captionValue)){
				Util.DOM.appendChild(captionValue, this.contentEl);
			}
			else{
				if (captionValue === ''){
					captionValue = '\u00A0';
				}
				Util.DOM.appendText(captionValue, this.contentEl);	
			}
			
			this.captionValue = (captionValue === '\u00A0') ? '' : captionValue;
			
			//Util.DOM.show(this.el);
			//var height = Util.DOM.height(this.el);
			//Util.DOM.hide(this.el);
			//console.log(height);			
			
		}

		
	
	});
	
	
	Code.PhotoSwipe.CaptionClass.CssClasses = {
		caption: 'ps-caption',
		bottom: 'ps-caption-bottom',
		content: 'ps-caption-content'
	};

})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	/*
	 * Class: Code.PhotoSwipe.ToolbarClass
	 */
	Code.PhotoSwipe.ToolbarClass = Code.PhotoSwipe.ElementClass.extend({
		
		closeEl: null,
		previousEl: null, 
		nextEl: null,
		playEl: null,
		
		clickHandler: null,
		touchStartHandler: null,
		touchMoveHandler: null,
		touched: null,
		
		isNextActive: null,
		isPreviousActive: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this.settings = {
				position: 'bottom',
				hideClose: false,
				zIndex: 1000
			};
			
			Util.extend(this.settings, options);
			
			this._super(this.settings);
			
			this.isNextActive = true;
			this.isPreviousActive = true;
			this.touched = false;
			
			this.clickHandler = this.onClick.bind(this);
			
			if (Util.browser.touchSupported){
				this.touchMoveHandler = this.onTouchMove.bind(this);
				this.touchStartHandler = this.onTouchStart.bind(this);
			}
			
			// Create element and append to body
			var cssClass = Code.PhotoSwipe.ToolbarClass.CssClasses.caption;
			if (this.settings.position === 'top'){
				cssClass = cssClass + ' ' + Code.PhotoSwipe.ToolbarClass.CssClasses.top;
			}
			
			this.el = Util.DOM.createElement('div', { 'class': cssClass }, '');
			Util.DOM.setStyle(this.el, {
				left: 0,
				position: 'absolute',
				overflow: 'hidden',
				zIndex: this.settings.zIndex,
				display: 'table',
				opacity: 0
			});
			Util.DOM.hide(this.el);
			Util.DOM.appendToBody(this.el);
			
			
			// Close
			this.closeEl = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ToolbarClass.CssClasses.close }, '<div class="' + Code.PhotoSwipe.ToolbarClass.CssClasses.content + '"></div>');
			
			if (this.settings.hideClose){
				Util.DOM.hide(this.closeEl);
			}
			Util.DOM.appendChild(this.closeEl, this.el);
			
			// Play
			this.playEl = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ToolbarClass.CssClasses.play }, '<div class="' + Code.PhotoSwipe.ToolbarClass.CssClasses.content + '"></div>');
			Util.DOM.appendChild(this.playEl, this.el);
			
			// Previous
			this.previousEl = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ToolbarClass.CssClasses.previous }, '<div class="' + Code.PhotoSwipe.ToolbarClass.CssClasses.content + '"></div>');
			Util.DOM.appendChild(this.previousEl, this.el);
			
			// Next
			this.nextEl = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ToolbarClass.CssClasses.next }, '<div class="' + Code.PhotoSwipe.ToolbarClass.CssClasses.content + '"></div>');
			Util.DOM.appendChild(this.nextEl, this.el);
			
		},
		
		
		/*
		 * Function: postFadeIn
		 */
		postFadeIn: function(e){
			
			if (this.isHidden){
				return;
			}
			
			Util.DOM.setStyle(this.el, { display: 'table' });
			
			this._super(this.settings);
						
		},
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
					
			if (Util.browser.touchSupported){
				// Had an issue with touchstart, animation and Blackberry. BB will default to click
				if (!Util.browser.isBlackberry){
					Util.DOM.addEventListener(this.el, 'touchstart', this.touchStartHandler);
				}
				Util.DOM.addEventListener(this.el, 'touchmove', this.touchMoveHandler);
			}
			
			Util.DOM.addEventListener(this.el, 'click', this.clickHandler);
			
		},
		
				
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			if (Util.browser.touchSupported){
				if (!Util.browser.isBlackberry){
					Util.DOM.removeEventListener(this.el, 'touchstart', this.touchStartHandler);
				}
				Util.DOM.removeEventListener(this.el, 'touchmove', this.touchMoveHandler);
			}
			Util.DOM.removeEventListener(this.el, 'click', this.clickHandler);
			
		},
		
		
		/*
		 * Function: onTouchStart
		 */
		onTouchStart: function(e){
			
			e.preventDefault();
			
			this.touched = true;
			this.handleClick(e);
			
		},
		
		
		/*
		 * Function: onTouchMove
		 */
		onTouchMove: function(e){
			
			e.preventDefault();
						
		},
		
		
		/*
		 * Function: onClick
		 */
		onClick: function(e){
			
			if (this.touched){
				return;
			}
			
			this.handleClick(e);
			
		},
		
		
		
		/*
		 * Function: handleClick
		 */
		handleClick: function(e){
						
			var action;
			
			switch(e.target.parentNode){
				
				case this.previousEl:
					if (this.isPreviousActive){
						action = Code.PhotoSwipe.ToolbarClass.Actions.previous;
					}
					break;
					
				case this.nextEl:
					if (this.isNextActive){
						action = Code.PhotoSwipe.ToolbarClass.Actions.next;
					}
					break;
				
				case this.playEl:
					action = Code.PhotoSwipe.ToolbarClass.Actions.play;
					break;
				
				case this.closeEl:
					action = Code.PhotoSwipe.ToolbarClass.Actions.close;
					break;
			}
			
			if (Util.isNothing(action)){
				return;
			}
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ToolbarClass.EventTypes.onClick, 
				target: this, 
				action: action 
			});
			
		},
		
	
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			var top;
			
			if (this.settings.position === 'bottom') {
				top = Util.DOM.windowHeight() - Util.DOM.outerHeight(this.el) + Util.DOM.windowScrollTop();
			}
			else {
				top = Util.DOM.windowScrollTop();
			}
					
			Util.DOM.setStyle(this.el, 'top', top + 'px');
			Util.DOM.width(this.el, Util.DOM.bodyWidth());
						
		},
		
		
		
		/*
		 * Function: setNextState
		 */
		setNextState: function (disable) {
			
			if (disable) {
				Util.DOM.addClass(this.nextEl, Code.PhotoSwipe.ToolbarClass.CssClasses.nextDisabled);
				this.isNextActive = false;
			}
			else {
				Util.DOM.removeClass(this.nextEl, Code.PhotoSwipe.ToolbarClass.CssClasses.nextDisabled);
				this.isNextActive = true;
			}
			
		},
		
		
		/*
		 * Function: setPreviousState
		 */
		setPreviousState: function (disable) {
			
			if (disable) {
				Util.DOM.addClass(this.previousEl, Code.PhotoSwipe.ToolbarClass.CssClasses.previousDisabled);
				this.isPreviousActive = false;
			}
			else {
				Util.DOM.removeClass(this.previousEl, Code.PhotoSwipe.ToolbarClass.CssClasses.previousDisabled);
				this.isPreviousActive = true;
			}
			
		}
		
	});
	
	
	
	Code.PhotoSwipe.ToolbarClass.CssClasses = {
		caption: 'ps-toolbar',
		top: 'ps-toolbar-top',
		close: 'ps-toolbar-close',
		previous: 'ps-toolbar-previous',
		previousDisabled: 'ps-toolbar-previous-disabled',
		next: 'ps-toolbar-next',
		nextDisabled: 'ps-toolbar-next-disabled',
		play: 'ps-toolbar-play',
		content: 'ps-toolbar-content'
	};
	
	
	
	Code.PhotoSwipe.ToolbarClass.Actions = {
		close: 'close',
		previous: 'previous',
		next: 'next',
		play: 'play'
	};
	
	
	Code.PhotoSwipe.ToolbarClass.EventTypes = {
		onClick: 'onClick'
	};

})
(
	window,
	Code.PhotoSwipe.Util
);
	// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util, CaptionClass, ToolbarClass){

	/*
	 * Class: Code.PhotoSwipe.CaptionToolbarClass
	 */
	Code.PhotoSwipe.CaptionToolbarClass = Code.PhotoSwipe.EventClass.extend({
		
		toolbar: null,
		caption: null,
		
		isHidden: null,
		
		hasAddedEventListeners: null,
		
		toolbarClickEventHandler: null,
	
		
		/*
		 * Function: init
		 */
		init: function(options){
			
			this._super();
			
			this.settings = {
				opacity: 0.8,
				fadeInSpeed: 250,
				fadeOutSpeed: 500,
				autoHideDelay: 5000,
				flipPosition: false,
				showEmptyCaptions: true,
				hideClose: false,
				zIndex: 1000
			};
			
			Util.extend(this.settings, options);
			
			this.isHidden = true;
			this.hasAddedEventListeners = false;
			
			this.toolbarClickEventHandler = this.onToolbarClick.bind(this);
			
			this.caption = new CaptionClass({
				fadeInSpeed: this.settings.fadeInSpeed,
				fadeOutSpeed: this.settings.fadeOutSpeed,
				opacity: this.settings.opacity,
				position: (this.settings.flipPosition) ? 'bottom' : 'top',
				zIndex: this.settings.zIndex
			});
			
			
			this.toolbar = new ToolbarClass({
				fadeInSpeed: this.settings.fadeInSpeed,
				fadeOutSpeed: this.settings.fadeOutSpeed,
				opacity: this.settings.opacity,
				position: (this.settings.flipPosition) ? 'top' : 'bottom',
				hideClose: this.settings.hideClose,
				zIndex: this.settings.zIndex+1
			});
						
		},
		
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			this.caption.resetPosition();
			this.toolbar.resetPosition();
			
		},
		
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
			
			if (this.hasAddedEventListeners){
				return;
			}
			
			this.toolbar.addEventListener(ToolbarClass.EventTypes.onClick, this.toolbarClickEventHandler);
			
			this.hasAddedEventListeners = true;
		
		},
		
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			this.toolbar.removeEventListener(ToolbarClass.EventTypes.onClick, this.toolbarClickEventHandler);
			this.hasAddedEventListeners = false;
		
		},
			
		
		
		/*
		 * Function: fadeIn 
		 */
		fadeIn: function(){
			
			this.stopAutoHideTimeout();
			this.stopFade();
			
			if (this.isHidden){
				
				this.isHidden = false;
				
				// Already hidden so fade in
				this.fadeInCaption();
			
				this.toolbar.fadeIn();
							
				window.setTimeout(
					this.onFadeIn.bind(this),
					this.settings.fadeInSpeed
				);
				
			}
			else{
				
				// Not hidden, just check caption is visible
				if (this.caption.isHidden){
					this.fadeInCaption();
				}
				
				// Reset the autoHideTimeout
				this.resetAutoHideTimeout();
				
			}
		
		},
		
		
		
		showCaption: function(){
		
			if (this.caption.captionValue === ''){
				
				// Caption is empty
				if (this.settings.showEmptyCaptions){
					this.caption.show();
				}
				
			}
			else{
				this.caption.show();
			}
			
		},
		
		
		
		/*
		 * Function: fadeInCaption
		 */
		fadeInCaption: function(){
			
			if (this.caption.captionValue === ''){
				// Caption is empty
				if (this.settings.showEmptyCaptions){
					this.caption.fadeIn();
				}
			}
			else{
				this.caption.fadeIn();
			}
			
		},
		
		
		
		/*
		 * Function: onFadeIn
		 */
		onFadeIn: function(){
			
			this.addEventListeners();
			this.resetAutoHideTimeout();
			
		},
		
		
		
		/*
		 * Function: fadeOut
		 */
		fadeOut: function(){
			
			this.stopAutoHideTimeout();
			this.stopFade();
			
			this.isHidden = true;
			
			this.caption.fadeOut();
			this.toolbar.fadeOut();
			
			window.setTimeout(
				this.onFadeOut.bind(this),
				this.settings.fadeOutSpeed
			);
		
		},
		
		
		/*
		 * Function: onFadeOut
		 */
		onFadeOut: function(){
		},
		
		
		
		/*
		 * Function: stopFade
		 */
		stopFade: function(){
			
			this.caption.stopFade();
			this.toolbar.stopFade();
			
		},
		
		
		/*
		 * Function: hide
		 */
		hide: function(){
			
			this.stopAutoHideTimeout();
			this.stopFade();
			
			this.isHidden = true;
			this.removeEventListeners();
			
			this.caption.hide();
			this.toolbar.hide();
		
		},
		
		
		
		/*
		 * Function: setCaptionValue
		 */
		setCaptionValue: function(captionValue){
			
			this.caption.setCaptionValue(captionValue);
			
			if (this.caption.captionValue === '' && !this.settings.showEmptyCaptions){
				// The caption is empty and we don't want to show empty caption
				this.caption.fadeOut();
			}
			
		
		},
		
		
		
		/*
		 * Function: resetAutoHideTimeout
		 */
		resetAutoHideTimeout: function(){
			
			if (this.isHidden){
				return;
			}
			
			this.stopAutoHideTimeout();
			
			if (this.settings.autoHideDelay > 0){
				
				this.autoHideTimeout = window.setTimeout(
					this.fadeOut.bind(this),
					this.settings.autoHideDelay
				);
				
			}
		
		},
		
		
		
		/*
		 * Function: stopAutoHideTimeout
		 */
		stopAutoHideTimeout: function(){
			
			if (!Util.isNothing(this.autoHideTimeout)){
				window.clearTimeout(this.autoHideTimeout);
			}
						
		},
		
		
		
		/*
		 * Function: onToolbarClick
		 */
		onToolbarClick: function(e){
			
			this.dispatchEvent({ 
				type: Code.PhotoSwipe.ToolbarClass.EventTypes.onClick, 
				target: this, 
				action: e.action 
			});
			
		},
		
		
		
		/*
		 * Function: setNextState
		 */
		setNextState: function (disable) {
			
			this.toolbar.setNextState(disable);
			
		},
		
		
		
		/*
		 * Function: setPreviousState
		 */
		setPreviousState: function (disable) {
			
			this.toolbar.setPreviousState(disable);
			
		}
		
		
	});


})
(
	window,
	Code.PhotoSwipe.Util, 
	Code.PhotoSwipe.CaptionClass, 
	Code.PhotoSwipe.ToolbarClass
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util){
	
	/*
	 * Class: Code.PhotoSwipe.ZoomPanRotateClass
	 */
	Code.PhotoSwipe.ZoomPanRotateClass = Code.PhotoSwipe.ElementClass.extend({
		
		containerEl: null,
		imageEl: null,
		parentEl: null,
		transformSettings: null,
		panStartingPoint: null,
		
		
		/*
		 * Function: init
		 */
		init: function(options, parentEl, imageEl){
			
			this.settings = {
				maxZoom: 5.0,
				minZoom: 0.5,
				adjustPanToZoom: true
			};
			
			Util.extend(this.settings, options);
		
			this._super(options);
						
			this.parentEl = parentEl;
			this.imageEl = imageEl.cloneNode(false);
			
			this.transformSettings = {
				
				startingScale: 1.0,
				scale: 1.0,
				startingRotation: 0,
				rotation: 0,
				startingTranslateX: 0,
				startingTranslateY: 0,
				translateX: 0,
				translateY: 0
			
			};
			
			// Create element and append to body
			this.el = Util.DOM.createElement('div', { 'class': Code.PhotoSwipe.ZoomPanRotateClass.CssClasses.documentOverlay }, '');
			Util.DOM.setStyle(this.el, {
				left: 0,
				top: 0,
				position: 'absolute'
			});
			Util.DOM.width(this.el, Util.DOM.bodyWidth());
			Util.DOM.height(this.el, Util.DOM.windowHeight());
			
			this.containerEl = Util.DOM.createElement('div');
			Util.DOM.setStyle(this.containerEl, {
				left: 0,
				top: 0,
				position: 'absolute'
			});
			Util.DOM.width(this.containerEl, Util.DOM.bodyWidth());
			Util.DOM.height(this.containerEl, Util.DOM.windowHeight());
			
			Util.DOM.appendChild(this.imageEl, this.containerEl);
			Util.DOM.appendChild(this.containerEl, this.el);
			Util.DOM.appendChild(this.el, this.parentEl);
			
		},
		
		
		/*
		 * Function: setStartingTranslateFromCurrentTranform
		 */
		setStartingTranslateFromCurrentTranform: function(){
			
			var transformExploded = this.containerEl.style.webkitTransform.match( /translate\((.*?)\)/ );
			
			if (!Util.isNothing(transformExploded)){
				
				transformExploded = transformExploded[1].split(', ');
				this.transformSettings.startingTranslateX = window.parseInt(transformExploded[0]);
				this.transformSettings.startingTranslateY = window.parseInt(transformExploded[1]);
			
			}
			
		},
		
		
		
		/*
		 * Function: setStartingScaleAndRotation
		 */
		setStartingScaleAndRotation: function(scaleValue, rotationValue){
			
			var scale = this.transformSettings.startingScale * scaleValue;
			
			if (this.settings.minZoom !== 0 && scale < this.settings.minZoom){
				scale = this.settings.minZoom;
			}
			else if (this.settings.maxZoom !== 0 && scale > this.settings.maxZoom){
				scale = this.settings.maxZoom;
			}
			
			this.transformSettings.startingScale = scale;
			
			this.transformSettings.startingRotation = 
				(this.transformSettings.startingRotation + rotationValue) % 360;
				
		},
		
		
		
		/*
		 * Function: zoomRotate
		 */
		zoomRotate: function(scaleValue, rotationValue){
			
			var scale = this.transformSettings.startingScale * scaleValue;
			
			if (this.settings.minZoom !== 0 && scale < this.settings.minZoom){
				scale = this.settings.minZoom;
			}
			else if (this.settings.maxZoom !== 0 && scale > this.settings.maxZoom){
				scale = this.settings.maxZoom;
			}
						
			this.transformSettings.scale = scale;
									
			this.transformSettings.rotation = 
				this.transformSettings.startingRotation + rotationValue;
			
			this.applyTransform();
			
		},
		
		
		
		/*
		 * Function: panStart
		 */
		panStart: function(point){
			
			this.setStartingTranslateFromCurrentTranform();
						
			this.panStartingPoint = {
				x: point.x,
				y: point.y
			};
			
		},
		
		
		
		/*
		 * Function: pan
		 */
		pan: function(point){ 
			
			var 
				dx = point.x - this.panStartingPoint.x,
				dy = point.y - this.panStartingPoint.y,
				dxScaleAdjust = (this.settings.adjustPanToZoom) ? dx / this.transformSettings.scale : dx,
        dyScaleAdjust = dy / this.transformSettings.scale ? dy / this.transformSettings.scale : dy ;
			
			
			this.transformSettings.translateX = 
				this.transformSettings.startingTranslateX + dxScaleAdjust;

			this.transformSettings.translateY = 
				this.transformSettings.startingTranslateY + dyScaleAdjust;

			this.applyTransform();
			
		},
		
		
		
		/*
		 * Function: applyTransform
		 */
		applyTransform: function(){
			 
			this.containerEl.style.webkitTransform = 'scale(' + this.transformSettings.scale + ') rotate(' + (this.transformSettings.rotation % 360) + 'deg) translate(' + this.transformSettings.translateX + 'px, ' + this.transformSettings.translateY + 'px)';
			
		},
			
		
		
		/*
		 * Function: removeFromDOM
		 */
		removeFromDOM: function(){
			
			Util.DOM.removeChild(this.el, this.parentEl);
		
		}
	
	});
	
	
	Code.PhotoSwipe.ZoomPanRotateClass.CssClasses = {
		documentOverlay: 'ps-zoom-pan-rotate'
	};

})
(
	window,
	Code.PhotoSwipe.Util
);
// PhotoSwipe - http://www.photoswipe.com/
// Copyright (c) 2011 by Code Computerlove (http://www.codecomputerlove.com)
// Licensed under the MIT license
// version: 1.0.11

(function(window, Util, ElementClass, DocumentOverlayClass, FullSizeImageClass, ViewportClass, SliderClass, CaptionClass, ToolbarClass, CaptionToolbarClass, ZoomPanRotateClass){

	var photoSwipe = Code.PhotoSwipe.EventClass.extend({
		
		fullSizeImages: null,
		
		documentOverlay: null,
		viewport: null,
		slider: null,
		captionAndToolbar: null,
		zoomPanRotate: null,
		
		settings: null,
		currentIndex: null,	
		isBusy: null,
		isActive: null,
		currentHistoryHashValue: null,
		isBackEventSupported: null,
		
		slideshowTimeout: null,
		isSlideshowActive: null,
		
		lastShowPrevTrigger: null,
		
		backButtonClicked: null,
		
		viewportFadeInEventHandler: null,
		windowOrientationChangeEventHandler: null,
		windowScrollEventHandler: null,
		windowHashChangeHandler: null,
		keyDownEventHandler: null,
		viewportTouchEventHandler: null,
		viewportFadeOutEventHandler: null,
		sliderDisplayCurrentFullSizeImageEventHandler: null,
		toolbarClickEventHandler: null,
		orientationEventName: null,
		
		
		/*
		 * Function: init
		 */
		init: function(){
			
			this._super();
						
			this.currentIndex = 0;
			this.isBusy = false;
			this.isActive = false;
			this.isSlideshowActive = false;
			this.backButtonClicked = false;
			
			this.settings = { 
				getImageSource: Code.PhotoSwipe.GetImageSource,
				getImageCaption: Code.PhotoSwipe.GetImageCaption,
				getImageMetaData: Code.PhotoSwipe.GetImageMetaData,
				fadeInSpeed: 250,
				fadeOutSpeed: 500,
				slideSpeed: 250,
				swipeThreshold: 50,
				swipeTimeThreshold: 250,
				loop: true,
				slideshowDelay: 3000,
				imageScaleMethod: 'fit', // Either "fit" or "zoom"
				preventHide: false,
				zIndex: 1000,
				backButtonHideEnabled: true,
				jQueryMobile: ( !Util.isNothing(window.jQuery) && !Util.isNothing(window.jQuery.mobile) ),
				jQueryMobileDialogHash: '&ui-state=dialog',
				
				/* Experimental - iOS only at the moment */
				allowUserZoom: true, 
				allowRotationOnUserZoom: true,
				maxUserZoom: 5.0,
				minUserZoom: 0.5,
				adjustUserPanToZoom: true,
				
				captionAndToolbarHide: false,
				captionAndToolbarHideOnSwipe: true,
				captionAndToolbarFlipPosition: false,
				captionAndToolbarAutoHideDelay: 5000,
				captionAndToolbarOpacity: 0.8,
				captionAndToolbarShowEmptyCaptions: true				
			};
						
			if (Util.browser.isAndroid){
				if (navigator.userAgent.indexOf('2.1')){
					this.isBackEventSupported = true;
				}
			}
			if (!this.isBackEventSupported){
				this.isBackEventSupported = 'onhashchange' in window;
			}
			
			if (this.settings.preventHide){
				this.settings.backButtonHideEnabled = false;
			}
			
			
			// Set pointers to event handlers
			this.viewportFadeInEventHandler = this.onViewportFadeIn.bind(this);
			this.windowOrientationChangeEventHandler = this.onWindowOrientationChange.bind(this);
			this.windowScrollEventHandler = this.onWindowScroll.bind(this);
			this.windowHashChangeHandler = this.onWindowHashChange.bind(this);
			this.keyDownEventHandler = this.onKeyDown.bind(this);
			this.viewportTouchEventHandler = this.onViewportTouch.bind(this);
			this.viewportFadeOutEventHandler = this.onViewportFadeOut.bind(this);
			this.sliderDisplayCurrentFullSizeImageEventHandler = this.onSliderDisplayCurrentFullSizeImage.bind(this);
			this.toolbarClickEventHandler = this.onToolbarClick.bind(this);
			
		},
		
		
		
		/*
		 * Function: setOptions
		 */
		setOptions: function(options){
			
			Util.extend(this.settings, options);
			
			if (this.settings.preventHide){
				this.settings.backButtonHideEnabled = false;
			}
			
		},
		
		
		
		/*
		 * Function: setImages
		 * Set images from DOM elements. Could be a list of image
		 * elments or anchors containing image elements etc.
		 * By default the gallery assumes the latter. If you change
		 * this, remember to set your own getImageSource and getImageCaption
		 * methods so the gallery knows what to look for.
		 */
		setImages: function(thumbEls){
			
			if (!Util.isArray){
				throw "thumbEls is not an array";
			}
			
			this.currentIndex = 0; 
			
			this.fullSizeImages = [];
			
			for (var i=0; i<thumbEls.length; i++){
				
				var thumbEl = thumbEls[i];
				
				// Create a new fullSizeImage object
				var fullSizeImage = new FullSizeImageClass(
					i, 
					this.settings.imageScaleMethod,
					this.settings.getImageSource(thumbEl), 
					this.settings.getImageCaption(thumbEl),
					this.settings.getImageMetaData(thumbEl)
				);
				
				// Add it to our internal array
				this.fullSizeImages.push(fullSizeImage);
				
			}
			
		},
		
		
		
		/*
		 * Function: show
		 */
		show: function(startingIndex){
			
			if (this.isBusy || this.isActive){
				return;
			}
			
			if (!Util.isNumber(startingIndex)){
				throw "startingIndex must be a number";
			}
			
			if (Util.isNothing(this.fullSizeImages)){
				throw "need to set images before showing the gallery";
			}
			
			this.backButtonClicked = false;
			this.isActive = true;
			this.isBusy = true;
						
			this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.show;
			
			Util.DOM.addClass(document.body, Code.PhotoSwipe.CssClasses.activeBody);
			
			
			// Check index is in acceptable bounds
			// If not, default it to the first index
			startingIndex = window.parseInt(startingIndex);
			if (startingIndex < 0 || startingIndex >= this.fullSizeImages.length){
				startingIndex = 0;
			}
			
			this.currentIndex = startingIndex;
			
			if (Util.isNothing(this.documentOverlay)){
				this.build();
			}
			else{
				this.resetPosition();
			}
			
			// Fade in the viewport overlay,
			// then show the viewport, slider and toolbar etc
			this.viewport.addEventListener(
				ElementClass.EventTypes.onFadeIn,
				this.viewportFadeInEventHandler
			);
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeShow);
			
			this.viewport.fadeIn();
			
		},
		
		
		
		/*
		 * Function: build
		 */
		build: function(){
			
			// Create the document overlay
			this.documentOverlay = new DocumentOverlayClass({ 
				fadeInSpeed: this.settings.fadeInSpeed,
				fadeOutSpeed: this.settings.fadeOutSpeed,
				zIndex: this.settings.zIndex
			});
			
			// Create the viewport
			this.viewport = new ViewportClass({ 
				fadeInSpeed: this.settings.fadeInSpeed,
				fadeOutSpeed: this.settings.fadeOutSpeed, 
				swipeThreshold: this.settings.swipeThreshold,
				swipeTimeThreshold: this.settings.swipeTimeThreshold,
				zIndex: this.settings.zIndex+1 
			});
			
			// Create the slider
			this.slider = new SliderClass(
				{
					fadeInSpeed: this.settings.fadeInSpeed,
					fadeOutSpeed: this.settings.fadeOutSpeed,
					slideSpeed: this.settings.slideSpeed
				}, 
				this.viewport.el
			);
				
			this.captionAndToolbar = new CaptionToolbarClass({
				
				opacity: this.settings.captionAndToolbarOpacity,
				fadeInSpeed: this.settings.fadeInSpeed,
				fadeOutSpeed: this.settings.fadeOutSpeed,
				autoHideDelay: this.settings.captionAndToolbarAutoHideDelay,
				flipPosition: this.settings.captionAndToolbarFlipPosition,
				showEmptyCaptions: this.settings.captionAndToolbarShowEmptyCaptions,
				hideClose: this.settings.preventHide,
				zIndex: this.settings.zIndex+3
			
			});
			
			this.resetPosition();
			
		},
		
		
		
		/*
		 * Function: addEventListeners
		 */
		addEventListeners: function(){
			
			// Set window handlers
			if (Util.browser.isAndroid){
				// For some reason, resize was more stable than orientationchange in Android
				// This fix was added in v1.0.5 - needs reviewing
				this.orientationEventName = 'resize';
			}
			else{
				var supportsOrientationChange = 'onorientationchange' in window;
				this.orientationEventName = supportsOrientationChange ? 'orientationchange' : 'resize';
			}
			
			Util.DOM.addEventListener(window, this.orientationEventName, this.windowOrientationChangeEventHandler);
			
			Util.DOM.addEventListener(window, 'scroll', this.windowScrollEventHandler);
					
			if (this.isBackEventSupported && this.settings.backButtonHideEnabled){
				
				if (this.settings.jQueryMobile){
					window.location.hash = this.settings.jQueryMobileDialogHash;
				}
				else{
					this.currentHistoryHashValue = 'PhotoSwipe' + new Date().getTime().toString();
					window.location.hash = this.currentHistoryHashValue;
				}
				
				Util.DOM.addEventListener(window, 'hashchange', this.windowHashChangeHandler);
				
			}
			
			// Set keydown event handlers for desktop browsers
			Util.DOM.addEventListener(document, 'keydown', this.keyDownEventHandler);
			
			// Set viewport handlers
			this.viewport.addEventListener(ViewportClass.EventTypes.onTouch, this.viewportTouchEventHandler);
			
			// Set slider handlers
			this.slider.addEventListener(SliderClass.EventTypes.onDisplayCurrentFullSizeImage, this.sliderDisplayCurrentFullSizeImageEventHandler);
			
			// Set captionAndToolbar handlers
			this.captionAndToolbar.addEventListener(ToolbarClass.EventTypes.onClick, this.toolbarClickEventHandler);
			
		},
		
		
		
		/*
		 * Function: removeEventListeners
		 */
		removeEventListeners: function(){
			
			// Remove window handlers
			Util.DOM.removeEventListener(window, this.orientationEventName, this.windowOrientationChangeEventHandler);
			
			Util.DOM.removeEventListener(window, 'scroll', this.windowScrollEventHandler);
			
			if (this.isBackEventSupported && this.settings.backButtonHideEnabled){
				Util.DOM.removeEventListener(window, 'hashchange', this.windowHashChangeHandler);
			}
			
			// Remove keydown event handlers for desktop browsers
			Util.DOM.removeEventListener(document, 'keydown', this.keyDownEventHandler);
			
			// Remove viewport handlers
			this.viewport.removeEventListener(ViewportClass.EventTypes.onTouch, this.viewportTouchEventHandler);
			
			// Remove slider handlers
			this.slider.removeEventListener(SliderClass.EventTypes.onDisplayCurrentFullSizeImage, this.sliderDisplayCurrentFullSizeImageEventHandler);
			
			// Remove captionAndToolbar handlers
			this.captionAndToolbar.removeEventListener(ToolbarClass.EventTypes.onClick, this.toolbarClickEventHandler);
			
		},
		
		
		
		/*
		 * Function: onViewportFadeIn
		 */
		onViewportFadeIn: function(e){
			
			// Remove the ElementClass.EventTypes.onFadeIn
			// event handler
			this.viewport.removeEventListener(
				ElementClass.EventTypes.onFadeIn,
				this.viewportFadeInEventHandler
			);
			
			this.documentOverlay.show();
			
			this.slider.fadeIn();
			
			this.addEventListeners();
			
			this.slider.setCurrentFullSizeImage(this.fullSizeImages[this.currentIndex]);
			
			this.isBusy = false;
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onShow);
			
		},
		
		
		
		/*
		 * Function: setSliderPreviousAndNextFullSizeImages
		 */
		setSliderPreviousAndNextFullSizeImages: function(){
			
			var 
				lastIndex,
				previousFullSizeImage = null,
				nextFullSizeImage = null;
			
			if (this.fullSizeImages.length > 1) {
				
				lastIndex = this.fullSizeImages.length - 1;
				
				// Current image is the last 
				if (this.currentIndex === lastIndex) {
				
					if (this.settings.loop) {
						nextFullSizeImage = this.fullSizeImages[0];
					}
					previousFullSizeImage = this.fullSizeImages[this.currentIndex - 1];
					
				}
				
				// Current image is the first
				else if (this.currentIndex === 0) {
					
					nextFullSizeImage = this.fullSizeImages[this.currentIndex + 1];
					if (this.settings.loop) {
						previousFullSizeImage = this.fullSizeImages[lastIndex];
					}
				
				}
				
				// Current image is in the middle of the thumbs  
				else {
					
					nextFullSizeImage = this.fullSizeImages[this.currentIndex + 1];
					previousFullSizeImage = this.fullSizeImages[this.currentIndex - 1];
				
				}
				
			}
			
			this.slider.setPreviousAndNextFullSizeImages(previousFullSizeImage, nextFullSizeImage);
		
		},
		
		
		
		/*
		 * Function: onKeyDown
		 */
		onKeyDown: function(e){
			
			this.stopSlideshow();
			
			if (e.keyCode === 37) { // Left
				e.preventDefault();
				this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.keyboard;
				this.showPrevious();
			}
			else if (e.keyCode === 39) { // Right
				e.preventDefault();
				this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.keyboard;
				this.showNext();
			}
			else if (e.keyCode === 38 || e.keyCode === 40) { // Up and down
				e.preventDefault();
			}
			else if (e.keyCode === 27) { // Escape
				e.preventDefault();
				this.hide();
			}
			else if (e.keyCode === 32) { // Spacebar
				if (!this.settings.hideToolbar){
					this.toggleCaptionAndToolbar();
				}
				else{
					this.hide();
				}
				e.preventDefault();
			}
			
		},
		
		
		
		/*
		 * Function: onWindowOrientationChange
		 */
		onWindowOrientationChange: function(e){
			
			this.resetPosition();
			
		},
		
		
		
		/*
		 * Function: onWindowScroll
		 */
		onWindowScroll: function(e){
			
			this.resetPosition();
			
		},
		
		
		/*
		 * Function: onWindowHashChange
		 */
		onWindowHashChange: function(e){
			
			var compareHash = '#' + 
				((this.settings.jQueryMobile) ? this.settings.jQueryMobileDialogHash : this.currentHistoryHashValue);
			
			if (window.location.hash !== compareHash){
				this.backButtonClicked = true;
				this.hide();
			}
			
		},
		
		
		
		/*
		 * Function: resetPosition
		 */
		resetPosition: function(){
			
			this.removeZoomPanRotate();
			
			this.viewport.resetPosition();
			this.slider.resetPosition();
			this.documentOverlay.resetPosition();
			this.captionAndToolbar.resetPosition();
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onResetPosition);
			
		},
		
		
		
		/*
		 * Function: canUserZoom
		 */
		canUserZoom: function(){
			
			if (!this.settings.allowUserZoom){
				return false;
			}
			
			if (this.isBusy){
				return false;
			}
			
			if (Util.isNothing(this.slider.currentItem.fullSizeImage)){
				return false;
			}
			
			if (!this.slider.currentItem.fullSizeImage.hasLoaded){
				return false;
			}
			
			return true;
			
		},
		
		
		
		/*
		 * Function: isZoomActive
		 */
		isZoomActive: function(){
			
			return (!Util.isNothing(this.zoomPanRotate));
		
		},
		
		
		
		/*
		 * Function: onViewportTouch
		 */
		onViewportTouch: function(e){
						
			switch(e.action){
				
				case ViewportClass.Actions.gestureStart:
					
					if (this.canUserZoom()){
						this.stopSlideshow();
						if (!this.isZoomActive()){
							this.zoomPanRotate = new ZoomPanRotateClass(
								{
									maxZoom: this.settings.maxUserZoom,
									minZoom: this.settings.minUserZoom,
									adjustPanToZoom: this.settings.adjustUserPanToZoom
								}, 
								this.viewport.el, 
								this.slider.currentItem.imageEl
							);
							Util.DOM.resetTranslate(this.zoomPanRotate.containerEl);
							Util.DOM.resetTranslate(this.zoomPanRotate.imageEl);
						}
						this.fadeOutCaptionAndToolbar();
					}
					break;
					
				case ViewportClass.Actions.gestureChange:
				
					if (this.isZoomActive()){
						this.zoomPanRotate.zoomRotate(e.scale, (this.settings.allowRotationOnUserZoom) ? e.rotation : 0);
					}
					break;
					
				case ViewportClass.Actions.gestureEnd:
				
					if (this.isZoomActive()){
						this.zoomPanRotate.setStartingScaleAndRotation(e.scale, (this.settings.allowRotationOnUserZoom) ? e.rotation : 0);
					}
					break;
					
				case ViewportClass.Actions.touchStart:
				
					this.stopSlideshow();
					if (this.isZoomActive()){
						this.zoomPanRotate.panStart(e.point);
					}
					break;
					
				case ViewportClass.Actions.touchMove:
					
					if (this.isZoomActive()){
						this.zoomPanRotate.pan(e.point);
					}
					break;
					
				case ViewportClass.Actions.click:
					
					this.stopSlideshow();
					if (!this.settings.hideToolbar){
						this.toggleCaptionAndToolbar();
					}
					else{
						this.hide();
					}
					this.dispatchEvent(Code.PhotoSwipe.EventTypes.onViewportClick);
					break;
				
				case ViewportClass.Actions.swipeLeft:
					
					this.stopSlideshow();
					this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.swipe;
					this.showNext();
					break;
					
				case ViewportClass.Actions.swipeRight:
					
					this.stopSlideshow();
					this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.swipe;
					this.showPrevious();
					break;
					
			}
			
		},
		
		
		
		/*
		 * Function: onViewportFadeOut
		 */
		onViewportFadeOut: function(e){
			
			this.viewport.removeEventListener(ElementClass.EventTypes.onFadeOut, this.viewportFadeOutEventHandler);
			
			this.isBusy = false;
			this.isActive = false;
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onHide);
			
			this.goBackInHistory();
			
		},
		
		
		
		/*
		 * Function: hide
		 */
		hide: function(){
			
			if (this.isBusy || this.settings.preventHide){
				return;
			}
			
			if (!this.isActive){
				return;
			}
			
			this.isBusy = true;
			
			this.removeZoomPanRotate();
			
			this.removeEventListeners();
			
			this.documentOverlay.hide();
			this.captionAndToolbar.hide();
			this.slider.hide();
			
			Util.DOM.removeClass(document.body, Code.PhotoSwipe.CssClasses.activeBody);
			
			this.viewport.addEventListener(ElementClass.EventTypes.onFadeOut, this.viewportFadeOutEventHandler);
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeHide);
			
			this.viewport.fadeOut();
			
		},
		
		
		/*
		 * Function: hideImmediately
		 */
		hideImmediately: function(){
			
			if (!this.isActive){
				return;
			}
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeHide);
			
			this.removeZoomPanRotate();
			this.removeEventListeners();
			this.documentOverlay.hide();
			this.captionAndToolbar.hide();
			this.slider.hide();
			this.viewport.hide();
			
			Util.DOM.removeClass(document.body, Code.PhotoSwipe.CssClasses.activeBody);
			
			this.isBusy = false;
			this.isActive = false;
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onHide);
			
			this.goBackInHistory();
		},
		
		
		/*
		 * Function: goBackInHistory
		 */
		goBackInHistory: function(){
			
			if (this.isBackEventSupported && this.settings.backButtonHideEnabled){	
				if ( !this.backButtonClicked ){
					window.history.back();
				}
			}
			
		},
		
		
		/*
		 * Function: showNext
		 */
		showNext: function(){
			
			if (this.isBusy){
				return;
			}
			
			this.isBusy = true;
			
			this.cleanUpZoomPanRotateForNextPrevious();
			
			this.setCaptionAndToolbarOnShowPreviousNext();
			
			this.slider.showNext();
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onShowNext);
			
		},
		
		
		
		/*
		 * Function: showPrevious
		 */
		showPrevious: function(){
			
			if (this.isBusy){
				return;
			}
			
			this.isBusy = true;
			
			this.cleanUpZoomPanRotateForNextPrevious();
			
			this.setCaptionAndToolbarOnShowPreviousNext();
			
			if (this.wasUserZoomActive){
				Util.DOM.hide(this.slider.currentItem.imageEl);
			}
			
			this.slider.showPrevious();
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onShowPrevious);
			
		},
		
		
		
		/*
		 * Function: cleanUpZoomPanRotateForNextPrevious
		 */
		cleanUpZoomPanRotateForNextPrevious: function(){
		
			if (!Util.isNothing(this.zoomPanRotate)){
				if (this.settings.loop){
					Util.DOM.hide(this.slider.currentItem.imageEl);
				}
				else{
					if (this.currentIndex > 0 && this.currentIndex < this.fullSizeImages.length - 2){
						Util.DOM.hide(this.slider.currentItem.imageEl);
					}
				}
			}
			
			this.removeZoomPanRotate();
			
		},
		
		
		
		/*
		 * Function: setCaptionAndToolbarOnShowPreviousNext
		 */
		setCaptionAndToolbarOnShowPreviousNext: function(){
		
			if (this.settings.captionAndToolbarHide){
				return;
			}
			
			var resetAutoTimeout = false;
			
			switch (this.lastShowPrevTrigger){
				
				case Code.PhotoSwipe.ShowPrevTriggers.toolbar:
					resetAutoTimeout = true;
					break;
					
				case Code.PhotoSwipe.ShowPrevTriggers.slideshow:
					resetAutoTimeout = false;
					break;
				
				default: 
					resetAutoTimeout = !this.settings.captionAndToolbarHideOnSwipe;
					break;
			}
			
			
			if (resetAutoTimeout) {
				
				// Reset the caption and toolbar's fadeOut timeout
				this.captionAndToolbar.resetAutoHideTimeout();
					
			}
			else{
				
				this.fadeOutCaptionAndToolbar();
				
			}
						
		},
		
		
		
		/*
		 * Function: onSliderDisplayCurrentFullSizeImage
		 */
		onSliderDisplayCurrentFullSizeImage: function(e){
			
			this.currentIndex = e.fullSizeImage.index;
			
			
			// Set caption and toolbar
			if (!this.settings.captionAndToolbarHide){
				
				if (this.settings.loop) {
					this.captionAndToolbar.setNextState(false);
					this.captionAndToolbar.setPreviousState(false);
				}
				else{
					if (this.currentIndex >= this.fullSizeImages.length - 1) {
						this.captionAndToolbar.setNextState(true);
					}
					else {
						this.captionAndToolbar.setNextState(false);
					}
					
					if (this.currentIndex < 1) {
						this.captionAndToolbar.setPreviousState(true);
					}
					else {
						this.captionAndToolbar.setPreviousState(false);
					}
				}
				
				this.captionAndToolbar.setCaptionValue(this.fullSizeImages[this.currentIndex].caption);
				
				var fadeIn = false;
				
				switch (this.lastShowPrevTrigger){
					
					case Code.PhotoSwipe.ShowPrevTriggers.toolbar:
						fadeIn = true;
						break;
						
					case Code.PhotoSwipe.ShowPrevTriggers.show:
						fadeIn = true;
						break;
						
					case Code.PhotoSwipe.ShowPrevTriggers.slideshow:
						fadeIn = false;
						break;
						
					default:
						fadeIn = !this.settings.captionAndToolbarHideOnSwipe;
						break;
					
				}
				
				
				if (fadeIn){
					
					this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeCaptionAndToolbarShow);
					this.captionAndToolbar.fadeIn();
					
				}
				
				this.dispatchEvent(Code.PhotoSwipe.EventTypes.onDisplayImage);
				
			}
			
			this.lastShowPrevTrigger = '';
			
			// Set the previous and next images for the slider
			this.setSliderPreviousAndNextFullSizeImages();
			
			if (this.isSlideshowActive){
				
				this.fireSlideshowTimeout();

			}
			
			this.isBusy = false;
			
		},
		
		
		
		/*
		 * Function: toggleCaptionAndToolbar
		 */
		toggleCaptionAndToolbar: function(){
			
			if (this.settings.captionAndToolbarHide){
				
				this.captionAndToolbar.hide();
				return;
				
			}
			
			if (this.captionAndToolbar.isHidden){
				
				this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeCaptionAndToolbarShow);
				this.captionAndToolbar.fadeIn();

			}
			else{
				
				this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeCaptionAndToolbarHide);
				this.captionAndToolbar.fadeOut();
				
			}
			
			
		},
		
		
		
		/*
		 * Function: fadeOutCaptionAndToolbar
		 */
		fadeOutCaptionAndToolbar: function(){
			
			if (!this.settings.captionAndToolbarHide && !this.captionAndToolbar.isHidden){
				this.dispatchEvent(Code.PhotoSwipe.EventTypes.onBeforeCaptionAndToolbarHide);
				this.captionAndToolbar.fadeOut();
			}
		
		},
		
		
		
		/*
		 * Function: onToolbarClick
		 */
		onToolbarClick: function(e){
			
			this.stopSlideshow();
			
			switch (e.action){
				
				case ToolbarClass.Actions.previous:
					this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.toolbar;
					this.showPrevious();
					break;
					
				case ToolbarClass.Actions.next:
					this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.toolbar;
					this.showNext();
					break;
				
				case ToolbarClass.Actions.play:
					this.startSlideshow();
					break;
				
				default:
					this.hide();
					break;
					
			}
			
		},
		
		
		
		/*
		 * Function: startSlideshow
		 */
		startSlideshow: function(){
			
			if (this.isBusy){
				return;
			}
			
			if (!Util.isNothing(this.slideshowTimeout)){
				window.clearTimeout(this.slideshowTimeout);
			}
				
			this.removeZoomPanRotate();
			
			this.isSlideshowActive = true;
			
			this.fadeOutCaptionAndToolbar();
			
			this.fireSlideshowTimeout();
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onSlideshowStart);
			
		},
		
		
		
		/*
		 * Function: stopSlideshow
		 */
		stopSlideshow: function(){
			
			if (!Util.isNothing(this.slideshowTimeout)){
				window.clearTimeout(this.slideshowTimeout);
			}
						
			this.isSlideshowActive = false;
			
			this.dispatchEvent(Code.PhotoSwipe.EventTypes.onSlideshowStop);
			
		},
		
		
	
		
		/*
		 * Function: fireSlideshowTimeout
		 */
		fireSlideshowTimeout: function(){
				
			var fire = false;
			
			if (this.settings.loop){
				if (this.fullSizeImages.length > 1){
					fire = true;
				}
			}
			else{
				if (this.currentIndex < this.fullSizeImages.length-1){
					fire = true;
				}
			}
			
			if (fire){
				
				this.lastShowPrevTrigger = Code.PhotoSwipe.ShowPrevTriggers.slideshow;
				this.slideshowTimeout = window.setTimeout(
					this.showNext.bind(this),
					this.settings.slideshowDelay
				);
			
			}
			
		},
		
		
		
		/*
		 * Function: removeZoomPanRotate
		 */
		removeZoomPanRotate: function(){
			
			if (Util.isNothing(this.zoomPanRotate)){
				return;
			}
			
			this.zoomPanRotate.removeFromDOM();
						
			this.zoomPanRotate = null;
		
		}
		
		
	});
	
	
	Code.PhotoSwipe.CssClasses = {
		activeBody: 'ps-active'
	};
	
	
	Code.PhotoSwipe.ShowPrevTriggers = {
		
		show: 'show',
		toolbar: 'toobar',
		swipe: 'swipe',
		keyboard: 'keyboard',
		slideshow: 'slideshow'
		
	};
	
	
	Code.PhotoSwipe.EventTypes = {
		
		onBeforeShow: 'onBeforeShow',
		onShow: 'onShow',
		onBeforeHide: 'onBeforeHide',
		onHide: 'onHide',
		onShowNext: 'onShowNext',
		onShowPrevious: 'onShowPrevious',
		onDisplayImage: 'onDisplayImage',
		onResetPosition: 'onResetPosition',
		onSlideshowStart: 'onSlideshowStart',
		onSlideshowStop: 'onSlideshowStop',
		onBeforeCaptionAndToolbarShow: 'onBeforeCaptionAndToolbarShow',
		onBeforeCaptionAndToolbarHide: 'onBeforeCaptionAndToolbarHide',
		onViewportClick: 'onViewportClick'
		
	};
	
	
	/*
	 * Function: Code.PhotoSwipe.GetImageSource
	 * Default method for returning an image's source
	 */
	Code.PhotoSwipe.GetImageSource = function(el){
		return el.href;
	};
	
	
	
	/*
	 * Function: Code.PhotoSwipe.GetImageCaption
	 * Default method for returning an image's caption
	 * Assumes the el is an anchor and the first child is the
	 * image. The returned value is the "alt" attribute of the
	 * image.
	 */
	Code.PhotoSwipe.GetImageCaption = function(el){
		if (el.nodeName === "IMG"){
			return Util.DOM.getAttribute(el, 'alt'); 
		}
		var i, childEl;
		for (i=0; i<el.childNodes.length; i++){
			childEl = el.childNodes[i];
			if (el.childNodes[i].nodeName === 'IMG'){
				return Util.DOM.getAttribute(childEl, 'alt'); 
			}
		}
	};
	
	
	
	/*
	 * Function: Code.PhotoSwip.GetImageMetaData
	 * Can be used if you wish to store additional meta
	 * data against the full size image
	 */
	Code.PhotoSwipe.GetImageMetaData = function(el){
		
		return  {};
		
	};
	
	
	Code.PhotoSwipe.Current = new photoSwipe();
	
	
	Code.photoSwipe = function(thumbEls, containerEl, opts){
		
		var useEventDelegation = true;
		
		if (Util.isNothing(thumbEls)){
			return;
		}
		
		/* See if there is a container element, if so we will use event delegation */
		
		if (Util.isNothing(containerEl)){
			containerEl = document.documentElement;
			useEventDelegation = false;
		}
		
		if (Util.isString(containerEl)){
			containerEl = document.documentElement.querySelector(containerEl);
		}
		
		if (Util.isNothing(containerEl)){
			throw 'Unable to find container element'; 
		}
		
		if (Util.isString(thumbEls)){
			thumbEls = containerEl.querySelectorAll(thumbEls);
		}
		
		if (Util.isNothing(thumbEls)){
			return;
		}
		
		
		var onClick = function(e){
		
			e.preventDefault();
					
			showPhotoSwipe(e.currentTarget);
			
		};
		
		var showPhotoSwipe = function(clickedEl){
			
			var startingIndex;
			for (startingIndex = 0; startingIndex < thumbEls.length; startingIndex++){
				if (thumbEls[startingIndex] === clickedEl){
					break;
				}
			}
			
			Code.PhotoSwipe.Current.show(startingIndex);
				
		};
		
		
		
		// Set up the options 
		Code.PhotoSwipe.Current.setOptions(opts);
		
		
		// Tell PhotoSwipe about the photos
		Code.PhotoSwipe.Current.setImages(thumbEls);
		
		
		if (useEventDelegation){
			
			/*
			 * Use event delegation rather than setting a click event on each 
			 * thumb element.
			 */
			containerEl.addEventListener('click', function(e){
			
				if (e.target === e.currentTarget){
					return;
				}
					
				e.preventDefault();
					
				var findNode = function(clickedEl, targetNodeName, stopAtEl){
					
					if (Util.isNothing(clickedEl) || Util.isNothing(targetNodeName) || Util.isNothing(stopAtEl)){
						return null;
					}
					
					if (clickedEl.nodeName === targetNodeName){
						return clickedEl;
					}
					
					if (clickedEl === stopAtEl){
						return null;
					}
										
					return findNode(clickedEl.parentNode, targetNodeName, stopAtEl);
				};
				
				
				var clickedEl = findNode(e.target, thumbEls[0].nodeName, e.currentTarget);
				
				if (Util.isNothing(clickedEl)){
					return;
				}
				
				showPhotoSwipe(clickedEl);
			
			}, false);
			
		}
		else{
						
			// Add a click event handler on each element
			for (var i = 0; i < thumbEls.length; i++){
				
				var thumbEl = thumbEls[i];
				thumbEl.addEventListener('click', onClick, false);
				
			}
		
		}
		
		return thumbEls;
			
	};
	
	
	
	/*
	 * jQuery plugin
	 */
	if (!Util.isNothing(window.jQuery)){
	
		window.jQuery.fn.photoSwipe = function (opts) {
			
			var thumbEls = this;
			Code.PhotoSwipe.Current.setOptions(opts);
			Code.PhotoSwipe.Current.setImages(thumbEls);
			
			jQuery(thumbEls).live('click', function(e){
				
				e.preventDefault();
				
				var startingIndex = jQuery(thumbEls).index(jQuery(e.currentTarget));
				Code.PhotoSwipe.Current.show(startingIndex);
				
			});
			
		};
		
	}
	
	
})
(
	window,
	Code.PhotoSwipe.Util, 
	Code.PhotoSwipe.ElementClass,
	Code.PhotoSwipe.DocumentOverlayClass,
	Code.PhotoSwipe.FullSizeImageClass,
	Code.PhotoSwipe.ViewportClass,
	Code.PhotoSwipe.SliderClass,
	Code.PhotoSwipe.CaptionClass,
	Code.PhotoSwipe.ToolbarClass,
	Code.PhotoSwipe.CaptionToolbarClass,
	Code.PhotoSwipe.ZoomPanRotateClass
);
