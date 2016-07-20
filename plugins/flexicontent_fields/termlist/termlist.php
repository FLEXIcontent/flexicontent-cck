<?php
/**
 * @version 1.0 $Id: termlist.php 1862 2014-03-07 03:29:42Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.termlist
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

class plgFlexicontent_fieldsTermlist extends JPlugin
{
	static $field_types = array('termlist');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_termlist', JPATH_ADMINISTRATOR);
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
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $field->parameters->get( 'editor',  $user->getParam('editor', $app->getCfg('editor'))  );
		$editor  = JFactory::getEditor($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, ignored by most editors !!
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// **********
		// Term title
		// **********
		
		// Label
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Input field display size & max characters
		$title_size      = $field->parameters->get( 'title_size', 80 ) ;
		$title_maxlength = $field->parameters->get( 'title_size', 0 ) ;
		
		
		// ***********************
		// Term text (description)
		// ***********************
		
		// Label
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Editing method, text editor or HTML editor
		$use_html = (int) $field->parameters->get( 'use_html', 0 );
		
		// *** Simple Textarea ***
		$rows  = $field->parameters->get( 'rows', 3 ) ;
		$cols  = $field->parameters->get( 'cols', 80 ) ;
		$maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		
		// *** HTML Editor configuration  ***
		$width = $field->parameters->get( 'width', '98%') ;
		if ($width != (int)$width) $width .= 'px';
		$height = $field->parameters->get( 'height', '250px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		// Decide editor plugin buttons to SKIP
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		
		// Clear empty value
		if (empty($skip_buttons[0]))  unset($skip_buttons[0]);
		
		// Force skipping pagebreak and readmore for CUSTOM textarea fields
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		$skip_buttons_arr = ($show_buttons && ($editor_name=='jce' || $editor_name=='tinymce') && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		// Initialise property with default value
		if ( !$field->value || (count($field->value)==1 && $field->value[0] === null) )
		{
			$field->value = array();
			$field->value[0]['title'] = $default_title;
			$field->value[0]['text']  = $default_value;
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
			$js .= "
				// Update the new term title
				var termtitle = newField.find('input.termtitle');
				var termtitle_dv = termtitle.attr('data-defvals');
				termtitle_dv && termtitle_dv.length ?
					termtitle.val(termtitle_dv) :
					termtitle.val('') ;
				termtitle.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				termtitle.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');

				// Update the new term description
				var boxClass = 'termtext';
				var container = newField.find('.fc_'+boxClass);
				var container_inner = newField.find('.fcfield_box');
				var txtarea = container.find('textarea').first();

				var hasTinyMCE = container.find('textarea').hasClass('mce_editable');  //typeof tinyMCE === 'undefined' ? false : !!tinyMCE.get( txtarea.attr('id') );
				var hasCodeMirror = typeof CodeMirror === 'undefined' ? false : txtarea.next().hasClass('CodeMirror');

				".( !$use_html ? "" : "
				if (hasCodeMirror)  // CodeMirror case
				{
					// Get options not from copy but from the original DOM element
					var CMoptions = jQuery('#'+txtarea.attr('id')).next().get(0).CodeMirror.options;

					// Cleanup the cloned HTML elements of the editor
					container.find('.CodeMirror').remove();
				}
				else   // tinyMCE / other editors
				{
					// Append a new container after the current textarea container
					container.after('<div class=\"'+ container.get(0).className +'\"></div>');

					// Copy label
					container.find('label.labeltext').appendTo(container.next());

					// Add inner container and copy only the textarea into the new container and make it visible
					jQuery('<div class=\"'+ container_inner.get(0).className +'\">' + (hasTinyMCE ? '<div class=\"editor\"></div>' : '') + '</div>').appendTo(container.next());
					var target = hasTinyMCE ? container.next().find('.editor') : container.next();
					container.find('textarea').appendTo(target).css('display', '').css('visibility', '');

					// Remove old (cloned) container box along with all the contents
					container.remove();
				}
				")."

				// Prepare the new textarea for attaching the HTML editor
				theArea = newField.find('.fc_'+boxClass).find('textarea');
				theArea.val('');
				theArea.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][text]');
				theArea.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				
				// Update the labels
				newField.find('label.labeltitle').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				newField.find('label.labeltitle').attr('id', '".$elementid."_'+uniqueRowNum".$field->id."+'_title-lbl');
				newField.find('label.labeltext').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				newField.find('label.labeltext').attr('id', '".$elementid."_'+uniqueRowNum".$field->id."+'_text-lbl');
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;

				// 2: means no DATA clean-up
				if (remove_previous && remove_previous!=2)
				{
					fc_removeAreaEditors(lastField.find('textarea'), 0);
				}
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);
				";
			
			// Attach a new JS HTML editor object
			if ($use_html) $js .= "

				if (hasCodeMirror)
				{
					var jsEditor = fc_attachCodeMirror(theArea, CMoptions);
				}
				else if (hasTinyMCE)
				{
					var jsEditor = fc_attachTinyMCE(theArea);
				}
				//window.console.log(jsEditor);
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

					// Remove known JS editors
					fc_removeAreaEditors( row.find('textarea'), 0 );

					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}

				// If not removing re-enable clicks
				else if (btn) btn.css('pointer-events', '').on('click');

				//if (typeof tinyMCE != 'undefined' && tinyMCE) window.console.log('Field \"".$field->label."\" # values: ' + rowCount".$field->id." + ' tinyMCE editor count: ' + tinyMCE.editors.length);
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="'.$add_on_class.' fcfield-delvalue'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="'.$add_on_class.' fcfield-drag-handle'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_before'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="'.$add_on_class.' fcfield-insertvalue fc_after'.($cparams->get('form_font_icons', 1) ? ' fcfont-icon' : '').'"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
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
					array('title' => $value, 'text' => '');
			}
			if ( empty($value['title']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$title = '
				<div class="fc_termtitle">
					<label id="'.$elementid_n.'_title-lbl" class="label label-info labeltitle" for="'.$elementid_n.'_title">'.$title_label.'</label>
					<input class="fcfield_textval termtitle '.($required ? ' required' : '').'" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" type="text" size="'.$title_size.'" maxlength="'.$title_maxlength.'"
						value="'.htmlspecialchars( @$value['title'], ENT_COMPAT, 'UTF-8' ).'" data-defvals="'.htmlspecialchars( $default_title, ENT_COMPAT, 'UTF-8' ).'"/>
				</div>';
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			//display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = array())
			$text = !$use_html ? '
				<textarea class="fcfield_textval termtext' .($required ? ' required' : ''). '" id="'.$elementid_n.'_text" name="'.$fieldname_n.'[text]" cols="'.$cols.'" rows="'.$rows.'">'
					.htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				' : $editor->display(
						$fieldname_n.'[text]', htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ), $width='100%', $height='100%', $cols, $rows,
						$show_buttons, $elementid_n.'_text'
				);
			
			$text = '
				<div class="fc_termtext">
					<label id="'.$elementid_n.'_text-lbl" class="label label-info labeltext" for="'.$elementid_n.'_text">'.$value_label.'</label>
					<div class="fcfield_box' .($required ? ' required_box' : ''). '" data-label_text="'.$field->label.'">
						'.$text.'
					</div>
				</div>';
			
			$field->html[] = '
				'.($use_ingroup ? '' : '
				<div class="'.$input_grp_class.' fc-xpended-btns">
					'.$move2.'
					'.$remove_button.'
					'.(!$add_position ? '' : $add_here).'
				</div>
				').'
				'.($use_ingroup ? '' : '<div class="fcclear"></div>').'
				'.$title.'
				'.$text.'
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
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		$clean_output = $field->parameters->get('clean_output', 0);
		$encode_output = $field->parameters->get('encode_output', 0);
		$use_html = (int) $field->parameters->get( 'use_html', 0 );
		
		// Term Title
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Term (description) Text
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Check for no values and no default value, and return empty display
		if ( empty($values) ) {
			if (!strlen($default_value)) {
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['title'] = $default_title;
			$values[0]['text']  = $default_value;
			$values[0] = serialize($values[0]);
		}
		
		
		// ******************************************
		// Language filter, clean output, encode HTML
		// ******************************************
		
		if ($clean_output) {
			$ifilter = $clean_output == 1 ? JFilterInput::getInstance(null, null, 1, 1) : JFilterInput::getInstance();
		}
		if (1)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for unserialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$v = !empty($value) ? @unserialize($value) : false;
					$value = ( $v !== false || $v === 'b:0;' ) ? $v :
						array('title' => $value, 'text' => '');
				}
				
				if ($lang_filter_values) {
					$value['title'] = JText::_($value['title']);
					$value['text']  = JText::_($value['text']);
				}
				if ($clean_output) {
					$value['title'] = $ifilter->clean($value['title'], 'string');
					$value['text']  = $ifilter->clean($value['text'], 'string');
				}
				if ($encode_output) {
					$value['title'] = htmlspecialchars( $value['title'], ENT_QUOTES, 'UTF-8' );
					$value['text']  = htmlspecialchars( $value['text'], ENT_QUOTES, 'UTF-8' );
				}
				if (!$use_html) {
					$value['text'] = nl2br(preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $value['text']));
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
			if ( !strlen($value['title']) && !$is_ingroup ) continue; // Skip empty if not in field group
			if ( !strlen($value['title']) ) {
				$field->{$prop}[$n++]	= '';
				continue;
			}
			
			$html = '
				<label class="fc_termtitle label label-success">'.$value['title'].'</label>
				<div class="fc_termdesc">'.$value['text'].'</div>';
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= $pretext . $html . $posttext;
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		
		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' )
			{
				// Apply field 's opening / closing texts
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
		
		// Server side validation
		$validation = $field->parameters->get( 'validation', 2 ) ;
		$use_html   = (int) $field->parameters->get( 'use_html', 0 );
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;
		$maxlength  = $use_html ? 0 : $maxlength;
		
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
					$v = array('title' => $v, 'text' => '');
				}
			}
			
			
			// **************************************************************
			// Validate data, skipping values that are empty after validation
			// **************************************************************
			
			$title = flexicontent_html::dataFilter($v['title'], $maxlength, 'HTML', 0);
			
			// Skip empty value, but if in group increment the value position
			if (!strlen($title))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}
			
			$newpost[$new] = array();
			$newpost[$new]['title'] = $title;
			$newpost[$new]['text']  = flexicontent_html::dataFilter($v['text'], $maxlength, $validation, 0);
			
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
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func='strip_tags');
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
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
}
