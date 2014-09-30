<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsSharedvideo extends JPlugin
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
		// execute the code only if the field type match the plugin type
		$field->label = JText::_($field->label);
		if ( !in_array($field->field_type, self::$field_types) ) return;

		// some parameter shortcuts
		$required = $field->parameters->get('required',0);
		$required = $required ? ' required' : '';
		$embedly_key = $field->parameters->get('embedly_key','') ;
		
		// get stored field value
		if ( isset($field->value[0]) ) $value = unserialize($field->value[0]);
		else {
			$value['url'] = '';
			$value['videotype'] = '';
			$value['videoid'] = '';
			$value['title'] = '';
			$value['author'] = '';
			$value['duration'] = '';
			$value['description'] = '';
		}
		
		$field->html  = '';
		$field->html .= '<table class="admintable" border="0" cellspacing="0" cellpadding="5">';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_URL').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][url]" value="'.$value['url'].'" size="60" '.$required.' /> <input class="fcfield-button" type="button" value="'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_FETCH').'" onclick="fetchVideo_'.$field->name.'();" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_TYPE').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][videotype]" value="'.$value['videotype'].'" size="10" readonly="readonly" style="background-color:#eee" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_ID').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][videoid]" value="'.$value['videoid'].'" size="15" readonly="readonly" style="background-color:#eee" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_TITLE').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_AUTHOR').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DURATION').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][duration]" value="'.$value['duration'].'" size="10" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DESCRIPTION').'</td><td><textarea class="fcfield_textareaval" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_PREVIEW').'</td><td><div id="'.$field->name.'_thumb">';
		if($value['videotype']!="" && $value['videoid']!="") {
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
			}
			$iframecode .= $value['videoid'].'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
			$field->html .= $iframecode;
		}
		$field->html	.= '</div></td></tr>';
		$field->html	.= '</table>';
		$field->html 	.= '
		<script type="text/javascript">
		function fetchVideo_'.$field->name.'() {
			var fieldname = \'custom['.$field->name.']\';
			var url = fieldname+"[url]";
			url = document.forms["adminForm"].elements[url].value;
			var videoID = false;
			var videoType = false;
			console.log("Fetching "+url);
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
			updateVideoInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
			updateVideoTypeId_'.$field->name.'(videoType,videoID);
			if(videoID && videoType){
				console.log("Video type: "+videoType);
				console.log("Video ID: "+videoID);
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
				updateVideoInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateVideoTypeId_'.$field->name.'("","");				
			}
		}
		function youtubeCallback_'.$field->name.'(data){
			updateVideoInfo_'.$field->name.'({title: data.entry.title.$t, author: data.entry.author[0].name.$t, duration: data.entry.media$group.yt$duration.seconds, description: data.entry.media$group.media$description.$t, thumb: data.entry.media$group.media$thumbnail[0].url});
		}
		function vimeoCallback_'.$field->name.'(data){
			updateVideoInfo_'.$field->name.'({title: data[0].title, author: data[0].user_name, duration: data[0].duration, description: data[0].description, thumb: data[0].thumbnail_small});
		}
		function dailymotionCallback_'.$field->name.'(data){
			updateVideoInfo_'.$field->name.'({title: data.title, author: data["owner.screenname"], duration: data.duration, description: data.description, thumb: data.thumbnail_60_url});
		}
		function embedlyCallback_'.$field->name.'(data){
			if(data.type!="error") {
				var myregexp = /(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if(data.html.match(myregexp) != null) {
					var iframeurl = data.html.match(myregexp)[0];
					var iframecode = \'<iframe class="sharedvideo" src="\'+iframeurl+\'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>\';
					updateVideoTypeId_'.$field->name.'("embed.ly:"+data.provider_name.toLowerCase(),iframeurl);
					updateVideoInfo_'.$field->name.'({title: data.title, author: data.author_name, duration: "", description: data.description, thumb: data.thumbnail_url});
					document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
				}
			}
			else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_UNABLE_TO_PARSE').'"); 
				updateVideoInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateVideoTypeId_'.$field->name.'("","");				
			}
		}
		function updateVideoTypeId_'.$field->name.'(videoType,videoID){
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
				iframecode += videoID + \'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>\';
				document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
			}
			else {
				document.getElementById("'.$field->name.'_thumb").innerHTML = "";
			}

		}
		function updateVideoInfo_'.$field->name.'(data){
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
		</script>';

	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// displays the field in the frontend
		
		// execute the code only if the field type match the plugin type
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
			}
			$field->{$prop} .= $value['videoid'].'?autoplay='.$autostart.$_show_related.$_show_srvlogo.'" width="'.$width.'" height="'.$height.'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
			if($display_title==1 && $value['title']!='') $field->{$prop} .= '<h'.$headinglevel.'>'.$value['title'].'</h'.$headinglevel.'>';
			if($display_author==1 && $value['author']!='') $field->{$prop} .= '<div class="author">'.$value['author'].'</div>';
			if($display_duration==1 && $value['duration']!='') {
				if($value['duration']>=3600) $h = intval($value['duration']/3600);
				if($value['duration']>=60) $m = intval((($value['duration']/60) - $h*60));
				$s = $value['duration'] - $m*60 -$h*3600;
				if($h>0) $h .= ":";
				$m = str_pad($m,2,'0',STR_PAD_LEFT).':';
				$s = str_pad($s,2,'0',STR_PAD_LEFT);
				$field->{$prop} .= '<div class="duration">'.$h.$m.$s.'</div>';
			}
			if($display_description==1 && $value['description']!='') $field->{$prop} .= '<div class="description">'.$value['description'].'</div>';
			$field->{$prop} .= $posttext;
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Check if field has posted data
		if ( empty($post) ) return;
		
		// Serialize multi-property data before storing them into the DB
		if( !empty($post['url']) ) {
			$post = serialize($post);
		}
		else {
			unset($post);
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