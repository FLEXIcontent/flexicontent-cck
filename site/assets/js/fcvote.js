jQuery(document).ready(function(){

	// Joomla Root and Base URL
	window.root_url = !!jroot_url_fc ? jroot_url_fc : '';
	window.base_url = !!jbase_url_fc ? jbase_url_fc : '';

	var under_vote = false;

	/**
	 * Show given message HTML as a small bubble (tooltip-like cloud) above the stars,
	 * automatically hidden after 2 seconds
	 */
	function fcvote_show_message(msg, html)
	{
		if (!html || !msg || !msg.length) return;
		msg = msg.first();

		// Strip wrapper and the alert-close button of the server-side message HTML
		var content = jQuery('<div></div>').html(html);
		content.find('button.close').remove();

		var is_warning = content.find('.fc-warning').length > 0;
		var mssg_inner = content.find('.fc-mssg');
		var inner_html = mssg_inner.length ? mssg_inner.html() : content.html();

		msg.css('margin-left', '')
			.html(inner_html)
			.removeClass('fcvote_message--success fcvote_message--warning')
			.addClass(is_warning ? 'fcvote_message--warning' : 'fcvote_message--success')
			.addClass('fcvote_message-visible');

		// Keep the bubble inside the viewport (important on mobile screens)
		var pad = 8;
		var rect = msg[0].getBoundingClientRect();
		var win_width = window.innerWidth || jQuery(window).width();
		var shift = 0;
		if (rect.left < pad)                    shift = pad - rect.left;
		else if (rect.right > win_width - pad)  shift = (win_width - pad) - rect.right;
		if (shift) msg.css('margin-left', Math.round(shift) + 'px');

		clearTimeout(msg.data('fcvote_hide_timer'));
		msg.data('fcvote_hide_timer', setTimeout(function() {
			msg.removeClass('fcvote_message-visible');
		}, 2000));
	}

	if (jQuery('.fcvote').length)
	{
		// Preview (on mouse over) the rating that would be submitted
		jQuery('.fcvote a.fc_dovote').on('mouseenter', function()
		{
			var vote_list = jQuery(this).closest('.fcvote_list');
			var total  = vote_list.find('li.voting-links').length;
			var rating = parseInt(jQuery(this).text(), 10);
			if (!total || !rating) return;

			vote_list.addClass('fcvote-hovering')
				.find('li.hover-rating').css('width', (100 * rating / total) + '%');
		});

		jQuery('.fcvote ul.fcvote_list').on('mouseleave', function()
		{
			jQuery(this).removeClass('fcvote-hovering')
				.find('li.hover-rating').css('width', 0);
		});

		jQuery('.fcvote a.fc_dovote').on('click', function(e)
		{
			if (under_vote) return;
			under_vote = true;

			var voting_group = jQuery(this).closest('.voting-group');
			voting_group.css('opacity', '0.5');

			var vote_list = jQuery(this).closest('.fcvote_list');
			var data_arr = jQuery(this).attr('data-rel').split("_");

			// ID for item being voted
			var itemID = data_arr[0];

			// Voting characteristic, (defaults to the 'main' voting characteristic if not set)
			var xid = typeof(data_arr[1])!='undefined' && data_arr[1] ? data_arr[1] : 'main';

			var xid_msg  = jQuery(this).closest('.fcvote').find('.fcvote_message');
			var main_msg = voting_group.find('.voting-row_main').find('.fcvote_message');
			if (!main_msg.length) main_msg = xid_msg;

			var xid_cnt  = jQuery(this).closest('.fcvote').find('.fcvote-count');
			var main_cnt = voting_group.find('.voting-row_main').find('.fcvote-count');

			var xid_rating  = jQuery(this).closest('.fcvote_list').find('.current-rating');
			var main_rating = voting_group.find('.voting-row_main').find('.fcvote_list').find('.current-rating');

			var _htmlrating_main = main_cnt.length ? main_cnt.html() : '';
			var _htmlrating = xid_cnt.html();

			var rating = jQuery(this).text();

			var voteurl = base_url
				+ 'index.php?option=com_flexicontent&task=reviews.ajaxvote&user_rating=' + rating + '&cid=' + itemID + '&xid=' + xid;

			jQuery.ajax({
				url: voteurl,
				dataType: "json",
				data: {
					lang: (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '')
				},
				success: function( data )
				{
					if (typeof(data.percentage)!="undefined" && data.percentage)
					{
						xid_rating.css('width', data.percentage + "%");
					}
					if (typeof(data.percentage_main)!="undefined" && data.percentage_main)
					{
						main_rating.css('width', data.percentage_main + "%");
					}

					if (typeof(data.htmlrating)!="undefined" && data.htmlrating)
					{
						_htmlrating = data.htmlrating;
					}
					if (typeof(data.htmlrating_main)!="undefined" && data.htmlrating_main)
					{
						_htmlrating_main = data.htmlrating_main;
					}

					// Show counter flash only if the counter is enabled in field configuration,
					// (server sends empty 'htmlrating' when the counter is disabled)
					if (data.html && data.htmlrating)
					{
						xid_cnt.html(data.html).show();

						setTimeout(function()
						{
							xid_cnt.animate({opacity: "0.5"}, 900);
						}, 2000);

						setTimeout(function()
						{
							xid_cnt.css('opacity', 'unset');
							if(_htmlrating.trim())
							{
								xid_cnt.css('opacity', 1).html(_htmlrating);
							}
							//else xid_cnt.html('').hide();
						}, 3000);
					}
					else if (_htmlrating && _htmlrating.trim())
					{
						xid_cnt.html(_htmlrating).show();
					}

					if (main_cnt.length) {
						if (data.html_main && data.htmlrating_main) {
							main_cnt.html(data.html_main).show();
							setTimeout(function() { main_cnt.animate({opacity: "0.5"}, 900);  }, 2000);
							setTimeout(function() {
								main_cnt.css('opacity', 'unset');
								if(_htmlrating_main.trim())
									main_cnt.css('opacity', 1).html(_htmlrating_main);
								else
									main_cnt.html('').hide();
							}, 3000);
						} else if (_htmlrating_main && _htmlrating_main.trim()) {
							main_cnt.html(_htmlrating_main);
						}
					}

					// Show vote messages as auto-hiding bubbles above the stars
					if (typeof(data.message)!="undefined" && data.message) {
						fcvote_show_message(xid_msg, data.message);
					}
					if (typeof(data.message_main)!="undefined" && data.message_main) {
						fcvote_show_message(main_msg, data.message_main);
					}

					// Clear hover preview (e.g. touch devices do not fire mouseleave)
					voting_group.find('ul.fcvote_list').removeClass('fcvote-hovering')
						.find('li.hover-rating').css('width', 0);

					under_vote = false;
					voting_group.css('opacity', '');
				},
				error: function (xhr, ajaxOptions, thrownError) {
					alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
					under_vote = false;
					voting_group.css('opacity', '');
				}
			});

		});
	}
});

	function fcvote_open_review_form(tagid, content_id, review_type)
	{
		var box = jQuery('#'+tagid);
		var box_loading = jQuery('#'+tagid+'_loading');

		if (box.is(":visible"))
		{
			box_loading.empty().removeClass('ajax-loader').css('display', 'none');
			box.slideUp(400, function(){ box.empty(); });
			return;
		}

		if (1)
		{
			var url = base_url
				+ 'index.php?option=com_flexicontent&task=reviews.edit&view=reviews&id=0&tmpl=component&tagid=' + tagid
				+ '&content_id=' + content_id + '&review_type=' + review_type
				+ '&lang=' + (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '');

			fc_showDialog(url, 'fc_modal_popup_container', 0, 800, 800, 0, {title: 'Review this item'});
		}
		else
		{
			var url = root_url
				+ 'index.php?option=com_flexicontent&format=raw&task=getreviewform&tagid=' + tagid
				+ '&content_id=' + content_id + '&review_type=' + review_type;

			box_loading.empty().addClass('ajax-loader').css('display', 'inline-block');

			jQuery.ajax({
				url: url,
				dataType: "json",
				data: {
					lang: (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '')
				},
				success: function( data )
				{
					box_loading.empty().removeClass('ajax-loader').css('display', 'none');
					if (typeof(data.html) && data.html)
					{
						box.html(data.html).slideDown();
					}
				},
				error: function (xhr, ajaxOptions, thrownError) {
					box_loading.empty().removeClass('ajax-loader').css('display', 'none');
					alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
				}
			});
		}
	}


	function fcvote_submit_review_form(tagid, form)
	{
		var box = jQuery('#'+tagid);
		var box_loading = jQuery('#'+tagid+'_loading');

		if (( typeof(form.checkValidity) == "function" ) )
		{
			if (!form.checkValidity())
			{
				box_loading.empty().removeClass('ajax-loader').css('display', '');
				fcvote_submit_review_form_show_validation(jQuery(form), box_loading);
				return;
			}
		}

		if (1)
		{
			var url = base_url
				+ 'index.php?option=com_flexicontent&task=reviews.edit&view=reviews&id=0&tmpl=component&tagid='
				+ tagid + '&content_id=' + content_id + '&review_type=' + review_type
				+ '&lang=' + (typeof fc_sef_lang != 'undefined' ? fc_sef_lang : '');

			fc_showDialog(url, 'fc_modal_popup_container', 0, 800, 800, 0, {title: 'Review this item'});
		}
		else
		{
			var url = root_url
				+ 'index.php?option=com_flexicontent&format=raw&task=storereviewform';

			box_loading.empty().addClass('ajax-loader').css('display', 'inline-block');

			jQuery.ajax({
				url: url,
				dataType: "json",
				data: jQuery(form).serialize(),
				success: function( data )
				{
					box_loading.empty().removeClass('ajax-loader').css('display', 'none');
					if (typeof(data.html) && data.html)
					{
						if (typeof(data.error) && data.error)
						{
							box_loading.html(data.html).css('display', 'block');
						}
						else
						{
							box.html(data.html).show();
						}
					}
				},
				error: function (xhr, ajaxOptions, thrownError) {
					box_loading.empty().removeClass('ajax-loader').css('display', 'none');
					alert('Error status: ' + xhr.status + ' , Error text: ' + thrownError);
				}
			});
		}
	}


	function fcvote_submit_review_form_show_validation(form, errorBox)
	{
		var errorList = jQuery('<div></div>');
		errorBox.empty().append(errorList);

		//Find all invalid fields within the form.
		form.find(':invalid').each(function(index, node)
		{
			//Find the field's corresponding label
			var label = jQuery('label[for=' + node.id + ']');

			//Opera incorrectly does not fill the validationMessage property.
			var message = node.validationMessage || 'Invalid value.';
			errorList.append('<div class="fc-mssg fc-warning fc-nobgimage"><button type="button" class="close" data-dismiss="alert">&times;</button><b>' + label.html() + '</b>: ' + message + '</div>');

			var $node = jQuery(node);
			if (!$node.hasClass('needs-validation'))
			{
				$node.addClass('needs-validation').on('blur', function() { jQuery(this).removeClass('invalid') });
			}
			$node.addClass('invalid');
		});
	};
