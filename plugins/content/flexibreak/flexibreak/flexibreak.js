var flexibreak = function(element, options)
{
	this.options = {
		duration:	200,
		mouseOverClass:	'active',
		activateOnLoad:	'hash',
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
	this.pages = jQuery('.articlePage, .articleAnchor');
	this.tocLinkAll = jQuery('.tocAll');
	
	
	this.activate = function(pageElement, skipAnim)
	{
		// Check if pageElement -exists-, by searching inside the known pages collection
		var found = this.pages.filter('#'+jQuery(pageElement).attr('id'));
		if (found.length==0) { alert("Page id: " + jQuery(pageElement).attr('id') + " not found"); return; }
		else if (found.length > 1) { found = found.first(); }
		
		// Check if page already active
		if (pageElement == this.currentPage) return;
		
		// Set new active page and new page index
		this.currentPage = pageElement;
		this.currentIndex = this.pages.index(pageElement);
		
		if(typeof skipAnim === 'undefined') skipAnim = false;
		
		// Show (activate) the new current page, hide all other pages 
		jQuery(this.pages).removeClass('active');
		jQuery(this.currentPage).addClass('active');
		jQuery(this.currentPage).css('opacity', '0.01').animate({'opacity': '1'}, 600, 'swing');
		
		// Activate new TOC entry
		jQuery(this.tocLinksPaginated).parent().removeClass('active');
		jQuery(this.tocLinkAll).parent().removeClass('active');
		
		// Show previous/next buttons container
		jQuery('.tocNav').css('display', 'block');
		jQuery('.tocReturnAll').css('display', 'none');  // visible only if showAll
		
		
		// Highlight current TOC entry
		jQuery(this.tocLinksPaginated[this.currentIndex]).parent().addClass('active');
		
		// Show previous/next buttons if appropriate, if wrapping at ends this code is not needed because buttons will be always visible
		if (this.options.wrap == false) {
			if (this.currentIndex == 0) {
				jQuery('.tocPrev').addClass('tocNoPrevNext');
				jQuery('.tocNext').removeClass('tocNoPrevNext');
			} else if (this.currentIndex == this.pages.length - 1) {
				jQuery('.tocNext').addClass('tocNoPrevNext');
				jQuery('.tocPrev').removeClass('tocNoPrevNext');
			} else {
				jQuery('.tocPrev').removeClass('tocNoPrevNext');
				jQuery('.tocNext').removeClass('tocNoPrevNext');
			}
		}
		
		// Set page total counter
		var outoftotal = '['+(this.currentIndex+1)+'/'+this.pages.length+']';
		jQuery('.tocPrevNextCnt').html(outoftotal);
	};
	
	
	this.showall = function(pageElement)
	{
		jQuery('.tocNav').css('display', 'none');   // hide prev/next links
		jQuery('.tocReturnAll').css('display', ''); // return links visible only when showAll
		
		jQuery(this.pages).addClass('active');
		jQuery(this.pages).css('opacity', '0.01').animate({'opacity': '1'},  600, 'swing');
		
		jQuery(this.tocLinksPaginated).parent().removeClass('active');
		jQuery(pageElement).parent().addClass('active');
		this.currentPage = pageElement;
	};
	
	
	// Activate initial page and highlight TOC entry
	if (this.options.activateOnLoad == 'hash')
	{
		this.options.activateOnLoad = 'none';
		
		var pageHash = window.location.hash;
		if (pageHash == '#showall')
		{
			this.showall(this.tocLinkAll.get(0));
		}
		else if (pageHash)
		{
			var pageId = pageHash.substring(1);
			var pageElement = jQuery('#'+pageId);
			if ( pageElement ) {
				var found = this.pages.filter('#'+pageElement.attr('id'));
				this.options.activateOnLoad = found.length ? this.pages.index(found.get(0)) : 'none';
			}
		}
	}
	
	if (this.options.activateOnLoad != 'none')
	{
		if (this.tocLinksPaginated.length) {
			this.activate(this.pages[this.options.activateOnLoad], true);
		}
		
		if (this.tocLinksScrolled.length) {
			jQuery(this.tocLinksScrolled).parent().removeClass('active');
			jQuery(this.tocLinksScrolled.get(this.options.activateOnLoad)).parent().addClass('active');
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
			jQuery(flexibreak.tocLinksScrolled).parent().removeClass('active');
			jQuery(tocAnchor).parent().addClass('active');
		});
	});
	
	
	// Handle paginated mode
	this.tocLinksPaginated.each(function(index, pageElement) {
		jQuery(pageElement).on('click', function(){
			flexibreak.activate(flexibreak.pages[index]);
			return false;  // prevent href link reloading
		});
	});
	this.tocLinkAll.each(function(index, tocLink) {
		jQuery(tocLink).on('click', function(){
			flexibreak.showall(tocLink);
			return false;  // prevent href link reloading
		});
	});
	
	
	this.next = function() {
		var next = this.currentIndex + 1;
		if (next == this.pages.length) {
			if (this.options.wrap == true) { next = 0 } else { return false }
		}
		this.activate(this.pages[next]);
		return false;  // prevent href link reloading
	};
	
	
	this.previous = function() {
		var prev = this.currentIndex - 1;
		if (prev < 0) {
			if (this.options.wrap == true) { prev = this.pages.length - 1 } else { return false }
		}
		this.activate(this.pages[prev]);
		return false;  // prevent href link reloading
	};
};


jQuery(document).ready(function() {
	flexibreak = new flexibreak('articleTOC');
});