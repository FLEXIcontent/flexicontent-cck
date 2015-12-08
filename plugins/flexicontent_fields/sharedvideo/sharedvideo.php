<?php
defined('_JEXEC') or die('Restricted access');

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsSharedvideo extends FCField {
	static $field_types = array('sharedvideo');

	// ***********
	// CONSTRUCTOR
	// ***********

	function plgFlexicontent_fieldsSharedvideo(&$subject, $params) {
		parent::__construct($subject, $params);
		JPlugin::loadLanguage('plg_flexicontent_fields_sharedvideo', JPATH_ADMINISTRATOR);
	}

	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item) {
		// displays the field when editing content item
		$field -> label = JText::_($field -> label);
		if (!in_array($field -> field_type, self::$field_types)) return;

		// initialize framework objects and other variables
		$document = JFactory::getDocument();

		$use_ingroup 				= $field -> parameters -> get('use_ingroup', 0);
		$value_classes 				= 'fcfieldval_container valuebox fcfieldval_container_' . $field -> id;
		$multiple 					= $use_ingroup || (int)$field -> parameters -> get('allow_multiple', 0);
		$add_position 				= (int)$field -> parameters -> get('add_position', 3);

		// some parameter shortcuts
		$required 					= $field -> parameters -> get('required', 0);
		$required 					= $required ? ' required' : '';
		$embedly_key 				= $field -> parameters -> get('embedly_key', '');
		$autostart 					= $field -> parameters -> get('autostart', 0);
		$autostart 					= $autostart ? 'true' : 'false';
		$force_ssl 					= $field -> parameters -> get('force_ssl', 1);
		$force_ssl 					= $force_ssl ? 'true' : 'false';
		$display_title_form 		= $field -> parameters -> get('display_title_form', 1);
		$display_author_form 		= $field -> parameters -> get('display_author_form', 1);
		$display_description_form 	= $field -> parameters -> get('display_description_form', 1);

		// Initialise value property
		$values = $this -> parseValues($field -> value);
		if (empty($values)) {
			$values = array();
			$values[0]['url'] = '';
			$values[0]['embed_url'] = '';
			$values[0]['title'] = '';
			$values[0]['author'] = '';
			$values[0]['description'] = '';
			$values[0]['thumb'] = '';
		}
		$value = $values[0];

		$field -> html = array();
		foreach ($values as $n => $value) {
			// backward compatibility check:
			if (isset($value['videotype']) && isset($value['videoid'])) {
				switch($value['videotype']) {
					case 'youtube' :
						$value['embed_url'] = '//www.youtube.com/embed/' . $value['videoid'];
						break;

					case 'vimeo' :
						$value['embed_url'] = '//player.vimeo.com/video/' . $value['videoid'];
						break;

					case 'dailymotion' :
						$value['embed_url'] = '//www.dailymotion.com/embed/video/' . $value['videoid'];
						break;

					default :
						$value['embed_url'] = $value['videoid'];
						break;
				}
			}

			if (!isset($value['url']))				$value['url'] = '';
			if (!isset($value['embed_url']))		$value['embed_url'] = '';
			if (!isset($value['title']))			$value['title'] = '';
			if (!isset($value['author']))			$value['author'] = '';
			if (!isset($value['description']))		$value['description'] = '';
			if (!isset($value['thumb']))			$value['thumb'] = '';

			$field -> html[$n] = '
			<table class="fc-form-tbl fcinner fc-sharedvideo-field-tbl">
			<tbody>
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_URL') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval ' . $required . '" name="custom[' . $field -> name . '][url]" value="' . $value['url'] . '" size="60" />
						<input class="fcfield-button" type="button" value="' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_FETCH') . '" onclick="fetchVideo_' . $field -> name . '();" />
						<span id="fcfield_fetching_msg_' . $field -> id . '"></span>
						<input type="hidden" name="custom[' . $field -> name . '][embed_url]" value="' . $value['embed_url'] . '" />
					</td>
				</tr>' . ($display_title_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_TITLE') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" name="custom[' . $field -> name . '][title]" value="' . $value['title'] . '" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom[' . $field -> name . '][title]" value="' . $value['title'] . '" size="60" />') . ($display_author_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_AUTHOR') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" name="custom[' . $field -> name . '][author]" value="' . $value['author'] . '" size="60" />
					</td>
				</tr>' : '
				<input type="hidden" name="custom[' . $field -> name . '][author]" value="' . $value['author'] . '" size="60" />') . ($display_description_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DESCRIPTION') . '</span></td>
					<td>
						<textarea class="fcfield_textareaval" name="custom[' . $field -> name . '][description]" rows="7" cols="50">' . $value['description'] . '</textarea>
					</td>
				</tr>' : '
				<textarea style="display:none;" name="custom[' . $field -> name . '][description]" rows="7" cols="50">' . $value['description'] . '</textarea>') . '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_PREVIEW') . '</span>
					</td>
					<td>
						<div id="' . $field -> name . '_preview">
							<iframe class="sharedvideo" src="' . $value['embed_url'] . '" style="width: 240px; height: 140px; border: none;" scrolling="no" seamless="seamless" allowFullScreen></iframe>
						</div>
						<input type="hidden" name="custom[' . $field -> name . '][thumb]" value="' . $value['thumb'] . '" />
					</td>
				</tr>
			</tbody></table>';
		}

		$js = "";
		$css = "";

		$js = '
		function fetchVideo_' . $field -> name . '() {
			var fieldname = \'custom[' . $field -> name . ']\';
			var url = fieldname+"[url]";
			url = document.forms["adminForm"].elements[url].value;
			var videoID = "";
			var jsonurl = "";
			
			var _loading_img = "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">";
			jQuery("#fcfield_fetching_msg_' . $field -> id . '").html(_loading_img);
			
			updateValueInfo_' . $field -> name . '({title:"", author:"", description:"", thumb:""});
			document.getElementById("' . $field -> name . '_preview").innerHTML = "";
			
			// try embed.ly
			jsonurl = "https://api.embed.ly/1/oembed?url="+encodeURIComponent(url)+"&key=' . $embedly_key . '&maxwidth=1280&wmode=transparent&secure=' . $force_ssl . '&autoplay=' . $autostart . '&callback=embedlyCallback_' . $field -> name . '";
			var jsonscript = document.createElement("script");
			jsonscript.setAttribute("type","text/javascript");
			jsonscript.setAttribute("src",jsonurl);
			document.body.appendChild(jsonscript);
		}
		function embedlyCallback_' . $field -> name . '(data){
			if(data.type!="error") {
				var myregexp = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				if(data.html.match(myregexp) != null) {
					var iframeurl = data.html.match(myregexp)[0];
					var iframecode = \'<iframe class="sharedvideo" src="\'+iframeurl+\'" style="width: 240px; height: 140px; border: none;" scrolling="no" seamless="seamless" allowFullScreen></iframe>\';
					document.getElementById("' . $field -> name . '_preview").innerHTML = iframecode;
					updateValueInfo_' . $field -> name . '({title: data.title, author: data.author_name, duration: "", description: data.description, thumb: data.thumbnail_url});
				}
			}
			else {
				alert("' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_UNABLE_TO_PARSE') . '"); 
				updateValueInfo_' . $field -> name . '({title:"", author:"", duration:"", description:"", thumb:""});
			}
			jQuery("#fcfield_fetching_msg_' . $field -> id . '").html("");
		}
		function updateValueInfo_' . $field -> name . '(data){
			var fieldname = \'custom[' . $field -> name . ']\';
			field = fieldname+"[title]";
			document.forms["adminForm"].elements[field].value = data.title;
			field = fieldname+"[author]";
			document.forms["adminForm"].elements[field].value = data.author;
			field = fieldname+"[description]";
			document.forms["adminForm"].elements[field].value = data.description;
			field = fieldname+"[thumb]";
			document.forms["adminForm"].elements[field].value = data.thumb;
		}
		';

		if ($js) $document -> addScriptDeclaration($js);
		if ($css) $document -> addStyleDeclaration($css);

		if ($use_ingroup) {// do not convert the array to string if field is in a group
		} 
		else if ($multiple) {// handle multiple records
			$field -> html = !count($field -> html) ? '' : '<li class="' . $value_classes . '">' . implode('</li><li class="' . $value_classes . '">', $field -> html) . '</li>';
			$field -> html = '<ul class="fcfield-sortables" id="sortables_' . $field -> id . '">' . $field -> html . '</ul>';
			if (!$add_position) $field -> html .= '<span class="fcfield-addvalue" onclick="addField' . $field -> id . '(this);" title="' . JText::_('FLEXI_ADD_TO_BOTTOM') . '"></span>';
		} 
		else {// handle single values
			$field -> html = '<div class="fcfieldval_container valuebox fcfieldval_container_' . $field -> id . '">' . $field -> html[0] . '</div>';
		}
	}

	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display') {
		// displays the field in the frontend

		$field -> label = JText::_($field -> label);
		if (!in_array($field -> field_type, self::$field_types))
			return;

		// Get field values
		$values = $values ? $values : $field -> value;

		$field -> {$prop} = '';
		foreach ($values as $value) {
			if (empty($value)) continue;

			$value = unserialize($value);
			if (empty($value['videotype']) || empty($value['videoid'])) continue;

			// some parameter shortcuts
			$display_title 			= $field -> parameters -> get('display_title', 1);
			$display_author 		= $field -> parameters -> get('display_author', 0);
			$display_description 	= $field -> parameters -> get('display_description', 0);

			$pretext 				= $field -> parameters -> get('pretext', '');
			$posttext 				= $field -> parameters -> get('posttext', '');

			$headinglevel 			= $field -> parameters -> get('headinglevel', 3);
			$width 					= $field -> parameters -> get('width', 960);
			$height 				= $field -> parameters -> get('height', 540);
			$autostart 				= $field -> parameters -> get('autostart', 0);

			// generate html output
			$field -> {$prop} .= $pretext;
			$field -> {$prop} .= '<div class="videoplayer"><iframe class="sharedvideo" src="';

			// backward compatibility
			if (!empty($value['embed_url'])) {
				$embed_url = $value['embed_url'];
			} else {
				switch($value['videotype']) {
					case 'youtube' :
						$embed_url = '//www.youtube.com/embed/' . $value['videoid'] . '?autoplay=' . $autostart .'&rel=0&modestbranding=1';
						break;
					case 'vimeo' :
						$embed_url = '//player.vimeo.com/video/' . $value['videoid'] . '?autoplay=' . $autostart;
						break;
					case 'dailymotion' :
						$embed_url = '//www.dailymotion.com/embed/video/' . $value['videoid'] . '?autoplay=' . $autostart . '&related=0&logo=0';
						break;
					default:
						$embed_url = $value['videoid'];
						break;
				}
			}
			$field -> {$prop} .= $embed_url . '" style="border: none;" scrolling="no" seamless="seamless" allowFullScreen></iframe></div>' . ($display_title && !empty($value['title']) ? '<h' . $headinglevel . '>' . $value['title'] . '</h' . $headinglevel . '>' : '') . ($display_author && !empty($value['author']) ? '<div class="author">' . $value['author'] . '</div>' : '') . ($display_description && !empty($value['description']) ? '<div class="description">' . $value['description'] . '</div>' : '');

			$field -> {$prop} .= $posttext;
		}
	}

	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField(&$field, &$post, &$file, &$item) {
		if (!in_array($field -> field_type, self::$field_types))
			return;

		$use_ingroup = $field -> parameters -> get('use_ingroup', 0);
		if (!is_array($post) && !strlen($post) && !$use_ingroup)
			return;

		$is_importcsv = JRequest::getVar('task') == 'importcsv';

		// Currently post is an array of properties, TODO: make field multi-value
		if (empty($post))
			$post = array();
		else if (!isset($post[0]))
			$post = array($post);

		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v) {
			// support for basic CSV import / export
			if ($is_importcsv && !is_array($v)) {
				if (@unserialize($v) !== false || $v === 'b:0;') {// support for exported serialized data)
					$v = unserialize($v);
				} else {
					$v = array('url' => $v);
				}
			}

			// **************************************************************
			// Validate data, skipping values that are empty after validation
			// **************************************************************

			$url = flexicontent_html::dataFilter($v['url'], 4000, 'URL', 0);
			// Clean bad text/html

			// Skip empty value, but if in group increment the value position
			if (empty($url)) {
				if ($use_ingroup)
					$newpost[$new++] = null;
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
		foreach ($post as $i => $v) {
			if ($v !== null)
				$post[$i] = serialize($v);
		}
	}

	// *************************
	// SEARCH / INDEXING METHODS
	// *************************

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if (!in_array($field -> field_type, self::$field_types))
			return;
		if (!$field -> isadvsearch && !$field -> isadvfilter)
			return;

		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties = array('url'), $search_properties = array('title', 'author', 'description', 'videotype'), $properties_spacer = ' ', $filter_func = null);
		return true;
	}

	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item) {
		if (!in_array($field -> field_type, self::$field_types))
			return;
		if (!$field -> issearch)
			return;

		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties = array('url'), $search_properties = array('title', 'author', 'description', 'videotype'), $properties_spacer = ' ', $filter_func = null);
		return true;
	}

}
