<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.textarea
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

class plgFlexicontent_fieldsTextarea extends JPlugin
{
	function plgFlexicontent_fieldsTextarea( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_textarea', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->field_type != 'textarea') return;
		$field_type = $field->field_type;
		$field->field_type =  'text';
		$field->parameters->set( 'size', $field->parameters->get( 'adv_size', 30 ) );
		plgFlexicontent_fieldsText::onDisplayField($field, $item);
		$field->field_type =  'textarea';
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;

		$editor 	= & JFactory::getEditor();
		
		// some parameter shortcuts
		$cols				= $field->parameters->get( 'cols', 75 ) ;
		$rows				= $field->parameters->get( 'rows', 20 ) ;
		$height				= $field->parameters->get( 'height', 400 ) ;
		$default_value			= $field->parameters->get( 'default_value' ) ;
		$use_html			= $field->parameters->get( 'use_html', 0 ) ;
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		} elseif (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}

		if ($use_html) {
			$field->value[0] = htmlspecialchars( $field->value[0], ENT_NOQUOTES, 'UTF-8' );
			$field->html	 = $editor->display( $field->name, $field->value[0], '100%', $height, $cols, $rows, array('pagebreak', 'readmore') );
		} else {
			$field->html	 = '<textarea name="' . $field->name . '" cols="'.$cols.'" rows="'.$rows.'" class="'.$required.'">';
			$field->html	.= $field->value[0];
			$field->html	.= '</textarea>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file ) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		if(!$post) return;
		
		// create the fulltext search index
		$searchindex = flexicontent_html::striptagsandcut($post) . ' | ';		
		$field->search = $field->issearch ? $searchindex : '';
		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			$this->onIndexAdvSearch($field, $post);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		$db = &JFactory::getDBO();
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='textarea';";
		$db->setQuery($query);
		$db->query();
		$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','textarea','0', ".$db->Quote($post).");";
		$db->setQuery($query);
		$db->query();
		return true;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display') {
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textarea') return;
		
		// some parameter shortcuts

		$use_html			= $field->parameters->get( 'use_html', 0 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;

		$values = $values ? $values : $field->value ;

		if ($values) {
			$field->{$prop}	 = $opentag;
			$field->{$prop}	.= $values ? ($use_html ? $values[0] : nl2br($values[0])) : '';
			$field->{$prop}	.= $closetag;
		} else {
			$field->{$prop}	 = '';
		}
	}
	
	function onFLEXIAdvSearch(&$field, $item_id, $fieldsearch) {
		if($field->field_type!='textarea') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		
		foreach($fieldsearch as $fsearch) {
			if((stristr($field->value, $fsearch)!== FALSE)) {
				//$items[] = $field->item_id;
				$obj = new stdClass;
				$obj->label = $field->label;
				$obj->value = $field->value;
				//$resultfields[$field->item_id][] = $obj;
				$resultfields[] = $obj;
				break;
			}
		}
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.item_id='{$item_id}' AND ai.extratable='textarea' AND ai.search_index like '%{$fsearch}%';";
			$db->setQuery($query);
			$objs = $db->loadObjectList();
			$objs = is_array($objs)?$objs:array();
			foreach($objs as $o) {
				$obj = new stdClass;
				$obj->label = $field->label;
				$obj->value = $fsearch;
				$resultfields[] = $obj;
			}
		}
		return $resultfields;
	}
}
