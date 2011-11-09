window.addEvent('domready', function() {
	if($$('.fcvote').length > 0) {
		$$('.fcvote a').addEvent('click', function(e) {
			e = new Event(e).stop();
			var itemID = this.getProperty('rel');
			var log = $('fcvote_' + itemID + '_main').empty().addClass('ajax-loader');
			var rating = this.get('text');

			var jsonRequest = new Request.JSON({
				url: getBaseURL() + "index.php?option=com_flexicontent&format=raw&task=ajaxvote&user_rating=" + rating + "&cid=" + itemID + "&xid=main",
				onSuccess: function(data){
					$('rating_' + itemID + '_main').setStyle('width', data.percentage + "%");
					$('fcvote_' + itemID + '_main').set('html', data.html).removeClass('ajax-loader');
					setTimeout(function() {
						$('fcvote_' + itemID + '_main').set('html', data.htmlrating);
					}, 2000);
				}
			}).send();
		});
	}

});

function getBaseURL() {
	var url = location.href;  // entire url including querystring - also: window.location.href;
	var baseURL = url.substring(0, url.indexOf('/', 14));

	return baseURL + sfolder + "/";
}