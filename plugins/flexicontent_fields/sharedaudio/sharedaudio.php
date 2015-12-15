<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsSharedaudio extends FCField
{
	static $field_types = array('sharedaudio');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsSharedaudio( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_sharedaudio', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// displays the field when editing content item
		$field->label = JText::_($field->label);
		if ( !in_array($field->field_type, self::$field_types) ) return;

		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		$use_ingroup  = $field->parameters->get('use_ingroup', 0);
		$value_classes= 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$multiple     = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		// some parameter shortcuts
		$required = $field->parameters->get('required',0);
		$required = $required ? ' required' : '';
		
		$embedly_key = $field->parameters->get('embedly_key', '');
		$youtube_key = $field->parameters->get('youtube_key', '');
		$use_native_apis = (int) $field->parameters->get('use_native_apis', 0);
		
		$display_audiotype_form = $field->parameters->get('display_audiotype_form',1) ;
		$display_audioid_form   = $field->parameters->get('display_audioid_form',1) ;
		$display_title_form     = $field->parameters->get('display_title_form',1) ;
		$display_author_form    = $field->parameters->get('display_author_form',1) ;
		$display_duration_form    = $field->parameters->get('display_duration_form',1) ;
		$display_description_form = $field->parameters->get('display_description_form',1) ;
		
		// Initialise value property
		$values = $this->parseValues($field->value);
		if (empty($values)) {
			$values = array();
			$values[0]['url'] = '';
			$values[0]['audiotype'] = '';
			$values[0]['audioid'] = '';
			$values[0]['title'] = '';
			$values[0]['author'] = '';
			$values[0]['duration'] = '';
			$values[0]['description'] = '';
		}
		$value = $values[0];
		
		$field->html = array();
		foreach ($values as $n => $value)
		{
			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['audiotype']))   $value['audiotype'] = '';
			if (!isset($value['audioid']))     $value['audioid'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
			if (!isset($value['duration']))    $value['duration'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			
			$field->html[$n] = '
			<table class="admintable"><tbody>
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_URL').'</td>
					<td>
						<input type="text" class="fcfield_textval '.$required.'" name="custom['.$field->name.'][url]" value="'.$value['url'].'" size="60" />
						<input class="fcfield-button" type="button" value="'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_FETCH').'" onclick="fetchAudio_'.$field->name.'();" />
						<span id="fcfield_fetching_msg_'.$field->id.'"></span>
					</td>
				</tr>'
			.($display_audiotype_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_TYPE').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][audiotype]" value="'.$value['audiotype'].'" size="10" readonly="readonly" style="background-color:#eee" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][audiotype]" value="'.$value['audiotype'].'" size="10" readonly="readonly" style="background-color:#eee" />')
			.($display_audioid_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_ID').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][audioid]" value="'.$value['audioid'].'" size="15" readonly="readonly" style="background-color:#eee" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][audioid]" value="'.$value['audioid'].'" size="15" readonly="readonly" style="background-color:#eee" />')
			.($display_title_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_TITLE').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" />')
			.($display_author_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUTHOR').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" />')
			.($display_duration_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_DURATION').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][duration]" value="'.$value['duration'].'" size="10" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][duration]" value="'.$value['duration'].'" size="10" />')
			.($display_description_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_DESCRIPTION').'</td>
					<td>
						<textarea class="fcfield_textareaval" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea>
					</td>
				</tr>' : '
				<textarea style="display:none;" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea>').'
			';
			
			$iframecode = '';
			if($value['audiotype']!="" && $value['audioid'] != '')
			{
				$iframecode = '<iframe class="sharedaudio" src="';
				switch($value['audiotype']){
					case "youtube":
						$iframecode .= "//www.youtube.com/embed/";
						break;
					case "vimeo":
						$iframecode .= "//player.vimeo.com/video/";
						break;
					case "dailymotion":
						$iframecode .= "//www.dailymotion.com/embed/video/";
						break;
					default:
						// For embed.ly we will have added 'URL' into our 'id' variable (below)
						break;
				}
				$val_id = $value['audioid'];  // In case of embed.ly, this is not id but it is full URL
				$iframecode .= $val_id.'" width="240" height="135" style="border:0;" allowFullScreen></iframe>';
			}
			
			$field->html[$n] .= '
				<tr>
					<td class="key">
						'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_PREVIEW').'
					</td>
					<td>
						<div id="'.$field->name.'_thumb">
							'.$iframecode.'
						</div>
					</td>
				</tr>
			</tbody></table>';
		}
		
		$js = "";
		$css = "";
		
		$js = '
		var fc_audioID_'.$field->name.';
		function fetchAudio_'.$field->name.'() {
			var fieldname = \'custom['.$field->name.']\';
			var url = fieldname+"[url]";
			url = document.forms["adminForm"].elements[url].value;
			var audioID = "";
			var audioType = "";
			
			var _loading_img = "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">";
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html(_loading_img);
			
			if (window.console) window.console.log("Fetching "+url);
			
			if ('.$use_native_apis.') {
				// try youtube
				var myregexp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
				if(url.match(myregexp) != null) {
					audioID = url.match(myregexp)[1];
					audioType = "youtube";
				}
				
				// Try vimeo
				var myregexp = /https?:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
				if(url.match(myregexp) != null) {
					audioID = url.match(myregexp)[2];
					audioType = "vimeo";
				}
				
				// Try dailymotion
				var myregexp = /^.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/;
				if(url.match(myregexp) != null) {
					audioID = url.match(myregexp)[4]!== undefined ? url.match(myregexp)[4] : url.match(myregexp)[2];
					audioType = "dailymotion";
				}
			}
			
			fc_audioID_'.$field->name.' = audioID;
			
			// Clear existing data
			updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
			updateValueTypeId_'.$field->name.'("","");
			
			var jsonurl;
			if (audioID && audioType) {
				if(window.console) window.console.log("Audio type: "+audioType);
				if(window.console) window.console.log("Audio ID: "+audioID);
				switch(audioType)
				{
					case "youtube"    : jsonurl = "https://www.googleapis.com/youtube/v3/videos?id="+audioID+"&key='.$youtube_key.'&part=snippet,contentDetails,statistics,status";/*&callback=youtubeCallback_'.$field->name.'";*/  break;
					case "vimeo"      : jsonurl = "//vimeo.com/api/v2/video/"+audioID+".json";/*?callback=vimeoCallback_'.$field->name.'";*/  break;
					case "dailymotion": jsonurl = "https://api.dailymotion.com/video/"+audioID+"?fields=description,duration,owner.screenname,thumbnail_60_url,title";/*&callback=dailymotionCallback_'.$field->name.'";*/  break;
				}
			}
			else {
				// try embed.ly
				jsonurl = "http://api.embed.ly/1/oembed?url="+encodeURIComponent(url)+"&key='.$embedly_key.'";//&callback=embedlyCallback_'.$field->name.'";
			}
			if (url!="") {
				jQuery.ajax({
					url: jsonurl,
					dataType: "json",
					success: function(data) {
						window.console.log("Received Server response");
						var response;
						try {
							response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
							window.console.log("Calling callback function on data:");
							window.console.log(response);
							if (audioID && audioType)
							{
								switch(audioType)
								{
									case "youtube"     : youtubeCallback_'.$field->name.'(response);  break;
									case "vimeo"       : vimeoCallback_'.$field->name.'(response);  break;
									case "dailymotion" : dailymotionCallback_'.$field->name.'(response);  break;
								}
							}
							else {
								embedlyCallback_'.$field->name.'(response);
							}
						} catch(err) {
							jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+response+"</span>");
						}
					},
					error: function(jqXHR, textStatus, errorThrown) {
						window.console.log("Error, responseText is:");
						window.console.log(jqXHR.responseText);
						var response;
						try {
							response = jQuery.parseJSON( jqXHR.responseText );
						} catch(err) {
							response = jqXHR.responseText;
						}
						var errorText = typeof response !== "object" ? response : (audioID && (audioType=="dailymotion" || audioType=="youtube")  ? response.error.message : response.error_message);
						if (audioType=="youtube") errorText += " Reason: "  +response.error.errors[0].reason;
						//document.getElementById("'.$field->name.'_thumb").innerHTML = "<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>";
						jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
					}
				});
			}
			else {
				updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateValueTypeId_'.$field->name.'("","");
			}
		}
		
		
		function youtubeDurationToSeconds_'.$field->name.'(duration)
		{
		  var match = duration.match(/PT(\d+H)?(\d+M)?(\d+S)?/);
		  var hours = (parseInt(match[1]) || 0);
		  var minutes = (parseInt(match[2]) || 0);
		  var seconds = (parseInt(match[3]) || 0);
			return hours * 3600 + minutes * 60 + seconds;
		}
		
		
		function youtubeCallback_'.$field->name.'(data, audioID)
		{
			if (typeof data === "object" && typeof data.error === "undefined" ) {
				if (data.items.length == 0) {
					jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">Not found</span>");
					return;
				}
				updateValueTypeId_'.$field->name.'("youtube", audioID);
				updateValueInfo_'.$field->name.'({title: data.items[0].snippet.title, author: data.items[0].snippet.channelTitle, duration: youtubeDurationToSeconds_'.$field->name.'(data.items[0].contentDetails.duration), description: data.items[0].snippet.description, thumb: data.items[0].snippet.thumbnails.medium.url});
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
			} else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error.message : data;
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("","");
			}
		}
		
		
		function vimeoCallback_'.$field->name.'(data, audioID)
		{
			if (typeof data === "object" && data.type != "error") {
				updateValueTypeId_'.$field->name.'("vimeo", audioID);
				updateValueInfo_'.$field->name.'({title: data[0].title, author: data[0].user_name, duration: data[0].duration, description: data[0].description, thumb: data[0].thumbnail_small});
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
			} else {
				var errorText = typeof data === "object" ? data.error_message : data;
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_UNABLE_TO_PARSE').'");
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("","");
			}
		}
		
		
		function dailymotionCallback_'.$field->name.'(data, audioID)
		{
			if (typeof data === "object" && typeof data.error === "undefined") {
				updateValueTypeId_'.$field->name.'("dailymotion", audioID);
				updateValueInfo_'.$field->name.'({title: data.title, author: data["owner.screenname"], duration: data.duration, description: data.description, thumb: data.thumbnail_60_url});
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
			} else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+data.error.message+"</span>");
				updateValueTypeId_'.$field->name.'("","");
			}
		}
		
		
		function embedlyCallback_'.$field->name.'(data)
		{
			if (typeof data === "object" && data.type != "error") {
				var myregexp = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if(data.html.match(myregexp) != null) {
					var iframeurl = data.html.match(myregexp)[0];
					var iframecode = \'<iframe class="sharedaudio" src="\'+iframeurl+\'" width="240" height="135" style="border:0;" allowFullScreen></iframe>\';
					updateValueTypeId_'.$field->name.'("embed.ly:"+data.provider_name.toLowerCase(),iframeurl);
					updateValueInfo_'.$field->name.'({title: data.title, author: data.author_name, duration: "", description: data.description, thumb: data.thumbnail_url});
					document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
				}
			}
			else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_fetching_msg_'.$field->id.'").html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("","");
			}
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
		}
		
		
		function updateValueTypeId_'.$field->name.'(audioType, audioID)
		{
			audioID = typeof audioID === "undefined" || !audioID  ?  fc_audioID_'.$field->name.' : audioID;
			if (audioType=="") audioID = "";
			
			var fieldname = \'custom['.$field->name.']\';
			field = fieldname+"[audiotype]";
			document.forms["adminForm"].elements[field].value = audioType;
			field = fieldname+"[audioid]";
			document.forms["adminForm"].elements[field].value = audioID;

			if (audioType!="" && audioID!="")
			{
				var iframecode = \'<iframe class="sharedaudio" src="\';
				switch(audioType) {
					case "youtube"    :  iframecode += "//www.youtube.com/embed/";   break;
					case "vimeo"      :  iframecode += "//player.vimeo.com/video/";  break;
					case "dailymotion":  iframecode += "//www.dailymotion.com/embed/video/"; break;
				}
				iframecode += audioID + \'" width="240" height="135" style="border:0;" allowFullScreen></iframe>\';
				document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
			}
			else {
				document.getElementById("'.$field->name.'_thumb").innerHTML = "";
			}
		}
		
		
		function updateValueInfo_'.$field->name.'(data)
		{
			var fieldname = \'custom['.$field->name.']\';
			
			document.forms["adminForm"].elements[  fieldname+"[title]"  ].value = data.title;
			document.forms["adminForm"].elements[  fieldname+"[author]"  ].value = data.author;
			document.forms["adminForm"].elements[  fieldname+"[duration]"  ].value = data.duration;
			document.forms["adminForm"].elements[  fieldname+"[description]"  ].value = data.description;
		}
		';
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue '.(JComponentHelper::getParams('com_flexicontent')->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// displays the field in the frontend
		
		$field->label = JText::_($field->label);
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		$field->{$prop} = '';
		foreach ( $values as $value )
		{
			if (empty($value)) continue;
			
			$value = unserialize($value);
			if ( empty($value['audioid']) ) continue;
			
			// some parameter shortcuts
			$display_title  = $field->parameters->get('display_title',1) ;
			$display_author = $field->parameters->get('display_author',0) ;
			$display_duration = $field->parameters->get('display_duration',0) ;
			$display_description = $field->parameters->get('display_description',0) ;
			
			$pretext  = $field->parameters->get('pretext','') ;
			$posttext = $field->parameters->get('posttext','') ;
			
			$headinglevel = $field->parameters->get('headinglevel', 3);
			$width        = $field->parameters->get('width', 960);
			$height       = $field->parameters->get('height', 540);
			$autostart    = $field->parameters->get('autostart', 0);
			
			// generate html output
			$field->{$prop} .= $pretext;
			$field->{$prop} .= '<iframe class="sharedaudio" src="';
			switch($value['audiotype']){
				case 'youtube':
					$field->{$prop} .= '//www.youtube.com/embed/';
					$_show_related = '&rel=0';
					$_show_srvlogo = '&modestbranding=1';
					break;
				case 'vimeo':
					$field->{$prop} .= '//player.vimeo.com/video/';
					$_show_related = '';
					$_show_srvlogo = '';
					break;
				case 'dailymotion':
					$field->{$prop} .= '//www.dailymotion.com/embed/video/';
					$_show_related = '&related=0';
					$_show_srvlogo = '&logo=0';
					break;
				default:
					// For embed.ly we will have added 'URL' into our 'id' variable (below)
					break;
			}
			$val_id = $value['audioid'];  // In case of embed.ly, this is not id but it is full URL
			$field->{$prop} .= '
				'.$val_id.'?autoplay='.$autostart.$_show_related.$_show_srvlogo.'" width="'.$width.'" height="'.$height.'" style="border:0;" allowFullScreen></iframe>
				'.($display_title && !empty($value['title'])   ? '<h'.$headinglevel.'>'.$value['title'].'</h'.$headinglevel.'>' : '').'
				'.($display_author && !empty($value['author']) ? '<div class="author">'.$value['author'].'</div>' : '')
				;
			$duration = intval($value['duration']);
			if ($display_duration && $duration) {
				if ($duration >= 3600) $h = intval($duration/3600);
				if ($duration >= 60)   $m = intval($duration/60 - $h*60);
				$s = $duration - $m*60 -$h*3600;
				if ($h>0) $h .= ":";
				$m = str_pad($m,2,'0',STR_PAD_LEFT).':';
				$s = str_pad($s,2,'0',STR_PAD_LEFT);
				$field->{$prop} .= '<div class="duration">'.$h.$m.$s.'</div>';
			}
			if ($display_description && !empty($value['description'])) $field->{$prop} .= '<div class="description">'.$value['description'].'</div>';
			$field->{$prop} .= $posttext;
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField(&$field, &$post, &$file, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
		// Currently post is an array of properties, TODO: make field multi-value
		if ( empty($post) ) $post = array();
		else if ( !isset($post[0]) ) $post = array( $post );
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($v) ) {
				if ( @unserialize($v)!== false || $v === 'b:0;' ) {  // support for exported serialized data)
					$v = unserialize($v);
				} else {
					$v = array('url' => $v);
				}
			}
			
			
			// **************************************************************
			// Validate data, skipping values that are empty after validation
			// **************************************************************
			
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
			$newpost[$new]['audiotype'] = flexicontent_html::dataFilter(@$v['audiotype'], 0, 'STRING', 0);
			$newpost[$new]['audioid'] = flexicontent_html::dataFilter(@$v['audioid'], 0, 'STRING', 0);
			$newpost[$new]['title'] = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 0);
			$newpost[$new]['author'] = flexicontent_html::dataFilter(@$v['author'], 0, 'STRING', 0);
			$newpost[$new]['duration'] = flexicontent_html::dataFilter(@$v['duration'], 0, 'INT', 0);
			$newpost[$new]['description'] = flexicontent_html::dataFilter(@$v['description'], 0, 'STRING', 0);

			$new++;
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v) {
			if ($v!==null) $post[$i] = serialize($v);
		}
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description','audiotype'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description','audiotype'), $properties_spacer=' ', $filter_func=null);
		return true;
	}

}