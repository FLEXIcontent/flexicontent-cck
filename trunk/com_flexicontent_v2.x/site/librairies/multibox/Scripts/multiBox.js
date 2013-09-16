/**************************************************************

	Script		: multiBox
	Version		: 2.0.6
	Authors		: Samuel Birch
	Desc		: Supports jpg, gif, png, flash, flv, mov, wmv, mp3, html, iframe
	Licence		: Open Source MIT Licence
	Modified	: Liam Smart (liam_smart@hotmail.com) - MooTools 1.2 upgrade
	Usage		: window.addEvent('domready', function(){
					  //call multiBox
					  var initMultiBox = new multiBox({
						  mbClass: '.mb',//class you need to add links that you want to trigger multiBox with (remember and update CSS files)
						  container: $(document.body),//where to inject multiBox
						  descClassName: 'multiBoxDesc',//the class name of the description divs
						  path: './Files/',//path to mp3 and flv players
						  useOverlay: true,//use a semi-transparent background. default: false;
						  maxSize: {w:1600, h:400},//max dimensions (width,height) - set to null to disable resizing
						  addDownload: true,//do you want the files to be downloadable?
						  pathToDownloadScript: './Scripts/forceDownload.asp',//if above is true, specify path to download script (classicASP and ASP.NET versions included)
						  addRollover: true,//add rollover fade to each multibox link
						  addOverlayIcon: true,//adds overlay icons to images within multibox links
						  addChain: true,//cycle through all images fading them out then in
						  recalcTop: true,//subtract the height of controls panel from top position
						  addTips: true,//adds MooTools built in 'Tips' class to each element (see: http://mootools.net/docs/Plugins/Tips)
						  autoOpen: 0//to auto open a multiBox element on page load change to (1, 2, or 3 etc)
					  });
				  });

**************************************************************/

if (typeof $chk != 'function') { 
	var $chk = function(obj) {  return !!(obj || obj === 0);  };
}

