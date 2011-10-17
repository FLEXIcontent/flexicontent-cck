<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.text
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

class plgFlexicontent_fieldsText extends JPlugin{
	function plgFlexicontent_fieldsText( &$subject, $params ) {
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_text', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->field_type != 'text') return;
		plgFlexicontent_fieldsText::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item) {
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'text') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$size				= $field->parameters->get( 'size', 30 ) ;
		$default_value		= $field->parameters->get( 'default_value', '' ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;

		if($pretext) { $pretext = $remove_space ? '' : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? ' ' : ' ' . $posttext . ' '; }
		$required 	= $required ? ' required' : '';
		
		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		} elseif (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		} else {
			for ($n=0; $n<count($field->value); $n++) {
				$field->value[$n] = htmlspecialchars( $field->value[$n], ENT_QUOTES, 'UTF-8' );			
			}
		}
		
		if ($multiple) // handle multiple records
		{
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
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$js = "
			var curRowNum".$field->id."	= ".count($field->value).";
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((curRowNum".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx			 = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					thisNewField.getFirst().setProperty('value','');

					thisNewField.injectAfter(thisField);
		
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
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 20px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			span.drag'.$field->id.' img {
				margin: -4px 8px;
				cursor: move;
			}
			';
			$document->addStyleDeclaration($css);

			$move2 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n = 0;
			$field->html = '<ul id="sortables_'.$field->id.'">';

			foreach ($field->value as $value) {
				$field->html	.= '<li>'.$pretext.'<input name="'.$field->name.'[]" id="'.$field->name.'" class="inputbox'.$required.'" type="text" size="'.$size.'" value="'.$value.'"'.$required.' />'.$posttext.'<input class="fcbutton" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="drag'.$field->id.'">'.$move2.'</span></li>';
				$n++;
				}
			$field->html .=	'</ul>';
			$field->html .= '<input type="button" id="add'.$field->name.'" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';

		} else { // handle single records
			$field->html	= '<div>'.$pretext.'<input name="'.$field->name.'[]" id="'.$field->name.'" class="inputbox'.$required.'" type="text" size="'.$size.'" value="'.$field->value[0].'"'.$required.' />'.$posttext.'</div>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'text') return;
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
		
		// create the fulltext search index
		$searchindex = '';
		
		foreach ($post as $v)
		{
			$searchindex .= $v;
			$searchindex .= ' ';
		}

		$searchindex .= ' | ';

		$field->search = $field->issearch ? $searchindex : '';

		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsText::onIndexAdvSearch($field, $post);
		}
	}

	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'text') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='text';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','text','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'text') return;

		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }

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
		
		// initialise property
		$field->{$prop} = array();
		
		$n = 0;
		foreach ($values as $value) {
			$field->{$prop}[]	= $values[$n] ? $pretext.$values[$n].$posttext : '';
			$n++;
		}
		if($field->{$prop}) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
	
	function onFLEXIAdvSearch(&$field, $fieldsearch) {
		if($field->field_type!='text') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='text' AND ai.search_index like '%{$fsearch}%';";
			$db->setQuery($query);
			$objs = $db->loadObjectList(); // or die($db->getErrorMsg());
			//echo "<pre>"; print_r($objs);echo "</pre>"; 
			if ($objs===false) continue;
			$objs = is_array($objs)?$objs:array($objs);
			foreach($objs as $o) {
				$obj = new stdClass;
				$obj->item_id = $o->item_id;
				$obj->label = $field->label;
				$obj->value = $fsearch;
				$resultfields[] = $obj;
			}
		}
		//echo "<pre>"; print_r($resultfields);echo "</pre>"; 
		$field->results = $resultfields;
		//return $resultfields;
	}
}
