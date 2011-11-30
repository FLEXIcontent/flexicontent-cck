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

class plgFlexicontent_fieldsHidden extends JPlugin{
	function plgFlexicontent_fieldsHidden( &$subject, $params ) {
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_hidden', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->field_type != 'hidden') return;
		plgFlexicontent_fieldsText::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item) {
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'hidden') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$size				= $field->parameters->get( 'size', 30 ) ;
		$default_value		= $field->parameters->get( 'default_value', '' ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		//$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;

		if($pretext) { $pretext = $remove_space ? '' : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? ' ' : ' ' . $posttext . ' '; }
		$required 	= $required ? ' required' : '';
		
		// initialise property
		if($item->getValue('version', NULL, 0) < 2 && $default_value) {
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
		$usetextinform			= $field->parameters->get( 'usetextinform', 1 ) ;
		if($usetextinform) {
			$field->html	= '<div>'.$pretext.'<input name="custom['.$field->name.']" id="custom_'.$field->name.'" class="inputbox'.$required.'" type="text" size="'.$size.'" value="'.$field->value[0].'" />'.$posttext.'</div>';
		}else{
			$field->html	= '<input name="custom['.$field->name.']" id="custom_'.$field->name.'" type="hidden" value="'.$field->value[0].'" />';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'text') return;
		if(!$post) return;
		$newpost = array();
		$new = 0;

		if(!is_array($post)) $post = array ($post);
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
		if($field->field_type != 'hidden') return;
		
		$field->value = is_array($field->value) ? $field->value : array('');

		// some parameter shortcuts
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$remove_space			= $field->parameters->get( 'remove_space', 0 ) ;
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }

		$field->{$prop}	= $field->value[0] ? $pretext.$field->value[0].$posttext : '';
		$field->{$prop}  = $opentag . '<input type="hidden" name="custom['.$field->name.']" id="custom_'.$field->name.'" value="" />' . $closetag;
	}
	
	function onFLEXIAdvSearch(&$field, $fieldsearch) {
		if($field->field_type!='hidden') return;
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
				$resultfields = $obj;
			}
		}
		//echo "<pre>"; print_r($resultfields);echo "</pre>"; 
		$field->results = $resultfields;
		//return $resultfields;
	}
}
