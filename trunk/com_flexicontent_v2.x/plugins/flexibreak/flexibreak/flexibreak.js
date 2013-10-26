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
		
		this.el = $(element);
		this.elid = element;
		
		this.titles = $$('#' + this.elid + ' ul li .tocLink');
		this.panels = $$('.articlePage');
		this.show = $$('.tocAll');
		
		this.titles.each(function(item) {
			item.addEvent('click', function(){
					index = this.titles.indexOf($(item));
					this.activate(this.panels[index]);
				}.bind(this)
			);
		}.bind(this));

		this.show.each(function(item) {
			item.addEvent('click', function(){
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
		if(!$defined(skipAnim))
		{
			skipAnim = false;
		}
		
		var newPage = page.getProperty('id');
		this.panels.removeClass('active');
		this.show.removeClass('active');
		$$('.tocNav').setStyle('display', 'block');
		
		this.activePanel = (MooTools.version>='1.2.4') ? this.panels.filter("#"+newPage)[0] : this.panels.filterById(newPage)[0];
		this.activePanel.addClass('active');

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

		this.titles.removeClass('active');
		this.current = this.panels.indexOf($(page));
		this.titles[this.current].addClass('active');
		this.activeTitle = page;
		
		if (this.options.wrap == false) {
			if (this.current == 0) {
				$$('.tocPrev').setStyle('display', 'none');
				$$('.tocNext').setStyle('display', 'inline-block');
			} else if (this.current == this.panels.length - 1) {
				$$('.tocNext').setStyle('display', 'none');
				$$('.tocPrev').setStyle('display', 'inline-block');
			} else {
				$$('.tocPrev').setStyle('display', 'inline-block');
				$$('.tocNext').setStyle('display', 'inline-block');
			}
			var outoftotal = '['+(this.current+1)+'/'+this.panels.length+']';
			(MooTools.version>='1.2.4') ? $$('.tocPrevNextCnt').set('html', outoftotal) : $$('.tocPrevNextCnt').setHTML(outoftotal);
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
		$$('.tocNav').setStyle('display', 'none');
		this.panels.addClass('active');
				
		this.titles.removeClass('active');
		page.addClass('active');
		this.activeTitle = page;
	}
});


window.addEvent('domready', function() {
		flexibreak = new flexibreak('articleTOC');
});