<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.weblink
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsWeblink extends JPlugin
{
	static $field_types = array('weblink');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsWeblink( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_weblink', JPATH_ADMINISTRATOR);
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
		
		
		// ***
		// URL
		// ***
		
		// Default value
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($item->version == 0 || $link_usage > 0) ? $field->parameters->get( 'default_value_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';
		
		
		// ***********************************
		// URL title & linking text (optional)
		// ***********************************
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		
		
		// **********
		// Hits usage
		// **********
		$usehits    = $field->parameters->get( 'use_hits', 1 ) ;
		
		
		// Form fields display parameters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced
		
		// create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($maxlength) $attribs .= ' maxlength="'.$maxlength.'" ';
		$attribs .= ' size="'.$size.'" ';
		
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['link']  = $default_link;
			$field->value[0]['title'] = $default_title;
			$field->value[0]['hits']  = 0;
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
				var newField  = lastField.clone();
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update new URL's address
			$js .= "
				var theInput = newField.find('input.urllink').first();
				theInput.val('".$default_link."');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][link]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_link');
				newField.find('.urllink-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_link');
				";
				
			// Update new URL optional properties
			if ($usetitle) $js .= "
				var theInput = newField.find('input.urltitle').first();
				theInput.val('".$default_title."');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				newField.find('.urltitle-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				";
				
			if ($usehits) $js .="
				theInput = newField.find('input.urlhits').first();
				theInput.val('0');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][hits]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_hits');
				newField.find('.urlhits-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_hits');
				
				// Set hits to zero for new row value
				newField.find('span span').html('0');
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
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
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('link' => $value, 'title' => '', 'hits'=>0);
			}
			if ( empty($value['link']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$value['link'] = !empty($value['link']) ? $value['link'] : $default_link;
			$value['link'] = htmlspecialchars(
				(FLEXI_J30GE ? JStringPunycode::urlToUTF8($value['link']) : $value['link']),
				ENT_COMPAT, 'UTF-8'
			);
			$link = '
				<div class="nowrap_box">
					<label class="label urllink-lbl" for="'.$elementid_n.'_link">'.JText::_( 'FLEXI_FIELD_URL' ).'</label>
					<input class="urllink fcfield_textval '.$required.'" name="'.$fieldname_n.'[link]" id="'.$elementid_n.'_link" type="text" '.$attribs.' value="'.$value['link'].'" />
				</div>';
			
			$title = '';
			if ($usetitle) {
				$value['title'] = !empty($value['title']) ? $value['title'] : $default_title;
				$value['title'] = htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8');
				$title = '
				<div class="nowrap_box urltitle-lbl">
					<label class="label" for="'.$elementid_n.'_title">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).'</label>
					<input class="urltitle fcfield_textval" name="'.$fieldname_n.'[title]" id="'.$elementid_n.'_title" type="text" size="'.$size.'" value="'.$value['title'].'" />
				</div>';
			}
			
			$hits = ''; $usehits = 1;
			if ($usehits) {
				$hits = (int) @ $value['hits'];
				$hits = '
					<div class="nowrap_box urlhits-lbl">
						<label class="label hits" for="'.$elementid_n.'_hits">'.JText::_( 'FLEXI_FIELD_HITS' ).'</label>
						<span class="hitcount">'.$hits.'</span> 
						<input class="urlhits" name="'.$fieldname_n.'[hits]" id="'.$elementid_n.'_hits" type="hidden" value="'.$hits.'" />
					</div>';
			}
			
			$field->html[] = '
				'.$link.'
				'.$title.'
				'.$hits.'
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		// Get isMobile / isTablet Flags
		static $isMobile = null;
		static $isTablet = null;
		static $useMobile = null;
		if ($useMobile===null) 
		{
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			$force_desktop_layout = $cparams->get('force_desktop_layout', 0 );
			//$start_microtime = microtime(true);
			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
			//$time_passed = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			//printf('<br/>-- [Detect Mobile: %.3f s] ', $time_passed/1000000);
		}
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		
		// some parameter shortcuts
		$target = $field->parameters->get( 'targetblank', 0 );
		$target_param   = $target ? ' target="_blank"' : '';
		$display_hits = $field->parameters->get( 'display_hits', 0 ) ;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || $isMobile;
		$rel_nofollow = $field->parameters->get( 'add_rel_nofollow', 0 ) ? ' rel="nofollow"'    : '';
		
		// URL value
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($link_usage == 2) ? $field->parameters->get( 'default_value_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';
		
		// URL title & linking text (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Check for no values and no default value, and return empty display
		if ( empty($values) ) {
			if (!strlen($default_link)) {
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['link']  = $default_link;
			$values[0]['title'] = $default_title;
			$values[0]['hits']  = 0;
			$values[0] = serialize($values[0]);
		}
		
		// (* BECAUSE OF THIS, the value display loop expects unserialized values)
		foreach ($values as &$value)
		{
			// Compatibility for unserialized values or for NULL values in a field group
			if ( !is_array($value) )
			{
				$v = !empty($value) ? @unserialize($value) : false;
				$value = ( $v !== false || $v === 'b:0;' ) ? $v :
					array('link' => $value, 'title' => '', 'hits'=>0);
			}
		}
		unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		
		
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
		
		// Optimization, do some stuff outside the loop
		static $hits_icon = null;
		if ($hits_icon===null && ($display_hits==1 || $display_hits==3)) {
			$_hits_tip = flexicontent_html::getToolTip(null, '%s '.JText::_( 'FLEXI_HITS', true ), 0, 0);
			$_attribs = $display_hits==1 ? 'class="'.(FLEXI_J30GE ? 'hasTooltip' : 'hasTip').'" title="'.$_hits_tip.'"' : '';
			$hits_icon = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'user.png', JText::_( 'FLEXI_HITS' ), $_attribs) :
				JHTML::_('image.site', 'user.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_HITS' ), $_attribs);
		}
		
		// Create field's HTML
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( empty($value['link']) && !$is_ingroup ) continue; // Skip empty if not in field group
			if ( empty($value['link']) ) {
				$field->{$prop}[$n++]	= '';
				continue;
			}
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$title    = ($usetitle && @$value['title']   )  ?  $value['title']    : $default_title;
			$linktext = '';  // no linktext for weblink for extended web link field if this is needed
			$hits     = (int) @ $value['hits'];
			
			$link_params  = $title ? ' title="'.$title.'"' : '';
			$link_params .= $target_param;
			$link_params .= $rel_nofollow;
			
			if ( $field->parameters->get( 'use_direct_link', 0 ) )
				// Direct access to the web-link, hits counting not possible
				$href = $value['link'];
			else 
				// Indirect access to the web-link, via calling FLEXIcontent component, thus counting hits too
				$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$item->id.'&ord='.($n+1).'&task=weblink' );
			
			// Create indirect link to web-link address with custom displayed text
			if( empty($linktext) )
				$linktext = $title ? $title : $this->cleanurl(
					(FLEXI_J30GE ? JStringPunycode::urlToUTF8($value['link']) : $value['link'])    // If using URL convert from Punycode to UTF8
				);
			$html = '<a href="' .$href. '" '.$link_params.' itemprop="url">' .$linktext. '</a>';
			
			// HITS: either as icon or as inline text or both
			$hits_html = '';
			if ($display_hits && $hits)
			{
				$hits_html = '<span class="fcweblink_hits">';
				if ( $add_hits_img && @ $hits_icon ) {
					$hits_html .= sprintf($hits_icon, $hits);
				}
				if ( $add_hits_txt ) {
					$hits_html .= '('.$hits.'&nbsp;'.JTEXT::_('FLEXI_HITS').')';
				}
				$hits_html .= '</span>';
				if ($prop == 'display_hitsonly')
					$html = $hits_html;
				else
					$html .= ' '. $hits_html;
			}
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= $pretext . $html . $posttext;
			
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
		$host = JURI::getInstance('SERVER')->gethost();
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
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
					$v = array('link' => $v, 'title' => '', 'id' => '', 'class' => '', 'linktext' => '', 'hits'=>0);
				}
			}
			
			
			// ***********************************************************
			// Validate URL, skipping URLs that are empty after validation
			// ***********************************************************
			
			$link = flexicontent_html::dataFilter($v['link'], 4000, 'URL', 0);  // Clean bad text/html
			
			// Skip empty value, but if in group increment the value position
			if (empty($link))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}
			
			// Sanitize the URL as absolute or relative
			// Has protocol nothing to do
			if ( parse_url($link, PHP_URL_SCHEME) ) $prefix = '';
			// Has current domain but no protocol just add http://
			else if (strpos($link, $host) === 0) $prefix = 'http://';
			// URLs are absolute, for relative URL support you need extended web link field
			else {
				if (substr($link, 0, 10) == '/index.php')  $link = substr($link, 1);
				$prefix = (substr($link, 0, 9) == 'index.php') ? JURI::root() : 'http://';
			}
			
			$newpost[$new] = array();
			$newpost[$new]['link'] = empty($link) ? '' : $prefix.$link;
			
			// Validate other value properties
			$newpost[$new]['title']   = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 0);
			$newpost[$new]['hits']    = (int) @ $v['hits'];
			
			$new++;
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v) {
			if ($v!==null) $post[$i] = serialize($v);
		}
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
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
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	// Get a url without the protocol
	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
	
}
