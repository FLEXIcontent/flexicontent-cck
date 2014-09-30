<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsPhonenumbers extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsPhonenumbers( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_phonenumbers', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'phonenumbers') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$size			= $field->parameters->get( 'size', 30 ) ;
		
		$default_value_use 	= $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value     	= ($item->version == 0 || $default_value_use > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		$multiple   = $field->parameters->get( 'allow_multiple', 1 ) ;
		$max_values = $field->parameters->get( 'max_values', 0 ) ;
		$remove_space =$field->parameters->get( 'remove_space', 0 ) ;
		
		$required			= $field->parameters->get( 'required', 0 ) ;
		$required			= $required ? ' required' : '';
		
		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		} /*else {
			for ($n=0; $n<count($field->value); $n++) {
				$field->value[$n] = htmlspecialchars( $field->value[$n], ENT_QUOTES, 'UTF-8' );
			}
		}*/
		
		$document	= JFactory::getDocument();
		
		if ($multiple) // handle multiple records
		{
			if (!FLEXI_J16GE) $document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/sortables.js' );
			
			//add the drag and drop sorting feature
			$js = "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id."	= ".$max_values.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var thisField 	 = $(el).getPrevious().getLast();
				var thisNewField = thisField.clone();
				if (MooTools.version>='1.2.4') {
					var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
				} else {
					var fx = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
				}
				
				thisNewField.getChildren('input[type=text]').setProperty('value','');
				thisNewField.getElements('input.phonelabel').setProperty('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][label]');
				thisNewField.getElements('input.phonelabel').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_label');
					
				thisNewField.getElements('input.phonecc').setProperty('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][cc]');
				thisNewField.getElements('input.phonecc').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_cc');
					
				thisNewField.getElements('input.phonenum1').setProperty('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone1]');
				thisNewField.getElements('input.phonenum1').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone1');
					
				thisNewField.getElements('input.phonenum2').setProperty('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone2]');
				thisNewField.getElements('input.phonenum2').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone2');
					
				thisNewField.getElements('input.phonenum3').setProperty('name','custom[".$field->name."]['+uniqueRowNum".$field->id."+'][phone3]');
				thisNewField.getElements('input.phonenum3').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_phone3');
					
				thisNewField.injectAfter(thisField);
										
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag',
					containment: 'parent',
					tolerance: 'pointer'
				});

				fx.start({ 'opacity': 1 }).chain(function(){
					this.setOptions({duration: 600});
					this.start({ 'opacity': 0 });
					})
					.chain(function(){
						this.setOptions({duration: 300});
						this.start({ 'opacity': 1 });
					});

				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el)
			{
				var field	= $(el);
				
				if(rowCount".$field->id." > 0)
				{
					var row = field.getParent();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
					} else {
						var fx = row.effects({duration: 300, transition: Fx.Transitions.linear});
					}
					
					fx.start({
						'height': 0,
						'opacity': 0
						}).chain(function(){
							(MooTools.version>='1.2.4')  ?  row.destroy()  :  row.remove();
						});
					rowCount".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				height: auto;
				position: relative;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root(true).'/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$css = '';
		}
		
		$document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value) {
			$value = unserialize($value);
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'[]';
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name.'_'.$n : $field->name.'_'.$n;
			$field->html[]	= '
					<label class="label" for="'.$field->name.'_label_'.$n.'">'.JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_LABEL' ).':</label>
					<input class="fcfield_textval phonelabel" name="'.$fieldname.'[label]" id="'.$elementid.'_label_'.$n.'" type="text" size="'.$size.'" value="'.$value['label'].'" class="inputbox" />
					<label class="label" for="'.$field->name.'_cc_'.$n.'">'.JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_COUNTRY_CODE' ).':</label>
					<input class="fcfield_textval phonecc" name="'.$fieldname.'[cc]" id="'.$elementid.'_cc_'.$n.'" type="text" size="4" maxsize="4" value="'.$value['cc'].'" class="inputbox" />
					<label class="label" for="'.$field->name.'_phone1_'.$n.'">'.JText::_( 'PLG_FLEXICONTENT_FIELDS_PHONENUMBERS_PHONE_NUMBER' ).':</label>
					<input class="fcfield_textval phonenum1" name="'.$fieldname.'[phone1]" id="'.$elementid.'_phone1_'.$n.'" type="text" size="4" maxsize="4" value="'.$value['phone1'].'" class="inputbox" />
					<label class="label" for="'.$field->name.'_phone2_'.$n.'">-</label>
					<input class="fcfield_textval phonenum2" name="'.$fieldname.'[phone2]" id="'.$elementid.'_phone2_'.$n.'" type="text" size="4" maxsize="4" value="'.$value['phone2'].'" class="inputbox" />
					<label class="label" for="'.$field->name.'_phone3_'.$n.'">-</label>
					<input class="fcfield_textval phonenum3" name="'.$fieldname.'[phone3]" id="'.$elementid.'_phone3_'.$n.'" type="text" size="4" maxsize="4" value="'.$value['phone3'].'" class="inputbox" />
					'.$remove_button.'
					'.$move2;

			/*
			$field->html[] = '
				<input id="'.$elementid.'_'.$n.'" name="'.$fieldname.'" class="inputbox'.$required.'" type="text" size="'.$size.'" value="'.$value.'"'.$required.' />
				'.$remove_button.'
				'.$move2.'
				';
			*/
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';
		} else {  // handle single values
			$field->html = '<div>'.$field->html[0].'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'phonenumbers') return;
		
		$field->label = JText::_($field->label);
		
		// Get field values
		$values = $values ? $values : $field->value;
		$values = !is_array($values) ? array($values) : $values;     // make sure values is an array
		$isempty = !count($values) || !strlen($values[0]);           // detect empty value
		
		if($isempty) return;
		
		// some parameter shortcuts
		$field_prefix			= $field->parameters->get( 'field_prefix', '' ) ;
		$field_suffix			= $field->parameters->get( 'field_suffix', '' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$display_phone_label		= $field->parameters->get( 'display_phone_label', 1 ) ;
		$label_prefix		= $field->parameters->get( 'label_prefix', '' ) ;
		$label_suffix		= $field->parameters->get( 'label_suffix', '' ) ;
		$display_country_code		= $field->parameters->get( 'display_phone_label', 1 ) ;
		$country_code_prefix		= $field->parameters->get( 'country_code_prefix', '' ) ;
		$display_area_code		= $field->parameters->get( 'display_area_code', 1 ) ;
		$separator_cc_phone1		= $field->parameters->get( 'separator_cc_phone1', '' ) ;
		$separator_phone1_phone2		= $field->parameters->get( 'separator_phone1_phone2', '' ) ;
		$separator_phone2_phone3		= $field->parameters->get( 'separator_phone2_phone3', '' ) ;
		
		// Some variables
		$document	= JFactory::getDocument();
		$view 	= JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			$value = unserialize($value);
			$phone = $opentag;
			if($display_phone_label) {
				$phone .= $label_prefix.$value['label'].$label_suffix;
			}
			if($display_country_code) {
				$phone .= $country_code_prefix.$value['cc'].$separator_cc_phone1;
			}
			if($display_area_code) {
				$phone .= $value['phone1'].$separator_phone1_phone2;
			}
			$phone .= $value['phone2'].$separator_phone2_phone3.$value['phone3'].$closetag;
			$field->{$prop}[] = $phone;
			$n++;
		}
		
		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop}  = implode('', $field->{$prop});
			$field->{$prop}  = $field_prefix . $field->{$prop} . $field_suffix;
		} else {
			$field->{$prop} = '';
		}
		
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'phonenumbers') return;
		if(!is_array($post) && !strlen($post)) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n=>$v) {
			if ($post[$n]['label']!= '' || $post[$n]['cc']!='' || $post[$n]['phone1']!='' || $post[$n]['phone2']!='' || $post[$n]['phone3']!='' ) {
				$newpost[$new]['label']	= $post[$n]['label'];
				$newpost[$new]['cc']	= $post[$n]['cc'];
				$newpost[$new]['phone1']	= $post[$n]['phone1'];
				$newpost[$new]['phone2']	= $post[$n]['phone2'];
				$newpost[$new]['phone3']	= $post[$n]['phone3'];
			}
			$new++;
		}
		$post = $newpost;
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
		if($filter->field_type != 'phonenumbers') return;
		
		plgFlexicontent_fieldsText::onDisplayFilter($filter, $value, $formName);
	}
	
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'phonenumbers') return;

		// ** some parameter shortcuts
		$label_filter 		= $filter->parameters->get( 'display_label_filter', 0 ) ;
		if ($label_filter == 2) $text_select = $filter->label; else $text_select = JText::_('FLEXI_ALL');
		$filter->html = '';
		
		
		if ( !$filter->parameters->get( 'range', 0 ) ) {
			
			// *** Retrieve values
			// *** Limit values, show only allowed values according to category configuration parameter 'limit_filter_values'
			$force = JRequest::getVar('view')=='search' ? 'all' : 'default';
			$results = flexicontent_cats::getFilterValues($filter, $force);
			
			
			// *** Create the select form field used for filtering
			$options = array();
			$options[] = JHTML::_('select.option', '', '-'.$text_select.'-');
			
			foreach($results as $result) {
				if ( !strlen($result->value) ) continue;
				$options[] = JHTML::_('select.option', $result->value, JText::_($result->text));
			}
			if ($label_filter == 1) $filter->html  .= $filter->label.': ';
			$filter->html	.= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
		} else {
			//print_r($value);
			$size = (int)($filter->parameters->get( 'size', 30 ) / 2);
			$filter->html	.='<input name="filter_'.$filter->id.'[1]" class="fc_field_filter" type="text" size="'.$size.'" value="'.@ $value[1].'" /> - ';
			$filter->html	.='<input name="filter_'.$filter->id.'[2]" class="fc_field_filter" type="text" size="'.$size.'" value="'.@ $value[2].'" />'."\n";
		}
	}
	
	
	/*function getFiltered($field_id, $value, $field_type = '')
	{
		return array();
	}*/
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item) {
		if ($field->field_type != 'phonenumbers') return;
		if ( !$field->isadvsearch ) return;
		
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ($field->field_type != 'phonenumbers') return;
		if ( !$field->issearch ) return;
		
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array(), $search_properties=array(), $properties_spacer=' ', $filter_func=null);
		return true;
	}
	
		
	// Method to get ALL items that have matching search values for the current field id
	function onFLEXIAdvSearch(&$field)
	{
		if ($field->field_type != 'phonenumbers') return;
		
		FlexicontentFields::onFLEXIAdvSearch($field);
	}
	
}
