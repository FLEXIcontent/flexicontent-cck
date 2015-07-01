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
			
			var main_cnt = jQuery('#fcvote_cnt_' + itemID + '_main');
			var _htmlrating_main = main_cnt.length ? main_cnt.html() : '';
			var _htmlrating = jQuery('#fcvote_cnt_' + itemID + '_' + xid).html();
			jQuery('#fcvote_cnt_' + itemID + '_' + xid).empty().css('display', '').addClass('ajax-loader');
			
			jQuery('#fcvote_message_' + itemID + '_' + xid).empty().css('display', '').addClass('ajax-loader');
			//jQuery('#fcvote_message_' + itemID + '_main').empty().css('display', '').addClass('ajax-loader');
			
			var rating = jQuery(this).text();
			var voteurl = live_site + "/index.php?option=com_flexicontent&format=raw&task=ajaxvote&user_rating=" + rating + "&cid=" + itemID + "&xid=" + xid;

			jQuery.ajax({
				url: voteurl,
				dataType: "json",
				data: {
					lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: '')
				},
				success: function( data )
				{
					if (typeof(data.percentage)!="undefined" && data.percentage) {
						jQuery('#rating_' + itemID + '_' + xid).css('width', data.percentage + "%");
					}
					if (typeof(data.percentage_main)!="undefined" && data.percentage_main) {
						jQuery('#rating_' + itemID + '_main').css('width', data.percentage_main + "%");
					}
					
					if (typeof(data.htmlrating)!="undefined" && data.htmlrating) {
						_htmlrating = data.htmlrating;
					}
					if (typeof(data.htmlrating_main)!="undefined" && data.htmlrating_main) {
						_htmlrating_main = data.htmlrating_main;
					}
					
					var cnt = jQuery('#fcvote_cnt_' + itemID + '_' + xid);
					if (typeof(data.html) && data.html) {
						cnt.html(data.html).removeClass('ajax-loader');
						setTimeout(function() { cnt.animate({opacity: "0.5"}, 900);  }, 2000);
						setTimeout(function() {
							cnt.css('opacity', 'unset');
							if(_htmlrating.trim())
								cnt.css('opacity', 1).html(_htmlrating);
							else
								cnt.html('').hide();
						}, 3000);
					} else {
						cnt.html(_htmlrating).removeClass('ajax-loader');
					}
					
					if (main_cnt.length) {
						if (typeof(data.html_main) && data.html_main) {
							main_cnt.html(data.html_main).removeClass('ajax-loader');
							setTimeout(function() { main_cnt.animate({opacity: "0.5"}, 900);  }, 2000);
							setTimeout(function() {
								main_cnt.css('opacity', 'unset');
								if(_htmlrating_main.trim())
									main_cnt.css('opacity', 1).html(_htmlrating_main);
								else
									main_cnt.html('').hide();
							}, 3000);
						} else {
							main_cnt.html(_htmlrating_main).removeClass('ajax-loader');
						}
					}
					
					jQuery('#fcvote_message_' + itemID + '_' + xid).removeClass('ajax-loader');
					//jQuery('#fcvote_message_' + itemID + '_main').removeClass('ajax-loader');
					
					if (typeof(data.message)!="undefined" && data.message) {
						jQuery('#fcvote_message_' + itemID + '_' + xid).css('display', '').html(data.message);
					}
					if (typeof(data.message_main)!="undefined" && data.message_main) {
						jQuery('#fcvote_message_' + itemID + '_main').css('display', '').html(data.message_main);
					}
					
				},
				error: function (xhr, ajaxOptions, thrownError) {
					alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
				}
			});
			
		});
	}
});

	function fcvote_open_review_form(tagid, content_id, review_type)
	{
		var box = jQuery('#'+tagid)
		box.empty().css('display', '').addClass('ajax-loader');
		
		var currentURL = window.location;
		var live_site = currentURL.protocol + '//' + currentURL.host + fcvote_rfolder;
		var url = live_site + "/index.php?option=com_flexicontent&format=raw&task=getreviewform&content_id=" + content_id + "&review_type=" + review_type;

		jQuery.ajax({
			url: url,
			dataType: "json",
			data: {
				lang: (typeof _FC_GET !="undefined" && 'lang' in _FC_GET ? _FC_GET['lang']: '')
			},
			success: function( data )
			{
				box.removeClass('ajax-loader');
				if (typeof(data.html) && data.html) {
					box.html(data.html).show();
				}
			},
			error: function (xhr, ajaxOptions, thrownError) {
				alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
			}
		});
	}

