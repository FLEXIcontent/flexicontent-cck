<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsSharedvideo extends FCField
{
	static $field_types = array('sharedvideo');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsSharedvideo( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_sharedvideo', JPATH_ADMINISTRATOR);
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
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		// some parameter shortcuts
		$required = $field->parameters->get('required',0);
		$required = $required ? ' required' : '';
		$embedly_key = $field->parameters->get('embedly_key','') ;
		
		$display_videotype_form = $field->parameters->get('display_videotype_form',1) ;
		$display_videoid_form   = $field->parameters->get('display_videoid_form',1) ;
		$display_title_form     = $field->parameters->get('display_title_form',1) ;
		$display_author_form    = $field->parameters->get('display_author_form',1) ;
		$display_duration_form    = $field->parameters->get('display_duration_form',1) ;
		$display_description_form = $field->parameters->get('display_description_form',1) ;
		
		// Initialise value property
		$values = $this->parseValues($field->value);
		if (empty($values)) {
			$values = array();
			$values[0]['url'] = '';
			$values[0]['videotype'] = '';
			$values[0]['videoid'] = '';
			$values[0]['title'] = '';
			$values[0]['author'] = '';
			$values[0]['duration'] = '';
			$values[0]['description'] = '';
		}
		$value = $values[0];
		
		$field->html = array();
		foreach($values as $n => $value)
		{
			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['videotype']))   $value['videotype'] = '';
			if (!isset($value['videoid']))     $value['videoid'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
			if (!isset($value['duration']))    $value['duration'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			
			$field->html[$n] = '
			<table class="admintable"><tbody>
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_URL').'</td>
					<td>
						<input type="text" class="fcfield_textval '.$required.'" name="custom['.$field->name.'][url]" value="'.$value['url'].'" size="60" />
						<input class="fcfield-button" type="button" value="'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_FETCH').'" onclick="fetchVideo_'.$field->name.'();" />
						<span id="fcfield_fetching_msg_'.$field->id.'"></span>
					</td>
				</tr>'
			.($display_videotype_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_TYPE').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][videotype]" value="'.$value['videotype'].'" size="10" readonly="readonly" style="background-color:#eee" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][videotype]" value="'.$value['videotype'].'" size="10" readonly="readonly" style="background-color:#eee" />')
			.($display_videoid_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_ID').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][videoid]" value="'.$value['videoid'].'" size="15" readonly="readonly" style="background-color:#eee" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][videoid]" value="'.$value['videoid'].'" size="15" readonly="readonly" style="background-color:#eee" />')
			.($display_title_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_TITLE').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" />')
			.($display_author_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_AUTHOR').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" />')
			.($display_duration_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DURATION').'</td>
					<td>
						<input type="text" class="fcfield_textval" name="custom['.$field->name.'][duration]" value="'.$value['duration'].'" size="10" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom['.$field->name.'][duration]" value="'.$value['duration'].'" size="10" />')
			.($display_description_form ? '
				<tr>
					<td class="key">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DESCRIPTION').'</td>
					<td>
						<textarea class="fcfield_textareaval" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea>
					</td>
				</tr>' : '
				<textarea style="display:none;" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea>').'
			';
			
			$iframecode = '';
			if($value['videotype']!="" && $value['videoid']!="")
			{
				$iframecode = '<iframe class="sharedvideo" src="';
				switch($value['videotype']){
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
				$val_id = $value['videoid'];  // In case of embed.ly, this is not id but it is full URL
				$iframecode .= $val_id.'" width="240" height="135" style="border:0;" allowFullScreen></iframe>';
			}
			
			$field->html[$n] .= '
				<tr>
					<td class="key">
						'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_PREVIEW').'
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
		function fetchVideo_'.$field->name.'() {
			var fieldname = \'custom['.$field->name.']\';
			var url = fieldname+"[url]";
			url = document.forms["adminForm"].elements[url].value;
			var videoID = "";
			var videoType = "";
			
			var _loading_img = "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">";
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html(_loading_img);
			
			if(window.console) window.console.log("Fetching "+url);
			// try youtube
			var myregexp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
			if(url.match(myregexp) != null) {
				videoID = url.match(myregexp)[1];
				videoType = "youtube";
			}
			// try vimeo
			var myregexp = /http:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
			if(url.match(myregexp) != null) {
				videoID = url.match(myregexp)[2];
				videoType = "vimeo";
			}
			// try dailymotion
			var myregexp = /^.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/;
			if(url.match(myregexp) != null) {
				videoID = url.match(myregexp)[4]!== undefined ? url.match(myregexp)[4] : url.match(myregexp)[2];
				videoType = "dailymotion";
			}
			var jsonurl;
			updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
			updateValueTypeId_'.$field->name.'(videoType,videoID);
			if(videoID && videoType){
				if(window.console) window.console.log("Video type: "+videoType);
				if(window.console) window.console.log("Video ID: "+videoID);
				switch(videoType) {
					case "youtube":
						jsonurl = "//gdata.youtube.com/feeds/api/videos/"+videoID+"?v=2&alt=json-in-script&callback=youtubeCallback_'.$field->name.'";
						break;
					case "vimeo":
						jsonurl = "//vimeo.com/api/v2/video/"+videoID+".json?callback=vimeoCallback_'.$field->name.'";
						break;
					case "dailymotion":
						jsonurl = "https://api.dailymotion.com/video/"+videoID+"?fields=description,duration,owner.screenname,thumbnail_60_url,title&callback=dailymotionCallback_'.$field->name.'";
						break;
				}
			}
			else { 
				// try embed.ly
				jsonurl = "http://api.embed.ly/1/oembed?url="+encodeURIComponent(url)+"&key='.$embedly_key.'&callback=embedlyCallback_'.$field->name.'";
			}
			if(url!="") {
				var jsonscript = document.createElement("script");
				jsonscript.setAttribute("type","text/javascript");
				jsonscript.setAttribute("src",jsonurl);
				document.body.appendChild(jsonscript);
			}
			else {
				updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateValueTypeId_'.$field->name.'("","");				
			}
		}
		function youtubeCallback_'.$field->name.'(data){
			updateValueInfo_'.$field->name.'({title: data.entry.title.$t, author: data.entry.author[0].name.$t, duration: data.entry.media$group.yt$duration.seconds, description: data.entry.media$group.media$description.$t, thumb: data.entry.media$group.media$thumbnail[0].url});
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
		}
		function vimeoCallback_'.$field->name.'(data){
			updateValueInfo_'.$field->name.'({title: data[0].title, author: data[0].user_name, duration: data[0].duration, description: data[0].description, thumb: data[0].thumbnail_small});
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
		}
		function dailymotionCallback_'.$field->name.'(data){
			updateValueInfo_'.$field->name.'({title: data.title, author: data["owner.screenname"], duration: data.duration, description: data.description, thumb: data.thumbnail_60_url});
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
		}
		function embedlyCallback_'.$field->name.'(data){
			if(data.type!="error") {
				var myregexp = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if(data.html.match(myregexp) != null) {
					var iframeurl = data.html.match(myregexp)[0];
					var iframecode = \'<iframe class="sharedvideo" src="\'+iframeurl+\'" width="240" height="135" style="border:0;" allowFullScreen></iframe>\';
					updateValueTypeId_'.$field->name.'("embed.ly:"+data.provider_name.toLowerCase(),iframeurl);
					updateValueInfo_'.$field->name.'({title: data.title, author: data.author_name, duration: "", description: data.description, thumb: data.thumbnail_url});
					document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
				}
			}
			else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_UNABLE_TO_PARSE').'"); 
				updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateValueTypeId_'.$field->name.'("","");				
			}
			jQuery("#fcfield_fetching_msg_'.$field->id.'").html("");
		}
		function updateValueTypeId_'.$field->name.'(videoType,videoID){
			var fieldname = \'custom['.$field->name.']\';
			field = fieldname+"[videotype]";
			document.forms["adminForm"].elements[field].value = videoType;
			field = fieldname+"[videoid]";
			document.forms["adminForm"].elements[field].value = videoID;
			if(videoType!="" && videoID!="") {
				var iframecode = \'<iframe class="sharedvideo" src="\';
				switch(videoType){
					case "youtube":
						iframecode += "//www.youtube.com/embed/";
						break;
					case "vimeo":
						iframecode += "//player.vimeo.com/video/";
						break;
					case "dailymotion":
						iframecode += "//www.dailymotion.com/embed/video/";
						break;
				}
				iframecode += videoID + \'" width="240" height="135" style="border:0;" allowFullScreen></iframe>\';
				document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
			}
			else {
				document.getElementById("'.$field->name.'_thumb").innerHTML = "";
			}

		}
		function updateValueInfo_'.$field->name.'(data){
			var fieldname = \'custom['.$field->name.']\';
			field = fieldname+"[title]";
			document.forms["adminForm"].elements[field].value = data.title;
			field = fieldname+"[author]";
			document.forms["adminForm"].elements[field].value = data.author;
			field = fieldname+"[duration]";
			document.forms["adminForm"].elements[field].value = data.duration;
			field = fieldname+"[description]";
			document.forms["adminForm"].elements[field].value = data.description;
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
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
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
			if ( empty($value['videotype']) || empty($value['videoid']) ) continue;
			
			// some parameter shortcuts
			$display_title = $field->parameters->get('display_title',1) ;
			$display_author = $field->parameters->get('display_author',0) ;
			$display_duration = $field->parameters->get('display_duration',0) ;
			$display_description = $field->parameters->get('display_description',0) ;
			
			$pretext = $field->parameters->get('pretext','') ;
			$posttext = $field->parameters->get('posttext','') ;
			
			$headinglevel = $field->parameters->get('headinglevel',3) ;
			$width = $field->parameters->get('width',480) ;
			$height = $field->parameters->get('height',270) ;
			$autostart = $field->parameters->get('autostart',0) ;
			
			// generate html output
			$field->{$prop} .= $pretext;
			$field->{$prop} .= '<iframe class="sharedvideo" src="';
			switch($value['videotype']){
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
			$val_id = $value['videoid'];  // In case of embed.ly, this is not id but it is full URL
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
			
			$url = flexicontent_html::dataFilter($v['url'], 0, 'URL', 0);  // Clean bad text/html
			
			// Skip empty value, but if in group increment the value position
			if (empty($url))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}
			
			$newpost[$new] = array();
			$newpost[$new]['url'] = $url;
			
			// Validate other value properties
			$newpost[$new]['videotype'] = flexicontent_html::dataFilter(@$v['videotype'], 0, 'STRING', 0);
			$newpost[$new]['videoid'] = flexicontent_html::dataFilter(@$v['videoid'], 0, 'STRING', 0);
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
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description','videotype'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('url'), $search_properties=array('title','author','description','videotype'), $properties_spacer=' ', $filter_func=null);
		return true;
	}

}