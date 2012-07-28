<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.email
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

class plgFlexicontent_fieldsEmail extends JPlugin
{
	function plgFlexicontent_fieldsEmail( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_email', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsEmail::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;

		// some parameter shortcuts
		$size				= $field->parameters->get( 'size', 30 ) ;
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$default_value		= $field->parameters->get( 'default_value', '' ) ;
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0]['addr'] = $default_value;
			$field->value[0]['text'] = '';
			$field->value[0] = serialize($field->value[0]);
		} elseif (!$field->value) {
			$field->value = array();
			$field->value[0]['addr'] = '';
			$field->value[0]['text'] = '';
			$field->value[0] = serialize($field->value[0]);
		}
		
		$document	= & JFactory::getDocument();
		
		if ($multiple) // handle multiple records
		{
			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});			
				});
			";
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";
			var curRowNum".$field->id."	= ".count($field->value).";
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((curRowNum".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					
					thisNewField.getElements('input.emailaddr').setProperty('value','');
					thisNewField.getElements('input.emailaddr').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][addr]');
					thisNewField.getElements('input.emailtext').setProperty('value','');
					thisNewField.getElements('input.emailtext').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][text]');

					thisNewField.injectAfter(thisField);
		
					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.fcfield-drag'
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
					}
				}

			function deleteField".$field->id."(el) {
				if(curRowNum".$field->id." > 1) {

				var field	= $(el);
				var row		= field.getParent();
				var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
				
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
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear:both;
				list-style: none;
				height: 20px;
				}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			li input.emailaddr, li input.emailtext, li input.fcfield-button {
				float:none;
			} 
			';
			$document->addStyleDeclaration($css);

			$move2 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n = 0;
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">';
			
			foreach ($field->value as $value) {
				if ( @unserialize($value)!== false || $value === 'b:0;' ) {
					$value = unserialize($value);
				} else {
					$value = array('addr' => $value, 'text' => '');
				}
				$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
				$field->html .= '
				<li>
					<label class="legende" for="'.$fieldname.'[addr]">'.JText::_( 'FLEXI_FIELD_EMAILADDRESS' ).':</label>
					<input class="emailaddr validate-email'.$required.'" name="'.$fieldname.'[addr]" type="text" size="'.$size.'" value="'.$value['addr'].'" />
					<label class="legende" for="'.$fieldname.'[text]">'.JText::_( 'FLEXI_FIELD_EMAILTITLE' ).':</label>
					<input class="emailtext'.$required.'" name="'.$fieldname.'[text]" type="text" size="'.$size.'" value="'.@$value['text'].'" />
					<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="fcfield-drag">'.$move2.'</span>
				</li>';
				$n++;
			}
			$field->html .=	'</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';

		} else { // handle single records
			$css = 'li input.emailaddr, li input.emailtext { float:none; }';
			$document->addStyleDeclaration($css);
			
			if ( @unserialize($field->value[0])!== false || $field->value[0] === 'b:0;' ) {
				$field->value[0] = unserialize($field->value[0]);
			} else {
				$field->value[0] = array('addr' => $field->value[0], 'text' => '');
			}
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.'][0]' : $field->name.'[0]';
			$field->html	= '<div>'
				.JText::_( 'FLEXI_FIELD_EMAILADDRESS' )
				.': <input name="'.$fieldname.'[addr]" class="emailaddr'.$required.'" type="text" size="'.$size.'" value="'.$field->value[0]['addr'].'" /> '
				.JText::_( 'FLEXI_FIELD_EMAILTITLE' )
				.': <input name="'.$fieldname.'[text]" class="emailtext'.$required.'" type="text" size="'.$size.'" value="'.@$field->value[0]['text'].'" />'
				.'</div>';
		}
	}

	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;
		if(!$post) return;
		
		$newpost = array();
		$new = 0;

		foreach ($post as $n=>$v)
		{
			if ($post[$n]['addr'] != '')
			{
				$newpost[$new] = $post[$n];
			}
			$new++;
		}
		$post = $newpost;

		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;

		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag				= $field->parameters->get( 'opentag', '' ) ;
		$closetag				= $field->parameters->get( 'closetag', '' ) ;
		$default_value_title =	JText::_( $field->parameters->get( 'default_value_title', '' ) );
						
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

			default:
			$separatorf = '&nbsp;';
			break;
		}

		$field->{$prop} = array();		
		foreach ($values as $value)
		{
			if ( empty($value) ) continue;
			
			// Compatibility for old unserialized values
			$value = (@unserialize($value)!== false || $value === 'b:0;') ? unserialize($value) : $value;
			if ( is_array($value) ) {
				$addr = $value['addr'];
				$text = $value['text'];
			} else {
				$addr = $value;
				$text = '';
			}
			
			// If a custom displayed text was not set above then set it to 'default_value_title'
			if (empty($text)) $text = $default_value_title;
			
			// Created cloacked email address with custom displayed text
			if ( !empty($text) ) {
				$field->{$prop}[]	= JHTML::_('email.cloak', $addr, 1, $text, 0);
			} else {
				$field->{$prop}[]	= JHTML::_('email.cloak', $addr);
			}
		}
		
		// Apply seperator and open/close tags
		if($field->{$prop}) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
}
