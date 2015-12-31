<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$_MEDIA_ = 'AUDIO';
		
		
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
		
		$display_datatype_form = $field->parameters->get('display_datatype_form', 0);
		$display_dataid_form   = $field->parameters->get('display_dataid_form', 0);
		
		$display_title_form       = $field->parameters->get('display_title_form', 1);
		$display_author_form      = $field->parameters->get('display_author_form', 1);
		$display_duration_form    = $field->parameters->get('display_duration_form', 0);
		
		$display_description_form = $field->parameters->get('display_description_form', 1);
		$display_edit_size_form   = $field->parameters->get('display_edit_size_form', 1);
		
		// Initialise value property
		if (empty($field->value)) 
		{
			$field->value = array();
			$field->value[0]['url'] = '';
			$field->value[0]['embed_url'] = '';
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
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				//var newField  = lastField.clone();
				
				// First, generate new field as HTML
				var newField_HTML = lastField.prop('outerHTML');
				
				// replace all field names and ids
				newField_HTML = newField_HTML.replace(/" . str_replace(array('[', ']'), array('\[', '\]'), $fieldname) . "\[(\d*)\]/g, '" . $fieldname . "[' + uniqueRowNum".$field->id." + ']');
				newField_HTML = newField_HTML.replace(/" . $elementid . "_(\d*)/g, '" . $elementid . "_' + uniqueRowNum".$field->id.");
				
				// Convert HTML to DOM element
				var newField = jQuery(newField_HTML);
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
				updateValueInfo_".$field->name."({title:'', author:'', description:'', thumb:'', embed_url:''}, element_id);
				
				jQuery('#' + element_id + '_preview').html('');
				jQuery('#' + element_id + '_url').val('');
				jQuery('#' + element_id + '_height').val('');
				jQuery('#' + element_id + '_width').val('');
				jQuery('#' + element_id + '_datatype').val('');
				jQuery('#' + element_id + '_dataid').val('');
				jQuery('#' + element_id + '_title, ' + '#' + element_id + '_author, ' + '#' + element_id + '_description, ' + '#' + element_id + '_datatype, ' + '#' + element_id + '_dataid, ' + '#' + element_id + '_preview, ' + '#' + element_id + '_width, ' + '#' + element_id + '_height').parents('tr').hide('fast');
				jQuery('#fcfield_fetching_msg_' + element_id).html('');
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({'html': true,'container': newField});
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="fcfield-delvalue'.(JComponentHelper::getParams('com_flexicontent')->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle'.(JComponentHelper::getParams('com_flexicontent')->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before'.(JComponentHelper::getParams('com_flexicontent')->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after'.(JComponentHelper::getParams('com_flexicontent')->get('form_font_icons', 1) ? ' fcfont-icon' : '').'"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		
		if( empty($embedly_key) ) 
		{
			$field->html[$n] = '<div class="alert alert-warning">'. JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_EMBEDLY_KEY_REQUIRED').'</div>';
		}
		
		else foreach ($field->value as $n => $value) 
		{
			$value = unserialize($value);
			$value['datatype'] = @ $value['audiotype'];
			$value['dataid']   = @ $value['audioid'];
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			// only for backward compatibility:
			if (!empty($value['datatype']) && !empty($value['dataid']))
			{
				$content_id = $value['dataid'];
				switch($value['datatype'])
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
			$iframecode = '<iframe class="sharedmedia seamless" src="'.($value['embed_url'] ? $value['embed_url'] : 'about:blank').'" style="width: 240px; height: 140px; border: none; overflow:hidden;" allowFullScreen></iframe>';

			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['embed_url']))   $value['embed_url'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
			if (!isset($value['duration']))    $value['duration'] = '';
			if (!isset($value['height']))      $value['height'] = '';
			if (!isset($value['width']))       $value['width'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			if (!isset($value['thumb']))       $value['thumb'] = '';
			

			$html_field = '
			<table class="fc-form-tbl fcinner fc-sharedmedia-field-tbl" data-row="'.$n.'">
			<tbody>
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_MEDIA_URL') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval' . $required . '" id="'.$elementid_n.'_url" name="'.$fieldname_n.'[url]" value="' . $value['url'] . '" size="60" />
						<input class="fcfield-button" type="button" value="' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_FETCH') . '" onclick="fetchData_'.$field->name.'(\''.$elementid_n.'\');" />
						'.($use_ingroup ? '' : $move2).'
						'.($use_ingroup ? '' : $remove_button).'
						'.($use_ingroup || !$add_position ? '' : $add_here).'
						<input type="hidden" id="'.$elementid_n.'_embed_url" name="'.$fieldname_n.'[embed_url]" value="' . $value['embed_url'] . '" />
					</td>
				</tr>
				<tr>
					<td colspan="2" style="padding:0"><span id="fcfield_fetching_msg_'.$elementid_n.'"></span></td>
				</tr>'
			.($display_datatype_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_MEDIA_TYPE').'</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_datatype" name="'.$fieldname_n.'[datatype]" value="'.$value['datatype'].'" size="10" readonly="readonly" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_datatype" name="'.$fieldname_n.'[datatype]" value="'.$value['datatype'].'" style="background-color:#eee" /></td></tr>')
			.($display_dataid_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_MEDIA_ID').'</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_dataid" name="'.$fieldname_n.'[dataid]" value="'.$value['dataid'].'" size="15" readonly="readonly" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_dataid" name="'.$fieldname_n .'[dataid]" value="'.$value['dataid'].'" style="background-color:#eee" /></td></tr>')
			.($display_title_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_TITLE') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="' . $value['title'] . '" size="60" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" value="' . $value['title'] . '" /></td></tr>')
			.($display_author_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_AUTHOR') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="' . $value['author'] . '" size="60" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_author" name="'.$fieldname_n.'[author]" value="' . $value['author'] . '" /></td></tr>')
			.($display_duration_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_DURATION') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="'.$value['duration'].'" size="10" readonly="readonly" />
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_duration" name="'.$fieldname_n.'[duration]" value="' . $value['duration'] . '" /></td></tr>')
			.($display_edit_size_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SIZE_WIDTH') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_width" name="'.$fieldname_n.'[width]" value="' . $value['width'] . '" size="60" />
					</td>
				</tr> 
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_SIZE_HEIGHT') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="'.$elementid_n.'_height" name="'.$fieldname_n.'[height]" value="' . $value['height'] . '" size="60" />
					</td>
				</tr>' : '
				<tr style="display:none;">
					<td colspan="2">
						<input type="hidden" id="'.$elementid_n.'_width" name="'.$fieldname_n.'[width]" value="' . $value['width'] . '" />
						<input type="hidden" id="'.$elementid_n.'_height" name="'.$fieldname_n.'[height]" value="' . $value['height'] . '" />
					</td>
				</tr>
				')
			.($display_description_form ? '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_DESCRIPTION') . '</span></td>
					<td>
						<textarea class="fcfield_textareaval" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" rows="7" cols="50">' . $value['description'] . '</textarea>
					</td>
				</tr>' : '
				<tr style="display:none;"><td colspan="2"><input type="hidden" id="'.$elementid_n.'_description" name="'.$fieldname_n.'[description]" value="' . $value['description'] . '" /></td></tr>')
				. '
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_PREVIEW') . '</span></td>
					<td>
						<div id="'.$elementid_n.'_preview">
							'.$iframecode.'
						</div>
						<input type="hidden" id="'.$elementid_n.'_thumb" name="'.$fieldname_n.'[thumb]" value="' . $value['thumb'] . '" />
					</td>
				</tr>
			</tbody>
			</table>
			';
			$field->html[$n] = $html_field;
		}
		
		$js = "";
		$css = "";
		
		$js = '
		var fc_dataID_'.$field->name.';
		var fc_elemID_'.$field->name.';
		
		function fetchData_'.$field->name.'(element_id) {
			element_id = typeof element_id === "undefined" || !element_id  ?  "'.$elementid.'_0" : element_id;
			
			var url = jQuery("#"+element_id+"_url").val();
			var dataID = "";
			var dataType = "";
			
			var _loading_img = "<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">";
			jQuery("#fcfield_fetching_msg_"+element_id).html(_loading_img);
			
			'.($debug_to_console ? 'window.console.log("Fetching "+url);' : '').'
			
			if ('.$use_native_apis.') {
				// try youtube
				var myregexp = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
				if(url.match(myregexp) != null) {
					dataID = url.match(myregexp)[1];
					dataType = "youtube";
				}
				
				// Try vimeo
				var myregexp = /https?:\/\/(www\.)?vimeo.com\/(\d+)($|\/)/;
				if(url.match(myregexp) != null) {
					dataID = url.match(myregexp)[2];
					dataType = "vimeo";
				}
				
				// Try dailymotion
				var myregexp = /^.+dailymotion.com\/(video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/;
				if(url.match(myregexp) != null) {
					dataID = url.match(myregexp)[4]!== undefined ? url.match(myregexp)[4] : url.match(myregexp)[2];
					dataType = "dailymotion";
				}
			}
			
			fc_dataID_'.$field->name.' = dataID;
			fc_elemID_'.$field->name.' = element_id;
			
			// Clear existing data
			updateValueInfo_'.$field->name.'({title:"", author:"", duration:"", description:"", thumb:""}, element_id);
			updateValueTypeId_'.$field->name.'("", "", element_id);
			
			if (url=="") return;  // URL field is empty
			
			// Create AJAX url
			var ajax_url;
			var data_type;
			if (dataID && dataType) {
				'.($debug_to_console ? 'window.console.log("Media type: "+dataType);' : '').'
				'.($debug_to_console ? 'window.console.log("Media ID: "+dataID);' : '').'
				switch(dataType)
				{
					case "youtube"    : ajax_url = "https://www.googleapis.com/youtube/v3/videos?id="+dataID+"&key='.$youtube_key.'&part=snippet,contentDetails,statistics,status";/*&callback=youtubeCallback_'.$field->name.'";*/  break;
					case "vimeo"      : ajax_url = "//vimeo.com/api/v2/video/"+dataID+".json";/*?callback=vimeoCallback_'.$field->name.'";*/  break;
					case "dailymotion": ajax_url = "https://api.dailymotion.com/video/"+dataID+"?fields=description,duration,owner.screenname,thumbnail_60_url,title";/*&callback=dailymotionCallback_'.$field->name.'";*/  break;
				}
				data_type = "json";
			}
			else {
				// try embed.ly
				ajax_url = "https://api.embed.ly/1/oembed?url="+encodeURIComponent(url)+"&key='.$embedly_key.'&maxwidth=1280&wmode=transparent&secure='.$force_ssl.'&autoplay='.$autostart.'&callback=_cbfunc_'.$field->name.'";
				data_type = "html";
			}
			
			// Make AJAX call
			jQuery.ajax({
				url: ajax_url,
				dataType: data_type,
				success: function(data) {
					'.($debug_to_console ? 'window.console.log("Received Server response");' : '').'
					var response;
					try {
						if (data_type=="html") 	data = data.replace(/_cbfunc_'.$field->name.'\(/, "").replace(/\)?;?$/, "");
						response = typeof data !== "object" ? jQuery.parseJSON( data ) : data;
						'.($debug_to_console ? 'window.console.log("Calling callback function on data:");' : '').'
						'.($debug_to_console ? 'window.console.log(response);' : '').'
						if (dataID && dataType)
						{
							switch(dataType)
							{
								case "youtube"     : youtubeCallback_'.$field->name.'(response, dataID, element_id);  break;
								case "vimeo"       : vimeoCallback_'.$field->name.'(response, dataID, element_id);  break;
								case "dailymotion" : dailymotionCallback_'.$field->name.'(response, dataID, element_id);  break;
							}
						}
						else {
							embedlyCallback_'.$field->name.'(response, element_id);
						}
					} catch(err) {
						jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">Failed to parse response</span>");
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
					var errorText = typeof response !== "object" ? response : (dataID && (dataType=="dailymotion" || dataType=="youtube")  ? response.error.message : response.error_message);
					if (dataType=="youtube") errorText += " Reason: "  +response.error.errors[0].reason;
					//document.getElementById("'.$field->name.'_preview").innerHTML = "<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>";
					jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				}
			});
		}
		
		
		function youtubeDurationToSeconds_'.$field->name.'(duration)
		{
		  var match = duration.match(/PT(\d+H)?(\d+M)?(\d+S)?/);
		  var hours = (parseInt(match[1]) || 0);
		  var minutes = (parseInt(match[2]) || 0);
		  var seconds = (parseInt(match[3]) || 0);
			return hours * 3600 + minutes * 60 + seconds;
		}
		
		
		function youtubeCallback_'.$field->name.'(data, dataID, element_id)
		{
			dataID = typeof dataID === "undefined" || !dataID  ?  fc_dataID_'.$field->name.' : dataID;
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			if (typeof data === "object" && typeof data.error === "undefined" ) {
				if (data.items.length == 0) {
					jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">Not found</span>");
					return;
				}
				updateValueTypeId_'.$field->name.'("youtube", dataID, element_id);
				updateValueInfo_'.$field->name.'({title: data.items[0].snippet.title, author: data.items[0].snippet.channelTitle, duration: youtubeDurationToSeconds_'.$field->name.'(data.items[0].contentDetails.duration), description: data.items[0].snippet.description, thumb: data.items[0].snippet.thumbnails.medium.url}, element_id);
				jQuery("#fcfield_fetching_msg_" + element_id).html("");
			} else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error.message : data;
				jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("", "", element_id);
			}
		}
		
		
		function vimeoCallback_'.$field->name.'(data, dataID, element_id)
		{
			dataID = typeof dataID === "undefined" || !dataID  ?  fc_dataID_'.$field->name.' : dataID;
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			if (typeof data === "object" && data.type != "error") {
				updateValueTypeId_'.$field->name.'("vimeo", dataID, element_id);
				updateValueInfo_'.$field->name.'({title: data[0].title, author: data[0].user_name, duration: data[0].duration, description: data[0].description, thumb: data[0].thumbnail_small}, element_id);
				jQuery("#fcfield_fetching_msg_" + element_id).html("");
			} else {
				var errorText = typeof data === "object" ? data.error_message : data;
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_UNABLE_TO_PARSE').'");
				jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("", "", element_id);
			}
		}
		
		
		function dailymotionCallback_'.$field->name.'(data, dataID, element_id)
		{
			dataID = typeof dataID === "undefined" || !dataID  ?  fc_dataID_'.$field->name.' : dataID;
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			if (typeof data === "object" && typeof data.error === "undefined") {
				updateValueTypeId_'.$field->name.'("dailymotion", dataID, element_id);
				updateValueInfo_'.$field->name.'({title: data.title, author: data["owner.screenname"], duration: data.duration, description: data.description, thumb: data.thumbnail_60_url}, element_id);
				jQuery("#fcfield_fetching_msg_" + element_id).html("");
			} else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">"+data.error.message+"</span>");
				updateValueTypeId_'.$field->name.'("", "", element_id);
			}
		}
		
		
		function embedlyCallback_'.$field->name.'(data, element_id)
		{
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			if (typeof data === "object" && data.type != "error") {
				if (data.type == "video" || data.type == "rich")
				{
					var urlregex = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
					if (data.html.match(urlregex) != null)
					{
						var iframeurl = data.html.match(urlregex)[0];
						var iframecode = \'<iframe class="sharedmedia seamless" src="\'+iframeurl+\'" style="width: 240px; height: 140px; border: none; overflow:hidden;" allowFullScreen></iframe>\';
						jQuery("#"+element_id+"_preview").innerHTML = iframecode;
						updateValueTypeId_'.$field->name.'("embed.ly:"+data.provider_name.toLowerCase(), iframeurl, element_id);
						updateValueInfo_'.$field->name.'({title: data.title, author: data.author_name, duration: "", description: data.description, thumb: data.thumbnail_url}, element_id);
						jQuery("#" + element_id + "_title, " + "#" + element_id + "_author, " + "#" + element_id + "_description, " + "#" + element_id + "_datatype, " + "#" + element_id + "_dataid, " + "#" + element_id + "_preview, " + "#" + element_id + "_width, " + "#" + element_id + "_height").parents("tr").show("fast");
					}
					jQuery("#fcfield_fetching_msg_" + element_id).html("");
				}
				else 
				{
					jQuery("#fcfield_fetching_msg_" + element_id).html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">?</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_URL_NOT_AUDIO').'</div>");
					jQuery("#" + element_id + "_title, " + "#" + element_id + "_author, " + "#" + element_id + "_description, " + "#" + element_id + "_datatype, " + "#" + element_id + "_dataid, " + "#" + element_id + "_preview, " + "#" + element_id + "_width, " + "#" + element_id + "_height").parents("tr").hide("fast");
				}
			}
			else {
				alert("'.JText::_('PLG_FLEXICONTENT_FIELDS_SHARED'.$_MEDIA_.'_UNABLE_TO_PARSE').'");
				var errorText = typeof data === "object" ? data.error_message : data;
				jQuery("#fcfield_fetching_msg_" + element_id).html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
				updateValueTypeId_'.$field->name.'("", "", element_id);
			}
		}
		
		
		function updateValueTypeId_'.$field->name.'(dataType, dataID, element_id)
		{
			dataID = typeof dataID === "undefined" || !dataID  ?  fc_dataID_'.$field->name.' : dataID;
			if (dataType=="") dataID = "";
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			jQuery("#"+element_id+"_datatype").val(dataType);
			jQuery("#"+element_id+"_dataid").val(dataID);

			if (dataType!="" && dataID!="")
			{
				var iframecode = \'<iframe class="sharedmedia seamless" src="\';
				switch(dataType) {
					case "youtube"    :  iframecode += "//www.youtube.com/embed/";   break;
					case "vimeo"      :  iframecode += "//player.vimeo.com/video/";  break;
					case "dailymotion":  iframecode += "//www.dailymotion.com/embed/video/"; break;
				}
				iframecode += dataID + \'" style="width: 240px; height: 140px; border: none; overflow:hidden;" allowFullScreen></iframe>\';
				document.getElementById(element_id+"_preview").innerHTML = iframecode;
			}
			else {
				document.getElementById(element_id+"_preview").innerHTML = "";
			}
		}
		
		
		function updateValueInfo_'.$field->name.'(data, element_id)
		{
			element_id = typeof element_id === "undefined" || !element_id  ?  fc_elemID_'.$field->name.' : element_id;
			
			jQuery("#"+element_id+"_title").val(data.title);
			jQuery("#"+element_id+"_author").val(data.author);
			jQuery("#"+element_id+"_duration").val(data.duration);
			jQuery("#"+element_id+"_description").val(data.description);
			jQuery("#"+element_id+"_thumb").val(data.thumb);
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
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		$field->label = JText::_($field->label);

		// Get field values
		$values = $values ? $values : $field->value;
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$_MEDIA_ = 'AUDIO';
		
		// Meta DATA that will be displayed
		$display_title    = $field->parameters->get('display_title', 1);
		$display_author   = $field->parameters->get('display_author', 0);
		$display_duration = $field->parameters->get('display_duration',0) ;
		$display_description = $field->parameters->get('display_description', 0);

		$headinglevel = $field->parameters->get('headinglevel', 3);
		$width        = $field->parameters->get('width', 960);
		$height       = $field->parameters->get('height', 540);
		$autostart    = $field->parameters->get('autostart', 0);
		$player_position = $field->parameters->get('player_position', 0);
		$display_edit_size_form = $field->parameters->get('display_edit_size_form', 1);

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
			$separatorf = '<br />';
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
			if (empty($value)) {
				if ($use_ingroup) $field->{$prop}[$n] = '';
				continue;
			}
			
			$value = unserialize($value);
			$value['datatype'] = @ $value['audiotype'];
			$value['dataid']   = @ $value['audioid'];
			
			if ((empty($value['datatype']) || empty($value['dataid'])) && empty($value['embed_url'])) {
				if ($use_ingroup) $field->{$prop}[$n] = '';
				continue;
			}
			
			$duration = intval($value['duration']);
			if ($display_duration && $duration && empty($value['embed_url']))
			{
				if ($duration >= 3600) $h = intval($duration/3600);
				if ($duration >= 60)   $m = intval($duration/60 - $h*60);
				$s = $duration - $m*60 -$h*3600;
				if ($h>0) $h .= ":";
				$m = str_pad($m,2,'0',STR_PAD_LEFT).':';
				$s = str_pad($s,2,'0',STR_PAD_LEFT);
				$duration_str = $h.$m.$s;
			}
			else $duration_str = '';
			
			// Create field's html
			$html_meta = '
				'.($display_title  && !empty($value['title'])  ? '<h'.$headinglevel.'>' . $value['title']  . '</h'.$headinglevel.'>' : '') .'
				'.($display_author && !empty($value['author']) ? '<div class="author">' . $value['author'] . '</div>' : '') .'
				'.($duration_str ? '<div class="duration">'.$duration_str.'</div>' : '') .'
				'.($display_description && !empty($value['description']) ? '<div class="description">' . $value['description'] . '</div>' : '');
			
			// backward compatibility
			if (!empty($value['embed_url']))
			{
				$embed_url = $value['embed_url'];
				$_show_related = '';
				$_show_srvlogo = '';
			}
			else
			{
				$content_id = $value['dataid'];
				switch($value['datatype'])
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
			
			$_width  = $display_edit_size_form ? $value['width']  : $width;
			$_height = $display_edit_size_form ? $value['height'] : $height;
			
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
			$newpost[$new]['audiotype']   = flexicontent_html::dataFilter(@$v['datatype'], 0, 'STRING', 0);
			$newpost[$new]['audioid']     = flexicontent_html::dataFilter(@$v['dataid'], 0, 'STRING', 0);
			$newpost[$new]['embed_url']   = flexicontent_html::dataFilter(@$v['embed_url'], 0, 'STRING', 0);
			$newpost[$new]['thumb']       = flexicontent_html::dataFilter(@$v['thumb'], 0, 'STRING', 0);
			$newpost[$new]['title']       = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 0);
			$newpost[$new]['author']      = flexicontent_html::dataFilter(@$v['author'], 0, 'STRING', 0);
			$newpost[$new]['duration']    = flexicontent_html::dataFilter(@$v['duration'], 0, 'INT', 0);
			$newpost[$new]['description'] = flexicontent_html::dataFilter(@$v['description'], 0, 'STRING', 0);
			$newpost[$new]['height']      = flexicontent_html::dataFilter(@$v['height'], 0, 'STRING', 0);
			$newpost[$new]['width']       = flexicontent_html::dataFilter(@$v['width'], 0, 'STRING', 0);
			
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
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