//start multiBox class
var multiBox = new Class({
	
	//implements
	Implements: Options,
	
	//options
	options:{
		initialSize: {w:250, h:250},//initial width/height the box will open at before resizing
		useOverlay: false,//do you want to use a semi-transparent background?
		contentColor: '#fff',//background colour of the content holder within the pop-up
		showNumbers: true,//show numbers such as "4 of 12"
		showControls: true,//show the previous/next, title, download etc
		descClassName: false,//class of description box
		movieSize: {w:400, h:300},//default width/height of movie
		offset: {x:0, y:0},//offset multiBox position
		fixedTop: false,//force multiBox to open at top of page
		path: './Files/',//path to mp3player and flvplayer etc
		openFromLink: true,//pop-up will slide in from the position of the element clicked
		useKeyboard: true//allow keyboard shortcuts (esc: close, spacebar & right arrow: next, left arrow: previous)
	},

	//initialization
	initialize: function(options){
		//set options
		this.setOptions(options);
		//set variables
		this.openClosePos = {};
		this.contentToLoad = {};
		this.contentObj = {};
		this.containerDefaults = {};
		this.multiBox = [];
		this.families = [];
		this.content = [];
		this.timer = 0;
		this.index = 0;
		this.opened = false;
		this.currentGallery = null;
		//start multiBox
		if($$(this.options.mbClass).length > 0){this.start();};
	},
	
	//start multiBox
	start: function(){
		//there will be no next/previous buttons unless you specify them to a group
		$$(this.options.mbClass).each(function(el){
			//we must store original rel & title values to use later
			if($chk(el.get('rel'))){
				el.store('origRel',el.get('rel'));
			};
			if($chk(el.get('title'))){
				el.store('origTitle',el.get('title'));
			};
			//check if it has a rel="[group]"
			if(el.rel.test(/\[*?]/i)){
				//if there are more than 1 rel value, we need to split them to find our group
				if(el.get('rel').contains(',')){
					//split then loop through each array instance of the split rel's
					var tempArr = el.get('rel').split(',');
					tempArr.each(function(temp,i){
						if(temp.contains('[')){//only take out the rel relating to a [group]
							//change this links relation to the temp variable
							el.set('rel',temp);
						};
					},this);
				};
				//if rel isnt already in fanilies then create a new instance for it
				this.families.include(el.get('rel'));
			};
			//finally now we have put them into families, push each link with 'mbClass' into multiBox array
			this.multiBox.push(el);
		},this);
		//loop through each mb link seperating content into groups of families
		this.multiBox.each(function(el){
			//check rel contains a group
			if(el.rel.test(/\[*?]/i)){
				//we know the link has a group so loop through each family to find where it belongs
				this.families.each(function(fam,i){
					//if the rel belongs to a family we make sure its pushed into correct family array within content array
					if(el.get('rel') == fam){
						//if there isnt a family array within content array to hold this family create one
						if(!this.content[i]){
							//create new gallery
							this.content[i] = [];
						};
						//finally push link into appropriate family within content array
						this.content[i].push(el);
					};
				},this);
			};
		},this);
		//loop through each mb link seperating content into groups of families
		this.multiBox.each(function(el){
			//check rel DOESNT contain a group
			if(el.rel.test(/\[*?]/i) == false){
				//add link into content array as a single array as it doesnt belong to a family
				this.content.push([el]);
			};
		},this);
		
		this.container = new Element('div').addClass('MultiBoxContainer').inject(this.options.container,'inside');
		this.iframe = new Element('iframe', {
			'id': 'multiBoxIframe',
			'name': 'mulitBoxIframe',
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
				opacity: 0
			}
		}).inject(this.container,'inside');

		this.box = new Element('div').addClass('MultiBoxContent').inject(this.container,'inside');
		this.closeButton = new Element('div').addClass('MultiBoxClose').inject(this.container,'inside').addEvent('click', this.close.bind(this));
		this.controlsContainer = new Element('div').addClass('MultiBoxControlsContainer').inject(this.container,'inside');
		this.controls = new Element('div').addClass('MultiBoxControls').inject(this.controlsContainer,'inside');
		this.previousButton = new Element('div').addClass('MultiBoxPrevious').inject(this.controls,'inside').addEvent('click', this.previous.bind(this));
		this.nextButton = new Element('div').addClass('MultiBoxNext').inject(this.controls,'inside').addEvent('click', this.next.bind(this));
		this.title = new Element('div').addClass('MultiBoxTitle').inject(this.controls,'inside');
		this.number = new Element('div').addClass('MultiBoxNumber').inject(this.controls,'inside');
		this.description = new Element('div').addClass('MultiBoxDescription').inject(this.controls,'inside');
		
		//check user options and call functions accordingly
		if(this.options.useKeyboard){
			$(window.document).addEvent('keydown',function(e){
				if(e.key == 'right' || e.key == 'space'){
					this.next();
				}else if(e.key == 'left'){
					this.previous();
				}else if(e.key == 'esc'){
					this.close();
				};
			}.bind(this));
		};
		if(this.options.useOverlay){
			this.overlay = new Overlay({
				container:this.options.container,
				onClick:this.close.bind(this)
			});
		};
		if(this.options.addOverlayIcon == true){
			this.addOverlayIcon(this.multiBox);
		};
		if(this.options.addRollover == true){
			this.addRollover(this.multiBox);
		};
		if(this.options.addChain == true){
			this.addChain(this.multiBox);
		};
		if(this.options.descClassName){
			this.descriptions = $$('.'+this.options.descClassName);
		};
		if(this.options.addDownload == true){
			this.addDownload(this.multiBox);
		};
		if(this.options.addTips == true){
			this.addTips(this.multiBox);
		};

		//if there is only one multiBox link don't show unneccesary buttons
		if(this.multiBox.length == 1){
			this.title.setStyle('margin-left',0);
			this.description.setStyle('margin-left',0);
			this.previousButton.setStyle('display','none');
			this.nextButton.setStyle('display','none');
			this.number.setStyle('display','none');
		};
		
		new Element('div').setStyle('clear','both').inject(this.controls,'inside');
		
		//start breaking into content array to add event listeners to each link within each group
		this.content.each(function(el,i){
			//now we are left with each group as arrays
			el.each(function(group,i){
				//add event listener
				group.addEvent('click', function(e){
					var myTarget = ($(e.target).match('a')) ? $(e.target) : $(e.target).getParent('a');
					e.preventDefault();
					this.open(el.indexOf(myTarget),el);
				}.bind(this));
				//check to see if link is an HTML element
				if(group.href.indexOf('#') > -1){
					//grab it as an object
					group.content = $(group.href.substr(group.href.indexOf('#')+1));
					//hide the object
					if(group.content){
						group.content.setStyle('display','none');
					};
				};
			},this);
		},this);
		
		this.containerEffects = new Fx.Morph(this.container,{duration:400});
		this.controlEffects = new Fx.Morph(this.controlsContainer,{duration:300});
		this.reset();
		
		//auto open a multiBox element
		if(this.options.autoOpen > 0){
			this.autoOpen(this.multiBox);
		};
	},
	
	setContentType: function(element){
		var str = element.href.substr(element.href.lastIndexOf('.')+1).toLowerCase();
		var myRel = element.retrieve('origRel');
		var contentOptions = {};
		//retrieve original rel values and make sure there was one
		if($chk(myRel)){
			//split the options just incase there are more than 1
			var optArr = myRel.split(',');
			optArr.each(function(el){
				//make sure the group is ignored
				if(el.test(/\[*?]/i) != true){
					var ta = el.split(':');
					contentOptions[ta[0]] = ta[1];
				};
			});
		};
		
		if(contentOptions.type != undefined){
			str = contentOptions.type;
		};
		
		this.contentObj.url = element.href;
		this.contentObj.xH = 0;
		
		if(contentOptions.width){
			this.contentObj.width = contentOptions.width;
		}else{
			this.contentObj.width = this.options.movieSize.w;
		};
		if(contentOptions.height){
			this.contentObj.height = contentOptions.height;
		}else{
			this.contentObj.height = this.options.movieSize.h;
		};
		if(contentOptions.panel){
			this.panelPosition = contentOptions.panel;
		}else{
			this.panelPosition = this.options.panel;
		};
		
		switch(str){
			case 'jpg':
			case 'gif':
			case 'png':
				this.type = 'image';
				break;
			case 'swf':
				this.type = 'flash';
				break;
			case 'flv':
				this.type = 'flashVideo';
				this.contentObj.xH = 70;
				break;
			case 'mov':
				this.type = 'quicktime';
				break;
			case 'wmv':
				this.type = 'windowsMedia';
				break;
			case 'rv':
			case 'rm':
			case 'rmvb':
				this.type = 'real';
				break;
			case 'mp3':
				this.type = 'flashMp3';
				this.contentObj.width = 320;
				this.contentObj.height = 70;
				break;
			case 'element':
				this.type = 'htmlelement';
				this.elementContent = element.content;
				this.elementContent.setStyles({
					display: 'block',
					opacity: 0,
					width: 'auto'//added this to get htmlElement to behave
				});
				
				//check and see if styles are being applied to HTML content section
				if(this.elementContent.getStyle('width') != 'auto'){
					this.contentObj.width = this.elementContent.getWidth();
				};
				
				this.contentObj.height = this.elementContent.getHeight();
				this.elementContent.setStyles({
					display: 'none',
					opacity: 1
				});
				break;
			default:
				this.type = 'iframe';
				if(contentOptions.req){
					this.type = 'req';
				};
				break;
		}
	},
	
	reset: function(){
		this.container.setStyles({
			opacity: 0,
			display: 'none'
		});
		this.controlsContainer.setStyle('height',0);
		this.removeContent();
		this.previousButton.removeClass('MultiBoxButtonDisabled');
		this.nextButton.removeClass('MultiBoxButtonDisabled');
		this.opened = false;
	},
	
	getOpenClosePos: function(element){
		if(this.options.openFromLink){
			if(element.getFirst()){
				var w = element.getFirst().getCoordinates().width - (this.container.getStyle('border').toInt() * 2);
				if(w < 0){
					w = 0;
				};
				var h = element.getFirst().getCoordinates().height - (this.container.getStyle('border').toInt() * 2);
				if(h < 0){
					h = 0;
				};
				this.openClosePos = {
					width: w,
					height: h,
					top: element.getFirst().getCoordinates().top,
					left: element.getFirst().getCoordinates().left
				};
			}else{
				var w = element.getCoordinates().width - (this.container.getStyle('border').toInt() * 2);
				if(w < 0){
					w = 0;
				};
				var h = element.getCoordinates().height - (this.container.getStyle('border').toInt() * 2);
				if(h < 0){
					h = 0;
				};
				this.openClosePos = {
					width: w,
					height: h,
					top: element.getCoordinates().top,
					left: element.getCoordinates().left
				};
			};
		}else{
			if(this.options.fixedTop){
				var top = this.options.fixedTop;
			}else{
				var top = ((window.getHeight()/2)-(this.options.initialSize.h/2)-this.container.getStyle('border').toInt())+this.options.offset.y;
			};
			this.openClosePos = {
				width: this.options.initialSize.w,
				height: this.options.initialSize.h,
				top: top,
				left: ((window.getWidth()/2)-(this.options.initialSize.w/2)-this.container.getStyle('border').toInt())+this.options.offset.x
			};
		};
		return this.openClosePos;
	},
	
	open: function(index,currGal){
		//need to store current gallery and index of the object in gallery
		this.currentGallery = currGal;
		this.index = index;
		//grab id so description can be matched
		this.openId = this.currentGallery[this.index].getProperty('id');
		//check to see if mb is already open
		if(!this.opened){
			this.opened = true;
			
			if(this.options.useOverlay){
				this.overlay.show();
			};
			
			this.container.setStyles(this.getOpenClosePos(this.currentGallery[this.index]));
			this.container.setStyles({
				opacity: 0,
				display: 'block'
			});
			
			if(this.options.fixedTop){
				var top = this.options.fixedTop;
			}else{
				var top = ((window.getHeight()/2)-(this.options.initialSize.h/2)-this.container.getStyle('border').toInt())+this.options.offset.y;
			};
			
			this.containerEffects.start({
				width: this.options.initialSize.w,
				height: this.options.initialSize.h,
				top: top,
				left: ((window.getWidth()/2)-(this.options.initialSize.w/2)-this.container.getStyle('border').toInt())+this.options.offset.x,
				opacity: [0, 1]
			});
			
			this.load(this.currentGallery[this.index]);
		}else{
			if(this.options.showControls){
				this.hideControls();
			};
			this.getOpenClosePos(this.currentGallery[this.index]);
			this.timer = this.hideContent.bind(this).delay(500);
			this.timer = this.load.pass(this.currentGallery[this.index],this).delay(1100);
		};
	},
	
	getContent: function(element){
		this.setContentType(element);
		var desc = {};
		if(this.options.descClassName){
			this.descriptions.each(function(el,i){
				if(el.hasClass(this.openId)){
					desc = el.clone();
				};
			},this);
		};
		this.contentToLoad = {
			title: element.retrieve('origTitle') || '&nbsp;',
			desc: desc,
			number: this.index+1
		};
	},
	
	close: function(){
		if(this.options.useOverlay){
			this.overlay.hide();
		};
		if(this.options.showControls){
			this.hideControls();
		};
		this.hideContent();
		this.containerEffects.cancel();
		this.zoomOut.bind(this).delay(500);
	},
	
	zoomOut: function(){
		this.containerEffects.start({
			width: this.openClosePos.width,
			height: this.openClosePos.height,
			top: this.openClosePos.top,
			left: this.openClosePos.left,
			opacity: 0
		});
		this.reset.bind(this).delay(500);
	},
	
	load: function(element){
		this.box.addClass('MultiBoxLoading');
		this.getContent(element);
		if(this.type == 'image'){
			var xH = this.contentObj.xH;
			this.contentObj = new Asset.image(element.href,{onload:this.resize.bind(this)});
			this.contentObj.xH = xH;
		}else{
			this.resize();
		};
	},
	
	resize: function(){
		//only resize if values have been set to resize to
		if(this.options.maxSize != null){
			var maxW = this.options.maxSize.w.toInt();//declare max width at top of script
			var maxH = this.options.maxSize.h.toInt();//declare max height at top of script
			var dW = 0;//set initial final width to 0
			var dH = 0;//set initial final height to 0
			var h = dH = this.contentObj.height;//retrieve image height
			var w = dW = this.contentObj.width;//retrieve image width
			
			if((h >= maxH) && (w >= maxW)){
				if(h > w){
					dH = maxH;
					dW = ((w * dH) / h).toInt();
				}else{
					dW = maxW;
					dH = ((h * dW) / w).toInt();
				};
			}else if((h > maxH) && (w < maxW)){
				dH = maxH;
				dW = ((w * dH) / h).toInt();
			}else if((h < maxH) && (w > maxW)){
				dW = maxW;
				dH = ((h * dW) / w).toInt();
			};
			
			this.contentObj.height = dH;//resize image height
			this.contentObj.width = dW;//resize image width
		};
		
		if(this.options.fixedTop){
			var top = this.options.fixedTop;
		}else{
			var top = ((window.getHeight() / 2) - ((Number(this.contentObj.height) + this.contentObj.xH) / 2) - this.container.getStyle('border').toInt() + window.getScrollTop()) + this.options.offset.y;
		};
		var left = ((window.getWidth() / 2) - (this.contentObj.width / 2) - this.container.getStyle('border').toInt()) + this.options.offset.x;
		if(top < 0){
			top = 0;
		};
		if(left < 0){
			left = 0;
		};
		
		this.containerEffects.cancel();
		this.containerEffects.start({
			width: this.contentObj.width,
			height: Number(this.contentObj.height) + this.contentObj.xH,
			top: top,
			left: left,
			opacity: 1
		});
		this.timer = this.showContent.bind(this).delay(500);
	},
	
	showContent: function(){
		this.box.removeClass('MultiBoxLoading');
		this.removeContent();
		this.contentContainer = new Element('div', {
			'id': 'MultiBoxContentContainer',
			'styles': {
				opacity: 0,
				width: this.contentObj.width,
				height: (Number(this.contentObj.height)+this.contentObj.xH)
			}
		}).inject(this.box,'inside');

		if(this.type == 'image'){
			this.contentObj.inject(this.contentContainer,'inside');
		}else if(this.type == 'iframe'){
			new Element('iframe', {
				'id': 'iFrame'+new Date().getTime(),
				'width': this.contentObj.width,
				'height': this.contentObj.height,
				'src': this.contentObj.url,
				'frameborder': 0,
				'scrolling': 'auto'
			}).inject(this.contentContainer,'inside');
		}else if(this.type == 'htmlelement'){
			this.elementContent.clone().setStyle('display','block').inject(this.contentContainer,'inside');
		}else if(this.type == 'req'){
			var req = new Request.HTML({
				url: this.contentObj.url,
				method: 'get',
				evalScripts: true,
				onSuccess: function(responseTree,responseElements,responseHTML,responseJavaScript){
					$('MultiBoxContentContainer').adopt(responseElements);
				},
				onFailure: function() {
					$('MultiBoxContentContainer').set('text','The request failed.');
				}
			}).send();
		}else{
			this.obj = new Element('div').setProperties({id: 'MultiBoxMediaObject'}).inject(this.contentContainer,'inside');
			this.createEmbedObject();
			//if its a movie inject the object string into obj
			if(this.str){
				this.obj.set('html',this.str);
				this.str = null;//clear the value after using it
			};
		};
		
		this.contentEffects = new Fx.Morph(this.contentContainer,{duration:500});
		this.contentEffects.start({
			opacity: 1
		});
		
		this.title.set('html',this.contentToLoad.title);
		this.number.set('html',this.contentToLoad.number+' of '+this.currentGallery.length);
		if(this.options.descClassName){
			//check to see if there is a desc override
			if(this.currentGallery[this.index].retrieve('origRel')){
				//declare variables
				var ignoreDesc = false;
				var myRel = this.currentGallery[this.index].retrieve('origRel');
				var optArr = myRel.split(',');
				//loop through each split looking for 'noDesc'
				optArr.each(function(el){
					if(el.test('noDesc') == true){
						ignoreDesc = true;
					};
				});
			};
			//check and see if user wants to override default description setting for this element
			if(ignoreDesc != true){
				if(this.description.getFirst()){
					this.description.getFirst().destroy();
				};
				this.contentToLoad.desc.inject(this.description,'inside').setStyle('display','block');
			};
		};

		if(this.options.showControls){
			this.timer = this.showControls.bind(this).delay(800);
		};
		
		if(this.options.addDownload){
			var filePath = this.currentGallery[this.index].href;
			var fileName = this.currentGallery[this.index].href.substring(this.currentGallery[this.index].href.lastIndexOf('/')+1);
			this.download.set('html','<a href="'+this.options.pathToDownloadScript+'?FilePath='+filePath+'" title="Download File '+fileName+'">Download File</a>');
			//empty download if its not an image
			if(this.type != 'image'){
				this.download.empty();
			};
		};
	},
	
	hideContent: function(){
		this.box.addClass('MultiBoxLoading');
		this.contentEffects.start({
			opacity: 0
		});
		this.removeContent.bind(this).delay(500);
	},
	
	removeContent: function(){
		if($('MultiBoxMediaObject')){
			$('MultiBoxMediaObject').empty();//so sound doesnt keep playing in IE
			$('MultiBoxMediaObject').dispose();//dispose() instead of destroy() as IE 6&7 crashes
		};
		if($('MultiBoxContentContainer')){
			$('MultiBoxContentContainer').dispose();//dispose() instead of destroy() as IE 6&7 crashes
		};
		if(this.description){
			this.description.empty();//empty description incase next element doesnt want to have one
		};
	},
	
	showControls: function(){
		if(this.container.getStyle('height') != 'auto'){
			this.containerDefaults.height = this.container.getStyle('height');
			this.containerDefaults.backgroundColor = this.options.contentColor;
			//controls box isnt taken into consideration when positioning the container from the top so correct this
			if(this.options.recalcTop == true){
				if(this.container.getStyle('top').toInt() > this.controls.getStyle('height').toInt()/2){
					this.finalResize = new Fx.Morph(this.container,{duration:400});
					this.finalResize.start({
						top: this.container.getStyle('top').toInt()-(this.controls.getStyle('height').toInt()/2)
					});
				};
			};
		};
		
		this.container.setStyle('height','auto');

		if(this.contentToLoad.number == 1){
			this.previousButton.addClass('MultiBoxPreviousDisabled');
		}else{
			this.previousButton.removeClass('MultiBoxPreviousDisabled');
		};
		if(this.contentToLoad.number == this.currentGallery.length){
			this.nextButton.addClass('MultiBoxNextDisabled');
		}else{
			this.nextButton.removeClass('MultiBoxNextDisabled');
		};
		
		this.controlEffects.start({
			'height': this.controls.getStyle('height')
		});
	},
	
	hideControls: function(num){
		this.controlEffects.start({'height': 0}).chain(function(){
			this.container.setStyles(this.containerDefaults);
		}.bind(this));
	},
	
	next: function(){
		if(this.index < this.currentGallery.length-1){
			this.index++;
			this.openId = this.currentGallery[this.index].getProperty('id');
			if(this.options.showControls){
				this.hideControls();
			};
			this.getOpenClosePos(this.currentGallery[this.index]);
			this.timer = this.hideContent.bind(this).delay(500);
			this.timer = this.load.pass(this.currentGallery[this.index],this).delay(1100);
		};
	},
	
	previous: function(){
		if(this.index > 0){
			this.index--;
			this.openId = this.currentGallery[this.index].getProperty('id');
			if(this.options.showControls){
				this.hideControls();
			};
			this.getOpenClosePos(this.currentGallery[this.index]);
			this.timer = this.hideContent.bind(this).delay(500);
			this.timer = this.load.pass(this.currentGallery[this.index],this).delay(1000);
		};
	},
	
	createEmbedObject: function(){
		if(this.type == 'flash'){
			var url = this.contentObj.url;
			var swfHolder = new Element('div').setProperties({id: 'swfHolder'}).inject(this.obj,'inside');
			var flashObj = new Swiff(url, {
				id: url,
				container: swfHolder,
				width: this.contentObj.width,
				height: this.contentObj.height
			});
		}else if(this.type == 'flashVideo'){
			var url = this.contentObj.url;
			var swfHolder = new Element('div').setProperties({id: 'swfHolder'}).inject(this.obj,'inside');
			var flashObj = new Swiff(this.options.path+'flvplayer.swf', {
				id: url,
				container: swfHolder,
				width: this.contentObj.width,
				height: (Number(this.contentObj.height)+this.contentObj.xH),
				vars: {
					path: url
				}
			});
		}else if(this.type == 'flashMp3'){
			var url = this.contentObj.url;
			var swfHolder = new Element('div').setProperties({id: 'swfHolder'}).inject(this.obj,'inside');
			var flashObj = new Swiff(this.options.path+'mp3player.swf', {
				id: url,
				container: swfHolder,
				width: this.contentObj.width,
				height: (Number(this.contentObj.height)+this.contentObj.xH),
				vars: {
					path: url
				}
			});
		}else if(this.type == 'quicktime'){
			var url = this.contentObj.url;
			this.str = '<object  type="video/quicktime" classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab"';
			this.str += ' width="'+this.contentObj.width+'" height="'+this.contentObj.height+'">';
			this.str += '<param name="src" value="'+url+'" />';
			this.str += '<param name="autoplay" value="true" />';
			this.str += '<param name="controller" value="true" />';
			this.str += '<param name="enablejavascript" value="true" />';
			this.str += '<embed src="'+url+'" autoplay="true" pluginspage="http://www.apple.com/quicktime/download/" width="'+this.contentObj.width+'" height="'+this.contentObj.height+'"></embed>';
			this.str += '</object>';
		}else if(this.type == 'windowsMedia'){
			var url = this.contentObj.url;
			this.str = '<object  type="application/x-oleobject" classid="CLSID:22D6f312-B0F6-11D0-94AB-0080C74C7E95" codebase="http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#Version=6,4,7,1112"';
			this.str += ' width="'+this.contentObj.width+'" height="'+this.contentObj.height+'">';
			this.str += '<param name="filename" value="'+url+'" />';
			this.str += '<param name="Showcontrols" value="true" />';
			this.str += '<param name="autoStart" value="true" />';
			this.str += '<embed type="application/x-mplayer2" src="'+url+'" Showcontrols="true" autoStart="true" width="'+this.contentObj.width+'" height="'+this.contentObj.height+'"></embed>';
			this.str += '</object>';
		}else if(this.type == 'real'){
			var url = this.contentObj.url;
			this.str = '<object classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA"';
			this.str += ' width="'+this.contentObj.width+'" height="'+this.contentObj.height+'">';
			this.str += '<param name="src" value="'+url+'" />';
			this.str += '<param name="controls" value="ImageWindow" />';
			this.str += '<param name="autostart" value="true" />';
			this.str += '<embed src="'+url+'" controls="ImageWindow" autostart="true" width="'+this.contentObj.width+'" height="'+this.contentObj.height+'"></embed>';
			this.str += '</object>';
		};
	},
	
	addOverlayIcon:function(element){
		//loop through each instance
		element.each(function(el,i){
			//if link contains an image ad overlay
			if(el.getElement('img')){
				//add position:relative to them so that icon is contained
				el.setStyle('position','relative');
				//inject a new div that is the overlay icon
				var overlayIcon = new Element('div').inject(el,'inside');
				overlayIcon.addClass('OverlayIcon');
				//IE6 causes too many issues due to lack of PNG support
				if ( (Browser.Engine && !Browser.Engine.trident4) || (Browser.ie && !Browser.ie6) ) {
					overlayIcon.setStyle('opacity',0);
					overlayIcon.set('tween',{duration:3000,transition:Fx.Transitions.Expo.easeIn}).tween('opacity',1);
				};
			};
		});
	},
	
	addRollover:function(element){
		element.each(function(el,i){
			//if link contains an image ad overlay
			if(el.getElement('img')){
				//add event listeners
				el.addEvents({
					'mouseenter': function(){
						el.getElement('img').set('tween',{duration:200,transition:Fx.Transitions.linear}).tween('opacity',0.5);
					},
					'mouseleave': function(){
						el.getElement('img').set('tween',{duration:400,transition:Fx.Transitions.linear}).tween('opacity',1);
					}
				});
			};
		});
	},
	
	addChain:function(element){
		//create new array to hold all links with images to chain through
		var chainArray = [];
		//push link into chainArray if it contains an image
		element.each(function(el,i){
			//detect whether link contains image
			if(el.getElement('img')){
				chainArray.push(el);
			};
		});
		//now chain through each item in the new array
		chainArray.each(function(el,i){
			//detect whether link contains image
			if(el.getElement('img')){
				//chain through each multibox link that contains an image
				var HoverMe = new Chain();
				var hoverOn = function(){
					el.getElement('img').set('tween',{duration:200,transition:Fx.Transitions.linear}).tween('opacity',0.5);
				};
				var hoverOff = function(){
					el.getElement('img').set('tween',{duration:400,transition:Fx.Transitions.linear}).tween('opacity',1);
				};
				HoverMe.chain(hoverOn);
				HoverMe.chain(hoverOff);
				HoverMe.callChain.delay(2000+(i+1)*1000,HoverMe);
				HoverMe.callChain.delay((i+2)*1000,HoverMe);
			};
		});
	},
	
	addDownload:function(element){
		this.download = new Element('div').addClass('MultiBoxDownload').inject(this.controls,'inside').setStyle('margin-left',0);
	},
	
	addTips:function(element){
		element.each(function(el,i){
			//add MooTools tips
			if(el.get('title')){
				var toolTips = new Tips(el, {
					onShow: function(el){el.fade(.9);},
					onHide: function(el){el.fade(0);},
					offsets: {'x':16,'y':5},
					className: 'mbTips'
				});
			};
			//remove title so dont get duplication of title and MooTools tips
			if(el.getElement('img')){
				if(el.getElement('img').get('title')){
					el.getElement('img').erase('title');
				};
				if(el.getElement('img').get('alt')){
					el.getElement('img').erase('alt');
				};
			};
		});
	},
	
	autoOpen:function(element){
		//make sure element number is valid
		if(this.options.autoOpen > $$(this.options.mbClass).length){
			this.options.autoOpen = $$(this.options.mbClass).length;
		};
		//auto open multiBox on page
		this.open(this.options.autoOpen-1,element);
	}
});