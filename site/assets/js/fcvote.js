window.addEvent('domready', function() {
	//var url = location.href;  // entire url including querystring - also: window.location.href;
	//var live_site = url.substring(0, url.indexOf('/', 14)) + fcvote_rfolder + '/';
	
	var currentURL = window.location;
	var live_site = currentURL.protocol + '//' + currentURL.host + fcvote_rfolder;
	
	if (jQuery('.fcvote').length)
	{
		jQuery('.fcvote a.fc_dovote').on('click', function(e){
			
			var data_arr = jQuery(this).attr('rel').split("_");
			var itemID = data_arr[0];
			// Extra voting option
			if (typeof(data_arr[1])!="undefined" && data_arr[1]) {
				xid = data_arr[1];
			} else {
				var xid = "main";  // default to ... 'main' voting
			}
			
			var _htmlrating = jQuery('#fcvote_cnt_' + itemID + '_' + xid).html();
			var log = jQuery('#fcvote_cnt_' + itemID + '_' + xid).empty().addClass('ajax-loader');
			
			var rating = jQuery(this).text();
			var voteurl = live_site + "/index.php?option=com_flexicontent&format=raw&task=ajaxvote&user_rating=" + rating + "&cid=" + itemID + "&xid=" + xid;

			jQuery.ajax({
				url: voteurl,
				dataType: "json",
				success: function( data ) {
					if (typeof(data.percentage)!="undefined" && data.percentage)
						jQuery('#rating_' + itemID + '_' + xid).css('width', data.percentage + "%");
					
					if (typeof(data.htmlrating)!="undefined" && data.htmlrating) {
						_htmlrating = data.htmlrating;
					}
					
					jQuery('#fcvote_cnt_' + itemID + '_' + xid).html(data.html).removeClass('ajax-loader');
					setTimeout(function() {
						jQuery('#fcvote_cnt_' + itemID + '_' + xid).html(_htmlrating);
					}, 4000);
				}
			});
			
		});
	}

});
