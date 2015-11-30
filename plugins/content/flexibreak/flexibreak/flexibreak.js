var flexibreak = function(element, options)
{
	this.options = {
		duration:	200,
		mouseOverClass:	'active',
		activateOnLoad:	0,
		wrap:	false
	}
	
	if( typeof options !== 'undefined') for (var key in options)
	{
		//console.log(key, options[key]);
		this.options[key] = options[key];
	};
	
	this.currentIndex = 0;
	this.el   = jQuery('#'+element);
	this.elid = element;
	
	this.tocLinksScrolled  = jQuery('#' + this.elid + ' ul li .tocScrolled');
	this.tocLinksPaginated = jQuery('#' + this.elid + ' ul li .tocPaginated');
	this.pages = jQuery('.articlePage');
	this.tocLinkAll = jQuery('.tocAll');
	
	
	this.activate = function(page, skipAnim)
	{
		// Check if page not valid, by searching inside the page collection
		var found = this.pages.filter('#'+jQuery(page).attr('id'));
		if (found.length==0) { alert("Page id: " + jQuery(page).attr('id') + "not found"); return; }
		else if (found.length > 1) { found = found.first(); }
		
		// Check if page already active
		if(page == this.currentPage) return;
		
		// Set new active page and new page index
		this.currentPage = page;
		this.currentIndex = this.pages.index(page);
		
		if(typeof skipAnim === 'undefined') skipAnim = false;
		
		// Hide all pages and all TOC entries
		jQuery(this.pages).removeClass('active');
		jQuery(this.tocLinksPaginated).removeClass('active');
		jQuery(this.tocLinkAll).removeClass('active');
		
		// Show previous/next buttons container
		jQuery('.tocNav').css('display', 'block');
		
		// Show (activate) the new current page
		jQuery(this.currentPage).addClass('active');
		jQuery(this.currentPage).css('opacity', '0.01').animate({'opacity': '1'}, 600, 'linear');
		
		// Highlight current TOC entry
		jQuery(this.tocLinksPaginated[this.currentIndex]).addClass('active');
		
		// Show previous/next buttons if appropriate, if wrapping at ends this code is not needed because buttons will be always visible
		if (this.options.wrap == false) {
			if (this.currentIndex == 0) {
				jQuery('.tocPrev').css('display', 'none');
				jQuery('.tocNext').css('display', 'inline-block');
			} else if (this.currentIndex == this.pages.length - 1) {
				jQuery('.tocNext').css('display', 'none');
				jQuery('.tocPrev').css('display', 'inline-block');
			} else {
				jQuery('.tocPrev').css('display', 'inline-block');
				jQuery('.tocNext').css('display', 'inline-block');
			}
		}
		
		// Set page total counter
		var outoftotal = '['+(this.currentIndex+1)+'/'+this.pages.length+']';
		jQuery('.tocPrevNextCnt').html(outoftotal);
	};
	
	
	// Activate initial page and highlight TOC entry
	if(this.options.activateOnLoad != 'none')
	{
		this.options.activateOnLoad == 'first' ? 0 : this.options.activateOnLoad;
		
		if (this.tocLinksPaginated.length) {
			this.activate(this.pages[this.options.activateOnLoad], true);
		}
		
		if (this.tocLinksScrolled.length) {
			jQuery(this.tocLinksScrolled).removeClass('active');
			jQuery(this.tocLinksScrolled.get(this.options.activateOnLoad)).addClass('active');
		}
	};
	
	
	// Handle scrolled mode
	this.tocLinksScrolled.each(function(index, tocAnchor) {
		jQuery(tocAnchor).on('click',function (e) {
			e.preventDefault();
			var target = this.hash;
			var $target = jQuery(target);
			jQuery('html, body').stop().animate({
				'scrollTop': $target.offset().top
			}, 900, 'swing', function () {
				window.location.hash = target;
			});
			jQuery(flexibreak.tocLinksScrolled).removeClass('active');
			jQuery(tocAnchor).addClass('active');
		});
	});
	
	
	// Handle paginated mode
	this.tocLinksPaginated.each(function(index, page) {
		jQuery(page).on('click', function(){
			flexibreak.activate(flexibreak.pages[index]);
		});
	});
	this.tocLinkAll.each(function(index, tocLink) {
		jQuery(tocLink).on('click', function(){
			flexibreak.showall(tocLink);
		});
	});
	
	
	this.next = function() {
		var next = this.currentIndex + 1;
		if (next == this.pages.length) {
			if (this.options.wrap == true) { next = 0 } else { return }
		}
		this.activate(this.pages[next]);
	};
	
	
	this.previous = function() {
		var prev = this.currentIndex - 1
		if (prev < 0) {
			if (this.options.wrap == true) { prev = this.pages.length - 1 } else { return }
		}
		this.activate(this.pages[prev]);
	};
	
	
	this.showall = function(page){
		jQuery('.tocNav').css('display', 'none');
		
		jQuery(this.pages).addClass('active');
		jQuery(this.pages).css('opacity', '0.01').animate({'opacity': '1'}, 600, 'linear');
		
		jQuery(this.tocLinksPaginated).removeClass('active');
		jQuery(page).addClass('active');
		this.currentPage = page;
	};
};


jQuery(document).ready(function() {
	flexibreak = new flexibreak('articleTOC');
});