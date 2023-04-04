<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsTermlist extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';


		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $field->parameters->get( 'editor',  $user->getParam('editor', $app->getCfg('editor'))  );
		$editor  = JEditor::getInstance($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, ignored by most editors !!


		/**
		 * Number of values
		 */

		$multiple     = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values   = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);
		$required     = (int) $field->parameters->get('required', 0);
		$add_position = (int) $field->parameters->get('add_position', 3);

		// Classes for marking field required
		$required_class = $required ? ' required' : '';

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 0);


		/**
		 * Value handling
		 */

		// Label
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));

		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = strlen($default_title) ? JText::_($default_title) : '';


		/**
		 * Form field display parameters
		 */

		// Usage information
		$show_usage  = (int) $field->parameters->get( 'show_usage', 0 ) ;
		$field_notes = '';

		// Input field display size & max characters
		$title_size      = (int) $field->parameters->get( 'title_size', 80 ) ;
		$title_maxlength = (int) $field->parameters->get( 'title_maxlength', 0 ) ;


		/**
		 * Term text (description) (value handling)
		 */

		// Label
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));

		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = strlen($default_value) ? JText::_($default_value) : '';

		// Editing method, text editor or HTML editor
		$use_html = (int) $field->parameters->get( 'use_html', 0 );

		// *** Simple Textarea ***
		$rows  = $field->parameters->get( 'rows', 3 ) ;
		$cols  = $field->parameters->get( 'cols', 80 ) ;
		$maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)

		// *** HTML Editor configuration  ***
		$width = $field->parameters->get( 'width', '98%') ;
		if ( is_numeric($width) ) $width .= 'px';
		$height = $field->parameters->get( 'height', '250px' ) ;
		if ( is_numeric($height) ) $height .= 'px';

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
		if (!$field->value || (count($field->value) === 1 && reset($field->value) === null))
		{
			$field->value = array();
			$field->value[0]['title'] = $default_title;
			$field->value[0]['text']  = $default_value;
			$field->value[0] = serialize($field->value[0]);
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		// JS safe Field name
		$field_name_js = str_replace('-', '_', $field->name);

		// Workaround Joomla multi-editor issues
		$mce_fieldname = '_FC_FIELD_' . $field->name;

		// JS & CSS of current field
		$js = '';
		$css = '';

		// Handle multiple records
		if ($multiple)
		{
			// Add the drag and drop sorting feature
			if ($add_ctrl_btns) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					cancel: false,
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($fields_box_placing ? "
					,start: function(e) {
						//jQuery(e.target).children().css('float', 'left');
						//fc_setEqualHeights(jQuery(e.target), 0);
					}
					,stop: function(e) {
						//jQuery(e.target).children().css({'float': 'none', 'min-height': '', 'height': ''});
					}
					" : '')."
				});
			});
			";

			if ($max_values) JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;

				if(!remove_previous && (rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}

				// Find last container of fields and clone it to create a new container of fields
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				newField.find('.fc-has-value').removeClass('fc-has-value');

				// New element's field name and id
				var uniqueRowN = uniqueRowNum" . $field->id . ";
				var element_id = '" . $elementid . "_' + uniqueRowN;
				var fname_pfx  = '" . $fieldname . "[' + uniqueRowN + ']';
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$js .= "
				// Update the new term title
				var termtitle = newField.find('input.termtitle');
				var termtitle_dv = termtitle.attr('data-defvals');
				termtitle_dv && termtitle_dv.length ?
					termtitle.val(termtitle_dv) :
					termtitle.val('') ;
				termtitle.attr('name', fname_pfx + '[title]');
				termtitle.attr('id', element_id + '_title');

				// Update the new term description
				var boxClass = 'termtext';
				var container = newField.find('.fc_'+boxClass);
				var container_inner = newField.find('.fcfield_box');

				var txtArea   = container.find('textarea').first();
				var oldAreaID = txtArea.attr('id');
				var newAreaID = element_id;
				var regex_oldAreaID = new RegExp(oldAreaID, 'g');

				// ID of originally cloned textarea 
				var srcAreaID = txtArea.attr('data-original-id') ? txtArea.attr('data-original-id') : txtArea.attr('id');

				var hasTinyMCE = txtArea.hasClass('mce_editable');  //typeof tinyMCE === 'undefined' ? false : !!tinyMCE.get( srcAreaID );
				var hasCodeMirror = typeof CodeMirror === 'undefined' ? false : txtArea.next().hasClass('CodeMirror');

				".( !$use_html ? "" : "
				if (hasCodeMirror)  // CodeMirror case
				{
					// Get options not from copy but from the original DOM element
					var CMoptions = document.getElementById(srcAreaID).nextSibling.CodeMirror.options;

					// Cleanup the cloned HTML elements of the editor
					container.find('.CodeMirror').remove();
				}
				else   // tinyMCE / other editors
				{
					// Get options not from copy but from the original DOM element
					var MCEoptions = null;
					if (hasTinyMCE)
					{
						MCEoptions = tinyMCE.majorVersion >= 4 ?
							tinymce.get(srcAreaID).settings :
							tinymce.EditorManager.get(srcAreaID).settings;

						// Clear some options
						MCEoptions.id = null;
						MCEoptions.url_converter_scope = null;

						// tinyMCE of J3.7.0+
						if (typeof MCEoptions.setupCallbackString != 'undefined')
						{
							MCEoptions.target = null;
							MCEoptions.setup = null;
							MCEoptions.setupCallbackString = MCEoptions.setupCallbackString.replace(regex_oldAreaID, newAreaID);
						}
					}
					//window.console.log(MCEoptions);

					// Append a new container after the current textarea container
					container.after('<div class=\"'+ container.get(0).className +'\"></div>');

					// Copy label
					container.find('label.labeltext').appendTo(container.next());

					// Add inner container and copy only the textarea into the new container and make it visible
					var newBox = jQuery('<div class=\"'+ container_inner.get(0).className +'\"></div>')
					newBox.appendTo(container.next());

					var target;
					if (hasTinyMCE && tinyMCE.majorVersion >= 4)
					{
						jQuery('<div class=\"js-editor-tinymce\"></div>').appendTo(newBox);
						target = container.next().find('.js-editor-tinymce');
					}
					else
					{
						target = container.next();
					}
					container.find('textarea').appendTo(target).css('display', '').css('visibility', '');

					// Legacy (external) editor XTD-buttons, e.g. JCE, tinyMCE of < J3.7.0
					var editor_xtd_buttons = false;
					if (hasTinyMCE)
					{
						editor_xtd_buttons = container.find('#editor-xtd-buttons');
						if (editor_xtd_buttons.length)
						{
							editor_xtd_buttons.removeClass(function (index, className) {
								return (className.match(/(^|\s)fc_xtd_btns_-\S+/g) || []).join(' ');
							});
							editor_xtd_buttons.html(editor_xtd_buttons.html().replace(regex_oldAreaID, newAreaID));
							editor_xtd_buttons.addClass('fc_xtd_btns_' + element_id);
							editor_xtd_buttons.appendTo(target.parent());
						}
					}

					// Remove old (cloned) container box along with all the contents
					container.remove();
				}
				")."

				// Prepare the new textarea for attaching the HTML editor
				var mce_fieldname_text = '" . $mce_fieldname . "_text';
				var mce_fieldname_text_sfx = mce_fieldname_text ? '[' + mce_fieldname_text + ']' : '';
				var theArea = newField.find('.fc_'+boxClass).find('textarea');
				theArea.val('');
				theArea.attr('name', fname_pfx + '[text]' + mce_fieldname_text_sfx);
				theArea.attr('id', element_id + '_text');

				// Update the labels
				newField.find('label.labeltitle').attr('for', element_id + '_title');
				newField.find('label.labeltitle').attr('id', element_id + '_title-lbl');
				newField.find('label.labeltext').attr('for', element_id + '_text');
				newField.find('label.labeltext').attr('id', element_id + '_text-lbl');
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
					var jsEditor = fc_attachTinyMCE(theArea, MCEoptions, mce_fieldname_text);

					// Add click event to Legacy (external) editor XTD-buttons, e.g. JCE, tinyMCE of < J3.7.0
					if (!(tinyMCE.majorVersion >= 4) && editor_xtd_buttons && editor_xtd_buttons.length)
					{
						SqueezeBox.assign(jQuery('#editor-xtd-buttons.fc_xtd_btns_' + element_id + ' a.modal-button').get(), { parse: 'rel' });
					}
				}
				";

			// Add new element to sortable objects (if field not in group)
			if ($add_ctrl_btns) $js .= "
				//jQuery('#sortables_".$field->id."').sortable('refresh');  // Refresh was done appendTo ?
				";

			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800, function() { jQuery(this).css('opacity', ''); });

				// Enable tooltips on new element
				newField.find('.hasTooltip').tooltip({html: true, container: newField});
				newField.find('.hasPopover').popover({html: true, container: newField, trigger : 'hover focus'});

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}


			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Disable clicks on remove button, so that it is not reclicked, while we do the field value hide effect (before DOM removal of field value)
				var btn = fieldval_box ? false : jQuery(el);
				if (btn && rowCount".$field->id." > 1) btn.css('pointer-events', 'none').off('click');

				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');

				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});

				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if (rowCount".$field->id." > 1)
				{
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the field value hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-expand-view').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();

					// Remove known JS editors
					fc_removeAreaEditors( row.find('textarea'), 0 );

					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}

				//if (typeof tinyMCE != 'undefined' && tinyMCE) window.console.log('Field \"".$field->label."\" # values: ' + rowCount".$field->id." + ' tinyMCE editor count: ' + tinyMCE.editors.length);
			}
			";

			$css .= '';

			$remove_button = '<span class="' . $add_on_class . ' fcfield-delvalue ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="' . $add_on_class . ' fcfield-drag-handle ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_before ' . $font_icon_class . '" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="' . $add_on_class . ' fcfield-insertvalue fc_after ' . $font_icon_class . '"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		}

		// Field not multi-value
		else
		{
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();
		$n = 0;

		// These are unused in this field
		$skipped_vals = array();
		$per_val_js   = '';

		foreach ($field->value as $value)
		{
			// Compatibility for non-serialized values (e.g. reload user input after form validation error) or for NULL values in a field group
			if ( !is_array($value) )
			{
				$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
				$value = $array ?: array(
					'title' => $value, 'text' => ''
				);
			}

			// Joomla tinyMCE custom config bug workaround, (form reload, data from SESSION)
			if (isset($value['text'][$mce_fieldname . '_text']))
			{
				$value['text'] = $value['text'][$mce_fieldname . '_text'];
			}

			if ( empty($value['title']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group

			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			$title = '
				<div class="fc_termtitle">
					<label id="'.$elementid_n.'_title-lbl" class="label text-white bg-info label-info labeltitle" for="'.$elementid_n.'_title">'.$title_label.'</label>
					<input class="fcfield_textval termtitle' . $required_class . '" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" type="text" size="'.$title_size.'" '.($title_maxlength ? 'maxlength="'.$title_maxlength.'"' : '').'
						value="'.htmlspecialchars( @$value['title'], ENT_COMPAT, 'UTF-8' ).'" data-defvals="'.htmlspecialchars( $default_title, ENT_COMPAT, 'UTF-8' ).'"/>
				</div>';

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			//display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = array())
			$mce_fieldname_sfx_text = $mce_fieldname ? '[' . $mce_fieldname . '_text]' : '';
			$text = !$use_html ? '
				<textarea class="fcfield_textval termtext' . $required_class . '" id="'.$elementid_n.'_text" name="'.$fieldname_n.'[text]" cols="'.$cols.'" rows="'.$rows.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '').'>'
					.htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				' : $editor->display(
						$fieldname_n.'[text]' . $mce_fieldname_sfx_text, htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ), $width, $height, $cols, $rows,
						$skip_buttons_arr, $elementid_n.'_text', $_asset_ = null, $_author_ = null, $editor_plg_params
				);

			$text = '
				<div class="fc_termtext">
					<label id="'.$elementid_n.'_text-lbl" class="label text-white bg-info label-info labeltext" for="'.$elementid_n.'_text">'.$value_label.'</label>
					<div class="fcfield_box' . ($required ? ' required_box' : '') . '" data-label_text="'.$field->label.'">
						'.$text.'
					</div>
				</div>';

			$field->html[] = '
				' . (!$add_ctrl_btns ? '' : '
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

		// Add per value JS
		if ($per_val_js)
		{
			$js .= "
			jQuery(document).ready(function()
			{
				" . $per_val_js . "
			});
			";
		}

		// Add field's CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		// Do not convert the array to string if field is in a group
		if ($use_ingroup);

		// Handle multiple records
		elseif ($multiple)
		{
			$field->html = !count($field->html) ? '' :
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '
				<div class="input-append input-prepend fc-xpended-btns">
					<span class="fcfield-addvalue ' . $font_icon_class . ' fccleared" onclick="addField'.$field->id.'(jQuery(this).closest(\'.fc-xpended-btns\').get(0));" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'">
						'.JText::_( 'FLEXI_ADD_VALUE' ).'
					</span>
				</div>';
		}

		// Handle single values
		else
		{
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">
				' . (isset($field->html[-1]) ? $field->html[-1] : '') . $field->html[0] . '
			</div>';
		}

		if (!$use_ingroup)
		{
			$field->html = ($show_usage && $field_notes
				? ' <div class="alert alert-info fc-small fc-iblock">'.$field_notes.'</div><div class="fcclear"></div>'
				: ''
			) . $field->html;
		}

		if (count($skipped_vals))
		{
			$app->enqueueMessage( JText::sprintf('FLEXI_FIELD_DATE_EDIT_VALUES_SKIPPED', $field->label, implode(',',$skipped_vals)), 'notice' );
		}
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		// The current view is a full item view of the item
		$isMatchedItemView = static::$itemViewId === (int) $item->id;

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;

		$app = JFactory::getApplication();
		$view = $app->input->get('flexi_callview', $app->input->get('view', 'item', 'cmd'), 'cmd');

		// Value handling parameters
		$lang_filter_values = $field->parameters->get('lang_filter_values', 0);
		$clean_output       = $field->parameters->get('clean_output', 0);
		$encode_output      = $field->parameters->get('encode_output', 0);
		$use_html           = (int) $field->parameters->get( 'use_html', 0 );

		// Optionally limit and cut term (description) text, and also optionally display in modal popup window
		$cut_text        = $view=='item' ? 0 : $field->parameters->get('cut_text_catview', 0);
		$cut_text_length = $field->parameters->get('cut_text_length_catview', 200);
		$cut_text_display = $field->parameters->get('cut_text_display_catview', 0);
		$cut_text_display_btn_icon = JText::_($field->parameters->get('cut_text_display_btn_icon_catview', 'icon-paragraph-center'));
		$cut_text_display_btn_text = JText::_($field->parameters->get('cut_text_display_btn_text_catview', '...'));


		/**
		 * Default value
		 */

		// Term Title
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = strlen($default_title) ? JText::_($default_title) : '';

		// Term (description) Text
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = strlen($default_value) ? JText::_($default_value) : '';

		// Get field values
		$values = $values ? $values : $field->value;

		// Check for no values and no default value, and return empty display
		if (empty($values))
		{
			if (!strlen($default_value))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['title'] = $default_title;
			$values[0]['text']  = $default_value;
			$values[0] = serialize($values[0]);
		}


		/**
		 * Language filter, clean output, encode HTML
		 */

		if ($clean_output)
		{
			$ifilter = $clean_output == 1 ? JFilterInput::getInstance([], [], 1, 1) : JFilterInput::getInstance();
		}
		if (1)  // 1 because we will unserialize
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'title' => $value, 'text' => ''
					);
				}

				if ($lang_filter_values)
				{
					$value['title'] = JText::_($value['title']);
					$value['text']  = JText::_($value['text']);
				}
				if ($clean_output)
				{
					$value['title'] = $ifilter->clean($value['title'], 'string');
					$value['text']  = $ifilter->clean($value['text'], 'string');
				}
				if ($encode_output)
				{
					$value['title'] = htmlspecialchars( $value['title'], ENT_QUOTES, 'UTF-8' );
					$value['text']  = htmlspecialchars( $value['text'], ENT_QUOTES, 'UTF-8' );
				}
				if (!$use_html)
				{
					$value['text'] = nl2br(preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $value['text']));
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}


		/**
		 * Get common parameters like: itemprop, value's prefix (pretext), suffix (posttext), separator, value list open/close text (opentag, closetag)
		 * This will replace other field values and item properties, if such are found inside the parameter texts
		 */
		$common_params_array = $this->getCommonParams();
		extract($common_params_array);


		// CSV export: Create a simpler output
		if ($prop === 'csv_export')
		{
			$separatorf = ', ';
			$itemprop = false;
		}


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

		// Do not convert the array to string if field is in a group, and do not add: FIELD's opentag, closetag, value separator
		if (!$is_ingroup)
		{
			// Apply values separator
			$field->{$prop} = implode($separatorf, $field->{$prop});

			if ($field->{$prop} !== '')
			{
				// Apply field 's opening / closing texts
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;

				// Add microdata once for all values, if field -- is NOT -- in a field group
				if ($itemprop)
				{
					$field->{$prop} = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}. '</div>';
				}
			}
		}
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Joomla tinyMCE custom config bug workaround
		$mce_fieldname = '_FC_FIELD_' . $field->name;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';

		// Server side validation
		$validation = $field->parameters->get( 'validation', 2 ) ;
		$use_html   = (int) $field->parameters->get( 'use_html', 0 );
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;
		$maxlength  = $use_html ? 0 : $maxlength;
		$title_maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;


		// ***
		// *** Reformat the posted data
		// ***

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// Support for serialized user data, e.g. basic CSV import / export. (Safety concern: objects code will abort unserialization!)
			if ( /*$is_importcsv &&*/ !is_array($v) && $v )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'title' => $v, 'text' => ''
				);
			}

			// Joomla tinyMCE custom config bug workaround
			if (isset($v['text'][$mce_fieldname . '_text']))
			{
				$v['text'] = $v['text'][$mce_fieldname . '_text'];
			}

			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$title = flexicontent_html::dataFilter($v['title'] ?? '', $title_maxlength, 'HTML', 0);

			// Skip empty value, but if in group increment the value position
			if (!strlen($title))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			$newpost[$new] = array();
			$newpost[$new]['title'] = $title;
			$newpost[$new]['text']  = flexicontent_html::dataFilter($v['text'] ?? '', $maxlength, $validation, 0);

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
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	public function onAdvSearchDisplayFilter(&$filter, $value = '', $formName = 'searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	public function getFilteredSearch(&$filter, $value, $return_sql = true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH INDEX METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	public function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
	public function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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
