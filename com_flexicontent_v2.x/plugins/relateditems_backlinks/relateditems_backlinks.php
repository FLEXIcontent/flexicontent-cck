<?php
/**
 * @version 1.0 $Id: relateditems_backlinks.php 1264 2012-05-04 15:55:52Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relateditems_backlinks
 * @copyright (C) 2011 ggppdk
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


class plgFlexicontent_fieldsRelateditems_backlinks extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsRelateditems_backlinks( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relateditems_backlinks', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, $item)
	{
		// execute the code only if the field type match the plugin type
	
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems_backlinks') return;
		
		//$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$addlink 			= $field->parameters->get( 'addlink' ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$reverse_field		= $field->parameters->get( 'reverse_field', 0) ;
		$displayway			= $field->parameters->get( 'displayway', 1 ) ;
		
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
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? $posttext : ' ' . $posttext; }

		if ($reverse_field) {
			$order = $field->parameters->get( 'orderby' );
			
			switch ($order) {
				case 'date' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
				default :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
			}
						
			$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';
			
			$db =& JFactory::getDBO();
			$field_elements= 'SELECT DISTINCT id as value, title as text'
							 .' FROM #__content as i'
							 .' LEFT JOIN #__flexicontent_fields_item_relations AS fi_rel ON i.id=fi_rel.item_id'
							 .' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid=fi_rel.item_id'
							 .' WHERE fi_rel.field_id='.$reverse_field
							 .' AND fi_rel.value='.$item->id
							 .$orderby
							 ;
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);

			$results = $db->loadObjectList();			
			
			if (!$results) {
				$field->html = JText::_('FLEXI_FIELD_NO_VALUE');;
			
			} else {

				$display = array();
				
				foreach($results as $result) {
					
							$display_text = $pretext;
							if($addlink){
								$link="<a href=\"".JRoute::_(FlexicontentHelperRoute::getItemRoute($result->value))."\" >";
								$display_text.=$link;
							}
							($displayway) ? $display_text.= $result->text : $display_text.= $result->value;
							//$display_text.= $result->text;
							if($addlink){
								$display_text.="</a>";
							}
							$display_text.= $posttext;
							$display[]= $display_text;
						
				}
			$field->html = $opentag . implode($separatorf, $display) . $closetag;
			}

		} else{
			$field->html = JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
				
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
	
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems_backlinks') return;
		
		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$addlink 			= $field->parameters->get( 'addlink' ) ;
		$separatorf			= $field->parameters->get( 'separatorf' ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$reverse_field		= $field->parameters->get( 'reverse_field', 18) ;
		$displayway			= $field->parameters->get( 'displayway', 1 ) ;
		
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
		
		if($pretext) 	{ $pretext 	= $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? $posttext : ' ' . $posttext; }

		if ($reverse_field) {

			$order = $field->parameters->get( 'orderby' );
			
			switch ($order) {
				case 'date' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'alpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
				case 'ralpha' :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'DESC';
				break;
				case 'hits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'ASC';
				break;
				case 'rhits' :
				$filter_order		= 'i.hits';
				$filter_order_dir	= 'DESC';
				break;
				case 'order' :
				$filter_order		= 'rel.ordering';
				$filter_order_dir	= 'ASC';
				break;
				default :
				$filter_order		= 'i.title';
				$filter_order_dir	= 'ASC';
				break;
			}
						
			$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';
			
			$db =& JFactory::getDBO();
			$field_elements= 'SELECT DISTINCT i.id as value, i.title as text, i.catid as catid,'
							 .' CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug,'
							 .' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
							 .' FROM #__content as i'
							 .' LEFT JOIN #__flexicontent_fields_item_relations AS fi_rel ON i.id=fi_rel.item_id'
							 .' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid=fi_rel.item_id'
							 .' LEFT JOIN #__categories AS c ON c.id = i.catid'
							 .' WHERE fi_rel.field_id='.$reverse_field
							 .' AND fi_rel.value='.$item->id
							 .$orderby
							 ;
						 
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();			
			if (!$results) {
				$field->{$prop} = '';
			
			} else {

				$display = array();
				
				foreach($results as $result) {
					
							$display_text = $pretext;
							if($addlink){
								$link="<a href=\"".JRoute::_(FlexicontentHelperRoute::getItemRoute($result->itemslug,$result->categoryslug))."\" >";
								$display_text.=$link;
							}
							($displayway) ? $display_text.= $result->text : $display_text.= $result->value;
							//$display_text.= $result->text;
							if($addlink){
								$display_text.="</a>";
							}
							$display_text.= $posttext;
							$display[]= $display_text;

				}
				
			$field->{$prop} = implode($separatorf, $display);
			$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			}

		} else{
			$field->html = JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
				
		}
		
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems_backlinks') return;
		if(!is_array($post) && !strlen($post)) return;
		
		// create the fulltext search index
		$searchindex = '';
		
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$remove_space		= $field->parameters->get( 'remove_space', 0 ) ;
		$reverse_field		= $field->parameters->get( 'reverse_field', 0) ;
		
		if($pretext) 	{ $pretext 	= $remove_space ? '' : $pretext . ' '; }
		if($posttext) 	{ $posttext	= $remove_space ? '' : ' ' . $posttext; }

		if ($reverse_field) {
			
			$db =& JFactory::getDBO();
			$field_elements= 'SELECT DISTINCT id as value, title as text'
							 .' FROM #__content as i'
							 .' LEFT JOIN #__flexicontent_fields_item_relations AS rel ON i.id=rel.item_id'
							 .' WHERE rel.field_id='.$reverse_field
							 .' AND i.state=1';
							 ;
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();

			if (!$results) {
				$display = '';
			
			} else {

				$display = array();
				foreach($results as $result) {
					for($n=0, $c=count($post); $n<$c; $n++) {
						if ($result->value == $post[$n]) {
							$display[] = $pretext . $result->text . $posttext;
						}
					}
				}
				$searchindex	 = implode(' ', $display);
				$searchindex	.= ' | ';
				$field->search = $field->issearch ? $searchindex : '';
			}

		} else { 
			$field->html = JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
		}			
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
	
	// Method to display a category filter for the category view
	function onDisplayFilter(&$filter, $value='')
	{
		/* execute the code only if the field type match the plugin type
		if($filter->field_type != 'relateditems_backlinks') return;
		// some parameter shortcuts
		
		$reverse_field				= $filter->parameters->get( 'reverse_field', 0 ) ;
		//echo $reverse_field;
		if ($reverse_field) {
			$db =& JFactory::getDBO();
			$field_elements= 'SELECT DISTINCT fir.item_id as value, i.title as text'
							 .' FROM #__content as i'
							 .' LEFT JOIN #__flexicontent_fields_item_relations as fir ON i.id=fir.item_id'
							 .' WHERE fir.field_id='.$reverse_field
							 ;
			$query = preg_match('#^select#i', $field_elements) ? $field_elements : '';
			$db->setQuery($query);
			$results = $db->loadObjectList();
			echo $db->getErrorMsg();
			if (!$results) {
				$field->html = '';
			
			} else {
			
				$options = array();
				$options[] = JHTML::_('select.option', '', '-'.JText::_('All').'-');
				foreach($results as $result) {
					$options[] = JHTML::_('select.option', $result->value, $result->text);
				}
				$filter->html	= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\'adminForm\').submit();"', 'value', 'text', $value);
			}

		} else {
			$field->html = JText::_('FLEXI_FIELD_NO_TYPE_SELECTED');
		}*/
	}
}