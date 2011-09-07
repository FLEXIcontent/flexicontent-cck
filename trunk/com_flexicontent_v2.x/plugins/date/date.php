<?php
/**
 * @version 1.0 $Id: date.php 714 2011-07-29 06:27:11Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.date
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsDate extends JPlugin
{
	function plgFlexicontent_fieldsDate( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_date', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsDate::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;

		// some parameter shortcuts
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$dateformat			= $field->parameters->get( 'date_format', '%Y-%m-%d' ) ;
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
		
		if ($multiple) {
			$document	= & JFactory::getDocument();

			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.drag".$field->id."'
					});			
				});
			";
			//$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";
			var curRowNum".$field->id."	= ".count($field->value).";
			var maxVal".$field->id."		= ".$maxval.";
			var test = 1;

			function addField".$field->id."(el) {
				if((curRowNum".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					thisNewField.getFirst().setProperty('value','');

					thisNewField.injectAfter(thisField);

					var input = thisNewField.getFirst();
					input.id = '".$field->name."_'+uniqueRowNum".$field->id.";
					var img = input.getNext();
					img.id = '".$field->name."_' +uniqueRowNum".$field->id." +'_img';
		
					Calendar.setup({
        				inputField:		'".$field->name."_'+uniqueRowNum".$field->id.",
        				ifFormat:		'%Y-%m-%d',
        				button:			'".$field->name."_' +uniqueRowNum".$field->id." +'_img',
        				align:			'Tl',
        				singleClick:	true
					});
    				
    					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.drag".$field->id."'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					curRowNum".$field->id."++;
					uniqueRowNum".$field->id."++;
					}
				}

			function deleteField".$field->id."(el) {
				if(curRowNum".$field->id." > 1) {

				var field	= $(el);
				var row		= field.getParent();
				var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
				
				fx.start({
					'height': 0,
					'opacity': 0			
					}).chain(function(){
						row.remove();
					});
				curRowNum".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 20px;
				width:500px;
				}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			span.drag'.$field->id.' img {
				margin: -4px 8px;
				cursor: move;
			}
			';
			$document->addStyleDeclaration($css);

			$move2 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n=0;
			$field->html = '<ul id="sortables_'.$field->id.'">';

			foreach ($field->value as $value) {
				$field->html .= '<li>' . JHTML::_('calendar', $value, 'custom['.$field->name.'][]', $field->name.'_'.$n, '%Y-%m-%d', 'class="'.$required.'"') . '<input class="fcbutton" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="drag'.$field->id.'">'.$move2.'</span></li>';
				$n++;
			}
			$field->html 	.=	'</ul>';
			$field->html 	.= '<input type="button" id="add'.$field->name.'" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';
		} else {
			$field->html	= '<div>' . JHTML::_('calendar', $field->value[0], 'custom['.$field->name.'][]', $field->name, '%Y-%m-%d', 'class="'.$required.'"') .'</div>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		if(!$post) return;
		
		$newpost = array();
		$new = 0;

		foreach ($post as $n=>$v)
		{
			if ($post[$n] != '')
			{
				$newpost[$new] = $post[$n];
			}
			$new++;
		}
		$post = $newpost;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'date') return;
		
		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$customdate			= $field->parameters->get( 'custom_date', '%Y-%m-%d' ) ; 
		$dateformat			= $field->parameters->get( 'date_format', $customdate ) ;
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;

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

			default:
			$separatorf = '&nbsp;';
			break;
		}
								
		// initialise property
		$field->{$prop} 	= array();

		$n=0;
		foreach ($values as $value) {
			// We must use timezone offset ZERO, because the date(-time) value is stored in its final value
			// AND NOT as GMT-0 which would need to be converted to localtime, if not specified the JHTML-date
			// will convert to local time using a timezone offset, giving erroneous output
			// J1.6+  CANNOT USE 0 as $timezone_offset, removed it ...
			$field->{$prop}[]	= $values[$n] ? JHTML::_('date', $values[$n], JText::_($dateformat)/*, $timezone_offset=0*/ ) : JText::_( 'FLEXI_NO_VALUE' );
			$n++;
		}
		$field->{$prop} = implode($separatorf, $field->{$prop});	
	}
}
