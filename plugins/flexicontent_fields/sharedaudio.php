<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsSharedaudio extends JPlugin
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
			$value['audiotype'] = '';
			$value['audioid'] = '';
			$value['title'] = '';
			$value['author'] = '';
			$value['description'] = '';
		}
		
		$field->html  = '';
		$field->html .= '<table class="admintable" border="0" cellspacing="0" cellpadding="5">';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_URL').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][url]" value="'.$value['url'].'" size="60" '.$required.' /> <input class="fcfield-button" type="button" value="'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_FETCH').'" onclick="fetchAudio_'.$field->name.'();"></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_TYPE').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][audiotype]" value="'.$value['audiotype'].'" size="10" readonly="readonly" style="background-color:#eee" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUDIO_ID').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][audioid]" value="'.$value['audioid'].'" size="15" readonly="readonly" style="background-color:#eee" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_TITLE').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][title]" value="'.$value['title'].'" size="60" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_AUTHOR').'</td><td><input type="text" class="fcfield_textval" name="custom['.$field->name.'][author]" value="'.$value['author'].'" size="60" /></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_DESCRIPTION').'</td><td><textarea class="fcfield_textareaval" name="custom['.$field->name.'][description]" rows="7" cols="50">'.$value['description'].'</textarea></td></tr>';
		$field->html .= '<tr><td class="key" align="right">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_PREVIEW').'</td><td><div id="'.$field->name.'_thumb">';
		if($value['audiotype']!="" && $value['audioid']!="") {
			$iframecode = '<iframe class="sharedaudio" src="'.$value['audioid'].'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
			$field->html .= $iframecode;
		}
		$field->html	.= '</div></td></tr>';
		$field->html	.= '</table>';
		$field->html 	.= '
		<script type="text/javascript">
		function fetchAudio_'.$field->name.'() {
			var fieldname = \'custom['.$field->name.']\';
			var url = fieldname+"[url]";
			url = document.forms["adminForm"].elements[url].value;
			var audioID = false;
			var audioType = false;
			console.log("Fetching "+url);
			var jsonurl;
			updateAudioInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
			updateAudioTypeId_'.$field->name.'(audioType,audioID);
			// try embed.ly
			jsonurl = "http://api.embed.ly/1/oembed?url="+encodeURIComponent(url)+"&key='.$embedly_key.'&callback=embedlyCallback_'.$field->name.'";
			if(url!="") {
				var jsonscript = document.createElement("script");
				jsonscript.setAttribute("type","text/javascript");
				jsonscript.setAttribute("src",jsonurl);
				document.body.appendChild(jsonscript);
			}
			else {
				updateAudioInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateAudioTypeId_'.$field->name.'("","");				
			}
		}
		function embedlyCallback_'.$field->name.'(data){
			if(data.type!="error") {
				var myregexp = /(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if(data.html.match(myregexp) != null) {
					var iframeurl = data.html.match(myregexp)[0];
					var iframecode = \'<iframe class="sharedaudio" src="\'+iframeurl+\'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>\';
					updateAudioTypeId_'.$field->name.'("embed.ly:"+data.provider_name.toLowerCase(),iframeurl);
					updateAudioInfo_'.$field->name.'({title: data.title, author: data.author_name, description: data.description, thumb: data.thumbnail_url});
					document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
				}
			}
			else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDAUDIO_UNABLE_TO_PARSE').'"); 
				updateAudioInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""});
				updateAudioTypeId_'.$field->name.'("","");				
			}
		}
		function updateAudioTypeId_'.$field->name.'(audioType,audioID){
			var fieldname = \'custom['.$field->name.']\';
			field = fieldname+"[audiotype]";
			document.forms["adminForm"].elements[field].value = audioType;
			field = fieldname+"[audioid]";
			document.forms["adminForm"].elements[field].value = audioID;
			if(audioType!="" && audioID!="") {
				var iframecode = \'<iframe class="sharedaudio" src="\';
				switch(audioType){
					case "youtube":
						iframecode += "//www.youtube.com/embed/";
						break;
					case "vimeo":
						iframecode += "//player.vimeo.com/audio/";
						break;
					case "dailymotion":
						iframecode += "//www.dailymotion.com/embed/audio/";
						break;
				}
				iframecode += audioID + \'" width="240" height="135" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>\';
				document.getElementById("'.$field->name.'_thumb").innerHTML = iframecode;
			}
			else {
				document.getElementById("'.$field->name.'_thumb").innerHTML = "";
			}

		}
		function updateAudioInfo_'.$field->name.'(data){
			var fieldname = \'custom['.$field->name.']\';
			field = fieldname+"[title]";
			document.forms["adminForm"].elements[field].value = data.title;
			field = fieldname+"[author]";
			document.forms["adminForm"].elements[field].value = data.author;
			/*field = fieldname+"[duration]";
			document.forms["adminForm"].elements[field].value = data.duration;*/
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
			if ( empty($value['audioid']) ) continue;
			
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
			$field->{$prop} .= '<iframe class="sharedaudio" src="'.$value['audioid'].'?autoplay='.$autostart.'" width="'.$width.'" height="'.$height.'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
			if($display_title==1 && $value['title']!='') $field->{$prop} .= '<h'.$headinglevel.'>'.$value['title'].'</h'.$headinglevel.'>';
			if($display_author==1 && $value['author']!='') $field->{$prop} .= '<div class="author">'.$value['author'].'</div>';
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
		if(!$post) return;
		
		if($post['url'] != '') {
			// create the fulltext search index
			$searchindex = $post['title'].' '.$post['author'].' '.$post['description'].' '.$post['title'].' '.$post['url'].' '.$post['audiotype'].' '.$post['audioid'].'|';
			$field->search = $searchindex;
			$post = serialize($post);
		}
		else {
			unset($post);
			return;
		}
	}
}