<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsWeblink extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if (!isset($field->formhidden_grp)) $field->formhidden_grp = $field->formhidden;
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;

		// Initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';


		// ***
		// *** Number of values
		// ***

		$multiple   = $use_ingroup || (int) $field->parameters->get('allow_multiple', 0);
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get('max_values', 0);

		$required = $field->parameters->get('required', 0);
		$required_class = $required ? ' required' : '';

		$add_position = (int) $field->parameters->get('add_position', 3);

		// If we are multi-value and not inside fieldgroup then add the control buttons (move, delete, add before/after)
		$add_ctrl_btns = !$use_ingroup && $multiple;
		$fields_box_placing = (int) $field->parameters->get('fields_box_placing', 1);
		$show_values_expand_btn = (int) $field->parameters->get('show_values_expand_btn', 1);

		// Link source mode. 0: is normal editing, 1: is editing of Joomla article links
		$link_source = (int) $field->parameters->get('link_source', 0);
		$show_values_expand_btn = $link_source === 0 ? $show_values_expand_btn : 0;

		// Form fields display parameters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 4000 ) ;   // client/server side enforced
		$allow_relative_addrs = (int) $field->parameters->get( 'allow_relative_addrs', 0 ) ;
		$inputmask	= $allow_relative_addrs ? '' : $field->parameters->get( 'inputmask', '' ) ;

		// create extra HTML TAG parameters for the form field
		$link_attribs = $field->parameters->get( 'extra_attributes', '' )
			. ($maxlength ? ' maxlength="' . $maxlength . '" ' : '')
			. ($size ? ' size="' . $size . '" ' : '')
			;

		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added)
		{
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}

		$link_classes = $required_class;
		if ($inputmask)
		{
			$link_attribs .= " data-inputmask=\" 'alias': '" . $inputmask . "' \" ";
			$link_classes .= ' has_inputmask';
		}
		$link_classes .= ' validate-url';


		// ***
		// *** Default values
		// ***

		// Legacy parameter names, weblink and extendended weblink were merged
		$this->checkLegacyParameters($field);

		// URL value
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($item->version == 0 || $link_usage > 0) ? $field->parameters->get( 'default_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';

		// URL title (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';

		// URL linking text (optional)
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($item->version == 0 || $text_usage > 0) ? $field->parameters->get( 'default_text', '' ) : '';
		$default_text = $default_text ? JText::_($default_text) : '';

		// URL class (optional)
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($item->version == 0 || $class_usage > 0) ? $field->parameters->get( 'default_class', '' ) : '';

		// URL id (optional)
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$id_usage   = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($item->version == 0 || $id_usage > 0) ? $field->parameters->get( 'default_id', '' ) : '';
		$default_id = $default_id ? JText::_($default_id) : '';

		// URL target
		$usetarget  = $field->parameters->get( 'use_target', 0 ) ;

		// URL Hits
		$usehits    = $field->parameters->get( 'use_hits', 1 ) ;


		// CSS class names
		$class_choices = $field->parameters->get( 'class_choices', '') ;
		if ($useclass==2)
		{
			$default_option = (object) array('value' => $default_class, 'label' => JText::_('FLEXI_DEFAULT'));
			$class_options = $this->getPropertyOptions($class_choices, $default_option);
		}

		// Initialise property with default value
		if (!$field->value)
		{
			$field->value = array();
			$field->value[0]['link']  = $default_link;
			$field->value[0]['title'] = $default_title;
			$field->value[0]['linktext']= $default_text;
			$field->value[0]['id']    = $default_id;

			$field->value[0]['class'] = '';  // Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			$field->value[0]['target']= '';  // Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			$field->value[0]['hits']  = 0;
			$field->value[0] = serialize($field->value[0]);
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= $fields_box_placing ? ' floated' : '';

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;


		// Joomla article links mode
		if ( $link_source == -1 )
		{
			$field->html = $use_ingroup ?
				array('<div class="alert alert-warning fc-small fc-iblock">Field is configured to use Joomla article links, please disable use in group</div>') :
				'_JOOMLA_ARTICLE_LINKS_HTML_';
			return;
		}


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
					/*containment: 'parent',*/
					tolerance: 'pointer'
					".($field->parameters->get('fields_box_placing', 1) ? "
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
				";

			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			// Update new URL's address
			$js .= "
				theInput = newField.find('input.urllink').first();
				theInput.val(".json_encode($default_link).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][link]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_link');
				newField.find('.urllink-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_link');

				// Update inputmask
				var has_inputmask = newField.find('input.has_inputmask').length != 0;
				if (has_inputmask)  newField.find('input.has_inputmask').inputmask();
				";

			if ($allow_relative_addrs === 2) $js .= "
				var nr = 0;
				newField.find('input.autoprefix').each(function() {
					var elem = jQuery(this);
					elem.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][autoprefix]');
					elem.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_autoprefix_'+nr);
					elem.next().removeClass('active');
					elem.prop('checked', false);
					elem.next().attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_autoprefix_'+nr);
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

			// Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			if ($useclass) $js .= "
				theField = newField.find('".($useclass==1 ? 'input' : 'select').".urlclass').first();
				theField.val('');
				theField.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][class]');
				theField.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_class');
				newField.find('.urlclass-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_class');
				";

			if ($useid) $js .= "
				theInput = newField.find('input.urlid').first();
				theInput.val(".json_encode($default_id).");
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][id]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_id');
				newField.find('.urlid-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_id');
				";

			// Do not load the default from viewing configuration !, this will allow re-configuring default in viewing configuration at any time
			if ($usetarget) $js .= "
				theInput = newField.find('select.urltarget').first();
				theInput.val('');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][target]');
				theInput.attr('target','".$elementid."_'+uniqueRowNum".$field->id."+'_target');
				newField.find('.urltarget-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_target');
				";

			if ($usehits) $js .="
				theInput = newField.find('input.urlhits').first();
				theInput.val('0');
				theInput.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][hits]');
				theInput.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_hits');
				newField.find('.urlhits-lbl').first().attr('for','".$elementid."_'+uniqueRowNum".$field->id."+'_hits');

				// Destroy any select2 elements
				var sel2_elements = newField.find('div.select2-container');
				if (sel2_elements.length)
				{
					sel2_elements.remove();
					newField.find('select.use_select2_lib').select2('destroy').show();
				}

				// Set hits to zero for new row value
				newField.find('span span').html('0');
				";

			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();

				// Attach form validation on new element
				fc_validationAttach(newField);

				// Re-init any select2 elements
				fc_attachSelect2(newField);
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

				// Attach bootstrap event on new element
				fc_bootstrapAttach(newField);

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
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ jQuery(this).remove(); });
					rowCount".$field->id."--;
				}
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


		// Added field's custom CSS / JS
		if ($multiple) $js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
		";
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);



		// ***
		// *** Create field's HTML display for item form
		// ***

		$field->html = array();

		$formlayout = $field->parameters->get('formlayout', '');
		$formlayout = $formlayout ? 'field_'.$formlayout : 'field_InlineBoxes';
		$simple_form_layout = $field->parameters->get('simple_form_layout', 0);

		//$this->setField($field);
		//$this->setItem($item);
		//$this->displayField( $formlayout );

		include(self::getFormPath($this->fieldtypes[0], $formlayout));

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
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}

		// Add toggle button for: Compact values view (= multiple values per row)
		$show_values_expand_btn = $formlayout === 'field_InlineBoxes' ? $show_values_expand_btn : 0;
		if (!$use_ingroup && $show_values_expand_btn)
		{
			$field->html = '
			<span class="fcfield-expand-view-btn btn btn-small" onclick="fc_toggleCompactValuesView(this, jQuery(this).closest(\'.container_fcfield\'));" data-expandedFieldState="0">
				<span class="fcfield-expand-view ' . $font_icon_class . '" title="'.JText::_( 'FLEXI_EXPAND_VALUES', true ).'"></span> &nbsp;'.JText::_( 'FLEXI_EXPAND_VALUES', true ).'
			</span>
			' . $field->html;
		}
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Get isMobile / isTablet Flags
		static $isMobile = null;
		static $isTablet = null;
		static $useMobile = null;
		if ($useMobile===null)
		{
			$force_desktop_layout = JComponentHelper::getParams( 'com_flexicontent' )->get('force_desktop_layout', 0 );

			$mobileDetector = flexicontent_html::getMobileDetector();
			$isMobile = $mobileDetector->isMobile();
			$isTablet = $mobileDetector->isTablet();
			$useMobile = $force_desktop_layout  ?  $isMobile && !$isTablet  :  $isMobile;
		}

		$field->label = JText::_($field->label);

		// Some variables
		$is_ingroup  = !empty($field->ingroup);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		$multiple    = $use_ingroup || (int) $field->parameters->get( 'allow_multiple', 0 ) ;

		// Link source mode. 0: is normal editing, 1: is editing of Joomla article links
		$link_source = (int) $field->parameters->get('link_source', 0);
		$multiple = $link_source==-1 ? 1 : $multiple;

		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);

		// some parameter shortcuts
		$tooltip_class = 'hasTooltip';


		// ***
		// *** Default values
		// ***

		// Legacy parameter names, weblink and extendended weblink were merged
		$this->checkLegacyParameters($field);

		// URL value
		$link_usage   = $field->parameters->get( 'link_usage', 0 ) ;
		$default_link = ($link_usage == 2) ? $field->parameters->get( 'default_link', '' ) : '';
		$default_link = $default_link ? JText::_($default_link) : '';

		// URL title (optional)
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';

		// URL linking text (optional)
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($text_usage == 2)  ?  $field->parameters->get( 'default_text', '' ) : '';
		$default_text = $default_text ? JText::_($default_text) : '';

		// URL class (optional)
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($class_usage == 2)  ?  $field->parameters->get( 'default_class', '' ) : '';

		// URL id (optional)
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$id_usage	  = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($id_usage == 2)  ?  $field->parameters->get( 'default_id', '' ) : '';
		$default_id = $default_id ? JText::_($default_id) : '';

		// URL target && rel-nofollow
		$usetarget      = $field->parameters->get( 'use_target', 0 ) ;
		$default_target = $field->parameters->get( 'target', '' );
		$add_rel_nofollow = $field->parameters->get( 'add_rel_nofollow', 0 );

		// URL Hits
		$display_hits = $field->parameters->get( 'display_hits', 0 ) ;
		$add_hits_img = $display_hits == 1 || $display_hits == 3;
		$add_hits_txt = $display_hits == 2 || $display_hits == 3 || $isMobile;

		// Compatibility with old layouts
		$target_param = $default_target ? ' target="'.$default_target.'" ' : '';
		$rel_nofollow = $add_rel_nofollow ? ' rel="nofollow" ' : '';

		// Get field values
		$values = $values ? $values : $field->value;

		// Joomla article links mode
		if ( $link_source == -1 )
		{
			$usetitle = false;
			$usetext = true;
			$useclass = false;
			$usetarget = true;
			$useid = false;
			$values = array();

			$target_remap = array(
				'', $default_target,
				'0' => '_self',   // current window / frame /tab
				'1' => '_blank',  // new window
				'2' => '_popup',  // use single (shared) popup window, that opens with onclick event using window.open()
				'3' => '_modal',  // use modal popup window
			);

			if ( $item->urls )
			{
				if (!is_object($item->urls))
				{
					try
					{
						$item->urls = new JRegistry($item->urls);
					}
					catch (Exception $e)
					{
						$item->urls = flexicontent_db::check_fix_JSON_column('urls', 'content', 'id', $item->id);
					}
				}
				//echo "<pre>"; print_r($item->urls); echo "</pre>"; exit;

				$c_arr = array('a', 'b', 'c');
				foreach ($c_arr as $c)
				{
					if ($url = $item->urls->get('url'.$c, null))
					{
						$values[] = serialize(array(
							'link' => $url,
							'linktext' => $item->urls->get('url'.$c.'text', null),
							'target' => $target_remap[$item->urls->get('target'.$c, 0)]
						));
					}
				}
				//echo "<pre>"; print_r($values); echo "</pre>"; exit;
			}
		}


		// Check for no values and no default value, and return empty display
		if ( empty($values) )
		{
			if (!strlen($default_link))
			{
				$field->{$prop} = $is_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['link']     = $default_link;
			$values[0]['title']    = $default_title;
			$values[0]['linktext'] = $default_text;
			$values[0]['class']    = $default_class;
			$values[0]['id']       = $default_id;
			$values[0]['target']   = '';  // do not set viewing default !, this will allow re-configuring default in viewing at any time ...
			$values[0]['hits']     = 0;
			$values[0] = serialize($values[0]);
		}

		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
						'link' => $value, 'title' => '', 'linktext' => '', 'class' => '', 'id' => '', 'hits' => 0
					);
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


		// CSV export: Create customized output and return
		if ($prop === 'csv_export')
		{
			$separatorf = ' | ';
			$itemprop = false;

			$csv_export_text = $field->parameters->get('csv_export_text', '{{title}} {{link}}');
			$field_matches = null;

			$result = preg_match_all("/\{\{([a-zA-Z_0-9-]+)\}\}/", $csv_export_text, $field_matches);
			$propertyNames = $result ? $field_matches[1] : array();

			$field->{$prop} = array();

			foreach ($values as $value)
			{
				$output = $csv_export_text;

				foreach ($propertyNames as $pname)
				{
					$output = str_replace('{{' . $pname . '}}', (isset($value[$pname]) ? $value[$pname] : ''), $output);
				}

				$field->{$prop}[] = $output;
			}

			// Apply values separator, creating a non-array output regardless of fieldgrouping, as fieldgroup CSV export is not supported
			$field->{$prop} = implode($separatorf, $field->{$prop});

			return;
		}


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_default';

		// Create field's HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));

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



	// ***
	// *** METHODS HANDLING events on field values
	// ***

	// Method to execute a task when an action on a value is performed
	function onFieldValueAction_FC(&$field, $item, $value_order, $config)
	{
		/**
		 * IMPORTANT:
		 * If you add EVENT 'onFieldValueAction_FC' to a SYSTEM plugin use $handled_types = array('weblink')
		 */
		$handled_types = static::$field_types;

		if (!in_array($field->field_type, $handled_types))
		{
			return;
		}

		/**
		 * Use $field->id, $item, $value_order, $config to decide on making an action
		 * Typical config array data is:

			$config = array(
				'task' => 'default'
			);
		*/

		//echo '<pre>' . get_class($this) . '::' . __FUNCTION__ . "()\n\n"; print_r($config); echo '</pre>'; die('TEST code reached exiting');

		/**
		 * false is failure, indicates abort further actions
		 * true is success
		 * null is no work done
		 */
		return null;
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$this->setField($field);
		$this->setItem($item);

		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;

		// Get configuration
		$app  = JFactory::getApplication();
		$is_importcsv = $app->input->get('task', '', 'cmd') == 'importcsv';
		$allow_relative_addrs = (int) $field->parameters->get( 'allow_relative_addrs', 0 ) ;
		$domain = JUri::getInstance('SERVER')->gethost();

		// URL title (optional)
		$usetitle   = $field->parameters->get( 'use_title', 0 ) ;
		$usetext    = $field->parameters->get( 'use_text', 0 ) ;
		$useclass   = $field->parameters->get( 'use_class', 0 ) ;
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$usetarget  = $field->parameters->get( 'use_target', 0 ) ;
		$usehits    = $field->parameters->get( 'use_hits', 1 ) ;

		// Server side validation
		$maxlength  = (int) $field->parameters->get( 'maxlength', 4000 ) ;

		$db_values_arr = $this->getExistingFieldValues();
		$db_values = array();
		foreach($db_values_arr as $db_value)
		{
				$array = $this->unserialize_array($db_value, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'link' => $v, 'title' => '', 'linktext' => '', 'class' => '', 'id' => '', 'hits' => 0
				);
				$db_values[$v['link']] = $v;
		}


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
			if ( $is_importcsv && !is_array($v) )
			{
				$array = $this->unserialize_array($v, $force_array=false, $force_value=false);
				$v = $array ?: array(
					'link' => $v, 'title' => '', 'linktext' => '', 'class' => '', 'id' => '', 'hits' => 0
				);
			}

			// Sanitize the URL as absolute or relative
			$force_absolute = $allow_relative_addrs === 0 || ($allow_relative_addrs === 2 && (int) @ $v['autoprefix']);
			$double_slash_without_proto = strpos($v['link'], '//') === 0;


			// ***
			// *** Validate data, skipping values that are empty after validation
			// ***

			$link = flexicontent_html::dataFilter($v['link'], $maxlength, 'URL', 0);  // Clean bad text/html

			// Restore double slash without protocol, if this was removed
			$link = $link && $double_slash_without_proto && strpos($link, '//') !== 0
				? '//' . $link
				: $link;

			// Absolute path without protocol, port, domain (subfolder only) and with them
			$Abs_Path = JUri::root(true) . '/';
			$Abs_Path_Full = JUri::root();

			// Remove joomla uri root to make it relative if relative allowed but an absolute URL was given
			if ( !$force_absolute )
			{
				if (strpos($link, $Abs_Path) === 0)
				{
					$link = substr($link, strlen($Abs_Path));
				}
				if (strpos($link, $Abs_Path_Full) === 0)
				{
					$link = substr($link, strlen($Abs_Path_Full));
				}
			}

			// Force full joomla uri root to make it absolute
			else
			{
				if (strpos($link, $Abs_Path) === 0)
				{
					$link = $Abs_Path_Full . substr($link, strlen($Abs_Path));
				}
			}

			// Skip empty value, but if in group increment the value position
			if (!strlen($link))
			{
				if ($use_ingroup) $newpost[$new++] = null;
				continue;
			}

			// Is absolute with protocol, NOTHING TO DO
			if ( parse_url($link, PHP_URL_SCHEME) ) $prefix = '';

			// Is absolute without protocol, NOTHING TO DO
			else if (strpos($link, '//') === 0) $prefix = '';

			// Has current domain but no protocol
			// - just add // instead of 'http://' (to allow using current protocol)
			else if (strpos($link, $domain) === 0) $prefix = '//';

			// Relative URL and Relative URLs are allowed (and no-autoprefix flag was set in the form)
			// - do not add Joomla ROOT, to allow website to be moved and change subfolder
			else if ( !$force_absolute ) $prefix = '';

			// Relative URL but absolute URLs are forced,
			// - either add Joomla uri root (if prefixed with 'index.php')
			// - or add the default protocol 'http://' (assuming the URL is a domain+path)
			else
			{
				if (substr($link, 0, 10) === '/index.php')
				{
					$link = substr($link, 1);
				}
				$prefix = substr($link, 0, 9) === 'index.php'
					? JUri::root()
					: 'http://';
			}

			$prefixed_link = empty($link) ? '' : $prefix . $link;
			//echo $v['link'] . ($force_absolute ? ' (ABSOLUTE)' : ' (RELATIVE)') . '<br/>';
			//echo $link . '<br/>';
			//echo $prefixed_link  . '<br/><br/>';

			$newpost[$new] = array();
			$newpost[$new]['link'] = $prefixed_link;

			// Validate other value properties
			$newpost[$new]['title']   = !$usetitle  ? '' : flexicontent_html::dataFilter(@$v['title'], 4000, 'STRING', 0);
			$newpost[$new]['linktext']= !$usetext   ? '' : flexicontent_html::dataFilter(@$v['linktext'], 4000, 'STRING', 0);
			$newpost[$new]['class']   = !$useclass  ? '' : flexicontent_html::dataFilter(@$v['class'], 200, 'STRING', 0);
			$newpost[$new]['id']      = !$useid     ? '' : flexicontent_html::dataFilter(@$v['id'], 200, 'STRING', 0);
			$newpost[$new]['target']  = !$usetarget ? '' : flexicontent_html::dataFilter(@$v['target'], 200, 'STRING', 0);

			// Hits come only from DB and not via posted data
			$newpost[$new]['hits']    = isset($db_values[$prefixed_link]) ? (int) @ $db_values[$prefixed_link]['hits'] : 0;

			$new++;
		}
		$post = $newpost;

		// Serialize multi-property data before storing them into the DB,
		// null indicates to increment valueorder without adding a value
		foreach($post as $i => $v)
		{
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



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;

		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($filter, $value, $return_sql);
	}



	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
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
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('link','title'), $search_properties=array('title'), $properties_spacer=' ', $filter_func=null);
		return true;
	}


	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
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



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	// Get a url without the protocol
	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}


	// Get a url without the protocol
	function checkLegacyParameters($field)
	{
		// Legacy parameter names, weblink and extendended weblink were merged
		if (
			$field->parameters->get('default_link_usage')!==null ||
			$field->parameters->get('default_value_link')!==null ||
			$field->parameters->get('default_value_title')!==null)
		{
			$this->setField($field);
			$this->setItem($item);
			$this->renameLegacyFieldParameters(array(
				'default_link_usage'=>'link_usage',
				'default_value_link'=>'default_link',
				'default_value_title'=>'default_title'
			));
		}
	}


	// Prefix a url with protocol and with current host if not already absolute
	function make_absolute_url($link)
	{
		return flexicontent_html::make_absolute_url($link);
	}
}
