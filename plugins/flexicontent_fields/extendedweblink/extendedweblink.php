<?php
/**
 * @version 1.0 $Id: extendedweblink.php 1863 2014-03-07 18:32:13Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.extendedweblink
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('cms.plugin.plugin');
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsExtendedWeblink extends FCField
{
	static $field_types = array('extendedweblink');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_extendedweblink', JPATH_ADMINISTRATOR);
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
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		
		
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
		$link_usage   = $field->parameters->get( 'default_link_usage', 0 ) ;
		$default_link = ($item->version == 0 || $link_usage > 0) ? $field->parameters->get( 'default_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';
		$allow_relative_addrs = $field->parameters->get( 'allow_relative_addrs', 0 ) ;
		
		
		// *********************************************************
		// URL title, linking text, CSS class, HTML tag id (optional)
		// **********************************************************
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		
		
		// *****************************
		// URL other optional properties
		// *****************************
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($item->version == 0 || $text_usage > 0) ? $field->parameters->get( 'default_text', '' ) : '';
		$default_text = $default_text ? JText::_($default_text) : '';
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($item->version == 0 || $class_usage > 0) ? $field->parameters->get( 'default_class', '' ) : '';
		$default_class = $default_class ? JText::_($default_class) : '';
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		// Css class names
		$class_choices = $field->parameters->get( 'class_choices', '') ;
		if ($useclass==2) $class_options = $this->getClassOptions($class_choices);
		
		$id_usage   = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($item->version == 0 || $id_usage > 0) ? $field->parameters->get( 'default_id', '' ) : '';
		$default_id = $default_id ? JText::_($default_id) : '';
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		
		// **********
		// Hits usage
		// **********
		$usehits    = $field->parameters->get( 'use_hits', 1 ) ;
		
		
		// Form fields display parameters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['link']  = $default_link;
			$field->value[0]['title'] = $default_title;
			$field->value[0]['linktext']  = $default_text;
			$field->value[0]['class'] = $default_class;
			$field->value[0]['id']    = $default_id;
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
				
				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update new URL's address
			$js .= "
				theInput = newField.find('input.urllink').first();
				theInput.val(".json_encode($default_link).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][link]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_link');
				newField.find('.urllink-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_link');
				";
			
			if ($allow_relative_addrs==2) $js .= "
				var nr = 0;
				newField.find('input.autoprefix').each(function() {
					var elem = jQuery(this);
					elem.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][autoprefix]');
					elem.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_autoprefix_'+nr);
					elem.next('label').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_autoprefix_'+nr);
					nr++;
				});
				";
			
			// Update new URL optional properties
			if ($usetitle) $js .= "
				theInput = newField.find('input.urltitle').first();
				theInput.val(".json_encode($default_title).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				newField.find('.urltitle-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				";
			
			if ($usetext) $js .= "
				theInput = newField.find('input.urllinktext').first();
				theInput.val(".json_encode($default_text).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][linktext]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_linktext');
				newField.find('.urllinktext-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_linktext');
				";
			
			if ($useclass) $js .= "
				theInput = newField.find('input.urlclass').first();
				theInput.val(".json_encode($default_class).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][class]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_class');
				newField.find('.urlclass-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_class');
				";
			
			if ($useid) $js .= "
				theInput = newField.find('input.urlid').first();
				theInput.val(".json_encode($default_id).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][id]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_id');
				newField.find('.urlid-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_id');
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

				// Attach form validation on new element
				fc_validationAttach(newField);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function expandFields".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				var fields_s = row.find('.fc-xpended');
				var fields_m = row.find('.fc-xpended-row');
				
				fields_s.each(function() {  jQuery(this).removeClass('fc-xpended').addClass('fc-xpended-row');  });
				fields_m.each(function() {  jQuery(this).removeClass('fc-xpended-row').addClass('fc-xpended');  });
			}
			
			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks
				var btn = fieldval_box ? false : jQuery(el);
				if (btn) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}

				// If not removing re-enable clicks
				else if (btn) btn.css('pointer-events', '').on('click');
			}
			";
			
			$css .= '';
			
			$expand_view = '<span class="'.$add_on_class.' fcfield-expand-view'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_EXPAND_VALUES' ).'" onclick="expandFields'.$field->id.'(this);"></span>';
			$remove_button = '<span class="'.$add_on_class.' fcfield-delvalue'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="'.$add_on_class.' fcfield-drag-handle'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_before'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_after'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$expand_view = '';
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
					array('link' => $value, 'title' => '', 'linktext'=>'', 'class'=>'', 'id'=>'', 'hits'=>0);
			}
			if ( empty($value['link']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$value['link'] = !empty($value['link']) ? $value['link'] : $default_link;
			$value['link'] = htmlspecialchars( JStringPunycode::urlToUTF8($value['link']), ENT_COMPAT, 'UTF-8' );
			$link = '
				<div class="'.$input_grp_class.' fc-xpended-row">
					<label class="'.$add_on_class.' fc-lbl urllink-lbl" for="'.$elementid_n.'_link">'.JText::_( 'FLEXI_FIELD_URL' ).'</label>
					<input class="urllink fcfield_textval '.$required.'" name="'.$fieldname_n.'[link]" id="'.$elementid_n.'_link" type="text" size="'.$size.'" value="'.$value['link'].'" />
				</div>';
			
			$autoprefix = '';
			if ($allow_relative_addrs==2) {
				$_tip_title  = flexicontent_html::getToolTip(null, 'FLEXI_EXTWL_IS_RELATIVE_DESC', 1, 1);
				$is_absolute = (boolean) parse_url($value['link'], PHP_URL_SCHEME); // preg_match("#^http|^https|^ftp#i", $value['link']);
				$autoprefix = '
				<div class="'.$input_grp_class.' fc-xpended-row btn-group group-fcinfo">
					<label class="'.$add_on_class.' fc-lbl '.$tooltip_class.'" title="'.$_tip_title.'">'.JText::_( 'FLEXI_EXTWL_IS_RELATIVE' ).'</label>
					<input class="autoprefix" id="'.$elementid_n.'_autoprefix_0" name="'.$fieldname_n.'[autoprefix]" type="radio" value="0" '.( !$is_absolute ? 'checked="checked"' : '' ).'/>
					<label class="'.$add_on_class.' btn" style="min-width: 48px;" for="'.$elementid_n.'_autoprefix_0">'.JText::_('FLEXI_YES').'</label>
					<input class="autoprefix" id="'.$elementid_n.'_autoprefix_1" name="'.$fieldname_n.'[autoprefix]" type="radio" value="1" '.( $is_absolute ? 'checked="checked"' : '' ).'/>
					<label class="'.$add_on_class.' btn" style="min-width: 48px;" for="'.$elementid_n.'_autoprefix_1">'.JText::_('FLEXI_NO').'</label>
				</div>';
			}
			
			$title = '';
			if ($usetitle) {
				$value['title'] = !empty($value['title']) ? $value['title'] : $default_title;
				$value['title'] = htmlspecialchars($value['title'], ENT_COMPAT, 'UTF-8');
				$title = '
				<div class="'.$input_grp_class.' fc-xpended-row">
					<label class="'.$add_on_class.' fc-lbl urltitle-lbl" for="'.$elementid_n.'_title">'.JText::_( 'FLEXI_EXTWL_URLTITLE' ).'</label>
					<input class="urltitle fcfield_textval" name="'.$fieldname_n.'[title]" id="'.$elementid_n.'_title" type="text" size="'.$size.'" value="'.$value['title'].'" />
				</div>';
			}
			
			$linktext = '';
			if ($usetext) {
				$value['linktext'] = !empty($value['linktext']) ? $value['linktext'] : $default_text;
				$value['linktext'] = htmlspecialchars($value['linktext'], ENT_COMPAT, 'UTF-8');
				$linktext = '
				<div class="'.$input_grp_class.' fc-xpended-row">
					<label class="'.$add_on_class.' fc-lbl urllinktext-lbl" for="'.$elementid_n.'_linktext">'.JText::_( 'FLEXI_EXTWL_URLLINK_TEXT' ).'</label>
					<input class="urllinktext fcfield_textval" name="'.$fieldname_n.'[linktext]" id="'.$elementid_n.'_linktext" type="text" size="'.$size.'" value="'.$value['linktext'].'" />
				</div>';
			}
			
			$class = '';
			if ($useclass) {
				$value['class'] = !empty($value['class']) ? $value['class'] : $default_class;
				$value['class'] = htmlspecialchars($value['class'], ENT_COMPAT, 'UTF-8');
			}
			if ($useclass==1) {
				$class = '
					<div class="'.$input_grp_class.' fc-xpended-row">
						<label class="'.$add_on_class.' fc-lbl urlclass-lbl" for="'.$elementid_n.'_class">'.JText::_( 'FLEXI_EXTWL_URLCLASS' ).'</label>
						<input class="urlclass fcfield_textval" name="'.$fieldname_n.'[class]" id="'.$elementid_n.'_class" type="text" size="'.$size.'" value="'.$value['class'].'" />
					</div>';
			} else if ($useclass==2) {
				$class_attribs = ' class="urlclass" ';
				$class = '
					<div class="'.$input_grp_class.' fc-xpended-row">
						<label class="'.$add_on_class.' fc-lbl urlclass-lbl" for="'.$elementid_n.'_class">'.JText::_( 'FLEXI_EXTWL_URLCLASS' ).'</label>
						'.JHTML::_('select.genericlist', $class_options, $fieldname_n.'[class]', $class_attribs, 'value', 'text', $value['class'], $class_elementid = $elementid_n.'_class').'
					</div>';
			}
			
			$id = '';
			if ($useid) {
				$value['id'] = !empty($value['id']) ? $value['id'] : $default_id;
				$value['id'] = htmlspecialchars($value['id'], ENT_COMPAT, 'UTF-8');
				$id = '
				<div class="'.$input_grp_class.' fc-xpended-row">
					<label class="'.$add_on_class.' fc-lbl urlid-lbl" for="'.$elementid_n.'_id">'.JText::_( 'FLEXI_EXTWL_URLID' ).'</label>
					<input class="urlid fcfield_textval" name="'.$fieldname_n.'[id]" id="'.$elementid_n.'_id" type="text" size="'.$size.'" value="'.$value['id'].'" />
				</div>';
			}
			
			$hits = '';
			if ($usehits) {
				$hits = (int) @ $value['hits'];
				$hits = '
					<div class="'.$input_grp_class.' fc-xpended-row">
						<label class="'.$add_on_class.' fc-lbl urlhits-lbl" for="'.$elementid_n.'_hits">'.JText::_( 'FLEXI_EXTWL_POPULARITY' ).'</label>
						<span class="'.$add_on_class.' hitcount" style="font-style: italic; min-width:64px;">'.$hits.' '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
					</div>
					<input class="urlhits fc_hidden_input" name="'.$fieldname_n.'[hits]" id="'.$elementid_n.'_hits" type="text" value="'.$hits.'" />';
			}
			
			$field->html[] = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$expand_view.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
				<div class="fc-field-props-box">
				'.$link.'
				'.$autoprefix.'
				'.$title.'
				'.$linktext.'
				'.$class.'
				'.$id.'
				'.$hits.'
				</div>
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
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue '.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').' fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">'.JText::_( 'FLEXI_ADD_VALUE' ).'</span>';
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
		$tooltip_class = 'hasTooltip';
		$target       = $field->parameters->get( 'target', '' );
		$target_param = $target ? ' target="'.$target.'"' : '';
		$display_hits = $field->parameters->get( 'display_hits', 0 ) ;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || $isMobile;
		$rel_nofollow = $field->parameters->get( 'add_rel_nofollow', 0 ) ? ' rel="nofollow"'    : '';
		
		// URL value
		$link_usage   = $field->parameters->get( 'default_link_usage', 0 ) ;
		$default_link = ($link_usage == 2) ? $field->parameters->get( 'default_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';
		
		// URL title & linking text (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($text_usage == 2)  ?  $field->parameters->get( 'default_text', '' ) : '';
		$default_text = $default_text ? JText::_($default_text) : '';
		
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($class_usage == 2)  ?  $field->parameters->get( 'default_class', '' ) : '';
		$default_class = $default_class ? JText::_($default_class) : '';
		
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$id_usage	  = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($id_usage == 2)  ?  $field->parameters->get( 'default_id', '' ) : '';
		$default_id = $default_id ? JText::_($default_id) : '';
		
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
			$values[0]['linktext']  = $default_text;
			$values[0]['class'] = $default_class;
			$values[0]['id']    = $default_id;
			$values[0]['hits']  = 0;
			$values[0] = serialize($values[0]);
		}
		
		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for unserialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$v = !empty($value) ? @unserialize($value) : false;
					$value = ( $v !== false || $v === 'b:0;' ) ? $v :
						array('link' => $value, 'title' => '', 'linktext'=>'', 'class'=>'', 'id'=>'', 'hits'=>0);
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
		
		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';
		
		// Create field's HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($this->fieldtypes[0], $viewlayout));
		
		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' )
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
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
		
		$allow_relative_addrs = $field->parameters->get( 'allow_relative_addrs', 0 ) ;
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
			$force_absolute = $allow_relative_addrs==0 || ($allow_relative_addrs==2 && (int)$v['autoprefix']);
			
			// Has protocol nothing to do
			if ( parse_url($link, PHP_URL_SCHEME) ) $prefix = '';
			// Has current domain but no protocol just add http://
			else if (strpos($link, $host) === 0) $prefix = 'http://';
			// Relative URLs allowed, do to not add Joomla ROOT, to allow website to be moved and change subfolder
			else if ( !$force_absolute ) $prefix = ''; //substr($link, 0, 1) == '/') ? '' : JURI::root(true) . '/';
			// Absolute URLs are forced
			else {
				if (substr($link, 0, 10) == '/index.php')  $link = substr($link, 1);
				$prefix = (substr($link, 0, 9) == 'index.php') ? JURI::root() : 'http://';
			}
			
			$newpost[$new] = array();
			$newpost[$new]['link'] = empty($link) ? '' : $prefix.$link;
			
			// Validate other value properties
			$newpost[$new]['title']   = flexicontent_html::dataFilter(@$v['title'], 0, 'STRING', 0);
			$newpost[$new]['id']      = flexicontent_html::dataFilter(@$v['id'], 0, 'STRING', 0);
			$newpost[$new]['class']   = flexicontent_html::dataFilter(@$v['class'], 0, 'STRING', 0);
			$newpost[$new]['linktext']= flexicontent_html::dataFilter(@$v['linktext'], 0, 'STRING', 0);
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
	
	
	// Create once the options for field properties that have drop down-selection
	function getClassOptions($class_choices) {
		// Parse the elements used by field unsetting last element if empty
		$choices = preg_split("/[\s]*%%[\s]*/", $class_choices);
		if ( empty($choices[count($choices)-1]) )	unset($choices[count($choices)-1]);
			
		// Split elements into their properties: value, label, extra_prop1, extra_prop2
		$elements = array();
		$k = 0;
		foreach ($choices as $choice) {
			$choice_props  = preg_split("/[\s]*::[\s]*/", $choice);
			if (count($choice_props) < 2) {
				echo "Error in field: ".$field->label.
					" while splitting class element: ".$choice.
					" properties needed: ".$props_needed.
					" properties found: ".count($choice_props);
				continue;
			}
			$class_elements[$k] = new stdClass();
			$class_elements[$k]->value = $choice_props[0];
			$class_elements[$k]->text  = $choice_props[1];
			$k++;
		}
			
		// Create the options for select drop down
		$class_options = array();
		$class_options[] = JHTML::_('select.option', '', '-');
		foreach ($class_elements as $element) {
			$class_options[] = JHTML::_('select.option', $element->value, JText::_($element->text));
		}
		return $class_options;
	}
	
}
