<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		// API key and other form configuration
		$embedly_key = $field->parameters->get('embedly_key','') ;
		
		$autostart   = $field->parameters->get('autostart', 0);
		$autostart   = $autostart ? 'true' : 'false';
		$force_ssl   = $field->parameters->get('force_ssl', 1);
		$force_ssl   = $force_ssl ? 'true' : 'false';
		$display_title_form       = $field->parameters->get('display_title_form', 1);
		$display_author_form      = $field->parameters->get('display_author_form', 1);
		$display_description_form = $field->parameters->get('display_description_form', 1);
        $display_edit_size_form = $field->parameters->get('display_edit_size_form', 1);
		
		// Initialise value property
		if (empty($field->value)) 
		{
			$field->value = array();
			$field->value[0]['url'] = '';
			$field->value[0]['embed_url'] = '';
			$field->value[0]['title'] = '';
			$field->value[0]['author'] = '';
			$field->value[0]['description'] = '';
            $field->value[0]['heightvideo'] = '';
            $field->value[0]['widthvideo'] = '';
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
				newField_HTML = newField_HTML.replace(/" . str_replace(array('[', ']'), array('\[', '\]'), $fieldname) . "\[(\d*)\]/g, '" . $fieldname . "[' + uniqueRowNum" . $field->id . " + ']');
				newField_HTML = newField_HTML.replace(/" . $elementid . "_(\d*)/g, '" . $elementid . "_' + uniqueRowNum" . $field->id . ");
				
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
				var updFun = 'updateValueInfo_" . $elementid . "_' + uniqueRowNum" . $field->id . ";
				window[updFun]({title:'', author:'', description:'', thumb:'', embed_url:''});
				jQuery('#" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_preview').html('');
				jQuery('#" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_url').val('');
                jQuery('#" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_heightvideo').val('');
                jQuery('#" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_widthvideo').val('');
				jQuery('#" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_title, #" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_author, #" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_description, #" . $elementid . "_' + uniqueRowNum" . $field->id . " + '_preview').parents('tr').hide('fast');
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
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
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
			$field->html[$n] = '<div class="alert alert-warning">'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_EMBEDLY_KEY_REQUIRED').'</div>';
		}
		
		else foreach ($field->value as $n => $value) 
		{
			$value = unserialize($value);
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			// only for backward compatibility:
			if (!empty($value['videotype']) && !empty($value['videoid'])) 
			{
				switch($value['videotype']) 
				{
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

			if (!isset($value['url']))         $value['url'] = '';
			if (!isset($value['embed_url']))   $value['embed_url'] = '';
			if (!isset($value['title']))       $value['title'] = '';
			if (!isset($value['author']))      $value['author'] = '';
            if (!isset($value['heightvideo'])) $value['heightvideo'] = '';
            if (!isset($value['widthvideo'])) $value['widthvideo'] = '';
			if (!isset($value['description'])) $value['description'] = '';
			if (!isset($value['thumb']))       $value['thumb'] = '';
			

			$html_field = '
			<table class="fc-form-tbl fcinner fc-sharedvideo-field-tbl" data-row="'.$n.'">
			<tbody>
				<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_VIDEO_URL') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval' . $required . '" id="' . $elementid_n . '_url" name="' . $fieldname_n . '[url]" value="' . $value['url'] . '" size="60" />
						<input class="fcfield-button" type="button" value="' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_FETCH') . '" onclick="fetchVideo_' . $elementid_n . '();" />
						<span id="fcfield_fetching_msg_' . $elementid_n . '"></span>
						'.($use_ingroup ? '' : $move2).'
						'.($use_ingroup ? '' : $remove_button).'
						'.($use_ingroup || !$add_position ? '' : $add_here).'
						<input type="hidden" id="' . $elementid_n . '_embed_url" name="' . $fieldname_n . '[embed_url]" value="' . $value['embed_url'] . '" />
					</td>
				</tr>' 
				. ($display_title_form ? 
				'<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_TITLE') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="' . $elementid_n . '_title" name="' . $fieldname_n . '[title]" value="' . $value['title'] . '" size="60" />
					</td>
				</tr>' 
				: '<input type="hidden" id="' . $elementid_n . '_title" name="' . $fieldname_n . '[title]" value="' . $value['title'] . '" />') 
				. ($display_author_form ? 
				'<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_AUTHOR') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="' . $elementid_n . '_author" name="' . $fieldname_n . '[author]" value="' . $value['author'] . '" size="60" />
					</td>
				</tr>' 
				: '<input type="hidden" id="' . $elementid_n . '_author" name="' . $fieldname_n . '[author]" value="' . $value['author'] . '" />') 
                            . ($display_edit_size_form ? 
				'<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_SIZE_HEIGHT') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="' . $elementid_n . '_heightvideo" name="' . $fieldname_n . '[heightvideo]" value="' . $value['heightvideo'] . '" size="60" />
					</td>
				</tr>
                               <tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_SIZE_WIDTH') . '</span></td>
					<td>
						<input type="text" class="fcfield_textval" id="' . $elementid_n . '_widthvideo" name="' . $fieldname_n . '[widthvideo]" value="' . $value['widthvideo'] . '" size="60" />
					</td>
				</tr>' 
				: '<input type="hidden" id="' . $elementid_n . '_heightvideo" name="' . $fieldname_n . '[heightvideo]" value="' . $value['heightvideo'] . '" />
                   <input type="hidden" id="' . $elementid_n . '_widthvideo" name="' . $fieldname_n . '[widthvideo]" value="' . $value['widthvideo'] . '" />')
				. ($display_description_form ? 
				'<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_DESCRIPTION') . '</span></td>
					<td>
						<textarea class="fcfield_textareaval" id="' . $elementid_n . '_description" name="' . $fieldname_n . '[description]" rows="7" cols="50">' . $value['description'] . '</textarea>
					</td>
				</tr>' : 
				'<input type="hidden" id="' . $elementid_n . '_description" name="' . $fieldname_n . '[description]" value="'. $value['description'] . '" />') 
				. '<tr>
					<td class="key"><span class="flexi label sub_label">' . JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_PREVIEW') . '</span>
					</td>
					<td>
						<div id="' . $elementid_n . '_preview">
							<iframe class="sharedvideo" src="' . $value['embed_url'] . '" style="width: 240px; height: 140px; border: none;" scrolling="no" seamless="seamless" allowFullScreen></iframe>
						</div>
						<input type="hidden" id="' . $elementid_n . '_thumb" name="' . $fieldname_n . '[thumb]" value="' . $value['thumb'] . '" />
					</td>
				</tr>
			</tbody>
			</table>
			<div id="fcfield_error_msg_' . $elementid_n . '"></div>
			<script>
			function fetchVideo_' . $elementid_n . '() 
			{
				updateValueInfo_' . $elementid_n . '({title:"", author:"", description:"", thumb:"", embed_url:""});
				jQuery("#' . $elementid_n . '_preview").html("");
				
				var urlregex = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
				var videourl = jQuery("#' . $elementid_n . '_url").val();
				if(videourl.match(urlregex) != null)
				{
					var jsonurl = "";
					
					jQuery("#fcfield_fetching_msg_' . $elementid_n . '").html("<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">");
					
					// try embed.ly
					jsonurl = "https://api.embed.ly/1/oembed?url="+encodeURIComponent(videourl)'. ($embedly_key ? '+"&key='.$embedly_key.'"' : '') .'+"&maxwidth=1280&wmode=transparent&secure=' . $force_ssl . '&autoplay=' . $autostart . '&callback=embedlyCallback_' . $elementid_n . '";
					var jsonscript = document.createElement("script");
					jsonscript.setAttribute("type","text/javascript");
					jsonscript.setAttribute("src",jsonurl);
					jsonscript.onerror = function(evt)
					{
						jQuery("#fcfield_error_msg_' . $elementid_n . '").html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_HTTP_ERROR').'</div>");
						jQuery("#fcfield_fetching_msg_' . $elementid_n . '").html("");
					};
					document.body.appendChild(jsonscript);
				}
				else 
				{
					jQuery("#fcfield_error_msg_' . $elementid_n . '").html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_INVALID_URL').'</div>");
					jQuery("#' . $elementid_n . '_title, #' . $elementid_n . '_author, #' . $elementid_n . '_description, #' . $elementid_n . '_preview").parents("tr").hide("fast");
				}
			}
			function embedlyCallback_' . $elementid_n . '(data)
			{
				if(typeof data === "object" && data.type != "error") 
				{
					if(data.type == "video")
					{
						var urlregex = /(http:|ftp:|https:)?\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
						if(data.html.match(urlregex) != null) 
						{
							var iframeurl = data.html.match(urlregex)[0];
							var iframecode = \'<iframe class="sharedvideo" src="\'+iframeurl+\'" style="width: 240px; height: 140px; border: none;" scrolling="no" seamless="seamless" allowFullScreen></iframe>\';
							jQuery("#' . $elementid_n . '_preview").html(iframecode);
							updateValueInfo_' . $elementid_n . '({title: data.title, author: data.author_name, description: data.description, thumb: data.thumbnail_url, embed_url: data.html.match(urlregex)[0]});
							jQuery("#' . $elementid_n . '_title, #' . $elementid_n . '_author, #' . $elementid_n . '_description, #' . $elementid_n . '_preview").parents("tr").show("fast");
						}
					}
					else 
					{
						jQuery("#fcfield_error_msg_' . $elementid_n . '").html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_URL_NOT_VIDEO').'</div>");
						jQuery("#' . $elementid_n . '_title, #' . $elementid_n . '_author, #' . $elementid_n . '_description, #' . $elementid_n . '_preview").parents("tr").hide("fast");
					}
				}
				else {
					jQuery("#fcfield_error_msg_' . $elementid_n . '").html("<div class=\"alert alert-warning\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>'. JText::_('PLG_FLEXICONTENT_FIELDS_SHAREDVIDEO_UNABLE_TO_PARSE').'</div>");
					var errorText = typeof data === "object" ? data.error_message : data;
					jQuery("#fcfield_fetching_msg_'.$elementid_n.'").html("<span class=\"alert alert-warning fc-iblock\">"+errorText+"</span>");
					jQuery("#' . $elementid_n . '_title, #' . $elementid_n . '_author, #' . $elementid_n . '_description, #' . $elementid_n . '_preview").parents("tr").hide("fast");
				}
				jQuery("#fcfield_fetching_msg_' . $elementid_n . '").html("");
			}
			function updateValueInfo_' . $elementid_n . '(data)
			{
				jQuery("#' . $elementid_n . '_title").val(data.title);
				jQuery("#' . $elementid_n . '_author").val(data.author);
                jQuery("#' . $elementid_n . '_heightvideo").val(data.heightvideo);
                jQuery("#' . $elementid_n . '_widthvideo").val(data.widthvideo);
				jQuery("#' . $elementid_n . '_description").val(data.description);
				jQuery("#' . $elementid_n . '_thumb").val(data.thumb);
				jQuery("#' . $elementid_n . '_embed_url").val(data.embed_url);
			}
			jQuery(document).ready(function()
			{
				// if field is empty, hide fields
				if(jQuery("#' . $elementid_n . '_url").val() == "") 
				{
					jQuery("#' . $elementid_n . '_title, #' . $elementid_n . '_author, #' . $elementid_n . '_description, #' . $elementid_n . '_preview").parents("tr").hide();
				}
			});
			</script>';
			
			$field->html[] = $html_field;
			
			$n++;
			if (!$multiple) break;
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// displays the field in the frontend

		$field->label = JText::_($field->label);
		if (!in_array($field->field_type, self::$field_types)) return;

		// Get field values
		$values = $values ? $values : $field->value;
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$display_title 			= $field->parameters->get('display_title', 1);
		$display_author 		= $field->parameters->get('display_author', 0);
		$display_description 	= $field->parameters->get('display_description', 0);

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
			if ((empty($value['videotype']) || empty($value['videoid'])) && empty($value['embed_url'])) {
				if ($use_ingroup) $field->{$prop}[$n] = '';
				continue;
			}
			
			// generate html output
			$html_meta = ($display_title && !empty($value['title']) ? '<h' . $headinglevel . '>' . $value['title'] . '</h' . $headinglevel . '>' : '') 
				. ($display_author && !empty($value['author']) ? '<div class="author">' . $value['author'] . '</div>' : '') 
				. ($display_description && !empty($value['description']) ? '<div class="description">' . $value['description'] . '</div>' : '');
			
			$html_video = '<div class="videoplayer"><iframe class="sharedvideo" src="';

			// backward compatibility
			if (!empty($value['embed_url']))
			{
				$embed_url = $value['embed_url'];
			}
			else
			{
				switch($value['videotype'])
				{
					case 'youtube'     :  $embed_url = '//www.youtube.com/embed/' . $value['videoid'] . '?autoplay=' . $autostart .'&rel=0&modestbranding=1&maxwidth=0&modestbranding=1';  break;
					case 'vimeo'       :  $embed_url = '//player.vimeo.com/video/' . $value['videoid'] . '?autoplay=' . $autostart;  break;
					case 'dailymotion' :  $embed_url = '//www.dailymotion.com/embed/video/' . $value['videoid'] . '?autoplay=' . $autostart . '&related=0&logo=0';  break;
					default            :  $embed_url = $value['videoid'];  break;
				}
			}
            //$width $height
            if ($display_edit_size_form ==1){
                $widthdisplay = $value['widthvideo'];
                $heightdisplay = $value['heightvideo'];
            }else{
                $widthdisplay = $width;
                $heightdisplay = $width;
            }
			$html_video .= $embed_url . '" style="border: none;" scrolling="no" seamless="seamless" allowFullScreen width="' . $widthdisplay . '" height="'. $heightdisplay .'"></iframe></div>';
			
			$field->{$prop}[$n] = $pretext 
				. ($player_position ? '' : $html_video)
				. $html_meta
				. ($player_position ? $html_video : '')
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
			//$newpost[$new]['videotype']   = flexicontent_html::dataFilter(@$v['videotype'], 0, 'STRING', 0);
			//$newpost[$new]['videoid']     = flexicontent_html::dataFilter(@$v['videoid'], 0, 'STRING', 0);
			//$newpost[$new]['duration']    = flexicontent_html::dataFilter(@$v['duration'], 0, 'INT', 0);
			$newpost[$new]['embed_url']   = flexicontent_html::dataFilter(@$v['embed_url'], 0, 'STRING', 0);
			$newpost[$new]['thumb']       = flexicontent_html::dataFilter(@$v['thumb'], 0, 'STRING', 0);
			$newpost[$new]['title']       = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 0);
			$newpost[$new]['author']      = flexicontent_html::dataFilter(@$v['author'], 0, 'STRING', 0);
			$newpost[$new]['description'] = flexicontent_html::dataFilter(@$v['description'], 0, 'STRING', 0);
            $newpost[$new]['heightvideo']      = flexicontent_html::dataFilter(@$v['heightvideo'], 0, 'STRING', 0);
			$newpost[$new]['widthvideo'] = flexicontent_html::dataFilter(@$v['widthvideo'], 0, 'STRING', 0);
			
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
