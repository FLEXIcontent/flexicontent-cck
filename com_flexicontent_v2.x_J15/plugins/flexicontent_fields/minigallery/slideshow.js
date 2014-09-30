
/**************************************************************

	Script		: SlideShow
	Version		: 1.3
	Authors		: Samuel Birch
	Desc		: 
	Licence		: Open Source MIT Licence

**************************************************************/

var SlideShow = new Class({
	
	getOptions: function(){
		return {
			effect: 'fade', //fade|wipe|slide|random
			duration: 2000,
			transition: Fx.Transitions.linear,
			direction: 'right', //top|right|bottom|left|random
			color: false,
			wait: 5000,
			loop: false,
			thumbnails: false,
			thumbnailCls: 'outline',
			backgroundSlider: false,
			loadingCls: 'loading',
			onClick: false
		};
	},

	initialize: function(container, images, options){
		this.setOptions(this.getOptions(), options);
		
		this.container = $(container);
		this.container.setStyles({
			position: 'relative',
			overflow: 'hidden'
		});
		if(this.options.onClick){
			this.container.addEvent('click', function(){
				this.options.onClick(this.imageLoaded);
			}.bind(this));
		}
		
		
		this.imagesHolder = new Element('div').setStyles({
			position: 'absolute',
			overflow: 'hidden',
			top: this.container.getStyle('height'),
			left: 0,
			width: '0px',
			height: '0px',
			display: 'none'
		}).injectInside(this.container);
		
		if($type(images) == 'string' && !this.options.thumbnails){
			var imageList = [];
			$$('.'+images).each(function(el){
				imageList.push(el.src);
				el.injectInside(this.imagesHolder);
			},this);
			this.images = imageList;
			
		}else if($type(images) == 'string' && this.options.thumbnails){
			var imageList = [];
			var srcList = [];
			this.thumbnails = $$('.'+images);
			this.thumbnails.each(function(el,i){
				srcList.push(el.href);
				imageList.push(el.getElement('img'));
				el.href = 'javascript:;';
				el.addEvent('click',function(){
					this.stop();
					this.play(i);				 
				}.bind(this,el,i));
			},this);
			this.images = srcList;
			this.thumbnailImages = imageList;
			
			if(this.options.backgroundSlider){
				this.bgSlider = new BackgroundSlider(this.thumbnailImages,{mouseOver: false, duration: this.options.duration, className: this.options.thumbnailCls, padding:{top:0,right:-2,bottom:-2,left:0}});
				this.bgSlider.set(this.thumbnailImages[0]);
			}
		
		}else{
			this.images = images;
		}
		
		this.loading = new Element('div').addClass(this.options.loadingCls).setStyles({
			position: 'absolute',
			top: 0,
			left: 0,
			zIndex: 3,
			display: 'none',
			width: this.container.getStyle('width'),
			height: this.container.getStyle('height')
		}).injectInside(this.container);
		
		this.oldImage = new Element('div').setStyles({
			position: 'absolute',
			overflow: 'hidden',
			top: 0,
			left: 0,
			opacity: 0,
			width: this.container.getStyle('width'),
			height: this.container.getStyle('height')
		}).injectInside(this.container);
		
		this.newImage = this.oldImage.clone();
		this.newImage.injectInside(this.container);
		
		
		
		this.timer = 0;
		this.image = -1;
		this.imageLoaded = 0;
		this.stopped = true;
		this.started = false;
		this.animating = false;
	},
	
	load: function(){
		$clear(this.timer);
		this.loading.setStyle('display','block');
		this.image++;
		var img = this.images[this.image];
		delete this.imageObj;
		
		doLoad = true;
		this.imagesHolder.getElements('img').each(function(el){
			var src = this.images[this.image];
			if(el.src == src){
				this.imageObj = el;
				doLoad = false;
				this.add = false;
				this.show();
			}
		},this);
		
		if(doLoad){
			this.add = true;
			this.imageObj = new Asset.image(img, {onload: this.show.bind(this)});
		}
		
	},

	show: function(add){
		
		if(this.add){
			this.imageObj.injectInside(this.imagesHolder);
		}
		
		this.newImage.setStyles({
			zIndex: 1,
			opacity: 0
		});
		var img = this.newImage.getElement('img');
		if(img){
			img.replaceWith(this.imageObj.clone());
		}else{
			var obj = this.imageObj.clone();
			obj.injectInside(this.newImage);
		}
		this.imageLoaded = this.image;
		this.loading.setStyle('display','none');
		if(this.options.thumbnails){
			
			if(this.options.backgroundSlider){
				var elT = this.thumbnailImages[this.image];
				this.bgSlider.move(elT);
				this.bgSlider.setStart(elT);
			}else{
				this.thumbnails.each(function(el,i){
					el.removeClass(this.options.thumbnailCls);
					if(i == this.image){
						el.addClass(this.options.thumbnailCls);
					}
				},this);
			}
		}
		this.effect();
	},
	
	wait: function(){
		this.timer = this.load.delay(this.options.wait,this);
	},
	
	play: function(num){
		if(this.stopped){
			if(num > -1){this.image = num-1};
			if(this.image < this.images.length){
				this.stopped = false;
				if(this.started){
					this.next();
				}else{
					this.load();
				}
				this.started = true;
			}
		}
	},
	
	stop: function(){
		$clear(this.timer);
		this.stopped = true;
	},
	
	next: function(wait){
		var doNext = true;
		if(wait && this.stopped){
			doNext = false;
		}
		if(this.animating){
			doNext = false;
		}
		if(doNext){
			this.cloneImage();
			$clear(this.timer);
			if(this.image < this.images.length-1){
				if(wait){
					this.wait();
				}else{
					this.load();	
				}
			}else{
				if(this.options.loop){
					this.image = -1;
					if(wait){
						this.wait();
					}else{
						this.load();	
					}
				}else{
					this.stopped = true;
				}
			}
		}
	},
	
	previous: function(){
		if(this.imageLoaded == 0){
			this.image = this.images.length-2;	
		}else{
			this.image = this.imageLoaded-2;
		}
		this.next();
	},
	
	cloneImage: function(){
		var img = this.oldImage.getElement('img');
		if(img){
			img.replaceWith(this.imageObj.clone());
		}else{
			var obj = this.imageObj.clone();
			obj.injectInside(this.oldImage);
		}
		
		this.oldImage.setStyles({
			zIndex: 0,
			top: 0,
			left: 0,
			opacity: 1
		});
		
		this.newImage.setStyles({opacity:0});
	},
	
	
	effect: function(){
		this.animating = true;
		this.effectObj = this.newImage.effects({
			duration: this.options.duration,
			transition: this.options.transition
		});
		
		var myFxTypes = ['fade','wipe','slide'];
		var myFxDir = ['top','right','bottom','left'];
		
		if(this.options.effect == 'fade'){
			this.fade();
			
		}else if(this.options.effect == 'wipe'){
			if(this.options.direction == 'random'){
				this.setup(myFxDir[Math.floor(Math.random()*(3+1))]);
			}else{
				this.setup(this.options.direction);
			}
			this.wipe();
			
		}else if(this.options.effect == 'slide'){
			if(this.options.direction == 'random'){
				this.setup(myFxDir[Math.floor(Math.random()*(3+1))]);
			}else{
				this.setup(this.options.direction);
			}
			this.slide();
			
		}else if(this.options.effect == 'random'){
			var type = myFxTypes[Math.floor(Math.random()*(2+1))];
			if(type != 'fade'){
				var dir = myFxDir[Math.floor(Math.random()*(3+1))];
				if(this.options.direction == 'random'){
					this.setup(dir);
				}else{
					this.setup(this.options.direction);
				}
			}else{
				this.setup();
			}
			this[type]();
		}
	},
	
	setup: function(dir){
		if(dir == 'top'){
			this.top = -this.container.getStyle('height').toInt();
			this.left = 0;
			this.topOut = this.container.getStyle('height').toInt();
			this.leftOut = 0;
			
		}else if(dir == 'right'){
			this.top = 0;
			this.left = this.container.getStyle('width').toInt();
			this.topOut = 0;
			this.leftOut = -this.container.getStyle('width').toInt();
			
		}else if(dir == 'bottom'){
			this.top = this.container.getStyle('height').toInt();
			this.left = 0;
			this.topOut = -this.container.getStyle('height').toInt();
			this.leftOut = 0;
			
		}else if(dir == 'left'){
			this.top = 0;
			this.left = -this.container.getStyle('width').toInt();
			this.topOut = 0;
			this.leftOut = this.container.getStyle('width').toInt();
			
		}else{
			this.top = 0;
			this.left = 0;
			this.topOut = 0;
			this.leftOut = 0;
		}
	},
	
	fade: function(){
		this.effectObj.start({
			opacity: [0,1]
		});
		this.resetAnimation.delay(this.options.duration+90,this);
		if(!this.stopped){
		this.next.delay(this.options.duration+100,this,true);
		}
	},
	
	wipe: function(){
		this.oldImage.effects({
			duration: this.options.duration,
			transition: this.options.transition
		}).start({
			top: [0,this.topOut],
			left: [0, this.leftOut]
		})
		this.effectObj.start({
			top: [this.top,0],
			left: [this.left,0],
			opacity: [1,1]
		},this);
		this.resetAnimation.delay(this.options.duration+90,this);
		if(!this.stopped){
		this.next.delay(this.options.duration+100,this,true);
		}
	},
	
	slide: function(){
		this.effectObj.start({
			top: [this.top,0],
			left: [this.left,0],
			opacity: [1,1]
		},this);
		this.resetAnimation.delay(this.options.duration+90,this);
		if(!this.stopped){
		this.next.delay(this.options.duration+100,this,true);
		}
	},
	
	resetAnimation: function(){
		this.animating = false;
	}
	
});
SlideShow.implement(new Options);
SlideShow.implement(new Events);


/*************************************************************/

