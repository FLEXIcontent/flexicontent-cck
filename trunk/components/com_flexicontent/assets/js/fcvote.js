window.addEvent('domready', function() {
	if($$('.fcvote').length > 0) {
		$$('.fcvote a').addEvent('click', function(e) {
			e = new Event(e).stop();
			var itemID = this.getProperty('rel');
			var log = $('fcvote_' + itemID + '_main').empty().addClass('ajax-loader');
			
			if(MooTools.version>="1.2.4") {
				
				var rating = this.get('text');
				var voteurl = getBaseURL() + "index.php?option=com_flexicontent&format=raw&task=ajaxvote&user_rating=" + rating + "&cid=" + itemID + "&xid=main";
				var jsonRequest = new Request.JSON({
					url: voteurl,
					onSuccess: function(data){
						if (typeof(data.percentage)!="undefined" && data.percentage)
							$('rating_' + itemID + '_main').setStyle('width', data.percentage + "%");
						$('fcvote_' + itemID + '_main').set('html', data.html).removeClass('ajax-loader');
						setTimeout(function() {
							$('fcvote_' + itemID + '_main').set('html', data.htmlrating);
						}, 2000);
					}
				}).send();
				
			} else {
				
				var rating = this.innerHTML;
				var voteurl = getBaseURL() + "index.php?option=com_flexicontent&format=raw&task=ajaxvote&user_rating=" + rating + "&cid=" + itemID + "&xid=main";
				var ajax = new Ajax(voteurl, {
					onComplete: function(data){
						data=Json.evaluate(data);
						if (typeof(data.percentage)!="undefined" && data.percentage)
							$('rating_' + itemID + '_main').setStyle('width', data.percentage + "%");
						$('fcvote_' + itemID + '_main').removeClass('ajax-loader');
						$('fcvote_' + itemID + '_main').innerHTML=data.html;
						setTimeout(function() {
							$('fcvote_' + itemID + '_main').innerHTML=data.htmlrating;
						}, 2000);
					}
				});
				ajax.request();
				
			}
			
		});
	}

});

function getBaseURL() {
	var url = location.href;  // entire url including querystring - also: window.location.href;
	var baseURL = url.substring(0, url.indexOf('/', 14));

	return baseURL + sfolder + "/";
}