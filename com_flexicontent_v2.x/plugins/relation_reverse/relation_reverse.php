<?php
/**
 * @version 1.0 : relation_reverse.php
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.relation_reverse
 * @copyright (C) 2011 ggppdk
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsRelation_reverse extends JPlugin
{
	// ***********
	// CONSTRUCTOR
	// ***********
	static $field_types = array('relation_reverse');
	
	function plgFlexicontent_fieldsRelation_reverse( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_relation_reverse', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->html = '';
		$reverse_field = $field->parameters->get( 'reverse_field', 0) ;
		if ( !$reverse_field ) {
			$field->html = 'Field [id:'.$filter->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
			return;
		}
		
		$field->html = FlexicontentFields::getItemsList($field->parameters, $_items=null, $isform=1, $reverse_field, $field, $item);
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;
		
		if ($field->field_type == 'relation_reverse')
		{
			$reverse_field = $field->parameters->get( 'reverse_field', 0) ;
			if ( !$reverse_field ) {
				$field->{$prop} = 'Field [id:'.$field->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
				return;
			}
			$_itemids_catids = null;  // Always ignore passed items, the DB query will determine the items
		}
		else  // $field->field_type == 'relation')
		{
			// Compatibility with old values, we no longer serialize all values to one, this way the field can be reversed !!!
			$values = ( $field_data = @unserialize($values) ) ? $field_data : $field->value;
			// No related items, just return empty display
			if ( !$values || !count($values) ) return;
			
			$_itemids_catids = array();
			foreach($values as $i => $val) {
				list ($itemid,$catid) = explode(":", $val);
				$_itemids_catids[$itemid] = new stdClass();
				$_itemids_catids[$itemid]->itemid = $itemid;
				$_itemids_catids[$itemid]->catid = $catid;
				$_itemids_catids[$itemid]->value  = $val;
			}
		}
		
		$field->{$prop} = FlexicontentFields::getItemsList($field->parameters, $_itemids_catids, $isform=0, @ $reverse_field, $field, $item);
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;
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
	/*function onDisplayFilter(&$filter, $value='', $formName='adminForm')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		// some parameter shortcuts
		
		$field_id = $filter->parameters->get( 'reverse_field', 0 ) ;
		if (!$field_id) {
			$field->html = 'Filter Field [id:'.$filter->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
			return;
		}
		
		$db =& JFactory::getDBO();
		$field_elements= 'SELECT DISTINCT fir.item_id as value, i.title as text'
						 .' FROM #__content as i'
						 .' LEFT JOIN #__flexicontent_fields_item_relations as fir ON i.id=fir.item_id AND fir.field_id='.$field_id
						 ;
		$db->setQuery($query);
		$results = $db->loadObjectList();
		echo $db->getErrorMsg();
		
		if (!$results) {
			$filter->html = '';
		} else {
			$options = array();
			$options[] = JHTML::_('select.option', '', '-'.JText::_('All').'-');
			foreach($results as $result) {
				$options[] = JHTML::_('select.option', $result->value, $result->text);
			}
			$filter->html	= JHTML::_('select.genericlist', $options, 'filter_'.$filter->id, ' class="fc_field_filter" onchange="document.getElementById(\''.$formName.'\').submit();"', 'value', 'text', $value);
		}

	}*/
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	//  Build the order clause
	function _buildItemOrderBy($order)
	{
		$params = & $field->parameters;
		$filter_order		= '';
		$filter_order_dir	= '';
		
		if ($order) {
			switch ($order) {
				case 'date' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'ASC';
				break;
				case 'rdate' :
				$filter_order		= 'i.created';
				$filter_order_dir	= 'DESC';
				break;
				case 'modified' :
				$filter_order		= 'i.modified';
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
				case 'author' :
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
				case 'rauthor' :
				$filter_order		= 'u.name';
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
			}
			
		}

		if ($filter_order)
			$orderby = ' ORDER BY '.$filter_order.' '.$filter_order_dir.', i.title';
		else
			$orderby = ' ORDER BY i.title';

		return $orderby;
	}
	
}