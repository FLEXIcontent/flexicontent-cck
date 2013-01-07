<?php
/**
 * @version 1.0 : relateditems_backlinks.php
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
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems_backlinks') return;
		
		$field->html = '';
		if ( !$field->parameters->get( 'reverse_field', 0) ) {
			$field->html = 'Field [id:'.$filter->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
			return;
		}
		
		// Execute query to get item list data 
		$db = & JFactory::getDBO();
		$query = $this->_createItemsQuery($field, $item, $isform=1, $values=null);
		$db->setQuery($query);
		$item_list = & $db->loadObjectList('id');
		$field->value_item_list = & $item_list;
		
		if ($db->getErrorNum()) {
			//echo $db->getErrorMsg();
			$field->html = 'Field [id:'.$filter->id.'] : SQL query error: ';
			return;
		}
		
		// No published related items or SQL query failed, return
		if ( !$item_list ) return;
		return;
		
		$field->html = $this->_createItemsList($field, $item, $values=null, $item_list, $isform=1);
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'relateditems_backlinks') return;
		
		$field->label = JText::_($field->label);
		$field->{$prop} = '';
		$values = $values ? $values : $field->value;
		
		
		if ( !$field->parameters->get( 'reverse_field', 0) ) {
			$field->html = 'Field [id:'.$filter->id.'] : '.JText::_('FLEXI_FIELD_NO_FIELD_SELECTED');
			return;
		}
		
		// Execute query to get item list data 
		$db = & JFactory::getDBO();
		$query = $this->_createItemsQuery($field, $item, $isform=0, $values);
		$db->setQuery($query);
		$item_list = & $db->loadObjectList('id');
		$field->value_item_list = & $item_list;
		//echo "<pre>"; print_r($item_list); echo "</pre>";
		
		if ($db->getErrorNum()) {
			//echo $db->getErrorMsg();
			$field->{$prop} = 'Field [id:'.$field->id.'] : SQL query error: '.$db->getErrorMsg();
			return;
		}
		
		// No published related items or SQL query failed, return
		if ( !$item_list ) return;
		
		$field->{$prop} = $this->_createItemsList($field, $item, $values, $item_list, $isform=0);
	}
	
	
	// Helper private method to create SQL query for retrieving items list data
	function &_createItemsQuery(&$field, &$item, $isform=0, $values=null)
	{
		$db = & JFactory::getDBO();
		$reverse_field = $field->parameters->get( 'reverse_field', 0) ;
		$order = $field->parameters->get( $isform ? 'orderby_form' : 'orderby', 'alpha' );
		
		// Get data like aliases and published state
		$publish_where = '';
		if ($field->parameters->get('use_publish_dates', 1 )) {
			$nullDate	= $db->getNullDate();
			$mainframe =& JFactory::getApplication();
			$now		= $mainframe->get('requestTime');
			$publish_where  = ' AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$db->Quote($now).' )'; 
			$publish_where .= ' AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$db->Quote($now).' )';
		}
		
		$orderby = $this->_buildItemOrderBy($order);
		//$query = 'SELECT i.title, i.id, i.alias, i.state, i.catid, '
		$query = 'SELECT i.*, ext.type_id,'."\n"
			.' GROUP_CONCAT(c.id SEPARATOR  ",") AS catidlist, '."\n"
			.' GROUP_CONCAT(c.alias SEPARATOR  ",") AS  cataliaslist '."\n"
			.' FROM #__content AS i '."\n"
			.' JOIN #__flexicontent_items_ext AS ext ON i.id=ext.item_id '."\n"
			.' JOIN #__flexicontent_fields_item_relations AS fi_rel'."\n"
			.'  ON i.id=fi_rel.item_id AND fi_rel.field_id=' .$reverse_field. ' AND CAST(fi_rel.value AS UNSIGNED)=' .$item->id."\n"
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON i.id=rel.itemid '."\n"
			.' LEFT JOIN #__categories AS c ON c.id=rel.catid '."\n"
			.' LEFT JOIN #__users AS u ON u.id = i.created_by'."\n"
			.' WHERE 1 '."\n"
			. $publish_where
			.' GROUP BY i.id '."\n"
			. $orderby."\n"
			;
		//echo "<pre>".$query."</pre>";
		return $query;
	}
	
	
	//  Build the order clause
	function &_createItemsList(&$field, &$item, $values, &$item_list, $isform=0)
	{
		$db = & JFactory::getDBO();
		global $globalcats;
		global $globalnoroute;
		if (!is_array($globalnoroute)) $globalnoroute = array();
		
		// Get fields of type relateditems
		static $related_items_fields = null;
		if ($related_items_fields===null) {
			$query = "SELECT name FROM #__flexicontent_fields WHERE field_type='relateditems'";
			$db->setQuery($query);
			$field_name_col = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			$related_items_fields = !$field_name_col ? array() : array_flip($field_name_col);
		}
		
		// some parameter shortcuts
		$remove_space	= $field->parameters->get( 'remove_space', 0 ) ;
		$pretext			= $field->parameters->get( $isform ? 'pretext_form' : 'pretext', '' ) ;
		$posttext			= $field->parameters->get( $isform ? 'posttext_form' : 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( $isform ? 'separator' : 'separatorf' ) ;
		$opentag			= $field->parameters->get( $isform ? 'opentag_form' : 'opentag', '' ) ;
		$closetag			= $field->parameters->get( $isform ? 'closetag_form' : 'closetag', '' ) ;
		$relitem_html = $field->parameters->get( $isform ? 'relitem_html_form' : 'relitem_html', '__display_text__' ) ;
		$displayway		= $field->parameters->get( $isform ? 'displayway_form' : 'displayway', 1 ) ;
		$addlink 			= $field->parameters->get( $isform ? 'addlink_form' : 'addlink', 1 ) ;
		$addtooltip		= $field->parameters->get( $isform ? 'addtooltip_form' : 'addtooltip', 1 ) ;
		
		// Parse and identify custom fields
		$result = preg_match_all("/\{\{([a-zA-Z_0-9]+)(##)?([a-zA-Z_0-9]+)?\}\}/", $relitem_html, $field_matches);
		if ($result) {
			$custom_field_names   = $field_matches[1];
			$custom_field_methods = $field_matches[3];
		} else {
			$custom_field_names   = array();
			$custom_field_methods = array();
		}
		
		/*echo "Fields for Related Items List: "; $sep = "";
		foreach ($custom_field_names as $i => $custom_field_name) {
			echo $sep . $custom_field_names[$i] . ($custom_field_methods[$i] ? "->". $custom_field_methods[$i] : ""); $sep = " , ";
		}
		echo "<br/>\n";*/
		
		// Parse and identify language strings and then make language replacements
		$result = preg_match_all("/\%\%([^%]+)\%\%/", $relitem_html, $translate_matches);
		if ($result) {
			$translate_strings = $translate_matches[1];
		} else {
			$translate_strings = array('FLEXI_READ_MORE_ABOUT');
		}
		foreach ($translate_strings as $translate_string) {
			$relitem_html = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $relitem_html);
		}
		
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
		if($posttext) { $posttext	= $remove_space ? $posttext : ' ' . $posttext; }
		
		
		global $fc_run_times;
		$fc_run_times['render_subfields'][$item->id."_".$field->id] = 0;
		
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			$itemslug = $result->id.":".$result->alias;
			$catslug = "";
			
			// Check if removed from category or inside a noRoute category or inside a non-published category
			// and use main category slug or other routable & published category slug
			$catid_arr = explode(",", $result->catidlist);
			$catalias_arr = explode(",", $result->cataliaslist);
			for($i=0; $i<count($catid_arr); $i++) {
				$itemcataliases[$catid_arr[$i]] = $catalias_arr[$i];
			}
			$rel_itemid = $result->id;
			$rel_catid = isset($values[$rel_itemid]->catid) ? $values[$rel_itemid]->catid : $result->catid;
			if ( isset($itemcataliases[$rel_catid]) && !in_array($rel_catid, $globalnoroute) && $globalcats[$rel_catid]->published) {
				$catslug = $rel_catid.":".$itemcataliases[$rel_catid];
			} else if (!in_array($result->catid, $globalnoroute) && $globalcats[$result->catid]->published ) {
				$catslug = $globalcats[$result->catid]->slug;
			} else {
				foreach ($catid_arr as $catid) {
					if ( !in_array($catid, $globalnoroute) && $globalcats[$catid]->published) {
						$catslug = $globalcats[$catid]->slug;
						break;
					}
				}
			}
			$result->slug = $itemslug;
			$result->categoryslug = $catslug;
		}
		
		foreach($custom_field_names as $i => $custom_field_name) {
			if ( !isset($related_items_fields[$custom_field_name]) ) {
				$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
				$start_microtime = microtime(true);
				FlexicontentFields::getFieldDisplay($item_list, $custom_field_name, $custom_field_values=null, $display_var);
				$fc_run_times['render_subfields'][$item->id."_".$field->id] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
			} else {
				//$custom_field_display = 'cannot replace field: "'.$custom_field_name.'" because it is of type "related_items", which can cause loop';
			}
		}
		
		$display = array();
		foreach($item_list as $result)
		{
			// Check if related item is published and skip if not published
			if ($result->state != 1 && $result->state != -5) continue;
			
			// a. Replace some custom made strings
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($result->slug, $result->categoryslug));
			$item_title_escaped = htmlspecialchars($result->title, ENT_COMPAT, 'UTF-8');
			$item_tooltip = ' class="hasTip relateditem" title="'. JText::_('FLEXI_READ_MORE_ABOUT').'::'.$item_title_escaped.'" ';
			
			$display_text = $displayway ? $result->title : $result->id;
			$display_text = !$addlink ? $display_text : '<a href="'.$item_url.'"'.($addtooltip ? $item_tooltip : '').' >' .$display_text. '</a>';
			
			$curr_relitem_html = $relitem_html;
			$curr_relitem_html = str_replace('__item_url__', $item_url, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_title_escaped__', $item_title_escaped, $curr_relitem_html);
			$curr_relitem_html = str_replace('__item_tooltip__', $item_tooltip, $curr_relitem_html);
			$curr_relitem_html = str_replace('__display_text__', $display_text, $curr_relitem_html);
			
			// b. Replace item properties, e.g. {item->id}, (item->title}, etc
			FlexicontentFields::doQueryReplacements($curr_relitem_html, $result);
			
			// c. Replace HTML display of various item fields
			foreach($custom_field_names as $i => $custom_field_name) {
				if ( !isset($related_items_fields[$custom_field_name]) ) {
					$display_var = $custom_field_methods[$i] ? $custom_field_methods[$i] : 'display';
					$custom_field_display = $result->fields[$custom_field_name]->{$display_var};
				} else {
					$custom_field_display = 'cannot replace field: "'.$custom_field_name.'" because it is of type "related_items", which can cause loop';
				}
				$custom_field_str = $custom_field_name . ($custom_field_methods[$i] ? "##".$custom_field_methods[$i] : "");
				$curr_relitem_html = str_replace('{{'.$custom_field_str.'}}', $custom_field_display, $curr_relitem_html);
			}
			$display[] = trim($pretext . $curr_relitem_html . $posttext);
		}
		
		$display = $opentag . implode($separatorf, $display) . $closetag;
		return $display;
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
		if($filter->field_type != 'relateditems_backlinks') return;
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