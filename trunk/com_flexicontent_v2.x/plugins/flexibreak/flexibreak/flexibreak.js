var flexibreak = new Class({
	initialize: function(element, options) {
		this.options = Object.extend({
			changeTransition:	Fx.Transitions.Expo.easeOut,
			duration:	200,
			mouseOverClass:	'active',
			activateOnLoad:	'first',
			wrap:	false
		}, options || {});
		this.current = 0;
		
		this.el   = jQuery('#'+element);
		this.elid = element;
		
		this.titles = jQuery('#' + this.elid + ' ul li .tocLink').toArray();
		this.panels = jQuery('.articlePage').toArray();
		this.show = jQuery('.tocAll').toArray();
		
		this.titles.each(function(item) {
			jQuery(item).bind('click', function(){
					index = this.titles.indexOf(item);
					this.activate(this.panels[index]);
				}.bind(this)
			);
		}.bind(this));

		this.show.each(function(item) {
			jQuery(item).bind('click', function(){
					this.showall(item);
				}.bind(this)
			);
		}.bind(this));

		if(this.options.activateOnLoad != 'none')
		{
			if(this.options.activateOnLoad == 'first')
			{
				this.activate(this.panels[0], true);
			}
			else
			{
				this.activate(this.options.activateOnLoad, true);	
			}
		}	
	},
	
	activate: function(page, skipAnim){
		if(page == this.activeTitle) {
			return;
		}
		if(skipAnim==null)
		{
			skipAnim = false;
		}
		
		var newPage = jQuery(page).attr('id');
		jQuery(this.panels).removeClass('active');
		jQuery(this.show).removeClass('active');
		jQuery('.tocNav').css('display', 'block');
		
		this.activePanel = jQuery(this.panels).filter("#"+newPage)[0];
		jQuery(this.activePanel).addClass('active');

		/*if(this.options.changeTransition != 'none' && skipAnim==false)
		{
			this.panels.filterById(newPage).setStyle('opacity', 0);
			var changeEffect = new Fx.Elements(this.panels.filterById(newPage), {duration: this.options.duration, transition: this.options.changeTransition});
			changeEffect.start({
				'0': {
					'opacity': [0, 1]
				}
			});
		}*/

		jQuery(this.titles).removeClass('active');
		this.current = this.panels.indexOf(page);
		jQuery(this.titles[this.current]).addClass('active');
		this.activeTitle = page;
		
		if (this.options.wrap == false) {
			if (this.current == 0) {
				jQuery('.tocPrev').css('display', 'none');
				jQuery('.tocNext').css('display', 'inline-block');
			} else if (this.current == this.panels.length - 1) {
				jQuery('.tocNext').css('display', 'none');
				jQuery('.tocPrev').css('display', 'inline-block');
			} else {
				jQuery('.tocPrev').css('display', 'inline-block');
				jQuery('.tocNext').css('display', 'inline-block');
			}
			var outoftotal = '['+(this.current+1)+'/'+this.panels.length+']';
			jQuery('.tocPrevNextCnt').html(outoftotal);
		}
	},

	next: function() {
		var next = this.current + 1;
		if (next == this.panels.length) {
			if (this.options.wrap == true) { next = 0 } else { return }
		}
		this.activate(this.panels[next]);
	},

	previous: function() {
		var prev = this.current - 1
		if (prev < 0) {
			if (this.options.wrap == true) { prev = this.panels.length - 1 } else { return }
		}
		this.activate(this.panels[prev]);
	},
	
	showall: function(page){
		jQuery('.tocNav').css('display', 'none');
		jQuery(this.panels).addClass('active');
		
		jQuery(this.titles).removeClass('active');
		jQuery(page).addClass('active');
		this.activeTitle = page;
	}
});


window.addEvent('domready', function() {
		flexibreak = new flexibreak('articleTOC');
});