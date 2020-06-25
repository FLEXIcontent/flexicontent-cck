/**
 * MultiBox for jQuery with Vegas Background jQuery Plugin
 * @version 0.1
 * @author Yoshiki Kozaki
 * @link http://www.joomler.net/multibox/
 * @email info@joomler.net
 * @license Dual licensed under the MIT and GPL licenses.
 * @Copyright Copyright (C) Yoshiki Kozaki
 */

/**************************************************************
Base script. Great!!
 Script		: MultiBox
 Version		: 1.4.1
 Authors		: Samuel Birch
 Desc		: Supports jpg, gif, png, flash, flv, mov, wmv, mp3, html, iframe
 Licence		: Open Source MIT Licence
 **************************************************************/

(function($){
	$.fn.jmultibox = function(options){
		var openClosePos = {};
		var timer = 0;
		var contentToLoad = 0;
		var index = 0;
		var opened = false;
		var isYoutube = false;
		var contentObj = {};
		var containerDefaults = {};
		var overlay;
		var type, str, elementContent, container, controlsContainer
		, previousButton, panelPosition, nextButton, content, openId
		, descriptions, tempSRC, box
		, contentContainer, elementContentParent, iframe
		, controls, number, description, title, closeButton, titleMargin;

		options = $.extend({
			initialWidth: 250,
			initialHeight: 250,
			container: document.body, //this will need to be setup to the box open in relation to
			contentColor: '#000',
			showNumbers: true,
			showControls: true,
			descClassName: false,
			descMinWidth: 400,
			descMaxWidth: 600,
			movieWidth: 576,
			movieHeight: 324,
			offset: {x: 0, y: 0},
			fixedTop: false,
			path: '',
			openFromLink: true,
			opac:0.7,
			useOverlay:false,
			overlaybg:'01.png',
			onOpen:function(){},
			onClose:function(){},
			easing:'swing',
			useratio:false,
			ratio:'90'
		}, options);

		//Init options value
		//path
		if(options.path.length){
			if(options.path.indexOf('/') !== 0){
				options.path = '/'+options.path;
			}
		}
		options.path += '';

		//ratio
		if(options.useratio){
			options.ratio = parseInt(options.ratio, 10);
			if(options.ratio < 20){
				options.useratio = false;
			}
			else {
				options.ratio = options.ratio/100;
			}
		}

		var setContentType = function(link)
		{
			var str = link.href.substr(link.href.lastIndexOf('.') + 1).toLowerCase();
			var contentOptions = {};
			var rel = link.getAttribute('data-rel') ? link.getAttribute('data-rel') : link.getAttribute('rel');

			if (rel)
			{
				var optArr = rel.split(',');
				$(optArr).each(function(i, el) {
					if(el.indexOf(':') > 0){
						var ta = el.split(':');
						contentOptions[ta[0]] = ta[1];
					}
				});
			}

			if (contentOptions.type !== undefined) {
				str = contentOptions.type;
			}

			contentObj = {};
			contentObj.url = link.href;
			contentObj.src = link.href;
			contentObj.xH = 0;
			if (contentOptions.width) {
				contentObj.width = contentOptions.width;
			} else {
				contentObj.width = options.movieWidth;
			}
			if (contentOptions.height) {
				contentObj.height = contentOptions.height;
			} else {
				contentObj.height = options.movieHeight;
			}
			if (contentOptions.panel) {
				panelPosition = contentOptions.panel;
			} else {
				panelPosition = options.panel;
			}

			switch (str) {
				case'jpg':
				case 'image':
				case'gif':
				case'png':
					type = 'image';
					break;
				case'swf':
					type = 'flash';
					break;
				case 'mp4':
				case'flv':
					type = 'flashVideo';
					contentObj.xH = 70;
					break;
				case'mov':
					type = 'quicktime';
					break;
				case'wmv':
					type = 'windowsMedia';
					break;
				case'rv':
				case'rm':
				case'rmvb':
					type = 'real';
					break;
				case'mp3':
					type = 'flashMp3';
					contentObj.width = 320;
					contentObj.height = 70;
					break;
				case'element':
					type = 'htmlelement';
					elementContent = link.content;
					elementContent.css({
						display: 'block',
						opacity: 0
					});

					if (contentOptions.width) {
						contentObj.width = contentOptions.width;

					} else if (elementContent.css('width') !== 'auto') {
						contentObj.width = elementContent.css('width');
					}

					if (contentOptions.height) {
						contentObj.height = contentOptions.height;
					} else {
						contentObj.height = elementContent.getSize().y;
					}
					elementContent.css({
						display: 'none',
						opacity: 1
					});
					break;

				default:
					if (contentObj.url.match(/youtube\.com\/v/i)
							|| contentObj.url.match(/youtu\.be/i)) {
						type = 'element';
						isYoutube = true;
						break
					}
					if (contentObj.url.match(/youtube\.com\/watch\?v=/)) {
						type = 'element';
						isYoutube = true;
						contentObj.url = 'http://www.youtube.com/v/' + contentObj.url.replace(/.*?youtube\.com\/watch\?v=/, '').replace(/&=.*$/, '');
						break
					}
					type = 'iframe';
					if (contentOptions.ajax) {
						type = 'ajax';
					}
					break
			}

			changeResolution();
		};

		var reset = function() {
			container.css({
				'opacity': 0,
				'display': 'none'
			});
			controlsContainer.css({
				'height': 0
			});
			removeContent();
			previousButton.removeClass('MultiBoxButtonDisabled');
			nextButton.removeClass('MultiBoxButtonDisabled');
			opened = false;
		};

		var getOpenClosePos = function(el) {
			var border = parseInt(container.css('border-left-width'), 10);
			if (options.openFromLink) {
				openId = el.attr('id');
				var first = el.children().get(0);
				if (first) {
					var w = $(first).width() - (border * 2);
					if (w < 0) {
						w = 0;
					}
					var h = $(first).height() - (border * 2);
					if (h < 0) {
						h = 0;
					}

					openClosePos = {
						width: w,
						height: h,
						top: $(first).offset().top,
						left: $(first).offset().left
					};
				}
				else {
					var w = el.width() - (border * 2);
					if (w < 0) {
						w = 0;
					}
					var h = el.height() - (border * 2);
					if (h < 0) {
						h = 0;
					}
					openClosePos = {
						width: w,
						height: h,
						top: el.offset().top,
						left: el.offset().left
					};
				}
			} else {
				if (options.fixedTop) {
					var top = options.fixedTop;
				} else {
					var top = (($(window).height() / 2) - (options.initialHeight / 2) - border) + options.offset.y + $(window).scrollTop();
				}
				openClosePos = {
					width: options.initialWidth,
					height: options.initialHeight,
					top: top,
					left: (($(window).width() / 2) - (options.initialWidth / 2) - border) + options.offset.x
				};
			}
			return openClosePos;
		};
		var open = function(el, i) {
			options.onOpen(el, i);
			index = i;
			var border = parseInt(container.css('border-left-width'), 10);

			var exists = parseInt(container.css('opacity'), 10);
			if(!exists){
				opened = getExists();
				if(opened.length){
					var btn = $(opened[0]).children('.MultiBoxClose');
					btn.trigger('click');
					delay(function(){return open(el, i);}, 1100);

				}
				else {
					opened = true;

					if (options.useOverlay) {
						alert(options.path+'overlays/'+options.overlaybg);
						$.vegas('overlay',{
							src: options.path+'overlays/'+options.overlaybg,
							opacity:options.opac
						});
						$('.vegas-overlay').bind('click', close);
					}

					container.css(getOpenClosePos(el));
					container.css({
						opacity: 0,
						display: 'block'
					});

					if (options.fixedTop) {
						var top = options.fixedTop;
					} else {
						var top = (($(window).height() / 2) - (options.initialHeight / 2) - border) + options.offset.y + $(window).scrollTop();
					}

					load(index);
				}

				return false;
			}
				if (options.showControls) {
					hideControls();
				}
				getOpenClosePos($(content[index]));
				delay(hideContent, 500);
				delay(function(){load(index);}, 1100);

			return false;
		};

		var getContent = function(index) {
			setContentType(content[index]);

			var desc = false;
			if (options.descClassName) {
				descriptions.each(function(i, el) {
					if ($(el).hasClass(openId) || $(el).prev() === $(content[index])) {
						desc = $(el).clone();
					}
				});
			}

			contentToLoad = {
				title: content[index].title || ' ',
				desc: desc,
				number: index + 1
			};
		};

		var close = function() {
			//Hide overlay
			if (options.useOverlay) {
				$('.vegas-overlay').animate({
					opacity:0
				}, 800, options.easing, function(){
					$.vegas('destroy', 'overlay');
				});
			}

			if (options.showControls) {
				hideControls();
			}
			hideContent();
			delay(zoomOut, 500);
			options.onClose();
		};

		var getExists = function(){
			return $('.MultiBoxContainer').filter(function(){
				return parseInt($(this).css('opacity'), 10) === 1;
			});
		};

		var zoomOut = function() {
			iframe.animate({
				width: openClosePos.width,
				height: openClosePos.height
			}, 400, options.easing);
			container.animate({
				width: openClosePos.width,
				height: openClosePos.height,
				top: openClosePos.top,
				left: openClosePos.left,
				opacity: 0
			}, 400, options.easing, function(){delay(reset, 500);});

		};

		var load = function(index) {
			box.addClass('MultiBoxLoading');
			getContent(index);

			if (type === 'image') {
				var xH = contentObj.xH;
				contentObj = createImage(content[index].href, {onload:resize});
				contentObj.xH = xH;
			} else {
				resize();
			}
		};

		var resize = function() {
			if (tempSRC !== contentObj.src) {
				var border = parseInt(container.css('border-left-width'), 10);

				if (options.fixedTop) {
					var top = options.fixedTop;
				}
				else {
					var top = (($(window).height() / 2) - ((parseInt(contentObj.height, 10) + contentObj.xH) / 2) - border + $(window).scrollTop()) + options.offset.y;
				}

				var left = (($(window).width() / 2) - (parseInt(contentObj.width, 10) / 2) - border) + options.offset.x;

				if (top < 0) {
					top = 0;
				}
				if (left < 0) {
					left = 0;
				}

				contentObj.width = parseInt(contentObj.width, 10);
				contentObj.height = parseInt(contentObj.height, 10);
				contentObj.xH = parseInt(contentObj.xH, 10);
				container.animate({
					width: contentObj.width,
					height: contentObj.height+contentObj.xH,
					top: top,
					left: left,
					opacity: 1
				}, 400, options.easing);

				iframe.animate({
					width: contentObj.width + (border * 2),
					height: contentObj.height + contentObj.xH + (border * 2)
				}, 400, options.easing);
				timer = delay(showContent, 500);
				tempSRC = contentObj.src;
			}
		};
		var showContent = function() {
			tempSRC = '';
			box.removeClass('MultiBoxLoading');
			removeContent();
			contentContainer = $('<div>', {id: 'MultiBoxContentContainer'})
					.css({opacity: 0, width: contentObj.width + 'px', height: (parseInt(contentObj.height, 10) + contentObj.xH) + 'px'})
					.appendTo(box);

			if (type === 'image') {
				$(contentObj).appendTo(contentContainer);

			} else if (type === 'iframe') {
				$('<iframe>', {
					id: 'iFrame' + new Date().getTime(),
					width: contentObj.width,
					height: contentObj.height,
					src: contentObj.url,
					frameborder: 0,
					scrolling: 'auto'
				}).appendTo(contentContainer);

			} else if (type === 'htmlelement') {
				contentContainer.css('overflow', 'auto');
				elementContentParent = elementContent.parent();
				elementContent.css('display', 'block').appendTo(contentContainer);
			} else if (type === 'ajax') {
				$.ajax({
					url:contentObj.url,
					cache:false,
					success:function(html){
						$('#MultiBoxContentContainer').append(html);
					}
				});
			} else {
				var obj = createEmbedObject().appendTo(contentContainer);
				if (str !== '') {
					$('#MultiBoxMediaObject').html(str);
				}
			}

			contentContainer.animate({
				opacity:1
			}, 500, options.easing);

			title.text(contentToLoad.title);
			if (content.length > 1) {
			number.text(contentToLoad.number + ' of ' + content.length);
			} else {
				number.text('');
			}

			if (options.descClassName) {
				var children = description.children();
				if (children.length) {
					$(children[0]).remove();
				}

				if (contentToLoad.desc) {
					$(contentToLoad.desc).appendTo(description).css({
						display: 'block'
					});
				}
			}

			if (options.showControls) {
				if (contentToLoad.title !== '&nbsp;' || content.length > 1) {
					timer = delay(showControls, 800);
				}
			}
		};
		var hideContent = function() {
			box.addClass('MultiBoxLoading');
			contentContainer.animate({
				opacity: 0
			}, 500, options.easing);
			delay(removeContent, 500);
		};
		var removeContent = function() {
			if ($('#MultiBoxMediaObject')) {
				$('#MultiBoxMediaObject').empty();
				$('#MultiBoxMediaObject').remove();
			}
			if ($('#MultiBoxContentContainer')) {
				if (type === 'htmlelement') {
					elementContent.css('display', 'none').appendTo(elementContentParent);
				}
				$('#MultiBoxContentContainer').remove();
			}
		};

		var showControls = function() {
			clicked = false;

			if (container.css('height') !== 'auto') {
				containerDefaults.height = container.css('height');
				containerDefaults.backgroundColor = options.contentColor;
			}

			container.css({
				//'backgroundColor': controls.css('backgroundColor'),
				'height': 'auto'
			});

			if (content.length > 1) {
				previousButton.css('visibility', 'visible');
				nextButton.css('visibility', 'visible');
				title.css('margin-left', titleMargin);
			} else {
				previousButton.css('visibility', 'hidden');
				nextButton.css('visibility', 'hidden');
				title.css('margin-left', 0);
			}

			controlsContainer.animate({
				'height': controls.height()
				}, 300, options.easing);
			iframe.animate({
				'height': parseInt(iframe.css('height'), 10) + parseInt(controls.css('height'), 10)
			}, 400, options.easing);

		};

		var hideControls = function(num) {
			iframe.animate({
				'height': parseInt(iframe.css('height'), 10) - parseInt(controls.css('height'), 10)
			}, 400, options.easing);
			controlsContainer.animate({
				'height': 0
				}, 300, options.easing, function(){
					container.css(containerDefaults);
				});
		};

		var next = function() {
			index++;
			if (index > content.length - 1) {
				index = 0;
			}

			openId = $(content[index]).attr('id');
			if (options.showControls) {
				hideControls();
			}

			getOpenClosePos($(content[index]));
			//getContent(index);
			delay(hideContent, 500);
			delay(function(){load(index);},1100);
		};

		var previous = function() {
			index--;
			if (index < 0) {
				index = content.length - 1;
			}

			openId = $(content[index]).attr('id');
			if (options.showControls) {
				hideControls();
			}
			getOpenClosePos($(content[index]));
			delay(hideContent, 500);
			delay(function(){load(index);}, 1000);
		};

		var changeResolution = function(){
			if(options.useratio){
				if(type === 'iframe' || isYoutube){
					contentObj.width = parseInt($(window).width() * options.ratio, 10);
					if(type === 'iframe'){
						contentObj.height = parseInt($(window).height() * options.ratio, 10);
					}
					else{
						//TODO Ratio
						contentObj.height = parseInt(contentObj.width * 10/16);
					}
				}
			}
		};

		var createImage = function(source, properties){
			if (!properties) properties = {};

			var image = new Image(),
				element = $(image);// || $('<img>');

			$.each(['load', 'abort', 'error'], function(i, name){
				var type = 'on' + name,
					cap = 'on' + name.toLowerCase().replace(/\b[a-z]/g, function(letter) {
							return letter.toUpperCase();
						}),
					event = properties[type] || properties[cap] || function(){};
				delete properties[cap];
				delete properties[type];

				image[type] = function(){
					if (!element.parentNode){
						element.width = image.width;
						element.height = image.height;
					}
					image.onload = image.onabort = image.onerror = null;
					delay(event, 1);
				};
			});

			image.src = element.src = source;
			return element.attr(properties);
		};

		var createEmbedObject = function(url, obj) {
			url = contentObj.url;
			switch (type) {
				case'flash':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,28,0" ';
					str += 'width="'+contentObj.width+'" ';
					str += 'height="'+contentObj.height+'" ';
					str += 'title="MultiBoxMedia">';
					str += '<param name="movie" value="'+url+'" />';
					str += '<param name="quality" value="high" />';
					str += '<embed src="'+url+'" ';
					str += 'quality="high" pluginspage="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" ';
					str += 'width="'+contentObj.width+'" ';
					str += 'height="'+contentObj.height+'"></embed>';
					str += '</object>';
					break;
				case'flashVideo':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,28,0" ';
					str += 'width="' + contentObj.width + '" ';
					str += 'height="' + (parseInt(contentObj.height, 10) + contentObj.xH) + '" ';
					str += 'title="MultiBoxMedia">';
					str += '<param name="movie" value="' + options.path + 'files/flvplayer.swf" />';
					str += '<param name="quality" value="high" />';
					str += '<param name="salign" value="TL" />';
					str += '<param name="scale" value="noScale" />';
					str += '<param name="FlashVars" value="path=' + url + '" />';
					str += '<embed src="' + options.path + 'files/flvplayer.swf" ';
					str += 'quality="high" pluginspage="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" ';
					str += 'width="' + contentObj.width + '" ';
					str += 'height="' + (parseInt(contentObj.height, 10) + contentObj.xH) + '"';
					str += 'salign="TL" ';
					str += 'scale="noScale" ';
					str += 'FlashVars="path=' + url + '"';
					str += '></embed>';
					str += '</object>';
					break;
				case'flashMp3':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,28,0" ';
					str += 'width="' + contentObj.width + '" ';
					str += 'height="' + contentObj.height + '" ';
					str += 'title="MultiBoxMedia">';
					str += '<param name="movie" value="' + options.path + 'files/mp3player.swf" />';
					str += '<param name="quality" value="high" />';
					str += '<param name="salign" value="TL" />';
					str += '<param name="scale" value="noScale" />';
					str += '<param name="FlashVars" value="path=' + url + '" />';
					str += '<embed src="' + options.path + 'files/mp3player.swf" ';
					str += 'quality="high" pluginspage="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" ';
					str += 'width="' + contentObj.width + '" ';
					str += 'height="' + contentObj.height + '"';
					str += 'salign="TL" ';
					str += 'scale="noScale" ';
					str += 'FlashVars="path=' + url + '"';
					str += '></embed>';
					str += '</object>';
					break;
				case'quicktime':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object  type="video/quicktime" classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab"';
					str += ' width="' + contentObj.width + '" height="' + contentObj.height + '">';
					str += '<param name="src" value="' + url + '" />';
					str += '<param name="autoplay" value="true" />';
					str += '<param name="controller" value="true" />';
					str += '<param name="enablejavascript" value="true" />';
					str += '<embed src="' + url + '" autoplay="true" pluginspage="http://www.apple.com/quicktime/download/" width="' + contentObj.width + '" height="' + contentObj.height + '"></embed>';
					str += '<object/>';
					break;
				case'windowsMedia':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object  type="application/x-oleobject" classid="CLSID:22D6f312-B0F6-11D0-94AB-0080C74C7E95" codebase="http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#Version=6,4,7,1112"';
					str += ' width="' + contentObj.width + '" height="' + contentObj.height + '">';
					str += '<param name="filename" value="' + url + '" />';
					str += '<param name="Showcontrols" value="true" />';
					str += '<param name="autoStart" value="true" />';
					str += '<embed type="application/x-mplayer2" src="' + url + '" Showcontrols="true" autoStart="true" width="' + contentObj.width + '" height="' + contentObj.height + '"></embed>';
					str += '<object/>';
					break;
				case'real':
					obj = $('<div>', {id: 'MultiBoxMediaObject'});
					str = '<object classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA"';
					str += ' width="' + contentObj.width + '" height="' + contentObj.height + '">';
					str += '<param name="src" value="' + url + '" />';
					str += '<param name="controls" value="ImageWindow" />';
					str += '<param name="autostart" value="true" />';
					str += '<embed src="' + url + '" controls="ImageWindow" autostart="true" width="' + contentObj.width + '" height="' + contentObj.height + '"></embed>';
					str += '<object/>';
					break;
				default:
					if(isYoutube){
						var videoid;
						//getId
						if (url.match(/youtu\.be/i)) {
							videoid = url.replace(/.*?youtu\.be\//, '');
						} else if (url.match(/youtube\.com\/watch\?v=/)) {
							videoid = url.replace(/.*?youtube\.com\/watch\?v=/, '').replace(/&=.*$/, '');
						} else if(url.match(/youtube\.com\/v/)){
							videoid = url.replace(/.*?youtube\.com\/v\//, '').replace(/&=.*$/, '');
						}

						obj = $('<iframe>', {
							id: 'MultiBoxMediaObject',
							width:contentObj.width,
							height:contentObj.height,
							src:'http://www.youtube.com/embed/'+videoid,
							frameborder:0,
							allowfullscreen:true
						});
					}
			}

			return obj;
		};

		var delay = function(fn, msec){
			if(fn){
				return setTimeout(fn, msec);
			}
		};

		content = this;

		if(options.descClassName){
			descriptions = $('.'+options.descClassName);
			descriptions.each(function(i, el){
				$(el).css('display', 'none');
			});
		}

		container = $('<div>').addClass('MultiBoxContainer').appendTo(options.container);
		iframe = $('<iframe>', {
			'id': 'multiBoxIframe',
			'name': 'mulitBoxIframe',
			'src': 'javascript:void(0);',
			'frameborder': 0,
			'scrolling': 'no'
		}).css({
			'position': 'absolute',
			'top': -20,
			'left': -20,
			'filter': 'progid:DXImageTransform.Microsoft.Alpha(style=0,opacity=0)',
			'opacity': 0
		}).appendTo(container);

		box = $('<div>').addClass('MultiBoxContent').appendTo(container);
		closeButton = $('<div>').addClass('MultiBoxClose').appendTo(container).bind('click', close);
		controlsContainer = $('<div>').addClass('MultiBoxControlsContainer').appendTo(container);
		controls = $('<div>').addClass('MultiBoxControls').appendTo(controlsContainer);

		previousButton = $('<div>').addClass('MultiBoxPrevious').appendTo(controls).bind('click', previous);
		nextButton = $('<div>').addClass('MultiBoxNext').appendTo(controls).bind('click', next);

		title = $('<div>').addClass('MultiBoxTitle').appendTo(controls);
		titleMargin = title.css('margin-left');
		number = $('<div>').addClass('MultiBoxNumber').appendTo(controls);
		description = $('<div>').addClass('MultiBoxDescription').appendTo(controls);

		if (content.length === 1) {
			title.css({
				'margin-left': 0
			});
			description.css({
				'margin-left': 0
			});
			previousButton.css('display', 'none');
			nextButton.css('display', 'none');
			number.css('display', 'none');
		}

		$('<div>').css('clear', 'both').appendTo(controls);

		content.each(function(i, el) {
			$(el).bind('click', function(e) {
				return open($(el), i);
			});
			if (el.href.indexOf('#') > -1) {
				el.content = $(el.href.substr(el.href.indexOf('#') + 1));
				if (el.content) {
					el.content.css('display', 'none');
				}
			}
		});

		reset();
	};
})(jQuery);