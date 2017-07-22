<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsSharedmedia extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

		$_MEDIA_ = 'MEDIA';
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;

		// API key and other form configuration
		$debug_to_console = (int) $field->parameters->get('debug_to_console', 1);
		$use_native_apis  = (int) $field->parameters->get('use_native_apis', 0);
		$embedly_key = $field->parameters->get('embedly_key','') ;
		$youtube_key = $field->parameters->get('youtube_key', '');

		$autostart   = $field->parameters->get('autostart', 0);
		$autostart   = $autostart ? 'true' : 'false';
		$force_ssl   = $field->parameters->get('force_ssl', 1);
		$force_ssl   = $force_ssl ? 'true' : 'false';

		$display_api_type_form = $field->parameters->get('display_api_type_form', 0);
		$display_media_id_form = $field->parameters->get('display_media_id_form', 0);

		$display_title_form       = $field->parameters->get('display_title_form', 1);
		$display_author_form      = $field->parameters->get('display_author_form', 1);
		$display_duration_form    = $field->parameters->get('display_duration_form', 0);

		$display_description_form = $field->parameters->get('display_description_form', 1);
		$display_edit_size_form   = $field->parameters->get('display_edit_size_form', 1);
		$width  = (int)$field->parameters->get('width', 960);
		$height = (int)$field->parameters->get('height', 540);


		// Return error message if api keys are missing
		//if( empty($embedly_key) && !$use_native_apis ) {
		//  $api_key_name = JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_EMBEDLY_API_KEY');
		//	$api_key_desc = JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_EMBEDLY_API_KEY_DESC');
		//	$error_text = JText::sprintf('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_API_KEY_REQUIRED', $api_key_name) ." <br/> ". $api_key_desc;
		//}
		if( empty($youtube_key) && $use_native_apis ) {
			$api_key_name = JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_YOUTUBE_API_KEY');
			$api_key_desc = JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_YOUTUBE_API_KEY_DESC');
			$error_text = JText::sprintf('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_API_KEY_REQUIRED', $api_key_name) ." <br/> ". $api_key_desc;
		}


		// Initialise value property
		if (empty($field->value))
		{
			$field->value = array();
			$field->value[0]['url'] = '';         // Actual media URL (enter by user)
			$field->value[0]['embed_url'] = '';   // Direct embed URL (e.g. via embed.ly)
			$field->value[0]['api_type'] = '';    // Native APIs: embed method (e.g. youtube)
			$field->value[0]['media_id'] = '';     // Native APIs: media ID
			$field->value[0]['title'] = '';
			$field->value[0]['author'] = '';
			$field->value[0]['duration'] = '';
			$field->value[0]['description'] = '';
			$field->value[0]['height'] = '';
			$field->value[0]['width'] = '';
			$field->value[0]['thumb'] = '';
			$field->value[0] = serialize($field->value[0]);
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		$js = "";
		$css = "";
		$field_name_js = str_replace('-', '_', $field->name);

		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($field->parameters->get('fields_box_placing', 1) ? "
					,start: function(e) {
						//jQuery(e.target).children().css('float', 'left');
						//fc_setEqualHeights(jQuery(e.target), 0);
					}
					,stop: function(e) {
						//jQuery(e.target).children().css({'float': 'none', 'min-height': '', 'height': ''});
					}
					" : '')."
				});
			});
			";

			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;

				if(!remove_previous && (rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}

				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');

				// First, generate new field as HTML
				//var newField_HTML = lastField.prop('outerHTML');

				// replace all field names and ids
				//newField_HTML = newField_HTML.replace(/" . str_replace(array('[', ']'), array('\[', '\]'), $fieldname) . "\[(\d*)\]/g, '" . $fieldname . "[' + uniqueRowNum".$field->id." + ']');
				//newField_HTML = newField_HTML.replace(/" . $elementid . "_(\d*)/g, '" . $elementid . "_' + uniqueRowNum".$field->id.");

				// Convert HTML to DOM element
				//var newField = jQuery(newField_HTML);

				var elements = ['sm_url', 'sm_fetch_btn', 'sm_clear_btn', 'sm_embed_url', 'sm_api_type', 'sm_media_id', 'sm_title', 'sm_author', 'sm_duration', 'sm_width', 'sm_height', 'sm_description', 'sm_thumb'];
				for	(var i = 0; i < elements.length; i++) {
					theInput = newField.find('.' + elements[i]).first();
					var el_name = elements[i].replace(/^sm_/, '');
					theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+']['+el_name+']');
					theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_'+el_name);
				}
				newField.find('.sm_preview').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview');
				newField.find('.sm_fetch_btn').attr('onclick','fetchData_".$field_name_js."(\'".$elementid."_'+uniqueRowNum".$field->id."+'\')');
				newField.find('.sm_clear_btn').attr('onclick','clearData_".$field_name_js."(\'".$elementid."_'+uniqueRowNum".$field->id."+'\')');
				newField.find('.fcfield_sm_mssg').attr('id','fcfield_sm_mssg_".$elementid."_'+uniqueRowNum".$field->id.");
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";

				// Extra actions after adding element to the DOM
			$js .= "
				var element_id = '".$elementid . "_' + uniqueRowNum".$field->id.";

				// Clear any existing message
				jQuery('#fcfield_sm_mssg_' + element_id).html('');

				// Clear old value (user entered) URL
				jQuery('#' + element_id + '_url').val('');

				// Clear and hide old value fields
				clearData_".$field_name_js."(element_id);
				";

			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				// Attach form validation on new element
				fc_validationAttach(newField);

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});

				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}
			}
			";

			$css .= '';

			$remove_button = '<span class="' . $add_on_class . ' fcfield-delvalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="' . $add_on_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		// JS CODE to handle fetching media DATA
		$js .= '
		var fc_mediaID_'.$field_name_js.';
		var fc_elemID_'.$field_name_js.';


		function clearData_'.$field_name_js.'(element_id)
		{
			// Clear embed Data and its preview HTML
			setEmbedData_'.$field_name_js.'("", "", element_id);

			// Clear META Data
			setMetaData_'.$field_name_js.'({title:"", author:"", duration:"", description:"", thumb:""}, element_id);

			// Clear other data
			jQuery("#" + element_id + "_height").val("");
			jQuery("#" + element_id + "_width").val("");

			// Hide the value rows
			toggleMETArows_'.$field_name_js.'(element_id, -1);
		}


		function fetchData_'.$field_name_js.'(element_id)
		{
			element_id = typeof element_id === "undefined" || !element_id  ?  "'.$elementid.'_0" : element_id;
			var msg_box = jQuery("#fcfield_sm_mssg_"+element_id);

			// Clear any existing message
			msg_box.html("");

			// Clear existing value
			clearData_'.$field_name_js.'(element_id);

			// if URL field is empty then nothing to do, else continue with creating the fetch URL
			var url = jQuery("#"+element_id+"_url").val();
			if (url=="") return;

			msg_box.html("<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">");

			'.($debug_to_console ? 'window.console.log("Fetching "+url);' : '').'

			var mediaID = "";
			var apiType = "";


			if ('.$use_native_apis.') {
				// try youtube
				var myregexp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
				if(url.match(myregexp) != null) {
					mediaID = url.match(myregexp)[1];
					apiType = "youtube";
				}

				// Try vimeo
				var myregexp = /https?:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
				if(url.match(myregexp) != null) {
					mediaID = url.match(myregexp)[2];
					apiType = "vimeo";
				}

				// Try dailymotion
				var myregexp = /^.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/;
				if(url.match(myregexp) != null) {
					mediaID = url.match(myregexp)[4]!== undefined ? url.match(myregexp)[4] : url.match(myregexp)[2];
					apiType = "dailymotion";
				}
			}

			// Global variables, needed if set-embed-data functions are called as callbacks, thus these can not be passed as parameters
			fc_elemID_'.$field_name_js.' = element_id;
			fc_mediaID_'.$field_name_js.' = mediaID;

			// Create AJAX url
			var ajax_url;
			var ajax_type;
			if (mediaID && apiType) {
				'.($debug_to_console ? 'window.console.log("Media type: "+apiType);' : '').'
				'.($debug_to_console ? 'window.console.log("Media ID: "+mediaID);' : '').'
				switch(apiType)
				{
					case "youtube"    : ajax_url = "https://www.googleapis.com/youtube/v3/videos?id="+mediaID+"&key='.$youtube_key.'&part=snippet,contentDetails,statistics,status";/*&callback=youtubeCallback_'.$field_name_js.'";*/  break;
					case "vimeo"      : ajax_url = "//vimeo.com/api/v2/video/"+mediaID+".json";/*?callback=vimeoCallback_'.$field_name_js.'";*/  break;
					case "dailymotion": ajax_url = "https://api.dailymotion.com/video/"+mediaID+"?fields=description,duration,owner.screenname,thumbnail_60_url,title";/*&callback=dailymotionCallback_'.$field_name_js.'";*/  break;
				}
				ajax_type = "json";
			}
			else {
				// try embed.ly
				ajax_url = "https://noembed.com/embed?url="+encodeURIComponent(url); // TODO check if needed to add more URL vars
				ajax_type = "json";
			}

			// Make AJAX call
			jQuery.ajax({
				url: ajax_url,
				dataType: ajax_type,
				success: function(data) {
					'.($debug_to_console ? 'window.console.log("Received Server response");' : '').'
					var response;
					try {
						if (ajax_type=="html") 	data = data.replace(/_cbfunc_'.$field_name_js.'\(/, "").replace(/\)?;?$/, "");
						response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
						'.($debug_to_console ? 'window.console.log("Calling callback "+(apiType ? apiType : "embedly")+" function on data:");' : '').'
						'.($debug_to_console ? 'window.console.log(response);' : '').'
						if (mediaID && apiType)
						{
							switch(apiType)
							{
								case "youtube"     : youtubeCallback_'.$field_name_js.'(response, mediaID, element_id);  break;
								case "vimeo"       : vimeoCallback_'.$field_name_js.'(response, mediaID, element_id);  break;
								case "dailymotion" : dailymotionCallback_'.$field_name_js.'(response, mediaID, element_id);  break;
							}
						}
						else {
							noembedCallback_'.$field_name_js.'(response, element_id);
						}
					} catch(err) {
						msg_box.html("<span class=\"alert alert-warning fc-iblock\">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_RESPONSE_PARSING_FAILED', true).': "+err.message+"</span>");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					'.($debug_to_console ? 'window.console.log("Error, responseText is:");' : '').'
					'.($debug_to_console ? 'window.console.log(jqXHR.responseText);' : '').'
					var response;
					try {
						response = jQuery.parseJSON( jqXHR.responseText );
					} catch(err) {
						response = jqXHR.responseText;
					}
					var errorText = typeof response !== "object" ? response : (mediaID && (apiType=="dailymotion" || apiType=="youtube")  ? response.error.message : response.error_message);
					if (apiType=="youtube" && typeof response == "object") errorText += " Reason: "  +response.error.errors[0].reason;
					msg_box.html("<span class=\"alert alert-warning fc-iblock\"><i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SERVER_RESPONDED_WITH_ERROR', true).'</i><br/><br/>"+errorText+"</span>");
				}
			});
		}


		function youtubeDurationToSeconds_'.$field_name_js.'(duration)
		{
		  var match = duration.match(/PT(\d+H)?(\d+M)?(\d+S)?/);
		  var hours = (parseInt(match[1]) || 0);
		  var minutes = (parseInt(match[2]) || 0);
		  var seconds = (parseInt(match[3]) || 0);
			return hours * 3600 + minutes * 60 + seconds;
		}


		function youtubeCallback_'.$field_name_js.'(data, mediaID, element_id)
		{
			mediaID = typeof mediaID === "undefined" || !mediaID  ?  fc_mediaID_'.$field_name_js.' : mediaID;    // *** mediaID not set if called as callback
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;    // *** element_id not set if called as callback

			if (typeof data === "object" && typeof data.error === "undefined" ) {
				if (data.items.length == 0) {
					jQuery("#fcfield_sm_mssg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">Not found</span>");
					return;
				}
				toggleMETArows_'.$field_name_js.'(element_id, 1);
				setEmbedData_'.$field_name_js.'("youtube", mediaID, element_id);
				setMetaData_'.$field_name_js.'({title: data.items[0].snippet.title, author: data.items[0].snippet.channelTitle, duration: youtubeDurationToSeconds_'.$field_name_js.'(data.items[0].contentDetails.duration), description: data.items[0].snippet.description, thumb: data.items[0].snippet.thumbnails.medium.url}, element_id);
				jQuery("#fcfield_sm_mssg_" + element_id).html("");
			} else {
				var errorText = typeof data === "object" ? data.error.message : data;
				jQuery("#fcfield_sm_mssg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\"><i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SERVER_RESPONDED_WITH_ERROR', true).'</i><br/><br/>"+errorText+"</span>");
			}
		}


		function vimeoCallback_'.$field_name_js.'(data, mediaID, element_id)
		{
			mediaID = typeof mediaID === "undefined" || !mediaID  ?  fc_mediaID_'.$field_name_js.' : mediaID;    // *** mediaID not set if called as callback
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;    // *** element_id not set if called as callback

			if (typeof data === "object" && data.type != "error") {
				toggleMETArows_'.$field_name_js.'(element_id, 1);
				setEmbedData_'.$field_name_js.'("vimeo", mediaID, element_id);
				setMetaData_'.$field_name_js.'({title: data[0].title, author: data[0].user_name, duration: data[0].duration, description: data[0].description, thumb: data[0].thumbnail_small}, element_id);
				jQuery("#fcfield_sm_mssg_" + element_id).html("");
			} else {
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_sm_mssg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\"><i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SERVER_RESPONDED_WITH_ERROR', true).'</i><br/><br/>"+errorText+"</span>");
			}
		}


		function dailymotionCallback_'.$field_name_js.'(data, mediaID, element_id)
		{
			mediaID = typeof mediaID === "undefined" || !mediaID  ?  fc_mediaID_'.$field_name_js.' : mediaID;    // *** mediaID not set if called as callback
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;    // *** element_id not set if called as callback

			if (typeof data === "object" && typeof data.error === "undefined") {
				toggleMETArows_'.$field_name_js.'(element_id, 1);
				setEmbedData_'.$field_name_js.'("dailymotion", mediaID, element_id);
				setMetaData_'.$field_name_js.'({title: data.title, author: data["owner.screenname"], duration: data.duration, description: data.description, thumb: data.thumbnail_60_url}, element_id);
				jQuery("#fcfield_sm_mssg_" + element_id).html("");
			} else {
				var errorText = typeof data === "object" ? data.error.message : data;
				jQuery("#fcfield_sm_mssg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\"><i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SERVER_RESPONDED_WITH_ERROR', true).'</i><br/><br/>"+errorText+"</span>");
			}
		}


		function noembedCallback_'.$field_name_js.'(data, element_id)
		{
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;    // *** element_id not set if called as callback

			if (typeof data === "object" && typeof data.error == "undefined")
			{
				if (1)  // TODO Possibly add more checks
				{
					var urlregex = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
					if (data.html.match(urlregex) != null)
					{
						toggleMETArows_'.$field_name_js.'(element_id, 1);
						var embed_url = data.html.match(urlregex)[0];
						setEmbedData_'.$field_name_js.'("noembed:"+data.provider_name.toLowerCase(), embed_url, element_id);
						setMetaData_'.$field_name_js.'({title: data.title, author: data.author_name, duration: data.duration, description: data.description, thumb: data.thumbnail_url}, element_id);
						jQuery("#fcfield_sm_mssg_" + element_id).html("");
					} else {
						jQuery("#fcfield_sm_mssg_" + element_id).html("IFRAME SRC parameter not found in response");
					}
				}
				else
				{
					jQuery("#fcfield_sm_mssg_" + element_id).html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">?</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_URL_NOT_'.$_MEDIA_).'</div>");
				}
			}
			else {
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_sm_mssg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\"><i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SERVER_RESPONDED_WITH_ERROR', true).'</i><br/><br/>"+errorText+"</span>");
			}
		}


		function setEmbedData_'.$field_name_js.'(apiType, mediaID, element_id)
		{
			mediaID = typeof mediaID === "undefined" || !mediaID  ?  fc_mediaID_'.$field_name_js.' : mediaID;
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;

			if (apiType=="") mediaID = "";
			document.getElementById(element_id+"_api_type").value  = apiType;
			document.getElementById(element_id+"_media_id").value  = mediaID;
			document.getElementById(element_id+"_embed_url").value = "";

			if (apiType!="" && mediaID!="")
			{
				var preview_html = \'<iframe class="sharedmedia seamless" src="\';
				switch(apiType) {
					case "youtube"    :  preview_html += "//www.youtube.com/embed/";   break;
					case "vimeo"      :  preview_html += "//player.vimeo.com/video/";  break;
					case "dailymotion":  preview_html += "//www.dailymotion.com/embed/video/"; break;
					default:
						// Other embed API, e.g. embed.ly , full embed URL is inside mediaID
						document.getElementById(element_id+"_media_id").value   = "";
						document.getElementById(element_id+"_embed_url").value = mediaID;
						break;
				}
				preview_html += mediaID + \'" style="width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" allowFullScreen></iframe>\';
				document.getElementById(element_id+"_preview").innerHTML = preview_html;
				setTimeout(function() { setHeight_'.$field_name_js.'("iframe.sharedmedia", 2/3); }, 200);
			}
			else {
				document.getElementById(element_id+"_preview").innerHTML = "";
			}
		}


		function setMetaData_'.$field_name_js.'(data, element_id)
		{
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field_name_js.' : element_id;

			jQuery("#"+element_id+"_title").val(data.title);
			jQuery("#"+element_id+"_author").val(data.author);
			jQuery("#"+element_id+"_duration").val(data.duration);
			jQuery("#"+element_id+"_description").val(data.description);
			jQuery("#"+element_id+"_thumb").val(data.thumb);
		}

		function toggleMETArows_'.$field_name_js.'(element_id, action)
		{
			action = typeof action === "undefined" ? 0 : action;
			if (action==1)
				jQuery("#" + element_id + "_title, " + "#" + element_id + "_author, " + "#" + element_id + "_description, " + "#" + element_id + "_api_type, " + "#" + element_id + "_media_id, " + "#" + element_id + "_preview, " + "#" + element_id + "_width, " + "#" + element_id + "_height").closest("tr").fadeIn(4000);  // compatibility ?: show with fade so that the elements size are proper in case some JS code needs them ?
			else if (action==-1)
				jQuery("#" + element_id + "_title, " + "#" + element_id + "_author, " + "#" + element_id + "_description, " + "#" + element_id + "_api_type, " + "#" + element_id + "_media_id, " + "#" + element_id + "_preview, " + "#" + element_id + "_width, " + "#" + element_id + "_height").closest("tr").hide("fast");
			else if (action==0)
				jQuery("#" + element_id + "_title, " + "#" + element_id + "_author, " + "#" + element_id + "_description, " + "#" + element_id + "_api_type, " + "#" + element_id + "_media_id, " + "#" + element_id + "_preview, " + "#" + element_id + "_width, " + "#" + element_id + "_height").closest("tr").toggle(0);  // compatibility ?: toggle instantly
		}

		function setHeight_'.$field_name_js.'(selector, factor)
		{
			jQuery(selector).each( function() {
	  		jQuery(this).css("height", parseInt(factor*jQuery(this).width()));
	  	});
		}

		jQuery(window).resize(function() {
			setHeight_'.$field_name_js.'("iframe.sharedmedia", 2/3);
		});

		jQuery(document).ready(function(){
			setHeight_'.$field_name_js.'("iframe.sharedmedia", 2/3);
			jQuery(document).on("mouseenter", ".fcfieldval_container_'.$field->id.'", function(event) {
				setHeight_'.$field_name_js.'("iframe.sharedmedia", 2/3);
			});
		});
		';

		// TODO more
		//if (!headers_sent()) header("Content-Security-Policy: script-src 'unsafe-inline' 'self' ...;");


		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		// *******************
		// Create field's HTML
		// *******************

		$field->html = array();
		$n = 0;

		foreach ($field->value as $n => $value)
		{
			// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$value = $array ?: array();
			}

			// Compatibility with deprecated fields
			if (empty($value['api_type'])) $value['api_type'] = isset($value['videotype']) ? $value['videotype'] : (isset($value['audiotype']) ? $value['audiotype'] : '');
			if (empty($value['media_id']))  $value['media_id']  = isset($value['videoid'])   ? $value['videoid']   : (isset($value['audioid'])   ? $value['audioid']   : '');

			$is_empty = (empty($value['api_type']) || empty($value['media_id'])) && empty($value['embed_url']);
			if ( $is_empty && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			if (!empty($value['api_type']) && !empty($value['media_id']))
			{
				$content_id = $value['media_id'];
				switch($value['api_type'])
				{
					case 'youtube':
						$value['embed_url'] = '//www.youtube.com/embed/' . $content_id;
						break;
					case 'vimeo':
						$value['embed_url'] = '//player.vimeo.com/video/' . $content_id;
						break;
					case 'dailymotion':
						$value['embed_url'] = '//www.dailymotion.com/embed/video/' . $content_id;
						break;
					default:
						// For embed.ly , the full URL is inside content ID
						$value['embed_url'] = $content_id;
						break;
				}
			}
			if (!isset($value['embed_url'])) $value['embed_url'] = '';
			$embed_html = '<iframe class="sharedmedia seamless" src="'.($value['embed_url'] ? $value['embed_url'] : 'about:blank').'" style="width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" allowFullScreen></iframe>';

			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['embed_url']))   $value['embed_url'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
			if (empty($value['duration']))     $value['duration'] = '';
			if (!isset($value['height']))      $value['height'] = '';
			if (!isset($value['width']))       $value['width'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			if (!isset($value['thumb']))       $value['thumb'] = '';

			// Force display of configuration's default dimensions
			if ($display_edit_size_form!=1)
			{
				$value['height'] = $height;
				$value['width']  = $width;
			}

			$html_field = '
				'.($use_ingroup || !$multiple ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
			<div class="fcclear"></div>
			<div class="fcfield_field_data_box">
			<table class="fc-form-tbl fcfullwidth fcinner fc-sharedmedia-field-tbl" data-row="'.$n.'">
			<tbody>
				<tr>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_MEDIA_URL') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval' . $required . ' sm_url" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="'.htmlspecialchars($value['url'], ENT_COMPAT, 'UTF-8').'" size="60" />
					</td>
				</tr>
				<tr>
					<td style="text-align:right; padding:0 8px 4px 0;">
						<a href="javascript:;" class="btn btn-primary btn-small sm_fetch_btn" id="'.$elementid_n.'_fetch_btn" title="'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_FETCH').'" onclick="fetchData_'.$field_name_js.'(\''.$elementid_n.'\'); return false;"><i class="icon-loop"></i>'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_FETCH').'</a>
					</td>
					<td style="text-align:left; padding:0 8px 4px 0;">
						'.($use_ingroup ? '<a href="javascript:;" class="btn btn-warning btn-small sm_clear_btn" id="'.$elementid_n.'_clear_btn" title="'.JText::_('FLEXI_CLEAR').'" onclick="clearData_'.$field_name_js.'(\''.$elementid_n.'\'); return false;" ><i class="icon-cancel"></i>'.JText::_('FLEXI_CLEAR').'</a>' : '').'
						<input type="hidden" class="sm_embed_url" id="'.$elementid_n.'_embed_url" name="'.$fieldname_n.'[embed_url]" value="'.htmlspecialchars($value['embed_url'], ENT_COMPAT, 'UTF-8').'" />
					</td>
				</tr>
				<tr>
					<td colspan="2" style="padding:0"><span class="fcfield_sm_mssg" id="fcfield_sm_mssg_'.$elementid_n.'"></span></td>
				</tr>'
			.($display_api_type_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_EMBED_METHOD').'</span></td>
					<td>
						<input type="text" class="fcfield_textval sm_api_type" id="'.$elementid_n.'_api_type" name="'.$fieldname_n.'[api_type]" value="'.htmlspecialchars($value['api_type'], ENT_COMPAT, 'UTF-8').'" size="30" readonly="readonly" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_api_type" id="'.$elementid_n.'_api_type" name="'.$fieldname_n.'[api_type]" value="'.htmlspecialchars($value['api_type'], ENT_COMPAT, 'UTF-8').'" style="background-color:#eee" /></td></tr>')
			.($display_media_id_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_MEDIA_ID').'</span></td>
					<td>
						<input type="text" class="fcfield_textval sm_media_id" id="'.$elementid_n.'_media_id" name="'.$fieldname_n.'[media_id]" value="'.htmlspecialchars($value['media_id'], ENT_COMPAT, 'UTF-8').'" size="30" readonly="readonly" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_media_id" id="'.$elementid_n.'_media_id" name="'.$fieldname_n .'[media_id]" value="'.htmlspecialchars($value['media_id'], ENT_COMPAT, 'UTF-8').'" style="background-color:#eee" /></td></tr>')
			.($display_title_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_TITLE') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval sm_title" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="'.htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8').'" size="60" '.($display_title_form==2 ? 'readonly="readonly"' : '').' />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_title" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="'.htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8').'" /></td></tr>')
			.($display_author_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_AUTHOR') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval sm_author" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="'.htmlspecialchars($value['author'], ENT_COMPAT, 'UTF-8').'" size="60" '.($display_author_form==2 ? 'readonly="readonly"' : '').' />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_author" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="'.htmlspecialchars($value['author'], ENT_COMPAT, 'UTF-8').'" /></td></tr>')
			.($display_duration_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_DURATION') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval inlineval sm_duration" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="'.htmlspecialchars($value['duration'], ENT_COMPAT, 'UTF-8').'" size="10" '.($display_duration_form==2 ? 'readonly="readonly"' : '').' /> '.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SECONDS').'
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_duration" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="'.htmlspecialchars($value['duration'], ENT_COMPAT, 'UTF-8').'" /></td></tr>')
			.($display_edit_size_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_PLAYER_DIMENSIONS') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval inlineval sm_width" size="5" id="'.$elementid_n.'_width"  name="'.$fieldname_n.'[width]"  value="'.htmlspecialchars($value['width'], ENT_COMPAT, 'UTF-8').'" '.($display_edit_size_form==2 ? 'readonly="readonly"' : '').' /> x
						<input type="text" class="fcfield_textval inlineval sm_height" size="5" id="'.$elementid_n.'_height" name="'.$fieldname_n.'[height]" value="'.htmlspecialchars($value['height'], ENT_COMPAT, 'UTF-8').'" '.($display_edit_size_form==2 ? 'readonly="readonly"' : '').' /> '.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_PIXELS').'
					</td>
				</tr>' : '')  // no need for hidden width/height fields, server validation will discard them anyway
			.($display_description_form ? '
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td class="key"><span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_DESCRIPTION') . '</span></td>
					<td>
						<textarea class="fcfield_textareaval sm_description" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" rows="7" '.($display_description_form==2 ? 'readonly="readonly"' : '').'>' . $value['description'] . '</textarea>
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" class="sm_description" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" value="'.htmlspecialchars($value['description'], ENT_COMPAT, 'UTF-8').'" /></td></tr>')
				. '
			</tbody>
			</table>
			</div>

			<div class="fcfield_field_preview_box">
			<table class="fcfield_field_preview_table">
			<tbody>
				<tr '.($is_empty ? ' style="display:none;" ' : '').'>
					<td>
						' /*<span class="flexi label prop_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_PREVIEW') . '</span><br/>*/ .'
						<div class="sm_preview" id="'.$elementid_n.'_preview">
							'.$embed_html.'
						</div>
						<input type="hidden" class="sm_thumb" id="'.$elementid_n.'_thumb" name="'.$fieldname_n.'[thumb]" value="'.htmlspecialchars($value['thumb'], ENT_COMPAT, 'UTF-8').'" />
					</td>
				</tr>
			</tbody>
			</table>
			</div>
			';
			$field->html[$n] = $html_field;
		}

		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables fcprops-boxed" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] .'</div>';
		}

		// Add Error message
		if ( !empty($error_text) )
		{
			$error_text = '<div class="alert alert-warning fc-small fc-iblock">'.$error_text.'</div>';
			if (!$use_ingroup) {
				$field->html = $error_text . $field->html;
			} else {
				foreach($field->html as & $html) $html = $error_text . $html;
				unset($html);
			}
		}
	}


	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		$field->label = JText::_($field->label);

		// Get field values
		$values = $values ? $values : $field->value;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$_MEDIA_ = 'MEDIA';

		// Meta DATA that will be displayed
		$display_title    = $field->parameters->get('display_title', 1);
		$display_author   = $field->parameters->get('display_author', 0);
		$display_duration = $field->parameters->get('display_duration',0) ;
		$display_description = $field->parameters->get('display_description', 0);

		$headinglevel = $field->parameters->get('headinglevel', 3);
		$width        = (int)$field->parameters->get('width', 960);
		$height       = (int)$field->parameters->get('height', 540);
		$autostart    = $field->parameters->get('autostart', 0);
		$player_position = $field->parameters->get('player_position', 0);
		$display_edit_size_form = $field->parameters->get('display_edit_size_form', 1);

		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array();
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}


		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );

		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }

		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br class="fcclear" />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}

		// Create field's HTML
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			// Skip empty value but add empty placeholder if inside fieldgroup
			if ( empty($value) )
			{
				if ($is_ingroup)
				{
					$field->{$prop}[$n++] = '';
				}
				continue;
			}

			// Compatibility with deprecated fields
			if (empty($value['api_type'])) $value['api_type'] = isset($value['videotype']) ? $value['videotype'] : (isset($value['audiotype']) ? $value['audiotype'] : '');
			if (empty($value['media_id']))  $value['media_id']  = isset($value['videoid'])   ? $value['videoid']   : (isset($value['audioid'])   ? $value['audioid']   : '');

			// Skip empty value but add empty placeholder if inside fieldgroup
			if ( (empty($value['api_type']) || empty($value['media_id'])) && empty($value['embed_url']) )
			{
				if ($is_ingroup)
				{
					$field->{$prop}[$n++] = '';
				}
				continue;
			}

			$duration = intval($value['duration']);
			if ($display_duration && $duration)
			{
				$h = $duration >= 3600  ?  intval($duration/3600)  :  0;
				$m = $duration >= 60    ?  intval($duration/60 - $h*60)  :  0;
				$s = $duration - $m*60 -$h*3600;
				$duration_str  = $h > 0  ? $h.":" : "";
				$duration_str .= str_pad($m,2,'0',STR_PAD_LEFT).':';
				$duration_str .= str_pad($s,2,'0',STR_PAD_LEFT);
			}
			else $duration_str = '';

			// Create field's html
			$html_meta = '
				'.($display_title  && !empty($value['title'])  ? '<h'.$headinglevel.'>' . $value['title']  . '</h'.$headinglevel.'>' : '') .'
				'.($display_author && !empty($value['author']) ? '<span class="label label-info label-small fc_sm_author-lbl">'.JText::_('Author').'</span> <b class="fc_sm_author">' . $value['author'] . '</b> ' : '') .'
				'.($duration_str ? '<span class="label label-info label-small fc_sm_duration-lbl">'.JText::_('Duration').'</span> <b class="fc_sm_duration">'.$duration_str.'</b> ' : '') .'
				'.($display_description && !empty($value['description']) ? '<div class="description">' . $value['description'] . '</div>' : '');

			if (!empty($value['embed_url']))
			{
				$embed_url = $value['embed_url'];
				$_show_related = '';
				$_show_srvlogo = '';
			}
			else
			{
				$content_id = $value['media_id'];
				switch($value['api_type'])
				{
					case 'youtube':
						$embed_url = '//www.youtube.com/embed/' . $content_id;
						$_show_related = '&rel=0';
						$_show_srvlogo = '&modestbranding=1&maxwidth=0&modestbranding=1';
						break;
					case 'vimeo':
						$embed_url = '//player.vimeo.com/video/' . $content_id;
						$_show_related = '';
						$_show_srvlogo = '';
						break;
					case 'dailymotion':
						$embed_url = '//www.dailymotion.com/embed/video/' . $content_id;
						$_show_related = '&related=0';
						$_show_srvlogo = '&logo=0';
						break;
					default:  // For embed.ly , the full URL is inside content ID
						$embed_url = $content_id;
						$_show_related = '';
						$_show_srvlogo = '';
						break;
				}
			}
			$player_url = ($embed_url ? $embed_url : 'about:blank').'?autoplay='.$autostart.$_show_related.$_show_srvlogo;

			$_width  = ($display_edit_size_form && (int) @ $value['width'])  ? (int)$value['width']  : $width;
			$_height = ($display_edit_size_form && (int) @ $value['height']) ? (int)$value['height'] : $height;

			$player_html = '
			<div class="fc_sharedmedia_player_outer">
				<iframe class="fc_sharedmedia_player_frame seamless" src="'.$player_url.'" style="width:'.$_width.'px; height:'.$_height.'px; border: none; overflow:hidden;" allowFullScreen></iframe>
			</div>';

			$field->{$prop}[$n] = $pretext
				. ($player_position ? '' : $player_html)
				. $html_meta
				. ($player_position ? $player_html : '')
				. $posttext;

			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}

		if (!$is_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}



	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$display_edit_size_form = $field->parameters->get('display_edit_size_form', 1);


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Support for serialized user data, e.g. basic CSV import / export. (Safety concern: objects code will abort unserialization!)
			if ( $is_importcsv && !is_array($v) )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'url' => $v
				);
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$url = flexicontent_html::dataFilter($v['url'], 4000, 'URL', 0);  // Clean bad text/html

			// Skip empty value, but if in group increment the value position
			if (empty($url))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = array();
			$newpost[$new]['url'] = $url;

			// Validate other value properties
			$newpost[$new]['api_type']    = flexicontent_html::dataFilter(@$v['api_type'], 0, 'STRING', 100);
			$newpost[$new]['media_id']    = flexicontent_html::dataFilter(@$v['media_id'], 0, 'STRING', 1000);
			$newpost[$new]['embed_url']   = flexicontent_html::dataFilter(@$v['embed_url'], 0, 'STRING', 1000);  // 'URL' strips needed characters ?
			$newpost[$new]['thumb']       = flexicontent_html::dataFilter(@$v['thumb'], 0, 'STRING', 1000);  // 'URL' strips needed characters ?
			$newpost[$new]['title']       = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 1000);
			$newpost[$new]['author']      = flexicontent_html::dataFilter(@$v['author'], 0, 'STRING', 1000);
			$newpost[$new]['duration']    = flexicontent_html::dataFilter(@$v['duration'], 0, 'INT', 20);
			$newpost[$new]['description'] = flexicontent_html::dataFilter(@$v['description'], 0, 'STRING', 10000);
			$newpost[$new]['height']      = $display_edit_size_form==1 ? flexicontent_html::dataFilter(@$v['height'], 0, 'INT', 0) : '';
			$newpost[$new]['width']       = $display_edit_size_form==1 ? flexicontent_html::dataFilter(@$v['width'], 0, 'INT', 0)  : '';

			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
			if ($v!==null) $post[$i] = serialize($v);
		}
	}



	// *************************
	// SEARCH / INDEXING METHODS
	// *************************

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;

		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;

		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description'), $properties_spacer=' ', $filter_func=null);
		return true;
	}

}
